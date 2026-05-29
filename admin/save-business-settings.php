<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/../app/Support/business-contact-links.php';

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
$businessWebsite = trim((string)($_POST['business_website'] ?? ''));
$gstNumber = trim((string)($_POST['business_gst_number'] ?? ''));
$panNumber = trim((string)($_POST['business_pan_number'] ?? ''));
$appsScriptEndpoint = trim((string)($_POST['upi_apps_script_endpoint_url'] ?? ''));
$appsScriptSecretInput = trim((string)($_POST['upi_apps_script_shared_secret'] ?? ''));
$appsScriptMode = trim((string)($_POST['upi_apps_script_mode'] ?? 'disabled'));
$appsScriptSenderAllowlist = trim((string)($_POST['upi_apps_script_sender_allowlist'] ?? ''));
// Full-payment policy is now always enforced.
$allowPartialPayment = '0';
$screenshotRequired = '1';
$invoiceDuplicateCopy = trim((string)($_POST['invoice_duplicate_copy'] ?? 'on'));
if (!in_array($invoiceDuplicateCopy, ['on', 'off'], true)) { $invoiceDuplicateCopy = 'on'; }

// Branding fields
$emailLogoUrl       = trim((string)($_POST['email_logo_url'] ?? ''));
$navbarLogoUrl      = trim((string)($_POST['navbar_logo_url'] ?? ''));
$footerLogoUrl      = trim((string)($_POST['footer_logo_url'] ?? ''));
$defaultProductImageUrl = trim((string)($_POST['default_product_image_url'] ?? ''));
$brandPrimaryColor  = trim((string)($_POST['brand_primary_color'] ?? '#80001F'));
$brandSecondaryColor = trim((string)($_POST['brand_secondary_color'] ?? '#140b0f'));
$supportEmail       = trim((string)($_POST['support_email'] ?? ''));
$supportPhone       = trim((string)($_POST['support_phone'] ?? ''));
$supportWhatsapp    = trim((string)($_POST['support_whatsapp'] ?? ''));
$contactPhone       = trim((string)($_POST['contact_phone'] ?? ''));
$whatsappNumber     = trim((string)($_POST['whatsapp_number'] ?? ''));
$facebookUrl        = trim((string)($_POST['facebook_url'] ?? ''));
$instagramUrl       = trim((string)($_POST['instagram_url'] ?? ''));
$googleMapsUrl      = trim((string)($_POST['google_maps_url'] ?? ''));
$businessLogo       = trim((string)($_POST['business_logo'] ?? ''));
$businessAddress    = trim((string)($_POST['business_address'] ?? ''));
$storeFoodMode      = normalizeDietaryMode((string)($_POST['store_food_mode'] ?? 'veg_only'));
$currencyCode       = strtoupper(trim((string)($_POST['currency_code'] ?? 'INR')));
$currencySymbol     = trim((string)($_POST['currency_symbol'] ?? '₹'));
$orderDeletePassword = (string)($_POST['order_delete_password'] ?? '');
$orderDeletePasswordConfirm = (string)($_POST['order_delete_password_confirm'] ?? '');
$archiveRetentionDays = (int)($_POST['order_archive_retention_days'] ?? 30);
if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $brandPrimaryColor)) { $brandPrimaryColor = '#80001F'; }
if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $brandSecondaryColor)) { $brandSecondaryColor = '#140b0f'; }
if ($archiveRetentionDays < 7 || $archiveRetentionDays > 3650) {
    $archiveRetentionDays = 30;
}
if ($currencyCode === '') {
    $currencyCode = 'INR';
}
if ($currencySymbol === '') {
    $currencySymbol = '₹';
}

if ($orderDeletePassword !== '' || $orderDeletePasswordConfirm !== '') {
    if ($orderDeletePassword !== $orderDeletePasswordConfirm) {
        header('Location: business-settings.php?status=error&message=' . rawurlencode('Order delete password confirmation does not match.'));
        exit;
    }
    if (strlen($orderDeletePassword) < 12) {
        header('Location: business-settings.php?status=error&message=' . rawurlencode('Order delete password must be at least 12 characters.'));
        exit;
    }
}

