<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class FinancialTransactionEngine
{
    private const TX_TYPE_PAYMENT_RECEIVED       = 'payment_received';
    private const TX_TYPE_REFUND_PROCESSED        = 'refund_processed';
    private const TX_TYPE_ADVANCE_COLLECTED       = 'advance_collected';
    private const TX_TYPE_CREDIT_SALE_RECOGNIZED  = 'credit_sale_recognized';
    private const TX_TYPE_BALANCE_SETTLED         = 'balance_settled';
    private const TX_TYPE_COUPON_DISCOUNT         = 'coupon_discount';
    private const TX_TYPE_BAD_DEBT_WRITEOFF       = 'bad_debt_writeoff';

    private const ACCOUNT_CASH            = 'CASH_ON_HAND';
    private const ACCOUNT_BANK            = 'BANK_CLEARING';
    private const ACCOUNT_AR              = 'ACCOUNTS_RECEIVABLE';
    private const ACCOUNT_ADVANCES        = 'CUSTOMER_ADVANCES';
    private const ACCOUNT_SALES_REVENUE   = 'SALES_REVENUE';
    private const ACCOUNT_SALES_REFUNDS   = 'SALES_REFUNDS';
    private const ACCOUNT_DISCOUNT        = 'DISCOUNT_EXPENSE';
    private const ACCOUNT_BAD_DEBT              = 'BAD_DEBT_EXPENSE';
    private const ACCOUNT_DISCOUNT_CONTRA        = 'SALES_DISCOUNT_CONTRA';
    private const ACCOUNT_ADJUSTMENT_REVENUE     = 'SALES_ADJUSTMENT_REVENUE';
    private const ACCOUNT_ADJUSTMENT_EXPENSE     = 'SALES_ADJUSTMENT_EXPENSE';
    private const ACCOUNT_CREDIT_WALLET          = 'CUSTOMER_CREDIT_WALLET';

    private const TX_TYPE_ADVANCE_FULFILLED      = 'advance_fulfilled';
    private const TX_TYPE_REVERSAL               = 'reversal';
    private const TX_TYPE_ORDER_UPGRADE          = 'order_upgrade';
    private const TX_TYPE_ORDER_DOWNGRADE_REFUND = 'order_downgrade_refund';
    private const TX_TYPE_STORE_CREDIT_ISSUED    = 'store_credit_issued';
    private const TX_TYPE_STORE_CREDIT_REDEEMED  = 'store_credit_redeemed';

    private const STATUS_POSTED = 'posted';

    private Database $db;
    private static ?bool $schemaReady = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array<string, mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordPaymentReceived(array $context): array
    {
        $orderId = (int)($context['order_id'] ?? 0);
        $amount = round((float)($context['amount'] ?? 0), 2);
        $paymentMethod = strtolower(trim((string)($context['payment_method'] ?? '')));
        $adminId = (int)($context['admin_id'] ?? 0);
        $adminName = trim((string)($context['admin_name'] ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid payment event payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        $debitAccount = $this->resolveReceiptAccount($paymentMethod);

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_PAYMENT_RECEIVED,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'source_event' => 'order_payment_confirmed',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId)),
            'idempotency_key' => $idempotencyKey,
            'payment_mode' => $paymentMethod,
            'narration' => trim((string)($context['narration'] ?? ('Payment received for order #' . $orderId))),
            'admin_id' => $adminId > 0 ? $adminId : null,
            'admin_name' => $adminName,
            'metadata' => [
                'order_number' => (string)($context['order_number'] ?? ''),
                'payment_status' => (string)($context['payment_status'] ?? 'paid'),
            ],
            'entries' => [
                [
                    'account_code' => $debitAccount,
                    'debit_amount' => $amount,
                    'credit_amount' => 0.0,
                    'line_narration' => 'Payment channel receipt',
                ],
                [
                    'account_code' => self::ACCOUNT_SALES_REVENUE,
                    'debit_amount' => 0.0,
                    'credit_amount' => $amount,
                    'line_narration' => 'Revenue recognition on payment confirmation',
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordRefundProcessed(array $context): array
    {
        $orderId = (int)($context['order_id'] ?? 0);
        $refundTransactionId = (int)($context['refund_transaction_id'] ?? 0);
        $amount = round((float)($context['amount'] ?? 0), 2);
        $paymentMethod = strtolower(trim((string)($context['payment_method'] ?? '')));
        $adminId = (int)($context['admin_id'] ?? 0);
        $adminName = trim((string)($context['admin_name'] ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $refundTransactionId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid refund event payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        $creditAccount = $this->resolveRefundCreditAccount($paymentMethod);

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_REFUND_PROCESSED,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'source_event' => 'refund_processed',
            'source_reference' => (string)($context['source_reference'] ?? ('refund:' . $refundTransactionId)),
            'idempotency_key' => $idempotencyKey,
            'payment_mode' => $paymentMethod,
            'narration' => trim((string)($context['narration'] ?? ('Refund processed for order #' . $orderId))),
            'admin_id' => $adminId > 0 ? $adminId : null,
            'admin_name' => $adminName,
            'metadata' => [
                'refund_transaction_id' => $refundTransactionId,
                'order_id' => $orderId,
                'order_number' => (string)($context['order_number'] ?? ''),
                'refund_type' => (string)($context['refund_type'] ?? ''),
            ],
            'entries' => [
                [
                    'account_code' => self::ACCOUNT_SALES_REFUNDS,
                    'debit_amount' => $amount,
                    'credit_amount' => 0.0,
                    'line_narration' => 'Contra revenue refund booking',
                ],
                [
                    'account_code' => $creditAccount,
                    'debit_amount' => 0.0,
                    'credit_amount' => $amount,
                    'line_narration' => $creditAccount === self::ACCOUNT_AR
                        ? 'Accounts receivable reversal'
                        : 'Refund payout channel',
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordAdvanceCollected(array $context): array
    {
        $orderId = (int)($context['order_id'] ?? 0);
        $amount = round((float)($context['amount'] ?? 0), 2);
        $paymentMethod = strtolower(trim((string)($context['payment_method'] ?? '')));
        $adminId = (int)($context['admin_id'] ?? 0);
        $adminName = trim((string)($context['admin_name'] ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid advance event payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        $debitAccount = $this->resolveReceiptAccount($paymentMethod);

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_ADVANCE_COLLECTED,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'source_event' => 'order_advance_collected',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':advance')),
            'idempotency_key' => $idempotencyKey,
            'payment_mode' => $paymentMethod,
            'narration' => trim((string)($context['narration'] ?? ('Advance collected for order #' . $orderId))),
            'admin_id' => $adminId > 0 ? $adminId : null,
            'admin_name' => $adminName,
            'metadata' => [
                'order_number' => (string)($context['order_number'] ?? ''),
                'payment_status' => (string)($context['payment_status'] ?? 'partial'),
            ],
            'entries' => [
                [
                    'account_code' => $debitAccount,
                    'debit_amount' => $amount,
                    'credit_amount' => 0.0,
                    'line_narration' => 'Advance payment receipt',
                ],
                [
                    'account_code' => self::ACCOUNT_ADVANCES,
                    'debit_amount' => 0.0,
                    'credit_amount' => $amount,
                    'line_narration' => 'Customer advance liability',
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordCreditSaleRecognized(array $context): array
    {
        $orderId = (int)($context['order_id'] ?? 0);
        $amount = round((float)($context['amount'] ?? 0), 2);
        $adminId = (int)($context['admin_id'] ?? 0);
        $adminName = trim((string)($context['admin_name'] ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid credit sale payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_CREDIT_SALE_RECOGNIZED,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'source_event' => 'order_credit_confirmed',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':credit')),
            'idempotency_key' => $idempotencyKey,
            'payment_mode' => 'credit',
            'narration' => trim((string)($context['narration'] ?? ('Credit sale recognized for order #' . $orderId))),
            'admin_id' => $adminId > 0 ? $adminId : null,
            'admin_name' => $adminName,
            'metadata' => [
                'order_number' => (string)($context['order_number'] ?? ''),
                'payment_status' => (string)($context['payment_status'] ?? 'credit'),
            ],
            'entries' => [
                [
                    'account_code' => self::ACCOUNT_AR,
                    'debit_amount' => $amount,
                    'credit_amount' => 0.0,
                    'line_narration' => 'Accounts receivable recognized',
                ],
                [
                    'account_code' => self::ACCOUNT_SALES_REVENUE,
                    'debit_amount' => 0.0,
                    'credit_amount' => $amount,
                    'line_narration' => 'Revenue recognized on credit confirmation',
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordBalanceSettled(array $context): array
    {
        $orderId = (int)($context['order_id'] ?? 0);
        $amount = round((float)($context['amount'] ?? 0), 2);
        $paymentMethod = strtolower(trim((string)($context['payment_method'] ?? '')));
        $adminId = (int)($context['admin_id'] ?? 0);
        $adminName = trim((string)($context['admin_name'] ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid settlement event payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        $debitAccount = $this->resolveReceiptAccount($paymentMethod);

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_BALANCE_SETTLED,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'source_event' => 'order_balance_settled',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':settled')),
            'idempotency_key' => $idempotencyKey,
            'payment_mode' => $paymentMethod,
            'narration' => trim((string)($context['narration'] ?? ('Balance settled for order #' . $orderId))),
            'admin_id' => $adminId > 0 ? $adminId : null,
            'admin_name' => $adminName,
            'metadata' => [
                'order_number' => (string)($context['order_number'] ?? ''),
            ],
            'entries' => [
                [
                    'account_code' => $debitAccount,
                    'debit_amount' => $amount,
                    'credit_amount' => 0.0,
                    'line_narration' => 'Balance collection receipt',
                ],
                [
                    'account_code' => self::ACCOUNT_AR,
                    'debit_amount' => 0.0,
                    'credit_amount' => $amount,
                    'line_narration' => 'Accounts receivable settlement',
                ],
            ],
        ]);
    }

    /**
     * Record a coupon / promotional discount applied at order time.
     *
     * Gross-up method: SALES_REVENUE remains at net cash received. This entry
     * debits DISCOUNT_EXPENSE (P&L promotion cost) and credits
     * SALES_DISCOUNT_CONTRA (contra-revenue account), grossing up SALES_REVENUE
     * for reporting accuracy.
     * Net revenue in reports = SALES_REVENUE − SALES_DISCOUNT_CONTRA.
     *
     * @param array<string, mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordCouponDiscount(array $context): array
    {
        $orderId        = (int)($context['order_id']        ?? 0);
        $amount         = round((float)($context['amount']  ?? 0), 2);
        $adminId        = (int)($context['admin_id']        ?? 0);
        $adminName      = trim((string)($context['admin_name']  ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid coupon discount payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_COUPON_DISCOUNT,
            'reference_type'   => 'order',
            'reference_id'     => $orderId,
            'source_event'     => 'order_coupon_applied',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':coupon')),
            'idempotency_key'  => $idempotencyKey,
            'payment_mode'     => 'coupon',
            'narration'        => trim((string)($context['narration'] ?? ('Coupon discount for order #' . $orderId))),
            'admin_id'         => $adminId > 0 ? $adminId : null,
            'admin_name'       => $adminName,
            'metadata'         => [
                'order_number' => (string)($context['order_number'] ?? ''),
                'coupon_code'  => (string)($context['coupon_code']  ?? ''),
            ],
            'entries' => [
                [
                    'account_code'  => self::ACCOUNT_DISCOUNT,
                    'debit_amount'  => $amount,
                    'credit_amount' => 0.0,
                    'line_narration' => 'Coupon discount expense',
                ],
                [
                    'account_code'  => self::ACCOUNT_DISCOUNT_CONTRA,
                    'debit_amount'  => 0.0,
                    'credit_amount' => $amount,
                    'line_narration' => 'Contra-revenue for coupon discount',
                ],
            ],
        ]);
    }

    /**
     * Write off an uncollectable receivable balance as bad debt.
     *
     * Journal: Debit BAD_DEBT_EXPENSE / Credit ACCOUNTS_RECEIVABLE
     * Closes the AR for an order whose outstanding balance is deemed irrecoverable.
     *
     * @param array<string, mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordBadDebtWriteOff(array $context): array
    {
        $orderId        = (int)($context['order_id']        ?? 0);
        $amount         = round((float)($context['amount']  ?? 0), 2);
        $adminId        = (int)($context['admin_id']        ?? 0);
        $adminName      = trim((string)($context['admin_name']  ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid bad debt write-off payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_BAD_DEBT_WRITEOFF,
            'reference_type'   => 'order',
            'reference_id'     => $orderId,
            'source_event'     => 'bad_debt_written_off',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':writeoff')),
            'idempotency_key'  => $idempotencyKey,
            'payment_mode'     => 'writeoff',
            'narration'        => trim((string)($context['narration'] ?? ('Bad debt write-off for order #' . $orderId))),
            'admin_id'         => $adminId > 0 ? $adminId : null,
            'admin_name'       => $adminName,
            'metadata'         => [
                'order_number' => (string)($context['order_number'] ?? ''),
                'reason'       => (string)($context['reason']       ?? ''),
            ],
            'entries' => [
                [
                    'account_code'  => self::ACCOUNT_BAD_DEBT,
                    'debit_amount'  => $amount,
                    'credit_amount' => 0.0,
                    'line_narration' => 'Bad debt expense recognised',
                ],
                [
                    'account_code'  => self::ACCOUNT_AR,
                    'debit_amount'  => 0.0,
                    'credit_amount' => $amount,
                    'line_narration' => 'Accounts receivable written off',
                ],
            ],
        ]);
    }

    /**
     * Close an advance order: transfers the liability to revenue when delivered.
     *
     * Journal: Debit CUSTOMER_ADVANCES / Credit SALES_REVENUE
     *
     * @param array<string, mixed> $context
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordAdvanceFulfilled(array $context): array
    {
        $orderId        = (int)($context['order_id']        ?? 0);
        $amount         = round((float)($context['amount']  ?? 0), 2);
        $adminId        = (int)($context['admin_id']        ?? 0);
        $adminName      = trim((string)($context['admin_name']  ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid advance fulfilment payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_ADVANCE_FULFILLED,
            'reference_type'   => 'order',
            'reference_id'     => $orderId,
            'source_event'     => 'order_advance_fulfilled',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':fulfilled')),
            'idempotency_key'  => $idempotencyKey,
            'payment_mode'     => 'advance',
            'narration'        => trim((string)($context['narration'] ?? ('Advance fulfilled for order #' . $orderId))),
            'admin_id'         => $adminId > 0 ? $adminId : null,
            'admin_name'       => $adminName,
            'metadata'         => [
                'order_number' => (string)($context['order_number'] ?? ''),
            ],
            'entries' => [
                [
                    'account_code'   => self::ACCOUNT_ADVANCES,
                    'debit_amount'   => $amount,
                    'credit_amount'  => 0.0,
                    'line_narration' => 'Customer advance liability cleared',
                ],
                [
                    'account_code'   => self::ACCOUNT_SALES_REVENUE,
                    'debit_amount'   => 0.0,
                    'credit_amount'  => $amount,
                    'line_narration' => 'Revenue recognised on advance fulfilment',
                ],
            ],
        ]);
    }

    /**
     * Reverse a previously posted transaction (mirror all debit/credit entries).
     * Marks the original transaction status as 'reversed'.
     *
     * @param array<string, mixed> $context  Must contain: original_transaction_id, idempotency_key
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordReversal(array $context): array
    {
        $originalId     = (int)($context['original_transaction_id'] ?? 0);
        $adminId        = (int)($context['admin_id']  ?? 0);
        $adminName      = trim((string)($context['admin_name']  ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($originalId <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid reversal payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        $original = $this->db->fetchOne(
            'SELECT * FROM financial_transactions WHERE id = :id LIMIT 1',
            ['id' => $originalId]
        );
        if ($original === null) {
            return ['success' => false, 'posted' => false, 'message' => 'Original transaction not found: ' . $originalId];
        }
        if ((string)($original['status'] ?? '') === 'reversed') {
            return ['success' => false, 'posted' => false, 'message' => 'Transaction #' . $originalId . ' is already reversed'];
        }

        $originalEntries = $this->db->fetchAll(
            'SELECT * FROM general_ledger_entries WHERE financial_transaction_id = :id ORDER BY line_number',
            ['id' => $originalId]
        );
        if (empty($originalEntries)) {
            return ['success' => false, 'posted' => false, 'message' => 'No GL lines found for transaction #' . $originalId];
        }

        $mirrorEntries = [];
        foreach ($originalEntries as $line) {
            $mirrorEntries[] = [
                'account_code'   => (string)$line['account_code'],
                'debit_amount'   => round((float)$line['credit_amount'], 2),
                'credit_amount'  => round((float)$line['debit_amount'], 2),
                'line_narration' => 'Reversal: ' . (string)($line['narration'] ?? ''),
            ];
        }

        $result = $this->postTransaction([
            'transaction_type'           => self::TX_TYPE_REVERSAL,
            'reference_type'             => (string)($original['reference_type'] ?? 'order'),
            'reference_id'               => (int)($original['reference_id'] ?? 0),
            'source_event'               => 'transaction_reversed',
            'source_reference'           => 'reversal:' . $originalId,
            'idempotency_key'            => $idempotencyKey,
            'payment_mode'               => 'reversal',
            'narration'                  => 'Reversal of transaction #' . $originalId,
            'admin_id'                   => $adminId > 0 ? $adminId : null,
            'admin_name'                 => $adminName,
            'is_reversal'                => 1,
            'reversal_of_transaction_id' => $originalId,
            'entry_type'                 => 'reversal',
            'metadata'                   => [
                'original_transaction_id' => $originalId,
                'reason'                  => (string)($context['reason'] ?? ''),
            ],
            'entries' => $mirrorEntries,
        ]);

        if ($result['success'] && $result['posted']) {
            $this->db->execute(
                "UPDATE financial_transactions SET status = 'reversed' WHERE id = :id",
                ['id' => $originalId]
            );
        }

        return $result;
    }

    /**
     * Record revenue from an order upgrade (customer agrees to a higher total).
     *
     * Journal: Debit ACCOUNTS_RECEIVABLE / Credit SALES_ADJUSTMENT_REVENUE
     *
     * @param array<string, mixed> $context  Must contain: order_id, revision_id, amount, idempotency_key
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordOrderUpgrade(array $context): array
    {
        $orderId    = (int)($context['order_id']    ?? 0);
        $revisionId = (int)($context['revision_id'] ?? 0);
        $amount     = round((float)($context['amount'] ?? 0), 2);
        $adminId    = (int)($context['admin_id']    ?? 0);
        $adminName  = trim((string)($context['admin_name']  ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid order upgrade payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_ORDER_UPGRADE,
            'reference_type'   => 'order',
            'reference_id'     => $orderId,
            'source_event'     => 'order_upgraded',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':upgrade:' . $revisionId)),
            'idempotency_key'  => $idempotencyKey,
            'payment_mode'     => 'upgrade',
            'narration'        => trim((string)($context['narration'] ?? ('Order upgrade for order #' . $orderId))),
            'admin_id'         => $adminId > 0 ? $adminId : null,
            'admin_name'       => $adminName,
            'entry_type'       => 'adjustment',
            'metadata'         => [
                'order_number' => (string)($context['order_number'] ?? ''),
                'revision_id'  => $revisionId,
            ],
            'entries' => [
                [
                    'account_code'   => self::ACCOUNT_AR,
                    'debit_amount'   => $amount,
                    'credit_amount'  => 0.0,
                    'line_narration' => 'Receivable for order upgrade',
                ],
                [
                    'account_code'   => self::ACCOUNT_ADJUSTMENT_REVENUE,
                    'debit_amount'   => 0.0,
                    'credit_amount'  => $amount,
                    'line_narration' => 'Adjustment revenue — order upgrade',
                ],
            ],
        ]);
    }

    /**
     * Record expense for an order downgrade settled via cash or bank refund.
     *
     * Journal: Debit SALES_ADJUSTMENT_EXPENSE / Credit CASH_ON_HAND or BANK_CLEARING
     *
     * @param array<string, mixed> $context  Must contain: order_id, revision_id, amount, payment_mode, idempotency_key
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordOrderDowngradeRefund(array $context): array
    {
        $orderId       = (int)($context['order_id']    ?? 0);
        $revisionId    = (int)($context['revision_id'] ?? 0);
        $amount        = round((float)($context['amount'] ?? 0), 2);
        $paymentMethod = strtolower(trim((string)($context['payment_mode'] ?? '')));
        $adminId       = (int)($context['admin_id']    ?? 0);
        $adminName     = trim((string)($context['admin_name']  ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid order downgrade refund payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        $creditAccount = $this->resolveReceiptAccount($paymentMethod);

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_ORDER_DOWNGRADE_REFUND,
            'reference_type'   => 'order',
            'reference_id'     => $orderId,
            'source_event'     => 'order_downgraded_refund',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':downgrade:' . $revisionId)),
            'idempotency_key'  => $idempotencyKey,
            'payment_mode'     => $paymentMethod,
            'narration'        => trim((string)($context['narration'] ?? ('Downgrade refund for order #' . $orderId))),
            'admin_id'         => $adminId > 0 ? $adminId : null,
            'admin_name'       => $adminName,
            'entry_type'       => 'adjustment',
            'metadata'         => [
                'order_number' => (string)($context['order_number'] ?? ''),
                'revision_id'  => $revisionId,
            ],
            'entries' => [
                [
                    'account_code'   => self::ACCOUNT_ADJUSTMENT_EXPENSE,
                    'debit_amount'   => $amount,
                    'credit_amount'  => 0.0,
                    'line_narration' => 'Adjustment expense — order downgrade',
                ],
                [
                    'account_code'   => $creditAccount,
                    'debit_amount'   => 0.0,
                    'credit_amount'  => $amount,
                    'line_narration' => 'Cash/bank refund to customer on downgrade',
                ],
            ],
        ]);
    }

    /**
     * Issue store credit to a customer when an order is downgraded.
     *
     * Journal: Debit SALES_ADJUSTMENT_EXPENSE / Credit CUSTOMER_CREDIT_WALLET
     *
     * @param array<string, mixed> $context  Must contain: order_id, revision_id, amount, idempotency_key
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordStoreCreditIssued(array $context): array
    {
        $orderId        = (int)($context['order_id']    ?? 0);
        $revisionId     = (int)($context['revision_id'] ?? 0);
        $amount         = round((float)($context['amount'] ?? 0), 2);
        $adminId        = (int)($context['admin_id']    ?? 0);
        $adminName      = trim((string)($context['admin_name']  ?? ''));
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid store credit issuance payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_STORE_CREDIT_ISSUED,
            'reference_type'   => 'order',
            'reference_id'     => $orderId,
            'source_event'     => 'store_credit_issued',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':credit_issued:' . $revisionId)),
            'idempotency_key'  => $idempotencyKey,
            'payment_mode'     => 'store_credit',
            'narration'        => trim((string)($context['narration'] ?? ('Store credit issued for order #' . $orderId))),
            'admin_id'         => $adminId > 0 ? $adminId : null,
            'admin_name'       => $adminName,
            'entry_type'       => 'adjustment',
            'metadata'         => [
                'order_number'    => (string)($context['order_number']    ?? ''),
                'customer_phone'  => (string)($context['customer_phone']  ?? ''),
                'revision_id'     => $revisionId,
            ],
            'entries' => [
                [
                    'account_code'   => self::ACCOUNT_ADJUSTMENT_EXPENSE,
                    'debit_amount'   => $amount,
                    'credit_amount'  => 0.0,
                    'line_narration' => 'Adjustment expense — store credit issued',
                ],
                [
                    'account_code'   => self::ACCOUNT_CREDIT_WALLET,
                    'debit_amount'   => 0.0,
                    'credit_amount'  => $amount,
                    'line_narration' => 'Customer credit wallet liability',
                ],
            ],
        ]);
    }

    /**
     * Redeem store credit when a customer applies it to a future order.
     *
     * Journal: Debit CUSTOMER_CREDIT_WALLET / Credit ACCOUNTS_RECEIVABLE
     *
     * @param array<string, mixed> $context  Must contain: order_id, customer_phone, amount, idempotency_key
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    public function recordStoreCreditRedeemed(array $context): array
    {
        $orderId         = (int)($context['order_id']    ?? 0);
        $amount          = round((float)($context['amount'] ?? 0), 2);
        $customerPhone   = trim((string)($context['customer_phone'] ?? ''));
        $adminId         = (int)($context['admin_id']    ?? 0);
        $adminName       = trim((string)($context['admin_name']  ?? ''));
        $idempotencyKey  = trim((string)($context['idempotency_key'] ?? ''));

        if ($orderId <= 0 || $amount <= 0 || $idempotencyKey === '') {
            return ['success' => false, 'posted' => false, 'message' => 'Invalid store credit redemption payload'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'posted' => false, 'message' => 'Financial engine schema not ready'];
        }

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_STORE_CREDIT_REDEEMED,
            'reference_type'   => 'order',
            'reference_id'     => $orderId,
            'source_event'     => 'store_credit_redeemed',
            'source_reference' => (string)($context['source_reference'] ?? ('order:' . $orderId . ':credit_redeemed')),
            'idempotency_key'  => $idempotencyKey,
            'payment_mode'     => 'store_credit',
            'narration'        => trim((string)($context['narration'] ?? ('Store credit redeemed on order #' . $orderId))),
            'admin_id'         => $adminId > 0 ? $adminId : null,
            'admin_name'       => $adminName,
            'entry_type'       => 'adjustment',
            'metadata'         => [
                'order_number'    => (string)($context['order_number']    ?? ''),
                'customer_phone'  => $customerPhone,
            ],
            'entries' => [
                [
                    'account_code'   => self::ACCOUNT_CREDIT_WALLET,
                    'debit_amount'   => $amount,
                    'credit_amount'  => 0.0,
                    'line_narration' => 'Customer credit wallet redeemed',
                ],
                [
                    'account_code'   => self::ACCOUNT_AR,
                    'debit_amount'   => 0.0,
                    'credit_amount'  => $amount,
                    'line_narration' => 'Receivable reduced by store credit',
                ],
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{success:bool,posted:bool,transaction_id?:int,batch_id?:int,message:string}
     */
    private function postTransaction(array $payload): array
    {
        $entries = $payload['entries'] ?? [];
        if (!is_array($entries) || count($entries) < 2) {
            return ['success' => false, 'posted' => false, 'message' => 'At least two ledger lines are required'];
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                return ['success' => false, 'posted' => false, 'message' => 'Invalid ledger line payload'];
            }
            $totalDebit += round((float)($entry['debit_amount'] ?? 0), 2);
            $totalCredit += round((float)($entry['credit_amount'] ?? 0), 2);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return ['success' => false, 'posted' => false, 'message' => 'Unbalanced journal entry'];
        }

        // Period-lock check: reject postings for a closed accounting day
        $businessDate = (string)($payload['business_date'] ?? date('Y-m-d'));
        $lockedPeriod = $this->db->fetchScalar(
            'SELECT id FROM accounting_close_log WHERE close_date = :d AND is_locked = 1 LIMIT 1',
            ['d' => $businessDate]
        );
        if ($lockedPeriod !== null) {
            return [
                'success' => false,
                'posted'  => false,
                'message' => 'Accounting period locked for ' . $businessDate,
            ];
        }

        $pdo = Database::getConnection();
        $startedLocalTransaction = !$pdo->inTransaction();
        if ($startedLocalTransaction) {
            $pdo->beginTransaction();
        }

        $lockName = 'fte:' . substr(hash('sha256', (string)$payload['idempotency_key']), 0, 48);
        $lockAcquired = false;
        try {
            $lockAcquired = $this->acquireIdempotencyLock($pdo, $lockName);
            if (!$lockAcquired) {
                if ($startedLocalTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'posted' => false, 'message' => 'Could not acquire posting lock'];
            }

            $existingId = $this->db->fetchScalar(
                'SELECT id FROM financial_transactions WHERE idempotency_key = :k LIMIT 1',
                ['k' => (string)$payload['idempotency_key']]
            );
            if ($existingId !== null) {
                if ($startedLocalTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return ['success' => true, 'posted' => false, 'transaction_id' => (int)$existingId, 'message' => 'Duplicate event ignored'];
            }

            $resolvedEntryType = (string)($payload['entry_type'] ?? $this->resolveEntryType((string)$payload['transaction_type']));
            $transactionId = $this->db->insert(
                'INSERT INTO financial_transactions
                    (transaction_type, reference_type, reference_id, source_event, source_reference,
                     idempotency_key, payment_mode, amount, status, narration, metadata_json,
                     business_date, source_channel, is_reversal, reversal_of_transaction_id,
                     review_required, created_by_admin_id, created_by_name, created_at)
                 VALUES
                    (:transaction_type, :reference_type, :reference_id, :source_event, :source_reference,
                     :idempotency_key, :payment_mode, :amount, :status, :narration, :metadata_json,
                     :business_date, :source_channel, :is_reversal, :reversal_of_transaction_id,
                     :review_required, :created_by_admin_id, :created_by_name, NOW())',
                [
                    'transaction_type'           => (string)$payload['transaction_type'],
                    'reference_type'             => (string)$payload['reference_type'],
                    'reference_id'               => (int)$payload['reference_id'],
                    'source_event'               => (string)$payload['source_event'],
                    'source_reference'           => (string)$payload['source_reference'],
                    'idempotency_key'            => (string)$payload['idempotency_key'],
                    'payment_mode'               => (string)$payload['payment_mode'],
                    'amount'                     => round($totalDebit, 2),
                    'status'                     => self::STATUS_POSTED,
                    'narration'                  => (string)$payload['narration'],
                    'metadata_json'              => json_encode((array)($payload['metadata'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'business_date'              => $businessDate,
                    'source_channel'             => $payload['source_channel'] ?? null,
                    'is_reversal'                => (int)(bool)($payload['is_reversal'] ?? false),
                    'reversal_of_transaction_id' => isset($payload['reversal_of_transaction_id']) ? (int)$payload['reversal_of_transaction_id'] : null,
                    'review_required'            => (int)(bool)($payload['review_required'] ?? false),
                    'created_by_admin_id'        => $payload['admin_id'] ?? null,
                    'created_by_name'            => (string)($payload['admin_name'] ?? ''),
                ]
            );

            $batchNumber = 'FTX-' . date('Ymd') . '-' . str_pad((string)$transactionId, 8, '0', STR_PAD_LEFT);
            $batchId = $this->db->insert(
                'INSERT INTO transaction_batches
                    (financial_transaction_id, batch_number, source_module, source_reference, debit_total, credit_total, status, posted_at, created_at)
                 VALUES
                    (:financial_transaction_id, :batch_number, :source_module, :source_reference, :debit_total, :credit_total, :status, NOW(), NOW())',
                [
                    'financial_transaction_id' => $transactionId,
                    'batch_number' => $batchNumber,
                    'source_module' => 'financial_transaction_engine',
                    'source_reference' => (string)$payload['source_reference'],
                    'debit_total' => round($totalDebit, 2),
                    'credit_total' => round($totalCredit, 2),
                    'status' => self::STATUS_POSTED,
                ]
            );

            $lineNumber = 1;
            foreach ($entries as $entry) {
                $accountCode  = (string)($entry['account_code'] ?? '');
                $accountName  = (string)$this->db->fetchScalar('SELECT account_name FROM ledger_accounts WHERE account_code = :code LIMIT 1', ['code' => $accountCode]);
                if ($accountName === '') {
                    throw new \RuntimeException('Unknown account code: ' . $accountCode);
                }

                $lineEntryType = (string)($entry['entry_type'] ?? $resolvedEntryType);
                $gleId = $this->db->insert(
                    'INSERT INTO general_ledger_entries
                        (batch_id, financial_transaction_id, line_number, account_code, account_name,
                         debit_amount, credit_amount, payment_mode, narration, entry_type,
                         reference_type, reference_id, created_by_admin_id, created_by_name, created_at)
                     VALUES
                        (:batch_id, :financial_transaction_id, :line_number, :account_code, :account_name,
                         :debit_amount, :credit_amount, :payment_mode, :narration, :entry_type,
                         :reference_type, :reference_id, :created_by_admin_id, :created_by_name, NOW())',
                    [
                        'batch_id'                 => $batchId,
                        'financial_transaction_id' => $transactionId,
                        'line_number'              => $lineNumber,
                        'account_code'             => $accountCode,
                        'account_name'             => $accountName,
                        'debit_amount'             => round((float)($entry['debit_amount']  ?? 0), 2),
                        'credit_amount'            => round((float)($entry['credit_amount'] ?? 0), 2),
                        'payment_mode'             => (string)$payload['payment_mode'],
                        'narration'                => (string)($entry['line_narration'] ?? $payload['narration']),
                        'entry_type'               => $lineEntryType,
                        'reference_type'           => (string)$payload['reference_type'],
                        'reference_id'             => (int)$payload['reference_id'],
                        'created_by_admin_id'      => $payload['admin_id'] ?? null,
                        'created_by_name'          => (string)($payload['admin_name'] ?? ''),
                    ]
                );

                // Stamp running balance (algebraic: positive = debit-heavy)
                $runningBalance = round((float)($this->db->fetchScalar(
                    'SELECT COALESCE(SUM(debit_amount) - SUM(credit_amount), 0) FROM general_ledger_entries WHERE account_code = :code',
                    ['code' => $accountCode]
                ) ?? 0.0), 2);
                $this->db->execute(
                    'UPDATE general_ledger_entries SET running_balance_after = :bal WHERE id = :id',
                    ['bal' => $runningBalance, 'id' => $gleId]
                );

                $lineNumber++;
            }

            $this->db->insert(
                'INSERT INTO financial_audit_logs
                    (financial_transaction_id, batch_id, event_type, actor_admin_id, actor_name, source_module, source_reference, metadata_json, created_at)
                 VALUES
                    (:financial_transaction_id, :batch_id, :event_type, :actor_admin_id, :actor_name, :source_module, :source_reference, :metadata_json, NOW())',
                [
                    'financial_transaction_id' => $transactionId,
                    'batch_id' => $batchId,
                    'event_type' => 'posted',
                    'actor_admin_id' => $payload['admin_id'] ?? null,
                    'actor_name' => (string)($payload['admin_name'] ?? ''),
                    'source_module' => 'financial_transaction_engine',
                    'source_reference' => (string)$payload['source_reference'],
                    'metadata_json' => json_encode([
                        'transaction_type' => (string)$payload['transaction_type'],
                        'debit_total' => round($totalDebit, 2),
                        'credit_total' => round($totalCredit, 2),
                        'idempotency_key' => (string)$payload['idempotency_key'],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]
            );

            if ($startedLocalTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            // Stamp the source order with the GL posting timestamp to prevent double-posting
            if ((string)($payload['reference_type'] ?? '') === 'order') {
                $this->db->execute(
                    'UPDATE orders SET gl_posted_at = NOW(), gl_transaction_id = :txId WHERE id = :orderId AND gl_posted_at IS NULL',
                    ['txId' => $transactionId, 'orderId' => (int)$payload['reference_id']]
                );
            }

            return [
                'success'        => true,
                'posted'         => true,
                'transaction_id' => $transactionId,
                'batch_id'       => $batchId,
                'message'        => 'Financial transaction posted',
            ];
        } catch (\Throwable $e) {
            if ($startedLocalTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[FinancialTransactionEngine] ' . $e->getMessage());
            return ['success' => false, 'posted' => false, 'message' => 'Posting failed: ' . $e->getMessage()];
        } finally {
            if ($lockAcquired) {
                $this->releaseIdempotencyLock($pdo, $lockName);
            }
        }
    }

    private function isSchemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        try {
            $requiredTables = [
                'ledger_accounts',
                'financial_transactions',
                'transaction_batches',
                'general_ledger_entries',
                'financial_audit_logs',
            ];
            foreach ($requiredTables as $table) {
                $exists = $this->db->fetchScalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name', ['table_name' => $table]);
                if ((int)$exists <= 0) {
                    self::$schemaReady = false;
                    return false;
                }
            }
            self::$schemaReady = true;
            return true;
        } catch (\Throwable $e) {
            error_log('[FinancialTransactionEngine] schema check failed: ' . $e->getMessage());
            self::$schemaReady = false;
            return false;
        }
    }

    private function resolveReceiptAccount(string $paymentMethod): string
    {
        return in_array($paymentMethod, ['upi_manual', 'upi', 'gateway', 'bank_transfer', 'pos_card', 'payment_link'], true)
            ? self::ACCOUNT_BANK
            : self::ACCOUNT_CASH;
    }

    private function resolveRefundCreditAccount(string $paymentMethod): string
    {
        if ($paymentMethod === 'credit') {
            return self::ACCOUNT_AR;
        }

        return in_array($paymentMethod, ['upi_manual', 'upi', 'gateway', 'bank_transfer', 'pos_card', 'payment_link'], true)
            ? self::ACCOUNT_BANK
            : self::ACCOUNT_CASH;
    }

    private function acquireIdempotencyLock(PDO $pdo, string $lockName): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 5)');
            $stmt->execute(['lock_name' => $lockName]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            error_log('[FinancialTransactionEngine] acquire lock failed: ' . $e->getMessage());
            return false;
        }
    }

    private function releaseIdempotencyLock(PDO $pdo, string $lockName): void
    {
        try {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $stmt->execute(['lock_name' => $lockName]);
        } catch (\Throwable $e) {
            error_log('[FinancialTransactionEngine] release lock failed: ' . $e->getMessage());
        }
    }

    /**
     * Map transaction_type to a canonical GL entry_type string.
     * Used to auto-populate entry_type when callers don't supply it explicitly.
     */
    private function resolveEntryType(string $transactionType): string
    {
        return match ($transactionType) {
            self::TX_TYPE_PAYMENT_RECEIVED,
            self::TX_TYPE_ADVANCE_FULFILLED          => 'sale',
            self::TX_TYPE_ADVANCE_COLLECTED          => 'advance',
            self::TX_TYPE_CREDIT_SALE_RECOGNIZED     => 'receivable',
            self::TX_TYPE_REFUND_PROCESSED           => 'refund',
            self::TX_TYPE_BALANCE_SETTLED            => 'settlement',
            self::TX_TYPE_COUPON_DISCOUNT            => 'discount',
            self::TX_TYPE_BAD_DEBT_WRITEOFF          => 'writeoff',
            self::TX_TYPE_REVERSAL                   => 'reversal',
            self::TX_TYPE_ORDER_UPGRADE,
            self::TX_TYPE_ORDER_DOWNGRADE_REFUND,
            self::TX_TYPE_STORE_CREDIT_ISSUED,
            self::TX_TYPE_STORE_CREDIT_REDEEMED      => 'adjustment',
            default                                  => 'other',
        };
    }
}
