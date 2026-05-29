<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../app/bootstrap.php';

require_admin_permission('order_edit');

use App\Core\Database;

$db  = Database::getInstance();
$msg = trim((string)($_GET['msg']    ?? ''));
$err = trim((string)($_GET['error']  ?? ''));

// ── Filters ───────────────────────────────────────────────────────────
$mode = trim((string)($_GET['mode'] ?? 'pending'));   // pending | all
if (!in_array($mode, ['pending', 'all'], true)) {
    $mode = 'pending';
}

$q = trim((string)($_GET['q'] ?? ''));   // customer name / order number search

// ── Build query ───────────────────────────────────────────────────────
$where  = ["o.payment_method = 'upi_manual'", "o.is_archived = 0"];
$params = [];

if ($mode === 'pending') {
    $where[] = "o.payment_status = 'pending'";
} else {
    $where[] = "o.payment_status IN ('pending', 'under_review')";
}

if ($q !== '') {
    $where[] = "(o.order_number LIKE :q OR o.customer_name LIKE :q OR o.customer_phone LIKE :q)";
    $params['q'] = '%' . $q . '%';
}

$whereSql = implode(' AND ', $where);

$rows = $db->fetchAll(
    "SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.customer_phone_e164,
        o.customer_email,
        o.order_status,
        o.payment_status,
        o.order_mode,
        o.order_source,
        COALESCE(o.revised_grand_total, o.grand_total) AS effective_total,
        o.grand_total,
        o.payment_proof_url,
        o.payment_proof_uploaded_at,
        o.created_at
     FROM orders o
     WHERE {$whereSql}
     ORDER BY o.created_at ASC
     LIMIT 200",
    $params
);

// ── Badge count for nav ───────────────────────────────────────────────
$pendingCount = (int)($db->fetchScalar(
    "SELECT COUNT(*) FROM orders WHERE payment_method='upi_manual' AND payment_status='pending' AND is_archived=0",
    []
) ?? 0);

$pageTitle = 'Payment Verification';
require_once __DIR__ . '/layout.php';

