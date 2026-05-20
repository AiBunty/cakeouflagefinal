<?php
$pageTitle = 'Sales Report';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('revenue_report');

$targetParams = [
  'view' => 'sales',
  'from_date' => $_GET['from_date'] ?? '',
  'to_date' => $_GET['to_date'] ?? '',
  'payment_method' => $_GET['payment_mode'] ?? 'all',
  'order_status' => $_GET['status'] ?? 'all',
];
$targetParams = array_filter($targetParams, static fn($v) => $v !== '' && $v !== null);
header('Location: sales_register.php' . (!empty($targetParams) ? ('?' . http_build_query($targetParams)) : ''));
exit;

$period = $_GET['period'] ?? 'month';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$productFilter = $_GET['product_id'] ?? '';
$paymentMode = $_GET['payment_mode'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$viewType = $_GET['view'] ?? 'summary';

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
        case 'quarter':
            $quarter = (int)ceil(date('n') / 3);
            $month = (($quarter - 1) * 3) + 1;
            $fromDate = date('Y-m-d', mktime(0, 0, 0, $month, 1, date('Y')));
            $toDate = $today;
            break;
        case 'year':
            $fromDate = date('Y-01-01');
            $toDate = $today;
            break;
    }
}

applyDateRange($fromDate, $toDate, $period);

$whereConditions = array('1=1');
if ($fromDate) {
    $whereConditions[] = "DATE(o.created_at) >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate) {
    $whereConditions[] = "DATE(o.created_at) <= '" . $conn->real_escape_string($toDate) . "'";
}
if ($productFilter) {
    $whereConditions[] = "oi.product_id = " . (int)$productFilter;
}
if ($paymentMode !== 'all') {
    $whereConditions[] = "o.payment_method = '" . $conn->real_escape_string($paymentMode) . "'";
}
if ($statusFilter !== 'all') {
    $whereConditions[] = "o.status = '" . $conn->real_escape_string($statusFilter) . "'";
}

$where = implode(' AND ', $whereConditions);

$totalsQuery = "
    SELECT 
        COUNT(DISTINCT o.id) as order_count,
        SUM(oi.quantity) as total_qty,
        SUM(oi.unit_price * oi.quantity) as subtotal,
        SUM(o.tax_amount) as tax_total,
        SUM(o.delivery_charge) as delivery_total,
        SUM(o.grand_total) as grand_total
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE $where
";

$totals = $conn->query($totalsQuery)->fetch_assoc();

$productSalesQuery = "
    SELECT 
        p.id, p.name,
        COUNT(DISTINCT o.id) as orders,
        SUM(oi.quantity) as qty,
        SUM(oi.unit_price * oi.quantity) as amount,
        AVG(oi.unit_price) as avg_price
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    LEFT JOIN orders o ON o.id = oi.order_id
    WHERE $where
    GROUP BY p.id, p.name
    ORDER BY amount DESC
";
$productSales = $conn->query($productSalesQuery);

$paymentSalesQuery = "
    SELECT 
        CASE 
            WHEN o.payment_method = 'cod' THEN 'Cash'
            WHEN o.payment_method IN ('upi_manual', 'gateway') THEN 'UPI / Bank'
            ELSE o.payment_method
        END as mode,
        COUNT(*) as orders,
        SUM(o.grand_total) as amount
    FROM orders o
    WHERE $where
    GROUP BY o.payment_method
    ORDER BY amount DESC
";
$paymentSales = $conn->query($paymentSalesQuery);

$statusSalesQuery = "
    SELECT 
        o.status,
        COUNT(*) as count,
        SUM(o.grand_total) as amount
    FROM orders o
    WHERE $where
    GROUP BY o.status
    ORDER BY amount DESC
";
$statusSales = $conn->query($statusSalesQuery);
?>

