<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$customerName = 'parindaulat';
$customerEmail = 'parin11@gmail.com';
$customerPhone = '+919330033000';

$pdo = Database::getConnection();

$findUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$findUser->execute(['email' => $customerEmail]);
$userId = (int)($findUser->fetchColumn() ?: 0);

if ($userId <= 0) {
    $insertUser = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, password_hash, role, is_active)
         VALUES (:full_name, :email, :phone, :password_hash, "customer", 1)'
    );
    $insertUser->execute([
        'full_name' => $customerName,
        'email' => $customerEmail,
        'phone' => $customerPhone,
        'password_hash' => password_hash('Dummy#12345', PASSWORD_DEFAULT),
    ]);
    $userId = (int)$pdo->lastInsertId();
}

$productId = (int)($pdo->query('SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
if ($productId <= 0) {
    $categoryId = (int)($pdo->query('SELECT id FROM categories WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    if ($categoryId <= 0) {
        $catSlug = 'dummy-orders-' . date('ymdHis');
        $insertCategory = $pdo->prepare(
            'INSERT INTO categories (parent_id, name, slug, description, sort_order, show_in_menu, is_featured, is_active)
             VALUES (NULL, :name, :slug, :description, 0, 0, 0, 1)'
        );
        $insertCategory->execute([
            'name' => 'Dummy Orders Category',
            'slug' => $catSlug,
            'description' => 'Auto-created local test category for dummy order validation',
        ]);
        $categoryId = (int)$pdo->lastInsertId();
    }

    $productSlug = 'dummy-test-cake-' . date('ymdHis');
    $insertProduct = $pdo->prepare(
        'INSERT INTO products (
            name, slug, short_description, long_description, sku,
            collection_category_id, dietary_tag, availability_status, lead_time_hours,
            delivery_eligible, pickup_eligible,
            starting_price, base_price, stock_quantity,
            is_featured, is_bestseller, is_chef_special,
            rating_average, review_count, is_b2b_enabled
         ) VALUES (
            :name, :slug, :short_description, :long_description, :sku,
            :collection_category_id, "regular", "in_stock", 24,
            1, 1,
            :starting_price, :base_price, 100,
            0, 0, 0,
            0.00, 0, 0
         )'
    );
    $insertProduct->execute([
        'name' => 'Dummy Test Cake',
        'slug' => $productSlug,
        'short_description' => 'Auto-created test product for local dummy order checks',
        'long_description' => 'This product is auto-created by tools/dummy-test-3-order-types.php for local order validation only.',
        'sku' => 'DUMMY-' . date('His'),
        'collection_category_id' => $categoryId,
        'starting_price' => 999.00,
        'base_price' => 999.00,
    ]);
    $productId = (int)$pdo->lastInsertId();
}

$stamp = date('ymdHis');

$onlineOrderNumber = 'ONL-' . $stamp;
$insertOnline = $pdo->prepare(
    'INSERT INTO orders (
        order_number, user_id, customer_name, customer_email, customer_phone,
        fulfilment_mode, order_status, payment_status, payment_method,
        order_source, subtotal, discount_total, tax_total, grand_total, admin_note
     ) VALUES (
        :order_number, :user_id, :customer_name, :customer_email, :customer_phone,
        "delivery", "pending", "pending", "gateway",
        "retail", :subtotal, 0, 0, :grand_total, :admin_note
     )'
);
$insertOnline->execute([
    'order_number' => $onlineOrderNumber,
    'user_id' => $userId,
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
    'subtotal' => 1299.00,
    'grand_total' => 1299.00,
    'admin_note' => 'Dummy ONLINE order test created locally',
]);
$onlineOrderId = (int)$pdo->lastInsertId();

$insertOnlineItem = $pdo->prepare(
    'INSERT INTO order_items (
        order_id, product_id, variant_id, product_name_snapshot, variant_snapshot,
        unit_price, quantity, line_total, customisation_note
     ) VALUES (
        :order_id, :product_id, NULL, :product_name_snapshot, NULL,
        :unit_price, 1, :line_total, :customisation_note
     )'
);
$insertOnlineItem->execute([
    'order_id' => $onlineOrderId,
    'product_id' => $productId,
    'product_name_snapshot' => 'Dummy Online Test Cake',
    'unit_price' => 1299.00,
    'line_total' => 1299.00,
    'customisation_note' => 'Local online order pipeline dummy test',
]);

$onlineAutomation = [
    'skipped' => true,
    'reason' => 'dummy DB insert test only',
];

$manualOrderNumber = 'MAN-' . $stamp;
$insertManual = $pdo->prepare(
    'INSERT INTO orders (
        order_number, user_id, customer_name, customer_email, customer_phone,
        fulfilment_mode, order_status, payment_status, payment_method,
        order_source, subtotal, discount_total, tax_total, grand_total, admin_note
     ) VALUES (
        :order_number, :user_id, :customer_name, :customer_email, :customer_phone,
        "pickup", "confirmed", "paid", "upi_manual",
        "retail", :subtotal, 0, 0, :grand_total, :admin_note
     )'
);
$insertManual->execute([
    'order_number' => $manualOrderNumber,
    'user_id' => $userId,
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
    'subtotal' => 1499.00,
    'grand_total' => 1499.00,
    'admin_note' => 'Dummy MANUAL order test created locally',
]);
$manualOrderId = (int)$pdo->lastInsertId();

