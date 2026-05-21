<?php /* Cakeouflage — Login */ ?>
<main class="section section--auth" data-page="login">
  <div class="auth-card-wrap">
    <div class="auth-card">
      <div class="auth-card__brand">
        <span class="auth-card__logomark">🎂</span>
        <span class="auth-card__brandname">Cakeouflage</span>
        <p class="auth-card__tagline">We bake sweet wonderful happy memories</p>
      </div>

      <h1 class="auth-card__title">Sign In</h1>
      <p class="auth-card__desc">Welcome back! Access your orders, wishlist, and profile.</p>

      <form class="form-grid" id="loginForm" data-otp-managed="1" novalidate>
        <label class="form-control">
          <span class="form-label">Email Address <span class="form-required">*</span></span>
          <input type="email" name="email" id="loginEmail" required autocomplete="email" placeholder="you@email.com">
        </label>
        <div class="auth-card__links-row">
          <button class="btn btn--secondary btn--sm" type="button" id="loginSendOtpBtn">Send OTP</button>
        </div>
        <div id="loginOtpNotice" class="form-feedback" aria-live="polite" style="display:none;margin-top:-0.25rem;padding:0.75rem 0.9rem;border-radius:12px;border:1px solid transparent;"></div>
        <label class="form-control">
          <span class="form-label">OTP <span class="form-required">*</span></span>
          <input type="text" name="otp" id="loginOtp" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit OTP">
        </label>
        <button class="btn btn--primary btn--lg btn--block" type="submit">Verify OTP &amp; Sign In</button>
        <p id="loginStatus" class="form-feedback" aria-live="polite"></p>
      </form>

      <p class="auth-card__footer-link">Don't have an account? <a href="/register" class="link">Create one →</a></p>
    </div>
  </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const OTP_COOLDOWN_MS = 60000;
  const OTP_STORAGE_KEY = "otp_cooldown_login_until";
  const form = document.getElementById("loginForm");
  const emailInput = document.getElementById("loginEmail");
  const otpInput = document.getElementById("loginOtp");
  const sendBtn = document.getElementById("loginSendOtpBtn");
  const noticeEl = document.getElementById("loginOtpNotice");
  const statusEl = document.getElementById("loginStatus");
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

  if (!form || !emailInput || !otpInput || !sendBtn || !statusEl) {
    return;
  }

  const existingCooldownUntil = readCooldownUntil();
  if (existingCooldownUntil > Date.now()) {
    startCooldownUi(existingCooldownUntil);
    showNotice("Cooldown active. You can request a new OTP after the timer ends.", "warn");
  }

  sendBtn.addEventListener("click", async () => {
    const email = emailInput.value.trim();
    if (!email) {
      statusEl.textContent = "Please enter your email first.";
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
        statusEl.textContent = "OTP sent to your email. It expires in 5 minutes.";
        return;
      }

      const responseMessage = String(data.message || "");
      if (res.status === 429 || responseMessage.includes('60 seconds before requesting a new OTP')) {
        const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
        window.localStorage.setItem(OTP_STORAGE_KEY, String(cooldownUntil));
        startCooldownUi(cooldownUntil);
        showNotice(responseMessage || "Please wait 60 seconds before requesting a new OTP.", "warn");
        statusEl.textContent = responseMessage || "Please wait 60 seconds before requesting a new OTP.";
        return;
      }

      clearNotice();
      statusEl.textContent = data.message || "Failed to send OTP";
    } catch (error) {
      statusEl.textContent = "Unable to send OTP right now.";
      sendBtn.disabled = false;
      sendBtn.textContent = defaultSendText;
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const email = emailInput.value.trim();
    const otp = otpInput.value.trim();
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
        return;
      }

      statusEl.textContent = "OTP verified. Redirecting...";
      window.location.href = window.BASE_URL + "/account";
    } catch (error) {
      statusEl.textContent = "Unable to verify OTP right now.";
    }
  });
});
</script>
