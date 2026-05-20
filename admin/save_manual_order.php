<?php
require_once __DIR__ . '/includes/auth.php';

require_permission_for_current_admin_page();

function manual_order_redirect_error(string $message): void
{
    header('Location: manual_order.php?status=error&message=' . rawurlencode($message));
    exit;
}

function queue_manual_order_email(mysqli $conn, int $userId, int $orderId, string $recipient, string $eventKey, array $context): void
{
    $payload = $context;
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        $payloadJson = '{}';
    }

    $logStmt = $conn->prepare('INSERT INTO communication_logs (user_id, order_id, channel, event_key, recipient, status, payload_json) VALUES (?, ?, "email", ?, ?, "queued", ?)');
    $logUserId = $userId > 0 ? $userId : null;
    $logOrderId = $orderId > 0 ? $orderId : null;
    $logStmt->bind_param('iisss', $logUserId, $logOrderId, $eventKey, $recipient, $payloadJson);
    $logStmt->execute();
    $logId = (int)$conn->insert_id;

    $queuePayload = json_encode(['log_id' => $logId], JSON_UNESCAPED_SLASHES);
    if ($queuePayload === false) {
        $queuePayload = '{}';
    }
    $queueStmt = $conn->prepare('INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (?, "email", ?)');
    $queueStmt->bind_param('is', $logId, $queuePayload);
    $queueStmt->execute();

    $jobPayload = json_encode([
        'log_id' => $logId,
        'channel' => 'email',
        'event_key' => $eventKey,
        'recipient' => $recipient,
    ], JSON_UNESCAPED_SLASHES);
    if ($jobPayload === false) {
        $jobPayload = '{}';
    }
    $jobStmt = $conn->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("send_communication", ?, "queued", NOW(), 0)');
    $jobStmt->bind_param('s', $jobPayload);
    $jobStmt->execute();
}

function ensure_manual_order_crm_setting(mysqli $conn): array
{
    $settingKey = 'manual_order_received';

    $seedStmt = $conn->prepare('INSERT INTO crm_settings (setting_key, endpoint, api_token, is_enabled) VALUES (?, "", "", 0) ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key)');
    $seedStmt->bind_param('s', $settingKey);
    $seedStmt->execute();

    $cfgStmt = $conn->prepare('SELECT endpoint, api_token, is_enabled FROM crm_settings WHERE setting_key = ? LIMIT 1');
    $cfgStmt->bind_param('s', $settingKey);
    $cfgStmt->execute();
    $cfgResult = $cfgStmt->get_result();
    $cfg = $cfgResult ? ($cfgResult->fetch_assoc() ?: []) : [];

    $endpoint = trim((string)($cfg['endpoint'] ?? ''));
    $apiToken = trim((string)($cfg['api_token'] ?? ''));
    $isEnabled = (int)($cfg['is_enabled'] ?? 0);

    if ($endpoint === '' || $apiToken === '' || $isEnabled !== 1) {
        $templateKey = 'payment_confirmed';
        $tplStmt = $conn->prepare('SELECT endpoint, api_token, is_enabled FROM crm_settings WHERE setting_key = ? LIMIT 1');
        $tplStmt->bind_param('s', $templateKey);
        $tplStmt->execute();
        $tplResult = $tplStmt->get_result();
        $tpl = $tplResult ? ($tplResult->fetch_assoc() ?: []) : [];

        $tplEndpoint = trim((string)($tpl['endpoint'] ?? ''));
        $tplToken = trim((string)($tpl['api_token'] ?? ''));
        $tplEnabled = (int)($tpl['is_enabled'] ?? 0) === 1 ? 1 : 0;

        if ($tplEndpoint !== '' && $tplToken !== '') {
            $updateStmt = $conn->prepare('UPDATE crm_settings SET endpoint = ?, api_token = ?, is_enabled = ? WHERE setting_key = ?');
            $updateStmt->bind_param('ssis', $tplEndpoint, $tplToken, $tplEnabled, $settingKey);
            $updateStmt->execute();

            $endpoint = $tplEndpoint;
            $apiToken = $tplToken;
            $isEnabled = $tplEnabled;
        }
    }

    return [
        'setting_key' => $settingKey,
        'endpoint' => $endpoint,
        'api_token' => $apiToken,
        'is_enabled' => $isEnabled,
    ];
}

