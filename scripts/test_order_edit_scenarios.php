<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Services\OrderEditService;

function firstRow(\PDO $pdo, string $sql): ?array
{
    $stmt = $pdo->query($sql);
    $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
    return $row === false ? null : $row;
}

function orderItems(\PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare('SELECT id, product_name_snapshot, quantity, unit_price, line_total FROM order_items WHERE order_id = :id ORDER BY id ASC');
    $stmt->execute(['id' => $orderId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}

function getEnumOptions(\PDO $pdo, string $table, string $column): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
    $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
    $type = (string)($row['Type'] ?? '');
    if ($type === '' || strpos($type, 'enum(') !== 0) {
        return [];
    }

    $inside = substr($type, 5, -1);
    if ($inside === false) {
        return [];
    }

    $parts = array_map(static function (string $value): string {
        return trim($value, "'\"");
    }, explode(',', $inside));

    return array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
}

function firstProductId(\PDO $pdo): int
{
    $id = (int)($pdo->query('SELECT id FROM products ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    if ($id <= 0) {
        throw new RuntimeException('No product rows available for fixture creation');
    }
    return $id;
}

function createByocQuote(\PDO $pdo, string $seed): int
{
    $inquiryInsert = $pdo->prepare(
        'INSERT INTO inquiries (inquiry_type, name, email, phone, message, status, created_at, updated_at)
         VALUES ("custom_cake", :name, :email, :phone, :message, "new", NOW(), NOW())'
    );
    $inquiryInsert->execute([
        'name' => 'Scenario BYOC Inquiry',
        'email' => 'scenario.byoc+' . strtolower($seed) . '@example.test',
        'phone' => '9000000001',
        'message' => 'Fixture inquiry for BYOC order-edit test',
    ]);
    $inquiryId = (int)$pdo->lastInsertId();

    $quoteInsert = $pdo->prepare(
        'INSERT INTO byoc_quotes
            (inquiry_id, quote_number, quote_subject, quote_message, quote_amount, currency, status, expires_at, created_by_admin_id, created_at, updated_at)
         VALUES
            (:inquiry_id, :quote_number, :quote_subject, :quote_message, :quote_amount, "INR", "accepted", DATE_ADD(NOW(), INTERVAL 7 DAY), NULL, NOW(), NOW())'
    );
    $quoteInsert->execute([
        'inquiry_id' => $inquiryId,
        'quote_number' => 'BYOC-Q-' . $seed,
        'quote_subject' => 'Scenario BYOC Quote',
        'quote_message' => 'Fixture quote for BYOC order-edit test',
        'quote_amount' => 300,
    ]);

    return (int)$pdo->lastInsertId();
}

function createOrderFixture(\PDO $pdo, array $options): int
{
    $productId = firstProductId($pdo);
    $seed = date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $orderNumber = 'TEST-' . $seed;
    $source = (string)($options['order_source'] ?? 'retail');
    $orderStatus = (string)($options['order_status'] ?? 'pending_payment');
    $paymentStatus = (string)($options['payment_status'] ?? 'pending');
    $productionStatus = (string)($options['production_status'] ?? 'pending');

    $pdo->beginTransaction();
    try {
        $byocQuoteId = null;
        if ($source === 'byoc_quote') {
            $byocQuoteId = createByocQuote($pdo, str_replace('-', '', $seed));
        }

        $orderInsert = $pdo->prepare(
            'INSERT INTO orders
                (order_number, customer_name, customer_email, customer_phone, fulfilment_mode, order_status, payment_status, payment_method, order_source, byoc_quote_id, production_status, subtotal, discount_total, tax_total, delivery_fee, grand_total, admin_note, created_at, updated_at)
             VALUES
                (:order_number, :customer_name, :customer_email, :customer_phone, :fulfilment_mode, :order_status, :payment_status, :payment_method, :order_source, :byoc_quote_id, :production_status, :subtotal, 0, 0, 0, :grand_total, :admin_note, NOW(), NOW())'
        );
        $orderInsert->execute([
            'order_number' => $orderNumber,
            'customer_name' => 'Scenario Tester',
            'customer_email' => 'scenario+' . strtolower(substr(str_replace('-', '', $seed), -8)) . '@example.test',
            'customer_phone' => '9000000000',
            'fulfilment_mode' => 'pickup',
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'payment_method' => 'cod',
            'order_source' => $source,
            'byoc_quote_id' => $byocQuoteId,
            'production_status' => $productionStatus,
            'subtotal' => 300,
            'grand_total' => 300,
            'admin_note' => 'Fixture for order edit scenario tests',
        ]);

        $orderId = (int)$pdo->lastInsertId();

        $itemInsert = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note)
             VALUES
                (:order_id, :product_id, NULL, :name, NULL, :unit_price, :qty, :line_total, NULL)'
        );

        $itemInsert->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
            'name' => 'Scenario Item A',
            'unit_price' => 100,
            'qty' => 1,
            'line_total' => 100,
        ]);

        $itemInsert->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
            'name' => 'Scenario Item B',
            'unit_price' => 200,
            'qty' => 1,
            'line_total' => 200,
        ]);

        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$pdo = Database::getConnection();
