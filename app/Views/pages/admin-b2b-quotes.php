<?php /* Cakeouflage Admin — B2B Quotes */ ?>
<section class="section section--compact" data-page="admin-b2b-quotes">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">B2B Quote Requests</h1>
      <p class="admin-page-desc">Review incoming wholesale quote requests, set pricing, and convert to orders.</p>
    </div>
    <div class="admin-page-actions">
      <button class="btn btn--secondary" onclick="loadAdminB2bQuotes()">↻ Refresh</button>
    </div>
  </div>

  <!-- status KPIs -->
  <div class="admin-kpi-grid admin-kpi-grid--4" style="margin-bottom:var(--space-4)">
    <article class="admin-kpi"><div class="admin-kpi__icon">📥</div><div><h3>New Requests</h3><p id="quoteKpiNew">-</p></div></article>
    <article class="admin-kpi admin-kpi--partpaid"><div class="admin-kpi__icon">📝</div><div><h3>Quoted</h3><p id="quoteKpiQuoted">-</p></div></article>
    <article class="admin-kpi admin-kpi--paid"><div class="admin-kpi__icon">✅</div><div><h3>Accepted</h3><p id="quoteKpiAccepted">-</p></div></article>
    <article class="admin-kpi admin-kpi--overdue"><div class="admin-kpi__icon">❌</div><div><h3>Rejected</h3><p id="quoteKpiRejected">-</p></div></article>
  </div>

  <article class="card">
    <div class="admin-table-header">
      <div class="admin-filter-row">
        <select id="b2bQuoteStatusFilter" class="admin-select-sm">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="quoted">Quoted</option>
          <option value="accepted">Accepted</option>
          <option value="rejected">Rejected</option>
        </select>
        <input type="text" id="b2bQuoteSearch" class="admin-search-input" placeholder="Search quote, company…">
      </div>
      <button class="btn btn--primary btn--sm" onclick="exportB2bQuotes()">⬇ Export</button>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="adminB2bQuotesTable">
        <thead>
          <tr>
            <th>Quote #</th><th>Company</th><th>Event / Purpose</th><th>Mode</th>
            <th>Status</th><th>Quoted Amount</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="7" class="admin-table-empty">Loading quotes…</td></tr>
        </tbody>
      </table>
    </div>
    <p id="adminB2bQuotesStatus" class="form-feedback" aria-live="polite"></p>
  </article>

  <!-- Quote detail drawer -->
  <div id="b2bQuoteDrawer" class="admin-drawer" style="display:none" role="dialog" aria-modal="true">
    <div class="admin-drawer__backdrop" onclick="document.getElementById('b2bQuoteDrawer').style.display='none'"></div>
    <div class="admin-drawer__panel">
      <div class="admin-drawer__header">
        <h2>Quote Detail</h2>
        <button class="btn btn--ghost" onclick="document.getElementById('b2bQuoteDrawer').style.display='none'">✕</button>
      </div>
      <div id="b2bQuoteDrawerBody"></div>
    </div>
  </div>

</section>

  <article class="card">
    <div class="admin-table-header">
      <div class="admin-filter-row">
        <select id="b2bQuoteStatusFilter" class="admin-select-sm">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="quoted">Quoted</option>
          <option value="accepted">Accepted</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="adminB2bQuotesTable">
        <thead>
          <tr>
            <th>Quote #</th><th>Company</th><th>Event</th><th>Mode</th>
            <th>Status</th><th>Total</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="7" class="admin-table-empty">Loading quotes…</td></tr>
        </tbody>
      </table>
    </div>
    <p id="adminB2bQuotesStatus" class="form-feedback"></p>
  </article>

</section>