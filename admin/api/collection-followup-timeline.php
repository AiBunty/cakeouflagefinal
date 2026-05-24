<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin_permission('revenue_report');

use App\Services\CollectionFollowupService;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order id'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $service = new CollectionFollowupService();
    $rows = $service->getTimeline($orderId);

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'rows' => $rows,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[collection-followup-timeline] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load timeline'], JSON_UNESCAPED_SLASHES);
}
