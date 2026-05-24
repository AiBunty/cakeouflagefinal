<?php
$pageTitle = 'CRM Report';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('crm_report');
require_once __DIR__ . '/includes/crm_report_helpers.php';

function crm_report_build_url(array $overrides = array()): string
{
  $params = array(
    'sub_report' => $_GET['sub_report'] ?? 'overview',
    'q' => $_GET['q'] ?? '',
    'per_page' => $_GET['per_page'] ?? '20',
    'page' => $_GET['page'] ?? '1',
  );

  foreach ($overrides as $k => $v) {
    $params[$k] = $v;
  }

  foreach ($params as $k => $v) {
    if ($v === '' || $v === null) {
      unset($params[$k]);
    }
  }

  return 'crm_report.php?' . http_build_query($params);
}

$subReportOptions = array(
  'overview' => 'Overview Summary',
  'users' => 'Customer Report',
  'followups' => 'Follow-Up Scheduling',
  'jobs' => 'Skipped CRM Jobs',
);

$selectedSubReport = strtolower(trim((string)($_GET['sub_report'] ?? 'overview')));
if (!isset($subReportOptions[$selectedSubReport])) {
  $selectedSubReport = 'overview';
}

$q = trim((string)($_GET['q'] ?? ''));
$perPageOptions = array(20, 50, 100);
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, $perPageOptions, true)) {
  $perPage = 20;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$totalUsers = ($selectedSubReport === 'users') ? fetch_crm_report_users_count($conn, $q) : 0;
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
if ($page > $totalPages) {
  $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$users = ($selectedSubReport === 'users') ? fetch_crm_report_users($conn, $q, $perPage, $offset) : array();
$summary = fetch_crm_report_summary($conn);
$followUps = ($selectedSubReport === 'followups') ? fetch_crm_follow_up_reminders($conn, 8) : array();
$skippedJobs = ($selectedSubReport === 'jobs') ? fetch_skipped_crm_jobs($conn, 8) : array();
$queueMode = fetch_crm_queue_push_mode($conn);

$export = strtolower(trim((string)($_GET['export'] ?? '')));
if (in_array($export, array('excel', 'csv', 'pdf'), true)) {
  $exportTotalUsers = fetch_crm_report_users_count($conn, $q);
  $exportLimit = $exportTotalUsers > 0 ? $exportTotalUsers : 1;
  $exportUsers = fetch_crm_report_users($conn, $q, $exportLimit, 0);

  if ($export === 'excel') {
    export_users_excel($exportUsers);
  }

  if ($export === 'pdf') {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>CRM Report</title><style>';
    echo 'body{font-family:Arial,Helvetica,sans-serif;color:#111;background:#fff;margin:18px}';
    echo 'h1{margin:0 0 10px}';
    echo 'table{width:100%;border-collapse:collapse;margin-top:12px}';
    echo 'th,td{border:1px solid #111;padding:7px;font-size:12px;text-align:left}';
    echo 'th{background:#efefef}';
    echo '</style></head><body>';
    echo '<h1>CRM Report</h1>';
    echo '<div><strong>Search:</strong> ' . htmlspecialchars($q !== '' ? $q : 'All Users', ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<table><tr><th>Name</th><th>Phone</th><th>Email</th><th>Orders</th><th>Completed</th><th>Pending</th></tr>';
    foreach ($exportUsers as $u) {
      echo '<tr>';
      echo '<td>' . htmlspecialchars((string)($u['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
      echo '<td>' . htmlspecialchars((string)($u['phone'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
      echo '<td>' . htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
      echo '<td>' . (int)($u['order_count'] ?? 0) . '</td>';
      echo '<td>' . (int)($u['completed_count'] ?? 0) . '</td>';
      echo '<td>' . (int)($u['pending_count'] ?? 0) . '</td>';
      echo '</tr>';
    }
    echo '</table><script>window.print();</script></body></html>';
    exit;
  }
}

require_once __DIR__ . '/layout.php';
?>
<style>
  .crm-report-shell {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
  }

  .crm-report-side,
  .crm-report-card,
  .crm-report-panel {
    background: var(--admin-surface, #fffdfd);
    border-radius: 18px;
    border: 1px solid rgba(128, 0, 31, 0.1);
    box-shadow: 0 14px 30px rgba(96, 18, 45, 0.08);
    overflow: hidden;
  }

  .crm-report-side {
    position: sticky;
    top: 12px;
  }

  .crm-report-side__head {
    padding: 18px 20px 12px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
  }

  .crm-report-side__head h2 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
  }

  .crm-report-side__head p {
    margin: 6px 0 0;
    color: #8f7681;
    font-size: 0.88rem;
  }

  .crm-report-menu {
    list-style: none;
    margin: 0;
    padding: 10px;
    display: grid;
    gap: 8px;
  }

  .crm-report-menu a {
    display: block;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(128, 0, 31, 0.14);
    color: #80001F;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 700;
  }

  .crm-report-menu a.active {
    background: #80001F;
    color: #fff;
    border-color: #80001F;
  }

  .crm-report-main {
    display: grid;
    gap: 18px;
  }

  .crm-report-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
  }

  .crm-report-card {
    padding: 18px 18px 16px;
  }

  .crm-report-card strong {
    display: block;
    color: #80001F;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
  }

  .crm-report-card span {
    font-family: 'DM Serif Display', Georgia, serif;
    font-size: 2rem;
    color: #2d1f25;
  }

  .crm-report-panel__head {
    padding: 18px 20px 12px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
  }

  .crm-report-panel__head h2,
  .crm-report-panel__head h3 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
  }

  .crm-report-panel__head p {
    margin: 6px 0 0;
    color: #8f7681;
    font-size: 0.92rem;
  }

  .crm-report-panel__body {
    padding: 20px;
  }

  .crm-report-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }

  .crm-report-form input {
    min-width: 260px;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1px solid rgba(128, 0, 31, 0.16);
    font: inherit;
  }

  .crm-report-form select {
    padding: 11px 12px;
    border-radius: 12px;
    border: 1px solid rgba(128, 0, 31, 0.16);
    font: inherit;
  }

  .crm-report-btn,
  .crm-report-btn:link,
  .crm-report-btn:visited {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 14px;
    border-radius: 12px;
    background: #80001F;
    color: #fff;
    text-decoration: none;
    border: none;
    font-weight: 600;
    cursor: pointer;
  }

  .crm-report-btn--ghost {
    background: #f8d8de;
    color: #80001F;
  }

  .crm-report-tag {
    display: inline-flex;
    border-radius: 999px;
    padding: 6px 11px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .crm-report-tag--paused { background: #fff2cf; color: #9a5b00; }
  .crm-report-tag--enabled { background: #dcfce7; color: #166534; }

  .crm-report-table-wrap {
    overflow: auto;
  }

  .crm-report-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 860px;
  }

  .crm-report-table th,
  .crm-report-table td {
    padding: 12px 10px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    text-align: left;
    vertical-align: top;
  }

  .crm-report-table th {
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #80001F;
    background: #fff6f8;
  }

  .crm-list {
    display: grid;
    gap: 10px;
  }

  .crm-list-item {
    border: 1px solid rgba(128, 0, 31, 0.08);
    border-radius: 14px;
    background: #fff8fa;
    padding: 12px 14px;
  }

  .crm-list-item strong {
    display: block;
    color: #80001F;
    margin-bottom: 4px;
  }

  .crm-list-item p {
    margin: 4px 0 0;
    color: #6e2a3e;
    font-size: 0.9rem;
  }

  .crm-report-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 14px;
  }

  .crm-report-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-top: 14px;
  }

  .crm-report-pagination__meta {
    color: #6e2a3e;
    font-size: 0.86rem;
  }

  @media (max-width: 1180px) {
    .crm-report-shell {
      grid-template-columns: 1fr;
    }

    .crm-report-side {
      position: static;
    }
  }

  @media (max-width: 1080px) {
    .crm-report-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 760px) {
    .crm-report-grid {
      grid-template-columns: 1fr;
    }

    .crm-report-form input {
      width: 100%;
      min-width: 0;
    }
  }
</style>

<div class="crm-report-shell">
  <aside class="crm-report-side">
    <div class="crm-report-side__head">
      <h2>CRM Report</h2>
      <p>Main category with sub-reports. Open one module at a time.</p>
      <p>
        Queue Mode:
        <span class="crm-report-tag <?= $queueMode === 'enabled' ? 'crm-report-tag--enabled' : 'crm-report-tag--paused' ?>">
          <?= htmlspecialchars($queueMode, ENT_QUOTES, 'UTF-8') ?>
        </span>
      </p>
    </div>
    <ul class="crm-report-menu">
      <?php foreach ($subReportOptions as $key => $label): ?>
        <li><a class="<?= $selectedSubReport === $key ? 'active' : '' ?>" href="<?= htmlspecialchars(crm_report_build_url(array('sub_report' => $key, 'page' => 1)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <div class="crm-report-main">
  <?php if ($selectedSubReport === 'overview'): ?>
    <div class="crm-report-grid">
      <div class="crm-report-card"><strong>Users</strong><span><?= (int) $summary['users'] ?></span></div>
      <div class="crm-report-card"><strong>Orders</strong><span><?= (int) $summary['orders'] ?></span></div>
      <div class="crm-report-card"><strong>Follow Ups</strong><span><?= (int) $summary['follow_ups'] ?></span></div>
      <div class="crm-report-card"><strong>CRM Jobs</strong><span><?= (int) $summary['skipped_jobs'] ?></span></div>
    </div>
  <?php endif; ?>

  <?php if ($selectedSubReport === 'users'): ?>
    <section class="crm-report-panel">
    <div class="crm-report-panel__head">
      <h2>Customer Report</h2>
      <p>Search customers, drill into order history, and export user report files.</p>
    </div>
    <div class="crm-report-panel__body">
      <form class="crm-report-form" method="get">
        <input type="hidden" name="sub_report" value="users">
        <input type="text" name="q" placeholder="Search name, email, phone..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
        <select name="per_page" aria-label="Rows per page">
          <?php foreach ($perPageOptions as $size): ?>
            <option value="<?= (int)$size ?>" <?= $perPage === (int)$size ? 'selected' : '' ?>><?= (int)$size ?> / page</option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="page" value="1">
        <button class="crm-report-btn" type="submit">Search</button>
        <button class="crm-report-btn crm-report-btn--ghost" type="submit" name="export" value="excel">Download Excel (.xlsx)</button>
        <button class="crm-report-btn crm-report-btn--ghost" type="submit" name="export" value="pdf">Download PDF</button>
      </form>

      <div class="crm-report-table-wrap">
        <table class="crm-report-table">
          <tr>
            <th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Completed</th><th>Pending</th><th>History</th>
          </tr>
          <?php if (!$users): ?>
            <tr><td colspan="7">No matching customers found.</td></tr>
          <?php endif; ?>
          <?php foreach ($users as $user): ?>
            <tr>
              <td><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int) ($user['order_count'] ?? 0) ?></td>
              <td><?= (int) ($user['completed_count'] ?? 0) ?></td>
              <td><?= (int) ($user['pending_count'] ?? 0) ?></td>
              <td><a class="crm-report-btn crm-report-btn--ghost" href="crm_user_history.php?user_id=<?= (int) $user['id'] ?>&q=<?= urlencode($q) ?>">History</a></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="crm-report-pagination">
        <span class="crm-report-pagination__meta">Showing <?= count($users) ?> of <?= (int)$totalUsers ?> users</span>
        <?php if ($page > 1): ?>
          <a class="crm-report-btn crm-report-btn--ghost" href="<?= htmlspecialchars(crm_report_build_url(array('page' => $page - 1)), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="crm-report-btn crm-report-btn--ghost" href="<?= htmlspecialchars(crm_report_build_url(array('page' => $page + 1)), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
    </section>
  <?php endif; ?>

  <?php if ($selectedSubReport === 'followups'): ?>
    <section class="crm-report-panel">
    <div class="crm-report-panel__head">
      <h3>Completed-Order Follow-Up Scheduling</h3>
      <p>Recent follow-up reminders created from completed orders.</p>
    </div>
    <div class="crm-report-panel__body">
      <div class="crm-list">
        <?php if (!$followUps): ?>
          <div class="crm-list-item">
            <strong>No follow-up reminders yet</strong>
            <p>Completed orders will appear here once follow-up scheduling runs.</p>
          </div>
        <?php endif; ?>
        <?php foreach ($followUps as $followUp): ?>
          <?php $notes = json_decode((string) ($followUp['notes'] ?? ''), true); $notes = is_array($notes) ? $notes : []; ?>
          <div class="crm-list-item">
            <strong><?= htmlspecialchars((string) ($notes['follow_up_type'] ?? $followUp['title']), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($followUp['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            <p>Scheduled for <?= htmlspecialchars((string) ($followUp['reminder_on'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · created <?= htmlspecialchars((string) ($followUp['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <p>Order <?= htmlspecialchars((string) ($notes['order_number'] ?? 'n/a'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($notes['customer_name'] ?? 'Unknown customer'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="crm-report-actions">
        <a class="crm-report-btn crm-report-btn--ghost" href="follow_ups.php">Open Follow Ups Module</a>
        <a class="crm-report-btn crm-report-btn--ghost" href="crm_settings.php">Open CRM Settings</a>
      </div>
    </div>
    </section>
  <?php endif; ?>

  <?php if ($selectedSubReport === 'jobs'): ?>
    <section class="crm-report-panel">
    <div class="crm-report-panel__head">
      <h3>Skipped CRM Jobs</h3>
      <p>These jobs were bypassed or failed so operators can inspect the reason.</p>
    </div>
    <div class="crm-report-panel__body">
      <div class="crm-list">
        <?php if (!$skippedJobs): ?>
          <div class="crm-list-item">
            <strong>No CRM job history</strong>
            <p>When CRM trigger pushes are paused or fail, the reason will appear here.</p>
          </div>
        <?php endif; ?>
        <?php foreach ($skippedJobs as $job): ?>
          <?php $payload = json_decode((string) ($job['payload_json'] ?? ''), true); $payload = is_array($payload) ? $payload : []; ?>
          <div class="crm-list-item">
            <strong><?= htmlspecialchars((string) ($job['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · job #<?= (int) ($job['id'] ?? 0) ?></strong>
            <p>Setting: <?= htmlspecialchars((string) ($payload['setting_key'] ?? 'crm_trigger_push'), ENT_QUOTES, 'UTF-8') ?> · attempts: <?= (int) ($job['attempts'] ?? 0) ?> · updated <?= htmlspecialchars((string) ($job['updated_at'] ?? $job['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= htmlspecialchars((string) ($job['last_error'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="crm-report-actions">
        <a class="crm-report-btn crm-report-btn--ghost" href="crm_push_logs.php">Open CRM Push Logs</a>
      </div>
    </div>
    </section>
  <?php endif; ?>

  <?php if ($selectedSubReport === 'overview'): ?>
    <section class="crm-report-panel">
      <div class="crm-report-panel__head">
        <h3>Overview Snapshot</h3>
        <p>Top-level CRM metrics for quick operations review.</p>
      </div>
      <div class="crm-report-panel__body">
        <div class="crm-report-table-wrap">
          <table class="crm-report-table" style="min-width: 640px;">
            <tr><th>Metric</th><th>Value</th></tr>
            <tr><td>Total Users</td><td><?= (int) $summary['users'] ?></td></tr>
            <tr><td>Total Orders</td><td><?= (int) $summary['orders'] ?></td></tr>
            <tr><td>Follow Ups Scheduled</td><td><?= (int) $summary['follow_ups'] ?></td></tr>
            <tr><td>Skipped/Failed CRM Jobs</td><td><?= (int) $summary['skipped_jobs'] ?></td></tr>
            <tr><td>Queue Mode</td><td><?= htmlspecialchars($queueMode, ENT_QUOTES, 'UTF-8') ?></td></tr>
          </table>
        </div>
      </div>
    </section>
  <?php endif; ?>
  </div>
</div>
