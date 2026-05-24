<?php

function get_business_setting(mysqli $conn, string $key, string $default = ''): string
{
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? (string)$row['setting_value'] : $default;
}

function get_business_settings(mysqli $conn): array
{
    return array(
        'business_name' => get_business_setting($conn, 'business_name', 'Cakeouflage'),
        'business_address_line1' => get_business_setting($conn, 'business_address_line1'),
        'business_address_line2' => get_business_setting($conn, 'business_address_line2'),
        'business_city' => get_business_setting($conn, 'business_city'),
        'business_state' => get_business_setting($conn, 'business_state'),
        'business_postal_code' => get_business_setting($conn, 'business_postal_code'),
        'business_phone' => get_business_setting($conn, 'business_phone'),
        'business_email' => get_business_setting($conn, 'business_email'),
        'business_gst_number' => get_business_setting($conn, 'business_gst_number'),
        'business_pan_number' => get_business_setting($conn, 'business_pan_number'),
        'email_logo_url' => get_business_setting($conn, 'email_logo_url'),
        'navbar_logo_url' => get_business_setting($conn, 'navbar_logo_url'),
        'footer_logo_url' => get_business_setting($conn, 'footer_logo_url'),
    );
}
