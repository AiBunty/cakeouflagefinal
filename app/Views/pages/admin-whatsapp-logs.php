<?php /* Cakeouflage Admin — WhatsApp Logs */ ?>
<section class="section section--compact" data-page="admin-whatsapp-logs">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">WhatsApp Logs &amp; Monitoring</h1>
      <p class="admin-page-desc">Sync logs, approval logs, message send history, failed queue, and template usage.</p>
    </div>
    <div class="admin-page-actions">
      <button class="btn btn--secondary" onclick="loadWaLogs()">↻ Refresh All</button>
    </div>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Sync Logs</h2>
      <div id="waSyncLogs" class="admin-list"></div>
    </article>
    <article class="card">
      <h2>Approval Logs</h2>
      <div id="waApprovalLogs" class="admin-list"></div>
    </article>
  </div>
  <div class="admin-panel-grid admin-panel-grid--two" style="margin-top: var(--space-4);">
    <article class="card">
      <h2>Send Logs</h2>
      <div id="waSendLogs" class="admin-list"></div>
    </article>
    <article class="card">
      <h2>Failed Template Queue</h2>
      <div id="waFailedQueue" class="admin-list"></div>
    </article>
  </div>
  <article class="card" style="margin-top: var(--space-4);">
    <h2>Template Usage Report</h2>
    <div class="admin-table-wrap">
      <table class="admin-table" id="waUsageReportTable">
        <thead><tr><th>Template</th><th>Total Sends</th><th>Sent</th><th>Failed</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </article>
</section>