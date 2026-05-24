<?php
$pageTitle = 'Cash Report';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('revenue_report');

$targetParams = [
  'payment_method' => 'cod',
  'view' => 'collection',
  'from_date' => $_GET['from_date'] ?? '',
  'to_date' => $_GET['to_date'] ?? '',
  'payment_status' => $_GET['status'] ?? 'confirmed_only',
];
$targetParams = array_filter($targetParams, static fn($v) => $v !== '' && $v !== null);
header('Location: sales_register.php?' . http_build_query($targetParams));
exit;

$period = $_GET['period'] ?? 'month';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';

function applyDateRange(&$fromDate, &$toDate, $period) {
    $today = date('Y-m-d');
    switch ($period) {
        case 'today':
            $fromDate = $toDate = $today;
            break;
        case 'week':
            $fromDate = date('Y-m-d', strtotime('monday this week'));
            $toDate = $today;
            break;
        case 'month':
            $fromDate = date('Y-m-01');
            $toDate = $today;
            break;
        case 'year':
            $fromDate = date('Y-01-01');
            $toDate = $today;
            break;
    }
}

applyDateRange($fromDate, $toDate, $period);

$whereConditions = array("o.payment_method = 'cod'");
if ($fromDate) {
    $whereConditions[] = "DATE(o.created_at) >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate) {
    $whereConditions[] = "DATE(o.created_at) <= '" . $conn->real_escape_string($toDate) . "'";
}
if ($statusFilter !== 'all') {
    $whereConditions[] = "o.payment_status = '" . $conn->real_escape_string($statusFilter) . "'";
}

$where = implode(' AND ', $whereConditions);

$totalQuery = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(o.grand_total) as total_cash,
        SUM(CASE WHEN o.payment_status = 'paid' THEN o.grand_total ELSE 0 END) as collected,
        SUM(CASE WHEN o.payment_status = 'pending' THEN o.grand_total ELSE 0 END) as pending,
        SUM(CASE WHEN o.payment_status = 'credit' THEN o.grand_total ELSE 0 END) as credit
    FROM orders o
    WHERE $where
";

$totals = $conn->query($totalQuery)->fetch_assoc();

$dailyQuery = "
    SELECT 
        DATE(o.created_at) as date,
        COUNT(*) as orders,
        SUM(o.grand_total) as amount,
        SUM(CASE WHEN o.payment_status = 'paid' THEN o.grand_total ELSE 0 END) as collected,
        SUM(CASE WHEN o.payment_status = 'pending' THEN o.grand_total ELSE 0 END) as pending
    FROM orders o
    WHERE $where
    GROUP BY DATE(o.created_at)
    ORDER BY DATE(o.created_at) DESC
    LIMIT 100
";
$daily = $conn->query($dailyQuery);

$statusBreakQuery = "
    SELECT 
        o.payment_status,
        COUNT(*) as count,
        SUM(o.grand_total) as amount
    FROM orders o
    WHERE $where
    GROUP BY o.payment_status
    ORDER BY amount DESC
";
$statusBreak = $conn->query($statusBreakQuery);
?>

