<?php /* Cakeouflage — Checkout */ ?>
<main class="section" data-page="checkout">
  <div class="container">
    <div class="page-inner-header">
      <div>
        <h1 class="page-inner-title">Checkout</h1>
        <p class="page-inner-desc">You're almost there! Choose delivery or pickup, confirm your slot, and place your order.</p>
      </div>
      <a href="/cart" class="btn btn--outline">Back to Cart</a>
    </div>

    <div class="checkout-layout">

      <!-- Left: Form -->
      <form class="checkout-form" id="checkoutForm" novalidate>

        <!-- Contact details -->
        <div class="checkout-section card">
          <h2 class="checkout-section__title">Contact Details</h2>
          <div class="form-row-2">
            <label class="form-control">
              <span class="form-label">Full Name <span class="form-required">*</span></span>
              <input id="customerName" name="customer_name" required autocomplete="name" placeholder="Priya Sharma">
            </label>
            <label class="form-control">
              <span class="form-label">Phone <span class="form-required">*</span></span>
             <input id="customerPhone" name="customer_phone" type="tel" autocomplete="tel" placeholder="+91 98765 43210">
            </label>







          </div>
          <label class="form-control">
            <span class="form-label">Email</span>
        <input id="customerEmail" name="customer_email" type="email" required autocomplete="email" placeholder="you@email.com">
          </label>
          <!-- 🔥 OTP UI START -->
<div style="margin-top:10px;">
  <button 
  type="button" 
  id="sendOtpBtn" 
  class="btn btn--outline"
  style="color:black; border:1px solid black;"
>
  Send OTP
</button>
</div>

<div id="otpSection" style="display:none; margin-top:10px;">
 <input 
  type="text" 
  id="otpInput" 
  placeholder="Enter OTP"
  maxlength="6"
  style="padding:8px; border:1px solid #000; color:#000; background:#fff;"
>

<button 
  type="button" 
  id="verifyOtpBtn" 
  class="btn btn--primary"
  style="background:#000; color:#fff;"
>
  Verify OTP
</button>
</div>

<p id="otpStatus" style="margin-top:6px; font-size:14px;"></p>
<!-- 🔥 OTP UI END -->

        </div>

        <!-- Fulfilment mode -->
        <div class="checkout-section card">
          <h2 class="checkout-section__title">Fulfilment Method</h2>
          <div class="fulfilment-options" role="group" aria-label="Select fulfilment method">
            <label class="fulfilment-option">
              <input type="radio" name="fulfilment_mode" value="delivery" checked>

              <span class="fulfilment-option__label">
                <span class="fulfilment-option__icon">🚚</span>
                <span>
                  <strong>Home Delivery</strong>
                  <small>Within 30 km of Nashik</small>
                </span>
              </span>
            </label>
            <label class="fulfilment-option">
              <input type="radio" name="fulfilment_mode" value="pickup">
              <span class="fulfilment-option__label">
                <span class="fulfilment-option__icon">🏪</span>
                <span>
                  <strong>Store Pickup</strong>
                  <small>Collect from our Nashik bakery</small>
                </span>
              </span>
            </label>
            <label class="fulfilment-option">
              <input type="radio" name="fulfilment_mode" value="custom_delivery">
              <span class="fulfilment-option__label">
                <span class="fulfilment-option__icon">🎂</span>
                <span>
                  <strong>Custom Cake Delivery</strong>
                  <small>Approval-based, beyond standard zones</small>
                </span>
              </span>
            </label>
          </div>
          <div class="form-control" id="postalCodeWrap" style="margin-top:var(--space-4)">
            <label class="form-label" for="postalCode">Delivery Pincode</label>
            <input id="postalCode" name="postal_code" placeholder="e.g. 422001" autocomplete="postal-code">
            <span class="form-hint" id="serviceabilityHint"></span>
          </div>
        </div>

        <!-- Delivery address -->
        <div class="checkout-section card" id="addressSection">
          <h2 class="checkout-section__title">Delivery Address</h2>
          <textarea name="delivery_address" id="deliveryAddress" rows="3" placeholder="House/flat no., street, area, Nashik"></textarea>
        </div>

        <!-- Slot selection -->
        <div class="checkout-section card">
          <h2 class="checkout-section__title">Date &amp; Time Slot</h2>
          <div class="form-row-2">
            <label class="form-control">
              <span class="form-label">Preferred Date <span class="form-required">*</span></span>
              <input type="date" name="delivery_date" id="deliveryDate" required>
            </label>
            <label class="form-control">
              <span class="form-label">Preferred Slot <span class="form-required">*</span></span>
              <select name="slot_id" id="deliverySlot" required>
                <option value="">Select date first…</option>
              </select>
            </label>
          </div>
        </div>

        <!-- Customisation note -->
        <div class="checkout-section card">
          <h2 class="checkout-section__title">Cake Message / Notes</h2>
          <label class="form-control">
            <span class="form-label">Message on Cake or Order Notes (optional)</span>
            <textarea name="customisation_note" rows="2" placeholder="e.g. \"Happy Birthday Priya!\" or any special requests"></textarea>
          </label>
        </div>

        <!-- Payment method -->
        <div class="checkout-section card">
          <h2 class="checkout-section__title">Payment Method</h2>
          <div class="upi-section" style="margin-top:16px">
  <p><strong>Scan & Pay (UPI)</strong></p>

