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

$allowedStatuses = ['pending_payment', 'payment_under_review', 'awaiting_confirmation', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'rejected'];
$allowedPaymentMethods = ['upi_manual', 'gateway', 'cod', 'credit'];

if ($orderId <= 0 || !in_array($status, $allowedStatuses, true) || !in_array($paymentMethod, $allowedPaymentMethods, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status update payload'], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($status === 'cancelled') {
    if (!admin_has_permission('order_reject') && !admin_has_permission('can_cancel_unpaid_orders') && !admin_has_permission('order_refund')) {
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
$adminName = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';

try {
    $pdo = \App\Core\Database::getConnection();
    $stateManager = new \App\Services\OrderStateManager();

    $readLockStmt = $conn->prepare('SELECT order_status, payment_status, payment_confirmed_at FROM orders WHERE id = ? LIMIT 1');
    $readLockStmt->bind_param('i', $orderId);
    $readLockStmt->execute();
    $existing = $readLockStmt->get_result()->fetch_assoc();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $existingPaymentStatus = (string)($existing['payment_status'] ?? '');
    $existingPaymentConfirmedAt = (string)($existing['payment_confirmed_at'] ?? '');
    $paymentLockedStates = ['paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded'];
    $isPaymentLocked = in_array($existingPaymentStatus, $paymentLockedStates, true) || $existingPaymentConfirmedAt !== '';
    $lockedDisallowedStatuses = ['pending_payment', 'payment_under_review', 'awaiting_confirmation', 'cancelled', 'rejected'];

    if ($isPaymentLocked && in_array($status, $lockedDisallowedStatuses, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Payment-confirmed orders are financially locked. Use fulfillment or refund workflow only.'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($isPaymentLocked && $status === 'confirmed') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Order is already financially confirmed and cannot be reconfirmed.'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $transition = $stateManager->transition(
        $pdo,
        $orderId,
        $status,
        $adminId,
        [
            'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
            'admin_permissions' => isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions']) ? $_SESSION['admin_permissions'] : [],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'reason' => 'Async admin order status update',
        ]
    );

    if (!$transition['success']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $transition['message']], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // State manager canonicalizes delivered -> completed.
    $status = (string)($transition['new_status'] ?? $status);

    if ($status === 'confirmed') {
        $paymentResult = (new \App\Services\OrderPaymentConfirmationService())->confirmOrderPayment($pdo, $orderId, [
            'payment_method' => $paymentMethod,
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'source_reference' => 'admin/api/update-order-status-async.php',
            'source_event' => 'async_order_status_confirmation',
            'skip_order_status_transition' => true,
            'sync_snapshot' => false,
        ]);

        if (!$paymentResult['success']) {
            http_response_code((int)($paymentResult['http_status'] ?? 422));
            echo json_encode(['success' => false, 'error' => (string)($paymentResult['message'] ?? 'Payment confirmation failed')], JSON_UNESCAPED_SLASHES);
            exit;
        }

        $paymentData = is_array($paymentResult['data'] ?? null) ? $paymentResult['data'] : [];
        $effectivePaymentStatus = (string)($paymentData['payment_status'] ?? ($paymentMethod === 'credit' ? 'credit' : 'paid'));
        $effectivePaymentMethod = (string)($paymentData['payment_method'] ?? $paymentMethod);

        $stateManager->writeOrderAudit($pdo, [
            'order_id' => $orderId,
            'action_type' => 'payment_status_update',
            'new_status' => $status,
            'payment_status' => $effectivePaymentStatus,
            'admin_id' => $adminId,
            'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'message' => 'Async payment confirmation from sales register/orders UI',
            'metadata' => ['payment_method' => $effectivePaymentMethod],
        ]);
    }

    $service = new \App\Services\OrderAutomationService();
    $service->handleStatusChange($pdo, $orderId, $status, $adminId);

    try {
        $snapshotService = new \App\Services\OrderFinanceSnapshotService();
        $snapshotService->syncOrderFinancialColumns($pdo, $orderId);
    } catch (\Throwable $syncErr) {
        error_log('[update-order-status-async][finance-sync] ' . $syncErr->getMessage());
    }

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
