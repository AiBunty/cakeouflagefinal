<?php
$quote = isset($quote) && is_array($quote) ? $quote : null;
$meta = isset($inquiryMeta) && is_array($inquiryMeta) ? $inquiryMeta : [];
$tokenValue = isset($token) ? (string)$token : '';
$isExpired = isset($isExpired) ? (bool)$isExpired : false;
$quoteStatus = $quote ? (string)($quote['quote_status'] ?? '') : '';
$linkUsed = $quote && !empty($quote['used_at']);
$orderId = $quote ? (int)($quote['order_id'] ?? 0) : 0;
$quoteAmount = $quote ? (float)($quote['quote_amount'] ?? 0) : 0;
?>
<main data-page="byoc-quote-accept">
  <section class="section byoc-accept-section">
    <div class="container">
      <div class="byoc-accept-card">
        <span class="byoc-accept-label">Build Your Own Cake</span>
        <h1 class="byoc-accept-title">Quote Acceptance</h1>

        <?php if (!$quote): ?>
          <p class="byoc-accept-status byoc-accept-status--error">This quote link is invalid or no longer available.</p>
        <?php elseif ($linkUsed || $orderId > 0): ?>
          <p class="byoc-accept-status byoc-accept-status--ok">This quote has already been accepted and converted to order #<?= htmlspecialchars((string)$orderId, ENT_QUOTES, 'UTF-8') ?>.</p>
        <?php elseif ($isExpired): ?>
          <p class="byoc-accept-status byoc-accept-status--error">This quote link has expired. Please request a fresh quote link from Cakeouflage.</p>
        <?php else: ?>
          <p class="byoc-accept-subtitle">Review your custom quote details and confirm to place it as a payment-pending order.</p>

          <div class="byoc-accept-grid">
            <div class="byoc-accept-item">
              <span>Quote Number</span>
              <strong><?= htmlspecialchars((string)($quote['quote_number'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Name</span>
              <strong><?= htmlspecialchars((string)($quote['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Event</span>
              <strong><?= htmlspecialchars((string)($meta['event_information'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Event Date</span>
              <strong><?= htmlspecialchars((string)($meta['event_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Guests</span>
              <strong><?= htmlspecialchars((string)($meta['number_of_servings_guests'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Quote Amount</span>
              <strong>INR <?= htmlspecialchars(number_format((float)($quote['quote_amount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
          </div>

          <div class="byoc-accept-note">
            <strong><?= htmlspecialchars((string)($quote['quote_subject'] ?? 'Custom Cake Quote'), ENT_QUOTES, 'UTF-8') ?></strong>
            <p><?= nl2br(htmlspecialchars((string)($quote['quote_message'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
          </div>

          <form id="byocQuoteAcceptForm"
            data-token="<?= htmlspecialchars($tokenValue, ENT_QUOTES, 'UTF-8') ?>"
            data-quote-amount="<?= htmlspecialchars((string)$quoteAmount, ENT_QUOTES, 'UTF-8') ?>"
            data-event-date="<?= htmlspecialchars((string)($meta['event_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

            <!-- Fulfillment type selector -->
            <div style="margin-bottom:18px;">
              <p style="font-weight:700; font-size:1rem; margin:0 0 10px;">📦 How would you like to receive your cake?</p>
              <div style="display:grid; gap:8px;">
                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; padding:10px 14px; border-radius:10px; border:2px solid rgba(128,0,31,0.2); font-size:0.9rem;">
                  <input type="radio" name="fulfillment_type" id="byocFulfilDelivery" value="delivery" checked style="margin-top:3px;">
                  <span><strong>Home Delivery</strong> — Porter delivers to your address (charges paid to delivery partner on arrival)</span>
                </label>
                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; padding:10px 14px; border-radius:10px; border:2px solid rgba(128,0,31,0.2); font-size:0.9rem;">
                  <input type="radio" name="fulfillment_type" id="byocFulfilPickup" value="pickup" style="margin-top:3px;">
                  <span><strong>Store Pickup</strong> — Collect your cake directly from Cakeouflage (free)</span>
                </label>
              </div>
            </div>

            <!-- Slot picker -->
            <div id="byocSlotSection" style="margin-bottom:18px; display:none;">
              <h3 style="font-size:1rem; font-weight:700; margin-bottom:10px;">🕐 Preferred Slot</h3>
              <div id="byocSlotStatus" style="font-size:0.85rem; color:#7f6973; margin-bottom:8px; display:none;"></div>
              <select id="byocSlotSelect" name="slot_id" style="width:100%; padding:9px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem;">
                <option value="0">Loading slots...</option>
              </select>
              <p style="font-size:0.8rem; color:#7f6973; margin:6px 0 0;">Slot selection is optional — our team will confirm availability.</p>
            </div>

            <!-- Delivery address section -->
            <div class="checkout-section" id="byocDeliverySection" style="margin-bottom:18px;">
              <h3 style="font-size:1rem; font-weight:700; margin-bottom:10px;">🚚 Delivery Address</h3>
              <div class="porter-notice" style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:10px 14px; font-size:0.85rem; margin-bottom:14px; color:#0c4a6e;">
                🚚 <strong>Porter Home Delivery:</strong> Delivery is handled via <strong>Porter</strong>. Charges are paid <strong>directly to the Porter delivery partner</strong> at the time of delivery (est. ₹80–₹150 within Nashik).
              </div>
              <div style="display:grid; gap:10px;">
                <label class="form-control">
                  <span class="form-label">Street Address <span style="color:#e11d48">*</span></span>
                  <input id="byocStreet" name="delivery_street" type="text" required placeholder="House / Society / Road name" autocomplete="street-address">
                </label>
                <label class="form-control">
                  <span class="form-label">Postal Code <span style="color:#e11d48">*</span></span>
                  <input id="byocPincode" name="delivery_pincode" type="text" required maxlength="15" placeholder="422001" autocomplete="postal-code">
                </label>
                <label class="form-control">
                  <span class="form-label">Google Maps Link (optional)</span>
                  <input id="byocMapsLink" name="delivery_maps_link" type="url" maxlength="600" placeholder="https://maps.app.goo.gl/...">
                </label>
                <button type="button" id="byocUseLocationBtn" class="btn btn--outline btn--sm" style="width:fit-content;">📍 Use My Location</button>
                <p id="byocLocationStatus" style="font-size:0.82rem; color:#7f6973; margin:0; display:none;"></p>
              </div>
            </div>

            <!-- Coupon code section -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:18px;">
              <p style="margin:0 0 10px; font-weight:700; font-size:0.95rem;">🏷️ Coupon Code (optional)</p>
              <div style="display:flex; gap:8px; align-items:stretch;">
                <input type="text" id="byocCouponInput" name="coupon_code" maxlength="50"
                  placeholder="Enter coupon code"
                  style="flex:1; padding:9px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem; text-transform:uppercase; letter-spacing:0.05em;"
                  autocomplete="off">
                <button type="button" id="byocApplyCouponBtn"
                  style="padding:9px 16px; background:#80001f; color:#fff; border:none; border-radius:8px; font-size:0.88rem; cursor:pointer; font-weight:600; white-space:nowrap;">
                  Apply
                </button>
              </div>
              <p id="byocCouponMsg" style="margin:6px 0 0; font-size:0.82rem; display:none;"></p>
            </div>

            <!-- Payment section -->
            <div style="background:#fff9f0; border:1px solid #fde68a; border-radius:10px; padding:14px 16px; margin-bottom:18px;">
              <p style="margin:0 0 12px; font-weight:700; font-size:0.95rem;">💳 Payment</p>

              <div style="display:grid; gap:8px; margin-bottom:14px;">
                <input type="hidden" name="payment_type" value="full">
                <label style="display:flex; align-items:flex-start; gap:8px; font-size:0.88rem; line-height:1.4;">
                  <input type="radio" id="byocPayFull" checked disabled style="margin-top:2px;">
                  <span>Pay <strong>Full Amount</strong> &mdash; <strong id="byocFullAmtDisplay">₹<?= htmlspecialchars(number_format($quoteAmount, 0), ENT_QUOTES, 'UTF-8') ?></strong></span>
                </label>
              </div>

              <p style="margin:0 0 4px; font-weight:600; font-size:0.88rem;">Scan &amp; Pay via UPI:</p>
              <p style="font-size:0.82rem; color:#7f6973; margin:0 0 8px;">Amount to pay: <strong id="byocQrAmountLabel">₹<?= htmlspecialchars(number_format($quoteAmount, 0), ENT_QUOTES, 'UTF-8') ?></strong></p>
              <img id="byocUpiQr"
                src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode('upi://pay?pa=anshpopo013-1@okaxis&pn=Cakeouflage&am=' . number_format($quoteAmount, 2, '.', '') . '&cu=INR&tn=BYOC+Quote+Payment') ?>"
                width="180" alt="UPI QR Code" style="display:block; margin:8px 0; border-radius:8px;">
              <a id="byocUpiDeepLink"
                href="upi://pay?pa=anshpopo013-1@okaxis&pn=Cakeouflage&am=<?= number_format($quoteAmount, 2, '.', '') ?>&cu=INR&tn=BYOC+Quote+Payment"
                class="btn btn--primary"
                style="display:inline-flex; align-items:center; gap:6px; margin:8px 0; text-decoration:none; font-size:0.85rem;">📱 Pay Now with UPI App</a>
              <p style="font-size:0.8rem; color:#7f6973; margin:4px 0 10px;">UPI ID: <code>anshpopo013-1@okaxis</code></p>
              <p style="font-size:0.82rem; color:#4b5563; margin:0 0 6px; font-weight:600;">After paying, upload your payment screenshot:</p>
              <input type="file" name="payment_proof" id="byocPaymentProof" accept="image/*">
            </div>

            <button type="submit" class="btn btn--primary" id="byocQuoteAcceptBtn">Accept Quote &amp; Confirm Order</button>
          </form>
          <p id="byocQuoteAcceptStatus" class="byoc-accept-status" role="status" aria-live="polite"></p>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
