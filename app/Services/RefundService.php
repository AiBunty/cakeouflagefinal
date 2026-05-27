<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * RefundService — orchestrates the full refund lifecycle.
 *
 * Flow:
 *   submitRequest() → refund_transactions row created, order → refund_requested
 *   approve()       → calls processRefund() internally
 *   reject()        → reverts order to previous_order_status
 *   processRefund() → private; resolves full vs partial, updates orders, calls
 *                     OrderStateManager to move order to refunded|partially_refunded
 *
 * Anti-fraud checks (6) are enforced in submitRequest().
 * Callers must invoke OrderAutomationService for customer notifications.
 */
final class RefundService
{
    /** @var array<string,bool> */
    private static array $columnPresence = [];

    /** Amount above which can_force_refund permission is required. */
    private const HIGH_VALUE_THRESHOLD = 5000.00;

    /** Reason codes that business has approved for use in the UI. */
    public const REASON_CODES = [
        'DAMAGED_ITEM',
        'WRONG_ORDER',
        'ITEM_NOT_DELIVERED',
        'QUALITY_ISSUE',
        'DUPLICATE_CHARGE',
        'CUSTOMER_CANCELLED',
        'OTHER',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Submit a refund request for an order.
     *
     * Eligible order statuses: delivered, completed, partially_refunded.
     * Runs 6 anti-fraud checks before inserting the refund_transactions row.
     *
     * @param PDO $pdo
     * @param int $orderId
     * @param array{
     *     reason_code?:      string,
     *     reason_notes?:     string,
     *     requested_amount?: float|string,
     * } $payload
     * @param int   $adminId
     * @param array{
     *     admin_role?:        string,
     *     admin_permissions?: array<int,string>,
     *     ip_address?:        string,
     * } $context
     * @return array{success: bool, message: string, refund_id?: int, refund_number?: string, fraud_flags?: array<int,string>}
     */
    public function submitRequest(
        PDO   $pdo,
        int   $orderId,
        array $payload,
        int   $adminId,
        array $context = []
    ): array {
        $adminRole        = (string)($context['admin_role']        ?? '');
        $adminPermissions = (array) ($context['admin_permissions'] ?? []);
        $ipAddress        = (string)($context['ip_address']        ?? '');

        $reasonCode       = strtoupper(trim((string)($payload['reason_code']      ?? '')));
        $reasonNotes      = trim((string)($payload['reason_notes']   ?? ''));
        $requestedAmount  = round((float)($payload['requested_amount'] ?? 0), 2);

        // Basic input validation
        if ($requestedAmount <= 0) {
            return ['success' => false, 'message' => 'Requested refund amount must be greater than zero'];
        }
        if (!in_array($reasonCode, self::REASON_CODES, true)) {
            return ['success' => false, 'message' => 'Invalid reason code: ' . $reasonCode];
        }

        // ── Load order ─────────────────────────────────────────────────────
        $orderStmt = $pdo->prepare(
            'SELECT id, order_number, order_status, payment_status, payment_method,
                    payment_proof_url, payment_confirmed_by_admin_id,
                    grand_total, delivery_fee, refund_amount, created_at
             FROM orders
             WHERE id = :id
             LIMIT 1'
        );
        $orderStmt->execute(['id' => $orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if ($order === false) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        $orderStatus        = (string)($order['order_status']                  ?? '');
        $grandTotal         = (float) ($order['grand_total']                   ?? 0);
        $deliveryFee        = (float) ($order['delivery_fee']                  ?? 0);
        $alreadyRefunded    = (float) ($order['refund_amount']                 ?? 0);
        $paymentMethod      = (string)($order['payment_method']                ?? '');
        $paymentProofUrl    = (string)($order['payment_proof_url']             ?? '');
        $confirmedByAdminId = (int)   ($order['payment_confirmed_by_admin_id'] ?? 0);
        $orderCreatedAt     = (string)($order['created_at']                    ?? '');

        // ── Eligible status check ───────────────────────────────────────────
        $eligibleStatuses = ['delivered', 'completed', 'partially_refunded'];
        if (!in_array($orderStatus, $eligibleStatuses, true)) {
            return [
                'success' => false,
                'message' => 'Refunds can only be requested for delivered or completed orders (current: ' . $orderStatus . ')',
            ];
        }

        // ── Refundable ceiling: grand_total - delivery_fee - already_refunded ─
        $refundableBalance = round($grandTotal - $deliveryFee - $alreadyRefunded, 2);
        if ($refundableBalance <= 0) {
            return ['success' => false, 'message' => 'No refundable balance remaining on this order'];
        }
        if ($requestedAmount > $refundableBalance) {
            return [
                'success' => false,
                'message' => 'Requested amount (' . $requestedAmount . ') exceeds refundable balance (' . $refundableBalance . ')',
            ];
        }

        // ── Anti-fraud checks ──────────────────────────────────────────────
        $fraudFlags = [];

        // 1. Double refund: active (non-rejected) refund already exists
        $dupStmt = $pdo->prepare(
            "SELECT id FROM refund_transactions
             WHERE order_id = :order_id AND status NOT IN ('rejected')
             LIMIT 1"
        );
        $dupStmt->execute(['order_id' => $orderId]);
        if ($dupStmt->fetchColumn() !== false) {
            $fraudFlags[] = 'DUPLICATE_REFUND';
        }

        // 2. Accounting lock: order older than accounting_lock_days setting
        $lockDays = $this->getAccountingLockDays($pdo);
        if ($lockDays > 0 && $orderCreatedAt !== '') {
            $createdTs = strtotime($orderCreatedAt);
            $lockTs    = strtotime('+' . $lockDays . ' days', $createdTs ?: 0);
            if (time() > $lockTs) {
                $fraudFlags[] = 'ACCOUNTING_PERIOD_LOCKED';
            }
        }

        // 3. Self-refund: requesting admin is the one who confirmed payment
        if ($adminId > 0 && $confirmedByAdminId > 0 && $adminId === $confirmedByAdminId) {
            $fraudFlags[] = 'SELF_REFUND';
        }

        // 4. Payment proof required: UPI orders must have uploaded proof
        if ($paymentMethod === 'upi_manual' && $paymentProofUrl === '') {
            $fraudFlags[] = 'PAYMENT_PROOF_MISSING';
        }

        // 5. High-value threshold: needs force_refund permission
        if ($requestedAmount > self::HIGH_VALUE_THRESHOLD) {
            $hasForce = $adminRole === 'super_admin'
                || in_array('can_force_refund', $adminPermissions, true);
            if (!$hasForce) {
                $fraudFlags[] = 'HIGH_VALUE_REQUIRES_FORCE_REFUND';
            }
        }

        // 6. Amount sanity: requested > grand_total (belt-and-suspenders on top of cap check)
        if ($requestedAmount > $grandTotal) {
            $fraudFlags[] = 'AMOUNT_EXCEEDS_ORDER_TOTAL';
        }

        // Hard-block fraud flags (cannot submit at all)
        $hardBlockFlags = ['DUPLICATE_REFUND', 'HIGH_VALUE_REQUIRES_FORCE_REFUND', 'AMOUNT_EXCEEDS_ORDER_TOTAL'];
        foreach ($hardBlockFlags as $flag) {
            if (in_array($flag, $fraudFlags, true)) {
                return [
                    'success'     => false,
                    'message'     => 'Refund request blocked by anti-fraud check: ' . $flag,
                    'fraud_flags' => $fraudFlags,
                ];
            }
        }

        // Determine refund type
        $isFullRefund  = ($requestedAmount >= $refundableBalance);
        $refundType    = $isFullRefund ? 'full' : 'partial';
        $refundNumber  = $this->generateRefundNumber();

        // ── Insert refund_transactions row ────────────────────────────────
        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare(
                'INSERT INTO refund_transactions
                     (order_id, refund_number, refund_type, reason_code, reason_notes,
                      requested_amount, status, requested_by_admin_id, previous_order_status,
                      fraud_flags, requested_at)
                 VALUES
                     (:order_id, :refund_number, :refund_type, :reason_code, :reason_notes,
                      :requested_amount, "pending_approval", :requested_by, :previous_status,
                      :fraud_flags, NOW())'
            );
            $insertStmt->execute([
                'order_id'         => $orderId,
                'refund_number'    => $refundNumber,
                'refund_type'      => $refundType,
                'reason_code'      => $reasonCode,
                'reason_notes'     => $reasonNotes !== '' ? $reasonNotes : null,
                'requested_amount' => $requestedAmount,
                'requested_by'     => $adminId > 0 ? $adminId : null,
                'previous_status'  => $orderStatus,
                'fraud_flags'      => !empty($fraudFlags)
                                      ? json_encode($fraudFlags, JSON_UNESCAPED_UNICODE)
                                      : null,
            ]);
            $refundId = (int)$pdo->lastInsertId();

            // Update orders.refund_status and snapshot the reason
            $orderUpdateStmt = $pdo->prepare(
                'UPDATE orders
                 SET refund_status = "requested",
                     refund_reason = :reason_code,
                     refund_notes  = :reason_notes,
                     refund_requested_at = NOW(),
                     refunded_by_admin_id = :admin_id
                 WHERE id = :id'
            );
            $orderUpdateStmt->execute([
                'reason_code'  => $reasonCode,
                'reason_notes' => $reasonNotes !== '' ? $reasonNotes : null,
                'admin_id'     => $adminId > 0 ? $adminId : null,
                'id'           => $orderId,
            ]);

            // Log submission to refund_approval_logs
            $this->writeApprovalLog($pdo, $refundId, 'submitted', $adminId, $adminRole, $ipAddress, null);

            $pdo->commit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[RefundService] submitRequest PDO error on order #' . $orderId . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error while creating refund request'];
        }

        // ── Transition order status via OrderStateManager (when legal) ─────
        // Done outside the refund_transactions transaction so a state machine
        // failure doesn't silently roll back the audit record.
        $stateManager = new OrderStateManager();
        $allowedTransitions = $stateManager->getAllowedTransitions($orderStatus);
        if (in_array('refund_requested', $allowedTransitions, true)) {
            $transition = $stateManager->transition($pdo, $orderId, 'refund_requested', $adminId, [
                'admin_role'        => $adminRole,
                'admin_permissions' => $adminPermissions,
                'ip_address'        => $ipAddress,
                'reason'            => 'Refund request #' . $refundNumber . ': ' . $reasonCode,
                'metadata'          => ['refund_transaction_id' => $refundId],
                'skip_permission'   => true, // permission already checked above
            ]);

            if (!$transition['success']) {
                error_log('[RefundService] State machine failed after refund insert for order #' . $orderId . ': ' . $transition['message']);
                // Non-fatal: refund record exists; admin dashboard will show pending
            }
        }

        return [
            'success'      => true,
            'message'      => 'Refund request submitted successfully',
            'refund_id'    => $refundId,
            'refund_number' => $refundNumber,
            'fraud_flags'  => $fraudFlags,  // soft flags (non-blocking) returned for UI display
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // NEW: Atomic single-step refund (the primary refund path going forward)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Process a refund in a single atomic step — no approval queue.
     *
     * Eligible statuses: confirmed, preparing, ready_for_pickup, out_for_delivery,
     *                    delivered, completed.
     * Requires: payment_status IN ('paid', 'credit')
     * Locks:    One refund per order — any prior refund blocks this permanently.
     *
     * @param PDO $pdo
     * @param int $orderId
     * @param array{
     *     refund_amount?:         float|string,
     *     reason_code?:           string,
     *     reason_notes?:          string,
     *     settlement_reference?:  string,
     *     settlement_proof_url?:  string,
     * } $payload
     * @param int   $adminId
     * @param array{
     *     admin_role?:        string,
     *     admin_permissions?: array<int,string>,
     *     ip_address?:        string,
     * } $context
     * @return array{success: bool, message: string, refund_type?: string, refund_id?: int, refund_number?: string}
     */
    public function processRefund(
        PDO   $pdo,
        int   $orderId,
        array $payload,
        int   $adminId,
        array $context = []
    ): array {
        $adminRole          = (string)($context['admin_role']        ?? '');
        $adminPermissions   = (array) ($context['admin_permissions'] ?? []);
        $ipAddress          = (string)($context['ip_address']        ?? '');
        $adminName          = trim((string)($context['admin_name'] ?? 'Admin'));

        $refundAmount       = round((float)($payload['refund_amount']        ?? 0), 2);
        $reasonCode         = strtoupper(trim((string)($payload['reason_code']       ?? '')));
        $reasonNotes        = trim((string)($payload['reason_notes']      ?? ''));
        $settlementRef      = trim((string)($payload['settlement_reference']  ?? ''));
        $settlementProofUrl = trim((string)($payload['settlement_proof_url']  ?? ''));

        // ── Permission check ───────────────────────────────────────────────
        if ($adminRole !== 'super_admin'
            && !in_array('can_approve_refund', $adminPermissions, true)
            && !in_array('can_force_refund', $adminPermissions, true)
        ) {
            return ['success' => false, 'message' => 'Insufficient permissions to process refunds'];
        }

        // ── Input validation ───────────────────────────────────────────────
        if ($refundAmount <= 0) {
            return ['success' => false, 'message' => 'Refund amount must be greater than zero'];
        }

        $validReasonCodes = array_merge(self::REASON_CODES, [
            'QUALITY_COMPLAINT', 'WRONG_CAKE_DELIVERED', 'DELAYED_DELIVERY',
            'DAMAGED_CAKE', 'CUSTOMER_COMPLAINT', 'DUPLICATE_ORDER',
            'KITCHEN_ISSUE', 'STAFF_ISSUE', 'FRAUD_PREVENTION', 'ADMIN_ADJUSTMENT',
        ]);
        if ($reasonCode !== '' && !in_array($reasonCode, $validReasonCodes, true)) {
            return ['success' => false, 'message' => 'Invalid reason code: ' . $reasonCode];
        }

        if ($reasonCode === 'OTHER' && $reasonNotes === '') {
            return ['success' => false, 'message' => 'Internal notes are required when reason is "Other"'];
        }

        if ($settlementProofUrl !== ''
            && !str_starts_with($settlementProofUrl, '/uploads/')
            && !str_starts_with($settlementProofUrl, 'uploads/')
        ) {
            return ['success' => false, 'message' => 'Invalid settlement proof URL'];
        }

        // ── Load order with row-level lock ─────────────────────────────────
        $pdo->beginTransaction();
        try {
            $orderStmt = $pdo->prepare(
                'SELECT id, order_number, order_status, payment_status, grand_total,
                        total_refunded, refund_status
                 FROM orders
                 WHERE id = :id
                 LIMIT 1
                 FOR UPDATE'
            );
            $orderStmt->execute(['id' => $orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if ($order === false) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Order not found'];
            }

            $orderStatus   = (string)($order['order_status']    ?? '');
            $paymentStatus = (string)($order['payment_status']  ?? '');
            $grandTotal    = (float) ($order['grand_total']      ?? 0);
            $totalRefunded = (float) ($order['total_refunded']   ?? 0);

            // ── Eligibility checks ─────────────────────────────────────────
            $eligibleStatuses = [
                'confirmed', 'preparing', 'ready_for_pickup',
                'out_for_delivery', 'delivered', 'completed',
            ];
            if (!in_array($orderStatus, $eligibleStatuses, true)) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Order is not eligible for refund (current status: ' . $orderStatus . ')',
                ];
            }

            if (!in_array($paymentStatus, ['paid', 'credit'], true)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Only paid orders can be refunded'];
            }

            // ── One-refund lock ────────────────────────────────────────────
            $alreadyRefundedStatuses = ['partially_refunded', 'fully_refunded', 'refunded'];
            if (in_array($orderStatus, $alreadyRefundedStatuses, true) || $totalRefunded > 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'This order has already been refunded. No further refunds are allowed.'];
            }

            // ── Amount cap ─────────────────────────────────────────────────
            if ($refundAmount > $grandTotal) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Refund amount (' . number_format($refundAmount, 2) . ') cannot exceed order total (' . number_format($grandTotal, 2) . ')',
                ];
            }

            // ── Determine refund type ──────────────────────────────────────
            $isFullRefund      = ($refundAmount >= $grandTotal);
            $refundType        = $isFullRefund ? 'full' : 'partial';
            $newOrderStatus    = $isFullRefund ? 'fully_refunded' : 'partially_refunded';
            $newPaymentStatus  = $isFullRefund ? 'refunded' : 'partially_refunded';
            $newRefundStatus   = $isFullRefund ? 'fully_refunded' : 'partially_refunded';
            $refundNumber      = $this->generateRefundNumber();

            // ── INSERT refund_transactions (schema-aware) ─────────────────
            $refundInsertColumns = [
                'order_id',
                'refund_number',
                'refund_type',
                'reason_code',
                'reason_notes',
                'requested_amount',
                'approved_amount',
                'status',
                'requested_by_admin_id',
                'approved_by_admin_id',
                'previous_order_status',
                'requested_at',
                'approved_at',
                'processed_at',
            ];

            $refundInsertValues = [
                ':order_id',
                ':refund_number',
                ':refund_type',
                ':reason_code',
                ':reason_notes',
                ':requested_amount',
                ':approved_amount',
                '"processed"',
                ':requested_by_admin_id',
                ':approved_by_admin_id',
                ':previous_status',
                'NOW()',
                'NOW()',
                'NOW()',
            ];

            $refundInsertParams = [
                'order_id'         => $orderId,
                'refund_number'    => $refundNumber,
                'refund_type'      => $refundType,
                'reason_code'      => $reasonCode !== '' ? $reasonCode : 'OTHER',
                'reason_notes'     => $reasonNotes !== '' ? $reasonNotes : null,
                'requested_amount' => $refundAmount,
                'approved_amount'  => $refundAmount,
                'requested_by_admin_id' => $adminId > 0 ? $adminId : null,
                'approved_by_admin_id'  => $adminId > 0 ? $adminId : null,
                'previous_status'  => $orderStatus,
            ];

            if ($this->tableHasColumn($pdo, 'refund_transactions', 'settlement_reference')) {
                $refundInsertColumns[] = 'settlement_reference';
                $refundInsertValues[] = ':settlement_ref';
                $refundInsertParams['settlement_ref'] = $settlementRef !== '' ? $settlementRef : null;
            }

            if ($this->tableHasColumn($pdo, 'refund_transactions', 'settlement_proof_url')) {
                $refundInsertColumns[] = 'settlement_proof_url';
                $refundInsertValues[] = ':settlement_proof';
                $refundInsertParams['settlement_proof'] = $settlementProofUrl !== '' ? $settlementProofUrl : null;
            } elseif ($this->tableHasColumn($pdo, 'refund_transactions', 'settlement_proof')) {
                $refundInsertColumns[] = 'settlement_proof';
                $refundInsertValues[] = ':settlement_proof';
                $refundInsertParams['settlement_proof'] = $settlementProofUrl !== '' ? $settlementProofUrl : null;
            }

            $insertStmt = $pdo->prepare(
                'INSERT INTO refund_transactions (' . implode(', ', $refundInsertColumns) . ') VALUES (' . implode(', ', $refundInsertValues) . ')'
            );
            $insertStmt->execute($refundInsertParams);
            $refundId = (int)$pdo->lastInsertId();

            // ── INSERT audit log ───────────────────────────────────────────
            $this->writeApprovalLog($pdo, $refundId, 'processed', $adminId, $adminRole, $ipAddress, $reasonNotes !== '' ? $reasonNotes : null);

            // ── UPDATE orders ──────────────────────────────────────────────
            $updateAssignments = [
                'total_refunded = :refund_amount',
                'refund_amount = :refund_amount',
                'refund_status = :refund_status',
                'refund_reason = :reason_code',
                'refund_notes = :reason_notes',
                'refunded_by_admin_id = :admin_id',
                'refunded_at = NOW()',
                'payment_status = :new_payment_status',
            ];
            $updateParams = [
                'refund_amount'        => $refundAmount,
                'refund_status'        => $newRefundStatus,
                'reason_code'          => $reasonCode !== '' ? $reasonCode : 'OTHER',
                'reason_notes'         => $reasonNotes !== '' ? $reasonNotes : null,
                'admin_id'             => $adminId > 0 ? $adminId : null,
                'new_payment_status'   => $newPaymentStatus,
                'id'                   => $orderId,
            ];

            if ($this->tableHasColumn($pdo, 'orders', 'settlement_reference')) {
                $updateAssignments[] = 'settlement_reference = :settlement_ref';
                $updateParams['settlement_ref'] = $settlementRef !== '' ? $settlementRef : null;
            }
            if ($this->tableHasColumn($pdo, 'orders', 'settlement_proof')) {
                $updateAssignments[] = 'settlement_proof = :settlement_proof';
                $updateParams['settlement_proof'] = $settlementProofUrl !== '' ? $settlementProofUrl : null;
            }

            $updateOrderStmt = $pdo->prepare(
                'UPDATE orders SET ' . implode(', ', $updateAssignments) . ' WHERE id = :id'
            );
            $updateOrderStmt->execute($updateParams);

            // ── Transition order_status via OrderStateManager ──────────────
            // Run inside the same transaction so a state-machine failure rolls everything back.
            $stateManager = new OrderStateManager();
            $transition = $stateManager->transition($pdo, $orderId, $newOrderStatus, $adminId, [
                'admin_role'        => $adminRole,
                'admin_permissions' => $adminPermissions,
                'ip_address'        => $ipAddress,
                'reason'            => ucfirst($refundType) . ' refund #' . $refundNumber . ': ' . ($reasonCode ?: 'no code'),
                'metadata'          => [
                    'refund_transaction_id' => $refundId,
                    'refund_type'           => $refundType,
                    'refund_amount'         => $refundAmount,
                ],
                'skip_permission'   => true, // permission already checked above
            ]);

            if (!$transition['success']) {
                $pdo->rollBack();
                error_log('[RefundService::processRefund] State machine rejected transition for order #' . $orderId . ': ' . $transition['message']);
                return ['success' => false, 'message' => 'Could not update order status: ' . $transition['message']];
            }

            $pdo->commit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[RefundService::processRefund] PDO error on order #' . $orderId . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error while processing refund'];
        }

        $this->postRefundToFinancialEngine(
            $orderId,
            $orderNumber,
            $refundId,
            $refundAmount,
            $paymentMethod,
            $refundType,
            $adminId,
            $adminName,
            'RefundService::processRefund'
        );

        return [
            'success'       => true,
            'message'       => ucfirst($refundType) . ' refund of ₹' . number_format($refundAmount, 2) . ' processed successfully',
            'refund_type'   => $refundType,
            'refund_id'     => $refundId,
            'refund_number' => $refundNumber,
            'order_status'  => $newOrderStatus,
        ];
    }

