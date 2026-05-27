<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin_permission('crm_report');
require_once __DIR__ . '/../includes/crm_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = array();
if ($method === 'POST') {
    $body = \App\Core\Request::json();
}

$userId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? ($body['user_id'] ?? 0));

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(array('success' => false, 'message' => 'user_id is required'));
    exit;
}

if ($method === 'POST') {
    if (!\App\Core\Csrf::validateRequest()) {
        http_response_code(419);
        echo json_encode(array('success' => false, 'message' => 'Invalid CSRF token'));
        exit;
    }

    $action = strtolower(trim((string)($body['action'] ?? '')));
    $adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['admin'] ?? 0);

    if ($action === 'toggle_tag') {
        $tag = trim((string)($body['tag'] ?? ''));
        $result = crm_toggle_customer_tag($conn, $userId, $tag, $adminId);
        http_response_code($result['success'] ? 200 : 422);
        echo json_encode($result);
        exit;
    }

    if ($action === 'add_note') {
        $note = trim((string)($body['note'] ?? ''));
        $result = crm_add_internal_note($conn, $userId, $note);
        http_response_code($result['success'] ? 200 : 422);
        echo json_encode($result);
        exit;
    }

    if ($action === 'schedule_follow_up') {
        $title = trim((string)($body['title'] ?? 'CRM Follow-up'));
        $notes = trim((string)($body['notes'] ?? ''));
        $when = trim((string)($body['when'] ?? ''));
        $result = crm_create_follow_up($conn, $userId, $title, $notes, $when, $adminId);
        http_response_code($result['success'] ? 200 : 422);
        echo json_encode($result);
        exit;
    }

    http_response_code(422);
    echo json_encode(array('success' => false, 'message' => 'Unsupported action'));
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(20, (int)($_GET['per_page'] ?? 5)));

$payload = fetch_crm_customer_timeline_payload($conn, $userId, $page, $perPage);
if (!$payload) {
    http_response_code(404);
    echo json_encode(array('success' => false, 'message' => 'Customer not found'));
    exit;
}

echo json_encode(array(
    'success' => true,
    'data' => $payload,
));
