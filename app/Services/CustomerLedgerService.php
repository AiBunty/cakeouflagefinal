<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * CustomerLedgerService
 *
 * Builds a customer account statement by joining orders, financial_transactions,
 * and general_ledger_entries into a chronological event timeline.
 */
final class CustomerLedgerService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Return a full account statement for a customer.
     *
     * @param string $identifier  Phone number, email, or integer ID (as string)
     * @param string $by          'phone' | 'email' | 'id'
     * @return array{
     *   customer: array{name:string,phone:string,email:string}|null,
     *   orders: list<array<string,mixed>>,
     *   events: list<array<string,mixed>>,
     *   summary: array{total_orders:int,total_billed:float,total_paid:float,outstanding:float}
     * }
     */
    public function getStatement(string $identifier, string $by = 'phone'): array
    {
        $field = match ($by) {
            'email' => 'customer_email',
            'id'    => 'id',
            default => 'customer_phone',
        };

        $orders = $this->db->fetchAll(
            "SELECT id, order_number, customer_name, customer_phone, customer_email,
                    created_at, order_status, payment_status, payment_method,
                    grand_total,
                    COALESCE(revised_grand_total, grand_total) AS effective_total,
                    COALESCE(advance_amount, 0)                AS advance_amount,
                    is_revised, current_revision_no,
                    gl_posted_at, gl_transaction_id
             FROM orders
             WHERE {$field} = :identifier
             ORDER BY created_at",
            ['identifier' => $identifier]
        );

        if (empty($orders)) {
            return [
                'customer' => null,
                'orders'   => [],
                'events'   => [],
                'summary'  => [
                    'total_orders' => 0,
                    'total_billed' => 0.0,
                    'total_paid'   => 0.0,
                    'outstanding'  => 0.0,
                ],
            ];
        }

        $customer = [
            'name'  => (string)$orders[0]['customer_name'],
            'phone' => (string)$orders[0]['customer_phone'],
            'email' => (string)($orders[0]['customer_email'] ?? ''),
        ];

        $orderIds     = array_map(static fn($o) => (int)$o['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        // Fetch all GL transactions for these orders in posting order
        $txRows = $this->db->fetchAll(
            "SELECT
                ft.id                    AS tx_id,
                ft.transaction_type,
                ft.business_date,
                ft.payment_mode,
                ft.amount,
                ft.narration             AS tx_narration,
                ft.source_channel,
                ft.is_reversal,
                ft.reversal_of_transaction_id,
                ft.reference_id          AS order_id,
                gle.id                   AS gle_id,
                gle.line_number,
                gle.account_code,
                gle.account_name,
                gle.debit_amount,
                gle.credit_amount,
                gle.narration            AS line_narration,
                gle.entry_type,
                gle.running_balance_after
             FROM financial_transactions ft
             INNER JOIN general_ledger_entries gle ON gle.financial_transaction_id = ft.id
             WHERE ft.reference_type = 'order'
               AND ft.reference_id IN ({$placeholders})
               AND ft.status        = 'posted'
             ORDER BY ft.business_date, ft.id, gle.line_number",
            $orderIds
        );

        // Group GL lines by transaction
        $txGroups = [];
        foreach ($txRows as $row) {
            $txId = (int)$row['tx_id'];
            if (!isset($txGroups[$txId])) {
                $txGroups[$txId] = [
                    'tx_id'                      => $txId,
                    'transaction_type'            => (string)$row['transaction_type'],
                    'business_date'               => (string)$row['business_date'],
                    'amount'                      => (float)$row['amount'],
                    'narration'                   => (string)$row['tx_narration'],
                    'source_channel'              => (string)($row['source_channel'] ?? ''),
                    'is_reversal'                 => (bool)$row['is_reversal'],
                    'reversal_of_transaction_id'  => $row['reversal_of_transaction_id'] ? (int)$row['reversal_of_transaction_id'] : null,
                    'order_id'                    => (int)$row['order_id'],
                    'lines'                       => [],
                ];
            }
            $txGroups[$txId]['lines'][] = [
                'account_code'     => (string)$row['account_code'],
                'account_name'     => (string)$row['account_name'],
                'debit_amount'     => round((float)$row['debit_amount'],  2),
                'credit_amount'    => round((float)$row['credit_amount'], 2),
                'narration'        => (string)$row['line_narration'],
                'entry_type'       => (string)($row['entry_type'] ?? ''),
                'running_balance'  => round((float)($row['running_balance_after'] ?? 0), 2),
            ];
        }

        // Build event timeline
        $events = [];
        foreach ($orders as $order) {
            $events[] = [
                'event_type'   => 'order_placed',
                'event_date'   => (string)$order['created_at'],
                'order_id'     => (int)$order['id'],
                'order_number' => (string)$order['order_number'],
                'description'  => 'Order placed — ' . $order['order_number'],
                'amount'       => round((float)$order['effective_total'], 2),
                'direction'    => 'debit',
                'gl_lines'     => [],
            ];
        }

        foreach ($txGroups as $tx) {
            $events[] = [
                'event_type'   => $tx['transaction_type'],
                'event_date'   => $tx['business_date'],
                'order_id'     => $tx['order_id'],
                'order_number' => null,
                'description'  => $tx['narration'],
                'amount'       => round($tx['amount'], 2),
                'direction'    => $this->resolveDirection($tx['transaction_type']),
                'source_channel'=> $tx['source_channel'],
                'is_reversal'  => $tx['is_reversal'],
                'gl_lines'     => $tx['lines'],
            ];
        }

        // Sort by event_date ascending
        usort($events, static fn($a, $b) => strcmp((string)$a['event_date'], (string)$b['event_date']));

        // Compute summary totals
        $totalBilled = 0.0;
        foreach ($orders as $order) {
            $totalBilled += (float)$order['effective_total'];
        }

        $paidResult = $this->db->fetchScalar(
            "SELECT COALESCE(SUM(gle.debit_amount - gle.credit_amount), 0)
               FROM general_ledger_entries gle
               INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
              WHERE gle.account_code IN ('CASH_ON_HAND', 'BANK_CLEARING')
                AND ft.reference_type = 'order'
                AND ft.reference_id IN ({$placeholders})
                AND ft.status = 'posted'",
            $orderIds
        );
        $totalPaid = round((float)($paidResult ?? 0), 2);

        $receivableNet = (float)($this->db->fetchScalar(
            "SELECT COALESCE(SUM(gle.debit_amount - gle.credit_amount), 0)
               FROM general_ledger_entries gle
               INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
              WHERE gle.account_code = 'ACCOUNTS_RECEIVABLE'
                AND ft.reference_type = 'order'
                AND ft.reference_id IN ({$placeholders})
                AND ft.status = 'posted'",
            $orderIds
        ) ?? 0.0);

        return [
            'customer' => $customer,
            'orders'   => $orders,
            'events'   => $events,
            'summary'  => [
                'total_orders' => count($orders),
                'total_billed' => round($totalBilled, 2),
                'total_paid'   => $totalPaid,
                'outstanding'  => round(max($receivableNet, 0), 2),
            ],
        ];
    }

    private function resolveDirection(string $transactionType): string
    {
        return match ($transactionType) {
            'payment_received', 'advance_collected', 'balance_settled',
            'store_credit_redeemed', 'advance_fulfilled'              => 'credit',
            'refund_processed', 'bad_debt_writeoff',
            'order_downgrade_refund', 'store_credit_issued'           => 'debit',
            default                                                   => 'neutral',
        };
    }
}
