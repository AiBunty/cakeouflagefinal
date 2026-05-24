<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/crm_settings_helpers.php';

$name   = trim($_POST['name']   ?? '');
$email  = trim($_POST['email']  ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$crmId  = isset($_POST['crm_id']) ? (int)$_POST['crm_id'] : 0;

if ($name === '' || ($email === '' && $mobile === '')) {
    echo '<span style="color:#e74c3c;font-weight:600;">Name and at least one of email or mobile are required.</span>';
    exit;
}

$row = fetch_crm_setting_by_id($conn, $crmId);
if (!$row) {
    echo '<span style="color:#e74c3c;">CRM setting not found.</span>';
    exit;
}
if (empty($row['is_enabled'])) {
    echo '<span style="color:#e08000;">CRM trigger is disabled. Enable it in CRM Settings before testing.</span>';
    exit;
}
if (empty($row['endpoint']) || empty($row['api_token'])) {
    echo '<span style="color:#e74c3c;">Endpoint or API token is not configured for this trigger.</span>';
    exit;
}

// Execute real push
$result = execute_crm_test_push($row, $name, $email, $mobile);

// Log all details
log_crm_push(
    $conn,
    $name,
    $mobile,
    $result['ok'] ? 'success' : 'fail',
    mb_substr($result['response_raw'], 0, 65000),
    (string)($row['setting_key'] ?? ''),
    (string)($row['endpoint'] ?? ''),
    extract_automation_id_from_url((string)($row['endpoint'] ?? '')),
    mask_api_token((string)($row['api_token'] ?? '')),
    $result['payload'] !== null ? json_encode($result['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    $result['http_status'] > 0 ? $result['http_status'] : null,
    $result['execution_status'],
    $result['error_message'] ?? '',
    $result['time_ms'] ?? 0
);

// Build status badge
$statusColor = $result['ok'] ? '#1f7a44' : '#c0392b';
$statusLabel = $result['ok'] ? 'SUCCESS' : 'FAILED';
$httpBadge   = $result['http_status'] > 0
    ? '<span style="background:' . ($result['http_status'] === 200 ? '#1f7a44' : '#c0392b') . ';color:#fff;padding:1px 7px;border-radius:3px;font-size:0.85em;">HTTP ' . $result['http_status'] . '</span>'
    : '<span style="background:#888;color:#fff;padding:1px 7px;border-radius:3px;font-size:0.85em;">no HTTP response</span>';

echo '<div style="border:1px solid ' . $statusColor . ';border-radius:6px;padding:12px 16px;margin-bottom:10px;">';
echo '<div style="color:' . $statusColor . ';font-weight:700;font-size:1.05em;">'
    . $statusLabel . ' &nbsp;' . $httpBadge
    . ' &nbsp;<span style="color:#888;font-size:0.85em;">' . $result['time_ms'] . ' ms</span></div>';

if (!$result['ok'] && $result['error_message'] !== '') {
    echo '<div style="margin-top:8px;color:#c0392b;font-size:0.92em;">Error: ' . htmlspecialchars($result['error_message']) . '</div>';
}
if ($result['curl_error'] !== '') {
    echo '<div style="margin-top:4px;color:#c0392b;font-size:0.88em;">cURL: ' . htmlspecialchars($result['curl_error']) . '</div>';
}

// Show parsed response
if ($result['response_decoded'] !== null) {
    echo '<details style="margin-top:8px;"><summary style="cursor:pointer;color:#444;font-size:0.92em;">Response JSON</summary>';
    echo '<pre style="background:#f6f8fa;border:1px solid #dde;padding:8px;border-radius:4px;font-size:0.82em;overflow:auto;max-height:200px;">'
        . htmlspecialchars(json_encode($result['response_decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        . '</pre></details>';
} elseif ($result['response_raw'] !== '') {
    echo '<details style="margin-top:8px;"><summary style="cursor:pointer;color:#444;font-size:0.92em;">Raw Response</summary>';
    echo '<pre style="background:#f6f8fa;border:1px solid #dde;padding:8px;border-radius:4px;font-size:0.82em;overflow:auto;max-height:200px;">'
        . htmlspecialchars(mb_substr($result['response_raw'], 0, 3000))
        . '</pre></details>';
}

// Show payload sent
if ($result['payload'] !== null) {
    $safePayload = $result['payload'];
    $safePayload['api_token'] = mask_api_token($safePayload['api_token'] ?? '');
    echo '<details style="margin-top:6px;"><summary style="cursor:pointer;color:#888;font-size:0.88em;">Payload sent</summary>';
    echo '<pre style="background:#f6f8fa;border:1px solid #dde;padding:8px;border-radius:4px;font-size:0.82em;overflow:auto;max-height:200px;">'
        . htmlspecialchars(json_encode($safePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        . '</pre></details>';
}

echo '</div>';

// Recent test log entries for this trigger
$logs = fetch_crm_push_logs($conn, 5);
echo '<hr style="margin:10px 0;"><strong style="font-size:0.92em;">Recent logs for this trigger</strong>';
echo '<ul style="font-size:0.88rem;padding-left:18px;margin-top:6px;">';
foreach ($logs as $log) {
    $lColor = ($log['execution_status'] ?? $log['status']) === 'success' ? '#1f7a44' : '#c0392b';
    $lLabel = htmlspecialchars($log['execution_status'] ?? $log['status']);
    echo '<li>'
        . htmlspecialchars($log['created_at']) . ' — '
        . htmlspecialchars($log['name'])
        . ' <span style="color:' . $lColor . ';font-weight:600;">' . $lLabel . '</span>'
        . (isset($log['http_status']) && $log['http_status'] ? ' HTTP ' . (int)$log['http_status'] : '')
        . '</li>';
}
echo '</ul>';

