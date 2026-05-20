<section class="section section--compact" data-page="admin-invoices">
  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Invoice Filters</h2>
      <form id="adminInvoiceFilters" class="form-grid" novalidate>
        <label class="form-control">
          <span>Search</span>
          <input type="search" name="q" placeholder="Invoice number or customer" />
        </label>
        <div class="form-row-two">
          <label class="form-control">
            <span>Status</span>
            <select name="status">
              <option value="">All</option>
              <option value="draft">Draft</option>
              <option value="pending_payment">Pending Payment</option>
              <option value="part_paid">Part Paid</option>
              <option value="paid">Paid</option>
              <option value="overdue">Overdue</option>
              <option value="payment_under_verification">Under Verification</option>
              <option value="unpaid_rejected">Unpaid/Rejected</option>
              <option value="cancelled">Cancelled</option>
              <option value="refunded">Refunded</option>
            </select>
          </label>
          <label class="form-control">
            <span>Customer Type</span>
            <select name="customer_type">
              <option value="">All</option>
              <option value="retail">Retail</option>
              <option value="b2b">B2B</option>
            </select>
          </label>
        </div>
        <button class="btn btn--secondary" type="submit">Apply</button>
      </form>
      <p class="text-muted" id="adminInvoiceStatus"></p>
    </article>

    <article class="card">
      <h2>Manual Payment Entry</h2>
      <form id="adminInvoicePaymentForm" class="form-grid" enctype="multipart/form-data" novalidate>
        <label class="form-control">
          <span>Invoice ID</span>
          <input type="number" name="invoice_id" min="1" required />
        </label>
        <div class="form-row-two">
          <label class="form-control">
            <span>Method</span>
            <select name="payment_method">
              <option value="upi">UPI</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cash">Cash</option>
              <option value="pos_card">POS / Manual Card</option>
              <option value="payment_link">Payment Link</option>
            </select>
          </label>
          <label class="form-control">
            <span>Status</span>
            <select name="payment_status">
              <option value="submitted">Submitted</option>
              <option value="verified">Verified</option>
              <option value="rejected">Rejected</option>
            </select>
          </label>
        </div>
        <div class="form-row-two">
          <label class="form-control">
            <span>Amount</span>
            <input type="number" step="0.01" min="0" name="amount" required />
          </label>
          <label class="form-control">
            <span>Reference</span>
            <input type="text" name="payment_reference" placeholder="UPI/Bank reference" />
          </label>
        </div>
        <label class="form-control">
          <span>Proof Screenshot (Optional)</span>
          <input type="file" name="proof" accept="image/jpeg,image/png,image/webp" />
        </label>
        <label class="form-control">
          <span>Note</span>
          <textarea name="note" rows="3"></textarea>
        </label>
        <button class="btn btn--primary" type="submit">Record Payment</button>
      </form>
    </article>
  </div>

  <article class="card" style="margin-top: var(--space-4);">
    <div class="admin-table-header">
      <h2>Invoices</h2>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="adminInvoicesTable">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Status</th>
            <th>Total</th>
            <th>Paid</th>
            <th>Due</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </article>

  <aside id="adminInvoiceDrawer" class="admin-drawer" hidden aria-hidden="true">
    <div class="admin-drawer__backdrop" data-action="close-invoice-drawer"></div>
    <div class="admin-drawer__panel" role="dialog" aria-modal="true" aria-label="Invoice details">
      <div class="admin-drawer__header">
        <h3 id="adminInvoiceDrawerTitle">Invoice</h3>
        <button class="btn btn--secondary" type="button" data-action="close-invoice-drawer">Close</button>
      </div>

      <div id="adminInvoiceDrawerMeta" class="admin-drawer__meta"></div>

      <section class="admin-drawer__section">
        <h4>Status Update</h4>
        <form id="adminInvoiceStatusForm" class="form-grid" novalidate>
          <label class="form-control">
            <span>Invoice Status</span>
            <select name="invoice_status">
              <option value="draft">Draft</option>
              <option value="pending_payment">Pending Payment</option>
              <option value="part_paid">Part Paid</option>
              <option value="paid">Paid</option>
              <option value="overdue">Overdue</option>
              <option value="payment_under_verification">Under Verification</option>
              <option value="unpaid_rejected">Unpaid/Rejected</option>
              <option value="cancelled">Cancelled</option>
              <option value="refunded">Refunded</option>
            </select>
          </label>
          <label class="form-control">
            <span>Note</span>
            <textarea name="note" rows="3"></textarea>
          </label>
          <button class="btn btn--primary" type="submit">Update Status</button>
        </form>
      </section>

      <section class="admin-drawer__section">
        <h4>Invoice Items</h4>
        <div id="adminInvoiceItems" class="admin-list"></div>
      </section>

      <section class="admin-drawer__section">
        <h4>Payments</h4>
        <div id="adminInvoicePayments" class="admin-list"></div>
      </section>

      <section class="admin-drawer__section">
        <h4>Payment Proofs</h4>
        <div id="adminInvoiceProofs" class="admin-list"></div>
      </section>

      <section class="admin-drawer__section">
        <h4>Status History</h4>
        <div id="adminInvoiceHistory" class="admin-list"></div>
      </section>
    </div>
  </aside>
</section>
