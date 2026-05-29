<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;
use App\Services\FinanceReportService;

$db = Database::getInstance();
$svc = new FinanceReportService($db);

$order = $db->fetchOne(
    "SELECT id, order_number, payment_status, COALESCE(advance_amount,0) AS advance_amount,
            COALESCE(revised_grand_total, grand_total) AS effective_total
     FROM orders
     WHERE order_status IN ('confirmed','preparing')
     ORDER BY id DESC LIMIT 1"
);

$result = [
    'timestamp' => date('c'),
    'selected_order' => $order,
    'register_assertions' => [],
    'variance_snapshot' => [],
];

if ($order === null) {
    $result['error'] = 'No eligible order found for reporting regression';
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$orderId = (int)$order['id'];
$originalStatus = (string)$order['payment_status'];
$originalAdvance = (float)$order['advance_amount'];
$effectiveTotal = (float)$order['effective_total'];
$testAdvance = min(400.0, max(100.0, round($effectiveTotal * 0.25, 2)));

$db->execute(
    "UPDATE orders
     SET payment_status = 'part_paid',
         advance_amount = :advance,
         created_at = NOW(),
         updated_at = NOW()
     WHERE id = :id",
    ['advance' => $testAdvance, 'id' => $orderId]
);

$filters = $svc->normalizeFilters([
    'view' => 'sales',
    'date_preset' => 'custom',
    'from_date' => date('Y-m-d'),
    'to_date' => date('Y-m-d'),
    'order_no' => (string)$order['order_number'],
    'payment_status' => 'all',
]);

$register = $svc->getRegister($filters, 50, 1);
$row = $register['rows'][0] ?? null;

if (is_array($row)) {
    $realized = (float)($row['realized_gross_amount'] ?? 0);
    $net = (float)($row['net_collected_amount'] ?? 0);
    $balance = (float)($row['balance_due_amount'] ?? 0);

    $result['register_assertions'] = [
        'order_id' => $orderId,
        'expected_advance_floor' => round($testAdvance, 2),
        'realized_gross_amount' => round($realized, 2),
        'net_collected_amount' => round($net, 2),
        'balance_due_amount' => round($balance, 2),
        'collection_status_label' => (string)($row['collection_status_label'] ?? ''),
        'finance_status_label' => (string)($row['finance_status_label'] ?? ''),
    ];
}

$result['variance_snapshot'] = $svc->getGLvsOrdersVariance(date('Y-m-01'), date('Y-m-d'));

$db->execute(
    "UPDATE orders
     SET payment_status = :status,
         advance_amount = :advance,
         updated_at = NOW()
     WHERE id = :id",
    ['status' => $originalStatus, 'advance' => $originalAdvance, 'id' => $orderId]
);

$reportFile = __DIR__ . '/phase1_reporting_regression_report.json';
file_put_contents($reportFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
