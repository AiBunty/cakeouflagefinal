<?php /* Cakeouflage — Register */ ?>
<main class="section section--auth" data-page="register">
  <div class="auth-card-wrap auth-card-wrap--wide">
    <div class="auth-card">
      <div class="auth-card__brand">
        <span class="auth-card__logomark">🎂</span>
        <span class="auth-card__brandname">Cakeouflage</span>
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
        <div class="auth-card__links-row">
          <button class="btn btn--secondary btn--sm" type="button" id="registerSendOtpBtn">Send OTP</button>
        </div>
        <div id="registerOtpNotice" class="form-feedback" aria-live="polite" style="display:none;margin-top:-0.25rem;padding:0.75rem 0.9rem;border-radius:12px;border:1px solid transparent;"></div>
        <div class="form-row-2">
          <label class="form-control">
            <span class="form-label">OTP <span class="form-required">*</span></span>
            <input type="text" name="otp" id="registerOtp" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit OTP">
          </label>
          <label class="form-control">
            <span class="form-label">Date of Birthday <span class="form-label-hint">(optional — for birthday treats!)</span></span>
            <input type="date" name="dob">
          </label>
        </div>
        <label class="form-control-check">
          <input type="checkbox" name="agree_terms" required>
          <span>I agree to the <a href="/terms" class="link" target="_blank">Terms &amp; Conditions</a> and <a href="/privacy" class="link" target="_blank">Privacy Policy</a></span>
        </label>
        <button class="btn btn--primary btn--lg btn--block" type="submit">Verify OTP &amp; Create Account</button>
        <p id="registerStatus" class="form-feedback" aria-live="polite"></p>
      </form>

      <p class="auth-card__footer-link">Already have an account?    <a href="/login" class="link">Sign In →</a></p>
    </div>
  </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const OTP_COOLDOWN_MS = 60000;
  const OTP_STORAGE_KEY = "otp_cooldown_register_until";
  const form = document.getElementById("registerForm");
  const nameInput = form?.querySelector('input[name="full_name"]');
  const phoneInput = form?.querySelector('input[name="phone"]');
  const emailInput = document.getElementById("registerEmail");
  const otpInput = document.getElementById("registerOtp");
  const sendBtn = document.getElementById("registerSendOtpBtn");
  const noticeEl = document.getElementById("registerOtpNotice");
  const statusEl = document.getElementById("registerStatus");
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

  if (!form || !nameInput || !phoneInput || !emailInput || !otpInput || !sendBtn || !statusEl) {
    return;
  }

  const existingCooldownUntil = readCooldownUntil();
  if (existingCooldownUntil > Date.now()) {
    startCooldownUi(existingCooldownUntil);
    showNotice("Cooldown active. You can request a new OTP after the timer ends.", "warn");
  }

  sendBtn.addEventListener("click", async () => {
    const fullName = nameInput.value.trim();
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
        body: JSON.stringify({ email, name: fullName || "Customer" })
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

    const fullName = String(nameInput.value || "").trim();
    const phone = String(phoneInput.value || "").trim();
    const email = String(emailInput.value || "").trim();
    const otp = String(otpInput.value || "").trim();

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
        return;
      }

      statusEl.textContent = "Registration complete. Redirecting...";
      window.location.href = window.BASE_URL + "/account";
    } catch (error) {
      statusEl.textContent = "Unable to verify OTP right now.";
    }
  });
});
</script>
