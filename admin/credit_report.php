<?php
$pageTitle = "Credit Report";
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('order_credit');

$targetParams = [
  'payment_method' => 'credit',
  'payment_status' => 'credit',
  'view' => 'collection',
  'from_date' => $_GET['from_date'] ?? '',
  'to_date' => $_GET['to_date'] ?? '',
];
$targetParams = array_filter($targetParams, static fn($v) => $v !== '' && $v !== null);
header('Location: sales_register.php?' . http_build_query($targetParams));
exit;

require __DIR__ . '/includes/db.php';

$period = $_GET['period'] ?? 'all';
$agingFilter = $_GET['aging'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'date_desc';

$perPageOptions = array(20, 50, 100);
$perPage = (int)($_GET['per_page'] ?? 50);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 50;
}
$page = max(1, (int)($_GET['page'] ?? 1));

$whereConditions = array("o.payment_status = 'credit'");

if ($period === 'recent') {
    $whereConditions[] = "DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($period === 'month') {
    $whereConditions[] = "DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

if ($agingFilter === 'due_0_30') {
    $whereConditions[] = "DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif ($agingFilter === 'due_30_60') {
    $whereConditions[] = "DATE(o.created_at) < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)";
} elseif ($agingFilter === 'overdue_60') {
    $whereConditions[] = "DATE(o.created_at) < DATE_SUB(CURDATE(), INTERVAL 60 DAY)";
}

$where = implode(' AND ', $whereConditions);

$orderBy = 'o.id DESC';
if ($sortBy === 'date_asc') {
    $orderBy = 'o.created_at ASC';
} elseif ($sortBy === 'date_desc') {
    $orderBy = 'o.created_at DESC';
} elseif ($sortBy === 'amount_high') {
    $orderBy = 'o.grand_total DESC';
} elseif ($sortBy === 'amount_low') {
    $orderBy = 'o.grand_total ASC';
}

$countResult = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE $where");
$totalRows   = (int)($countResult ? $countResult->fetch_assoc()['total'] : 0);
$totalPages  = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$result = $conn->query(
    "SELECT o.*, a.full_name AS collector_name,
            DATEDIFF(CURDATE(), DATE(o.created_at)) as days_pending
     FROM orders o
     LEFT JOIN admins a ON a.id = o.credit_collected_by_admin_id
     WHERE $where
     ORDER BY $orderBy
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);

// Credit stats
$statsResult = $conn->query(
    "SELECT 
        COUNT(*) as total_orders,
        SUM(o.grand_total) as total_outstanding,
        SUM(CASE WHEN DATEDIFF(CURDATE(), DATE(o.created_at)) <= 30 THEN o.grand_total ELSE 0 END) as due_0_30,
        SUM(CASE WHEN DATEDIFF(CURDATE(), DATE(o.created_at)) > 30 AND DATEDIFF(CURDATE(), DATE(o.created_at)) <= 60 THEN o.grand_total ELSE 0 END) as due_30_60,
        SUM(CASE WHEN DATEDIFF(CURDATE(), DATE(o.created_at)) > 60 THEN o.grand_total ELSE 0 END) as overdue_60
     FROM orders WHERE $where"
);
$stats = $statsResult ? $statsResult->fetch_assoc() : array();
$totalOutstanding = (float)($stats['total_outstanding'] ?? 0);

$flashCollected = (int)($_GET['credit_collected'] ?? 0);
$canOrderCredit = admin_has_permission('order_credit');
$currentUri = htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? 'credit_report.php'), ENT_QUOTES, 'UTF-8');
?>
<style>
.crx-wrap { max-width: 1400px; margin: 0 auto; padding: 20px; }
.crx-header { display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start; margin-bottom: 24px; }
.crx-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 2.2rem; font-weight: 400; color: #80001F; margin: 0; }
.crx-quick-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.crx-btn { background: #fff; border: 1px solid #ddd; padding: 8px 14px; border-radius: 10px; cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: all 150ms; text-decoration: none; display: inline-block; color: #333; }
.crx-btn:hover, .crx-btn.active { background: #80001F; color: #fff; border-color: #80001F; }
.crx-filters { background: #fff; border: 1px solid rgba(128,0,31,.1); border-radius: 16px; padding: 16px; margin-bottom: 20px; display: grid; gap: 12px; }
.crx-filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
.crx-filter-row label { display: grid; gap: 4px; font-size: 0.75rem; font-weight: 700; color: #80001F; text-transform: uppercase; }
.crx-filter-row select { border: 1px solid #ddd; border-radius: 8px; padding: 8px; font-size: 0.85rem; }
.crx-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px; }
.crx-card { background: linear-gradient(135deg, #fff8fa 0%, #fff 100%); border: 1px solid rgba(128,0,31,.1); border-radius: 12px; padding: 16px; }
.crx-card__label { font-size: 0.75rem; color: #8f7681; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
.crx-card__value { font-size: 1.8rem; font-weight: 700; color: #80001F; margin: 6px 0 0; }
.crx-card__meta { font-size: 0.75rem; color: #8f7681; margin-top: 8px; }
.crx-card--overdue { background: linear-gradient(135deg, #fecdd3 0%, #fef2f2 100%); border-color: #ef4444; }
.crx-card--overdue .crx-card__value { color: #991b1b; }
.crx-list { display: grid; gap: 10px; margin-bottom: 20px; }
.crx-card-item { background: #fff; border: 1px solid rgba(128,0,31,.12); border-radius: 12px; padding: 14px; transition: all 150ms; }
.crx-card-item:hover { background: #fff9fb; box-shadow: 0 4px 12px rgba(128,0,31,.08); }
.crx-card-item__top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
.crx-order-id { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #fce7f3; color: #9d174d; font-weight: 700; font-size: 0.75rem; white-space: nowrap; }
.crx-customer { flex: 1; min-width: 140px; }
.crx-customer-name { font-weight: 600; color: #2d1f25; font-size: 0.9rem; }
.crx-customer-phone { color: #7f6973; font-size: 0.78rem; margin-top: 2px; }
.crx-amount { text-align: right; }
.crx-amount-value { font-weight: 700; color: #9d174d; font-size: 1.1rem; }
.crx-amount-meta { color: #9c8590; font-size: 0.74rem; white-space: nowrap; }
.crx-aging { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-left: 10px; }
.crx-aging--0-30 { background: #dcfce7; color: #166534; }
.crx-aging--30-60 { background: #fef3c7; color: #92400e; }
.crx-aging--60+ { background: #fecdd3; color: #991b1b; }
.crx-card-item__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(128,0,31,.08); }
.crx-btn-sm { background: #fff; border: 1px solid rgba(128,0,31,.2); color: #80001F; padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 150ms; }
.crx-btn-sm:hover { background: #80001F; color: #fff; }
.crx-pagination { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 0; }
.crx-pagination__meta { font-size: 0.84rem; color: #7f6973; }
</style>

<div class="crx-wrap">
  <div class="crx-header">
    <h1 class="crx-title">💳 Credit Report</h1>
    <div class="crx-quick-filters">
      <a class="crx-btn <?= ($period === 'all') ? 'active' : '' ?>" href="?period=all">All</a>
      <a class="crx-btn <?= ($period === 'recent') ? 'active' : '' ?>" href="?period=recent">This Week</a>
      <a class="crx-btn <?= ($period === 'month') ? 'active' : '' ?>" href="?period=month">This Month</a>
    </div>
  </div>

  <div class="crx-filters">
    <form method="GET" style="display: contents;">
      <div class="crx-filter-row">
        <label>
          Time Period
          <select name="period" onchange="this.form.submit()">
            <option value="all" <?= ($period === 'all') ? 'selected' : '' ?>>All Time</option>
            <option value="recent" <?= ($period === 'recent') ? 'selected' : '' ?>>This Week</option>
            <option value="month" <?= ($period === 'month') ? 'selected' : '' ?>>This Month</option>
          </select>
        </label>
        <label>
          Aging Analysis
          <select name="aging" onchange="this.form.submit()">
            <option value="all" <?= ($agingFilter === 'all') ? 'selected' : '' ?>>All</option>
            <option value="due_0_30" <?= ($agingFilter === 'due_0_30') ? 'selected' : '' ?>>Due 0-30 Days</option>
            <option value="due_30_60" <?= ($agingFilter === 'due_30_60') ? 'selected' : '' ?>>Due 30-60 Days</option>
            <option value="overdue_60" <?= ($agingFilter === 'overdue_60') ? 'selected' : '' ?>>Overdue 60+ Days</option>
          </select>
        </label>
        <label>
          Sort By
          <select name="sort" onchange="this.form.submit()">
            <option value="date_desc" <?= ($sortBy === 'date_desc') ? 'selected' : '' ?>>Newest First</option>
            <option value="date_asc" <?= ($sortBy === 'date_asc') ? 'selected' : '' ?>>Oldest First</option>
            <option value="amount_high" <?= ($sortBy === 'amount_high') ? 'selected' : '' ?>>Highest Amount</option>
            <option value="amount_low" <?= ($sortBy === 'amount_low') ? 'selected' : '' ?>>Lowest Amount</option>
          </select>
        </label>
        <label>
          Per Page
          <select name="per_page" onchange="this.form.submit()">
            <option value="20" <?= ($perPage === 20) ? 'selected' : '' ?>>20 Items</option>
            <option value="50" <?= ($perPage === 50) ? 'selected' : '' ?>>50 Items</option>
            <option value="100" <?= ($perPage === 100) ? 'selected' : '' ?>>100 Items</option>
          </select>
        </label>
      </div>
    </form>
  </div>

  <div class="crx-summary">
    <div class="crx-card">
      <div class="crx-card__label">Total Credit Orders</div>
      <div class="crx-card__value"><?= number_format($stats['total_orders'] ?? 0) ?></div>
      <div class="crx-card__meta">Pending</div>
    </div>
    <div class="crx-card">
      <div class="crx-card__label">Total Outstanding</div>
      <div class="crx-card__value">₹<?= number_format($totalOutstanding, 2) ?></div>
      <div class="crx-card__meta">Grand Total</div>
    </div>
    <div class="crx-card">
      <div class="crx-card__label">Due 0-30 Days</div>
      <div class="crx-card__value">₹<?= number_format($stats['due_0_30'] ?? 0, 2) ?></div>
      <div class="crx-card__meta">Current</div>
    </div>
    <div class="crx-card">
      <div class="crx-card__label">Due 30-60 Days</div>
      <div class="crx-card__value">₹<?= number_format($stats['due_30_60'] ?? 0, 2) ?></div>
      <div class="crx-card__meta">Aging</div>
    </div>
    <div class="crx-card crx-card--overdue">
      <div class="crx-card__label">Overdue 60+ Days</div>
      <div class="crx-card__value" style="color: #991b1b;">₹<?= number_format($stats['overdue_60'] ?? 0, 2) ?></div>
      <div class="crx-card__meta">Critical</div>
    </div>
  </div>

  <h2 style="color: #80001F; font-family: 'DM Serif Display', serif; margin-top: 24px; margin-bottom: 16px;">Outstanding Credit Orders</h2>
  <div class="crx-list">
    <?php while ($row = $result->fetch_assoc()): ?>
      <?php
        $daysOld = (int)($row['days_pending'] ?? 0);
        if ($daysOld <= 30) {
            $agingClass = 'crx-aging--0-30';
            $agingLabel = '0-30 Days';
        } elseif ($daysOld <= 60) {
            $agingClass = 'crx-aging--30-60';
            $agingLabel = '30-60 Days';
        } else {
            $agingClass = 'crx-aging--60+';
            $agingLabel = '60+ Days Overdue';
        }
      ?>
      <div class="crx-card-item">
        <div class="crx-card-item__top">
          <div>
            <span class="crx-order-id"><?= htmlspecialchars($row['order_id'] ?? '#N/A') ?></span>
            <span class="crx-aging <?= $agingClass ?>"><?= $agingLabel ?></span>
          </div>
          <div class="crx-customer">
            <div class="crx-customer-name"><?= htmlspecialchars($row['customer_name'] ?? 'Unknown') ?></div>
            <div class="crx-customer-phone">📞 <?= htmlspecialchars($row['customer_phone'] ?? 'N/A') ?></div>
          </div>
          <div class="crx-amount">
            <div class="crx-amount-value">₹<?= number_format($row['grand_total'] ?? 0, 2) ?></div>
            <div class="crx-amount-meta"><?= date('d M, Y', strtotime($row['created_at'])) ?></div>
          </div>
        </div>

        <div class="crx-card-item__actions">
          <a class="crx-btn-sm" href="order_details.php?id=<?= $row['id'] ?>">View Order</a>
          <?php if ($canOrderCredit = admin_has_permission('order_credit')): ?>
            <form method="POST" action="collect_credit.php" style="display: inline;">
              <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
              <input type="hidden" name="redirect_to" value="order_details.php">
              <select name="collected_payment_method" style="border:1px solid #d9c3cc;border-radius:6px;padding:5px 7px;margin-right:6px;">
                <option value="cod">Cash</option>
                <option value="upi_manual">UPI / Bank</option>
              </select>
              <button class="crx-btn-sm" type="submit" onclick="return confirm('Mark as collected?')">✓ Mark Collected</button>
            </form>
          <?php endif; ?>
          <?php if (!empty($row['collector_name'])): ?>
            <span style="font-size: 0.75rem; color: #8f7681; padding: 6px 0;">Collected by: <strong><?= htmlspecialchars($row['collector_name']) ?></strong></span>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <div class="crx-pagination">
    <span class="crx-pagination__meta"><?= number_format($totalRows) ?> total credit orders</span>
    <span class="crx-pagination__meta" style="margin-left: auto;">Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page > 1): ?>
      <a class="crx-btn-sm" href="?page=<?= $page - 1 ?>&per_page=<?= $perPage ?>&period=<?= $period ?>&aging=<?= $agingFilter ?>&sort=<?= $sortBy ?>">← Previous</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a class="crx-btn-sm" href="?page=<?= $page + 1 ?>&per_page=<?= $perPage ?>&period=<?= $period ?>&aging=<?= $agingFilter ?>&sort=<?= $sortBy ?>">Next →</a>
    <?php endif; ?>
  </div>
</div>

<div class="cr-shell">

  <?php if ($flashCollected > 0): ?>
    <div class="cr-flash">Credit payment collected for order #<?php echo (int)$flashCollected; ?>. ✔</div>
  <?php endif; ?>

  <div class="cr-shell__head">
    <h3>💳 Credit Sales Report</h3>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <span style="font-size:.8rem; color:#9d174d;"><?php echo (int)$totalRows; ?> pending credit orders</span>
      <a class="btn btn--outline btn--sm" href="orders.php">← Back to Orders</a>
    </div>
  </div>

  <div class="cr-summary">
    <div class="cr-summary__item">Total Outstanding: <strong>₹<?php echo number_format($totalOutstanding, 2); ?></strong></div>
    <div class="cr-summary__item">Pending Credit Orders: <strong><?php echo (int)$totalRows; ?></strong></div>
  </div>

  <?php if ($totalRows === 0): ?>
    <div style="padding:40px 18px; text-align:center; color:#9c8590; font-size:.9rem;">No credit orders pending. All clear! 🎉</div>
  <?php else: ?>

  <div class="cr-list">
  <?php
  $statusLabels = array(
    'pending'        => 'Pending',
    'confirmed'      => 'Confirmed',
    'in_preparation' => 'Order Ready',
    'completed'      => 'Delivered',
    'cancelled'      => 'Rejected',
  );
  while ($row = $result->fetch_assoc()):
    $oid     = (int)$row['id'];
    $ostatus = (string)($row['order_status'] ?? 'pending');
  ?>
  <div class="cr-card">
    <div class="cr-card__top">
      <span class="cr-card__id">#<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?></span>
      <div class="cr-card__cust">
        <div class="cr-card__name"><?php echo htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="cr-card__phone"><?php echo htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
      <div class="cr-card__right">
        <span class="cr-card__price">₹<?php echo htmlspecialchars((string)$row['grand_total'], ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="cr-badge cr-badge--<?php echo htmlspecialchars($ostatus, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($statusLabels[$ostatus] ?? $ostatus, ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <span class="cr-card__date"><?php echo htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    </div>

    <div class="cr-card__actions">
      <!-- Collect payment -->
      <?php if ($canOrderCredit): ?>
      <div class="cr-collect-box">
        <form method="POST" action="collect_credit.php" onsubmit="return confirm('Mark this credit as collected?')">
          <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
          <input type="hidden" name="redirect_to" value="credit_report.php">
          <label>
            Collect via
            <select name="collected_payment_method">
              <option value="cod">Cash</option>
              <option value="upi_manual">UPI / Bank</option>
            </select>
          </label>
          <button type="submit" class="btn btn--pink btn--sm" style="margin-top:4px;">💳 Collect Now</button>
        </form>
      </div>
      <?php endif; ?>
      <a href="order_details.php?id=<?php echo $oid; ?>" class="btn btn--grey btn--sm">View Order</a>
      <span class="btn btn--black btn--sm" style="opacity:.45; background:#9ca3af; border-color:#9ca3af; cursor:not-allowed; pointer-events:none;" title="Invoice unlocks only after payment is confirmed.">Invoice</span>
    </div>
  </div>
  <?php endwhile; ?>
  </div><!-- /.cr-list -->

  <div class="cr-pagination">
    <span class="cr-pagination__meta">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?> &nbsp;·&nbsp; <?php echo (int)$totalRows; ?> orders</span>
    <?php if ($page > 1): ?>
      <a class="btn btn--sm btn--outline" href="credit_report.php?<?php echo http_build_query(array('per_page' => $perPage, 'page' => $page - 1)); ?>">← Previous</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a class="btn btn--sm" href="credit_report.php?<?php echo http_build_query(array('per_page' => $perPage, 'page' => $page + 1)); ?>">Next →</a>
    <?php endif; ?>
  </div>

  <?php endif; ?>

</div><!-- /.cr-shell -->
</div>
</div>
</body>
</html>
