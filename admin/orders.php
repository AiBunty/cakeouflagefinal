<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/includes/db.php';

function orders_stmt_bind(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || !$params) {
        return true;
    }

    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }

    return $stmt->bind_param($types, ...$refs);
}

function orders_query_string(array $state, array $overrides = []): string
{
    $merged = array_merge($state, $overrides);
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null) {
            unset($merged[$k]);
        }
    }
    return http_build_query($merged);
}

$orderPerPageOptions = [20, 50, 100];
$orderPerPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($orderPerPage, $orderPerPageOptions, true)) {
    $orderPerPage = 20;
}

$allowedOrderStatuses = ['pending_payment', 'payment_under_review', 'awaiting_confirmation', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'refund_requested', 'refunded', 'partially_refunded', 'fully_refunded', 'rejected'];
$allowedPaymentStatuses = ['pending', 'under_review', 'paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded', 'failed', 'rejected'];
$allowedPaymentMethods = ['upi_manual', 'cod', 'gateway', 'credit'];
$allowedFulfilmentModes = ['delivery', 'pickup', 'custom_delivery'];
$allowedSourceChannels = ['online', 'manual'];
 $allowedOrderSegments = ['operational', 'historical', 'all'];

$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$orderStatus = trim((string)($_GET['order_status'] ?? ''));
$paymentStatus = trim((string)($_GET['payment_status'] ?? ''));
$paymentMethod = trim((string)($_GET['payment_method'] ?? ''));
$fulfilmentMode = trim((string)($_GET['fulfilment_mode'] ?? ''));
$sourceChannel = trim((string)($_GET['source_channel'] ?? ''));
$orderSegment = trim((string)($_GET['order_segment'] ?? 'operational'));
$orderSource  = trim((string)($_GET['order_source'] ?? ''));
$allowedOrderSources = ['retail', 'byoc_quote', 'manual'];
if (!in_array($orderSource, $allowedOrderSources, true)) {
    $orderSource = '';
}
$ordersViewMode = trim((string)($_GET['orders_view'] ?? 'compact'));
if (!in_array($ordersViewMode, ['compact', 'expanded'], true)) {
  $ordersViewMode = 'compact';
}
$isCompactView = $ordersViewMode === 'compact';
$couponMode = trim((string)($_GET['coupon_mode'] ?? ''));
$couponCode = trim((string)($_GET['coupon_code'] ?? ''));
$mobileSearch = trim((string)($_GET['mobile'] ?? ''));
$amountMinRaw = trim((string)($_GET['amount_min'] ?? ''));
$amountMaxRaw = trim((string)($_GET['amount_max'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}
if (!in_array($orderStatus, $allowedOrderStatuses, true)) {
    $orderStatus = '';
}
if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
    $paymentStatus = '';
}
if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    $paymentMethod = '';
}
if (!in_array($fulfilmentMode, $allowedFulfilmentModes, true)) {
    $fulfilmentMode = '';
}
if (!in_array($sourceChannel, $allowedSourceChannels, true)) {
    $sourceChannel = '';
}
if (!in_array($orderSegment, $allowedOrderSegments, true)) {
  $orderSegment = 'operational';
}
if (!in_array($couponMode, ['', 'yes', 'no'], true)) {
    $couponMode = '';
}

$amountMin = is_numeric($amountMinRaw) ? (float)$amountMinRaw : null;
$amountMax = is_numeric($amountMaxRaw) ? (float)$amountMaxRaw : null;

$conditions = ['1=1'];
$types = '';
$params = [];

// All order sources (retail, byoc_quote, manual) flow into orders as master of truth.
// Filter by order_source only when explicitly requested.
if ($orderSource !== '') {
    if ($orderSource === 'retail') {
        $conditions[] = '(o.order_source IS NULL OR o.order_source = "retail")';
    } else {
        $conditions[] = 'o.order_source = ?';
        $types .= 's';
        $params[] = $orderSource;
    }
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $conditions[] = '(
        o.order_number LIKE ?
        OR o.customer_name LIKE ?
        OR o.customer_email LIKE ?
        OR o.customer_phone LIKE ?
        OR CAST(o.grand_total AS CHAR) LIKE ?
        OR o.order_status LIKE ?
        OR o.payment_status LIKE ?
        OR o.payment_method LIKE ?
        OR EXISTS (
            SELECT 1
            FROM order_items oi
            WHERE oi.order_id = o.id AND oi.product_name_snapshot LIKE ?
        )
    )';
    $types .= str_repeat('s', 9);
    for ($i = 0; $i < 9; $i++) {
        $params[] = $like;
    }
}

if ($dateFrom !== '') {
    $conditions[] = 'DATE(o.created_at) >= ?';
    $types .= 's';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $conditions[] = 'DATE(o.created_at) <= ?';
    $types .= 's';
    $params[] = $dateTo;
}
if ($orderStatus !== '') {
    $conditions[] = 'o.order_status = ?';
    $types .= 's';
    $params[] = $orderStatus;
}
if ($paymentStatus !== '') {
    $conditions[] = 'o.payment_status = ?';
    $types .= 's';
    $params[] = $paymentStatus;
}
if ($paymentMethod !== '') {
    $conditions[] = 'o.payment_method = ?';
    $types .= 's';
    $params[] = $paymentMethod;
}
if ($fulfilmentMode !== '') {
    $conditions[] = 'o.fulfilment_mode = ?';
    $types .= 's';
    $params[] = $fulfilmentMode;
}
if ($sourceChannel === 'online') {
    $conditions[] = 'o.user_id IS NOT NULL';
}
if ($sourceChannel === 'manual') {
    $conditions[] = 'o.user_id IS NULL';
}

$hasArchivedColumn = false;
$archivedColumnRes = $conn->query("SHOW COLUMNS FROM orders LIKE 'is_archived'");
if ($archivedColumnRes && $archivedColumnRes->fetch_assoc()) {
    $hasArchivedColumn = true;
}

$operationalStatuses = ['pending_payment', 'payment_under_review', 'awaiting_confirmation', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'];
$historicalStatuses = ['delivered', 'completed', 'cancelled', 'refunded', 'partially_refunded', 'fully_refunded', 'rejected'];
if ($orderSegment === 'operational') {
  $conditions[] = 'o.order_status IN ("pending_payment", "payment_under_review", "awaiting_confirmation", "confirmed", "preparing", "ready_for_pickup", "out_for_delivery")';
  if ($hasArchivedColumn) {
    $conditions[] = 'COALESCE(o.is_archived, 0) = 0';
  }
} elseif ($orderSegment === 'historical') {
  if ($hasArchivedColumn) {
    $conditions[] = '(o.order_status IN ("delivered", "completed", "cancelled", "refunded", "partially_refunded", "fully_refunded", "rejected") OR COALESCE(o.is_archived, 0) = 1)';
  } else {
    $conditions[] = 'o.order_status IN ("delivered", "completed", "cancelled", "refunded", "partially_refunded", "fully_refunded", "rejected")';
  }
}
if ($amountMin !== null) {
    $conditions[] = 'o.grand_total >= ?';
    $types .= 'd';
    $params[] = $amountMin;
}
if ($amountMax !== null) {
    $conditions[] = 'o.grand_total <= ?';
    $types .= 'd';
    $params[] = $amountMax;
}
if ($couponMode === 'yes') {
    $conditions[] = 'EXISTS (SELECT 1 FROM coupon_redemptions cr WHERE cr.order_id = o.id)';
}
if ($couponMode === 'no') {
    $conditions[] = 'NOT EXISTS (SELECT 1 FROM coupon_redemptions cr WHERE cr.order_id = o.id)';
}
if ($couponCode !== '') {
    $couponLike = '%' . $couponCode . '%';
    $conditions[] = 'EXISTS (
        SELECT 1
        FROM coupon_redemptions cr2
        LEFT JOIN coupons c2 ON c2.id = cr2.coupon_id
        WHERE cr2.order_id = o.id
          AND (cr2.code_snapshot LIKE ? OR c2.code LIKE ?)
    )';
    $types .= 'ss';
    $params[] = $couponLike;
    $params[] = $couponLike;
}
if ($mobileSearch !== '') {
    $mobileDigits = preg_replace('/\D/', '', $mobileSearch);
    if ($mobileDigits !== '') {
        $mobilePattern = '%' . $mobileDigits . '%';
        $conditions[] = '(o.customer_phone LIKE ? OR o.customer_phone_e164 LIKE ?)';
        $types .= 'ss';
        $params[] = $mobilePattern;
        $params[] = $mobilePattern;
    }
}

$whereSql = implode(' AND ', $conditions);

$priorityOrderSql = 'CASE
  WHEN o.order_status = "pending_payment" THEN 10
  WHEN o.order_status = "awaiting_confirmation" THEN 20
  WHEN o.order_status = "confirmed" THEN 30
  WHEN o.order_status = "preparing" THEN 40
  WHEN o.order_status = "ready_for_pickup" THEN 45
  WHEN o.order_status = "out_for_delivery" AND DATE(COALESCE(o.scheduled_slot, o.created_at)) = CURDATE() THEN 50
  WHEN o.order_status = "out_for_delivery" THEN 60
  WHEN o.order_status = "payment_under_review" THEN 70
  WHEN o.order_status = "delivered" THEN 110
  WHEN o.order_status = "completed" THEN 120
  WHEN o.order_status = "cancelled" THEN 130
  WHEN o.order_status IN ("refunded", "partially_refunded", "fully_refunded") THEN 140
  WHEN o.order_status = "rejected" THEN 150
  ELSE 999
END';

$countSql = 'SELECT COUNT(*) AS total_rows FROM orders o WHERE ' . $whereSql;
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    $countBind = $params;
    orders_stmt_bind($countStmt, $types, $countBind);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    $ordersTotalRows = (int)($countRow['total_rows'] ?? 0);
    $countStmt->close();
} else {
    $ordersTotalRows = 0;
}

$ordersPage = max(1, (int)($_GET['page'] ?? 1));
$ordersTotalPages = max(1, (int)ceil($ordersTotalRows / $orderPerPage));
if ($ordersPage > $ordersTotalPages) {
    $ordersPage = $ordersTotalPages;
}
$ordersOffset = ($ordersPage - 1) * $orderPerPage;

$listSql = 'SELECT o.* FROM orders o WHERE ' . $whereSql . ' ORDER BY ' . $priorityOrderSql . ' ASC, o.created_at DESC, o.id DESC LIMIT ? OFFSET ?';
$listStmt = $conn->prepare($listSql);
$orders = [];
if ($listStmt) {
    $listTypes = $types . 'ii';
    $listParams = $params;
    $listParams[] = $orderPerPage;
    $listParams[] = $ordersOffset;
    orders_stmt_bind($listStmt, $listTypes, $listParams);
    $listStmt->execute();
    $result = $listStmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $orders[] = $row;
    }
    $listStmt->close();
}

