<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;
use App\Core\Env;

$requiredKeys = [
    'online_order_received',
    'manual_order_created',
    'byoc_order_created',
    'payment_confirmed',
    'preparing',
    'delivered',
    'refund_started',
    'refund_completed',
];

$defaultEndpoint = trim((string)(Env::get('CRM_TRIGGER_ENDPOINT', Env::get('CRM_TRIGGER_DEFAULT_ENDPOINT', '')) ?? ''));
$defaultToken = trim((string)(Env::get('CRM_TRIGGER_API_TOKEN', Env::get('CRM_TRIGGER_DEFAULT_API_TOKEN', '')) ?? ''));

$pdo = Database::getConnection();
$updated = 0;
$skipped = [];
$rows = [];

foreach ($requiredKeys as $key) {
    $envPrefix = 'CRM_TRIGGER_' . strtoupper($key);
    $endpoint = trim((string)(Env::get($envPrefix . '_ENDPOINT', $defaultEndpoint) ?? ''));
    $token = trim((string)(Env::get($envPrefix . '_API_TOKEN', $defaultToken) ?? ''));

    if ($endpoint === '' || $token === '') {
        $skipped[] = $key;
        continue;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO crm_settings (setting_key, endpoint, api_token, is_enabled, created_at, updated_at)
         VALUES (:setting_key, :endpoint, :api_token, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            endpoint = VALUES(endpoint),
            api_token = VALUES(api_token),
            is_enabled = 1,
            updated_at = NOW()'
    );
    $stmt->execute([
        'setting_key' => $key,
        'endpoint' => $endpoint,
        'api_token' => $token,
    ]);

    $rows[] = [
        'setting_key' => $key,
        'endpoint' => $endpoint,
        'token_masked' => strlen($token) > 8
            ? substr($token, 0, 4) . '***' . substr($token, -4)
            : str_repeat('*', strlen($token)),
    ];
    $updated++;
}

$modeStmt = $pdo->prepare(
    'INSERT INTO settings (setting_key, setting_value, created_at, updated_at)
     VALUES ("crm_queue_push_mode", "enabled", NOW(), NOW())
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
);
$modeStmt->execute();

$result = [
    'updated_count' => $updated,
    'updated_rows' => $rows,
    'skipped_missing_env' => $skipped,
    'required_keys' => $requiredKeys,
    'queue_mode' => 'enabled',
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
