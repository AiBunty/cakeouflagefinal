<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/includes/invoice_helpers.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    echo "CLI only\n";
    exit(1);
}

$INVOICE_RENDER_BUSINESS_SETTINGS_OVERRIDE = [
    'business_name' => 'Cakeouflage UAT Studio',
    'business_address_line1' => '21 Testing Avenue',
    'business_address_line2' => 'Proof Artifact Block',
    'business_city' => 'Bengaluru',
    'business_state' => 'Karnataka',
    'business_postal_code' => '560001',
    'business_phone' => '+91 90000 00000',
    'business_email' => 'uat@cakeouflage.test',
    'business_gst_number' => '29ABCDE1234F1Z5',
    'business_pan_number' => 'ABCDE1234F',
    'email_logo_url' => '/client/assets/images/mainlogo.svg',
    'navbar_logo_url' => '/client/assets/images/mainlogo.svg',
    'footer_logo_url' => '/client/assets/images/mainlogo.svg',
    'invoice_duplicate_copy' => 'on',
];

$outputDir = __DIR__ . '/storage/recovery';
if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Failed to create output directory\n");
    exit(1);
}

/**
 * @return array<int,array<string,mixed>>
 */
function buildFixtureItems(int $count): array
{
    $items = [];
    for ($index = 1; $index <= $count; $index++) {
        $items[] = [
            'product_name_snapshot' => 'Celebration Signature Cake Layer ' . $index . ' with Extended Decorative Title',
            'variant_snapshot' => '1.5 kg Eggless Chocolate Truffle with Custom Accent Finish Batch ' . $index,
            'quantity' => 1,
            'unit_price' => 875 + ($index * 25),
            'line_total' => 875 + ($index * 25),
            'cake_message' => 'Happy Celebration ' . $index . ' - keep the message centered and readable',
            'topper_name_snapshot' => 'Premium Acrylic Topper Style ' . $index,
            'topper_price_snapshot' => 99,
            'customisation_note' => 'Use dense border piping, fresh flowers on one edge, and preserve the long custom note for overflow layout validation on item ' . $index . '.',
        ];
    }

    return $items;
}

/**
 * @return array<string,mixed>
 */
function buildFixtureOrder(int $itemCount): array
{
    $items = buildFixtureItems($itemCount);
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float)$item['line_total'];
    }

    $discount = 150.0;
    $tax = 0.0;
    $delivery = 120.0;
    $grandTotal = round($subtotal - $discount + $tax + $delivery, 2);

    return [
        'id' => 9000 + $itemCount,
        'user_id' => 1,
        'order_number' => 'UAT-LAYOUT-' . $itemCount,
        'customer_name' => 'Fixture Customer ' . $itemCount,
        'customer_phone' => '99999999' . str_pad((string)$itemCount, 2, '0', STR_PAD_LEFT),
        'customer_email' => 'fixture' . $itemCount . '@cakeouflage.test',
        'payment_method' => 'upi_manual',
        'payment_status' => 'paid',
        'collection_status' => 'fully_paid',
        'order_status' => 'confirmed',
        'fulfilment_mode' => 'delivery',
        'settlement_reference' => 'UAT-PRINT-' . $itemCount,
        'created_at' => '2026-05-27 10:30:00',
        'collection_due_date' => '2026-05-28',
        'subtotal' => $subtotal,
        'discount_total' => $discount,
        'tax_total' => $tax,
        'delivery_fee' => $delivery,
        'grand_total' => $grandTotal,
        'advance_amount' => 0,
        'advance_received_amount' => 0,
        'net_collected_amount' => $grandTotal,
        'balance_due_amount' => 0,
        'total_refunded' => 0,
        'refund_amount' => 0,
        'invoice_items' => $items,
        'invoice_address_lines' => [
            'Fixture Tower, Sector 9',
            'Layout Validation Lane',
            'Bengaluru, Karnataka',
            'PIN: 560001',
        ],
    ];
}

$fixtures = [
    ['items' => 4, 'expected_layout' => 'half', 'html_file' => 'uat-invoice-layout-4-items.html'],
    ['items' => 5, 'expected_layout' => 'full', 'html_file' => 'uat-invoice-layout-5-items.html'],
];

$report = [];
foreach ($fixtures as $fixture) {
    $order = buildFixtureOrder((int)$fixture['items']);
    $html = invoice_render_html($order);
    $htmlPath = $outputDir . '/' . $fixture['html_file'];
    file_put_contents($htmlPath, $html);

    $report[] = [
        'items' => (int)$fixture['items'],
        'expected_layout' => (string)$fixture['expected_layout'],
        'html_file' => $fixture['html_file'],
        'order_number' => (string)$order['order_number'],
    ];
}

$reportPath = $outputDir . '/uat-invoice-layout-report.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode([
    'success' => true,
    'output_dir' => $outputDir,
    'fixtures' => $report,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;