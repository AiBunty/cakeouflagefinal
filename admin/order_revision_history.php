<?php
$pageTitle = 'Revision History';
require_once __DIR__ . '/layout.php';
require_admin_permission('orders');
require_once __DIR__ . '/includes/db.php';

use App\Services\OrderRevisionService;
use App\Core\Database;

$db  = Database::getInstance();
$svc = new OrderRevisionService($db);

$msg   = '';
$msgOk = true;

// POST: confirm or cancel a revision
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
        $msg   = 'Invalid request token.';
        $msgOk = false;
    } else {
        $action     = trim((string)($_POST['action']      ?? ''));
        $revisionId = (int)($_POST['revision_id']         ?? 0);
        $adminId    = (int)($_SESSION['admin']             ?? 0);

        if ($action === 'confirm') {
            $context = [
                'admin_id'      => $adminId,
                'admin_name'    => (string)($_SESSION['admin_name'] ?? ''),
                'admin_role'    => (string)($_SESSION['admin_role'] ?? 'admin'),
                'payment_mode'  => trim((string)($_POST['payment_mode'] ?? 'cash')),
                'source_channel'=> 'admin',
            ];
            $r = $svc->confirmRevision($revisionId, $context);
        } elseif ($action === 'cancel') {
            $r = $svc->cancelRevision($revisionId, $adminId);
        } else {
            $r = ['success' => false, 'message' => 'Unknown action'];
        }
        $msg   = $r['message'] ?? '';
        $msgOk = (bool)($r['success'] ?? false);
    }
}

$orderId   = (int)($_GET['order_id'] ?? 0);
$highlight = (int)($_GET['highlight'] ?? 0);
$order     = null;
$history   = [];

if ($orderId > 0) {
    $order = $db->fetchOne(
        "SELECT id, order_number, customer_name,
                grand_total, COALESCE(revised_grand_total, grand_total) AS effective_total,
                order_status, current_revision_no
           FROM orders WHERE id = :id LIMIT 1",
        ['id' => $orderId]
    );
    if ($order) {
        $history = $svc->getRevisionHistory($orderId);
    }
}

