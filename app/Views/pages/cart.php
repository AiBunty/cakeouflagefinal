<?php /* Cakeouflage — Cart */ ?>
<main class="section" data-page="cart">
  <div class="container">
    <div class="page-inner-header">
      <div>
        <h1 class="page-inner-title">Your Cart</h1>
        <p class="page-inner-desc">Review your selected cakes before checkout.</p>
      </div>
     <a href="/category" class="btn btn--outline">Continue Shopping</a>
    </div>

    <!-- Empty state -->
    <div class="empty-state" id="cartEmpty" style="display:none">
      <span class="empty-state__icon">🎂</span>
      <h3>Your cart is empty</h3>
      <p>Looks like you haven't added any cakes yet.</p>
      <a href="/category" class="btn btn--primary">Browse Cakes</a>
    </div>

    <div class="cart-layout" id="cartMain">
      <!-- Cart items -->
      <section aria-label="Cart items">
        <div id="cartItemsContainer">
          <!-- Item rows populated by cart.js -->
          <div class="cart-loading">
            <span class="spinner"></span> Loading your cart…
          </div>
        </div>
        <div class="cart-actions-row">
          <button class="btn btn--ghost btn--sm" id="clearCartBtn" type="button">Remove All</button>
        </div>
      </section>

      <!-- Summary sidebar -->
      <aside class="cart-summary card" aria-label="Order summary">
        <h2 class="cart-summary__title">Order Summary</h2>

        <div class="cart-summary__rows">
          <div class="summary-row">
            <span>Subtotal</span>
            <strong id="cartSubtotal">₹0</strong>
          </div>
          <div class="summary-row summary-row--discount">
            <span>Discount</span>
            <strong id="cartDiscount" class="text-success">₹0</strong>
          </div>
          <div class="summary-row summary-row--total">
            <span>Estimated Total</span>
            <strong id="cartGrandTotal">₹0</strong>
          </div>
        </div>
 <!--
        <div class="cart-coupon">
          <div class="coupon-input-row">
            <label for="couponInput" class="sr-only">Coupon code</label>
            <input type="text" id="couponInput" class="coupon-input" placeholder="Coupon code" autocomplete="off">
            <button class="btn btn--secondary btn--sm" id="applyCouponBtn" type="button">Apply</button>
          </div>
          <p id="couponStatus" class="form-feedback" aria-live="polite"></p>
        </div>
-->
       <a href="/checkout" class="btn btn--primary btn--lg btn--block" id="checkoutBtn">Proceed to Checkout →</a>

        <div class="cart-trust">
          <span>🔒 Secure checkout</span>
          <span>🚚 Fresh delivery</span>
          <span>✅ Made to order</span>
        </div>
      </aside>
    </div>
  </div>
</main>
