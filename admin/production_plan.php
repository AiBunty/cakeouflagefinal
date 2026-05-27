<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

function production_bootstrap_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS order_production_plan (
        order_id BIGINT UNSIGNED NOT NULL,
        is_excluded TINYINT(1) NOT NULL DEFAULT 0,
        override_slot DATETIME NULL,
        override_reason VARCHAR(255) NULL,
        override_updated_by_admin_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (order_id),
        KEY idx_production_plan_excluded (is_excluded),
        KEY idx_production_plan_override_slot (override_slot),
        CONSTRAINT fk_production_plan_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS order_production_audit_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        event_type ENUM('exclude','include','override_slot','clear_override') NOT NULL,
        event_note VARCHAR(500) NULL,
        changed_by_admin_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_production_audit_order (order_id),
        KEY idx_production_audit_created (created_at),
        CONSTRAINT fk_production_audit_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        CONSTRAINT fk_production_audit_admin FOREIGN KEY (changed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $done = true;
}

function production_log_audit(mysqli $conn, int $orderId, string $eventType, ?string $eventNote, int $adminId): void
{
    if ($orderId <= 0) {
        return;
    }
    $stmt = $conn->prepare('INSERT INTO order_production_audit_logs (order_id, event_type, event_note, changed_by_admin_id) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('issi', $orderId, $eventType, $eventNote, $adminId);
    $stmt->execute();
    $stmt->close();
}

function production_sheet_title(string $raw, int $fallbackIndex): string
{
    $sanitized = preg_replace('/[\\\\\/*\?:\[\]]+/', ' ', $raw);
    $sanitized = trim((string)$sanitized);
    if ($sanitized === '') {
        $sanitized = 'Slot ' . $fallbackIndex;
    }
    if (strlen($sanitized) > 31) {
        $sanitized = substr($sanitized, 0, 31);
    }
    return $sanitized;
}

function production_get_setting(mysqli $conn, string $key, string $fallback = ''): string
{
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) {
        return $fallback;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $value = $fallback;
    if ($result && ($row = $result->fetch_assoc())) {
        $value = (string)($row['setting_value'] ?? $fallback);
    }
    $stmt->close();
    return $value;
}

function production_upsert_setting(mysqli $conn, string $key, string $value, int $adminId): void
{
    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value, updated_by_admin_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_admin_id = VALUES(updated_by_admin_id), updated_at = NOW()');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ssi', $key, $value, $adminId);
    $stmt->execute();
    $stmt->close();
}

function production_load_slots(mysqli $conn): array
{
    $slots = [];
    $res = $conn->query('SELECT id, slot_label, slot_name, slot_type, start_time, end_time, is_active FROM order_slots WHERE is_active = 1 ORDER BY slot_type, display_order, start_time, id');
    while ($res && ($row = $res->fetch_assoc())) {
        $slots[] = $row;
    }
    return $slots;
}

function production_slot_label_from_datetime(string $dt, array $slotLabelByStart): string
{
    if ($dt === '') {
        return '';
    }
    $time = substr($dt, 11, 8);
    if (isset($slotLabelByStart[$time])) {
        return (string)$slotLabelByStart[$time];
    }
    return $dt;
}

function production_first_cutoff_today(DateTimeImmutable $now, array $cutoffMap, string $defaultCutoff): DateTimeImmutable
{
    $times = [$defaultCutoff];
    foreach ($cutoffMap as $t) {
        if (is_string($t) && preg_match('/^\d{2}:\d{2}$/', $t)) {
            $times[] = $t;
        }
    }
    sort($times);
    $today = $now->format('Y-m-d');
    return new DateTimeImmutable($today . ' ' . $times[0] . ':00', $now->getTimezone());
}

function production_redirect_with_context(string $targetDate, string $mobileSearch): void
{
    $params = ['date=' . urlencode($targetDate)];
    if ($mobileSearch !== '') {
        $params[] = 'mobile=' . urlencode($mobileSearch);
    }
    header('Location: production_plan.php?' . implode('&', $params));
    exit;
}

$tz = new DateTimeZone('Asia/Kolkata');
$now = new DateTimeImmutable('now', $tz);
$adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['admin'] ?? 0);

production_bootstrap_schema($conn);

$slots = production_load_slots($conn);
$slotLabelByStart = [];
$slotLabelById = [];
$slotStartById = [];
$slotCatalogLabelByStart = [];
foreach ($slots as $slot) {
    $slotLabelByStart[(string)$slot['start_time']] = (string)$slot['slot_label'];
    $slotLabelById[(int)$slot['id']] = (string)$slot['slot_label'];
    $slotStartById[(int)$slot['id']] = (string)$slot['start_time'];
    $slotCatalogLabelByStart[(string)$slot['start_time']] = (string)$slot['slot_label'];
}

$defaultCutoffTime = production_get_setting($conn, 'production_default_cutoff_time', '23:45');
if (!preg_match('/^\d{2}:\d{2}$/', $defaultCutoffTime)) {
    $defaultCutoffTime = '23:45';
}
$slotCutoffJson = production_get_setting($conn, 'production_slot_cutoff_map', '{}');
$slotCutoffMap = json_decode($slotCutoffJson, true);
if (!is_array($slotCutoffMap)) {
    $slotCutoffMap = [];
}

$firstCutoff = production_first_cutoff_today($now, $slotCutoffMap, $defaultCutoffTime);
$defaultTargetDate = $now >= $firstCutoff
    ? $now->modify('+2 days')->format('Y-m-d')
    : $now->modify('+1 day')->format('Y-m-d');

