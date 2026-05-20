document.addEventListener("DOMContentLoaded", () => {
  const body = document.body;
  const siteHeader = document.getElementById("siteHeader");
  const announcementBar = document.getElementById("announcementBar");
  const announcementClose = document.getElementById("announcementClose");
  const desktopCategoriesItem = document.querySelector(".site-nav__item--mega");
  const desktopCategoriesToggle = document.getElementById("desktopCategoriesToggle");
  const drawer = document.getElementById("mobileDrawer");
  const mobileMenuToggle = document.getElementById("mobileMenuToggle");
  const mobileClose = document.getElementById("mobileClose");
  const mobileBackdrop = document.getElementById("mobileBackdrop");
  const searchOverlay = document.getElementById("searchOverlay");
  const searchToggle = document.getElementById("searchToggle");
  const searchClose = document.getElementById("searchClose");
  const searchInput = document.getElementById("searchInput");
  const cartCountDesktop = document.getElementById("cartCount");
  const cartCountMobile = document.getElementById("mobileCartCount");
  const desktopMediaQuery = window.matchMedia("(min-width: 1024px)") || {};

  const syncBodyState = () => {
    const drawerOpen = drawer?.classList.contains("is-open");
    const searchOpen = searchOverlay?.classList.contains("is-open");
    body.classList.toggle("drawer-open", Boolean(drawerOpen || searchOpen));
  };

  if (siteHeader) {
    const onScroll = () => {
      siteHeader.classList.toggle("is-scrolled", window.scrollY > 10);
    };

    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  announcementClose?.addEventListener("click", () => {
    if (!announcementBar) return;
    announcementBar.hidden = true;
  });

  const openDesktopMega = () => {
    desktopCategoriesItem?.classList.add("is-open");
    desktopCategoriesToggle?.setAttribute("aria-expanded", "true");
  };

  const closeDesktopMega = () => {
    desktopCategoriesItem?.classList.remove("is-open");
    desktopCategoriesToggle?.setAttribute("aria-expanded", "false");
  };

  if (desktopCategoriesItem && desktopCategoriesToggle) {
    desktopCategoriesItem.addEventListener("mouseenter", openDesktopMega);
    desktopCategoriesItem.addEventListener("mouseleave", closeDesktopMega);
    desktopCategoriesItem.addEventListener("focusin", openDesktopMega);
    desktopCategoriesItem.addEventListener("focusout", (event) => {
      if (desktopCategoriesItem.contains(event.relatedTarget)) {
        return;
      }

      closeDesktopMega();
    });

    desktopCategoriesToggle.addEventListener("click", (event) => {
      event.preventDefault();

      if (desktopCategoriesItem.classList.contains("is-open")) {
        closeDesktopMega();
      } else {
        openDesktopMega();
      }
    });

    document.addEventListener("click", (event) => {
      if (!desktopCategoriesItem.contains(event.target)) {
        closeDesktopMega();
      }
    });
  }

  const closeSearch = () => {
    if (!searchOverlay) return;

    searchOverlay.classList.remove("is-open");
    searchOverlay.setAttribute("aria-hidden", "true");
    searchOverlay.hidden = true;
    searchToggle?.setAttribute("aria-expanded", "false");
    syncBodyState();
  };

  const openDrawer = () => {
    if (!drawer) return;

    closeDesktopMega();
    closeSearch();
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    mobileBackdrop?.classList.add("is-visible");
    mobileMenuToggle?.setAttribute("aria-expanded", "true");
    mobileMenuToggle?.classList.add("is-open");
    syncBodyState();
    window.setTimeout(() => mobileClose?.focus(), 60);
  };

  const closeDrawer = () => {
    if (!drawer) return;

    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    mobileBackdrop?.classList.remove("is-visible");
    mobileMenuToggle?.setAttribute("aria-expanded", "false");
    mobileMenuToggle?.classList.remove("is-open");
    syncBodyState();
  };

  mobileMenuToggle?.addEventListener("click", () => {
    if (drawer?.classList.contains("is-open")) {
      closeDrawer();
    } else {
      openDrawer();
    }
  });

  mobileClose?.addEventListener("click", closeDrawer);
  mobileBackdrop?.addEventListener("click", closeDrawer);

  const openSearch = () => {
    if (!searchOverlay) return;

    closeDrawer();
    closeDesktopMega();
    searchOverlay.hidden = false;
    searchOverlay.classList.add("is-open");
    searchOverlay.setAttribute("aria-hidden", "false");
    searchToggle?.setAttribute("aria-expanded", "true");
    syncBodyState();
    window.setTimeout(() => searchInput?.focus(), 60);
  };

  searchToggle?.addEventListener("click", () => {
    if (searchOverlay?.classList.contains("is-open")) {
      closeSearch();
    } else {
      openSearch();
    }
  });

  searchClose?.addEventListener("click", closeSearch);
  searchOverlay?.addEventListener("click", (event) => {
    if (event.target === searchOverlay) {
      closeSearch();
    }
  });

  drawer?.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    if (target.closest("a[href]")) {
      closeDrawer();
    }
  });

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const trigger = target.closest("[data-accordion-trigger]");
    if (!trigger) return;

    const panelId = trigger.getAttribute("data-accordion-trigger");
    if (!panelId) return;

    const panel = document.getElementById(panelId);
    if (!panel) return;

    const expanded = trigger.getAttribute("aria-expanded") === "true";
    trigger.setAttribute("aria-expanded", String(!expanded));
    panel.hidden = expanded;
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeDrawer();
      closeSearch();
      closeDesktopMega();
    }
  });

 const syncViewportState = (mediaQuery) => {
  if (!mediaQuery) return;

  if (mediaQuery.matches) {
    if (drawer) closeDrawer();
  } else {
    closeDesktopMega();
    closeSearch();
  }
};

  if (typeof desktopMediaQuery.addEventListener === "function") {
    desktopMediaQuery.addEventListener("change", syncViewportState);
  } else if (typeof desktopMediaQuery.addListener === "function") {
    desktopMediaQuery.addListener(syncViewportState);
  }

  syncViewportState(desktopMediaQuery);

  const updateCartCount = (value) => {
    const numericValue = Number.parseInt(String(value ?? "0"), 10);
    const safeValue = Number.isFinite(numericValue) && numericValue > 0 ? numericValue : 0;
    const textValue = String(safeValue);

    try {
      window.localStorage.setItem("cakeouflage_cart_count", textValue);
    } catch (error) {
      // Ignore storage failures so the badge still renders from in-memory state.
    }

    if (cartCountDesktop) {
      cartCountDesktop.textContent = textValue;
      cartCountDesktop.classList.toggle("has-items", safeValue > 0);
    }

    if (cartCountMobile) {
      cartCountMobile.textContent = textValue;
    }
  };

  if (cartCountDesktop) {
   updateCartCount(cartCountDesktop.textContent);
  }

  try {
    const storedCount = window.localStorage.getItem("cakeouflage_cart_count");
    if (storedCount !== null) {
      updateCartCount(storedCount);
    } else {
      updateCartCount(cartCountDesktop?.textContent);
    }
  } catch (error) {
    updateCartCount(cartCountDesktop?.textContent);
  }

  document.addEventListener("cart:updated", (event) => {
    const detail = event.detail ?? {};
    const count =
      detail?.data?.item_count ??
      detail?.item_count ??
      detail?.count ??
      detail?.cart?.item_count;

    updateCartCount(count);
  });

  document.addEventListener("click", async (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const copyTrigger = target.closest("[data-copy-code]");
    if (!copyTrigger) return;

    const code = String(copyTrigger.getAttribute("data-copy-code") || "").trim();
    if (code === "") return;

    const originalLabel = copyTrigger.textContent || "Copy code";
    const showFeedback = (label) => {
      copyTrigger.textContent = label;
      window.setTimeout(() => {
        copyTrigger.textContent = originalLabel;
      }, 1600);
    };

    try {
      if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
        await navigator.clipboard.writeText(code);
      } else {
        const helper = document.createElement("textarea");
        helper.value = code;
        helper.setAttribute("readonly", "readonly");
        helper.style.position = "absolute";
        helper.style.left = "-9999px";
        document.body.appendChild(helper);
        helper.select();
        document.execCommand("copy");
        document.body.removeChild(helper);
      }

      showFeedback("Copied!");
    } catch (copyError) {
      showFeedback("Copy failed");
    }
  });
});
