<?php /* Cakeouflage — Customer Dashboard */ ?>
<?php
$isCustomerAuthenticated = !empty($isCustomerAuthenticated);
$brandLogo = (string)($siteConfig['branding']['navbar_logo_url'] ?? '/client/assets/images/mainlogo.svg');
$brandLogoFallback = (string)($siteConfig['branding']['navbar_logo_fallback'] ?? '/client/assets/images/mainlogo.svg');
$heroImage = '/client/assets/images/showcase/birthday.jpg';
?>
<main class="customer-shell" data-page="customer-dashboard">
  <aside class="customer-sidebar customer-panel" data-auth-section <?= $isCustomerAuthenticated ? '' : 'hidden' ?>>
    <div class="customer-sidebar__avatar" id="customerDashboardAvatar">C</div>
    <p class="customer-sidebar__name" id="customerDashboardName">Cakeouflage Customer</p>
    <nav class="customer-sidebar__nav" aria-label="Customer dashboard navigation">
      <button type="button" data-dashboard-tab="home">Overview</button>
      <button type="button" data-dashboard-tab="orders">Orders</button>
      <button type="button" data-dashboard-tab="tracking">Tracking</button>
      <button type="button" data-dashboard-tab="profile">Profile</button>
      <button type="button" data-dashboard-tab="addresses">Addresses</button>
      <button type="button" data-dashboard-tab="wishlist">Wishlist</button>
      <button type="button" data-dashboard-tab="notifications">Notifications</button>
      <button type="button" data-dashboard-tab="help">Help Center</button>
      <button type="button" id="customerLogoutBtn">Sign Out</button>
    </nav>
  </aside>

  <section>
    <header class="customer-topbar customer-panel" data-auth-section <?= $isCustomerAuthenticated ? '' : 'hidden' ?>>
      <div>
        <h1>Customer Dashboard</h1>
        <p>Personal profile, delivery tracking, wishlist, and support in one premium workspace.</p>
      </div>
      <div class="customer-topbar__actions">
        <a class="customer-btn customer-btn--ghost" href="/category">Shop Cakes</a>
      </div>
    </header>

    <div class="customer-mobile-tabs customer-panel" style="margin-top:12px;" data-auth-section <?= $isCustomerAuthenticated ? '' : 'hidden' ?>>
      <button type="button" data-dashboard-tab="home">Home</button>
      <button type="button" data-dashboard-tab="orders">Orders</button>
      <button type="button" data-dashboard-tab="tracking">Track</button>
      <button type="button" data-dashboard-tab="profile">Profile</button>
      <button type="button" data-dashboard-tab="wishlist">Wishlist</button>
      <button type="button" data-dashboard-tab="help">Help</button>
    </div>

    <section id="customerDashboardAuthGate" class="customer-panel" style="margin-top:12px;" <?= $isCustomerAuthenticated ? 'hidden' : '' ?>>
      <div class="customer-login__layout customer-login__layout--luxury" style="margin-top:8px;">
        <section class="customer-login__visual" aria-label="Celebration visual preview">
          <div class="customer-login__hero">
            <div class="customer-login__hero-copy">
              <a href="/" class="customer-login__brand-logo-link" aria-label="Cakeouflage home">
                <img src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cakeouflage logo" onerror="this.onerror=null;this.src='<?= htmlspecialchars($brandLogoFallback, ENT_QUOTES, 'UTF-8') ?>';" />
              </a>
              <p class="customer-login__hero-tagline">Celebrate Every Moment</p>
              <h2>Sign in to your account</h2>
              <p>Access orders, addresses, profile, and wishlist from one secure dashboard.</p>
            </div>
            <div class="customer-login__hero-visual">
              <img class="customer-login__hero-image" src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>" alt="Luxury pink birthday cake" loading="lazy" />
            </div>
          </div>
        </section>

        <section class="customer-login__card">
          <div class="customer-login__brand">
            <p class="customer-login__hero-tagline">Secure OTP Login</p>
            <h2 class="customer-login__title">Continue with email OTP</h2>
            <p class="customer-login__subtitle">Enter your email and verify OTP to unlock your dashboard.</p>
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
        </section>
      </div>
    </section>

    <section data-dashboard-panel="home" class="customer-grid" style="margin-top:12px;" data-auth-section <?= $isCustomerAuthenticated ? '' : 'hidden' ?>>
      <div class="customer-grid customer-grid--stats">
        <article class="customer-stat">
          <p class="customer-stat__label">Total Orders</p>
          <p class="customer-stat__value" id="customerStatOrders">0</p>
        </article>
        <article class="customer-stat customer-stat--accent-pending">
          <p class="customer-stat__label">Pending</p>
          <p class="customer-stat__value" id="customerStatPending">0</p>
        </article>
        <article class="customer-stat customer-stat--accent-delivered">
          <p class="customer-stat__label">Delivered</p>
          <p class="customer-stat__value" id="customerStatDelivered">0</p>
        </article>
        <article class="customer-stat customer-stat--accent-cancelled">
          <p class="customer-stat__label">Cancelled</p>
          <p class="customer-stat__value" id="customerStatCancelled">0</p>
        </article>
        <article class="customer-stat customer-stat--accent-refund">
          <p class="customer-stat__label">Refunds</p>
          <p class="customer-stat__value" id="customerStatRefunds">0</p>
        </article>
        <article class="customer-stat">
          <p class="customer-stat__label">Total Spend</p>
          <p class="customer-stat__value" id="customerStatSpent">Rs 0.00</p>
        </article>
        <article class="customer-stat">
          <p class="customer-stat__label">Wishlist Items</p>
          <p class="customer-stat__value" id="customerStatWishlist">0</p>
        </article>
        <article class="customer-stat">
          <p class="customer-stat__label">Saved Addresses</p>
          <p class="customer-stat__value" id="customerStatAddresses">0</p>
        </article>
      </div>

      <article class="customer-panel">
        <h2>Recent Orders</h2>
        <div id="customerOrdersRecent"></div>
      </article>
    </section>

    <section data-dashboard-panel="orders" class="customer-panel" hidden style="margin-top:12px;" data-auth-section>
      <h2>All Orders</h2>
      <div id="customerOrdersAll"></div>
    </section>

    <section data-dashboard-panel="tracking" class="customer-panel" hidden style="margin-top:12px;" data-auth-section>
      <h2>Order Tracking</h2>
      <form id="customerTrackForm" class="customer-track">
        <div class="customer-track__bar">
          <input id="customerTrackReference" class="customer-track__input" type="text" placeholder="Enter order number or ID" />
          <button class="customer-btn customer-btn--primary" type="submit">Track</button>
        </div>
      </form>
      <div id="customerTrackResult" style="margin-top:10px;"></div>
    </section>

    <section data-dashboard-panel="profile" class="customer-panel" hidden style="margin-top:12px;" data-auth-section>
      <h2>My Profile</h2>
      <form id="customerProfileForm" class="customer-grid customer-grid--two">
        <label>
          Full Name
          <input id="customerProfileName" name="full_name" type="text" required />
        </label>
        <label>
          Phone
          <input id="customerProfilePhone" name="phone" type="tel" required />
        </label>
        <label>
          Email
          <input id="customerProfileEmail" type="email" disabled />
        </label>
        <label>
          Date of Birth
          <input id="customerProfileDob" name="dob" type="date" />
        </label>
        <label>
          Anniversary Date
          <input id="customerProfileDoa" name="doa" type="date" />
        </label>
        <div>
          <button class="customer-btn customer-btn--primary" type="submit">Save Profile</button>
        </div>
      </form>
      <p id="customerProfileStatus" class="address-card__line" aria-live="polite"></p>
    </section>

    <section data-dashboard-panel="addresses" class="customer-panel" hidden style="margin-top:12px;" data-auth-section>
      <h2>Saved Addresses</h2>
      <form id="customerAddressForm" class="customer-grid customer-grid--two">
        <input id="customerAddressId" type="hidden" name="address_id" />
        <label>Label<input id="addressLabel" name="label" type="text" placeholder="Home / Office" /></label>
        <label>Recipient Name<input id="addressRecipientName" name="recipient_name" type="text" required /></label>
        <label>Phone<input id="addressPhone" name="phone" type="tel" required /></label>
        <label>Line 1<input id="addressLine1" name="line1" type="text" required /></label>
        <label>Line 2<input id="addressLine2" name="line2" type="text" /></label>
        <label>Landmark<input id="addressLandmark" name="landmark" type="text" /></label>
        <label>City<input id="addressCity" name="city" type="text" required /></label>
        <label>State<input id="addressState" name="state" type="text" required /></label>
        <label>Postal Code<input id="addressPostalCode" name="postal_code" type="text" required /></label>
        <label style="display:flex;align-items:center;gap:8px;">Set default <input id="addressIsDefault" name="is_default" type="checkbox" value="1" /></label>
        <div style="display:flex;gap:8px;">
          <button id="customerAddressSubmit" class="customer-btn customer-btn--primary" type="submit">Save Address</button>
          <button id="customerAddressReset" class="customer-btn customer-btn--ghost" type="button">Reset</button>
        </div>
      </form>
      <div id="customerAddresses" class="customer-grid" style="margin-top:12px;"></div>
    </section>

    <section data-dashboard-panel="wishlist" class="customer-panel" hidden style="margin-top:12px;" data-auth-section>
      <h2>Wishlist</h2>
      <div id="customerWishlist" class="customer-grid"></div>
    </section>

    <section data-dashboard-panel="notifications" class="customer-panel" hidden style="margin-top:12px;" data-auth-section>
      <h2>Notifications</h2>
      <div id="customerNotifications" class="customer-grid"></div>
    </section>

    <section data-dashboard-panel="help" class="customer-panel" hidden style="margin-top:12px;" data-auth-section>
      <h2>Help Center</h2>
      <div class="customer-grid">
        <details class="profile-card"><summary>How can I track my order?</summary><p class="address-card__line">Use the Tracking tab and enter your order number or ID to view the live timeline.</p></details>
        <details class="profile-card"><summary>How do I update delivery address?</summary><p class="address-card__line">Go to Addresses tab, edit any address card, and save instantly.</p></details>
        <details class="profile-card"><summary>Where can I download invoice?</summary><p class="address-card__line">Invoices are available for paid orders from the order details panel.</p></details>
      </div>
      <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
        <a class="customer-btn customer-btn--ghost" href="/contact">Raise a Ticket</a>
        <a class="customer-btn customer-btn--ghost" href="/faq">View FAQ</a>
      </div>
    </section>
  </section>

  <aside id="customerOrderDetail" class="customer-detail" aria-label="Order details panel" data-auth-section <?= $isCustomerAuthenticated ? '' : 'hidden' ?>></aside>

  <nav class="mobile-nav" aria-label="Mobile quick navigation" data-auth-section <?= $isCustomerAuthenticated ? '' : 'hidden' ?>>
    <button type="button" data-dashboard-tab="home">Home</button>
    <button type="button" data-dashboard-tab="orders">Orders</button>
    <button type="button" data-dashboard-tab="tracking">Track</button>
    <button type="button" data-dashboard-tab="wishlist">Wishlist</button>
  </nav>
</main>