<?php
define('SKIP_AUTH_ORDER_AUTO_HANDLER', true);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin_login();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'upi_manual'));

$allowedStatuses = ['pending', 'confirmed', 'in_preparation', 'out_for_delivery', 'ready_for_pickup', 'completed', 'cancelled'];
$allowedPaymentMethods = ['upi_manual', 'gateway', 'cod', 'credit'];

if ($orderId <= 0 || !in_array($status, $allowedStatuses, true) || !in_array($paymentMethod, $allowedPaymentMethods, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status update payload'], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($status === 'cancelled') {
    if (!admin_has_permission('order_reject')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to cancel orders'], JSON_UNESCAPED_SLASHES);
        exit;
    }
} else {
    if (!admin_has_permission('order_edit')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to edit order status'], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;

try {
    if ($status === 'confirmed') {
        if ($paymentMethod === 'credit') {
            $stmt = $conn->prepare('UPDATE orders SET payment_status = "credit", payment_method = "credit", payment_confirmed_at = NOW(), payment_confirmed_by_admin_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            $stmt->bind_param('ii', $adminId, $orderId);
            $stmt->execute();
        } else {
            $confirmedAt = date('Y-m-d H:i:s');
            $stmt = $conn->prepare('UPDATE orders SET payment_status = "paid", payment_method = ?, payment_confirmed_at = ?, payment_confirmed_by_admin_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            $stmt->bind_param('ssii', $paymentMethod, $confirmedAt, $adminId, $orderId);
            $stmt->execute();
        }
    }

    $service = new \App\Services\OrderAutomationService();
    $service->handleStatusChange(\App\Core\Database::getConnection(), $orderId, $status, $adminId);

    $readStmt = $conn->prepare('SELECT order_status, payment_status, payment_method FROM orders WHERE id = ? LIMIT 1');
    $readStmt->bind_param('i', $orderId);
    $readStmt->execute();
    $row = $readStmt->get_result()->fetch_assoc();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found after update'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order status updated',
        'order_id' => $orderId,
        'order_status' => (string)$row['order_status'],
        'payment_status' => (string)$row['payment_status'],
        'payment_method' => (string)$row['payment_method'],
    ], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    error_log('[update-order-status-async] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update order status'], JSON_UNESCAPED_SLASHES);
}