<style>
.cr-wrap { max-width: 1400px; margin: 0 auto; padding: 20px; }
.cr-header { display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start; margin-bottom: 24px; }
.cr-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 2.2rem; font-weight: 400; color: #80001F; margin: 0; }
.cr-quick-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.cr-btn { background: #fff; border: 1px solid #ddd; padding: 8px 14px; border-radius: 10px; cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: all 150ms; text-decoration: none; display: inline-block; color: #333; }
.cr-btn:hover, .cr-btn.active { background: #80001F; color: #fff; border-color: #80001F; }
.cr-filters { background: #fff; border: 1px solid rgba(128,0,31,.1); border-radius: 16px; padding: 16px; margin-bottom: 20px; display: grid; gap: 12px; }
.cr-filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.cr-filter-row label { display: grid; gap: 4px; font-size: 0.75rem; font-weight: 700; color: #80001F; text-transform: uppercase; }
.cr-filter-row input, .cr-filter-row select { border: 1px solid #ddd; border-radius: 8px; padding: 8px; font-size: 0.85rem; }
.cr-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
.cr-card { background: linear-gradient(135deg, #fff8fa 0%, #fff 100%); border: 1px solid rgba(128,0,31,.1); border-radius: 12px; padding: 16px; }
.cr-card__label { font-size: 0.75rem; color: #8f7681; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
.cr-card__value { font-size: 1.8rem; font-weight: 700; color: #80001F; margin: 6px 0 0; }
.cr-card__meta { font-size: 0.75rem; color: #8f7681; margin-top: 8px; }
.cr-table-wrap { background: #fff; border: 1px solid rgba(128,0,31,.1); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
.cr-table { width: 100%; border-collapse: collapse; }
.cr-table thead { background: linear-gradient(180deg, #fff8fa 0%, #f9f6f8 100%); }
.cr-table th { padding: 12px 14px; text-align: left; font-size: 0.75rem; font-weight: 700; color: #80001F; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid rgba(128,0,31,.1); }
.cr-table td { padding: 11px 14px; border-bottom: 1px solid rgba(128,0,31,.06); font-size: 0.85rem; }
.cr-table tbody tr:hover { background: #fff9fb; }
.cr-table .cr-total { background: linear-gradient(90deg, #f0e8eb 0%, #faf8f9 100%); font-weight: 700; color: #80001F; border-top: 2px solid #80001F; font-size: 0.9rem; }
.cr-badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
.cr-badge--paid { background: #dcfce7; color: #166534; }
.cr-badge--pending { background: #fef3c7; color: #92400e; }
.cr-badge--credit { background: #fecdd3; color: #991b1b; }
.cr-exports { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 20px; }
.cr-btn-export { background: #80001F; color: #fff; border: none; padding: 10px 16px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.8rem; }
.cr-btn-export:hover { background: #5f0017; }
@media (max-width: 768px) {
  .cr-header { grid-template-columns: 1fr; }
  .cr-filter-row { grid-template-columns: 1fr; }
  .cr-table { font-size: 0.75rem; }
  .cr-table th, .cr-table td { padding: 8px 10px; }
}
</style>

<div class="cr-wrap">
  <div class="cr-header">
    <h1 class="cr-title">💰 Cash Report</h1>
    <div class="cr-quick-filters">
      <a class="cr-btn <?= ($period === 'today') ? 'active' : '' ?>" href="?period=today">Today</a>
      <a class="cr-btn <?= ($period === 'week') ? 'active' : '' ?>" href="?period=week">This Week</a>
      <a class="cr-btn <?= ($period === 'month') ? 'active' : '' ?>" href="?period=month">This Month</a>
      <a class="cr-btn <?= ($period === 'year') ? 'active' : '' ?>" href="?period=year">This Year</a>
    </div>
  </div>

  <div class="cr-filters">
    <form method="GET" style="display: contents;">
      <div class="cr-filter-row">
        <label>
          Period
          <select name="period" onchange="this.form.submit()">
            <option value="today" <?= ($period === 'today') ? 'selected' : '' ?>>Today</option>
            <option value="week" <?= ($period === 'week') ? 'selected' : '' ?>>This Week</option>
            <option value="month" <?= ($period === 'month') ? 'selected' : '' ?>>This Month</option>
            <option value="year" <?= ($period === 'year') ? 'selected' : '' ?>>This Year</option>
            <option value="custom" <?= ($period === 'custom') ? 'selected' : '' ?>>Custom Range</option>
          </select>
        </label>
        <?php if ($period === 'custom'): ?>
        <label>
          From Date
          <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" onchange="this.form.submit()">
        </label>
        <label>
          To Date
          <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" onchange="this.form.submit()">
        </label>
        <?php endif; ?>
        <label>
          Status Filter
          <select name="status" onchange="this.form.submit()">
            <option value="all">All Status</option>
            <option value="paid" <?= ($statusFilter === 'paid') ? 'selected' : '' ?>>Collected</option>
            <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending</option>
            <option value="credit" <?= ($statusFilter === 'credit') ? 'selected' : '' ?>>Credit</option>
          </select>
        </label>
      </div>
    </form>
  </div>

  <div class="cr-summary">
    <div class="cr-card">
      <div class="cr-card__label">Total Cash Orders</div>
      <div class="cr-card__value"><?= number_format($totals['total_orders'] ?? 0) ?></div>
      <div class="cr-card__meta"><?= $fromDate ?> to <?= $toDate ?></div>
    </div>
    <div class="cr-card">
      <div class="cr-card__label">Total Cash Due</div>
      <div class="cr-card__value">₹<?= number_format($totals['total_cash'] ?? 0, 2) ?></div>
      <div class="cr-card__meta">Invoiced</div>
    </div>
    <div class="cr-card" style="background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%); border-color: #22c55e;">
      <div class="cr-card__label">Collected</div>
      <div class="cr-card__value" style="color: #166534;">₹<?= number_format($totals['collected'] ?? 0, 2) ?></div>
      <div class="cr-card__meta">Received</div>
    </div>
    <div class="cr-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border-color: #f59e0b;">
      <div class="cr-card__label">Pending Collection</div>
      <div class="cr-card__value" style="color: #92400e;">₹<?= number_format($totals['pending'] ?? 0, 2) ?></div>
      <div class="cr-card__meta">Awaiting</div>
    </div>
    <div class="cr-card" style="background: linear-gradient(135deg, #fecdd3 0%, #fef2f2 100%); border-color: #ef4444;">
      <div class="cr-card__label">Outstanding Credit</div>
      <div class="cr-card__value" style="color: #991b1b;">₹<?= number_format($totals['credit'] ?? 0, 2) ?></div>
      <div class="cr-card__meta">Extended</div>
    </div>
  </div>

  <div class="cr-exports">
    <button class="cr-btn-export" onclick="window.print()">🖨️ Print</button>
    <button class="cr-btn-export" onclick="exportToCSV()">📥 Export CSV</button>
  </div>

  <h2 style="color: #80001F; font-family: 'DM Serif Display', serif; margin-top: 32px; margin-bottom: 16px;">Payment Status Breakdown</h2>
  <div class="cr-table-wrap">
    <table class="cr-table">
      <thead>
        <tr>
          <th>Status</th>
          <th style="text-align: right;">Orders</th>
          <th style="text-align: right;">Amount</th>
          <th style="text-align: right;">% of Total</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $statusBreak->fetch_assoc()): ?>
        <tr>
          <td>
            <span class="cr-badge cr-badge--<?= strtolower($row['payment_status']) ?>">
              <?= ucfirst($row['payment_status']) ?>
            </span>
          </td>
          <td style="text-align: right;"><?= $row['count'] ?></td>
          <td style="text-align: right; font-weight: 600;">₹<?= number_format($row['amount'] ?? 0, 2) ?></td>
          <td style="text-align: right;"><?= number_format(($row['amount'] / max(1, $totals['total_cash'])) * 100, 1) ?>%</td>
        </tr>
        <?php endwhile; ?>
        <tr class="cr-total">
          <td colspan="2" style="text-align: right;">TOTAL</td>
          <td style="text-align: right;">₹<?= number_format($totals['total_cash'] ?? 0, 2) ?></td>
          <td style="text-align: right;">100%</td>
        </tr>
      </tbody>
    </table>
  </div>

  <h2 style="color: #80001F; font-family: 'DM Serif Display', serif; margin-top: 32px; margin-bottom: 16px;">Daily Cash Received</h2>
  <div class="cr-table-wrap">
    <table class="cr-table">
      <thead>
        <tr>
          <th>Date</th>
          <th style="text-align: right;">Orders</th>
          <th style="text-align: right;">Total</th>
          <th style="text-align: right;">Collected</th>
          <th style="text-align: right;">Pending</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $daily->fetch_assoc()): ?>
        <tr>
          <td><strong><?= date('d M, Y', strtotime($row['date'])) ?></strong></td>
          <td style="text-align: right;"><?= $row['orders'] ?></td>
          <td style="text-align: right;">₹<?= number_format($row['amount'] ?? 0, 2) ?></td>
          <td style="text-align: right; color: #22c55e; font-weight: 600;">₹<?= number_format($row['collected'] ?? 0, 2) ?></td>
          <td style="text-align: right; color: #f59e0b; font-weight: 600;">₹<?= number_format($row['pending'] ?? 0, 2) ?></td>
        </tr>
        <?php endwhile; ?>
        <tr class="cr-total">
          <td colspan="2" style="text-align: right;">TOTAL</td>
          <td style="text-align: right;">₹<?= number_format($totals['total_cash'] ?? 0, 2) ?></td>
          <td style="text-align: right;">₹<?= number_format($totals['collected'] ?? 0, 2) ?></td>
          <td style="text-align: right;">₹<?= number_format($totals['pending'] ?? 0, 2) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

</div>

<script>
function exportToCSV() {
  const table = document.querySelector('.cr-table');
  let csv = [];
  for (let row of table.rows) {
    let csvRow = [];
    for (let i = 0; i < row.cells.length; i++) {
      csvRow.push('"' + row.cells[i].innerText.replace(/"/g, '""') + '"');
    }
    csv.push(csvRow.join(','));
  }
  const csvContent = "data:text/csv;charset=utf-8," + encodeURIComponent(csv.join("\n"));
  const link = document.createElement("a");
  link.setAttribute("href", csvContent);
  link.setAttribute("download", "cash_report_<?= date('Y-m-d') ?>.csv");
  link.click();
}
</script>