    /**
     * Approve a pending refund request and process the refund.
     *
     * @param PDO   $pdo
     * @param int   $refundTransactionId
     * @param float $approvedAmount  Must be > 0 and <= requested_amount
     * @param int   $adminId
     * @param array{
     *     admin_role?:        string,
     *     admin_permissions?: array<int,string>,
     *     ip_address?:        string,
     *     notes?:             string,
     * } $context
     * @return array{success: bool, message: string, refund_type?: string}
     */
    public function approve(
        PDO   $pdo,
        int   $refundTransactionId,
        float $approvedAmount,
        int   $adminId,
        array $context = []
    ): array {
        $adminRole        = (string)($context['admin_role']        ?? '');
        $adminPermissions = (array) ($context['admin_permissions'] ?? []);
        $ipAddress        = (string)($context['ip_address']        ?? '');
        $notes            = (string)($context['notes']             ?? '');

        if ($approvedAmount <= 0) {
            return ['success' => false, 'message' => 'Approved amount must be greater than zero'];
        }

        // Permission check
        if ($adminRole !== 'super_admin'
            && !in_array('can_approve_refund', $adminPermissions, true)
            && !in_array('can_force_refund', $adminPermissions, true)
        ) {
            return ['success' => false, 'message' => 'Insufficient permissions to approve refunds'];
        }

        // Load refund transaction
        $refundStmt = $pdo->prepare(
            'SELECT rt.id, rt.order_id, rt.requested_amount, rt.status, rt.refund_number,
                rt.requested_by_admin_id,
                    o.grand_total, o.delivery_fee, o.refund_amount AS already_refunded
             FROM refund_transactions rt
             JOIN orders o ON o.id = rt.order_id
             WHERE rt.id = :id
             LIMIT 1'
        );
        $refundStmt->execute(['id' => $refundTransactionId]);
        $refund = $refundStmt->fetch(PDO::FETCH_ASSOC);

        if ($refund === false) {
            return ['success' => false, 'message' => 'Refund transaction not found'];
        }
        if ((string)($refund['status'] ?? '') !== 'pending_approval') {
            return ['success' => false, 'message' => 'Refund is not in pending_approval status'];
        }

        $requestedByAdminId = (int)($refund['requested_by_admin_id'] ?? 0);
        if ($requestedByAdminId <= 0) {
            return ['success' => false, 'message' => 'Refund request is missing requester identity. Re-submit the refund request.'];
        }
        if ($requestedByAdminId === $adminId) {
            return ['success' => false, 'message' => 'Dual-approval enforced: requester cannot approve/process the same refund.'];
        }

        $requestedAmount = (float)($refund['requested_amount']  ?? 0);
        $grandTotal      = (float)($refund['grand_total']       ?? 0);
        $deliveryFee     = (float)($refund['delivery_fee']      ?? 0);
        $alreadyRefunded = (float)($refund['already_refunded']  ?? 0);
        $orderId         = (int)  ($refund['order_id']          ?? 0);

        if ($approvedAmount > $requestedAmount) {
            return [
                'success' => false,
                'message' => 'Approved amount (' . $approvedAmount . ') cannot exceed requested amount (' . $requestedAmount . ')',
            ];
        }

        $refundableBalance = round($grandTotal - $deliveryFee - $alreadyRefunded, 2);
        if ($approvedAmount > $refundableBalance) {
            return [
                'success' => false,
                'message' => 'Approved amount exceeds refundable balance (' . $refundableBalance . ')',
            ];
        }

        // Mark as approved
        $pdo->beginTransaction();
        try {
            $approveStmt = $pdo->prepare(
                'UPDATE refund_transactions
                 SET status = "approved",
                     approved_amount = :amount,
                     approved_by_admin_id = :admin_id,
                     approved_at = NOW()
                 WHERE id = :id AND status = "pending_approval"'
            );
            $approveStmt->execute([
                'amount'   => $approvedAmount,
                'admin_id' => $adminId > 0 ? $adminId : null,
                'id'       => $refundTransactionId,
            ]);

            if ($approveStmt->rowCount() === 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Refund was already processed or not found'];
            }

            $this->writeApprovalLog($pdo, $refundTransactionId, 'approved', $adminId, $adminRole, $ipAddress, $notes !== '' ? $notes : null);

            $pdo->commit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[RefundService] approve PDO error on refund #' . $refundTransactionId . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error while approving refund'];
        }

        // Process the approved refund
        return $this->processApprovedRefund($pdo, $refundTransactionId, $orderId, $approvedAmount, $adminId, [
            'admin_role'        => $adminRole,
            'admin_permissions' => $adminPermissions,
            'ip_address'        => $ipAddress,
            'admin_name'        => (string)($context['admin_name'] ?? 'Admin'),
        ]);
    }