$service = new OrderEditService();
$results = [];

$sourceOptions = getEnumOptions($pdo, 'orders', 'order_source');
$supportsManualSource = in_array('manual', $sourceOptions, true);
$manualWhere = $supportsManualSource
    ? "o.order_source = 'manual'"
    : "(o.order_source = 'retail' OR o.order_source IS NULL)";

$manualOrder = firstRow(
    $pdo,
    "SELECT o.id
     FROM orders o
     JOIN (
       SELECT order_id, COUNT(*) AS item_count
       FROM order_items
       GROUP BY order_id
     ) oi ON oi.order_id = o.id
     WHERE {$manualWhere}
       AND o.payment_status IN ('pending','under_review','failed','rejected')
       AND o.order_status NOT IN ('refunded','partially_refunded','fully_refunded')
       AND COALESCE(o.production_status, 'pending') IN ('pending','not_required')
       AND oi.item_count >= 2
     ORDER BY o.id DESC
     LIMIT 1"
);

if ($manualOrder === null) {
    $manualOrder = ['id' => createOrderFixture($pdo, [
        'order_source' => $supportsManualSource ? 'manual' : 'retail',
        'order_status' => 'pending_payment',
        'payment_status' => 'pending',
        'production_status' => 'pending',
    ])];
}

if ($manualOrder === null) {
    $results[] = [
        'scenario' => 'unpaid_manual_edit_add_delete',
        'order_id' => null,
        'pass' => false,
        'message' => 'No manual-compatible order found and fixture creation failed',
    ];
} else {
    $manualId = (int)$manualOrder['id'];
    $items = orderItems($pdo, $manualId);
    $first = $items[0] ?? null;
    $second = $items[1] ?? null;

    $payload = [
        'customer_phone' => '9999911111',
        'admin_note' => 'Scenario test: manual-compatible meaningful edit',
        'scheduled_slot_label' => 'Scenario Slot A',
        'edit_reason' => 'Scenario test for edit/add/delete on unpaid manual-compatible order',
        'discount_override' => '0',
        'delivery_fee_override' => '0',
        'items' => [
            [
                'item_id' => (int)($first['id'] ?? 0),
                'name' => (string)($first['product_name_snapshot'] ?? 'Edited Item'),
                'quantity' => max(1, (int)($first['quantity'] ?? 1) + 1),
                'unit_price' => (string)((float)($first['unit_price'] ?? 0) + 5),
                'cake_message' => 'Scenario edit message',
            ],
        ],
        'items_new' => [
            [
                'name' => 'Scenario New Item 1',
                'quantity' => 1,
                'unit_price' => '75.00',
                'cake_message' => 'Scenario add row 1',
            ],
            [
                'name' => 'Scenario New Item 2',
                'quantity' => 2,
                'unit_price' => '55.00',
                'cake_message' => 'Scenario add row 2',
            ],
        ],
        'delete_item_ids' => $second ? [(int)$second['id']] : [],
    ];

    $outcome = $service->apply($pdo, $manualId, $payload, 1, [
        'admin_role' => 'superadmin',
        'admin_permissions' => ['order_edit'],
        'ip_address' => '127.0.0.1',
    ]);

    $results[] = [
        'scenario' => 'unpaid_manual_edit_add_delete',
        'order_id' => $manualId,
        'pass' => (bool)($outcome['success'] ?? false),
        'message' => (string)($outcome['message'] ?? ''),
    ];
}

