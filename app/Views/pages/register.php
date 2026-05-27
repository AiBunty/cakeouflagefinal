<?php /* Cakeouflage — Register */ ?>
<?php
$registerBrandLogo = (string)($siteConfig['branding']['navbar_logo_url'] ?? '/client/assets/images/mainlogo.svg');
$registerBrandFallback = (string)($siteConfig['branding']['navbar_logo_fallback'] ?? '/client/assets/images/mainlogo.svg');
?>
<style>
  .section--auth .auth-card { max-width: 560px; }
  .section--auth .form-grid { gap: 12px; }
  .section--auth .form-row-2 { gap: 12px; }
  .section--auth .form-control input,
  .section--auth .form-control select,
  .section--auth .form-control textarea {
    min-height: 48px;
    border-radius: 14px;
  }
  .auth-otp-actions {
    display: grid;
    gap: 10px;
  }
  .auth-otp-cta {
    width: 100%;
    justify-content: center;
    gap: 8px;
    min-height: 52px;
    font-weight: 700;
  }
  .auth-otp-meta {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: #6f5b64;
    font-size: 0.8rem;
  }
  .auth-otp-panel {
    margin-top: 10px;
    padding: 14px;
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(255, 248, 244, 0.92), rgba(255, 255, 255, 0.98));
    border: 1px solid rgba(128, 0, 31, 0.12);
  }
  .auth-toast-stack {
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 70;
    display: grid;
    gap: 10px;
    pointer-events: none;
  }
  .auth-toast {
    min-width: 220px;
    max-width: 320px;
    padding: 12px 14px;
    border-radius: 14px;
    background: rgba(45, 31, 37, 0.96);
    color: #fff;
    box-shadow: 0 18px 35px rgba(45, 31, 37, 0.16);
  }
  .auth-toast[data-tone="success"] { background: rgba(29, 111, 66, 0.96); }
  .auth-toast[data-tone="warn"] { background: rgba(138, 90, 0, 0.96); }
  .auth-toast[data-tone="error"] { background: rgba(163, 61, 61, 0.96); }
  .auth-card__links-row--otp { display: none; margin-top: 10px; }
  .auth-card__links-row--otp.is-visible { display: block; }
  .otp-row { margin-top: 12px; }
  .otp-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
  }
  .otp-cell {
    width: 100%;
    aspect-ratio: 1 / 1.05;
    border-radius: 12px;
    border: 1px solid rgba(128, 0, 31, 0.18);
    background: #fff;
    text-align: center;
    font-size: 1.15rem;
    font-weight: 700;
    color: #2d1f25;
    outline: none;
  }
  .otp-cell:focus {
    border-color: #80001F;
    box-shadow: 0 0 0 3px rgba(128, 0, 31, 0.14);
  }
  .otp-note {
    margin: 8px 0 0;
    font-size: 0.82rem;
    color: #6f5b64;
  }
  .auth-card__brand-logo {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-width: 0;
    height: auto;
    margin: 0 auto 10px;
  }
  .auth-card__brand-logo img {
    display: block;
    width: auto;
    height: 72px;
    max-width: min(240px, 72vw);
    object-fit: contain;
  }
  @media (max-width: 520px) {
    .otp-grid { gap: 6px; }
    .otp-cell { font-size: 1rem; border-radius: 10px; }
    .auth-card__brand-logo img { height: 60px; }
  }
