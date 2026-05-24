<?php
define('SKIP_AUTH_ORDER_AUTO_HANDLER', true);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice_helpers.php';

$id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'upi_manual'));
$sendInvoiceEmail = isset($_POST['send_invoice_email']) && (string)$_POST['send_invoice_email'] === '1';
$printAfterConfirm = isset($_POST['print_invoice']) && (string)$_POST['print_invoice'] === '1';
$redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
if ($redirectTo === '') {
    $redirectTo = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
}

$allowedPaymentMethods = array('upi_manual', 'cod', 'gateway', 'credit');
if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    $paymentMethod = 'upi_manual';
}

$allowed = array('pending', 'confirmed', 'in_preparation', 'out_for_delivery', 'ready_for_pickup', 'completed', 'cancelled');
if ($id <= 0 || !in_array($status, $allowed, true)) {
    die('Invalid order update request');
}

if ($status === 'cancelled') {
    if (!admin_has_permission('order_reject')) {
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

try {
    $adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;
    $adminName = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';
    $existingRead = $conn->prepare('SELECT payment_status FROM orders WHERE id = ? LIMIT 1');
    $existingPaymentStatus = '';
    if ($existingRead) {
        $existingRead->bind_param('i', $id);
        $existingRead->execute();
        $existingRow = $existingRead->get_result()->fetch_assoc();
        $existingPaymentStatus = (string)($existingRow['payment_status'] ?? '');
        $existingRead->close();
    }

    $statusUpdated = false;
    $service = new \App\Services\OrderAutomationService();
    try {
        $service->handleStatusChange(\App\Core\Database::getConnection(), $id, $status, isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0);
        $statusUpdated = true;
    } catch (\Throwable $automationError) {
        // Keep order flow unblocked if automation hooks fail.
        error_log('[update_order][automation] ' . $automationError->getMessage() . "\n" . $automationError->getTraceAsString());
    }

    if (!$statusUpdated) {
        $statusStmt = $conn->prepare('UPDATE orders SET order_status = ? WHERE id = ? LIMIT 1');
        $statusStmt->bind_param('si', $status, $id);
        $statusStmt->execute();
    }

    if ($status === 'confirmed') {
        if ($paymentMethod === 'credit') {
            $stmt = $conn->prepare('UPDATE orders SET payment_status = "credit", payment_method = "credit", payment_confirmed_at = NOW(), payment_confirmed_by_admin_id = ? WHERE id = ? LIMIT 1');
            $stmt->bind_param('ii', $adminId, $id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare('UPDATE orders SET payment_status = "paid", payment_method = ?, payment_confirmed_at = NOW(), payment_confirmed_by_admin_id = ? WHERE id = ? LIMIT 1');
            $stmt->bind_param('sii', $paymentMethod, $adminId, $id);
            $stmt->execute();
        }

        $financialRead = $conn->prepare('SELECT order_number, payment_status, payment_method, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ? LIMIT 1');
        if ($financialRead) {
            $financialRead->bind_param('i', $id);
            $financialRead->execute();
            $financialRow = $financialRead->get_result()->fetch_assoc();
            $financialRead->close();

            if ($financialRow) {
                $engine = new \App\Services\FinancialTransactionEngine();
                $recognizedAmount = max(0.0, round((float)($financialRow['grand_total'] ?? 0) - (float)($financialRow['refund_amount'] ?? 0), 2));
                $paymentStatusAfter = (string)($financialRow['payment_status'] ?? '');

                if ($paymentStatusAfter === 'credit' && $existingPaymentStatus !== 'credit') {
                    $postResult = $engine->recordCreditSaleRecognized([
                        'order_id' => $id,
                        'order_number' => (string)($financialRow['order_number'] ?? ''),
                        'amount' => $recognizedAmount,
                        'payment_status' => $paymentStatusAfter,
                        'source_reference' => 'admin/update_order.php',
                        'idempotency_key' => 'update-order-credit:' . $id,
                        'admin_id' => $adminId,
                        'admin_name' => $adminName,
                        'narration' => 'Credit sale recognized via legacy update_order endpoint',
                    ]);
                } elseif ($paymentStatusAfter === 'paid') {
                    if ($existingPaymentStatus === 'credit') {
                        $postResult = $engine->recordBalanceSettled([
                            'order_id' => $id,
                            'order_number' => (string)($financialRow['order_number'] ?? ''),
                            'amount' => $recognizedAmount,
                            'payment_method' => (string)($financialRow['payment_method'] ?? $paymentMethod),
                            'source_reference' => 'admin/update_order.php',
                            'idempotency_key' => 'update-order-balance-settled:' . $id . ':' . (string)($financialRow['payment_method'] ?? ''),
                            'admin_id' => $adminId,
                            'admin_name' => $adminName,
                            'narration' => 'Credit balance settled via legacy update_order endpoint',
                        ]);
                    } else {
                        $postResult = $engine->recordPaymentReceived([
                            'order_id' => $id,
                            'order_number' => (string)($financialRow['order_number'] ?? ''),
                            'amount' => $recognizedAmount,
                            'payment_method' => (string)($financialRow['payment_method'] ?? $paymentMethod),
                            'payment_status' => $paymentStatusAfter,
                            'source_reference' => 'admin/update_order.php',
                            'idempotency_key' => 'update-order-payment:' . $id . ':' . (string)($financialRow['payment_method'] ?? ''),
                            'admin_id' => $adminId,
                            'admin_name' => $adminName,
                            'narration' => 'Payment received via legacy update_order endpoint',
                        ]);
                    }

                    if (isset($postResult) && !$postResult['success']) {
                        error_log('[update_order][fte] ' . $postResult['message']);
                    }
                }
            }
        }

        if ($sendInvoiceEmail) {
            $order = invoice_fetch_order($conn, $id);
            if ($order) {
                $invoiceHtml = invoice_render_html($order);
                invoice_queue_email($conn, $order, $invoiceHtml);
            }
        }
    }
} catch (\Throwable $e) {
    error_log('[update_order] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo 'Order update failed';
    exit;
}

if ($status === 'confirmed' && $printAfterConfirm) {
    header('Location: order_invoice.php?id=' . $id . '&auto_print=1');
    exit;
}

// Allow redirect back to the originating admin page while preventing open redirects.
$target = 'orders.php';
if ($redirectTo !== '') {
    $parts = parse_url($redirectTo);
    if (is_array($parts)) {
        $path = basename((string)($parts['path'] ?? ''));
        if (in_array($path, array('orders.php', 'order_details.php', 'sales_register.php', 'collection_report.php'), true)) {
            if ($path === 'order_details.php') {
                $target = 'order_details.php?id=' . $id;
            } else {
                $query = isset($parts['query']) ? trim((string)$parts['query']) : '';
                if ($query !== '') {
                    parse_str($query, $params);
                    if (is_array($params)) {
                        unset($params['status']);
                        unset($params['order_id']);
                        unset($params['payment_method']);
                        unset($params['send_invoice_email']);
                        unset($params['print_invoice']);
                        $query = http_build_query($params);
                    }
                }
                if ($path === 'sales_register.php' || $path === 'collection_report.php') {
                    $params = array();
                    if ($query !== '') {
                        parse_str($query, $params);
                    }
                    if (!is_array($params)) {
                        $params = array();
                    }
                    $params['status_updated'] = $id;
                    $query = http_build_query($params);
                }
                $target = $path . ($query !== '' ? ('?' . $query) : '');
            }
        }
    }
}

header('Location: ' . $target);
exit;