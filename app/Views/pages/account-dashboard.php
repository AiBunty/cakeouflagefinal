<?php /* Cakeouflage — Customer Dashboard */ ?>
<?php
$promoImage = '/client/assets/images/showcase/birthday.jpg';
?>
<main class="account-dashboard" data-page="customer-dashboard">
  <aside class="account-sidebar" data-auth-section>
    <h2>My Account</h2>
    <nav class="account-sidebar__nav" aria-label="Customer dashboard navigation">
      <button type="button" data-dashboard-tab="home">Dashboard</button>
      <button type="button" data-dashboard-tab="orders">My Orders</button>
      <button type="button" data-dashboard-tab="profile">Profile</button>
      <button type="button" data-dashboard-tab="addresses">Address Book</button>
      <button type="button" data-dashboard-tab="wishlist">Wishlist</button>
      <button type="button" data-dashboard-tab="notifications">Notification Settings</button>
      <button type="button" id="customerLogoutBtn">Logout</button>
    </nav>
  </aside>

  <section class="account-main">
    <header class="account-mobile-head" data-auth-section>
      <button class="account-icon-btn" type="button" aria-label="Open menu">☰</button>
      <a href="/" class="account-mobile-logo" aria-label="Cakeouflage home">Cakeouflage</a>
      <button class="account-icon-btn" type="button" data-dashboard-tab="notifications" aria-label="Notifications">⌂</button>
    </header>

    <section data-dashboard-panel="home" class="account-home" data-auth-section>
      <article class="account-welcome">
        <div>
          <p>Welcome back,</p>
          <h1 id="customerDashboardName">Cakeouflage Customer</h1>
          <span>Cake makes every moment beautiful.</span>
        </div>
        <img id="customerDashboardAvatar" class="account-avatar" src="/client/assets/images/account/avatar-female.svg" alt="Selected customer avatar" />
      </article>

      <section class="account-stats" aria-label="Account order summary">
        <article class="account-stat">
          <span>Total Orders</span>
          <strong id="customerStatOrders">0</strong>
        </article>
        <article class="account-stat account-stat--success">
          <span>Completed Orders</span>
          <strong id="customerStatDelivered">0</strong>
        </article>
        <article class="account-stat account-stat--warning">
          <span>Processing Orders</span>
          <strong id="customerStatPending">0</strong>
        </article>
        <article class="account-stat account-stat--heart">
          <span>Wishlist Items</span>
          <strong id="customerStatWishlist">0</strong>
        </article>
      </section>

      <article class="account-promo account-promo--mobile">
        <img src="<?= htmlspecialchars($promoImage, ENT_QUOTES, 'UTF-8') ?>" alt="Premium celebration cake" loading="lazy" />
        <div>
          <h2>Make every celebration extra special</h2>
          <p>Explore our premium cakes</p>
          <a href="/category">Shop Now</a>
        </div>
      </article>

      <section class="account-dashboard-grid">
        <article class="account-panel account-panel--orders">
          <div class="account-panel__head">
            <h2>Recent Orders</h2>
            <button type="button" data-dashboard-tab="orders">View all orders</button>
          </div>
          <div id="customerOrdersRecent" class="account-orders-list"></div>
        </article>

        <article class="account-panel account-overview">
          <h2>Account Overview</h2>
          <button type="button" data-dashboard-tab="profile"><span>Profile</span><small>Manage your personal details</small></button>
          <button type="button" data-dashboard-tab="addresses"><span>Address Book</span><small>Manage delivery addresses</small></button>
          <button type="button" data-dashboard-tab="wishlist"><span>Wishlist</span><small>Review saved favourites</small></button>
          <button type="button" data-dashboard-tab="notifications"><span>Notification Settings</span><small>Manage your preferences</small></button>
          <button type="button" id="customerLogoutBtnSecondary"><span>Logout</span><small>Sign out from this device</small></button>
        </article>
      </section>

      <article class="account-promo account-promo--desktop">
        <img src="<?= htmlspecialchars($promoImage, ENT_QUOTES, 'UTF-8') ?>" alt="Premium celebration cake" loading="lazy" />
        <div>
          <h2>Celebrate Every Moment</h2>
          <p>Explore our premium collection and make every occasion unforgettable.</p>
          <a href="/category">Shop Now</a>
        </div>
      </article>
    </section>

    <section data-dashboard-panel="orders" class="account-panel" hidden data-auth-section>
      <div class="account-panel__head">
        <h2>My Orders</h2>
        <button type="button" data-dashboard-tab="home">Back to dashboard</button>
      </div>
      <div id="customerOrdersAll" class="account-orders-list"></div>
    </section>

    <section data-dashboard-panel="tracking" class="account-panel" hidden data-auth-section>
      <h2>Order Tracking</h2>
      <form id="customerTrackForm" class="customer-track">
        <div class="customer-track__bar">
          <input id="customerTrackReference" class="customer-track__input" type="text" placeholder="Enter order number or ID" />
          <button class="customer-btn customer-btn--primary" type="submit">Track</button>
        </div>
      </form>
      <div id="customerTrackResult" style="margin-top:10px;"></div>
    </section>

    <section data-dashboard-panel="profile" class="account-panel" hidden data-auth-section>
      <h2>My Profile</h2>
      <div class="account-avatar-picker" aria-label="Avatar selection">
        <button type="button" class="account-avatar-choice" data-avatar-choice="female" aria-pressed="true">
          <img src="/client/assets/images/account/avatar-female.svg" alt="Female avatar" />
          <span>Female</span>
        </button>
        <button type="button" class="account-avatar-choice" data-avatar-choice="male" aria-pressed="false">
          <img src="/client/assets/images/account/avatar-male.svg" alt="Male avatar" />
          <span>Male</span>
        </button>
      </div>
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

    <section data-dashboard-panel="addresses" class="account-panel" hidden data-auth-section>
      <h2>Address Book</h2>
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

    <section data-dashboard-panel="wishlist" class="account-panel" hidden data-auth-section>
      <h2>Wishlist</h2>
      <div id="customerWishlist" class="customer-grid"></div>
    </section>

    <section data-dashboard-panel="notifications" class="account-panel" hidden data-auth-section>
      <h2>Notification Settings</h2>
      <div id="customerNotifications" class="customer-grid"></div>
    </section>

    <section data-dashboard-panel="help" class="account-panel" hidden data-auth-section>
      <h2>Help Center</h2>
      <div class="customer-grid">
        <details class="profile-card"><summary>How can I track my order?</summary><p class="address-card__line">Use the orders tab to view your order status and timeline.</p></details>
        <details class="profile-card"><summary>How do I update delivery address?</summary><p class="address-card__line">Go to Address Book, edit an address, and save instantly.</p></details>
      </div>
    </section>
  </section>

  <aside id="customerOrderDetail" class="customer-detail" aria-label="Order details panel" data-auth-section></aside>

  <nav class="account-bottom-nav" aria-label="Mobile quick navigation" data-auth-section>
    <button type="button" data-dashboard-tab="home">Home</button>
    <a href="/category">Shop</a>
    <button type="button" data-dashboard-tab="orders">Orders</button>
    <button type="button" data-dashboard-tab="wishlist">Wishlist</button>
    <button type="button" data-dashboard-tab="profile">Account</button>
  </nav>
</main>
