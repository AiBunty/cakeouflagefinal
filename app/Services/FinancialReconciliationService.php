<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class FinancialReconciliationService
{
    private Database $db;
    /** @var array<string,bool> */
    private array $tableExistsCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeRange(string $fromDate, string $toDate): array
    {
        [$from, $to] = $this->normalizeDateRange($fromDate, $toDate);

        $orders = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(CASE
                    WHEN o.order_status IN ("cancelled", "rejected", "refunded", "fully_refunded")
                        OR o.payment_status IN ("refunded") THEN 0
                    WHEN o.payment_method = "cod" THEN GREATEST(
                        LEAST(CASE WHEN o.payment_status IN ("paid", "partially_refunded", "refunded") THEN o.grand_total ELSE COALESCE(o.advance_amount, 0) END, o.grand_total)
                        - COALESCE(r.refunded_total, 0),
                        0
                    )
                    ELSE 0
                END), 0) AS cash_total,
                COALESCE(SUM(CASE
                    WHEN o.order_status IN ("cancelled", "rejected", "refunded", "fully_refunded")
                        OR o.payment_status IN ("refunded") THEN 0
                    WHEN o.payment_method IN ("upi_manual", "gateway") THEN GREATEST(
                        LEAST(CASE WHEN o.payment_status IN ("paid", "partially_refunded", "refunded") THEN o.grand_total ELSE COALESCE(o.advance_amount, 0) END, o.grand_total)
                        - COALESCE(r.refunded_total, 0),
                        0
                    )
                    ELSE 0
                END), 0) AS bank_total,
                COALESCE(SUM(CASE
                    WHEN o.order_status IN ("cancelled", "rejected", "refunded", "fully_refunded")
                        OR o.payment_status IN ("refunded") THEN 0
                    ELSE GREATEST(
                        LEAST(CASE WHEN o.payment_status IN ("paid", "partially_refunded", "refunded") THEN o.grand_total ELSE COALESCE(o.advance_amount, 0) END, o.grand_total)
                        - COALESCE(r.refunded_total, 0),
                        0
                    )
                END), 0) AS realized_total,
                COALESCE(SUM(CASE
                    WHEN o.payment_status IN ("refunded", "partially_refunded") OR o.order_status IN ("cancelled", "rejected", "fully_refunded", "partially_refunded") THEN 0
                    ELSE GREATEST(
                        o.grand_total - LEAST(CASE WHEN o.payment_status IN ("paid", "partially_refunded", "refunded") THEN o.grand_total ELSE COALESCE(o.advance_amount, 0) END, o.grand_total),
                        0
                    )
                END), 0) AS outstanding_total,
                COALESCE(SUM(COALESCE(r.refunded_total, 0)), 0) AS refunded_total,
                COALESCE(SUM(CASE WHEN o.payment_status IN ("paid", "partially_refunded", "refunded") THEN 1 ELSE 0 END), 0) AS paid_orders,
                COALESCE(SUM(CASE WHEN COALESCE(r.refunded_total, 0) > 0 THEN 1 ELSE 0 END), 0) AS refunded_orders
             FROM orders o
             LEFT JOIN (
                SELECT order_id, COALESCE(SUM(CASE WHEN status = "processed" THEN COALESCE(approved_amount, requested_amount, 0) ELSE 0 END), 0) AS refunded_total
                FROM refund_transactions
                GROUP BY order_id
             ) r ON r.order_id = o.id
             WHERE DATE(COALESCE(o.payment_confirmed_at, o.created_at)) BETWEEN :from_date AND :to_date',
            [
                'from_date' => $from,
                'to_date' => $to,
            ]
        ) ?: [];

        $ledger = [];
        if ($this->tableExists('general_ledger_entries')) {
            $ledger = $this->db->fetchOne(
                'SELECT
                    COALESCE(SUM(CASE WHEN account_code = "CASH_ON_HAND" THEN debit_amount - credit_amount ELSE 0 END), 0) AS cash_total,
                    COALESCE(SUM(CASE WHEN account_code = "BANK_CLEARING" THEN debit_amount - credit_amount ELSE 0 END), 0) AS bank_total,
                    COALESCE(SUM(CASE WHEN account_code = "SALES_REFUNDS" THEN debit_amount - credit_amount ELSE 0 END), 0) AS refunded_total,
                    COALESCE(SUM(CASE WHEN account_code = "SALES_REVENUE" THEN credit_amount - debit_amount ELSE 0 END), 0) AS sales_revenue,
                    COALESCE(SUM(CASE WHEN account_code = "SALES_REFUNDS" THEN debit_amount - credit_amount ELSE 0 END), 0) AS refund_contra
                 FROM general_ledger_entries
                 WHERE DATE(created_at) BETWEEN :from_date AND :to_date',
                [
                    'from_date' => $from,
                    'to_date' => $to,
                ]
            ) ?: [];
        }

        $refunds = [];
        if ($this->tableExists('refund_transactions')) {
            $refunds = $this->db->fetchOne(
                'SELECT
                    COALESCE(SUM(CASE WHEN status = "pending_approval" THEN 1 ELSE 0 END), 0) AS pending_count,
                    COALESCE(SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END), 0) AS processed_count,
                    COALESCE(SUM(CASE WHEN status = "processed" THEN COALESCE(approved_amount, requested_amount, 0) ELSE 0 END), 0) AS processed_amount,
                    COALESCE(SUM(CASE WHEN status IN ("pending_approval", "approved") THEN COALESCE(requested_amount, 0) ELSE 0 END), 0) AS pending_amount
                 FROM refund_transactions
                 WHERE DATE(COALESCE(processed_at, requested_at, created_at)) BETWEEN :from_date AND :to_date',
                [
                    'from_date' => $from,
                    'to_date' => $to,
                ]
            ) ?: [];
        }

        $invoice = [];
        if ($this->tableExists('invoices')) {
            $invoice = $this->db->fetchOne(
                'SELECT
                    COUNT(*) AS total_invoices,
                    COALESCE(SUM(CASE WHEN invoice_status = "paid" THEN 1 ELSE 0 END), 0) AS paid_invoices,
                    COALESCE(SUM(CASE WHEN invoice_status IN ("pending_payment", "payment_under_verification", "unpaid_rejected") THEN 1 ELSE 0 END), 0) AS unpaid_invoices,
                    COALESCE(SUM(CASE WHEN invoice_status = "overdue" THEN 1 ELSE 0 END), 0) AS overdue_invoices,
                    COALESCE(SUM(CASE WHEN invoice_status = "part_paid" THEN 1 ELSE 0 END), 0) AS part_paid_invoices,
                    COALESCE(SUM(CASE WHEN customer_type = "retail" THEN balance_due ELSE 0 END), 0) AS retail_receivables,
                    COALESCE(SUM(CASE WHEN customer_type = "b2b" THEN balance_due ELSE 0 END), 0) AS b2b_receivables,
                    COALESCE(SUM(balance_due), 0) AS total_receivables
                 FROM invoices
                 WHERE DATE(COALESCE(issued_on, created_at)) BETWEEN :from_date AND :to_date',
                [
                    'from_date' => $from,
                    'to_date' => $to,
                ]
            ) ?: [];
        }

        $orderCash = round((float)($orders['cash_total'] ?? 0), 2);
        $orderBank = round((float)($orders['bank_total'] ?? 0), 2);
        $orderRefund = round((float)($orders['refunded_total'] ?? 0), 2);
        $orderNet = round((float)($orders['realized_total'] ?? 0), 2);

        $ledgerCash = round((float)($ledger['cash_total'] ?? 0), 2);
        $ledgerBank = round((float)($ledger['bank_total'] ?? 0), 2);
        $ledgerRefund = round((float)($ledger['refunded_total'] ?? 0), 2);
        $ledgerRevenue = round((float)($ledger['sales_revenue'] ?? 0), 2);
        $ledgerContra = round((float)($ledger['refund_contra'] ?? 0), 2);
        $ledgerNet = round($ledgerRevenue - $ledgerContra, 2);

        $cashVariance = round($orderCash - $ledgerCash, 2);
        $bankVariance = round($orderBank - $ledgerBank, 2);
        $refundVariance = round($orderRefund - $ledgerRefund, 2);
        $netVariance = round($orderNet - $ledgerNet, 2);

        $varianceAbs = abs($cashVariance) + abs($bankVariance) + abs($refundVariance) + abs($netVariance);
        $status = $this->resolveStatus($varianceAbs);

        return [
            'window' => [
                'from_date' => $from,
                'to_date' => $to,
            ],
            'orders' => [
                'cash_total' => $orderCash,
                'bank_total' => $orderBank,
                'realized_total' => $orderNet,
                'outstanding_total' => round((float)($orders['outstanding_total'] ?? 0), 2),
                'refunded_total' => $orderRefund,
                'paid_orders' => (int)($orders['paid_orders'] ?? 0),
                'refunded_orders' => (int)($orders['refunded_orders'] ?? 0),
            ],
            'ledger' => [
                'cash_total' => $ledgerCash,
                'bank_total' => $ledgerBank,
                'overall_total' => round($ledgerCash + $ledgerBank, 2),
                'refunded_total' => $ledgerRefund,
                'sales_revenue' => $ledgerRevenue,
                'net_revenue' => $ledgerNet,
            ],
            'refunds' => [
                'pending_count' => (int)($refunds['pending_count'] ?? 0),
                'processed_count' => (int)($refunds['processed_count'] ?? 0),
                'processed_amount' => round((float)($refunds['processed_amount'] ?? 0), 2),
                'pending_amount' => round((float)($refunds['pending_amount'] ?? 0), 2),
            ],
            'invoices' => [
                'total_invoices' => (int)($invoice['total_invoices'] ?? 0),
                'paid_invoices' => (int)($invoice['paid_invoices'] ?? 0),
                'unpaid_invoices' => (int)($invoice['unpaid_invoices'] ?? 0),
                'overdue_invoices' => (int)($invoice['overdue_invoices'] ?? 0),
                'part_paid_invoices' => (int)($invoice['part_paid_invoices'] ?? 0),
                'retail_receivables' => round((float)($invoice['retail_receivables'] ?? 0), 2),
                'b2b_receivables' => round((float)($invoice['b2b_receivables'] ?? 0), 2),
                'total_receivables' => round((float)($invoice['total_receivables'] ?? 0), 2),
            ],
            'variance' => [
                'cash' => $cashVariance,
                'bank' => $bankVariance,
                'refund' => $refundVariance,
                'net' => $netVariance,
                'absolute_sum' => round($varianceAbs, 2),
                'component_status' => [
                    'cash' => $this->resolveStatus(abs($cashVariance)),
                    'bank' => $this->resolveStatus(abs($bankVariance)),
                    'refund' => $this->resolveStatus(abs($refundVariance)),
                    'net' => $this->resolveStatus(abs($netVariance)),
                ],
            ],
            'source_tables' => [
                'orders' => true,
                'general_ledger_entries' => $this->tableExists('general_ledger_entries'),
                'refund_transactions' => $this->tableExists('refund_transactions'),
                'invoices' => $this->tableExists('invoices'),
            ],
            'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeToday(): array
    {
        $today = date('Y-m-d');
        return $this->summarizeRange($today, $today);
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeCurrentMonth(): array
    {
        $from = date('Y-m-01');
        $to = date('Y-m-d');
        return $this->summarizeRange($from, $to);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalizeDateRange(string $fromDate, string $toDate): array
    {
        $from = $this->isValidDate($fromDate) ? $fromDate : date('Y-m-01');
        $to = $this->isValidDate($toDate) ? $toDate : date('Y-m-d');

        if ($from > $to) {
            return [$to, $from];
        }

        return [$from, $to];
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function resolveStatus(float $absoluteVariance): string
    {
        if ($absoluteVariance <= 1.0) {
            return 'ok';
        }
        if ($absoluteVariance <= 25.0) {
            return 'warn';
        }
        return 'attention';
    }

    private function tableExists(string $tableName): bool
    {
        if (array_key_exists($tableName, $this->tableExistsCache)) {
            return $this->tableExistsCache[$tableName];
        }

        $count = $this->db->fetchScalar(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name',
            ['table_name' => $tableName]
        );

        $exists = ((int)$count) > 0;
        $this->tableExistsCache[$tableName] = $exists;
        return $exists;
    }
}