$settingsAction = trim((string)($_POST['settings_action'] ?? 'save'));

if ($businessName === '') {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Business name is required.'));
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Invalid email format.'));
    exit;
}

if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Invalid support email format.'));
    exit;
}

$businessWebsiteNormalized = normalize_business_url($businessWebsite);
if ($businessWebsite !== '' && $businessWebsiteNormalized === '') {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Invalid business website URL.'));
    exit;
}
$businessWebsite = $businessWebsiteNormalized;

$facebookUrlNormalized = normalize_business_url($facebookUrl);
if ($facebookUrl !== '' && $facebookUrlNormalized === '') {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Invalid Facebook URL.'));
    exit;
}
$facebookUrl = $facebookUrlNormalized;

$instagramUrlNormalized = normalize_business_url($instagramUrl);
if ($instagramUrl !== '' && $instagramUrlNormalized === '') {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Invalid Instagram URL.'));
    exit;
}
$instagramUrl = $instagramUrlNormalized;

$googleMapsUrlNormalized = normalize_business_url($googleMapsUrl);
if ($googleMapsUrl !== '' && $googleMapsUrlNormalized === '') {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Invalid Google Maps URL.'));
    exit;
}
$googleMapsUrl = $googleMapsUrlNormalized;

$normalizedWhatsappNumber = normalize_whatsapp_number($whatsappNumber);
$normalizedSupportWhatsapp = normalize_whatsapp_number($supportWhatsapp);
if ($normalizedSupportWhatsapp === '' && $normalizedWhatsappNumber !== '') {
    $normalizedSupportWhatsapp = $normalizedWhatsappNumber;
}

if ($appsScriptEndpoint !== '' && !filter_var($appsScriptEndpoint, FILTER_VALIDATE_URL)) {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Apps Script endpoint URL is invalid.'));
    exit;
}

if ($defaultProductImageUrl !== '' && !preg_match('#^(https?://|/public/)#i', $defaultProductImageUrl)) {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Default product image must be an absolute URL or a /public/ path.'));
    exit;
}

if ($businessLogo !== '' && !preg_match('#^(https?://|/public/)#i', $businessLogo)) {
    header('Location: business-settings.php?status=error&message=' . rawurlencode('Business logo must be an absolute URL or a /public/ path.'));
    exit;
}

if (!in_array($appsScriptMode, ['disabled', 'test', 'live'], true)) {
    $appsScriptMode = 'disabled';
}

$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;