<style>
.sr-wrap { max-width: 1400px; margin: 0 auto; padding: 20px; }
.sr-header { display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start; margin-bottom: 24px; }
.sr-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 2.2rem; font-weight: 400; color: #80001F; margin: 0; }
.sr-quick-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.sr-btn { background: #fff; border: 1px solid #ddd; padding: 8px 14px; border-radius: 10px; cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: all 150ms; text-decoration: none; display: inline-block; color: #333; }
.sr-btn:hover, .sr-btn.active { background: #80001F; color: #fff; border-color: #80001F; }
.sr-filters { background: #fff; border: 1px solid rgba(128,0,31,.1); border-radius: 16px; padding: 16px; margin-bottom: 20px; display: grid; gap: 12px; }
.sr-filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.sr-filter-row label { display: grid; gap: 4px; font-size: 0.75rem; font-weight: 700; color: #80001F; text-transform: uppercase; }
.sr-filter-row input, .sr-filter-row select { border: 1px solid #ddd; border-radius: 8px; padding: 8px; font-size: 0.85rem; }
.sr-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
.sr-card { background: linear-gradient(135deg, #fff8fa 0%, #fff 100%); border: 1px solid rgba(128,0,31,.1); border-radius: 12px; padding: 16px; }
.sr-card__label { font-size: 0.75rem; color: #8f7681; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
.sr-card__value { font-size: 1.8rem; font-weight: 700; color: #80001F; margin: 6px 0 0; }
.sr-card__meta { font-size: 0.75rem; color: #8f7681; margin-top: 8px; }
.sr-table-wrap { background: #fff; border: 1px solid rgba(128,0,31,.1); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
.sr-table { width: 100%; border-collapse: collapse; }
.sr-table thead { background: linear-gradient(180deg, #fff8fa 0%, #f9f6f8 100%); }
.sr-table th { padding: 12px 14px; text-align: left; font-size: 0.75rem; font-weight: 700; color: #80001F; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid rgba(128,0,31,.1); }
.sr-table td { padding: 11px 14px; border-bottom: 1px solid rgba(128,0,31,.06); font-size: 0.85rem; }
.sr-table tbody tr:hover { background: #fff9fb; }
.sr-table .sr-subtotal { background: #f9f6f8; font-weight: 600; color: #80001F; border-top: 2px solid rgba(128,0,31,.2); }
.sr-table .sr-total { background: linear-gradient(90deg, #f0e8eb 0%, #faf8f9 100%); font-weight: 700; color: #80001F; border-top: 2px solid #80001F; font-size: 0.9rem; }
.sr-exports { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 20px; }
.sr-btn-export { background: #80001F; color: #fff; border: none; padding: 10px 16px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.8rem; }
.sr-btn-export:hover { background: #5f0017; }
@media (max-width: 768px) {
  .sr-header { grid-template-columns: 1fr; }
  .sr-filter-row { grid-template-columns: 1fr; }
  .sr-table { font-size: 0.75rem; }
  .sr-table th, .sr-table td { padding: 8px 10px; }
}
</style>

<div class="sr-wrap">
  <div class="sr-header">
    <h1 class="sr-title">📊 Sales Report</h1>
    <div class="sr-quick-filters">
      <a class="sr-btn <?= ($period === 'today') ? 'active' : '' ?>" href="?period=today">Today</a>
      <a class="sr-btn <?= ($period === 'week') ? 'active' : '' ?>" href="?period=week">This Week</a>
      <a class="sr-btn <?= ($period === 'month') ? 'active' : '' ?>" href="?period=month">This Month</a>
      <a class="sr-btn <?= ($period === 'year') ? 'active' : '' ?>" href="?period=year">This Year</a>
      <a class="sr-btn <?= ($period === 'custom') ? 'active' : '' ?>" href="?period=custom">Custom</a>
    </div>
  </div>

  <div class="sr-filters">
    <form method="GET" style="display: contents;">
      <input type="hidden" name="view" value="<?= htmlspecialchars($viewType) ?>">
      <div class="sr-filter-row">
        <label>
          Period
          <select name="period" onchange="this.form.submit()">
            <option value="today" <?= ($period === 'today') ? 'selected' : '' ?>>Today</option>
            <option value="week" <?= ($period === 'week') ? 'selected' : '' ?>>This Week</option>
            <option value="month" <?= ($period === 'month') ? 'selected' : '' ?>>This Month</option>
            <option value="quarter" <?= ($period === 'quarter') ? 'selected' : '' ?>>This Quarter</option>
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
      </div>

      <div class="sr-filter-row">
        <label>
          Payment Mode
          <select name="payment_mode" onchange="this.form.submit()">
            <option value="all">All Methods</option>
            <option value="cod" <?= ($paymentMode === 'cod') ? 'selected' : '' ?>>Cash</option>
            <option value="upi_manual" <?= ($paymentMode === 'upi_manual') ? 'selected' : '' ?>>UPI / Bank</option>
          </select>
        </label>
        <label>
          Order Status
          <select name="status" onchange="this.form.submit()">
            <option value="all">All Status</option>
            <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending</option>
            <option value="confirmed" <?= ($statusFilter === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
            <option value="in_preparation" <?= ($statusFilter === 'in_preparation') ? 'selected' : '' ?>>Order Ready</option>
            <option value="completed" <?= ($statusFilter === 'completed') ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= ($statusFilter === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </label>
      </div>
    </form>
  </div>

  <div class="sr-summary">
    <div class="sr-card">
      <div class="sr-card__label">Total Orders</div>
      <div class="sr-card__value"><?= number_format($totals['order_count'] ?? 0) ?></div>
      <div class="sr-card__meta"><?= $fromDate ?> to <?= $toDate ?></div>
    </div>
    <div class="sr-card">
      <div class="sr-card__label">Total Items Sold</div>
      <div class="sr-card__value"><?= number_format($totals['total_qty'] ?? 0) ?></div>
      <div class="sr-card__meta">Units</div>
    </div>
    <div class="sr-card">
      <div class="sr-card__label">Revenue (Gross)</div>
      <div class="sr-card__value">₹<?= number_format($totals['subtotal'] ?? 0, 2) ?></div>
      <div class="sr-card__meta">Subtotal</div>
    </div>
    <div class="sr-card">
      <div class="sr-card__label">Total Tax</div>
      <div class="sr-card__value">₹<?= number_format($totals['tax_total'] ?? 0, 2) ?></div>
      <div class="sr-card__meta">Collected</div>
    </div>
    <div class="sr-card">
      <div class="sr-card__label">Delivery Charges</div>
      <div class="sr-card__value">₹<?= number_format($totals['delivery_total'] ?? 0, 2) ?></div>
      <div class="sr-card__meta">Net</div>
    </div>
    <div class="sr-card" style="background: linear-gradient(135deg, #fff8fa 0%, #fef0f5 100%); border-color: #80001F; box-shadow: 0 4px 12px rgba(128,0,31,.12);">
      <div class="sr-card__label">Net Collection</div>
      <div class="sr-card__value" style="color: #c41e3a;">₹<?= number_format($totals['grand_total'] ?? 0, 2) ?></div>
      <div class="sr-card__meta">Grand Total</div>
    </div>
  </div>

  <div class="sr-exports">
    <button class="sr-btn-export" onclick="window.print()">🖨️ Print</button>
    <button class="sr-btn-export" onclick="exportToCSV()">📥 Export CSV</button>
  </div>

  <h2 style="color: #80001F; font-family: 'DM Serif Display', serif; margin-top: 32px; margin-bottom: 16px;">Product-Wise Sales</h2>
  <div class="sr-table-wrap">
    <table class="sr-table">
      <thead>
        <tr>
          <th>Product</th>
          <th style="text-align: right;">Orders</th>
          <th style="text-align: right;">Qty</th>
          <th style="text-align: right;">Avg Price</th>
          <th style="text-align: right;">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $productSales->fetch_assoc()): ?>
        <tr>
          <td><strong><?= htmlspecialchars($row['name'] ?? 'Unknown') ?></strong></td>
          <td style="text-align: right;"><?= $row['orders'] ?></td>
          <td style="text-align: right;"><?= $row['qty'] ?></td>
          <td style="text-align: right;">₹<?= number_format($row['avg_price'] ?? 0, 2) ?></td>
          <td style="text-align: right; font-weight: 600;">₹<?= number_format($row['amount'] ?? 0, 2) ?></td>
        </tr>
        <?php endwhile; ?>
        <tr class="sr-total">
          <td colspan="4" style="text-align: right;">TOTAL</td>
          <td style="text-align: right;">₹<?= number_format($totals['subtotal'] ?? 0, 2) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <h2 style="color: #80001F; font-family: 'DM Serif Display', serif; margin-top: 32px; margin-bottom: 16px;">Payment Mode Breakdown</h2>
  <div class="sr-table-wrap">
    <table class="sr-table">
      <thead>
        <tr>
          <th>Payment Mode</th>
          <th style="text-align: right;">Orders</th>
          <th style="text-align: right;">Amount</th>
          <th style="text-align: right;">% of Total</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $paymentSales->fetch_assoc()): ?>
        <tr>
          <td><strong><?= htmlspecialchars($row['mode']) ?></strong></td>
          <td style="text-align: right;"><?= $row['orders'] ?></td>
          <td style="text-align: right; font-weight: 600;">₹<?= number_format($row['amount'] ?? 0, 2) ?></td>
          <td style="text-align: right;"><?= number_format(($row['amount'] / max(1, $totals['grand_total'])) * 100, 1) ?>%</td>
        </tr>
        <?php endwhile; ?>
        <tr class="sr-total">
          <td colspan="2" style="text-align: right;">TOTAL</td>
          <td style="text-align: right;">₹<?= number_format($totals['grand_total'] ?? 0, 2) ?></td>
          <td style="text-align: right;">100%</td>
        </tr>
      </tbody>
    </table>
  </div>

  <h2 style="color: #80001F; font-family: 'DM Serif Display', serif; margin-top: 32px; margin-bottom: 16px;">Order Status Distribution</h2>
  <div class="sr-table-wrap">
    <table class="sr-table">
      <thead>
        <tr>
          <th>Status</th>
          <th style="text-align: right;">Count</th>
          <th style="text-align: right;">Amount</th>
          <th style="text-align: right;">% of Total</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $statusSales->fetch_assoc()): ?>
        <tr>
          <td><strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status']))) ?></strong></td>
          <td style="text-align: right;"><?= $row['count'] ?></td>
          <td style="text-align: right; font-weight: 600;">₹<?= number_format($row['amount'] ?? 0, 2) ?></td>
          <td style="text-align: right;"><?= number_format(($row['amount'] / max(1, $totals['grand_total'])) * 100, 1) ?>%</td>
        </tr>
        <?php endwhile; ?>
        <tr class="sr-total">
          <td colspan="2" style="text-align: right;">TOTAL</td>
          <td style="text-align: right;">₹<?= number_format($totals['grand_total'] ?? 0, 2) ?></td>
          <td style="text-align: right;">100%</td>
        </tr>
      </tbody>
    </table>
  </div>

</div>

<script>
function exportToCSV() {
  const table = document.querySelector('.sr-table');
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
  link.setAttribute("download", "sales_report_<?= date('Y-m-d') ?>.csv");
  link.click();
}
</script>
