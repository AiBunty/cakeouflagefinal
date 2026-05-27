<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_admin_login();

if (!admin_has_permission('can_approve_refund')
    && !admin_has_permission('can_force_refund')
    && !admin_has_permission('can_view_refund_reports')
) {
    http_response_code(403);
    echo '<h2>Access Denied</h2><p>You do not have permission to view refund reports.</p>';
    exit;
}

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo   = trim((string)($_GET['date_to']   ?? date('Y-m-d')));

// Validate date format; reject anything that doesn't match YYYY-MM-DD to prevent SQL injection
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$dateFromEsc = $conn->real_escape_string($dateFrom);
$dateToEsc   = $conn->real_escape_string($dateTo);

$hasSettlementReference = false;
$hasSettlementProofUrl = false;
$hasAdminFullName = false;
$hasAdminFirstName = false;
$hasAdminLastName = false;

$schemaCols = $conn->query(
  "SELECT TABLE_NAME, COLUMN_NAME
   FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND (
         (TABLE_NAME = 'refund_transactions' AND COLUMN_NAME IN ('settlement_reference', 'settlement_proof_url'))
     OR (TABLE_NAME = 'admins' AND COLUMN_NAME IN ('full_name', 'first_name', 'last_name'))
     )"
);
while ($schemaCols && ($col = $schemaCols->fetch_assoc())) {
  $table = (string)($col['TABLE_NAME'] ?? '');
  $name = (string)($col['COLUMN_NAME'] ?? '');
  if ($table === 'refund_transactions' && $name === 'settlement_reference') {
    $hasSettlementReference = true;
  }
  if ($table === 'refund_transactions' && $name === 'settlement_proof_url') {
    $hasSettlementProofUrl = true;
  }
  if ($table === 'admins' && $name === 'full_name') {
    $hasAdminFullName = true;
  }
  if ($table === 'admins' && $name === 'first_name') {
    $hasAdminFirstName = true;
  }
  if ($table === 'admins' && $name === 'last_name') {
    $hasAdminLastName = true;
  }
}

$settlementRefExpr = $hasSettlementReference
  ? 'rt.settlement_reference AS settlement_reference'
  : "'' AS settlement_reference";

$settlementProofExpr = $hasSettlementProofUrl
  ? 'rt.settlement_proof_url AS settlement_proof_url'
  : "'' AS settlement_proof_url";

if ($hasAdminFullName) {
  $processedByExpr = 'COALESCE(a.full_name, "") AS processed_by_name';
} elseif ($hasAdminFirstName || $hasAdminLastName) {
  $firstExpr = $hasAdminFirstName ? 'COALESCE(a.first_name, "")' : '""';
  $lastExpr = $hasAdminLastName ? 'COALESCE(a.last_name, "")' : '""';
  $processedByExpr = "TRIM(CONCAT($firstExpr, ' ', $lastExpr)) AS processed_by_name";
} else {
  $processedByExpr = '"" AS processed_by_name';
}

$baseSql =
    "FROM refund_transactions rt
     JOIN orders o ON o.id = rt.order_id
     LEFT JOIN admins a ON a.id = rt.approved_by_admin_id
     WHERE rt.status = 'processed'
       AND DATE(rt.processed_at) BETWEEN '$dateFromEsc' AND '$dateToEsc'";

$countResult = false;
try {
  $countResult = safeQuery($conn, "SELECT COUNT(*) AS n $baseSql");
} catch (\Throwable $e) {
  error_log('[admin-refund-report] count query failed: ' . $e->getMessage());
}
$countRow    = $countResult ? $countResult->fetch_assoc() : null;
$total       = (int)($countRow['n'] ?? 0);
$totalPages  = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listResult = false;
try {
  $listResult = safeQuery($conn,
  "SELECT rt.id, rt.refund_number, rt.order_id, rt.refund_type, rt.reason_code,
      rt.reason_notes, rt.approved_amount, $settlementRefExpr,
        $settlementProofExpr, rt.processed_at,
            o.order_number, o.customer_name, o.customer_email, o.customer_phone,
            o.grand_total,
      $processedByExpr
     $baseSql
     ORDER BY rt.processed_at DESC
     LIMIT $perPage OFFSET $offset"
  );
} catch (\Throwable $e) {
  error_log('[admin-refund-report] list query failed: ' . $e->getMessage());
}
$rows = [];
while ($listResult && ($r = $listResult->fetch_assoc())) {
    $rows[] = $r;
}

