<?php
$pageTitle = 'Collections Queue';
require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');

use App\Core\Csrf;
use App\Core\Database;
use App\Services\FinanceReportService;

$financeReports = new FinanceReportService();
$db = Database::getInstance();

$perPageOptions = [20, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 20;
}
$page = max(1, (int)($_GET['page'] ?? 1));

$input = $_GET;
if (!isset($input['payment_status']) || trim((string)$input['payment_status']) === '') {
    $input['payment_status'] = 'pending_collection';
}

$filters = $financeReports->normalizeFilters($input);
$queueFilters = $financeReports->normalizeCollectionQueueFilters($input);
$queue = $financeReports->getCollectionsQueue($filters, $queueFilters, $perPage, $page);
$rows = $queue['rows'];
$totals = $queue['totals'];
$totalRows = (int)$queue['totalRows'];
$totalPages = (int)$queue['totalPages'];
$page = (int)$queue['page'];

$flashError = trim((string)($_GET['error'] ?? ''));
$flashMessage = trim((string)($_GET['message'] ?? ''));

$orderIds = [];
foreach ($rows as $row) {
    $orderIds[] = (int)$row['id'];
}

$logsByOrder = [];
$timelineSummaryByOrder = [];
$hasCollectionFollowupLogs = false;
try {
  $hasCollectionFollowupLogs = (int)$db->fetchScalar(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name',
    ['table_name' => 'collection_followup_logs']
  ) > 0;
} catch (\Throwable $e) {
  $hasCollectionFollowupLogs = false;
}
if (!empty($orderIds)) {
  if ($hasCollectionFollowupLogs) {
    try {
      $timelineSummaryRows = $db->fetchAll(
        'SELECT
          s.order_id,
          s.total_events,
          l.actor_name AS last_actor_name,
          l.created_at AS last_created_at
         FROM (
          SELECT order_id, COUNT(*) AS total_events, MAX(id) AS max_id
          FROM collection_followup_logs
          WHERE order_id IN (' . implode(',', array_map('intval', $orderIds)) . ')
          GROUP BY order_id
         ) s
         LEFT JOIN collection_followup_logs l ON l.id = s.max_id'
      );
      foreach ($timelineSummaryRows as $summary) {
        $oid = (int)($summary['order_id'] ?? 0);
        $timelineSummaryByOrder[$oid] = [
          'total_events' => (int)($summary['total_events'] ?? 0),
          'last_actor_name' => trim((string)($summary['last_actor_name'] ?? '')),
          'last_created_at' => trim((string)($summary['last_created_at'] ?? '')),
        ];
      }

      $logRows = $db->fetchAll(
          'SELECT order_id, action_type, followup_status, message_text, actor_name, created_at
           FROM collection_followup_logs
           WHERE order_id IN (' . implode(',', array_map('intval', $orderIds)) . ')
           ORDER BY created_at DESC
           LIMIT 500'
      );

      foreach ($logRows as $log) {
          $oid = (int)($log['order_id'] ?? 0);
          if (!isset($logsByOrder[$oid])) {
              $logsByOrder[$oid] = [];
          }
          if (count($logsByOrder[$oid]) < 3) {
              $logsByOrder[$oid][] = $log;
          }
      }
    } catch (\Throwable $e) {
      error_log('[collections_queue][timeline] ' . $e->getMessage());
    }
  }

  if (!$hasCollectionFollowupLogs && $flashError === '') {
    $flashError = 'Timeline log table is not available yet. Queue actions are still usable.';
  }
}

