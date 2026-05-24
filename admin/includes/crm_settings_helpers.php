<?php

function ensure_crm_support_settings($conn)
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS crm_settings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        setting_key VARCHAR(120) NOT NULL,
        endpoint VARCHAR(500) NOT NULL DEFAULT '',
        api_token VARCHAR(500) NOT NULL DEFAULT '',
        is_enabled TINYINT(1) NOT NULL DEFAULT 0,
        extra_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_crm_setting_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $requiredKeys = array(
        'online_order_received' => 'Online Order Received',
        'manual_order_received' => 'Manual Order Received',
        'payment_confirmed' => 'Payment Confirmed',
        'reject_order' => 'Reject Order',
        'ready_order' => 'Ready Order',
        'order_delivered' => 'Order Delivered',
        'follow_up_review' => 'Follow Up Review',
        'annual_reorder' => 'Annual Reorder'
    );

    foreach ($requiredKeys as $settingKey => $label) {
        $stmt = $conn->prepare('SELECT id FROM crm_settings WHERE setting_key = ? LIMIT 1');
        $stmt->bind_param('s', $settingKey);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            $insertStmt = $conn->prepare('INSERT INTO crm_settings (setting_key, endpoint, api_token, is_enabled) VALUES (?, ?, ?, 0)');
            $empty = '';
            $insertStmt->bind_param('sss', $settingKey, $empty, $empty);
            $insertStmt->execute();
        }
    }

    $bootstrapped = true;
}

function crm_follow_up_setting_defaults()
{
    return array(
        'google_review_link' => 'https://www.google.com/search?hl=en-IN&gl=IN&q=Cakeouflage+Nashik#lrd=0x3bdd955a6817e2e5:0xe45effc441329228,3,,,',
        'review_delay_days' => '3',
        'quarterly_follow_up_interval_months' => '3',
        'annual_reminder_days_before' => '7',
        'annual_reminder_basis' => 'last_completed_order',
        'celebration_reminder_days_before' => '7',
        'celebration_combined_email_on_same_day' => '1',
        'whatsapp_send_mode' => 'crm_trigger',
        'crm_queue_push_mode' => 'paused',
        'required_fields_note' => 'Order ID, Amount, Name, Mobile, Email and Quote Description are compulsory for every CRM trigger push.'
    );
}

function fetch_crm_follow_up_settings($conn)
{
    ensure_crm_support_settings($conn);

    $settings = crm_follow_up_setting_defaults();
    $keys = array_keys($settings);
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $types = str_repeat('s', count($keys));
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->bind_param($types, ...$keys);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($result && ($row = $result->fetch_assoc())) {
        $settings[$row['setting_key']] = (string) $row['setting_value'];
    }

    return $settings;
}

function save_crm_follow_up_settings($conn, $settings, $adminId)
{
    ensure_crm_support_settings($conn);
    $defaults = crm_follow_up_setting_defaults();
    $allowedKeys = array_keys($defaults);

    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value, updated_by_admin_id) VALUES (?, ?, ?) AS new ON DUPLICATE KEY UPDATE setting_value = new.setting_value, updated_by_admin_id = new.updated_by_admin_id');
    foreach ($allowedKeys as $key) {
        $value = isset($settings[$key]) ? (string) $settings[$key] : $defaults[$key];
        $stmt->bind_param('ssi', $key, $value, $adminId);
        $stmt->execute();
    }
}

function fetch_crm_settings($conn)
{
    ensure_crm_support_settings($conn);
    $rows = array();
    $result = $conn->query('SELECT * FROM crm_settings ORDER BY id ASC');
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    return $rows;
}

