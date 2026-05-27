<?php /* Cakeouflage — Order History */ ?>
<main class="customer-orders" data-page="customer-orders">
  <section id="orderSuccessBox" class="customer-panel" hidden>
    <h2 style="margin:0 0 8px;color:#7b102e;">Order placed successfully</h2>
    <p id="successOrderId" class="address-card__line"></p>
    <p class="address-card__line">Thank you for choosing Cakeouflage. We are preparing your celebration order.</p>
    <div id="utrSubmitBox" class="profile-card" style="margin-top:10px;">
      <h3>Paid via UPI? Submit your UTR</h3>
      <p class="address-card__line">This helps our team confirm your payment faster.</p>
      <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;">
        <input id="utrInput" type="text" maxlength="40" placeholder="Enter UTR reference" />
        <button id="utrSubmitBtn" class="customer-btn customer-btn--primary" type="button">Submit UTR</button>
      </div>
      <p id="utrSubmitMessage" class="address-card__line" aria-live="polite"></p>
    </div>
    <a class="customer-btn customer-btn--ghost" href="/category" style="margin-top:8px;display:inline-block;">Continue Shopping</a>
  </section>

  <header class="customer-topbar customer-panel" style="margin-top:12px;">
    <div>
      <h1>My Orders</h1>
      <p>Complete order timeline, invoice access, and live status updates synced with admin actions.</p>
    </div>
    <div class="customer-topbar__actions">
      <a class="customer-btn customer-btn--ghost" href="/account/dashboard.php">Dashboard</a>
    </div>
  </header>

  <section id="customerOrdersAuthGate" class="customer-panel" hidden style="margin-top:12px;">
    <h2>Please sign in</h2>
    <p class="address-card__line">Sign in to view your full order history and download invoices.</p>
    <a class="customer-btn customer-btn--primary" href="/account/login.php">Go to Login</a>
  </section>

  <section class="customer-panel" style="margin-top:12px;">
    <div class="customer-track__bar">
      <input id="customerOrdersSearch" class="customer-search" type="search" placeholder="Search by order number or cake name" />
      <select id="customerOrdersStatusFilter">
        <option value="all">All Statuses</option>
        <option value="pending_payment">Pending Payment</option>
        <option value="payment_under_review">Payment Review</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="preparing">Preparing</option>
        <option value="out_for_delivery">Out for Delivery</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>
  </section>

  <section class="customer-panel" style="margin-top:12px;">
    <div id="customerOrdersList"></div>
  </section>

  <aside id="customerOrdersDetail" class="customer-detail" aria-label="Order detail panel"></aside>
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
      box.hidden = false;
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

      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  }
});
</script>