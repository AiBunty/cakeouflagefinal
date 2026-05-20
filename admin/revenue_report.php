<?php
$pageTitle = 'Revenue Report';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('revenue_report');

$targetParams = [
  'view' => 'sales',
  'from_date' => $_GET['from_date'] ?? '',
  'to_date' => $_GET['to_date'] ?? '',
  'payment_method' => $_GET['payment_channel'] ?? 'all',
  'payment_status' => $_GET['status_scope'] ?? 'all',
];

if (($targetParams['payment_method'] ?? '') === 'upi_bank') {
  $targetParams['payment_method'] = 'bank';
}
if (($targetParams['payment_status'] ?? '') === 'paid_only') {
  $targetParams['payment_status'] = 'paid';
} elseif (($targetParams['payment_status'] ?? '') === 'payment_pending') {
  $targetParams['payment_status'] = 'pending';
} else {
  $targetParams['payment_status'] = 'all';
}

$targetParams = array_filter($targetParams, static fn($v) => $v !== '' && $v !== null);
header('Location: sales_register.php' . (!empty($targetParams) ? ('?' . http_build_query($targetParams)) : ''));
exit;

function revenue_payment_channel_label(string $paymentMethod): string
{
    $method = strtolower(trim($paymentMethod));
    if ($method === 'cod') {
        return 'Cash';
    }
    if ($method === 'upi_manual' || $method === 'gateway') {
        return 'UPI / Bank';
    }
    return strtoupper($method !== '' ? $method : 'NA');
}

function revenue_build_url(array $overrides = array()): string
{
    $params = array(
        'period' => $_GET['period'] ?? 'month',
        'from_date' => $_GET['from_date'] ?? '',
        'to_date' => $_GET['to_date'] ?? '',
        'mode' => $_GET['mode'] ?? 'net',
        'payment_channel' => $_GET['payment_channel'] ?? 'all',
    'sub_report' => $_GET['sub_report'] ?? 'overview',
    'status_scope' => $_GET['status_scope'] ?? 'paid_only',
    'reconcile_channel' => $_GET['reconcile_channel'] ?? 'all',
    'per_page' => $_GET['per_page'] ?? '20',
    'orders_page' => $_GET['orders_page'] ?? '1',
    'reconcile_page' => $_GET['reconcile_page'] ?? '1',
    );

    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }

    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
            unset($params[$k]);
        }
    }

    return 'revenue_report.php?' . http_build_query($params);
}

$periodOptions = array(
    'day' => 'Day Wise',
    'week' => 'Week Wise',
    'month' => 'Month Wise',
    'quarter' => 'Quarterly',
    'year' => 'Yearly',
    'custom' => 'Custom Range',
);

$modeOptions = array(
    'gross' => 'Gross Collection',
    'net' => 'Net Profit Mode',
);

$channelOptions = array(
    'all' => 'All Paid Methods',
    'upi_bank' => 'UPI / Bank',
    'cash' => 'Cash',
);

$reconcileOptions = array(
  'all' => 'All Collections',
  'upi_bank' => 'UPI / Bank Only',
  'cash' => 'Cash Only',
);

$statusScopeOptions = array(
  'paid_only' => 'Revenue Basis (Paid Only)',
  'pending_orders' => 'Pending Orders',
  'completed_orders' => 'Completed Orders',
  'payment_pending' => 'Payment Pending',
  'rejected_orders' => 'Rejected Orders',
);

$selectedPeriod = strtolower(trim((string)($_GET['period'] ?? 'month')));
if (!isset($periodOptions[$selectedPeriod])) {
    $selectedPeriod = 'month';
}

$selectedMode = strtolower(trim((string)($_GET['mode'] ?? 'net')));
if (!isset($modeOptions[$selectedMode])) {
    $selectedMode = 'net';
}

$paymentChannel = strtolower(trim((string)($_GET['payment_channel'] ?? 'all')));
if (!isset($channelOptions[$paymentChannel])) {
    $paymentChannel = 'all';
}

$selectedReconcileChannel = strtolower(trim((string)($_GET['reconcile_channel'] ?? 'all')));
if (!isset($reconcileOptions[$selectedReconcileChannel])) {
  $selectedReconcileChannel = 'all';
}

$perPageOptions = array(20, 50, 100);
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, $perPageOptions, true)) {
  $perPage = 20;
}

$ordersPage = max(1, (int)($_GET['orders_page'] ?? 1));
$reconcilePage = max(1, (int)($_GET['reconcile_page'] ?? 1));

$statusScope = strtolower(trim((string)($_GET['status_scope'] ?? 'paid_only')));
if (!isset($statusScopeOptions[$statusScope])) {
  $statusScope = 'paid_only';
}

$subReportOptions = array(
  'overview' => 'Overview Summary',
  'reconciliation' => 'Cash / Bank Reconciliation',
  'trend' => 'Trend Breakdown',
  'orders' => 'Paid Orders In Range',
);

$selectedSubReport = strtolower(trim((string)($_GET['sub_report'] ?? 'overview')));
if (!isset($subReportOptions[$selectedSubReport])) {
  $selectedSubReport = 'overview';
}

$customFromInput = trim((string)($_GET['from_date'] ?? ''));
$customToInput = trim((string)($_GET['to_date'] ?? ''));
$rangeError = '';
$rangeLabel = '';

