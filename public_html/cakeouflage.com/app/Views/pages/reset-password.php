<?php /* Cakeouflage — Reset Password */ ?>
<section class="section section--auth" data-page="reset-password">
  <div class="auth-card-wrap">
    <article class="auth-card">
      <div class="auth-card__brand">
        <span class="auth-card__logomark">🎂</span>
        <span class="auth-card__brandname">Cakeouflage</span>
      </div>
      <h1 class="auth-card__title">Choose a New Password</h1>
      <p class="auth-card__desc">Set a strong new password for your Cakeouflage account.</p>
      <form id="resetPasswordForm" class="form-grid" novalidate>
        <label class="form-control">
          <span class="form-label">Email Address <span class="form-required">*</span></span>
          <input type="email" name="email" value="<?= htmlspecialchars((string)($_GET['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
        </label>
        <label class="form-control">
          <span class="form-label">Reset Token <span class="form-required">*</span></span>
          <input type="text" name="token" value="<?= htmlspecialchars((string)($_GET['token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required placeholder="Paste the token from your email">
        </label>
        <label class="form-control">
          <span class="form-label">New Password <span class="form-required">*</span></span>
          <div class="input-with-action">
            <input type="password" name="password" id="resetPasswordInput" minlength="8" required autocomplete="new-password" placeholder="Min. 8 characters">
            <button type="button" class="input-toggle-pw" aria-label="Toggle password visibility" data-target="resetPasswordInput">👁</button>
          </div>
        </label>
        <button class="btn btn--primary btn--lg btn--block" type="submit">Reset Password</button>
        <p id="resetPasswordStatus" class="form-feedback" aria-live="polite"></p>
      </form>
      <p class="auth-card__footer-link"><a href="/login" class="link">← Back to Sign In</a></p>
    </article>
  </div>
</section>