<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/layout.php';

require_once __DIR__ . '/includes/db.php';

use App\Services\FinanceReportService;

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$adminName = (string)($_SESSION['admin_name'] ?? 'Admin');

function metric_value(mysqli $conn, string $sql)
{
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    if (!$row) {
        return 0;
    }
    $first = array_values($row);
    return $first[0] ?? 0;
}

$totalOrders = (int)metric_value($conn, 'SELECT COUNT(*) FROM orders');
$totalUsers = (int)metric_value($conn, 'SELECT COUNT(*) FROM users');
$totalProducts = (int)metric_value($conn, 'SELECT COUNT(*) FROM products');

$pendingOrders = (int)metric_value($conn, 'SELECT COUNT(*) FROM orders WHERE order_status = "pending_payment"');
$completedOrders = (int)metric_value($conn, 'SELECT COUNT(*) FROM orders WHERE order_status IN ("delivered","completed")');
$paymentPendingOrders = (int)metric_value($conn, 'SELECT COUNT(*) FROM orders WHERE payment_status = "pending"');
$rejectedOrders = (int)metric_value($conn, 'SELECT COUNT(*) FROM orders WHERE order_status IN ("cancelled","rejected") OR payment_status IN ("failed","rejected")');

$financeReports = new FinanceReportService();
$financeSnapshot = $financeReports->getDashboardSnapshot();

$refundedToday = (float)$financeSnapshot['refunded_today'];
$pendingRefunds = (int)$financeSnapshot['pending_refunds'];
$partialRefundCount = (int)$financeSnapshot['partial_refunds'];
$fullRefundCount = (int)$financeSnapshot['full_refunds'];
$totalRefundAmount = (float)$financeSnapshot['refunded_total'];

$todayRevenue = (float)$financeSnapshot['today_revenue'];
$thisMonthRevenue = (float)$financeSnapshot['this_month_revenue'];
$lastMonthRevenue = (float)$financeSnapshot['last_month_revenue'];
$pendingCollection = (float)($financeSnapshot['pending_collection'] ?? 0);
$advanceOrders = (int)($financeSnapshot['advance_orders'] ?? 0);
$overduePayments = (int)($financeSnapshot['overdue_payments'] ?? 0);
$todayCollectionsDue = (int)($financeSnapshot['today_collections_due'] ?? 0);
$totalReceivables = (float)($financeSnapshot['total_receivables'] ?? 0);
$collectedToday = (float)($financeSnapshot['collected_today'] ?? 0);

$thisYearRevenue = (float)$financeSnapshot['this_year_revenue'];
$lastYearRevenue = (float)$financeSnapshot['last_year_revenue'];

$monthDelta = $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;
$yearDelta = $lastYearRevenue > 0 ? (($thisYearRevenue - $lastYearRevenue) / $lastYearRevenue) * 100 : 0;
?>

