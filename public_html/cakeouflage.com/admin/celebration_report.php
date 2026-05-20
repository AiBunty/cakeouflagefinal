<?php
$pageTitle = 'Celebration Report';
require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');
require_once __DIR__ . '/includes/db.php';

$selectedDate = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$eventFilter = trim((string)($_GET['event'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$allowedEvent = ['birthday_greeting', 'birthday_preorder', 'anniversary_greeting', 'anniversary_preorder', 'combined_greeting', 'combined_preorder'];
$allowedStatus = ['pending', 'done', 'cancelled', 'queued', 'sent', 'failed'];
if (!in_array($eventFilter, $allowedEvent, true)) {
    $eventFilter = '';
}
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}

$reminderWhere = ['r.reminder_type = "birthday"', 'DATE(r.created_at) = ?'];
$reminderTypes = 's';
$reminderParams = [$selectedDate];
if ($eventFilter !== '') {
    $reminderWhere[] = 'r.notes LIKE ?';
    $reminderTypes .= 's';
    $reminderParams[] = '%"celebration_purpose":"' . $eventFilter . '"%';
}
if (in_array($statusFilter, ['pending', 'done', 'cancelled'], true)) {
    $reminderWhere[] = 'r.status = ?';
    $reminderTypes .= 's';
    $reminderParams[] = $statusFilter;
}

$reminderSql = 'SELECT
    r.id,
    r.user_id,
    r.title,
    r.reminder_on,
    r.status,
    r.notes,
    r.created_at,
    u.full_name,
    u.email,
    u.phone
  FROM reminders r
  LEFT JOIN users u ON u.id = r.user_id
  WHERE ' . implode(' AND ', $reminderWhere) . '
  ORDER BY r.created_at DESC, r.id DESC
  LIMIT 300';

$reminderStmt = $conn->prepare($reminderSql);
$reminderRows = [];
if ($reminderStmt) {
    if ($reminderTypes !== '') {
        $bindValues = $reminderParams;
        $refs = [];
        foreach ($bindValues as $k => $v) {
            $refs[$k] = &$bindValues[$k];
        }
        $reminderStmt->bind_param($reminderTypes, ...$refs);
    }
    $reminderStmt->execute();
    $reminderResult = $reminderStmt->get_result();
    while ($reminderResult && ($row = $reminderResult->fetch_assoc())) {
        $notes = json_decode((string)($row['notes'] ?? ''), true);
        if (!is_array($notes)) {
            $notes = [];
        }
        $row['celebration_purpose'] = (string)($notes['celebration_purpose'] ?? '');
        $row['event_date'] = (string)($notes['event_date'] ?? '');
        $reminderRows[] = $row;
    }
    $reminderStmt->close();
}

$queueWhere = ['channel = "email"', 'DATE(created_at) = ?', 'event_key IN ("birthday_greeting_email", "birthday_preorder_email", "anniversary_greeting_email", "anniversary_preorder_email", "celebration_combined_email")'];
$queueTypes = 's';
$queueParams = [$selectedDate];
if (in_array($statusFilter, ['queued', 'sent', 'failed'], true)) {
    $queueWhere[] = 'status = ?';
    $queueTypes .= 's';
    $queueParams[] = $statusFilter;
}

$queueSql = 'SELECT id, event_key, recipient, status, created_at
  FROM communication_logs
  WHERE ' . implode(' AND ', $queueWhere) . '
  ORDER BY id DESC
  LIMIT 300';

$queueStmt = $conn->prepare($queueSql);
$queueRows = [];
if ($queueStmt) {
    $bindValues = $queueParams;
    $refs = [];
    foreach ($bindValues as $k => $v) {
        $refs[$k] = &$bindValues[$k];
    }
    $queueStmt->bind_param($queueTypes, ...$refs);
    $queueStmt->execute();
    $queueResult = $queueStmt->get_result();
    while ($queueResult && ($row = $queueResult->fetch_assoc())) {
        $queueRows[] = $row;
    }
    $queueStmt->close();
}

$summary = [
    'generated' => count($reminderRows),
    'pending' => 0,
    'done' => 0,
    'cancelled' => 0,
    'queued' => 0,
    'sent' => 0,
    'failed' => 0,
];
foreach ($reminderRows as $row) {
    $st = (string)($row['status'] ?? 'pending');
    if (isset($summary[$st])) {
        $summary[$st]++;
    }
}
foreach ($queueRows as $row) {
    $st = (string)($row['status'] ?? 'queued');
    if (isset($summary[$st])) {
        $summary[$st]++;
    }
}

$eventLabels = [
    'birthday_greeting' => 'Birthday Greeting',
    'birthday_preorder' => 'Birthday Preorder',
    'anniversary_greeting' => 'Anniversary Greeting',
    'anniversary_preorder' => 'Anniversary Preorder',
    'combined_greeting' => 'Combined Greeting',
    'combined_preorder' => 'Combined Preorder',
];

  $today = date('Y-m-d');
  $yesterday = (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');
  $last7 = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');
?>
<style>
.cel-shell { display:grid; gap:16px; }
.cel-card { background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:16px; box-shadow:0 12px 26px rgba(68,16,34,.08); overflow:hidden; }
.cel-card__head { padding:16px 18px; border-bottom:1px solid rgba(128,0,31,.08); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); }
.cel-card__head h3 { margin:0; color:#80001F; font-family:'DM Serif Display',Georgia,serif; font-weight:400; }
.cel-card__body { padding:18px; }
.cel-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
.cel-field { display:grid; gap:6px; }
.cel-field label { font-size:.76rem; text-transform:uppercase; letter-spacing:.08em; color:#80001F; font-weight:700; }
.cel-field input, .cel-field select { border:1px solid rgba(128,0,31,.2); border-radius:10px; padding:9px 11px; font:inherit; }
.cel-actions { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
.cel-btn { border:0; border-radius:10px; padding:9px 14px; background:#80001F; color:#fff; font-weight:700; cursor:pointer; text-decoration:none; }
.cel-btn.ghost { background:#fff; border:1px solid rgba(128,0,31,.2); color:#80001F; }
.cel-quick { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
.cel-quick a { text-decoration:none; font-size:.74rem; padding:4px 8px; border-radius:8px; border:1px solid rgba(128,0,31,.18); color:#80001F; background:#fff; }
.cel-quick a:hover { background:#fff4f8; }
.cel-stats { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:10px; margin-top:12px; }
.cel-stat { border:1px solid rgba(128,0,31,.12); border-radius:12px; background:#fff8fa; padding:10px; }
.cel-stat .label { color:#8f7681; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; }
.cel-stat .value { color:#80001F; font-family:'DM Serif Display',Georgia,serif; font-size:1.2rem; }
.cel-table-wrap { overflow:auto; margin-top:12px; }
.cel-table { width:100%; border-collapse:collapse; min-width:980px; }
.cel-table th, .cel-table td { padding:10px; border-bottom:1px solid rgba(128,0,31,.08); text-align:left; font-size:.84rem; }
.cel-table th { color:#80001F; font-weight:700; background:#fff8fa; position:sticky; top:0; z-index:1; }
.cel-table tbody tr:nth-child(even) { background:#fffafb; }
.cel-table tbody tr:hover { background:#fff3f7; }
.cel-badge { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; font-size:.72rem; font-weight:700; }
.cel-badge--pending { background:#fff2cf; color:#9a5b00; }
.cel-badge--done, .cel-badge--sent { background:#dcfce7; color:#166534; }
.cel-badge--cancelled, .cel-badge--failed { background:#fee2e2; color:#991b1b; }
.cel-badge--queued { background:#e0e7ff; color:#3730a3; }
@media (max-width: 1200px) {
  .cel-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .cel-stats { grid-template-columns:repeat(3,minmax(0,1fr)); }
}
@media (max-width: 700px) {
  .cel-grid, .cel-stats { grid-template-columns:1fr; }
}
</style>

<div class="cel-shell">
  <section class="cel-card">
    <div class="cel-card__head"><h3>Today Celebration Reminders</h3></div>
    <div class="cel-card__body">
      <form method="get">
        <div class="cel-grid">
          <div class="cel-field">
            <label for="date">Date</label>
            <input id="date" type="date" name="date" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="cel-field">
            <label for="event">Event Type</label>
            <select id="event" name="event">
              <option value="">All</option>
              <?php foreach ($eventLabels as $k => $label): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $eventFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="cel-field">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="">All</option>
              <?php foreach ($allowedStatus as $st): ?>
                <option value="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="cel-actions">
          <button type="submit" class="cel-btn">Apply Filters</button>
          <a class="cel-btn ghost" href="celebration_report.php">Reset</a>
        </div>
        <div class="cel-quick">
          <a href="celebration_report.php?date=<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">Today</a>
          <a href="celebration_report.php?date=<?= htmlspecialchars($yesterday, ENT_QUOTES, 'UTF-8') ?>">Yesterday</a>
          <a href="celebration_report.php?date=<?= htmlspecialchars($last7, ENT_QUOTES, 'UTF-8') ?>">Start of Last 7 Days</a>
        </div>
      </form>

      <div class="cel-stats">
        <div class="cel-stat"><div class="label">Generated</div><div class="value"><?= (int)$summary['generated'] ?></div></div>
        <div class="cel-stat"><div class="label">Pending</div><div class="value"><?= (int)$summary['pending'] ?></div></div>
        <div class="cel-stat"><div class="label">Done</div><div class="value"><?= (int)$summary['done'] ?></div></div>
        <div class="cel-stat"><div class="label">Cancelled</div><div class="value"><?= (int)$summary['cancelled'] ?></div></div>
        <div class="cel-stat"><div class="label">Queued</div><div class="value"><?= (int)$summary['queued'] ?></div></div>
        <div class="cel-stat"><div class="label">Sent</div><div class="value"><?= (int)$summary['sent'] ?></div></div>
        <div class="cel-stat"><div class="label">Failed</div><div class="value"><?= (int)$summary['failed'] ?></div></div>
      </div>

      <div class="cel-table-wrap">
        <table class="cel-table">
          <thead>
            <tr>
              <th>Reminder ID</th>
              <th>Created At</th>
              <th>Customer</th>
              <th>Email</th>
              <th>Event Purpose</th>
              <th>Event Date</th>
              <th>Reminder Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$reminderRows): ?>
            <tr><td colspan="7">No celebration reminders generated for selected filters.</td></tr>
          <?php endif; ?>
          <?php foreach ($reminderRows as $row): ?>
            <?php $purpose = (string)($row['celebration_purpose'] ?? ''); ?>
            <tr>
              <td>#<?= (int)($row['id'] ?? 0) ?></td>
              <td><?= htmlspecialchars((string)($row['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($row['full_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($row['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($eventLabels[$purpose] ?? $purpose, ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($row['event_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="cel-badge cel-badge--<?= htmlspecialchars((string)($row['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($row['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="cel-table-wrap">
        <table class="cel-table">
          <thead>
            <tr>
              <th>Log ID</th>
              <th>Created At</th>
              <th>Event Key</th>
              <th>Recipient</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$queueRows): ?>
            <tr><td colspan="5">No communication logs found for celebration email keys on selected date.</td></tr>
          <?php endif; ?>
          <?php foreach ($queueRows as $row): ?>
            <tr>
              <td>#<?= (int)($row['id'] ?? 0) ?></td>
              <td><?= htmlspecialchars((string)($row['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($row['event_key'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($row['recipient'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="cel-badge cel-badge--<?= htmlspecialchars((string)($row['status'] ?? 'queued'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($row['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
