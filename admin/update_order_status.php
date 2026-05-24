<?php
define('SKIP_AUTH_ORDER_AUTO_HANDLER', true);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

require_admin_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice_helpers.php';

if (!function_exists('byoc_finalize_quote_after_payment')) {
    function byoc_finalize_quote_after_payment(mysqli $conn, int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $read = $conn->prepare('SELECT byoc_quote_id FROM orders WHERE id = ? LIMIT 1');
        if (!$read) {
            return;
        }
        $read->bind_param('i', $orderId);
        $read->execute();
        $res = $read->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $read->close();

        $quoteId = (int)($row['byoc_quote_id'] ?? 0);
        if ($quoteId <= 0) {
            return;
        }

        $updQuote = $conn->prepare('UPDATE byoc_quotes SET status = "accepted", accepted_at = COALESCE(accepted_at, NOW()), updated_at = NOW() WHERE id = ? LIMIT 1');
        if ($updQuote) {
            $updQuote->bind_param('i', $quoteId);
            $updQuote->execute();
            $updQuote->close();
        }

        $updInquiry = $conn->prepare('UPDATE inquiries i INNER JOIN byoc_quotes q ON q.inquiry_id = i.id SET i.status = "closed", i.updated_at = NOW() WHERE q.id = ? AND i.inquiry_type = "custom_cake"');
        if ($updInquiry) {
            $updInquiry->bind_param('i', $quoteId);
            $updInquiry->execute();
            $updInquiry->close();
        }
    }
}

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

$allowed = array('pending_payment', 'payment_under_review', 'awaiting_confirmation', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'rejected', 'partially_refunded', 'fully_refunded');
if ($id <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo 'Invalid order update request';
    exit;
}

// Check if order exists
$checkOrder = $conn->query('SELECT id, order_number, payment_status, payment_method, payment_confirmed_at, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ' . (int)$id . ' LIMIT 1');
$orderBefore = $checkOrder ? $checkOrder->fetch_assoc() : null;
if (!$orderBefore) {
    http_response_code(404);
    echo 'Order not found: id=' . $id;
    exit;
}
$existingPaymentStatusBefore = (string)($orderBefore['payment_status'] ?? '');
$paymentConfirmedAtBefore = (string)($orderBefore['payment_confirmed_at'] ?? '');

$paymentLockedStates = ['paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded'];
$fulfillmentAllowedStates = ['preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed', 'partially_refunded', 'fully_refunded'];
$isPaymentLocked = in_array($existingPaymentStatusBefore, $paymentLockedStates, true) || $paymentConfirmedAtBefore !== '';
if ($isPaymentLocked && !in_array($status, $fulfillmentAllowedStates, true) && $status !== 'confirmed') {
    http_response_code(422);
    echo 'Payment-confirmed orders are financially locked. Use fulfillment or refund workflow only.';
    exit;
}

if ($isPaymentLocked && $status === 'confirmed') {
    http_response_code(422);
    echo 'Order is already financially confirmed and cannot be reconfirmed.';
    exit;
}

