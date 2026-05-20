<?php
$pageTitle = 'Queue Diagnostics (Temporary)';
include 'layout.php';

require __DIR__ . '/includes/db.php';

$manualOrders = [];
$orderResult = $conn->query(
    'SELECT id, order_number, customer_name, customer_email, created_at
     FROM orders
     WHERE order_number LIKE "MAN-%"
     ORDER BY id DESC
     LIMIT 12'
);

while ($orderResult && ($row = $orderResult->fetch_assoc())) {
    $orderId = (int)$row['id'];
    $orderNumber = (string)$row['order_number'];

    $commStmt = $conn->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = "queued" THEN 1 ELSE 0 END) AS queued_count,
            SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent_count,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_count
         FROM communication_logs
         WHERE order_id = ?'
    );
    $commStmt->bind_param('i', $orderId);
    $commStmt->execute();
    $comm = $commStmt->get_result();
    $commRow = $comm ? ($comm->fetch_assoc() ?: []) : [];

    $crmStmt = $conn->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = "queued" THEN 1 ELSE 0 END) AS queued_count,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_count
         FROM queue_jobs
         WHERE job_type = "crm_trigger_push"
           AND payload_json LIKE CONCAT("%\"order_number\":\"", ?, "\"%")'
    );
    $crmStmt->bind_param('s', $orderNumber);
    $crmStmt->execute();
    $crm = $crmStmt->get_result();
    $crmRow = $crm ? ($crm->fetch_assoc() ?: []) : [];

    $manualOrders[] = [
        'order' => $row,
        'communication' => $commRow,
        'crm' => $crmRow,
    ];
}

$latestJobs = [];
$jobResult = $conn->query(
    'SELECT id, job_type, status, attempts, last_error, created_at, updated_at
     FROM queue_jobs
     WHERE job_type IN ("send_communication", "crm_trigger_push")
     ORDER BY id DESC
     LIMIT 20'
);
while ($jobResult && ($row = $jobResult->fetch_assoc())) {
    $latestJobs[] = $row;
}
?>

<style>
  .diag-wrap { display: grid; gap: 16px; }
  .diag-card {
    background: #fff;
    border: 1px solid rgba(128,0,31,0.12);
    border-radius: 14px;
    box-shadow: 0 10px 22px rgba(68,16,34,0.06);
    overflow: hidden;
  }
  .diag-head {
    padding: 12px 14px;
    border-bottom: 1px solid rgba(128,0,31,0.09);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
  }
  .diag-head h3 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
  .diag-body { padding: 14px; overflow: auto; }
  .diag-note { color: #7f6973; font-size: 0.86rem; margin-top: 6px; }
  .diag-table { width: 100%; border-collapse: collapse; min-width: 900px; }
  .diag-table th, .diag-table td { padding: 10px; border-bottom: 1px solid rgba(128,0,31,0.08); text-align: left; }
  .diag-table th { font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: #80001F; background: #fff6f8; }
  .pill { display: inline-block; border-radius: 999px; padding: 3px 8px; font-size: 0.73rem; font-weight: 700; }
  .pill.queued { background: #fff2cf; color: #9a5b00; }
  .pill.completed, .pill.sent { background: #dcfce7; color: #166534; }
  .pill.failed { background: #fee2e2; color: #991b1b; }
</style>

<div class="diag-wrap">
  <div class="diag-card">
    <div class="diag-head">
      <h3>Manual Order Queue State</h3>
      <p class="diag-note">Temporary diagnostics for validating queued to sent/completed transitions.</p>
    </div>
    <div class="diag-body">
      <table class="diag-table">
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Created</th>
          <th>Comm Total</th>
          <th>Comm Queued</th>
          <th>Comm Sent</th>
          <th>Comm Failed</th>
          <th>CRM Jobs</th>
          <th>CRM Queued</th>
          <th>CRM Completed</th>
          <th>CRM Failed</th>
        </tr>
        <?php if (!$manualOrders): ?>
          <tr><td colspan="11">No manual orders found.</td></tr>
        <?php endif; ?>
        <?php foreach ($manualOrders as $entry): ?>
          <?php $o = $entry['order']; $c = $entry['communication']; $r = $entry['crm']; ?>
          <tr>
            <td><?= htmlspecialchars((string)$o['order_number']) ?></td>
            <td><?= htmlspecialchars((string)$o['customer_name']) ?><br><small><?= htmlspecialchars((string)$o['customer_email']) ?></small></td>
            <td><?= htmlspecialchars((string)$o['created_at']) ?></td>
            <td><?= (int)($c['total'] ?? 0) ?></td>
            <td><span class="pill queued"><?= (int)($c['queued_count'] ?? 0) ?></span></td>
            <td><span class="pill sent"><?= (int)($c['sent_count'] ?? 0) ?></span></td>
            <td><span class="pill failed"><?= (int)($c['failed_count'] ?? 0) ?></span></td>
            <td><?= (int)($r['total'] ?? 0) ?></td>
            <td><span class="pill queued"><?= (int)($r['queued_count'] ?? 0) ?></span></td>
            <td><span class="pill completed"><?= (int)($r['completed_count'] ?? 0) ?></span></td>
            <td><span class="pill failed"><?= (int)($r['failed_count'] ?? 0) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="diag-card">
    <div class="diag-head">
      <h3>Latest Queue Jobs</h3>
    </div>
    <div class="diag-body">
      <table class="diag-table">
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Status</th>
          <th>Attempts</th>
          <th>Created</th>
          <th>Updated</th>
          <th>Last Error</th>
        </tr>
        <?php if (!$latestJobs): ?>
          <tr><td colspan="7">No queue jobs available.</td></tr>
        <?php endif; ?>
        <?php foreach ($latestJobs as $job): ?>
          <?php $status = (string)($job['status'] ?? 'queued'); ?>
          <tr>
            <td><?= (int)$job['id'] ?></td>
            <td><?= htmlspecialchars((string)$job['job_type']) ?></td>
            <td><span class="pill <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span></td>
            <td><?= (int)$job['attempts'] ?></td>
            <td><?= htmlspecialchars((string)$job['created_at']) ?></td>
            <td><?= htmlspecialchars((string)$job['updated_at']) ?></td>
            <td><?= htmlspecialchars((string)($job['last_error'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
