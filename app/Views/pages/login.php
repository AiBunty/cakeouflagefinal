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

  if (!form || !emailInput || !otpInput || !sendBtn || !statusEl) {
    return;
  }

  const existingCooldownUntil = readCooldownUntil();
  if (existingCooldownUntil > Date.now()) {
    startCooldownUi(existingCooldownUntil);
  }

  sendBtn.addEventListener("click", async () => {
    const email = emailInput.value.trim();
    if (!email) {
      statusEl.textContent = "Please enter your email first.";
      return;
    }

    statusEl.textContent = "Sending OTP...";
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
      }
      statusEl.textContent = data.success ? "OTP sent to your email." : (data.message || "Failed to send OTP");
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