<style>
  .dash-shell { display: grid; gap: 18px; }
  .dash-head {
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.1);
    border-radius: 16px;
    padding: 16px 18px;
    box-shadow: 0 12px 24px rgba(68, 16, 34, 0.08);
  }
  .dash-head h2 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
  }
  .dash-head p {
    margin: 6px 0 0;
    color: #7f6973;
    font-size: 0.9rem;
  }
  .dash-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
  }
  .dash-card {
    display: block;
    text-decoration: none;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 14px;
    padding: 14px;
    box-shadow: 0 10px 20px rgba(68, 16, 34, 0.07);
    color: #2d1f25;
    transition: transform 180ms ease, box-shadow 180ms ease;
  }
  .dash-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 28px rgba(68, 16, 34, 0.12);
  }
  .dash-card strong {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #80001F;
    margin-bottom: 7px;
  }
  .dash-card .value {
    font-family: 'DM Serif Display', Georgia, serif;
    font-size: 1.75rem;
    line-height: 1.1;
    color: #1f1520;
  }
  .dash-card .meta {
    margin-top: 6px;
    font-size: 0.83rem;
    color: #6f5b64;
  }
  .dash-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }
  .dash-panel {
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.11);
    border-radius: 14px;
    padding: 15px;
    box-shadow: 0 10px 20px rgba(68, 16, 34, 0.07);
  }
  .dash-panel h3 {
    margin: 0 0 8px;
    font-size: 1rem;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
  }
  .dash-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .dash-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid rgba(128, 0, 31, 0.18);
    text-decoration: none;
    color: #80001F;
    font-size: 0.78rem;
    font-weight: 700;
    background: #fff;
  }
  .scope-exports {
    margin-top: 12px;
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 12px;
    overflow: hidden;
  }
  .scope-row {
    display: grid;
    grid-template-columns: 1.2fr repeat(3, minmax(0, 1fr));
    gap: 8px;
    align-items: center;
    padding: 8px 10px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    font-size: 0.8rem;
  }
  .scope-row:last-child { border-bottom: 0; }
  .scope-row--head {
    background: #fff5f8;
    font-weight: 700;
    color: #7a1130;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-size: 0.7rem;
  }
  .scope-name {
    color: #2d1f25;
    font-weight: 700;
  }
  .scope-export-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    border-radius: 999px;
    border: 1px solid rgba(128, 0, 31, 0.18);
    color: #80001F;
    text-decoration: none;
    font-size: 0.74rem;
    font-weight: 700;
    background: #fff;
  }
  .delta-up { color: #166534; font-weight: 700; }
  .delta-down { color: #991b1b; font-weight: 700; }
  @media (max-width: 1080px) {
    .dash-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dash-summary { grid-template-columns: 1fr; }
  }
  @media (max-width: 720px) {
    .dash-grid { grid-template-columns: 1fr; }
    .scope-row { grid-template-columns: 1fr; }
    .scope-row--head { display: none; }
    .scope-name { margin-bottom: 2px; }
  }
</style>

<div class="dash-shell">
  <div class="dash-head">
    <h2>Welcome, <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></h2>
    <p>Click any card to open detailed reports with filters and exports.</p>
  </div>

  <div class="dash-grid">
    <a class="dash-card" href="collections_queue.php?payment_status=pending_collection">
      <strong>Pending Collection</strong>
      <div class="value">Rs <?= number_format($pendingCollection, 2) ?></div>
      <div class="meta">Outstanding balance pending collection</div>
    </a>

    <a class="dash-card" href="collections_queue.php?payment_status=pending_collection&followup_status=all">
      <strong>Advance Orders</strong>
      <div class="value"><?= $advanceOrders ?></div>
      <div class="meta">Orders with advance paid and balance due</div>
    </a>

    <a class="dash-card" href="collections_queue.php?payment_status=overdue&action_due=overdue">
      <strong>Overdue Payments</strong>
      <div class="value" style="color:#b91c1c;"><?= $overduePayments ?></div>
      <div class="meta">Receivables past due date</div>
    </a>

    <a class="dash-card" href="collections_queue.php?payment_status=due_today&action_due=today">
      <strong>Today Collections Due</strong>
      <div class="value"><?= $todayCollectionsDue ?></div>
      <div class="meta">Receivables due today</div>
    </a>

    <a class="dash-card" href="collections_queue.php?payment_status=pending_collection">
      <strong>Total Receivables</strong>
      <div class="value">Rs <?= number_format($totalReceivables, 2) ?></div>
      <div class="meta">Current open receivable book</div>
    </a>

    <a class="dash-card" href="sales_register.php?payment_status=realized_only&date_preset=today">
      <strong>Collected Today</strong>
      <div class="value">Rs <?= number_format($collectedToday, 2) ?></div>
      <div class="meta">Net cash collected today</div>
    </a>

    <a class="dash-card" href="sales_register.php?order_status=pending_payment">
      <strong>Pending Orders</strong>
      <div class="value"><?= $pendingOrders ?></div>
      <div class="meta">Click for pending order detail report</div>
    </a>

    <a class="dash-card" href="sales_register.php?payment_status=paid&from_date=<?= date('Y-m-d') ?>&to_date=<?= date('Y-m-d') ?>">
      <strong>Today's Net Revenue</strong>
      <div class="value">Rs <?= number_format($todayRevenue, 2) ?></div>
      <div class="meta">Paid revenue minus refunds for today</div>
    </a>

    <a class="dash-card" href="sales_register.php?payment_status=paid">
      <strong>Last Month vs This Month</strong>
      <div class="value">Rs <?= number_format($thisMonthRevenue, 2) ?></div>
      <div class="meta">Last: Rs <?= number_format($lastMonthRevenue, 2) ?> |
        <span class="<?= $monthDelta >= 0 ? 'delta-up' : 'delta-down' ?>"><?= ($monthDelta >= 0 ? '+' : '') . number_format($monthDelta, 1) ?>%</span>
      </div>
    </a>

    <a class="dash-card" href="collection_report.php">
      <strong>Last Year vs This Year</strong>
      <div class="value">Rs <?= number_format($thisYearRevenue, 2) ?></div>
      <div class="meta">Last: Rs <?= number_format($lastYearRevenue, 2) ?> |
        <span class="<?= $yearDelta >= 0 ? 'delta-up' : 'delta-down' ?>"><?= ($yearDelta >= 0 ? '+' : '') . number_format($yearDelta, 1) ?>%</span>
      </div>
    </a>

    <a class="dash-card" <?= $pendingRefunds > 0 ? 'style="border-color:#f59e0b;"' : '' ?> href="refunds.php?tab=pending">
      <strong>Legacy Pending Refunds</strong>
      <div class="value" <?= $pendingRefunds > 0 ? 'style="color:#d97706;"' : '' ?>><?= $pendingRefunds ?></div>
      <div class="meta"><?= $pendingRefunds > 0 ? '&#9888; Awaiting approval' : 'No refunds pending' ?></div>
    </a>

    <a class="dash-card" style="border-color:#7c3aed;" href="refunds.php?tab=processed">
      <strong>Processed Refunds</strong>
      <div class="value" style="color:#7c3aed;"><?= $partialRefundCount + $fullRefundCount ?></div>
      <div class="meta"><?= $partialRefundCount ?> partial &bull; <?= $fullRefundCount ?> full</div>
    </a>

    <a class="dash-card" href="refund_report.php">
      <strong>Total Refunded</strong>
      <div class="value" style="color:#dc2626;">&#8377;<?= number_format($totalRefundAmount, 0) ?></div>
      <div class="meta">Today: &#8377;<?= number_format($refundedToday, 0) ?></div>
    </a>
  </div>

  <div class="dash-summary">
    <section class="dash-panel">
      <h3>Operational Reports</h3>
      <div class="dash-links">
        <a class="dash-link" href="sales_register.php?order_status=pending_payment">Pending Orders</a>
        <a class="dash-link" href="collections_queue.php?payment_status=pending_collection">Collections Queue</a>
        <a class="dash-link" href="sales_register.php?order_status=delivered">Delivered Orders</a>
        <a class="dash-link" href="sales_register.php?payment_status=pending">Payment Pending</a>
        <a class="dash-link" href="collection_report.php?payment_status=credit&payment_method=credit">Credit Orders</a>
        <?php if (admin_has_permission('can_approve_refund') || admin_has_permission('can_view_refund_reports')): ?>
        <a class="dash-link" href="refunds.php">Refunds Queue</a>
        <a class="dash-link" href="refund_report.php">Refund Report</a>
        <?php endif; ?>
      </div>
      <div class="scope-exports">
        <div class="scope-row scope-row--head">
          <div>Report Scope</div>
          <div>CSV</div>
          <div>Excel</div>
          <div>PDF</div>
        </div>
        <div class="scope-row">
          <div class="scope-name">Pending Orders</div>
          <a class="scope-export-btn" href="sales_register.php?order_status=pending_payment&export=csv">CSV</a>
          <a class="scope-export-btn" href="sales_register.php?order_status=pending_payment&export=excel">Excel</a>
          <a class="scope-export-btn" href="sales_register.php?order_status=pending_payment&export=pdf">PDF</a>
        </div>
        <div class="scope-row">
          <div class="scope-name">Delivered Orders</div>
          <a class="scope-export-btn" href="sales_register.php?order_status=delivered&export=csv">CSV</a>
          <a class="scope-export-btn" href="sales_register.php?order_status=delivered&export=excel">Excel</a>
          <a class="scope-export-btn" href="sales_register.php?order_status=delivered&export=pdf">PDF</a>
        </div>
        <div class="scope-row">
          <div class="scope-name">Payment Pending</div>
          <a class="scope-export-btn" href="sales_register.php?payment_status=pending&export=csv">CSV</a>
          <a class="scope-export-btn" href="sales_register.php?payment_status=pending&export=excel">Excel</a>
          <a class="scope-export-btn" href="sales_register.php?payment_status=pending&export=pdf">PDF</a>
        </div>
        <div class="scope-row">
          <div class="scope-name">Rejected Orders</div>
          <a class="scope-export-btn" href="collection_report.php?payment_status=credit&payment_method=credit&export=csv">CSV</a>
          <a class="scope-export-btn" href="collection_report.php?payment_status=credit&payment_method=credit&export=excel">Excel</a>
          <a class="scope-export-btn" href="collection_report.php?payment_status=credit&payment_method=credit&export=pdf">PDF</a>
        </div>
      </div>
      <p class="meta">Counts: Pending <?= $pendingOrders ?> | Delivered/Completed <?= $completedOrders ?> | Payment Pending <?= $paymentPendingOrders ?> | Rejected/Cancelled <?= $rejectedOrders ?> | Refunded Today: Rs <?= number_format($refundedToday, 2) ?></p>
    </section>

    <section class="dash-panel">
      <h3>Business Snapshot</h3>
      <div class="dash-links">
        <a class="dash-link" href="orders.php">Orders (<?= $totalOrders ?>)</a>
        <a class="dash-link" href="products.php">Products (<?= $totalProducts ?>)</a>
        <a class="dash-link" href="sales_register.php">Sales Register</a>
        <a class="dash-link" href="collection_report.php?export=csv">Collection CSV</a>
      </div>
      <p class="meta">Customers registered: <?= $totalUsers ?></p>
    </section>
  </div>
</div>
