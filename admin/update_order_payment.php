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
$allowedStatuses = ['paid', 'pending', 'failed', 'refunded', 'credit'];
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

if ($paymentStatus === 'paid') {
    $confirmedAt = date('Y-m-d H:i:s');
    $confirmedBy = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : null;
    $stmt = $conn->prepare('UPDATE orders SET payment_method = ?, payment_status = ?, payment_confirmed_at = ?, payment_confirmed_by_admin_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    $stmt->bind_param('sssii', $paymentMethod, $paymentStatus, $confirmedAt, $confirmedBy, $orderId);
    $stmt->execute();
    byoc_finalize_quote_after_payment($conn, $orderId);
} else {
    $stmt = $conn->prepare('UPDATE orders SET payment_method = ?, payment_status = ?, payment_confirmed_at = NULL, payment_confirmed_by_admin_id = NULL, updated_at = NOW() WHERE id = ? LIMIT 1');
    $stmt->bind_param('ssi', $paymentMethod, $paymentStatus, $orderId);
    $stmt->execute();
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