</style>
<main class="section section--auth" data-page="register">
  <div class="auth-card-wrap auth-card-wrap--wide">
    <div class="auth-card">
      <div class="auth-card__brand">
        <a href="/" class="site-logo auth-card__brand-logo" aria-label="Cakeouflage home">
          <img src="<?= htmlspecialchars($registerBrandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cakeouflage Logo" onerror="this.onerror=null;this.src='<?= htmlspecialchars($registerBrandFallback, ENT_QUOTES, 'UTF-8') ?>';">
        </a>
        <p class="auth-card__tagline">We bake sweet wonderful happy memories</p>
      </div>

      <h1 class="auth-card__title">Create Account</h1>
      <p class="auth-card__desc">Join us and enjoy faster checkout, order tracking, and birthday surprises!</p>

      <form class="form-grid" id="registerForm" data-otp-managed="1" novalidate>
        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">Full Name <span class="form-required">*</span></span>
            <input type="text" name="full_name" required autocomplete="name" placeholder="Priya Sharma">
          </label>
          <label class="form-control">
            <span class="form-label">Phone <span class="form-required">*</span></span>
            <input type="tel" name="phone" required autocomplete="tel" placeholder="+91 98765 43210">
          </label>
        </div>
        <label class="form-control">
          <span class="form-label">Email Address <span class="form-required">*</span></span>
          <input type="email" name="email" id="registerEmail" required autocomplete="email" placeholder="you@email.com">
        </label>
        <div class="auth-otp-actions">
          <button class="btn btn--primary btn--lg btn--block auth-otp-cta" type="button" id="registerSendOtpBtn">
            <span aria-hidden="true">✉️</span>
            <span>Send Verification OTP</span>
          </button>
          <div class="auth-otp-meta">
            <span>Step 1: verify your email to continue.</span>
            <span id="registerOtpMetaHint">Takes a few seconds</span>
          </div>
        </div>
        <div id="registerOtpNotice" class="form-feedback" aria-live="polite" style="display:none;margin-top:-0.25rem;padding:0.75rem 0.9rem;border-radius:12px;border:1px solid transparent;"></div>
        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">Date of Birthday <span class="form-label-hint">(optional — for birthday treats!)</span></span>
            <input type="date" name="dob">
          </label>
        </div>
        <label class="form-control-check">
          <input type="checkbox" name="agree_terms" required>
          <span>I agree to the <a href="/terms" class="link" target="_blank">Terms &amp; Conditions</a> and <a href="/privacy" class="link" target="_blank">Privacy Policy</a></span>
        </label>
        <div id="registerOtpStep" class="otp-row auth-otp-panel" hidden>
          <label class="form-control" style="margin-bottom:10px;">
            <span class="form-label">Enter the 6-digit OTP <span class="form-required">*</span></span>
          </label>
          <div class="otp-grid" id="registerOtpGrid" aria-label="OTP input">
            <input class="otp-cell" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" data-otp-slot="0">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="1">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="2">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="3">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="4">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="5">
          </div>
          <input type="hidden" name="otp" id="registerOtpValue" maxlength="6">
          <p class="otp-note">Paste a 6-digit code or type it one box at a time.</p>
          <button class="btn btn--primary btn--lg btn--block" type="submit" id="registerVerifyBtn">Verify OTP &amp; Create Account</button>
        </div>
        <p id="registerStatus" class="form-feedback" aria-live="polite"></p>
      </form>

      <p class="auth-card__footer-link">Already have an account?    <a href="/account/login.php" class="link">Sign In →</a></p>
    </div>
  </div>
