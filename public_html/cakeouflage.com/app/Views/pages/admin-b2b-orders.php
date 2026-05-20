<?php /* Cakeouflage Admin — B2B Orders */ ?>
<section class="section section--compact" data-page="admin-b2b-orders">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">B2B Orders</h1>
      <p class="admin-page-desc">Track and manage wholesale, corporate gifting, and reseller orders end-to-end.</p>
    </div>
    <div class="admin-page-actions">
      <input type="text" id="b2bOrderSearchInput" class="admin-search-input" placeholder="Order #, company…">
      <button class="btn btn--secondary" onclick="exportB2bOrders()">⬇ Export CSV</button>
    </div>
  </div>


  <article class="card">
    <div class="admin-table-header">
      <div class="admin-filter-row">
        <select id="b2bOrderStatusFilter" class="admin-select-sm">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="confirmed">Confirmed</option>
          <option value="processing">Processing</option>
          <option value="dispatched">Dispatched</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select id="b2bOrderPaymentFilter" class="admin-select-sm">
          <option value="">All Payments</option>
          <option value="pending">Payment Pending</option>
          <option value="paid">Paid</option>
        </select>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="adminB2bOrdersTable">
        <thead>
          <tr>
            <th>Order #</th><th>Company</th><th>Mode</th><th>Order Status</th>
            <th>Payment</th><th>Total</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="7" class="admin-table-empty">Loading B2B orders…</td></tr>
        </tbody>
      </table>
    </div>
    <p id="adminB2bOrdersStatus" class="form-feedback"></p>
  </article>

</section>