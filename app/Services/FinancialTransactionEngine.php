<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class FinancialTransactionEngine
{
    private const TX_TYPE_PAYMENT_RECEIVED = 'payment_received';
    private const TX_TYPE_REFUND_PROCESSED = 'refund_processed';
    private const TX_TYPE_ADVANCE_COLLECTED = 'advance_collected';
    private const TX_TYPE_CREDIT_SALE_RECOGNIZED = 'credit_sale_recognized';
    private const TX_TYPE_BALANCE_SETTLED = 'balance_settled';

    private const ACCOUNT_CASH = 'CASH_ON_HAND';
    private const ACCOUNT_BANK = 'BANK_CLEARING';
    private const ACCOUNT_AR = 'ACCOUNTS_RECEIVABLE';
    private const ACCOUNT_ADVANCES = 'CUSTOMER_ADVANCES';
    private const ACCOUNT_SALES_REVENUE = 'SALES_REVENUE';
    private const ACCOUNT_SALES_REFUNDS = 'SALES_REFUNDS';

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

        $debitAccount = in_array($paymentMethod, ['upi_manual', 'gateway'], true) ? self::ACCOUNT_BANK : self::ACCOUNT_CASH;

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

        $creditAccount = in_array($paymentMethod, ['upi_manual', 'gateway'], true) ? self::ACCOUNT_BANK : self::ACCOUNT_CASH;

        return $this->postTransaction([
            'transaction_type' => self::TX_TYPE_REFUND_PROCESSED,
            'reference_type' => 'refund_transaction',
            'reference_id' => $refundTransactionId,
            'source_event' => 'refund_processed',
            'source_reference' => (string)($context['source_reference'] ?? ('refund:' . $refundTransactionId)),
            'idempotency_key' => $idempotencyKey,
            'payment_mode' => $paymentMethod,
            'narration' => trim((string)($context['narration'] ?? ('Refund processed for order #' . $orderId))),
            'admin_id' => $adminId > 0 ? $adminId : null,
            'admin_name' => $adminName,
            'metadata' => [
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
                    'line_narration' => 'Refund payout channel',
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

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $existingId = $this->db->fetchScalar(
                'SELECT id FROM financial_transactions WHERE idempotency_key = :k LIMIT 1',
                ['k' => (string)$payload['idempotency_key']]
            );
            if ($existingId !== null) {
                $pdo->rollBack();
                return ['success' => true, 'posted' => false, 'transaction_id' => (int)$existingId, 'message' => 'Duplicate event ignored'];
            }

            $transactionId = $this->db->insert(
                'INSERT INTO financial_transactions
                    (transaction_type, reference_type, reference_id, source_event, source_reference, idempotency_key, payment_mode, amount, status, narration, metadata_json, created_by_admin_id, created_by_name, created_at)
                 VALUES
                    (:transaction_type, :reference_type, :reference_id, :source_event, :source_reference, :idempotency_key, :payment_mode, :amount, :status, :narration, :metadata_json, :created_by_admin_id, :created_by_name, NOW())',
                [
                    'transaction_type' => (string)$payload['transaction_type'],
                    'reference_type' => (string)$payload['reference_type'],
                    'reference_id' => (int)$payload['reference_id'],
                    'source_event' => (string)$payload['source_event'],
                    'source_reference' => (string)$payload['source_reference'],
                    'idempotency_key' => (string)$payload['idempotency_key'],
                    'payment_mode' => (string)$payload['payment_mode'],
                    'amount' => round($totalDebit, 2),
                    'status' => self::STATUS_POSTED,
                    'narration' => (string)$payload['narration'],
                    'metadata_json' => json_encode((array)($payload['metadata'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'created_by_admin_id' => $payload['admin_id'] ?? null,
                    'created_by_name' => (string)($payload['admin_name'] ?? ''),
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
                $accountCode = (string)($entry['account_code'] ?? '');
                $accountName = (string)$this->db->fetchScalar('SELECT account_name FROM ledger_accounts WHERE account_code = :code LIMIT 1', ['code' => $accountCode]);
                if ($accountName === '') {
                    throw new \RuntimeException('Unknown account code: ' . $accountCode);
                }

                $this->db->insert(
                    'INSERT INTO general_ledger_entries
                        (batch_id, financial_transaction_id, line_number, account_code, account_name, debit_amount, credit_amount, payment_mode, narration, reference_type, reference_id, created_by_admin_id, created_by_name, created_at)
                     VALUES
                        (:batch_id, :financial_transaction_id, :line_number, :account_code, :account_name, :debit_amount, :credit_amount, :payment_mode, :narration, :reference_type, :reference_id, :created_by_admin_id, :created_by_name, NOW())',
                    [
                        'batch_id' => $batchId,
                        'financial_transaction_id' => $transactionId,
                        'line_number' => $lineNumber,
                        'account_code' => $accountCode,
                        'account_name' => $accountName,
                        'debit_amount' => round((float)($entry['debit_amount'] ?? 0), 2),
                        'credit_amount' => round((float)($entry['credit_amount'] ?? 0), 2),
                        'payment_mode' => (string)$payload['payment_mode'],
                        'narration' => (string)($entry['line_narration'] ?? $payload['narration']),
                        'reference_type' => (string)$payload['reference_type'],
                        'reference_id' => (int)$payload['reference_id'],
                        'created_by_admin_id' => $payload['admin_id'] ?? null,
                        'created_by_name' => (string)($payload['admin_name'] ?? ''),
                    ]
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

            $pdo->commit();

            return [
                'success' => true,
                'posted' => true,
                'transaction_id' => $transactionId,
                'batch_id' => $batchId,
                'message' => 'Financial transaction posted',
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[FinancialTransactionEngine] ' . $e->getMessage());
            return ['success' => false, 'posted' => false, 'message' => 'Posting failed: ' . $e->getMessage()];
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
        return in_array($paymentMethod, ['upi_manual', 'gateway'], true)
            ? self::ACCOUNT_BANK
            : self::ACCOUNT_CASH;
    }
}
