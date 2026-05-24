<?php
$pageTitle = 'Sales Register';
require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');
require_once __DIR__ . '/includes/db.php';

use App\Services\FinanceReportService;

$financeReports = new FinanceReportService();

$perPageOptions = [20, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, $perPageOptions, true)) {
  $perPage = 20;
}
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = $financeReports->normalizeFilters($_GET);
$view = $filters['view'];
$fromDate = $filters['from_date'];
$toDate = $filters['to_date'];
$datePreset = $filters['date_preset'];
$dateBasis = $filters['date_basis'];
$orderNo = $filters['order_no'];
$itemQuery = $filters['item'];
$mobileSearch = $filters['mobile'];
$paymentStatus = $filters['payment_status'];
$orderStatus = $filters['order_status'];
$paymentMethod = $filters['payment_method'];

$register = $financeReports->getRegister($filters, $perPage, $page);
$rows = $register['rows'];
$totals = $register['totals'];
$totalRows = (int)$register['totalRows'];
$totalPages = (int)$register['totalPages'];
$page = (int)$register['page'];

function sales_payment_channel_label(string $method): string
{
    if ($method === 'cod') {
        return 'Cash';
    }
    if ($method === 'credit') {
        return 'Credit';
    }
    if ($method === 'upi_manual' || $method === 'gateway') {
        return 'Bank';
    }
    return strtoupper($method !== '' ? $method : 'NA');
}