function normalize_phone_for_lookup(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manual_order.php');
    exit;
}

$sessionToken = isset($_SESSION['manual_order_idempotency_key']) ? (string)$_SESSION['manual_order_idempotency_key'] : '';
$requestToken = trim((string)($_POST['idempotency_key'] ?? ''));

if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
    manual_order_redirect_error('Invalid or expired submission token. Refresh and retry.');
}

$_SESSION['manual_order_idempotency_key'] = bin2hex(random_bytes(16));

$customerName = trim((string)($_POST['customer_name'] ?? ''));
$customerEmail = strtolower(trim((string)($_POST['customer_email'] ?? '')));
$customerPhone = trim((string)($_POST['customer_phone'] ?? ''));
$matchedUserId = max((int)($_POST['matched_user_id'] ?? 0), 0);
$itemName = trim((string)($_POST['item_name'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);
$adminNote = trim((string)($_POST['admin_note'] ?? ''));
$fulfilmentMode = trim((string)($_POST['fulfilment_mode'] ?? 'pickup'));
$orderStatus = trim((string)($_POST['order_status'] ?? 'confirmed'));
$paymentStatus = trim((string)($_POST['payment_status'] ?? 'paid'));
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'upi_manual'));
$scheduledSlotRaw = trim((string)($_POST['scheduled_slot'] ?? ''));
$scheduledSlotLabel = trim((string)($_POST['scheduled_slot_label'] ?? ''));
$billingAddressLine1 = trim((string)($_POST['billing_address_line1'] ?? ''));
$billingAddressLine2 = trim((string)($_POST['billing_address_line2'] ?? ''));
$billingCity = trim((string)($_POST['billing_city'] ?? ''));
$billingState = trim((string)($_POST['billing_state'] ?? ''));
$billingPostalCode = trim((string)($_POST['billing_postal_code'] ?? ''));
$deliveryMapsLink = trim((string)($_POST['delivery_maps_link'] ?? ''));
$advanceAmount = (float)($_POST['advance_amount'] ?? 0);
if ($advanceAmount < 0) { $advanceAmount = 0; }

$customerPhone = normalize_phone_for_lookup($customerPhone);

$orderItemsJson = trim((string)($_POST['order_items'] ?? ''));
$orderItems = array();
if ($orderItemsJson !== '') {
    $orderItems = json_decode($orderItemsJson, true);
    if (!is_array($orderItems)) {
        $orderItems = array();
    }
}

if ($customerPhone === '') {
    manual_order_redirect_error('Customer mobile is required for manual order punch.');
}

if (strlen($customerPhone) < 10 || strlen($customerPhone) > 15) {
    manual_order_redirect_error('Customer mobile number must be between 10 and 15 digits.');
}

if ($customerName === '' || $customerEmail === '') {
    manual_order_redirect_error('Manual order requires customer name and email.');
}

if (count($orderItems) === 0 && ($itemName === '' || $amount <= 0)) {
    manual_order_redirect_error('Manual order requires at least one item with a valid price.');
}

if (count($orderItems) > 0) {
    $amount = 0;
    foreach ($orderItems as $item) {
        $itemQty = max((int)($item['quantity'] ?? 1), 1);
        $itemPrice = max((float)($item['unit_price'] ?? 0), 0);
        $amount += $itemQty * $itemPrice;
    }
}

if ($amount <= 0) {
    manual_order_redirect_error('Order total must be greater than zero.');
}

if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    manual_order_redirect_error('Invalid customer email format.');
}

