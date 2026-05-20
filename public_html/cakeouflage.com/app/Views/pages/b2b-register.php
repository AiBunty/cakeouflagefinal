<?php /* Cakeouflage — B2B Registration */ ?>
<main class="section section--auth" data-page="b2b-register">
  <div class="auth-card-wrap auth-card-wrap--wide">
    <div class="auth-card">
      <div class="auth-card__brand">
        <span class="auth-card__logomark">🧁</span>
        <span class="auth-card__brandname">Cakeouflage B2B</span>
        <p class="auth-card__tagline">Apply for wholesale pricing and dedicated business support</p>
      </div>

      <h1 class="auth-card__title">B2B Registration</h1>
      <p class="auth-card__desc">Submit your business details. Accounts are reviewed before activation.</p>

      <form class="form-grid" id="b2bRegisterForm" novalidate>
        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">Contact Person Name <span class="form-required">*</span></span>
            <input type="text" name="full_name" required placeholder="Priya Sharma">
          </label>
          <label class="form-control">
            <span class="form-label">Contact Phone <span class="form-required">*</span></span>
            <input type="tel" name="phone" required placeholder="+91 98765 43210">
          </label>
        </div>

        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">Login Email <span class="form-required">*</span></span>
            <input type="email" name="email" required placeholder="you@business.com">
          </label>
          <label class="form-control">
            <span class="form-label">Password <span class="form-required">*</span></span>
            <input type="password" name="password" required minlength="8" placeholder="Minimum 8 characters">
          </label>
        </div>

        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">Company Name <span class="form-required">*</span></span>
            <input type="text" name="company_name" required placeholder="ABC Hospitality Pvt Ltd">
          </label>
          <label class="form-control">
            <span class="form-label">Account Type <span class="form-required">*</span></span>
            <select name="account_type" required>
              <option value="">Select account type...</option>
              <option value="corporate_client">Corporate Client</option>
              <option value="business_buyer">Business Buyer</option>
              <option value="reseller">Reseller</option>
              <option value="cake_shop_owner">Cake Shop Owner</option>
            </select>
          </label>
        </div>

        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">Company Phone</span>
            <input type="tel" name="company_phone" placeholder="+91 98765 43210">
          </label>
          <label class="form-control">
            <span class="form-label">Company Email</span>
            <input type="email" name="company_email" placeholder="accounts@business.com">
          </label>
        </div>

        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">GST Number</span>
            <input type="text" name="gst_number" placeholder="27ABCDE1234F1Z5">
          </label>
          <label class="form-control">
            <span class="form-label">Notes</span>
            <input type="text" name="notes" placeholder="Expected monthly volume / delivery area">
          </label>
        </div>

        <button class="btn btn--primary btn--lg btn--block" type="submit">Submit B2B Application</button>
        <p id="b2bRegisterStatus" class="form-feedback" aria-live="polite"></p>
      </form>

      <p class="auth-card__footer-link">Already approved? <a href="/b2b/login" class="link">Sign in here →</a></p>
    </div>
  </div>
</main>
