<?php
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
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'upi_manual'));
$paymentStatus = trim((string)($_POST['payment_status'] ?? 'paid'));

$allowedMethods = ['upi_manual', 'gateway', 'cod', 'credit'];
$allowedStatuses = ['paid', 'pending', 'failed', 'refunded', 'credit'];

if ($orderId <= 0 || !in_array($paymentMethod, $allowedMethods, true) || !in_array($paymentStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment update payload'], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($paymentMethod === 'credit' || $paymentStatus === 'credit') {
    if (!admin_has_permission('order_credit')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to mark credit payment'], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $paymentMethod = 'credit';
    $paymentStatus = 'credit';
} elseif (!admin_has_permission('order_edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to update payment'], JSON_UNESCAPED_SLASHES);
    exit;
}

$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;

try {
    if ($paymentStatus === 'paid') {
        $confirmedAt = date('Y-m-d H:i:s');
        $stmt = $conn->prepare('UPDATE orders SET payment_method = ?, payment_status = ?, payment_confirmed_at = ?, payment_confirmed_by_admin_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
        $stmt->bind_param('sssii', $paymentMethod, $paymentStatus, $confirmedAt, $adminId, $orderId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('UPDATE orders SET payment_method = ?, payment_status = ?, payment_confirmed_at = NULL, payment_confirmed_by_admin_id = NULL, updated_at = NOW() WHERE id = ? LIMIT 1');
        $stmt->bind_param('ssi', $paymentMethod, $paymentStatus, $orderId);
        $stmt->execute();
    }

    $readStmt = $conn->prepare('SELECT payment_status, payment_method FROM orders WHERE id = ? LIMIT 1');
    $readStmt->bind_param('i', $orderId);
    $readStmt->execute();
    $row = $readStmt->get_result()->fetch_assoc();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found after payment update'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment updated',
        'order_id' => $orderId,
        'payment_status' => (string)$row['payment_status'],
        'payment_method' => (string)$row['payment_method'],
    ], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    error_log('[update-order-payment-async] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update payment'], JSON_UNESCAPED_SLASHES);
}
