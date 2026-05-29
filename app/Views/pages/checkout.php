<?php
/* Cakeouflage — Checkout */
$isLoggedIn  = !empty($currentUser);
$prefillName  = $isLoggedIn ? htmlspecialchars((string)($currentUser['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') : '';
$prefillPhone = $isLoggedIn ? htmlspecialchars((string)($currentUser['phone']     ?? ''), ENT_QUOTES, 'UTF-8') : '';
$prefillEmail = $isLoggedIn ? htmlspecialchars((string)($currentUser['email']     ?? ''), ENT_QUOTES, 'UTF-8') : '';
$prefillStreet = ($isLoggedIn && !empty($lastAddress['street']))     ? htmlspecialchars((string)$lastAddress['street'],     ENT_QUOTES, 'UTF-8') : '';
$prefillPincode = ($isLoggedIn && !empty($lastAddress['postal_code'])) ? htmlspecialchars((string)$lastAddress['postal_code'], ENT_QUOTES, 'UTF-8') : '';
$prefillMaps    = ($isLoggedIn && !empty($lastAddress['maps_link']))   ? htmlspecialchars((string)$lastAddress['maps_link'],   ENT_QUOTES, 'UTF-8') : '';
$allowPartialPayment = isset($allowPartialPayment) ? (bool)$allowPartialPayment : false;
$screenshotRequired  = isset($screenshotRequired)  ? (bool)$screenshotRequired  : true;
?>
<style>
  main[data-page="checkout"] {
    --checkout-burgundy: #80001f;
    --checkout-ink: #2f1d25;
    --checkout-muted: #74636a;
    --checkout-line: rgba(128, 0, 31, 0.12);
    --checkout-blush: linear-gradient(145deg, rgba(255, 246, 243, 0.98), rgba(255, 252, 248, 0.96));
    --checkout-highlight: #fff5ef;
    --checkout-success: #1d6f42;
    --checkout-warn: #8a5a00;
  }
  main[data-page="checkout"] .page-inner-header {
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 14px;
  }
  main[data-page="checkout"] .page-inner-desc {
    max-width: 620px;
  }
  .checkout-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    margin-bottom: 10px;
    border-radius: 999px;
    background: rgba(128, 0, 31, 0.08);
    color: var(--checkout-burgundy);
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }
  .checkout-layout {
    gap: 18px;
  }
  .checkout-form {
    gap: 12px;
  }
  .checkout-section.card,
  .checkout-summary,
  .checkout-mobile-bar {
    border: 1px solid var(--checkout-line);
    box-shadow: 0 18px 45px rgba(85, 32, 48, 0.08);
  }
  .checkout-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    align-items: start;
  }
  .checkout-section.card {
    padding: 16px 18px;
    border-radius: 22px;
    background: #fff;
  }
  .checkout-section--full {
    grid-column: 1 / -1;
  }
  .checkout-section__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    color: var(--checkout-burgundy);
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .checkout-section__title {
    margin-bottom: 8px;
    padding-bottom: 0;
    border-bottom: 0;
  }
  .checkout-section__subtitle {
    margin: 0 0 12px;
    color: var(--checkout-muted);
    font-size: 0.88rem;
    line-height: 1.5;
  }
  .checkout-login-note,
  .checkout-feedback,
  .checkout-toast,
  .checkout-inline-banner,
  .otp-notice,
  #locationStatus {
    border-radius: 14px;
    padding: 11px 13px;
    font-size: 0.88rem;
    line-height: 1.45;
  }
  .checkout-login-note {
    margin-bottom: 12px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
  }
  .checkout-inline-banner {
    margin-bottom: 12px;
    background: var(--checkout-highlight);
    border: 1px solid rgba(194, 127, 85, 0.22);
    color: var(--checkout-ink);
  }
  .checkout-feedback,
  .otp-notice,
  #locationStatus {
    display: none;
    margin-top: 10px;
    border: 1px solid transparent;
  }
  .checkout-feedback.is-visible,
  .otp-notice.is-visible,
  #locationStatus.is-visible {
    display: block;
  }
  .checkout-feedback[data-tone="success"],
  .otp-notice[data-tone="success"],
  #locationStatus[data-tone="success"] {
    background: #eefaf2;
    border-color: #bfe8c8;
    color: var(--checkout-success);
  }
  .checkout-feedback[data-tone="warn"],
  .otp-notice[data-tone="warn"],
  #locationStatus[data-tone="warn"] {
    background: #fff7e7;
    border-color: #f5d08b;
    color: var(--checkout-warn);
  }
  .checkout-feedback[data-tone="error"],
  .otp-notice[data-tone="error"],
  #locationStatus[data-tone="error"] {
    background: #fff0f0;
    border-color: #f1b7b7;
    color: #a33d3d;
  }
  .checkout-feedback[data-tone="info"],
  .otp-notice[data-tone="info"],
  #locationStatus[data-tone="info"] {
    background: #eef6ff;
    border-color: #bfd7f6;
    color: #1f5e9c;
  }
  .checkout-form input,
  .checkout-form select,
  .checkout-form textarea,
  .coupon-input,
  #paymentProof {
    min-height: 48px;
    border-radius: 14px;
    border-color: rgba(128, 0, 31, 0.16);
    box-shadow: none;
  }
  .checkout-form textarea {
    min-height: 92px;
  }
  .checkout-form input:focus,
  .checkout-form select:focus,
  .checkout-form textarea:focus,
  .coupon-input:focus,
  .otp-cell:focus {
    border-color: var(--checkout-burgundy);
    box-shadow: 0 0 0 3px rgba(128, 0, 31, 0.12);
    outline: none;
  }
  .checkout-form .field-invalid {
    border-color: #cf5d5d;
    box-shadow: 0 0 0 3px rgba(207, 93, 93, 0.12);
    animation: shake-x 0.32s ease;
  }
  @keyframes shake-x {
    0%,100% { transform: translateX(0); }
    20%      { transform: translateX(-5px); }
    40%      { transform: translateX(5px); }
    60%      { transform: translateX(-4px); }
    80%      { transform: translateX(4px); }
  }
  .field-error-msg {
    display: none;
    font-size: .73rem;
    color: #cf5d5d;
    margin-top: 4px;
    font-weight: 500;
  }
  .field-error-msg.is-visible {
    display: block;
  }
  .checkout-auth-row {
    display: grid;
    gap: 10px;
    margin-top: 8px;
  }
    .checkout-phone-group {
      display: grid;
      grid-template-columns: minmax(180px, 1fr) minmax(0, 1.1fr);
      gap: 8px;
    }
    .checkout-phone-group__country {
      display: grid;
      gap: 6px;
    }
    .checkout-phone-group__country input,
    .checkout-phone-group__country select,
    .checkout-phone-group > input {
      min-height: 44px;
    }
  .checkout-auth-mode {
    margin-top: 8px;
    padding: 14px;
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(255, 248, 244, 0.94), rgba(255, 255, 255, 0.98));
    border: 1px solid rgba(128, 0, 31, 0.1);
  }
  .checkout-auth-mode__eyebrow {
    display: block;
    margin-bottom: 6px;
    color: var(--checkout-burgundy);
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .checkout-auth-mode__title {
    margin: 0;
    color: var(--checkout-ink);
    font-size: 0.98rem;
    font-weight: 700;
  }
  .checkout-auth-mode__subtitle {
    margin: 4px 0 0;
    color: var(--checkout-muted);
    font-size: 0.86rem;
    line-height: 1.45;
  }
  .checkout-auth-mode__actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 12px;
  }
  .checkout-auth-mode__actions .btn {
    min-height: 48px;
    justify-content: center;
    text-align: center;
  }
  .checkout-auth-mode__actions .btn.is-active {
    background: var(--checkout-burgundy);
    border-color: var(--checkout-burgundy);
    color: #fff;
  }
  .checkout-auth-button {
    width: 100%;
    min-height: 52px;
    justify-content: center;
    gap: 8px;
    font-size: 0.96rem;
    font-weight: 700;
    letter-spacing: 0.01em;
  }
  .checkout-auth-button[disabled],
  .checkout-auth-verify[disabled] {
    opacity: 0.68;
    cursor: not-allowed;
    transform: none;
  }
  .checkout-auth-meta {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    color: var(--checkout-muted);
    font-size: 0.8rem;
  }
  .checkout-auth-step {
    display: none;
    margin-top: 10px;
    padding: 14px;
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(255, 248, 244, 0.9), rgba(255, 255, 255, 0.98));
    border: 1px solid rgba(128, 0, 31, 0.1);
  }
  .checkout-auth-step.is-visible {
    display: block;
  }
  .otp-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
  }
  .otp-cell {
    width: 100%;
    aspect-ratio: 1 / 1.04;
    border-radius: 14px;
    border: 1px solid rgba(128, 0, 31, 0.16);
    background: #fff;
    color: var(--checkout-ink);
    text-align: center;
    font-size: 1.08rem;
    font-weight: 700;
  }
  .otp-note {
    margin: 8px 0 0;
    color: var(--checkout-muted);
    font-size: 0.8rem;
  }
  .checkout-auth-verify {
    margin-top: 12px;
    width: 100%;
    justify-content: center;
  }
  .checkout-section--hidden-by-mode {
    display: none !important;
  }
  .checkout-section--collapsible .checkout-section__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    cursor: pointer;
    user-select: none;
  }
  .checkout-section__toggle {
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: rgba(128, 0, 31, 0.08);
    color: var(--checkout-burgundy);
    font-size: 1rem;
    font-weight: 700;
  }
  .checkout-section__body {
    margin-top: 12px;
  }
  .checkout-section--collapsed .checkout-section__body {
    display: none;
  }
  .checkout-section--collapsed .checkout-section__toggle {
    transform: rotate(-45deg);
  }
  @media (max-width: 720px) {
    .checkout-auth-mode__actions {
      grid-template-columns: 1fr;
    }
    .checkout-auth-meta {
      flex-direction: column;
      align-items: flex-start;
    }
  }
  .porter-notice {
    margin-bottom: 12px;
    border-radius: 16px;
    background: #fff7ed;
  }
  .payment-choice-card {
    padding: 13px;
    border-radius: 16px;
    background: #fff9f0;
    border: 1px solid #fde68a;
  }
  .upi-section {
    display: grid;
    gap: 10px;
    margin-top: 14px;
    padding: 14px;
    border-radius: 18px;
    background: rgba(128, 0, 31, 0.03);
    border: 1px solid rgba(128, 0, 31, 0.08);
  }
  .upi-section img {
    border-radius: 18px;
    border: 1px solid rgba(128, 0, 31, 0.08);
    background: #fff;
  }
  .coupon-input-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 8px;
  }
  .checkout-actions {
    display: grid;
    grid-template-columns: 180px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
  }
  .checkout-summary {
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(255, 250, 248, 0.98), rgba(255, 255, 255, 1));
  }
  .checkout-items-mini {
    display: grid;
    gap: 8px;
    max-height: 240px;
    overflow: auto;
    padding-right: 4px;
  }
  .checkout-item-mini {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(128, 0, 31, 0.08);
    font-size: 0.88rem;
  }
  .checkout-item-mini__meta {
    display: grid;
    gap: 3px;
  }
  .checkout-item-mini__name {
    font-weight: 600;
    color: var(--checkout-ink);
  }
  .checkout-item-mini__qty {
    color: var(--checkout-muted);
    font-size: 0.8rem;
  }
  .checkout-summary__cta {
    margin-top: 14px;
  }
  .checkout-mobile-bar {
    display: none;
  }
  .checkout-toast-stack {
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 70;
    display: grid;
    gap: 10px;
    pointer-events: none;
  }
  .checkout-toast {
    min-width: 240px;
    max-width: 320px;
    background: rgba(47, 29, 37, 0.96);
    color: #fff;
    box-shadow: 0 18px 35px rgba(47, 29, 37, 0.18);
  }
  .checkout-toast[data-tone="success"] { background: rgba(29, 111, 66, 0.96); }
  .checkout-toast[data-tone="warn"] { background: rgba(138, 90, 0, 0.96); }
  .checkout-toast[data-tone="error"] { background: rgba(163, 61, 61, 0.96); }
  @media (max-width: 980px) {
    .checkout-form-grid {
    .checkout-phone-group {
      grid-template-columns: 1fr;
    }
      grid-template-columns: 1fr;
    }
    .checkout-section--half {
      grid-column: auto;
    }
    .checkout-hero {
      grid-template-columns: 1fr;
    }
    .checkout-hero__chips {
      justify-content: flex-start;
    }
  }
  @media (max-width: 720px) {
    .checkout-layout {
      gap: 16px;
    }
    .checkout-section.card,
    .checkout-summary,
    .checkout-hero {
      border-radius: 18px;
      padding: 14px;
    }
    .checkout-actions,
    .coupon-input-row {
      grid-template-columns: 1fr;
    }
    .otp-grid {
      gap: 6px;
    }
    .otp-cell {
      border-radius: 12px;
      font-size: 1rem;
    }
    .checkout-summary {
      position: static;
    }
    .checkout-mobile-bar {
      position: sticky;
      bottom: 10px;
      z-index: 25;
      display: grid;
      gap: 8px;
      margin-top: 10px;
      padding: 12px;
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(10px);
    }
    .checkout-mobile-bar__meta {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      font-size: 0.82rem;
      color: var(--checkout-muted);
    }
    .checkout-mobile-bar .btn {
      min-height: 48px;
    }
  }

  /* ─── Global overflow-x fix ─── */
  html, body { overflow-x: hidden; }

  /* ─── Progressive checkout step flow ─── */
  .checkout-steps { display: flex; flex-direction: column; gap: 14px; }
  .checkout-step {
    border-radius: 22px;
    background: #fff;
    border: 1px solid var(--checkout-line);
    box-shadow: 0 18px 45px rgba(85, 32, 48, .06);
    overflow: hidden;
    transition: box-shadow .26s ease, border-color .26s ease;
  }
  .checkout-step--active { border-color: rgba(128,0,31,.28); box-shadow: 0 18px 45px rgba(85,32,48,.12); }
  .checkout-step--pending { opacity: .55; pointer-events: none; }
  .checkout-step--pending .checkout-step__header { cursor: default; }
  .checkout-step__header {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 22px; cursor: pointer; user-select: none;
  }
  .checkout-step__num {
    flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .88rem;
    background: rgba(128,0,31,.1); color: var(--checkout-burgundy);
    transition: background .2s, color .2s;
  }
  .checkout-step--active .checkout-step__num { background: var(--checkout-burgundy); color: #fff; }
  .checkout-step--complete .checkout-step__num { background: var(--checkout-success); color: #fff; }
  .checkout-step__heading { margin: 0; font-size: 1rem; font-weight: 700; color: var(--checkout-ink); line-height: 1.25; }
  .checkout-step__summary { margin: 4px 0 0; font-size: .8rem; color: var(--checkout-muted); line-height: 1.4; }
  .checkout-step--complete .checkout-step__summary { color: var(--checkout-success); font-weight: 600; }
  .checkout-step__edit-btn {
    margin-left: auto; flex-shrink: 0; padding: 5px 14px;
    border-radius: 999px; border: 1px solid rgba(128,0,31,.22);
    background: transparent; color: var(--checkout-burgundy);
    font-size: .78rem; font-weight: 700; cursor: pointer;
    letter-spacing: .02em; transition: background .15s;
  }
  .checkout-step__edit-btn:hover { background: rgba(128,0,31,.06); }
  .checkout-step__body { display: none; padding: 0 22px 22px; }
  .checkout-step--active .checkout-step__body { display: block; }
  .checkout-step__continue {
    margin-top: 18px; width: 100%; min-height: 52px;
    display: flex; align-items: center; justify-content: center;
  }

  /* ─── Mobile order summary sidebar toggle ─── */
  .checkout-summary-toggle { display: none; }
  @media (max-width: 767px) {
    .checkout-summary-toggle {
      display: flex; align-items: center; justify-content: space-between;
      width: 100%; padding: 14px 20px;
      background: #fff; border: 1px solid var(--checkout-line);
      border-radius: 16px; font-size: .92rem; font-weight: 700;
      color: var(--checkout-ink); cursor: pointer; margin-top: 16px;
    }
    .checkout-summary-toggle__arrow { font-size: 1.1rem; transition: transform .2s; }
    .checkout-summary-toggle.is-open .checkout-summary-toggle__arrow { transform: rotate(180deg); }
    .checkout-summary { display: none !important; }
    .checkout-summary.is-open { display: block !important; }
    .checkout-step__header { padding: 14px 16px; gap: 12px; }
    .checkout-step__body { padding: 0 16px 16px; }
    .checkout-step__num { width: 32px; height: 32px; font-size: .82rem; }
  }
</style>
<main class="section" data-page="checkout">
  <div class="container">
    <div class="page-inner-header">
      <div>
        <span class="checkout-kicker">Premium Checkout</span>
        <h1 class="page-inner-title">Checkout</h1>
        <p class="page-inner-desc">You're almost there! Choose delivery or pickup, confirm your slot, and place your order.</p>
      </div>
      <a href="/cart" class="btn btn--outline">Back to Cart</a>
    </div>

    <div class="checkout-layout">

      <!-- Left: Form -->
      <form class="checkout-form" id="checkoutForm" novalidate>
        <div class="checkout-steps" id="checkoutSteps">

          <!-- ── Step 1: Contact & Verification ── -->
          <div class="checkout-step checkout-step--active" id="step-contact" data-step="1">
            <div class="checkout-step__header">
              <div class="checkout-step__num">1</div>
              <div>
                <p class="checkout-step__heading">Contact &amp; Verification</p>
                <p class="checkout-step__summary" id="step1Summary"><?= $isLoggedIn ? 'Logged in — details pre-filled' : 'Email-first smart verification' ?></p>
              </div>
              <button type="button" class="checkout-step__edit-btn" data-edit-step="step-contact"<?= $isLoggedIn ? '' : ' hidden' ?>>Edit</button>
            </div>
            <div class="checkout-step__body">
              <div id="checkoutUserDetection" class="checkout-inline-banner" hidden></div>
              <div
                id="checkoutAuthStateRoot"
                data-prefill-name="<?= htmlspecialchars($prefillName, ENT_QUOTES, 'UTF-8') ?>"
                data-prefill-phone="<?= htmlspecialchars($prefillPhone, ENT_QUOTES, 'UTF-8') ?>"
                data-prefill-email="<?= htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8') ?>"
                data-is-logged-in="<?= $isLoggedIn ? '1' : '0' ?>"
              ></div>
              <p id="otpStatus" class="checkout-feedback" aria-live="polite"></p>

            </div><!-- end step-contact body -->
          </div><!-- end step-contact -->

          <!-- ── Step 2: Delivery or Pickup ── -->
          <div class="checkout-step checkout-step--pending" id="step-delivery" data-step="2">
            <div class="checkout-step__header">
              <div class="checkout-step__num">2</div>
              <div>
                <p class="checkout-step__heading">Delivery or Pickup</p>
                <p class="checkout-step__summary" id="step2Summary">Choose how you receive your cake</p>
              </div>
              <button type="button" class="checkout-step__edit-btn" data-edit-step="step-delivery" hidden>Edit</button>
            </div>
            <div class="checkout-step__body">
              <p class="checkout-section__subtitle" style="margin-bottom:14px">Delivery shows address fields. Store pickup needs no address.</p>
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
              </div>
              <!-- Delivery address — shown only for Home Delivery -->
              <div id="addressSection" style="display:none;margin-top:18px">
                <div class="porter-notice" id="porterNotice">
                  <span class="porter-notice__icon">🚚</span>
                  <div>
                    <strong>Porter Home Delivery</strong>
                    <p>Delivery is handled via <strong>Porter</strong>. Charges are paid <strong>directly to the Porter delivery partner</strong> at the time of delivery. Estimated: ₹80–₹150 within Nashik.</p>
                  </div>
                </div>
                <div class="form-row-2" style="margin-top:14px">
                  <label class="form-control">
                    <span class="form-label">Street / Area <span class="form-required">*</span></span>
                    <input type="text" id="deliveryStreet" name="delivery_street" placeholder="House no., street, area, landmark" autocomplete="street-address" value="<?= $prefillStreet ?>" data-autosave="delivery_street">
                    <span class="field-error-msg" aria-live="polite"></span>
                  </label>
                  <label class="form-control">
                    <span class="form-label">Pincode <span class="form-required">*</span></span>
                    <input type="text" id="deliveryPincode" name="delivery_pincode" placeholder="e.g. 422001" maxlength="6" inputmode="numeric" autocomplete="postal-code" value="<?= $prefillPincode ?>" data-autosave="delivery_pincode">
                    <span class="field-error-msg" aria-live="polite"></span>
                  </label>
                </div>
                <label class="form-control" style="margin-top:12px">
                  <span class="form-label">Google Maps Link <span style="font-weight:400;opacity:.7">(optional)</span></span>
                  <input type="url" id="deliveryMapsLink" name="delivery_maps_link" placeholder="https://maps.app.goo.gl/…" value="<?= $prefillMaps ?>" data-autosave="delivery_maps_link">
                </label>
                <button type="button" class="btn btn--outline btn--sm" id="useMyLocationBtn" style="margin-top:12px">
                  📍 Use My Location
                </button>
                <p class="form-hint" id="locationStatus" aria-live="polite"></p>
              </div><!-- end addressSection -->

              <button type="button" id="step2ContinueBtn" class="btn btn--primary btn--lg checkout-step__continue">
                Continue: Date &amp; Slot →
              </button>
            </div><!-- end step-delivery body -->
          </div><!-- end step-delivery -->

          <!-- ── Step 3: Date & Time Slot ── -->
          <div class="checkout-step checkout-step--pending" id="step-datetime" data-step="3">
            <div class="checkout-step__header">
              <div class="checkout-step__num">3</div>
              <div>
                <p class="checkout-step__heading">Date &amp; Time Slot</p>
                <p class="checkout-step__summary" id="step3Summary">Choose your preferred baking date and slot</p>
              </div>
              <button type="button" class="checkout-step__edit-btn" data-edit-step="step-datetime" hidden>Edit</button>
            </div>
            <div class="checkout-step__body">
              <p class="checkout-section__subtitle" style="margin-bottom:14px">Choose your preferred date first, then we'll load the available baking and delivery slots.</p>
              <div class="form-row-2" style="margin-bottom:16px">
                <label class="form-control">
                  <span class="form-label">Preferred Date <span class="form-required">*</span></span>
                  <input type="date" name="delivery_date" id="deliveryDate" required>
                  <span class="field-error-msg" aria-live="polite"></span>
                </label>
                <!-- Hidden native select keeps form/validation working -->
                <select name="slot_id" id="deliverySlot" required style="display:none" aria-hidden="true">
                  <option value="">Select date first…</option>
                </select>
              </div>

              <!-- ── Slot card picker ─────────────────────────────── -->
              <div id="slotPickerWrap" style="display:none">
                <p class="form-label" style="margin-bottom:4px">Preferred Delivery / Pickup Slot <span class="form-required">*</span></p>
                <p style="font-size:.78rem;color:#7a6870;margin-bottom:10px;line-height:1.4">
                  📌 Your selected slot will be <strong>confirmed after our team verifies your payment.</strong> We'll notify you once it's confirmed.
                </p>
                <div id="slotCardGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;"></div>
                <p id="slotPickerMsg" style="font-size:.82rem;color:var(--checkout-muted,#7a6870);margin-top:6px"></p>
              </div>

              <style>
                .slot-pick-card {
                  border: 2px solid #e8dde0; border-radius: 12px; padding: 13px 14px 10px;
                  cursor: pointer; background: #fff; transition: border-color .13s, box-shadow .13s;
                  user-select: none; position: relative;
                }
                .slot-pick-card:hover:not(.slot-pick-card--disabled) { border-color: #80001f; box-shadow: 0 2px 10px rgba(128,0,31,.10); }
                .slot-pick-card--selected { border-color: #80001f; background: rgba(128,0,31,.03); box-shadow: 0 2px 14px rgba(128,0,31,.13); }
                .slot-pick-card--disabled { opacity: .48; cursor: not-allowed; }
                .slot-pick-card__name   { font-weight: 700; font-size: .88rem; color: #2d1f25; margin-bottom: 2px; }
                .slot-pick-card__time   { font-size: .75rem; color: #7a6870; margin-bottom: 7px; }
                .slot-pick-card__badges { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 7px; }
                .slot-pick-badge { font-size: .64rem; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
                .slot-pick-badge--rec    { background: #fce7f3; color: #9d174d; }
                .slot-pick-badge--fast   { background: #fef3c7; color: #92400e; }
                .slot-pick-badge--full   { background: #fee2e2; color: #991b1b; }
                .slot-pick-badge--closed { background: #e5e7eb; color: #374151; }
                .slot-pick-card__cap { font-size: .73rem; color: #7a6870; }
                .slot-pick-card__cap strong { color: #2d1f25; }
                .slot-pick-cap-bar { height: 4px; background: #f1e8ea; border-radius: 99px; margin-top: 6px; overflow: hidden; }
                .slot-pick-cap-fill { height: 100%; background: #80001f; border-radius: 99px; transition: width .3s; }
                .slot-pick-card--selected .slot-pick-cap-fill { background: #5f0017; }
                .slot-pick-card--full .slot-pick-cap-fill { background: #dc2626; }
                .slot-pick-tick {
                  display: none; position: absolute; top: 9px; right: 9px;
                  width: 18px; height: 18px; background: #80001f; border-radius: 50%;
                  align-items: center; justify-content: center; color: #fff; font-size: .65rem;
                }
                .slot-pick-card--selected .slot-pick-tick { display: flex; }
              </style>
              <button type="button" id="step3ContinueBtn" class="btn btn--primary btn--lg checkout-step__continue">
                Continue: Payment →
              </button>
            </div><!-- end step-datetime body -->
          </div><!-- end step-datetime -->

          <!-- ── Step 4: Payment & Place Order ── -->
          <div class="checkout-step checkout-step--pending" id="step-payment" data-step="4">
            <div class="checkout-step__header">
              <div class="checkout-step__num">4</div>
              <div>
                <p class="checkout-step__heading">Payment &amp; Place Order</p>
                <p class="checkout-step__summary">Coupon, UPI payment proof, then place order</p>
              </div>
              <button type="button" class="checkout-step__edit-btn" data-edit-step="step-payment" hidden>Edit</button>
            </div>
            <div class="checkout-step__body">
              <!-- Customisation note (collapsible) -->
              <div class="checkout-section--collapsible checkout-section--collapsed" data-collapsible style="margin-bottom:16px;padding:16px;border-radius:16px;background:rgba(128,0,31,.02);border:1px solid var(--checkout-line);">
                <div class="checkout-section__header" role="button" tabindex="0" aria-expanded="false">
                  <div>
                    <div class="checkout-section__eyebrow">Optional</div>
                    <h2 class="checkout-section__title">Order Customisation</h2>
                    <p class="checkout-section__subtitle">Keep notes compact. Topper and message summaries appear here.</p>
                  </div>
                  <span class="checkout-section__toggle" aria-hidden="true">+</span>
                </div>
                <div class="checkout-section__body">
                  <div id="checkoutItemCustomSummary" style="margin-bottom:12px;display:none;">
                    <p style="font-weight:600;font-size:.88rem;margin-bottom:6px;opacity:.7;">Your customisations per item:</p>
                    <div id="checkoutItemCustomList" style="font-size:.85rem;color:#5a4050;"></div>
                  </div>
                  <label class="form-control">
                    <span class="form-label">Special Instructions (optional)</span>
                    <textarea name="customisation_note" rows="2" placeholder="e.g. No nuts, extra candles, ring inside cake…" data-autosave="customisation_note"></textarea>
                  </label>
                </div>
              </div>

              <!-- Coupon -->
              <div style="margin-bottom:16px">
                <div class="checkout-section__eyebrow">Savings</div>
                <h2 class="checkout-section__title" style="margin-bottom:10px">Coupon</h2>
                <div class="coupon-input-row">
                  <label for="checkoutCouponInput" class="sr-only">Coupon code</label>
                  <input type="text" id="checkoutCouponInput" class="coupon-input" placeholder="Coupon code" autocomplete="off">
                  <button class="btn btn--secondary btn--sm" id="applyCheckoutCouponBtn" type="button">Apply</button>
                  <button class="btn btn--outline btn--sm" id="copyCheckoutCouponBtn" type="button" data-copy-code="" style="display:none;">Copy</button>
                </div>
                <p id="checkoutCouponStatus" class="form-feedback" aria-live="polite"></p>
              </div>

              <!-- Payment type -->
              <div class="checkout-section__eyebrow" style="margin-bottom:6px">Step 4</div>
              <h2 class="checkout-section__title" style="margin-bottom:12px">Payment Method</h2>
              <p class="checkout-section__subtitle" style="margin-bottom:14px">Preview the full amount and upload your UPI screenshot to confirm.</p>
              <div class="payment-choice-card">
                <p style="margin:0 0 8px; font-weight:600; font-size:0.92rem;">💳 Payment Option</p>
                <input type="hidden" name="payment_type" value="full">
                <p id="payFullLabel" style="margin:0;">Pay in Full <strong id="payFullAmount"></strong></p>
              </div>

              <div class="upi-section" style="margin-top:16px">
                <p><strong>Scan &amp; Pay (UPI)</strong></p>
                <img id="upiQR" src="" width="200" alt="UPI QR code" hidden />
                <p id="upiAmountText" style="margin-top:10px; font-weight:600;"></p>
                <a id="upiDeepLink" href="#" class="btn btn--primary btn--lg btn--block" style="margin-top:12px; display:none; align-items:center; justify-content:center; gap:8px; background:#80001f; color:#fff; border:2px solid #80001f; box-shadow:0 10px 24px rgba(128,0,31,.22);">
                  <span aria-hidden="true">📱</span>
                  <span>Pay Now with UPI App</span>
                </a>
                <p id="upiButtonHint" class="form-hint" style="margin-top:8px; display:none;">Opens Google Pay, PhonePe, Paytm, BHIM or any UPI app installed on your phone.</p>
                <p class="form-hint">After payment, upload screenshot below</p>

                <?php if ($screenshotRequired): ?>
                <div id="screenshotAlert" style="display:flex;align-items:flex-start;gap:10px;margin:12px 0 10px;padding:11px 14px;background:#fffbea;border:1px solid #e8c440;border-radius:10px;font-size:.83rem;color:#5a4a00;">
                  <span style="font-size:1.1rem;flex-shrink:0;margin-top:1px">📸</span>
                  <div><strong>Upload your UPI payment screenshot</strong> — your order will not be placed without it. Max 5 MB, JPG / PNG / WebP only.</div>
                </div>
                <?php else: ?>
                <p class="form-hint" style="margin-bottom:8px">Screenshot is optional but helps us match your payment faster.</p>
                <?php endif; ?>

                <label id="proofDropZone" for="paymentProof" style="display:block;margin-top:4px;padding:18px;border:2px dashed #c8b4bc;border-radius:12px;text-align:center;cursor:pointer;background:#fdf9fa;transition:border-color .15s,background .15s;" onmouseover="this.style.borderColor='#80001f'" onmouseout="this.style.borderColor='#c8b4bc'">
                  <span id="proofDropLabel" style="color:#74636a;font-size:.85rem;">Click or drag-and-drop your payment screenshot here</span>
                  <img id="proofPreviewImg" src="" alt="Payment screenshot preview" style="display:none;max-width:100%;max-height:200px;margin-top:10px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.12);" />
                </label>
                <input type="file" name="payment_proof" id="paymentProof" accept="image/*" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;">
                <span class="field-error-msg" aria-live="polite"></span>
              </div>

              <div class="payment-options" role="group" style="margin-top:12px">
                <label class="payment-option">
                  <input type="radio" name="payment_method" id="paymentMethod" value="upi_manual" checked>
                  <span>📱 UPI (Google Pay / PhonePe / Paytm)</span>
                </label>
              </div>

              <div class="checkout-actions" style="margin-top:20px">
                <button class="btn btn--secondary btn--block" type="button" id="previewCheckoutBtn">Preview Total</button>
                <button class="btn btn--primary btn--lg btn--block" type="submit" id="placeOrderBtn">Place Order</button>
              </div>
              <p id="checkoutStatus" class="form-feedback" aria-live="polite"></p>
            </div><!-- end step-payment body -->
          </div><!-- end step-payment -->

        </div><!-- end .checkout-steps -->

        <div class="checkout-mobile-bar" aria-label="Mobile checkout actions">
          <div class="checkout-mobile-bar__meta">
            <span>Grand Total</span>
            <strong id="checkoutMobileGrandTotal">₹0</strong>
          </div>
          <button class="btn btn--primary btn--lg btn--block" type="submit" form="checkoutForm">Place Order</button>
        </div>
      </form>

      <!-- Right: Summary -->
      <!-- Mobile order summary toggle (hidden on desktop via CSS) -->
      <button type="button" class="checkout-summary-toggle" id="checkoutSummaryToggle" aria-expanded="false">
        <span>🛒 Order Summary</span>
        <span class="checkout-summary-toggle__arrow">▼</span>
      </button>

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
        <div class="checkout-summary__cta">
          <button class="btn btn--secondary btn--block" type="button" id="summaryPreviewBtn">Refresh Preview</button>
        </div>
      </aside>

    </div>
  </div>
  <div class="checkout-toast-stack" id="checkoutToastStack" aria-live="polite"></div>
</main>

<script>
  window.otpVerified = <?= $isLoggedIn ? 'true' : 'false' ?>;
  window.__checkoutExistingCustomer = <?= $isLoggedIn ? 'true' : 'false' ?>;
  window.__checkoutExistingCustomerPhone = <?= json_encode($prefillPhone ?? '') ?>;
  window.__screenshotRequired = <?= $screenshotRequired ? 'true' : 'false' ?>;
<?php if ($isLoggedIn && !empty($prefillStreet)): ?>
document.addEventListener("DOMContentLoaded", function () {
  var ds = document.getElementById('deliveryStreet');
  var dp = document.getElementById('deliveryPincode');
  var dm = document.getElementById('deliveryMapsLink');
  if (ds && !ds.value) ds.value = <?= json_encode($prefillStreet) ?>;
  if (dp && !dp.value) dp.value = <?= json_encode($prefillPincode) ?>;
  if (dm && !dm.value) dm.value = <?= json_encode($prefillMaps) ?>;
});
<?php endif; ?>
document.addEventListener("DOMContentLoaded", () => {
  const CHECKOUT_DRAFT_KEY = "cakeo_checkout_draft_v1";
  const form = document.getElementById("checkoutForm");
  const upiSection = document.querySelector('.upi-section');
  const addressSection = document.getElementById('addressSection');
  const checkoutStatus = document.getElementById('checkoutStatus');
  const placeOrderBtn = document.getElementById('placeOrderBtn');
  const mobileGrandTotal = document.getElementById('checkoutMobileGrandTotal');
  const toastStack = document.getElementById('checkoutToastStack');

  if (!form) {
    console.log("Form not found ❌");
    return;
  }

  const setFeedback = (element, message, tone = 'info') => {
    if (!element) return;
    element.textContent = message || '';
    element.dataset.tone = tone;
    element.classList.toggle('is-visible', Boolean(message));
  };

  const showToast = (message, tone = 'info') => {
    if (!toastStack || !message) return;
    const toast = document.createElement('div');
    toast.className = 'checkout-toast';
    toast.dataset.tone = tone;
    toast.textContent = message;
    toastStack.appendChild(toast);
    window.setTimeout(() => toast.remove(), 3200);
  };

  const setButtonState = (button, isLoading, loadingText, defaultText) => {
    if (!button) return;
    if (!button.dataset.defaultLabel) {
      button.dataset.defaultLabel = defaultText || button.textContent || '';
    }
    button.disabled = isLoading;
    button.textContent = isLoading ? loadingText : (defaultText || button.dataset.defaultLabel);
  };

  const getCheckoutCsrfToken = () => {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    return String(metaToken || window.__csrf || '').trim();
  };

  const markFieldValidity = (field, isValid, msg = '') => {
    if (!field) return isValid;
    field.classList.toggle('field-invalid', !isValid);
    // find or locate adjacent error span (next sibling or within same form-control parent)
    const parent = field.closest('.form-control') || field.parentElement;
    const errSpan = parent ? parent.querySelector('.field-error-msg') : null;
    if (errSpan) {
      errSpan.textContent = isValid ? '' : (msg || errSpan.dataset.defaultMsg || '');
      errSpan.classList.toggle('is-visible', !isValid);
    }
    return isValid;
  };

  const validateField = (field, predicate = null, msg = '') => {
    if (!field) return true;
    const value = String(field.value || '').trim();
    const valid = predicate ? predicate(value) : value.length > 0;
    return markFieldValidity(field, valid, msg);
  };

  // auto-clear field error on user input
  const attachAutoClear = (field) => {
    if (!field || field.__autoClearBound) return;
    field.__autoClearBound = true;
    field.addEventListener('input', () => { if (field.classList.contains('field-invalid')) markFieldValidity(field, true); });
    field.addEventListener('change', () => { if (field.classList.contains('field-invalid')) markFieldValidity(field, true); });
  };

  const normalizeCountryCode = (value) => {
    const digits = String(value || '').replace(/\D+/g, '');
    if (!digits) {
      return '+91';
    }
    return '+' + digits;
  };

  const normalizeCheckoutPhone = (countryCode, rawPhone) => {
    const raw = String(rawPhone || '').trim();
    const hasPlusPrefix = raw.startsWith('+');
    const normalizedCountryCode = normalizeCountryCode(countryCode);
    const digitsOnly = raw.replace(/\D+/g, '');
    const countryDigits = normalizedCountryCode.slice(1);

    let localDigits = digitsOnly;
    if (countryDigits && localDigits.startsWith(countryDigits) && localDigits.length > countryDigits.length) {
      localDigits = localDigits.slice(countryDigits.length);
    }

    const e164 = hasPlusPrefix && digitsOnly
      ? ('+' + digitsOnly)
      : (localDigits ? (normalizedCountryCode + localDigits) : '');
    return {
      countryCode: normalizedCountryCode,
      localNumber: localDigits,
      e164,
    };
  };

  const validateCheckoutPhone = (countryCode, rawPhone) => {
    const normalized = normalizeCheckoutPhone(countryCode, rawPhone);
    if (!normalized.localNumber) {
      return false;
    }
    if (normalized.countryCode === '+91') {
      return normalized.localNumber.length === 10;
    }
    return normalized.localNumber.length >= 6 && normalized.localNumber.length <= 15;
  };

  window.__checkoutNormalizePhone = normalizeCheckoutPhone;
  window.__checkoutValidatePhone = validateCheckoutPhone;

  // ── Payment proof file preview + drag-drop ──
  const initProofUpload = () => {
    const proofInput   = document.getElementById('paymentProof');
    const dropZone     = document.getElementById('proofDropZone');
    const dropLabel    = document.getElementById('proofDropLabel');
    const previewImg   = document.getElementById('proofPreviewImg');
    if (!proofInput || !dropZone) return;

    const showPreview = (file) => {
      if (!file || !file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = (e) => {
        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
        dropLabel.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
        dropZone.style.borderColor = '#80001f';
        dropZone.style.background  = 'rgba(128,0,31,.03)';
      };
      reader.readAsDataURL(file);
    };

    proofInput.addEventListener('change', () => {
      const file = proofInput.files[0];
      if (!file) return;
      const maxMb = 5 * 1024 * 1024;
      const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
      if (!allowed.includes(file.type)) {
        markFieldValidity(proofInput, false, 'File must be a JPG, PNG, GIF, or WebP image.');
        proofInput.value = '';
        return;
      }
      if (file.size > maxMb) {
        markFieldValidity(proofInput, false, 'Image must be under 5 MB.');
        proofInput.value = '';
        return;
      }
      markFieldValidity(proofInput, true);
      showPreview(file);
    });

    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.borderColor = '#80001f'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = '#c8b4bc'; });
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      const file = e.dataTransfer?.files?.[0];
      if (!file) return;
      const dt = new DataTransfer();
      dt.items.add(file);
      proofInput.files = dt.files;
      proofInput.dispatchEvent(new Event('change'));
    });
  };
  document.addEventListener('DOMContentLoaded', initProofUpload);

  const readDraft = () => {
    try {
      return JSON.parse(window.localStorage.getItem(CHECKOUT_DRAFT_KEY) || '{}');
    } catch (error) {
      return {};
    }
  };

  const persistDraft = () => {
    const nextDraft = {};
    form.querySelectorAll('[data-autosave]').forEach((field) => {
      nextDraft[field.dataset.autosave] = field.value || '';
    });
    window.localStorage.setItem(CHECKOUT_DRAFT_KEY, JSON.stringify(nextDraft));
  };

  form.querySelectorAll('[data-autosave]').forEach((field) => {
    const draft = readDraft();
    if (!field.value && typeof draft[field.dataset.autosave] === 'string') {
      field.value = draft[field.dataset.autosave];
    }
    field.addEventListener('input', persistDraft);
    field.addEventListener('change', persistDraft);
  });

  const syncPaymentUi = () => {
    const method = form.querySelector('input[name="payment_method"]:checked')?.value;
    if (upiSection) {
      upiSection.style.display = method === 'upi_manual' ? 'grid' : 'none';
    }
  };

  const syncPaymentType = () => {
    const total = Number(window.__checkoutGrandTotal || 0);
    if (!total) return;
    document.getElementById('upiAmountText').textContent = 'Pay ₹' + total + ' using UPI';
    generateQR(total);
  };

  const syncAddressSection = () => {
    const mode = form.querySelector('input[name="fulfilment_mode"]:checked')?.value;
    if (addressSection) {
      addressSection.style.display = mode === 'delivery' ? 'block' : 'none';
    }
  };

  const syncMobileTotals = () => {
    if (mobileGrandTotal) {
      mobileGrandTotal.textContent = document.getElementById('checkoutGrandTotal')?.textContent || '₹0';
    }
  };

  document.querySelectorAll('[data-collapsible]').forEach((section) => {
    const header = section.querySelector('.checkout-section__header');
    const toggle = () => {
      const isCollapsed = section.classList.toggle('checkout-section--collapsed');
      header?.setAttribute('aria-expanded', String(!isCollapsed));
    };
    header?.addEventListener('click', toggle);
    header?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggle();
      }
    });
  });

  form.querySelectorAll('input[name="payment_method"]').forEach((input) => input.addEventListener('change', syncPaymentUi));
  form.querySelectorAll('input[name="fulfilment_mode"]').forEach((input) => input.addEventListener('change', syncAddressSection));

  ['customerName', 'customerPhone', 'customerEmail', 'deliveryStreet', 'deliveryPincode', 'deliveryDate', 'deliverySlot'].forEach((id) => {
    const field = document.getElementById(id);
    field?.addEventListener('blur', () => {
      if (id === 'customerEmail') {
        validateField(field, (value) => /.+@.+\..+/.test(value));
      } else if (id === 'customerPhone') {
        const countryCode = document.getElementById('customerCountryCode')?.value || '+91';
        validateField(field, (value) => validateCheckoutPhone(countryCode, value));
      } else if (id === 'deliveryPincode') {
        validateField(field, (value) => /^\d{6}$/.test(value));
      } else {
        validateField(field);
      }
    });
  });

  // Dynamic auth-state fields are rendered after load; keep draft + validation wired via delegation.
  form.addEventListener('input', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (target.matches('#customerName, #customerPhone, #customerEmail, #countryCodeSearch')) {
      persistDraft();
    }
  });

  form.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (target.matches('#customerCountryCode')) {
      persistDraft();
    }
  });

  form.addEventListener('blur', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.id === 'customerEmail') {
      validateField(target, (value) => /.+@.+\..+/.test(value));
    } else if (target.id === 'customerPhone') {
      const countryCode = document.getElementById('customerCountryCode')?.value || '+91';
      validateField(target, (value) => validateCheckoutPhone(countryCode, value));
    } else if (target.id === 'customerName') {
      validateField(target);
    }
  }, true);

  syncPaymentUi();
  syncPaymentType();
  syncAddressSection();
  syncMobileTotals();

  document.getElementById('useMyLocationBtn')?.addEventListener('click', function () {
    const statusEl = document.getElementById('locationStatus');
    if (!navigator.geolocation) {
      setFeedback(statusEl, 'Geolocation is not supported by your browser.', 'warn');
      return;
    }
    setFeedback(statusEl, 'Detecting location…', 'info');
    navigator.geolocation.getCurrentPosition(
      async function (pos) {
        try {
          const res = await fetch(
            `https://nominatim.openstreetmap.org/reverse?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}&format=json&addressdetails=1`,
            { headers: { 'Accept-Language': 'en', 'User-Agent': 'Cakeouflage/1.0 (checkout)' } }
          );
          const data = await res.json();
          const addr = data.address || {};
          const street = [addr.road, addr.neighbourhood, addr.suburb, addr.city_district].filter(Boolean).join(', ');
          const pincode = addr.postcode || '';
          if (street) document.getElementById('deliveryStreet').value = street;
          if (pincode) document.getElementById('deliveryPincode').value = pincode;
          persistDraft();
          setFeedback(statusEl, 'Location detected. Please verify before placing order.', 'success');
        } catch (error) {
          setFeedback(statusEl, 'Could not detect address. Please enter it manually.', 'warn');
        }
      },
      function () {
        setFeedback(statusEl, 'Location access denied. Please enter your address manually.', 'warn');
      }
    );
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!window.__checkoutCartReady) {
      setFeedback(checkoutStatus, 'Your cart is empty or invalid. Please return to cart and refresh checkout.', 'warn');
      showToast('Cart is not ready for checkout.', 'warn');
      return;
    }

    const checkoutAuthState = String(window.__checkoutAuthState || 'email-entry');
    const customerName = document.getElementById('customerName');
    const customerPhone = document.getElementById('customerPhone');
    const customerCountryCode = document.getElementById('customerCountryCode');
    const customerEmail = document.getElementById('customerEmail');
    const deliveryDate = document.getElementById('deliveryDate');
    const deliverySlot = document.getElementById('deliverySlot');
    const deliveryStreet = document.getElementById('deliveryStreet');
    const deliveryPincode = document.getElementById('deliveryPincode');
    const paymentProof = document.getElementById('paymentProof');
    const fulfilmentMode = form.querySelector('input[name="fulfilment_mode"]:checked')?.value || 'delivery';
    const paymentMethod = form.querySelector('input[name="payment_method"]:checked')?.value || 'upi_manual';

    // attach auto-clear listeners
    [customerName, customerPhone, customerEmail, deliveryDate, deliverySlot,
     deliveryStreet, deliveryPincode, paymentProof].forEach(attachAutoClear);

    const requireIdentityFields = checkoutAuthState === 'new-user-details' || (checkoutAuthState === 'otp-verification' && !window.__checkoutExistingCustomer);
    const validations = [
      requireIdentityFields
        ? validateField(customerName, null, 'Full name is required')
        : true,
      requireIdentityFields
        ? validateField(customerPhone, (v) => validateCheckoutPhone(customerCountryCode?.value || '+91', v), 'Enter a valid phone number')
        : true,
      validateField(customerEmail, (v) => /.+@.+\..+/.test(v), 'Please enter a valid email address'),
      validateField(deliveryDate, null, 'Please select a delivery date'),
      validateField(deliverySlot, null, 'Please select a delivery slot')
    ];

    if (fulfilmentMode === 'delivery') {
      validations.push(validateField(deliveryStreet, null, 'Delivery address is required'));
      validations.push(validateField(deliveryPincode, (v) => /^\d{6}$/.test(v), 'Enter a valid 6-digit pincode'));
    }

    const screenshotRequired = (typeof window.__screenshotRequired !== 'undefined') ? window.__screenshotRequired : true;
    if (paymentMethod === 'upi_manual' && screenshotRequired) {
      validations.push(markFieldValidity(paymentProof, Boolean(paymentProof?.files?.[0]), 'Payment screenshot is required before placing order'));
    }

    if (validations.some((r) => !r)) {
      const firstInvalid = form.querySelector('.field-invalid');
      if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setFeedback(checkoutStatus, 'Please complete the highlighted fields before placing your order.', 'error');
      showToast('Complete the required fields first.', 'warn');
      return;
    }

    if (!window.otpVerified) {
      const otpSection = document.getElementById('otpStatus') || document.getElementById('sendOtpBtn');
      if (otpSection) otpSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setFeedback(document.getElementById('otpStatus'), 'Verify your email OTP before placing the order.', 'warn');
      setFeedback(checkoutStatus, 'Email verification is still pending.', 'warn');
      showToast('Verify your OTP to continue.', 'warn');
      return;
    }

    const formData = new FormData(form);
    const csrfToken = getCheckoutCsrfToken();
    const resolvedCustomerName = String(customerName?.value || window.__checkoutExistingCustomerUser?.full_name || 'Customer').trim();
    const resolvedCountryCode = String(customerCountryCode?.value || '+91').trim();
    const resolvedCustomerPhoneRaw = String(customerPhone?.value || window.__checkoutExistingCustomerPhone || window.__checkoutExistingCustomerUser?.phone || '').trim();
    const normalizedPhone = normalizeCheckoutPhone(resolvedCountryCode, resolvedCustomerPhoneRaw);
    const fallbackPhone = normalizedPhone.localNumber
      ? (normalizedPhone.countryCode.replace('+', '') + normalizedPhone.localNumber)
      : '';
    const resolvedCustomerPhone = normalizedPhone.e164 || fallbackPhone;
    formData.set('customer_name', resolvedCustomerName);
    formData.set('customer_phone', resolvedCustomerPhone);
    formData.set('customer_country_code', normalizedPhone.countryCode);
    formData.set('customer_phone_local', normalizedPhone.localNumber);
    formData.set('customer_phone_e164', normalizedPhone.e164);
    formData.set('customer_email', String(customerEmail?.value || '').trim());
    formData.set('slot_id', deliverySlot.value);
    formData.set('payment_method', paymentMethod);
    formData.set('payment_type', 'full');
    if (csrfToken && !formData.has('_csrf')) {
      formData.set('_csrf', csrfToken);
    }

    if (fulfilmentMode === 'delivery') {
      formData.set('delivery_street', deliveryStreet.value.trim());
      formData.set('delivery_pincode', deliveryPincode.value.trim());
      const mapsLink = document.getElementById('deliveryMapsLink')?.value.trim() || '';
      if (mapsLink) {
        formData.set('delivery_maps_link', mapsLink);
      }
    }

    setFeedback(checkoutStatus, 'Placing your order…', 'info');
    setButtonState(placeOrderBtn, true, 'Placing Order…', 'Place Order');

    try {
      const response = await fetch(window.BASE_URL + "/api/orders/place", {
        method: 'POST',
        headers: {
          'X-CSRF-Token': csrfToken
        },
        body: formData,
        credentials: 'include'
      });

      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (error) {
        setFeedback(checkoutStatus, 'The checkout response was invalid. Check the local server log for details.', 'error');
        showToast('Checkout response was invalid.', 'error');
        setButtonState(placeOrderBtn, false, '', 'Place Order');
        return;
      }

      if (!data.success) {
        setFeedback(checkoutStatus, data.message || 'Order failed.', 'error');
        showToast(data.message || 'Order failed.', 'error');
        setButtonState(placeOrderBtn, false, '', 'Place Order');
        return;
      }

      window.localStorage.removeItem(CHECKOUT_DRAFT_KEY);
      setFeedback(checkoutStatus, 'Order placed! Your payment is under review. Slot confirmation will be shared shortly.', 'success');
      showToast('Order placed! Payment under review — slot will be confirmed soon.', 'success');
      window.location.href = window.BASE_URL + '/orders?success=1&order=' + data.data.order_number;
    } catch (error) {
      console.error('ERROR:', error);
      setFeedback(checkoutStatus, 'Something went wrong while placing the order.', 'error');
      showToast('Could not place the order.', 'error');
      setButtonState(placeOrderBtn, false, '', 'Place Order');
    }
  });

  window.__checkoutSetFeedback = setFeedback;
  window.__checkoutShowToast = showToast;
  window.__checkoutSetButtonState = setButtonState;
  window.__checkoutGetCsrfToken = getCheckoutCsrfToken;
  window.__checkoutSyncPaymentType = syncPaymentType;
  window.__checkoutSyncMobileTotals = syncMobileTotals;

});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var detectionBanner = document.getElementById('checkoutUserDetection');
  var stepSummary = document.getElementById('step1Summary');

  if (!detectionBanner) {
    return;
  }

  var setBanner = function (message) {
    detectionBanner.hidden = !message;
    detectionBanner.textContent = message || '';
  };

  var applyExistingCustomerMode = function (isExisting, user) {
    var existing = Boolean(isExisting);
    var profile = user || {};

    window.__checkoutExistingCustomer = existing;
    window.__checkoutExistingCustomerUser = profile;

    if (existing) {
      var phone = String(profile.phone || '').trim();
      if (phone) {
        window.__checkoutExistingCustomerPhone = phone;
      }

      if (stepSummary) {
        stepSummary.textContent = 'Welcome back — verify with OTP to continue';
      }
      setBanner('Existing user detected. Verify this email with OTP to continue. Saved profile and addresses will auto-load.');
      return;
    }

    if (stepSummary) {
      stepSummary.textContent = 'New customer — complete profile and verify OTP';
    }
    setBanner('Looks like you are new here. Please enter name and phone, then verify OTP.');
  };

  window.__checkoutApplyExistingCustomerMode = applyExistingCustomerMode;

  fetch('/api/auth/me', {
    method: 'GET',
    credentials: 'include',
    headers: {
      'Accept': 'application/json'
    }
  })
    .then(function (response) { return response.json(); })
    .then(function (payload) {
      if (!payload || payload.success === false || !payload.data || !payload.data.is_authenticated) {
        applyExistingCustomerMode(false, null);
        return;
      }

      applyExistingCustomerMode(true, payload.data.user || {});
    })
    .catch(function () {
      applyExistingCustomerMode(false, null);
    });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const deliveryDateEl = document.getElementById("deliveryDate");
  const deliverySlot   = document.getElementById("deliverySlot"); // hidden native select
  const slotPickerWrap = document.getElementById("slotPickerWrap");
  const slotCardGrid   = document.getElementById("slotCardGrid");
  const slotPickerMsg  = document.getElementById("slotPickerMsg");

  function fmtTime(t) {
    if (!t) return '';
    const parts = t.split(':');
    let h = parseInt(parts[0]), m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    if (h > 12) h -= 12; else if (h === 0) h = 12;
    return `${h}:${m} ${ampm}`;
  }

  function renderSlotCards(slots) {
    slotCardGrid.innerHTML = '';
    slotPickerMsg.textContent = '';

    if (!slots || slots.length === 0) {
      slotPickerMsg.textContent = 'No slots available for the selected date. Please try another date.';
      slotPickerWrap.style.display = 'block';
      return;
    }

    slots.forEach(slot => {
      const booked = parseInt(slot.booked ?? slot.booked_count) || 0;
      const cap    = parseInt(slot.capacity ?? slot.effective_capacity ?? slot.max_orders) || 1;
      const rem    = parseInt(slot.remaining ?? '') || Math.max(0, cap - booked);
      const pct    = Math.min(100, Math.round(booked / cap * 100));
      const isFull   = (slot.is_full === true) || booked >= cap || parseInt(slot.is_exception_closed) === 1;
      const isClosed = parseInt(slot.is_exception_closed) === 1;
      const isFast   = (slot.is_fast_selling === true) || (!isFull && rem < Math.ceil(cap * 0.30) && rem > 0);
      const isRec    = (slot.is_recommended === true) || parseInt(slot.is_recommended) === 1;

      let badges = '';
      if (isClosed)    badges += '<span class="slot-pick-badge slot-pick-badge--closed">🔒 Closed</span>';
      else if (isFull) badges += '<span class="slot-pick-badge slot-pick-badge--full">Full</span>';
      else {
        if (isRec)  badges += '<span class="slot-pick-badge slot-pick-badge--rec">✨ Recommended</span>';
        if (isFast) badges += '<span class="slot-pick-badge slot-pick-badge--fast">⚡ Selling Fast</span>';
      }

      const capBar = `
        <div class="slot-pick-cap-bar">
          <div class="slot-pick-cap-fill" style="width:${pct}%"></div>
        </div>`;

      const remText = rem > 0 ? `${rem} slot${rem > 1 ? 's' : ''} left` : 'No slots left';

      const card = document.createElement('div');
      card.className = 'slot-pick-card' + (isFull ? ' slot-pick-card--disabled slot-pick-card--full' : '');
      card.dataset.slotId = slot.id;
      card.innerHTML = `
        <div class="slot-pick-tick">✓</div>
        <div class="slot-pick-card__name">${slot.slot_label}</div>
        <div class="slot-pick-card__time">${fmtTime(slot.start_time)} – ${fmtTime(slot.end_time)}</div>
        ${badges ? `<div class="slot-pick-card__badges">${badges}</div>` : ''}
        <div class="slot-pick-card__cap"><strong>${booked}</strong> / ${cap} booked · ${remText}</div>
        ${capBar}`;

      if (!isFull) {
        card.addEventListener('click', () => selectSlotCard(slot.id, card));
      }

      slotCardGrid.appendChild(card);
    });

    slotPickerWrap.style.display = 'block';

    // Auto-select recommended or first available
    const recommended = Array.from(slotCardGrid.querySelectorAll('.slot-pick-card:not(.slot-pick-card--disabled)'))
      .find(c => c.querySelector('.slot-pick-badge--rec'));
    const firstAvail  = slotCardGrid.querySelector('.slot-pick-card:not(.slot-pick-card--disabled)');
    const autoTarget  = recommended || firstAvail;
    if (autoTarget) {
      selectSlotCard(autoTarget.dataset.slotId, autoTarget);
    }
  }

  function selectSlotCard(slotId, cardEl) {
    // Clear previous selection
    slotCardGrid.querySelectorAll('.slot-pick-card--selected').forEach(c => c.classList.remove('slot-pick-card--selected'));
    cardEl.classList.add('slot-pick-card--selected');
    // Sync hidden native select
    deliverySlot.innerHTML = `<option value="${slotId}" selected>Selected</option>`;
  }

  deliveryDateEl.addEventListener("change", async function () {
    const date = this.value;
    if (!date) return;

    slotPickerMsg.textContent = 'Loading available slots…';
    slotCardGrid.innerHTML = '';
    slotPickerWrap.style.display = 'block';
    deliverySlot.innerHTML = '<option value="">Loading…</option>';

    try {
      const res  = await fetch(window.BASE_URL + "/api/fulfilment/slots?date=" + date);
      const data = await res.json();

      if (data.success && data.data.items && data.data.items.length > 0) {
        renderSlotCards(data.data.items);
      } else {
        slotPickerMsg.textContent = 'No slots available for this date. Please try another.';
        slotCardGrid.innerHTML = '';
        deliverySlot.innerHTML = '<option value="">No slots available</option>';
      }
    } catch (err) {
      console.error(err);
      slotPickerMsg.textContent = 'Error loading slots. Please try again.';
      deliverySlot.innerHTML = '<option value="">Error loading slots</option>';
    }
  });

});
</script>


<script>
window.__checkoutCartReady = false;

async function loadCheckoutSummary() {
  try {
    const res = await fetch(window.BASE_URL + "/api/cart?auto_public=1",
      {
       credentials: "include"
      }
    );
    const data = await res.json();

    console.log("CART DATA:", data);

    const placeOrderBtn = document.getElementById("placeOrderBtn");
    const checkoutStatus = document.getElementById("checkoutStatus");
    const lockCheckout = (message) => {
      if (window.__checkoutSetButtonState && placeOrderBtn) {
        window.__checkoutSetButtonState(placeOrderBtn, true, '', 'Place Order');
      } else if (placeOrderBtn) {
        placeOrderBtn.disabled = true;
      }
      if (window.__checkoutSetFeedback && checkoutStatus) {
        window.__checkoutSetFeedback(checkoutStatus, message, 'warn');
      }
    };
    const unlockCheckout = () => {
      if (window.__checkoutSetButtonState && placeOrderBtn) {
        window.__checkoutSetButtonState(placeOrderBtn, false, '', 'Place Order');
      } else if (placeOrderBtn) {
        placeOrderBtn.disabled = false;
      }
    };

    if (!data.success) {
      lockCheckout('Unable to load your cart right now. Please refresh or return to cart.');
      return;
    }

    const cart = data.data;
    const isEmptyCart = !cart || Number(cart.item_count || 0) <= 0 || !Array.isArray(cart.items) || cart.items.length === 0;

    if (isEmptyCart) {
      window.__checkoutCartReady = false;
      document.getElementById("checkoutSubtotal").textContent = "₹0";
      document.getElementById("checkoutDiscount").textContent = "₹0";
      document.getElementById("checkoutDeliveryFee").textContent = "₹0";
      document.getElementById("checkoutGrandTotal").textContent = "₹0";
      document.getElementById("upiAmountText").textContent = "Pay ₹0 using UPI";
      document.getElementById("checkoutItemsList").innerHTML = '<div class="checkout-empty-state">Your cart is empty. Please add products before placing an order.</div>';
      const customSummaryEl = document.getElementById('checkoutItemCustomSummary');
      const customListEl = document.getElementById('checkoutItemCustomList');
      if (customListEl) {
        customListEl.innerHTML = '';
      }
      if (customSummaryEl) {
        customSummaryEl.style.display = 'none';
      }

      lockCheckout('Cart is empty. Redirecting you to cart to continue checkout.');
      if (!window.__checkoutEmptyCartWarned && window.__checkoutShowToast) {
        window.__checkoutShowToast('Your cart is empty. Add products to continue checkout.', 'warn');
      }
      window.__checkoutEmptyCartWarned = true;
      window.setTimeout(() => {
        window.location.href = window.BASE_URL + '/cart?empty=1';
      }, 1200);
      return;
    }

    window.__checkoutEmptyCartWarned = false;
    window.__checkoutCartReady = true;
    unlockCheckout();

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
    const preview = window.__checkoutPreview || null;
    const deliveryFee = preview ? preview.delivery_fee : (cart.delivery_fee || 0);
    const grandTotal = preview ? preview.grand_total : (cart.grand_total || 0);

    document.getElementById("checkoutSubtotal").textContent = "₹" + (cart.subtotal || 0);
    document.getElementById("checkoutDiscount").textContent = "₹" + (cart.discount_total || 0);
    document.getElementById("checkoutDeliveryFee").textContent = "₹" + deliveryFee;
  const total = parseFloat(grandTotal || 0);

document.getElementById("checkoutGrandTotal").textContent = "₹" + total;
if (window.__checkoutSyncMobileTotals) {
  window.__checkoutSyncMobileTotals();
}

// 👇 NEW LINE (important)
document.getElementById("upiAmountText").textContent = "Pay ₹" + total + " using UPI";

window.__checkoutGrandTotal = total;
generateQR(total);

const payFullAmt  = document.getElementById('payFullAmount');
if (payFullAmt)  payFullAmt.textContent  = '(₹' + total + ')';

    // ✅ items list
    let html = "";

    (cart.items || []).forEach(item => {
      html += `
        <div class="checkout-item-mini">
          <div class="checkout-item-mini__meta">
            <span class="checkout-item-mini__name">${item.product_name}</span>
            <span class="checkout-item-mini__qty">Qty ${item.quantity}</span>
          </div>
          <strong>₹${item.line_total}</strong>
        </div>
      `;
    });

    document.getElementById("checkoutItemsList").innerHTML = html;

    // Per-item customisation summary
    const customLines = (cart.items || []).filter(i => i.cake_message || (i.topper_name_snapshot && i.topper_name_snapshot !== 'No Topper'));
    const customSummaryEl = document.getElementById('checkoutItemCustomSummary');
    const customListEl = document.getElementById('checkoutItemCustomList');
    if (customListEl && customLines.length > 0) {
      customListEl.innerHTML = customLines.map(i => {
        let parts = [];
        if (i.cake_message) parts.push('🎂 ' + i.cake_message);
        if (i.topper_name_snapshot && i.topper_name_snapshot !== 'No Topper') {
          parts.push('🎀 ' + i.topper_name_snapshot + (parseFloat(i.topper_price||0)>0 ? ' (+₹'+Math.round(i.topper_price)+')' : ''));
        }
        return `<div style="margin-bottom:4px;"><strong>${i.product_name}:</strong> ${parts.join(' | ')}</div>`;
      }).join('');
      if (customSummaryEl) customSummaryEl.style.display = 'block';
    }

  } catch (err) {
    console.error("Cart load error:", err);
  }
}

// run on load
loadCheckoutSummary();

document.getElementById("applyCheckoutCouponBtn")?.addEventListener("click", async () => {
  const code = (document.getElementById("checkoutCouponInput")?.value || "").trim();
  const statusEl = document.getElementById("checkoutCouponStatus");
  const csrfToken = (window.__checkoutGetCsrfToken ? window.__checkoutGetCsrfToken() : String(window.__csrf || '').trim());

  try {
    const response = await fetch(window.BASE_URL + "/api/cart/coupon", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken
      },
      credentials: "include",
      body: JSON.stringify({ code, _csrf: csrfToken })
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

  const qrEl = document.getElementById("upiQR");
  if (qrEl) {
    qrEl.src = qrUrl;
    qrEl.hidden = false;
  }

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
  const root = document.getElementById("checkoutAuthStateRoot");
  const statusEl = document.getElementById("otpStatus");
  const detectionBanner = document.getElementById("checkoutUserDetection");
  const stepSummary = document.getElementById("step1Summary");
  const applyExistingCustomerMode = window.__checkoutApplyExistingCustomerMode || function () {};
  const setFeedback = window.__checkoutSetFeedback || function (element, message) {
    if (element) element.textContent = message || '';
  };
  const showToast = window.__checkoutShowToast || function () {};
  const getCheckoutCsrfToken = () => {
    if (window.__checkoutGetCsrfToken) {
      return window.__checkoutGetCsrfToken();
    }
    return String(window.__csrf || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '').trim();
  };

  if (!root) {
    return;
  }

  const COUNTRY_DIAL_OPTIONS = Object.freeze([
    { code: 'AF', name: 'Afghanistan', dial: '+93', flag: '🇦🇫' },
    { code: 'AL', name: 'Albania', dial: '+355', flag: '🇦🇱' },
    { code: 'DZ', name: 'Algeria', dial: '+213', flag: '🇩🇿' },
    { code: 'AD', name: 'Andorra', dial: '+376', flag: '🇦🇩' },
    { code: 'AO', name: 'Angola', dial: '+244', flag: '🇦🇴' },
    { code: 'AG', name: 'Antigua and Barbuda', dial: '+1', flag: '🇦🇬' },
    { code: 'AR', name: 'Argentina', dial: '+54', flag: '🇦🇷' },
    { code: 'AM', name: 'Armenia', dial: '+374', flag: '🇦🇲' },
    { code: 'AU', name: 'Australia', dial: '+61', flag: '🇦🇺' },
    { code: 'AT', name: 'Austria', dial: '+43', flag: '🇦🇹' },
    { code: 'AZ', name: 'Azerbaijan', dial: '+994', flag: '🇦🇿' },
    { code: 'BS', name: 'Bahamas', dial: '+1', flag: '🇧🇸' },
    { code: 'BH', name: 'Bahrain', dial: '+973', flag: '🇧🇭' },
    { code: 'BD', name: 'Bangladesh', dial: '+880', flag: '🇧🇩' },
    { code: 'BB', name: 'Barbados', dial: '+1', flag: '🇧🇧' },
    { code: 'BY', name: 'Belarus', dial: '+375', flag: '🇧🇾' },
    { code: 'BE', name: 'Belgium', dial: '+32', flag: '🇧🇪' },
    { code: 'BZ', name: 'Belize', dial: '+501', flag: '🇧🇿' },
    { code: 'BJ', name: 'Benin', dial: '+229', flag: '🇧🇯' },
    { code: 'BT', name: 'Bhutan', dial: '+975', flag: '🇧🇹' },
    { code: 'BO', name: 'Bolivia', dial: '+591', flag: '🇧🇴' },
    { code: 'BA', name: 'Bosnia and Herzegovina', dial: '+387', flag: '🇧🇦' },
    { code: 'BW', name: 'Botswana', dial: '+267', flag: '🇧🇼' },
    { code: 'BR', name: 'Brazil', dial: '+55', flag: '🇧🇷' },
    { code: 'BN', name: 'Brunei', dial: '+673', flag: '🇧🇳' },
    { code: 'BG', name: 'Bulgaria', dial: '+359', flag: '🇧🇬' },
    { code: 'BF', name: 'Burkina Faso', dial: '+226', flag: '🇧🇫' },
    { code: 'BI', name: 'Burundi', dial: '+257', flag: '🇧🇮' },
    { code: 'KH', name: 'Cambodia', dial: '+855', flag: '🇰🇭' },
    { code: 'CM', name: 'Cameroon', dial: '+237', flag: '🇨🇲' },
    { code: 'CA', name: 'Canada', dial: '+1', flag: '🇨🇦' },
    { code: 'CV', name: 'Cape Verde', dial: '+238', flag: '🇨🇻' },
    { code: 'CF', name: 'Central African Republic', dial: '+236', flag: '🇨🇫' },
    { code: 'TD', name: 'Chad', dial: '+235', flag: '🇹🇩' },
    { code: 'CL', name: 'Chile', dial: '+56', flag: '🇨🇱' },
    { code: 'CN', name: 'China', dial: '+86', flag: '🇨🇳' },
    { code: 'CO', name: 'Colombia', dial: '+57', flag: '🇨🇴' },
    { code: 'KM', name: 'Comoros', dial: '+269', flag: '🇰🇲' },
    { code: 'CG', name: 'Congo', dial: '+242', flag: '🇨🇬' },
    { code: 'CD', name: 'Congo (DRC)', dial: '+243', flag: '🇨🇩' },
    { code: 'CR', name: 'Costa Rica', dial: '+506', flag: '🇨🇷' },
    { code: 'CI', name: 'Cote d\'Ivoire', dial: '+225', flag: '🇨🇮' },
    { code: 'HR', name: 'Croatia', dial: '+385', flag: '🇭🇷' },
    { code: 'CU', name: 'Cuba', dial: '+53', flag: '🇨🇺' },
    { code: 'CY', name: 'Cyprus', dial: '+357', flag: '🇨🇾' },
    { code: 'CZ', name: 'Czechia', dial: '+420', flag: '🇨🇿' },
    { code: 'DK', name: 'Denmark', dial: '+45', flag: '🇩🇰' },
    { code: 'DJ', name: 'Djibouti', dial: '+253', flag: '🇩🇯' },
    { code: 'DM', name: 'Dominica', dial: '+1', flag: '🇩🇲' },
    { code: 'DO', name: 'Dominican Republic', dial: '+1', flag: '🇩🇴' },
    { code: 'EC', name: 'Ecuador', dial: '+593', flag: '🇪🇨' },
    { code: 'EG', name: 'Egypt', dial: '+20', flag: '🇪🇬' },
    { code: 'SV', name: 'El Salvador', dial: '+503', flag: '🇸🇻' },
    { code: 'GQ', name: 'Equatorial Guinea', dial: '+240', flag: '🇬🇶' },
    { code: 'ER', name: 'Eritrea', dial: '+291', flag: '🇪🇷' },
    { code: 'EE', name: 'Estonia', dial: '+372', flag: '🇪🇪' },
    { code: 'SZ', name: 'Eswatini', dial: '+268', flag: '🇸🇿' },
    { code: 'ET', name: 'Ethiopia', dial: '+251', flag: '🇪🇹' },
    { code: 'FJ', name: 'Fiji', dial: '+679', flag: '🇫🇯' },
    { code: 'FI', name: 'Finland', dial: '+358', flag: '🇫🇮' },
    { code: 'FR', name: 'France', dial: '+33', flag: '🇫🇷' },
    { code: 'GA', name: 'Gabon', dial: '+241', flag: '🇬🇦' },
    { code: 'GM', name: 'Gambia', dial: '+220', flag: '🇬🇲' },
    { code: 'GE', name: 'Georgia', dial: '+995', flag: '🇬🇪' },
    { code: 'DE', name: 'Germany', dial: '+49', flag: '🇩🇪' },
    { code: 'GH', name: 'Ghana', dial: '+233', flag: '🇬🇭' },
    { code: 'GR', name: 'Greece', dial: '+30', flag: '🇬🇷' },
    { code: 'GD', name: 'Grenada', dial: '+1', flag: '🇬🇩' },
    { code: 'GT', name: 'Guatemala', dial: '+502', flag: '🇬🇹' },
    { code: 'GN', name: 'Guinea', dial: '+224', flag: '🇬🇳' },
    { code: 'GW', name: 'Guinea-Bissau', dial: '+245', flag: '🇬🇼' },
    { code: 'GY', name: 'Guyana', dial: '+592', flag: '🇬🇾' },
    { code: 'HT', name: 'Haiti', dial: '+509', flag: '🇭🇹' },
    { code: 'HN', name: 'Honduras', dial: '+504', flag: '🇭🇳' },
    { code: 'HK', name: 'Hong Kong', dial: '+852', flag: '🇭🇰' },
    { code: 'HU', name: 'Hungary', dial: '+36', flag: '🇭🇺' },
    { code: 'IS', name: 'Iceland', dial: '+354', flag: '🇮🇸' },
    { code: 'IN', name: 'India', dial: '+91', flag: '🇮🇳' },
    { code: 'ID', name: 'Indonesia', dial: '+62', flag: '🇮🇩' },
    { code: 'IR', name: 'Iran', dial: '+98', flag: '🇮🇷' },
    { code: 'IQ', name: 'Iraq', dial: '+964', flag: '🇮🇶' },
    { code: 'IE', name: 'Ireland', dial: '+353', flag: '🇮🇪' },
    { code: 'IL', name: 'Israel', dial: '+972', flag: '🇮🇱' },
    { code: 'IT', name: 'Italy', dial: '+39', flag: '🇮🇹' },
    { code: 'JM', name: 'Jamaica', dial: '+1', flag: '🇯🇲' },
    { code: 'JP', name: 'Japan', dial: '+81', flag: '🇯🇵' },
    { code: 'JO', name: 'Jordan', dial: '+962', flag: '🇯🇴' },
    { code: 'KZ', name: 'Kazakhstan', dial: '+7', flag: '🇰🇿' },
    { code: 'KE', name: 'Kenya', dial: '+254', flag: '🇰🇪' },
    { code: 'KI', name: 'Kiribati', dial: '+686', flag: '🇰🇮' },
    { code: 'KW', name: 'Kuwait', dial: '+965', flag: '🇰🇼' },
    { code: 'KG', name: 'Kyrgyzstan', dial: '+996', flag: '🇰🇬' },
    { code: 'LA', name: 'Laos', dial: '+856', flag: '🇱🇦' },
    { code: 'LV', name: 'Latvia', dial: '+371', flag: '🇱🇻' },
    { code: 'LB', name: 'Lebanon', dial: '+961', flag: '🇱🇧' },
    { code: 'LS', name: 'Lesotho', dial: '+266', flag: '🇱🇸' },
    { code: 'LR', name: 'Liberia', dial: '+231', flag: '🇱🇷' },
    { code: 'LY', name: 'Libya', dial: '+218', flag: '🇱🇾' },
    { code: 'LI', name: 'Liechtenstein', dial: '+423', flag: '🇱🇮' },
    { code: 'LT', name: 'Lithuania', dial: '+370', flag: '🇱🇹' },
    { code: 'LU', name: 'Luxembourg', dial: '+352', flag: '🇱🇺' },
    { code: 'MO', name: 'Macao', dial: '+853', flag: '🇲🇴' },
    { code: 'MG', name: 'Madagascar', dial: '+261', flag: '🇲🇬' },
    { code: 'MW', name: 'Malawi', dial: '+265', flag: '🇲🇼' },
    { code: 'MY', name: 'Malaysia', dial: '+60', flag: '🇲🇾' },
    { code: 'MV', name: 'Maldives', dial: '+960', flag: '🇲🇻' },
    { code: 'ML', name: 'Mali', dial: '+223', flag: '🇲🇱' },
    { code: 'MT', name: 'Malta', dial: '+356', flag: '🇲🇹' },
    { code: 'MH', name: 'Marshall Islands', dial: '+692', flag: '🇲🇭' },
    { code: 'MR', name: 'Mauritania', dial: '+222', flag: '🇲🇷' },
    { code: 'MU', name: 'Mauritius', dial: '+230', flag: '🇲🇺' },
    { code: 'MX', name: 'Mexico', dial: '+52', flag: '🇲🇽' },
    { code: 'FM', name: 'Micronesia', dial: '+691', flag: '🇫🇲' },
    { code: 'MD', name: 'Moldova', dial: '+373', flag: '🇲🇩' },
    { code: 'MC', name: 'Monaco', dial: '+377', flag: '🇲🇨' },
    { code: 'MN', name: 'Mongolia', dial: '+976', flag: '🇲🇳' },
    { code: 'ME', name: 'Montenegro', dial: '+382', flag: '🇲🇪' },
    { code: 'MA', name: 'Morocco', dial: '+212', flag: '🇲🇦' },
    { code: 'MZ', name: 'Mozambique', dial: '+258', flag: '🇲🇿' },
    { code: 'MM', name: 'Myanmar', dial: '+95', flag: '🇲🇲' },
    { code: 'NA', name: 'Namibia', dial: '+264', flag: '🇳🇦' },
    { code: 'NR', name: 'Nauru', dial: '+674', flag: '🇳🇷' },
    { code: 'NP', name: 'Nepal', dial: '+977', flag: '🇳🇵' },
    { code: 'NL', name: 'Netherlands', dial: '+31', flag: '🇳🇱' },
    { code: 'NZ', name: 'New Zealand', dial: '+64', flag: '🇳🇿' },
    { code: 'NI', name: 'Nicaragua', dial: '+505', flag: '🇳🇮' },
    { code: 'NE', name: 'Niger', dial: '+227', flag: '🇳🇪' },
    { code: 'NG', name: 'Nigeria', dial: '+234', flag: '🇳🇬' },
    { code: 'KP', name: 'North Korea', dial: '+850', flag: '🇰🇵' },
    { code: 'MK', name: 'North Macedonia', dial: '+389', flag: '🇲🇰' },
    { code: 'NO', name: 'Norway', dial: '+47', flag: '🇳🇴' },
    { code: 'OM', name: 'Oman', dial: '+968', flag: '🇴🇲' },
    { code: 'PK', name: 'Pakistan', dial: '+92', flag: '🇵🇰' },
    { code: 'PW', name: 'Palau', dial: '+680', flag: '🇵🇼' },
    { code: 'PS', name: 'Palestine', dial: '+970', flag: '🇵🇸' },
    { code: 'PA', name: 'Panama', dial: '+507', flag: '🇵🇦' },
    { code: 'PG', name: 'Papua New Guinea', dial: '+675', flag: '🇵🇬' },
    { code: 'PY', name: 'Paraguay', dial: '+595', flag: '🇵🇾' },
    { code: 'PE', name: 'Peru', dial: '+51', flag: '🇵🇪' },
    { code: 'PH', name: 'Philippines', dial: '+63', flag: '🇵🇭' },
    { code: 'PL', name: 'Poland', dial: '+48', flag: '🇵🇱' },
    { code: 'PT', name: 'Portugal', dial: '+351', flag: '🇵🇹' },
    { code: 'QA', name: 'Qatar', dial: '+974', flag: '🇶🇦' },
    { code: 'RO', name: 'Romania', dial: '+40', flag: '🇷🇴' },
    { code: 'RU', name: 'Russia', dial: '+7', flag: '🇷🇺' },
    { code: 'RW', name: 'Rwanda', dial: '+250', flag: '🇷🇼' },
    { code: 'KN', name: 'Saint Kitts and Nevis', dial: '+1', flag: '🇰🇳' },
    { code: 'LC', name: 'Saint Lucia', dial: '+1', flag: '🇱🇨' },
    { code: 'VC', name: 'Saint Vincent and the Grenadines', dial: '+1', flag: '🇻🇨' },
    { code: 'WS', name: 'Samoa', dial: '+685', flag: '🇼🇸' },
    { code: 'SM', name: 'San Marino', dial: '+378', flag: '🇸🇲' },
    { code: 'ST', name: 'Sao Tome and Principe', dial: '+239', flag: '🇸🇹' },
    { code: 'SA', name: 'Saudi Arabia', dial: '+966', flag: '🇸🇦' },
    { code: 'SN', name: 'Senegal', dial: '+221', flag: '🇸🇳' },
    { code: 'RS', name: 'Serbia', dial: '+381', flag: '🇷🇸' },
    { code: 'SC', name: 'Seychelles', dial: '+248', flag: '🇸🇨' },
    { code: 'SL', name: 'Sierra Leone', dial: '+232', flag: '🇸🇱' },
    { code: 'SG', name: 'Singapore', dial: '+65', flag: '🇸🇬' },
    { code: 'SK', name: 'Slovakia', dial: '+421', flag: '🇸🇰' },
    { code: 'SI', name: 'Slovenia', dial: '+386', flag: '🇸🇮' },
    { code: 'SB', name: 'Solomon Islands', dial: '+677', flag: '🇸🇧' },
    { code: 'SO', name: 'Somalia', dial: '+252', flag: '🇸🇴' },
    { code: 'ZA', name: 'South Africa', dial: '+27', flag: '🇿🇦' },
    { code: 'KR', name: 'South Korea', dial: '+82', flag: '🇰🇷' },
    { code: 'SS', name: 'South Sudan', dial: '+211', flag: '🇸🇸' },
    { code: 'ES', name: 'Spain', dial: '+34', flag: '🇪🇸' },
    { code: 'LK', name: 'Sri Lanka', dial: '+94', flag: '🇱🇰' },
    { code: 'SD', name: 'Sudan', dial: '+249', flag: '🇸🇩' },
    { code: 'SR', name: 'Suriname', dial: '+597', flag: '🇸🇷' },
    { code: 'SE', name: 'Sweden', dial: '+46', flag: '🇸🇪' },
    { code: 'CH', name: 'Switzerland', dial: '+41', flag: '🇨🇭' },
    { code: 'SY', name: 'Syria', dial: '+963', flag: '🇸🇾' },
    { code: 'TW', name: 'Taiwan', dial: '+886', flag: '🇹🇼' },
    { code: 'TJ', name: 'Tajikistan', dial: '+992', flag: '🇹🇯' },
    { code: 'TZ', name: 'Tanzania', dial: '+255', flag: '🇹🇿' },
    { code: 'TH', name: 'Thailand', dial: '+66', flag: '🇹🇭' },
    { code: 'TL', name: 'Timor-Leste', dial: '+670', flag: '🇹🇱' },
    { code: 'TG', name: 'Togo', dial: '+228', flag: '🇹🇬' },
    { code: 'TO', name: 'Tonga', dial: '+676', flag: '🇹🇴' },
    { code: 'TT', name: 'Trinidad and Tobago', dial: '+1', flag: '🇹🇹' },
    { code: 'TN', name: 'Tunisia', dial: '+216', flag: '🇹🇳' },
    { code: 'TR', name: 'Turkiye', dial: '+90', flag: '🇹🇷' },
    { code: 'TM', name: 'Turkmenistan', dial: '+993', flag: '🇹🇲' },
    { code: 'TV', name: 'Tuvalu', dial: '+688', flag: '🇹🇻' },
    { code: 'UG', name: 'Uganda', dial: '+256', flag: '🇺🇬' },
    { code: 'UA', name: 'Ukraine', dial: '+380', flag: '🇺🇦' },
    { code: 'AE', name: 'United Arab Emirates', dial: '+971', flag: '🇦🇪' },
    { code: 'GB', name: 'United Kingdom', dial: '+44', flag: '🇬🇧' },
    { code: 'US', name: 'United States', dial: '+1', flag: '🇺🇸' },
    { code: 'UY', name: 'Uruguay', dial: '+598', flag: '🇺🇾' },
    { code: 'UZ', name: 'Uzbekistan', dial: '+998', flag: '🇺🇿' },
    { code: 'VU', name: 'Vanuatu', dial: '+678', flag: '🇻🇺' },
    { code: 'VA', name: 'Vatican City', dial: '+379', flag: '🇻🇦' },
    { code: 'VE', name: 'Venezuela', dial: '+58', flag: '🇻🇪' },
    { code: 'VN', name: 'Vietnam', dial: '+84', flag: '🇻🇳' },
    { code: 'YE', name: 'Yemen', dial: '+967', flag: '🇾🇪' },
    { code: 'ZM', name: 'Zambia', dial: '+260', flag: '🇿🇲' },
    { code: 'ZW', name: 'Zimbabwe', dial: '+263', flag: '🇿🇼' }
  ]);

  const countryEntryLabel = (entry) => `${entry.flag} ${entry.name} (${entry.dial})`;
  const normalizeCountryCode = (value) => {
    const digits = String(value || '').replace(/\D+/g, '');
    return digits ? ('+' + digits) : '+91';
  };

  const syncCountryOptions = () => {
    const select = root.querySelector('#customerCountryCode');
    if (!select) {
      return;
    }

    const searchTerm = String(context.countrySearch || '').trim().toLowerCase();
    const activeCode = normalizeCountryCode(context.countryCode || select.value || '+91');
    const filtered = COUNTRY_DIAL_OPTIONS.filter((entry) => {
      if (!searchTerm) {
        return true;
      }
      return entry.name.toLowerCase().includes(searchTerm)
        || entry.code.toLowerCase().includes(searchTerm)
        || entry.dial.includes(searchTerm.replace(/\s+/g, ''));
    });

    const list = filtered.length ? filtered : COUNTRY_DIAL_OPTIONS;
    select.innerHTML = list.map((entry) => {
      const selected = entry.dial === activeCode ? ' selected' : '';
      return `<option value="${entry.dial}" data-country="${entry.code}"${selected}>${countryEntryLabel(entry)}</option>`;
    }).join('');

    if (!select.value) {
      select.value = activeCode;
    }
    context.countryCode = normalizeCountryCode(select.value);
  };

  const context = {
    email: String(root.dataset.prefillEmail || '').trim(),
    name: String(root.dataset.prefillName || '').trim(),
    phone: String(root.dataset.prefillPhone || '').trim(),
    countryCode: '+91',
    countrySearch: '',
    otp: '',
    otpFlow: 'existing',
    existingUser: null,
    otpSent: false,
    cooldownUntil: 0
  };

  const splitPhoneForContext = (rawPhone) => {
    const cleaned = String(rawPhone || '').trim();
    if (!cleaned) {
      return { countryCode: '+91', localNumber: '' };
    }

    if (cleaned.startsWith('+')) {
      const digits = cleaned.replace(/\D+/g, '');
      const sorted = COUNTRY_DIAL_OPTIONS
        .map((entry) => entry.dial)
        .sort((a, b) => b.length - a.length);
      for (const dial of sorted) {
        const dialDigits = dial.replace('+', '');
        if (digits.startsWith(dialDigits) && digits.length > dialDigits.length) {
          return {
            countryCode: dial,
            localNumber: digits.slice(dialDigits.length),
          };
        }
      }
      return { countryCode: '+91', localNumber: digits };
    }

    return { countryCode: '+91', localNumber: cleaned.replace(/\D+/g, '') };
  };

  const initialPhone = splitPhoneForContext(context.phone);
  context.countryCode = initialPhone.countryCode;
  context.phone = initialPhone.localNumber;

  const syncPhoneHiddenValue = () => {
    const hidden = root.querySelector('#customerPhoneE164');
    if (!hidden) {
      return;
    }
    const normalizeFn = window.__checkoutNormalizePhone || function (countryCode, rawPhone) {
      const normalizedCountryCode = normalizeCountryCode(countryCode);
      const localNumber = String(rawPhone || '').replace(/\D+/g, '');
      return {
        countryCode: normalizedCountryCode,
        localNumber,
        e164: localNumber ? (normalizedCountryCode + localNumber) : '',
      };
    };
    const normalized = normalizeFn(context.countryCode, context.phone);
    hidden.value = normalized.e164 || '';
  };

  const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  let lookupTimer = null;
  let cooldownTimer = null;
  let otpVerifying = false;
  let currentState = (root.dataset.isLoggedIn === '1' || window.otpVerified) ? 'authenticated' : 'email-entry';

  const setBanner = (message) => {
    if (!detectionBanner) return;
    detectionBanner.hidden = !message;
    detectionBanner.textContent = message || '';
  };

  const readCooldownUntil = () => {
    const value = Number(window.localStorage.getItem(OTP_STORAGE_KEY) || '0');
    return Number.isFinite(value) ? value : 0;
  };

  const otpGridMarkup = () => `
    <div class="otp-grid" id="checkoutOtpGrid" aria-label="OTP input">
      <input class="otp-cell" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" data-otp-slot="0">
      <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="1">
      <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="2">
      <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="3">
      <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="4">
      <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="5">
    </div>
    <input type="hidden" id="otpInput" maxlength="6" value="${context.otp}">
    <p class="otp-note">Paste the code from your email or type it one box at a time.</p>
  `;

  const render = () => {
    const emailValue = escapeHtml(context.email);
    const emailReadonly = ['existing-user-otp', 'new-user-details', 'otp-verification', 'authenticated'].includes(currentState) ? 'readonly' : '';
    const checkingDisabled = currentState === 'checking-email' ? 'disabled' : '';

    let body = '';
    if (currentState === 'email-entry' || currentState === 'checking-email') {
      body = `
        <label class="form-control">
          <span class="form-label">Email Address <span class="form-required">*</span></span>
          <input id="customerEmail" name="customer_email" type="email" required autocomplete="email" placeholder="you@email.com" value="${emailValue}" ${checkingDisabled} data-autosave="customer_email">
          <span class="field-error-msg" aria-live="polite"></span>
        </label>
        <div class="checkout-inline-banner">We'll check if you already have an account.</div>
        <button type="button" id="continueEmailBtn" class="btn btn--primary btn--lg btn--block checkout-auth-button" ${checkingDisabled}>${currentState === 'checking-email' ? 'Checking…' : 'Continue'}</button>
      `;
      if (stepSummary) {
        stepSummary.textContent = 'Email-first smart verification';
      }
    } else if (currentState === 'existing-user-otp' || (currentState === 'otp-verification' && context.otpFlow === 'existing')) {
      body = `
        <div class="checkout-inline-banner">Welcome Back 👋</div>
        <label class="form-control">
          <span class="form-label">Email Address</span>
          <input id="customerEmail" name="customer_email" type="email" required autocomplete="email" value="${emailValue}" ${emailReadonly} data-autosave="customer_email">
          <span class="field-error-msg" aria-live="polite"></span>
        </label>
        <div class="checkout-auth-row">
          <button type="button" id="sendOtpBtn" class="btn btn--primary btn--lg btn--block checkout-auth-button">${context.otpSent ? 'Resend OTP' : 'Send Verification OTP'}</button>
          <div class="checkout-auth-meta">
            <span>Enter Verification OTP</span>
            <span id="otpCooldownHint">Takes a few seconds</span>
          </div>
          <div id="otpNotice" class="otp-notice" aria-live="polite"></div>
        </div>
        <div id="otpSection" class="checkout-auth-step is-visible">
          ${otpGridMarkup()}
        </div>
      `;
      if (stepSummary) {
        stepSummary.textContent = 'Welcome back — verify with OTP to continue';
      }
    } else if (currentState === 'new-user-details' || (currentState === 'otp-verification' && context.otpFlow === 'new')) {
      const otpForNew = currentState === 'otp-verification' && context.otpFlow === 'new';
      body = `
        <div class="checkout-inline-banner">Looks like you're new here.</div>
        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">Full Name <span class="form-required">*</span></span>
            <input id="customerName" name="customer_name" ${otpForNew ? 'readonly' : ''} required autocomplete="name" placeholder="Priya Sharma" value="${escapeHtml(context.name)}" data-autosave="customer_name">
            <span class="field-error-msg" aria-live="polite"></span>
          </label>
          <label class="form-control">
            <span class="form-label">Phone Number <span class="form-required">*</span></span>
            <div class="checkout-phone-group">
              <div class="checkout-phone-group__country">
                <input
                  id="countryCodeSearch"
                  type="search"
                  placeholder="Search country"
                  value="${escapeHtml(context.countrySearch)}"
                  ${otpForNew ? 'readonly' : ''}
                >
                <select id="customerCountryCode" name="customer_country_code" data-autosave="customer_country_code" ${otpForNew ? 'disabled' : ''}></select>
              </div>
              <input
                id="customerPhone"
                name="customer_phone_local"
                ${otpForNew ? 'readonly' : ''}
                type="tel"
                inputmode="numeric"
                autocomplete="tel-national"
                placeholder="9876543210"
                value="${escapeHtml(context.phone)}"
                data-autosave="customer_phone_local"
              >
            </div>
            <input type="hidden" id="customerPhoneE164" name="customer_phone_e164" value="">
            <span class="field-error-msg" aria-live="polite"></span>
          </label>
        </div>
        <label class="form-control">
          <span class="form-label">Email Address <span class="form-required">*</span></span>
          <input id="customerEmail" name="customer_email" type="email" required autocomplete="email" value="${emailValue}" readonly data-autosave="customer_email">
          <span class="field-error-msg" aria-live="polite"></span>
        </label>
        <div class="checkout-auth-row">
          <button type="button" id="sendOtpBtn" class="btn btn--primary btn--lg btn--block checkout-auth-button">${otpForNew ? 'Resend OTP' : 'Send Verification OTP'}</button>
          <div class="checkout-auth-meta">
            <span>${otpForNew ? 'Enter Verification OTP' : 'Send OTP to continue'}</span>
            <span id="otpCooldownHint">Takes a few seconds</span>
          </div>
          <div id="otpNotice" class="otp-notice" aria-live="polite"></div>
        </div>
        ${otpForNew ? `<div id="otpSection" class="checkout-auth-step is-visible">${otpGridMarkup()}</div>` : ''}
      `;
      if (stepSummary) {
        stepSummary.textContent = 'New customer — complete profile and verify OTP';
      }
    } else {
      body = `
        <div class="checkout-login-note">✅ Authenticated — contact details are ready for checkout.</div>
        <label class="form-control">
          <span class="form-label">Email Address</span>
          <input id="customerEmail" name="customer_email" type="email" required autocomplete="email" value="${emailValue}" readonly data-autosave="customer_email">
          <span class="field-error-msg" aria-live="polite"></span>
        </label>
      `;
      if (stepSummary) {
        stepSummary.textContent = 'Authenticated — continue to checkout';
      }
    }

    root.innerHTML = body;
    syncCountryOptions();

    const countrySearch = root.querySelector('#countryCodeSearch');
    if (countrySearch && !countrySearch.dataset.bound) {
      countrySearch.dataset.bound = '1';
      countrySearch.addEventListener('input', () => {
        context.countrySearch = String(countrySearch.value || '');
        syncCountryOptions();
      });
    }

    syncPhoneHiddenValue();

    const otpInput = root.querySelector('#otpInput');
    if (otpInput) {
      otpInput.value = context.otp;
      const slots = Array.from(root.querySelectorAll('#checkoutOtpGrid [data-otp-slot]'));
      context.otp.split('').forEach((digit, idx) => {
        if (slots[idx]) {
          slots[idx].value = digit;
        }
      });
    }
    applyCooldownUi();
  };

  const setState = (nextState, message) => {
    currentState = nextState;
    window.__checkoutAuthState = nextState;
    if (window.__checkoutSetAuthState && window.__checkoutSetAuthState !== setState) {
      window.__checkoutSetAuthState(nextState, message || '');
    }
    setBanner(message || '');
    render();
  };
  window.__checkoutSetAuthState = setState;

  const applyCooldownUi = () => {
    const sendBtn = root.querySelector('#sendOtpBtn');
    const cooldownHint = root.querySelector('#otpCooldownHint');
    if (!sendBtn) {
      return;
    }
    const now = Date.now();
    if (context.cooldownUntil <= now) {
      sendBtn.disabled = false;
      return;
    }
    const remainingSec = Math.ceil((context.cooldownUntil - now) / 1000);
    sendBtn.disabled = true;
    sendBtn.textContent = `Resend OTP in ${remainingSec}s`;
    if (cooldownHint) {
      cooldownHint.textContent = `Resend OTP in ${remainingSec}s`;
    }
  };

  const startCooldown = (untilEpochMs) => {
    context.cooldownUntil = untilEpochMs;
    window.localStorage.setItem(OTP_STORAGE_KEY, String(untilEpochMs));
    if (cooldownTimer) {
      window.clearInterval(cooldownTimer);
    }
    applyCooldownUi();
    cooldownTimer = window.setInterval(() => {
      if (context.cooldownUntil <= Date.now()) {
        if (cooldownTimer) {
          window.clearInterval(cooldownTimer);
          cooldownTimer = null;
        }
        window.localStorage.removeItem(OTP_STORAGE_KEY);
        context.cooldownUntil = 0;
      }
      applyCooldownUi();
    }, 250);
  };

  const hydrateCheckoutFromAccount = async () => {
    const streetInput = document.getElementById('deliveryStreet');
    const pincodeInput = document.getElementById('deliveryPincode');

    try {
      const profileRes = await fetch('/api/account/profile', {
        method: 'GET',
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const profilePayload = await profileRes.json();
      if (profilePayload?.success && profilePayload?.data?.user) {
        const user = profilePayload.data.user;
        context.name = String(user.full_name || context.name || '').trim();
        context.phone = String(user.phone || context.phone || '').trim();
        context.email = String(user.email || context.email || '').trim();
        if (context.phone) {
          window.__checkoutExistingCustomerPhone = context.phone;
        }
        applyExistingCustomerMode(true, user);
      }
    } catch (error) {
      // Non-blocking hydration path.
    }

    try {
      const addressesRes = await fetch('/api/account/addresses', {
        method: 'GET',
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const addressesPayload = await addressesRes.json();
      const items = Array.isArray(addressesPayload?.data?.items) ? addressesPayload.data.items : [];
      if (!items.length) return;
      const defaultAddress = items.find((item) => Number(item.is_default) === 1) || items[0];
      if (streetInput && !streetInput.value.trim()) {
        const streetParts = [
          String(defaultAddress.line1 || '').trim(),
          String(defaultAddress.line2 || '').trim(),
          String(defaultAddress.landmark || '').trim()
        ].filter(Boolean);
        streetInput.value = streetParts.join(', ');
      }
      if (pincodeInput && !pincodeInput.value.trim()) {
        pincodeInput.value = String(defaultAddress.postal_code || '').trim();
      }
    } catch (error) {
      // Non-blocking hydration path.
    }
  };

  const runOtpVerification = async () => {
    if (otpVerifying) return;
    const otpValue = String(context.otp || '').trim();
    const csrfToken = getCheckoutCsrfToken();
    if (!context.email) {
      setFeedback(statusEl, 'Enter your email before verifying OTP.', 'error');
      return;
    }
    if (otpValue.length !== 6) {
      setFeedback(statusEl, 'Enter the full 6-digit OTP.', 'error');
      return;
    }

    otpVerifying = true;
    setState('otp-verification', 'Verifying OTP…');
    setFeedback(statusEl, 'Verifying OTP…', 'info');

    try {
      const res = await fetch('/api/verify-otp', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          email: context.email,
          otp: otpValue,
          name: context.name || 'Customer',
          phone: context.phone || '',
          _csrf: csrfToken
        }),
        credentials: 'same-origin'
      });
      const data = await res.json();

      if (!data.success) {
        otpVerifying = false;
        setFeedback(statusEl, data.message || 'OTP verification failed.', 'error');
        setState(context.otpFlow === 'new' ? 'new-user-details' : 'existing-user-otp', 'Verification failed. Enter OTP again.');
        return;
      }

      window.otpVerified = true;
      context.otp = '';
      setState('authenticated', 'OTP verified. Checkout unlocked.');
      setFeedback(statusEl, '✅ Email verified. Checkout unlocked.', 'success');
      showToast('Email verified. Checkout unlocked.', 'success');
      await hydrateCheckoutFromAccount();
      if (window.__checkoutSteps) {
        window.__checkoutSteps.complete('step-contact', '✔ ' + context.email);
      }
      await loadCheckoutSummary();
    } catch (error) {
      otpVerifying = false;
      setFeedback(statusEl, 'Unable to verify OTP right now.', 'error');
      setState(context.otpFlow === 'new' ? 'new-user-details' : 'existing-user-otp', 'Unable to verify OTP right now.');
    }
  };
  window.__checkoutVerifyOtp = runOtpVerification;

  const requestOtp = async () => {
    const csrfToken = getCheckoutCsrfToken();
    if (!context.email) {
      setFeedback(statusEl, 'Enter your email before requesting OTP.', 'error');
      return;
    }
    if (context.otpFlow === 'new') {
      const nameOk = String(context.name || '').trim().length >= 2;
      const validator = window.__checkoutValidatePhone || function (countryCode, rawPhone) {
        const digits = String(rawPhone || '').replace(/\D+/g, '');
        if (normalizeCountryCode(countryCode) === '+91') {
          return digits.length === 10;
        }
        return digits.length >= 6 && digits.length <= 15;
      };
      const phoneOk = validator(context.countryCode, context.phone);
      if (!nameOk || !phoneOk) {
        setFeedback(statusEl, 'For new checkout, enter full name and phone before requesting OTP.', 'warn');
        return;
      }
    }

    setFeedback(statusEl, 'Sending OTP…', 'info');
    try {
      const res = await fetch('/api/send-otp', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ email: context.email, name: context.name || 'Customer', _csrf: csrfToken }),
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data.success) {
        const message = String(data.message || 'Failed to send OTP');
        if (res.status === 429 || message.includes('60 seconds before requesting a new OTP')) {
          const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
          startCooldown(cooldownUntil);
          setFeedback(statusEl, message, 'warn');
          return;
        }
        setFeedback(statusEl, message, 'error');
        return;
      }

      context.otpSent = true;
      context.otp = '';
      const existingCustomer = Boolean(data?.exists ?? data?.data?.exists ?? data?.data?.existing_customer);
      context.otpFlow = existingCustomer ? 'existing' : context.otpFlow;
      applyExistingCustomerMode(existingCustomer, data?.data?.user || null);
      setState('otp-verification', 'OTP sent. Enter the 6-digit code.');
      setFeedback(statusEl, 'OTP sent successfully. Enter the 6-digit code.', 'success');
      startCooldown(Date.now() + OTP_COOLDOWN_MS);
    } catch (error) {
      setFeedback(statusEl, 'Unable to send OTP right now.', 'error');
    }
  };

  const detectEmail = async () => {
    const email = String(context.email || '').trim();
    if (!/.+@.+\..+/.test(email)) {
      return;
    }
    const csrfToken = getCheckoutCsrfToken();
    setState('checking-email', 'Checking account…');
    try {
      const response = await fetch('/api/auth/check-user', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        credentials: 'same-origin',
        body: JSON.stringify({ email, _csrf: csrfToken })
      });
      const payload = await response.json();
      if (!response.ok) {
        console.error('[checkout] check-user failed:', response.status, payload?.message || '');
        setState('email-entry', 'Something went wrong. Please try again.');
        return;
      }
      const exists = Boolean(payload?.exists ?? payload?.data?.exists ?? payload?.data?.existing_customer);
      if (exists) {
        context.otpFlow = 'existing';
        context.existingUser = payload?.data?.user || null;
        if (context.existingUser?.full_name) {
          context.name = String(context.existingUser.full_name).trim();
        }
        if (context.existingUser?.phone) {
          context.phone = String(context.existingUser.phone).trim();
          window.__checkoutExistingCustomerPhone = context.phone;
        }
        applyExistingCustomerMode(true, context.existingUser);
        setState('existing-user-otp', 'Welcome back! Enter OTP to continue.');
      } else {
        context.otpFlow = 'new';
        applyExistingCustomerMode(false, null);
        setState('new-user-details', "Looks like you're new here.");
      }
    } catch (error) {
      setState('email-entry', 'Enter your email to continue checkout.');
    }
  };

  root.addEventListener('click', async (event) => {
    const target = event.target instanceof HTMLElement ? event.target.closest('button') : null;
    if (!target) return;

    if (target.id === 'continueEmailBtn') {
      await detectEmail();
      return;
    }
    if (target.id === 'sendOtpBtn') {
      await requestOtp();
    }
  });

  root.addEventListener('input', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;

    if (target.id === 'customerEmail') {
      context.email = String(target.value || '').trim();
      if (lookupTimer) {
        window.clearTimeout(lookupTimer);
      }
      if (currentState === 'email-entry' || currentState === 'checking-email') {
        lookupTimer = window.setTimeout(() => {
          void detectEmail();
        }, 500);
      }
      return;
    }

    if (target.id === 'customerName') {
      context.name = String(target.value || '').trim();
      return;
    }

    if (target.id === 'customerPhone') {
      const digitsOnly = String(target.value || '').replace(/\D+/g, '');
      target.value = digitsOnly;
      context.phone = digitsOnly;
      syncPhoneHiddenValue();
      return;
    }

    if (target.hasAttribute('data-otp-slot')) {
      const slots = Array.from(root.querySelectorAll('#checkoutOtpGrid [data-otp-slot]'));
      const currentIndex = Number(target.getAttribute('data-otp-slot'));
      const digits = String(target.value || '').replace(/\D+/g, '');
      if (digits.length > 1) {
        digits.split('').forEach((digit, offset) => {
          const slot = slots[currentIndex + offset];
          if (slot) slot.value = digit;
        });
      } else {
        target.value = digits.slice(0, 1);
      }
      const otp = slots.map((slot) => String(slot.value || '').replace(/\D+/g, '').slice(-1)).join('').slice(0, 6);
      context.otp = otp;
      const otpInput = root.querySelector('#otpInput');
      if (otpInput) {
        otpInput.value = otp;
      }
      if (target.value && currentIndex < 5) {
        slots[currentIndex + 1]?.focus();
      }
      if (otp.length === 6) {
        window.setTimeout(() => {
          void runOtpVerification();
        }, 180);
      }
    }
  });

  root.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement)) {
      return;
    }
    if (target.id === 'customerCountryCode') {
      context.countryCode = normalizeCountryCode(target.value || '+91');
      syncPhoneHiddenValue();
    }
  });

  root.addEventListener('keydown', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement) || !target.hasAttribute('data-otp-slot')) return;
    if (event.key !== 'Backspace' || target.value) return;
    const slots = Array.from(root.querySelectorAll('#checkoutOtpGrid [data-otp-slot]'));
    const index = Number(target.getAttribute('data-otp-slot'));
    if (index > 0) {
      slots[index - 1].value = '';
      slots[index - 1].focus();
      context.otp = slots.map((slot) => String(slot.value || '').replace(/\D+/g, '').slice(-1)).join('').slice(0, 6);
    }
  });

  root.addEventListener('paste', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement) || !target.hasAttribute('data-otp-slot')) return;
    event.preventDefault();
    const text = (event.clipboardData || window.clipboardData)?.getData('text') || '';
    const digits = text.replace(/\D+/g, '').slice(0, 6).split('');
    if (!digits.length) return;
    const slots = Array.from(root.querySelectorAll('#checkoutOtpGrid [data-otp-slot]'));
    digits.forEach((digit, index) => {
      if (slots[index]) slots[index].value = digit;
    });
    context.otp = digits.join('');
    const otpInput = root.querySelector('#otpInput');
    if (otpInput) otpInput.value = context.otp;
    if (context.otp.length === 6) {
      window.setTimeout(() => {
        void runOtpVerification();
      }, 180);
    }
  });

  context.cooldownUntil = readCooldownUntil();
  if (context.cooldownUntil > Date.now()) {
    startCooldown(context.cooldownUntil);
  }

  setState(currentState, currentState === 'authenticated' ? 'Authenticated. Continue checkout.' : 'Enter your email to continue checkout.');
});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {

  const previewBtn = document.getElementById("previewCheckoutBtn");
  const summaryPreviewBtn = document.getElementById("summaryPreviewBtn");
  const statusEl = document.getElementById("checkoutStatus");
  const setFeedback = window.__checkoutSetFeedback || function (element, message) {
    if (element) element.textContent = message || '';
  };
  const showToast = window.__checkoutShowToast || function () {};
  const setButtonState = window.__checkoutSetButtonState || function (button, isLoading, loadingText, defaultText) {
    if (!button) return;
    button.disabled = isLoading;
    button.textContent = isLoading ? loadingText : defaultText;
  };

  if (!previewBtn) return;

  const runPreview = async (button) => {
    const postalCode = document.getElementById("deliveryPincode")?.value.trim() || '';
    const fulfilmentMode = document.querySelector('input[name="fulfilment_mode"]:checked')?.value;

    if (!postalCode && fulfilmentMode !== "pickup") {
      setFeedback(statusEl, "Enter the delivery pincode before previewing totals.", "warn");
      showToast("Enter the delivery pincode first.", "warn");
      return;
    }

    setButtonState(button, true, "Previewing…", button?.textContent || "Preview Total");
    setFeedback(statusEl, "Refreshing delivery fee and total…", "info");
    const csrfToken = (window.__checkoutGetCsrfToken ? window.__checkoutGetCsrfToken() : String(window.__csrf || '').trim());

    try {
      const res = await fetch(window.BASE_URL + "/api/checkout/preview", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify({
          postal_code: postalCode,
          fulfilment_mode: fulfilmentMode,
          _csrf: csrfToken
        }),
        credentials: "include"
      });

      const data = await res.json();

      if (!data.success) {
        setFeedback(statusEl, data.message || "Preview failed.", "error");
        showToast(data.message || "Preview failed.", "error");
        setButtonState(button, false, '', button?.dataset.defaultLabel || button?.textContent || "Preview Total");
        return;
      }

      const preview = data.data;
      window.__checkoutPreview = preview;

      document.getElementById("checkoutDeliveryFee").textContent = "₹" + preview.delivery_fee;
      document.getElementById("checkoutGrandTotal").textContent = "₹" + preview.grand_total;
      window.__checkoutGrandTotal = Number(preview.grand_total || 0);
      if (window.__checkoutSyncMobileTotals) {
        window.__checkoutSyncMobileTotals();
      }
      if (window.__checkoutSyncPaymentType) {
        window.__checkoutSyncPaymentType();
      }
      setFeedback(statusEl, "Totals refreshed successfully.", "success");
      showToast("Checkout total updated.", "success");
      setButtonState(button, false, '', button?.dataset.defaultLabel || button?.textContent || "Preview Total");

    } catch (err) {
      console.error(err);
      setFeedback(statusEl, "Preview failed. Try again in a moment.", "error");
      showToast("Preview failed.", "error");
      setButtonState(button, false, '', button?.dataset.defaultLabel || button?.textContent || "Preview Total");
    }

  };

  previewBtn.addEventListener("click", () => {
    runPreview(previewBtn);
  });

  summaryPreviewBtn?.addEventListener("click", () => {
    runPreview(summaryPreviewBtn);
  });


});
</script>

