<?php /* Cakeouflage Admin — Finance Dashboard */ ?>
<section class="section section--compact" data-page="admin-finance-dashboard">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Finance Dashboard</h1>
      <p class="admin-page-desc">Invoice overview, receivables, and payment ageing at a glance.</p>
    </div>
    <div class="admin-page-actions">
      <a class="btn btn--secondary" href="/admin/invoices"><span>📄</span> All Invoices</a>
      <button class="btn btn--primary" onclick="loadFinanceDashboard()"><span>↻</span> Refresh</button>
    </div>
  </div>

  <!-- KPI Row -->
  <div class="admin-kpi-grid admin-kpi-grid--4" id="financeSummaryGrid">
    <article class="admin-kpi admin-kpi--paid">
      <div class="admin-kpi__icon">✅</div>
      <div>
        <h3>Paid Invoices</h3>
        <p id="finPaidInvoices"><span class="admin-kpi__loading">…</span></p>
      </div>
    </article>
    <article class="admin-kpi admin-kpi--unpaid">
      <div class="admin-kpi__icon">⏳</div>
      <div>
        <h3>Unpaid / Pending</h3>
        <p id="finUnpaidInvoices"><span class="admin-kpi__loading">…</span></p>
      </div>
    </article>
    <article class="admin-kpi admin-kpi--overdue">
      <div class="admin-kpi__icon">⚠️</div>
      <div>
        <h3>Overdue</h3>
        <p id="finOverdueInvoices"><span class="admin-kpi__loading">…</span></p>
      </div>
    </article>
    <article class="admin-kpi admin-kpi--partpaid">
      <div class="admin-kpi__icon">🔄</div>
      <div>
        <h3>Part-Paid</h3>
        <p id="finPartPaidInvoices"><span class="admin-kpi__loading">…</span></p>
      </div>
    </article>
  </div>

  <!-- Receivables Row -->
  <div class="admin-kpi-grid admin-kpi-grid--3" style="margin-top:var(--space-4)">
    <article class="admin-kpi">
      <div class="admin-kpi__icon">💰</div>
      <div>
        <h3>Total Receivables</h3>
        <p id="finTotalReceivables"><span class="admin-kpi__loading">…</span></p>
        <span class="admin-kpi__sub">All outstanding balance</span>
      </div>
    </article>
    <article class="admin-kpi">
      <div class="admin-kpi__icon">🧾</div>
      <div>
        <h3>Retail Receivables</h3>
        <p id="finRetailReceivables"><span class="admin-kpi__loading">…</span></p>
        <span class="admin-kpi__sub">Consumer orders</span>
      </div>
    </article>
    <article class="admin-kpi">
      <div class="admin-kpi__icon">🏢</div>
      <div>
        <h3>B2B Receivables</h3>
        <p id="finB2bReceivables"><span class="admin-kpi__loading">…</span></p>
        <span class="admin-kpi__sub">Corporate &amp; wholesale</span>
      </div>
    </article>
  </div>

  <!-- Ageing Report -->
  <article class="card" style="margin-top: var(--space-5);">
    <div class="admin-table-header">
      <div>
        <h2>Receivables Ageing Report</h2>
        <p class="admin-page-desc" style="margin:0">Outstanding invoices grouped by overdue period.</p>
      </div>
      <a class="btn btn--outline-burgundy" href="/api/admin/finance/export" download>⬇ Export CSV</a>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="financeAgeingTable">
        <thead>
          <tr>
            <th>Ageing Bucket</th>
            <th>Invoice Count</th>
            <th>Balance Due (₹)</th>
            <th>% of Total</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="4" class="admin-table-empty">Loading ageing data…</td></tr>
        </tbody>
      </table>
    </div>
  </article>

</section>