$requestedDate = trim((string)($_GET['date'] ?? ''));
$targetDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) ? $requestedDate : $defaultTargetDate;
$mobileSearch = trim((string)($_GET['mobile'] ?? ''));
$mobileDigits = $mobileSearch !== '' ? preg_replace('/\D/', '', $mobileSearch) : '';
$exportCsv = strtolower(trim((string)($_GET['export'] ?? ''))) === 'csv';
$exportXlsx = strtolower(trim((string)($_GET['export'] ?? ''))) === 'xlsx';
$printMode = strtolower(trim((string)($_GET['print'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\App\Core\Csrf::validateRequest()) {
        $_SESSION['production_flash'] = ['type' => 'error', 'message' => 'Invalid security token. Please refresh and try again.'];
        production_redirect_with_context((string)($_POST['target_date'] ?? $targetDate), (string)($_POST['mobile_search'] ?? $mobileSearch));
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $postDate = trim((string)($_POST['target_date'] ?? $targetDate));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)) {
        $postDate = $targetDate;
    }
    $postMobile = trim((string)($_POST['mobile_search'] ?? $mobileSearch));

    if ($action === 'save_cutoff_settings') {
        $newDefault = trim((string)($_POST['default_cutoff_time'] ?? ''));
        if (!preg_match('/^\d{2}:\d{2}$/', $newDefault)) {
            $newDefault = $defaultCutoffTime;
        }
        $postedSlotCutoff = $_POST['slot_cutoff'] ?? [];
        $cleanSlotCutoff = [];
        if (is_array($postedSlotCutoff)) {
            foreach ($postedSlotCutoff as $slotId => $timeVal) {
                $slotIdInt = (int)$slotId;
                $timeStr = trim((string)$timeVal);
                if ($slotIdInt > 0 && isset($slotLabelById[$slotIdInt]) && preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
                    $cleanSlotCutoff[(string)$slotIdInt] = $timeStr;
                }
            }
        }
        production_upsert_setting($conn, 'production_default_cutoff_time', $newDefault, $adminId);
        production_upsert_setting($conn, 'production_slot_cutoff_map', json_encode($cleanSlotCutoff, JSON_UNESCAPED_SLASHES), $adminId);
        $_SESSION['production_flash'] = ['type' => 'success', 'message' => 'Cutoff settings updated.'];
    } elseif ($action === 'toggle_exclusion') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $isExcluded = (string)($_POST['is_excluded'] ?? '0') === '1' ? 1 : 0;
        if ($orderId > 0) {
            $stmt = $conn->prepare('INSERT INTO order_production_plan (order_id, is_excluded, override_updated_by_admin_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_excluded = VALUES(is_excluded), override_updated_by_admin_id = VALUES(override_updated_by_admin_id), updated_at = NOW()');
            if ($stmt) {
                $stmt->bind_param('iii', $orderId, $isExcluded, $adminId);
                $stmt->execute();
                $stmt->close();
            }
            production_log_audit($conn, $orderId, $isExcluded === 1 ? 'exclude' : 'include', null, $adminId);
            $_SESSION['production_flash'] = ['type' => 'success', 'message' => $isExcluded ? 'Order excluded from production board.' : 'Order re-included in production board.'];
        }
    } elseif ($action === 'override_slot') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $overrideSlotId = (int)($_POST['override_slot_id'] ?? 0);
        $overrideReason = trim((string)($_POST['override_reason'] ?? ''));

        if ($orderId > 0 && isset($slotStartById[$overrideSlotId], $slotLabelById[$overrideSlotId])) {
            $overrideSlotDb = $postDate . ' ' . $slotStartById[$overrideSlotId];
            $stmt = $conn->prepare('INSERT INTO order_production_plan (order_id, is_excluded, override_slot, override_reason, override_updated_by_admin_id) VALUES (?, 0, ?, ?, ?) ON DUPLICATE KEY UPDATE override_slot = VALUES(override_slot), override_reason = VALUES(override_reason), override_updated_by_admin_id = VALUES(override_updated_by_admin_id), updated_at = NOW()');
            if ($stmt) {
                $stmt->bind_param('issi', $orderId, $overrideSlotDb, $overrideReason, $adminId);
                $stmt->execute();
                $stmt->close();
            }
            $eventNote = 'Slot: ' . $slotLabelById[$overrideSlotId] . ($overrideReason !== '' ? ' | Reason: ' . $overrideReason : '');
            production_log_audit($conn, $orderId, 'override_slot', $eventNote, $adminId);
            $_SESSION['production_flash'] = ['type' => 'success', 'message' => 'Order slot override saved.'];
        } else {
            $_SESSION['production_flash'] = ['type' => 'error', 'message' => 'Invalid slot override value.'];
        }
    } elseif ($action === 'clear_override') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId > 0) {
            $stmt = $conn->prepare('INSERT INTO order_production_plan (order_id, is_excluded, override_slot, override_reason, override_updated_by_admin_id) VALUES (?, 0, NULL, NULL, ?) ON DUPLICATE KEY UPDATE override_slot = NULL, override_reason = NULL, override_updated_by_admin_id = VALUES(override_updated_by_admin_id), updated_at = NOW()');
            if ($stmt) {
                $stmt->bind_param('ii', $orderId, $adminId);
                $stmt->execute();
                $stmt->close();
            }
            production_log_audit($conn, $orderId, 'clear_override', null, $adminId);
            $_SESSION['production_flash'] = ['type' => 'success', 'message' => 'Slot override removed.'];
        }
    }

    production_redirect_with_context($postDate, $postMobile);
}

$flash = null;
if (isset($_SESSION['production_flash']) && is_array($_SESSION['production_flash'])) {
    $flash = $_SESSION['production_flash'];
    unset($_SESSION['production_flash']);
}

$plannedOrders = [];
$excludedOrders = [];
$unscheduledOrders = [];
$totalItems = 0;

$mobileCond = '';
if ($mobileDigits !== '') {
    $safeMob = $conn->real_escape_string($mobileDigits);
    $mobileCond = " AND (o.customer_phone LIKE '%{$safeMob}%' OR o.customer_phone_e164 LIKE '%{$safeMob}%')";
}

