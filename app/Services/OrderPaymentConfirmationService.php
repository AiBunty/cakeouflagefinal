<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class OrderPaymentConfirmationService
{
    /** @var array<string,bool> */
    private static array $columnPresence = [];

    /**
     * @param array<string,mixed> $context
     * @return array{success:bool,message:string,http_status:int,data?:array<string,mixed>}
     */
    public function confirmOrderPayment(PDO $pdo, int $orderId, array $context = []): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Invalid order ID.', 'http_status' => 422];
        }

        $ownsTx = !$pdo->inTransaction();
        if ($ownsTx) {
            $pdo->beginTransaction();
        }

        try {
            $orderStmt = $pdo->prepare(
                'SELECT id, order_status, payment_status, payment_method, order_number, grand_total, COALESCE(refund_amount, 0) AS refund_amount
                 FROM orders WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $orderStmt->execute(['id' => $orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                if ($ownsTx) { $pdo->rollBack(); }
                return ['success' => false, 'message' => 'Order not found.', 'http_status' => 404];
            }

            $currentPaymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
            $paymentMethod = $this->normalizePaymentMethod((string)($context['payment_method'] ?? ($order['payment_method'] ?? 'upi_manual')));
            if (!in_array($paymentMethod, ['upi_manual', 'gateway', 'cod', 'credit'], true)) {
                if ($ownsTx) { $pdo->rollBack(); }
                return ['success' => false, 'message' => 'Invalid payment method for confirmation.', 'http_status' => 422];
            }

            $chargeableAmount = max(0.0, round((float)($order['grand_total'] ?? 0) - (float)($order['refund_amount'] ?? 0), 2));
            if ($chargeableAmount <= 0.0) {
                if ($ownsTx) { $pdo->rollBack(); }
                return ['success' => false, 'message' => 'Order chargeable amount must be greater than zero.', 'http_status' => 422];
            }

            $adminId = isset($context['admin_id']) ? (int)$context['admin_id'] : null;
            $adminName = trim((string)($context['admin_name'] ?? 'Admin'));
            $sourceReference = trim((string)($context['source_reference'] ?? 'OrderPaymentConfirmationService'));
            $sourceEvent = trim((string)($context['source_event'] ?? 'payment_confirmation'));
            $syncSnapshot = !array_key_exists('sync_snapshot', $context) || (bool)$context['sync_snapshot'];
            $skipOrderStatusTransition = (bool)($context['skip_order_status_transition'] ?? false);
            $enforceAccountingPost = !array_key_exists('enforce_accounting_post', $context)
                || (bool)$context['enforce_accounting_post'];

            if ($paymentMethod === 'credit') {
                if ($currentPaymentStatus === 'credit') {
                    if ($ownsTx) { $pdo->commit(); }
                    return [
                        'success' => true,
                        'message' => 'Order is already marked as credit.',
                        'http_status' => 200,
                        'data' => [
                            'payment_status' => 'credit',
                            'payment_method' => 'credit',
                            'recognized_amount' => $chargeableAmount,
                            'discount_amount' => 0.0,
                        ],
                    ];
                }

                $updateAssignments = [
                    'payment_status = "credit"',
                    'payment_method = "credit"',
                ];
                if (!$skipOrderStatusTransition) {
                    $updateAssignments[] = 'order_status = CASE WHEN order_status IN ("pending", "pending_payment", "payment_under_review") THEN "confirmed" ELSE order_status END';
                }
                $updateParams = ['id' => $orderId];
                if ($this->tableHasColumn($pdo, 'orders', 'payment_confirmed_at')) {
                    $updateAssignments[] = 'payment_confirmed_at = NOW()';
                }
                if ($this->tableHasColumn($pdo, 'orders', 'payment_confirmed_by_admin_id')) {
                    $updateAssignments[] = 'payment_confirmed_by_admin_id = :admin_id';
                    $updateParams['admin_id'] = $adminId;
                }

                $pdo->prepare('UPDATE orders SET ' . implode(', ', $updateAssignments) . ' WHERE id = :id')->execute($updateParams);

                $fteResult = (new FinancialTransactionEngine())->recordCreditSaleRecognized([
                    'order_id' => $orderId,
                    'order_number' => (string)($order['order_number'] ?? ''),
                    'amount' => $chargeableAmount,
                    'payment_status' => 'credit',
                    'source_reference' => $sourceReference,
                    'idempotency_key' => 'confirm-credit:' . $orderId . ':' . number_format($chargeableAmount, 2, '.', ''),
                    'admin_id' => $adminId,
                    'admin_name' => $adminName,
                    'narration' => 'Credit sale recognized via payment confirmation',
                ]);
                if (!$fteResult['success']) {
                    if ($enforceAccountingPost) {
                        if ($ownsTx && $pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        return [
                            'success' => false,
                            'message' => 'Credit confirmation aborted: accounting posting failed.',
                            'http_status' => 500,
                        ];
                    }
                    error_log('[OrderPaymentConfirmationService][fte-credit] ' . $fteResult['message']);
                }

                try {
                    $invoiceResult = (new InvoiceGenerationService())->ensureInvoiceForOrder($pdo, $orderId, [
                        'payment_status' => 'credit',
                        'payment_method' => 'credit',
                    ]);
                    if (!$invoiceResult['success']) {
                        error_log('[OrderPaymentConfirmationService][invoice-credit] ' . $invoiceResult['message']);
                    }
                } catch (\Throwable $invoiceErr) {
                    error_log('[OrderPaymentConfirmationService][invoice-credit] ' . $invoiceErr->getMessage());
                }

                if ($syncSnapshot) {
                    try {
                        (new OrderFinanceSnapshotService())->syncOrderFinancialColumns($pdo, $orderId);
                    } catch (\Throwable $syncErr) {
                        error_log('[OrderPaymentConfirmationService][snapshot-credit] ' . $syncErr->getMessage());
                    }
                }

                if ($ownsTx) {
                    $pdo->commit();
                }

                return [
                    'success' => true,
                    'message' => 'Order confirmed on credit.',
                    'http_status' => 200,
                    'data' => [
                        'payment_status' => 'credit',
                        'payment_method' => 'credit',
                        'recognized_amount' => $chargeableAmount,
                        'discount_amount' => 0.0,
                    ],
                ];
            }

            if ($currentPaymentStatus === 'paid') {
                if ($ownsTx) { $pdo->commit(); }
                return [
                    'success' => true,
                    'message' => 'Payment already confirmed.',
                    'http_status' => 200,
                    'data' => [
                        'payment_status' => 'paid',
                        'payment_method' => (string)($order['payment_method'] ?? $paymentMethod),
                        'recognized_amount' => $chargeableAmount,
                        'discount_amount' => 0.0,
                    ],
                ];
            }

            $rawReceived = $context['received_amount'] ?? null;
            $receivedAmount = $rawReceived === null ? $chargeableAmount : round((float)$rawReceived, 2);
            if ($receivedAmount <= 0) {
                if ($ownsTx) { $pdo->rollBack(); }
                return ['success' => false, 'message' => 'Received amount must be greater than zero.', 'http_status' => 422];
            }
            if ($receivedAmount - $chargeableAmount > 0.01) {
                if ($ownsTx) { $pdo->rollBack(); }
                return ['success' => false, 'message' => 'Received amount cannot exceed order payable amount.', 'http_status' => 422];
            }

            $discountAmount = max(0.0, round($chargeableAmount - $receivedAmount, 2));
            $discountRatio = $chargeableAmount > 0 ? ($discountAmount / $chargeableAmount) : 0.0;
            $discountReason = trim((string)($context['discount_reason'] ?? ''));
            $managerOverride = (bool)($context['manager_override'] ?? false);
            $hasDiscountOverridePermission = (bool)($context['has_discount_override_permission'] ?? false);

            if ($discountAmount > 0 && $discountReason === '') {
                if ($ownsTx) { $pdo->rollBack(); }
                return ['success' => false, 'message' => 'Shortfall discount requires a mandatory reason.', 'http_status' => 422];
            }
            if ($discountRatio > 0.05 && !($managerOverride && $hasDiscountOverridePermission)) {
                if ($ownsTx) { $pdo->rollBack(); }
                return ['success' => false, 'message' => 'Shortfall discount above 5% requires manager override.', 'http_status' => 422];
            }

            $paymentNoteSuffix = '';
            if ($discountAmount > 0) {
                $paymentNoteSuffix = "\n[Discount Applied] ₹" . number_format($discountAmount, 2, '.', '') . ' - ' . $discountReason;
            }

            $updateAssignments = [
                'payment_status = "paid"',
                'payment_method = :payment_method',
                'discount_total = ROUND(COALESCE(discount_total, 0) + :discount_amount, 2)',
                'grand_total = :final_grand_total',
                'admin_note = CONCAT(COALESCE(admin_note, ""), :payment_note_suffix)',
            ];
            if (!$skipOrderStatusTransition) {
                $updateAssignments[] = 'order_status = CASE WHEN order_status IN ("pending", "pending_payment", "payment_under_review") THEN "confirmed" ELSE order_status END';
            }
            $updateParams = [
                'id' => $orderId,
                'payment_method' => $paymentMethod,
                'discount_amount' => $discountAmount,
                'final_grand_total' => $receivedAmount,
                'payment_note_suffix' => $paymentNoteSuffix,
            ];
            if ($this->tableHasColumn($pdo, 'orders', 'payment_confirmed_at')) {
                $updateAssignments[] = 'payment_confirmed_at = NOW()';
            }
            if ($this->tableHasColumn($pdo, 'orders', 'payment_confirmed_by_admin_id')) {
                $updateAssignments[] = 'payment_confirmed_by_admin_id = :admin_id';
                $updateParams['admin_id'] = $adminId;
            }

            $pdo->prepare('UPDATE orders SET ' . implode(', ', $updateAssignments) . ' WHERE id = :id')->execute($updateParams);

            $accountingTxId = null;
            $postResult = (new AccountingPostingService())->postOrderPayment([
                'order_id' => $orderId,
                'order_number' => (string)($order['order_number'] ?? ''),
                'amount' => $receivedAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'previous_payment_status' => $currentPaymentStatus,
                'source_reference' => $sourceReference,
                'idempotency_key' => 'confirm-payment:' . $orderId . ':' . $paymentMethod . ':' . number_format($receivedAmount, 2, '.', ''),
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'narration' => $discountAmount > 0
                    ? ('Payment received with discount adjustment: ₹' . number_format($discountAmount, 2, '.', ''))
                    : 'Payment received via order confirmation',
            ]);
            if (!$postResult['success']) {
                if ($enforceAccountingPost) {
                    if ($ownsTx && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    return [
                        'success' => false,
                        'message' => 'Payment confirmation aborted: accounting posting failed.',
                        'http_status' => 500,
                    ];
                }
                error_log('[OrderPaymentConfirmationService][fte-paid] ' . $postResult['message']);
            } else {
                $accountingTxId = isset($postResult['transaction_id']) ? (int)$postResult['transaction_id'] : null;
            }

            try {
                $receiptResult = (new PaymentReceiptService($pdo))->issueAdvanceReceipt($orderId, [
                    'source_event' => $sourceEvent,
                    'source_reference' => $sourceReference . ':' . $orderId . ':' . $paymentMethod . ':' . number_format($receivedAmount, 2, '.', ''),
                    'amount' => $receivedAmount,
                    'balance_due' => 0,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'issued_by_admin_id' => $adminId,
                    'financial_transaction_id' => $accountingTxId,
                    'metadata' => [
                        'channel' => 'admin',
                        'trigger' => 'payment_confirmation',
                    ],
                ]);
                if (!$receiptResult['success'] && !in_array($receiptResult['message'], ['No advance amount available for receipt', 'Payment receipt schema is not ready'], true)) {
                    error_log('[OrderPaymentConfirmationService][receipt] ' . $receiptResult['message']);
                }
            } catch (\Throwable $receiptErr) {
                error_log('[OrderPaymentConfirmationService][receipt] ' . $receiptErr->getMessage());
            }

            try {
                $invoiceResult = (new InvoiceGenerationService())->ensureInvoiceForOrder($pdo, $orderId, [
                    'payment_status' => 'paid',
                    'payment_method' => $paymentMethod,
                ]);
                if (!$invoiceResult['success']) {
                    error_log('[OrderPaymentConfirmationService][invoice-paid] ' . $invoiceResult['message']);
                }
            } catch (\Throwable $invoiceErr) {
                error_log('[OrderPaymentConfirmationService][invoice-paid] ' . $invoiceErr->getMessage());
            }

            if ($syncSnapshot) {
                try {
                    (new OrderFinanceSnapshotService())->syncOrderFinancialColumns($pdo, $orderId);
                } catch (\Throwable $syncErr) {
                    error_log('[OrderPaymentConfirmationService][snapshot-paid] ' . $syncErr->getMessage());
                }
            }

            if ($ownsTx) {
                $pdo->commit();
            }

            return [
                'success' => true,
                'message' => $discountAmount > 0
                    ? ('Payment confirmed with discount adjustment of ₹' . number_format($discountAmount, 2, '.', '') . '.')
                    : 'Payment confirmed.',
                'http_status' => 200,
                'data' => [
                    'payment_status' => 'paid',
                    'payment_method' => $paymentMethod,
                    'recognized_amount' => $receivedAmount,
                    'discount_amount' => $discountAmount,
                    'discount_reason' => $discountReason,
                    'manager_override' => $managerOverride,
                ],
            ];
        } catch (\Throwable $e) {
            if ($ownsTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Payment confirmation failed: ' . $e->getMessage(),
                'http_status' => 500,
            ];
        }
    }

    private function normalizePaymentMethod(string $paymentMethod): string
    {
        $normalized = strtolower(trim($paymentMethod));
        if ($normalized === 'cash') {
            return 'cod';
        }
        if (in_array($normalized, ['upi', 'bank', 'bank_transfer', 'upi_bank'], true)) {
            return 'upi_manual';
        }
        return $normalized;
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnPresence)) {
            return self::$columnPresence[$key];
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => $table, 'column' => $column]);
        self::$columnPresence[$key] = ((int)$stmt->fetchColumn()) > 0;

        return self::$columnPresence[$key];
    }
}
