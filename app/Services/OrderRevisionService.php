<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * OrderRevisionService
 *
 * Manages the full lifecycle of an order revision:
 *   submit → pending → confirm (GL posted) → reflected in orders table
 *                    → cancel (no GL impact)
 *
 * All GL postings are delegated to FinancialTransactionEngine.
 */
final class OrderRevisionService
{
    private const VALID_REVISION_TYPES = [
        'upgrade',
        'downgrade',
        'topper_addition',
        'flavor_change',
        'delivery_change',
        'customer_request',
        'admin_adjustment',
    ];

    private const VALID_RESOLUTIONS = ['refund', 'store_credit'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Submit a new revision proposal for an order.
     *
     * @param  array{
     *   order_id: int,
     *   revision_type: string,
     *   new_grand_total: float,
     *   new_items_snapshot: array<mixed>,
     *   revision_reason: string,
     *   downgrade_resolution?: string,
     *   admin_id: int,
     *   requires_super_approval?: bool,
     * } $data
     * @return array{success:bool,message:string,revision_id?:int}
     */
    public function submitRevision(array $data): array
    {
        $orderId       = (int)($data['order_id']       ?? 0);
        $revisionType  = (string)($data['revision_type'] ?? '');
        $newTotal      = round((float)($data['new_grand_total'] ?? 0), 2);
        $newSnapshot   = (array)($data['new_items_snapshot'] ?? []);
        $reason        = trim((string)($data['revision_reason'] ?? ''));
        $resolution    = (string)($data['downgrade_resolution'] ?? '');
        $adminId       = (int)($data['admin_id'] ?? 0);
        $requiresSuper = (bool)($data['requires_super_approval'] ?? false);

        if ($orderId <= 0 || $revisionType === '' || $newTotal <= 0) {
            return ['success' => false, 'message' => 'Missing required revision fields'];
        }
        if (!in_array($revisionType, self::VALID_REVISION_TYPES, true)) {
            return ['success' => false, 'message' => 'Invalid revision_type: ' . $revisionType];
        }

        $order = $this->db->fetchOne(
            'SELECT id, order_number, grand_total,
                    COALESCE(revised_grand_total, grand_total) AS effective_total,
                    current_revision_no, order_status, payment_status, created_at
             FROM orders WHERE id = :id LIMIT 1',
            ['id' => $orderId]
        );
        if ($order === null) {
            return ['success' => false, 'message' => 'Order not found: ' . $orderId];
        }
        if (in_array((string)$order['order_status'], ['cancelled', 'rejected', 'fully_refunded'], true)) {
            return ['success' => false, 'message' => 'Cannot revise an order in status: ' . $order['order_status']];
        }

        if ($this->isAccountingLocked((string)($order['created_at'] ?? ''))) {
            return ['success' => false, 'message' => 'Order is locked for accounting period and cannot be revised'];
        }

        // Check for pending revision on this order
        $pendingRevision = $this->db->fetchOne(
            "SELECT id FROM order_revisions WHERE order_id = :oid AND revision_status = 'pending' LIMIT 1",
            ['oid' => $orderId]
        );
        if ($pendingRevision !== null) {
            return ['success' => false, 'message' => 'Order already has a pending revision (id=' . $pendingRevision['id'] . ')'];
        }

        $oldTotal   = round((float)$order['effective_total'], 2);
        $difference = round($newTotal - $oldTotal, 2);
        $revisionNo = (int)$order['current_revision_no'] + 1;

        // Determine revision_type from difference if needed
        if ($difference > 0) {
            $revisionType = ($revisionType === 'admin_adjustment') ? 'upgrade' : $revisionType;
        }
        if ($resolution !== '' && !in_array($resolution, self::VALID_RESOLUTIONS, true)) {
            return ['success' => false, 'message' => 'Invalid downgrade_resolution: ' . $resolution];
        }

        // Capture old items snapshot from order_items
        $oldItemsRaw = $this->db->fetchAll(
            'SELECT * FROM order_items WHERE order_id = :oid ORDER BY id',
            ['oid' => $orderId]
        );
        $oldSnapshot = $oldItemsRaw ?: [];

        $this->db->beginTransaction();
        try {
            $revisionId = $this->db->insert(
                "INSERT INTO order_revisions
                    (order_id, revision_no, revision_type,
                     old_grand_total, new_grand_total, difference_amount,
                     old_items_snapshot, new_items_snapshot,
                     revision_reason, downgrade_resolution,
                     revision_status, requires_super_approval,
                     created_by_admin_id, created_at, updated_at)
                 VALUES
                    (:order_id, :revision_no, :revision_type,
                     :old_total, :new_total, :diff,
                     :old_snap, :new_snap,
                     :reason, :resolution,
                     'pending', :requires_super,
                     :admin_id, NOW(), NOW())",
                [
                    'order_id'      => $orderId,
                    'revision_no'   => $revisionNo,
                    'revision_type' => $revisionType,
                    'old_total'     => $oldTotal,
                    'new_total'     => $newTotal,
                    'diff'          => $difference,
                    'old_snap'      => json_encode($oldSnapshot, JSON_UNESCAPED_UNICODE),
                    'new_snap'      => json_encode($newSnapshot, JSON_UNESCAPED_UNICODE),
                    'reason'        => $reason,
                    'resolution'    => $resolution ?: null,
                    'requires_super'=> (int)$requiresSuper,
                    'admin_id'      => $adminId,
                ]
            );

            // Update order: flag as revised, bump revision counter, store new total
            $this->db->execute(
                'UPDATE orders
                    SET is_revised          = 1,
                        current_revision_no = :rev_no,
                        revised_grand_total = :new_total,
                        updated_at          = NOW()
                  WHERE id = :id',
                [
                    'rev_no'    => $revisionNo,
                    'new_total' => $newTotal,
                    'id'        => $orderId,
                ]
            );

            $this->db->commit();

            return [
                'success'     => true,
                'message'     => 'Revision #' . $revisionNo . ' submitted for order #' . $order['order_number'],
                'revision_id' => $revisionId,
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[OrderRevisionService] submitRevision error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Submit revision failed: ' . $e->getMessage()];
        }
    }

    /**
     * Confirm a pending revision and post the GL entry.
     *
     * For upgrades: calls FTE::recordOrderUpgrade (Dr AR / Cr SALES_ADJUSTMENT_REVENUE)
     * For downgrades+refund: calls FTE::recordOrderDowngradeRefund (Dr ADJ_EXPENSE / Cr CASH|BANK)
     * For downgrades+store_credit: calls FTE::recordStoreCreditIssued (Dr ADJ_EXPENSE / Cr CREDIT_WALLET)
     *
     * @param  array{
     *   admin_id: int,
     *   admin_name?: string,
     *   admin_role?: string,
     *   payment_mode?: string,
     *   source_channel?: string,
     *   business_date?: string,
     * } $context
     * @return array{success:bool,message:string,gl_transaction_id?:int}
     */
    public function confirmRevision(int $revisionId, array $context = []): array
    {
        $adminId   = (int)($context['admin_id']    ?? 0);
        $adminName = (string)($context['admin_name'] ?? '');
        $adminRole = (string)($context['admin_role'] ?? '');

        $revision = $this->db->fetchOne(
            'SELECT r.*, o.order_number, o.customer_name,
                                        o.customer_phone, o.customer_phone_e164, o.created_at
               FROM order_revisions r
               INNER JOIN orders o ON o.id = r.order_id
              WHERE r.id = :id LIMIT 1',
            ['id' => $revisionId]
        );
        if ($revision === null) {
            return ['success' => false, 'message' => 'Revision not found: ' . $revisionId];
        }
        if ((string)$revision['revision_status'] !== 'pending') {
            return ['success' => false, 'message' => 'Revision is not in pending status (current: ' . $revision['revision_status'] . ')'];
        }

        if ($this->isAccountingLocked((string)($revision['created_at'] ?? ''))) {
            return ['success' => false, 'message' => 'Order is locked for accounting period and revision cannot be confirmed'];
        }

        if ((int)$revision['requires_super_approval'] === 1 && $adminRole !== 'super_admin') {
            return ['success' => false, 'message' => 'This revision requires super-admin approval'];
        }

        $orderId    = (int)$revision['order_id'];
        $difference = round((float)$revision['difference_amount'], 2);
        $absAmount  = abs($difference);
        $ikey       = 'revision:' . $revisionId . ':confirm';

        $fte = new FinancialTransactionEngine($this->db);
        $glResult = null;

        if ($difference > 0) {
            // Upgrade — Dr AR / Cr SALES_ADJUSTMENT_REVENUE
            $glResult = $fte->recordOrderUpgrade([
                'order_id'       => $orderId,
                'order_number'   => (string)$revision['order_number'],
                'revision_id'    => $revisionId,
                'amount'         => $absAmount,
                'admin_id'       => $adminId,
                'admin_name'     => $adminName,
                'idempotency_key'=> $ikey,
                'source_channel' => (string)($context['source_channel'] ?? 'admin'),
                'business_date'  => (string)($context['business_date']  ?? date('Y-m-d')),
                'narration'      => 'Order upgrade #' . $revision['order_number'] . ' rev#' . $revision['revision_no'],
            ]);
        } elseif ($difference < 0) {
            $resolution = (string)($revision['downgrade_resolution'] ?? 'store_credit');
            if ($resolution === 'refund') {
                $paymentMode = (string)($context['payment_mode'] ?? 'cash');
                $glResult    = $fte->recordOrderDowngradeRefund([
                    'order_id'       => $orderId,
                    'order_number'   => (string)$revision['order_number'],
                    'revision_id'    => $revisionId,
                    'amount'         => $absAmount,
                    'payment_mode'   => $paymentMode,
                    'admin_id'       => $adminId,
                    'admin_name'     => $adminName,
                    'idempotency_key'=> $ikey,
                    'source_channel' => (string)($context['source_channel'] ?? 'admin'),
                    'business_date'  => (string)($context['business_date']  ?? date('Y-m-d')),
                    'narration'      => 'Order downgrade refund #' . $revision['order_number'] . ' rev#' . $revision['revision_no'],
                ]);
            } else {
                // store_credit (default for downgrade)
                $glResult = $fte->recordStoreCreditIssued([
                    'order_id'       => $orderId,
                    'order_number'   => (string)$revision['order_number'],
                    'revision_id'    => $revisionId,
                    'amount'         => $absAmount,
                    'admin_id'       => $adminId,
                    'admin_name'     => $adminName,
                    'idempotency_key'=> $ikey,
                    'source_channel' => (string)($context['source_channel'] ?? 'admin'),
                    'business_date'  => (string)($context['business_date']  ?? date('Y-m-d')),
                    'narration'      => 'Store credit issued #' . $revision['order_number'] . ' rev#' . $revision['revision_no'],
                ]);
            }
        }
        // difference === 0 means informational revision (flavor/delivery change) — no GL posting needed

        $glTxId = null;
        if ($glResult !== null) {
            if (!$glResult['success']) {
                return ['success' => false, 'message' => 'GL posting failed: ' . $glResult['message']];
            }
            $glTxId = (int)($glResult['transaction_id'] ?? 0);
        }

        // Mark revision confirmed
        $this->db->execute(
            "UPDATE order_revisions
                SET revision_status      = 'confirmed',
                    gl_transaction_id    = :gl_tx_id,
                    approved_by_admin_id = :admin_id,
                    updated_at           = NOW()
              WHERE id = :id",
            [
                'gl_tx_id' => $glTxId > 0 ? $glTxId : null,
                'admin_id' => $adminId,
                'id'       => $revisionId,
            ]
        );

        // Notify customer via WhatsApp (queued, non-blocking)
        $customerPhone = (string)($revision['customer_phone_e164'] ?: ($revision['customer_phone'] ?? ''));
        $this->queueRevisionNotification($orderId, $customerPhone, [
            'order_id'      => $orderId,
            'order_number'  => (string)$revision['order_number'],
            'customer_name' => (string)($revision['customer_name'] ?? ''),
            'old_total'     => (float)$revision['old_grand_total'],
            'new_total'     => (float)$revision['new_grand_total'],
            'difference'    => (float)$revision['difference_amount'],
            'revision_type' => (string)$revision['revision_type'],
            'is_upgrade'    => $difference > 0,
            'admin_name'    => $adminName,
        ]);

        return [
            'success'            => true,
            'message'            => 'Revision #' . $revision['revision_no'] . ' confirmed',
            'gl_transaction_id'  => $glTxId,
        ];
    }

    /**
     * Queue a WhatsApp notification to the customer after a revision is confirmed.
     * Fires-and-forgets: failure is silenced to avoid blocking the confirm flow.
     */
    private function queueRevisionNotification(
        int $orderId,
        string $phone,
        array $context
    ): void {
        if ($phone === '') {
            return;
        }

        $payloadJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payloadJson)) {
            $payloadJson = '{}';
        }

        try {
            $logId = (int)$this->db->insert(
                'INSERT INTO communication_logs
                     (order_id, channel, event_key, recipient, status, payload_json)
                 VALUES
                     (:order_id, "whatsapp", "order_revision_confirmed", :recipient, "queued", :payload_json)',
                [
                    'order_id'     => $orderId,
                    'recipient'    => $phone,
                    'payload_json' => $payloadJson,
                ]
            );

            if ($logId > 0) {
                $this->db->insert(
                    'INSERT INTO communication_queue
                         (communication_log_id, channel, payload_json)
                     VALUES
                         (:communication_log_id, "whatsapp", :payload_json)',
                    [
                        'communication_log_id' => $logId,
                        'payload_json'         => $payloadJson,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal: revision is already confirmed; notification failure should not roll it back
        }
    }

    /**
     * Cancel a pending revision (no GL impact; reverts revised_grand_total if no confirmed revision exists).
     *
     * @return array{success:bool,message:string}
     */
    public function cancelRevision(int $revisionId, int $adminId): array
    {
        $revision = $this->db->fetchOne(
            'SELECT * FROM order_revisions WHERE id = :id LIMIT 1',
            ['id' => $revisionId]
        );
        if ($revision === null) {
            return ['success' => false, 'message' => 'Revision not found: ' . $revisionId];
        }
        if ((string)$revision['revision_status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only pending revisions can be cancelled'];
        }

        $orderId = (int)$revision['order_id'];

        $this->db->execute(
            "UPDATE order_revisions
                SET revision_status = 'cancelled', updated_at = NOW()
              WHERE id = :id",
            ['id' => $revisionId]
        );

        // Check if there are any other confirmed revisions for this order
        $lastConfirmed = $this->db->fetchOne(
            "SELECT new_grand_total FROM order_revisions
              WHERE order_id = :oid AND revision_status = 'confirmed'
              ORDER BY revision_no DESC LIMIT 1",
            ['oid' => $orderId]
        );

        if ($lastConfirmed !== null) {
            // Revert revised_grand_total to last confirmed revision's new_grand_total
            $this->db->execute(
                'UPDATE orders SET revised_grand_total = :total, updated_at = NOW() WHERE id = :id',
                ['total' => round((float)$lastConfirmed['new_grand_total'], 2), 'id' => $orderId]
            );
        } else {
            // No confirmed revisions — clear the revised_grand_total
            $this->db->execute(
                'UPDATE orders SET revised_grand_total = NULL, is_revised = 0, current_revision_no = 0, updated_at = NOW() WHERE id = :id',
                ['id' => $orderId]
            );
        }

        return ['success' => true, 'message' => 'Revision #' . $revision['revision_no'] . ' cancelled'];
    }

    /**
     * Return the full revision history for an order, newest first.
     *
     * @return list<array<string,mixed>>
     */
    public function getRevisionHistory(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT r.*,
                    a_created.username  AS created_by_name,
                    a_approved.username AS approved_by_name
               FROM order_revisions r
               LEFT JOIN admins a_created  ON a_created.id  = r.created_by_admin_id
               LEFT JOIN admins a_approved ON a_approved.id = r.approved_by_admin_id
              WHERE r.order_id = :oid
              ORDER BY r.revision_no DESC',
            ['oid' => $orderId]
        ) ?: [];
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
