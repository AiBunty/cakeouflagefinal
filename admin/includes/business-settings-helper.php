<?php

require_once dirname(__DIR__, 2) . '/app/Support/dietary-mode.php';
require_once dirname(__DIR__, 2) . '/app/Support/business-contact-links.php';

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
    $currencySymbol = get_business_setting($conn, 'currency_symbol', 'Rs');
    $currencyCode = get_business_setting($conn, 'currency_code', 'INR');

    return array(
        'business_name' => get_business_setting($conn, 'business_name', 'Cakeouflage'),
        'business_address_line1' => get_business_setting($conn, 'business_address_line1'),
        'business_address_line2' => get_business_setting($conn, 'business_address_line2'),
        'business_address' => get_business_setting($conn, 'business_address'),
        'business_city' => get_business_setting($conn, 'business_city'),
        'business_state' => get_business_setting($conn, 'business_state'),
        'business_postal_code' => get_business_setting($conn, 'business_postal_code'),
        'business_phone' => get_business_setting($conn, 'business_phone'),
        'contact_phone' => get_business_setting($conn, 'contact_phone'),
        'business_email' => get_business_setting($conn, 'business_email'),
        'business_website' => get_business_setting($conn, 'business_website', 'https://www.cakeouflage.com'),
        'whatsapp_number' => normalize_whatsapp_number(get_business_setting($conn, 'whatsapp_number')),
        'facebook_url' => normalize_business_url(get_business_setting($conn, 'facebook_url')),
        'instagram_url' => normalize_business_url(get_business_setting($conn, 'instagram_url')),
        'google_maps_url' => normalize_business_url(get_business_setting($conn, 'google_maps_url')),
        'business_gst_number' => get_business_setting($conn, 'business_gst_number'),
        'business_pan_number' => get_business_setting($conn, 'business_pan_number'),
        'business_logo' => get_business_setting($conn, 'business_logo'),
        'email_logo_url' => get_business_setting($conn, 'email_logo_url'),
        'navbar_logo_url' => get_business_setting($conn, 'navbar_logo_url'),
        'footer_logo_url' => get_business_setting($conn, 'footer_logo_url'),
        'currency_symbol' => $currencySymbol !== '' ? $currencySymbol : 'Rs',
        'currency_code' => $currencyCode !== '' ? strtoupper($currencyCode) : 'INR',
        'invoice_duplicate_copy' => get_business_setting($conn, 'invoice_duplicate_copy', 'on'),
        'allow_partial_payment' => get_business_setting($conn, 'allow_partial_payment', '0'),
        'store_food_mode' => getDietaryMode($conn),
    );
}

function format_money_with_business_currency(float $amount, ?array $settings = null): string
{
    if (!is_array($settings)) {
        global $conn;
        $settings = ($conn instanceof mysqli) ? get_business_settings($conn) : ['currency_symbol' => 'Rs'];
    }

    $symbol = trim((string)($settings['currency_symbol'] ?? 'Rs'));
    if ($symbol === '') {
        $symbol = 'Rs';
    }

    return $symbol . ' ' . number_format($amount, 2);
}