</main>
<div class="auth-toast-stack" id="registerToastStack" aria-live="polite"></div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const OTP_COOLDOWN_MS = 60000;
  const OTP_STORAGE_KEY = "otp_cooldown_register_until";
  const form = document.getElementById("registerForm");
  const nameInput = form?.querySelector('input[name="full_name"]');
  const phoneInput = form?.querySelector('input[name="phone"]');
  const emailInput = document.getElementById("registerEmail");
  const sendBtn = document.getElementById("registerSendOtpBtn");
  const noticeEl = document.getElementById("registerOtpNotice");
  const otpStepEl = document.getElementById("registerOtpStep");
  const otpHiddenInput = document.getElementById("registerOtpValue");
  const otpSlots = Array.from(document.querySelectorAll('[data-otp-slot]'));
  const verifyBtn = document.getElementById("registerVerifyBtn");
  const statusEl = document.getElementById("registerStatus");
  const toastStack = document.getElementById("registerToastStack");
  const metaHintEl = document.getElementById("registerOtpMetaHint");
  const defaultSendText = sendBtn ? sendBtn.textContent : "Send OTP";

  let cooldownTimer = null;

  const showToast = (message, tone = "info") => {
    if (!toastStack || !message) return;
    const toast = document.createElement("div");
    toast.className = "auth-toast";
    toast.dataset.tone = tone;
    toast.textContent = message;
    toastStack.appendChild(toast);
    window.setTimeout(() => toast.remove(), 3200);
  };

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
        if (metaHintEl) {
          metaHintEl.textContent = "You can request a fresh OTP now";
        }
        window.localStorage.removeItem(OTP_STORAGE_KEY);
        return;
      }
      const remainingSec = Math.ceil(remainingMs / 1000);
      sendBtn.disabled = true;
      sendBtn.textContent = `Resend OTP in ${remainingSec}s`;
      if (metaHintEl) {
        metaHintEl.textContent = `Resend OTP in ${remainingSec}s`;
      }
    };

    if (cooldownTimer) {
      window.clearInterval(cooldownTimer);
    }
    tick();
    cooldownTimer = window.setInterval(tick, 250);
  };

  const showNotice = (message, tone = "info") => {
    if (!noticeEl) return;
    noticeEl.style.display = "block";
    noticeEl.textContent = message;
    noticeEl.style.background = tone === "warn" ? "#fff4e5" : "#eef7ff";
    noticeEl.style.borderColor = tone === "warn" ? "#f0c36d" : "#b7d7f7";
    noticeEl.style.color = tone === "warn" ? "#8a5a00" : "#184d78";
  };

  const clearNotice = () => {
    if (!noticeEl) return;
    noticeEl.style.display = "none";
    noticeEl.textContent = "";
  };

  const showOtpStep = () => {
    if (!otpStepEl || !verifyBtn) return;
    otpStepEl.hidden = false;
    otpStepEl.classList.add("is-visible");
  };

  const syncOtpValue = () => {
    if (!otpHiddenInput) return;
    otpHiddenInput.value = otpSlots.map((slot) => String(slot.value || '').replace(/\D+/g, '').slice(-1)).join('').slice(0, 6);
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

  if (!form || !nameInput || !phoneInput || !emailInput || !sendBtn || !statusEl || !otpHiddenInput || otpSlots.length !== 6) {
    return;
  }

  const existingCooldownUntil = readCooldownUntil();
  if (existingCooldownUntil > Date.now()) {
    startCooldownUi(existingCooldownUntil);
    showNotice("Cooldown active. You can request a new OTP after the timer ends.", "warn");
    showOtpStep();
  }

  sendBtn.addEventListener("click", async () => {
    const fullName = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    const email = emailInput.value.trim();
    if (!fullName || !phone || !email) {
      statusEl.textContent = "Full name, phone, and email are required before sending OTP.";
      showToast("Complete name, phone, and email first.", "warn");
      return;
    }

    statusEl.textContent = "Sending OTP...";
    clearNotice();
    sendBtn.disabled = true;
    sendBtn.textContent = "Sending...";

    try {
      const res = await fetch(window.BASE_URL + "/api/send-otp", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.__csrf || ""
        },
        credentials: "include",
        body: JSON.stringify({ email, name: fullName || "Customer" })
      });

      const data = await res.json();
      if (data.success) {
        const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
        window.localStorage.setItem(OTP_STORAGE_KEY, String(cooldownUntil));
        startCooldownUi(cooldownUntil);
        clearNotice();
        showOtpStep();
        clearOtpSlots();
        focusOtpSlot(0);
        statusEl.textContent = "OTP sent successfully to your email. It expires in 5 minutes.";
        showToast("OTP sent successfully.", "success");
        return;
      }

      const responseMessage = String(data.message || "");
      if (res.status === 429 || responseMessage.includes('60 seconds before requesting a new OTP')) {
        const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
        window.localStorage.setItem(OTP_STORAGE_KEY, String(cooldownUntil));
        startCooldownUi(cooldownUntil);
        showNotice(responseMessage || "Please wait 60 seconds before requesting a new OTP.", "warn");
        showOtpStep();
        statusEl.textContent = responseMessage || "Please wait 60 seconds before requesting a new OTP.";
        showToast(statusEl.textContent, "warn");
        return;
      }

      clearNotice();
      statusEl.textContent = data.message || "Failed to send OTP";
      showToast(statusEl.textContent, "error");
    } catch (error) {
      statusEl.textContent = "Unable to send OTP right now.";
      showToast(statusEl.textContent, "error");
      sendBtn.disabled = false;
      sendBtn.textContent = defaultSendText;
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const fullName = String(nameInput.value || "").trim();
    const phone = String(phoneInput.value || "").trim();
    const email = String(emailInput.value || "").trim();
    syncOtpValue();
    const otp = String(otpHiddenInput.value || "").trim();

    if (!fullName || !phone || !email || !otp) {
      statusEl.textContent = "Name, phone, email, and OTP are required.";
      return;
    }

    statusEl.textContent = "Verifying OTP...";

    try {
      const res = await fetch(window.BASE_URL + "/api/verify-otp", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.__csrf || ""
        },
        credentials: "include",
        body: JSON.stringify({
          email,
          otp,
          name: fullName,
          phone
        })
      });

      const data = await res.json();
      if (!data.success) {
        statusEl.textContent = data.message || "OTP verification failed";
        showToast(statusEl.textContent, "error");
        return;
      }

      statusEl.textContent = "Registration complete. Redirecting...";
      showToast("Account created successfully.", "success");
      window.location.href = window.BASE_URL + "/account/dashboard.php";
    } catch (error) {
      statusEl.textContent = "Unable to verify OTP right now.";
      showToast(statusEl.textContent, "error");
    }
  });
});
</script>
