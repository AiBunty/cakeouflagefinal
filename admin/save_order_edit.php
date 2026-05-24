<?php
define('SKIP_AUTH_ORDER_AUTO_HANDLER', true);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('order_edit');

use App\Core\Database;
use App\Services\OrderEditService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}

$id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$redirectTo = trim((string)($_POST['redirect_to'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    die('Invalid order');
}

$readLock = Database::getConnection()->prepare('SELECT order_status, payment_status FROM orders WHERE id = :id LIMIT 1');
$readLock->execute([':id' => $id]);
$lockRow = $readLock->fetch(\PDO::FETCH_ASSOC);
if (!$lockRow) {
    http_response_code(404);
    die('Order not found');
}

$lockedPaymentStates = ['paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded'];
if (in_array((string)($lockRow['payment_status'] ?? ''), $lockedPaymentStates, true)) {
    http_response_code(422);
    die('Financial edit lock is active after payment confirmation. Use fulfillment, notes, or refund workflow.');
}

$payload = [
    'customer_phone' => (string)($_POST['customer_phone'] ?? ''),
    'admin_note' => (string)($_POST['admin_note'] ?? ''),
    'scheduled_slot_label' => (string)($_POST['scheduled_slot_label'] ?? ''),
    'edit_reason' => (string)($_POST['edit_reason'] ?? ''),
    'discount_override' => $_POST['discount_override'] ?? null,
    'delivery_fee_override' => $_POST['delivery_fee_override'] ?? null,
    'items' => is_array($_POST['items'] ?? null) ? $_POST['items'] : [],
    'items_new' => is_array($_POST['items_new'] ?? null) ? $_POST['items_new'] : [],
    'delete_item_ids' => is_array($_POST['delete_item_ids'] ?? null) ? $_POST['delete_item_ids'] : [],
];

$adminId = (int)($_SESSION['admin'] ?? 0);
$result = (new OrderEditService())->apply(
    Database::getConnection(),
    $id,
    $payload,
    $adminId,
    [
        'admin_role' => (string)($_SESSION['admin_role'] ?? ''),
        'admin_permissions' => isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions']) ? $_SESSION['admin_permissions'] : [],
        'ip_address' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ]
);

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
    $level = !empty($result['success']) ? 'success' : 'error';
    $message = !empty($result['success']) ? 'Order details updated successfully.' : (string)($result['message'] ?? 'Order update failed.');
    $target = $base . $sep . http_build_query([
        'action_order_id' => $id,
        'action_status' => !empty($result['success']) ? 'edited' : 'edit_failed',
        'action_level' => $level,
        'action_message' => $message,
    ]);
}

header('Location: ' . $target);
exit;
