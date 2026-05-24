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
        if ($paymentMethod === 'credit') {
            $stmt = $conn->prepare('UPDATE orders SET payment_status = "credit", payment_method = "credit", payment_confirmed_at = NOW(), payment_confirmed_by_admin_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            $stmt->bind_param('ii', $adminId, $orderId);
            $stmt->execute();

            $creditReadStmt = $conn->prepare('SELECT order_number, payment_status, payment_method, payment_confirmed_at, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ? LIMIT 1');
            $creditReadStmt->bind_param('i', $orderId);
            $creditReadStmt->execute();
            $creditRow = $creditReadStmt->get_result()->fetch_assoc();

            if ($creditRow && (string)$creditRow['payment_status'] === 'credit') {
                $engine = new \App\Services\FinancialTransactionEngine();
                $recognizedAmount = max(0.0, round((float)($creditRow['grand_total'] ?? 0) - (float)($creditRow['refund_amount'] ?? 0), 2));
                $confirmedAt = (string)($creditRow['payment_confirmed_at'] ?? '');
                $idempotencyKey = 'status-confirmed-credit:' . $orderId . ':' . $confirmedAt;

                $postResult = $engine->recordCreditSaleRecognized([
                    'order_id' => $orderId,
                    'order_number' => (string)($creditRow['order_number'] ?? ''),
                    'amount' => $recognizedAmount,
                    'payment_status' => (string)$creditRow['payment_status'],
                    'source_reference' => 'admin/api/update-order-status-async.php',
                    'idempotency_key' => $idempotencyKey,
                    'admin_id' => $adminId,
                    'admin_name' => $adminName,
                    'narration' => 'Credit sale recognized on status confirmation',
                ]);

                if (!$postResult['success']) {
                    error_log('[update-order-status-async][fte-credit] ' . $postResult['message']);
                }
            }
        } else {
            $confirmedAt = date('Y-m-d H:i:s');
            $stmt = $conn->prepare('UPDATE orders SET payment_status = "paid", payment_method = ?, payment_confirmed_at = ?, payment_confirmed_by_admin_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            $stmt->bind_param('ssii', $paymentMethod, $confirmedAt, $adminId, $orderId);
            $stmt->execute();
        }

        $stateManager->writeOrderAudit($pdo, [
            'order_id' => $orderId,
            'action_type' => 'payment_status_update',
            'new_status' => $status,
            'payment_status' => $paymentMethod === 'credit' ? 'credit' : 'paid',
            'admin_id' => $adminId,
            'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'message' => 'Async payment confirmation from sales register/orders UI',
            'metadata' => ['payment_method' => $paymentMethod],
        ]);

        if ($paymentMethod !== 'credit') {
            $paymentReadStmt = $conn->prepare('SELECT order_number, payment_status, payment_method, payment_confirmed_at, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ? LIMIT 1');
            $paymentReadStmt->bind_param('i', $orderId);
            $paymentReadStmt->execute();
            $paymentRow = $paymentReadStmt->get_result()->fetch_assoc();

            if ($paymentRow && (string)$paymentRow['payment_status'] === 'paid') {
                $engine = new \App\Services\FinancialTransactionEngine();
                $recognizedAmount = max(0.0, round((float)($paymentRow['grand_total'] ?? 0) - (float)($paymentRow['refund_amount'] ?? 0), 2));
                $confirmedAt = (string)($paymentRow['payment_confirmed_at'] ?? '');
                if ((string)$paymentRow['payment_status'] === 'paid' && (string)$paymentMethod !== 'credit') {
                    if ((string)$paymentRow['payment_method'] !== 'credit' && (string)$paymentMethod !== 'credit') {
                        $idempotencyKey = 'status-confirmed-payment:' . $orderId . ':' . (string)$paymentRow['payment_method'] . ':' . $confirmedAt;
                        $postResult = $engine->recordPaymentReceived([
                            'order_id' => $orderId,
                            'order_number' => (string)($paymentRow['order_number'] ?? ''),
                            'amount' => $recognizedAmount,
                            'payment_method' => (string)$paymentRow['payment_method'],
                            'payment_status' => (string)$paymentRow['payment_status'],
                            'source_reference' => 'admin/api/update-order-status-async.php',
                            'idempotency_key' => $idempotencyKey,
                            'admin_id' => $adminId,
                            'admin_name' => $adminName,
                            'narration' => 'Payment received on status confirmation',
                        ]);
                    } else {
                        $idempotencyKey = 'status-confirmed-balance-settled:' . $orderId . ':' . (string)$paymentRow['payment_method'] . ':' . $confirmedAt;
                        $postResult = $engine->recordBalanceSettled([
                            'order_id' => $orderId,
                            'order_number' => (string)($paymentRow['order_number'] ?? ''),
                            'amount' => $recognizedAmount,
                            'payment_method' => (string)$paymentRow['payment_method'],
                            'source_reference' => 'admin/api/update-order-status-async.php',
                            'idempotency_key' => $idempotencyKey,
                            'admin_id' => $adminId,
                            'admin_name' => $adminName,
                            'narration' => 'Credit balance settled on status confirmation',
                        ]);
                    }
                }

                if (!$postResult['success']) {
                    error_log('[update-order-status-async][fte] ' . $postResult['message']);
                }

                try {
                    $receiptService = new \App\Services\PaymentReceiptService();
                    $receiptResult = $receiptService->issueAdvanceReceipt($orderId, [
                        'source_event' => 'async_order_status_confirmation',
                        'source_reference' => 'async-order-status:' . $orderId . ':' . (string)($paymentRow['payment_method'] ?? $paymentMethod) . ':' . $confirmedAt,
                        'payment_method' => (string)($paymentRow['payment_method'] ?? $paymentMethod),
                        'payment_status' => (string)($paymentRow['payment_status'] ?? 'paid'),
                        'issued_by_admin_id' => $adminId,
                        'financial_transaction_id' => isset($postResult['transaction_id']) ? (int)$postResult['transaction_id'] : null,
                        'metadata' => [
                            'channel' => 'admin_async_status',
                            'trigger' => 'async_status_confirmed',
                        ],
                    ]);
                    if (!$receiptResult['success'] && !in_array($receiptResult['message'], ['Receipt not allowed after full payment', 'No advance amount available for receipt', 'Payment receipt schema is not ready', 'Receipt not required when partial payment is disabled'], true)) {
                        error_log('[update-order-status-async][receipt] ' . $receiptResult['message']);
                    }
                } catch (\Throwable $receiptErr) {
                    error_log('[update-order-status-async][receipt] ' . $receiptErr->getMessage());
                }
            }
        }
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
