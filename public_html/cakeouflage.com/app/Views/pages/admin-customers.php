<?php /* Cakeouflage Admin — Customers */ ?>
<section class="section section--compact" data-page="admin-customers">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Customers</h1>
      <p class="admin-page-desc">Manage customer profiles, tags, and CRM data.</p>
    </div>
    <div class="admin-page-actions">
      <input type="text" id="customerSearchInput" class="admin-search-input" placeholder="Search by name, email, phone…">
    </div>
  </div>

  <article class="card">
    <div class="admin-table-header">
      <div class="admin-filter-row">
        <select id="customerTagFilter" class="admin-select-sm">
          <option value="">All Tags</option>
          <option>VIP</option>
          <option>Wholesale</option>
          <option>Birthday Club</option>
        </select>
        <select id="customerSortSelect" class="admin-select-sm">
          <option value="newest">Newest First</option>
          <option value="orders_desc">Most Orders</option>
          <option value="name_asc">Name A–Z</option>
        </select>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="adminCustomersTable">
        <thead>
          <tr>
            <th>Name</th><th>Email</th><th>Phone</th>
            <th>Tags</th><th>DOB</th><th>Orders</th><th>Invoices</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="7" class="admin-table-empty">Loading customers…</td></tr>
        </tbody>
      </table>
    </div>
    <div class="admin-table-footer">
      <span id="customersCountLabel" class="text-sm text-muted"></span>
    </div>
  </article>

</section>