<?php
$pageTitle = 'CRM Report';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('crm_report');
require_once __DIR__ . '/includes/crm_helpers.php';

function crm_report_build_url(array $overrides = array()): string
{
  $params = array(
    'sub_report' => $_GET['sub_report'] ?? 'overview',
    'q' => $_GET['q'] ?? '',
    'segment' => $_GET['segment'] ?? 'all',
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
  'users' => 'Customer Intelligence',
  'followups' => 'Follow-Up Scheduling',
  'jobs' => 'Skipped CRM Jobs',
);

$selectedSubReport = strtolower(trim((string)($_GET['sub_report'] ?? 'overview')));
if (!isset($subReportOptions[$selectedSubReport])) {
  $selectedSubReport = 'overview';
}

$q = trim((string)($_GET['q'] ?? ''));
$segmentOptions = array(
  'all' => 'All Customers',
  'repeat_customers' => 'Repeat Customers',
  'refunded_users' => 'Refunded Users',
  'high_spenders' => 'High Spenders',
  'inactive_customers' => 'Inactive Customers',
  'pending_payments' => 'Pending Payments',
  'birthday_event_buyers' => 'Birthday/Event Buyers',
  'recent_buyers' => 'Recent Buyers',
);
$selectedSegment = strtolower(trim((string)($_GET['segment'] ?? 'all')));
if (!isset($segmentOptions[$selectedSegment])) {
  $selectedSegment = 'all';
}

$perPageOptions = array(20, 50, 100);
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, $perPageOptions, true)) {
  $perPage = 20;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$totalUsers = ($selectedSubReport === 'users') ? fetch_crm_customer_intelligence_count($conn, $q, $selectedSegment) : 0;
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
if ($page > $totalPages) {
  $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$users = ($selectedSubReport === 'users') ? fetch_crm_customer_intelligence_rows($conn, $q, $selectedSegment, $perPage, $offset) : array();
$intelligenceSummary = ($selectedSubReport === 'users') ? fetch_crm_order_intelligence_summary_header($conn) : array();
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
<link rel="stylesheet" href="assets/css/crm-report.css">

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
      <h2>Order History + Customer Timeline</h2>
      <p>Central customer intelligence screen with lazy customer timeline expansion, tags, and follow-up actions.</p>
    </div>
    <div class="crm-report-panel__body">
      <div class="crm-summary-grid">
        <article class="crm-summary-card">
          <div class="crm-summary-card__label">Total Customers</div>
          <div class="crm-summary-card__value"><?= (int)($intelligenceSummary['total_customers'] ?? 0) ?></div>
        </article>
        <article class="crm-summary-card">
          <div class="crm-summary-card__label">Repeat Buyers</div>
          <div class="crm-summary-card__value"><?= (int)($intelligenceSummary['repeat_buyers'] ?? 0) ?></div>
        </article>
        <article class="crm-summary-card">
          <div class="crm-summary-card__label">Revenue Generated</div>
          <div class="crm-summary-card__value">₹<?= number_format((float)($intelligenceSummary['revenue_generated'] ?? 0), 0) ?></div>
        </article>
        <article class="crm-summary-card">
          <div class="crm-summary-card__label">Pending Follow-ups</div>
          <div class="crm-summary-card__value"><?= (int)($intelligenceSummary['pending_follow_ups'] ?? 0) ?></div>
        </article>
        <article class="crm-summary-card">
          <div class="crm-summary-card__label">Refund Customers</div>
          <div class="crm-summary-card__value"><?= (int)($intelligenceSummary['refund_customers'] ?? 0) ?></div>
        </article>
        <article class="crm-summary-card">
          <div class="crm-summary-card__label">Active Today</div>
          <div class="crm-summary-card__value"><?= (int)($intelligenceSummary['active_today'] ?? 0) ?></div>
        </article>
      </div>

      <form class="crm-toolbar" method="get">
        <input type="hidden" name="sub_report" value="users">
        <input type="text" name="q" placeholder="Universal search: customer, email, phone, order number, item..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
        <select name="segment" aria-label="Segment">
          <?php foreach ($segmentOptions as $segmentKey => $segmentLabel): ?>
            <option value="<?= htmlspecialchars($segmentKey, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedSegment === $segmentKey ? 'selected' : '' ?>><?= htmlspecialchars($segmentLabel, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
        <select name="per_page" aria-label="Rows per page">
          <?php foreach ($perPageOptions as $size): ?>
            <option value="<?= (int)$size ?>" <?= $perPage === (int)$size ? 'selected' : '' ?>><?= (int)$size ?> / page</option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="page" value="1">
        <button class="crm-btn crm-btn--primary" type="submit">Apply Filters</button>
        <button class="crm-btn crm-btn--soft" type="submit" name="export" value="excel">Download Excel</button>
        <button class="crm-btn crm-btn--soft" type="submit" name="export" value="pdf">Download PDF</button>
      </form>

      <div class="crm-grid">
        <table class="crm-table">
          <thead>
          <tr>
            <th style="width: 30%">Customer</th>
            <th style="width: 33%">Order Intelligence</th>
            <th style="width: 20%">Recent Activity</th>
            <th style="width: 17%">Actions</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$users): ?>
            <tr><td colspan="4"><div class="crm-empty">No matching customers found.</div></td></tr>
          <?php endif; ?>
          <?php foreach ($users as $user): ?>
            <?php
              $badge = strtolower((string)($user['customer_badge'] ?? 'new'));
              $badgeClass = 'crm-badge';
              if ($badge === 'vip') {
                $badgeClass .= ' crm-badge--vip';
              } elseif ($badge === 'repeat') {
                $badgeClass .= ' crm-badge--repeat';
              } elseif ($badge === 'active') {
                $badgeClass .= ' crm-badge--active';
              }
              $waPhone = preg_replace('/[^0-9+]/', '', (string)($user['phone'] ?? ''));
              $waHref = $waPhone !== '' ? 'https://wa.me/' . rawurlencode($waPhone) : '#';
              $emailHref = !empty($user['email']) ? 'mailto:' . rawurlencode((string)$user['email']) : '#';
              $callHref = $waPhone !== '' ? 'tel:' . $waPhone : '#';
            ?>
            <tr class="crm-customer-row">
              <td>
                <div class="crm-customer-left">
                  <div class="crm-customer-name"><?= htmlspecialchars((string)$user['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="crm-customer-meta"><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="crm-customer-meta"><?= htmlspecialchars((string)$user['phone'], ENT_QUOTES, 'UTF-8') ?></div>
                  <span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($user['customer_badge'] ?? 'New'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </td>
              <td>
                <div class="crm-kpis">
                  <div class="crm-kpi">
                    <div class="crm-kpi__label">Orders</div>
                    <div class="crm-kpi__value"><?= (int)($user['order_count'] ?? 0) ?></div>
                  </div>
                  <div class="crm-kpi">
                    <div class="crm-kpi__label">Lifetime Spend</div>
                    <div class="crm-kpi__value">₹<?= number_format((float)($user['total_spend'] ?? 0), 0) ?></div>
                  </div>
                  <div class="crm-kpi">
                    <div class="crm-kpi__label">Pending Orders</div>
                    <div class="crm-kpi__value"><?= (int)($user['pending_orders'] ?? 0) ?></div>
                  </div>
                  <div class="crm-kpi">
                    <div class="crm-kpi__label">Refund Value</div>
                    <div class="crm-kpi__value">₹<?= number_format((float)($user['refund_total'] ?? 0), 0) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="crm-customer-meta">Last Order: <?= htmlspecialchars((string)($user['last_order_at'] ?? 'No orders yet'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="crm-customer-meta">Pending Payments: <?= (int)($user['pending_payment_orders'] ?? 0) ?></div>
                <div class="crm-customer-meta">Refund Orders: <?= (int)($user['refund_orders'] ?? 0) ?></div>
                <?php if (!empty($user['tags']) && is_array($user['tags'])): ?>
                  <div class="crm-customer-meta">Tags: <?= htmlspecialchars(implode(', ', $user['tags']), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="crm-actions">
                  <button type="button" class="crm-btn crm-btn--primary js-crm-expand" data-user-id="<?= (int)$user['id'] ?>">View History</button>
                  <a class="crm-icon-btn" href="<?= htmlspecialchars($waHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">WA</a>
                  <a class="crm-icon-btn" href="<?= htmlspecialchars($callHref, ENT_QUOTES, 'UTF-8') ?>">Call</a>
                  <a class="crm-icon-btn" href="<?= htmlspecialchars($emailHref, ENT_QUOTES, 'UTF-8') ?>">Mail</a>
                </div>
              </td>
            </tr>
            <tr class="crm-expand-row js-crm-expand-row" data-user-id="<?= (int)$user['id'] ?>">
              <td colspan="4" class="crm-expand-cell">
                <div class="crm-expand-panel js-crm-panel" data-user-id="<?= (int)$user['id'] ?>" data-page="1">
                  <div class="crm-expand-loading">Expand row to load full customer timeline...</div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="crm-report-pagination">
        <span class="crm-report-pagination__meta">Showing <?= count($users) ?> of <?= (int)$totalUsers ?> users</span>
        <?php if ($page > 1): ?>
          <a class="crm-btn crm-btn--ghost" href="<?= htmlspecialchars(crm_report_build_url(array('page' => $page - 1)), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="crm-btn crm-btn--ghost" href="<?= htmlspecialchars(crm_report_build_url(array('page' => $page + 1)), ENT_QUOTES, 'UTF-8') ?>">Next</a>
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

<?php if ($selectedSubReport === 'users'): ?>
  <script src="assets/js/crm-report.js"></script>
<?php endif; ?>
