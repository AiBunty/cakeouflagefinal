<?php
/* Cakeouflage — Checkout */
$isLoggedIn  = !empty($currentUser);
$prefillName  = $isLoggedIn ? htmlspecialchars((string)($currentUser['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') : '';
$prefillPhone = $isLoggedIn ? htmlspecialchars((string)($currentUser['phone']     ?? ''), ENT_QUOTES, 'UTF-8') : '';
$prefillEmail = $isLoggedIn ? htmlspecialchars((string)($currentUser['email']     ?? ''), ENT_QUOTES, 'UTF-8') : '';
$prefillStreet = ($isLoggedIn && !empty($lastAddress['street']))     ? htmlspecialchars((string)$lastAddress['street'],     ENT_QUOTES, 'UTF-8') : '';
$prefillPincode = ($isLoggedIn && !empty($lastAddress['postal_code'])) ? htmlspecialchars((string)$lastAddress['postal_code'], ENT_QUOTES, 'UTF-8') : '';
$prefillMaps    = ($isLoggedIn && !empty($lastAddress['maps_link']))   ? htmlspecialchars((string)$lastAddress['maps_link'],   ENT_QUOTES, 'UTF-8') : '';
$allowPartialPayment = isset($allowPartialPayment) ? (bool)$allowPartialPayment : true;
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
    margin-bottom: 20px;
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
    gap: 22px;
  }
  .checkout-form {
    gap: 16px;
  }
  .checkout-hero,
  .checkout-section.card,
  .checkout-summary,
  .checkout-mobile-bar {
    border: 1px solid var(--checkout-line);
    box-shadow: 0 18px 45px rgba(85, 32, 48, 0.08);
  }
  .checkout-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    padding: 18px 20px;
    border-radius: 24px;
    background: var(--checkout-blush);
    align-items: center;
  }
  .checkout-hero__title {
    margin: 0;
    font-size: clamp(1.15rem, 1rem + 0.8vw, 1.6rem);
    color: var(--checkout-ink);
  }
  .checkout-hero__text {
    margin: 6px 0 0;
    color: var(--checkout-muted);
    font-size: 0.95rem;
    line-height: 1.55;
  }
  .checkout-hero__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
  }
  .checkout-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 11px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.1);
    color: var(--checkout-ink);
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
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
    margin-top: 12px;
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
        <section class="checkout-hero" aria-label="Checkout highlights">
          <div>
            <h2 class="checkout-hero__title">Fast, clear, bakery-first checkout</h2>
            <p class="checkout-hero__text">Confirm your contact details once, choose delivery or pickup, then pay securely with UPI. Your order summary stays visible while you complete the essentials.</p>
          </div>
          <div class="checkout-hero__chips">
            <span class="checkout-chip">🔒 Secure payment proof</span>
            <span class="checkout-chip">⏱ Quick OTP verification</span>
            <span class="checkout-chip">🚚 Delivery or pickup</span>
          </div>
        </section>

        <div class="checkout-steps" id="checkoutSteps">

          <!-- ── Step 1: Contact & Verification ── -->
          <div class="checkout-step checkout-step--active" id="step-contact" data-step="1">
            <div class="checkout-step__header">
              <div class="checkout-step__num">1</div>
              <div>
                <p class="checkout-step__heading">Contact &amp; Verification</p>
                <p class="checkout-step__summary" id="step1Summary"><?= $isLoggedIn ? 'Logged in — details pre-filled' : 'Name, phone, email + OTP' ?></p>
              </div>
              <button type="button" class="checkout-step__edit-btn" data-edit-step="step-contact"<?= $isLoggedIn ? '' : ' hidden' ?>>Edit</button>
            </div>
            <div class="checkout-step__body">
              <?php if ($isLoggedIn): ?>
              <div class="checkout-login-note">✅ Logged in — contact details pre-filled. You can edit if needed.</div>
              <?php endif; ?>
              <div class="form-row-2">
                <label class="form-control">
                  <span class="form-label">Full Name <span class="form-required">*</span></span>
                  <input id="customerName" name="customer_name" required autocomplete="name" placeholder="Priya Sharma" value="<?= $prefillName ?>" data-autosave="customer_name">
                  <span class="field-error-msg" aria-live="polite"></span>
                </label>
                <label class="form-control">
                  <span class="form-label">Phone <span class="form-required">*</span></span>
                  <input id="customerPhone" name="customer_phone" type="tel" autocomplete="tel" placeholder="+91 98765 43210" value="<?= $prefillPhone ?>" data-autosave="customer_phone">
                  <span class="field-error-msg" aria-live="polite"></span>
                </label>
              </div>
              <label class="form-control">
                <span class="form-label">Email <span class="form-required">*</span></span>
                <input id="customerEmail" name="customer_email" type="email" required autocomplete="email" placeholder="you@email.com" value="<?= $prefillEmail ?>" data-autosave="customer_email">
                <span class="field-error-msg" aria-live="polite"></span>
              </label>
              <div class="checkout-inline-banner">Use the same email you want all order confirmations and updates sent to.</div>
              <!-- OTP UI START -->
