<?php /* Cakeouflage — Forgot Password */ ?>
<section class="section section--auth" data-page="forgot-password">
  <div class="auth-card-wrap">
    <article class="auth-card">
      <div class="auth-card__brand">
        <span class="auth-card__logomark">🎂</span>
        <span class="auth-card__brandname">Cakeouflage</span>
      </div>
      <h1 class="auth-card__title">Forgot Password?</h1>
      <p class="auth-card__desc">No worries. Enter your account email and we’ll send you a reset link.</p>
      <form id="forgotPasswordForm" class="form-grid" novalidate>
        <label class="form-control">
          <span class="form-label">Email Address <span class="form-required">*</span></span>
          <input type="email" name="email" required autocomplete="email" placeholder="you@email.com">
        </label>
        <button class="btn btn--primary btn--lg btn--block" type="submit">Send Reset Link</button>
        <p id="forgotPasswordStatus" class="form-feedback" aria-live="polite"></p>
      </form>
      <p class="auth-card__footer-link">Remembered it? <a href="/account/login.php" class="link">Back to Sign In →</a></p>
    </article>
  </div>
</section>