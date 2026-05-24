<?php
define('SKIP_AUTH_ORDER_AUTO_HANDLER', true);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('order_credit');
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: credit_report.php');
    exit;
}

$id              = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$collectedMethod = trim((string)($_POST['collected_payment_method'] ?? 'cash'));
$redirectTo      = trim((string)($_POST['redirect_to'] ?? 'credit_report.php'));

$allowedMethods = array('upi_manual', 'cod', 'gateway');
if (!in_array($collectedMethod, $allowedMethods, true)) {
    $collectedMethod = 'cod';
}

if ($id <= 0) {
    http_response_code(400);
    die('Invalid order');
}

$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;
$adminName = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';

// Confirm this order is actually a credit order
$check = $conn->prepare('SELECT id, order_number, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = ? AND payment_status = "credit" LIMIT 1');
if (!$check) {
    http_response_code(500);
    die('DB error');
}
$check->bind_param('i', $id);
$check->execute();
$found = $check->get_result()->fetch_assoc();
$check->close();

if (!$found) {
    // Not a credit order or already collected — redirect gracefully
    header('Location: credit_report.php?error=not_credit');
    exit;
}

$stmt = $conn->prepare(
    'UPDATE orders SET payment_status = "paid", payment_method = ?, credit_collected_at = NOW(), credit_collected_by_admin_id = ? WHERE id = ? LIMIT 1'
);
if (!$stmt) {
    http_response_code(500);
    die('DB error');
}
$stmt->bind_param('sii', $collectedMethod, $adminId, $id);
if (!$stmt->execute()) {
    error_log('[collect_credit] execute failed: ' . $stmt->error);
    http_response_code(500);
    die('Update failed');
}
$stmt->close();

$settlementAmount = max(0.0, round((float)($found['grand_total'] ?? 0) - (float)($found['refund_amount'] ?? 0), 2));
if ($settlementAmount > 0) {
    $engine = new \App\Services\FinancialTransactionEngine();
    $postResult = $engine->recordBalanceSettled([
        'order_id' => $id,
        'order_number' => (string)($found['order_number'] ?? ''),
        'amount' => $settlementAmount,
        'payment_method' => $collectedMethod,
        'source_reference' => 'admin/collect_credit.php',
        'idempotency_key' => 'credit-balance-settled:' . $id . ':' . $collectedMethod,
        'admin_id' => $adminId,
        'admin_name' => $adminName,
        'narration' => 'Credit order balance collected from admin credit report',
    ]);
    if (!$postResult['success']) {
        error_log('[collect_credit][fte] ' . $postResult['message']);
    }
}

// Allow redirect to order_details or credit_report only (open-redirect guard)
$safeRedirects = array('credit_report.php', 'order_details.php', 'orders.php', 'sales_register.php', 'collection_report.php');
$parts = parse_url($redirectTo);
$path = basename((string)($parts['path'] ?? ''));
if (!in_array($path, $safeRedirects, true)) {
    $path = 'credit_report.php';
}

if ($path === 'order_details.php') {
    $target = 'order_details.php?id=' . $id . '&credit_collected=1';
} else {
    $query = isset($parts['query']) ? trim((string)$parts['query']) : '';
    if ($query !== '') {
        parse_str($query, $queryParams);
        if (is_array($queryParams)) {
            unset($queryParams['credit_collected'], $queryParams['action_order_id'], $queryParams['action_status'], $queryParams['action_level'], $queryParams['action_message']);
            $queryParams['action_order_id'] = $id;
            $queryParams['action_status'] = 'credit_collected';
            $queryParams['action_level'] = 'success';
            $queryParams['action_message'] = 'Credit payment collected successfully.';
            $query = http_build_query($queryParams);
        }
    } else {
        $query = http_build_query([
            'action_order_id' => $id,
            'action_status' => 'credit_collected',
            'action_level' => 'success',
            'action_message' => 'Credit payment collected successfully.',
        ]);
    }
    $target = $path . '?' . $query;
}

header('Location: ' . $target);
exit;