<?php if (!$isLoggedIn): ?>
              <div class="checkout-auth-row">
                <button type="button" id="sendOtpBtn" class="btn btn--primary btn--lg btn--block checkout-auth-button">
                  <span aria-hidden="true">✉️</span>
                  <span>Send Verification OTP</span>
                </button>
                <div class="checkout-auth-meta">
                  <span>Verify once to unlock order placement.</span>
                  <span id="otpCooldownHint">Takes a few seconds</span>
                </div>
                <div id="otpNotice" class="otp-notice" aria-live="polite"></div>
              </div>
              <div id="otpSection" class="checkout-auth-step" hidden>
                <label class="form-control" style="margin-bottom:10px;">
                  <span class="form-label">Enter the 6-digit OTP <span class="form-required">*</span></span>
                </label>
                <div class="otp-grid" id="checkoutOtpGrid" aria-label="OTP input">
                  <input class="otp-cell" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" data-otp-slot="0">
                  <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="1">
                  <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="2">
                  <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="3">
                  <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="4">
                  <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="5">
                </div>
                <input type="hidden" id="otpInput" maxlength="6">
                <p class="otp-note">Paste the code from your email or type it one box at a time.</p>
                <button type="button" id="verifyOtpBtn" class="btn btn--primary btn--lg btn--block checkout-auth-verify">Verify &amp; Continue to Checkout</button>
              </div>
              <p id="otpStatus" class="checkout-feedback" aria-live="polite"></p>
