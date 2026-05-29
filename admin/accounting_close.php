<?php
$pageTitle = 'Daily Close';
require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');
require_once __DIR__ . '/includes/db.php';

use App\Services\DailyCloseService;

$svc   = new DailyCloseService();
$msg   = '';
$msgOk = true;

$action = trim((string)($_POST['action'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
        $msg   = 'Invalid request token.';
        $msgOk = false;
    } else {
        $date    = trim((string)($_POST['business_date'] ?? date('Y-m-d')));
        $adminId = (int)($_SESSION['admin'] ?? 0);
        $reason  = trim((string)($_POST['reason'] ?? ''));
        if ($action === 'close') {
            $r = $svc->close($date, $adminId);
        } elseif ($action === 'reopen') {
            $r = $svc->reopen($date, $adminId, $reason);
        } else {
            $r = ['success' => false, 'message' => 'Unknown action'];
        }
        $msg   = $r['message'] ?? '';
        $msgOk = (bool)($r['success'] ?? false);
    }
}

$log = $svc->getCloseLog(90);

$viewDate = trim((string)($_GET['date'] ?? date('Y-m-d')));
// Current date summary
$today = $svc->close($viewDate, 0); // dry-run returns summary even when already closed
// Actually just query the close log for this date
$dateEntry = null;
foreach ($log as $row) {
    if ($row['close_date'] === $viewDate) {
        $dateEntry = $row;
        break;
    }
}
?>
<div class="page-content">
  <div class="page-header">
    <h1 class="page-title">Daily Close &mdash; Accounting Ledger</h1>
  </div>

  <?php if ($msg !== ''): ?>
    <div class="alert <?= $msgOk ? 'alert-success' : 'alert-danger' ?>" style="margin-bottom:1rem">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <!-- Date picker + action -->
  <div class="card mb-4">
    <div class="card-body" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
      <form method="get" style="display:flex;align-items:center;gap:.5rem">
        <label for="date"><strong>View date:</strong></label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($viewDate) ?>"
               style="padding:.4rem .6rem;border:1px solid #ccc;border-radius:6px">
        <button type="submit" class="btn btn-sm btn-secondary">View</button>
      </form>

      <form method="post" style="display:flex;align-items:center;gap:.5rem">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="business_date" value="<?= htmlspecialchars($viewDate) ?>">
        <?php if ($dateEntry && (int)$dateEntry['is_locked'] === 1): ?>
          <input type="text" name="reason" placeholder="Reopen reason (required)" required
                 style="padding:.4rem .8rem;border:1px solid #ccc;border-radius:6px;min-width:240px">
          <button type="submit" name="action" value="reopen" class="btn btn-sm btn-warning"
                  onclick="return confirm('Reopen accounting day <?= htmlspecialchars($viewDate) ?>?')">
            🔓 Reopen Day
          </button>
        <?php else: ?>
          <button type="submit" name="action" value="close" class="btn btn-sm btn-danger"
                  onclick="return confirm('Close accounting day <?= htmlspecialchars($viewDate) ?>? This will lock GL entries for the day.')">
            🔒 Close Day
          </button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Date summary -->
  <?php if ($dateEntry): ?>
  <div class="card mb-4">
    <div class="card-header"><strong>Summary: <?= htmlspecialchars($dateEntry['close_date']) ?></strong>
      <span class="badge <?= (int)$dateEntry['is_locked'] ? 'badge-danger' : 'badge-warning' ?>" style="margin-left:.5rem">
        <?= (int)$dateEntry['is_locked'] ? 'LOCKED' : 'OPEN' ?>
      </span>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem">
        <div class="stat-card">
          <div class="stat-label">Total Debits</div>
          <div class="stat-value">₹<?= number_format((float)$dateEntry['total_debits'], 2) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Credits</div>
          <div class="stat-value">₹<?= number_format((float)$dateEntry['total_credits'], 2) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Transactions</div>
          <div class="stat-value"><?= (int)$dateEntry['transaction_count'] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Revenue Posted</div>
          <div class="stat-value">₹<?= number_format((float)$dateEntry['revenue_total'], 2) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Refunds Posted</div>
          <div class="stat-value">₹<?= number_format((float)$dateEntry['refund_total'], 2) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Closed by</div>
          <div class="stat-value" style="font-size:.9rem"><?= htmlspecialchars((string)$dateEntry['closed_by_name']) ?></div>
        </div>
      </div>
      <?php if ($dateEntry['notes']): ?>
        <p style="margin-top:.75rem;font-size:.85rem;color:#666"><?= nl2br(htmlspecialchars((string)$dateEntry['notes'])) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="card mb-4">
    <div class="card-body">
      <p style="color:#888">No close record found for <strong><?= htmlspecialchars($viewDate) ?></strong>. Use the button above to close this day.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Past close log -->
  <div class="card">
    <div class="card-header"><strong>Recent Close Log (last 90 days)</strong></div>
    <div class="card-body" style="padding:0;overflow-x:auto">
      <table class="admin-table" style="width:100%">
        <thead>
          <tr>
            <th>Date</th>
            <th>Status</th>
            <th>Debits</th>
            <th>Credits</th>
            <th>Txns</th>
            <th>Revenue</th>
            <th>Closed By</th>
            <th>Closed At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($log as $row): ?>
          <tr>
            <td>
              <a href="?date=<?= urlencode($row['close_date']) ?>" style="font-weight:600">
                <?= htmlspecialchars($row['close_date']) ?>
              </a>
            </td>
            <td>
              <?php if ((int)$row['is_locked']): ?>
                <span class="badge badge-danger">Locked</span>
              <?php else: ?>
                <span class="badge badge-warning">Open</span>
              <?php endif; ?>
            </td>
            <td>₹<?= number_format((float)$row['total_debits'], 2) ?></td>
            <td>₹<?= number_format((float)$row['total_credits'], 2) ?></td>
            <td><?= (int)$row['transaction_count'] ?></td>
            <td>₹<?= number_format((float)$row['revenue_total'], 2) ?></td>
            <td><?= htmlspecialchars((string)$row['closed_by_name']) ?></td>
            <td style="font-size:.8rem;color:#888"><?= htmlspecialchars((string)$row['closed_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($log)): ?>
          <tr><td colspan="8" style="text-align:center;color:#aaa;padding:2rem">No close records yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