function fetch_crm_setting_by_id($conn, $id)
{
    $stmt = $conn->prepare('SELECT * FROM crm_settings WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function is_crm_token_configured($row)
{
    return !empty($row['api_token']);
}

function update_crm_setting($conn, $id, $endpoint, $apiToken, $isEnabled)
{
    $existing = fetch_crm_setting_by_id($conn, $id);
    if (!$existing) {
        return false;
    }

    $tokenToStore = trim($apiToken) !== '' ? $apiToken : $existing['api_token'];
    $stmt = $conn->prepare('UPDATE crm_settings SET endpoint = ?, api_token = ?, is_enabled = ? WHERE id = ?');
    $stmt->bind_param('ssii', $endpoint, $tokenToStore, $isEnabled, $id);
    return $stmt->execute();
}

function reset_crm_token($conn, $id)
{
    $stmt = $conn->prepare('UPDATE crm_settings SET api_token = ? WHERE id = ?');
    $empty = '';
    $stmt->bind_param('si', $empty, $id);
    return $stmt->execute();
}

function extract_automation_id_from_url(string $url): string
{
    if (preg_match('#automations/([^/]+)/execute#', $url, $m)) {
        return $m[1];
    }
    return '';
}

function mask_api_token(string $token): string
{
    $len = strlen($token);
    if ($len === 0) {
        return '';
    }
    if ($len <= 8) {
        return str_repeat('*', $len);
    }
    return substr($token, 0, 4) . '***' . substr($token, -4);
}

/**
 * Execute a live CRM push via real cURL POST with JSON body.
 * Returns detailed result array — never fakes success.
 *
 * @param array<string,mixed> $row  crm_settings row (must have endpoint, api_token, setting_key)
 */
function execute_crm_test_push(array $row, string $name, string $email, string $phone): array
{
    $endpoint = trim((string)($row['endpoint'] ?? ''));
    $apiToken = trim((string)($row['api_token'] ?? ''));

    if ($endpoint === '' || $apiToken === '') {
        return [
            'ok' => false, 'http_status' => 0, 'response_raw' => '',
            'response_decoded' => null, 'curl_error' => '',
            'time_ms' => 0, 'payload' => null,
            'execution_status' => 'not_configured',
            'error_message' => 'Endpoint or API token is empty',
        ];
    }

    // Validate contact fields
    $nameTrimmed  = trim($name);
    $emailTrimmed = trim($email);
    $phoneTrimmed = trim($phone);
    if ($nameTrimmed === '' || ($emailTrimmed === '' && $phoneTrimmed === '')) {
        return [
            'ok' => false, 'http_status' => 0, 'response_raw' => '',
            'response_decoded' => null, 'curl_error' => '',
            'time_ms' => 0, 'payload' => null,
            'execution_status' => 'validation_failed',
            'error_message' => $nameTrimmed === ''
                ? 'contact_name is required'
                : 'contact_email or contact_phone is required',
        ];
    }

    // Normalise phone: ensure +CountryCode prefix
    $phoneNorm = $phoneTrimmed;
    if ($phoneNorm !== '' && $phoneNorm[0] !== '+') {
        $digits = preg_replace('/\D+/', '', $phoneNorm) ?: '';
        if ($digits !== '') {
            if (strpos($digits, '91') !== 0) {
                $digits = '91' . $digits;
            }
            $phoneNorm = '+' . $digits;
        }
    }

    $payload = [
        'api_token'     => $apiToken,
        'contact_name'  => $nameTrimmed,
        'contact_email' => $emailTrimmed,
        'contact_phone' => $phoneNorm,
        'contact.name'  => $nameTrimmed,
        'contact.email' => $emailTrimmed,
        'contact.phone' => $phoneNorm,
        'contact.mobile'=> $phoneNorm,
    ];
    $bodyJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $startTime = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $bodyJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $responseRaw = curl_exec($ch);
    $curlError   = curl_error($ch);
    $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $timeMs = (int)round((microtime(true) - $startTime) * 1000);

    $responseDecoded = null;
    if ($responseRaw !== false && $responseRaw !== '') {
        $dec = json_decode((string)$responseRaw, true);
        if (is_array($dec)) {
            $responseDecoded = $dec;
        }
    }

    // Success: HTTP 200 AND {"status":"success"}
    $ok = false;
    $errorMessage = '';
    $executionStatus = 'failed';
    if ($responseRaw === false || $curlError !== '') {
        $errorMessage    = $curlError !== '' ? $curlError : 'curl_exec returned false';
    } elseif ($httpCode !== 200) {
        $errorMessage    = 'HTTP ' . $httpCode . ' received (expected 200)';
    } elseif (is_array($responseDecoded) && ($responseDecoded['status'] ?? '') === 'success') {
        $ok              = true;
        $executionStatus = 'success';
    } else {
        $errorMessage    = 'Response did not contain {"status":"success"}';
    }

    return [
        'ok'               => $ok,
        'http_status'      => $httpCode,
        'response_raw'     => $responseRaw !== false ? (string)$responseRaw : '',
        'response_decoded' => $responseDecoded,
        'curl_error'       => $curlError,
        'time_ms'          => $timeMs,
        'payload'          => $payload,
        'execution_status' => $executionStatus,
        'error_message'    => $errorMessage,
    ];
}

function log_crm_push(
    $conn,
    string $name,
    string $mobile,
    string $status,
    string $response,
    string $trigger_key = '',
    string $endpoint = '',
    string $automation_id = '',
    string $api_token_masked = '',
    ?string $payload_json = null,
    ?int $http_status = null,
    string $execution_status = '',
    string $error_message = '',
    int $response_time_ms = 0
): bool {
    $stmt = $conn->prepare(
        'INSERT INTO crm_push_logs'
        . ' (name, mobile, trigger_key, endpoint, status, automation_id, api_token_masked,'
        . '  payload_json, http_status, execution_status, error_message, response_time_ms, response, created_at)'
        . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param(
        'ssssssssissis',
        $name, $mobile, $trigger_key, $endpoint, $status,
        $automation_id, $api_token_masked,
        $payload_json, $http_status,
        $execution_status, $error_message, $response_time_ms,
        $response
    );
    return (bool)$stmt->execute();
}

function fetch_crm_push_logs($conn, $limit)
{
    $rows = array();
    $stmt = $conn->prepare('SELECT * FROM crm_push_logs ORDER BY created_at DESC LIMIT ?');
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    return $rows;
}

function fetch_crm_diagnostics($conn): array
{
    $result = [];

    // Per-trigger: last push stats
    $sql = '
        SELECT
            cpl.trigger_key,
            cpl.endpoint,
            cpl.automation_id,
            cpl.payload_json,
            cpl.response,
            cpl.http_status,
            cpl.execution_status,
            cpl.error_message,
            cpl.response_time_ms,
            cpl.created_at,
            SUM(CASE WHEN cpl2.execution_status = \'success\' THEN 1 ELSE 0 END) AS successes_7d,
            COUNT(cpl2.id) AS total_7d
        FROM crm_push_logs cpl
        INNER JOIN (
            SELECT trigger_key, MAX(id) AS max_id
            FROM crm_push_logs
            GROUP BY trigger_key
        ) latest ON latest.trigger_key = cpl.trigger_key AND latest.max_id = cpl.id
        LEFT JOIN crm_push_logs cpl2
            ON cpl2.trigger_key = cpl.trigger_key
            AND cpl2.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY cpl.id
        ORDER BY cpl.created_at DESC
    ';
    $qResult = $conn->query($sql);
    while ($qResult && ($row = $qResult->fetch_assoc())) {
        $result[] = $row;
    }
    return $result;
}
