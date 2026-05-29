<?php
define('SKIP_AUTH_ORDER_AUTO_HANDLER', true);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'upi_manual'));
$paymentStatus = trim((string)($_POST['payment_status'] ?? 'paid'));
$redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
if ($redirectTo === '') {
    $redirectTo = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
}

$allowedMethods = ['upi_manual', 'gateway', 'cod', 'credit'];
$allowedStatuses = ['paid', 'part_paid', 'pending', 'failed', 'refunded', 'credit'];
if (!in_array($paymentMethod, $allowedMethods, true) || !in_array($paymentStatus, $allowedStatuses, true) || $orderId <= 0) {
    http_response_code(400);
    echo 'Invalid payment update request';
    exit;
}

if ($paymentMethod === 'credit') {
    $paymentStatus = 'credit';
}
if ($paymentStatus === 'credit') {
    $paymentMethod = 'credit';
}

$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;
$adminName = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';
$existingPaymentStatus = '';
$existingGrandTotal = 0.0;
$existingRefundAmount = 0.0;

$existingRead = $conn->prepare('SELECT payment_status, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ? LIMIT 1');
if ($existingRead) {
    $existingRead->bind_param('i', $orderId);
    $existingRead->execute();
    $existingRow = $existingRead->get_result()->fetch_assoc();
    $existingRead->close();
    if ($existingRow) {
        $existingPaymentStatus = (string)($existingRow['payment_status'] ?? '');
        $existingGrandTotal = (float)($existingRow['grand_total'] ?? 0);
        $existingRefundAmount = (float)($existingRow['refund_amount'] ?? 0);
    }
}