<img 
  id="upiQR"
  src="https://via.placeholder.com/200"
  width="200"
/>
<p id="upiAmountText" style="margin-top:10px; font-weight:600;"></p>
<a id="upiDeepLink" href="#" class="btn btn--primary btn--lg btn--block" style="margin-top:12px; display:none; align-items:center; justify-content:center; gap:8px; background:#80001f; color:#fff; border:2px solid #80001f; box-shadow:0 10px 24px rgba(128,0,31,.22);">
  <span aria-hidden="true">📱</span>
  <span>Pay Now with UPI App</span>
</a>
<p id="upiButtonHint" class="form-hint" style="margin-top:8px; display:none;">Opens Google Pay, PhonePe, Paytm, BHIM or any UPI app installed on your phone.</p>
  <p class="form-hint">After payment, upload screenshot below</p>

  <input type="file" name="payment_proof" id="paymentProof" accept="image/*">
</div>
          <div class="payment-options" role="group">
            <label class="payment-option">
              <input type="radio" name="payment_method" id="paymentMethod" value="upi_manual" checked>
              <span>📱 UPI (Google Pay / PhonePe / Paytm)</span>
            </label>
           
          </div>
        </div>

        <div class="checkout-section card">
          <h2 class="checkout-section__title">Coupon</h2>
          <div class="coupon-input-row">
            <label for="checkoutCouponInput" class="sr-only">Coupon code</label>
            <input type="text" id="checkoutCouponInput" class="coupon-input" placeholder="Coupon code" autocomplete="off">
            <button class="btn btn--secondary btn--sm" id="applyCheckoutCouponBtn" type="button">Apply</button>
            <button class="btn btn--outline btn--sm" id="copyCheckoutCouponBtn" type="button" data-copy-code="" style="display:none;">Copy</button>
          </div>
          <p id="checkoutCouponStatus" class="form-feedback" aria-live="polite"></p>
        </div>

        <button class="btn btn--secondary btn--block" type="button" id="previewCheckoutBtn">Preview Total</button>
        <button class="btn btn--primary btn--lg btn--block" type="submit" id="placeOrderBtn">Place Order</button>
        <p id="checkoutStatus" class="form-feedback" aria-live="polite"></p>
      </form>

      <!-- Right: Summary -->
      <aside class="cart-summary card checkout-summary" aria-label="Order preview">
        <h2 class="cart-summary__title">Order Preview</h2>
        <div class="cart-summary__rows">
          <div class="summary-row"><span>Subtotal</span><strong id="checkoutSubtotal">₹0</strong></div>
          <div class="summary-row"><span>Discount</span><strong id="checkoutDiscount" class="text-success">₹0</strong></div>
          <div class="summary-row"><span>Delivery Fee</span><strong id="checkoutDeliveryFee">₹0</strong></div>
          <div class="summary-row summary-row--total"><span>Grand Total</span><strong id="checkoutGrandTotal">₹0</strong></div>
        </div>
        <div id="checkoutItemsList" class="checkout-items-mini">
          <!-- Cart items mini list populated by JS -->
        </div>
        <div class="cart-trust">
          <span>🔒 Secure checkout</span>
          <span>🚚 Fresh delivery</span>
          <span>✅ Freshly baked</span>
        </div>
      </aside>

    </div>
  </div>
