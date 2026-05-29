<?php
$pageTitle = 'GL Register';
require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');
require_once __DIR__ . '/includes/db.php';

use App\Core\Database;

$db = Database::getInstance();

$perPageOptions = [25, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 25;
}
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-d')));
$dateTo   = trim((string)($_GET['date_to']   ?? date('Y-m-d')));
$txType   = trim((string)($_GET['tx_type']   ?? ''));
$channel  = trim((string)($_GET['channel']   ?? ''));
$refType  = trim((string)($_GET['ref_type']  ?? ''));
$refId    = trim((string)($_GET['ref_id']    ?? ''));

$conditions = ['ft.business_date BETWEEN :d1 AND :d2'];
$params     = ['d1' => $dateFrom, 'd2' => $dateTo];

if ($txType !== '') {
    $conditions[] = 'ft.transaction_type = :tx_type';
    $params['tx_type'] = $txType;
}
if ($channel !== '') {
    $conditions[] = 'ft.source_channel = :channel';
    $params['channel'] = $channel;
}
if ($refType !== '') {
    $conditions[] = 'ft.reference_type = :ref_type';
    $params['ref_type'] = $refType;
}
if ($refId !== '') {
    $conditions[] = 'ft.reference_id = :ref_id';
    $params['ref_id'] = (int)$refId;
}

$where = 'WHERE ' . implode(' AND ', $conditions);