$today = new DateTimeImmutable('today');
$start = $today->setTime(0, 0, 0);
$end = $today->setTime(23, 59, 59);

if ($selectedPeriod === 'day') {
    $rangeLabel = $today->format('d M Y');
} elseif ($selectedPeriod === 'week') {
    $start = $today->modify('monday this week')->setTime(0, 0, 0);
    $end = $start->modify('+6 day')->setTime(23, 59, 59);
    $rangeLabel = $start->format('d M Y') . ' to ' . $end->format('d M Y');
} elseif ($selectedPeriod === 'month') {
    $start = $today->modify('first day of this month')->setTime(0, 0, 0);
    $end = $today->modify('last day of this month')->setTime(23, 59, 59);
    $rangeLabel = $start->format('M Y');
} elseif ($selectedPeriod === 'quarter') {
    $month = (int)$today->format('n');
    $quarter = (int)floor(($month - 1) / 3) + 1;
    $quarterStartMonth = (($quarter - 1) * 3) + 1;
    $start = (new DateTimeImmutable($today->format('Y') . '-' . str_pad((string)$quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01'))->setTime(0, 0, 0);
    $end = $start->modify('+2 month')->modify('last day of this month')->setTime(23, 59, 59);
    $rangeLabel = 'Q' . $quarter . ' ' . $today->format('Y');
} elseif ($selectedPeriod === 'year') {
    $start = (new DateTimeImmutable($today->format('Y') . '-01-01'))->setTime(0, 0, 0);
    $end = (new DateTimeImmutable($today->format('Y') . '-12-31'))->setTime(23, 59, 59);
    $rangeLabel = $today->format('Y');
} elseif ($selectedPeriod === 'custom') {
    $from = DateTimeImmutable::createFromFormat('Y-m-d', $customFromInput);
    $to = DateTimeImmutable::createFromFormat('Y-m-d', $customToInput);

    if (!$from || !$to) {
        $rangeError = 'Pick valid From and To dates for custom range.';
        $selectedPeriod = 'month';
        $start = $today->modify('first day of this month')->setTime(0, 0, 0);
        $end = $today->modify('last day of this month')->setTime(23, 59, 59);
        $rangeLabel = $start->format('M Y');
    } else {
        $start = $from->setTime(0, 0, 0);
        $end = $to->setTime(23, 59, 59);
        if ($end < $start) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }
        $rangeLabel = $start->format('d M Y') . ' to ' . $end->format('d M Y');
        $customFromInput = $start->format('Y-m-d');
        $customToInput = $end->format('Y-m-d');
    }
}

$startSql = $start->format('Y-m-d H:i:s');
$endSql = $end->format('Y-m-d H:i:s');
$rangeDays = (int)$start->diff($end)->format('%a') + 1;

$channelClause = '';
if ($paymentChannel === 'cash') {
    $channelClause = ' AND payment_method = "cod" ';
} elseif ($paymentChannel === 'upi_bank') {
    $channelClause = ' AND payment_method IN ("upi_manual", "gateway") ';
}

$scopeClause = '';
$scopeBasisLabel = 'Only payment received';
if ($statusScope === 'paid_only') {
  $scopeClause = ' AND payment_status = "paid" AND order_status <> "cancelled" ';
} elseif ($statusScope === 'pending_orders') {
  $scopeClause = ' AND order_status = "pending" ';
  $scopeBasisLabel = 'Pending order pipeline';
} elseif ($statusScope === 'completed_orders') {
  $scopeClause = ' AND order_status = "completed" ';
  $scopeBasisLabel = 'Completed order pipeline';
} elseif ($statusScope === 'payment_pending') {
  $scopeClause = ' AND payment_status = "pending" ';
  $scopeBasisLabel = 'Pending payment pipeline';
} elseif ($statusScope === 'rejected_orders') {
  $scopeClause = ' AND (order_status = "cancelled" OR payment_status = "failed") ';
  $scopeBasisLabel = 'Rejected/cancelled pipeline';
}

$whereSql = ' WHERE created_at BETWEEN ? AND ? ' . $scopeClause . $channelClause;

$summary = array(
    'total_orders' => 0,
    'gross_revenue' => 0.0,
    'net_profit' => 0.0,
    'avg_order_value' => 0.0,
    'upi_bank_revenue' => 0.0,
    'cash_revenue' => 0.0,
);

$summarySql = 'SELECT
    COUNT(*) AS total_orders,
    COALESCE(SUM(grand_total), 0) AS gross_revenue,
    COALESCE(SUM((subtotal - discount_total) + tax_total + delivery_fee), 0) AS net_profit,
    COALESCE(AVG(grand_total), 0) AS avg_order_value,
    COALESCE(SUM(CASE WHEN payment_method IN ("upi_manual", "gateway") THEN grand_total ELSE 0 END), 0) AS upi_bank_revenue,
    COALESCE(SUM(CASE WHEN payment_method = "cod" THEN grand_total ELSE 0 END), 0) AS cash_revenue
  FROM orders ' . $whereSql;
$summaryStmt = $conn->prepare($summarySql);
$summaryStmt->bind_param('ss', $startSql, $endSql);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
if ($summaryResult) {
    $summaryRow = $summaryResult->fetch_assoc();
    if ($summaryRow) {
        $summary = array_merge($summary, $summaryRow);
    }
}

$bucketLabelTitle = 'Date';
$bucketLabelExpr = 'DATE(created_at)';
$bucketSortExpr = 'DATE(created_at)';
if ($selectedPeriod === 'day') {
    $bucketLabelTitle = 'Hour';
    $bucketLabelExpr = 'DATE_FORMAT(created_at, "%Y-%m-%d %H:00")';
    $bucketSortExpr = 'DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00")';
} elseif ($selectedPeriod === 'quarter' || $selectedPeriod === 'year' || ($selectedPeriod === 'custom' && $rangeDays > 62)) {
    $bucketLabelTitle = 'Month';
    $bucketLabelExpr = 'DATE_FORMAT(created_at, "%Y-%m")';
    $bucketSortExpr = 'DATE_FORMAT(created_at, "%Y-%m")';
}

$valueExpr = $selectedMode === 'net' ? '((subtotal - discount_total) + tax_total + delivery_fee)' : 'grand_total';

$trendSql = 'SELECT
    ' . $bucketLabelExpr . ' AS bucket_label,
    ' . $bucketSortExpr . ' AS bucket_sort,
    COUNT(*) AS total_orders,
    COALESCE(SUM(grand_total), 0) AS gross_revenue,
    COALESCE(SUM((subtotal - discount_total) + tax_total + delivery_fee), 0) AS net_profit,
    COALESCE(SUM(' . $valueExpr . '), 0) AS report_value
  FROM orders ' . $whereSql . '
  GROUP BY bucket_label, bucket_sort
  ORDER BY bucket_sort ASC';
$trendStmt = $conn->prepare($trendSql);
$trendStmt->bind_param('ss', $startSql, $endSql);
$trendStmt->execute();
$trendResult = $trendStmt->get_result();
$trendRows = array();
while ($trendResult && ($row = $trendResult->fetch_assoc())) {
    $trendRows[] = $row;
}

$orderCountSql = 'SELECT COUNT(*) AS total_rows FROM orders ' . $whereSql;
$orderCountStmt = $conn->prepare($orderCountSql);
$orderCountStmt->bind_param('ss', $startSql, $endSql);
$orderCountStmt->execute();
$orderCountResult = $orderCountStmt->get_result();
$orderCountRow = $orderCountResult ? $orderCountResult->fetch_assoc() : null;
$orderTotalRows = (int)($orderCountRow['total_rows'] ?? 0);
$orderTotalPages = max(1, (int)ceil($orderTotalRows / $perPage));
if ($ordersPage > $orderTotalPages) {
  $ordersPage = $orderTotalPages;
}
$ordersOffset = ($ordersPage - 1) * $perPage;

$orderRows = array();
$orderSql = 'SELECT id, order_number, customer_name, customer_phone, order_status, payment_status, payment_method, subtotal, discount_total, tax_total, delivery_fee, grand_total, created_at,
             ((subtotal - discount_total) + tax_total + delivery_fee) AS net_profit
    FROM orders ' . $whereSql . '
    ORDER BY created_at DESC, id DESC
  LIMIT ? OFFSET ?';
$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param('ssii', $startSql, $endSql, $perPage, $ordersOffset);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
while ($orderResult && ($row = $orderResult->fetch_assoc())) {
    $orderRows[] = $row;
}

$reconcileHaving = '';
if ($selectedReconcileChannel === 'upi_bank') {
  $reconcileHaving = ' HAVING bank_amount > 0 ';
} elseif ($selectedReconcileChannel === 'cash') {
  $reconcileHaving = ' HAVING cash_amount > 0 ';
}

$reconcileCountSql = 'SELECT COUNT(*) AS total_rows FROM (
  SELECT DATE(created_at) AS tx_date,
       COALESCE(SUM(CASE WHEN payment_method IN ("upi_manual", "gateway") THEN grand_total ELSE 0 END), 0) AS bank_amount,
       COALESCE(SUM(CASE WHEN payment_method = "cod" THEN grand_total ELSE 0 END), 0) AS cash_amount
    FROM orders ' . $whereSql . '
   GROUP BY tx_date' . $reconcileHaving . '
) AS reconciliation_rows';
$reconcileCountStmt = $conn->prepare($reconcileCountSql);
$reconcileCountStmt->bind_param('ss', $startSql, $endSql);
$reconcileCountStmt->execute();
$reconcileCountResult = $reconcileCountStmt->get_result();
$reconcileCountRow = $reconcileCountResult ? $reconcileCountResult->fetch_assoc() : null;
$reconcileTotalRows = (int)($reconcileCountRow['total_rows'] ?? 0);
$reconcileTotalPages = max(1, (int)ceil($reconcileTotalRows / $perPage));
if ($reconcilePage > $reconcileTotalPages) {
  $reconcilePage = $reconcileTotalPages;
}
$reconcileOffset = ($reconcilePage - 1) * $perPage;

$reconciliationRows = array();
$reconcileSql = 'SELECT DATE(created_at) AS tx_date,
  COUNT(*) AS total_orders,
  COALESCE(SUM(CASE WHEN payment_method IN ("upi_manual", "gateway") THEN grand_total ELSE 0 END), 0) AS bank_amount,
  COALESCE(SUM(CASE WHEN payment_method = "cod" THEN grand_total ELSE 0 END), 0) AS cash_amount,
  COALESCE(SUM(grand_total), 0) AS total_amount
  FROM orders ' . $whereSql . '
  GROUP BY tx_date' . $reconcileHaving . '
  ORDER BY tx_date DESC
  LIMIT ? OFFSET ?';
$reconcileStmt = $conn->prepare($reconcileSql);
$reconcileStmt->bind_param('ssii', $startSql, $endSql, $perPage, $reconcileOffset);
$reconcileStmt->execute();
$reconcileResult = $reconcileStmt->get_result();
while ($reconcileResult && ($row = $reconcileResult->fetch_assoc())) {
  $reconciliationRows[] = $row;
}

$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('Range', $rangeLabel));
    fputcsv($out, array('Mode', $modeOptions[$selectedMode]));
    fputcsv($out, array('Payment Channel', $channelOptions[$paymentChannel]));
    fputcsv($out, array('Scope', $statusScopeOptions[$statusScope]));
    fputcsv($out, array('Reconciliation Filter', $reconcileOptions[$selectedReconcileChannel]));
    fputcsv($out, array());
    fputcsv($out, array('Total Orders', 'Gross Revenue', 'Net Profit', 'Avg Order Value', 'UPI/Bank Revenue', 'Cash Revenue'));
    fputcsv($out, array(
        (int)$summary['total_orders'],
        number_format((float)$summary['gross_revenue'], 2, '.', ''),
        number_format((float)$summary['net_profit'], 2, '.', ''),
        number_format((float)$summary['avg_order_value'], 2, '.', ''),
        number_format((float)$summary['upi_bank_revenue'], 2, '.', ''),
        number_format((float)$summary['cash_revenue'], 2, '.', ''),
    ));
    fputcsv($out, array());
    fputcsv($out, array($bucketLabelTitle, 'Total Orders', 'Gross Revenue', 'Net Profit', 'Report Value'));
    foreach ($trendRows as $row) {
        fputcsv($out, array(
            (string)$row['bucket_label'],
            (int)$row['total_orders'],
            number_format((float)$row['gross_revenue'], 2, '.', ''),
            number_format((float)$row['net_profit'], 2, '.', ''),
            number_format((float)$row['report_value'], 2, '.', ''),
        ));
    }
    fputcsv($out, array());
    fputcsv($out, array('Date', 'Total Orders', 'UPI / Bank', 'Cash', 'Total'));
    foreach ($reconciliationRows as $row) {
      fputcsv($out, array(
        (string)$row['tx_date'],
        (int)$row['total_orders'],
        number_format((float)$row['bank_amount'], 2, '.', ''),
        number_format((float)$row['cash_amount'], 2, '.', ''),
        number_format((float)$row['total_amount'], 2, '.', ''),
      ));
    }
    fclose($out);
    exit;
}

