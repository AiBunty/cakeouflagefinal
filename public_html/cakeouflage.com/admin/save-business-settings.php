<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';

function upsert_setting(mysqli $conn, string $key, string $value, int $adminId): bool
{
    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value, updated_by_admin_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_admin_id = VALUES(updated_by_admin_id), updated_at = NOW()');
    $stmt->bind_param('ssi', $key, $value, $adminId);
    return $stmt->execute();
}

function get_setting(mysqli $conn, string $key): string
{
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? (string)$row['setting_value'] : '';
}

function test_apps_script_endpoint(string $endpointUrl, string $sharedSecret): array
{
    $payload = json_encode([
        'action' => 'ping',
        'shared_secret' => $sharedSecret,
        'requested_at' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        return ['success' => false, 'message' => 'Unable to build request payload'];
    }

    $headers = "Content-Type: application/json\r\n";
    $headers .= 'Content-Length: ' . strlen($payload) . "\r\n";

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => $payload,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($endpointUrl, false, $context);
    if ($response === false) {
        return ['success' => false, 'message' => 'Endpoint unreachable or timed out'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'Endpoint returned non-JSON response'];
    }

    if (!empty($decoded['success'])) {
        return ['success' => true, 'message' => (string)($decoded['message'] ?? 'Connection successful')];
    }

    return ['success' => false, 'message' => (string)($decoded['message'] ?? 'Endpoint responded with failure')];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: business-settings.php');
    exit;
}

$businessName = trim((string)($_POST['business_name'] ?? ''));
$addressLine1 = trim((string)($_POST['business_address_line1'] ?? ''));
$addressLine2 = trim((string)($_POST['business_address_line2'] ?? ''));
$city = trim((string)($_POST['business_city'] ?? ''));
$state = trim((string)($_POST['business_state'] ?? ''));
$postalCode = trim((string)($_POST['business_postal_code'] ?? ''));
$phone = trim((string)($_POST['business_phone'] ?? ''));
$email = trim((string)($_POST['business_email'] ?? ''));
$gstNumber = trim((string)($_POST['business_gst_number'] ?? ''));
$panNumber = trim((string)($_POST['business_pan_number'] ?? ''));
$appsScriptEndpoint = trim((string)($_POST['upi_apps_script_endpoint_url'] ?? ''));
$appsScriptSecretInput = trim((string)($_POST['upi_apps_script_shared_secret'] ?? ''));
$appsScriptMode = trim((string)($_POST['upi_apps_script_mode'] ?? 'disabled'));
$appsScriptSenderAllowlist = trim((string)($_POST['upi_apps_script_sender_allowlist'] ?? ''));
$settingsAction = trim((string)($_POST['settings_action'] ?? 'save'));

if ($businessName === '') {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Business name is required.'));
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Invalid email format.'));
    exit;
}

if ($appsScriptEndpoint !== '' && !filter_var($appsScriptEndpoint, FILTER_VALIDATE_URL)) {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Apps Script endpoint URL is invalid.'));
    exit;
}

if (!in_array($appsScriptMode, ['disabled', 'test', 'live'], true)) {
    $appsScriptMode = 'disabled';
}

$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;

try {
    $conn->begin_transaction();

    upsert_setting($conn, 'business_name', $businessName, $adminId);
    upsert_setting($conn, 'business_address_line1', $addressLine1, $adminId);
    upsert_setting($conn, 'business_address_line2', $addressLine2, $adminId);
    upsert_setting($conn, 'business_city', $city, $adminId);
    upsert_setting($conn, 'business_state', $state, $adminId);
    upsert_setting($conn, 'business_postal_code', $postalCode, $adminId);
    upsert_setting($conn, 'business_phone', $phone, $adminId);
    upsert_setting($conn, 'business_email', $email, $adminId);
    upsert_setting($conn, 'business_gst_number', $gstNumber, $adminId);
    upsert_setting($conn, 'business_pan_number', $panNumber, $adminId);

    $existingSecret = get_setting($conn, 'upi_apps_script_shared_secret');
    $appsScriptSecretToStore = $appsScriptSecretInput !== '' ? $appsScriptSecretInput : $existingSecret;
    upsert_setting($conn, 'upi_apps_script_endpoint_url', $appsScriptEndpoint, $adminId);
    upsert_setting($conn, 'upi_apps_script_shared_secret', $appsScriptSecretToStore, $adminId);
    upsert_setting($conn, 'upi_apps_script_mode', $appsScriptMode, $adminId);
    upsert_setting($conn, 'upi_apps_script_sender_allowlist', $appsScriptSenderAllowlist, $adminId);

    if ($settingsAction === 'test_apps_script') {
        if ($appsScriptEndpoint === '') {
            throw new RuntimeException('Provide Apps Script endpoint URL before testing.');
        }
        if ($appsScriptSecretToStore === '') {
            throw new RuntimeException('Provide shared secret before testing connection.');
        }

        $testResult = test_apps_script_endpoint($appsScriptEndpoint, $appsScriptSecretToStore);
        $testStatus = $testResult['success'] ? 'success' : 'failed';
        $testMessage = (string)($testResult['message'] ?? 'Unknown response');
        upsert_setting($conn, 'upi_apps_script_last_test_status', $testStatus, $adminId);
        upsert_setting($conn, 'upi_apps_script_last_test_at', date('Y-m-d H:i:s'), $adminId);
        upsert_setting($conn, 'upi_apps_script_last_test_message', $testMessage, $adminId);

        if (!$testResult['success']) {
            throw new RuntimeException('Apps Script test failed: ' . $testMessage);
        }
    }

    $conn->commit();

    if ($settingsAction === 'test_apps_script') {
        header('Location: business-settings.php?status=success&message=' . rawurlencode('Apps Script connection test passed.'));
        exit;
    }

    header('Location: business-settings.php?status=success');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Database error: ' . $e->getMessage()));
    exit;
}
