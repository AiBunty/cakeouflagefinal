<?php
$pageTitle = 'Chart of Accounts';
require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');
require_once __DIR__ . '/includes/db.php';

use App\Core\Database;

$db = Database::getInstance();

$fromDate = trim((string)($_GET['date_from'] ?? date('Y-m-01'))); // default to month start
$toDate   = trim((string)($_GET['date_to']   ?? date('Y-m-d')));

// Load all ledger accounts with hierarchy + period totals via GL entries
$accounts = $db->fetchAll(
    "SELECT la.id, la.account_code, la.account_name, la.account_type,
            la.account_group, la.parent_account_id,
            la.account_number, la.is_active,
            COALESCE(SUM(gle.debit_amount),  0) AS period_debits,
            COALESCE(SUM(gle.credit_amount), 0) AS period_credits
       FROM ledger_accounts la
       LEFT JOIN general_ledger_entries gle
              ON gle.account_code = la.account_code
             AND gle.posting_date BETWEEN :d1 AND :d2
      GROUP BY la.id
      ORDER BY COALESCE(la.account_number, la.account_code) ASC",
    ['d1' => $fromDate, 'd2' => $toDate]
) ?: [];

// Build hierarchy map
$byId     = [];
$roots    = [];
foreach ($accounts as $acc) {
    $acc['children'] = [];
    $byId[(int)$acc['id']] = $acc;
}
foreach ($byId as $id => &$acc) {
    $pid = (int)$acc['parent_account_id'];
    if ($pid && isset($byId[$pid])) {
        $byId[$pid]['children'][] = &$acc;
    } else {
        $roots[] = &$acc;
    }
}
unset($acc);

function coa_type_badge(string $type): string
{
    $map = [
        'asset'          => '#3498db',
        'liability'      => '#e67e22',
        'equity'         => '#9b59b6',
        'revenue'        => '#27ae60',
        'expense'        => '#c0392b',
        'contra_revenue' => '#95a5a6',
    ];
    $color = $map[$type] ?? '#888';
    return "<span style=\"font-size:.7rem;padding:.15rem .5rem;border-radius:10px;background:{$color}22;color:{$color};font-weight:600\">"
         . htmlspecialchars(strtoupper($type)) . '</span>';
}

function coa_render_row(array $acc, int $depth = 0): void
{
    $indent      = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
    $debits      = (float)$acc['period_debits'];
    $credits     = (float)$acc['period_credits'];
    $netBalance  = $credits - $debits; // credit-normal perspective
    $isInactive  = !(int)$acc['is_active'];
    ?>
    <tr style="<?= $isInactive ? 'opacity:.45' : '' ?>">
      <td style="padding:.4rem .75rem;font-family:monospace;font-size:.82rem">
        <?= $indent ?><?= htmlspecialchars((string)$acc['account_code']) ?>
      </td>
      <td style="padding:.4rem .75rem">
        <?= $indent ?><?= htmlspecialchars((string)$acc['account_name']) ?>
        <?php if ($isInactive): ?>
          <span style="font-size:.7rem;color:#aaa;margin-left:.25rem">(inactive)</span>
        <?php endif; ?>
      </td>
      <td style="padding:.4rem .75rem"><?= coa_type_badge((string)$acc['account_type']) ?></td>
      <td style="padding:.4rem .75rem;font-size:.8rem;color:#888"><?= htmlspecialchars((string)$acc['account_group']) ?></td>
      <td style="padding:.4rem .75rem;text-align:right;color:#c0392b"><?= $debits > 0 ? '₹' . number_format($debits, 2) : '—' ?></td>
      <td style="padding:.4rem .75rem;text-align:right;color:#27ae60"><?= $credits > 0 ? '₹' . number_format($credits, 2) : '—' ?></td>
      <td style="padding:.4rem .75rem;text-align:right;font-weight:600;color:<?= $netBalance >= 0 ? '#27ae60' : '#c0392b' ?>">
        <?= ($debits + $credits) > 0 ? '₹' . number_format(abs($netBalance), 2) . ($netBalance < 0 ? ' Dr' : ' Cr') : '—' ?>
      </td>
    </tr>
    <?php
    foreach ($acc['children'] as $child) {
        coa_render_row($child, $depth + 1);
    }
}
?>
<div class="page-content">
  <div class="page-header">
    <h1 class="page-title">Chart of Accounts</h1>
  </div>

  <!-- Date filter -->
  <form method="get" class="card mb-3" style="padding:.75rem 1rem">
    <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label style="font-size:.8rem;font-weight:600">From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($fromDate) ?>"
               style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600">To</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($toDate) ?>"
               style="display:block;padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px">
      </div>
      <button type="submit" class="btn btn-sm btn-primary">Show Period</button>
      <a href="?" class="btn btn-sm btn-secondary">This Month</a>
    </div>
  </form>

  <div class="card">
    <div class="card-header">
      <strong>Accounts — Period <?= htmlspecialchars($fromDate) ?> to <?= htmlspecialchars($toDate) ?></strong>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.875rem">
        <thead>
          <tr style="background:#f5f0f1;font-size:.8rem;font-weight:700;text-transform:uppercase;color:#80001F">
            <th style="padding:.5rem .75rem;text-align:left">Code</th>
            <th style="padding:.5rem .75rem;text-align:left">Account Name</th>
            <th style="padding:.5rem .75rem">Type</th>
            <th style="padding:.5rem .75rem">Group</th>
            <th style="padding:.5rem .75rem;text-align:right">Debits</th>
            <th style="padding:.5rem .75rem;text-align:right">Credits</th>
            <th style="padding:.5rem .75rem;text-align:right">Balance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roots as $root): ?>
            <?php coa_render_row($root, 0); ?>
          <?php endforeach; ?>
          <?php if (empty($roots)): ?>
          <tr><td colspan="7" style="text-align:center;padding:2rem;color:#aaa">No accounts found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
