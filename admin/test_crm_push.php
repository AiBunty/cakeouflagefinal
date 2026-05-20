<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/crm_settings_helpers.php';

$name = trim($_POST['name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$crmId = isset($_POST['crm_id']) ? (int) $_POST['crm_id'] : 0;

if ($name === '' || $mobile === '') {
    echo '<span style="color:#e74c3c;">Name and mobile are required.</span>';
    exit;
}

$row = fetch_crm_setting_by_id($conn, $crmId);
if (!$row || empty($row['is_enabled']) || empty($row['endpoint']) || empty($row['api_token'])) {
    echo '<span style="color:#e74c3c;">CRM is not fully configured or enabled.</span>';
    exit;
}

$status = 'success';
$response = 'Dummy push sent to ' . $row['endpoint'] . ' for ' . $name . ' (' . $mobile . ')';
log_crm_push($conn, $name, $mobile, $status, $response);

echo '<div style="color:#1f7a44;font-weight:600;">Test push successful.</div>';
echo '<div style="margin-top:6px;color:#5e3a45;">' . htmlspecialchars($response) . '</div>';

$logs = fetch_crm_push_logs($conn, 5);
echo '<hr><strong>Recent Test Logs</strong>';
echo '<ul style="font-size:0.95rem;padding-left:18px;">';
foreach ($logs as $log) {
    echo '<li>' . htmlspecialchars($log['created_at']) . ' - ' . htmlspecialchars($log['name']) . ' / ' . htmlspecialchars($log['mobile']) . ' / ' . htmlspecialchars($log['status']) . '</li>';
}
echo '</ul>';