function collection_queue_url(array $overrides = []): string
{
    $params = [
        'date_preset' => $_GET['date_preset'] ?? 'this_month',
        'date_basis' => $_GET['date_basis'] ?? 'payment',
        'from_date' => $_GET['from_date'] ?? date('Y-m-01'),
        'to_date' => $_GET['to_date'] ?? date('Y-m-d'),
        'order_no' => $_GET['order_no'] ?? '',
        'item' => $_GET['item'] ?? '',
        'mobile' => $_GET['mobile'] ?? '',
        'payment_status' => $_GET['payment_status'] ?? 'pending_collection',
        'order_status' => $_GET['order_status'] ?? 'all',
        'payment_method' => $_GET['payment_method'] ?? 'all',
        'followup_status' => $_GET['followup_status'] ?? 'all',
        'collection_priority' => $_GET['collection_priority'] ?? 'all',
        'action_due' => $_GET['action_due'] ?? 'all',
        'per_page' => $_GET['per_page'] ?? '20',
        'page' => $_GET['page'] ?? '1',
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return 'collections_queue.php?' . http_build_query($params);
}

function queue_status_badge(string $status): string
{
    return match ($status) {
        'escalated' => 'cq-badge bad',
        'payment_promised', 'reminder_sent' => 'cq-badge warn',
        'settled', 'customer_responded' => 'cq-badge ok',
        default => 'cq-badge info',
    };
}

function queue_write_export_audit(Database $db, array $payload): void
{
  try {
    $createdAt = date('Y-m-d H:i:s');
    $db->execute(
      'INSERT INTO ar_export_lock_audit
        (archive_month, lock_token, source, event_type, variant, format, fingerprint, filters_json, issued_by_admin_id, issued_by_name, request_ip, user_agent, created_at)
       VALUES
        (:archive_month, :lock_token, :source, :event_type, :variant, :format, :fingerprint, :filters_json, :issued_by_admin_id, :issued_by_name, :request_ip, :user_agent, :created_at)',
      [
        'archive_month' => date('Y-m', strtotime($createdAt)),
        'lock_token' => (string)($payload['lock_token'] ?? ''),
        'source' => (string)($payload['source'] ?? 'collections_queue'),
        'event_type' => (string)($payload['event_type'] ?? 'issued'),
        'variant' => (string)($payload['variant'] ?? ''),
        'format' => (string)($payload['format'] ?? ''),
        'fingerprint' => (string)($payload['fingerprint'] ?? ''),
        'filters_json' => (string)($payload['filters_json'] ?? '{}'),
        'issued_by_admin_id' => (int)($payload['issued_by_admin_id'] ?? 0),
        'issued_by_name' => (string)($payload['issued_by_name'] ?? ''),
        'request_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'created_at' => $createdAt,
      ]
    );
  } catch (\Throwable $e) {
    error_log('[collections_queue][export_audit] ' . $e->getMessage());
  }
}

$filterPayload = ['register' => $filters, 'queue' => $queueFilters];
$filterFingerprint = hash('sha256', json_encode($filterPayload, JSON_UNESCAPED_SLASHES));
$exportToken = bin2hex(random_bytes(16));
if (!isset($_SESSION['ar_export_locks']) || !is_array($_SESSION['ar_export_locks'])) {
    $_SESSION['ar_export_locks'] = [];
}
$_SESSION['ar_export_locks'][$exportToken] = [
    'fingerprint' => $filterFingerprint,
    'filters' => $filterPayload,
    'issued_at' => time(),
    'issued_by_admin' => (int)($_SESSION['admin'] ?? 0),
    'issued_by_name' => (string)($_SESSION['admin_name'] ?? ''),
    'source' => 'collections_queue',
];
if (count($_SESSION['ar_export_locks']) > 40) {
    $_SESSION['ar_export_locks'] = array_slice($_SESSION['ar_export_locks'], -40, null, true);
}

queue_write_export_audit($db, [
  'lock_token' => $exportToken,
  'source' => 'collections_queue',
  'event_type' => 'issued',
  'variant' => 'all',
  'format' => 'csv',
  'fingerprint' => $filterFingerprint,
  'filters_json' => json_encode($filterPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
  'issued_by_admin_id' => (int)($_SESSION['admin'] ?? 0),
  'issued_by_name' => (string)($_SESSION['admin_name'] ?? ''),
]);

$flashError = $flashError !== '' ? $flashError : trim((string)($_GET['error'] ?? ''));
$flashMessage = $flashMessage !== '' ? $flashMessage : trim((string)($_GET['message'] ?? ''));
$canEscalateCollections = admin_has_permission('order_reject') || admin_has_permission('can_approve_refund') || admin_is_super_admin() || in_array((string)($_SESSION['admin_role'] ?? ''), ['admin', 'ops_manager'], true);
$canMarkSettledCollections = admin_has_permission('order_credit') || admin_has_permission('order_edit') || admin_is_super_admin() || in_array((string)($_SESSION['admin_role'] ?? ''), ['admin', 'sales_manager', 'ops_manager'], true);
?>

<style>
  .cq-shell { display: grid; gap: 16px; }
  .cq-panel { background: #fff; border: 1px solid rgba(128,0,31,0.12); border-radius: 14px; box-shadow: 0 10px 26px rgba(68,16,34,0.08); }
  .cq-head { padding: 16px 18px; border-bottom: 1px solid rgba(128,0,31,0.1); background: linear-gradient(180deg, #fff7fa 0%, #fff 100%); }
  .cq-head h2 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
  .cq-head p { margin: 6px 0 0; color: #8f7681; font-size: 0.88rem; }
  .cq-body { padding: 16px 18px; }
  .cq-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
  .cq-card { border: 1px solid rgba(128,0,31,0.12); border-radius: 10px; padding: 10px 12px; }
  .cq-card strong { display: block; font-size: 0.72rem; text-transform: uppercase; color: #80001F; letter-spacing: 0.04em; }
  .cq-card span { display: block; margin-top: 6px; font-size: 1.18rem; color: #2d1f25; font-weight: 700; }
  .cq-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
  .cq-row input, .cq-row select { border: 1px solid rgba(128,0,31,0.18); border-radius: 8px; padding: 8px 10px; min-width: 142px; }
  .cq-btn { border: 0; border-radius: 8px; padding: 8px 12px; background: #80001F; color: #fff; text-decoration: none; font-weight: 700; font-size: 0.82rem; cursor: pointer; }
  .cq-btn.ghost { background: #fff; color: #80001F; border: 1px solid rgba(128,0,31,0.2); }
  .cq-btn.alt { background: #374151; }
  .cq-muted { color: #8f7681; font-size: 0.82rem; }
  .cq-table-wrap { overflow: auto; }
  .cq-table { width: 100%; border-collapse: collapse; min-width: 1640px; }
  .cq-table th, .cq-table td { border-bottom: 1px solid rgba(128,0,31,0.08); padding: 9px 8px; text-align: left; vertical-align: top; }
  .cq-table th { font-size: 0.72rem; text-transform: uppercase; color: #80001F; background: #fff4f7; letter-spacing: 0.04em; }
  .cq-link { color: #80001F; text-decoration: none; font-weight: 700; }
  .cq-link:hover { text-decoration: underline; }
  .cq-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 8px; font-size: 0.68rem; text-transform: uppercase; font-weight: 700; }
  .cq-badge.ok { background: #dcfce7; color: #166534; }
  .cq-badge.warn { background: #fef3c7; color: #92400e; }
  .cq-badge.bad { background: #fecdd3; color: #991b1b; }
  .cq-badge.info { background: #dbeafe; color: #1d4ed8; }
  .cq-timeline-badge {
    display: inline-flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 6px;
    border: 1px solid rgba(128,0,31,0.14);
    border-radius: 10px;
    padding: 6px 8px;
    background: #fff7fa;
    color: #5b1a30;
    font-size: 0.7rem;
    line-height: 1.2;
  }
  .cq-timeline-badge strong { font-size: 0.74rem; color: #80001F; }
  .cq-timeline-badge small { color: #7c5a68; }
  .cq-actions { display: grid; gap: 6px; min-width: 340px; }
  .cq-actions-row { display: flex; flex-wrap: wrap; gap: 6px; }
  .cq-mini { width: 100%; border: 1px solid rgba(128,0,31,0.2); border-radius: 8px; padding: 6px 8px; font-size: 0.78rem; }
  .cq-log { font-size: 0.74rem; color: #4b5563; margin-bottom: 3px; }
  .cq-flash { border-radius: 10px; padding: 10px 12px; font-size: 0.83rem; margin-bottom: 10px; }
  .cq-flash.error { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
  .cq-flash.ok { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
  .cq-modal-backdrop { position: fixed; inset: 0; background: rgba(17,24,39,0.42); z-index: 9998; display: none; }
  .cq-modal-backdrop.open { display: block; }
  .cq-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    width: min(920px, 96vw);
    max-height: 84vh;
    transform: translate(-50%, -50%);
    background: #fff;
    border: 1px solid rgba(128,0,31,0.16);
    border-radius: 12px;
    box-shadow: 0 24px 44px rgba(17,24,39,0.26);
    z-index: 9999;
    display: none;
    overflow: hidden;
  }
  .cq-modal.open { display: block; }
  .cq-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(128,0,31,0.12);
    background: #fff5f8;
  }
  .cq-modal-head h3 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
  .cq-modal-close {
    border: 1px solid rgba(128,0,31,0.22);
    border-radius: 8px;
    background: #fff;
    color: #80001F;
    font-weight: 700;
    padding: 6px 10px;
    cursor: pointer;
  }
  .cq-modal-body { padding: 12px 14px; overflow: auto; max-height: calc(84vh - 56px); }
  .cq-timeline { width: 100%; border-collapse: collapse; }
  .cq-timeline th, .cq-timeline td { border-bottom: 1px solid rgba(128,0,31,0.08); padding: 8px 6px; text-align: left; vertical-align: top; font-size: 0.78rem; }
  .cq-timeline th { color: #80001F; text-transform: uppercase; letter-spacing: 0.04em; font-size: 0.68rem; background: #fff8fa; }
  @media (max-width: 1200px) { .cq-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 700px) { .cq-grid { grid-template-columns: 1fr; } }
</style>

<div class="cq-shell">
  <section class="cq-panel">
    <div class="cq-head">
      <h2>Collections Queue</h2>
      <p>Actionable receivables queue with WhatsApp deep-link, email reminders, follow-up states, and auditable exports.</p>
    </div>
    <div class="cq-body">
      <?php if ($flashError !== ''): ?>
        <div class="cq-flash error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php elseif ($flashMessage !== ''): ?>
        <div class="cq-flash ok"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="cq-grid">
        <div class="cq-card"><strong>Queue Rows</strong><span><?= number_format($totalRows) ?></span></div>
        <div class="cq-card"><strong>Balance Due</strong><span>Rs <?= number_format((float)($totals['total_balance_due'] ?? 0), 2) ?></span></div>
        <div class="cq-card"><strong>Overdue Orders</strong><span><?= (int)($totals['overdue_orders'] ?? 0) ?></span></div>
        <div class="cq-card"><strong>Promised</strong><span><?= (int)($totals['promised_orders'] ?? 0) ?></span></div>
        <div class="cq-card"><strong>Escalated</strong><span><?= (int)($totals['escalated_orders'] ?? 0) ?></span></div>
      </div>
    </div>
  </section>

  <section class="cq-panel">
    <div class="cq-body">
      <form method="get">
        <div class="cq-row">
          <select name="date_preset">
            <?php foreach (['today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'custom' => 'Custom'] as $presetKey => $presetLabel): ?>
              <option value="<?= htmlspecialchars($presetKey, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['date_preset'] === $presetKey ? 'selected' : '' ?>><?= htmlspecialchars($presetLabel, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <select name="date_basis">
            <option value="payment" <?= $filters['date_basis'] === 'payment' ? 'selected' : '' ?>>Payment Date</option>
            <option value="booking" <?= $filters['date_basis'] === 'booking' ? 'selected' : '' ?>>Booking Date</option>
            <option value="fulfilment" <?= $filters['date_basis'] === 'fulfilment' ? 'selected' : '' ?>>Fulfilment Date</option>
          </select>
          <input type="date" name="from_date" value="<?= htmlspecialchars($filters['from_date'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="date" name="to_date" value="<?= htmlspecialchars($filters['to_date'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="text" name="order_no" placeholder="Order no" value="<?= htmlspecialchars($filters['order_no'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="tel" name="mobile" placeholder="Mobile" value="<?= htmlspecialchars($filters['mobile'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="cq-row">
          <select name="payment_status">
            <option value="pending_collection" <?= $filters['payment_status'] === 'pending_collection' ? 'selected' : '' ?>>Pending Collection</option>
            <option value="due_today" <?= $filters['payment_status'] === 'due_today' ? 'selected' : '' ?>>Due Today</option>
            <option value="due_tomorrow" <?= $filters['payment_status'] === 'due_tomorrow' ? 'selected' : '' ?>>Due Tomorrow</option>
            <option value="overdue" <?= $filters['payment_status'] === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            <option value="all" <?= $filters['payment_status'] === 'all' ? 'selected' : '' ?>>All</option>
          </select>
          <select name="followup_status">
            <?php foreach (['all' => 'Any Followup', 'no_reminder' => 'No Reminder', 'reminder_sent' => 'Reminder Sent', 'customer_responded' => 'Customer Responded', 'payment_promised' => 'Payment Promised', 'escalated' => 'Escalated', 'settled' => 'Settled'] as $statusKey => $statusLabel): ?>
              <option value="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>" <?= $queueFilters['followup_status'] === $statusKey ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <select name="collection_priority">
            <option value="all" <?= $queueFilters['collection_priority'] === 'all' ? 'selected' : '' ?>>Any Priority</option>
            <option value="normal" <?= $queueFilters['collection_priority'] === 'normal' ? 'selected' : '' ?>>Normal</option>
            <option value="high" <?= $queueFilters['collection_priority'] === 'high' ? 'selected' : '' ?>>High</option>
          </select>
          <select name="action_due">
            <option value="all" <?= $queueFilters['action_due'] === 'all' ? 'selected' : '' ?>>Any Action Window</option>
            <option value="today" <?= $queueFilters['action_due'] === 'today' ? 'selected' : '' ?>>Action Due Today</option>
            <option value="next_24h" <?= $queueFilters['action_due'] === 'next_24h' ? 'selected' : '' ?>>Next 24h</option>
            <option value="overdue" <?= $queueFilters['action_due'] === 'overdue' ? 'selected' : '' ?>>Action Overdue</option>
          </select>
          <select name="per_page">
            <?php foreach ($perPageOptions as $size): ?>
              <option value="<?= (int)$size ?>" <?= $perPage === (int)$size ? 'selected' : '' ?>><?= (int)$size ?> / page</option>
            <?php endforeach; ?>
          </select>
          <button class="cq-btn" type="submit">Apply</button>
          <a class="cq-btn ghost" href="<?= htmlspecialchars(collection_queue_url(['page' => 1]), ENT_QUOTES, 'UTF-8') ?>">Reset</a>
        </div>
      </form>

      <div class="cq-row">
        <a class="cq-btn ghost" href="collections_export.php?variant=aging&format=csv&lock=<?= urlencode($exportToken) ?>">Aging Buckets CSV</a>
        <a class="cq-btn ghost" href="collections_export.php?variant=overdue_followup&format=csv&lock=<?= urlencode($exportToken) ?>">Overdue Follow-up CSV</a>
      </div>
      <p class="cq-muted">Audit lock token: <?= htmlspecialchars(substr($exportToken, 0, 12), ENT_QUOTES, 'UTF-8') ?>... linked to current filter state.</p>

      <div class="cq-table-wrap">
        <table class="cq-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Due Date</th>
              <th>Balance Due</th>
              <th>Followup State</th>
              <th>Priority</th>
              <th>Next Followup</th>
              <th>Recent Logs</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9" class="cq-muted">No receivables found for selected filters.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                $oid = (int)$row['id'];
                $latestLogs = $logsByOrder[$oid] ?? [];
                $timelineSummary = $timelineSummaryByOrder[$oid] ?? ['total_events' => 0, 'last_actor_name' => '', 'last_created_at' => ''];
                $returnTo = urlencode(collection_queue_url(['page' => $page]));
                ?>
                <tr id="queue-row-<?= $oid ?>">
                  <td>
                    <a class="cq-link" href="order_details.php?id=<?= $oid ?>&return_to=<?= $returnTo ?>"><?= htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8') ?></a>
                    <div class="cq-muted">Status: <?= htmlspecialchars((string)$row['collection_status_label'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="cq-timeline-badge" id="timeline-badge-<?= $oid ?>">
                      <strong><?= (int)$timelineSummary['total_events'] ?> events</strong>
                      <small>
                        <?php if ((string)$timelineSummary['last_created_at'] !== ''): ?>
                          Last: <?= htmlspecialchars((string)$timelineSummary['last_actor_name'] !== '' ? (string)$timelineSummary['last_actor_name'] : 'System', ENT_QUOTES, 'UTF-8') ?> @ <?= htmlspecialchars((string)$timelineSummary['last_created_at'], ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                          No activity yet
                        <?php endif; ?>
                      </small>
                    </div>
                  </td>
                  <td>
                    <?= htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8') ?><br>
                    <span class="cq-muted"><?= htmlspecialchars((string)($row['customer_phone_e164'] ?: $row['customer_phone']), ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td><?= htmlspecialchars((string)$row['collection_due_date'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td>Rs <?= number_format((float)($row['balance_due_amount'] ?? 0), 2) ?></td>
                  <td><span class="<?= htmlspecialchars(queue_status_badge((string)$row['followup_status']), ENT_QUOTES, 'UTF-8') ?>" id="followup-status-pill-<?= $oid ?>"><?= htmlspecialchars((string)$row['followup_status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td id="priority-text-<?= $oid ?>"><?= htmlspecialchars((string)$row['collection_priority'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td id="next-followup-text-<?= $oid ?>"><?= htmlspecialchars((string)($row['next_followup_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <?php if (empty($latestLogs)): ?>
                      <span class="cq-muted">No logs</span>
                    <?php else: ?>
                      <?php foreach ($latestLogs as $log): ?>
                        <div class="cq-log">
                          <strong><?= htmlspecialchars((string)$log['action_type'], ENT_QUOTES, 'UTF-8') ?></strong>
                          (<?= htmlspecialchars((string)$log['created_at'], ENT_QUOTES, 'UTF-8') ?>)
                          <?php if (!empty($log['actor_name'])): ?> by <?= htmlspecialchars((string)$log['actor_name'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="cq-actions">
                      <div class="cq-actions-row">
                        <button type="button" class="cq-btn ghost" onclick='openTimelineModal(<?= $oid ?>, <?= json_encode((string)$row['order_number'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>)'>Timeline</button>
                        <button type="button" class="cq-btn" onclick="runCollectionAction(<?= $oid ?>, 'reminder_whatsapp')">WhatsApp Link</button>
                        <button type="button" class="cq-btn alt" onclick="runCollectionAction(<?= $oid ?>, 'reminder_email')">Send Email</button>
                      </div>
                      <div class="cq-actions-row">
                        <button type="button" class="cq-btn ghost" onclick="runCollectionAction(<?= $oid ?>, 'customer_responded')">Responded</button>
                        <button type="button" class="cq-btn ghost" onclick="runCollectionAction(<?= $oid ?>, 'payment_promised')">Promised</button>
                        <?php if ($canEscalateCollections): ?>
                          <button type="button" class="cq-btn ghost" onclick="runCollectionAction(<?= $oid ?>, 'escalated')">Escalate</button>
                        <?php endif; ?>
                        <?php if ($canMarkSettledCollections): ?>
                          <button type="button" class="cq-btn ghost" onclick="runCollectionAction(<?= $oid ?>, 'payment_collected')">Mark Settled</button>
                        <?php endif; ?>
                      </div>
                      <select id="priority-<?= $oid ?>" class="cq-mini">
                        <option value="normal" <?= (string)$row['collection_priority'] === 'normal' ? 'selected' : '' ?>>Priority: Normal</option>
                        <option value="high" <?= (string)$row['collection_priority'] === 'high' ? 'selected' : '' ?>>Priority: High</option>
                      </select>
                      <input id="next-followup-<?= $oid ?>" class="cq-mini" type="datetime-local" value="<?= htmlspecialchars(!empty($row['next_followup_at']) ? str_replace(' ', 'T', substr((string)$row['next_followup_at'], 0, 16)) : '', ENT_QUOTES, 'UTF-8') ?>">
                      <textarea id="note-<?= $oid ?>" class="cq-mini" rows="2" placeholder="Add follow-up note"></textarea>
                      <button type="button" class="cq-btn ghost" onclick="runCollectionAction(<?= $oid ?>, 'internal_note')">Save Note</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="cq-row" style="margin-top:10px;">
        <span class="cq-muted">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
        <?php if ($page > 1): ?>
          <a class="cq-btn ghost" href="<?= htmlspecialchars(collection_queue_url(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="cq-btn ghost" href="<?= htmlspecialchars(collection_queue_url(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<div id="cqTimelineBackdrop" class="cq-modal-backdrop" onclick="closeTimelineModal()"></div>
<div id="cqTimelineModal" class="cq-modal" role="dialog" aria-modal="true" aria-labelledby="cqTimelineTitle">
  <div class="cq-modal-head">
    <h3 id="cqTimelineTitle">Activity Timeline</h3>
    <button type="button" class="cq-modal-close" onclick="closeTimelineModal()">Close</button>
  </div>
  <div class="cq-modal-body" id="cqTimelineBody">
    <p class="cq-muted">Loading timeline...</p>
  </div>
</div>

<script>
  const collectionCsrf = <?= json_encode(Csrf::token(), JSON_UNESCAPED_SLASHES) ?>;

  function badgeClass(status) {
    if (status === 'escalated') {
      return 'cq-badge bad';
    }
    if (status === 'payment_promised' || status === 'reminder_sent') {
      return 'cq-badge warn';
    }
    if (status === 'settled' || status === 'customer_responded') {
      return 'cq-badge ok';
    }
    return 'cq-badge info';
  }

  function runCollectionAction(orderId, actionType) {
    const note = document.getElementById('note-' + orderId)?.value || '';
    const nextFollowup = document.getElementById('next-followup-' + orderId)?.value || '';
    const priority = document.getElementById('priority-' + orderId)?.value || 'normal';

    const formData = new FormData();
    formData.append('_csrf', collectionCsrf);
    formData.append('order_id', String(orderId));
    formData.append('action_type', actionType);
    formData.append('note', note);
    formData.append('next_followup_at', nextFollowup);
    formData.append('collection_priority', priority);

    if (actionType === 'reminder_email') {
      const customMessage = prompt('Optional: override email reminder text', '');
      if (customMessage !== null && customMessage.trim() !== '') {
        formData.append('email_message', customMessage.trim());
      }
    }

    if (actionType === 'payment_promised') {
      const promiseDate = prompt('Promise date (YYYY-MM-DD)', '');
      if (promiseDate !== null && promiseDate.trim() !== '') {
        formData.append('promise_date', promiseDate.trim());
      }
    }

    if (actionType === 'payment_collected') {
      const settlementRef = prompt('Settlement reference (UTR/receipt ID)', '');
      if (settlementRef === null || settlementRef.trim() === '') {
        alert('Settlement reference is required to mark payment as collected.');
        return;
      }
      formData.append('settlement_reference', settlementRef.trim());

      const paymentModeRaw = prompt('Settlement mode (cod / upi_manual / gateway)', 'upi_manual');
      const paymentMode = paymentModeRaw && paymentModeRaw.trim() !== '' ? paymentModeRaw.trim().toLowerCase() : 'upi_manual';
      formData.append('settlement_payment_method', paymentMode);

      const settledAmountRaw = prompt('Settled amount (leave blank for full balance)', '');
      if (settledAmountRaw !== null && settledAmountRaw.trim() !== '') {
        formData.append('settled_amount', settledAmountRaw.trim());
      }
    }

    fetch('api/collection-followup-action.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          alert(data.error || 'Collection action failed');
          return;
        }

        const statusPill = document.getElementById('followup-status-pill-' + orderId);
        if (statusPill) {
          statusPill.textContent = data.followup_status || 'no_reminder';
          statusPill.className = badgeClass(data.followup_status || 'no_reminder');
        }

        const nextText = document.getElementById('next-followup-text-' + orderId);
        if (nextText) {
          nextText.textContent = data.next_followup_at || '—';
        }

        const priorityText = document.getElementById('priority-text-' + orderId);
        if (priorityText) {
          priorityText.textContent = data.collection_priority || priority;
        }

        const timelineBadge = document.getElementById('timeline-badge-' + orderId);
        if (timelineBadge) {
          const safeActor = data.actor_name ? String(data.actor_name) : 'You';
          const safeTime = data.logged_at ? String(data.logged_at) : 'now';
          const totalEvents = Number(data.timeline_total_events || 0);
          timelineBadge.innerHTML =
            '<strong>' + escapeHtml(String(totalEvents)) + ' events</strong>' +
            '<small>Last: ' + escapeHtml(safeActor) + ' @ ' + escapeHtml(safeTime) + '</small>';
        }

        if (data.whatsapp_link) {
          window.open(data.whatsapp_link, '_blank', 'noopener');
        }

        alert(data.message || 'Collection action saved');
      })
      .catch(() => {
        alert('Network error while processing collection action');
      });
  }

  function closeTimelineModal() {
    document.getElementById('cqTimelineBackdrop')?.classList.remove('open');
    document.getElementById('cqTimelineModal')?.classList.remove('open');
  }

  function openTimelineModal(orderId, orderNumber) {
    const backdrop = document.getElementById('cqTimelineBackdrop');
    const modal = document.getElementById('cqTimelineModal');
    const title = document.getElementById('cqTimelineTitle');
    const body = document.getElementById('cqTimelineBody');
    if (!backdrop || !modal || !title || !body) {
      return;
    }

    title.textContent = 'Activity Timeline: ' + orderNumber;
    body.innerHTML = '<p class="cq-muted">Loading timeline...</p>';
    backdrop.classList.add('open');
    modal.classList.add('open');

    const endpoint = 'api/collection-followup-timeline.php?order_id=' + encodeURIComponent(String(orderId));
    fetch(endpoint, {
      method: 'GET',
      credentials: 'same-origin'
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          body.innerHTML = '<p class="cq-muted">Unable to load timeline.</p>';
          return;
        }

        const rows = Array.isArray(data.rows) ? data.rows : [];
        if (rows.length === 0) {
          body.innerHTML = '<p class="cq-muted">No follow-up activity recorded for this order.</p>';
          return;
        }

        let html = '<table class="cq-timeline"><thead><tr><th>When</th><th>Action</th><th>Status</th><th>Actor</th><th>Message</th></tr></thead><tbody>';
        rows.forEach((row) => {
          const when = row.created_at || '';
          const action = row.action_type || '';
          const status = row.followup_status || '';
          const actor = row.actor_name || '';
          const message = row.message_text || '';
          html += '<tr>' +
            '<td>' + escapeHtml(String(when)) + '</td>' +
            '<td>' + escapeHtml(String(action)) + '</td>' +
            '<td>' + escapeHtml(String(status)) + '</td>' +
            '<td>' + escapeHtml(String(actor)) + '</td>' +
            '<td>' + escapeHtml(String(message)) + '</td>' +
            '</tr>';
        });
        html += '</tbody></table>';
        body.innerHTML = html;
      })
      .catch(() => {
        body.innerHTML = '<p class="cq-muted">Network error while loading timeline.</p>';
      });
  }

  function escapeHtml(value) {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
</script>