function rev_status_badge(string $status): string
{
    $map = [
        'pending'   => ['#e67e22', 'Pending'],
        'confirmed' => ['#27ae60', 'Confirmed'],
        'cancelled' => ['#95a5a6', 'Cancelled'],
    ];
    [$color, $label] = $map[$status] ?? ['#aaa', ucfirst($status)];
    return "<span style=\"font-size:.75rem;padding:.2rem .6rem;border-radius:10px;background:{$color}22;color:{$color};font-weight:600\">{$label}</span>";
}
?>
<div class="page-content">
  <div class="page-header">
    <h1 class="page-title">Order Revision History</h1>
  </div>

  <?php if ($msg !== ''): ?>
    <div class="alert <?= $msgOk ? 'alert-success' : 'alert-danger' ?>" style="margin-bottom:1rem">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <!-- Order lookup -->
  <form method="get" class="card mb-3" style="padding:.75rem 1rem">
    <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label style="font-size:.8rem;font-weight:600">Order ID</label>
        <input type="number" name="order_id" value="<?= $orderId ?: '' ?>"
               style="display:block;padding:.4rem .6rem;border:1px solid #ccc;border-radius:5px;width:140px">
      </div>
      <button type="submit" class="btn btn-primary">Load</button>
    </div>
  </form>

  <?php if ($orderId > 0 && $order === null): ?>
    <div class="card" style="padding:1.5rem;color:#c0392b">Order #<?= $orderId ?> not found.</div>
  <?php endif; ?>

  <?php if ($order): ?>
  <!-- Order header -->
  <div class="card mb-3">
    <div class="card-body" style="display:flex;gap:2rem;flex-wrap:wrap;align-items:center">
      <div><strong>Order #<?= htmlspecialchars((string)$order['order_number']) ?></strong></div>
      <div><?= htmlspecialchars((string)$order['customer_name']) ?></div>
      <div>Status: <strong><?= htmlspecialchars((string)$order['order_status']) ?></strong></div>
      <div>Original: ₹<?= number_format((float)$order['grand_total'], 2) ?></div>
      <div>Effective: <strong>₹<?= number_format((float)$order['effective_total'], 2) ?></strong></div>
      <div style="margin-left:auto">
        <a href="order_revision.php?order_id=<?= $orderId ?>" class="btn btn-sm btn-primary">+ New Revision</a>
      </div>
    </div>
  </div>

  <!-- History table -->
  <div class="card">
    <div class="card-header"><strong>Revisions (<?= count($history) ?>)</strong></div>
    <?php if (empty($history)): ?>
    <div class="card-body" style="text-align:center;color:#aaa;padding:2rem">No revisions yet for this order.</div>
    <?php else: ?>
    <?php foreach ($history as $rev): ?>
    <?php $diff = (float)$rev['difference_amount']; ?>
    <div class="card-body" id="rev-<?= (int)$rev['id'] ?>"
         style="border-top:1px solid #f0e8ea;<?= $highlight === (int)$rev['id'] ? 'background:#fffbe6' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.75rem">
        <div>
          <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem">
            <strong>Revision #<?= (int)$rev['revision_no'] ?></strong>
            <?= rev_status_badge((string)$rev['revision_status']) ?>
            <?php if ((int)$rev['requires_super_approval']): ?>
              <span style="font-size:.75rem;color:#e67e22">⚠ Needs super-admin</span>
            <?php endif; ?>
          </div>
          <div style="font-size:.85rem;color:#555;margin-bottom:.3rem">
            <strong>Type:</strong> <?= htmlspecialchars((string)$rev['revision_type']) ?>
            &nbsp;&nbsp;
            <strong>Reason:</strong> <?= htmlspecialchars((string)$rev['revision_reason']) ?>
          </div>
          <div style="font-size:.82rem;color:#888">
            ₹<?= number_format((float)$rev['old_grand_total'], 2) ?>
            → ₹<?= number_format((float)$rev['new_grand_total'], 2) ?>
            &nbsp;
            <span style="font-weight:700;color:<?= $diff > 0 ? '#27ae60' : ($diff < 0 ? '#c0392b' : '#888') ?>">
              <?= $diff > 0 ? '+' : '' ?>₹<?= number_format($diff, 2) ?>
            </span>
            <?php if ($rev['downgrade_resolution']): ?>
              &nbsp;· <?= htmlspecialchars((string)$rev['downgrade_resolution']) ?>
            <?php endif; ?>
          </div>
          <div style="font-size:.78rem;color:#aaa;margin-top:.3rem">
            By <?= htmlspecialchars((string)($rev['created_by_name'] ?? '?')) ?> · <?= htmlspecialchars((string)$rev['created_at']) ?>
            <?php if ($rev['approved_by_name']): ?>
              &nbsp;· Approved by <?= htmlspecialchars((string)$rev['approved_by_name']) ?>
            <?php endif; ?>
            <?php if ($rev['gl_transaction_id']): ?>
              &nbsp;· GL TX #<?= (int)$rev['gl_transaction_id'] ?>
            <?php endif; ?>
          </div>
        </div>
        <!-- Actions for pending revisions -->
        <?php if ((string)$rev['revision_status'] === 'pending'): ?>
        <div style="display:flex;gap:.5rem;align-items:flex-start">
          <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="action" value="confirm">
            <input type="hidden" name="revision_id" value="<?= (int)$rev['id'] ?>">
            <?php if ($diff < 0 && (string)($rev['downgrade_resolution'] ?? '') === 'refund'): ?>
              <select name="payment_mode" style="padding:.3rem .5rem;border:1px solid #ccc;border-radius:4px;font-size:.8rem;margin-right:.25rem">
                <option value="cash">Cash</option>
                <option value="upi">UPI</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-sm btn-success"
                    onclick="return confirm('Confirm revision #<?= (int)$rev['revision_no'] ?> and post GL entry?')">
              ✓ Confirm
            </button>
          </form>
          <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="revision_id" value="<?= (int)$rev['id'] ?>">
            <button type="submit" class="btn btn-sm btn-secondary"
                    onclick="return confirm('Cancel this revision?')">
              ✕ Cancel
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <!-- Snapshots toggle -->
      <div style="margin-top:.5rem">
        <button type="button" class="btn btn-xs btn-secondary snapshot-toggle" data-rev="<?= (int)$rev['id'] ?>">
          Show item snapshots
        </button>
        <div id="snap-<?= (int)$rev['id'] ?>" style="display:none;margin-top:.75rem">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div>
              <strong style="font-size:.8rem;color:#888">BEFORE</strong>
              <pre style="font-size:.76rem;background:#f8f8f8;padding:.6rem;border-radius:4px;overflow:auto;max-height:200px"><?= htmlspecialchars(json_encode(json_decode((string)$rev['old_items_snapshot'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
            <div>
              <strong style="font-size:.8rem;color:#888">AFTER</strong>
              <pre style="font-size:.76rem;background:#f8f8f8;padding:.6rem;border-radius:4px;overflow:auto;max-height:200px"><?= htmlspecialchars(json_encode(json_decode((string)$rev['new_items_snapshot'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.snapshot-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var revId = btn.getAttribute('data-rev');
        var el = document.getElementById('snap-' + revId);
        if (!el) return;
        var hidden = el.style.display === 'none';
        el.style.display = hidden ? 'block' : 'none';
        btn.textContent = hidden ? 'Hide item snapshots' : 'Show item snapshots';
    });
});
<?php if ($highlight): ?>
var hl = document.getElementById('rev-<?= $highlight ?>');
if (hl) { hl.scrollIntoView({behavior:'smooth', block:'start'}); }
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
