<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * OrderStateManager — single source of truth for order governance.
 *
 * All order-status writes must route through this service. It enforces
 * transition legality, role permissions, finance-closure constraints,
 * and writes immutable status/audit logs for every state change.
 */
final class OrderStateManager
{
    /**
     * Allowed target states for each source state.
     * States absent from this map, or mapping to [], are terminal.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITION_MAP = [
        // Pre-payment
        'pending_payment'      => ['payment_under_review', 'awaiting_confirmation', 'confirmed', 'cancelled', 'rejected'],
        'payment_under_review' => ['pending_payment', 'awaiting_confirmation', 'confirmed', 'cancelled', 'rejected'],
        'awaiting_confirmation'=> ['confirmed', 'cancelled', 'rejected'],

        // Post-payment lifecycle
        'confirmed'            => ['preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'partially_refunded', 'fully_refunded'],
        'preparing'            => ['ready_for_pickup', 'out_for_delivery', 'delivered', 'partially_refunded', 'fully_refunded'],
        'ready_for_pickup'     => ['delivered', 'completed', 'partially_refunded', 'fully_refunded'],
        'out_for_delivery'     => ['delivered', 'completed', 'partially_refunded', 'fully_refunded'],
        'delivered'            => ['completed', 'partially_refunded', 'fully_refunded'],
        'completed'            => ['partially_refunded', 'fully_refunded'],

        // Closures
        'cancelled'            => [],
        'rejected'             => [],
        'partially_refunded'   => [],
        'fully_refunded'       => [],

        // Legacy compat statuses
        'refund_requested'     => ['partially_refunded', 'fully_refunded', 'rejected'],
        'refunded'             => [],
    ];

    /** @var array<int, string> */
    private const TERMINAL_STATES = ['cancelled', 'rejected', 'partially_refunded', 'fully_refunded', 'refunded'];

    /** @var array<int, string> */
    private const REFUND_FINAL_STATES = ['partially_refunded', 'fully_refunded', 'refunded'];