if ($export === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue_report.xls"');
    echo '<table border="1">';
    echo '<tr><th colspan="6">Revenue Report</th></tr>';
    echo '<tr><td>Range</td><td colspan="5">' . htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    echo '<tr><td>Mode</td><td colspan="5">' . htmlspecialchars($modeOptions[$selectedMode], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    echo '<tr><td>Payment Channel</td><td colspan="5">' . htmlspecialchars($channelOptions[$paymentChannel], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    echo '<tr><td>Scope</td><td colspan="5">' . htmlspecialchars($statusScopeOptions[$statusScope], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    echo '<tr><td>Reconciliation Filter</td><td colspan="5">' . htmlspecialchars($reconcileOptions[$selectedReconcileChannel], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    echo '<tr><th>Total Orders</th><th>Gross Revenue</th><th>Net Profit</th><th>Avg Order Value</th><th>UPI/Bank Revenue</th><th>Cash Revenue</th></tr>';
    echo '<tr>';
    echo '<td>' . (int)$summary['total_orders'] . '</td>';
    echo '<td>' . number_format((float)$summary['gross_revenue'], 2) . '</td>';
    echo '<td>' . number_format((float)$summary['net_profit'], 2) . '</td>';
    echo '<td>' . number_format((float)$summary['avg_order_value'], 2) . '</td>';
    echo '<td>' . number_format((float)$summary['upi_bank_revenue'], 2) . '</td>';
    echo '<td>' . number_format((float)$summary['cash_revenue'], 2) . '</td>';
    echo '</tr>';
    echo '<tr><td colspan="6"></td></tr>';
    echo '<tr><th>' . htmlspecialchars($bucketLabelTitle, ENT_QUOTES, 'UTF-8') . '</th><th>Total Orders</th><th>Gross Revenue</th><th>Net Profit</th><th>Report Value</th><th>Mode</th></tr>';
    foreach ($trendRows as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)$row['bucket_label'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . (int)$row['total_orders'] . '</td>';
        echo '<td>' . number_format((float)$row['gross_revenue'], 2) . '</td>';
        echo '<td>' . number_format((float)$row['net_profit'], 2) . '</td>';
        echo '<td>' . number_format((float)$row['report_value'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($modeOptions[$selectedMode], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '<tr><td colspan="6"></td></tr>';
    echo '<tr><th>Date</th><th>Total Orders</th><th>UPI / Bank</th><th>Cash</th><th>Total</th><th>Channel</th></tr>';
    foreach ($reconciliationRows as $row) {
      echo '<tr>';
      echo '<td>' . htmlspecialchars((string)$row['tx_date'], ENT_QUOTES, 'UTF-8') . '</td>';
      echo '<td>' . (int)$row['total_orders'] . '</td>';
      echo '<td>' . number_format((float)$row['bank_amount'], 2) . '</td>';
      echo '<td>' . number_format((float)$row['cash_amount'], 2) . '</td>';
      echo '<td>' . number_format((float)$row['total_amount'], 2) . '</td>';
      echo '<td>' . htmlspecialchars($reconcileOptions[$selectedReconcileChannel], ENT_QUOTES, 'UTF-8') . '</td>';
      echo '</tr>';
    }
    echo '</table>';
    exit;
}

if ($export === 'pdf') {
    $printTitle = 'Revenue Report - ' . $rangeLabel;
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($printTitle, ENT_QUOTES, 'UTF-8') . '</title><style>
      body{font-family:Arial,Helvetica,sans-serif;color:#111;background:#fff;margin:18px}
      h1,h2{margin:0 0 10px}
      .meta{margin-bottom:14px;font-size:13px}
      table{width:100%;border-collapse:collapse;margin-top:12px}
      th,td{border:1px solid #111;padding:7px;font-size:12px;text-align:left}
      th{background:#efefef}
      @media print{body{margin:0;padding:12px}}
    </style></head><body>';
    echo '<h1>Cakeouflage Revenue Report</h1>';
    echo '<div class="meta"><div><strong>Range:</strong> ' . htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') . '</div><div><strong>Mode:</strong> ' . htmlspecialchars($modeOptions[$selectedMode], ENT_QUOTES, 'UTF-8') . '</div><div><strong>Channel:</strong> ' . htmlspecialchars($channelOptions[$paymentChannel], ENT_QUOTES, 'UTF-8') . '</div><div><strong>Scope:</strong> ' . htmlspecialchars($statusScopeOptions[$statusScope], ENT_QUOTES, 'UTF-8') . '</div><div><strong>Basis:</strong> ' . htmlspecialchars($scopeBasisLabel, ENT_QUOTES, 'UTF-8') . '</div></div>';
    echo '<table><tr><th>Total Orders</th><th>Gross Revenue</th><th>Net Profit</th><th>UPI/Bank</th><th>Cash</th></tr>';
    echo '<tr><td>' . (int)$summary['total_orders'] . '</td><td>Rs ' . number_format((float)$summary['gross_revenue'], 2) . '</td><td>Rs ' . number_format((float)$summary['net_profit'], 2) . '</td><td>Rs ' . number_format((float)$summary['upi_bank_revenue'], 2) . '</td><td>Rs ' . number_format((float)$summary['cash_revenue'], 2) . '</td></tr></table>';
    echo '<h2>Trend Breakdown</h2><table><tr><th>' . htmlspecialchars($bucketLabelTitle, ENT_QUOTES, 'UTF-8') . '</th><th>Total Orders</th><th>Gross Revenue</th><th>Net Profit</th><th>Report Value</th></tr>';
    foreach ($trendRows as $row) {
        echo '<tr><td>' . htmlspecialchars((string)$row['bucket_label'], ENT_QUOTES, 'UTF-8') . '</td><td>' . (int)$row['total_orders'] . '</td><td>Rs ' . number_format((float)$row['gross_revenue'], 2) . '</td><td>Rs ' . number_format((float)$row['net_profit'], 2) . '</td><td>Rs ' . number_format((float)$row['report_value'], 2) . '</td></tr>';
    }
      echo '</table>';
      echo '<h2>Cash / Bank Reconciliation</h2><table><tr><th>Date</th><th>Total Orders</th><th>UPI / Bank</th><th>Cash</th><th>Total</th></tr>';
      foreach ($reconciliationRows as $row) {
        echo '<tr><td>' . htmlspecialchars((string)$row['tx_date'], ENT_QUOTES, 'UTF-8') . '</td><td>' . (int)$row['total_orders'] . '</td><td>Rs ' . number_format((float)$row['bank_amount'], 2) . '</td><td>Rs ' . number_format((float)$row['cash_amount'], 2) . '</td><td>Rs ' . number_format((float)$row['total_amount'], 2) . '</td></tr>';
      }
      echo '</table><script>window.print();</script></body></html>';
    exit;
}

include __DIR__ . '/layout.php';
?>
<style>
  .revenue-shell { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 18px; align-items: start; }
  .revenue-side,.revenue-card,.revenue-panel { background: var(--admin-surface, #fffdfd); border-radius: 18px; border: 1px solid rgba(128, 0, 31, 0.1); box-shadow: 0 14px 30px rgba(96, 18, 45, 0.08); overflow: hidden; }
  .revenue-side { position: sticky; top: 12px; }
  .revenue-side__head { padding: 18px 20px 10px; border-bottom: 1px solid rgba(128, 0, 31, 0.08); background: linear-gradient(180deg, #fff8fa 0%, #fff 100%); }
  .revenue-side__head h2 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
  .revenue-side__head p { margin: 6px 0 0; color: #8f7681; font-size: 0.88rem; }
  .revenue-menu { list-style: none; margin: 0; padding: 10px; display: grid; gap: 8px; }
  .revenue-menu a { display: block; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(128, 0, 31, 0.14); color: #80001F; text-decoration: none; font-size: 0.9rem; font-weight: 700; }
  .revenue-menu a.active { background: #80001F; color: #fff; border-color: #80001F; }
  .revenue-main { display: grid; gap: 18px; }
  .revenue-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
  .revenue-card,.revenue-panel { background: var(--admin-surface, #fffdfd); border-radius: 18px; border: 1px solid rgba(128, 0, 31, 0.1); box-shadow: 0 14px 30px rgba(96, 18, 45, 0.08); overflow: hidden; }
  .revenue-card { padding: 18px 18px 16px; }
  .revenue-card strong { display: block; color: #80001F; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 7px; }
  .revenue-card span { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.9rem; color: #2d1f25; }
  .revenue-panel__head { padding: 18px 20px 12px; border-bottom: 1px solid rgba(128, 0, 31, 0.08); background: linear-gradient(180deg, #fff8fa 0%, #fff 100%); }
  .revenue-panel__head h2,.revenue-panel__head h3 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
  .revenue-panel__head p { margin: 6px 0 0; color: #8f7681; font-size: 0.92rem; }
  .revenue-panel__body { padding: 20px; }
  .revenue-periods { display: flex; flex-wrap: wrap; gap: 9px; margin-bottom: 14px; }
  .revenue-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 0 12px; border-radius: 999px; border: 1px solid rgba(128, 0, 31, 0.18); color: #80001F; text-decoration: none; font-size: 0.84rem; font-weight: 600; background: #fff; }
  .revenue-pill.is-active { background: #80001F; color: #fff; border-color: #80001F; }
  .revenue-filters { display: grid; gap: 10px; margin: 10px 0 14px; }
  .revenue-filters .row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .revenue-filters label { font-size: 0.83rem; color: #6e2a3e; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
  .revenue-filters select, .revenue-filters input { border: 1px solid rgba(128, 0, 31, 0.18); border-radius: 10px; padding: 9px 11px; font: inherit; min-width: 180px; }
  .revenue-btn { min-height: 38px; padding: 0 14px; border-radius: 10px; border: 0; background: #80001F; color: #fff; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .revenue-btn.ghost { background: #fff; border: 1px solid rgba(128, 0, 31, 0.25); color: #80001F; }
  .revenue-table-wrap { overflow: auto; }
  .revenue-table { width: 100%; border-collapse: collapse; min-width: 980px; }
  .revenue-table th,.revenue-table td { padding: 11px 10px; border-bottom: 1px solid rgba(128, 0, 31, 0.08); text-align: left; vertical-align: top; }
  .revenue-table th { font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: #80001F; background: #fff6f8; }
  .status-pill { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; background: #dcfce7; color: #166534; }
  .revenue-range { margin-top: 6px; color: #6e2a3e; font-size: 0.9rem; }
  .revenue-error { margin: 10px 0; color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; border-radius: 10px; padding: 9px 12px; font-size: 0.88rem; }
  .revenue-pagination { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 12px; }
  .revenue-pagination__meta { color: #6e2a3e; font-size: 0.85rem; }
  @media (max-width: 1180px) {
    .revenue-shell { grid-template-columns: 1fr; }
    .revenue-side { position: static; }
  }
  @media (max-width: 1080px) { .revenue-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 760px) { .revenue-grid { grid-template-columns: 1fr; } }
</style>

<div class="revenue-shell">
  <aside class="revenue-side">
    <div class="revenue-side__head">
      <h2>Revenue Report</h2>
      <p>Main category with focused sub-reports. Open one module at a time.</p>
    </div>
    <ul class="revenue-menu">
      <?php foreach ($subReportOptions as $key => $label): ?>
        <li><a class="<?= $selectedSubReport === $key ? 'active' : '' ?>" href="<?= htmlspecialchars(revenue_build_url(array('sub_report' => $key, 'orders_page' => 1, 'reconcile_page' => 1)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <div class="revenue-main">
    <?php if ($selectedSubReport === 'overview'): ?>
      <div class="revenue-grid">
        <div class="revenue-card"><strong>Total Paid Orders</strong><span><?= (int)$summary['total_orders'] ?></span></div>
        <div class="revenue-card"><strong>Gross Collection</strong><span>Rs <?= number_format((float)$summary['gross_revenue'], 2) ?></span></div>
        <div class="revenue-card"><strong>Net Profit Mode</strong><span>Rs <?= number_format((float)$summary['net_profit'], 2) ?></span></div>
        <div class="revenue-card"><strong>Avg Order Value</strong><span>Rs <?= number_format((float)$summary['avg_order_value'], 2) ?></span></div>
      </div>
    <?php endif; ?>

    <section class="revenue-panel">
    <div class="revenue-panel__head">
      <h2><?= htmlspecialchars($subReportOptions[$selectedSubReport], ENT_QUOTES, 'UTF-8') ?></h2>
      <p>Filters are scoped to the selected report module.</p>
    </div>
    <div class="revenue-panel__body">
      <div class="revenue-periods">
        <?php foreach ($periodOptions as $key => $label): ?>
          <a class="revenue-pill <?= $selectedPeriod === $key ? 'is-active' : '' ?>" href="<?= htmlspecialchars(revenue_build_url(array('period' => $key)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </div>

      <form class="revenue-filters" method="get">
        <div class="row">
          <label for="mode">Report Mode</label>
          <select id="mode" name="mode">
            <?php foreach ($modeOptions as $key => $label): ?>
              <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedMode === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>

          <label for="payment_channel">Collection Type</label>
          <select id="payment_channel" name="payment_channel">
            <?php foreach ($channelOptions as $key => $label): ?>
              <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $paymentChannel === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>

          <?php if ($selectedSubReport === 'reconciliation'): ?>
            <label for="reconcile_channel">Reconciliation Filter</label>
            <select id="reconcile_channel" name="reconcile_channel">
              <?php foreach ($reconcileOptions as $key => $label): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedReconcileChannel === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>

          <label for="status_scope">Report Scope</label>
          <select id="status_scope" name="status_scope">
            <?php foreach ($statusScopeOptions as $key => $label): ?>
              <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $statusScope === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>

          <label for="per_page">Rows / Page</label>
          <select id="per_page" name="per_page">
            <?php foreach ($perPageOptions as $size): ?>
              <option value="<?= (int)$size ?>" <?= $perPage === (int)$size ? 'selected' : '' ?>><?= (int)$size ?></option>
            <?php endforeach; ?>
          </select>

          <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="sub_report" value="<?= htmlspecialchars($selectedSubReport, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="orders_page" value="1">
          <input type="hidden" name="reconcile_page" value="1">
          <button class="revenue-btn" type="submit">Apply Filters</button>
        </div>

        <div class="row">
          <label for="from_date">From Date</label>
          <input id="from_date" type="date" name="from_date" value="<?= htmlspecialchars($customFromInput !== '' ? $customFromInput : $today->format('Y-m-01'), ENT_QUOTES, 'UTF-8') ?>">
          <label for="to_date">To Date</label>
          <input id="to_date" type="date" name="to_date" value="<?= htmlspecialchars($customToInput !== '' ? $customToInput : $today->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="period" value="custom">
          <input type="hidden" name="sub_report" value="<?= htmlspecialchars($selectedSubReport, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="orders_page" value="1">
          <input type="hidden" name="reconcile_page" value="1">
          <button class="revenue-btn ghost" type="submit">Apply Custom Range</button>
        </div>
      </form>

      <div class="row" style="display:flex; gap:8px; flex-wrap:wrap; margin:10px 0 0;">
        <a class="revenue-btn ghost" href="<?= htmlspecialchars(revenue_build_url(array('export' => 'csv')), ENT_QUOTES, 'UTF-8') ?>">Export CSV</a>
        <a class="revenue-btn ghost" href="<?= htmlspecialchars(revenue_build_url(array('export' => 'excel')), ENT_QUOTES, 'UTF-8') ?>">Export Excel</a>
        <a class="revenue-btn ghost" href="<?= htmlspecialchars(revenue_build_url(array('export' => 'pdf')), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Export PDF</a>
      </div>

      <?php if ($rangeError !== ''): ?>
        <p class="revenue-error"><?= htmlspecialchars($rangeError, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>

      <p class="revenue-range"><strong>Active Range:</strong> <?= htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') ?> | <strong>Mode:</strong> <?= htmlspecialchars($modeOptions[$selectedMode], ENT_QUOTES, 'UTF-8') ?> | <strong>Collection:</strong> <?= htmlspecialchars($channelOptions[$paymentChannel], ENT_QUOTES, 'UTF-8') ?></p>
      <p class="revenue-range"><strong>Scope:</strong> <?= htmlspecialchars($statusScopeOptions[$statusScope], ENT_QUOTES, 'UTF-8') ?> | <strong>Basis:</strong> <?= htmlspecialchars($scopeBasisLabel, ENT_QUOTES, 'UTF-8') ?></p>
      <p class="revenue-range"><strong>UPI / Bank:</strong> Rs <?= number_format((float)$summary['upi_bank_revenue'], 2) ?> | <strong>Cash:</strong> Rs <?= number_format((float)$summary['cash_revenue'], 2) ?></p>
      <?php if ($selectedSubReport === 'reconciliation'): ?>
        <p class="revenue-range"><strong>Reconciliation:</strong> <?= htmlspecialchars($reconcileOptions[$selectedReconcileChannel], ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($selectedSubReport === 'reconciliation'): ?>
    <section class="revenue-panel">
    <div class="revenue-panel__head">
      <h3>Cash / Bank Reconciliation</h3>
      <p>Date-wise split to compare bank settlement and cash handover against order totals.</p>
    </div>
    <div class="revenue-panel__body">
      <div class="revenue-table-wrap">
        <table class="revenue-table">
          <tr>
            <th>Date</th>
            <th>Total Orders</th>
            <th>UPI / Bank</th>
            <th>Cash</th>
            <th>Total</th>
          </tr>
          <?php if (!$reconciliationRows): ?>
            <tr><td colspan="5">No rows found for selected reconciliation filter.</td></tr>
          <?php endif; ?>
          <?php foreach ($reconciliationRows as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string)($row['tx_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int)($row['total_orders'] ?? 0) ?></td>
              <td>Rs <?= number_format((float)($row['bank_amount'] ?? 0), 2) ?></td>
              <td>Rs <?= number_format((float)($row['cash_amount'] ?? 0), 2) ?></td>
              <td>Rs <?= number_format((float)($row['total_amount'] ?? 0), 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="revenue-pagination">
        <span class="revenue-pagination__meta">Showing <?= count($reconciliationRows) ?> of <?= (int)$reconcileTotalRows ?> rows</span>
        <?php if ($reconcilePage > 1): ?>
          <a class="revenue-btn ghost" href="<?= htmlspecialchars(revenue_build_url(array('reconcile_page' => $reconcilePage - 1)), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>
        <?php if ($reconcilePage < $reconcileTotalPages): ?>
          <a class="revenue-btn ghost" href="<?= htmlspecialchars(revenue_build_url(array('reconcile_page' => $reconcilePage + 1)), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
    </section>
  <?php endif; ?>

  <?php if ($selectedSubReport === 'trend'): ?>
    <section class="revenue-panel">
    <div class="revenue-panel__head">
      <h3>Trend Breakdown</h3>
      <p>Bucketed view for <?= htmlspecialchars($periodOptions[$selectedPeriod], ENT_QUOTES, 'UTF-8') ?> based on <?= htmlspecialchars($modeOptions[$selectedMode], ENT_QUOTES, 'UTF-8') ?>.</p>
    </div>
    <div class="revenue-panel__body">
      <div class="revenue-table-wrap">
        <table class="revenue-table">
          <tr>
            <th><?= htmlspecialchars($bucketLabelTitle, ENT_QUOTES, 'UTF-8') ?></th>
            <th>Total Paid Orders</th>
            <th>Gross Revenue</th>
            <th>Net Profit</th>
            <th>Report Value</th>
          </tr>
          <?php if (!$trendRows): ?>
            <tr><td colspan="5">No paid orders found in this period.</td></tr>
          <?php endif; ?>
          <?php foreach ($trendRows as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string)$row['bucket_label'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int)($row['total_orders'] ?? 0) ?></td>
              <td>Rs <?= number_format((float)($row['gross_revenue'] ?? 0), 2) ?></td>
              <td>Rs <?= number_format((float)($row['net_profit'] ?? 0), 2) ?></td>
              <td>Rs <?= number_format((float)($row['report_value'] ?? 0), 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    </section>
  <?php endif; ?>

  <?php if ($selectedSubReport === 'orders'): ?>
    <section class="revenue-panel">
    <div class="revenue-panel__head">
      <h3>Paid Orders In Range</h3>
      <p>Paginated order list for fast loading and quick drill-down.</p>
    </div>
    <div class="revenue-panel__body">
      <div class="revenue-table-wrap">
        <table class="revenue-table">
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Collection Type</th>
            <th>Date</th>
            <th>Gross</th>
            <th>Net Profit</th>
            <th>Details</th>
          </tr>
          <?php if (!$orderRows): ?>
            <tr><td colspan="7">No paid orders available for this period.</td></tr>
          <?php endif; ?>
          <?php foreach ($orderRows as $order): ?>
            <tr>
              <td><?= htmlspecialchars((string)$order['order_number'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$order['customer_name'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars((string)$order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></small></td>
              <td><span class="status-pill"><?= htmlspecialchars(revenue_payment_channel_label((string)$order['payment_method']), ENT_QUOTES, 'UTF-8') ?></span></td>
              <td><?= htmlspecialchars((string)$order['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>Rs <?= number_format((float)($order['grand_total'] ?? 0), 2) ?></td>
              <td>Rs <?= number_format((float)($order['net_profit'] ?? 0), 2) ?></td>
              <td><a class="revenue-btn ghost" href="order_details.php?id=<?= (int)$order['id'] ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="revenue-pagination">
        <span class="revenue-pagination__meta">Showing <?= count($orderRows) ?> of <?= (int)$orderTotalRows ?> orders</span>
        <?php if ($ordersPage > 1): ?>
          <a class="revenue-btn ghost" href="<?= htmlspecialchars(revenue_build_url(array('orders_page' => $ordersPage - 1)), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>
        <?php if ($ordersPage < $orderTotalPages): ?>
          <a class="revenue-btn ghost" href="<?= htmlspecialchars(revenue_build_url(array('orders_page' => $ordersPage + 1)), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
    </section>
  <?php endif; ?>

  <?php if ($selectedSubReport === 'overview'): ?>
    <section class="revenue-panel">
      <div class="revenue-panel__head">
        <h3>Overview Snapshot</h3>
        <p>Headline numbers for the selected period and filter scope.</p>
      </div>
      <div class="revenue-panel__body">
        <div class="revenue-table-wrap">
          <table class="revenue-table" style="min-width: 640px;">
            <tr>
              <th>Metric</th>
              <th>Value</th>
            </tr>
            <tr><td>Total Paid Orders</td><td><?= (int)$summary['total_orders'] ?></td></tr>
            <tr><td>Gross Collection</td><td>Rs <?= number_format((float)$summary['gross_revenue'], 2) ?></td></tr>
            <tr><td>Net Profit</td><td>Rs <?= number_format((float)$summary['net_profit'], 2) ?></td></tr>
            <tr><td>Average Order Value</td><td>Rs <?= number_format((float)$summary['avg_order_value'], 2) ?></td></tr>
            <tr><td>UPI / Bank Collection</td><td>Rs <?= number_format((float)$summary['upi_bank_revenue'], 2) ?></td></tr>
            <tr><td>Cash Collection</td><td>Rs <?= number_format((float)$summary['cash_revenue'], 2) ?></td></tr>
          </table>
        </div>
      </div>
    </section>
  <?php endif; ?>
  </div>
</div>
