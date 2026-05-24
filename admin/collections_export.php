<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');

use App\Core\Database;
use App\Services\FinanceReportService;

function collection_export_audit(Database $db, array $payload): void
{
    try {
        $createdAt = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO ar_export_lock_audit
                (archive_month, lock_token, source, event_type, variant, format, fingerprint, filters_json, issued_by_admin_id, issued_by_name, request_ip, user_agent, created_at)
             VALUES
                (:archive_month, :lock_token, :source, :event_type, :variant, :format, :fingerprint, :filters_json, :issued_by_admin_id, :issued_by_name, :request_ip, :user_agent, :created_at)',
            [
                'archive_month' => date('Y-m', strtotime($createdAt)),
                'lock_token' => (string)($payload['lock_token'] ?? ''),
                'source' => 'collections_export',
                'event_type' => (string)($payload['event_type'] ?? 'validated'),
                'variant' => (string)($payload['variant'] ?? ''),
                'format' => (string)($payload['format'] ?? ''),
                'fingerprint' => (string)($payload['fingerprint'] ?? ''),
                'filters_json' => (string)($payload['filters_json'] ?? '{}'),
                'issued_by_admin_id' => (int)($payload['issued_by_admin_id'] ?? 0),
                'issued_by_name' => (string)($payload['issued_by_name'] ?? ''),
                'request_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at' => $createdAt,
            ]
        );
    } catch (\Throwable $e) {
        error_log('[collections_export][audit] ' . $e->getMessage());
    }
}

$db = Database::getInstance();

$variant = strtolower(trim((string)($_GET['variant'] ?? '')));
$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
$lockToken = trim((string)($_GET['lock'] ?? ''));

$allowedVariants = ['aging', 'overdue_followup'];
$allowedFormats = ['csv'];

if (!in_array($variant, $allowedVariants, true) || !in_array($format, $allowedFormats, true)) {
    collection_export_audit($db, [
        'lock_token' => $lockToken,
        'event_type' => 'failed',
        'variant' => $variant,
        'format' => $format,
        'fingerprint' => '',
        'filters_json' => '{}',
        'issued_by_admin_id' => (int)($_SESSION['admin'] ?? 0),
        'issued_by_name' => (string)($_SESSION['admin_name'] ?? ''),
    ]);
    http_response_code(400);
    echo 'Invalid export request.';
    exit;
}

$locks = $_SESSION['ar_export_locks'] ?? [];
if (!is_array($locks) || $lockToken === '' || !isset($locks[$lockToken]) || !is_array($locks[$lockToken])) {
    collection_export_audit($db, [
        'lock_token' => $lockToken,
        'event_type' => 'missing',
        'variant' => $variant,
        'format' => $format,
        'fingerprint' => '',
        'filters_json' => '{}',
        'issued_by_admin_id' => (int)($_SESSION['admin'] ?? 0),
        'issued_by_name' => (string)($_SESSION['admin_name'] ?? ''),
    ]);
    http_response_code(409);
    echo 'Export lock missing. Refresh Collections Queue and retry export.';
    exit;
}