$plannedStmt = $conn->prepare(
    'SELECT
        o.id,
        MAX(o.order_number) AS order_number,
        MAX(o.customer_name) AS customer_name,
        MAX(o.customer_phone) AS customer_phone,
        MAX(o.order_status) AS order_status,
        MAX(o.payment_status) AS payment_status,
        MAX(o.payment_method) AS payment_method,
        MAX(o.scheduled_slot) AS scheduled_slot,
        MAX(o.scheduled_slot_label) AS scheduled_slot_label,
        MAX(COALESCE(opp.override_slot, o.scheduled_slot)) AS effective_slot,
        MAX(opp.override_slot) AS override_slot,
        MAX(opp.override_reason) AS override_reason,
        MAX(COALESCE(opp.is_excluded, 0)) AS is_excluded,
        MAX(o.grand_total) AS grand_total,
        COALESCE(SUM(oi.quantity), 0) AS total_qty,
        GROUP_CONCAT(CONCAT(oi.quantity, "x ", oi.product_name_snapshot) ORDER BY oi.id ASC SEPARATOR ", ") AS items_summary,
        GROUP_CONCAT(
            CONCAT(
                oi.quantity, "x ", oi.product_name_snapshot,
                CASE WHEN NULLIF(TRIM(oi.variant_snapshot), "") IS NULL THEN "" ELSE CONCAT(" (", oi.variant_snapshot, ")") END,
                CASE WHEN NULLIF(TRIM(oi.cake_message), "") IS NULL THEN "" ELSE CONCAT(" | Msg: ", oi.cake_message) END,
                CASE WHEN NULLIF(TRIM(oi.topper_name_snapshot), "") IS NULL OR oi.topper_name_snapshot = "No Topper" THEN "" ELSE CONCAT(" | Topper: ", oi.topper_name_snapshot) END,
                CASE WHEN NULLIF(TRIM(oi.customisation_note), "") IS NULL THEN "" ELSE CONCAT(" | Note: ", oi.customisation_note) END
            )
            ORDER BY oi.id ASC SEPARATOR " || "
        ) AS item_details
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     LEFT JOIN order_production_plan opp ON opp.order_id = o.id
     WHERE o.order_status IN ("confirmed", "in_preparation")
        AND (o.payment_status IN ("paid", "confirmed") OR o.payment_status = "credit" OR o.payment_method = "credit")' . $mobileCond . '
        AND COALESCE(opp.override_slot, o.scheduled_slot) IS NOT NULL
        AND DATE(COALESCE(opp.override_slot, o.scheduled_slot)) = ?
     GROUP BY o.id
     ORDER BY MAX(COALESCE(opp.override_slot, o.scheduled_slot)) ASC, o.id ASC'
);
$plannedStmt->bind_param('s', $targetDate);
$plannedStmt->execute();
$plannedResult = $plannedStmt->get_result();
while ($plannedResult && ($row = $plannedResult->fetch_assoc())) {
    $row['effective_slot_label'] = production_slot_label_from_datetime((string)($row['effective_slot'] ?? ''), $slotLabelByStart);
    if ((int)($row['is_excluded'] ?? 0) === 1) {
        $excludedOrders[] = $row;
        continue;
    }
    $plannedOrders[] = $row;
    $totalItems += (int)($row['total_qty'] ?? 0);
}
$plannedStmt->close();

$unscheduledStmt = $conn->prepare(
    'SELECT
        o.id,
        MAX(o.order_number) AS order_number,
        MAX(o.customer_name) AS customer_name,
        MAX(o.customer_phone) AS customer_phone,
        MAX(o.order_status) AS order_status,
        MAX(o.payment_status) AS payment_status,
        MAX(o.payment_method) AS payment_method,
        MAX(o.created_at) AS created_at,
        COALESCE(SUM(oi.quantity), 0) AS total_qty,
        GROUP_CONCAT(CONCAT(oi.quantity, "x ", oi.product_name_snapshot) ORDER BY oi.id ASC SEPARATOR ", ") AS items_summary
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     LEFT JOIN order_production_plan opp ON opp.order_id = o.id
     WHERE o.order_status IN ("confirmed", "in_preparation")
        AND (o.payment_status IN ("paid", "confirmed") OR o.payment_status = "credit" OR o.payment_method = "credit")' . $mobileCond . '
        AND COALESCE(opp.override_slot, o.scheduled_slot) IS NULL
     GROUP BY o.id
     ORDER BY MAX(o.created_at) DESC
     LIMIT 200'
);
$unscheduledStmt->execute();
$unscheduledResult = $unscheduledStmt->get_result();
while ($unscheduledResult && ($row = $unscheduledResult->fetch_assoc())) {
    $unscheduledOrders[] = $row;
}
$unscheduledStmt->close();

$slotSummary = [];
$slotBoards = [];
foreach ($plannedOrders as $row) {
    $slotKey = (string)($row['effective_slot'] ?? '');
    $slotLabel = (string)($row['effective_slot_label'] ?? $slotKey);
    if (!isset($slotSummary[$slotKey])) {
        $slotSummary[$slotKey] = ['label' => $slotLabel, 'orders' => 0, 'qty' => 0, 'revenue' => 0.0];
    }
    if (!isset($slotBoards[$slotKey])) {
        $slotBoards[$slotKey] = ['label' => $slotLabel, 'rows' => []];
    }
    $slotSummary[$slotKey]['orders']++;
    $slotSummary[$slotKey]['qty'] += (int)($row['total_qty'] ?? 0);
    $slotSummary[$slotKey]['revenue'] += (float)($row['grand_total'] ?? 0);
    $slotBoards[$slotKey]['rows'][] = $row;
}
$totalRevenue = array_sum(array_column($slotSummary, 'revenue'));

$auditByOrder = [];
$auditOrderIds = [];
foreach ($plannedOrders as $row) {
    $auditOrderIds[] = (int)$row['id'];
}
foreach ($excludedOrders as $row) {
    $auditOrderIds[] = (int)$row['id'];
}
$auditOrderIds = array_values(array_unique(array_filter($auditOrderIds, static fn($v) => $v > 0)));
if ($auditOrderIds) {
    $idSql = implode(',', array_map('intval', $auditOrderIds));
    $auditSql = 'SELECT l.order_id, l.event_type, l.event_note, l.created_at, COALESCE(a.full_name, CONCAT("Admin #", l.changed_by_admin_id)) AS admin_name
                 FROM order_production_audit_logs l
                 LEFT JOIN admins a ON a.id = l.changed_by_admin_id
                 WHERE l.order_id IN (' . $idSql . ')
                 ORDER BY l.created_at DESC, l.id DESC';
    $auditRes = $conn->query($auditSql);
    while ($auditRes && ($auditRow = $auditRes->fetch_assoc())) {
        $oid = (int)($auditRow['order_id'] ?? 0);
        if (!isset($auditByOrder[$oid])) {
            $auditByOrder[$oid] = [];
        }
        if (count($auditByOrder[$oid]) >= 8) {
            continue;
        }
        $auditByOrder[$oid][] = $auditRow;
    }
}

