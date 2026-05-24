<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_login();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../app/bootstrap.php';

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
$adminName = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';

try {
    $readLockStmt = $conn->prepare('SELECT order_status, payment_status, payment_method, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ? LIMIT 1');
    $readLockStmt->bind_param('i', $orderId);
    $readLockStmt->execute();
    $existing = $readLockStmt->get_result()->fetch_assoc();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $existingOrderStatus = (string)($existing['order_status'] ?? '');
    $existingPaymentStatus = (string)($existing['payment_status'] ?? '');
    $existingPaymentMethod = (string)($existing['payment_method'] ?? '');
    $existingGrandTotal = (float)($existing['grand_total'] ?? 0);
    $existingRefundAmount = (float)($existing['refund_amount'] ?? 0);
    if (in_array($existingOrderStatus, ['partially_refunded', 'fully_refunded', 'refunded'], true)
        || in_array($existingPaymentStatus, ['partially_refunded', 'refunded'], true)
    ) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Financial edits are locked for refunded orders'], JSON_UNESCAPED_SLASHES);
        exit;
    }

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

    $readStmt = $conn->prepare('SELECT payment_status, payment_method, payment_confirmed_at, grand_total, COALESCE(refund_amount, 0) AS refund_amount, order_number FROM orders WHERE id = ? LIMIT 1');
    $readStmt->bind_param('i', $orderId);
    $readStmt->execute();
    $row = $readStmt->get_result()->fetch_assoc();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found after payment update'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stateManager = new \App\Services\OrderStateManager();
    $stateManager->writeOrderAudit(\App\Core\Database::getConnection(), [
        'order_id' => $orderId,
        'action_type' => 'payment_status_update',
        'payment_status' => (string)$row['payment_status'],
        'admin_id' => $adminId,
        'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'message' => 'Payment details updated from async endpoint',
        'metadata' => [
            'payment_method' => (string)$row['payment_method'],
            'requested_payment_status' => $paymentStatus,
            'requested_payment_method' => $paymentMethod,
        ],
    ]);

    if ((string)$row['payment_status'] === 'credit' && $existingPaymentStatus !== 'credit') {
        $engine = new \App\Services\FinancialTransactionEngine();
        $recognizedAmount = max(0.0, round((float)($row['grand_total'] ?? $existingGrandTotal) - (float)($row['refund_amount'] ?? $existingRefundAmount), 2));
        $confirmedAt = (string)($row['payment_confirmed_at'] ?? '');
        $idempotencyKey = 'payment-credit-recognized:' . $orderId . ':' . $confirmedAt;
        $postResult = $engine->recordCreditSaleRecognized([
            'order_id' => $orderId,
            'order_number' => (string)($row['order_number'] ?? ''),
            'amount' => $recognizedAmount,
            'payment_status' => (string)$row['payment_status'],
            'source_reference' => 'admin/api/update-order-payment-async.php',
            'idempotency_key' => $idempotencyKey,
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'narration' => 'Credit sale recognized via admin async payment update',
        ]);

        if (!$postResult['success']) {
            error_log('[update-order-payment-async][fte-credit] ' . $postResult['message']);
        }
    }

    if ((string)$row['payment_status'] === 'paid' && $existingPaymentStatus !== 'paid' && (string)$row['payment_method'] !== 'credit') {
        $engine = new \App\Services\FinancialTransactionEngine();
        $recognizedAmount = max(0.0, round((float)($row['grand_total'] ?? $existingGrandTotal) - (float)($row['refund_amount'] ?? $existingRefundAmount), 2));
        $confirmedAt = (string)($row['payment_confirmed_at'] ?? '');
        if ($existingPaymentStatus === 'credit') {
            $idempotencyKey = 'payment-balance-settled:' . $orderId . ':' . (string)$row['payment_method'] . ':' . $confirmedAt;
            $postResult = $engine->recordBalanceSettled([
                'order_id' => $orderId,
                'order_number' => (string)($row['order_number'] ?? ''),
                'amount' => $recognizedAmount,
                'payment_method' => (string)$row['payment_method'],
                'source_reference' => 'admin/api/update-order-payment-async.php',
                'idempotency_key' => $idempotencyKey,
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'narration' => 'Credit order balance settled via async payment update',
            ]);
        } else {
            $idempotencyKey = 'payment-confirmed:' . $orderId . ':' . (string)$row['payment_status'] . ':' . (string)$row['payment_method'] . ':' . $confirmedAt;
            $postResult = $engine->recordPaymentReceived([
                'order_id' => $orderId,
                'order_number' => (string)($row['order_number'] ?? ''),
                'amount' => $recognizedAmount,
                'payment_method' => (string)$row['payment_method'],
                'payment_status' => (string)$row['payment_status'],
                'source_reference' => 'admin/api/update-order-payment-async.php',
                'idempotency_key' => $idempotencyKey,
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'narration' => 'Payment received via admin async payment update',
            ]);
        }

        if (!$postResult['success']) {
            error_log('[update-order-payment-async][fte] ' . $postResult['message']);
        }
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
