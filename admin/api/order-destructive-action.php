<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_login();
require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;
use App\Services\OrderDestructiveService;

header('Content-Type: application/json; charset=utf-8');

if (!admin_has_permission('order_delete')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to perform destructive order actions.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));
$orderId = (int)($_POST['order_id'] ?? 0);

$allowedActions = ['preview', 'archive', 'restore', 'force_purge'];
if (!in_array($action, $allowedActions, true) || $orderId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid destructive action payload.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'force_purge' && !admin_is_super_admin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only super admins can force purge an order.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$service = new OrderDestructiveService();
$pdo = Database::getConnection();
$context = [
    'order_id' => $orderId,
    'admin_id' => (int)($_SESSION['admin'] ?? 0),
    'admin_name' => (string)($_SESSION['admin_name'] ?? 'Admin'),
    'admin_role' => (string)($_SESSION['admin_role'] ?? ''),
    'ip_address' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
    'delete_password' => (string)($_POST['delete_password'] ?? ''),
    'reason_code' => (string)($_POST['reason_code'] ?? 'other'),
    'reason_notes' => (string)($_POST['reason_notes'] ?? ''),
    'confirm_financial_purge' => isset($_POST['confirm_financial_purge']) && (string)$_POST['confirm_financial_purge'] === '1',
];

if ($action !== 'preview' && (!isset($_POST['final_confirm']) || (string)$_POST['final_confirm'] !== '1')) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Final confirmation is required to proceed.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($action === 'preview') {
        $result = $service->preview($pdo, $orderId);
    } elseif ($action === 'archive') {
        $result = $service->archiveOrder($pdo, $context);
    } elseif ($action === 'restore') {
        $result = $service->restoreOrder($pdo, $context);
    } else {
        $result = $service->forcePurgeOrder($pdo, $context);
    }

    $ok = !empty($result['success']);
    if (!$ok) {
        $code = !empty($result['requires_financial_confirmation']) ? 409 : 422;
        http_response_code($code);
    }

    echo json_encode($result, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[order-destructive-action] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Destructive action failed: ' . $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
}