try {
    $conn->begin_transaction();

    $previousDefaultProductImageUrl = get_setting($conn, 'default_product_image_url');

    upsert_setting($conn, 'business_name', $businessName, $adminId);
    upsert_setting($conn, 'business_address_line1', $addressLine1, $adminId);
    upsert_setting($conn, 'business_address_line2', $addressLine2, $adminId);
    upsert_setting($conn, 'business_city', $city, $adminId);
    upsert_setting($conn, 'business_state', $state, $adminId);
    upsert_setting($conn, 'business_postal_code', $postalCode, $adminId);
    upsert_setting($conn, 'business_phone', $phone, $adminId);
    upsert_setting($conn, 'business_email', $email, $adminId);
    upsert_setting($conn, 'business_website', $businessWebsite, $adminId);
    upsert_setting($conn, 'business_gst_number', $gstNumber, $adminId);
    upsert_setting($conn, 'business_pan_number', $panNumber, $adminId);

    $existingSecret = get_setting($conn, 'upi_apps_script_shared_secret');
    $appsScriptSecretToStore = $appsScriptSecretInput !== '' ? $appsScriptSecretInput : $existingSecret;
    upsert_setting($conn, 'upi_apps_script_endpoint_url', $appsScriptEndpoint, $adminId);
    upsert_setting($conn, 'upi_apps_script_shared_secret', $appsScriptSecretToStore, $adminId);
    upsert_setting($conn, 'upi_apps_script_mode', $appsScriptMode, $adminId);
    upsert_setting($conn, 'upi_apps_script_sender_allowlist', $appsScriptSenderAllowlist, $adminId);
    upsert_setting($conn, 'allow_partial_payment', $allowPartialPayment, $adminId);
    upsert_setting($conn, 'payment_screenshot_required', $screenshotRequired, $adminId);
    upsert_setting($conn, 'invoice_duplicate_copy', $invoiceDuplicateCopy, $adminId);
    upsert_setting($conn, 'email_logo_url', $emailLogoUrl, $adminId);
    upsert_setting($conn, 'navbar_logo_url', $navbarLogoUrl, $adminId);
    upsert_setting($conn, 'footer_logo_url', $footerLogoUrl, $adminId);
    upsert_setting($conn, 'business_logo', $emailLogoUrl !== '' ? $emailLogoUrl : $businessLogo, $adminId);
    upsert_setting($conn, 'default_product_image_url', $defaultProductImageUrl, $adminId);

    // When admin changes the global fallback image, migrate existing rows that still point
    // to previous/default fallback paths so storefront reflects the new default immediately.
    if ($defaultProductImageUrl !== '' && $defaultProductImageUrl !== $previousDefaultProductImageUrl) {
        $fallbackCandidates = array_values(array_unique(array_filter([
            $previousDefaultProductImageUrl,
            '/public/assets/defaults/default-product-image.webp',
            '/assets/defaults/default-product-image.webp',
            '/public/assets/defaults/default-product-image.png',
            '/assets/defaults/default-product-image.png',
        ], static fn($v) => is_string($v) && trim($v) !== '')));

        foreach ($fallbackCandidates as $candidate) {
            $updProducts = $conn->prepare('UPDATE products SET featured_image = ? WHERE featured_image = ?');
            $updProducts->bind_param('ss', $defaultProductImageUrl, $candidate);
            $updProducts->execute();
            $updProducts->close();

            $updGallery = $conn->prepare('UPDATE product_images SET image_url = ? WHERE image_url = ?');
            $updGallery->bind_param('ss', $defaultProductImageUrl, $candidate);
            $updGallery->execute();
            $updGallery->close();
        }
    }
    upsert_setting($conn, 'brand_primary_color', $brandPrimaryColor, $adminId);
    upsert_setting($conn, 'brand_secondary_color', $brandSecondaryColor, $adminId);
    upsert_setting($conn, 'support_email', $supportEmail, $adminId);
    upsert_setting($conn, 'support_phone', $supportPhone, $adminId);
    upsert_setting($conn, 'support_whatsapp', $normalizedSupportWhatsapp, $adminId);
    upsert_setting($conn, 'contact_phone', $contactPhone, $adminId);
    upsert_setting($conn, 'whatsapp_number', $normalizedWhatsappNumber, $adminId);
    upsert_setting($conn, 'facebook_url', $facebookUrl, $adminId);
    upsert_setting($conn, 'instagram_url', $instagramUrl, $adminId);
    upsert_setting($conn, 'google_maps_url', $googleMapsUrl, $adminId);
    if ($businessAddress === '') {
        $addressParts = array_filter([
            $addressLine1,
            $addressLine2,
            $city,
            $state,
            $postalCode,
        ], static fn(string $part): bool => trim($part) !== '');
        $businessAddress = implode(', ', $addressParts);
    }
    upsert_setting($conn, 'business_address', $businessAddress, $adminId);
    upsert_setting($conn, 'store_food_mode', $storeFoodMode, $adminId);
    upsert_setting($conn, 'currency_code', $currencyCode, $adminId);
    upsert_setting($conn, 'currency_symbol', $currencySymbol, $adminId);
    upsert_setting($conn, 'order_archive_retention_days', (string)$archiveRetentionDays, $adminId);

    if ($orderDeletePassword !== '') {
        $deletePasswordHash = password_hash($orderDeletePassword, PASSWORD_DEFAULT);
        if (!is_string($deletePasswordHash) || $deletePasswordHash === '') {
            throw new RuntimeException('Unable to hash order delete password.');
        }
        upsert_setting($conn, 'order_delete_password_hash', $deletePasswordHash, $adminId);
    }

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
