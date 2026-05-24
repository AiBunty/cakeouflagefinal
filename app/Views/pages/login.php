<?php /* Cakeouflage — Login */ ?>
<style>
  .section--auth .auth-card { max-width: 500px; }
  .section--auth .form-grid { gap: 12px; }
  .section--auth .form-control input { min-height: 48px; border-radius: 14px; }
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
<main class="section section--auth" data-page="login">
  <div class="auth-card-wrap">
    <div class="auth-card">
      <div class="auth-card__brand">
        <a href="/" class="site-logo auth-card__brand-logo" aria-label="Cakeouflage home">
          <img src="/client/assets/images/mainlogo.svg" alt="Cakeouflage Logo">
        </a>
        <p class="auth-card__tagline">We bake sweet wonderful happy memories</p>
      </div>

      <h1 class="auth-card__title">Sign In</h1>
      <p class="auth-card__desc">Welcome back! Access your orders, wishlist, and profile.</p>

      <form class="form-grid" id="loginForm" data-otp-managed="1" novalidate>
        <label class="form-control">
          <span class="form-label">Email Address <span class="form-required">*</span></span>
          <input type="email" name="email" id="loginEmail" required autocomplete="email" placeholder="you@email.com">
        </label>
        <div class="auth-otp-actions">
          <button class="btn btn--primary btn--lg btn--block auth-otp-cta" type="button" id="loginSendOtpBtn">
            <span aria-hidden="true">✉️</span>
            <span>Send Verification OTP</span>
          </button>
          <div class="auth-otp-meta">
            <span>Step 1: request your login OTP.</span>
            <span id="loginOtpMetaHint">Takes a few seconds</span>
          </div>
        </div>
        <div id="loginOtpNotice" class="form-feedback" aria-live="polite" style="display:none;margin-top:-0.25rem;padding:0.75rem 0.9rem;border-radius:12px;border:1px solid transparent;"></div>
        <div id="loginOtpStep" class="otp-row auth-otp-panel" hidden>
          <label class="form-control" style="margin-bottom:10px;">
            <span class="form-label">Enter the 6-digit OTP <span class="form-required">*</span></span>
          </label>
          <div class="otp-grid" id="loginOtpGrid" aria-label="OTP input">
            <input class="otp-cell" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" data-otp-slot="0">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="1">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="2">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="3">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="4">
            <input class="otp-cell" type="text" inputmode="numeric" maxlength="1" data-otp-slot="5">
          </div>
          <input type="hidden" name="otp" id="loginOtpValue" maxlength="6">
          <p class="otp-note">Paste a 6-digit code or type it one box at a time.</p>
          <button class="btn btn--primary btn--lg btn--block" type="submit" id="loginVerifyBtn">Verify &amp; Login</button>
        </div>
        <p id="loginStatus" class="form-feedback" aria-live="polite"></p>
      </form>

      <p class="auth-card__footer-link">Don't have an account? <a href="/register" class="link">Create one →</a></p>
    </div>
  </div>
</main>
<div class="auth-toast-stack" id="loginToastStack" aria-live="polite"></div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const OTP_COOLDOWN_MS = 60000;
  const OTP_STORAGE_KEY = "otp_cooldown_login_until";
  const form = document.getElementById("loginForm");
  const emailInput = document.getElementById("loginEmail");
  const sendBtn = document.getElementById("loginSendOtpBtn");
  const noticeEl = document.getElementById("loginOtpNotice");
  const otpStepEl = document.getElementById("loginOtpStep");
  const otpHiddenInput = document.getElementById("loginOtpValue");
  const otpSlots = Array.from(document.querySelectorAll('[data-otp-slot]'));
  const verifyBtn = document.getElementById("loginVerifyBtn");
  const statusEl = document.getElementById("loginStatus");
  const toastStack = document.getElementById("loginToastStack");
  const metaHintEl = document.getElementById("loginOtpMetaHint");
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

  const hideOtpStep = () => {
    if (!otpStepEl) return;
    otpStepEl.hidden = true;
    otpStepEl.classList.remove("is-visible");
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

  if (!form || !emailInput || !sendBtn || !statusEl || !otpHiddenInput || otpSlots.length !== 6) {
    return;
  }

  const existingCooldownUntil = readCooldownUntil();
  if (existingCooldownUntil > Date.now()) {
    startCooldownUi(existingCooldownUntil);
    showNotice("Cooldown active. You can request a new OTP after the timer ends.", "warn");
    showOtpStep();
  }

  sendBtn.addEventListener("click", async () => {
    const email = emailInput.value.trim();
    if (!email) {
      statusEl.textContent = "Please enter your email first.";
      showToast(statusEl.textContent, "warn");
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
        body: JSON.stringify({ email })
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

    const email = emailInput.value.trim();
    syncOtpValue();
    const otp = otpHiddenInput.value.trim();
    if (!email || !otp) {
      statusEl.textContent = "Email and OTP are required.";
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
        body: JSON.stringify({ email, otp })
      });

      const data = await res.json();
      if (!data.success) {
        statusEl.textContent = data.message || "OTP verification failed";
        showToast(statusEl.textContent, "error");
        return;
      }

      statusEl.textContent = "OTP verified. Redirecting...";
      showToast("OTP verified successfully.", "success");
      window.location.href = window.BASE_URL + "/account";
    } catch (error) {
      statusEl.textContent = "Unable to verify OTP right now.";
      showToast(statusEl.textContent, "error");
    }
  });
});
</script>
