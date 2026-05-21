document.addEventListener("DOMContentLoaded", () => {
  const utils = window.CakeouflageUtils;
  if (!utils) {
    return;
  }

  const countEl = document.getElementById("cartCount");

  const setCartCount = (count) => {
    const safeCount = Number.isFinite(count) ? count : 0;
    if (countEl) {
      countEl.textContent = String(safeCount);
    }
    localStorage.setItem("cakeouflage_cart_count", String(safeCount));
  };

  const hydrateCount = async () => {
    try {
      const payload = await utils.apiGet("/api/cart");
      setCartCount(Number(payload.data?.item_count || 0));
      return payload.data;
    } catch (error) {
      const fallback = Number(localStorage.getItem("cakeouflage_cart_count") || 0);
      setCartCount(fallback);
      return null;
    }
  };

  const renderCartPage = async () => {
    const page = document.querySelector('[data-page="cart"]');
    if (!page) {
      await hydrateCount();
      return;
    }

    const itemsContainer = document.getElementById("cartItemsContainer");
    const subtotalEl = document.getElementById("cartSubtotal");
    const discountEl = document.getElementById("cartDiscount");
    const totalEl = document.getElementById("cartGrandTotal");
    const couponInput = document.getElementById("couponInput");
    const couponButton = document.getElementById("applyCouponBtn");

    const render = (cart) => {
      setCartCount(Number(cart.item_count || 0));

      subtotalEl.textContent = utils.formatInr(cart.subtotal || 0);
      discountEl.textContent = utils.formatInr(cart.discount_total || 0);
      totalEl.textContent = utils.formatInr(cart.grand_total || 0);

      if (!cart.items || cart.items.length === 0) {
        itemsContainer.innerHTML = '<p class="text-muted">Your cart is empty. Browse cakes to start ordering.</p>';
        return;
      }

      const rows = cart.items
        .map((item) => {
          return `
            <article class="cart-item" data-item-id="${item.id}">
              <div>
                <h3>${item.product_name}</h3>
                <p class="text-muted">${item.variant_label || "Standard"}</p>
               <a href="/Cakeouflage-E-commerce/product/${item.product_slug}" class="link-inline">
  View Product
</a>
                ${item.cake_message ? `<p style="font-size:.8rem;color:#7a6870;margin-top:3px;">🎂 ${item.cake_message}</p>` : ''}
                ${item.topper_name_snapshot && item.topper_name_snapshot !== 'No Topper' ? `<p style="font-size:.8rem;color:#7a6870;margin-top:2px;">🎀 ${item.topper_name_snapshot}${parseFloat(item.topper_price||0) > 0 ? ' (+₹' + Math.round(item.topper_price) + ')' : ''}</p>` : ''}
              </div>
              <div class="cart-item__actions">
                <div class="qty-stepper">
                  <button type="button" class="qty-stepper__btn" data-role="qty-dec" aria-label="Decrease quantity">−</button>
                  <input class="qty-stepper__input" type="number" min="1" value="${item.quantity}" data-role="qty" aria-label="Quantity" />
                  <button type="button" class="qty-stepper__btn" data-role="qty-inc" aria-label="Increase quantity">+</button>
                </div>
                <strong>${utils.formatInr(item.line_total)}</strong>
                <button class="btn btn--secondary" data-role="remove" type="button">Remove</button>
              </div>
            </article>
          `;
        })
        .join("");

      itemsContainer.innerHTML = rows;
    };

    const load = async () => {
      try {
        const payload = await utils.apiGet("/api/cart");
        render(payload.data);
      } catch (error) {
        itemsContainer.innerHTML = `<p class="text-muted">${error.message}</p>`;
      }
    };

    itemsContainer.addEventListener("change", async (event) => {
      const input = event.target;
      if (!(input instanceof HTMLInputElement) || input.dataset.role !== "qty") {
        return;
      }

      const itemEl = input.closest("[data-item-id]");
      if (!itemEl) {
        return;
      }

      const itemId = itemEl.getAttribute("data-item-id");
      const quantity = Math.max(1, Number(input.value || 1));

      try {
        const payload = await utils.apiPatch(`/api/cart/items/${itemId}`, { quantity });
        render(payload.data);
      } catch (error) {
        alert(error.message);
      }
    });

    itemsContainer.addEventListener("click", async (event) => {
      const button = event.target;
      if (!(button instanceof HTMLElement)) return;

      // Quantity stepper buttons
      if (button.dataset.role === 'qty-dec' || button.dataset.role === 'qty-inc') {
        const itemEl = button.closest('[data-item-id]');
        if (!itemEl) return;
        const qtyInput = itemEl.querySelector('[data-role="qty"]');
        if (!qtyInput) return;
        const delta = button.dataset.role === 'qty-dec' ? -1 : 1;
        qtyInput.value = Math.max(1, (Number(qtyInput.value) || 1) + delta);
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }

      if (button.dataset.role !== "remove") {
        return;
      }

      const itemEl = button.closest("[data-item-id]");
      if (!itemEl) {
        return;
      }

      const itemId = itemEl.getAttribute("data-item-id");
      try {
        const payload = await utils.apiDelete(`/api/cart/items/${itemId}`);
        render(payload.data);
      } catch (error) {
        alert(error.message);
      }
    });

    couponButton?.addEventListener("click", async () => {
      try {
        const payload = await utils.apiPost("/api/cart/coupon", {
          code: (couponInput?.value || "").trim()
        });
        render(payload.data);
      } catch (error) {
        alert(error.message);
      }
    });

    await load();
  };

  window.CakeouflageCart = {
    async addItem(productId, variantId, quantity = 1, extras = {}) {
      const payload = await utils.apiPost("/api/cart/items", {
        product_id: productId,
        variant_id: variantId,
        quantity,
        ...extras
      });
      setCartCount(Number(payload.data?.item_count || 0));
      return payload;
    },
    syncCount: hydrateCount
  };

  void renderCartPage();
});
