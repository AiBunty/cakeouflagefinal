<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * PaymentSplitService
 *
 * Records one or more partial payments against an order, creating a
 * payment_transactions row for each method and posting the matching GL
 * entry via FinancialTransactionEngine.
 *
 * Rules:
 *  - Sum of payments must not exceed order balance_due (within ₹1 tolerance).
 *  - Each payment is atomic: all-or-nothing via DB transaction.
 *  - Idempotency key is auto-derived from order_id + method + amount if not supplied.
 */
final class PaymentSplitService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Record one or more payment methods against an order.
     *
     * @param  int    $orderId
     * @param  list<array{method:string,amount:float,reference?:string,idempotency_key?:string}> $payments
     * @param  int    $adminId
     * @param  array<string,mixed> $context  admin_name, source_channel, business_date, etc.
     * @return array{success:bool,message:string,payment_ids?:list<int>,gl_transaction_ids?:list<int>}
     */
    public function recordSplit(int $orderId, array $payments, int $adminId, array $context = []): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Invalid order ID'];
        }
        if (empty($payments)) {
            return ['success' => false, 'message' => 'No payments provided'];
        }

        // Load order
        $order = $this->db->fetchOne(
            'SELECT id, order_number, customer_name, grand_total,
                    COALESCE(revised_grand_total, grand_total) AS effective_total,
                    payment_status, order_status, created_at
             FROM orders WHERE id = :id LIMIT 1',
            ['id' => $orderId]
        );
        if ($order === null) {
            return ['success' => false, 'message' => 'Order not found: ' . $orderId];
        }
        if (in_array((string)$order['order_status'], ['cancelled', 'rejected', 'fully_refunded'], true)) {
            return ['success' => false, 'message' => 'Cannot record payment for order in status: ' . $order['order_status']];
        }
        if ($this->isAccountingLocked((string)($order['created_at'] ?? ''))) {
            return ['success' => false, 'message' => 'Order is locked for accounting period and cannot accept split payment'];
        }

        $effectiveTotal = round((float)$order['effective_total'], 2);

        // Compute already-verified total (from payment_transactions table, verified status)
        $alreadyPaid = (float)($this->db->fetchScalar(
            "SELECT COALESCE(SUM(amount), 0)
               FROM payment_transactions
              WHERE order_id = :oid AND status = 'verified'",
            ['oid' => $orderId]
        ) ?? 0.0);

        $balanceDue = round($effectiveTotal - $alreadyPaid, 2);

        $arOutstanding = (float)($this->db->fetchScalar(
            "SELECT COALESCE(SUM(gle.debit_amount - gle.credit_amount), 0)
               FROM general_ledger_entries gle
               INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
              WHERE ft.reference_type = 'order'
                AND ft.reference_id = :oid
                AND gle.account_code = 'ACCOUNTS_RECEIVABLE'",
            ['oid' => $orderId]
        ) ?? 0.0);
        $hasReceivableToSettle = $arOutstanding > 0.01;

        // Validate payment totals
        $paymentSum = round(array_sum(array_column($payments, 'amount')), 2);
        if ($paymentSum <= 0) {
            return ['success' => false, 'message' => 'Payment total must be greater than zero'];
        }
        if ($paymentSum > $balanceDue + 1.0) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Payment sum ₹%.2f exceeds balance due ₹%.2f for order #%s',
                    $paymentSum, $balanceDue, $order['order_number']
                ),
            ];
        }

        $adminName     = (string)($context['admin_name']     ?? '');
        $sourceChannel = (string)($context['source_channel'] ?? 'admin');
        $businessDate  = (string)($context['business_date']  ?? date('Y-m-d'));

        $fte = new FinancialTransactionEngine($this->db);

        $this->db->beginTransaction();
        try {
            $paymentIds    = [];
            $glTxIds       = [];
            $lineNumber    = 1;

            foreach ($payments as $payment) {
                $method    = strtolower(trim((string)($payment['method']    ?? '')));
                $amount    = round((float)($payment['amount']              ?? 0), 2);
                $reference = (string)($payment['reference']                ?? '');
                $ikey      = (string)($payment['idempotency_key']          ?? '');

                if ($amount <= 0) {
                    continue; // skip zero rows
                }

                $allowedMethods = ['cash', 'upi', 'bank_transfer', 'pos_card', 'payment_link', 'store_credit'];
                if (!in_array($method, $allowedMethods, true)) {
                    $this->db->rollback();
                    return ['success' => false, 'message' => 'Unknown payment method: ' . $method];
                }

                // Insert payment_transactions row (pending — GL post will verify)
                $ptId = $this->db->insert(
                    'INSERT INTO payment_transactions
                        (order_id, payment_method, amount, reference_code, status,
                         created_by_admin_id, created_at, updated_at)
                     VALUES
                        (:order_id, :method, :amount, :reference, \'pending\',
                         :admin_id, NOW(), NOW())',
                    [
                        'order_id'  => $orderId,
                        'method'    => $method,
                        'amount'    => $amount,
                        'reference' => $reference,
                        'admin_id'  => $adminId,
                    ]
                );

                // Derive idempotency key if not provided
                if ($ikey === '') {
                    $ikey = 'split:' . $orderId . ':' . $method . ':' . round($amount, 2) . ':' . $lineNumber;
                }

                // Route collections against existing receivable to settlement journal,
                // otherwise post as normal payment receipt.
                if ($hasReceivableToSettle) {
                    $glResult = $fte->recordBalanceSettled([
                        'order_id'       => $orderId,
                        'order_number'   => (string)$order['order_number'],
                        'amount'         => $amount,
                        'payment_method' => $method,
                        'admin_id'       => $adminId,
                        'admin_name'     => $adminName,
                        'idempotency_key'=> $ikey,
                        'source_channel' => $sourceChannel,
                        'business_date'  => $businessDate,
                        'narration'      => sprintf('Settlement (%s) ₹%.2f for order #%s', $method, $amount, $order['order_number']),
                    ]);
                } else {
                    $glResult = $fte->recordPaymentReceived([
                        'order_id'       => $orderId,
                        'order_number'   => (string)$order['order_number'],
                        'amount'         => $amount,
                        'payment_method' => $method,
                        'admin_id'       => $adminId,
                        'admin_name'     => $adminName,
                        'idempotency_key'=> $ikey,
                        'source_channel' => $sourceChannel,
                        'business_date'  => $businessDate,
                        'narration'      => sprintf('Payment (%s) ₹%.2f for order #%s', $method, $amount, $order['order_number']),
                    ]);
                }

                if (!$glResult['success']) {
                    $this->db->rollback();
                    return [
                        'success' => false,
                        'message' => 'GL posting failed for ' . $method . ': ' . $glResult['message'],
                    ];
                }

                // Stamp GL transaction ID on payment row and mark verified
                $this->db->execute(
                    "UPDATE payment_transactions
                        SET status = 'verified', gl_transaction_id = :gl_id,
                            verified_by_admin_id = :admin_id, updated_at = NOW()
                      WHERE id = :pt_id",
                    [
                        'gl_id'    => $glResult['transaction_id'],
                        'admin_id' => $adminId,
                        'pt_id'    => $ptId,
                    ]
                );

                $paymentIds[] = $ptId;
                $glTxIds[]    = (int)$glResult['transaction_id'];
                $lineNumber++;
            }

            $this->db->commit();

            return [
                'success'            => true,
                'message'            => count($paymentIds) . ' payment(s) recorded for order #' . $order['order_number'],
                'payment_ids'        => $paymentIds,
                'gl_transaction_ids' => $glTxIds,
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[PaymentSplitService] recordSplit error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment recording failed: ' . $e->getMessage()];
        }
    }

    private function isAccountingLocked(string $orderCreatedAt): bool
    {
        if ($orderCreatedAt === '') {
            return false;
        }

        $lockDays = $this->getAccountingLockDays();
        if ($lockDays <= 0) {
            return false;
        }

        $createdTs = strtotime($orderCreatedAt);
        if ($createdTs === false) {
            return false;
        }

        $lockTs = strtotime('+' . $lockDays . ' days', $createdTs);
        return $lockTs !== false && time() > $lockTs;
    }

    private function getAccountingLockDays(): int
    {
        $row = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'accounting_lock_days' LIMIT 1"
        );
        $value = (int)($row['setting_value'] ?? 30);
        return $value > 0 ? $value : 30;
    }
}