$allowedFulfilment = ['delivery', 'pickup', 'custom_delivery'];
if (!in_array($fulfilmentMode, $allowedFulfilment, true)) {
    $fulfilmentMode = 'pickup';
}

if (($fulfilmentMode === 'delivery' || $fulfilmentMode === 'custom_delivery') && $scheduledSlotRaw === '') {
    manual_order_redirect_error('Delivery date and time are required for delivery/manual dispatch orders.');
}

$scheduledSlotForDb = null;
if ($scheduledSlotRaw !== '') {
    $slotTs = strtotime($scheduledSlotRaw);
    if ($slotTs === false) {
        manual_order_redirect_error('Invalid delivery date/time format.');
    }
    $scheduledSlotForDb = date('Y-m-d H:i:s', $slotTs);
    if ($scheduledSlotLabel === '') {
        $scheduledSlotLabel = date('d M Y h:i A', $slotTs);
    }
}

$allowedOrderStatus = ['pending', 'confirmed', 'in_preparation', 'completed', 'cancelled'];
if (!in_array($orderStatus, $allowedOrderStatus, true)) {
    $orderStatus = 'confirmed';
}

$allowedPaymentStatus = ['pending', 'paid', 'failed', 'refunded', 'credit', 'partial'];
    if (!in_array($paymentStatus, $allowedPaymentStatus, true)) {
    $paymentStatus = 'paid';
}

$allowedPaymentMethods = ['upi_manual', 'cod', 'gateway', 'credit'];
if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    $paymentMethod = 'upi_manual';
}

