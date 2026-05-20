<?php
$pageTitle = 'Bank Report';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('revenue_report');

$targetParams = [
  'payment_method' => 'bank',
  'view' => 'collection',
  'from_date' => $_GET['from_date'] ?? '',
  'to_date' => $_GET['to_date'] ?? '',
  'payment_status' => $_GET['status'] ?? 'all',
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

$whereConditions = array("o.payment_method IN ('upi_manual', 'gateway')");
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
        SUM(o.grand_total) as total_bank,
        SUM(CASE WHEN o.payment_status = 'paid' THEN o.grand_total ELSE 0 END) as deposited,
        SUM(CASE WHEN o.payment_status = 'pending' THEN o.grand_total ELSE 0 END) as pending_deposit,
        MIN(o.created_at) as earliest_date,
        MAX(o.created_at) as latest_date
    FROM orders o
    WHERE $where
";

$totals = $conn->query($totalQuery)->fetch_assoc();

$dailyQuery = "
    SELECT 
        DATE(o.created_at) as date,
        COUNT(*) as orders,
        SUM(o.grand_total) as amount,
        SUM(CASE WHEN o.payment_status = 'paid' THEN o.grand_total ELSE 0 END) as deposited,
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
.br-wrap { max-width: 1400px; margin: 0 auto; padding: 20px; }
.br-header { display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start; margin-bottom: 24px; }
.br-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 2.2rem; font-weight: 400; color: #80001F; margin: 0; }
.br-quick-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.br-btn { background: #fff; border: 1px solid #ddd; padding: 8px 14px; border-radius: 10px; cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: all 150ms; text-decoration: none; display: inline-block; color: #333; }
.br-btn:hover, .br-btn.active { background: #80001F; color: #fff; border-color: #80001F; }
.br-filters { background: #fff; border: 1px solid rgba(128,0,31,.1); border-radius: 16px; padding: 16px; margin-bottom: 20px; display: grid; gap: 12px; }
.br-filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.br-filter-row label { display: grid; gap: 4px; font-size: 0.75rem; font-weight: 700; color: #80001F; text-transform: uppercase; }
.br-filter-row input, .br-filter-row select { border: 1px solid #ddd; border-radius: 8px; padding: 8px; font-size: 0.85rem; }
.br-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
.br-card { background: linear-gradient(135deg, #fff8fa 0%, #fff 100%); border: 1px solid rgba(128,0,31,.1); border-radius: 12px; padding: 16px; }
.br-card__label { font-size: 0.75rem; color: #8f7681; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
.br-card__value { font-size: 1.8rem; font-weight: 700; color: #80001F; margin: 6px 0 0; }
.br-card__meta { font-size: 0.75rem; color: #8f7681; margin-top: 8px; }
.br-table-wrap { background: #fff; border: 1px solid rgba(128,0,31,.1); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
.br-table { width: 100%; border-collapse: collapse; }
.br-table thead { background: linear-gradient(180deg, #fff8fa 0%, #f9f6f8 100%); }
.br-table th { padding: 12px 14px; text-align: left; font-size: 0.75rem; font-weight: 700; color: #80001F; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid rgba(128,0,31,.1); }
.br-table td { padding: 11px 14px; border-bottom: 1px solid rgba(128,0,31,.06); font-size: 0.85rem; }
.br-table tbody tr:hover { background: #fff9fb; }
.br-table .br-total { background: linear-gradient(90deg, #f0e8eb 0%, #faf8f9 100%); font-weight: 700; color: #80001F; border-top: 2px solid #80001F; font-size: 0.9rem; }
.br-badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
.br-badge--paid { background: #dcfce7; color: #166534; }
.br-badge--pending { background: #fef3c7; color: #92400e; }
.br-badge--failed { background: #fecdd3; color: #991b1b; }
.br-exports { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 20px; }
.br-btn-export { background: #80001F; color: #fff; border: none; padding: 10px 16px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.8rem; }
.br-btn-export:hover { background: #5f0017; }
@media (max-width: 768px) {
  .br-header { grid-template-columns: 1fr; }
  .br-filter-row { grid-template-columns: 1fr; }
  .br-table { font-size: 0.75rem; }
  .br-table th, .br-table td { padding: 8px 10px; }
}
</style>

<div class="br-wrap">
  <div class="br-header">
    <h1 class="br-title">🏦 Bank Report</h1>
    <div class="br-quick-filters">
      <a class="br-btn <?= ($period === 'today') ? 'active' : '' ?>" href="?period=today">Today</a>
      <a class="br-btn <?= ($period === 'week') ? 'active' : '' ?>" href="?period=week">This Week</a>
      <a class="br-btn <?= ($period === 'month') ? 'active' : '' ?>" href="?period=month">This Month</a>
      <a class="br-btn <?= ($period === 'year') ? 'active' : '' ?>" href="?period=year">This Year</a>
    </div>
  </div>

  <div class="br-filters">
    <form method="GET" style="display: contents;">
      <div class="br-filter-row">
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
          Payment Status
          <select name="status" onchange="this.form.submit()">
            <option value="all">All Status</option>
            <option value="paid" <?= ($statusFilter === 'paid') ? 'selected' : '' ?>>Deposited</option>
            <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending Deposit</option>
            <option value="failed" <?= ($statusFilter === 'failed') ? 'selected' : '' ?>>Failed</option>
          </select>
        </label>
      </div>
    </form>
  </div>

  <div class="br-summary">
    <div class="br-card">
      <div class="br-card__label">Total UPI/Bank Orders</div>
      <div class="br-card__value"><?= number_format($totals['total_orders'] ?? 0) ?></div>
      <div class="br-card__meta"><?= $fromDate ?> to <?= $toDate ?></div>
    </div>
    <div class="br-card">
      <div class="br-card__label">Total Amount</div>
      <div class="br-card__value">₹<?= number_format($totals['total_bank'] ?? 0, 2) ?></div>
      <div class="br-card__meta">In Transaction</div>
    </div>
    <div class="br-card" style="background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%); border-color: #22c55e;">
      <div class="br-card__label">Deposited</div>
      <div class="br-card__value" style="color: #166534;">₹<?= number_format($totals['deposited'] ?? 0, 2) ?></div>
      <div class="br-card__meta">Confirmed</div>
    </div>
    <div class="br-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border-color: #f59e0b;">
      <div class="br-card__label">Pending Deposit</div>
      <div class="br-card__value" style="color: #92400e;">₹<?= number_format($totals['pending_deposit'] ?? 0, 2) ?></div>
      <div class="br-card__meta">In Process</div>
    </div>
    <div class="br-card">
      <div class="br-card__label">Reconciliation Rate</div>
      <div class="br-card__value"><?= number_format(($totals['deposited'] / max(1, $totals['total_bank'])) * 100, 1) ?>%</div>
      <div class="br-card__meta">Success Rate</div>
    </div>
  </div>

  <div class="br-exports">
    <button class="br-btn-export" onclick="window.print()">🖨️ Print</button>
    <button class="br-btn-export" onclick="exportToCSV()">📥 Export CSV</button>
  </div>

  <h2 style="color: #80001F; font-family: 'DM Serif Display', serif; margin-top: 32px; margin-bottom: 16px;">Payment Status Breakdown</h2>
  <div class="br-table-wrap">
    <table class="br-table">
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
            <span class="br-badge br-badge--<?= strtolower($row['payment_status']) ?>">
              <?= ucfirst($row['payment_status']) ?>
            </span>
          </td>
          <td style="text-align: right;"><?= $row['count'] ?></td>
          <td style="text-align: right; font-weight: 600;">₹<?= number_format($row['amount'] ?? 0, 2) ?></td>
          <td style="text-align: right;"><?= number_format(($row['amount'] / max(1, $totals['total_bank'])) * 100, 1) ?>%</td>
        </tr>
        <?php endwhile; ?>
        <tr class="br-total">
          <td colspan="2" style="text-align: right;">TOTAL</td>
          <td style="text-align: right;">₹<?= number_format($totals['total_bank'] ?? 0, 2) ?></td>
          <td style="text-align: right;">100%</td>
        </tr>
      </tbody>
    </table>
  </div>

  <h2 style="color: #80001F; font-family: 'DM Serif Display', serif; margin-top: 32px; margin-bottom: 16px;">Daily Bank Deposits</h2>
  <div class="br-table-wrap">
    <table class="br-table">
      <thead>
        <tr>
          <th>Date</th>
          <th style="text-align: right;">Orders</th>
          <th style="text-align: right;">Total Amount</th>
          <th style="text-align: right;">Deposited</th>
          <th style="text-align: right;">Pending</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $daily->fetch_assoc()): ?>
        <tr>
          <td><strong><?= date('d M, Y', strtotime($row['date'])) ?></strong></td>
          <td style="text-align: right;"><?= $row['orders'] ?></td>
          <td style="text-align: right;">₹<?= number_format($row['amount'] ?? 0, 2) ?></td>
          <td style="text-align: right; color: #22c55e; font-weight: 600;">₹<?= number_format($row['deposited'] ?? 0, 2) ?></td>
          <td style="text-align: right; color: #f59e0b; font-weight: 600;">₹<?= number_format($row['pending'] ?? 0, 2) ?></td>
        </tr>
        <?php endwhile; ?>
        <tr class="br-total">
          <td colspan="2" style="text-align: right;">TOTAL</td>
          <td style="text-align: right;">₹<?= number_format($totals['total_bank'] ?? 0, 2) ?></td>
          <td style="text-align: right;">₹<?= number_format($totals['deposited'] ?? 0, 2) ?></td>
          <td style="text-align: right;">₹<?= number_format($totals['pending_deposit'] ?? 0, 2) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

</div>

<script>
function exportToCSV() {
  const table = document.querySelector('.br-table');
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
  link.setAttribute("download", "bank_report_<?= date('Y-m-d') ?>.csv");
  link.click();
}
</script>
