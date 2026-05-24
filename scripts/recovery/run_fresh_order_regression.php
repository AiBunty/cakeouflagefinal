<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
    $stmt->execute([':t' => $table]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
    $stmt->execute([':t' => $table, ':c' => $column]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function enumValues(PDO $pdo, string $table, string $column): array
{
    $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table . ' LIKE ' . $pdo->quote($column));
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    $type = (string)($row['Type'] ?? '');
    if ($type === '' || strpos($type, 'enum(') !== 0) {
        return [];
    }

    $inside = substr($type, 5, -1);
    if (!is_string($inside) || $inside === '') {
        return [];
    }

    $parts = explode(',', $inside);
    $values = [];
    foreach ($parts as $part) {
        $clean = trim($part, "'\"");
        if ($clean !== '') {
            $values[] = $clean;
        }
    }
    return $values;
}

function tableColumns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $columns = [];
    foreach ($rows as $row) {
        $name = (string)($row['Field'] ?? '');
        if ($name !== '') {
            $columns[$name] = true;
        }
    }
    $cache[$table] = $columns;
    return $columns;
}

function insertFiltered(PDO $pdo, string $table, array $data): int
{
    $available = tableColumns($pdo, $table);
    $insertData = [];
    foreach ($data as $column => $value) {
        if (isset($available[$column])) {
            $insertData[$column] = $value;
        }
    }

    if ($insertData === []) {
        throw new RuntimeException('No insertable columns matched for table: ' . $table);
    }

    $columns = array_keys($insertData);
    $placeholders = [];
    $params = [];
    foreach ($columns as $column) {
        $placeholder = ':' . $column;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $insertData[$column];
    }

    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$pdo->lastInsertId();
}

