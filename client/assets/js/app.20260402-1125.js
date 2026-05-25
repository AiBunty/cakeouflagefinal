document.addEventListener("DOMContentLoaded", () => {
  document.documentElement.classList.add("app-ready");

  const utils = window.CakeouflageUtils;
  if (!utils) {
    return;
  }

  const BASE_PATH = ((window.BASE_URL || "") + "").replace(/\/$/, "");

  const toCategoryUrl = (slug) => `${BASE_PATH}/category/${slug}`.replace(/^\/\//, "/");

  const buildCategoryTreeMarkup = (items, mode, activeSlug = "", selectedCategory = "") => {
    const normalized = Array.isArray(items) ? items : [];
    const byParent = new Map();
    normalized.forEach((item) => {
      const parentKey = item.parent_id == null ? "root" : String(item.parent_id);
      if (!byParent.has(parentKey)) {
        byParent.set(parentKey, []);
      }
      byParent.get(parentKey).push(item);
    });

    const roots = byParent.get("root") || [];
    const findRootForSlug = (slug) => {
      if (!slug) return "";
      for (const root of roots) {
        if (String(root.slug || "") === slug) return String(root.slug || "");
        const children = byParent.get(String(root.id || "")) || [];
        if (children.some((child) => String(child.slug || "") === slug)) {
          return String(root.slug || "");
        }
      }
      return "";
    };

    const initiallyOpenRoot = findRootForSlug(activeSlug || selectedCategory) || String((roots[0] || {}).slug || "");

    return roots.map((root) => {
      const rootId = String(root.id || "");
      const rootSlug = String(root.slug || "");
      const children = byParent.get(rootId) || [];
      const countText = Number(root.product_count || 0);
      const isOpen = rootSlug === initiallyOpenRoot;
      const isActiveRoot = activeSlug === rootSlug || selectedCategory === rootSlug;

      const rootRow = mode === "shop"
        ? `<label class="category-tree__link ${isActiveRoot ? "is-active" : ""}"><input type="radio" name="filterCat" value="${rootSlug}" ${isActiveRoot ? "checked" : ""} /><span class="category-tree__label"><span class="category-tree__thumb">${(String(root.name || "?").trim().charAt(0) || "?").toUpperCase()}</span><span class="category-tree__name">${root.name}</span><span class="category-tree__count">${countText}</span></span></label>`
        : `<a class="category-tree__link ${isActiveRoot ? "is-active" : ""}" href="${toCategoryUrl(rootSlug)}"><span class="category-tree__label"><span class="category-tree__thumb">${(String(root.name || "?").trim().charAt(0) || "?").toUpperCase()}</span><span class="category-tree__name">${root.name}</span><span class="category-tree__count">${countText}</span></span></a>`;

      const childrenRows = children.map((child) => {
        const childSlug = String(child.slug || "");
        const isActiveChild = activeSlug === childSlug || selectedCategory === childSlug;
        const childCount = Number(child.product_count || 0);
        if (mode === "shop") {
          return `<label class="category-tree__sub-link ${isActiveChild ? "is-active" : ""}"><input type="radio" name="filterCat" value="${childSlug}" ${isActiveChild ? "checked" : ""} /><span>${child.name}</span><span class="category-tree__count">${childCount}</span></label>`;
        }
        return `<a class="category-tree__sub-link ${isActiveChild ? "is-active" : ""}" href="${toCategoryUrl(childSlug)}"><span>${child.name}</span><span class="category-tree__count">${childCount}</span></a>`;
      }).join("");

      return `
        <section class="category-accordion ${isOpen ? "is-open" : ""}" data-root-slug="${rootSlug}">
          <button type="button" class="category-accordion__trigger" aria-expanded="${isOpen ? "true" : "false"}">
            <span class="category-accordion__title">${root.name}</span>
            <span class="category-accordion__arrow" aria-hidden="true">▾</span>
          </button>
          <div class="category-accordion__panel" ${isOpen ? "" : "hidden"}>
            ${rootRow}
            ${childrenRows}
          </div>
        </section>
      `;
    }).join("");
  };

  const bindAccordionBehavior = (container) => {
    if (!container) return;
    const triggers = Array.from(container.querySelectorAll(".category-accordion__trigger"));
    triggers.forEach((trigger) => {
      trigger.addEventListener("click", () => {
        const section = trigger.closest(".category-accordion");
        if (!(section instanceof HTMLElement)) return;
        const isOpen = section.classList.contains("is-open");
        const all = Array.from(container.querySelectorAll(".category-accordion"));
        all.forEach((item) => {
          const panel = item.querySelector(".category-accordion__panel");
          const t = item.querySelector(".category-accordion__trigger");
          item.classList.remove("is-open");
          if (panel instanceof HTMLElement) panel.hidden = true;
          if (t instanceof HTMLElement) t.setAttribute("aria-expanded", "false");
        });
        if (!isOpen) {
          const panel = section.querySelector(".category-accordion__panel");
          section.classList.add("is-open");
          if (panel instanceof HTMLElement) panel.hidden = false;
          trigger.setAttribute("aria-expanded", "true");
        }
      });
    });
  };

  const renderShop = async () => {
    const page = document.querySelector('[data-page="shop"]');
    if (!page) {
      return;
    }

    const grid = document.getElementById("shopGrid");
    const categoryWrap = document.getElementById("filterCategories");
    const sortEl = document.getElementById("shopSort");
    const searchEl = document.getElementById("shopSearch");
    const applyBtn = document.getElementById("applyFiltersBtn");
    const clearBtn = document.getElementById("clearFiltersBtn");
    const countEl = document.getElementById("shopCount");
    const priceBucketEl = document.getElementById("priceBucket");
    const maxPriceInput = document.getElementById("maxPriceInput");
    const sidebar = document.getElementById("shopSidebar");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");
    const sidebarToggle = document.getElementById("toggleSidebar");
    const sidebarClose = document.getElementById("sidebarClose");
    const activeFiltersEl = document.getElementById("activeFilters");
    const quickChips = Array.from(document.querySelectorAll("[data-quick-filter]"));
    const mobileFilterBtn = document.getElementById("mobileFilterBtn");
    const mobileSortBtn = document.getElementById("mobileSortBtn");
    const mobileSearchBtn = document.getElementById("mobileSearchBtn");
    const mobileShopCartCount = document.getElementById("mobileShopCartCount");

    if (!grid) {
      return;
    }

    const getSelectedCategory = () => {
      const selected = document.querySelector('input[name="filterCat"]:checked');
      return selected ? String(selected.value || "") : "";
    };

    const getSelectedDietary = () => {
      const nodes = Array.from(document.querySelectorAll('input[name="dietary"]:checked'));
      return nodes.map((node) => node.value).filter(Boolean).join(",");
    };

    const getSelectedVeg = () => {
      const node = document.querySelector('input[name="filterVeg"]:checked');
      return node ? String(node.value) : "";
    };

    const getSelectedFeatureFlags = () => {
      const flags = {};
      Array.from(document.querySelectorAll('input[name="featureFlag"]:checked')).forEach((node) => {
        const key = String(node.value || "").trim();
        if (key) {
          flags[key] = true;
        }
      });
      return flags;
    };

    const setDrawerOpen = (isOpen) => {
      if (!sidebar || !sidebarBackdrop) {
        return;
      }
      sidebar.classList.toggle("is-open", isOpen);
      sidebarBackdrop.classList.toggle("is-open", isOpen);
      sidebarBackdrop.hidden = !isOpen;
      if (sidebarToggle) {
        sidebarToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      }
      if (!isOpen) {
        sidebarToggle?.focus();
      }
    };

    const syncMobileCartCount = () => {
      if (!mobileShopCartCount) return;
      const count = Number(window.localStorage.getItem("cakeouflage_cart_count") || 0);
      mobileShopCartCount.textContent = String(Math.max(0, count));
    };

    const renderLoadingSkeletons = () => {
      grid.innerHTML = Array.from({ length: 8 })
        .map(() => `
          <article class="product-card product-card--loading" aria-hidden="true">
            <div class="product-card__media skeleton"></div>
            <div class="product-card__body">
              <span class="skeleton" style="height:14px;width:72%;display:block;border-radius:8px;"></span>
              <span class="skeleton" style="height:11px;width:52%;display:block;border-radius:8px;margin-top:8px;"></span>
              <span class="skeleton" style="height:18px;width:36%;display:block;border-radius:8px;margin-top:10px;"></span>
            </div>
          </article>
        `)
        .join("");
    };

    const setQuickChip = (name) => {
      quickChips.forEach((chip) => {
        chip.classList.toggle("is-active", chip.getAttribute("data-quick-filter") === name);
      });
    };

    const renderActiveFilters = () => {
      if (!activeFiltersEl) {
        return;
      }
      const chips = [];
      const categoryNode = document.querySelector('input[name="filterCat"]:checked');
      const categoryText = (categoryNode?.nextElementSibling?.textContent || "").trim();
      if (categoryNode && categoryNode.value && categoryText) {
        chips.push({ key: "category", label: categoryText });
      }

      const vegNode = document.querySelector('input[name="filterVeg"]:checked');
      if (vegNode && vegNode.value !== "") {
        chips.push({ key: "veg", label: vegNode.value === "1" ? "Veg" : "Non-Veg" });
      }

      Array.from(document.querySelectorAll('input[name="dietary"]:checked')).forEach((node) => {
        const label = (node.parentElement?.textContent || "").trim();
        if (label) {
          chips.push({ key: `dietary:${node.value}`, label });
        }
      });

      if (priceBucketEl && priceBucketEl.value) {
        const bucketLabels = {
          under_500: "Under ₹500",
          "500_1000": "₹500-₹1000",
          "1000_2000": "₹1000-₹2000",
          above_2000: "Above ₹2000",
        };
        chips.push({ key: "priceBucket", label: bucketLabels[priceBucketEl.value] || "Price range" });
      }

      if (maxPriceInput && String(maxPriceInput.value || "").trim() !== "") {
        chips.push({ key: "maxPrice", label: `Under ₹${String(maxPriceInput.value).trim()}` });
      }

      const featureLabels = {
        is_bestseller: "Bestseller",
        is_chef_special: "Chef's Special",
        customizable: "Customizable",
        topper_enabled: "Topper Available",
        note_enabled: "Note on Cake",
        same_day: "Same Day Delivery",
        express: "Express Delivery",
      };
      Array.from(document.querySelectorAll('input[name="featureFlag"]:checked')).forEach((node) => {
        const key = String(node.value || "").trim();
        if (key) {
          chips.push({ key: `feature:${key}`, label: featureLabels[key] || key.replace(/_/g, " ") });
        }
      });

      if (quickChips.some((chip) => chip.classList.contains("is-active") && chip.getAttribute("data-quick-filter") !== "all")) {
        // Keep quick chips visually synced with active filters section.
      }

      if (searchEl && searchEl.value.trim() !== "") {
        chips.push({ key: "search", label: `Search: ${searchEl.value.trim()}` });
      }

      if (!chips.length) {
        activeFiltersEl.hidden = true;
        activeFiltersEl.innerHTML = "";
        return;
      }

      activeFiltersEl.hidden = false;
      activeFiltersEl.innerHTML = chips
        .map((chip) => `<button type="button" class="shop-active-filter" data-remove-filter="${chip.key}">${chip.label} <span aria-hidden="true">×</span></button>`)
        .join("");
    };

    const loadCategories = async () => {
      const payload = await utils.apiGet("/api/catalog/categories");
      const items = payload.data?.items || [];
      if (!categoryWrap) {
        return;
      }
      const selectedCategory = getSelectedCategory();
      categoryWrap.innerHTML = buildCategoryTreeMarkup(items, "shop", "", selectedCategory);
      bindAccordionBehavior(categoryWrap);
    };

    const loadProducts = async () => {
      renderLoadingSkeletons();

      const params = new URLSearchParams();
      if (searchEl && searchEl.value.trim()) params.set("q", searchEl.value.trim());

      const selectedCategory = getSelectedCategory();
      if (selectedCategory) params.set("category", selectedCategory);

      const selectedDietary = getSelectedDietary();
      if (selectedDietary) params.set("dietary", selectedDietary);

      const selectedVeg = getSelectedVeg();
      if (selectedVeg !== "") params.set("is_veg", selectedVeg);

      if (sortEl && sortEl.value) params.set("sort", sortEl.value);
      if (priceBucketEl && priceBucketEl.value) params.set("price_bucket", priceBucketEl.value);
      if (maxPriceInput && String(maxPriceInput.value || "").trim() !== "") {
        params.set("max_price", String(maxPriceInput.value).trim());
      }

      const selectedFlags = getSelectedFeatureFlags();
      Object.keys(selectedFlags).forEach((flag) => {
        params.set(flag, "1");
      });

      params.set("limit", "24");

      const payload = await utils.apiGet(`/api/catalog/products?${params.toString()}`);
      const items = payload.data?.items || [];

      if (countEl) {
        const total = Number(payload.data?.total || items.length || 0);
        countEl.textContent = `${total} products`;
      }

      if (!items.length) {
        grid.innerHTML = '<article class="card"><p class="text-muted">No products matched your current filters.</p></article>';
        renderActiveFilters();
        return;
      }

      grid.innerHTML = items
        .map((item) => {
          const imageUrl = utils.safeImage(item.image || item.featured_image || "", utils.productPlaceholder);
          const badges = [];
          if (String(item.dietary_tag || "").toLowerCase() === "eggless") badges.push("Eggless");
          if (Number(item.is_chef_special || 0) === 1) badges.push("Bestseller");
          if (Number(item.topper_enabled || 0) === 1) badges.push("Topper Available");
          if (Number(item.note_enabled || 0) === 1) badges.push("Message on Cake");
          if (String(item.customisation_note || "").trim() !== "") badges.push("Customizable");
          const badgeHtml = badges.slice(0, 3).map((badge) => `<span class="product-card__badge">${badge}</span>`).join("");
          return `
            <article class="product-card">
              <a class="product-card__image-wrap" href="${BASE_PATH}/product/${item.slug}">
                <img class="product-card__image" src="${imageUrl}" alt="${item.name}" loading="lazy" width="400" height="500" onerror="this.onerror=null;this.src='${utils.productPlaceholder}'" />
                <div class="product-card__badge-row">${badgeHtml}</div>
                <span class="product-card__veg-badge veg-dot veg-dot--${item.is_veg ? 'veg' : 'nonveg'}" title="${item.is_veg ? 'Vegetarian' : 'Non-Vegetarian'}"></span>
              </a>
              <div class="product-card__body">
                <h3 class="product-card__title">${item.name}</h3>
                <p class="product-card__meta">${item.category_name || "Cake"}${item.dietary_tag ? ` · ${item.dietary_tag}` : ""}</p>
                <div class="product-card__footer">
                  <p class="product-card__price">${utils.formatInr(item.starting_price)}</p>
                </div>
                <div class="product-card__actions">
                  <a class="btn btn--secondary btn--sm product-card__view-btn" href="${BASE_PATH}/product/${item.slug}">View Details</a>
                  <button class="btn btn--primary btn--sm product-card__quick-add" type="button" data-add-product="${item.id}" data-add-variant="${item.default_variant_id || ""}">Quick Add</button>
                </div>
              </div>
            </article>
          `;
        })
        .join("");

      renderActiveFilters();
    };

    applyBtn?.addEventListener("click", () => {
      setDrawerOpen(false);
      void loadProducts();
    });

    clearBtn?.addEventListener("click", () => {
      const allCat = document.querySelector('input[name="filterCat"][value=""]');
      if (allCat) {
        allCat.checked = true;
      }
      const allVeg = document.querySelector('input[name="filterVeg"][value=""]');
      if (allVeg) {
        allVeg.checked = true;
      }
      Array.from(document.querySelectorAll('input[name="dietary"]')).forEach((node) => {
        node.checked = false;
      });
      if (priceBucketEl) {
        priceBucketEl.value = "";
      }
      if (maxPriceInput) {
        maxPriceInput.value = "";
      }
      Array.from(document.querySelectorAll('input[name="featureFlag"]')).forEach((node) => {
        node.checked = false;
      });
      if (searchEl) {
        searchEl.value = "";
      }
      if (sortEl) {
        sortEl.value = "latest";
      }
      setQuickChip("all");
      void loadProducts();
    });

    priceBucketEl?.addEventListener("change", () => {
      void loadProducts();
    });

    maxPriceInput?.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        void loadProducts();
      }
    });

    Array.from(document.querySelectorAll('input[name="featureFlag"]')).forEach((node) => {
      node.addEventListener("change", () => {
        void loadProducts();
      });
    });

    sortEl?.addEventListener("change", () => {
      void loadProducts();
    });

    sidebarToggle?.addEventListener("click", () => {
      const isOpen = sidebar?.classList.contains("is-open") === true;
      setDrawerOpen(!isOpen);
    });
    sidebarClose?.addEventListener("click", () => setDrawerOpen(false));
    sidebarBackdrop?.addEventListener("click", () => setDrawerOpen(false));
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        setDrawerOpen(false);
      }
    });

    quickChips.forEach((chip) => {
      chip.addEventListener("click", () => {
        const mode = chip.getAttribute("data-quick-filter") || "all";
        setQuickChip(mode);

        if (mode === "all") {
          clearBtn?.click();
          return;
        }

        if (mode === "eggless") {
          const eggless = document.querySelector('input[name="dietary"][value="eggless"]');
          if (eggless) eggless.checked = true;
        }
        if (mode === "vegan") {
          const vegan = document.querySelector('input[name="dietary"][value="vegan"]');
          if (vegan) vegan.checked = true;
        }
        if (mode === "under1000") {
          if (priceBucketEl) {
            priceBucketEl.value = "500_1000";
          }
          if (maxPriceInput) {
            maxPriceInput.value = "1000";
          }
        }
        if (mode === "bestseller") {
          const bestseller = document.querySelector('input[name="featureFlag"][value="is_bestseller"]');
          if (bestseller) bestseller.checked = true;
        }
        if (mode === "sameDay") {
          const sameDay = document.querySelector('input[name="featureFlag"][value="same_day"]');
          if (sameDay) sameDay.checked = true;
        }
        if (mode === "chefSpecial") {
          const chefSpecial = document.querySelector('input[name="featureFlag"][value="is_chef_special"]');
          if (chefSpecial) chefSpecial.checked = true;
        }

        void loadProducts();
      });
    });

    mobileFilterBtn?.addEventListener("click", () => setDrawerOpen(true));
    mobileSearchBtn?.addEventListener("click", () => {
      searchEl?.focus();
      searchEl?.scrollIntoView({ behavior: "smooth", block: "center" });
    });
    mobileSortBtn?.addEventListener("click", () => {
      sortEl?.focus();
      sortEl?.scrollIntoView({ behavior: "smooth", block: "center" });
    });

    searchEl?.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        void loadProducts();
      }
    });

    grid.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const productId = Number(target.dataset.addProduct || 0);
      if (!productId) {
        return;
      }

      const variantId = Number(target.dataset.addVariant || 0);
      try {
        target.setAttribute("aria-busy", "true");
        target.textContent = "Adding...";
        await window.CakeouflageCart?.addItem(productId, variantId || null, 1);
        target.textContent = "Added";
        setTimeout(() => {
          target.textContent = "Quick Add";
          target.removeAttribute("aria-busy");
        }, 1200);
        syncMobileCartCount();
      } catch (error) {
        target.textContent = "Quick Add";
        target.removeAttribute("aria-busy");
        alert(error.message);
      }
    });

    activeFiltersEl?.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const chip = target.closest("[data-remove-filter]");
      if (!(chip instanceof HTMLElement)) return;
      const key = chip.getAttribute("data-remove-filter") || "";

      if (key === "category") {
        const allCat = document.querySelector('input[name="filterCat"][value=""]');
        if (allCat) allCat.checked = true;
      }
      if (key === "veg") {
        const allVeg = document.querySelector('input[name="filterVeg"][value=""]');
        if (allVeg) allVeg.checked = true;
      }
      if (key.startsWith("dietary:")) {
        const val = key.split(":")[1] || "";
        const input = document.querySelector(`input[name="dietary"][value="${val}"]`);
        if (input) input.checked = false;
      }
      if (key === "priceBucket" && priceBucketEl) {
        priceBucketEl.value = "";
      }
      if (key === "maxPrice" && maxPriceInput) {
        maxPriceInput.value = "";
      }
      if (key.startsWith("feature:")) {
        const flag = key.split(":")[1] || "";
        const input = document.querySelector(`input[name="featureFlag"][value="${flag}"]`);
        if (input) input.checked = false;
      }
      if (key === "search" && searchEl) {
        searchEl.value = "";
      }

      void loadProducts();
    });

    window.addEventListener("cart:updated", syncMobileCartCount);

    try {
      await loadCategories();
      syncMobileCartCount();
      await loadProducts();
    } catch (error) {
      grid.innerHTML = `<article class="card"><p class="text-muted">${error.message}</p></article>`;
    }
  };

  const initUnifiedBrowseUi = () => {
    const page = document.querySelector('[data-page="shop"], [data-page="category"]');
    if (!page) {
      return;
    }

    const pageType = page.getAttribute("data-page") || "";
    const isCategoryPage = pageType === "category";
    const sidebar = document.getElementById("shopSidebar");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");
    const sidebarToggle = document.getElementById("toggleSidebar");
    const sidebarClose = document.getElementById("sidebarClose");
    const mobileFilterBtn = document.getElementById("mobileFilterBtn");
    const mobileSortBtn = document.getElementById("mobileSortBtn");
    const mobileSearchBtn = document.getElementById("mobileSearchBtn");
    const searchEl = document.getElementById("shopSearch");
    const sortEl = document.getElementById("shopSort");
    const viewBtns = Array.from(document.querySelectorAll(".view-btn[data-view]"));
    const mobileShopCartCount = document.getElementById("mobileShopCartCount");
    const quickChips = Array.from(document.querySelectorAll("#shopQuickChips [data-quick-filter]"));

    const syncMobileCartCount = () => {
      if (!mobileShopCartCount) return;
      const count = Number(window.localStorage.getItem("cakeouflage_cart_count") || 0);
      mobileShopCartCount.textContent = String(Math.max(0, count));
    };

    const setViewMode = (mode) => {
      const nextMode = mode === "list" ? "list" : "grid";
      page.classList.toggle("is-list-view", nextMode === "list");
      viewBtns.forEach((btn) => {
        btn.classList.toggle("is-active", btn.getAttribute("data-view") === nextMode);
      });
      try {
        window.localStorage.setItem("cakeouflage_browse_view_mode", nextMode);
      } catch (error) {
        // Ignore localStorage access errors.
      }
    };

    if (viewBtns.length) {
      let savedMode = "grid";
      try {
        savedMode = window.localStorage.getItem("cakeouflage_browse_view_mode") || "grid";
      } catch (error) {
        savedMode = "grid";
      }
      setViewMode(savedMode);

      viewBtns.forEach((btn) => {
        btn.addEventListener("click", () => {
          setViewMode(btn.getAttribute("data-view") || "grid");
        });
      });
    }

    if (isCategoryPage) {
      const preserveScroll = () => {
        if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.saveState === "function") {
          window.CakeScrollPreserver.saveState();
        }
      };

      const submitWithPreserve = (form) => {
        if (!(form instanceof HTMLFormElement)) {
          return;
        }
        if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.submitForm === "function") {
          window.CakeScrollPreserver.submitForm(form);
          return;
        }
        preserveScroll();
        form.submit();
      };

      const setDrawerOpen = (isOpen) => {
        if (!sidebar || !sidebarBackdrop) return;
        sidebar.classList.toggle("is-open", isOpen);
        sidebarBackdrop.classList.toggle("is-open", isOpen);
        sidebarBackdrop.hidden = !isOpen;
        if (sidebarToggle) {
          sidebarToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        }
      };

      sidebarToggle?.addEventListener("click", () => {
        const isOpen = sidebar?.classList.contains("is-open") === true;
        setDrawerOpen(!isOpen);
      });
      sidebarClose?.addEventListener("click", () => setDrawerOpen(false));
      sidebarBackdrop?.addEventListener("click", () => setDrawerOpen(false));
      mobileFilterBtn?.addEventListener("click", () => setDrawerOpen(true));
      mobileSearchBtn?.addEventListener("click", () => {
        searchEl?.focus();
        searchEl?.scrollIntoView({ behavior: "smooth", block: "center" });
      });
      mobileSortBtn?.addEventListener("click", () => {
        sortEl?.focus();
        sortEl?.scrollIntoView({ behavior: "smooth", block: "center" });
      });

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          setDrawerOpen(false);
        }
      });

      const trimEmptyFields = (form) => {
        const fields = form.querySelectorAll("input, select, textarea");
        fields.forEach((field) => {
          if (field.disabled || !field.name) return;
          if ((field.type === "checkbox" || field.type === "radio") && !field.checked) return;
          if ((field.value || "").trim() === "") {
            field.disabled = true;
          }
        });
      };

      const forms = [
        document.getElementById("filterForm"),
        document.getElementById("sortForm"),
        document.querySelector(".shop-search-wrap[role='search']"),
      ].filter(Boolean);

      forms.forEach((form) => {
        form.addEventListener("submit", () => {
          trimEmptyFields(form);
          preserveScroll();
        });
      });

      const filterForm = document.getElementById("filterForm");
      const categoryWrap = document.getElementById("filterCategories");
      const currentCategorySlug = String(categoryWrap?.getAttribute("data-current-category") || "").trim();

      const setRadioValue = (name, value) => {
        const target = document.querySelector(`input[name="${name}"][value="${value}"]`);
        if (target instanceof HTMLInputElement) {
          target.checked = true;
        }
      };

      const clearFeatureFlags = () => {
        ["is_bestseller", "is_chef_special", "customizable", "topper_enabled", "note_enabled", "same_day", "express"].forEach((name) => {
          const input = document.querySelector(`input[name="${name}"]`);
          if (input instanceof HTMLInputElement) {
            input.checked = false;
          }
        });
      };

      const applyCategoryQuickFilter = (mode) => {
        if (!(filterForm instanceof HTMLFormElement)) {
          return;
        }
        if (mode === "all") {
          if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.navigate === "function") {
            window.CakeScrollPreserver.navigate(window.location.pathname);
          } else {
            preserveScroll();
            window.location.href = window.location.pathname;
          }
          return;
        }
        if (mode === "eggless") {
          const eggless = document.querySelector('input[name="dietary[]"][value="eggless"]');
          if (eggless instanceof HTMLInputElement) eggless.checked = true;
        }
        if (mode === "vegan") {
          const vegan = document.querySelector('input[name="dietary[]"][value="vegan"]');
          if (vegan instanceof HTMLInputElement) vegan.checked = true;
        }
        if (mode === "under1000") {
          const bucket = document.getElementById("priceBucket");
          if (bucket instanceof HTMLSelectElement) bucket.value = "500_1000";
          const maxPrice = document.getElementById("maxPriceInput");
          if (maxPrice instanceof HTMLInputElement) maxPrice.value = "1000";
        }
        if (mode === "bestseller") setRadioValue("is_bestseller", "1");
        if (mode === "sameDay") setRadioValue("same_day", "1");
        if (mode === "chefSpecial") setRadioValue("is_chef_special", "1");
        clearFeatureFlags();
        if (mode === "bestseller") setRadioValue("is_bestseller", "1");
        if (mode === "sameDay") setRadioValue("same_day", "1");
        if (mode === "chefSpecial") setRadioValue("is_chef_special", "1");
        submitWithPreserve(filterForm);
      };

      quickChips.forEach((chip) => {
        chip.addEventListener("click", () => {
          quickChips.forEach((item) => item.classList.remove("is-active"));
          chip.classList.add("is-active");
          const mode = chip.getAttribute("data-quick-filter") || "all";
          applyCategoryQuickFilter(mode);
        });
      });

      // Reflect active filter in quick chips on page load
      (function syncActiveChipOnLoad() {
        const params = new URLSearchParams(window.location.search);
        let activeMode = "all";
        const dietary = params.getAll("dietary[]");
        if (dietary.includes("eggless")) activeMode = "eggless";
        else if (dietary.includes("vegan")) activeMode = "vegan";
        else if (params.get("is_bestseller") === "1") activeMode = "bestseller";
        else if (params.get("same_day") === "1") activeMode = "sameDay";
        else if (params.get("is_chef_special") === "1") activeMode = "chefSpecial";
        else if (params.get("price_bucket") === "500_1000" || params.get("max_price") === "1000") activeMode = "under1000";
        if (activeMode !== "all") {
          quickChips.forEach((c) => {
            c.classList.toggle("is-active", c.getAttribute("data-quick-filter") === activeMode);
          });
        }
      }());

      const loadCategoryTree = async () => {
        if (!categoryWrap) return;
        const payload = await utils.apiGet("/api/catalog/categories");
        const items = payload.data?.items || [];
        categoryWrap.innerHTML = buildCategoryTreeMarkup(items, "category", currentCategorySlug);
        bindAccordionBehavior(categoryWrap);
      };

      categoryWrap?.addEventListener("click", (event) => {
        const link = event.target instanceof Element ? event.target.closest("a[href]") : null;
        if (!(link instanceof HTMLAnchorElement)) {
          return;
        }
        if (event.defaultPrevented || event.button !== 0) {
          return;
        }
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
          return;
        }
        preserveScroll();
      });

      void loadCategoryTree();

      // Toolbar sticky shadow via IntersectionObserver sentinel
      const toolbar = document.querySelector('[data-page="category"] .shop-toolbar');
      if (toolbar && 'IntersectionObserver' in window) {
        const sentinel = document.createElement('div');
        sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none';
        toolbar.parentElement?.insertBefore(sentinel, toolbar);
        new IntersectionObserver(
          ([entry]) => { toolbar.classList.toggle('is-stuck', !entry.isIntersecting); },
          { rootMargin: '-1px 0px 0px 0px', threshold: [1] }
        ).observe(sentinel);
      }

      const categoryGrid = document.getElementById("shopGrid");
      categoryGrid?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        const productId = Number(target.dataset.addProduct || 0);
        if (!productId) return;
        const variantId = Number(target.dataset.addVariant || 0);
        try {
          target.setAttribute("aria-busy", "true");
          target.textContent = "Adding...";
          await window.CakeouflageCart?.addItem(productId, variantId || null, 1);
          target.textContent = "Added";
          setTimeout(() => {
            target.textContent = "Quick Add";
            target.removeAttribute("aria-busy");
          }, 1200);
          syncMobileCartCount();
        } catch (error) {
          target.textContent = "Quick Add";
          target.removeAttribute("aria-busy");
          alert(error.message);
        }
      });
    }

    syncMobileCartCount();
    window.addEventListener("cart:updated", syncMobileCartCount);
  };

  const renderProduct = async () => {
    const page = document.querySelector('[data-page="product"]');
    if (!page) {
      return;
    }

    const slug = page.getAttribute("data-product-slug") || "";
    const titleEl = document.getElementById("productTitle");
    const shortEl = document.getElementById("productShortDescription");
    const priceEl = document.getElementById("productPrice");
    const variantEl = document.getElementById("variantSelect");
    const relatedEl = document.getElementById("relatedProductsGrid");
    const noteEl = document.getElementById("customisationNote");
    const addBtn = document.getElementById("addToCartButton");

    if (!slug) {
      titleEl.textContent = "Product unavailable";
      return;
    }

    try {
      const payload = await utils.apiGet(`/api/catalog/products/${slug}`);
      const product = payload.data?.product;
      const variants = payload.data?.variants || [];
      const related = payload.data?.related_products || [];

      titleEl.textContent = product.name;
      shortEl.textContent = product.short_description;
      noteEl.textContent = product.customisation_note || "";

      variantEl.innerHTML = variants
        .map((variant) => {
          const displayPrice = Number(variant.discount_price || variant.price || 0);
          return `<option value="${variant.id}">${variant.variant_label} - ${utils.formatInr(displayPrice)}</option>`;
        })
        .join("");

      const setPriceFromSelectedVariant = () => {
        const selected = variants.find((variant) => String(variant.id) === String(variantEl.value));
        const displayPrice = Number(selected?.discount_price || selected?.price || product.starting_price || 0);
        priceEl.textContent = utils.formatInr(displayPrice);
      };
      setPriceFromSelectedVariant();
      variantEl.addEventListener("change", setPriceFromSelectedVariant);

      relatedEl.innerHTML = related
        .map((item) => {
          const imageUrl = utils.safeImage(item.image || item.featured_image || "", utils.productPlaceholder);
          return `<article class="product-card"><a class="product-card__image-wrap" href="/Cakeouflage-E-commerce/product/${item.slug}"><img class="product-card__image" src="${imageUrl}" alt="${item.name}" loading="lazy" width="400" height="400" onerror="this.onerror=null;this.src='${utils.productPlaceholder}'" /></a><h3>${item.name}</h3><p>${utils.formatInr(item.starting_price)}</p><a class="btn btn--secondary" href="/Cakeouflage-E-commerce/product/${item.slug}">View</a></article>`;
        })
        .join("");

      addBtn?.addEventListener("click", async () => {
        try {
          await window.CakeouflageCart?.addItem(Number(product.id), Number(variantEl.value || 0), 1);
          addBtn.textContent = "Added To Cart";
        } catch (error) {
          alert(error.message);
        }
      });

      const wishlistBtn = document.getElementById("pdpWishlistBtn");
      wishlistBtn?.addEventListener("click", async () => {
        try {
          await utils.apiPost("/api/wishlist/items", { product_id: Number(product.id) });
          wishlistBtn.classList.add("is-active");
        } catch (error) {
          alert(error.message);
        }
      });
    } catch (error) {
      titleEl.textContent = "Product unavailable";
      shortEl.textContent = error.message;
    }
  };

  const initCheckout = async () => {
    const page = document.querySelector('[data-page="checkout"]');
    if (!page) {
      return;
    }

    const form = document.getElementById("checkoutForm");
    const modeEl = document.getElementById("fulfilmentMode");
    const postalWrap = document.getElementById("postalCodeWrap");
    const postalEl = document.getElementById("postalCode");
    const previewBtn = document.getElementById("previewCheckoutBtn");
    const statusEl = document.getElementById("checkoutStatus");
    const subtotalEl = document.getElementById("checkoutSubtotal");
    const discountEl = document.getElementById("checkoutDiscount");
    const deliveryEl = document.getElementById("checkoutDeliveryFee");
    const totalEl = document.getElementById("checkoutGrandTotal");

    const syncFulfilment = () => {
      postalWrap.style.display = modeEl.value === "pickup" ? "none" : "grid";
    };

    const doPreview = async () => {
      const payload = await utils.apiPost("/api/checkout/preview", {
        fulfilment_mode: modeEl.value,
        postal_code: postalEl.value.trim()
      });

      subtotalEl.textContent = utils.formatInr(payload.data?.cart?.subtotal || 0);
      discountEl.textContent = utils.formatInr(payload.data?.cart?.discount_total || 0);
      deliveryEl.textContent = utils.formatInr(payload.data?.delivery_fee || 0);
      totalEl.textContent = utils.formatInr(payload.data?.grand_total || 0);
      statusEl.textContent = "Checkout preview updated.";
      return payload;
    };

    modeEl.addEventListener("change", syncFulfilment);
    previewBtn?.addEventListener("click", async () => {
      try {
        await doPreview();
      } catch (error) {
        statusEl.textContent = error.message;
      }
    });

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData(form);
      const payloadBody = Object.fromEntries(formData.entries());

      try {
        const payload = await utils.apiPost("/api/orders/place", payloadBody);
        statusEl.textContent = `Order placed successfully. Reference: ${payload.data?.order_number}`;
        await window.CakeouflageCart?.syncCount();
      } catch (error) {
        statusEl.textContent = error.message;
      }
    });

    syncFulfilment();
  };

  const renderOrderCards = (orders = []) => {
    if (!orders.length) {
      return `
        <div class="empty-state">
          <span class="empty-state__icon">📦</span>
          <p>No orders yet. <a href="/category" class="link">Browse our cakes →</a></p>
        </div>
      `;
    }

    return orders
      .map((item) => `
        <article class="card" style="margin-bottom:var(--space-3)">
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
              <h3 style="margin:0 0 6px 0">${item.order_number}</h3>
              
              <p class="text-muted" style="margin:0">${item.order_status} | ${item.payment_status} | ${item.item_count} items</p>
            </div>
            <div style="text-align:right">
              <strong>${utils.formatInr(item.grand_total || 0)}</strong>
              <p class="text-muted" style="margin:6px 0 0 0">${item.created_at || "-"}</p>
            </div>
          </div>
        </article>
      `)
      .join("");
  };

  const renderWishlistCards = (items = [], compact = false) => {
    if (!items.length) {
      return compact
        ? '<div class="empty-state"><span class="empty-state__icon">❤️</span><p>No saved items yet. <a href="/category" class="link">Start browsing →</a></p></div>'
        : "";
    }

    return items
      .map((item) => {
        const imageUrl = utils.safeImage(item.image || item.featured_image || "", utils.productPlaceholder);
        return `
        <article class="product-card">
          <a class="product-card__image-wrap" href="/Cakeouflage-E-commerce/product/${item.slug}">
            <img class="product-card__image" src="${imageUrl}" alt="${item.name}" loading="lazy" width="400" height="400" onerror="this.onerror=null;this.src='${utils.productPlaceholder}'" />
          </a>
          <h3>${item.name}</h3>
          <p class="text-muted">${item.short_description || ""}</p>
          <p>${utils.formatInr(item.starting_price || 0)}</p>
          <div class="product-card__actions">
            <a class="btn btn--secondary" href="/Cakeouflage-E-commerce/product/${item.slug}">View</a>
            <button class="btn btn--ghost" type="button" data-remove-wishlist="${item.product_id}">Remove</button>
          </div>
        </article>
      `;
      })
      .join("");
  };

  const renderAddressCards = (items = []) => {
    if (!items.length) {
      return '<div class="empty-state"><span class="empty-state__icon">📍</span><p>No addresses saved yet.</p></div>';
    }

    return items
      .map((item) => `
        <article class="card" style="margin-bottom:var(--space-3)">
          <h3 style="margin:0 0 6px 0">${item.label || "Address"}${Number(item.is_default) === 1 ? " (Default)" : ""}</h3>
          <p style="margin:0 0 6px 0">${item.recipient_name} | ${item.phone}</p>
          <p class="text-muted" style="margin:0 0 12px 0">${item.line1}${item.line2 ? `, ${item.line2}` : ""}${item.landmark ? `, ${item.landmark}` : ""}, ${item.city}, ${item.state} ${item.postal_code}</p>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn--secondary btn--sm" type="button" data-edit-address="${item.id}">Edit</button>
            <button class="btn btn--ghost btn--sm" type="button" data-delete-address="${item.id}">Delete</button>
          </div>
        </article>
      `)
      .join("");
  };

  const collectAddressFromPrompts = (existing = null) => {
    const label = window.prompt("Address label (Home/Office)", existing?.label || "Home");
    if (label === null) return null;
    const recipientName = window.prompt("Recipient full name", existing?.recipient_name || "");
    if (recipientName === null || !recipientName.trim()) return null;
    const phone = window.prompt("Phone number", existing?.phone || "");
    if (phone === null || !phone.trim()) return null;
    const line1 = window.prompt("Address line 1", existing?.line1 || "");
    if (line1 === null || !line1.trim()) return null;
    const line2 = window.prompt("Address line 2 (optional)", existing?.line2 || "") || "";
    const landmark = window.prompt("Landmark (optional)", existing?.landmark || "") || "";
    const city = window.prompt("City", existing?.city || "Nashik");
    if (city === null || !city.trim()) return null;
    const state = window.prompt("State", existing?.state || "Maharashtra");
    if (state === null || !state.trim()) return null;
    const postalCode = window.prompt("Postal code", existing?.postal_code || "");
    if (postalCode === null || !postalCode.trim()) return null;
    const defaultAnswer = window.prompt("Set as default? (yes/no)", Number(existing?.is_default || 0) === 1 ? "yes" : "no") || "no";

    return {
      label: label.trim(),
      recipient_name: recipientName.trim(),
      phone: phone.trim(),
      line1: line1.trim(),
      line2: line2.trim(),
      landmark: landmark.trim(),
      city: city.trim(),
      state: state.trim(),
      postal_code: postalCode.trim(),
      is_default: /^(1|y|yes|true)$/i.test(defaultAnswer) ? 1 : 0
    };
  };

  const initAccount = async () => {
    const page = document.querySelector('[data-page="account"]');
    if (!page) {
      return;
    }

    const gate = document.getElementById("accountGate");
    const sidebarName = document.getElementById("accountName");
    const profileForm = document.getElementById("profileForm");
    const profileStatus = document.getElementById("profileStatus");
    const ordersContainer = document.getElementById("ordersListContainer");
    const wishlistContainer = document.getElementById("wishlistContainer");
    const addressListContainer = document.getElementById("addressListContainer");
    const addAddressBtn = document.getElementById("addAddressBtn");
    const logoutLink = document.querySelector(".account-nav-link--logout");

    const tabLinks = Array.from(document.querySelectorAll(".account-nav-link[data-tab]"));
    const tabs = Array.from(document.querySelectorAll(".account-tab"));

    let addressesCache = [];

    const activateTab = (tabKey) => {
      tabLinks.forEach((link) => {
        link.classList.toggle("active", link.dataset.tab === tabKey);
      });
      tabs.forEach((tab) => {
        tab.style.display = tab.id === `tab-${tabKey}` ? "block" : "none";
      });
    };

    const loadOrders = async () => {
      const payload = await utils.apiGet("/api/orders");
      const items = payload.data?.items || [];
      ordersContainer.innerHTML = renderOrderCards(items);
    };

    const loadWishlist = async () => {
      const payload = await utils.apiGet("/api/wishlist");
      const items = payload.data?.items || [];
      wishlistContainer.innerHTML = renderWishlistCards(items, true);
    };

    const loadAddresses = async () => {
      const payload = await utils.apiGet("/api/account/addresses");
      const items = payload.data?.items || [];
      addressesCache = items;
      addressListContainer.innerHTML = renderAddressCards(items);
    };

    try {
      const payload = await utils.apiGet("/api/account/profile");
      const user = payload.data?.user || {};
      const profile = payload.data?.profile || {};

      gate.style.display = "none";
      tabs.forEach((tab) => {
        tab.style.display = "none";
      });
      activateTab("profile");

      sidebarName.textContent = user.full_name || "Account";
      const profileFullName = document.getElementById("profileFullName");
      const profilePhone = document.getElementById("profilePhone");
      const profileEmail = document.getElementById("profileEmail");
      const profileDob = document.getElementById("profileDob");
      profileFullName.value = user.full_name || "";
      profilePhone.value = user.phone || "";
      profileEmail.value = user.email || "";
      profileDob.value = profile.date_of_birth || "";

      await loadOrders();
      await loadWishlist();
      await loadAddresses();
    } catch (error) {
      gate.style.display = "block";
      tabs.forEach((tab) => {
        tab.style.display = "none";
      });
      return;
    }

    tabLinks.forEach((link) => {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        const tab = link.dataset.tab || "profile";
        activateTab(tab);
      });
    });

    profileForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(profileForm).entries());
      try {
        await utils.apiPatch("/api/account/profile", payload);
        profileStatus.textContent = "Profile updated.";
      } catch (error) {
        profileStatus.textContent = error.message;
      }
    });

    wishlistContainer?.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const productId = Number(target.dataset.removeWishlist || 0);
      if (!productId) return;

      try {
        await utils.apiDelete(`/api/wishlist/items/${productId}`);
        await loadWishlist();
      } catch (error) {
        window.alert(error.message);
      }
    });

    addressListContainer?.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;

      const editId = Number(target.dataset.editAddress || 0);
      if (editId) {
        const existing = addressesCache.find((item) => Number(item.id) === editId) || null;
        const payload = collectAddressFromPrompts(existing);
        if (!payload) return;

        try {
          await utils.apiPatch(`/api/account/addresses/${editId}`, payload);
          await loadAddresses();
        } catch (error) {
          window.alert(error.message);
        }
        return;
      }

      const deleteId = Number(target.dataset.deleteAddress || 0);
      if (!deleteId) return;
      if (!window.confirm("Delete this address?")) return;

      try {
        await utils.apiDelete(`/api/account/addresses/${deleteId}`);
        await loadAddresses();
      } catch (error) {
        window.alert(error.message);
      }
    });

    addAddressBtn?.addEventListener("click", async () => {
      const payload = collectAddressFromPrompts();
      if (!payload) return;

      try {
        await utils.apiPost("/api/account/addresses", payload);
        await loadAddresses();
      } catch (error) {
        window.alert(error.message);
      }
    });

    logoutLink?.addEventListener("click", async (event) => {
      event.preventDefault();
      try {
        await utils.apiPost("/api/auth/logout", {});
        window.location.href = "/login";
      } catch (error) {
        window.alert(error.message);
      }
    });
  };

  const initOrdersPage = async () => {
    const page = document.querySelector('[data-page="orders"]');
    if (!page) {
      return;
    }

    const authGate = document.getElementById("ordersAuthGate");
    const emptyState = document.getElementById("ordersEmpty");
    const container = document.getElementById("ordersContainer");
    const drawer = document.getElementById("orderDetailDrawer");
    const drawerOverlay = document.getElementById("orderDrawerOverlay");
    const drawerClose = document.getElementById("orderDrawerClose");
    const drawerBody = document.getElementById("orderDetailBody");

    const closeDrawer = () => {
      drawer?.setAttribute("aria-hidden", "true");
      drawer?.classList.remove("is-open");
      drawerOverlay?.classList.remove("is-open");
    };

    const openDrawer = () => {
      drawer?.setAttribute("aria-hidden", "false");
      drawer?.classList.add("is-open");
      drawerOverlay?.classList.add("is-open");
    };

  // 🔥 STEP 1: GLOBAL STATUS FUNCTION (PASTE HERE)
const getStatus = (status) => {
  const map = {
    pending: "confirmed",
    confirmed: "confirmed",
    preparing: "preparing",
    delivered: "delivered"
  };

  return map[status] || "confirmed";
};


// 👇 तुझा existing code
window.openOrderDrawer = async (orderId) => {
  try {
    const payload = await utils.apiGet(`/api/orders/${orderId}`);
    const order = payload.data?.order || {};
    const items = payload.data?.items || [];

    // ✅ OUTSIDE HTML
    const statusMap = {
      pending: "confirmed",
      confirmed: "confirmed",
      preparing: "preparing",
      delivered: "delivered"
    };

    drawerBody.innerHTML = `
      <h3>${order.order_number}</h3>

      <p>${order.customer_name || "Customer"}</p>
      <p class="text-muted">📞 ${order.customer_phone || ""}</p>

      <!-- ✅ STATUS BADGE -->
      <span class="badge badge--${getStatus(order.order_status) || "confirmed"}">
        ${getStatus(order.order_status) || "confirmed"}
      </span>

      <div class="drawer-box">
        <div class="total">
          Total: ${utils.formatInr(order.grand_total)}
        </div>
      </div>

      <h4>💰 Price Details</h4>
      <p>Subtotal: ${utils.formatInr(order.subtotal || 0)}</p>
      <p>Delivery Fee: ${utils.formatInr(order.delivery_fee || 0)}</p>
      <p><strong>Grand Total: ${utils.formatInr(order.grand_total || 0)}</strong></p>

      <h4>🧁 Items</h4>
      <ul>
        ${items.map(i => `<li>${i.product_name_snapshot} x ${i.quantity}</li>`).join("")}
      </ul>
    `;

    openDrawer();
  } catch (error) {
    alert(error.message);
  }
};
    drawerClose?.addEventListener("click", closeDrawer);
    drawerOverlay?.addEventListener("click", closeDrawer);

    try {
      const payload = await utils.apiGet("/api/orders");
      const items = payload.data?.items || [];
      const params = new URLSearchParams(window.location.search);
const successOrderId = params.get("order");

if (params.get("success") === "1" && successOrderId) {
  setTimeout(() => {
    window.openOrderDrawer(successOrderId);
  }, 300);
}

      authGate.style.display = "none";
      if (!items.length) {
        emptyState.style.display = "block";
        container.innerHTML = "";
      } else {
        emptyState.style.display = "none";
    container.innerHTML = items
  .map((item) => `
    <article class="order-card">

      <div class="order-top">
        <div>
          <h3 class="order-id">${item.order_number}</h3>
          <div class="order-meta">
           <span class="badge badge--${getStatus(item.order_status)}">
  ${getStatus(item.order_status)}
</span>
            <span>📦 ${item.item_count} items</span>
          </div>
        </div>

        <div class="order-right">
          <strong class="order-price">${utils.formatInr(item.grand_total || 0)}</strong>
          <button class="btn btn--primary btn--sm" data-view-order="${item.id}">
            View
          </button>
        </div>

      </div>

      <div class="order-bottom">
        <span>📅 ${item.created_at || "-"}</span>
      </div>

    </article>
  `)
  .join("");
      }
    } catch (error) {
      authGate.style.display = "block";
      emptyState.style.display = "none";
      container.innerHTML = "";
      return;
    }

    container.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const orderId = Number(target.dataset.viewOrder || 0);
      if (!orderId) return;

      try {
        const payload = await utils.apiGet(`/api/orders/${orderId}`);
        const order = payload.data?.order || {};
        const items = payload.data?.items || [];
        const timeline = payload.data?.timeline || [];

  drawerBody.innerHTML = `
  <div class="order-drawer">

    <h3>${order.order_number}</h3>

    <div class="drawer-section">
      <p><strong>${order.customer_name}</strong></p>
      <p>📞 ${order.customer_phone}</p>
     <span class="badge badge--${getStatus(order.order_status)}">
  ${getStatus(order.order_status)}
</span>
    </div>

    <div class="drawer-box">
      <h4>💰 Price Details</h4>
      <p>Subtotal: ${utils.formatInr(order.subtotal)}</p>
      <p>Delivery: ${utils.formatInr(order.delivery_fee)}</p>
      <p class="total">Total: ${utils.formatInr(order.grand_total)}</p>
    </div>

    <div class="drawer-section">
      <h4>🧁 Items</h4>
      ${items.map(i => `
        <div class="item-row">
          <span>${i.product_name_snapshot}</span>
          <span>x${i.quantity}</span>
        </div>
      `).join("")}
    </div>

    <div class="drawer-section">
      <h4>📍 Order Timeline</h4>
      <ul class="timeline">
        <li>Order Created</li>
        <li>Preparing</li>
        <li>Out for Delivery</li>
        <li>Delivered</li>
      </ul>
    </div>

  </div>
`;
        openDrawer();
      } catch (error) {
        window.alert(error.message);
      }
    });
  };

  const initWishlistPage = async () => {
    const page = document.querySelector('[data-page="wishlist"]');
    if (!page) {
      return;
    }

    const authGate = document.getElementById("wishlistAuthGate");
    const emptyState = document.getElementById("wishlistEmpty");
    const grid = document.getElementById("wishlistGrid");

    const loadWishlist = async () => {
      const payload = await utils.apiGet("/api/wishlist");
      const items = payload.data?.items || [];

      authGate.style.display = "none";
      if (!items.length) {
        emptyState.style.display = "block";
        grid.innerHTML = "";
      } else {
        emptyState.style.display = "none";
        grid.innerHTML = items
          .map((item) => {
            const imageUrl = utils.safeImage(item.image || item.featured_image || "", utils.productPlaceholder);
            return `
            <article class="product-card">
              <a class="product-card__image-wrap" href="/Cakeouflage-E-commerce/product/${item.slug}">
                <img class="product-card__image" src="${imageUrl}" alt="${item.name}" loading="lazy" width="400" height="400" onerror="this.onerror=null;this.src='${utils.productPlaceholder}'" />
              </a>
              <h3>${item.name}</h3>
              <p class="text-muted">${item.short_description || ""}</p>
              <p>${utils.formatInr(item.starting_price || 0)}</p>
              <div class="product-card__actions">
                <a class="btn btn--secondary" href="/Cakeouflage-E-commerce/product/${item.slug}">View</a>
                <button class="btn btn--primary" type="button" data-cart-from-wishlist="${item.product_id}">Add to Cart</button>
                <button class="btn btn--ghost" type="button" data-remove-wishlist="${item.product_id}">Remove</button>
              </div>
            </article>
          `;
          })
          .join("");
      }
    };

    try {
      await loadWishlist();
    } catch (error) {
      authGate.style.display = "block";
      emptyState.style.display = "none";
      grid.innerHTML = "";
      return;
    }

    grid.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;

      const productId = Number(target.dataset.removeWishlist || 0);
      if (productId) {
        try {
          await utils.apiDelete(`/api/wishlist/items/${productId}`);
          await loadWishlist();
        } catch (error) {
          window.alert(error.message);
        }
        return;
      }

      const cartProductId = Number(target.dataset.cartFromWishlist || 0);
      if (!cartProductId) return;

      try {
        await window.CakeouflageCart?.addItem(cartProductId, null, 1);
        target.textContent = "Added";
      } catch (error) {
        window.alert(error.message);
      }
    });
  };

  const initAuthForms = () => {
    const loginPage = document.querySelector('[data-page="login"]');
    if (loginPage) {
      const loginForm = document.getElementById("loginForm");
      const loginStatus = document.getElementById("loginStatus");

      loginForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(loginForm).entries());
        try {
          await utils.apiPost("/api/auth/login", payload);
          loginStatus.textContent = "Login successful. Redirecting to account...";
          window.location.href = "/account";
        } catch (error) {
          loginStatus.textContent = error.message;
        }
      });
    }

    const registerPage = document.querySelector('[data-page="register"]');
    if (registerPage) {
      const registerForm = document.getElementById("registerForm");
      const registerStatus = document.getElementById("registerStatus");

      registerForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(registerForm).entries());
        try {
          await utils.apiPost("/api/auth/register", payload);
          registerStatus.textContent = "Registration successful. Redirecting to account...";
          window.location.href = "/account";
        } catch (error) {
          registerStatus.textContent = error.message;
        }
      });
    }

    const forgotPage = document.querySelector('[data-page="forgot-password"]');
    if (forgotPage) {
      const form = document.getElementById("forgotPasswordForm");
      const status = document.getElementById("forgotPasswordStatus");

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
          await utils.apiPost("/api/auth/forgot-password", payload);
          status.textContent = "If the account exists, a reset link has been queued.";
        } catch (error) {
          status.textContent = error.message;
        }
      });
    }

    const resetPage = document.querySelector('[data-page="reset-password"]');
    if (resetPage) {
      const form = document.getElementById("resetPasswordForm");
      const status = document.getElementById("resetPasswordStatus");

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
          await utils.apiPost("/api/auth/reset-password", payload);
          status.textContent = "Password reset successful. Redirecting to login...";
          window.setTimeout(() => {
            window.location.href = "/login";
          }, 900);
        } catch (error) {
          status.textContent = error.message;
        }
      });
    }
  };

  const initContactForm = () => {
    const page = document.querySelector('[data-page="contact"]');
    if (!page) {
      return;
    }

    const form = document.getElementById("contactForm");
    const status = document.getElementById("contactStatus");

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        await utils.apiPost("/api/inquiries/contact", payload);
        status.textContent = "Message received. Our team will contact you shortly.";
        form.reset();
      } catch (error) {
        status.textContent = error.message;
      }
    });
  };

  const initCoursePage = () => {
    const page = document.querySelector('[data-page="course"]');
    if (!page) {
      return;
    }

    const grid = document.getElementById("courseProgramsGrid");
    const batchesBody = document.getElementById("batchesTableBody");
    const workshopSelect = document.getElementById("courseWorkshopSelect");
    const form = document.getElementById("courseEnquiryForm");
    const status = document.getElementById("courseEnquiryStatus");

    const renderCourseCard = (course) => {
      return `
        <article class="course-program-card">
          <div class="course-program-card__icon">🎓</div>
          <h3 class="course-program-card__title">${course.title}</h3>
          <p class="course-program-card__desc">${course.short_description || "Workshop details coming soon."}</p>
          <div class="course-program-card__meta">
            <span>🧭 ${course.mode || "offline"}</span>
            <span>⏱ ${course.duration_text || "Duration shared on enquiry"}</span>
          </div>
          <div class="course-program-card__footer">
            <span class="course-program-card__price">Starting ${utils.formatInr(course.fee_amount || 0)}</span>
            <a href="/course/${course.slug}" class="btn btn--primary btn--sm">View Course</a>
          </div>
        </article>
      `;
    };

    const loadCourses = async () => {
      const payload = await utils.apiGet("/api/catalog/courses");
      const items = payload.data?.items || [];

      if (grid) {
        grid.innerHTML = items.length
          ? items.map((course) => renderCourseCard(course)).join("")
          : '<article class="card"><p class="text-muted">No active workshops available right now.</p></article>';
      }

      if (workshopSelect) {
        const options = items
          .map((course) => `<option value="${course.title}">${course.title}</option>`)
          .join("");
        workshopSelect.innerHTML = '<option value="">Select a workshop…</option>' + options;
      }

      if (batchesBody) {
        const rows = [];
        for (const course of items.slice(0, 6)) {
          try {
            const batchPayload = await utils.apiGet(`/api/catalog/courses/${course.slug}/batches`);
            const batches = batchPayload.data?.items || [];
            batches.slice(0, 2).forEach((batch) => {
              rows.push(`
                <tr>
                  <td>${course.title}</td>
                  <td>${batch.starts_on || "-"}</td>
                  <td><span class="badge ${Number(batch.seats_available || 0) > 3 ? "badge--success" : "badge--warning"}">${batch.seats_available || 0} seats</span></td>
                  <td>${utils.formatInr(batch.fee_amount || course.fee_amount || 0)}</td>
                  <td><a href="/course/${course.slug}#courseDetailInquiry" class="btn btn--primary btn--sm">Book</a></td>
                </tr>
              `);
            });
          } catch (error) {
            // Skip individual course batch failures and keep remaining courses visible.
          }
        }
        batchesBody.innerHTML = rows.join("") || '<tr><td colspan="5">Upcoming batches will be announced soon.</td></tr>';
      }
    };

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        await utils.apiPost("/api/inquiries/course", payload);
        status.textContent = "Course enquiry submitted. We will confirm your seat shortly.";
        form.reset();
      } catch (error) {
        status.textContent = error.message;
      }
    });

    void loadCourses().catch((error) => {
      if (status) {
        status.textContent = error.message;
      }
    });
  };

  const initCourseDetailPage = () => {
    const page = document.querySelector('[data-page="course-detail"]');
    if (!page) {
      return;
    }

    const slug = page.getAttribute("data-course-slug") || "";
    const title = document.getElementById("courseDetailTitle");
    const shortDesc = document.getElementById("courseDetailShort");
    const description = document.getElementById("courseDetailDescription");
    const mode = document.getElementById("courseDetailMode");
    const duration = document.getElementById("courseDetailDuration");
    const fee = document.getElementById("courseDetailFee");
    const modules = document.getElementById("courseDetailModules");
    const cta = document.getElementById("courseDetailCta");
    const workshopInput = document.getElementById("courseDetailWorkshop");
    const tableBody = document.querySelector("#courseDetailBatchesTable tbody");
    const form = document.getElementById("courseDetailEnquiryForm");
    const status = document.getElementById("courseDetailEnquiryStatus");

    const loadDetail = async () => {
      const payload = await utils.apiGet(`/api/catalog/courses/${slug}`);
      const course = payload.data?.course || {};
      const batches = payload.data?.batches || [];

      title.textContent = course.title || "Course unavailable";
      shortDesc.textContent = course.short_description || "";
      description.textContent = course.description || "";
      mode.textContent = course.mode || "-";
      duration.textContent = course.duration_text || "-";
      fee.textContent = utils.formatInr(course.fee_amount || 0);
      modules.textContent = course.modules || "Module plan shared after enquiry.";
      workshopInput.value = course.title || "";

      if (cta) {
        cta.textContent = course.cta_label || "Enquire Now";
        cta.setAttribute("href", course.cta_url || "#courseDetailInquiry");
      }

      tableBody.innerHTML = batches.length
        ? batches
            .map(
              (batch) => `
                <tr>
                  <td>${batch.batch_name}</td>
                  <td>${batch.starts_on || "-"}</td>
                  <td>${batch.ends_on || "-"}</td>
                  <td>${batch.seats_available || 0}/${batch.seats_total || 0}</td>
                  <td>${utils.formatInr(batch.fee_amount || course.fee_amount || 0)}</td>
                </tr>
              `
            )
            .join("")
        : '<tr><td colspan="5">No upcoming batches available yet.</td></tr>';
    };

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        await utils.apiPost("/api/inquiries/course", payload);
        status.textContent = "Enquiry submitted. We will contact you with the next batch details.";
        form.reset();
        workshopInput.value = title.textContent || "";
      } catch (error) {
        status.textContent = error.message;
      }
    });

    void loadDetail().catch((error) => {
      title.textContent = "Course unavailable";
      shortDesc.textContent = error.message;
    });
  };

  const initEventsPage = () => {
    const page = document.querySelector('[data-page="events"]');
    if (!page) {
      return;
    }

    const grid = document.getElementById("eventsGrid");
    const filterButtons = Array.from(document.querySelectorAll("[data-events-filter]"));
    let activeFilter = "all";

    const renderEventCard = (item) => {
      const startsAt = item.starts_at ? new Date(item.starts_at).toLocaleString() : "Date to be announced";
      const venue = item.online_link ? "Online" : (item.location_text || "Nashik");
      return `
        <article class="course-program-card">
          <div class="course-program-card__icon">${item.event_type === "webinar" ? "💻" : "🎤"}</div>
          <h3 class="course-program-card__title">${item.title}</h3>
          <p class="course-program-card__desc">${item.short_description || "Details available on event page."}</p>
          <div class="course-program-card__meta">
            <span>📅 ${startsAt}</span>
            <span>📍 ${venue}</span>
          </div>
          <div class="course-program-card__footer">
            <span class="course-program-card__price">${item.seats_available || 0} seats left</span>
            <a href="/events/${item.slug}" class="btn btn--primary btn--sm">${item.registration_cta_label || "View Details"}</a>
          </div>
        </article>
      `;
    };

    const loadEvents = async () => {
      const query = activeFilter === "all" ? "" : `?type=${encodeURIComponent(activeFilter)}`;
      const payload = await utils.apiGet(`/api/catalog/events${query}`);
      const items = payload.data?.items || [];
      grid.innerHTML = items.length
        ? items.map((item) => renderEventCard(item)).join("")
        : '<article class="card"><p class="text-muted">No published events available.</p></article>';
    };

    filterButtons.forEach((button) => {
      button.addEventListener("click", async () => {
        activeFilter = button.getAttribute("data-events-filter") || "all";
        filterButtons.forEach((node) => node.classList.remove("btn--primary"));
        filterButtons.forEach((node) => node.classList.add("btn--secondary"));
        button.classList.add("btn--primary");
        button.classList.remove("btn--secondary");
        await loadEvents();
      });
    });

    void loadEvents();
  };

  const initEventDetailPage = () => {
    const page = document.querySelector('[data-page="event-detail"]');
    if (!page) {
      return;
    }

    const slug = page.getAttribute("data-event-slug") || "";
    const title = document.getElementById("eventDetailTitle");
    const shortDesc = document.getElementById("eventDetailShort");
    const description = document.getElementById("eventDetailDescription");
    const type = document.getElementById("eventDetailType");
    const instructor = document.getElementById("eventDetailInstructor");
    const startsAt = document.getElementById("eventDetailStartsAt");
    const status = document.getElementById("eventDetailStatus");
    const seats = document.getElementById("eventDetailSeats");
    const category = document.getElementById("eventDetailCategory");
    const location = document.getElementById("eventDetailLocation");
    const onlineLink = document.getElementById("eventDetailOnlineLink");
    const slugField = document.getElementById("eventDetailSlugField");
    const form = document.getElementById("eventDetailRegisterForm");
    const formStatus = document.getElementById("eventDetailRegisterStatus");
    const submitBtn = document.getElementById("eventDetailSubmitBtn");

    const loadDetail = async () => {
      const payload = await utils.apiGet(`/api/catalog/events/${slug}`);
      const eventItem = payload.data?.event || {};

      title.textContent = eventItem.title || "Event unavailable";
      shortDesc.textContent = eventItem.short_description || "";
      description.textContent = eventItem.full_description || "";
      type.textContent = eventItem.event_type || "event";
      instructor.textContent = eventItem.instructor_name || "-";
      startsAt.textContent = eventItem.starts_at ? new Date(eventItem.starts_at).toLocaleString() : "-";
      status.textContent = eventItem.event_status || "-";
      seats.textContent = `${eventItem.seats_available || 0} / ${eventItem.capacity || 0}`;
      category.textContent = eventItem.event_category || "-";
      location.textContent = eventItem.location_text || "Online";

      if (eventItem.online_link) {
        onlineLink.textContent = "Join Link";
        onlineLink.href = eventItem.online_link;
      } else {
        onlineLink.textContent = "-";
        onlineLink.href = "#";
      }

      slugField.value = eventItem.slug || slug;
      submitBtn.textContent = eventItem.registration_cta_label || "Submit Registration";
    };

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        await utils.apiPost("/api/inquiries/event", payload);
        formStatus.textContent = "Registration submitted. Team Cakeouflage will contact you shortly.";
        form.reset();
        slugField.value = slug;
        await loadDetail();
      } catch (error) {
        formStatus.textContent = error.message;
      }
    });

    void loadDetail().catch((error) => {
      title.textContent = "Event unavailable";
      shortDesc.textContent = error.message;
    });
  };

  const initCustomCakeInquiry = () => {
    const page = document.querySelector('[data-page="custom-cake-inquiry"]');
    if (!page) {
      return;
    }

    const form = document.getElementById("customCakeInquiryForm");
    const status = document.getElementById("customCakeInquiryStatus");

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        await utils.apiPost("/api/inquiries/custom-cake", payload);
        status.textContent = "Inquiry submitted. Our team will contact you shortly.";
        form.reset();
      } catch (error) {
        status.textContent = error.message;
      }
    });
  };

  const initB2bAuthForms = () => {
    const loginPage = document.querySelector('[data-page="b2b-login"]');
    if (loginPage) {
      const form = document.getElementById("b2bLoginForm");
      const status = document.getElementById("b2bLoginStatus");

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
          await utils.apiPost("/api/b2b/auth/login", payload);
          status.textContent = "Login successful. Redirecting...";
          window.location.href = "/b2b/dashboard";
        } catch (error) {
          status.textContent = error.message;
        }
      });
    }

    const registerPage = document.querySelector('[data-page="b2b-register"]');
    if (registerPage) {
      const form = document.getElementById("b2bRegisterForm");
      const status = document.getElementById("b2bRegisterStatus");

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
          await utils.apiPost("/api/b2b/auth/register", payload);
          status.textContent = "Application submitted. You can sign in after admin approval.";
          form.reset();
        } catch (error) {
          status.textContent = error.message;
        }
      });
    }
  };

  const initB2bDashboard = async () => {
    const page = document.querySelector('[data-page="b2b-dashboard"]');
    if (!page) {
      return;
    }

    const welcome = document.getElementById("b2bWelcomeText");
    const companyName = document.getElementById("b2bCompanyName");
    const accountType = document.getElementById("b2bAccountType");
    const approvalStatus = document.getElementById("b2bApprovalStatus");
    const companyEmail = document.getElementById("b2bCompanyEmail");
    const pendingQuotes = document.getElementById("b2bPendingQuotes");
    const acceptedQuotes = document.getElementById("b2bAcceptedQuotes");
    const ordersTotal = document.getElementById("b2bOrdersTotal");
    const creditLimit = document.getElementById("b2bCreditLimit");
    const tableBody = document.querySelector("#b2bRecentQuotesTable tbody");
    const quoteForm = document.getElementById("b2bQuoteForm");
    const quoteStatus = document.getElementById("b2bQuoteStatus");
    const logoutBtn = document.getElementById("b2bLogoutBtn");

    const loadDashboard = async () => {
      const mePayload = await utils.apiGet("/api/b2b/auth/me");
      const summaryPayload = await utils.apiGet("/api/b2b/dashboard/summary");
      const quotesPayload = await utils.apiGet("/api/b2b/dashboard/quotes");

      const user = mePayload.data?.user || {};
      const account = mePayload.data?.account || {};
      const summary = summaryPayload.data || {};
      const items = quotesPayload.data?.items || [];

      welcome.textContent = `Welcome ${user.full_name || ""}. Manage quotes and bulk orders for ${account.company_name || "your business"}.`;
      companyName.textContent = account.company_name || "-";
      accountType.textContent = account.account_type || "-";
      approvalStatus.textContent = account.approval_status || "-";
      companyEmail.textContent = account.company_email || user.email || "-";

      pendingQuotes.textContent = summary.pending_quotes || 0;
      acceptedQuotes.textContent = summary.accepted_quotes || 0;
      ordersTotal.textContent = summary.orders_total || 0;
      creditLimit.textContent = utils.formatInr(summary.current_credit_limit || 0);

      tableBody.innerHTML =
        items.map((item) => `
          <tr>
            <td>${item.quote_number}</td>
            <td>${item.event_type || "-"}</td>
            <td>${item.fulfilment_mode}</td>
            <td>${item.status}</td>
            <td>${utils.formatInr(item.grand_total || 0)}</td>
            <td>${item.created_at || "-"}</td>
          </tr>
        `).join("") || '<tr><td colspan="6">No quote requests yet.</td></tr>';
    };

    quoteForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = Object.fromEntries(new FormData(quoteForm).entries());

      let items = [];
      try {
        items = JSON.parse(String(formData.items_json || "[]"));
      } catch (error) {
        quoteStatus.textContent = "Item list must be valid JSON.";
        return;
      }

      try {
        const payload = {
          event_type: formData.event_type || "",
          fulfilment_mode: formData.fulfilment_mode || "delivery",
          scheduled_date: formData.scheduled_date || "",
          note: formData.note || "",
          items
        };
        const response = await utils.apiPost("/api/b2b/dashboard/quote-request", payload);
        quoteStatus.textContent = `Quote request submitted: ${response.data?.quote_number}`;
        quoteForm.reset();
        await loadDashboard();
      } catch (error) {
        quoteStatus.textContent = error.message;
      }
    });

    logoutBtn?.addEventListener("click", async () => {
      try {
        await utils.apiPost("/api/b2b/auth/logout", {});
        window.location.href = "/b2b/login";
      } catch (error) {
        quoteStatus.textContent = error.message;
      }
    });

    try {
      await loadDashboard();
    } catch (error) {
      welcome.textContent = error.message;
    }
  };

  initUnifiedBrowseUi();
  void renderShop();
   void renderProduct();
  //void initCheckout();
  void initAccount();
  void initOrdersPage();
  void initWishlistPage();
  initAuthForms();
  initContactForm();
  initCoursePage();
  initCourseDetailPage();
  initEventsPage();
  initEventDetailPage();
  initCustomCakeInquiry();
  initB2bAuthForms();
  void initB2bDashboard();
});
