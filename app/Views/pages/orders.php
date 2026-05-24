<?php /* Cakeouflage — Order History */ ?>
<main class="section" data-page="orders">
  <div class="container">
<div id="orderSuccessBox" class="order-success" style="display:none">
  <div class="order-success__card modern-success">
    
    <div class="success-icon">✅</div>
    
    <h2>Order Placed Successfully!</h2>
    
    <p id="successOrderId" class="order-id"></p>
    
    <p class="sub-text">We are preparing your order 🍰</p>
    <p class="brand-text">Thank you for ordering with <strong>Cakeouflage</strong> ❤️</p>

    <div id="utrSubmitBox" style="margin-top:14px; text-align:left; border:1px solid rgba(128,0,31,.14); border-radius:12px; padding:12px; background:#fff8fa;">
      <div style="font-weight:600; color:#80001F; margin-bottom:6px;">Paid via UPI? Submit your UTR</div>
      <p style="margin:0 0 8px; font-size:.84rem; color:#5f4550;">Share your UTR to speed up payment confirmation. Admin can still confirm manually if auto-match is unavailable.</p>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <input id="utrInput" type="text" maxlength="40" placeholder="Enter UTR reference" style="flex:1; min-width:220px; border:1px solid rgba(128,0,31,.2); border-radius:8px; padding:9px 10px;" />
        <button id="utrSubmitBtn" type="button" class="btn btn--outline">Submit UTR</button>
      </div>
      <div id="utrSubmitMessage" style="margin-top:8px; font-size:.8rem; color:#5f4550;"></div>
    </div>

    <div class="success-actions">
      <a href="<?= $baseUrl ?>/category" class="btn btn--primary">Continue Shopping</a>
    </div>

  </div>
</div>
    <div class="page-inner-header">
      <div>
        <h1 class="page-inner-title">My Orders</h1>
        <p class="page-inner-desc">Your complete order history with tracking and invoice access.</p>
      </div>
      <a href="<?= $baseUrl ?>/category" class="btn btn--outline">Order Again</a>
    </div>

    <!-- Auth gate -->
    <div class="account-gate" id="ordersAuthGate" style="display:none">
      <div class="account-gate__inner">
        <span class="account-gate__icon">📦</span>
        <h2>View Your Orders</h2>
        <p>Sign in to track your orders and download invoices.</p>
        <a href="<?= $baseUrl ?>/login" class="btn btn--primary btn--lg">Sign In</a>
      </div>
    </div>

    <!-- Empty state -->
    <div class="empty-state" id="ordersEmpty" style="display:none">
      <span class="empty-state__icon">📦</span>
      <h3>No orders yet</h3>
      <p>Your delicious orders will appear here once you place them.</p>
      <a href="<?= $baseUrl ?>/shop" class="btn btn--primary">Browse Cakes</a>
      
    </div>

    <!-- Orders list -->
    <div id="ordersContainer">
      <!-- Populated by account.js -->
    </div>

    <!-- Order detail drawer -->
    <div class="drawer-overlay" id="orderDrawerOverlay"></div>
    <aside class="drawer" id="orderDetailDrawer" aria-label="Order details" aria-hidden="true">
      <button class="drawer__close" id="orderDrawerClose" aria-label="Close">&times;</button>
      <div class="drawer__body" id="orderDetailBody">
        <!-- Populated by JS -->
      </div>
    </aside>
  </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const params = new URLSearchParams(window.location.search);

  if (params.get("success") === "1") {
    const orderId = params.get("order");

    const box = document.getElementById("orderSuccessBox");
    const text = document.getElementById("successOrderId");
    const utrInput = document.getElementById("utrInput");
    const utrSubmitBtn = document.getElementById("utrSubmitBtn");
    const utrSubmitMessage = document.getElementById("utrSubmitMessage");

    if (box && text) {
      box.style.display = "block";
      text.innerText = "Order ID: " + orderId;

      if (utrSubmitBtn && utrInput && utrSubmitMessage && orderId) {
        utrSubmitBtn.addEventListener("click", async () => {
          const rawUtr = String(utrInput.value || "").trim();
          const normalized = rawUtr.replace(/[^a-zA-Z0-9]/g, "").toUpperCase();
          if (normalized.length < 6) {
            utrSubmitMessage.style.color = "#991b1b";
            utrSubmitMessage.innerText = "Enter a valid UTR (minimum 6 characters).";
            return;
          }

          utrSubmitBtn.setAttribute("disabled", "disabled");
          utrSubmitMessage.style.color = "#5f4550";
          utrSubmitMessage.innerText = "Submitting UTR...";

          try {
            const response = await fetch("<?= $baseUrl ?>/api/orders/" + encodeURIComponent(orderId) + "/utr", {
              method: "POST",
              credentials: "include",
              headers: {
                "Content-Type": "application/json"
              },
              body: JSON.stringify({ utr: normalized })
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
              throw new Error(payload.message || "Failed to submit UTR");
            }

            utrSubmitMessage.style.color = "#166534";
            utrSubmitMessage.innerText = payload.message || "UTR submitted successfully.";
          } catch (error) {
            utrSubmitMessage.style.color = "#991b1b";
            utrSubmitMessage.innerText = error && error.message ? error.message : "Unable to submit UTR right now.";
          } finally {
            utrSubmitBtn.removeAttribute("disabled");
          }
        });
      }

        window.scrollTo({ top: 0, behavior: "smooth" }); // 🔥 add this
    }
  }
});
</script>