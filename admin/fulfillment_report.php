<?php
$pageTitle = 'Fulfillment Report';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/includes/db.php';

$opsDate = trim((string)($_GET['ops_date'] ?? date('Y-m-d')));
// Validate date
$dtCheck = DateTime::createFromFormat('Y-m-d', $opsDate);
if (!$dtCheck || $dtCheck->format('Y-m-d') !== $opsDate) {
    $opsDate = date('Y-m-d');
}
$opsDateDisplay = date('D, d M Y', strtotime($opsDate));

// ── 1. Slot utilization for the day ──────────────────────────────────
$slotRows = [];
$slotQ = $conn->prepare(
    'SELECT
        os.id AS slot_id,
        os.slot_name,
        os.slot_label,
        os.slot_type,
        os.max_capacity,
        COALESCE(sc.booked_count, 0) AS booked_count
     FROM order_slots os
     LEFT JOIN slot_capacities sc ON sc.slot_id = os.id AND sc.booking_date = ?
     WHERE os.is_active = 1
     ORDER BY os.id ASC'
);
$slotQ->bind_param('s', $opsDate);
$slotQ->execute();
$slotRes = $slotQ->get_result();
if ($slotRes) {
    while ($row = $slotRes->fetch_assoc()) {
        $slotRows[] = $row;
    }
    $slotRes->free();
}

// ── 2. Orders for the day (by scheduled slot date) ───────────────────
$orderRows = [];
$orderQ = $conn->prepare(
    'SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.fulfilment_mode,
        o.order_mode,
        o.order_status,
        o.payment_status,
        o.grand_total,
        o.advance_amount,
        o.production_status,
        o.slot_id,
        o.scheduled_slot_label,
        o.admin_note,
        o.created_at
     FROM orders o
     WHERE DATE(o.scheduled_slot) = ?
       AND o.order_status NOT IN ("cancelled")
     ORDER BY o.slot_id IS NULL ASC, o.slot_id ASC, o.id ASC'
);
$orderQ->bind_param('s', $opsDate);
$orderQ->execute();
$orderRes = $orderQ->get_result();
if ($orderRes) {
    while ($row = $orderRes->fetch_assoc()) {
        $orderRows[] = $row;
    }
    $orderRes->free();
}

// ── 3. Production status summary ──────────────────────────────────────
$prodSummary = [];
$prodQ = $conn->prepare(
    'SELECT production_status, COUNT(*) AS cnt
     FROM orders
     WHERE DATE(scheduled_slot) = ?
       AND order_status NOT IN ("cancelled")
     GROUP BY production_status'
);
$prodQ->bind_param('s', $opsDate);
$prodQ->execute();
$prodRes = $prodQ->get_result();
if ($prodRes) {
    while ($row = $prodRes->fetch_assoc()) {
        $prodSummary[$row['production_status']] = (int)$row['cnt'];
    }
    $prodRes->free();
}

// ── 4. Fulfilment mode summary ────────────────────────────────────────
$fulfilSummary = [];
$fulfilQ = $conn->prepare(
    'SELECT fulfilment_mode, COUNT(*) AS cnt
     FROM orders
     WHERE DATE(scheduled_slot) = ?
       AND order_status NOT IN ("cancelled")
     GROUP BY fulfilment_mode'
);
$fulfilQ->bind_param('s', $opsDate);
$fulfilQ->execute();
$fulfilRes = $fulfilQ->get_result();
if ($fulfilRes) {
    while ($row = $fulfilRes->fetch_assoc()) {
        $fulfilSummary[$row['fulfilment_mode']] = (int)$row['cnt'];
    }
    $fulfilRes->free();
}

$totalOrders = count($orderRows);
$totalRevenue = array_sum(array_column($orderRows, 'grand_total'));

// Build slot label map
$slotLabelMap = [];
foreach ($slotRows as $s) {
    $slotLabelMap[(int)$s['slot_id']] = $s['slot_label'] ?: $s['slot_name'];
}

