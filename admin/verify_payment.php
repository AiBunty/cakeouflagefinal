<?php
declare(strict_types=1);

/**
 * verify_payment.php  — POST handler for Payment Verification queue.
 *
 * Actions: approve | reject
 *
 * approve:
 *   1. Validate CSRF + order is still pending/under_review
 *   2. Call PaymentSplitService::recordSplit() → posts GL, marks payment_transactions verified
 *   3. UPDATE orders: payment_status='paid', order_status='confirmed', payment_confirmed_at, payment_confirmed_by_admin_id
 *   4. Queue WhatsApp notification (event_key: payment_confirmed)
 *   5. Redirect → payment_verification.php?msg=...
 *
 * reject:
 *   1. Validate CSRF + order is still pending/under_review
 *   2. UPDATE orders: payment_status='rejected', order_status='cancelled'
 *   3. Queue WhatsApp notification (event_key: payment_rejected)
 *   4. Redirect → payment_verification.php?msg=...
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../app/bootstrap.php';

require_admin_permission('order_edit');

// ── Only accept POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: payment_verification.php');
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────
if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    header('Location: payment_verification.php?error=' . rawurlencode('Invalid security token. Please try again.'));
    exit;
}

use App\Core\Database;
use App\Services\PaymentSplitService;

$db      = Database::getInstance();
$action  = trim((string)($_POST['action'] ?? ''));
$orderId = (int)($_POST['order_id'] ?? 0);
$rejNote = trim((string)($_POST['rejection_note'] ?? ''));

if (!in_array($action, ['approve', 'reject'], true) || $orderId <= 0) {
    header('Location: payment_verification.php?error=' . rawurlencode('Invalid request.'));
    exit;
}

// ── Load order ────────────────────────────────────────────────────────
$order = $db->fetchOne(
    "SELECT id, order_number, customer_name, customer_phone, customer_phone_e164,
            payment_status, payment_method, order_status, order_mode,
            COALESCE(revised_grand_total, grand_total) AS effective_total,
            grand_total
     FROM orders
     WHERE id = :id AND is_archived = 0
     LIMIT 1",
    ['id' => $orderId]
);

if ($order === null) {
    header('Location: payment_verification.php?error=' . rawurlencode('Order not found.'));
    exit;
}

// ── Guard: only pending/under_review allowed ──────────────────────────
$allowedStatuses = ['pending', 'under_review'];
if (!in_array((string)$order['payment_status'], $allowedStatuses, true)) {
    header('Location: payment_verification.php?error=' . rawurlencode(
        'Order #' . $order['order_number'] . ' is already ' . $order['payment_status'] . '. No change made.'
    ));
    exit;
}

$adminId   = (int)($_SESSION['admin'] ?? 0);
$adminName = (string)($_SESSION['admin_name'] ?? 'Admin');
$orderNo   = (string)$order['order_number'];

// ── APPROVE ───────────────────────────────────────────────────────────
if ($action === 'approve') {

    $splitSvc = new PaymentSplitService($db);
    $result   = $splitSvc->recordSplit(
        $orderId,
        [
            [
                'method'          => 'upi',          // maps to payment_transactions ENUM
                'amount'          => (float)$order['effective_total'],
                'reference'       => '',              // no text UTR captured on this flow
                'idempotency_key' => 'verify:approve:' . $orderId . ':' . (int)$order['effective_total'],
            ],
        ],
        $adminId,
        [
            'admin_name'     => $adminName,
            'source_channel' => 'payment_verification',
            'business_date'  => date('Y-m-d'),
        ]
    );

    if (!$result['success']) {
        header('Location: payment_verification.php?error=' . rawurlencode('GL posting failed: ' . ($result['message'] ?? 'Unknown error')));
        exit;
    }

    // Update order: paid + confirmed
    $db->execute(
        "UPDATE orders
            SET payment_status               = 'paid',
                order_status                 = 'confirmed',
                payment_confirmed_at         = NOW(),
                payment_confirmed_by_admin_id = :admin_id,
                updated_at                   = NOW()
          WHERE id = :id",
        ['admin_id' => $adminId, 'id' => $orderId]
    );

    // Queue WhatsApp notification (non-fatal)
    _pv_queue_notification($db, $orderId, $order, 'payment_confirmed', [
        'order_id'      => $orderId,
        'order_number'  => $orderNo,
        'customer_name' => (string)$order['customer_name'],
        'amount'        => (float)$order['effective_total'],
        'admin_name'    => $adminName,
    ]);

    header('Location: payment_verification.php?msg=' . rawurlencode('Payment approved for order #' . $orderNo . '. GL posted and order confirmed.'));
    exit;
}

// ── REJECT ────────────────────────────────────────────────────────────
if ($action === 'reject') {

    $db->execute(
        "UPDATE orders
            SET payment_status = 'rejected',
                order_status   = 'cancelled',
                admin_note     = CONCAT(COALESCE(admin_note, ''), :note),
                updated_at     = NOW()
          WHERE id = :id",
        [
            'note' => $rejNote !== '' ? ('[Payment rejected by admin: ' . $rejNote . '] ') : '[Payment rejected by admin] ',
            'id'   => $orderId,
        ]
    );

    // Queue WhatsApp notification (non-fatal)
    _pv_queue_notification($db, $orderId, $order, 'payment_rejected', [
        'order_id'      => $orderId,
        'order_number'  => $orderNo,
        'customer_name' => (string)$order['customer_name'],
        'amount'        => (float)$order['effective_total'],
        'rejection_note'=> $rejNote,
        'admin_name'    => $adminName,
    ]);

    header('Location: payment_verification.php?msg=' . rawurlencode('Payment rejected for order #' . $orderNo . '.'));
    exit;
}

// Should never reach here
header('Location: payment_verification.php');
exit;

// ── Helper: queue a WhatsApp communication log ────────────────────────
/**
 * @param array<string,mixed> $order
 * @param array<string,mixed> $context
 */
function _pv_queue_notification(
    Database $db,
    int $orderId,
    array $order,
    string $eventKey,
    array $context
): void {
    $phone = (string)($order['customer_phone_e164'] ?: ($order['customer_phone'] ?? ''));
    if ($phone === '') {
        return;
    }

    $payloadJson = (string)json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payloadJson === '') {
        $payloadJson = '{}';
    }

    try {
        $logId = (int)$db->insert(
            'INSERT INTO communication_logs
                 (order_id, channel, event_key, recipient, status, payload_json)
             VALUES
                 (:order_id, "whatsapp", :event_key, :recipient, "queued", :payload_json)',
            [
                'order_id'     => $orderId,
                'event_key'    => $eventKey,
                'recipient'    => $phone,
                'payload_json' => $payloadJson,
            ]
        );

        if ($logId > 0) {
            $db->insert(
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
        // Non-fatal: payment action already committed
        error_log('[verify_payment] notification queue failed: ' . $e->getMessage());
    }
}
