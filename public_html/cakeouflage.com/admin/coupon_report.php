<?php
$pageTitle = 'Coupon Report';
require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');
require_once __DIR__ . '/includes/db.php';

$couponCodeFilter = trim((string)($_GET['code'] ?? ''));
$userFilter = trim((string)($_GET['user'] ?? ''));
$orderFilter = trim((string)($_GET['order'] ?? ''));
$dateFromFilter = trim((string)($_GET['date_from'] ?? ''));
$dateToFilter = trim((string)($_GET['date_to'] ?? ''));
$orderStatusFilter = trim((string)($_GET['order_status'] ?? ''));
$paymentStatusFilter = trim((string)($_GET['payment_status'] ?? ''));

$allowedOrderStatuses = ['pending', 'confirmed', 'in_preparation', 'out_for_delivery', 'ready_for_pickup', 'completed', 'cancelled'];
$allowedPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'credit'];
if (!in_array($orderStatusFilter, $allowedOrderStatuses, true)) {
    $orderStatusFilter = '';
}
if (!in_array($paymentStatusFilter, $allowedPaymentStatuses, true)) {
    $paymentStatusFilter = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromFilter)) {
    $dateFromFilter = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToFilter)) {
    $dateToFilter = '';
}

$where = ['1=1'];

if ($couponCodeFilter !== '') {
    $safe = $conn->real_escape_string($couponCodeFilter);
    $where[] = "(c.code LIKE '%{$safe}%' OR cr.code_snapshot LIKE '%{$safe}%')";
}
if ($userFilter !== '') {
    $safe = $conn->real_escape_string($userFilter);
    if (ctype_digit($userFilter)) {
        $where[] = '(cr.user_id = ' . (int)$userFilter . ' OR u.id = ' . (int)$userFilter . ')';
    } else {
        $where[] = "(u.email LIKE '%{$safe}%' OR u.full_name LIKE '%{$safe}%' OR u.phone LIKE '%{$safe}%')";
    }
}
if ($orderFilter !== '') {
    $safe = $conn->real_escape_string($orderFilter);
    if (ctype_digit($orderFilter)) {
        $where[] = '(cr.order_id = ' . (int)$orderFilter . ' OR o.id = ' . (int)$orderFilter . ')';
    } else {
        $where[] = "o.order_number LIKE '%{$safe}%'";
    }
}
if ($dateFromFilter !== '') {
    $safe = $conn->real_escape_string($dateFromFilter);
    $where[] = "DATE(cr.created_at) >= '{$safe}'";
}
if ($dateToFilter !== '') {
    $safe = $conn->real_escape_string($dateToFilter);
    $where[] = "DATE(cr.created_at) <= '{$safe}'";
}
if ($orderStatusFilter !== '') {
    $safe = $conn->real_escape_string($orderStatusFilter);
    $where[] = "o.order_status = '{$safe}'";
}
if ($paymentStatusFilter !== '') {
    $safe = $conn->real_escape_string($paymentStatusFilter);
    $where[] = "o.payment_status = '{$safe}'";
}

$whereSql = implode(' AND ', $where);
$rows = [];
$summary = [
    'total_redemptions' => 0,
    'total_discount' => 0.0,
    'unique_coupons' => 0,
    'unique_users' => 0,
];

$sql =
  'SELECT
    cr.id,
    cr.coupon_id,
    cr.order_id,
    cr.user_id,
    cr.code_snapshot,
    cr.discount_total,
    cr.created_at,
    c.code AS coupon_code,
    u.full_name AS user_name,
    u.email AS user_email,
    o.order_number,
    o.order_status,
    o.payment_status
   FROM coupon_redemptions cr
   LEFT JOIN coupons c ON c.id = cr.coupon_id
   LEFT JOIN users u ON u.id = cr.user_id
   LEFT JOIN orders o ON o.id = cr.order_id
   WHERE ' . $whereSql . '
   ORDER BY cr.created_at DESC
   LIMIT 500';