$byocOrder = firstRow(
    $pdo,
    "SELECT id
     FROM orders
     WHERE (order_source = 'byoc_quote' OR byoc_quote_id IS NOT NULL)
       AND payment_status IN ('pending','under_review','failed','rejected')
       AND order_status NOT IN ('refunded','partially_refunded','fully_refunded')
       AND COALESCE(production_status, 'pending') IN ('pending','not_required')
     ORDER BY id DESC
     LIMIT 1"
);

if ($byocOrder === null) {
    $byocOrder = ['id' => createOrderFixture($pdo, [
        'order_source' => 'byoc_quote',
        'order_status' => 'pending_payment',
        'payment_status' => 'pending',
        'production_status' => 'pending',
    ])];
}

if ($byocOrder === null) {
    $results[] = [
        'scenario' => 'byoc_unpaid_message_only',
        'order_id' => null,
        'pass' => false,
        'message' => 'No unpaid BYOC order found and fixture creation failed',
    ];
} else {
    $byocId = (int)$byocOrder['id'];
    $items = orderItems($pdo, $byocId);
    $first = $items[0] ?? null;

    $payload = [
        'customer_phone' => '',
        'admin_note' => 'Scenario test: BYOC message-only edit',
        'scheduled_slot_label' => '',
        'edit_reason' => 'Scenario test for message-only BYOC edit',
        'items' => [
            [
                'item_id' => (int)($first['id'] ?? 0),
                'cake_message' => 'Scenario BYOC message update only',
            ],
        ],
        'items_new' => [],
        'delete_item_ids' => [],
    ];

    $outcome = $service->apply($pdo, $byocId, $payload, 1, [
        'admin_role' => 'superadmin',
        'admin_permissions' => ['order_edit'],
        'ip_address' => '127.0.0.1',
    ]);

    $results[] = [
        'scenario' => 'byoc_unpaid_message_only',
        'order_id' => $byocId,
        'pass' => (bool)($outcome['success'] ?? false),
        'message' => (string)($outcome['message'] ?? ''),
    ];
}

$refundedOrder = firstRow(
    $pdo,
    "SELECT id
     FROM orders
     WHERE order_status IN ('refunded','partially_refunded','fully_refunded')
        OR payment_status IN ('refunded','partially_refunded')
     ORDER BY id DESC
     LIMIT 1"
);

if ($refundedOrder === null) {
    $refundedOrder = ['id' => createOrderFixture($pdo, [
        'order_source' => 'retail',
        'order_status' => 'fully_refunded',
        'payment_status' => 'refunded',
        'production_status' => 'delivered',
    ])];
}

if ($refundedOrder === null) {
    $results[] = [
        'scenario' => 'refunded_order_blocked',
        'order_id' => null,
        'pass' => false,
        'message' => 'No refunded order found and fixture creation failed',
    ];
} else {
    $refundId = (int)$refundedOrder['id'];
    $payload = [
        'customer_phone' => '9999988888',
        'admin_note' => 'Scenario test: should block',
        'scheduled_slot_label' => 'Should Block',
        'edit_reason' => 'Scenario test for refunded lock',
    ];

    $outcome = $service->apply($pdo, $refundId, $payload, 1, [
        'admin_role' => 'superadmin',
        'admin_permissions' => ['order_edit'],
        'ip_address' => '127.0.0.1',
    ]);

    $results[] = [
        'scenario' => 'refunded_order_blocked',
        'order_id' => $refundId,
        'pass' => ((bool)($outcome['success'] ?? false) === false),
        'message' => (string)($outcome['message'] ?? ''),
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
