<?php /* Cakeouflage — Wishlist Page */ ?>
<main class="customer-wishlist" data-page="customer-wishlist">
  <header class="customer-topbar customer-panel">
    <div>
      <h1>My Wishlist</h1>
      <p>Curated favorites ready for quick add-to-cart when your celebration date is near.</p>
    </div>
    <div class="customer-topbar__actions">
      <a class="customer-btn customer-btn--ghost" href="/category">Continue Shopping</a>
      <a class="customer-btn customer-btn--ghost" href="/account/dashboard.php">Dashboard</a>
    </div>
  </header>

  <section id="customerWishlistAuthGate" class="customer-panel" hidden style="margin-top:12px;">
    <h2>Please sign in</h2>
    <p class="address-card__line">Login to save and manage your wishlist across devices.</p>
    <a class="customer-btn customer-btn--primary" href="/account/login.php">Go to Login</a>
  </section>

  <section class="customer-panel" style="margin-top:12px;">
    <div id="customerWishlistGrid" class="customer-grid"></div>
  </section>
</main>
