<?php
$pageTitle = 'Kitchen Queue';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/includes/db.php';

$queueDate = trim((string)($_GET['queue_date'] ?? date('Y-m-d')));
$dtCheck = DateTime::createFromFormat('Y-m-d', $queueDate);
if (!$dtCheck || $dtCheck->format('Y-m-d') !== $queueDate) {
    $queueDate = date('Y-m-d');
}

$filterStatus = trim((string)($_GET['prod_status'] ?? 'active'));

$allowedProductionStatuses = ['not_required','pending','in_production','decoration_pending','ready','packed','out_for_delivery','delivered'];

$statusCondition = '1=1';
if ($filterStatus === 'active') {
    $statusCondition = "o.production_status IN ('pending','in_production','decoration_pending')";
} elseif ($filterStatus === 'done') {
    $statusCondition = "o.production_status IN ('ready','packed','out_for_delivery','delivered')";
} elseif (in_array($filterStatus, $allowedProductionStatuses, true)) {
    $safeStatus = $conn->real_escape_string($filterStatus);
    $statusCondition = "o.production_status = '$safeStatus'";
}

$safeDate = $conn->real_escape_string($queueDate);
$sql = "
    SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.fulfilment_mode,
        o.order_mode,
        o.order_status,
        o.production_status,
        o.requires_kitchen_production,
        o.slot_id,
        o.scheduled_slot_label,
        o.grand_total,
        o.advance_amount,
        o.admin_note,
        o.created_at,
        GROUP_CONCAT(oi.product_name_snapshot ORDER BY oi.id ASC SEPARATOR ', ') AS items_summary
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE DATE(o.scheduled_slot) = '$safeDate'
      AND o.order_status NOT IN ('cancelled')
      AND $statusCondition
    GROUP BY o.id
    ORDER BY
        FIELD(o.production_status,'in_production','decoration_pending','pending','ready','packed','out_for_delivery','delivered','not_required'),
        o.slot_id IS NULL ASC,
        o.slot_id ASC,
        o.id ASC
