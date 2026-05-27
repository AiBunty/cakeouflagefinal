<?php /* Cakeouflage — Login */ ?>
<?php
$brandLogo = (string)($siteConfig['branding']['navbar_logo_url'] ?? '/client/assets/images/mainlogo.svg');
$brandLogoFallback = (string)($siteConfig['branding']['navbar_logo_fallback'] ?? '/client/assets/images/mainlogo.svg');
$heroImage = '/client/assets/images/showcase/birthday.jpg';
?>
<main class="customer-login" data-page="customer-login">
  <section class="customer-login__headline">
    <h1>CAKEOUFLAGE - USER LOGIN</h1>
    <p>Modern <span aria-hidden="true">•</span> Elegant <span aria-hidden="true">•</span> Secure <span aria-hidden="true">•</span> Responsive</p>
  </section>

  <div class="customer-login__layout customer-login__layout--luxury">
    <section class="customer-login__visual" aria-label="Celebration visual preview">
      <div class="customer-login__view-tag">Desktop View</div>
      <div class="customer-login__hero">
        <div class="customer-login__hero-copy">
          <a href="/" class="customer-login__brand-logo-link" aria-label="Cakeouflage home">
            <img src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cakeouflage logo" onerror="this.onerror=null;this.src='<?= htmlspecialchars($brandLogoFallback, ENT_QUOTES, 'UTF-8') ?>';" />
          </a>
          <p class="customer-login__hero-tagline">Celebrate Every Moment</p>
          <h2>Welcome Back!</h2>
          <p>Login to access your orders, manage wishlist and track your celebrations.</p>

          <ul class="customer-login__benefits" aria-label="Account benefits">
            <li><strong>Secure OTP Login</strong><span>Your account is protected with one-time password.</span></li>
            <li><strong>Track Your Orders</strong><span>Real-time updates on your sweet moments.</span></li>
            <li><strong>Save Your Favorites</strong><span>Wishlist your favorite cakes and designs.</span></li>
          </ul>
        </div>
        <div class="customer-login__hero-visual">
          <img class="customer-login__hero-image" src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>" alt="Luxury pink birthday cake" loading="eager" />
        </div>
      </div>
    </section>

    <section class="customer-login__card">
      <div class="customer-login__mobile-view-tag">Mobile View</div>
      <div class="customer-login__brand">
        <a href="/" aria-label="Cakeouflage home" class="customer-login__brand-logo-link">
          <img src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cakeouflage logo" onerror="this.onerror=null;this.src='<?= htmlspecialchars($brandLogoFallback, ENT_QUOTES, 'UTF-8') ?>';" />
        </a>
        <p class="customer-login__hero-tagline">Celebrate Every Moment</p>
        <h2 class="customer-login__title">Login to Your Account</h2>
        <p class="customer-login__subtitle">Enter your email and we'll send you a secure OTP.</p>
      </div>

      <form id="customerLoginForm" class="customer-form customer-form--luxury" novalidate>
        <label for="customerLoginEmail">Email Address</label>
        <input id="customerLoginEmail" type="email" name="email" autocomplete="email" required placeholder="you@email.com" />

        <button class="customer-btn customer-btn--primary" type="button" id="customerSendOtpBtn">Send OTP</button>
        <p id="customerOtpCooldown" class="address-card__line" aria-live="polite"></p>

        <div id="customerOtpGroup" hidden>
          <label for="customerOtp">Enter 6 digit OTP</label>
          <div class="customer-login__otp-grid" role="group" aria-label="OTP input boxes">
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
          </div>
          <input id="customerOtp" type="hidden" name="otp" />
        </div>

        <label class="customer-login__remember" for="customerRememberDevice">
          <input id="customerRememberDevice" type="checkbox" name="remember_device" value="1" />
          <span>Remember this device</span>
        </label>

        <button class="customer-btn customer-btn--primary" id="customerVerifyBtn" type="submit" hidden>Verify &amp; Continue</button>
        <p id="customerLoginStatus" class="address-card__line" aria-live="polite"></p>
      </form>

      <p class="address-card__line customer-login__register-link">Don't have an account? <a href="/register">Create one</a></p>
    </section>
  </div>
</main>