function sales_register_url(array $overrides = []): string
{
    $params = [
    'view' => $_GET['view'] ?? 'sales',
    'date_preset' => $_GET['date_preset'] ?? 'this_month',
    'date_basis' => $_GET['date_basis'] ?? 'payment',
    'from_date' => $_GET['from_date'] ?? date('Y-m-01'),
    'to_date' => $_GET['to_date'] ?? date('Y-m-d'),
    'order_no' => $_GET['order_no'] ?? '',
    'item' => $_GET['item'] ?? '',
    'mobile' => $_GET['mobile'] ?? '',
    'payment_status' => $_GET['payment_status'] ?? 'finance_safe',
    'order_status' => $_GET['order_status'] ?? 'all',
    'payment_method' => $_GET['payment_method'] ?? 'all',
    'per_page' => $_GET['per_page'] ?? '20',
    'page' => $_GET['page'] ?? '1',
    ];

    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }

    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
            unset($params[$k]);
        }
    }

    return 'sales_register.php?' . http_build_query($params);
}
$export = strtolower(trim((string)($_GET['export'] ?? '')));
if (in_array($export, ['csv', 'excel', 'pdf'], true)) {
    $exportRows = $financeReports->getRegisterExportRows($filters);

    $companyName = 'Cakeouflage';
    $rangeText = $fromDate . ' to ' . $toDate;

    if ($export === 'csv') {
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="sales_register.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, [$companyName . ' - ' . ($view === 'collection' ? 'Collection Split Register' : 'Sales Register')]);
      fputcsv($out, ['Date Range', $rangeText]);
      fputcsv($out, ['Order Filter', $orderNo !== '' ? $orderNo : 'All']);
      fputcsv($out, []);
      fputcsv($out, ['Booking Date', 'Payment Date', 'Invoice No', 'Order No', 'Customer', 'Mobile', 'Items', 'Gross Amount', 'Refund', 'Net Collected', 'Advance Received', 'Balance Due', 'Collection Status', 'Payment Status', 'Order Status', 'Payment Channel']);
      foreach ($exportRows as $row) {
        fputcsv($out, [
          (string)$row['created_at'],
          (string)($row['recognized_at'] ?? ''),
          (string)($row['invoice_number'] ?? ''),
          (string)$row['order_number'],
          (string)$row['customer_name'],
          (string)($row['customer_phone_e164'] ?: $row['customer_phone']),
          (string)($row['items_summary'] ?? ''),
          number_format((float)($row['gross_amount'] ?? 0), 2, '.', ''),
          number_format((float)($row['refund_amount'] ?? 0), 2, '.', ''),
          number_format((float)($row['net_collected_amount'] ?? 0), 2, '.', ''),
          number_format((float)($row['advance_collected_amount'] ?? 0), 2, '.', ''),
          number_format((float)($row['balance_due_amount'] ?? 0), 2, '.', ''),
          (string)($row['collection_status_label'] ?? $row['finance_status_label'] ?? ''),
          (string)$row['payment_status'],
          (string)$row['order_status'],
          sales_payment_channel_label((string)$row['payment_method']),
        ]);
      }
      fclose($out);
      exit;
    }

    if ($export === 'excel') {
      header('Content-Type: application/vnd.ms-excel; charset=utf-8');
      header('Content-Disposition: attachment; filename="sales_register.xls"');
      echo '<table border="1">';
      echo '<tr><th colspan="15">' . htmlspecialchars($companyName . ' - ' . ($view === 'collection' ? 'Collection Split Register' : 'Sales Register'), ENT_QUOTES, 'UTF-8') . '</th></tr>';
      echo '<tr><td colspan="15">Date Range: ' . htmlspecialchars($rangeText, ENT_QUOTES, 'UTF-8') . '</td></tr>';
      echo '<tr><th>Booking Date</th><th>Payment Date</th><th>Invoice</th><th>Order No</th><th>Customer</th><th>Items</th><th>Gross Amount</th><th>Refund</th><th>Net Collected</th><th>Advance Received</th><th>Balance Due</th><th>Collection Status</th><th>Payment Status</th><th>Order Status</th><th>Payment Channel</th></tr>';
      foreach ($exportRows as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['recognized_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['items_summary'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . number_format((float)($row['gross_amount'] ?? 0), 2) . '</td>';
        echo '<td>' . number_format((float)($row['refund_amount'] ?? 0), 2) . '</td>';
        echo '<td>' . number_format((float)($row['net_collected_amount'] ?? 0), 2) . '</td>';
        echo '<td>' . number_format((float)($row['advance_collected_amount'] ?? 0), 2) . '</td>';
        echo '<td>' . number_format((float)($row['balance_due_amount'] ?? 0), 2) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['collection_status_label'] ?? $row['finance_status_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(sales_payment_channel_label((string)$row['payment_method']), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
      }
      echo '</table>';
      exit;
    }

    if ($export === 'pdf') {
      echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Sales Register</title><style>';
      echo 'body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111;margin:18px}';
      echo 'table{width:100%;border-collapse:collapse;margin-top:12px}';
      echo 'th,td{border:1px solid #111;padding:6px;vertical-align:top;text-align:left}';
      echo 'th{background:#efefef}';
      echo '</style></head><body>';
      echo '<h2>' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '</h2>';
      echo '<p><strong>' . htmlspecialchars($view === 'collection' ? 'Collection Split Register' : 'Sales Register', ENT_QUOTES, 'UTF-8') . '</strong><br>Date Range: ' . htmlspecialchars($rangeText, ENT_QUOTES, 'UTF-8') . '</p>';
      echo '<table><tr><th>Booking Date</th><th>Payment Date</th><th>Invoice</th><th>Order No</th><th>Customer</th><th>Items</th><th>Gross Amount</th><th>Refund</th><th>Net Collected</th><th>Advance Received</th><th>Balance Due</th><th>Collection Status</th><th>Payment Status</th><th>Order Status</th><th>Payment Channel</th></tr>';
      foreach ($exportRows as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['recognized_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['items_summary'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>Rs ' . number_format((float)($row['gross_amount'] ?? 0), 2) . '</td>';
        echo '<td>Rs ' . number_format((float)($row['refund_amount'] ?? 0), 2) . '</td>';
        echo '<td>Rs ' . number_format((float)($row['net_collected_amount'] ?? 0), 2) . '</td>';
        echo '<td>Rs ' . number_format((float)($row['advance_collected_amount'] ?? 0), 2) . '</td>';
        echo '<td>Rs ' . number_format((float)($row['balance_due_amount'] ?? 0), 2) . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['collection_status_label'] ?? $row['finance_status_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(sales_payment_channel_label((string)$row['payment_method']), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
      }
      echo '</table><script>window.print();</script></body></html>';
      exit;
    }
}

$currentUrl = sales_register_url(['page' => $page]);
?>

<style>
  .sr-shell { display: grid; gap: 16px; }
  .sr-panel { background: #fff; border: 1px solid rgba(128, 0, 31, 0.12); border-radius: 14px; box-shadow: 0 10px 26px rgba(68, 16, 34, 0.08); }
  .sr-head { padding: 16px 18px; border-bottom: 1px solid rgba(128, 0, 31, 0.1); background: linear-gradient(180deg, #fff7fa 0%, #fff 100%); }
  .sr-head h2 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
  .sr-head p { margin: 6px 0 0; color: #8f7681; font-size: 0.88rem; }
  .sr-body { padding: 16px 18px; }
  .sr-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
  .sr-card { border: 1px solid rgba(128, 0, 31, 0.12); border-radius: 10px; padding: 10px 12px; background: #fff; }
  .sr-card strong { display: block; font-size: 0.74rem; color: #80001F; text-transform: uppercase; letter-spacing: 0.05em; }
  .sr-card span { display: block; margin-top: 6px; font-size: 1.2rem; color: #2d1f25; font-weight: 700; }
  .sr-card small { display: block; margin-top: 4px; color: #8f7681; font-size: 0.76rem; }
  .sr-filters { display: grid; gap: 8px; }
  .sr-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .sr-row input, .sr-row select { border: 1px solid rgba(128,0,31,0.18); border-radius: 8px; padding: 8px 10px; min-width: 150px; }
  .sr-presets { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
  .sr-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; border: 1px solid rgba(128,0,31,0.18); padding: 6px 10px; text-decoration: none; color: #80001F; font-size: 0.78rem; font-weight: 700; background: #fff; }
  .sr-chip.active { background: #80001F; color: #fff; border-color: #80001F; }
  .sr-btn { border: 0; border-radius: 8px; padding: 8px 12px; background: #80001F; color: #fff; text-decoration: none; font-weight: 700; font-size: 0.82rem; cursor: pointer; }
  .sr-btn.ghost { background: #fff; color: #80001F; border: 1px solid rgba(128,0,31,0.2); }
  .sr-table-wrap { overflow: auto; }
  .sr-table { width: 100%; border-collapse: collapse; min-width: 1480px; }
  .sr-table th, .sr-table td { border-bottom: 1px solid rgba(128, 0, 31, 0.08); padding: 10px 8px; text-align: left; }
  .sr-table th { font-size: 0.74rem; text-transform: uppercase; color: #80001F; background: #fff4f7; letter-spacing: 0.05em; }
  .sr-link { color: #80001F; text-decoration: none; font-weight: 700; }
  .sr-link:hover { text-decoration: underline; }
  .sr-pill { display: inline-block; border-radius: 999px; padding: 4px 8px; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; }
  .sr-pill.ok { background: #dcfce7; color: #166534; }
  .sr-pill.pending { background: #fef3c7; color: #92400e; }
  .sr-pill.bad { background: #fecdd3; color: #991b1b; }
  .sr-pill.info { background: #dbeafe; color: #1d4ed8; }
  .sr-pagination { margin-top: 10px; display: flex; gap: 8px; align-items: center; }
  .sr-muted { color: #8f7681; font-size: 0.82rem; }
  .sr-amount { white-space: nowrap; font-variant-numeric: tabular-nums; }
  .sr-amount.negative { color: #9f1239; }
  .sr-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  .sr-edit-toggle,
  .sr-edit-save,
  .sr-edit-cancel {
    border: 0;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
  }
  .sr-edit-toggle,
  .sr-edit-save { background: #80001F; color: #fff; }
  .sr-edit-cancel { background: #e5e7eb; color: #1f2937; }
  .sr-edit-panel {
    display: none;
    margin-top: 8px;
    border: 1px solid rgba(128, 0, 31, 0.16);
    border-radius: 10px;
    padding: 8px;
    background: #fff9fb;
    gap: 8px;
  }
  .sr-edit-panel.open { display: grid; }
  .sr-edit-group { display: grid; gap: 6px; }
  .sr-edit-group label {
    font-size: 0.67rem;
    text-transform: uppercase;
    color: #80001F;
    font-weight: 700;
    letter-spacing: 0.05em;
  }
  .sr-edit-group select {
    min-width: 120px;
    padding: 6px 8px;
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-radius: 8px;
  }
  .sr-toast-wrap {
    position: fixed;
    right: 18px;
    bottom: 18px;
    z-index: 10000;
    display: grid;
    gap: 8px;
  }
  .sr-toast {
    min-width: 260px;
    max-width: 340px;
    border-radius: 10px;
    padding: 10px 12px;
    box-shadow: 0 12px 24px rgba(17, 24, 39, 0.18);
    border: 1px solid transparent;
    font-size: 0.83rem;
    background: #fff;
  }
  .sr-toast strong { display: block; margin-bottom: 2px; }
  .sr-toast.success {
    background: #ecfdf3;
    border-color: #bbf7d0;
    color: #166534;
  }
  .sr-toast.error {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #9f1239;
  }
  @media (max-width: 960px) { .sr-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 700px) { .sr-grid { grid-template-columns: 1fr; } }
</style>

<div class="sr-shell">
  <section class="sr-panel">
    <div class="sr-head">
      <h2><?= $view === 'collection' ? 'Collection Split Register' : 'Sales Register' ?></h2>
      <p>Finance-safe register using realized gross, processed refunds, advances, and outstanding balances.</p>
    </div>
    <div class="sr-body">
      <div class="sr-grid">
        <div class="sr-card"><strong>Net Cash</strong><span>Rs <?= number_format((float)($totals['cash_total'] ?? 0), 2) ?></span><small>Settled cash after refunds</small></div>
        <div class="sr-card"><strong>Net Bank</strong><span>Rs <?= number_format((float)($totals['bank_total'] ?? 0), 2) ?></span><small>UPI and gateway settlements</small></div>
        <div class="sr-card"><strong>Net Collected</strong><span>Rs <?= number_format((float)($totals['overall_total'] ?? 0), 2) ?></span><small>Collected cash after refund impact</small></div>
        <div class="sr-card"><strong>Gross Amount</strong><span>Rs <?= number_format((float)($totals['gross_total'] ?? 0), 2) ?></span><small>Total order value in filtered rows</small></div>
        <div class="sr-card"><strong>Refunded</strong><span>Rs <?= number_format((float)($totals['refunded_total'] ?? 0), 2) ?></span><small><?= (int)($totals['refunded_orders'] ?? 0) ?> refunded orders in view</small></div>
        <div class="sr-card"><strong>Advance Received</strong><span>Rs <?= number_format((float)($totals['advance_collected'] ?? 0), 2) ?></span><small>Advance payments captured</small></div>
        <div class="sr-card"><strong>Pending Collection</strong><span>Rs <?= number_format((float)($totals['balance_outstanding'] ?? 0), 2) ?></span><small>Outstanding receivables to collect</small></div>
        <div class="sr-card"><strong>Credit Outstanding</strong><span>Rs <?= number_format((float)($totals['credit_total'] ?? 0), 2) ?></span><small>Credit channel balance only</small></div>
      </div>
    </div>
  </section>

  <section class="sr-panel">
    <div class="sr-body">
      <form method="get" class="sr-filters">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>">
        <div class="sr-presets">
          <?php foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This Week', 'this_month' => 'This Month', 'last_month' => 'Last Month'] as $presetValue => $presetLabel): ?>
            <a class="sr-chip <?= $datePreset === $presetValue ? 'active' : '' ?>" href="<?= htmlspecialchars(sales_register_url(['date_preset' => $presetValue, 'page' => 1]), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($presetLabel, ENT_QUOTES, 'UTF-8') ?></a>
          <?php endforeach; ?>
          <a class="sr-chip <?= $datePreset === 'custom' ? 'active' : '' ?>" href="<?= htmlspecialchars(sales_register_url(['date_preset' => 'custom', 'page' => 1]), ENT_QUOTES, 'UTF-8') ?>">Custom</a>
        </div>
        <div class="sr-row">
          <select name="date_basis">
            <option value="payment" <?= $dateBasis === 'payment' ? 'selected' : '' ?>>Payment Date</option>
            <option value="booking" <?= $dateBasis === 'booking' ? 'selected' : '' ?>>Booking Date</option>
            <option value="fulfilment" <?= $dateBasis === 'fulfilment' ? 'selected' : '' ?>>Fulfilment Date</option>
          </select>
          <input type="hidden" name="date_preset" value="<?= htmlspecialchars($datePreset, ENT_QUOTES, 'UTF-8') ?>">
          <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8') ?>">
          <input type="date" name="to_date" value="<?= htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8') ?>">
          <input type="text" name="order_no" placeholder="Order no" value="<?= htmlspecialchars($orderNo, ENT_QUOTES, 'UTF-8') ?>">
          <input type="text" name="item" placeholder="Item contains" value="<?= htmlspecialchars($itemQuery, ENT_QUOTES, 'UTF-8') ?>">
          <input type="tel" name="mobile" placeholder="Mobile search" value="<?= htmlspecialchars($mobileSearch, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="sr-row">
          <select name="payment_status">
            <option value="finance_safe" <?= $paymentStatus === 'finance_safe' ? 'selected' : '' ?>>Finance Safe Rows</option>
            <option value="realized_only" <?= $paymentStatus === 'realized_only' ? 'selected' : '' ?>>Realized Revenue Only</option>
            <option value="pending_collection" <?= $paymentStatus === 'pending_collection' ? 'selected' : '' ?>>Pending Collection</option>
            <option value="due_today" <?= $paymentStatus === 'due_today' ? 'selected' : '' ?>>Due Today</option>
            <option value="due_tomorrow" <?= $paymentStatus === 'due_tomorrow' ? 'selected' : '' ?>>Due Tomorrow</option>
            <option value="overdue" <?= $paymentStatus === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            <option value="refunds" <?= $paymentStatus === 'refunds' ? 'selected' : '' ?>>Refund Activity</option>
            <option value="exceptions" <?= $paymentStatus === 'exceptions' ? 'selected' : '' ?>>Exceptions</option>
            <option value="all" <?= $paymentStatus === 'all' ? 'selected' : '' ?>>All Payment Status</option>
            <option value="paid" <?= $paymentStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="pending" <?= $paymentStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="credit" <?= $paymentStatus === 'credit' ? 'selected' : '' ?>>Credit</option>
            <option value="under_review" <?= $paymentStatus === 'under_review' ? 'selected' : '' ?>>Under Review</option>
            <option value="refund_pending" <?= $paymentStatus === 'refund_pending' ? 'selected' : '' ?>>Refund Pending</option>
            <option value="partially_refunded" <?= $paymentStatus === 'partially_refunded' ? 'selected' : '' ?>>Partially Refunded</option>
            <option value="refunded" <?= $paymentStatus === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            <option value="failed" <?= $paymentStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="rejected" <?= $paymentStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
          <select name="order_status">
            <option value="all" <?= $orderStatus === 'all' ? 'selected' : '' ?>>All Order Status</option>
            <option value="pending_payment" <?= $orderStatus === 'pending_payment' ? 'selected' : '' ?>>Pending Payment</option>
            <option value="payment_under_review" <?= $orderStatus === 'payment_under_review' ? 'selected' : '' ?>>Payment Under Review</option>
            <option value="confirmed" <?= $orderStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
            <option value="preparing" <?= $orderStatus === 'preparing' ? 'selected' : '' ?>>Preparing</option>
            <option value="out_for_delivery" <?= $orderStatus === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
            <option value="ready_for_pickup" <?= $orderStatus === 'ready_for_pickup' ? 'selected' : '' ?>>Ready for Pickup</option>
            <option value="delivered" <?= $orderStatus === 'delivered' ? 'selected' : '' ?>>Delivered</option>
            <option value="completed" <?= $orderStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= $orderStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            <option value="refund_requested" <?= $orderStatus === 'refund_requested' ? 'selected' : '' ?>>Refund Requested</option>
            <option value="partially_refunded" <?= $orderStatus === 'partially_refunded' ? 'selected' : '' ?>>Partially Refunded</option>
            <option value="fully_refunded" <?= $orderStatus === 'fully_refunded' ? 'selected' : '' ?>>Fully Refunded</option>
            <option value="rejected" <?= $orderStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
          <select name="payment_method">
            <option value="all" <?= $paymentMethod === 'all' ? 'selected' : '' ?>>All Channels</option>
            <option value="cod" <?= $paymentMethod === 'cod' ? 'selected' : '' ?>>Cash</option>
            <option value="bank" <?= $paymentMethod === 'bank' ? 'selected' : '' ?>>Bank</option>
            <option value="credit" <?= $paymentMethod === 'credit' ? 'selected' : '' ?>>Credit</option>
          </select>
          <select name="per_page">
            <?php foreach ($perPageOptions as $size): ?>
              <option value="<?= (int)$size ?>" <?= $perPage === (int)$size ? 'selected' : '' ?>><?= (int)$size ?> / page</option>
            <?php endforeach; ?>
          </select>
          <button class="sr-btn" type="submit">Apply Filters</button>
          <a class="sr-btn ghost" href="collections_queue.php?<?= htmlspecialchars(http_build_query([
            'date_preset' => $datePreset,
            'date_basis' => $dateBasis,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'payment_status' => 'pending_collection',
          ]), ENT_QUOTES, 'UTF-8') ?>">Collections Queue</a>
          <a class="sr-btn ghost" href="<?= htmlspecialchars(sales_register_url(['export' => 'csv']), ENT_QUOTES, 'UTF-8') ?>">CSV</a>
          <a class="sr-btn ghost" href="<?= htmlspecialchars(sales_register_url(['export' => 'excel']), ENT_QUOTES, 'UTF-8') ?>">Excel</a>
          <a class="sr-btn ghost" href="<?= htmlspecialchars(sales_register_url(['export' => 'pdf']), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">PDF</a>
        </div>
      </form>
      <p class="sr-muted">Rows: <?= number_format($totalRows) ?> | Date basis: <?= htmlspecialchars(ucfirst($dateBasis), ENT_QUOTES, 'UTF-8') ?> | Range: <?= htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8') ?></p>

      <div class="sr-table-wrap">
        <table class="sr-table">
          <thead>
            <tr>
              <th>Booking Date</th>
              <th>Payment Date</th>
              <th>Invoice</th>
              <th>Order No</th>
              <th>Customer</th>
              <th>Mobile</th>
              <th>Items</th>
              <th>Gross Amount</th>
              <th>Refund</th>
              <th>Net Collected</th>
              <th>Advance Received</th>
              <th>Balance Due</th>
              <th>Collection Status</th>
              <th>Payment Status</th>
              <th>Order Status</th>
              <th>Channel</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="16" class="sr-muted">No finance-safe rows found for the selected filters.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $statusClass = 'ok';
                  if (in_array((string)$row['payment_status'], ['pending', 'credit', 'under_review', 'refund_pending'], true)) {
                    $statusClass = 'pending';
                  }
                  if (in_array((string)$row['payment_status'], ['failed', 'rejected'], true)) {
                    $statusClass = 'bad';
                  }
                  if (in_array((string)$row['payment_status'], ['partially_refunded', 'refunded'], true)) {
                    $statusClass = 'info';
                  }
                  $returnTo = urlencode(sales_register_url(['page' => $page]));
                  $rowId = (int)$row['id'];
                ?>
                <tr id="sr-row-<?= $rowId ?>" data-order-id="<?= $rowId ?>" data-order-status="<?= htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') ?>" data-payment-method="<?= htmlspecialchars((string)$row['payment_method'], ENT_QUOTES, 'UTF-8') ?>" data-payment-status="<?= htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8') ?>">
                  <td><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($row['recognized_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($row['invoice_number'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><a class="sr-link" href="order_details.php?id=<?= (int)$row['id'] ?>&return_to=<?= $returnTo ?>"><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></a></td>
                  <td><?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($row['customer_phone_e164'] ?: $row['customer_phone']), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($row['items_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="sr-amount">Rs <?= number_format((float)($row['gross_amount'] ?? 0), 2) ?></td>
                  <td class="sr-amount<?= (float)($row['refund_amount'] ?? 0) > 0 ? ' negative' : '' ?>"><?= (float)($row['refund_amount'] ?? 0) > 0 ? '- Rs ' . number_format((float)($row['refund_amount'] ?? 0), 2) : '—' ?></td>
                  <td class="sr-amount">Rs <?= number_format((float)($row['net_collected_amount'] ?? 0), 2) ?></td>
                  <td class="sr-amount"><?= (float)($row['advance_collected_amount'] ?? 0) > 0 ? 'Rs ' . number_format((float)($row['advance_collected_amount'] ?? 0), 2) : '—' ?></td>
                  <td class="sr-amount"><?= (float)($row['balance_due_amount'] ?? 0) > 0 ? 'Rs ' . number_format((float)($row['balance_due_amount'] ?? 0), 2) : '—' ?></td>
                  <td><span class="sr-pill <?= $statusClass ?>"><?= htmlspecialchars((string)($row['collection_status_label'] ?? $row['finance_status_label'] ?? 'Payment Pending'), ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td><span id="payment-status-pill-<?= $rowId ?>" class="sr-pill <?= $statusClass ?>"><?= htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td id="order-status-text-<?= $rowId ?>"><?= htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td id="payment-channel-text-<?= $rowId ?>"><?= htmlspecialchars(sales_payment_channel_label((string)$row['payment_method']), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <div class="sr-actions">
                      <a class="sr-link" href="order_details.php?id=<?= (int)$row['id'] ?>&return_to=<?= $returnTo ?>">Open</a>
                      <button type="button" class="sr-edit-toggle" onclick="toggleRowEdit(<?= $rowId ?>)">Edit</button>
                    </div>

                    <div class="sr-edit-panel" id="sr-edit-panel-<?= $rowId ?>">
                      <div class="sr-edit-group">
                        <label for="sr-status-<?= $rowId ?>">Order Status</label>
                        <select id="sr-status-<?= $rowId ?>">
                          <option value="pending_payment" <?= (string)$row['order_status'] === 'pending_payment' ? 'selected' : '' ?>>Pending Payment</option>
                          <option value="payment_under_review" <?= (string)$row['order_status'] === 'payment_under_review' ? 'selected' : '' ?>>Payment Under Review</option>
                          <option value="confirmed" <?= (string)$row['order_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                          <option value="preparing" <?= (string)$row['order_status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                          <option value="out_for_delivery" <?= (string)$row['order_status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                          <option value="ready_for_pickup" <?= (string)$row['order_status'] === 'ready_for_pickup' ? 'selected' : '' ?>>Ready for Pickup</option>
                          <option value="delivered" <?= (string)$row['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                          <option value="completed" <?= (string)$row['order_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                          <option value="cancelled" <?= (string)$row['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                          <option value="refund_requested" <?= (string)$row['order_status'] === 'refund_requested' ? 'selected' : '' ?>>Refund Requested</option>
                          <option value="partially_refunded" <?= (string)$row['order_status'] === 'partially_refunded' ? 'selected' : '' ?>>Partially Refunded</option>
                          <option value="fully_refunded" <?= (string)$row['order_status'] === 'fully_refunded' ? 'selected' : '' ?>>Fully Refunded</option>
                          <option value="rejected" <?= (string)$row['order_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <div class="sr-actions">
                          <button type="button" class="sr-edit-save" onclick="saveRowStatus(<?= $rowId ?>)">Save Status</button>
                        </div>
                      </div>

                      <div class="sr-edit-group">
                        <label for="sr-payment-method-<?= $rowId ?>">Payment</label>
                        <select id="sr-payment-method-<?= $rowId ?>">
                          <option value="cod" <?= (string)$row['payment_method'] === 'cod' ? 'selected' : '' ?>>Cash</option>
                          <option value="upi_manual" <?= ((string)$row['payment_method'] === 'upi_manual' || (string)$row['payment_method'] === 'gateway') ? 'selected' : '' ?>>Bank</option>
                          <option value="credit" <?= (string)$row['payment_method'] === 'credit' ? 'selected' : '' ?>>Credit</option>
                        </select>
                        <select id="sr-payment-status-<?= $rowId ?>">
                          <option value="paid" <?= (string)$row['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                          <option value="pending" <?= (string)$row['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                          <option value="credit" <?= (string)$row['payment_status'] === 'credit' ? 'selected' : '' ?>>Credit</option>
                          <option value="under_review" <?= (string)$row['payment_status'] === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                          <option value="refund_pending" <?= (string)$row['payment_status'] === 'refund_pending' ? 'selected' : '' ?>>Refund Pending</option>
                          <option value="partially_refunded" <?= (string)$row['payment_status'] === 'partially_refunded' ? 'selected' : '' ?>>Partially Refunded</option>
                          <option value="failed" <?= (string)$row['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                          <option value="refunded" <?= (string)$row['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                          <option value="rejected" <?= (string)$row['payment_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <div class="sr-actions">
                          <button type="button" class="sr-edit-save" onclick="saveRowPayment(<?= $rowId ?>)">Save Payment</button>
                          <button type="button" class="sr-edit-cancel" onclick="cancelRowEdit(<?= $rowId ?>)">Cancel</button>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="sr-pagination">
        <span class="sr-muted">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
        <?php if ($page > 1): ?>
          <a class="sr-btn ghost" href="<?= htmlspecialchars(sales_register_url(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="sr-btn ghost" href="<?= htmlspecialchars(sales_register_url(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<div id="srToastWrap" class="sr-toast-wrap" aria-live="polite" aria-atomic="true"></div>

<script>
  function showSalesToast(title, message, type) {
    const wrap = document.getElementById('srToastWrap');
    const toast = document.createElement('div');
    toast.className = 'sr-toast ' + (type === 'error' ? 'error' : 'success');
    toast.innerHTML = '<strong>' + title + '</strong><div>' + message + '</div>';
    wrap.appendChild(toast);
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 3200);
  }

  function paymentStatusPillClass(status) {
    if (status === 'failed' || status === 'rejected') {
      return 'bad';
    }
    if (status === 'partially_refunded' || status === 'refunded') {
      return 'info';
    }
    if (status === 'pending' || status === 'credit' || status === 'under_review' || status === 'refund_pending') {
      return 'pending';
    }
    return 'ok';
  }

  function paymentChannelLabel(method) {
    if (method === 'cod') {
      return 'Cash';
    }
    if (method === 'credit') {
      return 'Credit';
    }
    if (method === 'upi_manual' || method === 'gateway') {
      return 'Bank';
    }
    return (method || 'NA').toUpperCase();
  }

  function toggleRowEdit(rowId) {
    const panel = document.getElementById('sr-edit-panel-' + rowId);
    if (!panel) {
      return;
    }
    document.querySelectorAll('.sr-edit-panel.open').forEach((p) => {
      if (p !== panel) {
        p.classList.remove('open');
      }
    });
    panel.classList.toggle('open');
  }

  function cancelRowEdit(rowId) {
    const row = document.getElementById('sr-row-' + rowId);
    if (!row) {
      return;
    }

    document.getElementById('sr-status-' + rowId).value = row.dataset.orderStatus || 'pending_payment';
    document.getElementById('sr-payment-method-' + rowId).value = row.dataset.paymentMethod || 'upi_manual';
    document.getElementById('sr-payment-status-' + rowId).value = row.dataset.paymentStatus || 'paid';

    const panel = document.getElementById('sr-edit-panel-' + rowId);
    if (panel) {
      panel.classList.remove('open');
    }
  }

  function saveRowStatus(rowId) {
    const row = document.getElementById('sr-row-' + rowId);
    const statusSelect = document.getElementById('sr-status-' + rowId);
    const paymentMethodSelect = document.getElementById('sr-payment-method-' + rowId);

    const formData = new FormData();
    formData.append('order_id', String(rowId));
    formData.append('status', statusSelect.value);
    formData.append('payment_method', paymentMethodSelect.value);

    fetch('api/update-order-status-async.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          showSalesToast('Status update failed', data.error || 'Unable to update order status.', 'error');
          return;
        }

        row.dataset.orderStatus = data.order_status;
        if (data.payment_method) {
          row.dataset.paymentMethod = data.payment_method;
        }
        if (data.payment_status) {
          row.dataset.paymentStatus = data.payment_status;
        }

        const statusText = document.getElementById('order-status-text-' + rowId);
        if (statusText) {
          statusText.textContent = data.order_status;
        }

        if (data.payment_status) {
          const pill = document.getElementById('payment-status-pill-' + rowId);
          if (pill) {
            pill.textContent = data.payment_status;
            pill.className = 'sr-pill ' + paymentStatusPillClass(data.payment_status);
          }
          const paymentStatusSelect = document.getElementById('sr-payment-status-' + rowId);
          if (paymentStatusSelect) {
            paymentStatusSelect.value = data.payment_status;
          }
        }

        if (data.payment_method) {
          const channelText = document.getElementById('payment-channel-text-' + rowId);
          if (channelText) {
            channelText.textContent = paymentChannelLabel(data.payment_method);
          }
          const paymentMethod = document.getElementById('sr-payment-method-' + rowId);
          if (paymentMethod) {
            paymentMethod.value = data.payment_method;
          }
        }

        const panel = document.getElementById('sr-edit-panel-' + rowId);
        if (panel) {
          panel.classList.remove('open');
        }

        showSalesToast('Status updated', data.message || 'Order status saved successfully.', 'success');
      })
      .catch(() => {
        showSalesToast('Status update failed', 'Network error while saving status.', 'error');
      });
  }

  function saveRowPayment(rowId) {
    const row = document.getElementById('sr-row-' + rowId);
    const methodSelect = document.getElementById('sr-payment-method-' + rowId);
    const statusSelect = document.getElementById('sr-payment-status-' + rowId);

    const formData = new FormData();
    formData.append('order_id', String(rowId));
    formData.append('payment_method', methodSelect.value);
    formData.append('payment_status', statusSelect.value);

    fetch('api/update-order-payment-async.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          showSalesToast('Payment update failed', data.error || 'Unable to update payment.', 'error');
          return;
        }

        row.dataset.paymentMethod = data.payment_method;
        row.dataset.paymentStatus = data.payment_status;

        const channelText = document.getElementById('payment-channel-text-' + rowId);
        if (channelText) {
          channelText.textContent = paymentChannelLabel(data.payment_method);
        }

        const pill = document.getElementById('payment-status-pill-' + rowId);
        if (pill) {
          pill.textContent = data.payment_status;
          pill.className = 'sr-pill ' + paymentStatusPillClass(data.payment_status);
        }

        methodSelect.value = data.payment_method;
        statusSelect.value = data.payment_status;

        const panel = document.getElementById('sr-edit-panel-' + rowId);
        if (panel) {
          panel.classList.remove('open');
        }

        showSalesToast('Payment updated', data.message || 'Payment details saved successfully.', 'success');
      })
      .catch(() => {
        showSalesToast('Payment update failed', 'Network error while saving payment.', 'error');
      });
  }
</script>
