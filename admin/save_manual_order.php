<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../app/bootstrap.php';

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

    $seedStmt = $conn->prepare('INSERT INTO crm_settings (setting_key, endpoint, api_token, is_enabled) VALUES (?, "", "", 0) AS new ON DUPLICATE KEY UPDATE setting_key = new.setting_key');
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

function sanitize_cake_message(string $value): string
{
    return substr(trim($value), 0, 200);
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
// Normalize to E.164 (+91XXXXXXXXXX) for storage
$_cpDigits = preg_replace('/\D/', '', $customerPhone);
if (strlen($_cpDigits) === 10) {
    $customerPhoneE164 = '+91' . $_cpDigits;
} elseif (strlen($_cpDigits) === 12 && strpos($_cpDigits, '91') === 0) {
    $customerPhoneE164 = '+91' . substr($_cpDigits, 2);
} elseif (strlen($_cpDigits) >= 7 && strlen($_cpDigits) <= 15) {
    $customerPhoneE164 = ($customerPhone[0] ?? '') === '+' ? '+' . $_cpDigits : null;
} else {
    $customerPhoneE164 = null;
}
unset($_cpDigits);
$matchedUserId = max((int)($_POST['matched_user_id'] ?? 0), 0);
$itemName = trim((string)($_POST['item_name'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);
$adminNote = trim((string)($_POST['admin_note'] ?? ''));
$fulfilmentMode = trim((string)($_POST['fulfilment_mode'] ?? 'pickup'));
$orderStatus = trim((string)($_POST['order_status'] ?? 'confirmed'));
$paymentStatus = trim((string)($_POST['payment_status'] ?? 'paid'));
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'upi_manual'));
$scheduledSlotLabel = '';
$billingAddressLine1 = trim((string)($_POST['billing_address_line1'] ?? ''));
$billingAddressLine2 = trim((string)($_POST['billing_address_line2'] ?? ''));
$billingCity = trim((string)($_POST['billing_city'] ?? ''));
$billingState = trim((string)($_POST['billing_state'] ?? ''));
$billingPostalCode = trim((string)($_POST['billing_postal_code'] ?? ''));
$deliveryMapsLink = trim((string)($_POST['delivery_maps_link'] ?? ''));
$orderMode = trim((string)($_POST['order_mode'] ?? 'scheduled_custom'));
if (!in_array($orderMode, ['ready_pos', 'scheduled_custom'], true)) {
    $orderMode = 'scheduled_custom';
}
$slotId = max((int)($_POST['slot_id'] ?? 0), 0);
$slotBookingDate = trim((string)($_POST['slot_booking_date'] ?? ''));
$slotFormLabel = trim((string)($_POST['slot_label'] ?? ''));
$advanceAmount = (float)($_POST['advance_amount'] ?? 0);
if ($advanceAmount < 0) { $advanceAmount = 0; }
$couponCode = strtoupper(trim((string)($_POST['coupon_code'] ?? '')));

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

// Build topper price map before order-items loop (used to recalculate amount)
$activeTopperMap = [];
$topperStmt = $conn->query('SELECT id, name, price FROM cake_toppers WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
if ($topperStmt instanceof mysqli_result) {
    while ($topperRow = $topperStmt->fetch_assoc()) {
        $activeTopperMap[(int)$topperRow['id']] = [
            'name'  => (string)$topperRow['name'],
            'price' => (float)$topperRow['price'],
        ];
    }
    $topperStmt->free();
}

if (count($orderItems) > 0) {
    $amount = 0;
    foreach ($orderItems as $item) {
        $itemQty = max((int)($item['quantity'] ?? 1), 1);
        $itemPrice = max((float)($item['unit_price'] ?? 0), 0);
        $itemTopperId = isset($item['topper_id']) && (int)$item['topper_id'] > 0 ? (int)$item['topper_id'] : null;
        $itemTopperPrice = ($itemTopperId !== null && array_key_exists($itemTopperId, $activeTopperMap))
            ? (float)$activeTopperMap[$itemTopperId]['price']
            : 0;
        $amount += $itemQty * ($itemPrice + $itemTopperPrice);
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

// scheduled_slot is now derived from the slot booking system (slot_id + slot_booking_date).
// Manual datetime-local entry has been removed. Pre-populate from slot data if available.
$scheduledSlotForDb = null;
if ($slotId > 0 && $slotBookingDate !== '') {
    $slotDateTs = strtotime($slotBookingDate);
    if ($slotDateTs !== false) {
        $slotInfoStmt = $conn->prepare('SELECT slot_label, start_time FROM order_slots WHERE id = ? LIMIT 1');
        if ($slotInfoStmt) {
            $slotInfoStmt->bind_param('i', $slotId);
            $slotInfoStmt->execute();
            $slotInfoRow = $slotInfoStmt->get_result()->fetch_assoc();
            $slotInfoStmt->close();
            if ($slotInfoRow) {
                $startTime = !empty($slotInfoRow['start_time']) ? $slotInfoRow['start_time'] : '00:00:00';
                $scheduledSlotForDb  = date('Y-m-d', $slotDateTs) . ' ' . $startTime;
                $scheduledSlotLabel  = $slotInfoRow['slot_label'] ?: $slotFormLabel;
            }
        }
    }
}
if ($scheduledSlotLabel === '' && $slotFormLabel !== '') {
    $scheduledSlotLabel = $slotFormLabel;
}

$allowedOrderStatus = ['pending', 'confirmed', 'in_preparation', 'completed', 'cancelled'];
if (!in_array($orderStatus, $allowedOrderStatus, true)) {
    $orderStatus = 'confirmed';
}

$allowedPaymentStatus = ['pending', 'paid', 'failed', 'refunded', 'credit'];
if (!in_array($paymentStatus, $allowedPaymentStatus, true)) {
    $paymentStatus = 'paid';
}

$isPaymentConfirmed = $paymentStatus === 'paid';
// Business rule: until payment is confirmed, manual advance should not be treated as collected.
if (!$isPaymentConfirmed && $paymentStatus !== 'credit') {
    $advanceAmount = 0.0;
}

$allowedPaymentMethods = ['upi_manual', 'gateway', 'credit'];
if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    $paymentMethod = 'upi_manual';
}

// $activeTopperMap is now built earlier (before the orderItems recalculation loop).

// ── Coupon validation for manual order ────────────────────────────────────
$appliedCouponId = null;
$discountTotal   = 0.0;
if ($couponCode !== '') {
    $couponRow = null;
    $cs = $conn->prepare('SELECT id, discount_type, discount_value, max_discount, min_order_amount, usage_limit, usage_count, starts_at, ends_at, applicable_to FROM coupons WHERE UPPER(code) = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1');
    if ($cs) {
        $cs->bind_param('s', $couponCode);
        $cs->execute();
        $couponRow = $cs->get_result()->fetch_assoc();
        $cs->close();
    }
    if (!$couponRow) {
        manual_order_redirect_error('Coupon code not found or inactive: ' . htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'));
    }
    $couponModules = array_map('trim', explode(',', (string)($couponRow['applicable_to'] ?? '')));
    if (!in_array('manual', $couponModules, true)) {
        manual_order_redirect_error('Coupon "' . htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8') . '" cannot be used for manual orders.');
    }
    $now = time();
    if (!empty($couponRow['starts_at']) && strtotime((string)$couponRow['starts_at']) > $now) {
        manual_order_redirect_error('Coupon is not yet active.');
    }
    if (!empty($couponRow['ends_at']) && strtotime((string)$couponRow['ends_at']) < $now) {
        manual_order_redirect_error('Coupon has expired.');
    }
    if ($couponRow['usage_limit'] !== null && (int)$couponRow['usage_count'] >= (int)$couponRow['usage_limit']) {
        manual_order_redirect_error('Coupon usage limit has been reached.');
    }
    $minOrder = $couponRow['min_order_amount'] !== null ? (float)$couponRow['min_order_amount'] : 0.0;
    if ($amount < $minOrder) {
        manual_order_redirect_error('Order total must be at least ₹' . number_format($minOrder, 2) . ' to use this coupon.');
    }
    $appliedCouponId = (int)$couponRow['id'];
    if ((string)$couponRow['discount_type'] === 'flat') {
        $discountTotal = min((float)$couponRow['discount_value'], $amount);
    } else {
        $discountTotal = ($amount * (float)$couponRow['discount_value']) / 100.0;
        if ($couponRow['max_discount'] !== null) {
            $discountTotal = min($discountTotal, (float)$couponRow['max_discount']);
        }
    }
    $discountTotal = round($discountTotal, 2);
}
$grandTotal = max(round($amount - $discountTotal, 2), 0.0);
// ─────────────────────────────────────────────────────────────────────────

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

    $requiresKitchenProduction = ($orderMode === 'ready_pos') ? 0 : 1;
    $productionStatus = ($orderMode === 'ready_pos') ? 'not_required' : 'pending';
    if ($orderMode === 'ready_pos') {
        $fulfilmentMode = 'pickup';
        if (!in_array($orderStatus, ['confirmed', 'completed'], true)) {
            $orderStatus = 'confirmed';
        }
    }
    if ($scheduledSlotLabel === '' && $slotFormLabel !== '') {
        $scheduledSlotLabel = $slotFormLabel;
    }

    $recognizedAmount = max(0.0, round($grandTotal, 2));
    $advanceCollectedAmount = ($paymentStatus !== 'credit' && $isPaymentConfirmed)
        ? max(0.0, round(min($advanceAmount, $grandTotal), 2))
        : 0.0;
    $netCollectedAmount = $isPaymentConfirmed && $paymentStatus !== 'credit'
        ? $recognizedAmount
        : $advanceCollectedAmount;
    $balanceDueAmount = max(0.0, round($recognizedAmount - $netCollectedAmount, 2));
    $collectionStatus = $balanceDueAmount <= 0.01 && $netCollectedAmount > 0
        ? 'fully_paid'
        : ($netCollectedAmount > 0 ? 'advance_paid' : 'payment_pending');

    $orderInsert = $conn->prepare('INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, customer_phone_e164, fulfilment_mode, order_status, payment_status, payment_method, payment_confirmed_at, payment_confirmed_by_admin_id, scheduled_slot, scheduled_slot_label, billing_address_line1, billing_address_line2, billing_city, billing_state, billing_postal_code, delivery_maps_link, advance_amount, advance_received_amount, net_collected_amount, balance_due_amount, collection_status, subtotal, discount_total, tax_total, grand_total, admin_note, order_mode, requires_kitchen_production, production_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)');
    $confirmedAt = $isPaymentConfirmed ? date('Y-m-d H:i:s') : null;
    $confirmedBy = ($isPaymentConfirmed && !empty($_SESSION['admin'])) ? (int)$_SESSION['admin'] : null;
    $advanceForDb = $advanceAmount > 0 ? $advanceAmount : null;
    $orderInsert->bind_param('sisssssssssissssssssddddsdddssis', $orderNumber, $userId, $customerName, $customerEmail, $customerPhone, $customerPhoneE164, $fulfilmentMode, $orderStatus, $paymentStatus, $paymentMethod, $confirmedAt, $confirmedBy, $scheduledSlotForDb, $scheduledSlotLabel, $billingAddressLine1, $billingAddressLine2, $billingCity, $billingState, $billingPostalCode, $deliveryMapsLink, $advanceForDb, $advanceCollectedAmount, $netCollectedAmount, $balanceDueAmount, $collectionStatus, $amount, $discountTotal, $grandTotal, $finalNote, $orderMode, $requiresKitchenProduction, $productionStatus);
    $orderInsert->execute();
    $orderId = (int)$conn->insert_id;

    // Slot reservation for scheduled_custom orders
    if ($orderId > 0 && $orderMode === 'scheduled_custom' && $slotId > 0 && $slotBookingDate !== '') {
        $slotDateTs = strtotime($slotBookingDate);
        if ($slotDateTs !== false) {
            $slotDateForDb = date('Y-m-d', $slotDateTs);
            $reserveStmt = $conn->prepare('INSERT IGNORE INTO slot_reservations (order_id, slot_id, booking_date, reservation_status, confirmed_at, created_at, updated_at) VALUES (?, ?, ?, "confirmed", NOW(), NOW(), NOW())');
            $reserveStmt->bind_param('iis', $orderId, $slotId, $slotDateForDb);
            $reserveStmt->execute();
            $capStmt = $conn->prepare('INSERT INTO slot_capacities (slot_id, booking_date, booked_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE booked_count = booked_count + 1');
            $capStmt->bind_param('is', $slotId, $slotDateForDb);
            $capStmt->execute();
            $slotUpdateStmt = $conn->prepare('UPDATE orders SET slot_id = ? WHERE id = ?');
            $slotUpdateStmt->bind_param('ii', $slotId, $orderId);
            $slotUpdateStmt->execute();
        }
    }

    // Record coupon redemption if a coupon was applied
    if ($orderId > 0 && $appliedCouponId !== null) {
        $redemptionStmt = $conn->prepare('INSERT IGNORE INTO coupon_redemptions (coupon_id, order_id, user_id, code_snapshot, discount_total) VALUES (?, ?, ?, ?, ?)');
        if ($redemptionStmt) {
            $redemptionUserId = $userId > 0 ? $userId : null;
            $redemptionStmt->bind_param('iiisd', $appliedCouponId, $orderId, $redemptionUserId, $couponCode, $discountTotal);
            $redemptionStmt->execute();
            $redemptionStmt->close();
        }
        $usageStmt = $conn->prepare('UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?');
        if ($usageStmt) {
            $usageStmt->bind_param('i', $appliedCouponId);
            $usageStmt->execute();
            $usageStmt->close();
        }
    }

    $adminIdForFinancial = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;
    $adminNameForFinancial = isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : 'Admin';
    $financialEngine = new \App\Services\FinancialTransactionEngine();
    if ($recognizedAmount > 0 && $paymentStatus === 'credit') {
        $postResult = $financialEngine->recordCreditSaleRecognized([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'amount' => $recognizedAmount,
            'payment_status' => $paymentStatus,
            'source_reference' => 'admin/save_manual_order.php',
            'idempotency_key' => 'manual-order-credit:' . $orderId,
            'admin_id' => $adminIdForFinancial,
            'admin_name' => $adminNameForFinancial,
            'narration' => 'Credit sale recognized on manual order creation',
        ]);
        if (!$postResult['success']) {
            error_log('[save_manual_order][fte-credit] ' . $postResult['message']);
        }
    } elseif ($recognizedAmount > 0 && $paymentStatus === 'paid' && $paymentMethod !== 'credit') {
        $postResult = $financialEngine->recordPaymentReceived([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'amount' => $recognizedAmount,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'source_reference' => 'admin/save_manual_order.php',
            'idempotency_key' => 'manual-order-paid:' . $orderId,
            'admin_id' => $adminIdForFinancial,
            'admin_name' => $adminNameForFinancial,
            'narration' => 'Payment received on manual order creation',
        ]);
        if (!$postResult['success']) {
            error_log('[save_manual_order][fte-paid] ' . $postResult['message']);
        }
    }

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
        $itemInsert = $conn->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note, cake_message, topper_id, topper_name_snapshot, topper_price_snapshot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($orderItems as $item) {
            $itemProductId = max((int)($item['product_id'] ?? 0), 0);
            $isCustomItem = !empty($item['is_custom_item']);
            // Custom items submitted with product_id=0; use fallback to satisfy FK constraint
            if ($itemProductId <= 0) {
                $itemProductId = $fallbackProductId;
            }
            $itemVariantId = isset($item['variant_id']) && (int)$item['variant_id'] > 0 ? (int)$item['variant_id'] : null;
            $itemName = trim((string)($item['name'] ?? ''));
            $itemVariantLabel = trim((string)($item['variant_label'] ?? ''));
            $itemQty = max((int)($item['quantity'] ?? 1), 1);
            $itemPrice = max((float)($item['unit_price'] ?? 0), 0);
            $itemCakeMessage = sanitize_cake_message((string)($item['cake_message'] ?? ($item['note'] ?? '')));
            $requestedTopperId = isset($item['topper_id']) && (int)$item['topper_id'] > 0 ? (int)$item['topper_id'] : null;
            $topperId = null;
            $topperNameSnapshot = null;
            $topperPriceSnapshot = 0.00;

            if (!$isCustomItem && $requestedTopperId !== null) {
                $productFlagsStmt = $conn->prepare('SELECT topper_enabled FROM products WHERE id = ? LIMIT 1');
                $productFlagsStmt->bind_param('i', $itemProductId);
                $productFlagsStmt->execute();
                $productFlagsResult = $productFlagsStmt->get_result();
                $productFlags = $productFlagsResult ? ($productFlagsResult->fetch_assoc() ?: null) : null;
                if ($productFlagsResult) {
                    $productFlagsResult->free();
                }
                $productFlagsStmt->close();
                $topperAllowed = !empty($productFlags) && (int)($productFlags['topper_enabled'] ?? 0) === 1;
                if (!$topperAllowed) {
                    throw new RuntimeException('Selected product does not allow toppers.');
                }

                if (!array_key_exists($requestedTopperId, $activeTopperMap)) {
                    throw new RuntimeException('Invalid or inactive topper selected for manual order item.');
                }
                $topperId = $requestedTopperId;
                $topperNameSnapshot = $activeTopperMap[$topperId]['name'];
                $topperPriceSnapshot = (float)$activeTopperMap[$topperId]['price'];
            }

            $itemLineTotal = ($itemPrice + $topperPriceSnapshot) * $itemQty;

            if ($itemName === '') {
                $itemName = 'Item';
            }
            if ($itemVariantLabel === '') {
                $itemVariantLabel = null;
            }

            $itemCustomisationNote = null;
            $itemInsert->bind_param('iiissdidssisd', $orderId, $itemProductId, $itemVariantId, $itemName, $itemVariantLabel, $itemPrice, $itemQty, $itemLineTotal, $itemCustomisationNote, $itemCakeMessage, $topperId, $topperNameSnapshot, $topperPriceSnapshot);
            $itemInsert->execute();
        }
    } else {
        $itemCakeMessage = '';
        $itemTopperId = null;
        $itemTopperName = null;
        $itemTopperPrice = 0.00;
        $itemInsert = $conn->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note, cake_message, topper_id, topper_name_snapshot, topper_price_snapshot) VALUES (?, ?, NULL, ?, NULL, ?, 1, ?, NULL, ?, ?, ?, ?, ?)');
        $itemInsert->bind_param('iisddsisd', $orderId, $fallbackProductId, $itemName, $amount, $amount, $itemCakeMessage, $itemTopperId, $itemTopperName, $itemTopperPrice);
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
        $tableCheck = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'admin_action_logs' LIMIT 1");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $logStmt = $conn->prepare('INSERT INTO admin_action_logs (admin_id, action_type, target_type, target_id, entity_type, entity_id, metadata_json) VALUES (?, "manual_order_punch", "order", ?, "manual_order", ?, ?)');
            $logStmt->bind_param('iiss', $adminId, $orderId, $orderNumber, $meta);
            $logStmt->execute();
        }
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
