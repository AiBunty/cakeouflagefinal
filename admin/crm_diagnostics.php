<?php
$pageTitle = 'CRM Push Diagnostics';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/crm_settings_helpers.php';

$diagnostics = fetch_crm_diagnostics($conn);

// All configured triggers from crm_settings
$allSettings = fetch_crm_settings($conn);

// Last 20 push logs
$recentLogs = fetch_crm_push_logs($conn, 20);

require_once __DIR__ . '/layout.php';
?>
<style>
  .diag-panel {
    background: var(--admin-surface, #fffdfd);
    border-radius: 18px;
    border: 1px solid rgba(128, 0, 31, 0.1);
    box-shadow: 0 14px 30px rgba(96, 18, 45, 0.08);
    overflow: hidden;
    margin-bottom: 28px;
  }
  .diag-panel__head {
    padding: 18px 20px 12px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .diag-panel__head h2 {
    margin: 0;
    flex: 1;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
    font-size: 1.35rem;
  }
  .diag-panel__body { padding: 20px; }

  .diag-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 780px;
  }
  .diag-table th,
  .diag-table td {
    padding: 10px 8px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.07);
    text-align: left;
    vertical-align: top;
  }
  .diag-table th {
    background: #fff6f8;
    color: #80001F;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.73rem;
    white-space: nowrap;
  }
  .diag-table td { font-size: 0.9rem; }

  .badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.78em;
    font-weight: 600;
  }
  .badge--success { background:#e6f7ee; color:#1a7a44; }
  .badge--fail    { background:#fde8e8; color:#c0392b; }
  .badge--warn    { background:#fff4e0; color:#a05a00; }
  .badge--grey    { background:#efefef; color:#666; }

  .rate-bar {
    display: inline-block;
    height: 8px;
    border-radius: 4px;
    background: #e6f7ee;
    border: 1px solid #b0dac0;
    position: relative;
    width: 80px;
    vertical-align: middle;
  }
  .rate-bar__fill {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    border-radius: 4px;
    background: #1a7a44;
  }

  .trigger-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    gap: 14px;
    margin-bottom: 8px;
  }
  .trigger-card {
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 10px;
    padding: 14px 16px;
    background: #fff;
  }
  .trigger-card__key {
    font-weight: 700;
    color: #80001F;
    font-size: 0.92rem;
    margin-bottom: 4px;
  }
  .trigger-card__detail {
    font-size: 0.82rem;
    color: #666;
    margin-bottom: 3px;
  }
  .trigger-card--enabled  { border-left: 4px solid #1a7a44; }
  .trigger-card--disabled { border-left: 4px solid #ccc; }
  .trigger-card--empty    { border-left: 4px solid #e08000; }
</style>

<div class="diag-panel">
  <div class="diag-panel__head">
    <h2>CRM Push Diagnostics</h2>
    <a href="crm_push_logs.php" style="font-size:0.88rem;color:#80001F;text-decoration:none;border:1px solid rgba(128,0,31,0.25);padding:4px 12px;border-radius:6px;">View All Logs</a>
    <a href="crm_settings.php" style="font-size:0.88rem;color:#80001F;text-decoration:none;border:1px solid rgba(128,0,31,0.25);padding:4px 12px;border-radius:6px;">&#8592; Settings</a>
  </div>
</div>

<!-- Trigger configuration overview -->
<div class="diag-panel">
  <div class="diag-panel__head">
    <h2 style="font-size:1.1rem;">Trigger Configuration</h2>
  </div>
  <div class="diag-panel__body">
    <div class="trigger-grid">
      <?php foreach ($allSettings as $s):
        $enabled  = !empty($s['is_enabled']);
        $hasEp    = !empty(trim($s['endpoint'] ?? ''));
        $hasTok   = !empty(trim($s['api_token'] ?? ''));
        $autoId   = '';
        if ($hasEp) {
            if (preg_match('#automations/([^/]+)/execute#', $s['endpoint'], $m)) {
                $autoId = $m[1];
            }
        }
        if (!$enabled) {
            $cardClass = 'trigger-card--disabled';
            $badgeClass = 'badge--grey'; $badgeLabel = 'Disabled';
        } elseif (!$hasEp || !$hasTok) {
            $cardClass = 'trigger-card--empty';
            $badgeClass = 'badge--warn'; $badgeLabel = 'Incomplete';
        } else {
            $cardClass = 'trigger-card--enabled';
            $badgeClass = 'badge--success'; $badgeLabel = 'Active';
        }
      ?>
        <div class="trigger-card <?= $cardClass ?>">
          <div class="trigger-card__key"><?= htmlspecialchars($s['setting_key']) ?></div>
          <div class="trigger-card__detail">
            <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
          </div>
          <?php if ($autoId): ?>
            <div class="trigger-card__detail">Auto ID: <code><?= htmlspecialchars($autoId) ?></code></div>
          <?php else: ?>
            <div class="trigger-card__detail" style="color:#a05a00;"><?= $hasEp ? 'Endpoint URL pattern unrecognised' : 'No endpoint set' ?></div>
          <?php endif; ?>
          <?php if ($hasTok): ?>
            <div class="trigger-card__detail" style="color:#1a7a44;">Token configured</div>
          <?php else: ?>
            <div class="trigger-card__detail" style="color:#c0392b;">No token</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Per-trigger last push + 7-day success rate -->
<?php if (!empty($diagnostics)): ?>
<div class="diag-panel">
  <div class="diag-panel__head">
    <h2 style="font-size:1.1rem;">Per-Trigger Push Stats (last 7 days)</h2>
  </div>
  <div class="diag-panel__body">
    <div style="overflow:auto;">
      <table class="diag-table">
        <tr>
          <th>Trigger</th>
          <th>Auto ID</th>
          <th>Last Status</th>
          <th>Last HTTP</th>
          <th>7d Success Rate</th>
          <th>7d Total</th>
          <th>Last Error</th>
          <th>Last Push</th>
        </tr>
        <?php foreach ($diagnostics as $d):
          $execStatus = $d['execution_status'] ?? '';
          $isSuccess  = $execStatus === 'success';
          $sBadge     = $isSuccess ? 'badge--success' : 'badge--fail';
          $total7d    = (int)($d['total_7d'] ?? 0);
          $succ7d     = (int)($d['successes_7d'] ?? 0);
          $rate       = $total7d > 0 ? round($succ7d / $total7d * 100) : 0;
          $rateColor  = $rate >= 90 ? '#1a7a44' : ($rate >= 50 ? '#a05a00' : '#c0392b');
          $httpCode   = isset($d['http_status']) && (int)$d['http_status'] > 0 ? (int)$d['http_status'] : null;
          $autoId     = (string)($d['automation_id'] ?? '');
          if ($autoId === '' && !empty($d['endpoint'])) {
              if (preg_match('#automations/([^/]+)/execute#', $d['endpoint'], $m)) { $autoId = $m[1]; }
          }
        ?>
          <tr>
            <td><?= htmlspecialchars($d['trigger_key'] ?? '—') ?></td>
            <td style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($autoId ?: '—') ?></td>
            <td><span class="badge <?= $sBadge ?>"><?= htmlspecialchars($execStatus ?: '—') ?></span></td>
            <td style="<?= $httpCode === 200 ? 'color:#1a7a44;font-weight:600;' : 'color:#c0392b;font-weight:600;' ?>">
              <?= $httpCode ?? '—' ?>
            </td>
            <td>
              <?php if ($total7d > 0): ?>
                <span class="rate-bar"><span class="rate-bar__fill" style="width:<?= $rate ?>%;background:<?= $rate >= 90 ? '#1a7a44' : ($rate >= 50 ? '#f39c12' : '#c0392b') ?>;"></span></span>
                <span style="margin-left:6px;color:<?= $rateColor ?>;font-size:0.85rem;"><?= $rate ?>%</span>
              <?php else: ?>
                <span style="color:#aaa;font-size:0.85rem;">No data</span>
              <?php endif; ?>
            </td>
            <td><?= $total7d ?></td>
            <td style="font-size:0.80rem;color:#c0392b;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= htmlspecialchars((string)($d['error_message'] ?? '')) ?>">
                <?= htmlspecialchars(mb_substr((string)($d['error_message'] ?? ''), 0, 80)) ?>
            </td>
            <td style="white-space:nowrap;font-size:0.85rem;"><?= htmlspecialchars((string)($d['created_at'] ?? '—')) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Recent 20 push log entries -->
<div class="diag-panel">
  <div class="diag-panel__head">
    <h2 style="font-size:1.1rem;">Recent 20 Pushes</h2>
    <a href="crm_push_logs.php" style="font-size:0.85rem;color:#80001F;text-decoration:none;">View all &rarr;</a>
  </div>
  <div class="diag-panel__body">
    <div style="overflow:auto;">
      <table class="diag-table">
        <tr>
          <th>Date</th>
          <th>Trigger</th>
          <th>Name</th>
          <th>Mobile</th>
          <th>Status</th>
          <th>HTTP</th>
          <th>ms</th>
          <th>Error</th>
        </tr>
        <?php foreach ($recentLogs as $log):
          $execStatus = $log['execution_status'] ?? $log['status'] ?? '';
          $isSuccess  = $execStatus === 'success';
          $badgeClass = $isSuccess ? 'badge--success' : 'badge--fail';
          $httpCode   = isset($log['http_status']) && (int)$log['http_status'] > 0 ? (int)$log['http_status'] : null;
        ?>
          <tr>
            <td style="white-space:nowrap;font-size:0.85rem;"><?= htmlspecialchars($log['created_at']) ?></td>
            <td style="white-space:nowrap;"><?= htmlspecialchars((string)($log['trigger_key'] ?? '—')) ?></td>
            <td><?= htmlspecialchars($log['name']) ?></td>
            <td style="white-space:nowrap;"><?= htmlspecialchars($log['mobile']) ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($execStatus ?: '—') ?></span></td>
            <td style="<?= $httpCode === 200 ? 'color:#1a7a44;font-weight:600;' : ($httpCode ? 'color:#c0392b;font-weight:600;' : '') ?>">
              <?= $httpCode ?? '—' ?>
            </td>
            <td><?= isset($log['response_time_ms']) && (int)$log['response_time_ms'] > 0 ? (int)$log['response_time_ms'] : '—' ?></td>
            <td style="font-size:0.80rem;color:#c0392b;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= htmlspecialchars((string)($log['error_message'] ?? '')) ?>">
                <?= htmlspecialchars(mb_substr((string)($log['error_message'] ?? ''), 0, 80)) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
