<?php
require_once __DIR__ . '/../includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method'], JSON_UNESCAPED_SLASHES);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));
$orderId = (int)($_POST['order_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;
$reason = $reason !== '' ? $reason : 'No reason provided';

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID'], JSON_UNESCAPED_SLASHES);
    exit;
}

if (!in_array($action, ['cancel', 'refund'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action'], JSON_UNESCAPED_SLASHES);
    exit;
}

$orderStmt = $conn->prepare('SELECT id, order_status, payment_status, grand_total FROM orders WHERE id = ? LIMIT 1');
$orderStmt->bind_param('i', $orderId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
$order = $orderResult ? $orderResult->fetch_assoc() : null;

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found'], JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$transactionOpen = false;
try {
    if ($action === 'cancel') {
        if (!admin_has_permission('order_reject')) {
            throw new Exception('You do not have permission to reject/cancel orders');
        }

        if ($order['order_status'] === 'completed' || $order['order_status'] === 'cancelled') {
            throw new Exception('Cannot cancel a ' . $order['order_status'] . ' order');
        }

        $conn->begin_transaction();
        $transactionOpen = true;
        $cancelStatus = 'cancelled';
        $noteLine = "\n[Admin " . $adminId . '] Order cancelled. Reason: ' . $reason;
        $updateStmt = $conn->prepare('UPDATE orders SET order_status = ?, admin_note = CONCAT(IFNULL(admin_note, ""), ?) WHERE id = ?');
        $updateStmt->bind_param('ssi', $cancelStatus, $noteLine, $orderId);
        $updateStmt->execute();

        $conn->commit();
        $transactionOpen = false;
        echo json_encode([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'new_status' => 'cancelled'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'refund') {
        if (!admin_has_permission('order_refund')) {
            throw new Exception('You do not have permission to process refunds');
        }

        if ($order['payment_status'] === 'refunded') {
            throw new Exception('Order is already refunded');
        }

        if ($order['payment_status'] !== 'paid') {
            throw new Exception('Cannot refund an order with payment status: ' . $order['payment_status']);
        }

        $conn->begin_transaction();
        $transactionOpen = true;
        $noteLine = "\n[Admin " . $adminId . '] Refund processed. Reason: ' . $reason;
        $updateStmt = $conn->prepare('UPDATE orders SET payment_status = ?, admin_note = CONCAT(IFNULL(admin_note, ""), ?) WHERE id = ?');
        $refundStatus = 'refunded';
        $updateStmt->bind_param('ssi', $refundStatus, $noteLine, $orderId);
        $updateStmt->execute();

        $conn->commit();
        $transactionOpen = false;
        echo json_encode([
            'success' => true,
            'message' => 'Refund processed successfully',
            'new_status' => 'refunded',
            'refund_amount' => (float)$order['grand_total']
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

} catch (Exception $e) {
    if ($transactionOpen) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
    exit;
}