$pendingPaymentOrders = [];
$pendingStmt = $conn->prepare(
    'SELECT
        o.id,
        MAX(o.order_number) AS order_number,
        MAX(o.customer_name) AS customer_name,
        MAX(o.customer_phone) AS customer_phone,
        MAX(o.payment_status) AS payment_status,
        MAX(o.payment_method) AS payment_method,
        MAX(COALESCE(opp.override_slot, o.scheduled_slot)) AS effective_slot,
        MAX(o.grand_total) AS grand_total,
        COALESCE(SUM(oi.quantity), 0) AS total_qty,
        GROUP_CONCAT(CONCAT(oi.quantity, "x ", oi.product_name_snapshot) ORDER BY oi.id ASC SEPARATOR ", ") AS items_summary
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     LEFT JOIN order_production_plan opp ON opp.order_id = o.id
     WHERE o.order_status NOT IN ("cancelled", "completed")
        AND o.payment_status IN ("pending", "under_review")
        AND o.payment_method != "credit"' . $mobileCond . '
        AND COALESCE(opp.is_excluded, 0) = 0
        AND DATE(COALESCE(opp.override_slot, o.scheduled_slot)) = ?
     GROUP BY o.id
     ORDER BY MAX(COALESCE(opp.override_slot, o.scheduled_slot)) ASC, o.id ASC'
);
$pendingStmt->bind_param('s', $targetDate);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
while ($pendingResult && ($row = $pendingResult->fetch_assoc())) {
    $row['effective_slot_label'] = production_slot_label_from_datetime((string)($row['effective_slot'] ?? ''), $slotLabelByStart);
    $pendingPaymentOrders[] = $row;
}
$pendingStmt->close();

if ($exportCsv) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="production-plan-' . $targetDate . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Slot DateTime', 'Slot Label', 'Order Number', 'Customer', 'Phone', 'Status', 'Payment', 'Qty', 'Items', 'Item Details']);
    foreach ($plannedOrders as $row) {
        fputcsv($out, [
            (string)($row['effective_slot'] ?? ''),
            (string)($row['effective_slot_label'] ?? ''),
            (string)($row['order_number'] ?? ''),
            (string)($row['customer_name'] ?? ''),
            (string)($row['customer_phone'] ?? ''),
            (string)($row['order_status'] ?? ''),
            (string)(($row['payment_method'] ?? '') . ' / ' . ($row['payment_status'] ?? '')),
            (string)($row['total_qty'] ?? 0),
            (string)($row['items_summary'] ?? ''),
            str_replace(' || ', ' ; ', (string)($row['item_details'] ?? '')),
        ]);
    }
    fclose($out);
    exit;
}

