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
$allowedOrderSegments = ['operational', 'historical', 'archived', 'all'];

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

$operationalStatuses = ['pending_payment', 'payment_under_review', 'awaiting_confirmation', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed'];
$historicalStatuses = ['delivered', 'completed', 'cancelled', 'refunded', 'partially_refunded', 'fully_refunded', 'rejected'];
if ($orderSegment === 'operational') {
  // Keep recently fulfilled orders visible so they do not disappear right after delivery completion.
  $conditions[] = 'o.order_status IN ("pending_payment", "payment_under_review", "awaiting_confirmation", "confirmed", "preparing", "ready_for_pickup", "out_for_delivery", "delivered", "completed")';
  if ($hasArchivedColumn) {
    $conditions[] = 'COALESCE(o.is_archived, 0) = 0';
  }
} elseif ($orderSegment === 'historical') {
  if ($hasArchivedColumn) {
    $conditions[] = 'o.order_status IN ("delivered", "completed", "cancelled", "refunded", "partially_refunded", "fully_refunded", "rejected")';
    $conditions[] = 'COALESCE(o.is_archived, 0) = 0';
  } else {
    $conditions[] = 'o.order_status IN ("delivered", "completed", "cancelled", "refunded", "partially_refunded", "fully_refunded", "rejected")';
  }
} elseif ($orderSegment === 'archived') {
  if ($hasArchivedColumn) {
    $conditions[] = 'COALESCE(o.is_archived, 0) = 1';
  } else {
    $conditions[] = '1 = 0';
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
<?php
$statusLabels = [
  'pending_payment' => 'Pending Payment',
  'payment_under_review' => 'Under Review',
  'awaiting_confirmation' => 'Awaiting Confirmation',
  'confirmed' => 'Confirmed',
  'preparing' => 'Preparing',
  'ready_for_pickup' => 'Ready',
  'out_for_delivery' => 'Out For Delivery',
  'delivered' => 'Delivered',
  'completed' => 'Completed',
  'cancelled' => 'Cancelled',
  'refund_requested' => 'Refund Requested',
  'refunded' => 'Refunded',
  'partially_refunded' => 'Partial Refund',
  'fully_refunded' => 'Fully Refunded',
  'rejected' => 'Rejected',
];

$payMethodLabels = [
  'upi_manual' => 'UPI',
  'cod' => 'Cash',
  'gateway' => 'Gateway',
  'credit' => 'Credit',
];

$quickCounts = [
  'pending_payment' => 0,
  'confirmed' => 0,
  'preparing' => 0,
  'ready_for_pickup' => 0,
  'delivered' => 0,
  'refunded' => 0,
  'refund_alerts' => 0,
];

foreach ($orders as $orderRowStats) {
  $st = (string)($orderRowStats['order_status'] ?? '');
  if (array_key_exists($st, $quickCounts)) {
    $quickCounts[$st]++;
  }
  if (in_array($st, ['refunded', 'partially_refunded', 'fully_refunded'], true)) {
    $quickCounts['refunded']++;
  }
  if (in_array((string)($orderRowStats['refund_status'] ?? ''), ['initiated', 'pending', 'approved'], true)) {
    $quickCounts['refund_alerts']++;
  }
}

$ordersCssVersion = @filemtime(__DIR__ . '/assets/css/orders.css') ?: time();
$ordersMobileCssVersion = @filemtime(__DIR__ . '/assets/css/orders-mobile.css') ?: time();
$ordersJsVersion = @filemtime(__DIR__ . '/assets/js/orders.js') ?: time();
$showAdvancedOnLoad = !empty($activeFilters);
?>

<link rel="stylesheet" href="assets/css/orders.css?v=<?php echo (int)$ordersCssVersion; ?>">
<link rel="stylesheet" href="assets/css/orders-mobile.css?v=<?php echo (int)$ordersMobileCssVersion; ?>">

<div class="orders-layout">
  <div class="orders-main">
    <div class="orders-shell">
      <div class="orders-head">
        <h3>Orders</h3>
        <div class="orders-head-actions">
          <span class="orders-meta"><?php echo (int)$ordersTotalRows; ?> Orders Found</span>
          <span class="orders-view-toggle">
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['orders_view' => 'compact', 'page' => 1]); ?>" class="<?php echo $isCompactView ? 'active' : ''; ?>">Compact</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['orders_view' => 'expanded', 'page' => 1]); ?>" class="<?php echo !$isCompactView ? 'active' : ''; ?>">Expanded</a>
          </span>
          <button type="button" id="ordersFilterToggleBtn" class="btnx btnx-outline" onclick="ordersToggleAdvancedFilters()">
            <?php echo $showAdvancedOnLoad ? 'Hide Filters' : 'Filters'; ?>
          </button>
          <?php if (admin_has_permission('manual_orders')): ?>
            <a class="btnx btnx-primary" href="manual_order.php">+ Manual Order</a>
          <?php endif; ?>
          <a class="btnx btnx-muted" href="production_plan.php">Production</a>
        </div>
      </div>

      <div class="orders-filters">
        <div class="orders-quick-row" style="padding-bottom:8px;border-bottom:1px solid rgba(128,0,31,.08);margin-bottom:10px;">
          <div class="orders-chip-row">
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_segment' => 'operational', 'page' => 1]); ?>" class="<?php echo $orderSegment === 'operational' ? 'active' : ''; ?>">Operational</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_segment' => 'historical', 'page' => 1]); ?>" class="<?php echo $orderSegment === 'historical' ? 'active' : ''; ?>">Historical</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_segment' => 'archived', 'page' => 1]); ?>" class="<?php echo $orderSegment === 'archived' ? 'active' : ''; ?>">Archived</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_segment' => 'all', 'page' => 1]); ?>" class="<?php echo $orderSegment === 'all' ? 'active' : ''; ?>">All</a>
          </div>
        </div>

        <?php if ($orderSegment === 'archived' && !$hasArchivedColumn): ?>
          <div class="orders-quick-row" style="padding-top:0;">
            <span class="orders-meta" style="color:#9f1239;">Archive governance columns are not available in this database yet. Run the destructive-governance migration to use Archived orders.</span>
          </div>
        <?php endif; ?>

        <div class="orders-quick-row">
          <div class="orders-chip-row">
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['page' => 1]); ?>" class="<?php echo $orderStatus === '' ? 'active' : ''; ?>">All Orders</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_status' => 'pending_payment', 'page' => 1]); ?>" class="<?php echo $orderStatus === 'pending_payment' ? 'active' : ''; ?>">Pending Payment</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_status' => 'preparing', 'page' => 1]); ?>" class="<?php echo $orderStatus === 'preparing' ? 'active' : ''; ?>">Preparing</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_status' => 'ready_for_pickup', 'page' => 1]); ?>" class="<?php echo $orderStatus === 'ready_for_pickup' ? 'active' : ''; ?>">Ready</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_status' => 'delivered', 'page' => 1]); ?>" class="<?php echo $orderStatus === 'delivered' ? 'active' : ''; ?>">Delivered</a>
            <a href="orders.php?<?php echo orders_query_string($filtersState, ['order_status' => 'refunded', 'page' => 1]); ?>" class="<?php echo $orderStatus === 'refunded' ? 'active' : ''; ?>">Refunded</a>
          </div>
          <span class="orders-meta"><?php echo count($activeFilters); ?> active filters</span>
        </div>

        <form method="get">
          <div id="ordersAdvancedFilters" class="orders-advanced <?php echo $showAdvancedOnLoad ? 'open' : ''; ?>">
            <div class="orders-field orders-field-wide">
              <label for="q">Search</label>
              <input id="q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="order, customer, phone, email, product">
            </div>
            <div class="orders-field"><label for="mobile">Mobile</label><input id="mobile" name="mobile" value="<?php echo htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="orders-field"><label for="date_from">Date From</label><input id="date_from" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="orders-field"><label for="date_to">Date To</label><input id="date_to" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>"></div>

            <div class="orders-field">
              <label for="order_status">Order Status</label>
              <select id="order_status" name="order_status">
                <option value="">All</option>
                <?php foreach ($allowedOrderStatuses as $st): ?>
                  <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $orderStatus === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="orders-field">
              <label for="payment_status">Payment Status</label>
              <select id="payment_status" name="payment_status">
                <option value="">All</option>
                <?php foreach ($allowedPaymentStatuses as $st): ?>
                  <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $paymentStatus === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="orders-field">
              <label for="fulfilment_mode">Fulfilment</label>
              <select id="fulfilment_mode" name="fulfilment_mode">
                <option value="">All</option>
                <?php foreach ($allowedFulfilmentModes as $mode): ?>
                  <option value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $fulfilmentMode === $mode ? 'selected' : ''; ?>><?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="orders-field">
              <label for="payment_method">Payment Method</label>
              <select id="payment_method" name="payment_method">
                <option value="">All</option>
                <?php foreach ($allowedPaymentMethods as $pm): ?>
                  <option value="<?php echo htmlspecialchars($pm, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $paymentMethod === $pm ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="orders-field"><label for="amount_min">Amount Min</label><input id="amount_min" type="number" step="0.01" name="amount_min" value="<?php echo htmlspecialchars($amountMinRaw, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="orders-field"><label for="amount_max">Amount Max</label><input id="amount_max" type="number" step="0.01" name="amount_max" value="<?php echo htmlspecialchars($amountMaxRaw, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="orders-field"><label for="coupon_code">Coupon</label><input id="coupon_code" name="coupon_code" value="<?php echo htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'); ?>"></div>
          </div>

          <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
            <button type="submit" class="btnx btnx-primary">Apply Filters</button>
            <a class="btnx btnx-outline" href="orders.php">Reset</a>
            <input type="hidden" name="order_segment" value="<?php echo htmlspecialchars($orderSegment, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="per_page" value="<?php echo (int)$orderPerPage; ?>">
            <input type="hidden" name="page" value="1">
          </div>
        </form>
      </div>

      <div class="orders-list">
        <?php foreach ($orders as $row): ?>
          <?php
            $oid = (int)$row['id'];
            $ostatus = (string)($row['order_status'] ?? 'pending');
            $pstatus = (string)($row['payment_status'] ?? 'pending');
            $isArchived = $hasArchivedColumn && (int)($row['is_archived'] ?? 0) === 1;
            $governance = $stateManager->getAllowedActions($ostatus, $pstatus);
            $canRefundAction = (bool)$governance['can_refund'] && $canOrderRefund;
            $refundSummary = $refundSummaryByOrder[$oid] ?? ['count' => 0, 'total' => 0.0, 'last_refunded_at' => ''];
          ?>
          <?php include __DIR__ . '/partials/order-row.php'; ?>
        <?php endforeach; ?>

        <?php if (!$orders): ?>
          <div class="order-row">
            <div class="order-line">No orders found for current filters.</div>
          </div>
        <?php endif; ?>
      </div>

      <div class="orders-pagination">
        <span class="orders-meta">Showing <?php echo count($orders); ?> of <?php echo (int)$ordersTotalRows; ?> · Page <?php echo (int)$ordersPage; ?> / <?php echo (int)$ordersTotalPages; ?></span>
        <div style="display:flex;gap:8px;">
          <?php if ($ordersPage > 1): ?>
            <a class="btnx btnx-outline" href="orders.php?<?php echo orders_query_string($filtersState, ['page' => $ordersPage - 1]); ?>">Previous</a>
          <?php endif; ?>
          <?php if ($ordersPage < $ordersTotalPages): ?>
            <a class="btnx btnx-primary" href="orders.php?<?php echo orders_query_string($filtersState, ['page' => $ordersPage + 1]); ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <aside class="orders-rail">
    <h4 class="rail-title">Status Legend</h4>
    <div class="rail-list">
      <div class="rail-item"><span><span class="rail-dot rail-dot-amber"></span> Pending Payment</span><strong><?php echo (int)$quickCounts['pending_payment']; ?></strong></div>
      <div class="rail-item"><span><span class="rail-dot rail-dot-green"></span> Confirmed</span><strong><?php echo (int)$quickCounts['confirmed']; ?></strong></div>
      <div class="rail-item"><span><span class="rail-dot rail-dot-blue"></span> Preparing</span><strong><?php echo (int)$quickCounts['preparing']; ?></strong></div>
      <div class="rail-item"><span><span class="rail-dot rail-dot-purple"></span> Ready</span><strong><?php echo (int)$quickCounts['ready_for_pickup']; ?></strong></div>
      <div class="rail-item"><span><span class="rail-dot rail-dot-darkgreen"></span> Delivered</span><strong><?php echo (int)$quickCounts['delivered']; ?></strong></div>
      <div class="rail-item"><span><span class="rail-dot rail-dot-gray"></span> Refunded</span><strong><?php echo (int)$quickCounts['refunded']; ?></strong></div>
      <div class="rail-item"><span><span class="rail-dot rail-dot-red"></span> Refund Alerts</span><strong><?php echo (int)$quickCounts['refund_alerts']; ?></strong></div>
    </div>
  </aside>
</div>

<script src="/client/assets/js/scroll-preserve.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../client/assets/js/scroll-preserve.js') ?: time()); ?>"></script>
<script src="assets/js/orders.js?v=<?php echo (int)$ordersJsVersion; ?>"></script>

</div>
</div>

</body>
</html>