    /**
     * Reject a pending refund request and revert order to its pre-request status.
     *
     * @param PDO    $pdo
     * @param int    $refundTransactionId
     * @param string $notes  Rejection reason shown in audit log
     * @param int    $adminId
     * @param array{
     *     admin_role?:        string,
     *     admin_permissions?: array<int,string>,
     *     ip_address?:        string,
     * } $context
     * @return array{success: bool, message: string}
     */
    public function reject(
        PDO    $pdo,
        int    $refundTransactionId,
        string $notes,
        int    $adminId,
        array  $context = []
    ): array {
        $adminRole        = (string)($context['admin_role']        ?? '');
        $adminPermissions = (array) ($context['admin_permissions'] ?? []);
        $ipAddress        = (string)($context['ip_address']        ?? '');

        // Permission check
        if ($adminRole !== 'super_admin'
            && !in_array('can_approve_refund', $adminPermissions, true)
            && !in_array('can_force_refund', $adminPermissions, true)
        ) {
            return ['success' => false, 'message' => 'Insufficient permissions to reject refunds'];
        }

        // Load refund transaction
        $refundStmt = $pdo->prepare(
            'SELECT id, order_id, status, previous_order_status
             FROM refund_transactions
             WHERE id = :id
             LIMIT 1'
        );
        $refundStmt->execute(['id' => $refundTransactionId]);
        $refund = $refundStmt->fetch(PDO::FETCH_ASSOC);

        if ($refund === false) {
            return ['success' => false, 'message' => 'Refund transaction not found'];
        }
        if ((string)($refund['status'] ?? '') !== 'pending_approval') {
            return ['success' => false, 'message' => 'Refund is not in pending_approval status'];
        }

        $orderId              = (int)   ($refund['order_id']              ?? 0);
        $previousOrderStatus  = (string)($refund['previous_order_status'] ?? '');

        $pdo->beginTransaction();
        try {
            // Reject the refund transaction
            $rejectStmt = $pdo->prepare(
                'UPDATE refund_transactions
                 SET status = "rejected"
                 WHERE id = :id AND status = "pending_approval"'
            );
            $rejectStmt->execute(['id' => $refundTransactionId]);

            if ($rejectStmt->rowCount() === 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Refund was already processed or not found'];
            }

            // Reset orders.refund_status back to 'none'
            $resetStmt = $pdo->prepare(
                'UPDATE orders
                 SET refund_status = "none",
                     refund_reason = NULL,
                     refund_notes  = NULL,
                     refund_requested_at = NULL
                 WHERE id = :id'
            );
            $resetStmt->execute(['id' => $orderId]);

            $this->writeApprovalLog($pdo, $refundTransactionId, 'rejected', $adminId, $adminRole, $ipAddress, $notes !== '' ? $notes : null);

            $pdo->commit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[RefundService] reject PDO error on refund #' . $refundTransactionId . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error while rejecting refund'];
        }

        // Revert order status to what it was before the refund request (deterministic snapshot)
        if ($previousOrderStatus !== '') {
            $stateManager = new OrderStateManager();
            $revert = $stateManager->transition($pdo, $orderId, $previousOrderStatus, $adminId, [
                'admin_role'      => $adminRole,
                'ip_address'      => $ipAddress,
                'reason'          => 'Refund request #' . $refundTransactionId . ' rejected — reverting to ' . $previousOrderStatus,
                'metadata'        => ['refund_transaction_id' => $refundTransactionId],
                'skip_permission' => true,
            ]);
            if (!$revert['success']) {
                error_log('[RefundService] Status revert failed after rejection for order #' . $orderId . ': ' . $revert['message']);
            }
        }

        return ['success' => true, 'message' => 'Refund request rejected successfully'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Execute the actual refund after approval.
     * Updates orders, resolves full vs partial, transitions order state.
     *
     * @param array{
     *     admin_role?:        string,
     *     admin_permissions?: array<int,string>,
     *     ip_address?:        string,
     * } $context
     * @return array{success: bool, message: string, refund_type?: string}
     */
    private function processApprovedRefund(
        PDO   $pdo,
        int   $refundTransactionId,
        int   $orderId,
        float $approvedAmount,
        int   $adminId,
        array $context = []
    ): array {
        $adminRole   = (string)($context['admin_role']  ?? '');
        $ipAddress   = (string)($context['ip_address']  ?? '');
        $adminName   = trim((string)($context['admin_name'] ?? 'Admin'));

        // Fetch current refund totals for order
        $totalsStmt = $pdo->prepare(
            'SELECT grand_total, delivery_fee, COALESCE(refund_amount, 0) AS already_refunded, payment_method, order_number
             FROM orders WHERE id = :id LIMIT 1'
        );
        $totalsStmt->execute(['id' => $orderId]);
        $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);

        if ($totals === false) {
            return ['success' => false, 'message' => 'Order not found during refund processing'];
        }

        $grandTotal       = (float)($totals['grand_total']       ?? 0);
        $deliveryFee      = (float)($totals['delivery_fee']      ?? 0);
        $alreadyRefunded  = (float)($totals['already_refunded']  ?? 0);
        $paymentMethod    = (string)($totals['payment_method']   ?? 'upi_manual');
        $orderNumber      = (string)($totals['order_number']     ?? '');
        $newTotalRefunded = round($alreadyRefunded + $approvedAmount, 2);
        $refundableTotal  = round($grandTotal - $deliveryFee, 2);

        // Determine new order states
        $isFullRefund      = ($newTotalRefunded >= $refundableTotal);
        $newOrderStatus    = $isFullRefund ? 'refunded' : 'partially_refunded';
        $newPaymentStatus  = $isFullRefund ? 'refunded' : 'partially_refunded';
        $refundType        = $isFullRefund ? 'full' : 'partial';

        $pdo->beginTransaction();
        try {
            // Update orders refund tracking columns
            $updateOrderStmt = $pdo->prepare(
                'UPDATE orders
                 SET total_refunded              = :total_refunded_amount,
                     refund_amount               = :refund_amount_snapshot,
                     refund_status               = "processed",
                     payment_status              = :payment_status,
                     refunded_at                 = NOW(),
                     refund_approved_by_admin_id = :admin_id
                 WHERE id = :id'
            );
            $updateOrderStmt->execute([
                'total_refunded_amount' => $newTotalRefunded,
                'refund_amount_snapshot' => $newTotalRefunded,
                'payment_status' => $newPaymentStatus,
                'admin_id'       => $adminId > 0 ? $adminId : null,
                'id'             => $orderId,
            ]);

            // Mark refund_transaction as processed
            $processTxStmt = $pdo->prepare(
                'UPDATE refund_transactions
                 SET status = "processed", processed_at = NOW()
                 WHERE id = :id'
            );
            $processTxStmt->execute(['id' => $refundTransactionId]);

            $pdo->commit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[RefundService] processRefund PDO error on order #' . $orderId . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error while processing refund'];
        }

        // Transition order status via state machine
        $stateManager = new OrderStateManager();
        $transition = $stateManager->transition($pdo, $orderId, $newOrderStatus, $adminId, [
            'admin_role'      => $adminRole,
            'ip_address'      => $ipAddress,
            'reason'          => ucfirst($refundType) . ' refund of ' . $approvedAmount . ' processed',
            'metadata'        => [
                'refund_transaction_id' => $refundTransactionId,
                'refund_type'           => $refundType,
                'approved_amount'       => $approvedAmount,
                'total_refunded'        => $newTotalRefunded,
            ],
            'skip_permission' => true,
        ]);

        if (!$transition['success']) {
            error_log('[RefundService] State machine failed after processRefund for order #' . $orderId . ': ' . $transition['message']);
        }

        $this->postRefundToFinancialEngine(
            $orderId,
            $orderNumber,
            $refundTransactionId,
            $approvedAmount,
            $paymentMethod,
            $refundType,
            $adminId,
            $adminName,
            'RefundService::processApprovedRefund'
        );

        return [
            'success'     => true,
            'message'     => ucfirst($refundType) . ' refund of ' . number_format($approvedAmount, 2) . ' processed successfully',
            'refund_type' => $refundType,
        ];
    }

    /**
     * Write an entry to refund_approval_logs.
     * Called inside an open transaction.
     */
    private function writeApprovalLog(
        PDO     $pdo,
        int     $refundTransactionId,
        string  $action,
        int     $adminId,
        string  $adminRole,
        string  $ipAddress,
        ?string $notes
    ): void {
        $logStmt = $pdo->prepare(
            'INSERT INTO refund_approval_logs
                 (refund_transaction_id, action, performed_by_admin_id, admin_role, ip_address, notes)
             VALUES
                 (:refund_id, :action, :admin_id, :admin_role, :ip, :notes)'
        );
        $logStmt->execute([
            'refund_id'  => $refundTransactionId,
            'action'     => $action,
            'admin_id'   => $adminId > 0 ? $adminId : null,
            'admin_role' => $adminRole !== '' ? $adminRole : null,
            'ip'         => $ipAddress !== '' ? $ipAddress : null,
            'notes'      => $notes,
        ]);
    }

    /**
     * Generate a unique refund reference number, e.g. RFD-20260527-A3B9F210.
     */
    private function generateRefundNumber(): string
    {
        return 'RFD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function postRefundToFinancialEngine(
        int $orderId,
        string $orderNumber,
        int $refundTransactionId,
        float $amount,
        string $paymentMethod,
        string $refundType,
        int $adminId,
        string $adminName,
        string $sourceReference
    ): void {
        $engine = new FinancialTransactionEngine();
        $postResult = $engine->recordRefundProcessed([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'refund_transaction_id' => $refundTransactionId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'refund_type' => $refundType,
            'source_reference' => $sourceReference,
            'idempotency_key' => 'refund-processed:' . $refundTransactionId . ':' . number_format($amount, 2, '.', ''),
            'admin_id' => $adminId,
            'admin_name' => $adminName !== '' ? $adminName : 'Admin',
            'narration' => ucfirst($refundType) . ' refund processed for order #' . ($orderNumber !== '' ? $orderNumber : (string)$orderId),
        ]);
        if (!$postResult['success']) {
            error_log('[RefundService][fte] ' . $postResult['message']);
        }
    }

    /**
     * Read accounting_lock_days from settings table.
     * Falls back to 30 if not set.
     */
    private function getAccountingLockDays(PDO $pdo): int
    {
        $stmt = $pdo->prepare(
            "SELECT setting_value FROM settings WHERE setting_key = 'accounting_lock_days' LIMIT 1"
        );
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val === false || (int)$val <= 0) {
            return 30;
        }
        return (int)$val;
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnPresence)) {
            return self::$columnPresence[$key];
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        self::$columnPresence[$key] = ((int)$stmt->fetchColumn()) > 0;
        return self::$columnPresence[$key];
    }
}
