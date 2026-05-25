<?php /* Cakeouflage — Login */ ?>
<main class="customer-login" data-page="customer-login">
  <div class="customer-login__layout">
    <section class="customer-login__visual" aria-hidden="true">
      <div class="customer-login__hero">
        <div>
          <h2>Crafted celebrations, now in one premium dashboard.</h2>
          <p>Track each order, save favorites, and manage every sweet detail with one secure OTP login.</p>
        </div>
        <img class="customer-login__hero-image" src="/client/assets/images/showcase/wedding.jpg" alt="Decorated cake showcase" loading="lazy" />
      </div>
    </section>

    <section class="customer-login__card">
      <div class="customer-login__brand">
        <a href="/" aria-label="Cakeouflage home">
          <img src="/client/assets/images/mainlogo.svg" alt="Cakeouflage logo" />
        </a>
        <h1 class="customer-login__title">Welcome Back</h1>
        <p class="customer-login__subtitle">Secure OTP access to your orders, wishlist, and celebration timeline.</p>
      </div>

      <form id="customerLoginForm" class="customer-form" novalidate>
        <label for="customerLoginEmail">Email Address</label>
        <input id="customerLoginEmail" type="email" name="email" autocomplete="email" required placeholder="you@email.com" />

        <button class="customer-btn customer-btn--primary" type="button" id="customerSendOtpBtn">Send OTP</button>
        <p id="customerOtpCooldown" class="address-card__line" aria-live="polite"></p>

        <div id="customerOtpGroup" hidden>
          <label for="customerOtp">Enter 6 digit OTP</label>
          <div class="customer-grid" style="grid-template-columns:repeat(6,minmax(0,1fr));gap:6px;">
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
            <input class="otp-slot" type="text" inputmode="numeric" maxlength="1" />
          </div>
          <input id="customerOtp" type="hidden" name="otp" />
        </div>

        <button class="customer-btn customer-btn--primary" id="customerVerifyBtn" type="submit" hidden>Verify & Continue</button>
        <p id="customerLoginStatus" class="address-card__line" aria-live="polite"></p>
      </form>

      <p class="address-card__line" style="margin-top:10px;">No account yet? <a href="/register">Create one now</a></p>
    </section>
  </div>
</main>