</main>

<script>
  window.otpVerified = false;
document.addEventListener("DOMContentLoaded", () => {

  const form = document.getElementById("checkoutForm");
  const upiSection = document.querySelector('.upi-section');

  const syncPaymentUi = () => {
    const method = form?.querySelector('input[name="payment_method"]:checked')?.value;
    if (!upiSection) {
      return;
    }
    upiSection.style.display = method === 'upi_manual' ? 'block' : 'none';
  };

  form?.querySelectorAll('input[name="payment_method"]').forEach((input) => {
    input.addEventListener('change', syncPaymentUi);
  });
  syncPaymentUi();

  if (!form) {
    console.log("Form not found ❌");
    return;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
  if (!window.otpVerified) {
    alert("Please verify OTP first");
    return;
  }
    const formData = new FormData(form);

    const fulfilmentMode = form.querySelector('input[name="fulfilment_mode"]:checked')?.value;

    if (fulfilmentMode === "pickup") {
      formData.delete("postal_code");
    }

    const paymentMethod = form.querySelector('input[name="payment_method"]:checked')?.value;

    const proofFile = document.getElementById("paymentProof")?.files[0];

    if (paymentMethod === "upi_manual" && !proofFile) {
      alert("Please upload payment screenshot");
      return;
    }

    formData.set("customer_name", document.getElementById("customerName").value.trim());
    formData.set("customer_phone", document.getElementById("customerPhone").value.trim());
    formData.set("customer_email", document.getElementById("customerEmail").value.trim());
    formData.set("delivery_address", document.getElementById("deliveryAddress").value.trim());
    formData.set("slot_id", document.getElementById("deliverySlot").value);
    formData.set("payment_method", paymentMethod);

    try {
      const res = await fetch(window.BASE_URL + "/api/orders/place", {
        method: "POST",
        headers: {
          "X-CSRF-Token": window.__csrf
        },
        body: formData,
        credentials: "include"
      });

      const text = await res.text();
      console.log("RAW RESPONSE:", text);

      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        alert("Backend Error:\n" + text);
        return;
      }

      if (data.success) {
        window.location.href = window.BASE_URL + "/orders?success=1&order=" + data.data.order_number;
      } else {
        alert(data.message || "Order failed");
      }

    } catch (err) {
      console.error("ERROR:", err);
      alert("Something went wrong");
    }

  });

});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {

  document.getElementById("deliveryDate").addEventListener("change", async function () {
    const date = this.value;

    const slotSelect = document.getElementById("deliverySlot");
    slotSelect.innerHTML = '<option>Loading...</option>';

    try {
      const res = await fetch(window.BASE_URL + "/api/fulfilment/slots?date=" + date);
      const data = await res.json();

      console.log("SLOTS API RESPONSE:", data);

      slotSelect.innerHTML = '<option value="">Select Slot</option>';

      if (data.success && data.data.items.length > 0) {
        data.data.items.forEach(slot => {
          const option = document.createElement("option");
          option.value = slot.id;
          option.textContent = slot.slot_label;
          slotSelect.appendChild(option);
        });
      } else {
        slotSelect.innerHTML = '<option>No slots available</option>';
      }

    } catch (err) {
      console.error(err);
      slotSelect.innerHTML = '<option>Error loading slots</option>';
    }
  });

});
</script>


