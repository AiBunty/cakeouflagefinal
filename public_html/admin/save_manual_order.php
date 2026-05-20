<?php
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/includes/auth.php';

require_permission_for_current_admin_page();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manual_order.php');
    exit;
}

$sessionToken = isset($_SESSION['manual_order_idempotency_key']) ? (string)$_SESSION['manual_order_idempotency_key'] : '';
$requestToken = trim((string)($_POST['idempotency_key'] ?? ''));

if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
    header('Location: manual_order.php?status=error&message=' . rawurlencode('Invalid or expired submission token. Refresh and retry.'));
    exit;
}

$_SESSION['manual_order_idempotency_key'] = bin2hex(random_bytes(16));

$payload = [
    'customer_name' => trim((string)($_POST['customer_name'] ?? '')),
    'customer_email' => trim((string)($_POST['customer_email'] ?? '')),
    'customer_phone' => trim((string)($_POST['customer_phone'] ?? '')),
    'item_name' => trim((string)($_POST['item_name'] ?? '')),
    'amount' => trim((string)($_POST['amount'] ?? '0')),
    'admin_note' => trim((string)($_POST['admin_note'] ?? '')),
    'fulfilment_mode' => trim((string)($_POST['fulfilment_mode'] ?? 'pickup')),
    'order_status' => trim((string)($_POST['order_status'] ?? 'confirmed')),
    'payment_status' => trim((string)($_POST['payment_status'] ?? 'paid')),
];

try {
    $pdo = \App\Core\Database::getConnection();
    $service = new \App\Services\OrderAutomationService();
    $result = $service->createManualOrder($pdo, $payload, isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0);

    if (!empty($_SESSION['admin'])) {
        $logStmt = $pdo->prepare('INSERT INTO admin_action_logs (admin_id, action_type, target_type, target_id, entity_type, entity_id, metadata_json) VALUES (:admin_id, :action_type, :target_type, :target_id, :entity_type, :entity_id, :metadata_json)');
        $logStmt->execute([
            'admin_id' => (int)$_SESSION['admin'],
            'action_type' => 'manual_order_punch',
            'target_type' => 'order',
            'target_id' => (int)($result['order_id'] ?? 0),
            'entity_type' => 'manual_order',
            'entity_id' => (string)($result['order_number'] ?? ''),
            'metadata_json' => json_encode([
                'emails_queued' => (int)($result['emails_queued'] ?? 0),
                'crm_jobs_queued' => (int)($result['crm_jobs_queued'] ?? 0),
                'customer_email' => $payload['customer_email'],
                'customer_phone' => $payload['customer_phone'],
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    header('Location: manual_order.php?status=success&order_number=' . rawurlencode((string)($result['order_number'] ?? '')));
    exit;
} catch (\Throwable $e) {
    error_log('[manual_order_save] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    header('Location: manual_order.php?status=error&message=' . rawurlencode($e->getMessage()));
    exit;
}
