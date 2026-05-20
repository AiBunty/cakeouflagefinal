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

    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value, updated_by_admin_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_admin_id = VALUES(updated_by_admin_id)');
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

function log_crm_push($conn, $name, $mobile, $status, $response)
{
    $stmt = $conn->prepare('INSERT INTO crm_push_logs (name, mobile, status, response, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->bind_param('ssss', $name, $mobile, $status, $response);
    return $stmt->execute();
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
