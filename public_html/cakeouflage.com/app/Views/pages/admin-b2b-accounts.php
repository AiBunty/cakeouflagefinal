<?php /* Cakeouflage Admin — B2B Accounts */ ?>
<section class="section section--compact" data-page="admin-b2b-accounts">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">B2B Accounts</h1>
      <p class="admin-page-desc">Approve, suspend, and manage corporate clients, resellers, and event planners.</p>
    </div>
    <div class="admin-page-actions">
      <input type="text" id="b2bAccountSearchInput" class="admin-search-input" placeholder="Search by company or owner…">
      <button class="btn btn--secondary" onclick="loadAdminB2bAccounts()">↻ Refresh</button>
    </div>
  </div>

  <!-- KPI strip -->
  <div class="admin-kpi-grid admin-kpi-grid--3" id="b2bAccountKpiRow">
    <article class="admin-kpi">
      <div class="admin-kpi__icon">⏳</div>
      <div><h3>Pending Approval</h3><p id="b2bKpiPending">-</p></div>
    </article>
    <article class="admin-kpi admin-kpi--paid">
      <div class="admin-kpi__icon">✅</div>
      <div><h3>Approved Accounts</h3><p id="b2bKpiApproved">-</p></div>
    </article>
    <article class="admin-kpi admin-kpi--overdue">
      <div class="admin-kpi__icon">🚫</div>
      <div><h3>Suspended</h3><p id="b2bKpiSuspended">-</p></div>
    </article>
  </div>

  <article class="card" style="margin-top:var(--space-4)">
    <div class="admin-table-header">
      <div class="admin-filter-row">
        <select id="b2bAccountStatusFilter" class="admin-select-sm">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="suspended">Suspended</option>
        </select>
        <select id="b2bAccountTypeFilter" class="admin-select-sm">
          <option value="">All Types</option>
          <option value="corporate">Corporate</option>
          <option value="reseller">Reseller</option>
          <option value="event_planner">Event Planner</option>
          <option value="restaurant">Restaurant / Cafe</option>
        </select>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="adminB2bAccountsTable">
        <thead>
          <tr>
            <th>Company</th><th>Owner</th><th>Type</th><th>Status</th>
            <th>Credit Limit</th><th>Quotes</th><th>Orders</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="8" class="admin-table-empty">Loading B2B accounts…</td></tr>
        </tbody>
      </table>
    </div>
    <p id="adminB2bAccountsStatus" class="form-feedback" aria-live="polite"></p>
  </article>

  <!-- Account detail drawer (filled via JS) -->
  <div id="b2bAccountDrawer" class="admin-drawer" style="display:none" role="dialog" aria-modal="true">
    <div class="admin-drawer__backdrop" onclick="document.getElementById('b2bAccountDrawer').style.display='none'"></div>
    <div class="admin-drawer__panel">
      <div class="admin-drawer__header">
        <h2>Account Detail</h2>
        <button class="btn btn--ghost" onclick="document.getElementById('b2bAccountDrawer').style.display='none'">✕</button>
      </div>
      <div id="b2bAccountDrawerBody"></div>
    </div>
  </div>

</section>

  <article class="card">
    <div class="admin-table-header">
      <div class="admin-filter-row">
        <select id="b2bAccountStatusFilter" class="admin-select-sm">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="suspended">Suspended</option>
        </select>
        <select id="b2bAccountTypeFilter" class="admin-select-sm">
          <option value="">All Types</option>
          <option value="corporate">Corporate</option>
          <option value="reseller">Reseller</option>
          <option value="event_planner">Event Planner</option>
          <option value="restaurant">Restaurant/Cafe</option>
        </select>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="adminB2bAccountsTable">
        <thead>
          <tr>
            <th>Company</th><th>Owner</th><th>Type</th><th>Status</th>
            <th>Credit Limit</th><th>Quotes</th><th>Orders</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="8" class="admin-table-empty">Loading B2B accounts…</td></tr>
        </tbody>
      </table>
    </div>
    <p id="adminB2bAccountsStatus" class="form-feedback"></p>
  </article>

</section>