$insertManualItem = $pdo->prepare(
    'INSERT INTO order_items (
        order_id, product_id, variant_id, product_name_snapshot, variant_snapshot,
        unit_price, quantity, line_total, customisation_note
     ) VALUES (
        :order_id, :product_id, NULL, :product_name_snapshot, NULL,
        :unit_price, 1, :line_total, :customisation_note
     )'
);
$insertManualItem->execute([
    'order_id' => $manualOrderId,
    'product_id' => $productId,
    'product_name_snapshot' => 'Dummy Manual Punch Test Cake',
    'unit_price' => 1499.00,
    'line_total' => 1499.00,
    'customisation_note' => 'Local manual order dummy test',
]);

$manualResult = [
    'order_id' => $manualOrderId,
    'order_number' => $manualOrderNumber,
    'created_by_admin_id' => 0,
];

$inquiryStmt = $pdo->prepare(
    'INSERT INTO inquiries (inquiry_type, name, email, phone, message, status)
     VALUES ("custom_cake", :name, :email, :phone, :message, "in_review")'
);
$inquiryStmt->execute([
    'name' => $customerName,
    'email' => $customerEmail,
    'phone' => $customerPhone,
    'message' => 'Dummy BYOC test inquiry created locally',
]);
$inquiryId = (int)$pdo->lastInsertId();

$quoteNumber = 'BYQ-' . $stamp;
$quoteStmt = $pdo->prepare(
    'INSERT INTO byoc_quotes (
        inquiry_id, quote_number, quote_subject, quote_message, quote_amount,
        currency, status, expires_at, accepted_at, order_id, created_by_admin_id
     ) VALUES (
        :inquiry_id, :quote_number, :quote_subject, :quote_message, :quote_amount,
        "INR", "sent", DATE_ADD(NOW(), INTERVAL 7 DAY), NULL, NULL, NULL
     )'
);
$quoteStmt->execute([
    'inquiry_id' => $inquiryId,
    'quote_number' => $quoteNumber,
    'quote_subject' => 'Dummy BYOC Test Cake',
    'quote_message' => 'Dummy BYOC order conversion local test',
    'quote_amount' => 1899.00,
]);
$byocQuoteId = (int)$pdo->lastInsertId();

$byocOrderNumber = 'BYOC-' . $stamp;
$insertByocOrder = $pdo->prepare(
    'INSERT INTO orders (
        order_number, user_id, customer_name, customer_email, customer_phone,
        fulfilment_mode, order_status, payment_status, payment_method,
        order_source, byoc_quote_id,
          subtotal, discount_total, tax_total, grand_total, admin_note
     ) VALUES (
        :order_number, :user_id, :customer_name, :customer_email, :customer_phone,
          "custom_delivery", "pending", "pending", "upi_manual",
        "byoc_quote", :byoc_quote_id,
          :subtotal, 0, 0, :grand_total, :admin_note
     )'
);
$insertByocOrder->execute([
    'order_number' => $byocOrderNumber,
    'user_id' => $userId,
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
    'byoc_quote_id' => $byocQuoteId,
    'subtotal' => 1899.00,
    'grand_total' => 1899.00,
    'admin_note' => 'Dummy BYOC order test created locally',
]);
$byocOrderId = (int)$pdo->lastInsertId();

$insertByocItem = $pdo->prepare(
    'INSERT INTO order_items (
        order_id, product_id, variant_id, product_name_snapshot, variant_snapshot,
        unit_price, quantity, line_total, customisation_note
     ) VALUES (
        :order_id, :product_id, NULL, :product_name_snapshot, NULL,
        :unit_price, 1, :line_total, :customisation_note
     )'
);
$insertByocItem->execute([
    'order_id' => $byocOrderId,
    'product_id' => $productId,
    'product_name_snapshot' => 'Dummy BYOC Test Cake',
    'unit_price' => 1899.00,
    'line_total' => 1899.00,
    'customisation_note' => 'BYOC dummy local order',
]);

$updateQuote = $pdo->prepare(
    'UPDATE byoc_quotes
     SET order_id = :order_id, status = "accepted", accepted_at = NOW(), updated_at = NOW()
     WHERE id = :id'
);
$updateQuote->execute([
    'order_id' => $byocOrderId,
    'id' => $byocQuoteId,
]);

$verifyStmt = $pdo->prepare(
    'SELECT id, order_number, customer_name, customer_email, customer_phone,
            payment_method, order_source, order_status, payment_status, grand_total, created_at
     FROM orders
     WHERE id IN (:online_id, :manual_id, :byoc_id)
     ORDER BY created_at DESC'
);
$verifyStmt->bindValue(':online_id', $onlineOrderId, PDO::PARAM_INT);
$verifyStmt->bindValue(':manual_id', (int)$manualResult['order_id'], PDO::PARAM_INT);
$verifyStmt->bindValue(':byoc_id', $byocOrderId, PDO::PARAM_INT);
$verifyStmt->execute();
$orders = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);

$result = [
    'customer' => [
        'name' => $customerName,
        'email' => $customerEmail,
        'phone' => $customerPhone,
        'user_id' => $userId,
    ],
    'created' => [
        'online' => [
            'order_id' => $onlineOrderId,
            'order_number' => $onlineOrderNumber,
            'automation' => $onlineAutomation,
        ],
        'manual' => $manualResult,
        'byoc' => [
            'order_id' => $byocOrderId,
            'order_number' => $byocOrderNumber,
            'byoc_quote_id' => $byocQuoteId,
            'byoc_quote_number' => $quoteNumber,
            'inquiry_id' => $inquiryId,
        ],
    ],
    'verification_orders' => $orders,
];

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