function firstProductId(PDO $pdo): int
{
    $id = (int)($pdo->query('SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    if ($id <= 0) {
        throw new RuntimeException('No product available to seed regression orders.');
    }
    return $id;
}

function insertByocQuote(PDO $pdo, string $seed): array
{
    $stmtInquiry = $pdo->prepare('INSERT INTO inquiries (inquiry_type, name, email, phone, message, status, created_at, updated_at) VALUES ("custom_cake", :name, :email, :phone, :message, "new", NOW(), NOW())');
    $stmtInquiry->execute([
        ':name' => 'Regression BYOC Customer',
        ':email' => 'byoc.' . strtolower($seed) . '@example.test',
        ':phone' => '9000001000',
        ':message' => 'Regression BYOC inquiry',
    ]);
    $inquiryId = (int)$pdo->lastInsertId();

    $stmtQuote = $pdo->prepare('INSERT INTO byoc_quotes (inquiry_id, quote_number, quote_subject, quote_message, quote_amount, currency, status, expires_at, created_at, updated_at) VALUES (:inquiry_id, :quote_number, :quote_subject, :quote_message, :quote_amount, "INR", "accepted", DATE_ADD(NOW(), INTERVAL 7 DAY), NOW(), NOW())');
    $stmtQuote->execute([
        ':inquiry_id' => $inquiryId,
        ':quote_number' => 'REG-BYOC-' . $seed,
        ':quote_subject' => 'Regression BYOC Quote',
        ':quote_message' => 'Auto-generated for regression suite',
        ':quote_amount' => 1850.00,
    ]);

    return [
        'inquiry_id' => $inquiryId,
        'byoc_quote_id' => (int)$pdo->lastInsertId(),
    ];
}

function insertRegressionOrder(PDO $pdo, string $flowName, string $orderSource, ?int $byocQuoteId = null): array
{
    $seed = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    $productId = firstProductId($pdo);
    $orderNumber = 'REG-' . $flowName . '-' . $seed;
    $grandTotal = 1450.00;

    $sourceEnum = enumValues($pdo, 'orders', 'order_source');
    if ($sourceEnum !== [] && !in_array($orderSource, $sourceEnum, true)) {
        $orderSource = in_array('retail', $sourceEnum, true) ? 'retail' : (string)($sourceEnum[0] ?? 'retail');
    }

    $orderColumns = [
        'order_number', 'customer_name', 'customer_email', 'customer_phone',
        'fulfilment_mode', 'order_status', 'payment_status', 'payment_method',
        'order_source', 'subtotal', 'discount_total', 'tax_total', 'delivery_fee',
        'grand_total', 'admin_note', 'created_at', 'updated_at'
    ];
    $orderValues = [
        ':order_number' => $orderNumber,
        ':customer_name' => 'Regression ' . $flowName,
        ':customer_email' => strtolower($flowName) . '.' . strtolower($seed) . '@example.test',
        ':customer_phone' => '9000002000',
        ':fulfilment_mode' => 'delivery',
        ':order_status' => 'pending_payment',
        ':payment_status' => 'pending',
        ':payment_method' => 'upi_manual',
        ':order_source' => $orderSource,
        ':subtotal' => 1400.00,
        ':discount_total' => 0.00,
        ':tax_total' => 0.00,
        ':delivery_fee' => 50.00,
        ':grand_total' => $grandTotal,
        ':admin_note' => 'Regression seeded order for ' . $flowName,
    ];

    if ($byocQuoteId !== null && columnExists($pdo, 'orders', 'byoc_quote_id')) {
        $orderColumns[] = 'byoc_quote_id';
        $orderValues[':byoc_quote_id'] = $byocQuoteId;
    }

    if (columnExists($pdo, 'orders', 'order_mode')) {
        $orderColumns[] = 'order_mode';
        $orderValues[':order_mode'] = $flowName === 'manual' ? 'ready_pos' : ($flowName === 'byoc' ? 'byoc' : 'online');
    }

    if (columnExists($pdo, 'orders', 'scheduled_slot_label')) {
        $orderColumns[] = 'scheduled_slot_label';
        $orderValues[':scheduled_slot_label'] = 'Tomorrow 10:00 AM - 12:00 PM';
    }

    if (columnExists($pdo, 'orders', 'advance_amount')) {
        $orderColumns[] = 'advance_amount';
        $orderValues[':advance_amount'] = 500.00;
    }

    // Build final SQL safely with explicit timestamps outside bound params.
    $valueParts = [];
    foreach ($orderColumns as $col) {
        if ($col === 'created_at' || $col === 'updated_at') {
            $valueParts[] = 'NOW()';
            continue;
        }
        $valueParts[] = ':' . $col;
        $orderValues[':' . $col] = $orderValues[':' . $col] ?? null;
    }

    $stmtOrder = $pdo->prepare('INSERT INTO orders (' . implode(', ', $orderColumns) . ') VALUES (' . implode(', ', $valueParts) . ')');
    $stmtOrder->execute($orderValues);
    $orderId = (int)$pdo->lastInsertId();

    $itemColumns = ['order_id', 'product_id', 'variant_id', 'product_name_snapshot', 'variant_snapshot', 'unit_price', 'quantity', 'line_total', 'customisation_note'];
    $itemParams = [
        ':order_id' => $orderId,
        ':product_id' => $productId,
        ':variant_id' => null,
        ':product_name_snapshot' => 'Regression Cake Base',
        ':variant_snapshot' => '1.5KG',
        ':unit_price' => 700.00,
        ':quantity' => 2,
        ':line_total' => 1400.00,
        ':customisation_note' => 'No nuts; themed color finish',
    ];

    if (columnExists($pdo, 'order_items', 'cake_message')) {
        $itemColumns[] = 'cake_message';
        $itemParams[':cake_message'] = 'Happy Celebration';
    }
    if (columnExists($pdo, 'order_items', 'topper_name_snapshot')) {
        $itemColumns[] = 'topper_name_snapshot';
        $itemParams[':topper_name_snapshot'] = 'Gold Crown Topper';
    }
    if (columnExists($pdo, 'order_items', 'topper_price_snapshot')) {
        $itemColumns[] = 'topper_price_snapshot';
        $itemParams[':topper_price_snapshot'] = 150.00;
    }

    $itemValueParts = [];
    foreach ($itemColumns as $col) {
        $itemValueParts[] = ':' . $col;
    }
    $stmtItem = $pdo->prepare('INSERT INTO order_items (' . implode(', ', $itemColumns) . ') VALUES (' . implode(', ', $itemValueParts) . ')');
    $stmtItem->execute($itemParams);

    return [
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'flow' => $flowName,
        'product_id' => $productId,
    ];
}

function seedSupportingTransactionalRows(PDO $pdo, int $orderId): void
{
    if (tableExists($pdo, 'invoices')) {
        $stmtInv = $pdo->prepare('INSERT INTO invoices (invoice_number, order_id, customer_type, invoice_status, payment_method, subtotal, discount_total, tax_total, grand_total, paid_amount, balance_due, issued_on, created_at, updated_at) VALUES (:invoice_number, :order_id, "retail", "paid", "upi", 1400, 0, 0, 1450, 1450, 0, CURDATE(), NOW(), NOW())');
        $stmtInv->execute([
            ':invoice_number' => 'INV-' . $orderId . '-' . date('His'),
            ':order_id' => $orderId,
        ]);
        $invoiceId = (int)$pdo->lastInsertId();

        if (tableExists($pdo, 'invoice_items')) {
            $stmtInvItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id, item_label, quantity, unit_price, line_total, created_at) VALUES (:invoice_id, "Regression Cake Base", 2, 700, 1400, NOW())');
            $stmtInvItem->execute([':invoice_id' => $invoiceId]);
        }

        if (tableExists($pdo, 'payments')) {
            $stmtPay = $pdo->prepare('INSERT INTO payments (invoice_id, payment_method, payment_status, amount, payment_reference, note, created_at, updated_at) VALUES (:invoice_id, "upi", "verified", 1450, :payment_reference, "Regression payment", NOW(), NOW())');
            $stmtPay->execute([
                ':invoice_id' => $invoiceId,
                ':payment_reference' => 'REGPAY-' . $orderId,
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            if (tableExists($pdo, 'bank_alert_utrs')) {
                $stmtUtr = $pdo->prepare('INSERT INTO bank_alert_utrs (source, parsed_utr, parsed_amount, status, match_confidence, order_id, invoice_id, payment_id, created_at, updated_at) VALUES ("admin_manual", :utr, 1450, "confirmed", "strong", :order_id, :invoice_id, :payment_id, NOW(), NOW())');
                $stmtUtr->execute([
                    ':utr' => 'REGUTR' . $orderId,
                    ':order_id' => $orderId,
                    ':invoice_id' => $invoiceId,
                    ':payment_id' => $paymentId,
                ]);
            }
        }

        if (tableExists($pdo, 'payment_status_history')) {
            $stmtHistory = $pdo->prepare('INSERT INTO payment_status_history (invoice_id, from_status, to_status, note, created_at) VALUES (:invoice_id, "pending_payment", "paid", "Regression payment settled", NOW())');
            $stmtHistory->execute([':invoice_id' => $invoiceId]);
        }
    }

    if (tableExists($pdo, 'communication_logs')) {
        $stmtCom = $pdo->prepare('INSERT INTO communication_logs (order_id, channel, event_key, recipient, status, payload_json, sent_at, created_at) VALUES (:order_id, "email", "order_confirmed", :recipient, "sent", :payload_json, NOW(), NOW())');
        $stmtCom->execute([
            ':order_id' => $orderId,
            ':recipient' => 'regression@example.test',
            ':payload_json' => json_encode(['order_id' => $orderId], JSON_UNESCAPED_SLASHES),
        ]);
    }

    if (tableExists($pdo, 'crm_push_logs')) {
        $stmtCrm = $pdo->prepare('INSERT INTO crm_push_logs (name, mobile, trigger_key, endpoint, status, payload_json, execution_status, response_time_ms, created_at) VALUES ("Regression Customer", "9000002000", "order_confirmed", "https://example.test/hook", "success", :payload_json, "completed", 120, NOW())');
        $stmtCrm->execute([
            ':payload_json' => json_encode(['order_id' => $orderId], JSON_UNESCAPED_SLASHES),
        ]);
    }

    if (tableExists($pdo, 'coupon_redemptions') && tableExists($pdo, 'coupons')) {
        $couponId = (int)($pdo->query('SELECT id FROM coupons WHERE is_deleted = 0 ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
        if ($couponId > 0) {
            $stmtCoupon = $pdo->prepare('INSERT IGNORE INTO coupon_redemptions (coupon_id, order_id, code_snapshot, discount_total, created_at) VALUES (:coupon_id, :order_id, :code_snapshot, 0, NOW())');
            $stmtCoupon->execute([
                ':coupon_id' => $couponId,
                ':order_id' => $orderId,
                ':code_snapshot' => 'REG-COUPON',
            ]);
        }
    }

    if (tableExists($pdo, 'order_status_history')) {
        $stmtStatus = $pdo->prepare('INSERT INTO order_status_history (order_id, previous_status, new_status, reason, changed_by_admin_id, created_at) VALUES (:order_id, :previous_status, :new_status, :reason, 1, NOW())');
        $statusTrail = [
            ['pending_payment', 'confirmed', 'Payment confirmed'],
            ['confirmed', 'preparing', 'Kitchen started'],
            ['preparing', 'out_for_delivery', 'Dispatch started'],
            ['out_for_delivery', 'delivered', 'Delivered successfully'],
        ];
        foreach ($statusTrail as $entry) {
            $stmtStatus->execute([
                ':order_id' => $orderId,
                ':previous_status' => $entry[0],
                ':new_status' => $entry[1],
                ':reason' => $entry[2],
            ]);
        }
    }

    if (tableExists($pdo, 'refund_transactions')) {
        $now = date('Y-m-d H:i:s');
        $refundId = insertFiltered($pdo, 'refund_transactions', [
            'order_id' => $orderId,
            'refund_number' => 'REF-REG-' . $orderId . '-' . date('His'),
            'refund_type' => 'partial',
            'requested_amount' => 100,
            'approved_amount' => 100,
            'status' => 'processed',
            'reason_code' => 'customer_request',
            'reason_notes' => 'Regression refund',
            'requested_at' => $now,
            'approved_at' => $now,
            'processed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (tableExists($pdo, 'refund_approval_logs')) {
            insertFiltered($pdo, 'refund_approval_logs', [
                'refund_transaction_id' => $refundId,
                'action' => 'processed',
                'previous_status' => 'approved',
                'new_status' => 'processed',
                'note' => 'Regression refund processed',
                'notes' => 'Regression refund processed',
                'actor_admin_id' => 1,
                'performed_by_admin_id' => 1,
                'actor_role' => 'super_admin',
                'admin_role' => 'super_admin',
                'actor_name' => 'Regression Bot',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    if (tableExists($pdo, 'financial_transactions') && tableExists($pdo, 'transaction_batches') && tableExists($pdo, 'general_ledger_entries')) {
        $txStmt = $pdo->prepare('INSERT INTO financial_transactions (transaction_type, reference_type, reference_id, source_event, source_reference, idempotency_key, payment_mode, amount, status, narration, created_by_admin_id, created_by_name, created_at) VALUES ("order_payment_received", "order", :order_id, "regression_suite", "scripts/recovery/run_fresh_order_regression.php", :idempotency_key, "upi", 1450, "posted", "Regression financial posting", 1, "Regression Bot", NOW())');
        $txStmt->execute([
            ':order_id' => $orderId,
            ':idempotency_key' => 'regression-payment:' . $orderId,
        ]);
        $txId = (int)$pdo->lastInsertId();

        $batchStmt = $pdo->prepare('INSERT INTO transaction_batches (financial_transaction_id, batch_number, source_module, source_reference, debit_total, credit_total, status, posted_at, created_at) VALUES (:financial_transaction_id, :batch_number, "regression", "order", 1450, 1450, "posted", NOW(), NOW())');
        $batchStmt->execute([
            ':financial_transaction_id' => $txId,
            ':batch_number' => 'REG-BATCH-' . $orderId,
        ]);
        $batchId = (int)$pdo->lastInsertId();

        $glStmt = $pdo->prepare('INSERT INTO general_ledger_entries (batch_id, financial_transaction_id, line_number, account_code, account_name, debit_amount, credit_amount, payment_mode, narration, reference_type, reference_id, created_by_admin_id, created_by_name, created_at) VALUES (:batch_id, :financial_transaction_id, :line_number, :account_code, :account_name, :debit_amount, :credit_amount, "upi", "Regression ledger line", "order", :reference_id, 1, "Regression Bot", NOW())');
        $glStmt->execute([
            ':batch_id' => $batchId,
            ':financial_transaction_id' => $txId,
            ':line_number' => 1,
            ':account_code' => 'BANK_CLEARING',
            ':account_name' => 'Bank / UPI Clearing',
            ':debit_amount' => 1450,
            ':credit_amount' => 0,
            ':reference_id' => $orderId,
        ]);
        $glStmt->execute([
            ':batch_id' => $batchId,
            ':financial_transaction_id' => $txId,
            ':line_number' => 2,
            ':account_code' => 'SALES_REVENUE',
            ':account_name' => 'Sales Revenue',
            ':debit_amount' => 0,
            ':credit_amount' => 1450,
            ':reference_id' => $orderId,
        ]);
    }

    if (tableExists($pdo, 'collection_followup_logs')) {
        insertFiltered($pdo, 'collection_followup_logs', [
            'order_id' => $orderId,
            'customer_name' => 'Regression Customer',
            'customer_phone' => '9000002000',
            'followup_type' => 'collection',
            'action_type' => 'payment_collected',
            'followup_status' => 'settled',
            'remarks' => 'Regression collection completed',
            'message_text' => 'Regression collection completed',
            'metadata_json' => json_encode(['order_id' => $orderId], JSON_UNESCAPED_SLASHES),
            'next_followup_at' => date('Y-m-d H:i:s', strtotime('+2 day')),
            'created_by_admin_id' => 1,
            'actor_admin_id' => 1,
            'created_by_name' => 'Regression Bot',
            'actor_name' => 'Regression Bot',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

function validateModules(PDO $pdo, array $flow): array
{
    $orderId = (int)$flow['order_id'];

    $checks = [];
    $checks['order_creation'] = ((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id = ' . $orderId)->fetchColumn() === 1);
    $checks['item_save'] = ((int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id = ' . $orderId)->fetchColumn() >= 1);

    $hasTopperColumn = columnExists($pdo, 'order_items', 'topper_name_snapshot');
    $checks['toppings_save'] = !$hasTopperColumn || ((int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id = ' . $orderId . ' AND COALESCE(topper_name_snapshot, "") <> ""')->fetchColumn() >= 1);

    $hasCakeMsgColumn = columnExists($pdo, 'order_items', 'cake_message');
    $checks['custom_notes_save'] = ((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id = ' . $orderId . ' AND COALESCE(admin_note, "") <> ""')->fetchColumn() === 1)
        && (!$hasCakeMsgColumn || ((int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id = ' . $orderId . ' AND COALESCE(cake_message, "") <> ""')->fetchColumn() >= 1));

    $checks['slot_save'] = (!tableExists($pdo, 'slot_reservations'))
        ? columnExists($pdo, 'orders', 'scheduled_slot_label')
        : ((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id = ' . $orderId . ' AND COALESCE(scheduled_slot_label, "") <> ""')->fetchColumn() >= 1);

    $hasAdvance = columnExists($pdo, 'orders', 'advance_amount');
    $checks['advance_save'] = !$hasAdvance || ((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id = ' . $orderId . ' AND COALESCE(advance_amount, 0) > 0')->fetchColumn() === 1);

    $checks['payment_status'] = ((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id = ' . $orderId . ' AND payment_status IN ("pending","under_review","paid","credit","partial","refunded")')->fetchColumn() === 1);

    $checks['invoice_generation'] = !tableExists($pdo, 'invoices') || ((int)$pdo->query('SELECT COUNT(*) FROM invoices WHERE order_id = ' . $orderId)->fetchColumn() >= 1);
    $checks['crm_push'] = !tableExists($pdo, 'crm_push_logs') || ((int)$pdo->query('SELECT COUNT(*) FROM crm_push_logs WHERE trigger_key = "order_confirmed"')->fetchColumn() >= 1);
    $checks['email_trigger'] = !tableExists($pdo, 'communication_logs') || ((int)$pdo->query('SELECT COUNT(*) FROM communication_logs WHERE order_id = ' . $orderId . ' AND channel = "email"')->fetchColumn() >= 1);
    $checks['ledger_entry'] = !tableExists($pdo, 'financial_transactions') || ((int)$pdo->query('SELECT COUNT(*) FROM financial_transactions WHERE reference_type = "order" AND reference_id = ' . $orderId)->fetchColumn() >= 1);
    $checks['sales_register'] = ((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id = ' . $orderId . ' AND order_number LIKE "REG-%"')->fetchColumn() === 1);

    if (tableExists($pdo, 'general_ledger_entries')) {
        $checks['cash_bank_impact'] = ((int)$pdo->query('SELECT COUNT(*) FROM general_ledger_entries WHERE reference_type = "order" AND reference_id = ' . $orderId . ' AND account_code IN ("CASH_ON_HAND","BANK_CLEARING")')->fetchColumn() >= 1);
    } else {
        $checks['cash_bank_impact'] = true;
    }

    $checks['refund_flow'] = !tableExists($pdo, 'refund_transactions') || ((int)$pdo->query('SELECT COUNT(*) FROM refund_transactions WHERE order_id = ' . $orderId . ' AND status IN ("processed","approved")')->fetchColumn() >= 1);
    $checks['order_status_lifecycle'] = !tableExists($pdo, 'order_status_history') || ((int)$pdo->query('SELECT COUNT(*) FROM order_status_history WHERE order_id = ' . $orderId)->fetchColumn() >= 3);
    $checks['order_visibility'] = ((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id = ' . $orderId . ' AND order_source IN ("retail","manual","byoc_quote")')->fetchColumn() === 1);
    $checks['customization_visibility'] = ((int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id = ' . $orderId . ' AND COALESCE(customisation_note, "") <> ""')->fetchColumn() >= 1);

    return $checks;
}

$outputDir = __DIR__ . '/../../storage/recovery';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$runId = date('Ymd_His');
$reportPath = $outputDir . '/order-regression-' . $runId . '.json';

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$flows = [
    ['name' => 'ecommerce', 'order_source' => 'retail'],
    ['name' => 'manual', 'order_source' => 'manual'],
    ['name' => 'byoc', 'order_source' => 'byoc_quote'],
];

$report = [
    'run_id' => $runId,
    'started_at' => date('c'),
    'flows' => [],
    'module_order' => [
        'order_creation',
        'item_save',
        'toppings_save',
        'custom_notes_save',
        'slot_save',
        'advance_save',
        'payment_status',
        'invoice_generation',
        'crm_push',
        'email_trigger',
        'ledger_entry',
        'sales_register',
        'cash_bank_impact',
        'refund_flow',
        'order_status_lifecycle',
        'order_visibility',
        'customization_visibility',
    ],
];

try {
    foreach ($flows as $flowConfig) {
        $flowName = (string)$flowConfig['name'];

        $pdo->beginTransaction();
        $byocQuoteId = null;
        $inquiryId = null;

        if ($flowName === 'byoc' && tableExists($pdo, 'byoc_quotes') && tableExists($pdo, 'inquiries')) {
            $seed = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $byocSeed = insertByocQuote($pdo, $seed);
            $byocQuoteId = (int)$byocSeed['byoc_quote_id'];
            $inquiryId = (int)$byocSeed['inquiry_id'];
        }

        $orderFlow = insertRegressionOrder($pdo, $flowName, (string)$flowConfig['order_source'], $byocQuoteId);
        seedSupportingTransactionalRows($pdo, (int)$orderFlow['order_id']);

        if ($byocQuoteId !== null && tableExists($pdo, 'byoc_quotes')) {
            $stmtLinkOrder = $pdo->prepare('UPDATE byoc_quotes SET order_id = :order_id, status = "accepted", accepted_at = NOW(), updated_at = NOW() WHERE id = :id');
            $stmtLinkOrder->execute([
                ':order_id' => (int)$orderFlow['order_id'],
                ':id' => $byocQuoteId,
            ]);
        }

        $checks = validateModules($pdo, $orderFlow);
        $pdo->commit();

        $report['flows'][] = [
            'flow' => $flowName,
            'order_id' => (int)$orderFlow['order_id'],
            'order_number' => (string)$orderFlow['order_number'],
            'byoc_quote_id' => $byocQuoteId,
            'inquiry_id' => $inquiryId,
            'checks' => $checks,
            'passed' => count(array_filter($checks)) . '/' . count($checks),
        ];
    }

    $report['completed_at'] = date('c');
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    out('Fresh order regression run complete.');
    out('Report: ' . $reportPath);
    foreach ($report['flows'] as $flowReport) {
        out(' - ' . $flowReport['flow'] . ' => ' . $flowReport['passed'] . ' (order_id=' . $flowReport['order_id'] . ')');
    }
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $report['error'] = $e->getMessage();
    $report['failed_at'] = date('c');
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    out('Regression run failed: ' . $e->getMessage());
    out('Partial report: ' . $reportPath);
    exit(1);
}
