<?php
$pageTitle = 'CRM Push Logs';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/crm_settings_helpers.php';

$logs = fetch_crm_push_logs($conn, 100);
include __DIR__ . '/layout.php';
?>
<style>
  .crm-log-panel {
    background: var(--admin-surface, #fffdfd);
    border-radius: 18px;
    border: 1px solid rgba(128, 0, 31, 0.1);
    box-shadow: 0 14px 30px rgba(96, 18, 45, 0.08);
    overflow: hidden;
  }

  .crm-log-panel__head {
    padding: 18px 20px 12px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
  }

  .crm-log-panel__head h2 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
  }

  .crm-log-panel__body {
    padding: 20px;
  }

  .crm-log-table-wrap {
    overflow: auto;
  }

  .crm-log-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
  }

  .crm-log-table th,
  .crm-log-table td {
    padding: 12px 10px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    text-align: left;
    vertical-align: top;
  }

  .crm-log-table th {
    background: #fff6f8;
    color: #80001F;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.76rem;
  }
</style>

<div class="crm-log-panel">
  <div class="crm-log-panel__head">
    <h2>CRM Push Logs</h2>
  </div>
  <div class="crm-log-panel__body">
    <div class="crm-log-table-wrap">
      <table class="crm-log-table">
        <tr>
          <th>Name</th><th>Mobile</th><th>Status</th><th>Response</th><th>Date</th>
        </tr>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= htmlspecialchars($log['name']) ?></td>
            <td><?= htmlspecialchars($log['mobile']) ?></td>
            <td><?= htmlspecialchars($log['status']) ?></td>
            <td><?= htmlspecialchars($log['response']) ?></td>
            <td><?= htmlspecialchars($log['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
