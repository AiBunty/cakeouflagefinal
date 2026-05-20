<?php /* Cakeouflage Admin — Reports */ ?>
<section class="section section--compact" data-page="admin-reports">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Reports</h1>
      <p class="admin-page-desc">Business overview: sales, communications, and platform health.</p>
    </div>
    <div class="admin-page-actions">
      <select id="reportPeriodSelect" class="admin-select-sm">
        <option value="30">Last 30 days</option>
        <option value="7">Last 7 days</option>
        <option value="90">Last 90 days</option>
        <option value="365">Last 12 months</option>
      </select>
      <button class="btn btn--secondary btn--sm" id="refreshReportsBtn" type="button">↻ Refresh</button>
    </div>
  </div>

  <!-- KPI summary -->
  <div class="admin-kpi-grid">
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">🛒</div>
      <div class="admin-kpi__body">
        <h3>Retail Orders</h3>
        <p class="admin-kpi__value" id="reportRetailOrders">-</p>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">🏢</div>
      <div class="admin-kpi__body">
        <h3>B2B Orders</h3>
        <p class="admin-kpi__value" id="reportB2bOrders">-</p>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">📄</div>
      <div class="admin-kpi__body">
        <h3>Pending Invoices</h3>
        <p class="admin-kpi__value" id="reportPendingInvoices">-</p>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">📧</div>
      <div class="admin-kpi__body">
        <h3>Queued Comms</h3>
        <p class="admin-kpi__value" id="reportQueuedComms">-</p>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">⚠️</div>
      <div class="admin-kpi__body">
        <h3>Failed Comms</h3>
        <p class="admin-kpi__value" id="reportFailedComms">-</p>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">💬</div>
      <div class="admin-kpi__body">
        <h3>Approved WA Templates</h3>
        <p class="admin-kpi__value" id="reportApprovedWa">-</p>
      </div>
    </article>
  </div>

  <!-- Top products + revenue breakdown -->
  <div class="admin-dash-grid">
    <article class="card">
      <h2 class="admin-card-title">Top Products by Orders</h2>
      <div class="admin-table-wrap">
        <table class="admin-table" id="reportTopProductsTable">
          <thead><tr><th>Product</th><th>Orders</th><th>Revenue</th></tr></thead>
          <tbody id="reportTopProductsTbody">
            <tr><td colspan="3" class="admin-table-empty">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </article>
    <article class="card">
      <h2 class="admin-card-title">Revenue by Category</h2>
      <div id="reportRevByCategoryList">
        <p class="text-sm text-muted">Loading…</p>
      </div>
    </article>
  </div>

</section>