    /** @var array<int, string> */
    private const UNPAID_ORDER_STATES = ['pending_payment', 'payment_under_review', 'awaiting_confirmation'];

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param array{
     *   admin_role?: string,
     *   admin_permissions?: array<int,string>,
     *   ip_address?: string,
     *   reason?: string,
     *   metadata?: array<string,mixed>,
     *   skip_permission?: bool,
     * } $context
     */
    public function transition(
        PDO    $pdo,
        int    $orderId,
        string $newStatus,
        int    $adminId,
        array  $context = []
    ): array {
        $adminRole        = (string)($context['admin_role']        ?? '');
        $adminPermissions = (array) ($context['admin_permissions'] ?? []);
        $ipAddress        = (string)($context['ip_address']        ?? '');
        $reason           = (string)($context['reason']            ?? '');
        $metadata         = (array) ($context['metadata']          ?? []);
        $skipPermission   = (bool)  ($context['skip_permission']   ?? false);

        $pdo->beginTransaction();
        try {
            // Load order with row-level lock
            $stmt = $pdo->prepare(
                'SELECT id, order_status, payment_status, fulfilment_mode
                 FROM orders
                 WHERE id = :id
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order === false) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Order not found', 'previous_status' => ''];
            }

            $previousStatus = (string)($order['order_status'] ?? '');
            $currentPayment = (string)($order['payment_status'] ?? 'pending');
            $fulfilmentMode = strtolower(trim((string)($order['fulfilment_mode'] ?? 'delivery')));

            if ($previousStatus === $newStatus) {
                $pdo->rollBack();
                return [
                    'success'         => true,
                    'message'         => 'Order is already in that status',
                    'previous_status' => $previousStatus,
                ];
            }

            $newStatus = strtolower(trim($newStatus));

            if (!$this->isKnownState($newStatus)) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Unknown target status: ' . $newStatus,
                    'previous_status' => $previousStatus,
                ];
            }

            $allowed = self::TRANSITION_MAP[$previousStatus] ?? null;
            if ($allowed === null) {
                $pdo->rollBack();
                return [
                    'success'         => false,
                    'message'         => 'Unknown source status: ' . $previousStatus,
                    'previous_status' => $previousStatus,
                ];
            }
            if (!in_array($newStatus, $allowed, true)) {
                $pdo->rollBack();
                return [
                    'success'         => false,
                    'message'         => $this->buildTransitionErrorMessage($previousStatus, $newStatus, $currentPayment),
                    'previous_status' => $previousStatus,
                ];
            }

            // Fulfilment routing constraints
            if ($fulfilmentMode === 'pickup' && $newStatus === 'out_for_delivery') {
                $pdo->rollBack();
                return [
                    'success'         => false,
                    'message'         => 'Pickup orders cannot be sent out for delivery',
                    'previous_status' => $previousStatus,
                ];
            }
            if (in_array($fulfilmentMode, ['delivery', 'custom_delivery'], true)
                && $newStatus === 'ready_for_pickup'
            ) {
                $pdo->rollBack();
                return [
                    'success'         => false,
                    'message'         => 'Delivery orders cannot be set to ready-for-pickup',
                    'previous_status' => $previousStatus,
                ];
            }

            if ($newStatus === 'cancelled' && $this->isPaidState($currentPayment, $previousStatus)) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Confirmed or delivered orders cannot be cancelled. Use refund workflow.',
                    'previous_status' => $previousStatus,
                ];
            }

            if (in_array($previousStatus, self::REFUND_FINAL_STATES, true)) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Refunded orders are final and cannot be modified.',
                    'previous_status' => $previousStatus,
                ];
            }

            if (!$skipPermission
                && !$this->hasPermission($previousStatus, $newStatus, $adminRole, $adminPermissions)
            ) {
                $pdo->rollBack();
                return [
                    'success'         => false,
                    'message'         => 'Insufficient permissions for this status transition',
                    'previous_status' => $previousStatus,
                ];
            }

            // Apply transition
            $updateStmt = $pdo->prepare(
                'UPDATE orders SET order_status = :status, updated_at = NOW() WHERE id = :id'
            );
            $updateStmt->execute(['status' => $newStatus, 'id' => $orderId]);

            // Immutable history
            $histStmt = $pdo->prepare(
                'INSERT INTO order_status_history
                     (order_id, previous_status, new_status, changed_by_admin_id,
                      admin_role, ip_address, reason, metadata)
                 VALUES
                     (:order_id, :previous_status, :new_status, :admin_id,
                      :admin_role, :ip_address, :reason, :metadata)'
            );
            $histStmt->execute([
                'order_id'        => $orderId,
                'previous_status' => $previousStatus !== '' ? $previousStatus : null,
                'new_status'      => $newStatus,
                'admin_id'        => $adminId > 0 ? $adminId : null,
                'admin_role'      => $adminRole  !== '' ? $adminRole  : null,
                'ip_address'      => $ipAddress  !== '' ? $ipAddress  : null,
                'reason'          => $reason     !== '' ? $reason     : null,
                'metadata'        => !empty($metadata)
                                     ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                                     : null,
            ]);

            $this->writeOrderAudit($pdo, [
                'order_id' => $orderId,
                'action_type' => 'status_transition',
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'payment_status' => $currentPayment,
                'admin_id' => $adminId,
                'admin_role' => $adminRole,
                'ip_address' => $ipAddress,
                'message' => 'Order status changed',
                'metadata' => $metadata,
            ]);

            $pdo->commit();

            return [
                'success'         => true,
                'message'         => 'Order status updated to ' . $newStatus,
                'previous_status' => $previousStatus,
            ];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[OrderStateManager] PDO error on order #' . $orderId . ': ' . $e->getMessage());
            return [
                'success'         => false,
                'message'         => 'Database error during status transition',
                'previous_status' => '',
            ];
        }
    }

    /**
     * Returns all states the given status can legally transition to.
     * Use this to build allowed-action dropdowns in admin UI.
     *
     * @return array<int, string>
     */
    public function getAllowedTransitions(string $fromStatus): array
    {
        return self::TRANSITION_MAP[$fromStatus] ?? [];
    }

    /** Returns true if a status is terminal (no further transitions possible). */
    public function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATES, true);
    }

    /**
     * @return array<string,mixed>
     */
    public function getAllowedActions(string $orderStatus, string $paymentStatus): array
    {
        $orderStatus = strtolower(trim($orderStatus));
        $paymentStatus = strtolower(trim($paymentStatus));

        $refundFinal = in_array($orderStatus, self::REFUND_FINAL_STATES, true)
            || in_array($paymentStatus, ['refunded', 'partially_refunded'], true);

        $isUnpaid = in_array($orderStatus, self::UNPAID_ORDER_STATES, true)
            || in_array($paymentStatus, ['pending', 'under_review', 'rejected', 'failed'], true);

        $isPostPayment = in_array($orderStatus, ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed'], true)
            || in_array($paymentStatus, ['paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded'], true);

        $canCancel = $isUnpaid && !$refundFinal && in_array('cancelled', $this->getAllowedTransitions($orderStatus), true);

        $canRefund = !$refundFinal
            && in_array($orderStatus, ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed'], true)
            && $isPostPayment;

        return [
            'can_confirm_payment' => $isUnpaid && !$refundFinal,
            'can_cancel' => $canCancel,
            'can_refund' => $canRefund,
            'can_mark_preparing' => in_array('preparing', $this->getAllowedTransitions($orderStatus), true),
            'can_mark_delivered' => in_array('delivered', $this->getAllowedTransitions($orderStatus), true),
            'can_mark_completed' => in_array('completed', $this->getAllowedTransitions($orderStatus), true),
            'is_refund_final' => $refundFinal,
            'is_financially_locked' => $refundFinal,
            'finance_badge' => $this->financeBadge($orderStatus, $paymentStatus),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function writeOrderAudit(PDO $pdo, array $payload): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO order_audit_logs
                    (order_id, action_type, previous_status, new_status, payment_status, admin_id, admin_role, ip_address, message, metadata, created_at)
                 VALUES
                    (:order_id, :action_type, :previous_status, :new_status, :payment_status, :admin_id, :admin_role, :ip_address, :message, :metadata, NOW())'
            );
            $stmt->execute([
                'order_id' => (int)($payload['order_id'] ?? 0),
                'action_type' => (string)($payload['action_type'] ?? 'state_event'),
                'previous_status' => $this->nullableString((string)($payload['previous_status'] ?? '')),
                'new_status' => $this->nullableString((string)($payload['new_status'] ?? '')),
                'payment_status' => $this->nullableString((string)($payload['payment_status'] ?? '')),
                'admin_id' => (($payload['admin_id'] ?? null) !== null && (int)$payload['admin_id'] > 0) ? (int)$payload['admin_id'] : null,
                'admin_role' => $this->nullableString((string)($payload['admin_role'] ?? '')),
                'ip_address' => $this->nullableString((string)($payload['ip_address'] ?? '')),
                'message' => $this->nullableString((string)($payload['message'] ?? '')),
                'metadata' => !empty($payload['metadata']) ? json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[OrderStateManager] order_audit_logs write skipped: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Permission gate: checks whether the given admin role + permission set
     * is allowed to perform the requested transition.
     *
     * @param array<int, string> $permissions
     */
    private function hasPermission(
        string $fromStatus,
        string $toStatus,
        string $role,
        array  $permissions
    ): bool {
        if ($role === 'super_admin') {
            return true;
        }

        switch ($toStatus) {
            case 'cancelled':
                if (in_array($fromStatus, self::UNPAID_ORDER_STATES, true)) {
                    return in_array('can_cancel_unpaid_orders', $permissions, true)
                        || in_array('order_refund', $permissions, true);
                }
                return in_array('order_refund', $permissions, true);

            case 'rejected':
                return in_array('order_refund', $permissions, true);

            case 'completed':
                // Closing a delivered order requires explicit permission
                return in_array('can_edit_completed_orders', $permissions, true);

            case 'refund_requested':
                // Legacy path for pre-migration rows only
                return in_array('order_refund', $permissions, true);

            case 'partially_refunded':
            case 'fully_refunded':
            case 'refunded':
                return in_array('can_approve_refund', $permissions, true)
                    || in_array('can_force_refund', $permissions, true);

            default:
                return true;
        }
    }

    private function isKnownState(string $status): bool
    {
        return array_key_exists($status, self::TRANSITION_MAP);
    }

    private function isPaidState(string $paymentStatus, string $orderStatus): bool
    {
        return in_array($paymentStatus, ['paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded'], true)
            || in_array($orderStatus, ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed'], true);
    }

    private function buildTransitionErrorMessage(string $fromStatus, string $toStatus, string $paymentStatus): string
    {
        if ($toStatus === 'cancelled' && $this->isPaidState($paymentStatus, $fromStatus)) {
            return 'Confirmed or delivered orders cannot be cancelled. Use refund workflow.';
        }
        if (in_array($fromStatus, self::REFUND_FINAL_STATES, true)) {
            return 'Refunded orders are final and cannot transition again.';
        }
        return 'Transition not allowed: ' . $fromStatus . ' -> ' . $toStatus;
    }

    private function financeBadge(string $orderStatus, string $paymentStatus): string
    {
        if (in_array($orderStatus, self::REFUND_FINAL_STATES, true) || in_array($paymentStatus, ['refunded', 'partially_refunded'], true)) {
            return 'Refunded';
        }
        if (in_array($paymentStatus, ['pending', 'under_review'], true)) {
            return 'Pending';
        }
        if ($paymentStatus === 'paid') {
            return 'Paid';
        }
        if ($paymentStatus === 'refund_pending') {
            return 'Partial Refund';
        }
        if ($paymentStatus === 'credit') {
            return 'Pending';
        }
        return 'Pending';
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
