<?php
$pageTitle = 'CRM Push Logs';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/crm_settings_helpers.php';

$logs = fetch_crm_push_logs($conn, 200);
require_once __DIR__ . '/layout.php';
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
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .crm-log-panel__head h2 {
    margin: 0;
    flex: 1;
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
    min-width: 1000px;
  }

  .crm-log-table th,
  .crm-log-table td {
    padding: 10px 8px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    text-align: left;
    vertical-align: top;
  }

  .crm-log-table th {
    background: #fff6f8;
    color: #80001F;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.74rem;
    white-space: nowrap;
  }

  .badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.78em;
    font-weight: 600;
    letter-spacing: 0.04em;
  }
  .badge--success { background:#e6f7ee; color:#1a7a44; }
  .badge--fail    { background:#fde8e8; color:#c0392b; }
  .badge--warn    { background:#fff4e0; color:#a05a00; }
  .badge--grey    { background:#efefef; color:#555; }
  .http-ok   { color: #1a7a44; font-weight:600; }
  .http-fail { color: #c0392b; font-weight:600; }
</style>

<div class="crm-log-panel">
  <div class="crm-log-panel__head">
    <h2>CRM Push Logs</h2>
    <a href="crm_diagnostics.php" style="font-size:0.88rem;color:#80001F;text-decoration:none;border:1px solid rgba(128,0,31,0.25);padding:4px 12px;border-radius:6px;">&#9741; Diagnostics</a>
    <a href="crm_settings.php" style="font-size:0.88rem;color:#80001F;text-decoration:none;border:1px solid rgba(128,0,31,0.25);padding:4px 12px;border-radius:6px;">&#8592; Settings</a>
  </div>
  <div class="crm-log-panel__body">
    <div class="crm-log-table-wrap">
      <table class="crm-log-table">
        <tr>
          <th>Trigger</th>
          <th>Auto ID</th>
          <th>Name</th>
          <th>Mobile</th>
          <th>Exec Status</th>
          <th>HTTP</th>
          <th>ms</th>
          <th>Error</th>
          <th>Response</th>
          <th>Date</th>
        </tr>
        <?php foreach ($logs as $log):
          $execStatus = $log['execution_status'] ?? $log['status'] ?? '';
          $isSuccess  = $execStatus === 'success';
          $isFail     = in_array($execStatus, ['failed', 'fail', 'validation_failed', 'not_configured'], true);
          $badgeClass = $isSuccess ? 'badge--success' : ($isFail ? 'badge--fail' : 'badge--grey');
          $httpCode   = isset($log['http_status']) && (int)$log['http_status'] > 0 ? (int)$log['http_status'] : null;
          $httpClass  = $httpCode === 200 ? 'http-ok' : 'http-fail';
          $endpoint   = (string)($log['endpoint'] ?? '');
          $autoId     = (string)($log['automation_id'] ?? '');
          if ($autoId === '' && $endpoint !== '') {
              if (preg_match('#automations/([^/]+)/execute#', $endpoint, $m)) { $autoId = $m[1]; }
          }
        ?>
          <tr>
            <td style="white-space:nowrap;"><?= htmlspecialchars((string)($log['trigger_key'] ?? '—')) ?></td>
            <td style="font-size:0.80rem;font-family:monospace;"><?= htmlspecialchars($autoId ?: '—') ?></td>
            <td><?= htmlspecialchars($log['name']) ?></td>
            <td style="white-space:nowrap;"><?= htmlspecialchars($log['mobile']) ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($execStatus ?: '—') ?></span></td>
            <td class="<?= $httpCode ? $httpClass : '' ?>"><?= $httpCode ? $httpCode : '—' ?></td>
            <td style="white-space:nowrap;"><?= isset($log['response_time_ms']) && (int)$log['response_time_ms'] > 0 ? (int)$log['response_time_ms'] : '—' ?></td>
            <td style="font-size:0.80rem;color:#c0392b;"><?= htmlspecialchars(mb_substr((string)($log['error_message'] ?? ''), 0, 120)) ?></td>
            <td style="font-size:0.78rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= htmlspecialchars((string)($log['response'] ?? '')) ?>">
                <?= htmlspecialchars(mb_substr((string)($log['response'] ?? ''), 0, 100)) ?>
            </td>
            <td style="white-space:nowrap;"><?= htmlspecialchars($log['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