<?php endif; ?>
              <!-- OTP UI END -->

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
              <p class="checkout-section__subtitle" style="margin-bottom:14px">Preview the amount, choose full or advance payment, then upload your UPI screenshot to confirm.</p>
              <div class="payment-choice-card">
                <p style="margin:0 0 8px; font-weight:600; font-size:0.92rem;">💳 Payment Option</p>
                <label style="display:flex; align-items:center; gap:8px; margin-bottom:6px; cursor:pointer;">
                  <input type="radio" name="payment_type" id="payFull" value="full" checked>
                  <span id="payFullLabel">Pay in Full <span id="payFullAmount"></span></span>
                </label>
                <?php if ($allowPartialPayment): ?>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                  <input type="radio" name="payment_type" id="payAdvance50" value="advance_50">
                  <span id="payAdvanceLabel">Pay 50% Advance <span id="payAdvanceAmount"></span> &amp; 50% on Delivery</span>
                </label>
                <?php endif; ?>
              </div>

              <div class="upi-section" style="margin-top:16px">
                <p><strong>Scan &amp; Pay (UPI)</strong></p>
                <img id="upiQR" src="https://via.placeholder.com/200" width="200" />
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
  window.ALLOW_PARTIAL_PAYMENT = <?= $allowPartialPayment ? 'true' : 'false' ?>;
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
    const type = form.querySelector('input[name="payment_type"]:checked')?.value || 'full';
    const amount = type === 'advance_50' ? Math.ceil(total * 0.5) : total;
    const balance = Math.max(total - amount, 0);
    document.getElementById('upiAmountText').textContent = type === 'advance_50'
      ? 'Pay ₹' + amount + ' now (50% advance). Balance ₹' + balance + ' due on delivery.'
      : 'Pay ₹' + amount + ' using UPI';
    generateQR(amount);
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
  form.querySelectorAll('input[name="payment_type"]').forEach((input) => input.addEventListener('change', syncPaymentType));
  form.querySelectorAll('input[name="fulfilment_mode"]').forEach((input) => input.addEventListener('change', syncAddressSection));

  ['customerName', 'customerPhone', 'customerEmail', 'deliveryStreet', 'deliveryPincode', 'deliveryDate', 'deliverySlot'].forEach((id) => {
    const field = document.getElementById(id);
    field?.addEventListener('blur', () => {
      if (id === 'customerEmail') {
        validateField(field, (value) => /.+@.+\..+/.test(value));
      } else if (id === 'customerPhone') {
        validateField(field, (value) => value.replace(/\D+/g, '').length >= 10);
      } else if (id === 'deliveryPincode') {
        validateField(field, (value) => /^\d{6}$/.test(value));
      } else {
        validateField(field);
      }
    });
  });

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

    const customerName = document.getElementById('customerName');
    const customerPhone = document.getElementById('customerPhone');
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

    const validations = [
      validateField(customerName, null, 'Full name is required'),
      validateField(customerPhone, (v) => v.replace(/\D+/g, '').length >= 10, 'Enter a valid 10-digit phone number'),
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
    formData.set('customer_name', customerName.value.trim());
    formData.set('customer_phone', customerPhone.value.trim());
    formData.set('customer_email', customerEmail.value.trim());
    formData.set('slot_id', deliverySlot.value);
    formData.set('payment_method', paymentMethod);
    formData.set('payment_type', form.querySelector('input[name="payment_type"]:checked')?.value || 'full');

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
          'X-CSRF-Token': window.__csrf
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
  window.__checkoutSyncPaymentType = syncPaymentType;
  window.__checkoutSyncMobileTotals = syncMobileTotals;

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
const payType = document.querySelector('input[name="payment_type"]:checked')?.value || 'full';
const payAmt = payType === 'advance_50' ? Math.ceil(total * 0.5) : total;
generateQR(payAmt);

const payFullAmt  = document.getElementById('payFullAmount');
const payAdvAmt   = document.getElementById('payAdvanceAmount');
if (payFullAmt)  payFullAmt.textContent  = '(₹' + total + ')';
if (payAdvAmt)   payAdvAmt.textContent   = '(₹' + Math.ceil(total * 0.5) + ')';

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
  const noticeEl = document.getElementById("otpNotice");
  const cooldownHint = document.getElementById("otpCooldownHint");
  const otpStepEl = document.getElementById("otpSection");
  const emailInput = document.getElementById("customerEmail");
  const nameInput = document.getElementById("customerName");
  const otpInput = document.getElementById("otpInput");
  const otpSlots = Array.from(document.querySelectorAll('#checkoutOtpGrid [data-otp-slot]'));
  const defaultSendText = sendBtn ? sendBtn.textContent.trim() : "Send Verification OTP";
  let cooldownTimer = null;

  const setFeedback = window.__checkoutSetFeedback || function (element, message) {
    if (element) element.textContent = message || '';
  };
  const showToast = window.__checkoutShowToast || function () {};

  const readCooldownUntil = () => {
    const value = Number(window.localStorage.getItem(OTP_STORAGE_KEY) || "0");
    return Number.isFinite(value) ? value : 0;
  };

  const showOtpStep = () => {
    if (!otpStepEl) return;
    otpStepEl.hidden = false;
    otpStepEl.classList.add("is-visible");
  };

  const hideOtpStep = () => {
    if (!otpStepEl) return;
    otpStepEl.hidden = true;
    otpStepEl.classList.remove("is-visible");
  };

  const syncOtpValue = () => {
    if (!otpInput) return;
    otpInput.value = otpSlots.map((slot) => String(slot.value || '').replace(/\D+/g, '').slice(-1)).join('').slice(0, 6);
    // Auto-submit when all 6 digits entered
    if (otpInput.value.length === 6) {
      const vBtn = document.getElementById('verifyOtpBtn');
      if (vBtn && !vBtn.disabled) {
        window.setTimeout(() => vBtn.click(), 300);
      }
    }
  };

  const clearOtpSlots = () => {
    otpSlots.forEach((slot) => { slot.value = ''; });
    syncOtpValue();
  };

  const focusOtpSlot = (index) => {
    const slot = otpSlots[index];
    if (slot) slot.focus();
  };

  otpSlots.forEach((slot, index) => {
    slot.addEventListener('input', () => {
      const digits = String(slot.value || '').replace(/\D+/g, '');
      if (digits.length > 1) {
        digits.split('').forEach((digit, digitIndex) => {
          const target = otpSlots[index + digitIndex];
          if (target) target.value = digit;
        });
        focusOtpSlot(Math.min(index + digits.length, otpSlots.length - 1));
      } else {
        slot.value = digits.slice(0, 1);
        if (slot.value && index < otpSlots.length - 1) {
          focusOtpSlot(index + 1);
        }
      }
      syncOtpValue();
    });

    slot.addEventListener('keydown', (event) => {
      if (event.key === 'Backspace' && !slot.value && index > 0) {
        otpSlots[index - 1].value = '';
        focusOtpSlot(index - 1);
        syncOtpValue();
      }
    });

    slot.addEventListener('paste', (event) => {
      event.preventDefault();
      const pasted = (event.clipboardData || window.clipboardData)?.getData('text') || '';
      const digits = pasted.replace(/\D+/g, '').slice(0, 6);
      if (!digits) return;
      digits.split('').forEach((digit, digitIndex) => {
        const target = otpSlots[digitIndex];
        if (target) target.value = digit;
      });
      syncOtpValue();
      focusOtpSlot(Math.min(digits.length, otpSlots.length - 1));
    });
  });

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
        if (cooldownHint) {
          cooldownHint.textContent = "You can request a fresh OTP now";
        }
        window.localStorage.removeItem(OTP_STORAGE_KEY);
        return;
      }
      const remainingSec = Math.ceil(remainingMs / 1000);
      sendBtn.disabled = true;
      sendBtn.textContent = `Resend OTP in ${remainingSec}s`;
      if (cooldownHint) {
        cooldownHint.textContent = `Resend OTP in ${remainingSec}s`;
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
    showOtpStep();
    setFeedback(noticeEl, "Cooldown active. Please use the last OTP sent to your email.", "warn");
  }

  sendBtn.addEventListener("click", async () => {
    const email = emailInput?.value.trim() || '';
    const customerName = nameInput?.value.trim() || 'Customer';

    if (!email) {
      setFeedback(statusEl, "Enter your email before requesting an OTP.", "error");
      showToast("Enter your email first.", "warn");
      return;
    }

    setFeedback(statusEl, "Sending OTP…", "info");
    setFeedback(noticeEl, "We are sending a 6-digit verification code to your email.", "info");
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

      if (data.success) {
        emailInput.readOnly = true;
        showOtpStep();
        clearOtpSlots();
        focusOtpSlot(0);
        setFeedback(statusEl, "OTP sent successfully to your email.", "success");
        setFeedback(noticeEl, "OTP sent successfully to your email.", "success");
        showToast("OTP sent successfully.", "success");

        const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
        window.localStorage.setItem(OTP_STORAGE_KEY, String(cooldownUntil));
        startCooldownUi(cooldownUntil);
      } else {
        const message = String(data.message || "Failed to send OTP");
        if (res.status === 429 || message.includes("60 seconds before requesting a new OTP")) {
          const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
          window.localStorage.setItem(OTP_STORAGE_KEY, String(cooldownUntil));
          startCooldownUi(cooldownUntil);
          showOtpStep();
          setFeedback(noticeEl, message, "warn");
          setFeedback(statusEl, message, "warn");
          showToast(message, "warn");
          return;
        }
        setFeedback(statusEl, message, "error");
        setFeedback(noticeEl, message, "error");
        sendBtn.disabled = false;
        sendBtn.textContent = defaultSendText;
      }

    } catch (err) {
      console.error(err);
      setFeedback(statusEl, "Unable to send OTP right now.", "error");
      setFeedback(noticeEl, "Unable to send OTP right now.", "error");
      sendBtn.disabled = false;
      sendBtn.textContent = defaultSendText;
    }

  });

  emailInput?.addEventListener("input", () => {
    window.otpVerified = false;
    emailInput.readOnly = false;
    hideOtpStep();
    clearOtpSlots();
    setFeedback(statusEl, '', 'info');
    setFeedback(noticeEl, '', 'info');
  });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

  const verifyBtn = document.getElementById("verifyOtpBtn");
  const otpInput = document.getElementById("otpInput");
  const statusEl = document.getElementById("otpStatus");
  const noticeEl = document.getElementById("otpNotice");
  const setFeedback = window.__checkoutSetFeedback || function (element, message) {
    if (element) element.textContent = message || '';
  };
  const showToast = window.__checkoutShowToast || function () {};

  if (!verifyBtn) {
    console.log("Verify button not found ❌");
    return;
  }

  verifyBtn.addEventListener("click", async () => {

    const email = document.getElementById("customerEmail").value.trim();
    const otp = otpInput.value.trim();

    if (!email) {
      setFeedback(statusEl, "Enter your email before verifying OTP.", "error");
      return;
    }

    if (otp.length !== 6) {
      setFeedback(statusEl, "Enter the full 6-digit OTP.", "error");
      return;
    }

    verifyBtn.disabled = true;
    verifyBtn.textContent = "Verifying…";
    setFeedback(statusEl, "Verifying OTP…", "info");

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

      if (data.success) {
        window.otpVerified = true;
        // Fix: stop loading state, show verified text
        verifyBtn.textContent = "\u2713 OTP Verified";
        verifyBtn.disabled = true;
        verifyBtn.style.cssText += ";background:#1d6f42;border-color:#1d6f42;cursor:default;";
        // Lock OTP cells
        document.querySelectorAll('#checkoutOtpGrid [data-otp-slot]').forEach(function (cell) {
          cell.disabled = true;
          cell.style.cssText += ";background:#eefaf2;border-color:#bfe8c8;color:#1d6f42;";
        });
        setFeedback(statusEl, "\u2705 Email verified. Checkout unlocked.", "success");
        setFeedback(noticeEl, "\u2705 OTP verified. You can now place your order.", "success");
        showToast("Email verified. Checkout unlocked.", "success");
        // Advance to step 2
        if (window.__checkoutSteps) {
          window.__checkoutSteps.complete('step-contact', '\u2714 ' + email);
        }
        await loadCheckoutSummary();
      } else {
        setFeedback(statusEl, data.message || "OTP verification failed.", "error");
        verifyBtn.disabled = false;
        verifyBtn.textContent = "Verify & Continue to Checkout";
      }

    } catch (err) {
      console.error(err);
      setFeedback(statusEl, "Unable to verify OTP right now.", "error");
      verifyBtn.disabled = false;
      verifyBtn.textContent = "Verify & Continue to Checkout";
    }

  });

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
