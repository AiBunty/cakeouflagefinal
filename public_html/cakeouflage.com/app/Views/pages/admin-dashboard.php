<?php /* Cakeouflage Admin — Dashboard */ ?>
<section class="section section--compact" data-page="admin-dashboard">
  <style>
    .admin-mini-chart {
      display: grid;
      gap: 0.5rem;
      padding: 0.5rem 0;
    }
    .admin-mini-chart__row {
      display: grid;
      grid-template-columns: 70px 1fr auto;
      gap: 0.5rem;
      align-items: center;
      font-size: 0.85rem;
    }
    .admin-mini-chart__track {
      height: 10px;
      border-radius: 999px;
      background: #f1f3f7;
      overflow: hidden;
    }
    .admin-mini-chart__fill {
      height: 100%;
      border-radius: 999px;
      background: linear-gradient(90deg, #7b1f3a 0%, #d94b73 100%);
      min-width: 2px;
    }
    .admin-mini-chart__label {
      color: #475569;
      font-weight: 600;
    }
    .admin-mini-chart__value {
      color: #0f172a;
      font-weight: 600;
    }
  </style>

  <!-- Page header -->
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Dashboard</h1>
      <p class="admin-page-desc">Welcome back. Here's what's happening today.</p>
    </div>
    <div class="admin-page-actions">
      <button class="btn btn--secondary btn--sm" id="refreshDashboardBtn" type="button">↻ Refresh</button>
    </div>
  </div>

  <!-- KPI Grid -->
  <div class="admin-kpi-grid" id="adminSummaryGrid">
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">📦</div>
      <div class="admin-kpi__body">
        <h3>Total Orders</h3>
        <p class="admin-kpi__value" id="kpiOrders">-</p>
        <span class="admin-kpi__sub" id="kpiOrdersSub">all time</span>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">💰</div>
      <div class="admin-kpi__body">
        <h3>Revenue</h3>
        <p class="admin-kpi__value" id="kpiRevenue">-</p>
        <span class="admin-kpi__sub" id="kpiRevenueSub">all time</span>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">📅</div>
      <div class="admin-kpi__body">
        <h3>Today Sales</h3>
        <p class="admin-kpi__value" id="kpiRevenueToday">-</p>
        <span class="admin-kpi__sub">paid orders</span>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">🗓️</div>
      <div class="admin-kpi__body">
        <h3>Month Sales</h3>
        <p class="admin-kpi__value" id="kpiRevenueMonth">-</p>
        <span class="admin-kpi__sub">current month</span>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">📈</div>
      <div class="admin-kpi__body">
        <h3>Year Sales</h3>
        <p class="admin-kpi__value" id="kpiRevenueYear">-</p>
        <span class="admin-kpi__sub">current year</span>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">👥</div>
      <div class="admin-kpi__body">
        <h3>Customers</h3>
        <p class="admin-kpi__value" id="kpiCustomers">-</p>
        <span class="admin-kpi__sub">registered</span>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">🎂</div>
      <div class="admin-kpi__body">
        <h3>Active Products</h3>
        <p class="admin-kpi__value" id="kpiProducts">-</p>
        <span class="admin-kpi__sub">in catalog</span>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">🏢</div>
      <div class="admin-kpi__body">
        <h3>B2B Accounts</h3>
        <p class="admin-kpi__value" id="kpiB2b">-</p>
        <span class="admin-kpi__sub">approved</span>
      </div>
    </article>
    <article class="admin-kpi card">
      <div class="admin-kpi__icon">📝</div>
      <div class="admin-kpi__body">
        <h3>Pending Quotes</h3>
        <p class="admin-kpi__value" id="kpiQuotes">-</p>
        <span class="admin-kpi__sub">awaiting review</span>
      </div>
    </article>
  </div>

  <!-- Two column: recent orders + quick actions -->
  <div class="admin-dash-grid">
    <article class="card">
      <div class="admin-table-header">
        <h2 class="admin-card-title">Recent Orders</h2>
        <a href="/admin/orders" class="btn btn--ghost btn--sm">View All →</a>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="dashRecentOrdersTable">
          <thead>
            <tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr>
          </thead>
          <tbody id="dashRecentOrdersTbody">
            <tr><td colspan="5" class="admin-table-empty">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </article>

    <div class="admin-dash-sidebar">
      <article class="card">
        <h2 class="admin-card-title">Low Stock Alerts</h2>
        <div id="dashLowStock">
          <p class="text-muted text-sm">Loading…</p>
        </div>
      </article>
      <article class="card">
        <h2 class="admin-card-title">Quick Actions</h2>
        <div class="admin-quick-actions">
          <a href="/admin/products" class="btn btn--secondary btn--block">✚ Add Product</a>
          <a href="/admin/orders" class="btn btn--secondary btn--block">📦 View Orders</a>
          <a href="/admin/customers" class="btn btn--secondary btn--block">👥 Customers</a>
          <a href="/admin/communications" class="btn btn--secondary btn--block">📬 Communications</a>
        </div>
      </article>
    </div>
  </div>

  <div class="admin-dash-grid" style="margin-top:1rem;">
    <article class="card">
      <div class="admin-table-header">
        <h2 class="admin-card-title">Monthly Revenue (Last 12 Months)</h2>
      </div>
      <div id="monthlyRevenueBars" class="admin-mini-chart">
        <p class="text-muted text-sm">Loading…</p>
      </div>
    </article>
    <article class="card">
      <div class="admin-table-header">
        <h2 class="admin-card-title">Yearly Revenue (Last 5 Years)</h2>
      </div>
      <div id="yearlyRevenueBars" class="admin-mini-chart">
        <p class="text-muted text-sm">Loading…</p>
      </div>
    </article>
  </div>

</section>