if ($exportXlsx) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'XLSX export unavailable: PhpSpreadsheet is not loaded.';
        exit;
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    $summary = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Summary');
    $spreadsheet->addSheet($summary, 0);
    $summary->fromArray(['Slot DateTime', 'Slot Label', 'Orders', 'Qty', 'Revenue'], null, 'A1');
    $summaryRow = 2;
    foreach ($slotSummary as $slotKey => $ss) {
        $summary->setCellValue('A' . $summaryRow, (string)$slotKey);
        $summary->setCellValue('B' . $summaryRow, (string)($ss['label'] ?? ''));
        $summary->setCellValue('C' . $summaryRow, (int)($ss['orders'] ?? 0));
        $summary->setCellValue('D' . $summaryRow, (int)($ss['qty'] ?? 0));
        $summary->setCellValue('E' . $summaryRow, (float)($ss['revenue'] ?? 0));
        $summaryRow++;
    }
    $summary->setCellValue('A' . $summaryRow, 'TOTAL');
    $summary->setCellValue('C' . $summaryRow, count($plannedOrders));
    $summary->setCellValue('D' . $summaryRow, (int)$totalItems);
    $summary->setCellValue('E' . $summaryRow, (float)$totalRevenue);
    foreach (range('A', 'E') as $col) {
        $summary->getColumnDimension($col)->setAutoSize(true);
    }
    $summary->getStyle('A1:E1')->getFont()->setBold(true);
    $summary->getStyle('A1:E1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8D8DE');
    $summary->getStyle('A' . $summaryRow . ':E' . $summaryRow)->getFont()->setBold(true);
    $summary->freezePane('A2');

    $sheetIndex = 1;
    foreach ($slotBoards as $slotKey => $slotData) {
        $sheetTitle = production_sheet_title((string)($slotData['label'] ?? $slotKey), $sheetIndex);
        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $sheetTitle);
        $spreadsheet->addSheet($sheet, $sheetIndex);
        $sheet->fromArray(
            ['Order #', 'Customer', 'Phone', 'Slot DateTime', 'Slot Label', 'Qty', 'Items', 'Item Details', 'Status', 'Payment'],
            null,
            'A1'
        );
        $r = 2;
        foreach ($slotData['rows'] as $row) {
            $sheet->setCellValue('A' . $r, (string)($row['order_number'] ?? ''));
            $sheet->setCellValue('B' . $r, (string)($row['customer_name'] ?? ''));
            $sheet->setCellValue('C' . $r, (string)($row['customer_phone'] ?? ''));
            $sheet->setCellValue('D' . $r, (string)($row['effective_slot'] ?? ''));
            $sheet->setCellValue('E' . $r, (string)($row['effective_slot_label'] ?? ''));
            $sheet->setCellValue('F' . $r, (int)($row['total_qty'] ?? 0));
            $sheet->setCellValue('G' . $r, (string)($row['items_summary'] ?? ''));
            $sheet->setCellValue('H' . $r, str_replace(' || ', "\n", (string)($row['item_details'] ?? '')));
            $sheet->setCellValue('I' . $r, (string)($row['order_status'] ?? ''));
            $sheet->setCellValue('J' . $r, (string)(($row['payment_method'] ?? '') . ' / ' . ($row['payment_status'] ?? '')));
            $r++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8D8DE');
        $sheet->freezePane('A2');
        $sheetIndex++;
    }

    $spreadsheet->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="production-plan-' . $targetDate . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if (in_array($printMode, ['kitchen', 'dispatch', 'customer'], true)) {
    $printTitles = [
        'kitchen' => 'Kitchen Prep Sheet',
        'dispatch' => 'Dispatch Sheet',
        'customer' => 'Customer Summary Sheet',
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($printTitles[$printMode], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?></title>
<style>
body { font-family: Arial, sans-serif; margin: 24px; color: #1d1115; }
h1 { margin: 0 0 8px; color: #80001f; }
h2 { margin: 18px 0 6px; color: #5a2136; font-size: 18px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
th { background: #80001f; color: #fff; text-align: left; padding: 7px; font-size: 12px; }
td { border-bottom: 1px solid #edd6dd; padding: 8px; vertical-align: top; font-size: 12px; }
.muted { color: #6a5660; }
.no-print { margin-bottom: 14px; }
@media print { .no-print { display: none !important; } }
</style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()" style="background:#80001f;color:#fff;border:0;border-radius:8px;padding:8px 14px;cursor:pointer">Print</button>
    <a href="production_plan.php?date=<?= urlencode($targetDate) ?>" style="margin-left:10px;color:#80001f">Back to Production Plan</a>
</div>
<h1><?= htmlspecialchars($printTitles[$printMode], ENT_QUOTES, 'UTF-8') ?></h1>
<div class="muted">Date: <?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?> | Generated: <?= htmlspecialchars($now->format('d M Y, h:i A'), ENT_QUOTES, 'UTF-8') ?> IST</div>
<?php if (!$slotBoards): ?>
<p>No orders found for this date.</p>
<?php else: ?>
<?php foreach ($slotBoards as $slotKey => $slotData): ?>
    <h2><?= htmlspecialchars((string)$slotKey, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$slotData['label'], ENT_QUOTES, 'UTF-8') ?></h2>
    <table>
        <thead>
            <?php if ($printMode === 'kitchen'): ?>
                <tr><th>Order</th><th>Customer</th><th>Qty</th><th>Item Notes</th><th>Done</th></tr>
            <?php elseif ($printMode === 'dispatch'): ?>
                <tr><th>Order</th><th>Customer</th><th>Phone</th><th>Payment</th><th>Status</th></tr>
            <?php else: ?>
                <tr><th>Order</th><th>Customer</th><th>Phone</th><th>Items</th><th>Special Notes</th></tr>
            <?php endif; ?>
        </thead>
        <tbody>
        <?php foreach ($slotData['rows'] as $row): ?>
            <tr>
                <?php if ($printMode === 'kitchen'): ?>
                    <td><strong><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></strong><br><span class="muted">#<?= (int)$row['id'] ?></span></td>
                    <td><?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)($row['total_qty'] ?? 0) ?></td>
                    <td><?= nl2br(htmlspecialchars(str_replace(' || ', "\n", (string)($row['item_details'] ?? '')), ENT_QUOTES, 'UTF-8')) ?></td>
                    <td style="font-size:16px;text-align:center">[]</td>
                <?php elseif ($printMode === 'dispatch'): ?>
                    <td><strong><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></strong><br><span class="muted">#<?= (int)$row['id'] ?></span></td>
                    <td><?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['payment_method'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') ?></td>
                <?php else: ?>
                    <td><strong><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></strong><br><span class="muted">#<?= (int)$row['id'] ?></span></td>
                    <td><?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['items_summary'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= nl2br(htmlspecialchars(str_replace(' || ', "\n", (string)($row['item_details'] ?? '')), ENT_QUOTES, 'UTF-8')) ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
<?php
    exit;
}

// Single-order print mode preserved for per-order production sheet
$singleOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($singleOrderId > 0) {
    $oStmt = $conn->prepare('SELECT o.*, GROUP_CONCAT(oi.id) AS item_ids FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.id = ? GROUP BY o.id LIMIT 1');
    $oStmt->bind_param('i', $singleOrderId);
    $oStmt->execute();
    $singleOrder = ($oStmt->get_result())->fetch_assoc();
    $oStmt->close();

    $singleItems = [];
    if ($singleOrder) {
        $iStmt = $conn->prepare('SELECT product_name_snapshot, variant_snapshot, quantity, unit_price, line_total, cake_message, topper_name_snapshot, topper_price_snapshot, customisation_note FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $iStmt->bind_param('i', $singleOrderId);
        $iStmt->execute();
        $iRes = $iStmt->get_result();
        while ($iRes && ($iRow = $iRes->fetch_assoc())) {
            $singleItems[] = $iRow;
        }
        $iStmt->close();
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Production Plan - Order #<?= (int)$singleOrderId ?></title>
<style>
  body { font-family: Arial, sans-serif; margin: 32px; color: #1d1115; }
  h1 { color: #80001f; font-size: 22px; margin-bottom: 4px; }
  .meta { font-size: 13px; color: #5a3044; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  th { background: #80001f; color: #fff; padding: 8px; text-align: left; font-size: 13px; }
  td { padding: 8px; border-bottom: 1px solid #f0d7df; font-size: 13px; vertical-align: top; }
  .signature { border-top: 1px dashed #ccc; margin-top: 30px; padding-top: 16px; display: flex; justify-content: space-between; font-size: 12px; color: #888; }
  @media print { button, .no-print { display: none !important; } }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:16px;">
  <button onclick="window.print()" style="background:#80001f;color:#fff;border:none;border-radius:8px;padding:8px 18px;cursor:pointer;font-size:14px;">Print</button>
  <a href="order_details.php?id=<?= (int)$singleOrderId ?>" style="font-size:13px;color:#80001f;margin-left:10px;">Back to Order</a>
</div>
<h1>Production Sheet - #<?= htmlspecialchars((string)($singleOrder['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
<div class="meta">
  Customer: <strong><?= htmlspecialchars((string)($singleOrder['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
  | Phone: <strong><?= htmlspecialchars((string)($singleOrder['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
  | Slot: <strong><?= htmlspecialchars((string)($singleOrder['scheduled_slot_label'] ?? $singleOrder['scheduled_slot'] ?? 'Not scheduled'), ENT_QUOTES, 'UTF-8') ?></strong>
  | Printed: <?= htmlspecialchars($now->format('d M Y, h:i A'), ENT_QUOTES, 'UTF-8') ?> IST
</div>
<?php if (empty($singleItems)): ?>
  <p>No items found for this order.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Item / Variant</th>
      <th>Qty</th>
      <th>Topper</th>
      <th>Cake Message</th>
      <th>Special Note</th>
      <th>Done</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($singleItems as $i => $itm): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td>
        <strong><?= htmlspecialchars((string)($itm['product_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
        <?php if (!empty($itm['variant_snapshot'])): ?>
          <br><small><?= htmlspecialchars((string)$itm['variant_snapshot'], ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
      </td>
      <td><?= (int)$itm['quantity'] ?></td>
      <td><?= !empty($itm['topper_name_snapshot']) && $itm['topper_name_snapshot'] !== 'No Topper' ? htmlspecialchars((string)$itm['topper_name_snapshot'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
      <td><?= !empty($itm['cake_message']) ? htmlspecialchars((string)$itm['cake_message'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
      <td><?= !empty($itm['customisation_note']) ? htmlspecialchars((string)$itm['customisation_note'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
      <td style="text-align:center;font-size:18px;">[]</td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<div class="signature">
  <span>Baker: ___________________________</span>
  <span>Quality Check: ___________________________</span>
  <span>Dispatch: ___________________________</span>
</div>
</body>
</html>
<?php
    exit;
}

$firstSlot = count($plannedOrders) > 0 ? (string)$plannedOrders[0]['effective_slot'] : 'NA';
$lastSlot = count($plannedOrders) > 0 ? (string)$plannedOrders[count($plannedOrders) - 1]['effective_slot'] : 'NA';
$csrfToken = \App\Core\Csrf::token();

$pageTitle = 'Production Plan';
require_once __DIR__ . '/layout.php';
?>
<style>
.production-wrap { display:grid; gap:18px; }
.production-card { background:#fffdfd; border:1px solid rgba(128,0,31,.1); border-radius:16px; box-shadow:0 10px 24px rgba(96,18,45,.08); }
.production-card__head { padding:18px 20px 10px; border-bottom:1px solid rgba(128,0,31,.08); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); }
.production-card__head h2, .production-card__head h3 { margin:0; color:#80001f; font-family:'DM Serif Display', Georgia, serif; font-weight:400; }
.production-card__body { padding:16px 20px 20px; }
.production-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:end; }
.production-toolbar label { font-size:.85rem; color:#6e2a3e; display:grid; gap:6px; }
.production-toolbar input[type="date"], .production-toolbar input[type="time"], .production-toolbar input[type="tel"] { padding:8px 10px; border:1px solid rgba(128,0,31,.2); border-radius:10px; }
.production-btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:0 12px; border-radius:10px; border:0; cursor:pointer; text-decoration:none; font-weight:600; }
.production-btn--primary { background:#80001f; color:#fff; }
.production-btn--ghost { background:#f8d8de; color:#80001f; }
.production-btn--warn { background:#f59e0b; color:#fff; }
.production-btn--danger { background:#dc2626; color:#fff; }
.production-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
.production-kpi { border:1px solid rgba(128,0,31,.09); background:#fff8fa; border-radius:12px; padding:12px; }
.production-kpi strong { display:block; color:#80001f; margin-bottom:4px; }
.production-kpi span { color:#6e2a3e; }
.production-table { width:100%; border-collapse:collapse; }
.production-table th, .production-table td { border-bottom:1px solid rgba(128,0,31,.08); padding:10px 8px; text-align:left; vertical-align:top; font-size:.9rem; }
.production-table th { color:#80001f; background:#fff8fa; }
.production-chip { display:inline-block; padding:2px 8px; border-radius:999px; background:#f8d8de; color:#80001f; font-size:.76rem; margin-bottom:4px; }
.production-muted { color:#7b6170; font-size:.8rem; }
.production-slot-group { border:1px solid rgba(128,0,31,.11); border-radius:12px; margin-bottom:14px; overflow:hidden; }
.production-slot-head { background:#fff3f6; padding:10px 12px; font-weight:700; color:#6c1f38; display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.production-item-lines { margin:6px 0 0; padding-left:16px; color:#523643; font-size:.8rem; }
.production-alert { padding:10px 12px; border-radius:10px; margin-bottom:10px; font-size:.85rem; }
.production-alert--ok { background:#ecfdf3; border:1px solid #86efac; color:#166534; }
.production-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
.production-inline-form { display:grid; gap:6px; margin-top:6px; }
.production-inline-form input, .production-inline-form select { padding:6px 8px; border:1px solid rgba(128,0,31,.24); border-radius:8px; font-size:.78rem; }
.production-inline-form small { color:#7b6170; font-size:.75rem; }
.production-audit-panel { margin-top:10px; border:1px dashed rgba(128,0,31,.3); border-radius:8px; background:#fff9fb; padding:8px; }
.production-audit-title { color:#6c1f38; font-size:.75rem; font-weight:700; margin-bottom:6px; }
.production-audit-list { margin:0; padding-left:16px; }
.production-audit-list li { color:#5a3b47; font-size:.75rem; margin-bottom:4px; }
@media (max-width:1100px) {
    .production-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
</style>

<div class="production-wrap">
    <?php if ($flash): ?>
        <div class="production-alert <?= (($flash['type'] ?? '') === 'success') ? 'production-alert--ok' : 'production-alert--error' ?>">
            <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="production-card">
        <div class="production-card__head">
            <h2>Production Planning Control Tower</h2>
        </div>
        <div class="production-card__body">
            <form method="get" class="production-toolbar">
                <label>
                    Target Date
                    <input type="date" name="date" value="<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    Mobile Search
                    <input type="tel" name="mobile" value="<?= htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="98765 43210">
                </label>
                <button type="submit" class="production-btn production-btn--primary">Apply</button>
                <a class="production-btn production-btn--ghost" href="production_plan.php?date=<?= urlencode($targetDate) ?>&mobile=<?= urlencode($mobileSearch) ?>&export=csv">CSV</a>
                <a class="production-btn production-btn--ghost" href="production_plan.php?date=<?= urlencode($targetDate) ?>&mobile=<?= urlencode($mobileSearch) ?>&export=xlsx">XLSX</a>
                <a class="production-btn production-btn--ghost" target="_blank" href="production_plan.php?date=<?= urlencode($targetDate) ?>&mobile=<?= urlencode($mobileSearch) ?>&print=kitchen">Print Kitchen</a>
                <a class="production-btn production-btn--ghost" target="_blank" href="production_plan.php?date=<?= urlencode($targetDate) ?>&mobile=<?= urlencode($mobileSearch) ?>&print=dispatch">Print Dispatch</a>
                <a class="production-btn production-btn--ghost" target="_blank" href="production_plan.php?date=<?= urlencode($targetDate) ?>&mobile=<?= urlencode($mobileSearch) ?>&print=customer">Print Customer</a>
            </form>
            <p class="production-muted" style="margin-top:8px;">Default date now follows editable cutoff settings. Current IST: <?= htmlspecialchars($now->format('d M Y, h:i A'), ENT_QUOTES, 'UTF-8') ?>.</p>
        </div>
    </section>

    <section class="production-card">
        <div class="production-card__head">
            <h3>Cutoff Time Settings</h3>
        </div>
        <div class="production-card__body">
            <form method="post" class="production-toolbar" style="align-items:start;">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save_cutoff_settings">
                <input type="hidden" name="target_date" value="<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="mobile_search" value="<?= htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8') ?>">
                <label>
                    Default Cutoff
                    <input type="time" name="default_cutoff_time" value="<?= htmlspecialchars($defaultCutoffTime, ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <?php foreach ($slots as $slot): ?>
                    <?php
                        $slotId = (int)$slot['id'];
                        $slotCutoff = (string)($slotCutoffMap[(string)$slotId] ?? $defaultCutoffTime);
                    ?>
                    <label>
                        <?= htmlspecialchars((string)$slot['slot_label'], ENT_QUOTES, 'UTF-8') ?> cutoff
                        <input type="time" name="slot_cutoff[<?= $slotId ?>]" value="<?= htmlspecialchars($slotCutoff, ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                <?php endforeach; ?>
                <button class="production-btn production-btn--primary" type="submit">Save Cutoffs</button>
            </form>
        </div>
    </section>

    <section class="production-card">
        <div class="production-card__head">
            <h3>Planning Snapshot (<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>)</h3>
        </div>
        <div class="production-card__body">
            <div class="production-kpis">
                <div class="production-kpi"><strong>Included Orders</strong><span><?= count($plannedOrders) ?></span></div>
                <div class="production-kpi"><strong>Excluded Orders</strong><span><?= count($excludedOrders) ?></span></div>
                <div class="production-kpi"><strong>Total Cakes/Qty</strong><span><?= (int)$totalItems ?></span></div>
                <div class="production-kpi"><strong>First Slot</strong><span><?= htmlspecialchars($firstSlot, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="production-kpi"><strong>Last Slot</strong><span><?= htmlspecialchars($lastSlot, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="production-kpi"><strong>Confirmed Revenue</strong><span>INR <?= number_format($totalRevenue, 0) ?></span></div>
                <div class="production-kpi"><strong>Slots Active</strong><span><?= count($slotSummary) ?></span></div>
                <div class="production-kpi"><strong>Needs Scheduling</strong><span><?= count($unscheduledOrders) ?></span></div>
            </div>
        </div>
    </section>

    <section class="production-card">
        <div class="production-card__head">
            <h3>Slot-wise Preparation Board</h3>
        </div>
        <div class="production-card__body">
            <?php if (!$slotBoards): ?>
                <p>No included production orders found for this date.</p>
            <?php else: ?>
                <?php foreach ($slotBoards as $slotKey => $slotData): ?>
                    <div class="production-slot-group">
                        <div class="production-slot-head">
                            <div>
                                <?= htmlspecialchars((string)$slotKey, ENT_QUOTES, 'UTF-8') ?>
                                <span class="production-muted">(<?= htmlspecialchars((string)$slotData['label'], ENT_QUOTES, 'UTF-8') ?>)</span>
                            </div>
                            <div><?= count($slotData['rows']) ?> order(s)</div>
                        </div>
                        <div style="padding:10px;overflow:auto;">
                            <table class="production-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Items + Notes</th>
                                        <th>Status</th>
                                        <th style="min-width:280px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($slotData['rows'] as $row): ?>
                                        <?php $itemLines = array_values(array_filter(array_map('trim', explode(' || ', (string)($row['item_details'] ?? ''))))); ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                                <span class="production-muted">#<?= (int)$row['id'] ?></span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?><br>
                                                <span class="production-muted"><?= htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td>
                                                <span class="production-chip">Qty: <?= (int)($row['total_qty'] ?? 0) ?></span>
                                                <?php if ($itemLines): ?>
                                                    <ul class="production-item-lines">
                                                        <?php foreach ($itemLines as $line): ?>
                                                            <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <div class="production-muted">No item detail.</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="production-chip"><?= htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') ?></span><br>
                                                <span class="production-muted"><?= htmlspecialchars((string)$row['payment_method'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td>
                                                <form method="post" style="display:inline-block;margin-right:6px;" onsubmit="return confirm('Exclude this order from production board?');">
                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="toggle_exclusion">
                                                    <input type="hidden" name="order_id" value="<?= (int)$row['id'] ?>">
                                                    <input type="hidden" name="is_excluded" value="1">
                                                    <input type="hidden" name="target_date" value="<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="mobile_search" value="<?= htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="production-btn production-btn--danger">Exclude</button>
                                                </form>
                                                <a href="production_plan.php?order_id=<?= (int)$row['id'] ?>" target="_blank" class="production-btn production-btn--ghost">Print Order</a>

                                                <form method="post" class="production-inline-form">
                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="override_slot">
                                                    <input type="hidden" name="order_id" value="<?= (int)$row['id'] ?>">
                                                    <input type="hidden" name="target_date" value="<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="mobile_search" value="<?= htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php $selectedOverrideSlotId = 0; ?>
                                                    <?php if (!empty($row['override_slot'])): ?>
                                                        <?php
                                                            $overrideTime = substr((string)$row['override_slot'], 11, 8);
                                                            foreach ($slotStartById as $sid => $stime) {
                                                                if ($stime === $overrideTime) {
                                                                    $selectedOverrideSlotId = (int)$sid;
                                                                    break;
                                                                }
                                                            }
                                                        ?>
                                                    <?php endif; ?>
                                                    <select name="override_slot_id" required>
                                                        <option value="">Select active slot</option>
                                                        <?php foreach ($slots as $slot): ?>
                                                            <?php $sid = (int)$slot['id']; ?>
                                                            <option value="<?= $sid ?>" <?= $selectedOverrideSlotId === $sid ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars((string)$slot['slot_label'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$slot['start_time'], ENT_QUOTES, 'UTF-8') ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="text" name="override_reason" value="<?= htmlspecialchars((string)($row['override_reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Reason for slot change">
                                                    <button type="submit" class="production-btn production-btn--warn">Save Slot Change</button>
                                                </form>

                                                <?php if (!empty($row['override_slot'])): ?>
                                                    <form method="post" style="margin-top:6px;display:inline-block;">
                                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="clear_override">
                                                        <input type="hidden" name="order_id" value="<?= (int)$row['id'] ?>">
                                                        <input type="hidden" name="target_date" value="<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="mobile_search" value="<?= htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="production-btn production-btn--ghost">Clear Override</button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php $timeline = $auditByOrder[(int)$row['id']] ?? []; ?>
                                                <div class="production-audit-panel">
                                                    <div class="production-audit-title">Audit Timeline</div>
                                                    <?php if (!$timeline): ?>
                                                        <div class="production-muted">No actions logged yet.</div>
                                                    <?php else: ?>
                                                        <ul class="production-audit-list">
                                                            <?php foreach ($timeline as $entry): ?>
                                                                <li>
                                                                    <strong><?= htmlspecialchars((string)($entry['event_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                                                    by <?= htmlspecialchars((string)($entry['admin_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?>
                                                                    at <?= htmlspecialchars((string)($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                                    <?php if (!empty($entry['event_note'])): ?>
                                                                        <br><span class="production-muted"><?= htmlspecialchars((string)$entry['event_note'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($excludedOrders): ?>
    <section class="production-card">
        <div class="production-card__head">
            <h3>Excluded Orders (<?= count($excludedOrders) ?>)</h3>
        </div>
        <div class="production-card__body">
            <table class="production-table">
                <thead>
                    <tr><th>Order</th><th>Customer</th><th>Slot</th><th>Items</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($excludedOrders as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></strong><br><span class="production-muted">#<?= (int)$row['id'] ?></span></td>
                            <td><?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?><br><span class="production-muted"><?= htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string)($row['effective_slot'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br><span class="production-muted"><?= htmlspecialchars((string)($row['effective_slot_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string)($row['items_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="toggle_exclusion">
                                    <input type="hidden" name="order_id" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="is_excluded" value="0">
                                    <input type="hidden" name="target_date" value="<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="mobile_search" value="<?= htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="production-btn production-btn--primary">Re-Include</button>
                                </form>
                                <?php $timeline = $auditByOrder[(int)$row['id']] ?? []; ?>
                                <div class="production-audit-panel">
                                    <div class="production-audit-title">Audit Timeline</div>
                                    <?php if (!$timeline): ?>
                                        <div class="production-muted">No actions logged yet.</div>
                                    <?php else: ?>
                                        <ul class="production-audit-list">
                                            <?php foreach ($timeline as $entry): ?>
                                                <li>
                                                    <strong><?= htmlspecialchars((string)($entry['event_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                                    by <?= htmlspecialchars((string)($entry['admin_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?>
                                                    at <?= htmlspecialchars((string)($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    <?php if (!empty($entry['event_note'])): ?>
                                                        <br><span class="production-muted"><?= htmlspecialchars((string)$entry['event_note'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($pendingPaymentOrders): ?>
    <section class="production-card" style="border-color:#f59e0b">
        <div class="production-card__head" style="background:linear-gradient(180deg,#fffbeb,#fff)">
            <h3 style="color:#b45309">Pending Payment Scheduled (<?= count($pendingPaymentOrders) ?>)</h3>
        </div>
        <div class="production-card__body">
            <table class="production-table">
                <thead>
                    <tr><th>Order</th><th>Customer</th><th>Slot</th><th>Items</th><th>Payment</th><th>Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingPaymentOrders as $row): ?>
                        <tr>
                            <td><a href="order_details.php?id=<?= (int)$row['id'] ?>" style="color:#b45309;font-weight:700"><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></a></td>
                            <td><?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?><br><span class="production-muted"><?= htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string)($row['effective_slot'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br><span class="production-muted"><?= htmlspecialchars((string)($row['effective_slot_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string)($row['items_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['payment_method'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong>INR <?= number_format((float)($row['grand_total'] ?? 0), 0) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="production-card">
        <div class="production-card__head">
            <h3>Needs Scheduling</h3>
        </div>
        <div class="production-card__body">
            <table class="production-table">
                <thead>
                    <tr><th>Order</th><th>Customer</th><th>Items</th><th>Status</th><th>Created</th></tr>
                </thead>
                <tbody>
                    <?php if (!$unscheduledOrders): ?>
                        <tr><td colspan="5">No unscheduled production orders pending.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($unscheduledOrders as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?><br><span class="production-muted"><?= htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="production-chip">Qty: <?= (int)($row['total_qty'] ?? 0) ?></span><br><span class="production-muted"><?= htmlspecialchars((string)($row['items_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="production-chip"><?= htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