";
$orderRows = [];
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $orderRows[] = $row;
    }
    $res->free();
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
$prodStatusBg = [
    'not_required' => '#f3f4f6',
    'pending' => '#fef3c7',
    'in_production' => '#dbeafe',
    'decoration_pending' => '#ede9fe',
    'ready' => '#d1fae5',
    'packed' => '#a7f3d0',
    'out_for_delivery' => '#bfdbfe',
    'delivered' => '#bbf7d0',
];
$prodStatusText = [
    'not_required' => '#6b7280',
    'pending' => '#92400e',
    'in_production' => '#1e40af',
    'decoration_pending' => '#5b21b6',
    'ready' => '#065f46',
    'packed' => '#065f46',
    'out_for_delivery' => '#1e3a8a',
    'delivered' => '#14532d',
];
?>
<style>
.kq-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
.kq-title { font-size:1.4rem; font-weight:700; color:#80001F; margin:0; }
.kq-controls { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.kq-controls input[type=date] { padding:7px 10px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; }
.kq-controls select { padding:7px 10px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; }
.kq-controls button { padding:7px 14px; background:#80001F; color:#fff; border:none; border-radius:8px; font-size:0.88rem; cursor:pointer; font-weight:600; }
.kq-card { background:#fff; border:1px solid #f0e6e9; border-radius:12px; overflow:hidden; margin-bottom:10px; display:flex; align-items:stretch; }
.kq-status-bar { width:6px; flex-shrink:0; }
.kq-body { padding:14px 16px; flex:1; }
.kq-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.kq-order-num { font-weight:700; font-size:0.95rem; color:#80001F; text-decoration:none; }
.kq-customer { font-size:0.88rem; font-weight:600; color:#2d1f25; }
.kq-phone { font-size:0.8rem; color:#7f6973; }
.kq-items { font-size:0.82rem; color:#5f4c55; margin:6px 0 0; background:#fdf9fa; border-radius:6px; padding:6px 10px; }
.kq-meta { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; align-items:center; }
.kq-badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:0.75rem; font-weight:600; }
.kq-status-select { padding:4px 8px; border:1px solid #ddd; border-radius:8px; font-size:0.82rem; background:#fff; cursor:pointer; font-weight:600; }
.kq-save-btn { padding:4px 12px; background:#80001F; color:#fff; border:none; border-radius:8px; font-size:0.82rem; cursor:pointer; font-weight:600; display:none; }
.kq-saved-msg { font-size:0.78rem; color:#065f46; font-weight:600; display:none; }
.kq-empty { text-align:center; padding:40px; color:#7f6973; font-size:0.95rem; }
</style>

<div style="padding:24px 28px; max-width:1100px;">
  <div class="kq-header">
    <h2 class="kq-title">Kitchen Queue</h2>
    <form class="kq-controls" method="get">
      <input type="date" name="queue_date" value="<?= htmlspecialchars($queueDate) ?>">
      <select name="prod_status">
        <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>Active (Pending + In Prod + Deco)</option>
        <option value="done" <?= $filterStatus==='done'?'selected':'' ?>>Done (Ready → Delivered)</option>
        <?php foreach ($prodStatusLabels as $k => $l): ?>
        <option value="<?= $k ?>" <?= $filterStatus===$k?'selected':'' ?>><?= htmlspecialchars($l) ?></option>
        <?php endforeach; ?>
        <option value="all" <?= $filterStatus==='all'?'selected':'' ?>>All Statuses</option>
      </select>
      <button type="submit">Filter</button>
      <a href="fulfillment_report.php?ops_date=<?= urlencode($queueDate) ?>" style="padding:7px 14px; border:1px solid #80001F; color:#80001F; border-radius:8px; font-size:0.88rem; font-weight:600; text-decoration:none;">Fulfillment Report</a>
    </form>
  </div>
  <p style="font-size:0.88rem; color:#7f6973; margin:0 0 16px;">
    <strong><?= count($orderRows) ?> order<?= count($orderRows) === 1 ? '' : 's' ?></strong>
    for <strong><?= htmlspecialchars(date('D, d M Y', strtotime($queueDate))) ?></strong>
  </p>

  <?php if (empty($orderRows)): ?>
    <div class="kq-empty">No orders found for this filter.</div>
  <?php else: ?>
  <?php foreach ($orderRows as $ord): ?>
  <?php $ps = $ord['production_status']; $bg = $prodStatusBg[$ps] ?? '#f3f4f6'; $txt = $prodStatusText[$ps] ?? '#374151'; ?>
  <div class="kq-card" id="kq-card-<?= (int)$ord['id'] ?>">
    <div class="kq-status-bar" style="background:<?= $bg ?>;"></div>
    <div class="kq-body">
      <div class="kq-top">
        <div>
          <a class="kq-order-num" href="order_details.php?id=<?= (int)$ord['id'] ?>"><?= htmlspecialchars($ord['order_number']) ?></a>
          <div class="kq-customer"><?= htmlspecialchars($ord['customer_name']) ?></div>
          <div class="kq-phone"><?= htmlspecialchars($ord['customer_phone']) ?></div>
        </div>
        <div style="text-align:right; font-size:0.88rem;">
          <div style="font-weight:700; color:#2d1f25;">₹<?= number_format((float)$ord['grand_total'], 0) ?></div>
          <div style="font-size:0.78rem; color:#7f6973;"><?= htmlspecialchars($ord['scheduled_slot_label'] ?: '—') ?></div>
        </div>
      </div>
      <?php if ($ord['items_summary']): ?>
      <div class="kq-items"><?= htmlspecialchars($ord['items_summary']) ?></div>
      <?php endif; ?>
      <div class="kq-meta">
        <span class="kq-badge" style="background:<?= $bg ?>; color:<?= $txt ?>;"><?= htmlspecialchars($prodStatusLabels[$ps] ?? $ps) ?></span>
        <span class="kq-badge" style="background:#f3f4f6; color:#374151;"><?= htmlspecialchars($ord['fulfilment_mode']) ?></span>
        <?php if ($ord['order_mode']): ?>
        <span class="kq-badge" style="background:#fce7f3; color:#9d174d;"><?= htmlspecialchars($ord['order_mode']) ?></span>
        <?php endif; ?>
        <div style="display:flex; gap:6px; align-items:center; margin-left:auto;" id="kq-actions-<?= (int)$ord['id'] ?>">
          <select class="kq-status-select" id="kq-sel-<?= (int)$ord['id'] ?>" onchange="kqMarkDirty(<?= (int)$ord['id'] ?>)">
            <?php foreach ($prodStatusLabels as $k => $l): ?>
            <option value="<?= $k ?>" <?= $ps===$k?'selected':'' ?>><?= htmlspecialchars($l) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="kq-save-btn" id="kq-btn-<?= (int)$ord['id'] ?>" onclick="kqSave(<?= (int)$ord['id'] ?>)">Save</button>
          <span class="kq-saved-msg" id="kq-msg-<?= (int)$ord['id'] ?>">Saved ✓</span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function kqMarkDirty(orderId) {
    document.getElementById('kq-btn-' + orderId).style.display = 'inline-block';
    document.getElementById('kq-msg-' + orderId).style.display = 'none';
}

function kqSave(orderId) {
    const sel = document.getElementById('kq-sel-' + orderId);
    const btn = document.getElementById('kq-btn-' + orderId);
    const msg = document.getElementById('kq-msg-' + orderId);
    const newStatus = sel.value;
    btn.disabled = true;
    btn.textContent = '…';

    fetch('/api/admin/orders/' + orderId + '/status', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ production_status: newStatus }),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.style.display = 'none';
            msg.style.display = 'inline';
            // Update status bar color
            const statusBg = {
                not_required:'#f3f4f6', pending:'#fef3c7', in_production:'#dbeafe',
                decoration_pending:'#ede9fe', ready:'#d1fae5', packed:'#a7f3d0',
                out_for_delivery:'#bfdbfe', delivered:'#bbf7d0'
            };
            const bar = document.querySelector('#kq-card-' + orderId + ' .kq-status-bar');
            if (bar) bar.style.background = statusBg[newStatus] || '#f3f4f6';
        } else {
            alert('Error: ' + (data.message || 'Update failed'));
        }
        btn.disabled = false;
        btn.textContent = 'Save';
    })
    .catch(() => {
        alert('Network error, please try again.');
        btn.disabled = false;
        btn.textContent = 'Save';
    });
}
</script>
