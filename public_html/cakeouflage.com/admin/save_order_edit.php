<?php
define('SKIP_AUTH_ORDER_AUTO_HANDLER', true);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('order_edit');
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}

$id                  = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$customerPhone       = trim((string)($_POST['customer_phone'] ?? ''));
$adminNote           = trim((string)($_POST['admin_note'] ?? ''));
$scheduledSlotLabel  = trim((string)($_POST['scheduled_slot_label'] ?? ''));
$redirectTo          = trim((string)($_POST['redirect_to'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    die('Invalid order');
}

// Sanitise phone: allow digits, spaces, +, -, ( ) only
$customerPhone = preg_replace('/[^0-9 +\-()]/', '', $customerPhone);
$customerPhone = substr($customerPhone, 0, 20);
$adminNote     = substr($adminNote, 0, 1000);
$scheduledSlotLabel = substr($scheduledSlotLabel, 0, 100);

$stmt = $conn->prepare(
    'UPDATE orders SET customer_phone = ?, admin_note = ?, scheduled_slot_label = ? WHERE id = ? LIMIT 1'
);
if (!$stmt) {
    http_response_code(500);
    die('DB error');
}
$stmt->bind_param('sssi', $customerPhone, $adminNote, $scheduledSlotLabel, $id);
if (!$stmt->execute()) {
    error_log('[save_order_edit] execute failed: ' . $stmt->error);
    http_response_code(500);
    die('Update failed');
}
$stmt->close();

// Safe redirect
$safeRedirects = array('orders.php', 'order_details.php');
$parts = parse_url($redirectTo);
$path = basename((string)($parts['path'] ?? ''));
if (!in_array($path, $safeRedirects, true)) {
    $path = 'orders.php';
}

if ($path === 'order_details.php') {
    $target = 'order_details.php?id=' . $id . '&order_updated=' . $id;
} else {
    $query = isset($parts['query']) ? trim((string)$parts['query']) : '';
    if ($query !== '') {
        parse_str($query, $params);
        if (is_array($params)) {
            unset($params['order_updated']);
            $query = http_build_query($params);
        }
    }
    $base = $path . ($query !== '' ? '?' . $query : '');
    $sep = strpos($base, '?') === false ? '?' : '&';
    $target = $base . $sep . http_build_query([
        'action_order_id' => $id,
        'action_status' => 'edited',
        'action_level' => 'success',
        'action_message' => 'Order details updated successfully.',
    ]);
}

header('Location: ' . $target);
exit;
