<?php /* Cakeouflage — B2B Login */ ?>
<main class="section section--auth" data-page="b2b-login">
  <div class="auth-card-wrap">
    <div class="auth-card">
      <div class="auth-card__brand">
        <span class="auth-card__logomark">🏢</span>
        <span class="auth-card__brandname">Cakeouflage B2B</span>
        <p class="auth-card__tagline">Bulk ordering for businesses, resellers, and events</p>
      </div>

      <h1 class="auth-card__title">B2B Sign In</h1>
      <p class="auth-card__desc">Use your approved business account credentials to access pricing, quotes, and order history.</p>

      <form class="form-grid" id="b2bLoginForm" novalidate>
        <label class="form-control">
          <span class="form-label">Business Email <span class="form-required">*</span></span>
          <input type="email" name="email" required autocomplete="email" placeholder="procurement@company.com">
        </label>
        <label class="form-control">
          <span class="form-label">Password <span class="form-required">*</span></span>
          <div class="input-with-action">
            <input type="password" name="password" id="b2bLoginPassword" required autocomplete="current-password" placeholder="Your password">
            <button type="button" class="input-toggle-pw" aria-label="Toggle password visibility" data-target="b2bLoginPassword">👁</button>
          </div>
        </label>

        <button class="btn btn--primary btn--lg btn--block" type="submit">Sign In</button>
        <p id="b2bLoginStatus" class="form-feedback" aria-live="polite"></p>
      </form>

      <p class="auth-card__footer-link">Need access? <a href="/b2b/register" class="link">Apply for B2B account →</a></p>
    </div>
  </div>
</main>