$result = $conn->query($sql);
$couponSeen = [];
$userSeen = [];
while ($result && ($row = $result->fetch_assoc())) {
    $rows[] = $row;
    $summary['total_redemptions']++;
    $summary['total_discount'] += (float)($row['discount_total'] ?? 0);
    if (isset($row['coupon_id']) && (int)$row['coupon_id'] > 0) {
        $couponSeen[(int)$row['coupon_id']] = true;
    }
    if (isset($row['user_id']) && (int)$row['user_id'] > 0) {
        $userSeen[(int)$row['user_id']] = true;
    }
}
$summary['unique_coupons'] = count($couponSeen);
$summary['unique_users'] = count($userSeen);
?>
<style>
.report-shell { display:grid; gap:16px; }
.report-card { background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:16px; box-shadow:0 12px 26px rgba(68,16,34,.08); overflow:hidden; }
.report-card__head { padding:16px 18px; border-bottom:1px solid rgba(128,0,31,.08); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); }
.report-card__head h3 { margin:0; color:#80001F; font-family:'DM Serif Display',Georgia,serif; font-weight:400; }
.report-card__body { padding:18px; }
.report-grid { display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap:10px; }
.report-field { display:grid; gap:6px; }
.report-field label { font-size:.76rem; text-transform:uppercase; letter-spacing:.08em; color:#80001F; font-weight:700; }
.report-field input, .report-field select { border:1px solid rgba(128,0,31,.2); border-radius:10px; padding:9px 11px; font:inherit; }
.report-actions { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
.report-btn { border:0; border-radius:10px; padding:9px 14px; background:#80001F; color:#fff; font-weight:700; cursor:pointer; text-decoration:none; }
.report-btn.ghost { background:#fff; border:1px solid rgba(128,0,31,.2); color:#80001F; }
.report-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-top:12px; }
.report-stat { border:1px solid rgba(128,0,31,.12); border-radius:12px; background:#fff8fa; padding:12px; }
.report-stat .label { color:#8f7681; font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; }
.report-stat .value { color:#80001F; font-family:'DM Serif Display',Georgia,serif; font-size:1.45rem; }
.report-table-wrap { overflow:auto; margin-top:12px; }
.report-table { width:100%; border-collapse:collapse; min-width:860px; }
.report-table th, .report-table td { padding:10px; border-bottom:1px solid rgba(128,0,31,.08); text-align:left; font-size:.86rem; }
.report-table th { color:#80001F; font-weight:700; background:#fff8fa; position:sticky; top:0; z-index:1; }
.report-table tbody tr:nth-child(even) { background:#fffafb; }
.report-table tbody tr:hover { background:#fff3f7; }
.report-note { color:#8f7681; font-size:.85rem; margin-top:10px; }
@media (max-width: 980px) {
  .report-grid, .report-stats { grid-template-columns: repeat(2,minmax(0,1fr)); }
}
@media (max-width: 640px) {
  .report-grid, .report-stats { grid-template-columns: 1fr; }
}
</style>

<div class="report-shell">
  <section class="report-card">
    <div class="report-card__head"><h3>Coupon Report</h3></div>
    <div class="report-card__body">
      <form method="get">
        <div class="report-grid">
          <div class="report-field"><label for="code">Coupon Code</label><input id="code" name="code" value="<?= htmlspecialchars($couponCodeFilter, ENT_QUOTES, 'UTF-8') ?>" placeholder="code or snapshot"></div>
          <div class="report-field"><label for="user">User</label><input id="user" name="user" value="<?= htmlspecialchars($userFilter, ENT_QUOTES, 'UTF-8') ?>" placeholder="id, email, name, phone"></div>
          <div class="report-field"><label for="order">Order</label><input id="order" name="order" value="<?= htmlspecialchars($orderFilter, ENT_QUOTES, 'UTF-8') ?>" placeholder="id or order number"></div>
          <div class="report-field"><label for="order_status">Order Status</label><select id="order_status" name="order_status"><option value="">All</option><?php foreach ($allowedOrderStatuses as $st): ?><option value="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>" <?= $orderStatusFilter === $st ? 'selected' : '' ?>><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
          <div class="report-field"><label for="payment_status">Payment Status</label><select id="payment_status" name="payment_status"><option value="">All</option><?php foreach ($allowedPaymentStatuses as $st): ?><option value="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>" <?= $paymentStatusFilter === $st ? 'selected' : '' ?>><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
          <div class="report-field"><label for="date_from">Redeemed From</label><input id="date_from" type="date" name="date_from" value="<?= htmlspecialchars($dateFromFilter, ENT_QUOTES, 'UTF-8') ?>"></div>
          <div class="report-field"><label for="date_to">Redeemed To</label><input id="date_to" type="date" name="date_to" value="<?= htmlspecialchars($dateToFilter, ENT_QUOTES, 'UTF-8') ?>"></div>
        </div>
        <div class="report-actions">
          <button type="submit" class="report-btn">Apply Filters</button>
          <a href="coupon_report.php" class="report-btn ghost">Reset</a>
        </div>
      </form>

      <p class="report-note">Filtered rows: <?= (int)count($rows) ?> · Max shown: 500</p>

      <div class="report-stats">
        <div class="report-stat"><div class="label">Total Redemptions</div><div class="value"><?= (int)$summary['total_redemptions'] ?></div></div>
        <div class="report-stat"><div class="label">Total Discount Given</div><div class="value">Rs <?= number_format((float)$summary['total_discount'], 2) ?></div></div>
        <div class="report-stat"><div class="label">Unique Coupons Used</div><div class="value"><?= (int)$summary['unique_coupons'] ?></div></div>
        <div class="report-stat"><div class="label">Unique Customers</div><div class="value"><?= (int)$summary['unique_users'] ?></div></div>
      </div>

      <div class="report-table-wrap">
        <table class="report-table">
          <thead>
            <tr>
              <th>Redeemed At</th>
              <th>Coupon</th>
              <th>Order</th>
              <th>User</th>
              <th>Discount</th>
              <th>Order Status</th>
              <th>Payment Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7">No coupon redemptions found for current filters.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $userLabel = trim((string)($row['user_name'] ?? ''));
              $userEmail = trim((string)($row['user_email'] ?? ''));
              if ($userLabel === '') {
                  $userLabel = $userEmail !== '' ? $userEmail : ('User #' . (int)($row['user_id'] ?? 0));
              } elseif ($userEmail !== '') {
                  $userLabel .= ' (' . $userEmail . ')';
              }
            ?>
            <tr>
              <td><?= htmlspecialchars((string)($row['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><strong><?= htmlspecialchars((string)($row['coupon_code'] ?? $row['code_snapshot'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong></td>
              <td><?= htmlspecialchars((string)($row['order_number'] ?? ('#' . (int)($row['order_id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></td>
              <td>Rs <?= number_format((float)($row['discount_total'] ?? 0), 2) ?></td>
              <td><?= htmlspecialchars((string)($row['order_status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($row['payment_status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="report-note">Showing latest 500 filtered redemptions.</p>
    </div>
  </section>
</div>
