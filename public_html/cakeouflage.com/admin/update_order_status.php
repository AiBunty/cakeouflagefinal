<?php
define('SKIP_AUTH_ORDER_AUTO_HANDLER', true);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

require_admin_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice_helpers.php';

// Capture all input
$id            = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status        = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'upi_manual'));
$sendInvoice   = isset($_POST['send_invoice_email']) && (string)$_POST['send_invoice_email'] === '1';
$printInvoice  = isset($_POST['print_invoice']) && (string)$_POST['print_invoice'] === '1';
$redirectTo    = trim((string)($_POST['redirect_to'] ?? ''));
if ($redirectTo === '') {
    $redirectTo = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
}

$allowedPaymentMethods = array('upi_manual', 'cod', 'gateway', 'credit');
if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    $paymentMethod = 'upi_manual';
}

$allowed = array('pending', 'confirmed', 'in_preparation', 'completed', 'cancelled');
if ($id <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo 'Invalid order update request';
    exit;
}

// Check if order exists
$checkOrder = $conn->query('SELECT id FROM orders WHERE id = ' . (int)$id . ' LIMIT 1');
if (!$checkOrder || !$checkOrder->fetch_assoc()) {
    http_response_code(404);
    echo 'Order not found: id=' . $id;
    exit;
}

// Permission gate
if ($status === 'cancelled') {
    if (!admin_has_permission('order_reject')) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
} elseif ($status === 'confirmed' && $paymentMethod === 'credit') {
    if (!admin_has_permission('order_credit')) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
} else {
    if (!admin_has_permission('order_edit')) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;

// ── Step 1: Update order_status ──
$statusStmt = $conn->prepare('UPDATE orders SET order_status = ? WHERE id = ? LIMIT 1');
if ($statusStmt) {
    $statusStmt->bind_param('si', $status, $id);
    $statusStmt->execute();
    $statusStmt->close();
}

// ── Step 2: Payment update for confirmed orders ──
if ($status === 'confirmed') {
    if ($paymentMethod === 'credit') {
        $payStmt = $conn->prepare(
            'UPDATE orders SET payment_status = "credit", payment_method = "credit", payment_confirmed_at = NOW(), payment_confirmed_by_admin_id = ? WHERE id = ? LIMIT 1'
        );
        if ($payStmt) {
            $payStmt->bind_param('ii', $adminId, $id);
            $payStmt->execute();
            $payStmt->close();
        }
    } else {
        $payStmt = $conn->prepare(
            'UPDATE orders SET payment_status = "paid", payment_method = ?, payment_confirmed_at = NOW(), payment_confirmed_by_admin_id = ? WHERE id = ? LIMIT 1'
        );
        if ($payStmt) {
            $payStmt->bind_param('sii', $paymentMethod, $adminId, $id);
            $payStmt->execute();
            $payStmt->close();
        }

        // Invoice email (non-blocking)
        if ($sendInvoice) {
            try {
                $order = invoice_fetch_order($conn, $id);
                if ($order) {
                    $invoiceHtml = invoice_render_html($order);
                    invoice_queue_email($conn, $order, $invoiceHtml);
                }
            } catch (\Throwable $invoiceErr) {
                error_log('[update_order_status][invoice] ' . $invoiceErr->getMessage());
            }
        }
    }
}

// ── Step 3: Fire automation hooks (non-blocking) ──
try {
    $service = new \App\Services\OrderAutomationService();
    $service->handleStatusChange(\App\Core\Database::getConnection(), $id, $status, $adminId);
} catch (\Throwable $automationError) {
    error_log('[update_order_status][automation] ' . $automationError->getMessage());
}

// ── Step 4: Redirect ──
if ($status === 'confirmed' && $printInvoice && $paymentMethod !== 'credit') {
    header('Location: /admin/order_invoice.php?id=' . $id . '&auto_print=1');
    exit;
}

// Build redirect back to orders page with inline action context
$actionMessage = 'Order updated successfully.';
if ($status === 'confirmed') {
    $actionMessage = $paymentMethod === 'credit'
        ? 'Order confirmed on credit. Payment collection pending.'
        : 'Payment confirmed and order approved.';
} elseif ($status === 'in_preparation') {
    $actionMessage = 'Order marked ready for preparation/dispatch.';
} elseif ($status === 'completed') {
    $actionMessage = 'Order marked delivered/completed.';
} elseif ($status === 'cancelled') {
    $actionMessage = 'Order cancelled successfully.';
}

$redirectParams = [
    'action_order_id' => $id,
    'action_status' => $status,
    'action_level' => 'success',
    'action_message' => $actionMessage,
];

// Preserve existing filter params from referrer
if ($redirectTo !== '') {
    $parts = parse_url($redirectTo);
    if (is_array($parts) && isset($parts['query'])) {
        $query = trim((string)$parts['query']);
        if ($query !== '') {
            parse_str($query, $params);
            if (is_array($params)) {
                unset($params['status'], $params['order_id'], $params['payment_method'], $params['send_invoice_email'], $params['print_invoice'], $params['order_updated']);
                if (!empty($params)) {
                    $redirectParams = array_merge($params, $redirectParams);
                }
            }
        }
    }
}

header('Location: /admin/orders.php?' . http_build_query($redirectParams));
exit;