$prodStatusLabels = [
    'not_required' => 'Not Required',
    'pending' => 'Pending',
    'in_production' => 'In Production',
    'decoration_pending' => 'Decoration Pending',
    'ready' => 'Ready',
    'packed' => 'Packed',
    'out_for_delivery' => 'Out for Delivery',
    'delivered' => 'Delivered',
];
$prodStatusColors = [
    'not_required' => '#e5e7eb',
    'pending' => '#fef3c7',
    'in_production' => '#dbeafe',
    'decoration_pending' => '#ede9fe',
    'ready' => '#d1fae5',
    'packed' => '#a7f3d0',
    'out_for_delivery' => '#bfdbfe',
    'delivered' => '#bbf7d0',
];
?>
<style>
.fr-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
.fr-title { font-size:1.4rem; font-weight:700; color:#80001F; margin:0; }
.fr-date-form { display:flex; gap:8px; align-items:center; }
.fr-date-form input[type=date] { padding:7px 10px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem; }
.fr-date-form button { padding:7px 14px; background:#80001F; color:#fff; border:none; border-radius:8px; font-size:0.9rem; cursor:pointer; font-weight:600; }
.fr-summary-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; margin-bottom:24px; }
.fr-summary-card { background:#fff; border:1px solid #f0e6e9; border-radius:12px; padding:16px; text-align:center; }
.fr-summary-card .val { font-size:1.8rem; font-weight:700; color:#80001F; }
.fr-summary-card .lbl { font-size:0.8rem; color:#7f6973; margin-top:2px; }
.fr-section { background:#fff; border:1px solid #f0e6e9; border-radius:12px; padding:20px; margin-bottom:20px; }
.fr-section-title { font-size:1rem; font-weight:700; color:#2d1f25; margin:0 0 14px; }
.fr-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
.fr-table th { background:#fdf2f4; padding:8px 10px; text-align:left; color:#5f0017; font-weight:600; border-bottom:2px solid #f0e6e9; white-space:nowrap; }
.fr-table td { padding:8px 10px; border-bottom:1px solid #f9f0f2; vertical-align:middle; }
.fr-table tr:hover td { background:#fffaf9; }
.prod-badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:0.78rem; font-weight:600; }
.mode-badge { display:inline-block; padding:2px 7px; border-radius:6px; font-size:0.75rem; font-weight:600; background:#f3f4f6; color:#374151; }
.util-bar { background:#f3f4f6; border-radius:4px; height:8px; overflow:hidden; margin-top:4px; }
.util-fill { height:8px; border-radius:4px; background:#80001F; transition:width 0.3s; }
</style>

<div style="padding:24px 28px; max-width:1200px;">
  <div class="fr-header">
    <h2 class="fr-title">Fulfillment Report</h2>
    <form class="fr-date-form" method="get">
      <label style="font-size:0.88rem; font-weight:600; color:#5f0017;">Ops Date:</label>
      <input type="date" name="ops_date" value="<?= htmlspecialchars($opsDate) ?>">
      <button type="submit">View</button>
    </form>
  </div>
  <p style="font-size:0.9rem; color:#7f6973; margin:0 0 20px;">Operations for: <strong><?= htmlspecialchars($opsDateDisplay) ?></strong></p>

  <!-- Summary cards -->
  <div class="fr-summary-grid">
    <div class="fr-summary-card">
      <div class="val"><?= $totalOrders ?></div>
      <div class="lbl">Total Orders</div>
    </div>
    <div class="fr-summary-card">
      <div class="val">₹<?= number_format($totalRevenue, 0) ?></div>
      <div class="lbl">Total Revenue</div>
    </div>
    <div class="fr-summary-card">
      <div class="val"><?= (int)($fulfilSummary['pickup'] ?? 0) + (int)($fulfilSummary['custom_delivery'] ?? 0) + (int)($fulfilSummary['delivery'] ?? 0) ?></div>
      <div class="lbl">Active Deliveries</div>
    </div>
    <div class="fr-summary-card">
      <div class="val"><?= (int)($prodSummary['ready'] ?? 0) + (int)($prodSummary['packed'] ?? 0) ?></div>
      <div class="lbl">Ready / Packed</div>
    </div>
    <div class="fr-summary-card">
      <div class="val"><?= (int)($prodSummary['pending'] ?? 0) + (int)($prodSummary['in_production'] ?? 0) + (int)($prodSummary['decoration_pending'] ?? 0) ?></div>
      <div class="lbl">In Kitchen</div>
    </div>
  </div>

  <?php if (!empty($slotRows)): ?>
  <!-- Slot utilization -->
  <div class="fr-section">
    <h3 class="fr-section-title">Slot Utilization</h3>
    <table class="fr-table">
      <thead>
        <tr><th>Slot</th><th>Type</th><th>Booked</th><th>Capacity</th><th>Utilization</th></tr>
      </thead>
      <tbody>
        <?php foreach ($slotRows as $sl): ?>
        <?php $cap = max(1, (int)$sl['max_capacity']); $bk = (int)$sl['booked_count']; $pct = min(100, round($bk / $cap * 100)); ?>
        <tr>
          <td><strong><?= htmlspecialchars($sl['slot_label'] ?: $sl['slot_name']) ?></strong></td>
          <td><span class="mode-badge"><?= htmlspecialchars($sl['slot_type']) ?></span></td>
          <td><?= $bk ?></td>
          <td><?= $cap ?></td>
          <td style="min-width:120px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div class="util-bar" style="flex:1;"><div class="util-fill" style="width:<?= $pct ?>%; background:<?= $pct >= 90 ? '#dc2626' : ($pct >= 70 ? '#f59e0b' : '#80001F') ?>;"></div></div>
              <span style="font-size:0.8rem; font-weight:600; color:#5f0017;"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Production status breakdown -->
  <?php if (!empty($prodSummary)): ?>
  <div class="fr-section">
    <h3 class="fr-section-title">Production Status Breakdown</h3>
    <div style="display:flex; flex-wrap:wrap; gap:10px;">
      <?php foreach ($prodStatusLabels as $key => $label): if (!isset($prodSummary[$key])) continue; ?>
      <div style="background:<?= $prodStatusColors[$key] ?? '#f3f4f6' ?>; border-radius:10px; padding:10px 16px; min-width:120px; text-align:center;">
        <div style="font-size:1.5rem; font-weight:700; color:#2d1f25;"><?= (int)$prodSummary[$key] ?></div>
        <div style="font-size:0.78rem; color:#5f4c55;"><?= htmlspecialchars($label) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Orders table -->
  <div class="fr-section">
    <h3 class="fr-section-title">Orders for <?= htmlspecialchars($opsDateDisplay) ?> (<?= $totalOrders ?>)</h3>
    <?php if (empty($orderRows)): ?>
      <p style="color:#7f6973; font-size:0.9rem; margin:0;">No orders scheduled for this date.</p>
    <?php else: ?>
    <table class="fr-table">
      <thead>
        <tr><th>#</th><th>Order</th><th>Customer</th><th>Slot</th><th>Mode</th><th>Fulfilment</th><th>Production</th><th>Payment</th><th>Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($orderRows as $ord): ?>
        <tr>
          <td><?= (int)$ord['id'] ?></td>
          <td><a href="order_details.php?id=<?= (int)$ord['id'] ?>" style="color:#80001F; font-weight:600; text-decoration:none;"><?= htmlspecialchars($ord['order_number']) ?></a></td>
          <td>
            <div style="font-weight:600; font-size:0.88rem;"><?= htmlspecialchars($ord['customer_name']) ?></div>
            <div style="font-size:0.78rem; color:#7f6973;"><?= htmlspecialchars($ord['customer_phone']) ?></div>
          </td>
          <td><?= $ord['slot_id'] ? htmlspecialchars($slotLabelMap[(int)$ord['slot_id']] ?? 'Slot #' . $ord['slot_id']) : htmlspecialchars($ord['scheduled_slot_label'] ?: '—') ?></td>
          <td><span class="mode-badge"><?= htmlspecialchars($ord['order_mode'] ?: '—') ?></span></td>
          <td><span class="mode-badge"><?= htmlspecialchars($ord['fulfilment_mode']) ?></span></td>
          <td>
            <span class="prod-badge" style="background:<?= $prodStatusColors[$ord['production_status']] ?? '#f3f4f6' ?>; color:#2d1f25;">
              <?= htmlspecialchars($prodStatusLabels[$ord['production_status']] ?? $ord['production_status']) ?>
            </span>
          </td>
          <td><span class="mode-badge"><?= htmlspecialchars($ord['payment_status']) ?></span></td>
          <td style="font-weight:600;">₹<?= number_format((float)$ord['grand_total'], 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
