<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;
use App\Services\OrderRevisionService;
use App\Services\PaymentSplitService;
use App\Services\RefundService;

$db = Database::getInstance();

$result = [
    'timestamp' => date('c'),
    'config' => [],
    'tests' => [],
];

$originalLock = (int)($db->fetchScalar("SELECT setting_value FROM settings WHERE setting_key='accounting_lock_days' LIMIT 1") ?? 30);
$result['config']['original_accounting_lock_days'] = $originalLock;

$db->execute(
    "INSERT INTO settings (setting_key, setting_value)
     VALUES ('accounting_lock_days', '1')
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
);
$result['config']['effective_accounting_lock_days'] = 1;

$refundOrderId = (int)($db->fetchScalar("SELECT id FROM orders WHERE order_status IN ('completed','delivered') ORDER BY id DESC LIMIT 1") ?? 0);
$revisionOrderId = (int)($db->fetchScalar("SELECT id FROM orders WHERE order_status IN ('confirmed','preparing') ORDER BY id DESC LIMIT 1") ?? 0);
$splitOrderId = $revisionOrderId;

if ($refundOrderId > 0) {
    $db->execute("UPDATE orders SET created_at = DATE_SUB(NOW(), INTERVAL 5 DAY) WHERE id = :id", ['id' => $refundOrderId]);
}
if ($revisionOrderId > 0) {
    $db->execute("UPDATE orders SET created_at = DATE_SUB(NOW(), INTERVAL 5 DAY) WHERE id = :id", ['id' => $revisionOrderId]);
}

$refundSvc = new RefundService();
$revisionSvc = new OrderRevisionService($db);
$splitSvc = new PaymentSplitService($db);

$refundRes = ['success' => false, 'message' => 'refund order not found'];
if ($refundOrderId > 0) {
    $refundRes = $refundSvc->submitRequest(
        Database::getConnection(),
        $refundOrderId,
        [
            'reason_code' => 'CUSTOMER_CANCELLED',
            'reason_notes' => 'Lock regression check',
            'requested_amount' => 50.00,
        ],
        1,
        [
            'admin_role' => 'super_admin',
            'admin_permissions' => ['can_force_refund', 'order_refund'],
            'ip_address' => '127.0.0.1',
        ]
    );
}
$result['tests']['refund_submit_locked'] = [
    'order_id' => $refundOrderId,
    'success' => (bool)($refundRes['success'] ?? false),
    'message' => (string)($refundRes['message'] ?? ''),
];

$revisionRes = ['success' => false, 'message' => 'revision order not found'];
if ($revisionOrderId > 0) {
    $revisionRes = $revisionSvc->submitRevision([
        'order_id' => $revisionOrderId,
        'revision_type' => 'customer_request',
        'new_grand_total' => 1000.00,
        'new_items_snapshot' => [['product_name' => 'Regression Item', 'unit_price' => 1000.00, 'quantity' => 1]],
        'revision_reason' => 'Lock regression check',
        'admin_id' => 1,
    ]);
}
$result['tests']['revision_submit_locked'] = [
    'order_id' => $revisionOrderId,
    'success' => (bool)($revisionRes['success'] ?? false),
    'message' => (string)($revisionRes['message'] ?? ''),
];

$splitRes = ['success' => false, 'message' => 'split order not found'];
if ($splitOrderId > 0) {
    $splitRes = $splitSvc->recordSplit(
        $splitOrderId,
        [['method' => 'cash', 'amount' => 100.00, 'reference' => 'LOCK-TEST']],
        1,
        ['admin_name' => 'Lock Test', 'source_channel' => 'phase1_regression', 'business_date' => date('Y-m-d')]
    );
}
$result['tests']['split_payment_locked'] = [
    'order_id' => $splitOrderId,
    'success' => (bool)($splitRes['success'] ?? false),
    'message' => (string)($splitRes['message'] ?? ''),
];

$db->execute(
    "INSERT INTO settings (setting_key, setting_value)
     VALUES ('accounting_lock_days', :value)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
    ['value' => (string)$originalLock]
);
$result['config']['restored_accounting_lock_days'] = $originalLock;

$reportFile = __DIR__ . '/phase1_lock_regression_report.json';
file_put_contents($reportFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
