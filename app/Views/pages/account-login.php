<?php /* Cakeouflage — Customer OTP Login */ ?>
<?php
$brandLogo = (string)($siteConfig['branding']['navbar_logo_url'] ?? '/client/assets/images/mainlogo.svg');
$brandLogoFallback = (string)($siteConfig['branding']['navbar_logo_fallback'] ?? '/client/assets/images/mainlogo.svg');
$heroImage = '/client/assets/images/showcase/wedding.jpg';
?>
<main class="customer-login" data-page="customer-login">
  <div class="customer-login__layout customer-login__layout--luxury">
    <section class="customer-login__visual" aria-label="Celebration visual preview">
      <img class="customer-login__hero-image" src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>" alt="Luxury celebration cake" loading="eager" />
      <div class="customer-login__visual-copy">
        <a href="/" class="customer-login__brand-logo-link customer-login__brand-logo-link--light" aria-label="Cakeouflage home">
          <img src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cakeouflage logo" onerror="this.onerror=null;this.src='<?= htmlspecialchars($brandLogoFallback, ENT_QUOTES, 'UTF-8') ?>';" />
        </a>
        <p class="customer-login__eyebrow">Private cake boutique</p>
        <h1>One OTP. Secure Access.</h1>
        <ul class="customer-login__benefits" aria-label="Account benefits">
          <li>Order tracking</li>
          <li>Saved addresses</li>
          <li>Wishlist access</li>
        </ul>
      </div>
    </section>

    <section class="customer-login__card">
      <div class="customer-login__brand">
        <a href="/" aria-label="Cakeouflage home" class="customer-login__brand-logo-link">
          <img src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cakeouflage logo" onerror="this.onerror=null;this.src='<?= htmlspecialchars($brandLogoFallback, ENT_QUOTES, 'UTF-8') ?>';" />
        </a>
        <p class="customer-login__eyebrow">Customer access</p>
        <h2 class="customer-login__title">Welcome back</h2>
        <p class="customer-login__subtitle">Enter your email and we will send a secure 6-digit OTP.</p>
      </div>

      <form id="customerLoginForm" class="customer-form customer-form--luxury" novalidate>
        <div class="customer-login__field">
          <label for="customerLoginEmail">Email address</label>
          <input id="customerLoginEmail" type="email" name="email" autocomplete="email" required autofocus placeholder="you@email.com" />
        </div>

        <button class="customer-btn customer-btn--primary customer-login__submit" type="button" id="customerSendOtpBtn">
          <span class="customer-btn__label">Send OTP</span>
          <span class="customer-btn__spinner" aria-hidden="true"></span>
        </button>
        <p id="customerOtpCooldown" class="customer-login__meta" aria-live="polite"></p>

        <div id="customerOtpGroup" class="customer-login__otp-panel" hidden>
          <label for="customerOtp">Enter 6 digit OTP</label>
          <div class="customer-login__otp-grid" role="group" aria-label="OTP input boxes">
            <input class="otp-slot" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" aria-label="OTP digit 1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 2" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 3" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 4" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 5" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 6" />
          </div>
          <input id="customerOtp" type="hidden" name="otp" />
        </div>

        <label class="customer-login__remember" for="customerRememberDevice">
          <input id="customerRememberDevice" type="checkbox" name="remember_device" value="1" />
          <span>Remember this device</span>
        </label>
        <p id="customerLoginStatus" class="customer-login__status" aria-live="polite"></p>
      </form>

      <p class="customer-login__terms">By continuing, you agree to secure OTP access for your Cakeouflage account.</p>
    </section>
  </div>
</main>
