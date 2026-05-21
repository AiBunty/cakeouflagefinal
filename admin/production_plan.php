<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

$tz = new DateTimeZone('Asia/Kolkata');
$now = new DateTimeImmutable('now', $tz);

// ── Single-order print mode ──────────────────────────────────────────────────
$singleOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($singleOrderId > 0) {
    $oStmt = $conn->prepare('SELECT o.*, GROUP_CONCAT(oi.id) AS item_ids FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.id = ? GROUP BY o.id LIMIT 1');
    $oStmt->bind_param('i', $singleOrderId);
    $oStmt->execute();
    $singleOrder = ($oStmt->get_result())->fetch_assoc();
    $oStmt->close();

    $singleItems = [];
    if ($singleOrder) {
        $iStmt = $conn->prepare('SELECT product_name_snapshot, variant_snapshot, quantity, unit_price, line_total, cake_message, topper_name_snapshot, topper_price_snapshot, customisation_note FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $iStmt->bind_param('i', $singleOrderId);
        $iStmt->execute();
        $iRes = $iStmt->get_result();
        while ($iRes && ($iRow = $iRes->fetch_assoc())) {
            $singleItems[] = $iRow;
        }
        $iStmt->close();
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Production Plan — Order #<?= (int)$singleOrderId ?></title>
<style>
  body { font-family: Arial, sans-serif; margin: 32px; color: #1d1115; }
  h1 { color: #80001F; font-size: 22px; margin-bottom: 4px; }
  .meta { font-size: 13px; color: #5a3044; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  th { background: #80001F; color: #fff; padding: 8px; text-align: left; font-size: 13px; }
  td { padding: 8px; border-bottom: 1px solid #f0d7df; font-size: 13px; vertical-align: top; }
  .note { background: #fff4f7; border-radius: 6px; padding: 4px 8px; font-size: 12px; color: #5b1f3a; margin-top: 4px; }
  .signature { border-top: 1px dashed #ccc; margin-top: 30px; padding-top: 16px; display: flex; justify-content: space-between; font-size: 12px; color: #888; }
  @media print { button, .no-print { display: none !important; } }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:16px;">
  <button onclick="window.print()" style="background:#80001F;color:#fff;border:none;border-radius:8px;padding:8px 18px;cursor:pointer;font-size:14px;">🖨 Print</button>
  &nbsp;
  <a href="order_details.php?id=<?= (int)$singleOrderId ?>" style="font-size:13px;color:#80001F;">← Back to Order</a>
</div>
<h1>🎂 Production Sheet — #<?= htmlspecialchars((string)($singleOrder['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
<div class="meta">
  Customer: <strong><?= htmlspecialchars((string)($singleOrder['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
  &nbsp;|&nbsp; Phone: <strong><?= htmlspecialchars((string)($singleOrder['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
  &nbsp;|&nbsp; Slot: <strong><?= htmlspecialchars((string)($singleOrder['scheduled_slot_label'] ?? $singleOrder['scheduled_slot'] ?? 'Not scheduled'), ENT_QUOTES, 'UTF-8') ?></strong>
  &nbsp;|&nbsp; Printed: <?= $now->format('d M Y, h:i A') ?> IST
</div>
<?php if (empty($singleItems)): ?>
  <p>No items found for this order.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Item / Variant</th>
      <th>Qty</th>
      <th>Topper</th>
      <th>Note on Cake</th>
      <th>Special Instructions</th>
      <th>✔ Done</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($singleItems as $i => $itm): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td>
        <strong><?= htmlspecialchars((string)($itm['product_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
        <?php if (!empty($itm['variant_snapshot'])): ?>
          <br><small><?= htmlspecialchars((string)$itm['variant_snapshot'], ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
      </td>
      <td><?= (int)$itm['quantity'] ?></td>
      <td><?= !empty($itm['topper_name_snapshot']) && $itm['topper_name_snapshot'] !== 'No Topper' ? htmlspecialchars((string)$itm['topper_name_snapshot'], ENT_QUOTES, 'UTF-8') . ((float)($itm['topper_price_snapshot'] ?? 0) > 0 ? ' (+₹' . number_format((float)$itm['topper_price_snapshot'], 0) . ')' : '') : '—' ?></td>
      <td><?= !empty($itm['cake_message']) ? htmlspecialchars((string)$itm['cake_message'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
      <td><?= !empty($itm['customisation_note']) ? htmlspecialchars((string)$itm['customisation_note'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
      <td style="text-align:center;font-size:18px;">☐</td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<div class="signature">
  <span>Baker: ___________________________</span>
  <span>Quality Check: ___________________________</span>
  <span>Dispatch: ___________________________</span>
</div>
</body>
</html>
<?php
    exit;
}
// ── End single-order mode ────────────────────────────────────────────────────

$cutoff = new DateTimeImmutable($now->format('Y-m-d') . ' 23:45:00', $tz);
$defaultTargetDate = $now >= $cutoff
    ? $now->modify('+2 days')->format('Y-m-d')
    : $now->modify('+1 day')->format('Y-m-d');

$requestedDate = trim((string)($_GET['date'] ?? ''));
$targetDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) ? $requestedDate : $defaultTargetDate;
$exportCsv = strtolower(trim((string)($_GET['export'] ?? ''))) === 'csv';

$plannedOrders = [];
$unscheduledOrders = [];
$totalItems = 0;

$plannedStmt = $conn->prepare(
    'SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.order_status,
        o.payment_status,
        o.payment_method,
        o.scheduled_slot,
        o.scheduled_slot_label,
        COALESCE(SUM(oi.quantity), 0) AS total_qty,
        GROUP_CONCAT(CONCAT(oi.quantity, "x ", oi.product_name_snapshot) ORDER BY oi.id ASC SEPARATOR ", ") AS items_summary
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.order_status IN ("confirmed", "in_preparation")
       AND (o.payment_status = "paid" OR o.payment_status = "credit" OR o.payment_method = "credit")
       AND o.scheduled_slot IS NOT NULL
       AND DATE(o.scheduled_slot) = ?
     GROUP BY o.id
     ORDER BY o.scheduled_slot ASC, o.id ASC'
);
$plannedStmt->bind_param('s', $targetDate);
$plannedStmt->execute();
$plannedResult = $plannedStmt->get_result();
while ($plannedResult && ($row = $plannedResult->fetch_assoc())) {
    $plannedOrders[] = $row;
    $totalItems += (int)($row['total_qty'] ?? 0);
}

$unscheduledStmt = $conn->prepare(
    'SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.order_status,
        o.payment_status,
        o.payment_method,
        o.created_at,
        COALESCE(SUM(oi.quantity), 0) AS total_qty,
        GROUP_CONCAT(CONCAT(oi.quantity, "x ", oi.product_name_snapshot) ORDER BY oi.id ASC SEPARATOR ", ") AS items_summary
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.order_status IN ("confirmed", "in_preparation")
       AND (o.payment_status = "paid" OR o.payment_status = "credit" OR o.payment_method = "credit")
       AND o.scheduled_slot IS NULL
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 200'
);
$unscheduledStmt->execute();
$unscheduledResult = $unscheduledStmt->get_result();
while ($unscheduledResult && ($row = $unscheduledResult->fetch_assoc())) {
    $unscheduledOrders[] = $row;
}

if ($exportCsv) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="production-plan-' . $targetDate . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order Number', 'Customer', 'Phone', 'Scheduled Slot', 'Slot Label', 'Status', 'Payment', 'Total Qty', 'Items']);

    foreach ($plannedOrders as $row) {
        fputcsv($out, [
            (string)($row['order_number'] ?? ''),
            (string)($row['customer_name'] ?? ''),
            (string)($row['customer_phone'] ?? ''),
            (string)($row['scheduled_slot'] ?? ''),
            (string)($row['scheduled_slot_label'] ?? ''),
            (string)($row['order_status'] ?? ''),
            (string)(($row['payment_method'] ?? '') . ' / ' . ($row['payment_status'] ?? '')),
            (string)($row['total_qty'] ?? 0),
            (string)($row['items_summary'] ?? ''),
        ]);
    }

    fclose($out);
    exit;
}

$pageTitle = 'Production Plan';
include 'layout.php';

$firstSlot = count($plannedOrders) > 0 ? (string)$plannedOrders[0]['scheduled_slot'] : 'NA';
$lastSlot = count($plannedOrders) > 0 ? (string)$plannedOrders[count($plannedOrders) - 1]['scheduled_slot'] : 'NA';
?>
<style>
.production-wrap { display:grid; gap:18px; }
.production-card { background:#fffdfd; border:1px solid rgba(128,0,31,.1); border-radius:16px; box-shadow:0 10px 24px rgba(96,18,45,.08); }
.production-card__head { padding:18px 20px 10px; border-bottom:1px solid rgba(128,0,31,.08); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); }
.production-card__head h2, .production-card__head h3 { margin:0; color:#80001F; font-family:'DM Serif Display', Georgia, serif; font-weight:400; }
.production-card__body { padding:16px 20px 20px; }
.production-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:end; }
.production-toolbar label { font-size:.85rem; color:#6e2a3e; display:grid; gap:6px; }
.production-toolbar input[type="date"] { padding:8px 10px; border:1px solid rgba(128,0,31,.2); border-radius:10px; }
.production-btn { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:0 14px; border-radius:10px; border:0; cursor:pointer; text-decoration:none; font-weight:600; }
.production-btn--primary { background:#80001F; color:#fff; }
.production-btn--ghost { background:#f8d8de; color:#80001F; }
.production-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
.production-kpi { border:1px solid rgba(128,0,31,.09); background:#fff8fa; border-radius:12px; padding:12px; }
.production-kpi strong { display:block; color:#80001F; margin-bottom:4px; }
.production-kpi span { color:#6e2a3e; }
.production-table { width:100%; border-collapse:collapse; }
.production-table th, .production-table td { border-bottom:1px solid rgba(128,0,31,.08); padding:10px 8px; text-align:left; vertical-align:top; font-size:.92rem; }
.production-table th { color:#80001F; background:#fff8fa; }
.production-chip { display:inline-block; padding:2px 8px; border-radius:999px; background:#f8d8de; color:#80001F; font-size:.78rem; }
@media (max-width:1100px) { .production-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>

<div class="production-wrap">
    <section class="production-card">
        <div class="production-card__head">
            <h2>Production Plan</h2>
        </div>
        <div class="production-card__body">
            <form method="get" class="production-toolbar">
                <label>
                    Target delivery date
                    <input type="date" name="date" value="<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button type="submit" class="production-btn production-btn--primary">Apply</button>
                <a class="production-btn production-btn--ghost" href="production_plan.php?date=<?= urlencode($targetDate) ?>&export=csv">Export CSV</a>
            </form>
            <p style="margin:12px 0 0; color:#8f7681;">Default date auto-follows 11:45 PM IST cutoff. Current IST: <?= htmlspecialchars($now->format('d M Y, h:i A'), ENT_QUOTES, 'UTF-8') ?>.</p>
        </div>
    </section>

    <section class="production-card">
        <div class="production-card__head">
            <h3>Planning Snapshot (<?= htmlspecialchars($targetDate, ENT_QUOTES, 'UTF-8') ?>)</h3>
        </div>
        <div class="production-card__body">
            <div class="production-kpis">
                <div class="production-kpi"><strong>Orders</strong><span><?= count($plannedOrders) ?></span></div>
                <div class="production-kpi"><strong>Total Cakes/Qty</strong><span><?= (int)$totalItems ?></span></div>
                <div class="production-kpi"><strong>First Slot</strong><span><?= htmlspecialchars($firstSlot, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="production-kpi"><strong>Last Slot</strong><span><?= htmlspecialchars($lastSlot, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="production-kpi"><strong>Needs Scheduling</strong><span><?= count($unscheduledOrders) ?></span></div>
            </div>
        </div>
    </section>

    <section class="production-card">
        <div class="production-card__head">
            <h3>Scheduled Orders</h3>
        </div>
        <div class="production-card__body">
            <table class="production-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Slot</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$plannedOrders): ?>
                        <tr><td colspan="6">No scheduled production orders found for this date.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($plannedOrders as $row): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                <small>#<?= (int)$row['id'] ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?><br>
                                <small><?= htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars((string)$row['scheduled_slot'], ENT_QUOTES, 'UTF-8') ?><br>
                                <small><?= htmlspecialchars((string)($row['scheduled_slot_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td>
                                <span class="production-chip">Qty: <?= (int)($row['total_qty'] ?? 0) ?></span><br>
                                <small><?= htmlspecialchars((string)($row['items_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td>
                                <span class="production-chip"><?= htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') ?></span><br>
                                <small><?= htmlspecialchars((string)$row['payment_method'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td><a href="orders.php" class="production-btn production-btn--ghost" style="min-height:30px;">Open Orders</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="production-card">
        <div class="production-card__head">
            <h3>Needs Scheduling</h3>
        </div>
        <div class="production-card__body">
            <table class="production-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$unscheduledOrders): ?>
                        <tr><td colspan="5">No unscheduled production orders pending.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($unscheduledOrders as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><span class="production-chip">Qty: <?= (int)($row['total_qty'] ?? 0) ?></span><br><small><?= htmlspecialchars((string)($row['items_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><span class="production-chip"><?= htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