<script>
async function loadCheckoutSummary() {
  try {
    const res = await fetch(window.BASE_URL + "/api/cart?auto_public=1",
      {
       credentials: "include"
      }
    );
    const data = await res.json();

    console.log("CART DATA:", data);

    if (!data.success) return;

    const cart = data.data;

    const couponStatus = document.getElementById("checkoutCouponStatus");
    const couponInput = document.getElementById("checkoutCouponInput");
    const copyCouponBtn = document.getElementById("copyCheckoutCouponBtn");
    if (couponStatus) {
      if (cart.coupon && cart.coupon.code) {
        const isAutoApplied = Boolean(cart.coupon.auto_applied);
        couponStatus.textContent = isAutoApplied
          ? "Best public coupon auto-applied: " + cart.coupon.code
          : "Coupon applied: " + cart.coupon.code;

        if (couponInput) {
          couponInput.value = cart.coupon.code;
        }
        if (copyCouponBtn) {
          copyCouponBtn.style.display = "inline-flex";
          copyCouponBtn.setAttribute("data-copy-code", cart.coupon.code);
        }
      } else {
        couponStatus.textContent = "";
        if (couponInput) {
          couponInput.value = "";
        }
        if (copyCouponBtn) {
          copyCouponBtn.style.display = "none";
          copyCouponBtn.setAttribute("data-copy-code", "");
        }
      }
    }

    // ✅ totals directly backend मधून
    document.getElementById("checkoutSubtotal").textContent = "₹" + (cart.subtotal || 0);
    document.getElementById("checkoutDiscount").textContent = "₹" + (cart.discount_total || 0);
    document.getElementById("checkoutDeliveryFee").textContent = "₹" + (cart.delivery_fee || 0);
  const total = parseFloat(cart.grand_total || 0);

document.getElementById("checkoutGrandTotal").textContent = "₹" + total;

// 👇 NEW LINE (important)
document.getElementById("upiAmountText").textContent = "Pay ₹" + total + " using UPI";
document.getElementById("checkoutDeliveryFee").textContent = "₹0";
generateQR(total);

    // ✅ items list
    let html = "";

    (cart.items || []).forEach(item => {
      html += `
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
          <span>${item.product_name} x ${item.quantity}</span>
          <span>₹${item.line_total}</span>
        </div>
      `;
    });

    document.getElementById("checkoutItemsList").innerHTML = html;

  } catch (err) {
    console.error("Cart load error:", err);
  }
}

// run on load
loadCheckoutSummary();

document.getElementById("applyCheckoutCouponBtn")?.addEventListener("click", async () => {
  const code = (document.getElementById("checkoutCouponInput")?.value || "").trim();
  const statusEl = document.getElementById("checkoutCouponStatus");

  try {
    const response = await fetch(window.BASE_URL + "/api/cart/coupon", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": window.__csrf
      },
      credentials: "include",
      body: JSON.stringify({ code })
    });

    const payload = await response.json();
    if (!payload.success) {
      throw new Error(payload.message || "Unable to apply coupon");
    }

    if (statusEl) {
      statusEl.textContent = payload.message || "Coupon updated";
    }
    await loadCheckoutSummary();
  } catch (error) {
    if (statusEl) {
      statusEl.textContent = error.message || "Coupon apply failed";
    }
  }
});

</script>
<script>

