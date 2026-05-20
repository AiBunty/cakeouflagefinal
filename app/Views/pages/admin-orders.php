<section class="section section--compact" data-page="admin-orders">
  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Filters</h2>
      <form id="adminOrderFilters" class="form-grid" novalidate>
        <label class="form-control">
          <span>Search</span>
          <input type="search" name="q" placeholder="Order number, name, email, phone" />
        </label>
        <div class="form-row-two">
          <label class="form-control">
            <span>Order Status</span>
            <select name="order_status">
              <option value="">All</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="in_preparation">In Preparation</option>
              <option value="out_for_delivery">Out For Delivery</option>
              <option value="ready_for_pickup">Ready For Pickup</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </label>
          <label class="form-control">
            <span>Payment Status</span>
            <select name="payment_status">
              <option value="">All</option>
              <option value="pending">Pending</option>
              <option value="paid">Paid</option>
              <option value="failed">Failed</option>
              <option value="refunded">Refunded</option>
            </select>
          </label>
        </div>
        <button class="btn btn--secondary" type="submit">Apply Filters</button>
      </form>
      <div class="product-card__actions">
        <a id="adminOrderExport" class="btn btn--primary" href="/api/admin/orders/export">Export CSV</a>
      </div>
      <p id="adminOrderStatus" class="text-muted"></p>
    </article>

    <article class="card">
      <h2>Orders</h2>
      <div class="admin-table-wrap">
        <table class="admin-table" id="adminOrdersTable">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Fulfilment</th>
              <th>Amount</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </article>
  </div>

  <aside id="adminOrderDrawer" class="admin-drawer" hidden aria-hidden="true">
    <div class="admin-drawer__backdrop" data-action="close-order-drawer"></div>
    <div class="admin-drawer__panel" role="dialog" aria-modal="true" aria-label="Order details">
      <div class="admin-drawer__header">
        <h3 id="adminOrderDrawerTitle">Order Details</h3>
        <button class="btn btn--secondary" type="button" data-action="close-order-drawer">Close</button>
      </div>

      <div id="adminOrderDrawerMeta" class="admin-drawer__meta"></div>

      <section class="admin-drawer__section">
        <h4>Line Items</h4>
        <div id="adminOrderDrawerItems" class="admin-list"></div>
      </section>

      <section class="admin-drawer__section">
        <h4>Slot And Notes</h4>
        <form id="adminOrderDetailForm" class="form-grid" novalidate>
          <div class="form-row-two">
            <label class="form-control">
              <span>Scheduled Slot Label</span>
              <input type="text" name="scheduled_slot_label" placeholder="e.g. 2 PM - 4 PM" />
            </label>
            <label class="form-control">
              <span>Scheduled DateTime</span>
              <input type="datetime-local" name="scheduled_slot" />
            </label>
          </div>
          <label class="form-control">
            <span>Admin Note</span>
            <textarea name="admin_note" rows="3" placeholder="Internal note for fulfilment team"></textarea>
          </label>
          <button class="btn btn--primary" type="submit">Save Slot/Note</button>
        </form>
      </section>

      <section class="admin-drawer__section">
        <h4>Timeline</h4>
        <div id="adminOrderTimeline" class="admin-list"></div>
      </section>
    </div>
  </aside>
</section>