if ($paymentStatus === 'paid') {
    $confirmedAt = date('Y-m-d H:i:s');
    $confirmedBy = $adminId > 0 ? $adminId : null;
    $stmt = $conn->prepare('UPDATE orders SET payment_method = ?, payment_status = ?, payment_confirmed_at = ?, payment_confirmed_by_admin_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    $stmt->bind_param('sssii', $paymentMethod, $paymentStatus, $confirmedAt, $confirmedBy, $orderId);
    $stmt->execute();
    byoc_finalize_quote_after_payment($conn, $orderId);
} else {
    $stmt = $conn->prepare('UPDATE orders SET payment_method = ?, payment_status = ?, payment_confirmed_at = NULL, payment_confirmed_by_admin_id = NULL, updated_at = NOW() WHERE id = ? LIMIT 1');
    $stmt->bind_param('ssi', $paymentMethod, $paymentStatus, $orderId);
    $stmt->execute();
}

$readStmt = $conn->prepare('SELECT payment_status, payment_method, payment_confirmed_at, grand_total, COALESCE(refund_amount, 0) AS refund_amount, order_number FROM orders WHERE id = ? LIMIT 1');
if ($readStmt) {
    $readStmt->bind_param('i', $orderId);
    $readStmt->execute();
    $row = $readStmt->get_result()->fetch_assoc();
    $readStmt->close();

    if ($row && (string)$row['payment_status'] === 'credit' && $existingPaymentStatus !== 'credit') {
        $engine = new \App\Services\FinancialTransactionEngine();
        $recognizedAmount = max(0.0, round((float)($row['grand_total'] ?? $existingGrandTotal) - (float)($row['refund_amount'] ?? $existingRefundAmount), 2));
        $confirmedAt = (string)($row['payment_confirmed_at'] ?? '');
        $postResult = $engine->recordCreditSaleRecognized([
            'order_id' => $orderId,
            'order_number' => (string)($row['order_number'] ?? ''),
            'amount' => $recognizedAmount,
            'payment_status' => (string)$row['payment_status'],
            'source_reference' => 'admin/update_order_payment.php',
            'idempotency_key' => 'legacy-payment-credit:' . $orderId . ':' . $confirmedAt,
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'narration' => 'Credit sale recognized via legacy payment update',
        ]);
        if (!$postResult['success']) {
            error_log('[update_order_payment][fte-credit] ' . $postResult['message']);
        }
    }

    if ($row && (string)$row['payment_status'] === 'paid' && $existingPaymentStatus !== 'paid' && (string)$row['payment_method'] !== 'credit') {
        $engine = new \App\Services\FinancialTransactionEngine();
        $recognizedAmount = max(0.0, round((float)($row['grand_total'] ?? $existingGrandTotal) - (float)($row['refund_amount'] ?? $existingRefundAmount), 2));
        $confirmedAt = (string)($row['payment_confirmed_at'] ?? '');
        if ($existingPaymentStatus === 'credit') {
            $postResult = $engine->recordBalanceSettled([
                'order_id' => $orderId,
                'order_number' => (string)($row['order_number'] ?? ''),
                'amount' => $recognizedAmount,
                'payment_method' => (string)$row['payment_method'],
                'source_reference' => 'admin/update_order_payment.php',
                'idempotency_key' => 'legacy-payment-balance:' . $orderId . ':' . (string)$row['payment_method'] . ':' . $confirmedAt,
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'narration' => 'Credit balance settled via legacy payment update',
            ]);
        } else {
            $postResult = $engine->recordPaymentReceived([
                'order_id' => $orderId,
                'order_number' => (string)($row['order_number'] ?? ''),
                'amount' => $recognizedAmount,
                'payment_method' => (string)$row['payment_method'],
                'payment_status' => (string)$row['payment_status'],
                'source_reference' => 'admin/update_order_payment.php',
                'idempotency_key' => 'legacy-payment-confirmed:' . $orderId . ':' . (string)$row['payment_method'] . ':' . $confirmedAt,
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'narration' => 'Payment received via legacy payment update',
            ]);
        }

        if (!$postResult['success']) {
            error_log('[update_order_payment][fte] ' . $postResult['message']);
        }

        try {
            $receiptService = new \App\Services\PaymentReceiptService();
            $receiptResult = $receiptService->issueAdvanceReceipt($orderId, [
                'source_event' => 'legacy_payment_update',
                'source_reference' => 'legacy-payment-update:' . $orderId . ':' . (string)$row['payment_method'] . ':' . $confirmedAt,
                'payment_method' => (string)$row['payment_method'],
                'payment_status' => (string)$row['payment_status'],
                'issued_by_admin_id' => $adminId,
                'financial_transaction_id' => isset($postResult['transaction_id']) ? (int)$postResult['transaction_id'] : null,
                'metadata' => [
                    'channel' => 'legacy_payment_update',
                    'trigger' => 'manual_payment_confirmation',
                ],
            ]);
            if (!$receiptResult['success'] && !in_array($receiptResult['message'], ['Receipt not allowed after full payment', 'No advance amount available for receipt', 'Payment receipt schema is not ready', 'Receipt not required when partial payment is disabled'], true)) {
                error_log('[update_order_payment][receipt] ' . $receiptResult['message']);
            }
        } catch (\Throwable $receiptErr) {
            error_log('[update_order_payment][receipt] ' . $receiptErr->getMessage());
        }
    }

    try {
        $snapshotService = new \App\Services\OrderFinanceSnapshotService();
        $snapshotService->syncOrderFinancialColumns(\App\Core\Database::getConnection(), $orderId);
    } catch (\Throwable $syncErr) {
        error_log('[update_order_payment][finance-sync] ' . $syncErr->getMessage());
    }
}

$target = 'orders.php';
if ($redirectTo !== '') {
    $parts = parse_url($redirectTo);
    if (is_array($parts)) {
        $path = basename((string)($parts['path'] ?? ''));
        if (in_array($path, ['orders.php', 'sales_register.php', 'collection_report.php'], true)) {
            $query = isset($parts['query']) ? trim((string)$parts['query']) : '';
            if ($query !== '') {
                parse_str($query, $params);
                if (is_array($params)) {
                    $params['payment_updated'] = $orderId;
                    $query = http_build_query($params);
                }
            } else {
                $query = 'payment_updated=' . $orderId;
            }
            $target = $path . '?' . $query;
        }
    }
}

header('Location: ' . $target);
exit;