function generateQR(amount) {
  const upiId = "anshpopo013-1@okaxis";   // ✅ client UPI
  const name = "Cakeouflage";

  const upiLink = `upi://pay?pa=${upiId}&pn=${name}&am=${amount}&cu=INR`;

  const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(upiLink)}`;

  document.getElementById("upiQR").src = qrUrl;

  const deepLink = document.getElementById("upiDeepLink");
  if (deepLink) {
    deepLink.href = upiLink;
    deepLink.style.display = "flex";
  }
  const buttonHint = document.getElementById("upiButtonHint");
  if (buttonHint) {
    buttonHint.style.display = "block";
  }
}
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const OTP_COOLDOWN_MS = 60000;
  const OTP_STORAGE_KEY = "otp_cooldown_checkout_until";

  const sendBtn = document.getElementById("sendOtpBtn");
  const statusEl = document.getElementById("otpStatus");
  const defaultSendText = sendBtn ? sendBtn.textContent : "Send OTP";
  let cooldownTimer = null;

  const readCooldownUntil = () => {
    const value = Number(window.localStorage.getItem(OTP_STORAGE_KEY) || "0");
    return Number.isFinite(value) ? value : 0;
  };

  const startCooldownUi = (untilEpochMs) => {
    if (!sendBtn) return;

    const tick = () => {
      const remainingMs = untilEpochMs - Date.now();
      if (remainingMs <= 0) {
        if (cooldownTimer) {
          window.clearInterval(cooldownTimer);
          cooldownTimer = null;
        }
        sendBtn.disabled = false;
        sendBtn.textContent = defaultSendText;
        window.localStorage.removeItem(OTP_STORAGE_KEY);
        return;
      }
      const remainingSec = Math.ceil(remainingMs / 1000);
      sendBtn.disabled = true;
      sendBtn.textContent = `Resend OTP in ${remainingSec}s`;
      if (statusEl) {
        statusEl.textContent = `OTP sent. You can resend in ${remainingSec}s.`;
      }
    };

    if (cooldownTimer) {
      window.clearInterval(cooldownTimer);
    }
    tick();
    cooldownTimer = window.setInterval(tick, 250);
  };

  if (!sendBtn) {
    console.log("Send OTP button not found ❌");
    return;
  }

  const existingCooldownUntil = readCooldownUntil();
  if (existingCooldownUntil > Date.now()) {
    startCooldownUi(existingCooldownUntil);
  }

  sendBtn.addEventListener("click", async () => {

const email = document.getElementById("customerEmail")?.value.trim();
const customerName = document.getElementById("customerName")?.value.trim() || "Customer";

if (!email) {
  alert("Enter email first");
  return;
}

    // 🔥 show OTP input
    document.getElementById("otpSection").style.display = "block";
    document.getElementById("otpStatus").textContent = "Sending OTP...";
    sendBtn.disabled = true;
    sendBtn.textContent = "Sending...";

    try {
      const res = await fetch(window.BASE_URL + "/api/send-otp", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.__csrf
        },
        body: JSON.stringify({ email, name: customerName }),
        credentials: "include"
      });

      const data = await res.json();
      console.log("OTP:", data);

      if (data.success) {
        document.getElementById("otpStatus").textContent = "OTP sent successfully ✅";
          document.getElementById("customerEmail").readOnly = true;

  const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
  window.localStorage.setItem(OTP_STORAGE_KEY, String(cooldownUntil));
  startCooldownUi(cooldownUntil);
      } else {
        document.getElementById("otpStatus").textContent = data.message || "Failed to send OTP";
        sendBtn.disabled = false;
        sendBtn.textContent = defaultSendText;
      }

    } catch (err) {
      console.error(err);
      document.getElementById("otpStatus").textContent = "Error sending OTP";
      sendBtn.disabled = false;
      sendBtn.textContent = defaultSendText;
    }

  });

});
document.getElementById("customerEmail").addEventListener("input", () => {
  window.otpVerified = false;
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

  const verifyBtn = document.getElementById("verifyOtpBtn");

  if (!verifyBtn) {
    console.log("Verify button not found ❌");
    return;
  }

  verifyBtn.addEventListener("click", async () => {

    const email = document.getElementById("customerEmail").value.trim();
    const otp = document.getElementById("otpInput").value.trim();

    if (!email) {
      alert("Enter email first");
      return;
    }

    if (!otp) {
      alert("Enter OTP");
      return;
    }

    try {
      const res = await fetch(window.BASE_URL + "/api/verify-otp", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.__csrf
        },
        body: JSON.stringify({
  email,
  otp,
  name: document.getElementById("customerName").value.trim()
}),
       // body: JSON.stringify({ email, otp }), // ✅ FIXED
        credentials: "include"
      });

      const data = await res.json();
      console.log("VERIFY RESPONSE:", data);

      if (data.success) {
        document.getElementById("otpStatus").textContent = "Email OTP verified ✅";
        window.otpVerified = true;
          document.getElementById("verifyOtpBtn").disabled = true;
          await loadCheckoutSummary();
      } else {
        document.getElementById("otpStatus").textContent = data.message;
      }

    } catch (err) {
      console.error(err);
    }

  });

});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {

  const previewBtn = document.getElementById("previewCheckoutBtn");

  if (!previewBtn) return;

  previewBtn.addEventListener("click", async () => {

    const postalCode = document.getElementById("postalCode").value;
    const fulfilmentMode = document.querySelector('input[name="fulfilment_mode"]:checked')?.value;

    if (!postalCode && fulfilmentMode !== "pickup") {
      alert("Enter pincode first");
      return;
    }

    try {
      const res = await fetch(window.BASE_URL + "/api/checkout/preview", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.__csrf
        },
        body: JSON.stringify({
          postal_code: postalCode,
          fulfilment_mode: fulfilmentMode
        }),
        credentials: "include"
      });

      const data = await res.json();
      console.log("PREVIEW:", data);

      if (!data.success) {
        alert(data.message);
        return;
      }

      const preview = data.data;

      // ✅ UI update
      document.getElementById("checkoutDeliveryFee").textContent = "₹" + preview.delivery_fee;
      document.getElementById("checkoutGrandTotal").textContent = "₹" + preview.grand_total;
      await loadCheckoutSummary();

    } catch (err) {
      console.error(err);
      alert("Preview failed");
    }

  });
  

});
</script>
