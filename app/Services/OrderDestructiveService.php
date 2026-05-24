<?php

namespace App\Services;

use PDO;
use Throwable;

class OrderDestructiveService
{
    /** @var array<string,bool> */
    private array $tableExistsCache = [];

    /** @var array<string,bool> */
    private array $columnExistsCache = [];

    /** @var string[] */
    private array $allowedReasons = [
        'duplicate_order',
        'fraudulent_order',
        'customer_request',
        'test_order',
        'compliance_removal',
        'data_correction',
        'other',
    ];

    public function preview(PDO $pdo, int $orderId): array
    {
        if ($orderId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid order id.',
            ];
        }

        $order = $this->fetchOrderRow($pdo, $orderId);
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Order not found.',
            ];
        }

        $financial = $this->buildFinancialImpactSummary($pdo, $orderId, $order);
        return [
            'success' => true,
            'order_id' => $orderId,
            'is_archived' => (bool)($order['is_archived'] ?? 0),
            'has_financial_entries' => (bool)$financial['has_financial_entries'],
            'financial_impact_level' => (string)$financial['impact_level'],
            'financial_message' => (string)$financial['message'],
            'financial_summary' => $financial,
        ];
    }

    public function archiveOrder(PDO $pdo, array $context): array
    {
        $orderId = (int)($context['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Invalid order id.'];
        }

        $reasonCode = $this->sanitizeReasonCode((string)($context['reason_code'] ?? 'other'));
        $reasonNotes = $this->sanitizeReasonNotes((string)($context['reason_notes'] ?? ''));
        $deletePassword = (string)($context['delete_password'] ?? '');

        if ($deletePassword === '') {
            return ['success' => false, 'message' => 'Delete password is required.'];
        }

        if (!$this->verifyDeletePassword($pdo, $deletePassword)) {
            return ['success' => false, 'message' => 'Delete password verification failed.'];
        }

        if (!$this->columnExists($pdo, 'orders', 'is_archived')) {
            return ['success' => false, 'message' => 'Archive columns are not available. Run the governance migration first.'];
        }

        try {
            $pdo->beginTransaction();

            $order = $this->fetchOrderRowForUpdate($pdo, $orderId);
            if (!$order) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Order not found.'];
            }

            if ((int)($order['is_archived'] ?? 0) === 1) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Order is already archived.'];
            }

            $snapshot = $this->buildOrderSnapshot($pdo, $orderId, $order);
            $financial = $this->buildFinancialImpactSummary($pdo, $orderId, $order);

            $stmt = $pdo->prepare(
                'UPDATE orders
                 SET is_archived = 1,
                     archived_at = NOW(),
                     archived_by_admin_id = :admin_id,
                     archive_reason_code = :reason_code,
                     archive_reason_notes = :reason_notes,
                     updated_at = NOW()
                 WHERE id = :order_id
                 LIMIT 1'
            );
            $stmt->execute([
                ':admin_id' => (int)($context['admin_id'] ?? 0) ?: null,
                ':reason_code' => $reasonCode,
                ':reason_notes' => $reasonNotes !== '' ? $reasonNotes : null,
                ':order_id' => $orderId,
            ]);

            $this->writeDestructiveLog($pdo, [
                'order_id' => $orderId,
                'action_type' => 'archive',
                'reason_code' => $reasonCode,
                'reason_notes' => $reasonNotes,
                'financial_impact_level' => (string)$financial['impact_level'],
                'requires_delete_password' => 1,
                'actor_admin_id' => (int)($context['admin_id'] ?? 0) ?: null,
                'actor_role' => (string)($context['admin_role'] ?? ''),
                'actor_name' => (string)($context['admin_name'] ?? ''),
                'ip_address' => (string)($context['ip_address'] ?? ''),
                'user_agent' => (string)($context['user_agent'] ?? ''),
                'order_snapshot_json' => $this->safeJsonEncode($snapshot),
                'recovery_payload_json' => $this->safeJsonEncode([
                    'operation' => 'restore',
                    'order_id' => $orderId,
                    'archived_at' => date('c'),
                ]),
            ]);

            $pdo->commit();
            return [
                'success' => true,
                'message' => 'Order archived successfully.',
                'order_id' => $orderId,
                'financial_impact_level' => (string)$financial['impact_level'],
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Archive failed: ' . $e->getMessage(),
            ];
        }
    }

    public function restoreOrder(PDO $pdo, array $context): array
    {
        $orderId = (int)($context['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Invalid order id.'];
        }

        $deletePassword = (string)($context['delete_password'] ?? '');
        if ($deletePassword === '') {
            return ['success' => false, 'message' => 'Delete password is required.'];
        }

        if (!$this->verifyDeletePassword($pdo, $deletePassword)) {
            return ['success' => false, 'message' => 'Delete password verification failed.'];
        }

        if (!$this->columnExists($pdo, 'orders', 'is_archived')) {
            return ['success' => false, 'message' => 'Archive columns are not available. Run the governance migration first.'];
        }

        try {
            $pdo->beginTransaction();

            $order = $this->fetchOrderRowForUpdate($pdo, $orderId);
            if (!$order) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Order not found.'];
            }

            if ((int)($order['is_archived'] ?? 0) !== 1) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Order is not archived.'];
            }

            $snapshot = $this->buildOrderSnapshot($pdo, $orderId, $order);

            $stmt = $pdo->prepare(
                'UPDATE orders
                 SET is_archived = 0,
                     archived_at = NULL,
                     archived_by_admin_id = NULL,
                     archive_reason_code = NULL,
                     archive_reason_notes = NULL,
                     updated_at = NOW()
                 WHERE id = :order_id
                 LIMIT 1'
            );
            $stmt->execute([':order_id' => $orderId]);

            $this->writeDestructiveLog($pdo, [
                'order_id' => $orderId,
                'action_type' => 'restore',
                'reason_code' => 'data_correction',
                'reason_notes' => (string)($context['reason_notes'] ?? 'Archive restored by admin'),
                'financial_impact_level' => 'none',
                'requires_delete_password' => 1,
                'actor_admin_id' => (int)($context['admin_id'] ?? 0) ?: null,
                'actor_role' => (string)($context['admin_role'] ?? ''),
                'actor_name' => (string)($context['admin_name'] ?? ''),
                'ip_address' => (string)($context['ip_address'] ?? ''),
                'user_agent' => (string)($context['user_agent'] ?? ''),
                'order_snapshot_json' => $this->safeJsonEncode($snapshot),
                'recovery_payload_json' => $this->safeJsonEncode([
                    'operation' => 'archive',
                    'order_id' => $orderId,
                ]),
            ]);

            $pdo->commit();
            return [
                'success' => true,
                'message' => 'Order restored successfully.',
                'order_id' => $orderId,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage(),
            ];
        }
    }

    public function forcePurgeOrder(PDO $pdo, array $context): array
    {
        $orderId = (int)($context['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Invalid order id.'];
        }

        $reasonCode = $this->sanitizeReasonCode((string)($context['reason_code'] ?? 'other'));
        $reasonNotes = $this->sanitizeReasonNotes((string)($context['reason_notes'] ?? ''));
        $deletePassword = (string)($context['delete_password'] ?? '');
        $confirmImpact = (bool)($context['confirm_financial_purge'] ?? false);

        if ($deletePassword === '') {
            return ['success' => false, 'message' => 'Delete password is required.'];
        }

        if (!$this->verifyDeletePassword($pdo, $deletePassword)) {
            return ['success' => false, 'message' => 'Delete password verification failed.'];
        }

        try {
            $pdo->beginTransaction();

            $order = $this->fetchOrderRowForUpdate($pdo, $orderId);
            if (!$order) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Order not found.'];
            }

            $financial = $this->buildFinancialImpactSummary($pdo, $orderId, $order);
            if ((bool)$financial['has_financial_entries'] && !$confirmImpact) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Financial impact confirmation is required before force purge.',
                    'requires_financial_confirmation' => true,
                ];
            }

            $snapshot = $this->buildOrderSnapshot($pdo, $orderId, $order);
            $refundIds = $this->fetchRefundIds($pdo, $orderId);

            $this->deleteOrderDependents($pdo, $orderId, $refundIds);

            $this->writeDestructiveLog($pdo, [
                'order_id' => $orderId,
                'action_type' => 'force_purge',
                'reason_code' => $reasonCode,
                'reason_notes' => $reasonNotes,
                'financial_impact_level' => (bool)$financial['has_financial_entries'] ? 'financial_entries_purged' : 'none',
                'requires_delete_password' => 1,
                'actor_admin_id' => (int)($context['admin_id'] ?? 0) ?: null,
                'actor_role' => (string)($context['admin_role'] ?? ''),
                'actor_name' => (string)($context['admin_name'] ?? ''),
                'ip_address' => (string)($context['ip_address'] ?? ''),
                'user_agent' => (string)($context['user_agent'] ?? ''),
                'order_snapshot_json' => $this->safeJsonEncode($snapshot),
                'recovery_payload_json' => $this->safeJsonEncode([
                    'operation' => 'manual_recovery_required',
                    'order_number' => (string)($order['order_number'] ?? ''),
                    'purged_at' => date('c'),
                ]),
            ]);

            $stmtDeleteOrder = $pdo->prepare('DELETE FROM orders WHERE id = :order_id LIMIT 1');
            $stmtDeleteOrder->execute([':order_id' => $orderId]);

            $pdo->commit();
            return [
                'success' => true,
                'message' => 'Order force purged successfully.',
                'order_id' => $orderId,
                'financial_impact_level' => (bool)$financial['has_financial_entries'] ? 'financial_entries_purged' : 'none',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Force purge failed: ' . $e->getMessage(),
            ];
        }
    }

    private function verifyDeletePassword(PDO $pdo, string $deletePassword): bool
    {
        $hash = '';

        // Support both legacy (key/value) and current (setting_key/setting_value) schemas.
        if ($this->columnExists($pdo, 'settings', 'setting_key') && $this->columnExists($pdo, 'settings', 'setting_value')) {
            $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1');
            $stmt->execute([':k' => 'order_delete_password_hash']);
            $hash = (string)$stmt->fetchColumn();
        } elseif ($this->columnExists($pdo, 'settings', 'key') && $this->columnExists($pdo, 'settings', 'value')) {
            $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = :k LIMIT 1');
            $stmt->execute([':k' => 'order_delete_password_hash']);
            $hash = (string)$stmt->fetchColumn();
        }

        if ($hash === '') {
            return false;
        }

        return password_verify($deletePassword, $hash);
    }

    private function fetchOrderRow(PDO $pdo, int $orderId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function fetchOrderRowForUpdate(PDO $pdo, int $orderId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :order_id LIMIT 1 FOR UPDATE');
        $stmt->execute([':order_id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function buildOrderSnapshot(PDO $pdo, int $orderId, array $order): array
    {
        $snapshot = ['order' => $order];

        if ($this->tableExists($pdo, 'order_items')) {
            $stmtItems = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
            $stmtItems->execute([':order_id' => $orderId]);
            $snapshot['order_items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $snapshot['order_items'] = [];
        }

        if ($this->tableExists($pdo, 'refund_transactions')) {
            $stmtRefunds = $pdo->prepare('SELECT * FROM refund_transactions WHERE order_id = :order_id');
            $stmtRefunds->execute([':order_id' => $orderId]);
            $snapshot['refund_transactions'] = $stmtRefunds->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $snapshot['refund_transactions'] = [];
        }

        return $snapshot;
    }

    private function buildFinancialImpactSummary(PDO $pdo, int $orderId, array $order): array
    {
        $paymentStatus = (string)($order['payment_status'] ?? '');
        $financialTxCount = 0;
        $refundCount = 0;
        $invoicePaymentCount = 0;

        if ($this->tableExists($pdo, 'financial_transactions')) {
            $sql = 'SELECT COUNT(*)
                    FROM financial_transactions
                    WHERE reference_type = :ref_type
                      AND reference_id = :order_id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ref_type' => 'order', ':order_id' => $orderId]);
            $financialTxCount += (int)$stmt->fetchColumn();
        }

        if ($this->tableExists($pdo, 'refund_transactions')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM refund_transactions WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
            $refundCount = (int)$stmt->fetchColumn();
        }

        if ($this->tableExists($pdo, 'invoices') && $this->tableExists($pdo, 'payments')) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 WHERE i.order_id = :order_id'
            );
            $stmt->execute([':order_id' => $orderId]);
            $invoicePaymentCount = (int)$stmt->fetchColumn();
        }

        $isPaidLike = in_array($paymentStatus, ['paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded'], true);
        $hasFinancial = $isPaidLike || $financialTxCount > 0 || $refundCount > 0 || $invoicePaymentCount > 0;
        $impactLevel = $hasFinancial ? 'contains_financial_entries' : 'none';

        return [
            'has_financial_entries' => $hasFinancial,
            'impact_level' => $impactLevel,
            'payment_status' => $paymentStatus,
            'financial_transaction_count' => $financialTxCount,
            'refund_count' => $refundCount,
            'invoice_payment_count' => $invoicePaymentCount,
            'message' => $hasFinancial
                ? 'This order has financial records. Force purge can impact audit and reconciliation history.'
                : 'No financial records detected for this order.',
        ];
    }

    /**
     * @return int[]
     */
    private function fetchRefundIds(PDO $pdo, int $orderId): array
    {
        if (!$this->tableExists($pdo, 'refund_transactions')) {
            return [];
        }

        $stmt = $pdo->prepare('SELECT id FROM refund_transactions WHERE order_id = :order_id');
        $stmt->execute([':order_id' => $orderId]);
        $ids = [];
        while (($val = $stmt->fetchColumn()) !== false) {
            $ids[] = (int)$val;
        }
        return $ids;
    }

    /**
     * @param int[] $refundIds
     */
    private function deleteOrderDependents(PDO $pdo, int $orderId, array $refundIds): void
    {
        if ($this->tableExists($pdo, 'order_items')) {
            $stmt = $pdo->prepare('DELETE FROM order_items WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'order_status_history')) {
            $stmt = $pdo->prepare('DELETE FROM order_status_history WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'order_notes')) {
            $stmt = $pdo->prepare('DELETE FROM order_notes WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'collection_followup_logs')) {
            $stmt = $pdo->prepare('DELETE FROM collection_followup_logs WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'communication_logs')) {
            if ($this->tableExists($pdo, 'communication_queue')) {
                $stmtQueue = $pdo->prepare(
                    'DELETE cq FROM communication_queue cq
                     INNER JOIN communication_logs cl ON cl.id = cq.communication_log_id
                     WHERE cl.order_id = :order_id'
                );
                $stmtQueue->execute([':order_id' => $orderId]);
            }

            $stmt = $pdo->prepare('DELETE FROM communication_logs WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'coupon_redemptions')) {
            $stmt = $pdo->prepare('DELETE FROM coupon_redemptions WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'refund_approval_logs') && $refundIds !== []) {
            $in = implode(',', array_fill(0, count($refundIds), '?'));
            $stmt = $pdo->prepare('DELETE FROM refund_approval_logs WHERE refund_transaction_id IN (' . $in . ')');
            foreach ($refundIds as $idx => $refundId) {
                $stmt->bindValue($idx + 1, $refundId, PDO::PARAM_INT);
            }
            $stmt->execute();
        }

        if ($this->tableExists($pdo, 'refund_transactions')) {
            $stmt = $pdo->prepare('DELETE FROM refund_transactions WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'payment_status_history') && $this->tableExists($pdo, 'invoices')) {
            $stmt = $pdo->prepare(
                'DELETE psh FROM payment_status_history psh
                 INNER JOIN invoices i ON i.id = psh.invoice_id
                 WHERE i.order_id = :order_id'
            );
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'payments') && $this->tableExists($pdo, 'invoices')) {
            $stmt = $pdo->prepare(
                'DELETE p FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 WHERE i.order_id = :order_id'
            );
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'invoices')) {
            $stmt = $pdo->prepare('DELETE FROM invoices WHERE order_id = :order_id');
            $stmt->execute([':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'financial_audit_logs') && $this->tableExists($pdo, 'financial_transactions')) {
            $stmt = $pdo->prepare(
                'DELETE fal FROM financial_audit_logs fal
                 INNER JOIN financial_transactions ft ON ft.id = fal.financial_transaction_id
                 WHERE ft.reference_type = :ref_type
                   AND ft.reference_id = :order_id'
            );
            $stmt->execute([':ref_type' => 'order', ':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'general_ledger_entries')) {
            $stmt = $pdo->prepare('DELETE FROM general_ledger_entries WHERE reference_type = :ref_type AND reference_id = :order_id');
            $stmt->execute([':ref_type' => 'order', ':order_id' => $orderId]);
        }

        if ($this->tableExists($pdo, 'financial_transactions')) {
            $stmt = $pdo->prepare('DELETE FROM financial_transactions WHERE reference_type = :ref_type AND reference_id = :order_id');
            $stmt->execute([':ref_type' => 'order', ':order_id' => $orderId]);
        }

        // Final safety sweep: delete any remaining child rows that reference orders via order_id.
        $this->deleteAllOrderIdDependents($pdo, $orderId);
    }

    private function deleteAllOrderIdDependents(PDO $pdo, int $orderId): void
    {
        $stmt = $pdo->query(
            "SELECT DISTINCT table_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND column_name = 'order_id'"
        );
        $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($tables as $tableNameRaw) {
            $tableName = trim((string)$tableNameRaw);
            if ($tableName === '' || $tableName === 'orders' || $tableName === 'order_destructive_logs') {
                continue;
            }

            // Guard against identifier issues by validating table names from metadata.
            if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
                continue;
            }

            $pdo->exec('DELETE FROM `' . $tableName . '` WHERE order_id = ' . (int)$orderId);
        }
    }

    private function writeDestructiveLog(PDO $pdo, array $payload): void
    {
        if (!$this->tableExists($pdo, 'order_destructive_logs')) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO order_destructive_logs
            (
                order_id,
                action_type,
                reason_code,
                reason_notes,
                financial_impact_level,
                requires_delete_password,
                actor_admin_id,
                actor_role,
                actor_name,
                ip_address,
                user_agent,
                order_snapshot_json,
                recovery_payload_json,
                created_at
            )
            VALUES
            (
                :order_id,
                :action_type,
                :reason_code,
                :reason_notes,
                :financial_impact_level,
                :requires_delete_password,
                :actor_admin_id,
                :actor_role,
                :actor_name,
                :ip_address,
                :user_agent,
                :order_snapshot_json,
                :recovery_payload_json,
                NOW()
            )'
        );

        $stmt->execute([
            ':order_id' => (int)($payload['order_id'] ?? 0),
            ':action_type' => (string)($payload['action_type'] ?? 'archive'),
            ':reason_code' => $this->sanitizeReasonCode((string)($payload['reason_code'] ?? 'other')),
            ':reason_notes' => $this->sanitizeReasonNotes((string)($payload['reason_notes'] ?? '')),
            ':financial_impact_level' => (string)($payload['financial_impact_level'] ?? 'none'),
            ':requires_delete_password' => (int)($payload['requires_delete_password'] ?? 1),
            ':actor_admin_id' => (int)($payload['actor_admin_id'] ?? 0) ?: null,
            ':actor_role' => (string)($payload['actor_role'] ?? ''),
            ':actor_name' => (string)($payload['actor_name'] ?? ''),
            ':ip_address' => (string)($payload['ip_address'] ?? ''),
            ':user_agent' => (string)($payload['user_agent'] ?? ''),
            ':order_snapshot_json' => (string)($payload['order_snapshot_json'] ?? ''),
            ':recovery_payload_json' => (string)($payload['recovery_payload_json'] ?? ''),
        ]);
    }

    private function sanitizeReasonCode(string $reasonCode): string
    {
        $reasonCode = trim($reasonCode);
        if (!in_array($reasonCode, $this->allowedReasons, true)) {
            return 'other';
        }
        return $reasonCode;
    }

    private function sanitizeReasonNotes(string $reasonNotes): string
    {
        $reasonNotes = trim($reasonNotes);
        if ($reasonNotes === '') {
            return '';
        }
        return substr($reasonNotes, 0, 2000);
    }

    private function tableExists(PDO $pdo, string $tableName): bool
    {
        if (array_key_exists($tableName, $this->tableExistsCache)) {
            return $this->tableExistsCache[$tableName];
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute([':table_name' => $tableName]);
        $exists = ((int)$stmt->fetchColumn()) > 0;
        $this->tableExistsCache[$tableName] = $exists;
        return $exists;
    }

    private function columnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $cacheKey = $tableName . ':' . $columnName;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name'
        );
        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        $exists = ((int)$stmt->fetchColumn()) > 0;
        $this->columnExistsCache[$cacheKey] = $exists;
        return $exists;
    }

    private function safeJsonEncode(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === false) {
            return '{}';
        }
        return $encoded;
    }
}