// Cancel-on-paid guard with explicit finance-safe messaging.
if ($status === 'cancelled') {
    $paidRow = $conn->query(
        'SELECT order_status, payment_status FROM orders WHERE id = ' . (int)$id . ' LIMIT 1'
    );
    $paidRowData = $paidRow ? $paidRow->fetch_assoc() : null;
    $currentPaymentStatus = (string)($paidRowData['payment_status'] ?? '');
    $currentOrderStatus = (string)($paidRowData['order_status'] ?? '');
    if (in_array($currentPaymentStatus, ['paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded'], true)
        || in_array($currentOrderStatus, ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed', 'partially_refunded', 'fully_refunded', 'refunded'], true)
    ) {
        http_response_code(422);
        echo 'Confirmed or delivered orders cannot be cancelled. Use refund workflow.';
        exit;
    }
}

// Permission gate
if ($status === 'cancelled') {
    if (!admin_has_permission('order_reject') && !admin_has_permission('order_refund') && !admin_has_permission('can_cancel_unpaid_orders')) {
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
$adminName = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';

// ── Step 1: Transition order_status via state machine ──
try {
    $stateManager = new \App\Services\OrderStateManager();
    $adminRole        = isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '';
    $adminPermissions = isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])
        ? $_SESSION['admin_permissions'] : [];
    $smResult = $stateManager->transition(
        \App\Core\Database::getConnection(),
        $id,
        $status,
        $adminId,
        [
            'admin_role'        => $adminRole,
            'admin_permissions' => $adminPermissions,
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'reason'            => 'Admin form update',
        ]
    );
    if (!$smResult['success']) {
        http_response_code(422);
        echo htmlspecialchars($smResult['message'], ENT_QUOTES, 'UTF-8');
        exit;
    }

    // State manager canonicalizes delivered -> completed.
    $status = (string)($smResult['new_status'] ?? $status);
} catch (\Throwable $smErr) {
    error_log('[update_order_status][state_machine] ' . $smErr->getMessage());
    http_response_code(500);
    echo 'Order status update failed';
    exit;
}

// Step 2: Payment update for confirmed orders.
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

        try {
            $stateManager = new \App\Services\OrderStateManager();
            $stateManager->writeOrderAudit(\App\Core\Database::getConnection(), [
                'order_id' => $id,
                'action_type' => 'payment_status_update',
                'new_status' => $status,
                'payment_status' => 'credit',
                'admin_id' => $adminId,
                'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'message' => 'Order confirmed on credit from admin orders page',
            ]);
        } catch (\Throwable $auditErr) {
            error_log('[update_order_status][audit] ' . $auditErr->getMessage());
        }

        $financialRead = $conn->prepare('SELECT order_number, payment_status, payment_method, payment_confirmed_at, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ? LIMIT 1');
        if ($financialRead) {
            $financialRead->bind_param('i', $id);
            $financialRead->execute();
            $financialRow = $financialRead->get_result()->fetch_assoc();
            $financialRead->close();

            if ($financialRow && (string)$financialRow['payment_status'] === 'credit' && $existingPaymentStatusBefore !== 'credit') {
                $engine = new \App\Services\FinancialTransactionEngine();
                $recognizedAmount = max(0.0, round((float)($financialRow['grand_total'] ?? 0) - (float)($financialRow['refund_amount'] ?? 0), 2));
                $idempotencyKey = 'update-order-status-credit:' . $id;
                $postResult = $engine->recordCreditSaleRecognized([
                    'order_id' => $id,
                    'order_number' => (string)($financialRow['order_number'] ?? ''),
                    'amount' => $recognizedAmount,
                    'payment_status' => (string)$financialRow['payment_status'],
                    'source_reference' => 'admin/update_order_status.php',
                    'idempotency_key' => $idempotencyKey,
                    'admin_id' => $adminId,
                    'admin_name' => $adminName,
                    'narration' => 'Credit sale recognized via legacy order status update',
                ]);
                if (!$postResult['success']) {
                    error_log('[update_order_status][fte-credit] ' . $postResult['message']);
                }
            }
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

        try {
            $stateManager = new \App\Services\OrderStateManager();
            $stateManager->writeOrderAudit(\App\Core\Database::getConnection(), [
                'order_id' => $id,
                'action_type' => 'payment_status_update',
                'new_status' => $status,
                'payment_status' => 'paid',
                'admin_id' => $adminId,
                'admin_role' => isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'message' => 'Order payment confirmed from admin orders page',
                'metadata' => ['payment_method' => $paymentMethod],
            ]);
        } catch (\Throwable $auditErr) {
            error_log('[update_order_status][audit] ' . $auditErr->getMessage());
        }

        $financialRead = $conn->prepare('SELECT order_number, payment_status, payment_method, payment_confirmed_at, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ? LIMIT 1');
        if ($financialRead) {
            $financialRead->bind_param('i', $id);
            $financialRead->execute();
            $financialRow = $financialRead->get_result()->fetch_assoc();
            $financialRead->close();

            if ($financialRow && (string)$financialRow['payment_status'] === 'paid') {
                $engine = new \App\Services\FinancialTransactionEngine();
                $recognizedAmount = max(0.0, round((float)($financialRow['grand_total'] ?? 0) - (float)($financialRow['refund_amount'] ?? 0), 2));

                if ($existingPaymentStatusBefore === 'credit') {
                    $idempotencyKey = 'update-order-status-balance-settled:' . $id . ':' . (string)($financialRow['payment_method'] ?? '');
                    $postResult = $engine->recordBalanceSettled([
                        'order_id' => $id,
                        'order_number' => (string)($financialRow['order_number'] ?? ''),
                        'amount' => $recognizedAmount,
                        'payment_method' => (string)($financialRow['payment_method'] ?? $paymentMethod),
                        'source_reference' => 'admin/update_order_status.php',
                        'idempotency_key' => $idempotencyKey,
                        'admin_id' => $adminId,
                        'admin_name' => $adminName,
                        'narration' => 'Credit balance settled via legacy order status update',
                    ]);
                } else {
                    $idempotencyKey = 'update-order-status-payment:' . $id . ':' . (string)($financialRow['payment_method'] ?? '');
                    $postResult = $engine->recordPaymentReceived([
                        'order_id' => $id,
                        'order_number' => (string)($financialRow['order_number'] ?? ''),
                        'amount' => $recognizedAmount,
                        'payment_method' => (string)($financialRow['payment_method'] ?? $paymentMethod),
                        'payment_status' => (string)($financialRow['payment_status'] ?? 'paid'),
                        'source_reference' => 'admin/update_order_status.php',
                        'idempotency_key' => $idempotencyKey,
                        'admin_id' => $adminId,
                        'admin_name' => $adminName,
                        'narration' => 'Payment received via legacy order status update',
                    ]);
                }

                if (!$postResult['success']) {
                    error_log('[update_order_status][fte] ' . $postResult['message']);
                }

                try {
                    $receiptService = new \App\Services\PaymentReceiptService();
                    $receiptResult = $receiptService->issueAdvanceReceipt($id, [
                        'source_event' => 'legacy_order_status_confirmation',
                        'source_reference' => 'legacy-order-status:' . $id . ':' . (string)($financialRow['payment_method'] ?? $paymentMethod) . ':' . (string)($financialRow['payment_confirmed_at'] ?? ''),
                        'payment_method' => (string)($financialRow['payment_method'] ?? $paymentMethod),
                        'payment_status' => (string)($financialRow['payment_status'] ?? 'paid'),
                        'issued_by_admin_id' => $adminId,
                        'financial_transaction_id' => isset($postResult['transaction_id']) ? (int)$postResult['transaction_id'] : null,
                        'metadata' => [
                            'channel' => 'admin_orders_status',
                            'trigger' => 'legacy_status_confirmed',
                        ],
                    ]);
                    if (!$receiptResult['success'] && !in_array($receiptResult['message'], ['Receipt not allowed after full payment', 'No advance amount available for receipt', 'Payment receipt schema is not ready', 'Receipt not required when partial payment is disabled'], true)) {
                        error_log('[update_order_status][receipt] ' . $receiptResult['message']);
                    }
                } catch (\Throwable $receiptErr) {
                    error_log('[update_order_status][receipt] ' . $receiptErr->getMessage());
                }
            }
        }

        byoc_finalize_quote_after_payment($conn, $id);

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
} elseif ($status === 'preparing') {
    $actionMessage = 'Order marked ready for preparation/dispatch.';
} elseif ($status === 'delivered') {
    $actionMessage = 'Order marked as delivered.';
} elseif ($status === 'completed') {
    $actionMessage = 'Order marked delivered and completed.';
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