$filtersState = [
    'q' => $q,
    'mobile' => $mobileSearch,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'order_status' => $orderStatus,
    'payment_status' => $paymentStatus,
    'payment_method' => $paymentMethod,
    'fulfilment_mode' => $fulfilmentMode,
    'source_channel' => $sourceChannel,
    'order_segment' => $orderSegment,
    'order_source' => $orderSource,
    'amount_min' => $amountMinRaw,
    'amount_max' => $amountMaxRaw,
    'coupon_mode' => $couponMode,
    'coupon_code' => $couponCode,
    'orders_view' => $ordersViewMode,
    'per_page' => (string)$orderPerPage,
    'page' => (string)$ordersPage,
];

  $activeFilters = [];
  if ($q !== '') {
    $activeFilters[] = ['key' => 'q', 'label' => 'Search', 'value' => $q];
  }
  if ($mobileSearch !== '') {
    $activeFilters[] = ['key' => 'mobile', 'label' => 'Mobile', 'value' => $mobileSearch];
  }
  if ($dateFrom !== '' || $dateTo !== '') {
    $activeFilters[] = ['key' => 'date_range', 'label' => 'Date', 'value' => ($dateFrom !== '' ? $dateFrom : '...') . ' to ' . ($dateTo !== '' ? $dateTo : '...')];
  }
  if ($orderStatus !== '') {
    $activeFilters[] = ['key' => 'order_status', 'label' => 'Order Status', 'value' => $orderStatus];
  }
  if ($paymentStatus !== '') {
    $activeFilters[] = ['key' => 'payment_status', 'label' => 'Payment Status', 'value' => $paymentStatus];
  }
  if ($paymentMethod !== '') {
    $activeFilters[] = ['key' => 'payment_method', 'label' => 'Payment Method', 'value' => $paymentMethod];
  }
  if ($fulfilmentMode !== '') {
    $activeFilters[] = ['key' => 'fulfilment_mode', 'label' => 'Fulfilment', 'value' => $fulfilmentMode];
  }
  if ($sourceChannel !== '') {
    $activeFilters[] = ['key' => 'source_channel', 'label' => 'Channel', 'value' => $sourceChannel];
  }
  if ($orderSegment !== 'operational') {
    $activeFilters[] = ['key' => 'order_segment', 'label' => 'Order Segment', 'value' => $orderSegment];
  }
  if ($orderSource !== '') {
    $sourceLabelMap = ['retail' => 'Online/Retail', 'byoc_quote' => 'BYOC', 'manual' => 'Manual'];
    $activeFilters[] = ['key' => 'order_source', 'label' => 'Order Source', 'value' => $sourceLabelMap[$orderSource] ?? $orderSource];
  }
  if ($amountMinRaw !== '' || $amountMaxRaw !== '') {
    $activeFilters[] = ['key' => 'amount_range', 'label' => 'Amount', 'value' => ($amountMinRaw !== '' ? $amountMinRaw : '0') . ' to ' . ($amountMaxRaw !== '' ? $amountMaxRaw : 'max')];
  }
  if ($couponMode !== '') {
    $activeFilters[] = ['key' => 'coupon_mode', 'label' => 'Coupon Usage', 'value' => $couponMode];
  }
  if ($couponCode !== '') {
    $activeFilters[] = ['key' => 'coupon_code', 'label' => 'Coupon Code', 'value' => $couponCode];
  }

  $todayDate = date('Y-m-d');
  $last7Date = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');
  $last30Date = (new DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');

$actionOrderId = (int)($_GET['action_order_id'] ?? 0);
$actionLevel = trim((string)($_GET['action_level'] ?? 'success'));
if (!in_array($actionLevel, ['success', 'error', 'info'], true)) {
    $actionLevel = 'success';
}
$actionMessage = trim((string)($_GET['action_message'] ?? ''));
$actionMessage = $actionMessage !== '' ? htmlspecialchars($actionMessage, ENT_QUOTES, 'UTF-8') : '';

$canOrderEdit = admin_has_permission('order_edit');
$canOrderReject = admin_has_permission('order_reject');
$canOrderCredit = admin_has_permission('order_credit');
$canOrderRefund = admin_has_permission('order_refund') || admin_has_permission('can_approve_refund') || admin_has_permission('can_force_refund');
$canOrderDelete = admin_has_permission('order_delete');
$isSuperAdmin = admin_is_super_admin();
$canCancelUnpaid = admin_has_permission('can_cancel_unpaid_orders') || admin_has_permission('order_reject') || admin_has_permission('order_refund');
$stateManager = new \App\Services\OrderStateManager();
$currentUri = htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? 'orders.php'), ENT_QUOTES, 'UTF-8');

$timelineByOrder = [];
$refundSummaryByOrder = [];
$orderItemsByOrder = [];
$financeSnapshotByOrder = [];
$retailActsAsManual = false;

$orderSourceColumnRes = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_source'");
if ($orderSourceColumnRes && ($orderSourceColumn = $orderSourceColumnRes->fetch_assoc())) {
  $orderSourceType = strtolower((string)($orderSourceColumn['Type'] ?? ''));
  $retailActsAsManual = $orderSourceType !== '' && strpos($orderSourceType, "'manual'") === false;
}

$orderIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $orders), static fn(int $id): bool => $id > 0));
if (!empty($orderIds)) {
  $in = implode(',', array_map('intval', $orderIds));

  $timelineSql = 'SELECT order_id, previous_status, new_status, reason, created_at FROM order_status_history WHERE order_id IN (' . $in . ') ORDER BY created_at DESC, id DESC';
  $timelineRes = $conn->query($timelineSql);
  while ($timelineRes && ($ev = $timelineRes->fetch_assoc())) {
    $oid = (int)($ev['order_id'] ?? 0);
    if ($oid <= 0) {
      continue;
    }
    if (!isset($timelineByOrder[$oid])) {
      $timelineByOrder[$oid] = [];
    }
    if (count($timelineByOrder[$oid]) < 8) {
      $timelineByOrder[$oid][] = $ev;
    }
  }

  $refundSql = 'SELECT order_id, COALESCE(SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END), 0) AS refund_count, COALESCE(SUM(CASE WHEN status = "processed" THEN COALESCE(approved_amount, requested_amount, 0) ELSE 0 END), 0) AS refund_total, MAX(CASE WHEN status = "processed" THEN COALESCE(processed_at, updated_at, created_at) ELSE NULL END) AS last_refunded_at FROM refund_transactions WHERE order_id IN (' . $in . ') GROUP BY order_id';
  $refundRes = $conn->query($refundSql);
  while ($refundRes && ($rr = $refundRes->fetch_assoc())) {
    $oid = (int)($rr['order_id'] ?? 0);
    if ($oid <= 0) {
      continue;
    }
    $refundSummaryByOrder[$oid] = [
      'count' => (int)($rr['refund_count'] ?? 0),
      'total' => (float)($rr['refund_total'] ?? 0),
      'last_refunded_at' => (string)($rr['last_refunded_at'] ?? ''),
    ];
  }

  $itemColumns = ['id', 'order_id', 'product_name_snapshot', 'unit_price', 'quantity', 'line_total'];
  $columnRes = $conn->query('SHOW COLUMNS FROM order_items');
  $itemColumnMap = [];
  while ($columnRes && ($column = $columnRes->fetch_assoc())) {
    $name = strtolower((string)($column['Field'] ?? ''));
    if ($name !== '') {
      $itemColumnMap[$name] = true;
    }
  }
  if (!empty($itemColumnMap['cake_message'])) {
    $itemColumns[] = 'cake_message';
  }
  if (!empty($itemColumnMap['variant_snapshot'])) {
    $itemColumns[] = 'variant_snapshot';
  }
  if (!empty($itemColumnMap['topper_name_snapshot'])) {
    $itemColumns[] = 'topper_name_snapshot';
  }
  if (!empty($itemColumnMap['customisation_note'])) {
    $itemColumns[] = 'customisation_note';
  }

  $itemSql = 'SELECT ' . implode(', ', $itemColumns) . ' FROM order_items WHERE order_id IN (' . $in . ') ORDER BY order_id ASC, id ASC';
  $itemRes = $conn->query($itemSql);
  while ($itemRes && ($item = $itemRes->fetch_assoc())) {
    $oid = (int)($item['order_id'] ?? 0);
    if ($oid <= 0) {
      continue;
    }
    if (!isset($orderItemsByOrder[$oid])) {
      $orderItemsByOrder[$oid] = [];
    }
    $orderItemsByOrder[$oid][] = $item;
  }

  try {
    $pdoFinance = \App\Core\Database::getConnection();
    $snapshotService = new \App\Services\OrderFinanceSnapshotService();
    foreach ($orderIds as $oid) {
      $snap = $snapshotService->buildSnapshot($pdoFinance, (int)$oid);
      if (!empty($snap['ok'])) {
        $financeSnapshotByOrder[(int)$oid] = $snap;
      }
    }
  } catch (\Throwable $e) {
    error_log('[orders.php] Finance snapshot build skipped: ' . $e->getMessage());
  }
}
?>
<style>
.o-shell { background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:18px; box-shadow:0 14px 28px rgba(68,16,34,.08); overflow:hidden; }
.o-shell__head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:16px 18px; border-bottom:1px solid rgba(128,0,31,.09); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); flex-wrap:wrap; }
.o-shell__head h3 { margin:0; font-family:'DM Serif Display',Georgia,serif; font-weight:400; color:#80001F; font-size:1.35rem; }
.o-shell__meta { font-size:.8rem; color:#8f7681; }
.o-head-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.o-view-switch { display:inline-flex; gap:6px; align-items:center; }
.o-view-switch a { text-decoration:none; font-size:.73rem; padding:5px 9px; border-radius:999px; border:1px solid rgba(128,0,31,.2); color:#80001F; background:#fff; }
.o-view-switch a.active { background:#80001F; color:#fff; border-color:#80001F; }
.o-page-size { display:inline-flex; align-items:center; gap:8px; }
.o-page-size select { border:1px solid rgba(128,0,31,.18); border-radius:10px; padding:8px 10px; font-size:.8rem; color:#4b343d; }

.o-filter-meta { margin-top:8px; color:#8f7681; font-size:.8rem; }
.o-filter-chips { margin-top:10px; display:flex; flex-wrap:wrap; gap:8px; }
.o-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:#fff3f6; border:1px solid rgba(128,0,31,.14); font-size:.76rem; color:#6e2a3e; }
.o-chip a { color:#80001F; text-decoration:none; font-weight:700; }
.o-quick-links { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
.o-quick-links a { text-decoration:none; font-size:.74rem; padding:4px 8px; border-radius:8px; border:1px solid rgba(128,0,31,.18); color:#80001F; background:#fff; }
.o-quick-links a:hover { background:#fff4f8; }

.o-filter-wrap { padding:14px 18px; border-bottom:1px solid rgba(128,0,31,.09); background:#fffdfd; }
.o-filter-grid { display:grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap:10px; }
.o-field { display:grid; gap:6px; }
.o-field label { font-size:.7rem; text-transform:uppercase; letter-spacing:.08em; color:#80001F; font-weight:700; }
.o-field input, .o-field select { border:1px solid rgba(128,0,31,.18); border-radius:10px; min-height:38px; padding:0 10px; font-size:.82rem; color:#4b343d; }
.o-field--wide { grid-column: span 2; }
.o-filter-actions { margin-top:10px; display:flex; flex-wrap:wrap; gap:8px; }

.o-list { display:grid; gap:0; }
.o-card { border-bottom:1px solid rgba(128,0,31,.08); padding:14px 18px; transition:background 140ms; }
.o-card:last-child { border-bottom:none; }
.o-card:hover { background:#fff9fb; }

.o-card__top { display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; }
.o-card__id { display:inline-block; padding:5px 10px; border-radius:999px; background:#f8d8de; color:#80001F; font-weight:700; font-size:.76rem; white-space:nowrap; }
.o-card__cust { flex:1; min-width:140px; }
.o-card__name { font-weight:600; color:#2d1f25; font-size:.9rem; }
.o-card__phone { color:#7f6973; font-size:.78rem; margin-top:2px; }
.o-card__right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; }
.o-card__price { font-weight:700; color:#80001F; font-size:1rem; }
.o-card__date { color:#9c8590; font-size:.74rem; white-space:nowrap; }

.o-badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:.7rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
.o-badge--pending_payment { background:#fff2cf; color:#9a5b00; }
.o-badge--payment_under_review { background:#fef9c3; color:#713f12; }
.o-badge--confirmed { background:#dcfce7; color:#166534; }
.o-badge--preparing { background:#fef3c7; color:#92400e; }
.o-badge--out_for_delivery { background:#e0f2fe; color:#0c4a6e; }
.o-badge--ready_for_pickup { background:#ede9fe; color:#5b21b6; }
.o-badge--delivered { background:#e0e7ff; color:#3730a3; }
.o-badge--completed { background:#d1fae5; color:#065f46; }
.o-badge--cancelled { background:#fee2e2; color:#991b1b; }
.o-badge--refund_requested { background:#ffedd5; color:#9a3412; }
.o-badge--refunded { background:#f3e8ff; color:#6b21a8; }
.o-badge--partially_refunded { background:#fae8ff; color:#86198f; }
.o-badge--fully_refunded { background:#ede9fe; color:#5b21b6; }
.o-badge--rejected { background:#fecaca; color:#7f1d1d; }

.o-pay-badge { display:inline-block; padding:3px 8px; border-radius:999px; font-size:.66rem; font-weight:600; letter-spacing:.03em; text-transform:uppercase; }
.o-pay-badge--paid { background:#d1fae5; color:#065f46; }
.o-pay-badge--pending { background:#fef9c3; color:#713f12; }
.o-pay-badge--under_review { background:#fff2cf; color:#9a5b00; }
.o-pay-badge--credit { background:#fce7f3; color:#9d174d; }
.o-pay-badge--failed { background:#fee2e2; color:#991b1b; }
.o-pay-badge--refunded { background:#e0e7ff; color:#3730a3; }
.o-pay-badge--partially_refunded { background:#f3e8ff; color:#6b21a8; }
.o-pay-badge--refund_pending { background:#ffedd5; color:#9a3412; }
.o-pay-badge--rejected { background:#fecaca; color:#7f1d1d; }
.o-fin-badge { display:inline-block; padding:3px 9px; border-radius:999px; font-size:.64rem; font-weight:700; letter-spacing:.03em; text-transform:uppercase; }
.o-fin-badge--paid { background:#dcfce7; color:#166534; }
.o-fin-badge--pending { background:#fef3c7; color:#92400e; }
.o-fin-badge--refunded { background:#e9d5ff; color:#6b21a8; }
.o-fin-badge--partial { background:#ffedd5; color:#9a3412; }
.o-src-badge { display:inline-block; padding:2px 7px; border-radius:999px; font-size:.64rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; vertical-align:middle; }
.o-src-badge--byoc { background:#ede9fe; color:#5b21b6; }
.o-src-badge--manual { background:#dcfce7; color:#166534; }
.o-src-badge--archived { background:#ffe4e6; color:#9f1239; }

.o-card__actions { margin-top:10px; display:flex; flex-wrap:wrap; gap:8px; align-items:flex-start; }
.o-card__details-toggle { background:#fff; color:#80001F; border:1px solid rgba(128,0,31,.3); }
.o-card__details-toggle:hover { background:#fff6f8; }
.o-segment-switch { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
.o-segment-switch a { text-decoration:none; font-size:.74rem; padding:5px 9px; border-radius:999px; border:1px solid rgba(128,0,31,.2); color:#80001F; background:#fff; }
.o-segment-switch a.active { background:#80001F; color:#fff; border-color:#80001F; }
.o-ops-grid { margin-top:10px; display:grid; gap:8px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.o-card__details { display:block; }
.o-shell--compact .o-card__details { display:none; }
.o-shell--compact .o-card.o-card--expanded .o-card__details { display:block; }
.o-ops-block { border:1px solid rgba(128,0,31,.1); border-radius:10px; background:#fff; padding:8px 10px; }
.o-ops-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:#8f7681; font-weight:700; margin-bottom:3px; }
.o-ops-value { font-size:.82rem; color:#3f2a33; line-height:1.35; }
.o-items-inline { margin-top:10px; border:1px solid rgba(128,0,31,.12); border-radius:10px; background:#fffdfd; padding:10px 12px; }
.o-items-inline h4 { margin:0 0 8px; color:#6f2940; font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; }
.o-item-inline-row { padding:6px 0; border-top:1px dashed rgba(128,0,31,.15); }
.o-item-inline-row:first-of-type { border-top:none; padding-top:0; }
.o-item-head { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.o-item-qty { font-weight:700; color:#6b102b; font-size:.78rem; }
.o-item-name { font-weight:600; color:#2d1f25; font-size:.82rem; }
.o-item-custom { margin-top:4px; display:flex; gap:6px; flex-wrap:wrap; }
.o-item-chip { padding:2px 7px; border-radius:999px; font-size:.68rem; background:#fdf2f8; color:#9d174d; border:1px solid rgba(236,72,153,.18); }

.o-inline-activity { margin-top:10px; border-radius:10px; border:1px solid transparent; padding:10px 36px 10px 12px; position:relative; font-size:.82rem; }
.o-inline-activity--success { background:#ecfdf3; color:#166534; border-color:#bbf7d0; }
.o-inline-activity--error { background:#fff1f2; color:#9f1239; border-color:#fecdd3; }
.o-inline-activity--info { background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }
.o-inline-activity__close { position:absolute; right:8px; top:8px; border:0; background:transparent; font-size:1rem; color:inherit; cursor:pointer; line-height:1; }

.o-confirm-box { border:1px solid rgba(128,0,31,.15); border-radius:12px; padding:10px 12px; display:grid; gap:8px; background:#fff; min-width:200px; }
.o-confirm-box label { font-size:.77rem; color:#5c3b46; display:grid; gap:4px; }
.o-confirm-box select { border:1px solid rgba(128,0,31,.18); border-radius:8px; padding:6px 8px; font-size:.8rem; color:#4b343d; }
.o-confirm-box .o-checks { display:grid; gap:5px; }
.o-confirm-box .o-checks label { display:flex; align-items:center; gap:6px; font-size:.74rem; color:#5f4550; }
.o-confirm-box .o-checks input[type=checkbox] { accent-color:#80001F; }

.btn { background:#80001F; color:#fff; padding:8px 14px; border-radius:10px; text-decoration:none; font-size:.78rem; font-weight:600; display:inline-block; transition:background 160ms,transform 160ms; border:none; cursor:pointer; }
.btn:hover { background:#5f0017; transform:translateY(-1px); }
.btn--green { background:#16a34a; } .btn--green:hover { background:#15803d; }
.btn--blue { background:#2563eb; } .btn--blue:hover { background:#1d4ed8; }
.btn--red { background:#dc2626; } .btn--red:hover { background:#b91c1c; }
.btn--grey { background:#6b7280; } .btn--grey:hover { background:#4b5563; }
.btn--pink { background:#db2777; } .btn--pink:hover { background:#be185d; }
.btn--black { background:#111; } .btn--black:hover { background:#333; }
.btn--outline { background:#fff; color:#80001F; border:1px solid rgba(128,0,31,.3); } .btn--outline:hover { background:#fff6f8; }
.btn--sm { padding:6px 10px; font-size:.73rem; }
.btn--icon { padding:7px 10px; font-size:.88rem; line-height:1; }

.o-edit-panel { display:none; margin-top:10px; border:1px solid rgba(128,0,31,.15); border-radius:12px; padding:12px; background:#fffbfc; }
.o-edit-panel.open { display:grid; gap:10px; }
.o-edit-panel label { font-size:.78rem; color:#5c3b46; display:grid; gap:4px; }
.o-edit-panel input, .o-edit-panel textarea { border:1px solid rgba(128,0,31,.18); border-radius:8px; padding:7px 9px; font-size:.82rem; color:#2d1f25; font-family:inherit; }
.o-edit-panel textarea { resize:vertical; min-height:56px; }
.o-edit-panel .o-edit-row { display:flex; gap:8px; flex-wrap:wrap; }
.o-edit-meta { font-size:.72rem; color:#7a5a66; margin-top:-4px; }
.o-items-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:10px; overflow:hidden; }
.o-items-table th, .o-items-table td { border-bottom:1px solid rgba(128,0,31,.1); padding:8px; text-align:left; vertical-align:middle; }
.o-items-table tr:last-child td { border-bottom:none; }
.o-items-table th { background:#fff2f6; color:#6f2940; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; }
.o-items-table input[type="text"], .o-items-table input[type="number"] { width:100%; min-width:80px; }
.o-items-note { font-size:.72rem; color:#855f6d; }
.o-new-items { display:grid; gap:8px; }
.o-new-item-row { display:grid; grid-template-columns: 2fr .8fr 1fr 2fr auto; gap:8px; align-items:end; padding:8px; border:1px solid rgba(128,0,31,.12); border-radius:10px; background:#fff; }
.o-new-item-row .btn { height:34px; }
.o-preview-box { border:1px solid rgba(37,99,235,.2); background:#eff6ff; color:#1e3a8a; border-radius:10px; padding:10px; display:none; }
.o-preview-box.open { display:block; }
.o-preview-box h4 { margin:0 0 6px; font-size:.82rem; }
.o-preview-list { margin:0; padding-left:18px; display:grid; gap:3px; }
.o-preview-meta { margin-top:8px; font-size:.74rem; color:#334155; }

.o-meta-row { margin-top:8px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.o-refund-chip { display:inline-flex; align-items:center; gap:6px; border:1px solid rgba(124,58,237,.18); color:#6b21a8; background:#faf5ff; border-radius:999px; padding:4px 10px; font-size:.72rem; font-weight:700; }
.o-timeline { margin-top:10px; border:1px solid rgba(128,0,31,.12); border-radius:10px; padding:10px 12px; background:#fff9fb; display:none; }
.o-timeline.open { display:block; }
.o-timeline ul { margin:0; padding-left:18px; display:grid; gap:6px; }
.o-timeline li { color:#4b343d; font-size:.78rem; }
.o-timeline strong { color:#80001F; }

.o-pagination { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:14px 18px 18px; }
.o-pagination__meta { font-size:.84rem; color:#7f6973; }

.o-credit-box { border:1px solid rgba(219,39,119,.2); border-radius:12px; padding:10px 12px; background:#fdf2f8; display:grid; gap:8px; min-width:180px; }
.o-credit-box label { font-size:.76rem; color:#9d174d; display:grid; gap:4px; }
.o-credit-box select { border:1px solid rgba(219,39,119,.25); border-radius:8px; padding:6px 8px; font-size:.8rem; }

.o-destructive-warning { border:1px solid #fecdd3; background:#fff1f2; color:#9f1239; border-radius:10px; padding:10px; font-size:.78rem; line-height:1.35; }
.o-destructive-warning.is-hidden { display:none; }

.o-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:9990; display:none; align-items:center; justify-content:center; padding:16px; }
.o-modal-overlay.open { display:flex; }
.o-modal { width:min(620px, 100%); background:#fff; border-radius:14px; border:1px solid rgba(128,0,31,.18); box-shadow:0 28px 50px rgba(15,23,42,.28); }
.o-modal__head { padding:14px 16px; border-bottom:1px solid rgba(128,0,31,.12); }
.o-modal__title { margin:0; font-size:1rem; color:#6f2940; }
.o-modal__sub { margin-top:4px; font-size:.8rem; color:#7a5a66; }
.o-modal__body { padding:14px 16px; display:grid; gap:10px; }
.o-modal__row { display:grid; gap:6px; }
.o-modal__row label { font-size:.76rem; text-transform:uppercase; letter-spacing:.06em; color:#7f1d1d; font-weight:700; }
.o-modal__row input, .o-modal__row select, .o-modal__row textarea { border:1px solid rgba(128,0,31,.2); border-radius:9px; padding:8px 10px; font-size:.84rem; color:#2d1f25; font-family:inherit; }
.o-modal__row textarea { min-height:80px; resize:vertical; }
.o-modal__checks { display:grid; gap:8px; font-size:.82rem; color:#4b343d; }
.o-modal__checks label { display:flex; gap:8px; align-items:flex-start; }
.o-modal__checks input[type=checkbox] { margin-top:2px; accent-color:#9f1239; }
.o-modal__foot { padding:12px 16px 14px; border-top:1px solid rgba(128,0,31,.12); display:flex; gap:8px; justify-content:flex-end; }
.o-modal__status { font-size:.78rem; color:#6b7280; }

@media (max-width: 1200px) {
  .o-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 760px) {
  .o-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .o-field--wide { grid-column: span 2; }
  .o-ops-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .o-filter-grid { grid-template-columns: 1fr; }
  .o-field--wide { grid-column: span 1; }
  .o-shell__head { flex-direction:column; align-items:flex-start; }
  .o-card__top { flex-direction:column; }
  .o-card__right { align-items:flex-start; flex-direction:row; gap:10px; }
  .o-confirm-box { min-width:unset; width:100%; }
  .o-items-table { font-size:.76rem; }
  .o-new-item-row { grid-template-columns: 1fr; }
}
</style>

<div class="o-shell <?php echo $isCompactView ? 'o-shell--compact' : ''; ?>">
  <div class="o-shell__head">
    <h3>Orders</h3>
    <div class="o-head-actions">
      <div class="o-shell__meta"><?php echo (int)$ordersTotalRows; ?> orders found</div>
      <div class="o-view-switch">
        <a href="orders.php?<?php echo orders_query_string($filtersState, ['orders_view' => 'compact', 'page' => 1]); ?>" class="<?php echo $isCompactView ? 'active' : ''; ?>">Compact</a>
        <a href="orders.php?<?php echo orders_query_string($filtersState, ['orders_view' => 'expanded', 'page' => 1]); ?>" class="<?php echo !$isCompactView ? 'active' : ''; ?>">Expanded</a>
      </div>
      <form class="o-page-size" method="get">
        <?php foreach ($filtersState as $key => $value): ?>
          <?php if ($key !== 'per_page' && $key !== 'page' && $value !== ''): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <label for="per_page" class="o-shell__meta">Rows</label>
        <select id="per_page" name="per_page" onchange="this.form.submit()">
          <?php foreach ($orderPerPageOptions as $size): ?>
            <option value="<?php echo (int)$size; ?>" <?php echo $orderPerPage === (int)$size ? 'selected' : ''; ?>><?php echo (int)$size; ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="page" value="1">
      </form>
      <?php if ($canOrderCredit): ?>
        <a class="btn btn--pink btn--sm" href="credit_report.php">Credit Report</a>
      <?php endif; ?>
      <?php if (admin_has_permission('manual_orders')): ?>
        <a class="btn btn--sm" href="manual_order.php">+ Manual Order</a>
      <?php endif; ?>
      <a class="btn btn--sm" href="production_plan.php" style="background:#5b1f3a;color:#fff;">🖨 Daily Production</a>
    </div>
  </div>

  <div class="o-filter-wrap">
    <form method="get">
      <div class="o-filter-grid">
        <div class="o-field o-field--wide">
          <label for="q">Universal Search</label>
          <input id="q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="order, customer, phone, email, product, status, payment, amount">
        </div>

        <div class="o-field"><label for="mobile">Mobile Search</label><input id="mobile" type="tel" name="mobile" value="<?php echo htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 98765 43210"></div>

        <div class="o-field"><label for="date_from">Date From</label><input id="date_from" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="o-field"><label for="date_to">Date To</label><input id="date_to" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>"></div>

        <div class="o-field">
          <label for="order_status">Order Status</label>
          <select id="order_status" name="order_status">
            <option value="">All</option>
            <?php foreach ($allowedOrderStatuses as $st): ?>
              <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $orderStatus === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="o-field">
          <label for="payment_status">Payment Status</label>
          <select id="payment_status" name="payment_status">
            <option value="">All</option>
            <?php foreach ($allowedPaymentStatuses as $st): ?>
              <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $paymentStatus === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="o-field">
          <label for="payment_method">Payment Method</label>
          <select id="payment_method" name="payment_method">
            <option value="">All</option>
            <?php foreach ($allowedPaymentMethods as $pm): ?>
              <option value="<?php echo htmlspecialchars($pm, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $paymentMethod === $pm ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="o-field">
          <label for="fulfilment_mode">Fulfilment</label>
          <select id="fulfilment_mode" name="fulfilment_mode">
            <option value="">All</option>
            <?php foreach ($allowedFulfilmentModes as $mode): ?>
              <option value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $fulfilmentMode === $mode ? 'selected' : ''; ?>><?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="o-field">
          <label for="source_channel">User Channel</label>
          <select id="source_channel" name="source_channel">
            <option value="">All</option>
            <option value="online" <?php echo $sourceChannel === 'online' ? 'selected' : ''; ?>>Registered User</option>
            <option value="manual" <?php echo $sourceChannel === 'manual' ? 'selected' : ''; ?>>Guest / No Account</option>
          </select>
        </div>

        <div class="o-field">
          <label for="order_source">Order Source</label>
          <select id="order_source" name="order_source">
            <option value="">All Sources</option>
            <option value="retail" <?php echo $orderSource === 'retail' ? 'selected' : ''; ?>>Online / Retail</option>
            <option value="byoc_quote" <?php echo $orderSource === 'byoc_quote' ? 'selected' : ''; ?>>BYOC Quote</option>
            <option value="manual" <?php echo $orderSource === 'manual' ? 'selected' : ''; ?>>Manual Order</option>
          </select>
        </div>

        <div class="o-field"><label for="amount_min">Amount Min</label><input id="amount_min" type="number" step="0.01" min="0" name="amount_min" value="<?php echo htmlspecialchars($amountMinRaw, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="o-field"><label for="amount_max">Amount Max</label><input id="amount_max" type="number" step="0.01" min="0" name="amount_max" value="<?php echo htmlspecialchars($amountMaxRaw, ENT_QUOTES, 'UTF-8'); ?>"></div>

        <div class="o-field">
          <label for="coupon_mode">Coupon Usage</label>
          <select id="coupon_mode" name="coupon_mode">
            <option value="">All</option>
            <option value="yes" <?php echo $couponMode === 'yes' ? 'selected' : ''; ?>>Has Coupon</option>
            <option value="no" <?php echo $couponMode === 'no' ? 'selected' : ''; ?>>No Coupon</option>
          </select>
        </div>
        <div class="o-field"><label for="coupon_code">Coupon Code</label><input id="coupon_code" name="coupon_code" value="<?php echo htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'); ?>" placeholder="code or snapshot"></div>
      </div>

      <div class="o-filter-meta"><?php echo count($activeFilters); ?> active filters</div>
      <div class="o-quick-links">
        <a href="orders.php?<?php echo orders_query_string($filtersState, ['date_from' => $todayDate, 'date_to' => $todayDate, 'page' => 1]); ?>">Today</a>
        <a href="orders.php?<?php echo orders_query_string($filtersState, ['date_from' => $last7Date, 'date_to' => $todayDate, 'page' => 1]); ?>">Last 7 Days</a>
        <a href="orders.php?<?php echo orders_query_string($filtersState, ['date_from' => $last30Date, 'date_to' => $todayDate, 'page' => 1]); ?>">Last 30 Days</a>
      </div>
      <div class="o-segment-switch">
        <a class="<?php echo $orderSegment === 'operational' ? 'active' : ''; ?>" href="orders.php?<?php echo orders_query_string($filtersState, ['order_segment' => 'operational', 'page' => 1]); ?>">Operational</a>
        <a class="<?php echo $orderSegment === 'historical' ? 'active' : ''; ?>" href="orders.php?<?php echo orders_query_string($filtersState, ['order_segment' => 'historical', 'page' => 1]); ?>">Historical</a>
        <a class="<?php echo $orderSegment === 'all' ? 'active' : ''; ?>" href="orders.php?<?php echo orders_query_string($filtersState, ['order_segment' => 'all', 'page' => 1]); ?>">All</a>
      </div>

      <?php if ($activeFilters): ?>
      <div class="o-filter-chips">
        <?php foreach ($activeFilters as $chip): ?>
          <?php
            $remove = [];
            if ($chip['key'] === 'date_range') {
              $remove = ['date_from' => '', 'date_to' => '', 'page' => 1];
            } elseif ($chip['key'] === 'amount_range') {
              $remove = ['amount_min' => '', 'amount_max' => '', 'page' => 1];
            } else {
              $remove = [$chip['key'] => '', 'page' => 1];
            }
          ?>
          <span class="o-chip">
            <?php echo htmlspecialchars($chip['label'], ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($chip['value'], ENT_QUOTES, 'UTF-8'); ?>
            <a href="orders.php?<?php echo orders_query_string($filtersState, $remove); ?>" aria-label="Remove <?php echo htmlspecialchars($chip['label'], ENT_QUOTES, 'UTF-8'); ?> filter">x</a>
          </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="o-filter-actions">
        <button type="submit" class="btn btn--sm">Apply Filters</button>
        <a class="btn btn--outline btn--sm" href="orders.php">Reset</a>
        <input type="hidden" name="per_page" value="<?php echo (int)$orderPerPage; ?>">
        <input type="hidden" name="page" value="1">
      </div>
    </form>
  </div>

  <div class="o-list">
<?php
$statusLabels = [
  'pending_payment'      => 'Pending Payment',
  'payment_under_review' => 'Under Review',
  'awaiting_confirmation'=> 'Awaiting Confirmation',
  'confirmed'            => 'Confirmed',
  'preparing'            => 'Preparing',
  'ready_for_pickup'     => 'Ready For Pickup',
  'out_for_delivery'     => 'Out For Delivery',
  'delivered'            => 'Delivered',
  'completed'            => 'Completed',
  'cancelled'            => 'Cancelled',
  'refund_requested'     => 'Refund Requested',
  'refunded'             => 'Refunded',
  'partially_refunded'   => 'Partial Refund',
  'fully_refunded'       => 'Fully Refunded',
  'rejected'             => 'Rejected',
];
$payMethodLabels = [
  'upi_manual' => 'UPI/Bank',
  'cod' => 'Cash',
  'gateway' => 'Gateway',
  'credit' => 'Credit',
];

foreach ($orders as $row):
  $oid = (int)$row['id'];
  $ostatus = (string)($row['order_status'] ?? 'pending');
  $pstatus = (string)($row['payment_status'] ?? 'pending');
  $isArchived = $hasArchivedColumn && (int)($row['is_archived'] ?? 0) === 1;
  $orderSource = (string)($row['order_source'] ?? 'retail');
  $governance = $stateManager->getAllowedActions($ostatus, $pstatus);
  $canCancelAction = (bool)$governance['can_cancel'] && $canCancelUnpaid;
  $canRefundAction = (bool)$governance['can_refund'] && $canOrderRefund;
  $canFinancialEdit = !(bool)$governance['is_financially_locked'] && !$isArchived;
  $isManualOrder = $orderSource === 'manual' || ($retailActsAsManual && ($orderSource === 'retail' || $orderSource === ''));
  $isByocOrder = $orderSource === 'byoc_quote' || (int)($row['byoc_quote_id'] ?? 0) > 0;
  $canMutateItems = $canFinancialEdit
    && ($isManualOrder || $isByocOrder)
    && in_array($pstatus, ['pending', 'under_review', 'failed', 'rejected'], true)
    && in_array((string)($row['production_status'] ?? 'pending'), ['pending', 'not_required'], true);
  $orderItems = $orderItemsByOrder[$oid] ?? [];
  $eventDate = '';
  if (!empty($row['scheduled_slot'])) {
    $eventDate = (string)$row['scheduled_slot'];
  } elseif (!empty($row['scheduled_for'])) {
    $eventDate = (string)$row['scheduled_for'];
  }
  $snapshot = $financeSnapshotByOrder[$oid] ?? null;
  $advancePaid = round((float)($row['advance_amount'] ?? 0), 2);
  $refundAmount = round((float)($row['refund_amount'] ?? $row['total_refunded'] ?? 0), 2);
  $grossTotal = round((float)($row['grand_total'] ?? 0), 2);
  $collectedTotal = round((float)($snapshot['collected_total'] ?? 0), 2);
  $advanceReceived = round((float)($snapshot['advance_received'] ?? 0), 2);
  $balanceDue = isset($snapshot['balance_due'])
    ? round((float)$snapshot['balance_due'], 2)
    : max(0.0, round($grossTotal - min($grossTotal, $advancePaid) - $refundAmount, 2));
  $invoiceStatusHint = (string)($snapshot['invoice_status_hint'] ?? '');
  $financialLastEvent = (string)($snapshot['financial_last_event'] ?? '');
  $collectionStatus = strtolower(trim((string)($snapshot['collection_status'] ?? 'payment_pending')));
  $financeBadge = (string)($governance['finance_badge'] ?? 'Pending');
  $refundSummary = $refundSummaryByOrder[$oid] ?? ['count' => 0, 'total' => 0.0, 'last_refunded_at' => ''];
  $timelineEvents = $timelineByOrder[$oid] ?? [];
  $editId = 'edit-' . $oid;
  $timelineId = 'timeline-' . $oid;
?>
<div class="o-card">
  <div class="o-card__top">
    <span class="o-card__id">#<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?></span>
    <?php if ($orderSource !== '' && $orderSource !== 'retail'): ?>
      <?php $srcClass = $orderSource === 'byoc_quote' ? 'byoc' : 'manual'; ?>
      <?php $srcLabel = $orderSource === 'byoc_quote' ? 'BYOC' : 'Manual'; ?>
      <span class="o-src-badge o-src-badge--<?php echo $srcClass; ?>"><?php echo $srcLabel; ?></span>
    <?php endif; ?>
    <?php if ($isArchived): ?>
      <span class="o-src-badge o-src-badge--archived">Archived</span>
    <?php endif; ?>

    <div class="o-card__cust">
      <div class="o-card__name"><?php echo htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="o-card__phone"><?php echo htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div class="o-card__right">
      <span class="o-card__price">Rs <?php echo htmlspecialchars((string)$row['grand_total'], ENT_QUOTES, 'UTF-8'); ?></span>
      <span class="o-badge o-badge--<?php echo htmlspecialchars($ostatus, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($statusLabels[$ostatus] ?? $ostatus, ENT_QUOTES, 'UTF-8'); ?>
      </span>
      <?php if ($pstatus !== 'pending'): ?>
        <span class="o-pay-badge o-pay-badge--<?php echo htmlspecialchars($pstatus, ENT_QUOTES, 'UTF-8'); ?>">
          <?php
          if ($pstatus === 'paid') {
            $pm = $payMethodLabels[$row['payment_method'] ?? ''] ?? '';
            echo 'Paid' . ($pm !== '' ? ' · ' . htmlspecialchars($pm, ENT_QUOTES, 'UTF-8') : '');
          } elseif ($pstatus === 'credit') {
            echo 'Credit';
          } else {
            echo htmlspecialchars(ucfirst($pstatus), ENT_QUOTES, 'UTF-8');
          }
          ?>
        </span>
      <?php endif; ?>
      <?php
        $financeBadgeClass = 'pending';
        if ($financeBadge === 'Paid') {
            $financeBadgeClass = 'paid';
        } elseif ($financeBadge === 'Refunded') {
            $financeBadgeClass = 'refunded';
        } elseif ($financeBadge === 'Partial Refund') {
            $financeBadgeClass = 'partial';
        }
      ?>
      <span class="o-fin-badge o-fin-badge--<?php echo htmlspecialchars($financeBadgeClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($financeBadge, ENT_QUOTES, 'UTF-8'); ?></span>
      <span class="o-card__date"><?php echo htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </div>

  <div class="o-meta-row">
    <?php if ((int)$refundSummary['count'] > 0): ?>
      <span class="o-refund-chip">Refunds: <?php echo (int)$refundSummary['count']; ?> · Rs <?php echo number_format((float)$refundSummary['total'], 2); ?></span>
      <?php if ((string)$refundSummary['last_refunded_at'] !== ''): ?>
        <span class="o-shell__meta">Last refund: <?php echo htmlspecialchars((string)$refundSummary['last_refunded_at'], ENT_QUOTES, 'UTF-8'); ?></span>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($timelineEvents)): ?>
      <button type="button" class="btn btn--outline btn--sm" onclick="toggleTimeline('<?php echo $timelineId; ?>')">Timeline</button>
    <?php endif; ?>
  </div>

  <div class="o-card__details">
  <div class="o-ops-grid">
    <div class="o-ops-block">
      <div class="o-ops-label">Contact</div>
      <div class="o-ops-value"><?php echo htmlspecialchars((string)($row['customer_phone'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="o-ops-value"><?php echo htmlspecialchars((string)($row['customer_email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="o-ops-block">
      <div class="o-ops-label">Fulfillment & Slot</div>
      <div class="o-ops-value"><?php echo htmlspecialchars((string)($row['fulfilment_mode'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="o-ops-value"><?php echo htmlspecialchars((string)($row['scheduled_slot_label'] ?? ($eventDate !== '' ? $eventDate : '-')), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="o-ops-block">
      <div class="o-ops-label">Finance Snapshot</div>
      <?php $isFullyPaid = in_array($pstatus, ['paid', 'partially_refunded', 'refunded'], true) || $collectionStatus === 'fully_paid'; ?>
      <?php $showVerifiedAdvance = $collectionStatus === 'advance_paid' && $advanceReceived > 0; ?>
      <?php if ($isFullyPaid): ?>
        <div class="o-ops-value">Collected: Rs <?php echo number_format(max($collectedTotal, $grossTotal - $refundAmount), 2); ?></div>
      <?php elseif ($showVerifiedAdvance): ?>
        <div class="o-ops-value">Advance: Rs <?php echo number_format(max($advanceReceived, 0), 2); ?></div>
      <?php else: ?>
        <div class="o-ops-value">Advance: Rs <?php echo number_format(0, 2); ?></div>
      <?php endif; ?>
      <div class="o-ops-value">Balance Due: Rs <?php echo number_format($balanceDue, 2); ?></div>
      <div class="o-ops-value">Refund Status: <?php echo htmlspecialchars((string)($row['refund_status'] ?? 'none'), ENT_QUOTES, 'UTF-8'); ?></div>
      <?php if ($financialLastEvent !== '' || $invoiceStatusHint !== ''): ?>
        <div class="o-ops-value">Txn Link: <?php echo htmlspecialchars($financialLastEvent !== '' ? $financialLastEvent : $invoiceStatusHint, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
    </div>
    <div class="o-ops-block">
      <div class="o-ops-label">Order Source</div>
      <div class="o-ops-value"><?php echo htmlspecialchars((string)($row['order_source'] ?? 'retail'), ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="o-ops-value">Mode: <?php echo htmlspecialchars((string)($row['order_mode'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="o-ops-block">
      <div class="o-ops-label">Event Date</div>
      <div class="o-ops-value"><?php echo htmlspecialchars($eventDate !== '' ? $eventDate : '-', ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="o-ops-value">Created: <?php echo htmlspecialchars((string)($row['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="o-ops-block">
      <div class="o-ops-label">Payment & Fulfillment</div>
      <div class="o-ops-value">Payment: <?php echo htmlspecialchars((string)($row['payment_status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string)($row['payment_method'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>)</div>
      <div class="o-ops-value">Status: <?php echo htmlspecialchars((string)($row['order_status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <div class="o-items-inline">
    <h4>Ordered Products & Customization</h4>
    <?php if (!empty($orderItems)): ?>
      <?php foreach ($orderItems as $item): ?>
        <div class="o-item-inline-row">
          <div class="o-item-head">
            <span class="o-item-qty"><?php echo (int)($item['quantity'] ?? 1); ?>x</span>
            <span class="o-item-name"><?php echo htmlspecialchars((string)($item['product_name_snapshot'] ?? 'Item'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="o-item-custom">
            <?php if (!empty($item['variant_snapshot'])): ?><span class="o-item-chip"><?php echo htmlspecialchars((string)$item['variant_snapshot'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
            <?php if (!empty($item['cake_message'])): ?><span class="o-item-chip"><?php echo htmlspecialchars((string)$item['cake_message'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
            <?php if (!empty($item['topper_name_snapshot'])): ?><span class="o-item-chip">Topper: <?php echo htmlspecialchars((string)$item['topper_name_snapshot'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
            <?php if (!empty($item['customisation_note'])): ?><span class="o-item-chip"><?php echo htmlspecialchars((string)$item['customisation_note'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="o-ops-value">No item snapshot available.</div>
    <?php endif; ?>
  </div>
  </div>

  <div class="o-card__actions">
    <?php if ($isCompactView): ?>
      <button type="button" class="btn btn--sm o-card__details-toggle" onclick="toggleCardDetails(this)">Details</button>
    <?php endif; ?>
    <?php if ((bool)$governance['can_confirm_payment'] && $canOrderEdit && !$isArchived): ?>
      <form method="POST" action="#" class="o-confirm-box js-confirm-payment-form" data-order-id="<?php echo $oid; ?>" data-grand-total="<?php echo htmlspecialchars(number_format((float)($row['grand_total'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
        <label>
          Payment Mode
          <select name="payment_method">
            <option value="upi_manual">UPI / Bank</option>
            <option value="gateway">Gateway</option>
          </select>
        </label>
        <label>
          Amount Received
          <input type="number" name="received_amount" min="0.01" step="0.01" value="<?php echo htmlspecialchars(number_format((float)($row['grand_total'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <button type="submit" class="btn btn--green btn--sm">Confirm Payment</button>
      </form>
    <?php endif; ?>

    <?php if ($canCancelAction && !$isArchived): ?>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Reject this order?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="cancelled">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--red btn--icon" title="Reject Order">X</button>
      </form>
    <?php endif; ?>

    <?php if ((bool)$governance['can_mark_preparing'] && $canOrderEdit && !$isArchived): ?>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Mark this order as preparing?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="preparing">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--blue btn--sm">Mark Preparing</button>
      </form>
    <?php endif; ?>

    <?php if ((bool)$governance['can_mark_delivered'] && $canOrderEdit && !$isArchived): ?>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Mark this order as delivered and complete it?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="delivered">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--sm" style="background:#7c3aed;">Mark Delivered (Complete)</button>
      </form>
    <?php endif; ?>

    <?php if ($canRefundAction && !$isArchived): ?>
      <a href="refunds.php?order_id=<?php echo $oid; ?>" class="btn btn--sm" style="background:#a21caf;">Refund</a>
    <?php endif; ?>

    <?php if ($canOrderDelete): ?>
      <?php if (!$isArchived): ?>
        <button
          type="button"
          class="btn btn--outline btn--sm js-destructive-trigger"
          data-action="archive"
          data-order-id="<?php echo $oid; ?>"
          data-order-number="<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>"
        >Archive</button>
      <?php else: ?>
        <button
          type="button"
          class="btn btn--outline btn--sm js-destructive-trigger"
          data-action="restore"
          data-order-id="<?php echo $oid; ?>"
          data-order-number="<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>"
        >Restore</button>
      <?php endif; ?>
      <?php if ($isSuperAdmin): ?>
        <button
          type="button"
          class="btn btn--red btn--sm js-destructive-trigger"
          data-action="force_purge"
          data-order-id="<?php echo $oid; ?>"
          data-order-number="<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>"
        >Delete Entry</button>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($pstatus === 'credit' && $canOrderCredit && $canFinancialEdit && !$isArchived): ?>
    <div class="o-credit-box">
      <form method="POST" action="collect_credit.php" onsubmit="return confirm('Mark credit as collected?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <label>
          Collect via
          <select name="collected_payment_method">
            <option value="cod">Cash</option>
            <option value="upi_manual">UPI / Bank</option>
          </select>
        </label>
        <button type="submit" class="btn btn--pink btn--sm" style="margin-top:4px;">Collect Now</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($canOrderEdit && $canFinancialEdit): ?>
    <button class="btn btn--outline btn--sm" onclick="toggleEdit('<?php echo $editId; ?>')">Edit</button>
    <?php elseif (!$canFinancialEdit): ?>
    <span class="btn btn--outline btn--sm" style="opacity:.55; cursor:not-allowed;">Financial Edit Locked</span>
    <?php endif; ?>

    <a href="order_details.php?id=<?php echo $oid; ?>" class="btn btn--grey btn--sm">View</a>
    <?php if ($pstatus === 'paid'): ?>
      <a href="order_invoice.php?id=<?php echo $oid; ?>" class="btn btn--black btn--sm">Invoice</a>
    <?php else: ?>
      <span class="btn btn--black btn--sm" style="opacity:.45; background:#9ca3af; border-color:#9ca3af; cursor:not-allowed; pointer-events:none;" title="Invoice unlocks only after payment is confirmed.">Invoice</span>
    <?php endif; ?>
  </div>

  <?php if (!empty($timelineEvents)): ?>
  <div class="o-timeline" id="<?php echo $timelineId; ?>">
    <ul>
      <?php foreach ($timelineEvents as $ev): ?>
        <li>
          <strong><?php echo htmlspecialchars((string)($ev['new_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
          <?php if ((string)($ev['previous_status'] ?? '') !== ''): ?>
            <span>(from <?php echo htmlspecialchars((string)$ev['previous_status'], ENT_QUOTES, 'UTF-8'); ?>)</span>
          <?php endif; ?>
          <span> · <?php echo htmlspecialchars((string)($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
          <?php if ((string)($ev['reason'] ?? '') !== ''): ?>
            <span> · <?php echo htmlspecialchars((string)$ev['reason'], ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if ($actionOrderId === $oid && $actionMessage !== ''): ?>
    <div class="o-inline-activity o-inline-activity--<?php echo htmlspecialchars($actionLevel, ENT_QUOTES, 'UTF-8'); ?>" data-autohide="6000">
      <?php echo $actionMessage; ?>
      <button type="button" class="o-inline-activity__close" aria-label="Close">x</button>
    </div>
  <?php endif; ?>

  <?php if ($canOrderEdit && !$isArchived): ?>
  <div class="o-edit-panel" id="<?php echo $editId; ?>">
    <form method="POST" action="save_order_edit.php" class="js-order-edit-form" data-order-id="<?php echo $oid; ?>" data-order-number="<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
      <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
      <input type="hidden" name="preview_seen" value="0" class="js-preview-seen">
      <div class="o-edit-row">
        <label style="flex:1; min-width:140px;">
          Customer Phone
          <input type="text" name="customer_phone" value="<?php echo htmlspecialchars((string)($row['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="20" data-original="<?php echo htmlspecialchars((string)($row['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label style="flex:1; min-width:160px;">
          Slot / Delivery Label
          <input type="text" name="scheduled_slot_label" value="<?php echo htmlspecialchars((string)($row['scheduled_slot_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" data-original="<?php echo htmlspecialchars((string)($row['scheduled_slot_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
      </div>
      <label>
        Admin Note
        <textarea name="admin_note" data-original="<?php echo htmlspecialchars((string)($row['admin_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($row['admin_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>

      <label>
        Edit Reason <span style="color:#b91c1c;">*</span>
        <textarea name="edit_reason" required placeholder="Explain why this edit is needed"></textarea>
      </label>

      <?php if ($isManualOrder || $isByocOrder): ?>
      <div class="o-edit-meta">
        <?php if ($isByocOrder): ?>
          BYOC order: pricing fields are immutable. Only allowed non-pricing details can be edited.
        <?php elseif (!$canMutateItems): ?>
          Item edits are disabled after production starts or once payment is finalized.
        <?php else: ?>
          Manual order: item names, quantities and prices can be edited before payment finalization.
        <?php endif; ?>
      </div>

      <?php if (!empty($orderItems)): ?>
      <table class="o-items-table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Message</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orderItems as $item): ?>
            <?php $itemId = (int)($item['id'] ?? 0); ?>
            <tr class="js-existing-item-row" data-item-id="<?php echo $itemId; ?>" data-orig-name="<?php echo htmlspecialchars((string)($item['product_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-orig-qty="<?php echo (int)($item['quantity'] ?? 1); ?>" data-orig-unit-price="<?php echo htmlspecialchars((string)($item['unit_price'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" data-orig-message="<?php echo htmlspecialchars((string)($item['cake_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <td>
                <input type="hidden" name="items[<?php echo $itemId; ?>][item_id]" value="<?php echo $itemId; ?>">
                <input type="text" name="items[<?php echo $itemId; ?>][name]" value="<?php echo htmlspecialchars((string)($item['product_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($canMutateItems && $isManualOrder) ? '' : 'readonly'; ?>>
              </td>
              <td>
                <input type="number" min="1" step="1" name="items[<?php echo $itemId; ?>][quantity]" value="<?php echo (int)($item['quantity'] ?? 1); ?>" <?php echo ($canMutateItems && $isManualOrder) ? '' : 'readonly'; ?>>
              </td>
              <td>
                <input type="number" min="0" step="0.01" name="items[<?php echo $itemId; ?>][unit_price]" value="<?php echo htmlspecialchars((string)($item['unit_price'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($canMutateItems && $isManualOrder) ? '' : 'readonly'; ?>>
              </td>
              <td>
                <input type="text" name="items[<?php echo $itemId; ?>][cake_message]" value="<?php echo htmlspecialchars((string)($item['cake_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($canMutateItems || $isByocOrder) ? '' : 'readonly'; ?>>
              </td>
              <td style="text-align:center;">
                <?php if ($canMutateItems && $isManualOrder): ?>
                  <input type="checkbox" class="js-delete-item" name="delete_item_ids[]" value="<?php echo $itemId; ?>">
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if ($canMutateItems && $isManualOrder): ?>
      <div class="o-items-note">Add as many new manual items as needed. Use Preview Changes before save.</div>
      <div class="o-new-items js-new-items-container" data-next-index="0"></div>
      <div class="o-edit-row">
        <button type="button" class="btn btn--outline btn--sm js-add-item-row">+ Add Item Row</button>
      </div>
      <?php endif; ?>

      <div class="o-edit-row">
        <label style="min-width:140px; flex:1;">
          Discount Override
          <input type="number" name="discount_override" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($row['discount_total'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" data-original="<?php echo htmlspecialchars((string)($row['discount_total'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($canMutateItems && $isManualOrder) ? '' : 'readonly'; ?>>
        </label>
        <label style="min-width:140px; flex:1;">
          Delivery Fee Override
          <input type="number" name="delivery_fee_override" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($row['delivery_fee'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" data-original="<?php echo htmlspecialchars((string)($row['delivery_fee'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($canMutateItems && $isManualOrder) ? '' : 'readonly'; ?>>
        </label>
      </div>
      <?php endif; ?>

      <div class="o-preview-box js-preview-box">
        <h4>Change Preview</h4>
        <ul class="o-preview-list js-preview-list"></ul>
        <div class="o-preview-meta js-preview-meta"></div>
      </div>

      <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:2px;">
        <button type="button" class="btn btn--blue btn--sm js-preview-btn">Preview Changes</button>
        <button type="submit" class="btn btn--green btn--sm">Save Changes</button>
        <button type="button" class="btn btn--outline btn--sm" onclick="toggleEdit('<?php echo $editId; ?>')">Cancel</button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (!$orders): ?>
<div class="o-card">
  <div class="o-card__name">No orders found for current filters.</div>
</div>
<?php endif; ?>
  </div>

  <div class="o-pagination">
    <span class="o-pagination__meta">Showing <?php echo count($orders); ?> of <?php echo (int)$ordersTotalRows; ?> orders · Page <?php echo (int)$ordersPage; ?> of <?php echo (int)$ordersTotalPages; ?></span>
    <?php if ($ordersPage > 1): ?>
      <a class="btn btn--sm btn--outline" href="orders.php?<?php echo orders_query_string($filtersState, ['page' => $ordersPage - 1]); ?>">Previous</a>
    <?php endif; ?>
    <?php if ($ordersPage < $ordersTotalPages): ?>
      <a class="btn btn--sm" href="orders.php?<?php echo orders_query_string($filtersState, ['page' => $ordersPage + 1]); ?>">Next</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($canOrderDelete): ?>
<div class="o-modal-overlay" id="destructiveModalOverlay" aria-hidden="true">
  <div class="o-modal" role="dialog" aria-modal="true" aria-labelledby="destructiveModalTitle">
    <div class="o-modal__head">
      <h4 class="o-modal__title" id="destructiveModalTitle">Destructive Order Action</h4>
      <div class="o-modal__sub" id="destructiveModalSub">Validate reason, impact, and authorization before continuing.</div>
    </div>
    <form id="destructiveActionForm">
      <div class="o-modal__body">
        <input type="hidden" name="order_id" id="destructive_order_id" value="">
        <input type="hidden" name="action" id="destructive_action" value="archive">
        <input type="hidden" name="final_confirm" value="1">

        <div class="o-modal__row">
          <label for="destructive_reason_code">Reason Code</label>
          <select id="destructive_reason_code" name="reason_code" required>
            <option value="">Select a reason</option>
            <option value="duplicate_order">Duplicate Order</option>
            <option value="fraudulent_order">Fraudulent Order</option>
            <option value="customer_request">Customer Request</option>
            <option value="test_order">Test Order</option>
            <option value="compliance_removal">Compliance Removal</option>
            <option value="data_correction">Data Correction</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div class="o-modal__row">
          <label for="destructive_reason_notes">Reason Notes</label>
          <textarea id="destructive_reason_notes" name="reason_notes" maxlength="2000" placeholder="Describe the action context for audit and recovery."></textarea>
        </div>

        <div class="o-destructive-warning is-hidden" id="destructiveFinancialWarning"></div>

        <div class="o-modal__row">
          <label for="destructive_delete_password">Delete Password</label>
          <input id="destructive_delete_password" type="password" name="delete_password" autocomplete="off" required>
        </div>

        <div class="o-modal__checks">
          <label><input type="checkbox" id="confirm_financial_purge" name="confirm_financial_purge" value="1"> I acknowledge financial and audit impact if this action purges linked records.</label>
          <label><input type="checkbox" id="destructive_final_confirm" required> I understand this action is operationally destructive and should only be used with approval.</label>
        </div>

        <div class="o-modal__status" id="destructiveStatus">Impact preview will be loaded automatically.</div>
      </div>
      <div class="o-modal__foot">
        <button type="button" class="btn btn--outline btn--sm" id="destructiveCancelBtn">Cancel</button>
        <button type="submit" class="btn btn--red btn--sm" id="destructiveSubmitBtn">Execute Action</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function toggleEdit(id) {
  var panel = document.getElementById(id);
  if (panel) panel.classList.toggle('open');
}

function toggleTimeline(id) {
  var panel = document.getElementById(id);
  if (panel) panel.classList.toggle('open');
}

function toggleCardDetails(trigger) {
  var card = trigger ? trigger.closest('.o-card') : null;
  if (!card) return;
  card.classList.toggle('o-card--expanded');
  trigger.textContent = card.classList.contains('o-card--expanded') ? 'Hide Details' : 'Details';
}

function normaliseNum(value) {
  var num = parseFloat(String(value || '').trim());
  if (!isFinite(num)) return 0;
  return Math.round(num * 100) / 100;
}

function markPreviewDirty(form) {
  var seen = form.querySelector('.js-preview-seen');
  if (seen) {
    seen.value = '0';
  }
}

function createNewItemRow(container, index) {
  var wrapper = document.createElement('div');
  wrapper.className = 'o-new-item-row js-new-item-row';
  wrapper.innerHTML =
    '<label>New Item Name<input type="text" maxlength="180" name="items_new[' + index + '][name]" placeholder="Custom Cake"></label>' +
    '<label>Qty<input type="number" min="1" step="1" value="1" name="items_new[' + index + '][quantity]"></label>' +
    '<label>Unit Price<input type="number" min="0" step="0.01" value="0" name="items_new[' + index + '][unit_price]"></label>' +
    '<label>Cake Message<input type="text" maxlength="280" name="items_new[' + index + '][cake_message]" placeholder="Happy Birthday..."></label>' +
    '<button type="button" class="btn btn--outline btn--sm js-remove-item-row">Remove</button>';
  return wrapper;
}

function renderChangePreview(form) {
  var list = form.querySelector('.js-preview-list');
  var box = form.querySelector('.js-preview-box');
  var meta = form.querySelector('.js-preview-meta');
  if (!list || !box || !meta) {
    return 0;
  }

  var changes = [];
  var phone = form.querySelector('[name="customer_phone"]');
  var slot = form.querySelector('[name="scheduled_slot_label"]');
  var note = form.querySelector('[name="admin_note"]');
  var discount = form.querySelector('[name="discount_override"]');
  var delivery = form.querySelector('[name="delivery_fee_override"]');

  if (phone && phone.dataset.original !== undefined && String(phone.value) !== String(phone.dataset.original)) {
    changes.push('Customer phone updated');
  }
  if (slot && slot.dataset.original !== undefined && String(slot.value) !== String(slot.dataset.original)) {
    changes.push('Slot/delivery label updated');
  }
  if (note && note.dataset.original !== undefined && String(note.value) !== String(note.dataset.original)) {
    changes.push('Admin note updated');
  }
  if (discount && discount.dataset.original !== undefined && normaliseNum(discount.value) !== normaliseNum(discount.dataset.original)) {
    changes.push('Discount override changed to Rs ' + normaliseNum(discount.value).toFixed(2));
  }
  if (delivery && delivery.dataset.original !== undefined && normaliseNum(delivery.value) !== normaliseNum(delivery.dataset.original)) {
    changes.push('Delivery fee override changed to Rs ' + normaliseNum(delivery.value).toFixed(2));
  }

  form.querySelectorAll('.js-existing-item-row').forEach(function(row) {
    var itemId = row.getAttribute('data-item-id') || '';
    var nameInput = row.querySelector('input[name$="[name]"]');
    var qtyInput = row.querySelector('input[name$="[quantity]"]');
    var priceInput = row.querySelector('input[name$="[unit_price]"]');
    var messageInput = row.querySelector('input[name$="[cake_message]"]');
    var deleteInput = row.querySelector('.js-delete-item');

    if (deleteInput && deleteInput.checked) {
      changes.push('Item #' + itemId + ' marked for deletion');
      return;
    }

    if (nameInput && String(nameInput.value) !== String(row.getAttribute('data-orig-name') || '')) {
      changes.push('Item #' + itemId + ' name changed');
    }
    if (qtyInput && normaliseNum(qtyInput.value) !== normaliseNum(row.getAttribute('data-orig-qty') || '0')) {
      changes.push('Item #' + itemId + ' quantity changed to ' + String(qtyInput.value || '0'));
    }
    if (priceInput && normaliseNum(priceInput.value) !== normaliseNum(row.getAttribute('data-orig-unit-price') || '0')) {
      changes.push('Item #' + itemId + ' price changed to Rs ' + normaliseNum(priceInput.value).toFixed(2));
    }
    if (messageInput && String(messageInput.value) !== String(row.getAttribute('data-orig-message') || '')) {
      changes.push('Item #' + itemId + ' cake message changed');
    }
  });

  form.querySelectorAll('.js-new-item-row').forEach(function(row) {
    var nameInput = row.querySelector('input[name$="[name]"]');
    var qtyInput = row.querySelector('input[name$="[quantity]"]');
    var priceInput = row.querySelector('input[name$="[unit_price]"]');
    if (!nameInput) {
      return;
    }
    var name = String(nameInput.value || '').trim();
    if (name === '') {
      return;
    }
    var qty = qtyInput ? normaliseNum(qtyInput.value) : 1;
    var unit = priceInput ? normaliseNum(priceInput.value) : 0;
    changes.push('New item to add: ' + name + ' (' + qty + ' x Rs ' + unit.toFixed(2) + ')');
  });

  list.innerHTML = '';
  if (changes.length === 0) {
    var empty = document.createElement('li');
    empty.textContent = 'No changes detected yet.';
    list.appendChild(empty);
  } else {
    changes.forEach(function(text) {
      var li = document.createElement('li');
      li.textContent = text;
      list.appendChild(li);
    });
  }

  var orderNumber = form.getAttribute('data-order-number') || '#';
  meta.textContent = 'Order ' + orderNumber + ' · ' + changes.length + ' change(s) prepared.';
  box.classList.add('open');

  var seen = form.querySelector('.js-preview-seen');
  if (seen) {
    seen.value = '1';
  }
  return changes.length;
}

function setDestructiveStatus(text, isError) {
  var statusNode = document.getElementById('destructiveStatus');
  if (!statusNode) {
    return;
  }
  statusNode.textContent = text;
  statusNode.style.color = isError ? '#9f1239' : '#6b7280';
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.o-inline-activity').forEach(function(node) {
    var close = node.querySelector('.o-inline-activity__close');
    if (close) {
      close.addEventListener('click', function() {
        node.remove();
      });
    }
    var ms = parseInt(node.getAttribute('data-autohide') || '0', 10);
    if (ms > 0) {
      window.setTimeout(function() {
        if (node && node.parentNode) {
          node.remove();
        }
      }, ms);
    }
  });

  document.querySelectorAll('.js-order-edit-form').forEach(function(form) {
    form.querySelectorAll('input, textarea, select').forEach(function(field) {
      field.addEventListener('input', function() {
        markPreviewDirty(form);
      });
      field.addEventListener('change', function() {
        markPreviewDirty(form);
      });
    });

    var addBtn = form.querySelector('.js-add-item-row');
    if (addBtn) {
      addBtn.addEventListener('click', function() {
        var container = form.querySelector('.js-new-items-container');
        if (!container) {
          return;
        }
        var nextIndex = parseInt(container.getAttribute('data-next-index') || '0', 10);
        var row = createNewItemRow(container, nextIndex);
        container.appendChild(row);
        container.setAttribute('data-next-index', String(nextIndex + 1));
        markPreviewDirty(form);
      });
    }

    form.addEventListener('click', function(event) {
      var target = event.target;
      if (target && target.classList && target.classList.contains('js-remove-item-row')) {
        var row = target.closest('.js-new-item-row');
        if (row) {
          row.remove();
          markPreviewDirty(form);
        }
      }
    });

    var previewBtn = form.querySelector('.js-preview-btn');
    if (previewBtn) {
      previewBtn.addEventListener('click', function() {
        renderChangePreview(form);
      });
    }

    form.addEventListener('submit', function(event) {
      var previewSeen = form.querySelector('.js-preview-seen');
      if (!previewSeen || previewSeen.value !== '1') {
        event.preventDefault();
        renderChangePreview(form);
        alert('Preview generated. Review the Change Preview box, then click Save Changes again.');
        return;
      }
    });
  });

  var modalOverlay = document.getElementById('destructiveModalOverlay');
  if (modalOverlay) {
    var destructiveForm = document.getElementById('destructiveActionForm');
    var destructiveOrderId = document.getElementById('destructive_order_id');
    var destructiveAction = document.getElementById('destructive_action');
    var destructiveTitle = document.getElementById('destructiveModalTitle');
    var destructiveSub = document.getElementById('destructiveModalSub');
    var destructiveSubmitBtn = document.getElementById('destructiveSubmitBtn');
    var destructiveWarning = document.getElementById('destructiveFinancialWarning');
    var confirmFinancial = document.getElementById('confirm_financial_purge');
    var finalConfirm = document.getElementById('destructive_final_confirm');
    var reasonCode = document.getElementById('destructive_reason_code');
    var reasonNotes = document.getElementById('destructive_reason_notes');
    var deletePassword = document.getElementById('destructive_delete_password');

    function closeModal() {
      modalOverlay.classList.remove('open');
      modalOverlay.setAttribute('aria-hidden', 'true');
    }

    function openModalFor(button) {
      var action = String(button.getAttribute('data-action') || 'archive');
      var orderId = String(button.getAttribute('data-order-id') || '0');
      var orderNumber = String(button.getAttribute('data-order-number') || orderId);

      destructiveOrderId.value = orderId;
      destructiveAction.value = action;
      reasonCode.value = '';
      reasonNotes.value = '';
      deletePassword.value = '';
      confirmFinancial.checked = false;
      finalConfirm.checked = false;
      confirmFinancial.disabled = action !== 'force_purge';
      destructiveWarning.classList.add('is-hidden');
      destructiveWarning.textContent = '';

      var titleMap = {
        archive: 'Archive Order #' + orderNumber,
        restore: 'Restore Archived Order #' + orderNumber,
        force_purge: 'Delete Entry #' + orderNumber
      };
      var submitMap = {
        archive: 'Archive Order',
        restore: 'Restore Order',
        force_purge: 'Delete Entry'
      };

      destructiveTitle.textContent = titleMap[action] || 'Destructive Order Action';
      destructiveSub.textContent = action === 'force_purge'
        ? 'This can permanently remove order records and linked financial history.'
        : 'This action is audited and requires password verification.';
      destructiveSubmitBtn.textContent = submitMap[action] || 'Execute Action';
      setDestructiveStatus('Checking financial impact and dependencies...', false);

      modalOverlay.classList.add('open');
      modalOverlay.setAttribute('aria-hidden', 'false');

      var previewBody = new URLSearchParams();
      previewBody.set('action', 'preview');
      previewBody.set('order_id', orderId);

      fetch('api/order-destructive-action.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: previewBody.toString()
      })
        .then(function(response) { return response.json(); })
        .then(function(payload) {
          if (!payload || !payload.success) {
            setDestructiveStatus(payload && payload.message ? payload.message : 'Preview check failed.', true);
            return;
          }
          var hasFinancial = Boolean(payload.has_financial_entries);
          if (hasFinancial) {
            destructiveWarning.classList.remove('is-hidden');
            destructiveWarning.textContent = String(payload.financial_message || 'Financial records are linked to this order. Review impact before continuing.');
            if (action === 'force_purge') {
              confirmFinancial.required = true;
            }
          } else {
            confirmFinancial.required = false;
          }
          setDestructiveStatus(hasFinancial ? 'Financial links detected. Additional acknowledgement is required for delete entry.' : 'No linked financial entries detected.', false);
        })
        .catch(function() {
          setDestructiveStatus('Could not load impact preview. You can still proceed with explicit confirmation.', true);
        });
    }

    document.querySelectorAll('.js-destructive-trigger').forEach(function(button) {
      button.addEventListener('click', function() {
        openModalFor(button);
      });
    });

    var cancelButton = document.getElementById('destructiveCancelBtn');
    if (cancelButton) {
      cancelButton.addEventListener('click', function() {
        closeModal();
      });
    }

    modalOverlay.addEventListener('click', function(event) {
      if (event.target === modalOverlay) {
        closeModal();
      }
    });

    destructiveForm.addEventListener('submit', function(event) {
      event.preventDefault();
      if (!finalConfirm.checked) {
        setDestructiveStatus('Final confirmation is required.', true);
        return;
      }

      var action = destructiveAction.value;
      if (action === 'force_purge' && !confirmFinancial.checked) {
        setDestructiveStatus('Financial impact acknowledgement is required for delete entry.', true);
        return;
      }

      if (String(reasonCode.value || '').trim() === '') {
        setDestructiveStatus('Please choose a reason code.', true);
        return;
      }

      if (String(deletePassword.value || '').trim() === '') {
        setDestructiveStatus('Delete password is required.', true);
        return;
      }

      var formBody = new URLSearchParams();
      formBody.set('action', action);
      formBody.set('order_id', destructiveOrderId.value);
      formBody.set('reason_code', reasonCode.value);
      formBody.set('reason_notes', reasonNotes.value);
      formBody.set('delete_password', deletePassword.value);
      formBody.set('final_confirm', '1');
      if (confirmFinancial.checked) {
        formBody.set('confirm_financial_purge', '1');
      }

      destructiveSubmitBtn.disabled = true;
      setDestructiveStatus('Executing action...', false);

      fetch('api/order-destructive-action.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: formBody.toString()
      })
        .then(function(response) { return response.json(); })
        .then(function(payload) {
          if (!payload || !payload.success) {
            setDestructiveStatus(payload && payload.message ? payload.message : 'Destructive action failed.', true);
            destructiveSubmitBtn.disabled = false;
            return;
          }

          var query = new URLSearchParams(window.location.search || '');
          query.set('action_order_id', destructiveOrderId.value);
          query.set('action_level', 'success');
          query.set('action_message', payload.message || 'Destructive action completed successfully.');
          window.location.search = query.toString();
        })
        .catch(function() {
          setDestructiveStatus('Network error while executing action.', true);
          destructiveSubmitBtn.disabled = false;
        });
    });
  }

  document.querySelectorAll('.js-confirm-payment-form').forEach(function(form) {
    form.addEventListener('submit', function(event) {
      event.preventDefault();

      var orderId = parseInt(String(form.getAttribute('data-order-id') || '0'), 10);
      if (!orderId) {
        alert('Invalid order ID for confirmation.');
        return;
      }

      var expectedAmount = parseFloat(String(form.getAttribute('data-grand-total') || '0'));
      var paymentMethodEl = form.querySelector('select[name="payment_method"]');
      var receivedAmountEl = form.querySelector('input[name="received_amount"]');
      var paymentMethod = paymentMethodEl ? String(paymentMethodEl.value || 'upi_manual') : 'upi_manual';
      var receivedAmount = receivedAmountEl ? parseFloat(String(receivedAmountEl.value || '0')) : 0;
      if (!isFinite(receivedAmount) || receivedAmount <= 0) {
        alert('Please enter a valid received amount.');
        return;
      }

      var shortfall = Math.max(0, +(expectedAmount - receivedAmount).toFixed(2));
      var discountReason = '';
      var managerOverride = false;
      if (shortfall > 0) {
        discountReason = prompt('Shortfall will be adjusted as discount. Enter reason:', 'On-call approved adjustment') || '';
        if (!confirm('Apply discount ₹' + shortfall.toFixed(2) + ' and confirm payment?')) {
          return;
        }
        var ratio = expectedAmount > 0 ? (shortfall / expectedAmount) : 0;
        if (ratio > 0.05) {
          managerOverride = confirm('Discount exceeds 5%. Confirm manager override?');
        }
      } else if (!confirm('Confirm payment and approve this order?')) {
        return;
      }

      var submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
      }

      fetch('/api/admin/orders/' + orderId + '/confirm-payment', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          payment_method: paymentMethod,
          received_amount: receivedAmount,
          discount_reason: discountReason,
          manager_override: managerOverride
        })
      })
        .then(function(response) { return response.json(); })
        .then(function(payload) {
          if (!payload || !payload.success) {
            throw new Error(payload && payload.message ? payload.message : 'Payment confirmation failed.');
          }
          window.location.reload();
        })
        .catch(function(error) {
          alert(error.message || 'Payment confirmation failed.');
          if (submitBtn) {
            submitBtn.disabled = false;
          }
        });
    });
  });
});
</script>

</div>
</div>

</body>
</html>
