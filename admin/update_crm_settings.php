<?php

require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/crm_settings_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: crm_settings.php');
    exit;
}

if (isset($_POST['settings_form']) && $_POST['settings_form'] === 'follow_up_settings') {
    $settingsPayload = array(
        'google_review_link' => trim($_POST['google_review_link'] ?? ''),
        'review_delay_days' => trim($_POST['review_delay_days'] ?? ''),
        'quarterly_follow_up_interval_months' => trim($_POST['quarterly_follow_up_interval_months'] ?? ''),
        'annual_reminder_days_before' => trim($_POST['annual_reminder_days_before'] ?? ''),
        'annual_reminder_basis' => trim($_POST['annual_reminder_basis'] ?? ''),
        'celebration_reminder_days_before' => trim($_POST['celebration_reminder_days_before'] ?? ''),
        'celebration_combined_email_on_same_day' => isset($_POST['celebration_combined_email_on_same_day']) ? '1' : '0',
        'whatsapp_send_mode' => trim($_POST['whatsapp_send_mode'] ?? ''),
        'crm_queue_push_mode' => trim($_POST['crm_queue_push_mode'] ?? ''),
        'required_fields_note' => trim($_POST['required_fields_note'] ?? '')
    );

    save_crm_follow_up_settings($conn, $settingsPayload, isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : 0);
    header('Location: follow_ups.php?status=follow_up_saved');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    header('Location: crm_settings.php');
    exit;
}

if (isset($_POST['reset_token'])) {
    reset_crm_token($conn, $id);
    header('Location: crm_settings.php?status=reset');
    exit;
}

$endpoint = trim($_POST['endpoint'] ?? '');
$apiToken = $_POST['api_token'] ?? '';
$isEnabled = isset($_POST['is_enabled']) ? 1 : 0;

if ($endpoint !== '') {
    update_crm_setting($conn, $id, $endpoint, $apiToken, $isEnabled);
}

header('Location: crm_settings.php?status=saved');
exit;