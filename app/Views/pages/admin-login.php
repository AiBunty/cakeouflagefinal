<?php /* Cakeouflage Admin — Login */ ?>
<article class="admin-auth__card card" data-page="admin-login">

  <div class="admin-auth__brand">
    <div class="admin-auth__logomark" aria-hidden="true">🍰</div>
    <strong class="admin-auth__name">Cakeouflage</strong>
    <span class="admin-auth__role-badge">Admin Panel</span>
  </div>

  <h1 class="admin-auth__title">Welcome Back</h1>
  <p class="admin-auth__desc">Sign in to manage your bakery — products, orders, customers &amp; more.</p>

  <form id="adminLoginForm" class="form-grid" novalidate>
    <label class="form-control">
      <span class="form-label">Email Address <span class="form-required">*</span></span>
      <input type="email" name="email" placeholder="admin@cakeouflage.com" required autocomplete="email" />
    </label>
    <label class="form-control">
      <span class="form-label">Password <span class="form-required">*</span></span>
      <div class="input-with-action">
        <input type="password" name="password" id="adminLoginPassword" placeholder="Enter your password" required autocomplete="current-password" />
        <button type="button" class="input-toggle-pw" aria-label="Toggle password visibility" data-target="adminLoginPassword">👁</button>
      </div>
    </label>
    <button class="btn btn--primary btn--lg btn--block" type="submit">Sign In to Admin</button>
    <p id="adminLoginStatus" class="form-feedback" aria-live="polite"></p>
  </form>

  <p class="admin-auth__footer">Bakery management system &mdash; Cakeouflage &copy; <?= date('Y') ?></p>

</article>
