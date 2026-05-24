<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin_permission('revenue_report');

use App\Core\Csrf;
use App\Services\CollectionFollowupService;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

if (!Csrf::validateRequest()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $service = new CollectionFollowupService();
    $result = $service->applyAction([
        'order_id' => (int)($_POST['order_id'] ?? 0),
        'action_type' => trim((string)($_POST['action_type'] ?? '')),
        'note' => trim((string)($_POST['note'] ?? '')),
        'collection_priority' => trim((string)($_POST['collection_priority'] ?? 'normal')),
        'next_followup_at' => trim((string)($_POST['next_followup_at'] ?? '')),
        'promise_date' => trim((string)($_POST['promise_date'] ?? '')),
        'email_subject' => trim((string)($_POST['email_subject'] ?? '')),
        'email_message' => trim((string)($_POST['email_message'] ?? '')),
        'admin_id' => (int)($_SESSION['admin'] ?? 0),
        'admin_name' => trim((string)($_SESSION['admin_name'] ?? 'Admin')),
        'admin_role' => trim((string)($_SESSION['admin_role'] ?? 'admin')),
        'admin_permissions' => isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])
            ? $_SESSION['admin_permissions']
            : [],
    ]);

    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[collection-followup-action] ' . $e->getMessage());
    $statusCode = stripos($e->getMessage(), 'permission') !== false ? 403 : 422;
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Collection action failed',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
