<?php
$pageTitle = 'Orders';
include 'layout.php';
require __DIR__ . '/includes/db.php';

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

$allowedOrderStatuses = ['pending', 'confirmed', 'in_preparation', 'out_for_delivery', 'ready_for_pickup', 'completed', 'cancelled'];
$allowedPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'credit'];
$allowedPaymentMethods = ['upi_manual', 'cod', 'gateway', 'credit'];
$allowedFulfilmentModes = ['delivery', 'pickup', 'custom_delivery'];
$allowedSourceChannels = ['online', 'manual'];

$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$orderStatus = trim((string)($_GET['order_status'] ?? ''));
$paymentStatus = trim((string)($_GET['payment_status'] ?? ''));
$paymentMethod = trim((string)($_GET['payment_method'] ?? ''));
$fulfilmentMode = trim((string)($_GET['fulfilment_mode'] ?? ''));
$sourceChannel = trim((string)($_GET['source_channel'] ?? ''));
$couponMode = trim((string)($_GET['coupon_mode'] ?? ''));
$couponCode = trim((string)($_GET['coupon_code'] ?? ''));
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
if (!in_array($couponMode, ['', 'yes', 'no'], true)) {
    $couponMode = '';
}

$amountMin = is_numeric($amountMinRaw) ? (float)$amountMinRaw : null;
$amountMax = is_numeric($amountMaxRaw) ? (float)$amountMaxRaw : null;

$conditions = ['1=1'];
$types = '';
$params = [];