$total = (int)($db->fetchScalar(
    "SELECT COUNT(*) FROM financial_transactions ft $where",
    $params
) ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rows = $db->fetchAll(
    "SELECT ft.id, ft.business_date, ft.transaction_type, ft.reference_type, ft.reference_id,
            ft.narration, ft.source_channel, ft.is_reversal, ft.review_required,
            ft.created_at
       FROM financial_transactions ft
       $where
       ORDER BY ft.created_at DESC, ft.id DESC
       LIMIT :lim OFFSET :off",
    array_merge($params, ['lim' => $perPage, 'off' => $offset])
) ?: [];

// Prefetch GLE rows for all tx IDs
$txIds = array_column($rows, 'id');
$gleMap = [];
if ($txIds) {
    $in = implode(',', array_map('intval', $txIds));
    $gleRows = $db->fetchAll(
        "SELECT * FROM general_ledger_entries WHERE transaction_id IN ($in) ORDER BY transaction_id, id"
    ) ?: [];
    foreach ($gleRows as $gle) {
        $gleMap[(int)$gle['transaction_id']][] = $gle;
    }
}

function gl_register_url(array $overrides = []): string
{
    $base = ['date_from', 'date_to', 'tx_type', 'channel', 'ref_type', 'ref_id', 'per_page', 'page'];
    $curr = [];
    foreach ($base as $k) {
        $curr[$k] = $_GET[$k] ?? '';
    }
    return '?' . http_build_query(array_filter(array_merge($curr, $overrides), fn($v) => $v !== ''));
}
?>
<div class="page-content">
  <div class="page-header">
    <h1 class="page-title">GL Register</h1>
  </div>

  <!-- Filters -->
  <form method="get" class="card mb-3" style="padding:1rem">
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <div>
        <label style="font-size:.8rem;font-weight:600">From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
               style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600">To</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
               style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600">Type</label>
        <input type="text" name="tx_type" value="<?= htmlspecialchars($txType) ?>" placeholder="e.g. sale"
               style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px;width:130px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600">Channel</label>
        <input type="text" name="channel" value="<?= htmlspecialchars($channel) ?>" placeholder="online/admin"
               style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px;width:130px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600">Ref Type</label>
        <input type="text" name="ref_type" value="<?= htmlspecialchars($refType) ?>" placeholder="order"
               style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px;width:100px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600">Ref ID</label>
        <input type="number" name="ref_id" value="<?= htmlspecialchars($refId) ?>" placeholder="ID"
               style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px;width:90px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600">Per page</label>
        <select name="per_page" style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px">
          <?php foreach ($perPageOptions as $opt): ?>
            <option value="<?= $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-sm btn-primary">Filter</button>
      <a href="?" class="btn btn-sm btn-secondary">Clear</a>
    </div>
  </form>

  <div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
      <strong>Transactions (<?= $total ?>)</strong>
      <div style="font-size:.85rem;color:#888">Page <?= $page ?> / <?= $totalPages ?></div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto">
      <table class="admin-table" style="width:100%">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Type</th>
            <th>Ref</th>
            <th>Channel</th>
            <th>Narration</th>
            <th>Flags</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $tx): ?>
          <?php $gles = $gleMap[(int)$tx['id']] ?? []; ?>
          <tr class="gl-tx-row" data-tx="<?= (int)$tx['id'] ?>">
            <td style="font-weight:600">#<?= (int)$tx['id'] ?></td>
            <td><?= htmlspecialchars((string)$tx['business_date']) ?></td>
            <td><code style="font-size:.8rem"><?= htmlspecialchars((string)$tx['transaction_type']) ?></code></td>
            <td style="font-size:.82rem">
              <?= htmlspecialchars((string)$tx['reference_type']) ?>
              <?php if ($tx['reference_id']): ?>#<?= (int)$tx['reference_id'] ?><?php endif; ?>
            </td>
            <td><span style="font-size:.8rem;color:#666"><?= htmlspecialchars((string)$tx['source_channel']) ?></span></td>
            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.85rem">
              <?= htmlspecialchars((string)$tx['narration']) ?>
            </td>
            <td>
              <?php if ((int)$tx['is_reversal']): ?>
                <span class="badge badge-warning" style="font-size:.7rem">REV</span>
              <?php endif; ?>
              <?php if ((int)$tx['review_required']): ?>
                <span class="badge badge-danger" style="font-size:.7rem">⚠</span>
              <?php endif; ?>
            </td>
            <td>
              <button type="button" class="btn btn-xs btn-secondary gl-expand-btn"
                      data-tx="<?= (int)$tx['id'] ?>">▼ Entries</button>
            </td>
          </tr>
          <!-- GLE expandable row -->
          <tr class="gl-gle-row" id="gle-<?= (int)$tx['id'] ?>" style="display:none">
            <td colspan="8" style="background:#fafafa;padding:.75rem 1.5rem">
              <?php if (empty($gles)): ?>
                <em style="color:#aaa">No GL entries found.</em>
              <?php else: ?>
              <table style="width:100%;font-size:.83rem;border-collapse:collapse">
                <thead>
                  <tr style="background:#f0f0f0">
                    <th style="padding:.3rem .6rem;text-align:left">Account</th>
                    <th style="padding:.3rem .6rem;text-align:left">Entry Type</th>
                    <th style="padding:.3rem .6rem;text-align:right">Debit</th>
                    <th style="padding:.3rem .6rem;text-align:right">Credit</th>
                    <th style="padding:.3rem .6rem;text-align:right">Running Bal</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($gles as $gle): ?>
                  <tr style="border-top:1px solid #e8e8e8">
                    <td style="padding:.3rem .6rem;font-family:monospace"><?= htmlspecialchars((string)$gle['account_code']) ?></td>
                    <td style="padding:.3rem .6rem"><?= htmlspecialchars((string)($gle['entry_type'] ?? '')) ?></td>
                    <td style="padding:.3rem .6rem;text-align:right;color:<?= (float)$gle['debit_amount'] > 0 ? '#c0392b' : '#aaa' ?>">
                      <?= (float)$gle['debit_amount'] > 0 ? '₹' . number_format((float)$gle['debit_amount'], 2) : '—' ?>
                    </td>
                    <td style="padding:.3rem .6rem;text-align:right;color:<?= (float)$gle['credit_amount'] > 0 ? '#27ae60' : '#aaa' ?>">
                      <?= (float)$gle['credit_amount'] > 0 ? '₹' . number_format((float)$gle['credit_amount'], 2) : '—' ?>
                    </td>
                    <td style="padding:.3rem .6rem;text-align:right;font-weight:600">
                      ₹<?= number_format((float)($gle['running_balance_after'] ?? 0), 2) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($rows)): ?>
          <tr><td colspan="8" style="text-align:center;padding:2rem;color:#aaa">No transactions found for the selected filters.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <div style="padding:.75rem 1rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <?php if ($page > 1): ?>
        <a href="<?= gl_register_url(['page' => $page - 1]) ?>" class="btn btn-sm btn-secondary">← Prev</a>
      <?php endif; ?>
      <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
      ?>
        <a href="<?= gl_register_url(['page' => $p]) ?>"
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= gl_register_url(['page' => $page + 1]) ?>" class="btn btn-sm btn-secondary">Next →</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.gl-expand-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var txId = btn.getAttribute('data-tx');
        var row = document.getElementById('gle-' + txId);
        if (!row) return;
        var hidden = row.style.display === 'none';
        row.style.display = hidden ? 'table-row' : 'none';
        btn.textContent = hidden ? '▲ Entries' : '▼ Entries';
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
