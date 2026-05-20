<?php /* Cakeouflage — B2B Dashboard */ ?>
<main class="section" data-page="b2b-dashboard">
  <div class="container">
    <div class="section-header">
      <div class="section-label">Business Console</div>
      <h1 class="section-title">B2B Dashboard</h1>
      <p class="text-muted" id="b2bWelcomeText">Loading your account...</p>
    </div>

    <section class="kpi-grid" aria-label="B2B summary">
      <article class="kpi-card">
        <h3 class="kpi-card__label">Pending Quotes</h3>
        <p class="kpi-card__value" id="b2bPendingQuotes">0</p>
      </article>
      <article class="kpi-card">
        <h3 class="kpi-card__label">Accepted Quotes</h3>
        <p class="kpi-card__value" id="b2bAcceptedQuotes">0</p>
      </article>
      <article class="kpi-card">
        <h3 class="kpi-card__label">Total Orders</h3>
        <p class="kpi-card__value" id="b2bOrdersTotal">0</p>
      </article>
      <article class="kpi-card">
        <h3 class="kpi-card__label">Credit Limit</h3>
        <p class="kpi-card__value" id="b2bCreditLimit">Rs 0.00</p>
      </article>
    </section>

    <div class="contact-layout" style="margin-top:var(--space-8)">
      <section class="contact-form-panel">
        <h2 class="contact-form-panel__title">Request New Quote</h2>
        <form class="form-grid" id="b2bQuoteForm" novalidate>
          <div class="form-row-2">
            <label class="form-control">
              <span class="form-label">Event Type</span>
              <input type="text" name="event_type" placeholder="Corporate gifting / wedding / event">
            </label>
            <label class="form-control">
              <span class="form-label">Fulfilment Mode</span>
              <select name="fulfilment_mode">
                <option value="delivery">Delivery</option>
                <option value="pickup">Pickup</option>
              </select>
            </label>
          </div>

          <div class="form-row-2">
            <label class="form-control">
              <span class="form-label">Scheduled Date</span>
              <input type="date" name="scheduled_date">
            </label>
            <label class="form-control">
              <span class="form-label">Item List (JSON) <span class="form-required">*</span></span>
              <textarea name="items_json" rows="4" required placeholder='[{"product_id":1,"variant_id":2,"quantity":5}]'></textarea>
            </label>
          </div>

          <label class="form-control">
            <span class="form-label">Note to Team</span>
            <textarea name="note" rows="3" placeholder="Flavor constraints, packaging requirements, delivery timing"></textarea>
          </label>

          <button class="btn btn--primary" type="submit">Submit Quote Request</button>
          <p id="b2bQuoteStatus" class="form-feedback" aria-live="polite"></p>
        </form>
      </section>

      <aside class="contact-info-panel">
        <h2 class="contact-info-panel__title">Account Details</h2>
        <p><strong>Company:</strong> <span id="b2bCompanyName">-</span></p>
        <p><strong>Account Type:</strong> <span id="b2bAccountType">-</span></p>
        <p><strong>Status:</strong> <span id="b2bApprovalStatus">-</span></p>
        <p><strong>Contact Email:</strong> <span id="b2bCompanyEmail">-</span></p>
        <button class="btn btn--secondary btn--block" id="b2bLogoutBtn" type="button" style="margin-top:var(--space-4)">Sign Out</button>
      </aside>
    </div>

    <section class="card" style="margin-top:var(--space-8)">
      <h2>Recent Quotes</h2>
      <div class="table-responsive">
        <table class="table" id="b2bRecentQuotesTable">
          <thead>
            <tr>
              <th>Quote #</th>
              <th>Event</th>
              <th>Mode</th>
              <th>Status</th>
              <th>Total</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="6">Loading...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</main>
