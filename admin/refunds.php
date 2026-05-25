<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_admin_login();

if (!admin_has_permission('can_approve_refund') && !admin_has_permission('can_force_refund') && !admin_has_permission('can_view_refund_reports')) {
    http_response_code(403);
    echo '<h2>Access Denied</h2><p>You do not have permission to view refunds.</p>';
    exit;
}

$canApprove    = admin_has_permission('can_approve_refund') || admin_has_permission('can_force_refund');
$canForce      = admin_has_permission('can_force_refund');
$adminId       = (int)($_SESSION['admin'] ?? 0);
$adminRole     = (string)($_SESSION['admin_role'] ?? '');
$adminPerms    = (array)($_SESSION['admin_permissions'] ?? []);

$tab      = trim((string)($_GET['tab'] ?? 'processed'));
$validTab = ['pending', 'processed'];
if (!in_array($tab, $validTab, true)) {
    $tab = 'processed';
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$statusFilter = $tab === 'pending' ? 'pending_approval' : 'processed';

$where  = [];
$params = [];
if ($statusFilter !== '') {
    $where[]           = "rt.status = '" . $conn->real_escape_string($statusFilter) . "'";
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) AS n FROM refund_transactions rt $whereClause";
try {
  $countResult = safeQuery($conn, $countSql);
} catch (\Throwable $e) {
  error_log('[admin-refunds] count query failed: ' . $e->getMessage());
  $countResult = false;
}
$total = (int)(($countResult instanceof mysqli_result ? $countResult->fetch_assoc() : null)['n'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$listSql =
  "SELECT rt.id, rt.refund_number, rt.order_id, rt.refund_type, rt.reason_code,
      rt.reason_notes, rt.requested_amount, rt.approved_amount, rt.status,
      rt.fraud_flags, rt.requested_at, rt.approved_at, rt.processed_at,
      o.order_number, o.customer_name, o.customer_phone, o.grand_total, o.delivery_fee, o.refund_amount AS already_refunded,
      req.full_name AS requested_by_name
   FROM   refund_transactions rt
   JOIN   orders  o   ON o.id  = rt.order_id
   LEFT JOIN admins req ON req.id = rt.requested_by_admin_id
   $whereClause
   ORDER BY rt.requested_at DESC
   LIMIT $perPage OFFSET $offset";

try {
  $listResult = safeQuery($conn, $listSql);
} catch (\Throwable $e) {
  error_log('[admin-refunds] list query failed: ' . $e->getMessage());
  $listResult = false;
}

$rows = [];
while ($listResult instanceof mysqli_result && ($r = $listResult->fetch_assoc())) {
    $rows[] = $r;
}

$pageTitle = 'Refunds';
require_once __DIR__ . '/layout.php';
?>

<style>
.rf-shell { background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:18px; box-shadow:0 14px 28px rgba(68,16,34,.08); overflow:hidden; margin-bottom:32px; }
.rf-head { display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid rgba(128,0,31,.09); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); flex-wrap:wrap; gap:10px; }
.rf-head h3 { margin:0; font-family:'DM Serif Display',Georgia,serif; color:#80001F; font-size:1.35rem; font-weight:400; }
.rf-tabs { display:flex; gap:6px; }
.rf-tab { padding:7px 16px; border-radius:10px; font-size:.8rem; font-weight:600; text-decoration:none; color:#80001F; border:1px solid rgba(128,0,31,.2); background:#fff; cursor:pointer; }
.rf-tab.active { background:#80001F; color:#fff; border-color:#80001F; }
.rf-table-wrap { overflow-x:auto; }
table.rf-table { width:100%; border-collapse:collapse; font-size:.84rem; }
table.rf-table th { background:#fff8fa; color:#80001F; font-size:.71rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; padding:10px 12px; text-align:left; border-bottom:1px solid rgba(128,0,31,.1); white-space:nowrap; }
table.rf-table td { padding:11px 12px; border-bottom:1px solid rgba(128,0,31,.06); vertical-align:top; }
table.rf-table tr:last-child td { border-bottom:none; }
table.rf-table tr:hover td { background:#fff9fb; }
.rf-badge { display:inline-block; padding:3px 9px; border-radius:999px; font-size:.68rem; font-weight:700; text-transform:uppercase; }
.rf-badge--pending_approval { background:#fef9c3; color:#713f12; }
.rf-badge--approved { background:#dcfce7; color:#166534; }
.rf-badge--rejected { background:#fee2e2; color:#991b1b; }
.rf-badge--processed { background:#ede9fe; color:#5b21b6; }
.rf-fraud { margin-top:4px; font-size:.7rem; color:#dc2626; }
.btn { background:#80001F; color:#fff; padding:6px 12px; border-radius:8px; font-size:.76rem; font-weight:600; border:none; cursor:pointer; text-decoration:none; display:inline-block; transition:background 150ms; }
.btn:hover { background:#5f0017; }
.btn--green { background:#16a34a; } .btn--green:hover { background:#15803d; }
.btn--red { background:#dc2626; } .btn--red:hover { background:#b91c1c; }
.btn--outline { background:#fff; color:#80001F; border:1px solid rgba(128,0,31,.3); } .btn--outline:hover { background:#fff6f8; }
.rf-empty { padding:40px 20px; text-align:center; color:#9c8590; font-size:.9rem; }
.rf-pagination { display:flex; gap:8px; align-items:center; padding:12px 20px; font-size:.82rem; color:#7f6973; }
.rf-pagination a { color:#80001F; text-decoration:none; padding:5px 10px; border:1px solid rgba(128,0,31,.2); border-radius:8px; }
.rf-pagination a:hover { background:#fff6f8; }
</style>

<div class="rf-shell">
  <div class="rf-head">
    <h3>Refunds</h3>
    <div class="rf-tabs">
      <a class="rf-tab <?php echo $tab === 'pending' ? 'active' : ''; ?>" href="refunds.php?tab=pending">Legacy Pending</a>
      <a class="rf-tab <?php echo $tab === 'processed' ? 'active' : ''; ?>" href="refunds.php?tab=processed">Processed Refunds</a>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="rf-empty">
      <?php echo $tab === 'pending' ? 'No legacy refunds pending approval.' : 'No processed refunds found.'; ?>
    </div>
  <?php else: ?>
  <div class="rf-table-wrap">
    <table class="rf-table">
      <thead>
        <tr>
          <th>Refund #</th>
          <th>Order</th>
          <th>Customer</th>
          <th>Type</th>
          <th>Requested</th>
          <th>Refundable</th>
          <th>Reason</th>
          <th>Status</th>
          <th>Requested At</th>
          <?php if ($canApprove && $tab === 'pending'): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
            $refundable = (float)$r['grand_total'] - (float)$r['delivery_fee'] - (float)$r['already_refunded'];
            $fraudFlags = json_decode((string)($r['fraud_flags'] ?? '[]'), true);
            if (!is_array($fraudFlags)) {
                $fraudFlags = [];
            }
        ?>
        <tr>
          <td>
            <a href="/admin/order_details.php?id=<?php echo (int)$r['order_id']; ?>" style="color:#80001F;font-weight:700;">
              <?php echo htmlspecialchars((string)$r['refund_number'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </td>
          <td>
            <a href="/admin/order_details.php?id=<?php echo (int)$r['order_id']; ?>" style="color:#80001F;">
              <?php echo htmlspecialchars((string)$r['order_number'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </td>
          <td>
            <?php echo htmlspecialchars((string)$r['customer_name'], ENT_QUOTES, 'UTF-8'); ?>
            <div style="font-size:.74rem;color:#8f7681;"><?php echo htmlspecialchars((string)$r['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></div>
          </td>
          <td><?php echo htmlspecialchars(ucfirst((string)$r['refund_type']), ENT_QUOTES, 'UTF-8'); ?></td>
          <td>Rs <?php echo number_format((float)$r['requested_amount'], 2); ?></td>
          <td>Rs <?php echo number_format(max(0.0, $refundable), 2); ?></td>
          <td>
            <?php echo htmlspecialchars((string)$r['reason_code'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($r['reason_notes'])): ?>
              <div style="font-size:.73rem;color:#8f7681;"><?php echo htmlspecialchars((string)$r['reason_notes'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($fraudFlags)): ?>
              <div class="rf-fraud">&#9888; <?php echo htmlspecialchars(implode(', ', $fraudFlags), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
          </td>
          <td><span class="rf-badge rf-badge--<?php echo htmlspecialchars((string)$r['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$r['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
          <td style="white-space:nowrap;font-size:.78rem;">
            <?php echo htmlspecialchars((string)$r['requested_at'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($r['requested_by_name'])): ?>
              <div style="color:#9c8590;"><?php echo htmlspecialchars((string)$r['requested_by_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
          </td>
          <?php if ($canApprove && $tab === 'pending'): ?>
          <td>
            <?php if ($r['status'] === 'pending_approval'): ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <button class="btn btn--green"
                onclick="approveRefund(<?php echo (int)$r['id']; ?>, <?php echo number_format(min((float)$r['requested_amount'], max(0.0, $refundable)), 2, '.', ''); ?>)">
                Approve
              </button>
              <button class="btn btn--red" onclick="rejectRefund(<?php echo (int)$r['id']; ?>)">Reject</button>
            </div>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="rf-pagination">
    <?php if ($page > 1): ?><a href="?tab=<?php echo $tab; ?>&page=<?php echo $page - 1; ?>">&laquo; Prev</a><?php endif; ?>
    <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
    <?php if ($page < $totalPages): ?><a href="?tab=<?php echo $tab; ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($canApprove): ?>
<script src="/client/assets/js/scroll-preserve.js"></script>
<script>
function approveRefund(refundId, maxAmount) {
  const amt = prompt('Enter approved amount (max: ' + maxAmount + '):', maxAmount);
  if (amt === null) return;
  const approved = parseFloat(amt);
  if (isNaN(approved) || approved <= 0 || approved > maxAmount) {
    alert('Invalid amount. Must be > 0 and \u2264 ' + maxAmount);
    return;
  }
  fetch('/api/admin/refunds/' + refundId + '/approve', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ approved_amount: approved })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('\u2705 Refund approved successfully');
      if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
        window.CakeScrollPreserver.reload();
      } else {
        location.reload();
      }
    } else {
      alert('\u274c ' + (data.message || 'Failed to approve refund'));
    }
  })
  .catch(() => alert('\u274c Network error'));
}

function rejectRefund(refundId) {
  const notes = prompt('Enter rejection reason:', '');
  if (notes === null) return;
  fetch('/api/admin/refunds/' + refundId + '/reject', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notes: notes })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('\u2705 Refund rejected');
      if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
        window.CakeScrollPreserver.reload();
      } else {
        location.reload();
      }
    } else {
      alert('\u274c ' + (data.message || 'Failed to reject refund'));
    }
  })
  .catch(() => alert('\u274c Network error'));
}
</script>
<?php endif; ?>