// Totals for summary bar
$summaryResult = false;
try {
  $summaryResult = safeQuery($conn,
    "SELECT
         COUNT(*) AS total_count,
         SUM(rt.approved_amount) AS total_amount,
         SUM(rt.refund_type = 'partial') AS partial_count,
         SUM(rt.refund_type = 'full') AS full_count
     $baseSql"
  );
} catch (\Throwable $e) {
  error_log('[admin-refund-report] summary query failed: ' . $e->getMessage());
}
$summary = $summaryResult ? $summaryResult->fetch_assoc() : [];
$summaryTotal   = (float)($summary['total_amount']  ?? 0);
$summaryCount   = (int)  ($summary['total_count']   ?? 0);
$partialCount   = (int)  ($summary['partial_count'] ?? 0);
$fullCount      = (int)  ($summary['full_count']    ?? 0);

$pageTitle = 'Refund Report';
require_once __DIR__ . '/layout.php';
?>
<style>
.rr-shell { background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:18px; box-shadow:0 14px 28px rgba(68,16,34,.08); overflow:hidden; margin-bottom:32px; }
.rr-head { display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid rgba(128,0,31,.09); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); flex-wrap:wrap; gap:10px; }
.rr-head h3 { margin:0; font-family:'DM Serif Display',Georgia,serif; color:#80001F; font-size:1.35rem; font-weight:400; }
.rr-filter { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; }
.rr-filter label { font-size:.78rem; font-weight:600; color:#5f4c55; display:block; margin-bottom:3px; }
.rr-filter input[type=date] { padding:7px 10px; border:1px solid rgba(128,0,31,.2); border-radius:8px; font-size:.82rem; }
.rr-filter button { background:#80001F; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; }
.rr-filter button:hover { background:#5f0017; }
.rr-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; padding:16px 20px; border-bottom:1px solid rgba(128,0,31,.07); background:#fffbfc; }
.rr-stat { border:1px solid rgba(128,0,31,.1); border-radius:14px; padding:14px 16px; }
.rr-stat .lbl { font-size:.72rem; font-weight:600; color:#7f6973; letter-spacing:.05em; text-transform:uppercase; margin-bottom:4px; }
.rr-stat .val { font-size:1.45rem; font-weight:700; color:#80001F; font-family:'DM Serif Display',Georgia,serif; }
.rr-stat .sub { font-size:.74rem; color:#9c8590; margin-top:2px; }
.rr-table-wrap { overflow-x:auto; }
table.rr-table { width:100%; border-collapse:collapse; font-size:.84rem; }
table.rr-table th { background:#fff8fa; color:#80001F; font-size:.71rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; padding:10px 12px; text-align:left; border-bottom:1px solid rgba(128,0,31,.1); white-space:nowrap; }
table.rr-table td { padding:11px 12px; border-bottom:1px solid rgba(128,0,31,.06); vertical-align:top; }
table.rr-table tr:last-child td { border-bottom:none; }
table.rr-table tr:hover td { background:#fff9fb; }
.rr-type { display:inline-block; padding:3px 9px; border-radius:999px; font-size:.68rem; font-weight:700; text-transform:uppercase; }
.rr-type--full { background:#dcfce7; color:#166534; }
.rr-type--partial { background:#ede9fe; color:#5b21b6; }
.rr-pagination { display:flex; gap:8px; align-items:center; padding:12px 20px; font-size:.82rem; color:#7f6973; }
.rr-pagination a { color:#80001F; text-decoration:none; padding:5px 10px; border:1px solid rgba(128,0,31,.2); border-radius:8px; }
.rr-pagination a:hover { background:#fff6f8; }
.rr-empty { padding:40px 20px; text-align:center; color:#9c8590; font-size:.9rem; }
</style>

<div class="rr-shell">

  <!-- Header + Filters -->
  <div class="rr-head">
    <h3>Refund Report</h3>
    <form method="GET" action="refund_report.php" class="rr-filter">
      <div>
        <label for="rr-date-from">From</label>
        <input type="date" id="rr-date-from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
      </div>
      <div>
        <label for="rr-date-to">To</label>
        <input type="date" id="rr-date-to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
      </div>
      <button type="submit">Filter</button>
    </form>
  </div>

  <!-- Summary KPIs -->
  <div class="rr-summary">
    <div class="rr-stat">
      <div class="lbl">Total Processed</div>
      <div class="val"><?php echo $summaryCount; ?></div>
      <div class="sub"><?php echo $partialCount; ?> partial &bull; <?php echo $fullCount; ?> full</div>
    </div>
    <div class="rr-stat">
      <div class="lbl">Total Refunded</div>
      <div class="val">&#8377;<?php echo number_format($summaryTotal, 0); ?></div>
      <div class="sub"><?php echo htmlspecialchars($dateFrom); ?> &rarr; <?php echo htmlspecialchars($dateTo); ?></div>
    </div>
    <div class="rr-stat">
      <div class="lbl">Partial Refunds</div>
      <div class="val" style="color:#7c3aed;"><?php echo $partialCount; ?></div>
      <div class="sub">Orders partially refunded</div>
    </div>
    <div class="rr-stat">
      <div class="lbl">Full Refunds</div>
      <div class="val" style="color:#166534;"><?php echo $fullCount; ?></div>
      <div class="sub">Orders fully refunded</div>
    </div>
  </div>

  <!-- Data Table -->
  <?php if (empty($rows)): ?>
    <div class="rr-empty">No processed refunds found for the selected date range.</div>
  <?php else: ?>
  <div class="rr-table-wrap">
    <table class="rr-table">
      <thead>
        <tr>
          <th>Refund #</th>
          <th>Order</th>
          <th>Customer</th>
          <th>Type</th>
          <th>Refund Amt</th>
          <th>Order Total</th>
          <th>Reason</th>
          <th>Reference</th>
          <th>Processed At</th>
          <th>By</th>
          <th>Proof</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
            $typeClass = (string)($r['refund_type'] ?? '') === 'full' ? 'rr-type--full' : 'rr-type--partial';
        ?>
        <tr>
          <td style="font-weight:700;">
            <a href="order_details.php?id=<?php echo (int)$r['order_id']; ?>" style="color:#80001F;">
              <?php echo htmlspecialchars((string)$r['refund_number'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </td>
          <td>
            <a href="order_details.php?id=<?php echo (int)$r['order_id']; ?>" style="color:#80001F;">
              <?php echo htmlspecialchars((string)$r['order_number'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </td>
          <td>
            <?php echo htmlspecialchars((string)$r['customer_name'], ENT_QUOTES, 'UTF-8'); ?>
            <div style="font-size:.74rem;color:#8f7681;"><?php echo htmlspecialchars((string)$r['customer_email'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div style="font-size:.74rem;color:#8f7681;"><?php echo htmlspecialchars((string)$r['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></div>
          </td>
          <td><span class="rr-type <?php echo $typeClass; ?>"><?php echo htmlspecialchars(ucfirst((string)$r['refund_type']), ENT_QUOTES, 'UTF-8'); ?></span></td>
          <td style="font-weight:700;">&#8377;<?php echo number_format((float)$r['approved_amount'], 2); ?></td>
          <td>&#8377;<?php echo number_format((float)$r['grand_total'], 2); ?></td>
          <td>
            <?php echo htmlspecialchars((string)$r['reason_code'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($r['reason_notes'])): ?>
              <div style="font-size:.73rem;color:#8f7681;"><?php echo htmlspecialchars((string)$r['reason_notes'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;">
            <?php echo htmlspecialchars((string)($r['settlement_reference'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
          </td>
          <td style="white-space:nowrap;font-size:.78rem;">
            <?php echo htmlspecialchars((string)$r['processed_at'], ENT_QUOTES, 'UTF-8'); ?>
          </td>
          <td style="font-size:.78rem;">
            <?php echo htmlspecialchars(trim((string)$r['processed_by_name']) ?: '—', ENT_QUOTES, 'UTF-8'); ?>
          </td>
          <td>
            <?php if (!empty($r['settlement_proof_url'])): ?>
              <a href="/<?php echo htmlspecialchars((string)$r['settlement_proof_url'], ENT_QUOTES, 'UTF-8'); ?>"
                 target="_blank" rel="noopener" style="color:#80001F;font-size:.78rem;">View</a>
            <?php else: ?>
              <span style="color:#bbb;font-size:.78rem;">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="rr-pagination">
    <?php if ($page > 1): ?>
      <a href="?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&page=<?php echo $page - 1; ?>">&laquo; Prev</a>
    <?php endif; ?>
    <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
    <?php if ($page < $totalPages): ?>
      <a href="?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>