// ── Helpers ───────────────────────────────────────────────────────────
function pv_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function pv_status_chip(string $status): string {
    $map = [
        'pending'      => ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => 'Pending'],
        'under_review' => ['bg' => '#e0f2fe', 'color' => '#075985', 'label' => 'Under Review'],
        'paid'         => ['bg' => '#d1fae5', 'color' => '#065f46', 'label' => 'Paid'],
        'rejected'     => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => 'Rejected'],
    ];
    $s = $map[$status] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'label' => ucfirst($status)];
    return '<span class="pv-chip" style="background:' . $s['bg'] . ';color:' . $s['color'] . '">' . pv_h($s['label']) . '</span>';
}
?>
<style>
.pv-toolbar { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; margin-bottom:1.25rem; }
.pv-toolbar h1 { margin:0; font-size:1.35rem; flex:1; }
.pv-chip { display:inline-block; padding:.18rem .65rem; border-radius:9999px; font-size:.75rem; font-weight:600; white-space:nowrap; }
.pv-badge { background:#dc2626; color:#fff; padding:.18rem .55rem; border-radius:9999px; font-size:.72rem; font-weight:700; }
.pv-filter-bar { background:#fff; border:1px solid #e8d8e0; border-radius:.65rem; padding:.8rem 1rem; margin-bottom:1.25rem; display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; }
.pv-filter-bar label { font-size:.82rem; color:#5a4750; font-weight:500; }
.pv-filter-bar input[type=text] { border:1px solid #ddd; border-radius:.4rem; padding:.38rem .7rem; font-size:.85rem; min-width:220px; }
.pv-filter-bar select { border:1px solid #ddd; border-radius:.4rem; padding:.38rem .7rem; font-size:.85rem; }
.pv-filter-bar button { background:#80001F; color:#fff; border:none; border-radius:.4rem; padding:.4rem .9rem; font-size:.85rem; cursor:pointer; font-weight:600; }
.pv-filter-bar a.reset { color:#80001F; font-size:.82rem; }
.pv-empty { text-align:center; padding:3rem 1rem; color:#9ca3af; font-size:.95rem; }
.pv-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:1.25rem; }
.pv-card { background:#fff; border:1px solid #e8d8e0; border-radius:.8rem; overflow:hidden; box-shadow:0 2px 8px rgba(128,0,31,.06); transition:box-shadow .15s; }
.pv-card:hover { box-shadow:0 4px 18px rgba(128,0,31,.12); }
.pv-card-header { background:linear-gradient(90deg,#80001F 0%,#a0002a 100%); color:#fff; padding:.65rem 1rem; display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
.pv-card-header .order-no { font-weight:700; font-size:.92rem; }
.pv-card-header .order-mode { font-size:.72rem; background:rgba(255,255,255,.2); padding:.1rem .45rem; border-radius:9999px; }
.pv-card-body { padding:.9rem 1rem; }
.pv-row { display:flex; justify-content:space-between; margin-bottom:.4rem; font-size:.84rem; }
.pv-row dt { color:#7a6870; flex-shrink:0; margin-right:.5rem; }
.pv-row dd { font-weight:600; text-align:right; word-break:break-all; }
.pv-amount { font-size:1.1rem; font-weight:700; color:#065f46; }
.pv-proof-wrap { margin:.75rem 0; text-align:center; }
.pv-proof-thumb { max-width:100%; max-height:160px; object-fit:contain; border-radius:.5rem; border:1px solid #e5e7eb; cursor:pointer; transition:transform .15s; }
.pv-proof-thumb:hover { transform:scale(1.03); }
.pv-no-proof { font-size:.8rem; color:#9ca3af; font-style:italic; padding:.5rem 0; }
.pv-proof-link { font-size:.8rem; color:#1d4ed8; text-decoration:underline; }
.pv-actions { display:flex; gap:.6rem; padding:.75rem 1rem; border-top:1px solid #f3e8eb; }
.pv-btn { flex:1; padding:.52rem 0; border-radius:.45rem; font-size:.85rem; font-weight:600; cursor:pointer; border:none; transition:opacity .15s; }
.pv-btn:hover { opacity:.88; }
.pv-btn-approve { background:#059669; color:#fff; }
.pv-btn-reject  { background:#dc2626; color:#fff; }
.pv-btn-view    { background:#f3f4f6; color:#374151; border:1px solid #ddd; }
.pv-meta { font-size:.75rem; color:#9ca3af; padding:.4rem 1rem .65rem; }
/* Lightbox */
.pv-lightbox-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.8); z-index:9999; align-items:center; justify-content:center; }
.pv-lightbox-overlay.open { display:flex; }
.pv-lightbox-overlay img { max-width:92vw; max-height:90vh; object-fit:contain; border-radius:.5rem; }
.pv-lightbox-close { position:absolute; top:1rem; right:1.25rem; color:#fff; font-size:2rem; cursor:pointer; line-height:1; }
/* Inline rejection note modal */
.pv-reject-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9998; align-items:center; justify-content:center; }
.pv-reject-modal.open { display:flex; }
.pv-reject-inner { background:#fff; border-radius:.8rem; padding:1.5rem 1.75rem; max-width:440px; width:90%; }
.pv-reject-inner h3 { margin:0 0 .75rem; font-size:1rem; }
.pv-reject-inner textarea { width:100%; border:1px solid #ddd; border-radius:.4rem; padding:.5rem .7rem; font-size:.88rem; resize:vertical; }
.pv-reject-btns { display:flex; gap:.6rem; margin-top:.75rem; justify-content:flex-end; }
.pv-reject-btns button { padding:.45rem 1rem; border-radius:.4rem; border:none; cursor:pointer; font-size:.85rem; font-weight:600; }
.pv-reject-confirm { background:#dc2626; color:#fff; }
.pv-reject-cancel  { background:#f3f4f6; color:#374151; }
</style>

<!-- Lightbox overlay -->
<div class="pv-lightbox-overlay" id="pvLightbox" onclick="closeLightbox(event)">
  <span class="pv-lightbox-close" onclick="document.getElementById('pvLightbox').classList.remove('open')">&times;</span>
  <img id="pvLightboxImg" src="" alt="Payment proof">
</div>

<!-- Reject note modal -->
<div class="pv-reject-modal" id="pvRejectModal">
  <div class="pv-reject-inner">
    <h3>Reject Payment</h3>
    <p id="pvRejectOrderNo" style="font-size:.85rem;color:#5a4750;margin:.25rem 0 .75rem"></p>
    <textarea id="pvRejectNote" rows="3" placeholder="Reason for rejection (optional — sent to customer)"></textarea>
    <div class="pv-reject-btns">
      <button class="pv-reject-cancel" onclick="closeRejectModal()">Cancel</button>
      <button class="pv-reject-confirm" onclick="submitReject()">Confirm Reject</button>
    </div>
  </div>
  <form id="pvRejectForm" method="post" action="verify_payment.php" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= pv_h(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="order_id" id="pvRejectOrderId">
    <input type="hidden" name="rejection_note" id="pvRejectNoteHidden">
  </form>
</div>

<div class="pv-toolbar">
  <h1>
    Payment Verification
    <?php if ($pendingCount > 0): ?>
      <span class="pv-badge"><?= $pendingCount ?></span>
    <?php endif; ?>
  </h1>
  <a href="orders.php" class="pv-btn pv-btn-view" style="text-decoration:none;padding:.4rem .9rem;font-size:.82rem;border-radius:.4rem;">← All Orders</a>
</div>

<?php if ($msg !== ''): ?>
  <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem;font-size:.88rem;">
    <?= pv_h($msg) ?>
  </div>
<?php endif; ?>
<?php if ($err !== ''): ?>
  <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem;font-size:.88rem;">
    <?= pv_h($err) ?>
  </div>
<?php endif; ?>

<!-- Filter bar -->
<form method="get" class="pv-filter-bar">
  <label>Status:</label>
  <select name="mode" onchange="this.form.submit()">
    <option value="pending" <?= $mode === 'pending' ? 'selected' : '' ?>>Pending Only</option>
    <option value="all"     <?= $mode === 'all'     ? 'selected' : '' ?>>Pending + Under Review</option>
  </select>
  <label>Search:</label>
  <input type="text" name="q" value="<?= pv_h($q) ?>" placeholder="Order # / Customer / Phone">
  <button type="submit">Filter</button>
  <?php if ($q !== '' || $mode !== 'pending'): ?>
    <a class="reset" href="payment_verification.php">Reset</a>
  <?php endif; ?>
</form>

<?php if (count($rows) === 0): ?>
  <div class="pv-empty">
    <svg style="width:2.5rem;height:2.5rem;color:#d1d5db;margin-bottom:.75rem;display:block;margin-inline:auto" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php if ($mode === 'pending'): ?>
      No pending UPI payments to verify. All caught up!
    <?php else: ?>
      No matching orders found.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div style="font-size:.8rem;color:#7a6870;margin-bottom:.75rem"><?= count($rows) ?> order<?= count($rows) !== 1 ? 's' : '' ?> awaiting verification</div>
  <div class="pv-grid">
    <?php foreach ($rows as $row):
      $proofUrl  = (string)($row['payment_proof_url'] ?? '');
      $orderId   = (int)$row['id'];
      $orderNo   = (string)$row['order_number'];
      $isOnline  = in_array((string)($row['order_mode'] ?? ''), ['online', 'byoc'], true);
      $modeLabel = match((string)($row['order_mode'] ?? '')) {
          'online' => 'Online',
          'byoc'   => 'BYOC',
          'ready_pos' => 'POS',
          default  => ucfirst((string)($row['order_mode'] ?? 'Order')),
      };
      $submittedAt = $row['payment_proof_uploaded_at'] ?? $row['created_at'] ?? '';
    ?>
    <div class="pv-card">
      <div class="pv-card-header">
        <span class="order-no"><?= pv_h($orderNo) ?></span>
        <div style="display:flex;gap:.4rem;align-items:center">
          <span class="order-mode"><?= pv_h($modeLabel) ?></span>
          <?= pv_status_chip((string)$row['payment_status']) ?>
        </div>
      </div>
      <div class="pv-card-body">
        <dl>
          <div class="pv-row"><dt>Customer</dt><dd><?= pv_h((string)$row['customer_name']) ?></dd></div>
          <div class="pv-row"><dt>Phone</dt><dd><?= pv_h((string)$row['customer_phone']) ?></dd></div>
          <?php if (!empty($row['customer_email'])): ?>
          <div class="pv-row"><dt>Email</dt><dd><?= pv_h((string)$row['customer_email']) ?></dd></div>
          <?php endif; ?>
          <div class="pv-row">
            <dt>Amount</dt>
            <dd class="pv-amount">₹<?= number_format((float)$row['effective_total'], 2) ?></dd>
          </div>
          <div class="pv-row"><dt>Order Status</dt><dd><?= pv_h((string)$row['order_status']) ?></dd></div>
          <?php if ($submittedAt !== ''): ?>
          <div class="pv-row"><dt>Submitted</dt><dd><?= pv_h(date('d M Y, g:ia', strtotime((string)$submittedAt))) ?></dd></div>
          <?php endif; ?>
        </dl>

        <!-- Proof preview -->
        <div class="pv-proof-wrap">
          <?php if ($proofUrl !== ''): ?>
            <?php
              // Determine if it's an absolute URL or relative path
              $displayUrl = (strncmp($proofUrl, 'http', 4) === 0) ? $proofUrl : '/' . ltrim($proofUrl, '/');
            ?>
            <img
              class="pv-proof-thumb"
              src="<?= pv_h($displayUrl) ?>"
              alt="Payment proof"
              onclick="openLightbox(<?= pv_h(json_encode($displayUrl)) ?>)"
              loading="lazy"
            >
            <div style="margin-top:.35rem">
              <a class="pv-proof-link" href="<?= pv_h($displayUrl) ?>" target="_blank" rel="noopener">Open full size ↗</a>
            </div>
          <?php else: ?>
            <div class="pv-no-proof">No payment screenshot uploaded</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="pv-meta">Order #<?= pv_h($orderNo) ?> · Placed <?= pv_h(date('d M Y', strtotime((string)$row['created_at']))) ?></div>

      <!-- Action buttons -->
      <div class="pv-actions">
        <form method="post" action="verify_payment.php" style="flex:1;display:flex;gap:.6rem"
              onsubmit="return confirm('Approve payment for order <?= pv_h($orderNo) ?>?\nThis will post to the GL and mark the order as paid.')">
          <input type="hidden" name="csrf_token" value="<?= pv_h(\App\Core\Csrf::token()) ?>">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="order_id" value="<?= $orderId ?>">
          <button type="submit" class="pv-btn pv-btn-approve">✓ Approve</button>
        </form>
        <button
          type="button"
          class="pv-btn pv-btn-reject"
          onclick="openRejectModal(<?= $orderId ?>, <?= pv_h(json_encode($orderNo)) ?>)"
        >✕ Reject</button>
        <a href="order_details.php?id=<?= $orderId ?>" class="pv-btn pv-btn-view" style="text-decoration:none;text-align:center;line-height:2.1">View</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
function openLightbox(url) {
  document.getElementById('pvLightboxImg').src = url;
  document.getElementById('pvLightbox').classList.add('open');
}
function closeLightbox(e) {
  if (e.target === document.getElementById('pvLightbox')) {
    document.getElementById('pvLightbox').classList.remove('open');
  }
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.getElementById('pvLightbox').classList.remove('open');
    closeRejectModal();
  }
});

var _rejectOrderId = 0;
function openRejectModal(orderId, orderNo) {
  _rejectOrderId = orderId;
  document.getElementById('pvRejectOrderNo').textContent = 'Order: ' + orderNo;
  document.getElementById('pvRejectOrderId').value = orderId;
  document.getElementById('pvRejectNote').value = '';
  document.getElementById('pvRejectModal').classList.add('open');
}
function closeRejectModal() {
  document.getElementById('pvRejectModal').classList.remove('open');
}
function submitReject() {
  var note = document.getElementById('pvRejectNote').value.trim();
  document.getElementById('pvRejectNoteHidden').value = note;
  document.getElementById('pvRejectForm').submit();
}
// Close rejection modal on overlay click
document.getElementById('pvRejectModal').addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
