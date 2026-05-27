(function () {
  const utils = window.CakeouflageUtils;
  if (!utils) {
    return;
  }

  const OTP_STORAGE_KEY = "cakeouflage.otpCooldownUntil";
  const OTP_COOLDOWN_MS = 60000;

  const STATUS_LABELS = {
    pending_payment: "Pending Payment",
    payment_under_review: "Payment Review",
    pending: "Pending",
    confirmed: "Confirmed",
    preparing: "Preparing",
    ready: "Ready",
    ready_for_pickup: "Ready for Pickup",
    out_for_delivery: "Out for Delivery",
    delivered: "Delivered",
    completed: "Completed",
    cancelled: "Cancelled",
    rejected: "Rejected",
    refund_requested: "Refund Requested",
    partially_refunded: "Partially Refunded",
    fully_refunded: "Fully Refunded",
    refunded: "Refunded"
  };

  const TRACK_STAGES = [
    "pending_payment",
    "payment_under_review",
    "pending",
    "confirmed",
    "preparing",
    "ready_for_pickup",
    "out_for_delivery",
    "completed"
  ];

  const byId = (id) => document.getElementById(id);

  const escapeHtml = (value) =>
    String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const toStatusKey = (value) => String(value || "pending").trim().toLowerCase().replace(/\s+/g, "_");
  const statusLabel = (value) => STATUS_LABELS[toStatusKey(value)] || String(value || "Pending");

  const formatDate = (value) => {
    const dt = new Date(value || "");
    if (Number.isNaN(dt.getTime())) {
      return "-";
    }
    return dt.toLocaleString("en-IN", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit"
    });
  };

  const makeTimelineSteps = (order, timeline) => {
    const state = toStatusKey(order?.order_status || "pending");
    const stageIndex = Math.max(0, TRACK_STAGES.indexOf(state));
    const stageLabel = {
      pending_payment: "Payment Pending",
      payment_under_review: "Payment Review",
      pending: "Order Pending",
      confirmed: "Order Confirmed",
      preparing: "Kitchen Preparing",
      ready_for_pickup: "Ready for Pickup",
      out_for_delivery: "Out for Delivery",
      completed: "Delivered"
    };

    const mapped = TRACK_STAGES.map((stage, index) => {
      const isDone = index < stageIndex;
      const isActive = index === stageIndex;
      return {
        label: stageLabel[stage] || statusLabel(stage),
        className: isDone ? "is-done" : isActive ? "is-active" : "",
        time: ""
      };
    });

    if (Array.isArray(timeline) && timeline.length) {
      timeline.forEach((item) => {
        const idx = TRACK_STAGES.indexOf(toStatusKey(item.step));
        if (idx >= 0) {
          mapped[idx].time = formatDate(item.timestamp);
        }
      });
    }

    return mapped;
  };

  const renderOrderTimeline = (order, timeline) => {
    const steps = makeTimelineSteps(order, timeline);
    return `
      <div class="order-timeline">
        ${steps
          .map(
            (step) => `
            <div class="order-timeline__item ${step.className}">
              <span class="order-timeline__dot"></span>
              <div>
                <div class="order-timeline__label">${escapeHtml(step.label)}</div>
                <div class="order-timeline__time">${escapeHtml(step.time || "Waiting")}</div>
              </div>
            </div>
          `
          )
          .join("")}
      </div>
    `;
  };

  const renderOrderCard = (order, opts = {}) => {
    const status = toStatusKey(order.order_status);
    const preview = String(order.cake_names || "").slice(0, 120);
    const showInvoice = Boolean(order.can_download_invoice);
    const thumbnail = utils.safeImage(order.preview_image || order.featured_image || "", utils.productPlaceholder);
    const reorderHint = encodeURIComponent(String(order.cake_names || "").split(",")[0]?.replace(/ x \d+$/, "").trim() || "cake");
    return `
      <article class="order-card" data-order-id="${Number(order.id || 0)}">
        <div class="order-card__head">
          <div class="order-card__summary">
            <div>
              <p class="order-card__id">${escapeHtml(order.order_number || `Order #${order.id}`)}</p>
              <p class="order-card__items">${escapeHtml(preview || `${Number(order.item_count || 0)} items`)}</p>
            </div>
            <img class="order-card__thumb" src="${escapeHtml(thumbnail)}" alt="${escapeHtml(order.order_number || `Order #${order.id}`)}" onerror="this.onerror=null;this.src='${utils.productPlaceholder}';" loading="lazy" />
          </div>
          <div>
            <span class="status-chip ${status}">${escapeHtml(statusLabel(order.order_status))}</span>
            <div class="order-card__value">${escapeHtml(utils.formatInr(order.grand_total || 0))}</div>
          </div>
        </div>
        <div class="order-card__meta">
          <span>Items: ${Number(order.item_count || 0)}</span>
          <span>Placed: ${escapeHtml(formatDate(order.created_at))}</span>
        </div>
        <div class="order-card__actions">
          <button class="customer-btn customer-btn--primary" type="button" data-order-view="${Number(order.id || 0)}">View Details</button>
          ${showInvoice ? `<a class="customer-btn customer-btn--ghost" href="${escapeHtml(order.invoice_download_url || "#")}" target="_blank" rel="noopener">Invoice</a>` : ""}
          <a class="customer-btn customer-btn--ghost" href="/category?q=${reorderHint}">Reorder</a>
          ${opts.showTrack ? `<button class="customer-btn customer-btn--ghost" type="button" data-order-track="${Number(order.id || 0)}">Track</button>` : ""}
        </div>
      </article>
    `;
  };

  const renderWishlistCard = (item) => {
    const imageUrl = utils.safeImage(item.image || item.featured_image || "", utils.productPlaceholder);
    return `
      <article class="order-card">
        <div class="order-card__head">
          <div>
            <p class="order-card__id">${escapeHtml(item.name || "Product")}</p>
            <p class="order-card__items">${escapeHtml(item.short_description || "Handcrafted delight")}</p>
          </div>
          <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item.name || "Wishlist product")}" width="66" height="66" style="border-radius:12px;object-fit:cover;" onerror="this.onerror=null;this.src='${utils.productPlaceholder}'" />
        </div>
        <div class="order-card__actions">
          <a class="customer-btn customer-btn--ghost" href="/product/${encodeURIComponent(item.slug || "")}">View</a>
          <button class="customer-btn customer-btn--primary" type="button" data-add-cart="${Number(item.product_id || 0)}">Add to Cart</button>
          <button class="customer-btn customer-btn--ghost" type="button" data-remove-wishlist="${Number(item.product_id || 0)}">Remove</button>
        </div>
      </article>
    `;
  };

  const renderAddressCard = (item) => {
    const label = item.label || "Address";
    const primaryLine = [item.line1, item.line2].filter(Boolean).join(", ");
    const locationLine = [item.landmark, item.city, item.state, item.postal_code].filter(Boolean).join(", ");
    return `
      <article class="address-card" data-address-id="${Number(item.id || 0)}">
        <div class="order-card__head">
          <div>
            <p class="order-card__id">${escapeHtml(label)}</p>
            <p class="order-card__items">${escapeHtml(item.recipient_name || "")}, ${escapeHtml(item.phone || "")}</p>
          </div>
          ${Number(item.is_default || 0) === 1 ? '<span class="customer-chip status-chip confirmed">Default</span>' : ""}
        </div>
        <p class="address-card__line">${escapeHtml(primaryLine)}</p>
        <p class="address-card__line">${escapeHtml(locationLine)}</p>
        <div class="order-card__actions">
          <button class="customer-btn customer-btn--ghost" type="button" data-edit-address="${Number(item.id || 0)}">Edit</button>
          <button class="customer-btn customer-btn--ghost" type="button" data-delete-address="${Number(item.id || 0)}">Delete</button>
        </div>
      </article>
    `;
  };

  const renderNotificationCard = (entry) => `
    <article class="notification-card ${entry.unread ? "is-unread" : ""}">
      <h4>${escapeHtml(entry.title)}</h4>
      <p class="address-card__line">${escapeHtml(entry.body)}</p>
      <p class="notification-card__meta">${escapeHtml(entry.meta)}</p>
    </article>
  `;

  const openOrderDetailPanel = (container, payload) => {
    if (!container) {
      return;
    }
    const order = payload?.order || {};
    const items = payload?.items || [];
    const timeline = payload?.timeline || [];

    container.innerHTML = `
      <div class="customer-detail__top">
        <div>
          <h3>${escapeHtml(order.order_number || `Order #${order.id || ""}`)}</h3>
          <p class="address-card__line">Placed ${escapeHtml(formatDate(order.created_at))}</p>
        </div>
        <button class="customer-btn customer-btn--ghost" type="button" data-close-detail="1">Close</button>
      </div>
      <div style="margin-top:6px;">
        <span class="status-chip ${escapeHtml(toStatusKey(order.order_status))}">${escapeHtml(statusLabel(order.order_status))}</span>
      </div>
      <div class="customer-detail__items">
        ${items
          .map(
            (item) => `
            <div class="customer-detail__item">
              <span>${escapeHtml(item.product_name_snapshot || "Item")}</span>
              <span>x${Number(item.quantity || 0)} - ${escapeHtml(utils.formatInr(item.line_total || 0))}</span>
            </div>
          `
          )
          .join("")}
      </div>
      <div class="profile-card">
        <h3>Delivery Summary</h3>
        <p class="address-card__line">Mode: ${escapeHtml(order.fulfilment_mode || "delivery")}</p>
        <p class="address-card__line">Payment: ${escapeHtml(statusLabel(order.payment_status || "pending"))}</p>
        <p class="address-card__line">Grand Total: ${escapeHtml(utils.formatInr(order.grand_total || 0))}</p>
      </div>
      ${renderOrderTimeline(order, timeline)}
      <div class="customer-detail__sticky-actions">
        ${payload?.can_download_invoice ? `<a class="customer-btn customer-btn--primary" href="${escapeHtml(payload.invoice_download_url || "#")}" target="_blank" rel="noopener">Download Invoice</a>` : ""}
        <a class="customer-btn customer-btn--ghost" href="/contact">Need Help</a>
      </div>
    `;
    container.classList.add("is-open");
  };

  const closeDetailPanel = (container) => {
    if (container) {
      container.classList.remove("is-open");
    }
  };

  const readCooldownUntil = () => Number(window.localStorage.getItem(OTP_STORAGE_KEY) || 0);

  const initCustomerLogin = () => {
    const page = document.querySelector('[data-page="customer-login"]');
    if (!page) {
      return;
    }

    const form = byId("customerLoginForm");
    const emailInput = byId("customerLoginEmail");
    const sendBtn = byId("customerSendOtpBtn");
    const otpGroup = byId("customerOtpGroup");
    const otpSlots = Array.from(page.querySelectorAll(".otp-slot"));
    const otpHiddenInput = byId("customerOtp");
    const verifyBtn = byId("customerVerifyBtn");
    const rememberDeviceInput = byId("customerRememberDevice");
    const statusEl = byId("customerLoginStatus");
    const cooldownEl = byId("customerOtpCooldown");

    if (!form || !emailInput || !sendBtn || !otpGroup || !otpHiddenInput || !verifyBtn || !statusEl || otpSlots.length !== 6) {
      return;
    }

    let cooldownTimer = null;

    const syncOtpValue = () => {
      otpHiddenInput.value = otpSlots.map((slot) => slot.value.trim()).join("").slice(0, 6);
    };

    const setStatus = (message) => {
      statusEl.textContent = message;
    };

    const focusSlot = (index) => {
      const slot = otpSlots[Math.max(0, Math.min(index, otpSlots.length - 1))];
      slot?.focus();
      slot?.select();
    };

    const toggleOtp = (show) => {
      otpGroup.hidden = !show;
      verifyBtn.hidden = !show;
    };

    const clearOtp = () => {
      otpSlots.forEach((slot) => {
        slot.value = "";
      });
      syncOtpValue();
    };

    const startCooldown = (untilTs) => {
      window.localStorage.setItem(OTP_STORAGE_KEY, String(untilTs));
      sendBtn.disabled = true;

      const tick = () => {
        const remaining = Math.ceil((untilTs - Date.now()) / 1000);
        if (remaining <= 0) {
          sendBtn.disabled = false;
          sendBtn.textContent = "Send OTP";
          cooldownEl.textContent = "";
          window.localStorage.removeItem(OTP_STORAGE_KEY);
          if (cooldownTimer) {
            window.clearInterval(cooldownTimer);
            cooldownTimer = null;
          }
          return;
        }
        sendBtn.textContent = `Resend in ${remaining}s`;
        cooldownEl.textContent = `You can resend OTP in ${remaining}s.`;
      };

      tick();
      if (cooldownTimer) {
        window.clearInterval(cooldownTimer);
      }
      cooldownTimer = window.setInterval(tick, 1000);
    };

    otpSlots.forEach((slot, index) => {
      slot.addEventListener("input", () => {
        slot.value = slot.value.replace(/\D+/g, "").slice(0, 1);
        if (slot.value && index < otpSlots.length - 1) {
          focusSlot(index + 1);
        }
        syncOtpValue();
      });

      slot.addEventListener("keydown", (event) => {
        if (event.key === "Backspace" && !slot.value && index > 0) {
          otpSlots[index - 1].value = "";
          focusSlot(index - 1);
          syncOtpValue();
        }
      });

      slot.addEventListener("paste", (event) => {
        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData)?.getData("text") || "";
        const digits = text.replace(/\D+/g, "").slice(0, 6).split("");
        if (!digits.length) {
          return;
        }
        digits.forEach((digit, digitIndex) => {
          if (otpSlots[digitIndex]) {
            otpSlots[digitIndex].value = digit;
          }
        });
        syncOtpValue();
        focusSlot(Math.min(digits.length, otpSlots.length - 1));
      });
    });

    const existingCooldown = readCooldownUntil();
    if (existingCooldown > Date.now()) {
      startCooldown(existingCooldown);
      toggleOtp(true);
    }

    sendBtn.addEventListener("click", async () => {
      const email = emailInput.value.trim();
      if (!email) {
        setStatus("Please enter your email address first.");
        return;
      }

      sendBtn.disabled = true;
      sendBtn.textContent = "Sending...";
      setStatus("Sending OTP...");

      try {
        const response = await fetch("/api/send-otp", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": window.__csrf || utils.getCsrfToken() || ""
          },
          credentials: "include",
          body: JSON.stringify({ email })
        });
        const data = await response.json();

        if (data.success) {
          const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
          startCooldown(cooldownUntil);
          toggleOtp(true);
          clearOtp();
          focusSlot(0);
          setStatus("OTP sent. It is valid for 5 minutes.");
          return;
        }

        if (response.status === 429) {
          const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
          startCooldown(cooldownUntil);
          toggleOtp(true);
        } else {
          sendBtn.disabled = false;
          sendBtn.textContent = "Send OTP";
        }
        setStatus(data.message || "Unable to send OTP.");
      } catch (error) {
        sendBtn.disabled = false;
        sendBtn.textContent = "Send OTP";
        setStatus(error.message || "Unable to send OTP right now.");
      }
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      syncOtpValue();
      const email = emailInput.value.trim();
      const otp = otpHiddenInput.value.trim();
      if (!email || otp.length !== 6) {
        setStatus("Please enter your email and 6 digit OTP.");
        return;
      }

      verifyBtn.disabled = true;
      verifyBtn.textContent = "Verifying...";
      setStatus("Verifying OTP...");

      try {
        const response = await fetch("/api/verify-otp", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": window.__csrf || utils.getCsrfToken() || ""
          },
          credentials: "include",
          body: JSON.stringify({
            email,
            otp,
            remember_device: Boolean(rememberDeviceInput?.checked) ? "1" : "0"
          })
        });
        const data = await response.json();

        if (!data.success) {
          setStatus(data.message || "OTP verification failed.");
          verifyBtn.disabled = false;
          verifyBtn.textContent = "Verify & Continue";
          return;
        }

        try {
          await utils.apiGet("/api/auth/me");
        } catch (authError) {
          setStatus(authError.message || "Login succeeded but session validation failed. Please retry.");
          verifyBtn.disabled = false;
          verifyBtn.textContent = "Verify & Continue";
          return;
        }

        setStatus("Verified. Redirecting to your dashboard...");
        window.location.href = data?.data?.redirect_to || "/account";
      } catch (error) {
        setStatus(error.message || "Unable to verify OTP right now.");
        verifyBtn.disabled = false;
        verifyBtn.textContent = "Verify & Continue";
      }
    });
  };

  const initDashboardTabs = () => {
    const page = document.querySelector('[data-page="customer-dashboard"]');
    if (!page) {
      return null;
    }

    const buttons = Array.from(page.querySelectorAll("[data-dashboard-tab]"));
    const panels = Array.from(page.querySelectorAll("[data-dashboard-panel]"));

    const activate = (tab) => {
      buttons.forEach((btn) => {
        btn.classList.toggle("is-active", btn.dataset.dashboardTab === tab);
      });
      panels.forEach((panel) => {
        panel.hidden = panel.dataset.dashboardPanel !== tab;
      });
    };

    buttons.forEach((button) => {
      button.addEventListener("click", () => {
        activate(button.dataset.dashboardTab || "home");
      });
    });

    activate("home");
    return { activate };
  };

  const initCustomerAccount = async () => {
    const page = document.querySelector('[data-page="customer-dashboard"]');
    if (!page) {
      return;
    }

    const tabs = initDashboardTabs();
    const authGate = byId("customerDashboardAuthGate");
    const dashboardName = byId("customerDashboardName");
    const dashboardAvatar = byId("customerDashboardAvatar");
    const profileForm = byId("customerProfileForm");
    const profileStatus = byId("customerProfileStatus");
    const ordersRecent = byId("customerOrdersRecent");
    const ordersAll = byId("customerOrdersAll");
    const addressesWrap = byId("customerAddresses");
    const addressForm = byId("customerAddressForm");
    const wishlistWrap = byId("customerWishlist");
    const notificationsWrap = byId("customerNotifications");
    const trackForm = byId("customerTrackForm");
    const trackResult = byId("customerTrackResult");
    const statOrders = byId("customerStatOrders");
    const statSpent = byId("customerStatSpent");
    const statWishlist = byId("customerStatWishlist");
    const statAddresses = byId("customerStatAddresses");
    const statPending = byId("customerStatPending");
    const statDelivered = byId("customerStatDelivered");
    const statCancelled = byId("customerStatCancelled");
    const statRefunds = byId("customerStatRefunds");
    const detailPanel = byId("customerOrderDetail");
    const logoutBtn = byId("customerLogoutBtn");

    const state = {
      orders: [],
      wishlist: [],
      addresses: []
    };

    const renderOrders = () => {
      const list = state.orders;
      const recent = list.slice(0, 3);
      ordersRecent.innerHTML = recent.length
        ? recent.map((item) => renderOrderCard(item, { showTrack: true })).join("")
        : '<div class="customer-empty">No orders yet. Your sweetest stories will appear here.</div>';
      ordersAll.innerHTML = list.length
        ? list.map((item) => renderOrderCard(item, { showTrack: true })).join("")
        : '<div class="customer-empty">No active or historical orders found.</div>';

      statOrders.textContent = String(list.length);
      const spend = list.reduce((sum, row) => sum + Number(row.grand_total || 0), 0);
      statSpent.textContent = utils.formatInr(spend);

      if (statPending) {
        statPending.textContent = String(
          list.filter((row) => {
            const status = toStatusKey(row.order_status);
            return status === "pending" || status === "pending_payment" || status === "payment_under_review" || status === "confirmed" || status === "preparing" || status === "out_for_delivery";
          }).length
        );
      }
      if (statDelivered) {
        statDelivered.textContent = String(
          list.filter((row) => {
            const status = toStatusKey(row.order_status);
            return status === "delivered" || status === "completed";
          }).length
        );
      }
      if (statCancelled) {
        statCancelled.textContent = String(
          list.filter((row) => {
            const status = toStatusKey(row.order_status);
            return status === "cancelled" || status === "rejected";
          }).length
        );
      }
      if (statRefunds) {
        statRefunds.textContent = String(
          list.filter((row) => {
            const status = toStatusKey(row.order_status);
            return status === "refund_requested" || status === "partially_refunded" || status === "fully_refunded" || status === "refunded";
          }).length
        );
      }
    };

    const renderWishlist = () => {
      const list = state.wishlist;
      wishlistWrap.innerHTML = list.length
        ? list.map((item) => renderWishlistCard(item)).join("")
        : '<div class="customer-empty">Wishlist is empty. Add favorites to revisit quickly.</div>';
      statWishlist.textContent = String(list.length);
    };

    const renderAddresses = () => {
      const list = state.addresses;
      addressesWrap.innerHTML = list.length
        ? list.map((item) => renderAddressCard(item)).join("")
        : '<div class="customer-empty">No saved addresses. Add one for smooth checkout.</div>';
      statAddresses.textContent = String(list.length);
    };

    const renderNotifications = () => {
      const list = state.orders.slice(0, 5).map((order, index) => ({
        title: `${order.order_number || `Order #${order.id}`}: ${statusLabel(order.order_status)}`,
        body: `Your order total is ${utils.formatInr(order.grand_total || 0)} and currently marked as ${statusLabel(order.order_status)}.`,
        meta: `Updated ${formatDate(order.created_at)}`,
        unread: index < 2
      }));
      notificationsWrap.innerHTML = list.length
        ? list.map((item) => renderNotificationCard(item)).join("")
        : '<div class="customer-empty">Notifications will appear here once your order journey starts.</div>';
    };

    const loadOrderDetail = async (orderId) => {
      try {
        const payload = await utils.apiGet(`/api/orders/${orderId}`);
        openOrderDetailPanel(detailPanel, payload.data || {});
      } catch (error) {
        window.alert(error.message || "Unable to load order details.");
      }
    };

    const bindOrderInteractions = (container) => {
      container?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const orderId = Number(target.dataset.orderView || target.dataset.orderTrack || 0);
        if (orderId) {
          if (target.dataset.orderTrack) {
            tabs?.activate("tracking");
          }
          await loadOrderDetail(orderId);
        }
      });
    };

    bindOrderInteractions(ordersRecent);
    bindOrderInteractions(ordersAll);

    detailPanel?.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      if (target.dataset.closeDetail) {
        closeDetailPanel(detailPanel);
      }
    });

    let authPayload;
    try {
      authPayload = await utils.apiGet("/api/auth/me");
    } catch (_) {
      if (authGate) {
        authGate.hidden = false;
      }
      return;
    }

    if (authGate) {
      authGate.hidden = true;
    }

    const authUser = authPayload?.data?.user || {};

    const [profileResult, ordersResult, wishlistResult, addressesResult] = await Promise.allSettled([
      utils.apiGet("/api/account/profile"),
      utils.apiGet("/api/orders"),
      utils.apiGet("/api/wishlist"),
      utils.apiGet("/api/account/addresses")
    ]);

    const profilePayload = profileResult.status === "fulfilled" ? profileResult.value : null;
    const user = profilePayload?.data?.user || authUser;
    const profile = profilePayload?.data?.profile || {};

    const initials = String(user.full_name || "C")
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join("") || "C";

    dashboardName.textContent = user.full_name || "Cakeouflage Guest";
    dashboardAvatar.textContent = initials;

    byId("customerProfileName").value = user.full_name || "";
    byId("customerProfileEmail").value = user.email || "";
    byId("customerProfilePhone").value = user.phone || "";
    byId("customerProfileDob").value = profile.date_of_birth || "";
    byId("customerProfileDoa").value = profile.doa || "";

    state.orders = ordersResult.status === "fulfilled" ? (ordersResult.value.data?.items || []) : [];
    state.wishlist = wishlistResult.status === "fulfilled" ? (wishlistResult.value.data?.items || []) : [];
    state.addresses = addressesResult.status === "fulfilled" ? (addressesResult.value.data?.items || []) : [];

    renderOrders();
    renderWishlist();
    renderAddresses();
    renderNotifications();

    profileForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(profileForm).entries());
      try {
        await utils.apiPatch("/api/account/profile", payload);
        profileStatus.textContent = "Profile updated successfully.";
      } catch (error) {
        profileStatus.textContent = error.message || "Unable to update profile.";
      }
    });

    const resetAddressForm = () => {
      if (!addressForm) {
        return;
      }
      addressForm.reset();
      byId("customerAddressId").value = "";
      byId("customerAddressSubmit").textContent = "Save Address";
    };

    addressForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(addressForm).entries());
      payload.is_default = payload.is_default ? 1 : 0;
      const addressId = Number(payload.address_id || 0);
      delete payload.address_id;

      try {
        if (addressId) {
          await utils.apiPatch(`/api/account/addresses/${addressId}`, payload);
        } else {
          await utils.apiPost("/api/account/addresses", payload);
        }
        const addressesPayload = await utils.apiGet("/api/account/addresses");
        state.addresses = addressesPayload.data?.items || [];
        renderAddresses();
        resetAddressForm();
      } catch (error) {
        window.alert(error.message || "Unable to save address.");
      }
    });

    byId("customerAddressReset")?.addEventListener("click", () => {
      resetAddressForm();
    });

    addressesWrap?.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const editId = Number(target.dataset.editAddress || 0);
      if (editId) {
        const selected = state.addresses.find((entry) => Number(entry.id) === editId);
        if (!selected) {
          return;
        }
        byId("customerAddressId").value = String(selected.id || "");
        byId("addressLabel").value = selected.label || "";
        byId("addressRecipientName").value = selected.recipient_name || "";
        byId("addressPhone").value = selected.phone || "";
        byId("addressLine1").value = selected.line1 || "";
        byId("addressLine2").value = selected.line2 || "";
        byId("addressLandmark").value = selected.landmark || "";
        byId("addressCity").value = selected.city || "";
        byId("addressState").value = selected.state || "";
        byId("addressPostalCode").value = selected.postal_code || "";
        byId("addressIsDefault").checked = Number(selected.is_default || 0) === 1;
        byId("customerAddressSubmit").textContent = "Update Address";
        tabs?.activate("addresses");
        return;
      }

      const deleteId = Number(target.dataset.deleteAddress || 0);
      if (!deleteId) {
        return;
      }
      if (!window.confirm("Delete this address?")) {
        return;
      }

      try {
        await utils.apiDelete(`/api/account/addresses/${deleteId}`);
        const addressesPayload = await utils.apiGet("/api/account/addresses");
        state.addresses = addressesPayload.data?.items || [];
        renderAddresses();
      } catch (error) {
        window.alert(error.message || "Unable to delete address.");
      }
    });

    wishlistWrap?.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const removeId = Number(target.dataset.removeWishlist || 0);
      if (removeId) {
        try {
          await utils.apiDelete(`/api/wishlist/items/${removeId}`);
          const payload = await utils.apiGet("/api/wishlist");
          state.wishlist = payload.data?.items || [];
          renderWishlist();
        } catch (error) {
          window.alert(error.message || "Unable to remove wishlist item.");
        }
        return;
      }

      const addCartId = Number(target.dataset.addCart || 0);
      if (!addCartId) {
        return;
      }
      try {
        await window.CakeouflageCart?.addItem(addCartId, null, 1);
        target.textContent = "Added";
      } catch (error) {
        window.alert(error.message || "Unable to add to cart.");
      }
    });

    trackForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const reference = String(byId("customerTrackReference")?.value || "").trim().toLowerCase();
      if (!reference) {
        trackResult.innerHTML = '<div class="customer-empty">Enter order number or order ID to track.</div>';
        return;
      }
      const found = state.orders.find((item) => {
        const orderNo = String(item.order_number || "").trim().toLowerCase();
        const orderId = String(item.id || "").trim().toLowerCase();
        return orderNo === reference || orderId === reference;
      });

      if (!found) {
        trackResult.innerHTML = '<div class="customer-empty">No matching order found for this reference.</div>';
        return;
      }

      try {
        const payload = await utils.apiGet(`/api/orders/${found.id}`);
        const data = payload.data || {};
        trackResult.innerHTML = `
          <article class="profile-card">
            <h3>${escapeHtml(found.order_number || `Order #${found.id}`)}</h3>
            <p class="address-card__line">Current status: <span class="status-chip ${escapeHtml(toStatusKey(found.order_status))}">${escapeHtml(statusLabel(found.order_status))}</span></p>
            ${renderOrderTimeline(data.order || found, data.timeline || [])}
          </article>
        `;
      } catch (error) {
        trackResult.innerHTML = `<div class="customer-empty">${escapeHtml(error.message || "Unable to fetch tracking details.")}</div>`;
      }
    });

    logoutBtn?.addEventListener("click", async (event) => {
      event.preventDefault();
      try {
        await utils.apiPost("/api/auth/logout", {});
      } catch (_) {
        // proceed to login even if logout API fails silently
      }
      window.location.href = "/login";
    });
  };

  const initCustomerOrders = async () => {
    const page = document.querySelector('[data-page="customer-orders"]');
    if (!page) {
      return;
    }

    const gate = byId("customerOrdersAuthGate");
    const listWrap = byId("customerOrdersList");
    const detailPanel = byId("customerOrdersDetail");
    const searchInput = byId("customerOrdersSearch");
    const statusFilter = byId("customerOrdersStatusFilter");

    let allOrders = [];

    const renderList = (items) => {
      listWrap.innerHTML = items.length
        ? items.map((item) => renderOrderCard(item, { showTrack: true })).join("")
        : '<div class="customer-empty">No orders found for this filter.</div>';
    };

    const applyFilters = () => {
      const q = String(searchInput?.value || "").trim().toLowerCase();
      const selectedStatus = String(statusFilter?.value || "all").toLowerCase();
      const filtered = allOrders.filter((item) => {
        const matchesStatus = selectedStatus === "all" || toStatusKey(item.order_status) === selectedStatus;
        const orderNum = String(item.order_number || "").toLowerCase();
        const cakeNames = String(item.cake_names || "").toLowerCase();
        const matchesQuery = !q || orderNum.includes(q) || cakeNames.includes(q);
        return matchesStatus && matchesQuery;
      });
      renderList(filtered);
    };

    try {
      const payload = await utils.apiGet("/api/orders");
      allOrders = payload.data?.items || [];
      gate.hidden = true;
      renderList(allOrders);
    } catch (_) {
      gate.hidden = false;
      listWrap.innerHTML = "";
      return;
    }

    listWrap.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      const orderId = Number(target.dataset.orderView || target.dataset.orderTrack || 0);
      if (!orderId) {
        return;
      }
      try {
        const payload = await utils.apiGet(`/api/orders/${orderId}`);
        openOrderDetailPanel(detailPanel, payload.data || {});
      } catch (error) {
        window.alert(error.message || "Unable to fetch order detail.");
      }
    });

    detailPanel?.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      if (target.dataset.closeDetail) {
        closeDetailPanel(detailPanel);
      }
    });

    searchInput?.addEventListener("input", applyFilters);
    statusFilter?.addEventListener("change", applyFilters);
  };

  const initCustomerWishlist = async () => {
    const page = document.querySelector('[data-page="customer-wishlist"]');
    if (!page) {
      return;
    }

    const gate = byId("customerWishlistAuthGate");
    const grid = byId("customerWishlistGrid");

    const load = async () => {
      const payload = await utils.apiGet("/api/wishlist");
      const items = payload.data?.items || [];
      grid.innerHTML = items.length
        ? items.map((item) => renderWishlistCard(item)).join("")
        : '<div class="customer-empty">Your wishlist is empty right now.</div>';
    };

    try {
      gate.hidden = true;
      await load();
    } catch (_) {
      gate.hidden = false;
      grid.innerHTML = "";
      return;
    }

    grid.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const productId = Number(target.dataset.removeWishlist || 0);
      if (productId) {
        try {
          await utils.apiDelete(`/api/wishlist/items/${productId}`);
          await load();
        } catch (error) {
          window.alert(error.message || "Unable to remove item.");
        }
        return;
      }

      const cartProductId = Number(target.dataset.addCart || 0);
      if (!cartProductId) {
        return;
      }
      try {
        await window.CakeouflageCart?.addItem(cartProductId, null, 1);
        target.textContent = "Added";
      } catch (error) {
        window.alert(error.message || "Unable to add to cart.");
      }
    });
  };

  document.addEventListener("DOMContentLoaded", () => {
    initCustomerLogin();
    initCustomerAccount();
    initCustomerOrders();
    initCustomerWishlist();
  });
})();
