<?php /* Cakeouflage — Wishlist Page */ ?>
<main class="section" data-page="wishlist">
  <div class="container">
    <div class="page-inner-header">
      <div>
        <h1 class="page-inner-title">My Wishlist</h1>
        <p class="page-inner-desc">Your saved favourites — move them to cart when you're ready.</p>
      </div>
      <a href="/category" class="btn btn--outline">Continue Shopping</a>
    </div>

    <!-- Auth gate -->
    <div class="account-gate" id="wishlistAuthGate" style="display:none">
      <div class="account-gate__inner">
        <span class="account-gate__icon">❤️</span>
        <h2>Save Your Favourites</h2>
        <p>Sign in to view and manage your wishlist.</p>
     <a href="/login" class="btn btn--primary btn--lg">Sign In</a>
        <p>New here? <a href="/register" class="link">Create an account →</a></p>
      </div>
    </div>

    <!-- Empty state -->
    <div class="empty-state" id="wishlistEmpty" style="display:none">
      <span class="empty-state__icon">❤️</span>
      <h3>Your wishlist is empty</h3>
      <p>Browse our cakes and tap the heart icon to save your favourites.</p>
      <a href="/category" class="btn btn--primary">Explore Cakes</a>
    </div>

    <!-- Wishlist grid -->
    <div class="product-grid" id="wishlistGrid">
      <!-- Populated by wishlist.js -->
    </div>
  </div>
</main>