<script>
/* ── Checkout step progression manager ── */
document.addEventListener('DOMContentLoaded', function () {
  var STEP_IDS = ['step-contact', 'step-delivery', 'step-datetime', 'step-payment'];

  function getStep(id) { return document.getElementById(id); }

  function openStep(id) {
    var stepIndex = STEP_IDS.indexOf(id);
    STEP_IDS.forEach(function (sid, i) {
      var el = getStep(sid);
      if (!el) return;
      if (sid === id) {
        el.classList.remove('checkout-step--pending', 'checkout-step--complete');
        el.classList.add('checkout-step--active');
        setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 50);
      } else if (i < stepIndex) {
        el.classList.remove('checkout-step--active', 'checkout-step--pending');
        el.classList.add('checkout-step--complete');
      } else {
        el.classList.remove('checkout-step--active', 'checkout-step--complete');
        el.classList.add('checkout-step--pending');
      }
    });
  }

  function completeStep(id, summaryText) {
    var el = getStep(id);
    if (!el) return;
    el.classList.remove('checkout-step--active', 'checkout-step--pending');
    el.classList.add('checkout-step--complete');
    if (summaryText) {
      var summaryEl = el.querySelector('.checkout-step__summary');
      if (summaryEl) summaryEl.textContent = summaryText;
    }
    var editBtn = el.querySelector('.checkout-step__edit-btn');
    if (editBtn) editBtn.removeAttribute('hidden');
    var nextIndex = STEP_IDS.indexOf(id) + 1;
    if (nextIndex < STEP_IDS.length) {
      var nextEl = getStep(STEP_IDS[nextIndex]);
      if (nextEl) {
        nextEl.classList.remove('checkout-step--pending');
        nextEl.classList.add('checkout-step--active');
        setTimeout(function () { nextEl.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
      }
    }
  }

  /* Wire Edit buttons on all steps */
  document.querySelectorAll('.checkout-step__edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var stepEl = btn.closest('.checkout-step');
      if (stepEl && stepEl.id) openStep(stepEl.id);
    });
  });

  /* Expose globally for OTP verify script */
  window.__checkoutSteps = { complete: completeStep, open: openStep };

  /* ── Logged-in: auto-complete step 1, activate step 2 ── */
  <?php if ($isLoggedIn): ?>
  (function () {
    var s1 = getStep('step-contact');
    if (s1) {
      s1.classList.remove('checkout-step--active', 'checkout-step--pending');
      s1.classList.add('checkout-step--complete');
      var s1s = s1.querySelector('#step1Summary');
      if (s1s) s1s.textContent = '\u2714 Logged in \u2014 <?= htmlspecialchars((string)($prefillEmail ?: 'verified'), ENT_QUOTES, 'UTF-8') ?>';
      var s1e = s1.querySelector('.checkout-step__edit-btn');
      if (s1e) s1e.removeAttribute('hidden');
    }
    var s2 = getStep('step-delivery');
    if (s2) {
      s2.classList.remove('checkout-step--pending');
      s2.classList.add('checkout-step--active');
    }
  })();
  <?php endif; ?>

  /* ── Step 2 continue ── */
  var step2Btn = document.getElementById('step2ContinueBtn');
  if (step2Btn) {
    step2Btn.addEventListener('click', function () {
      var modeInput = document.querySelector('input[name="fulfilment_mode"]:checked');
      var mode = modeInput ? modeInput.value : 'delivery';
      if (mode === 'delivery') {
        var street = document.getElementById('deliveryStreet');
        var pincode = document.getElementById('deliveryPincode');
        if (!street || !street.value.trim() || !pincode || !pincode.value.trim()) {
          if (window.__checkoutShowToast) window.__checkoutShowToast('Enter delivery street and pincode to continue.', 'warn');
          if (street && !street.value.trim()) street.focus();
          return;
        }
        var area = street.value.trim().split(',')[0].trim().slice(0, 28);
        completeStep('step-delivery', '\u2714 Home Delivery \u2014 ' + area + ', ' + pincode.value.trim());
      } else {
        completeStep('step-delivery', '\u2714 Store Pickup \u2014 Nashik bakery');
      }
    });
  }

  /* ── Step 3 continue ── */
  var step3Btn = document.getElementById('step3ContinueBtn');
  if (step3Btn) {
    step3Btn.addEventListener('click', function () {
      var dateEl = document.getElementById('deliveryDate');
      var slotEl = document.getElementById('deliverySlot');
      if (!dateEl || !dateEl.value || !slotEl || !slotEl.value) {
        if (window.__checkoutShowToast) window.__checkoutShowToast('Select a date and time slot to continue.', 'warn');
        if (dateEl && !dateEl.value) dateEl.focus();
        return;
      }
      var dateLabel = dateEl.value;
      try {
        dateLabel = new Date(dateEl.value + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
      } catch (e) {}
      var slotText = slotEl.options[slotEl.selectedIndex] ? slotEl.options[slotEl.selectedIndex].text : '';
      completeStep('step-datetime', '\u2714 ' + dateLabel + ' \u00b7 ' + slotText);
    });
  }

  /* ── Mobile order summary sidebar toggle ── */
  var summaryToggle = document.getElementById('checkoutSummaryToggle');
  var summaryAside  = document.querySelector('.checkout-summary');
  if (summaryToggle && summaryAside) {
    summaryToggle.addEventListener('click', function () {
      var isOpen = summaryAside.classList.toggle('is-open');
      summaryToggle.classList.toggle('is-open', isOpen);
      summaryToggle.setAttribute('aria-expanded', String(isOpen));
    });
  }
});
</script>