$lock = $locks[$lockToken];
$issuedAt = (int)($lock['issued_at'] ?? 0);
if ($issuedAt <= 0 || (time() - $issuedAt) > 1800) {
    collection_export_audit($db, [
        'lock_token' => $lockToken,
        'event_type' => 'expired',
        'variant' => $variant,
        'format' => $format,
        'fingerprint' => (string)($lock['fingerprint'] ?? ''),
        'filters_json' => json_encode((array)($lock['filters'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'issued_by_admin_id' => (int)($lock['issued_by_admin'] ?? 0),
        'issued_by_name' => (string)($lock['issued_by_name'] ?? ''),
    ]);
    http_response_code(409);
    echo 'Export lock expired. Refresh Collections Queue and retry export.';
    exit;
}

$source = (string)($lock['source'] ?? '');
if ($source !== 'collections_queue') {
    collection_export_audit($db, [
        'lock_token' => $lockToken,
        'event_type' => 'invalidated',
        'variant' => $variant,
        'format' => $format,
        'fingerprint' => (string)($lock['fingerprint'] ?? ''),
        'filters_json' => json_encode((array)($lock['filters'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'issued_by_admin_id' => (int)($lock['issued_by_admin'] ?? 0),
        'issued_by_name' => (string)($lock['issued_by_name'] ?? ''),
    ]);
    http_response_code(409);
    echo 'Export lock source mismatch.';
    exit;
}

$filters = (array)($lock['filters']['register'] ?? []);
$queueFilters = (array)($lock['filters']['queue'] ?? []);

$financeReports = new FinanceReportService();
$normalizedFilters = $financeReports->normalizeFilters($filters);
$normalizedQueueFilters = $financeReports->normalizeCollectionQueueFilters($queueFilters);

$fingerprint = hash('sha256', json_encode([
    'register' => $normalizedFilters,
    'queue' => $normalizedQueueFilters,
], JSON_UNESCAPED_SLASHES));
if (!hash_equals((string)($lock['fingerprint'] ?? ''), $fingerprint)) {
    collection_export_audit($db, [
        'lock_token' => $lockToken,
        'event_type' => 'invalidated',
        'variant' => $variant,
        'format' => $format,
        'fingerprint' => (string)($lock['fingerprint'] ?? ''),
        'filters_json' => json_encode(['register' => $normalizedFilters, 'queue' => $normalizedQueueFilters], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'issued_by_admin_id' => (int)($lock['issued_by_admin'] ?? 0),
        'issued_by_name' => (string)($lock['issued_by_name'] ?? ''),
    ]);
    http_response_code(409);
    echo 'Filter state changed; export lock invalidated.';
    exit;
}

collection_export_audit($db, [
    'lock_token' => $lockToken,
    'event_type' => 'validated',
    'variant' => $variant,
    'format' => $format,
    'fingerprint' => (string)($lock['fingerprint'] ?? ''),
    'filters_json' => json_encode(['register' => $normalizedFilters, 'queue' => $normalizedQueueFilters], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    'issued_by_admin_id' => (int)($lock['issued_by_admin'] ?? 0),
    'issued_by_name' => (string)($lock['issued_by_name'] ?? ''),
]);

$filenameDate = date('Ymd_His');

header('Content-Type: text/csv; charset=utf-8');
if ($variant === 'aging') {
    header('Content-Disposition: attachment; filename="ar_aging_buckets_' . $filenameDate . '.csv"');

    $rows = $financeReports->getAgingBucketRows($normalizedFilters, $normalizedQueueFilters);
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Cakeouflage AR Aging Buckets']);
    fputcsv($out, ['Export Timestamp', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Lock Token', $lockToken]);
    fputcsv($out, ['Locked By', (string)($lock['issued_by_name'] ?? 'Admin')]);
    fputcsv($out, ['Locked At', date('Y-m-d H:i:s', $issuedAt)]);
    fputcsv($out, []);
    fputcsv($out, ['Aging Bucket', 'Order Count', 'Balance Due Total', 'Earliest Due Date', 'Latest Due Date']);

    foreach ($rows as $row) {
        fputcsv($out, [
            (string)($row['aging_bucket'] ?? ''),
            (int)($row['order_count'] ?? 0),
            number_format((float)($row['balance_due_total'] ?? 0), 2, '.', ''),
            (string)($row['earliest_due_date'] ?? ''),
            (string)($row['latest_due_date'] ?? ''),
        ]);
    }
    fclose($out);
    collection_export_audit($db, [
        'lock_token' => $lockToken,
        'event_type' => 'exported',
        'variant' => $variant,
        'format' => $format,
        'fingerprint' => (string)($lock['fingerprint'] ?? ''),
        'filters_json' => json_encode(['register' => $normalizedFilters, 'queue' => $normalizedQueueFilters], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'issued_by_admin_id' => (int)($lock['issued_by_admin'] ?? 0),
        'issued_by_name' => (string)($lock['issued_by_name'] ?? ''),
    ]);
    exit;
}

header('Content-Disposition: attachment; filename="ar_overdue_followup_' . $filenameDate . '.csv"');
$rows = $financeReports->getOverdueFollowupRows($normalizedFilters, $normalizedQueueFilters);
$out = fopen('php://output', 'w');
fputcsv($out, ['Cakeouflage Overdue Follow-up Report']);
fputcsv($out, ['Export Timestamp', date('Y-m-d H:i:s')]);
fputcsv($out, ['Lock Token', $lockToken]);
fputcsv($out, ['Locked By', (string)($lock['issued_by_name'] ?? 'Admin')]);
fputcsv($out, ['Locked At', date('Y-m-d H:i:s', $issuedAt)]);
fputcsv($out, []);
fputcsv($out, ['Order No', 'Customer', 'Mobile', 'Due Date', 'Balance Due', 'Followup Status', 'Priority', 'Next Followup', 'Last Action', 'Last Action At', 'Last Actor', 'Last Message']);

foreach ($rows as $row) {
    fputcsv($out, [
        (string)($row['order_number'] ?? ''),
        (string)($row['customer_name'] ?? ''),
        (string)($row['customer_phone_e164'] ?: $row['customer_phone'] ?? ''),
        (string)($row['collection_due_date'] ?? ''),
        number_format((float)($row['balance_due_amount'] ?? 0), 2, '.', ''),
        (string)($row['followup_status'] ?? ''),
        (string)($row['collection_priority'] ?? ''),
        (string)($row['next_followup_at'] ?? ''),
        (string)($row['last_action_type'] ?? ''),
        (string)($row['last_action_at'] ?? ''),
        (string)($row['last_actor_name'] ?? ''),
        (string)($row['last_message'] ?? ''),
    ]);
}

fclose($out);
collection_export_audit($db, [
    'lock_token' => $lockToken,
    'event_type' => 'exported',
    'variant' => $variant,
    'format' => $format,
    'fingerprint' => (string)($lock['fingerprint'] ?? ''),
    'filters_json' => json_encode(['register' => $normalizedFilters, 'queue' => $normalizedQueueFilters], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    'issued_by_admin_id' => (int)($lock['issued_by_admin'] ?? 0),
    'issued_by_name' => (string)($lock['issued_by_name'] ?? ''),
]);
exit;