$hasOrderSourceColumn = false;
if ($sourceColumnResult = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_source'")) {
  $hasOrderSourceColumn = $sourceColumnResult->num_rows > 0;
  $sourceColumnResult->close();
}
if ($hasOrderSourceColumn) {
  $conditions[] = 'COALESCE(o.order_source, "retail") = "retail"';
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

$whereSql = implode(' AND ', $conditions);

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

$listSql = 'SELECT o.* FROM orders o WHERE ' . $whereSql . ' ORDER BY o.id DESC LIMIT ? OFFSET ?';
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
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'order_status' => $orderStatus,
    'payment_status' => $paymentStatus,
    'payment_method' => $paymentMethod,
    'fulfilment_mode' => $fulfilmentMode,
    'source_channel' => $sourceChannel,
    'amount_min' => $amountMinRaw,
    'amount_max' => $amountMaxRaw,
    'coupon_mode' => $couponMode,
    'coupon_code' => $couponCode,
    'per_page' => (string)$orderPerPage,
    'page' => (string)$ordersPage,
];

  $activeFilters = [];
  if ($q !== '') {
    $activeFilters[] = ['key' => 'q', 'label' => 'Search', 'value' => $q];
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
    $activeFilters[] = ['key' => 'source_channel', 'label' => 'Source', 'value' => $sourceChannel];
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
$currentUri = htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? 'orders.php'), ENT_QUOTES, 'UTF-8');
?>
<style>
.o-shell { background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:18px; box-shadow:0 14px 28px rgba(68,16,34,.08); overflow:hidden; }
.o-shell__head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:16px 18px; border-bottom:1px solid rgba(128,0,31,.09); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); flex-wrap:wrap; }
.o-shell__head h3 { margin:0; font-family:'DM Serif Display',Georgia,serif; font-weight:400; color:#80001F; font-size:1.35rem; }
.o-shell__meta { font-size:.8rem; color:#8f7681; }
.o-head-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
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
.o-badge--pending { background:#fff2cf; color:#9a5b00; }
.o-badge--confirmed { background:#dcfce7; color:#166534; }
.o-badge--in_preparation { background:#fef3c7; color:#92400e; }
.o-badge--out_for_delivery { background:#e0f2fe; color:#0c4a6e; }
.o-badge--ready_for_pickup { background:#ede9fe; color:#5b21b6; }
.o-badge--completed { background:#e0e7ff; color:#3730a3; }
.o-badge--cancelled { background:#fee2e2; color:#991b1b; }
.o-badge--credit { background:#fce7f3; color:#9d174d; }

.o-pay-badge { display:inline-block; padding:3px 8px; border-radius:999px; font-size:.66rem; font-weight:600; letter-spacing:.03em; text-transform:uppercase; }
.o-pay-badge--paid { background:#d1fae5; color:#065f46; }
.o-pay-badge--pending { background:#fef9c3; color:#713f12; }
.o-pay-badge--credit { background:#fce7f3; color:#9d174d; }
.o-pay-badge--failed { background:#fee2e2; color:#991b1b; }
.o-pay-badge--refunded { background:#e0e7ff; color:#3730a3; }

.o-card__actions { margin-top:10px; display:flex; flex-wrap:wrap; gap:8px; align-items:flex-start; }

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

.o-pagination { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:14px 18px 18px; }
.o-pagination__meta { font-size:.84rem; color:#7f6973; }

.o-credit-box { border:1px solid rgba(219,39,119,.2); border-radius:12px; padding:10px 12px; background:#fdf2f8; display:grid; gap:8px; min-width:180px; }
.o-credit-box label { font-size:.76rem; color:#9d174d; display:grid; gap:4px; }
.o-credit-box select { border:1px solid rgba(219,39,119,.25); border-radius:8px; padding:6px 8px; font-size:.8rem; }

@media (max-width: 1200px) {
  .o-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 760px) {
  .o-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .o-field--wide { grid-column: span 2; }
}
@media (max-width: 480px) {
  .o-filter-grid { grid-template-columns: 1fr; }
  .o-field--wide { grid-column: span 1; }
  .o-shell__head { flex-direction:column; align-items:flex-start; }
  .o-card__top { flex-direction:column; }
  .o-card__right { align-items:flex-start; flex-direction:row; gap:10px; }
  .o-confirm-box { min-width:unset; width:100%; }
}
</style>

<div class="o-shell">
  <div class="o-shell__head">
    <h3>Orders</h3>
    <div class="o-head-actions">
      <div class="o-shell__meta"><?php echo (int)$ordersTotalRows; ?> orders found</div>
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
    </div>
  </div>

  <div class="o-filter-wrap">
    <form method="get">
      <div class="o-filter-grid">
        <div class="o-field o-field--wide">
          <label for="q">Universal Search</label>
          <input id="q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="order, customer, phone, email, product, status, payment, amount">
        </div>

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
          <label for="source_channel">Source Channel</label>
          <select id="source_channel" name="source_channel">
            <option value="">All</option>
            <option value="online" <?php echo $sourceChannel === 'online' ? 'selected' : ''; ?>>Online</option>
            <option value="manual" <?php echo $sourceChannel === 'manual' ? 'selected' : ''; ?>>Manual</option>
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
  'pending' => 'Pending',
  'confirmed' => 'Confirmed',
  'in_preparation' => 'Order Ready',
  'out_for_delivery' => 'Out For Delivery',
  'ready_for_pickup' => 'Ready For Pickup',
  'completed' => 'Delivered',
  'cancelled' => 'Rejected',
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
  $editId = 'edit-' . $oid;
?>
<div class="o-card">
  <div class="o-card__top">
    <span class="o-card__id">#<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?></span>

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
      <span class="o-card__date"><?php echo htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </div>

  <div class="o-card__actions">
    <?php if ($ostatus === 'pending'): ?>
      <?php if ($canOrderEdit): ?>
      <form method="POST" action="update_order_status.php" class="o-confirm-box" onsubmit="return confirm('Confirm payment and approve this order?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="confirmed">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <label>
          Payment Mode
          <select name="payment_method">
            <option value="upi_manual">UPI / Bank</option>
            <option value="cod">Cash</option>
            <?php if ($canOrderCredit): ?><option value="credit">Credit (collect later)</option><?php endif; ?>
          </select>
        </label>
        <div class="o-checks">
          <label><input type="checkbox" name="send_invoice_email" value="1" checked> Send bill to customer email</label>
          <label><input type="checkbox" name="print_invoice" value="1"> Open print bill after confirm</label>
        </div>
        <button type="submit" class="btn btn--green btn--sm">Confirm Payment</button>
      </form>
      <?php endif; ?>

      <?php if ($canOrderReject): ?>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Reject this order?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="cancelled">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--red btn--icon" title="Reject Order">X</button>
      </form>
      <?php endif; ?>

    <?php elseif ($ostatus === 'confirmed'): ?>
      <?php if ($canOrderEdit): ?>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Mark this order as ready?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="in_preparation">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--blue btn--sm">Mark Order Ready</button>
      </form>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Mark this order as completed/delivered?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="completed">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--sm" style="background:#7c3aed;">Mark Delivered</button>
      </form>
      <?php endif; ?>
      <?php if ($canOrderReject): ?>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Cancel this confirmed order?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="cancelled">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--red btn--icon" title="Cancel Order">X</button>
      </form>
      <?php endif; ?>

    <?php elseif ($ostatus === 'in_preparation' || $ostatus === 'out_for_delivery' || $ostatus === 'ready_for_pickup'): ?>
      <?php if ($canOrderEdit): ?>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Mark this order as delivered/completed?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="completed">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--sm" style="background:#7c3aed;">Mark Delivered</button>
      </form>
      <?php endif; ?>
      <?php if ($canOrderReject): ?>
      <form method="POST" action="update_order_status.php" onsubmit="return confirm('Cancel this order?')">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="cancelled">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btn btn--red btn--icon" title="Cancel Order">X</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($pstatus === 'credit' && $canOrderCredit): ?>
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

    <?php if ($canOrderEdit): ?>
    <button class="btn btn--outline btn--sm" onclick="toggleEdit('<?php echo $editId; ?>')">Edit</button>
    <?php endif; ?>

    <a href="order_details.php?id=<?php echo $oid; ?>" class="btn btn--grey btn--sm">View</a>
    <?php if ($pstatus === 'paid'): ?>
      <a href="order_invoice.php?id=<?php echo $oid; ?>" class="btn btn--black btn--sm">Invoice</a>
    <?php else: ?>
      <span class="btn btn--black btn--sm" style="opacity:.45; background:#9ca3af; border-color:#9ca3af; cursor:not-allowed; pointer-events:none;" title="Invoice unlocks only after payment is confirmed.">Invoice</span>
    <?php endif; ?>
  </div>

  <?php if ($actionOrderId === $oid && $actionMessage !== ''): ?>
    <div class="o-inline-activity o-inline-activity--<?php echo htmlspecialchars($actionLevel, ENT_QUOTES, 'UTF-8'); ?>" data-autohide="6000">
      <?php echo $actionMessage; ?>
      <button type="button" class="o-inline-activity__close" aria-label="Close">x</button>
    </div>
  <?php endif; ?>

  <?php if ($canOrderEdit): ?>
  <div class="o-edit-panel" id="<?php echo $editId; ?>">
    <form method="POST" action="save_order_edit.php">
      <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
      <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
      <div class="o-edit-row">
        <label style="flex:1; min-width:140px;">
          Customer Phone
          <input type="text" name="customer_phone" value="<?php echo htmlspecialchars((string)($row['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="20">
        </label>
        <label style="flex:1; min-width:160px;">
          Slot / Delivery Label
          <input type="text" name="scheduled_slot_label" value="<?php echo htmlspecialchars((string)($row['scheduled_slot_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
        </label>
      </div>
      <label>
        Admin Note
        <textarea name="admin_note"><?php echo htmlspecialchars((string)($row['admin_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>
      <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:2px;">
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

<script>
function toggleEdit(id) {
  var panel = document.getElementById(id);
  if (panel) panel.classList.toggle('open');
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
});
</script>

</div>
</div>

</body>
</html>
