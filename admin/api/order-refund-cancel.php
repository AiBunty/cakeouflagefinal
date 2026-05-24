<?php
require_once __DIR__ . '/../includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../app/bootstrap.php';

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
    $pdo = \App\Core\Database::getConnection();
    $stateManager = new \App\Services\OrderStateManager();

    if ($action === 'cancel') {
        if (!admin_has_permission('order_reject') && !admin_has_permission('can_cancel_unpaid_orders') && !admin_has_permission('order_refund')) {
            throw new Exception('You do not have permission to reject/cancel orders');
        }

        $transition = $stateManager->transition(
            $pdo,
            $orderId,
            'cancelled',
            $adminId,
            [
                'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
                'admin_permissions' => isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions']) ? $_SESSION['admin_permissions'] : [],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'reason' => $reason,
                'metadata' => ['source' => 'legacy-order-refund-cancel-endpoint'],
            ]
        );

        if (!$transition['success']) {
            throw new Exception($transition['message']);
        }

        $stateManager->writeOrderAudit($pdo, [
            'order_id' => $orderId,
            'action_type' => 'cancel_request',
            'previous_status' => (string)($order['order_status'] ?? ''),
            'new_status' => 'cancelled',
            'payment_status' => (string)($order['payment_status'] ?? ''),
            'admin_id' => $adminId,
            'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'message' => 'Order cancelled from legacy endpoint',
            'metadata' => ['reason' => $reason],
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'new_status' => 'cancelled'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'refund') {
        if (!admin_has_permission('order_refund') && !admin_has_permission('can_approve_refund') && !admin_has_permission('can_force_refund')) {
            throw new Exception('You do not have permission to process refunds');
        }

        $refundService = new \App\Services\RefundService();
        $amount = (float)($order['grand_total'] ?? 0);
        $result = $refundService->processRefund(
            $pdo,
            $orderId,
            [
                'refund_amount' => $amount,
                'reason_code' => 'ADMIN_ADJUSTMENT',
                'reason_notes' => $reason,
            ],
            $adminId,
            [
                'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
                'admin_permissions' => isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions']) ? $_SESSION['admin_permissions'] : [],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'admin_name' => isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin',
            ]
        );

        if (!$result['success']) {
            throw new Exception($result['message']);
        }

        $stateManager->writeOrderAudit($pdo, [
            'order_id' => $orderId,
            'action_type' => 'refund_processed',
            'previous_status' => (string)($order['order_status'] ?? ''),
            'new_status' => (string)($result['order_status'] ?? ''),
            'payment_status' => 'refunded',
            'admin_id' => $adminId,
            'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'message' => 'Refund processed from legacy endpoint',
            'metadata' => ['refund_number' => $result['refund_number'] ?? null, 'reason' => $reason],
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Refund processed successfully',
            'new_status' => (string)($result['order_status'] ?? 'fully_refunded'),
            'refund_amount' => $amount
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