try {
    $conn->begin_transaction();

    $userId = 0;
    $matchedUser = null;

    if ($matchedUserId > 0) {
        $matchedStmt = $conn->prepare('SELECT id, full_name, email, phone FROM users WHERE id = ? LIMIT 1');
        $matchedStmt->bind_param('i', $matchedUserId);
        $matchedStmt->execute();
        $matchedResult = $matchedStmt->get_result();
        if ($matchedResult && ($matchedRow = $matchedResult->fetch_assoc())) {
            $matchedUser = $matchedRow;
            $userId = (int)$matchedRow['id'];
        }
    }

    if ($userId <= 0) {
        $phoneLookup = $conn->prepare('SELECT id, full_name, email, phone FROM users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", ""), "(", ""), ")", "") = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
        $phoneLookup->bind_param('s', $customerPhone);
        $phoneLookup->execute();
        $phoneResult = $phoneLookup->get_result();
        if ($phoneResult && ($phoneRow = $phoneResult->fetch_assoc())) {
            $matchedUser = $phoneRow;
            $userId = (int)$phoneRow['id'];
        }
    }

    if ($userId <= 0) {
        $emailLookup = $conn->prepare('SELECT id, full_name, email, phone FROM users WHERE email = ? LIMIT 1');
        $emailLookup->bind_param('s', $customerEmail);
        $emailLookup->execute();
        $emailResult = $emailLookup->get_result();
        if ($emailResult && ($emailRow = $emailResult->fetch_assoc())) {
            $matchedUser = $emailRow;
            $userId = (int)$emailRow['id'];
        }
    }

    if ($userId > 0 && is_array($matchedUser)) {
        // Phone is source of truth: existing record drives canonical name/email.
        $customerName = trim((string)($matchedUser['full_name'] ?? $customerName));
        $customerEmail = strtolower(trim((string)($matchedUser['email'] ?? $customerEmail)));
        if ($customerName === '') {
            $customerName = trim((string)($_POST['customer_name'] ?? ''));
        }
        if ($customerEmail === '') {
            $customerEmail = strtolower(trim((string)($_POST['customer_email'] ?? '')));
        }

        $userUpdate = $conn->prepare('UPDATE users SET full_name = ?, phone = ?, updated_at = NOW() WHERE id = ?');
        $userUpdate->bind_param('ssi', $customerName, $customerPhone, $userId);
        $userUpdate->execute();
    } else {
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $userInsert = $conn->prepare('INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, "customer")');
        $userInsert->bind_param('ssss', $customerName, $customerEmail, $customerPhone, $passwordHash);
        $userInsert->execute();
        $userId = (int)$conn->insert_id;
    }

    $productId = 0;
    $productResult = $conn->query('SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1');
    if ($productResult && ($productRow = $productResult->fetch_assoc())) {
        $productId = (int)$productRow['id'];
    }
    if ($productId <= 0) {
        throw new \RuntimeException('Cannot create manual order because no products exist in catalog.');
    }

    $amountText = number_format($amount, 2, '.', '');
    $orderNumber = 'MAN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $finalNote = $adminNote !== '' ? $adminNote : 'Created from admin manual order punch';
    if ($paymentMethod === 'credit') {
        $paymentStatus = 'credit';
        $orderStatus = $orderStatus === 'cancelled' ? 'cancelled' : 'confirmed';
    }

    $orderInsert = $conn->prepare('INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, fulfilment_mode, order_status, payment_status, payment_method, payment_confirmed_at, payment_confirmed_by_admin_id, scheduled_slot, scheduled_slot_label, billing_address_line1, billing_address_line2, billing_city, billing_state, billing_postal_code, delivery_maps_link, advance_amount, subtotal, discount_total, tax_total, grand_total, admin_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)');
    $confirmedAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;
    $confirmedBy = ($paymentStatus === 'paid' && !empty($_SESSION['admin'])) ? (int)$_SESSION['admin'] : null;
    $advanceForDb = $advanceAmount > 0 ? $advanceAmount : null;
    $orderInsert->bind_param('sissssssssissssssssddds', $orderNumber, $userId, $customerName, $customerEmail, $customerPhone, $fulfilmentMode, $orderStatus, $paymentStatus, $paymentMethod, $confirmedAt, $confirmedBy, $scheduledSlotForDb, $scheduledSlotLabel, $billingAddressLine1, $billingAddressLine2, $billingCity, $billingState, $billingPostalCode, $deliveryMapsLink, $advanceForDb, $amount, $amount, $finalNote);
    $orderInsert->execute();
    $orderId = (int)$conn->insert_id;

    // Fallback product ID used when a custom item has no linked product (product_id=0)
    $fallbackProductId = 0;
    $fallbackProductResult = $conn->query('SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1');
    if ($fallbackProductResult && ($fallbackProductRow = $fallbackProductResult->fetch_assoc())) {
        $fallbackProductId = (int)$fallbackProductRow['id'];
    }
    if ($fallbackProductId <= 0) {
        throw new \RuntimeException('Cannot create manual order because no products exist in catalog.');
    }

    if (count($orderItems) > 0) {
        $itemInsert = $conn->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($orderItems as $item) {
            $itemProductId = max((int)($item['product_id'] ?? 0), 0);
            // Custom items submitted with product_id=0; use fallback to satisfy FK constraint
            if ($itemProductId <= 0) {
                $itemProductId = $fallbackProductId;
            }
            $itemVariantId = isset($item['variant_id']) && (int)$item['variant_id'] > 0 ? (int)$item['variant_id'] : null;
            $itemName = trim((string)($item['name'] ?? ''));
            $itemVariantLabel = trim((string)($item['variant_label'] ?? ''));
            $itemQty = max((int)($item['quantity'] ?? 1), 1);
            $itemPrice = max((float)($item['unit_price'] ?? 0), 0);
            $itemLineTotal = $itemQty * $itemPrice;
            $itemNote = trim((string)($item['note'] ?? ''));

            if ($itemName === '') {
                $itemName = 'Item';
            }
            if ($itemVariantLabel === '') {
                $itemVariantLabel = null;
            }

            $itemInsert->bind_param('iiissiids', $orderId, $itemProductId, $itemVariantId, $itemName, $itemVariantLabel, $itemPrice, $itemQty, $itemLineTotal, $itemNote);
            $itemInsert->execute();
        }
    } else {
        $itemInsert = $conn->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note) VALUES (?, ?, NULL, ?, NULL, ?, 1, ?, "Manual order entry")');
        $itemInsert->bind_param('iisdd', $orderId, $fallbackProductId, $itemName, $amount, $amount);
        $itemInsert->execute();
    }

    $upiLink = 'upi://pay?pa=test@upi&pn=Cakeouflage&am=' . $amountText;
    
    $itemsList = '';
    if (count($orderItems) > 0) {
        $itemNames = array();
        foreach ($orderItems as $item) {
            $name = trim((string)($item['name'] ?? ''));
            $qty = (int)($item['quantity'] ?? 1);
            $itemNames[] = $qty . 'x ' . $name;
        }
        $itemsList = implode(', ', $itemNames);
    } else {
        $itemsList = $itemName;
    }
    
    $context = [
        'order_id' => $orderId,
        'user_id' => $userId,
        'order_number' => $orderNumber,
        'customer_name' => $customerName,
        'first_name' => preg_split('/\s+/', $customerName)[0] ?? $customerName,
        'customer_email' => $customerEmail,
        'customer_phone' => $customerPhone,
        'item_names' => $itemsList,
        'grand_total' => $amountText,
        'upi_link' => $upiLink,
        'contact.name' => $customerName,
        'contact.first_name' => preg_split('/\s+/', $customerName)[0] ?? $customerName,
        'contact.mobile' => $customerPhone,
        'contact.phone' => $customerPhone,
        'contact.email' => $customerEmail,
        'contact.orderid' => $orderNumber,
        'contact.item' => $itemsList,
        'contact.amount' => $amountText,
        'contact.upi_link' => $upiLink,
        'payment_method' => $paymentMethod,
    ];

    queue_manual_order_email($conn, $userId, $orderId, $customerEmail, 'manual_order_received_customer', $context);
    queue_manual_order_email($conn, 0, $orderId, 'cakeouflage@gmail.com', 'manual_order_received_admin', $context);

    $crmSetting = ensure_manual_order_crm_setting($conn);

    $crmQueued = 0;
    if ((int)($crmSetting['is_enabled'] ?? 0) === 1 && trim((string)($crmSetting['endpoint'] ?? '')) !== '' && trim((string)($crmSetting['api_token'] ?? '')) !== '') {
        $crmPayload = json_encode([
            'setting_key' => (string)$crmSetting['setting_key'],
            'follow_up_id' => 0,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES);
        if ($crmPayload === false) {
            $crmPayload = '{}';
        }
        $crmJob = $conn->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("crm_trigger_push", ?, "queued", NOW(), 0)');
        $crmJob->bind_param('s', $crmPayload);
        $crmJob->execute();
        $crmQueued = 1;
    }

    if (!empty($_SESSION['admin'])) {
        $meta = json_encode([
            'emails_queued' => 2,
            'crm_jobs_queued' => $crmQueued,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
        ], JSON_UNESCAPED_SLASHES);
        if ($meta === false) {
            $meta = '{}';
        }

        $adminId = (int)$_SESSION['admin'];
        $logStmt = $conn->prepare('INSERT INTO admin_action_logs (admin_id, action_type, target_type, target_id, entity_type, entity_id, metadata_json) VALUES (?, "manual_order_punch", "order", ?, "manual_order", ?, ?)');
        $logStmt->bind_param('iiss', $adminId, $orderId, $orderNumber, $meta);
        $logStmt->execute();
    }

    $conn->commit();
    header('Location: manual_order.php?status=success&order_number=' . rawurlencode($orderNumber) . '&order_id=' . $orderId . '&payment_status=' . rawurlencode($paymentStatus));
    exit;
} catch (\Throwable $e) {
    if (method_exists($conn, 'rollback')) {
        $conn->rollback();
    }
    error_log('[manual_order_save] ' . $e->getMessage());
    manual_order_redirect_error($e->getMessage());
}
