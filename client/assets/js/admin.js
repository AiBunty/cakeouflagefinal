document.addEventListener("DOMContentLoaded", () => {
  const utils = window.CakeouflageUtils;
  if (!utils) {
    return;
  }

  const toSlug = (value) => {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
  };

  const formatBytes = (value) => {
    const size = Number(value || 0);
    if (size < 1024) {
      return `${size} B`;
    }
    if (size < 1024 * 1024) {
      return `${(size / 1024).toFixed(1)} KB`;
    }
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  };

  const withAdminGuard = async (action) => {
    try {
      await utils.apiGet("/api/admin/auth/me");
      await action();
    } catch (error) {
      if (!window.location.pathname.startsWith("/admin/login")) {
        window.location.href = "/admin/login";
      }
    }
  };

  const initAdminLogout = () => {
    const button = document.getElementById("adminLogoutBtn");
    if (!button) {
      return;
    }

    button.addEventListener("click", async () => {
      try {
        await utils.apiPost("/api/admin/auth/logout", {});
      } finally {
        window.location.href = "/admin/login";
      }
    });
  };

  const initAdminLogin = () => {
    const page = document.querySelector('[data-page="admin-login"]');
    if (!page) {
      return;
    }

    const form = document.getElementById("adminLoginForm");
    const status = document.getElementById("adminLoginStatus");

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      status.textContent = "Signing in...";
      try {
        await utils.apiPost("/api/admin/auth/login", payload);
        status.textContent = "Login successful. Redirecting...";
        window.location.href = "/admin/dashboard";
      } catch (error) {
        status.textContent = error.message;
      }
    });
  };

  const initAdminDashboard = () => {
    const page = document.querySelector('[data-page="admin-dashboard"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const payload = await utils.apiGet("/api/admin/dashboard/summary");
      const data = payload.data || {};

      const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) {
          element.textContent = String(value ?? "-");
        }
      };

      const renderRevenueBars = (containerId, points) => {
        const container = document.getElementById(containerId);
        if (!container) {
          return;
        }

        const items = Array.isArray(points) ? points : [];
        if (!items.length) {
          container.innerHTML = '<p class="text-muted text-sm">No data available.</p>';
          return;
        }

        const maxValue = Math.max(...items.map((item) => Number(item.value || 0)), 0);
        const rows = items
          .map((item) => {
            const value = Number(item.value || 0);
            const width = maxValue > 0 ? Math.max(2, Math.round((value / maxValue) * 100)) : 2;
            return `
              <div class="admin-mini-chart__row">
                <span class="admin-mini-chart__label">${item.label || "-"}</span>
                <div class="admin-mini-chart__track"><div class="admin-mini-chart__fill" style="width:${width}%"></div></div>
                <span class="admin-mini-chart__value">${utils.formatInr(value)}</span>
              </div>
            `;
          })
          .join("");

        container.innerHTML = rows;
      };

      setText("kpiOrders", data.orders_total || 0);
      setText("kpiRevenue", utils.formatInr(data.revenue_total || 0));
      setText("kpiRevenueToday", utils.formatInr(data.revenue_today || 0));
      setText("kpiRevenueMonth", utils.formatInr(data.revenue_month || 0));
      setText("kpiRevenueYear", utils.formatInr(data.revenue_year || 0));
      setText("kpiCustomers", data.customers_total || 0);
      setText("kpiProducts", data.products_total || 0);
      setText("kpiB2b", data.b2b_accounts_total || 0);
      setText("kpiQuotes", data.pending_quotes || 0);
      setText("kpiRevenueSub", `today ${utils.formatInr(data.revenue_today || 0)}`);

      renderRevenueBars("monthlyRevenueBars", data.monthly_series || []);
      renderRevenueBars("yearlyRevenueBars", data.yearly_series || []);
    });
  };

  const initAdminProducts = () => {
    const page = document.querySelector('[data-page="admin-products"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("adminProductForm");
      const status = document.getElementById("adminProductStatus");
      const categorySelect = document.getElementById("adminProductCategory");
      const tableBody = document.querySelector("#adminProductTable tbody");
      const search = document.getElementById("adminProductSearch");
      const galleryPanel = document.getElementById("productGalleryPanel");
      const galleryList = document.getElementById("productGalleryList");
      let editingId = 0;
      let draggedGalleryId = 0;

      const loadCategories = async () => {
        const payload = await utils.apiGet("/api/admin/categories");
        const options = (payload.data?.items || [])
          .filter((item) => Number(item.is_active) === 1)
          .map((item) => `<option value="${item.id}">${item.name}</option>`)
          .join("");

        categorySelect.innerHTML = options;
      };

      const setFormFromProduct = (product) => {
        editingId = Number(product.id || 0);
        form.elements.name.value = product.name || "";
        form.elements.slug.value = product.slug || "";
        form.elements.sku.value = product.sku || "";
        form.elements.collection_category_id.value = product.collection_category_id || "";
        form.elements.short_description.value = product.short_description || "";
        form.elements.long_description.value = product.long_description || "";
        form.elements.starting_price.value = product.starting_price || "";
        form.elements.base_price.value = product.base_price || "";
        form.elements.stock_quantity.value = product.stock_quantity || 0;
        form.elements.availability_status.value = product.availability_status || "in_stock";
      };

      const clearForm = () => {
        form.reset();
        editingId = 0;
        status.textContent = "";
        if (galleryPanel) {
          galleryPanel.hidden = true;
        }
        if (galleryList) {
          galleryList.innerHTML = "";
        }
      };

      const loadProductGallery = async (productId) => {
        if (!galleryPanel || !galleryList) {
          return;
        }

        galleryPanel.hidden = false;
        const payload = await utils.apiGet(`/api/admin/products/${productId}/media`);
        const items = payload.data?.items || [];

        if (!items.length) {
          galleryList.innerHTML = '<p class="text-muted">No gallery images attached yet.</p>';
          return;
        }

        galleryList.innerHTML = items
          .map(
            (item) => `
              <article class="admin-gallery-item" data-image-id="${item.id}" draggable="true">
                <img src="${item.image_url}" alt="${item.alt_text || "Gallery image"}" loading="lazy" />
                <div class="admin-gallery-item__body">
                  <p class="text-muted"><span class="drag-handle" aria-hidden="true">::</span> #${item.sort_order} ${item.image_url}</p>
                  <div class="product-card__actions">
                    <button class="btn btn--secondary" type="button" data-action="remove" data-id="${item.id}">Remove</button>
                  </div>
                </div>
              </article>
            `
          )
          .join("");
      };

      const persistGalleryOrder = async () => {
        if (!galleryList || editingId <= 0) {
          return;
        }

        const orderedIds = Array.from(galleryList.querySelectorAll("[data-image-id]"))
          .map((node) => Number(node.getAttribute("data-image-id") || 0))
          .filter((value) => Number.isInteger(value) && value > 0);

        if (!orderedIds.length) {
          return;
        }

        await utils.apiPatch(`/api/admin/products/${editingId}/media/reorder`, {
          ordered_ids: orderedIds,
        });
      };

      const loadProducts = async () => {
        const q = search?.value?.trim() || "";
        const payload = await utils.apiGet(`/api/admin/products?limit=80&q=${encodeURIComponent(q)}`);
        const items = payload.data?.items || [];

        if (!items.length) {
          tableBody.innerHTML = '<tr><td colspan="7">No products found.</td></tr>';
          return;
        }

        tableBody.innerHTML = items
          .map(
            (item) => `
              <tr>
                <td>${item.name}</td>
                <td>${item.sku}</td>
                <td>${item.category_name || "-"}</td>
                <td>${utils.formatInr(item.starting_price)}</td>
                <td>${item.stock_quantity}</td>
                <td>${item.availability_status}</td>
                <td>
                  <button class="btn btn--secondary" type="button" data-action="edit" data-id="${item.id}">Edit</button>
                  <button class="btn btn--secondary" type="button" data-action="gallery" data-id="${item.id}">Gallery</button>
                  <button class="btn btn--secondary" type="button" data-action="delete" data-id="${item.id}">Archive</button>
                </td>
              </tr>
            `
          )
          .join("");
      };

      const loadProductById = async (id) => {
        const payload = await utils.apiGet("/api/admin/products?limit=80");
        const product = (payload.data?.items || []).find((item) => Number(item.id) === Number(id));
        if (product) {
          setFormFromProduct(product);
        }
      };

      tableBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const action = target.dataset.action;
        const id = Number(target.dataset.id || 0);
        if (!id || !action) {
          return;
        }

        if (action === "delete") {
          if (!window.confirm("Archive this product?")) {
            return;
          }

          try {
            await utils.apiDelete(`/api/admin/products/${id}`);
            await loadProducts();
            status.textContent = "Product archived.";
          } catch (error) {
            status.textContent = error.message;
          }
          return;
        }

        if (action === "edit") {
          await loadProductById(id);
          await loadProductGallery(id);
          status.textContent = "Editing selected product.";
        }

        if (action === "gallery") {
          editingId = id;
          await loadProductGallery(id);
          status.textContent = "Managing gallery for selected product.";
        }
      });

      galleryList?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const action = target.dataset.action;
        const imageId = Number(target.dataset.id || 0);
        if (!action || imageId <= 0 || editingId <= 0) {
          return;
        }

        try {
          if (action === "remove") {
            if (!window.confirm("Remove this gallery image from product?")) {
              return;
            }
            await utils.apiDelete(`/api/admin/products/${editingId}/media/${imageId}`);
            status.textContent = "Gallery image removed.";
          }

          await loadProductGallery(editingId);
        } catch (error) {
          status.textContent = error.message;
        }
      });

      galleryList?.addEventListener("dragstart", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const card = target.closest("[data-image-id]");
        if (!(card instanceof HTMLElement)) {
          return;
        }

        draggedGalleryId = Number(card.dataset.imageId || 0);
        card.classList.add("is-dragging");
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = "move";
          event.dataTransfer.setData("text/plain", String(draggedGalleryId));
        }
      });

      galleryList?.addEventListener("dragend", (event) => {
        const target = event.target;
        if (target instanceof HTMLElement) {
          const card = target.closest("[data-image-id]");
          if (card instanceof HTMLElement) {
            card.classList.remove("is-dragging");
          }
        }
        draggedGalleryId = 0;
        galleryList
          ?.querySelectorAll(".admin-gallery-item.is-drop-target")
          .forEach((node) => node.classList.remove("is-drop-target"));
      });

      galleryList?.addEventListener("dragover", (event) => {
        event.preventDefault();
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const card = target.closest("[data-image-id]");
        if (!(card instanceof HTMLElement)) {
          return;
        }

        galleryList
          .querySelectorAll(".admin-gallery-item.is-drop-target")
          .forEach((node) => node.classList.remove("is-drop-target"));
        card.classList.add("is-drop-target");
      });

      galleryList?.addEventListener("dragleave", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        const card = target.closest("[data-image-id]");
        if (card instanceof HTMLElement) {
          card.classList.remove("is-drop-target");
        }
      });

      galleryList?.addEventListener("drop", async (event) => {
        event.preventDefault();
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const toCard = target.closest("[data-image-id]");
        if (!(toCard instanceof HTMLElement)) {
          return;
        }

        const toId = Number(toCard.dataset.imageId || 0);
        if (!draggedGalleryId || !toId || draggedGalleryId === toId) {
          toCard.classList.remove("is-drop-target");
          return;
        }

        const fromCard = galleryList.querySelector(`[data-image-id="${draggedGalleryId}"]`);
        if (!(fromCard instanceof HTMLElement)) {
          toCard.classList.remove("is-drop-target");
          return;
        }

        const cards = Array.from(galleryList.querySelectorAll("[data-image-id]"));
        const fromIndex = cards.indexOf(fromCard);
        const toIndex = cards.indexOf(toCard);
        if (fromIndex < 0 || toIndex < 0) {
          toCard.classList.remove("is-drop-target");
          return;
        }

        if (fromIndex < toIndex) {
          toCard.after(fromCard);
        } else {
          toCard.before(fromCard);
        }

        toCard.classList.remove("is-drop-target");

        try {
          await persistGalleryOrder();
          status.textContent = "Gallery order updated.";
          await loadProductGallery(editingId);
        } catch (error) {
          status.textContent = error.message;
          await loadProductGallery(editingId);
        }
      });

      search?.addEventListener("input", () => {
        void loadProducts();
      });

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.slug = payload.slug || toSlug(payload.name);

        try {
          if (editingId > 0) {
            await utils.apiPatch(`/api/admin/products/${editingId}`, payload);
            status.textContent = "Product updated successfully.";
          } else {
            await utils.apiPost("/api/admin/products", payload);
            status.textContent = "Product created successfully.";
          }

          clearForm();
          await loadProducts();
        } catch (error) {
          status.textContent = error.message;
        }
      });

      form?.elements?.name?.addEventListener("blur", () => {
        if (!form.elements.slug.value.trim()) {
          form.elements.slug.value = toSlug(form.elements.name.value);
        }
      });

      await loadCategories();
      await loadProducts();
    });
  };

  const initAdminCategories = () => {
    const page = document.querySelector('[data-page="admin-categories"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("adminCategoryForm");
      const status = document.getElementById("adminCategoryStatus");
      const tableBody = document.querySelector("#adminCategoryTable tbody");
      const parentSelect = document.getElementById("adminParentCategory");
      let editingId = 0;

      const hydrateParentOptions = (items) => {
        const options = items
          .filter((item) => Number(item.is_active) === 1)
          .map((item) => `<option value="${item.id}">${item.name}</option>`)
          .join("");
        parentSelect.innerHTML = '<option value="">None</option>' + options;
      };

      const loadCategories = async () => {
        const payload = await utils.apiGet("/api/admin/categories");
        const items = payload.data?.items || [];

        hydrateParentOptions(items);

        if (!items.length) {
          tableBody.innerHTML = '<tr><td colspan="6">No categories found.</td></tr>';
          return;
        }

        tableBody.innerHTML = items
          .map(
            (item) => `
              <tr>
                <td>${item.name}</td>
                <td>${item.slug}</td>
                <td>${item.category_type}</td>
                <td>${item.parent_name || "-"}</td>
                <td>${Number(item.is_active) === 1 ? "Yes" : "No"}</td>
                <td>
                  <button class="btn btn--secondary" type="button" data-action="edit" data-id="${item.id}">Edit</button>
                  <button class="btn btn--secondary" type="button" data-action="delete" data-id="${item.id}">Archive</button>
                </td>
              </tr>
            `
          )
          .join("");

        tableBody.dataset.cache = JSON.stringify(items);
      };

      const setFormFromCategory = (category) => {
        editingId = Number(category.id || 0);
        form.elements.name.value = category.name || "";
        form.elements.slug.value = category.slug || "";
        form.elements.category_type.value = category.category_type || "core";
        form.elements.parent_id.value = category.parent_id || "";
        form.elements.description.value = category.description || "";
        form.elements.sort_order.value = category.sort_order || 0;
      };

      const clearForm = () => {
        form.reset();
        editingId = 0;
      };

      tableBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const action = target.dataset.action;
        const id = Number(target.dataset.id || 0);
        if (!id || !action) {
          return;
        }

        if (action === "delete") {
          if (!window.confirm("Archive this category?")) {
            return;
          }

          try {
            await utils.apiDelete(`/api/admin/categories/${id}`);
            await loadCategories();
            status.textContent = "Category archived.";
          } catch (error) {
            status.textContent = error.message;
          }
          return;
        }

        if (action === "edit") {
          const cache = JSON.parse(tableBody.dataset.cache || "[]");
          const category = cache.find((item) => Number(item.id) === id);
          if (category) {
            setFormFromCategory(category);
            status.textContent = "Editing selected category.";
          }
        }
      });

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.slug = payload.slug || toSlug(payload.name);

        try {
          if (editingId > 0) {
            await utils.apiPatch(`/api/admin/categories/${editingId}`, payload);
            status.textContent = "Category updated.";
          } else {
            await utils.apiPost("/api/admin/categories", payload);
            status.textContent = "Category created.";
          }

          clearForm();
          await loadCategories();
        } catch (error) {
          status.textContent = error.message;
        }
      });

      form?.elements?.name?.addEventListener("blur", () => {
        if (!form.elements.slug.value.trim()) {
          form.elements.slug.value = toSlug(form.elements.name.value);
        }
      });

      await loadCategories();
    });
  };

  const initAdminCourses = () => {
    const page = document.querySelector('[data-page="admin-courses"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("adminCourseForm");
      const status = document.getElementById("adminCourseStatus");
      const tableBody = document.querySelector("#adminCoursesTable tbody");
      const search = document.getElementById("adminCourseSearch");
      const resetBtn = document.getElementById("adminCourseResetBtn");
      let editingId = 0;

      const clearForm = () => {
        form.reset();
        editingId = 0;
        form.elements.id.value = "";
        form.elements.is_active.checked = true;
      };

      const setFormFromCourse = (course) => {
        editingId = Number(course.id || 0);
        form.elements.id.value = course.id || "";
        form.elements.title.value = course.title || "";
        form.elements.slug.value = course.slug || "";
        form.elements.short_description.value = course.short_description || "";
        form.elements.description.value = course.description || "";
        form.elements.modules.value = course.modules || "";
        form.elements.duration_text.value = course.duration_text || "";
        form.elements.mode.value = course.mode || "offline";
        form.elements.fee_amount.value = course.fee_amount || "";
        form.elements.image_url.value = course.image_url || "";
        form.elements.cta_label.value = course.cta_label || "";
        form.elements.cta_url.value = course.cta_url || "";
        form.elements.is_active.checked = Number(course.is_active) === 1;
      };

      const loadCourses = async () => {
        const q = search?.value?.trim() || "";
        const payload = await utils.apiGet(`/api/admin/courses?limit=120&q=${encodeURIComponent(q)}`);
        const items = payload.data?.items || [];

        if (!items.length) {
          tableBody.innerHTML = '<tr><td colspan="5">No courses found.</td></tr>';
          tableBody.dataset.cache = "[]";
          return;
        }

        tableBody.innerHTML = items
          .map(
            (item) => `
              <tr>
                <td>${item.title}</td>
                <td>${item.mode}</td>
                <td>${utils.formatInr(item.fee_amount || 0)}</td>
                <td>${Number(item.is_active) === 1 ? "active" : "inactive"}</td>
                <td>
                  <button class="btn btn--secondary" type="button" data-action="edit" data-id="${item.id}">Edit</button>
                  <button class="btn btn--secondary" type="button" data-action="archive" data-id="${item.id}">Archive</button>
                </td>
              </tr>
            `
          )
          .join("");

        tableBody.dataset.cache = JSON.stringify(items);
      };

      tableBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const action = target.dataset.action;
        const id = Number(target.dataset.id || 0);
        if (!action || id <= 0) {
          return;
        }

        if (action === "edit") {
          const payload = await utils.apiGet(`/api/admin/courses?limit=120`);
          const course = (payload.data?.items || []).find((item) => Number(item.id) === id);
          if (course) {
            setFormFromCourse(course);
            status.textContent = "Editing selected course.";
          }
          return;
        }

        if (action === "archive") {
          if (!window.confirm("Archive this course?")) {
            return;
          }
          try {
            await utils.apiDelete(`/api/admin/courses/${id}`);
            status.textContent = "Course archived.";
            if (editingId === id) {
              clearForm();
            }
            await loadCourses();
          } catch (error) {
            status.textContent = error.message;
          }
        }
      });

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.slug = payload.slug || toSlug(payload.title);
        payload.is_active = form.elements.is_active.checked ? 1 : 0;

        try {
          if (editingId > 0) {
            await utils.apiPatch(`/api/admin/courses/${editingId}`, payload);
            status.textContent = "Course updated.";
          } else {
            await utils.apiPost("/api/admin/courses", payload);
            status.textContent = "Course created.";
          }
          clearForm();
          await loadCourses();
        } catch (error) {
          status.textContent = error.message;
        }
      });

      resetBtn?.addEventListener("click", () => {
        clearForm();
        status.textContent = "Form reset.";
      });

      search?.addEventListener("input", () => {
        void loadCourses();
      });

      form?.elements?.title?.addEventListener("blur", () => {
        if (!form.elements.slug.value.trim()) {
          form.elements.slug.value = toSlug(form.elements.title.value);
        }
      });

      await loadCourses();
    });
  };

  const initBulkImport = () => {
    const page = document.querySelector('[data-page="admin-bulk-import"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("bulkImportForm");
      const input = document.getElementById("bulkImportFile");
      const status = document.getElementById("bulkImportStatus");
      const report = document.getElementById("bulkImportReport");
      const logsBody = document.querySelector("#importLogsTable tbody");

      const loadLogs = async () => {
        const payload = await utils.apiGet("/api/admin/import/logs");
        const items = payload.data?.items || [];

        if (!items.length) {
          logsBody.innerHTML = '<tr><td colspan="7">No logs found.</td></tr>';
          return;
        }

        logsBody.innerHTML = items
          .map(
            (item) => `
              <tr>
                <td>${item.generated_at || "-"}</td>
                <td>${item.mode || "commit"}${item.strict_variants ? " / strict" : ""}${item.abort_on_error ? " / fail-fast" : ""}</td>
                <td>${item.created_count}</td>
                <td>${item.updated_count}</td>
                <td>${item.failed_count}</td>
                <td>${item.failed_rows_csv_url ? `<a class="link-inline" href="${item.failed_rows_csv_url}">Download</a>` : "-"}</td>
                <td>${item.file}</td>
              </tr>
            `
          )
          .join("");
      };

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const file = input.files?.[0];
        if (!file) {
          status.textContent = "Please choose a CSV or Excel (.xlsx) file first.";
          return;
        }

        const formData = new FormData();
        formData.append("file", file);
        formData.append("strict_variants", form.querySelector('[name="strict_variants"]')?.checked ? "1" : "0");
        formData.append("dry_run", form.querySelector('[name="dry_run"]')?.checked ? "1" : "0");
        formData.append("abort_on_error", form.querySelector('[name="abort_on_error"]')?.checked ? "1" : "0");
        utils.appendCsrfToFormData(formData);

        status.textContent = "Import in progress...";
        report.textContent = "";

        try {
          const response = await fetch("/api/admin/import/products", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
          });
          const payload = await response.json();

          if (!response.ok || payload.success === false) {
            throw new Error(payload.message || "Import failed");
          }

          status.textContent = "Import completed.";
          report.textContent = JSON.stringify(payload.data, null, 2);
          if (payload.data?.failed_rows_csv_url) {
            report.textContent += `\n\nfailed_rows_csv_url: ${payload.data.failed_rows_csv_url}`;
          }
          await loadLogs();
        } catch (error) {
          status.textContent = error.message;
        }
      });

      await loadLogs();
    });
  };

  const initMediaManager = () => {
    const page = document.querySelector('[data-page="admin-media"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("mediaUploadForm");
      const input = document.getElementById("mediaUploadInput");
      const status = document.getElementById("mediaUploadStatus");
      const progress = document.getElementById("mediaUploadProgress");
      const progressText = document.getElementById("mediaUploadProgressText");
      const activeVideoInfo = document.getElementById("mediaActiveVideoInfo");
      const previewWrap = document.getElementById("mediaPreviewWrap");
      const grid = document.getElementById("mediaLibraryGrid");
      const attachProduct = document.getElementById("mediaAttachProductId");
      const attachMode = document.getElementById("mediaAttachMode");
      const queuePending = document.getElementById("mediaQueuePending");
      const queueProcessing = document.getElementById("mediaQueueProcessing");
      const queueCompleted = document.getElementById("mediaQueueCompleted");
      const queueFailed = document.getElementById("mediaQueueFailed");
      const storageOriginal = document.getElementById("mediaStorageOriginal");
      const storageOptimized = document.getElementById("mediaStorageOptimized");
      const storageSavings = document.getElementById("mediaStorageSavings");
      const storageRatio = document.getElementById("mediaStorageRatio");
      const queueOrphans = document.getElementById("mediaQueueOrphans");
      const queueStatus = document.getElementById("mediaQueueStatus");
      const queueRefresh = document.getElementById("mediaQueueRefresh");
      const queueJobs = document.getElementById("mediaQueueJobs");
      const queueHealthBadge = document.getElementById("mediaQueueHealthBadge");
      const savingsBadge = document.getElementById("mediaSavingsBadge");
      const failureSparkline = document.getElementById("mediaFailureSparkline");
      const throughputSparkline = document.getElementById("mediaThroughputSparkline");
      const failureTrendValue = document.getElementById("mediaFailureTrendValue");
      const throughputTrendValue = document.getElementById("mediaThroughputTrendValue");
      let isUploading = false;
      const maxUploadBytes = 100 * 1024 * 1024;
      const trendState = {
        failedSeries: [],
        throughputSeries: [],
        lastCompleted: null,
      };

      const setUploadProgress = (value) => {
        if (!progress || !progressText) return;
        if (value <= 0 || value >= 100) {
          progress.hidden = true;
          progressText.hidden = true;
          progress.value = 0;
          progressText.textContent = "Uploading: 0%";
          return;
        }
        progress.hidden = false;
        progressText.hidden = false;
        progress.value = value;
        progressText.textContent = `Uploading: ${value}%`;
      };

      const loadProducts = async () => {
        const payload = await utils.apiGet("/api/admin/products?limit=200");
        const items = payload.data?.items || [];
        const options = ['<option value="">Select product</option>']
          .concat(items.map((item) => `<option value="${item.id}">${item.name} (${item.sku})</option>`));
        attachProduct.innerHTML = options.join("");
      };

      const renderMedia = (items) => {
        if (!items.length) {
          grid.innerHTML = '<article class="card"><p class="text-muted">No media uploaded yet.</p></article>';
          return;
        }

        const mediaMarkup = (item) => {
          const path = item.url || item.path || "";
          const ext = path.split("?")[0].split(".").pop()?.toLowerCase() || "";
          const videoExts = new Set(["mp4", "webm", "mov", "m4v", "ogg", "avi", "mkv", "mpg", "mpeg"]);
          if (videoExts.has(ext)) {
            return `<video class="media-card__image" src="${item.url}" muted playsinline controls preload="metadata"></video>`;
          }
          return `<img class="media-card__image" src="${item.url}" alt="${item.name}" loading="lazy" />`;
        };

        const firstVideo = items.find((item) => {
          const ext = (item.path || "").split("?")[0].split(".").pop()?.toLowerCase() || "";
          return ["mp4", "webm", "mov", "m4v", "ogg", "avi", "mkv", "mpg", "mpeg"].includes(ext);
        });
        if (activeVideoInfo) {
          if (firstVideo) {
            const conversionStatus = firstVideo.conversion_status ? ` | Conversion: ${firstVideo.conversion_status}` : "";
            activeVideoInfo.textContent = `Active video candidate: ${firstVideo.name || "video"} (${formatBytes(Number(firstVideo.size || 0))})${conversionStatus}`;
          } else {
            activeVideoInfo.textContent = "No video found in current media library.";
          }
        }

        grid.innerHTML = items
          .map(
            (item) => `
              <article class="media-card">
                ${mediaMarkup(item)}
                <div class="media-card__body">
                  <p><strong>${item.name}</strong></p>
                  <p class="text-muted">${formatBytes(Number(item.size || 0))} | ${item.updated_at || item.uploaded_at || "-"}</p>
                  <p class="text-muted">${item.conversion_status ? `Status: ${item.conversion_status}` : "Status: ready"}${item.conversion_error ? ` | ${item.conversion_error}` : ""}</p>
                  <div class="product-card__actions">
                    <button class="btn btn--secondary" type="button" data-action="copy" data-path="${item.path}">Copy Path</button>
                    <button class="btn btn--secondary" type="button" data-action="attach" data-path="${item.path}">Attach</button>
                    <button class="btn btn--secondary" type="button" data-action="delete" data-path="${item.path}">Delete</button>
                  </div>
                </div>
              </article>
            `
          )
          .join("");
      };

      const loadMedia = async () => {
        const payload = await utils.apiGet("/api/admin/media");
        renderMedia(payload.data?.items || []);
      };

      const setBadge = (element, type, label) => {
        if (!element) return;
        element.className = `badge media-health-badge badge--${type}`;
        element.textContent = label;
      };

      const updateSparkline = (container, points, tone, label) => {
        if (!container) return;
        const normalized = points.slice(-12);
        const max = Math.max(1, ...normalized.map((point) => Number(point?.value || 0)));
        container.className = `media-sparkline media-sparkline--${tone}`;
        container.innerHTML = normalized
          .map((point) => {
            const value = Number(point?.value || 0);
            const timestamp = String(point?.timestamp || "-");
            const pct = Math.max(8, Math.round((value / max) * 100));
            const tooltip = `${label}: ${value}\n${timestamp}`;
            return `<span class="media-sparkline__bar" style="height:${pct}%" title="${tooltip}"></span>`;
          })
          .join("");
      };

      const updateTrendChips = (counts) => {
        const failedCount = Number(counts.failed || 0);
        const completedCount = Number(counts.completed || 0);
        const refreshTimestamp = new Date().toLocaleString();

        let throughputDelta = 0;
        if (trendState.lastCompleted !== null) {
          throughputDelta = Math.max(0, completedCount - Number(trendState.lastCompleted || 0));
        }
        trendState.lastCompleted = completedCount;

        trendState.failedSeries.push({ value: failedCount, timestamp: refreshTimestamp });
        trendState.throughputSeries.push({ value: throughputDelta, timestamp: refreshTimestamp });
        trendState.failedSeries = trendState.failedSeries.slice(-12);
        trendState.throughputSeries = trendState.throughputSeries.slice(-12);

        if (failureTrendValue) {
          failureTrendValue.textContent = String(failedCount);
        }
        if (throughputTrendValue) {
          throughputTrendValue.textContent = `+${throughputDelta}`;
        }

        updateSparkline(failureSparkline, trendState.failedSeries, failedCount > 0 ? "danger" : "warning", "Failures");
        updateSparkline(throughputSparkline, trendState.throughputSeries, "success", "Throughput");
      };

      const renderProcessingSummary = (data) => {
        const counts = data?.counts || {};
        const storage = data?.storage || {};
        const orphans = data?.orphans || {};
        const pendingCount = Number(counts.pending || 0);
        const processingCount = Number(counts.processing || 0);
        const failedCount = Number(counts.failed || 0);
        const savingsRatio = Number(storage.optimization_ratio || 0);

        if (queuePending) queuePending.textContent = String(pendingCount);
        if (queueProcessing) queueProcessing.textContent = String(processingCount);
        if (queueCompleted) queueCompleted.textContent = String(Number(counts.completed || 0));
        if (queueFailed) queueFailed.textContent = String(failedCount);

        if (storageOriginal) storageOriginal.textContent = formatBytes(Number(storage.original_bytes || 0));
        if (storageOptimized) storageOptimized.textContent = formatBytes(Number(storage.optimized_bytes || 0));
        if (storageSavings) storageSavings.textContent = formatBytes(Number(storage.savings_bytes || 0));
        if (storageRatio) storageRatio.textContent = `${savingsRatio}%`;

        if (queueOrphans) queueOrphans.textContent = String(Number(orphans.optimized_without_queue || 0));

        if (failedCount > 0) {
          setBadge(queueHealthBadge, "danger", "Queue: At Risk");
        } else if (pendingCount > 0 || processingCount > 0) {
          setBadge(queueHealthBadge, "warning", "Queue: Active");
        } else {
          setBadge(queueHealthBadge, "success", "Queue: Healthy");
        }

        if (savingsRatio >= 40) {
          setBadge(savingsBadge, "success", `Savings: ${savingsRatio}%`);
        } else if (savingsRatio >= 15) {
          setBadge(savingsBadge, "warning", `Savings: ${savingsRatio}%`);
        } else {
          setBadge(savingsBadge, "danger", `Savings: ${savingsRatio}%`);
        }

        updateTrendChips(counts);
      };

      const renderProcessingJobs = (items) => {
        if (!queueJobs) return;

        if (!Array.isArray(items) || !items.length) {
          queueJobs.innerHTML = "No queue jobs to show.";
          return;
        }

        queueJobs.innerHTML = `
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Status</th>
                  <th>Module</th>
                  <th>Entity</th>
                  <th>Attempts</th>
                  <th>Updated</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody>
                ${items
                  .map((item) => {
                    const status = String(item.processing_status || "-");
                    const moduleName = String(item.module_name || "-");
                    const entity = `${String(item.entity_type || "-")}#${Number(item.entity_id || 0)}`;
                    const attempts = Number(item.attempts || 0);
                    const updated = String(item.updated_at || item.processed_at || item.created_at || "-");
                    const error = String(item.last_error || "-").slice(0, 120);
                    return `
                      <tr>
                        <td>${Number(item.id || 0)}</td>
                        <td>${status}</td>
                        <td>${moduleName}</td>
                        <td>${entity}</td>
                        <td>${attempts}</td>
                        <td>${updated}</td>
                        <td>${error}</td>
                      </tr>
                    `;
                  })
                  .join("")}
              </tbody>
            </table>
          </div>
        `;
      };

      const loadProcessingDashboard = async () => {
        if (queueStatus) queueStatus.textContent = "Refreshing queue metrics...";

        try {
          const [summaryPayload, jobsPayload] = await Promise.all([
            utils.apiGet("/api/admin/media/processing/summary"),
            utils.apiGet("/api/admin/media/processing/jobs?limit=20"),
          ]);

          renderProcessingSummary(summaryPayload.data || {});
          const items = (jobsPayload.data?.items || []).filter((item) =>
            ["pending", "processing", "failed"].includes(String(item.processing_status || ""))
          );
          renderProcessingJobs(items.slice(0, 20));

          if (queueStatus) {
            queueStatus.textContent = `Updated ${new Date().toLocaleString()}`;
          }
        } catch (error) {
          if (queueStatus) {
            queueStatus.textContent = `Queue metrics unavailable: ${error.message}`;
          }
        }
      };

      input?.addEventListener("change", () => {
        const file = input.files?.[0];
        if (!file) {
          previewWrap.innerHTML = "";
          return;
        }

        const reader = new FileReader();
        reader.onload = () => {
          if (file.type.startsWith("video/")) {
            previewWrap.innerHTML = `<video src="${reader.result}" controls muted playsinline></video>`;
            return;
          }
          previewWrap.innerHTML = `<img src="${reader.result}" alt="Preview" />`;
        };
        reader.readAsDataURL(file);
      });

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (isUploading) {
          status.textContent = "Upload already in progress. Please wait...";
          return;
        }

        const file = input.files?.[0];
        if (!file) {
          status.textContent = "Please select a media file.";
          return;
        }

        if (Number(file.size || 0) > maxUploadBytes) {
          status.textContent = "File exceeds 100 MB media upload limit.";
          return;
        }

        const formData = new FormData();
        formData.append("file", file);
        utils.appendCsrfToFormData(formData);

        status.textContent = "Uploading media...";
        isUploading = true;
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn instanceof HTMLButtonElement) {
          submitBtn.disabled = true;
        }
        setUploadProgress(1);

        try {
          const payload = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "/api/admin/media/upload", true);
            xhr.withCredentials = true;
            xhr.upload.onprogress = (progressEvent) => {
              if (!progressEvent.lengthComputable) return;
              const percent = Math.max(1, Math.min(99, Math.round((progressEvent.loaded / progressEvent.total) * 100)));
              setUploadProgress(percent);
            };
            xhr.onerror = () => reject(new Error("Upload failed"));
            xhr.onload = () => {
              try {
                const json = JSON.parse(xhr.responseText || "{}");
                if (xhr.status < 200 || xhr.status >= 300 || json.success === false) {
                  reject(new Error(json.message || "Upload failed"));
                  return;
                }
                resolve(json);
              } catch (parseError) {
                reject(new Error("Upload response parsing failed"));
              }
            };
            xhr.send(formData);
          });

          const uploadData = payload.data || {};

          if (uploadData.conversion_status && uploadData.conversion_status !== "ready") {
            status.textContent = `Uploaded: ${uploadData.path}. Conversion status: ${uploadData.conversion_status}.`;
          } else {
            status.textContent = `Uploaded: ${uploadData.path}`;
          }
          form.reset();
          previewWrap.innerHTML = "";
          await loadMedia();
        } catch (error) {
          status.textContent = error.message;
        } finally {
          isUploading = false;
          if (submitBtn instanceof HTMLButtonElement) {
            submitBtn.disabled = false;
          }
          setUploadProgress(0);
        }
      });

      grid?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const action = target.dataset.action;
        const path = target.dataset.path || "";
        if (!action || !path) {
          return;
        }

        if (action === "copy") {
          await navigator.clipboard.writeText(path);
          status.textContent = `Copied: ${path}`;
          return;
        }

        if (action === "attach") {
          const productId = Number(attachProduct?.value || 0);
          const mode = attachMode?.value || "featured";

          if (productId <= 0) {
            status.textContent = "Choose a target product before attaching media.";
            return;
          }

          try {
            await utils.apiPost(`/api/admin/products/${productId}/media/attach`, {
              path,
              mode,
            });
            status.textContent = `Attached ${path} as ${mode} image.`;
          } catch (error) {
            status.textContent = error.message;
          }
          return;
        }

        if (action === "delete") {
          if (!window.confirm("Delete this media file?")) {
            return;
          }

          try {
            await utils.apiPost("/api/admin/media/delete", { path });
            status.textContent = "Media file deleted.";
            await loadMedia();
          } catch (error) {
            status.textContent = error.message;
          }
        }
      });

      queueRefresh?.addEventListener("click", async () => {
        await loadProcessingDashboard();
      });

      await loadProducts();
      await loadMedia();
      await loadProcessingDashboard();
    });
  };

  const initAdminOrders = () => {
    const page = document.querySelector('[data-page="admin-orders"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("adminOrderFilters");
      const status = document.getElementById("adminOrderStatus");
      const tableBody = document.querySelector("#adminOrdersTable tbody");
      const exportLink = document.getElementById("adminOrderExport");
      const drawer = document.getElementById("adminOrderDrawer");
      const drawerTitle = document.getElementById("adminOrderDrawerTitle");
      const drawerMeta = document.getElementById("adminOrderDrawerMeta");
      const drawerItems = document.getElementById("adminOrderDrawerItems");
      const drawerTimeline = document.getElementById("adminOrderTimeline");
      const detailForm = document.getElementById("adminOrderDetailForm");
      let selectedOrderId = 0;

      const allowedOrderStatuses = [
        "pending",
        "confirmed",
        "in_preparation",
        "out_for_delivery",
        "ready_for_pickup",
        "completed",
        "cancelled",
      ];
      const allowedPaymentStatuses = ["pending", "paid", "failed", "refunded"];

      const buildQuery = () => {
        const params = new URLSearchParams();
        const formData = new FormData(form);
        formData.forEach((value, key) => {
          const normalized = String(value || "").trim();
          if (normalized !== "") {
            params.set(key, normalized);
          }
        });
        params.set("limit", "100");
        return params.toString();
      };

      const renderStatusSelect = (type, selected) => {
        const options = type === "order" ? allowedOrderStatuses : allowedPaymentStatuses;
        return `
          <select data-role="${type}-status">
            ${options
              .map((value) => `<option value="${value}" ${String(selected) === value ? "selected" : ""}>${value}</option>`)
              .join("")}
          </select>
        `;
      };

      const closeDrawer = () => {
        if (!drawer) {
          return;
        }
        drawer.hidden = true;
        drawer.setAttribute("aria-hidden", "true");
      };

      const openDrawer = () => {
        if (!drawer) {
          return;
        }
        drawer.hidden = false;
        drawer.setAttribute("aria-hidden", "false");
      };

      const toDateTimeLocalValue = (value) => {
        if (!value) {
          return "";
        }
        const normalized = String(value).replace(" ", "T");
        return normalized.slice(0, 16);
      };

      const renderTimeline = (events) => {
        if (!drawerTimeline) {
          return;
        }
        if (!events.length) {
          drawerTimeline.innerHTML = '<p class="text-muted">No timeline events yet.</p>';
          return;
        }
        drawerTimeline.innerHTML = events
          .map(
            (event) => `
              <article class="admin-timeline-item">
                <p><span class="status-pill status-pill--${event.badge || "neutral"}">${event.label || event.action_type}</span></p>
                <p>${event.message || "Action recorded by admin."}</p>
                <p class="text-muted">${event.created_at} by ${event.admin_name || "Admin"}</p>
                <pre class="admin-timeline-item__meta">${JSON.stringify(event.metadata || {}, null, 2)}</pre>
              </article>
            `
          )
          .join("");
      };

      const openOrderDetail = async (orderId) => {
        const payload = await utils.apiGet(`/api/admin/orders/${orderId}`);
        const order = payload.data?.order || {};
        const items = payload.data?.items || [];
        const timeline = payload.data?.timeline || [];

        selectedOrderId = Number(order.id || orderId);

        if (drawerTitle) {
          drawerTitle.textContent = `Order ${order.order_number || ""}`;
        }
        if (drawerMeta) {
          drawerMeta.innerHTML = `
            <p><strong>Customer:</strong> ${order.customer_name || "-"} (${order.customer_email || "-"})</p>
            <p><strong>Phone:</strong> ${order.customer_phone || "-"}</p>
            <p><strong>Fulfilment:</strong> ${order.fulfilment_mode || "-"}</p>
            <p><strong>Current Slot:</strong> ${order.scheduled_slot_label || "-"}</p>
            <p><strong>Total:</strong> ${utils.formatInr(order.grand_total || 0)}</p>
          `;
        }

        if (drawerItems) {
          if (!items.length) {
            drawerItems.innerHTML = '<p class="text-muted">No line items found.</p>';
          } else {
            drawerItems.innerHTML = items
              .map(
                (item) => `
                  <article class="admin-list-item">
                    <p><strong>${item.product_name_snapshot}</strong> ${item.variant_snapshot ? `(${item.variant_snapshot})` : ""}</p>
                    <p class="text-muted">Qty ${item.quantity} x ${utils.formatInr(item.unit_price || 0)} = ${utils.formatInr(item.line_total || 0)}</p>
                    ${item.customisation_note ? `<p class="text-muted">Note: ${item.customisation_note}</p>` : ""}
                  </article>
                `
              )
              .join("");
          }
        }

        if (detailForm) {
          detailForm.elements.scheduled_slot_label.value = order.scheduled_slot_label || "";
          detailForm.elements.scheduled_slot.value = toDateTimeLocalValue(order.scheduled_slot || "");
          detailForm.elements.admin_note.value = order.admin_note || "";
        }

        renderTimeline(timeline);
        openDrawer();
      };

      const loadOrders = async () => {
        const query = buildQuery();
        const payload = await utils.apiGet(`/api/admin/orders?${query}`);
        const items = payload.data?.items || [];

        exportLink.setAttribute("href", `/api/admin/orders/export?${query}`);

        if (!items.length) {
          tableBody.innerHTML = '<tr><td colspan="7">No orders found.</td></tr>';
          return;
        }

        tableBody.innerHTML = items
          .map(
            (item) => `
              <tr data-id="${item.id}">
                <td>
                  <strong>${item.order_number}</strong>
                  <div class="text-muted">${item.created_at}</div>
                </td>
                <td>
                  <strong>${item.customer_name}</strong>
                  <div class="text-muted">${item.customer_email}</div>
                  <div class="text-muted">${item.customer_phone}</div>
                </td>
                <td>
                  <div>${item.fulfilment_mode}</div>
                  <div class="text-muted">${item.scheduled_slot_label || "-"}</div>
                  <div class="text-muted">${item.delivery_postal_code || "-"}</div>
                </td>
                <td>${utils.formatInr(item.grand_total || 0)}</td>
                <td>${renderStatusSelect("payment", item.payment_status)}</td>
                <td>${renderStatusSelect("order", item.order_status)}</td>
                <td>
                  <button class="btn btn--secondary" type="button" data-action="detail">Detail</button>
                  <button class="btn btn--secondary" type="button" data-action="save">Save</button>
                </td>
              </tr>
            `
          )
          .join("");
      };

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        try {
          await loadOrders();
          status.textContent = "Filters applied.";
        } catch (error) {
          status.textContent = error.message;
        }
      });

      tableBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        if (target.dataset.action === "detail") {
          const detailRow = target.closest("tr");
          if (!(detailRow instanceof HTMLTableRowElement)) {
            return;
          }
          const detailId = Number(detailRow.dataset.id || 0);
          if (detailId <= 0) {
            return;
          }

          try {
            await openOrderDetail(detailId);
          } catch (error) {
            status.textContent = error.message;
          }
          return;
        }

        if (target.dataset.action !== "save") {
          return;
        }

        const row = target.closest("tr");
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }

        const id = Number(row.dataset.id || 0);
        const orderStatus = row.querySelector('[data-role="order-status"]')?.value;
        const paymentStatus = row.querySelector('[data-role="payment-status"]')?.value;
        if (id <= 0) {
          return;
        }

        try {
          await utils.apiPatch(`/api/admin/orders/${id}/status`, {
            order_status: orderStatus,
            payment_status: paymentStatus,
            delivery_status: orderStatus,
          });
          status.textContent = `Order ${id} updated.`;
        } catch (error) {
          status.textContent = error.message;
        }
      });

      drawer?.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        if (target.dataset.action === "close-order-drawer") {
          closeDrawer();
        }
      });

      detailForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (selectedOrderId <= 0) {
          return;
        }

        const payload = Object.fromEntries(new FormData(detailForm).entries());
        try {
          await utils.apiPatch(`/api/admin/orders/${selectedOrderId}/status`, payload);
          status.textContent = `Order ${selectedOrderId} slot/note updated.`;
          await openOrderDetail(selectedOrderId);
          await loadOrders();
        } catch (error) {
          status.textContent = error.message;
        }
      });

      await loadOrders();
    });
  };

  const initAdminFinanceDashboard = () => {
    const page = document.querySelector('[data-page="admin-finance-dashboard"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const summary = await utils.apiGet("/api/admin/finance/summary");
      const ageing = await utils.apiGet("/api/admin/finance/ageing");

      const setText = (id, value) => {
        const node = document.getElementById(id);
        if (node) {
          node.textContent = String(value ?? "-");
        }
      };

      const data = summary.data || {};
      setText("finTotalInvoices", data.total_invoices || 0);
      setText("finPaidInvoices", data.paid_invoices || 0);
      setText("finUnpaidInvoices", data.unpaid_invoices || 0);
      setText("finOverdueInvoices", data.overdue_invoices || 0);
      setText("finPartPaidInvoices", data.part_paid_invoices || 0);
      setText("finTotalReceivables", utils.formatInr(data.total_receivables || 0));
      setText("finRetailReceivables", utils.formatInr(data.retail_receivables || 0));
      setText("finB2bReceivables", utils.formatInr(data.b2b_receivables || 0));

      const tableBody = document.querySelector("#financeAgeingTable tbody");
      const items = ageing.data?.items || [];
      if (!items.length) {
        tableBody.innerHTML = '<tr><td colspan="3">No ageing data.</td></tr>';
        return;
      }
      tableBody.innerHTML = items
        .map(
          (item) => `
            <tr>
              <td>${item.ageing_bucket}</td>
              <td>${item.invoice_count}</td>
              <td>${utils.formatInr(item.balance_due || 0)}</td>
            </tr>
          `
        )
        .join("");
    });
  };

  const initAdminInvoices = () => {
    const page = document.querySelector('[data-page="admin-invoices"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const filterForm = document.getElementById("adminInvoiceFilters");
      const paymentForm = document.getElementById("adminInvoicePaymentForm");
      const statusNode = document.getElementById("adminInvoiceStatus");
      const tableBody = document.querySelector("#adminInvoicesTable tbody");

      const drawer = document.getElementById("adminInvoiceDrawer");
      const drawerTitle = document.getElementById("adminInvoiceDrawerTitle");
      const drawerMeta = document.getElementById("adminInvoiceDrawerMeta");
      const drawerItems = document.getElementById("adminInvoiceItems");
      const drawerPayments = document.getElementById("adminInvoicePayments");
      const drawerProofs = document.getElementById("adminInvoiceProofs");
      const drawerHistory = document.getElementById("adminInvoiceHistory");
      const statusForm = document.getElementById("adminInvoiceStatusForm");
      let selectedInvoiceId = 0;

      const closeDrawer = () => {
        if (drawer) {
          drawer.hidden = true;
          drawer.setAttribute("aria-hidden", "true");
        }
      };

      const openDrawer = () => {
        if (drawer) {
          drawer.hidden = false;
          drawer.setAttribute("aria-hidden", "false");
        }
      };

      const renderList = (target, items, mapper, emptyText) => {
        if (!target) {
          return;
        }
        if (!items.length) {
          target.innerHTML = `<p class="text-muted">${emptyText}</p>`;
          return;
        }
        target.innerHTML = items.map(mapper).join("");
      };

      const openInvoiceDetail = async (invoiceId) => {
        const payload = await utils.apiGet(`/api/admin/invoices/${invoiceId}`);
        const invoice = payload.data?.invoice || {};
        selectedInvoiceId = Number(invoice.id || invoiceId);

        if (drawerTitle) {
          drawerTitle.textContent = `Invoice ${invoice.invoice_number || ""}`;
        }
        if (drawerMeta) {
          drawerMeta.innerHTML = `
            <p><strong>Customer:</strong> ${invoice.customer_type === "b2b" ? invoice.company_name || "B2B" : invoice.retail_customer_name || "Retail"}</p>
            <p><strong>Status:</strong> ${invoice.invoice_status || "-"}</p>
            <p><strong>Method:</strong> ${invoice.payment_method || "-"}</p>
            <p><strong>Total:</strong> ${utils.formatInr(invoice.grand_total || 0)}</p>
            <p><strong>Paid:</strong> ${utils.formatInr(invoice.paid_amount || 0)} | <strong>Due:</strong> ${utils.formatInr(invoice.balance_due || 0)}</p>
          `;
        }

        if (statusForm) {
          statusForm.elements.invoice_status.value = invoice.invoice_status || "pending_payment";
          statusForm.elements.note.value = "";
        }

        renderList(
          drawerItems,
          payload.data?.items || [],
          (item) => `<article class="admin-list-item"><p><strong>${item.item_label}</strong></p><p class="text-muted">${item.quantity} x ${utils.formatInr(item.unit_price || 0)} = ${utils.formatInr(item.line_total || 0)}</p></article>`,
          "No invoice items."
        );

        renderList(
          drawerPayments,
          payload.data?.payments || [],
          (item) => `<article class="admin-list-item"><p><strong>${item.payment_method}</strong> | ${item.payment_status}</p><p class="text-muted">${utils.formatInr(item.amount || 0)} | Ref: ${item.payment_reference || "-"}</p><p class="text-muted">${item.created_at}</p></article>`,
          "No payment entries."
        );

        renderList(
          drawerProofs,
          payload.data?.proofs || [],
          (item) => `<article class="admin-list-item"><a class="link-inline" href="${item.file_url}" target="_blank" rel="noopener">${item.file_url}</a><p class="text-muted">${item.uploaded_by} | ${item.created_at}</p></article>`,
          "No payment proofs."
        );

        renderList(
          drawerHistory,
          payload.data?.history || [],
          (item) => `<article class="admin-list-item"><p><strong>${item.from_status || "-"}</strong> -> <strong>${item.to_status}</strong></p><p class="text-muted">${item.created_at} by ${item.changed_by || "Admin"}</p><p class="text-muted">${item.note || ""}</p></article>`,
          "No status history."
        );

        openDrawer();
      };

      const loadInvoices = async () => {
        const params = new URLSearchParams(new FormData(filterForm));
        params.set("limit", "120");
        const payload = await utils.apiGet(`/api/admin/invoices?${params.toString()}`);
        const items = payload.data?.items || [];
        if (!items.length) {
          tableBody.innerHTML = '<tr><td colspan="8">No invoices found.</td></tr>';
          return;
        }

        tableBody.innerHTML = items
          .map(
            (item) => `
              <tr data-id="${item.id}">
                <td><strong>${item.invoice_number}</strong><div class="text-muted">Due: ${item.due_on || "-"}</div></td>
                <td>${item.customer_type === "b2b" ? item.b2b_customer_name || "B2B" : item.retail_customer_name || "Retail"}</td>
                <td>${item.customer_type}</td>
                <td>${item.invoice_status}</td>
                <td>${utils.formatInr(item.grand_total || 0)}</td>
                <td>${utils.formatInr(item.paid_amount || 0)}</td>
                <td>${utils.formatInr(item.balance_due || 0)}</td>
                <td><button class="btn btn--secondary" type="button" data-action="detail">Detail</button></td>
              </tr>
            `
          )
          .join("");
      };

      filterForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        try {
          await loadInvoices();
          statusNode.textContent = "Filters applied.";
        } catch (error) {
          statusNode.textContent = error.message;
        }
      });

      tableBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "detail") {
          return;
        }
        const row = target.closest("tr");
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }
        const id = Number(row.dataset.id || 0);
        if (id <= 0) {
          return;
        }
        try {
          await openInvoiceDetail(id);
        } catch (error) {
          statusNode.textContent = error.message;
        }
      });

      paymentForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const formData = new FormData(paymentForm);
        utils.appendCsrfToFormData(formData);
        const invoiceId = Number(formData.get("invoice_id") || 0);
        if (invoiceId <= 0) {
          statusNode.textContent = "Invoice ID is required.";
          return;
        }

        try {
          const response = await fetch(`/api/admin/invoices/${invoiceId}/payments`, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
          });
          const payload = await response.json();
          if (!response.ok || payload.success === false) {
            throw new Error(payload.message || "Payment entry failed");
          }
          statusNode.textContent = "Payment entry recorded.";
          await loadInvoices();
          if (selectedInvoiceId === invoiceId) {
            await openInvoiceDetail(invoiceId);
          }
        } catch (error) {
          statusNode.textContent = error.message;
        }
      });

      statusForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (selectedInvoiceId <= 0) {
          return;
        }
        const payload = Object.fromEntries(new FormData(statusForm).entries());
        try {
          await utils.apiPatch(`/api/admin/invoices/${selectedInvoiceId}/status`, payload);
          statusNode.textContent = "Invoice status updated.";
          await openInvoiceDetail(selectedInvoiceId);
          await loadInvoices();
        } catch (error) {
          statusNode.textContent = error.message;
        }
      });

      drawer?.addEventListener("click", (event) => {
        const target = event.target;
        if (target instanceof HTMLElement && target.dataset.action === "close-invoice-drawer") {
          closeDrawer();
        }
      });

      await loadInvoices();
    });
  };

  const initAdminCommunications = () => {
    const page = document.querySelector('[data-page="admin-communications"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const smtpForm = document.getElementById("smtpSettingsForm");
      const smtpTestForm = document.getElementById("smtpTestForm");
      const smtpStatus = document.getElementById("smtpStatus");
      const waForm = document.getElementById("whatsappSettingsForm");
      const waStatus = document.getElementById("whatsappStatus");
      const templatesBody = document.querySelector("#commTemplatesTable tbody");
      const templateStatus = document.getElementById("commTemplateStatus");
      const editorStatus = document.getElementById("commTemplateEditorStatus");
      const editorForm = document.getElementById("commTemplateEditorForm");
      const tmplId = document.getElementById("tmplId");
      const tmplEventKey = document.getElementById("tmplEventKey");
      const tmplChannel = document.getElementById("tmplChannel");
      const tmplSubject = document.getElementById("tmplSubject");
      const tmplIsActive = document.getElementById("tmplIsActive");
      const tmplPlainWrap = document.getElementById("tmplPlainWrap");
      const tmplHtmlWrap = document.getElementById("tmplHtmlWrap");
      const tmplPreviewWrap = document.getElementById("tmplPreviewWrap");
      const tmplPlain = document.getElementById("tmplPlain");
      const tmplHtml = document.getElementById("tmplHtml");
      const tmplPreviewFrame = document.getElementById("tmplPreviewFrame");
      const tmplModePlain = document.getElementById("tmplModePlain");
      const tmplModeHtml = document.getElementById("tmplModeHtml");
      const tmplModePreview = document.getElementById("tmplModePreview");
      const tmplPreviewEditToggle = document.getElementById("tmplPreviewEditToggle");
      const tmplReloadBtn = document.getElementById("tmplReloadBtn");
      const tmplInsertLink = document.getElementById("tmplInsertLink");
      const tmplInsertImage = document.getElementById("tmplInsertImage");
      const tmplInsertVariable = document.getElementById("tmplInsertVariable");
      const tmplEditorToolbar = document.getElementById("tmplEditorToolbar");
      const logsBody = document.querySelector("#commLogsTable tbody");
      const logsStatus = document.getElementById("commLogsStatus");

      let templateItems = [];
      let selectedTemplate = null;
      let currentEditorMode = "plain";
      let previewEditEnabled = false;

      const loadSettings = async () => {
        const smtpPayload = await utils.apiGet("/api/admin/settings/smtp");
        const smtp = smtpPayload.data?.settings || {};
        if (smtpForm && smtp && smtp.id) {
          smtpForm.elements.host.value = smtp.host || "";
          smtpForm.elements.port.value = smtp.port || "";
          smtpForm.elements.username.value = smtp.username || "";
          smtpForm.elements.encryption.value = smtp.encryption || "tls";
          smtpForm.elements.from_name.value = smtp.from_name || "";
          smtpForm.elements.from_email.value = smtp.from_email || "";
          smtpForm.elements.is_active.checked = Number(smtp.is_active) === 1;
        }

        const waPayload = await utils.apiGet("/api/admin/settings/whatsapp");
        const wa = waPayload.data?.settings || {};
        if (waForm && wa && wa.id) {
          waForm.elements.provider_name.value = wa.provider_name || "";
          waForm.elements.api_base_url.value = wa.api_base_url || "";
          waForm.elements.phone_number_id.value = wa.phone_number_id || "";
          waForm.elements.business_account_id.value = wa.business_account_id || "";
          waForm.elements.webhook_verify_token.value = wa.webhook_verify_token || "";
          waForm.elements.is_active.checked = Number(wa.is_active) === 1;
        }
      };

      const htmlToPlain = (html) => {
        const temp = document.createElement("div");
        temp.innerHTML = html || "";
        return (temp.textContent || temp.innerText || "").trim();
      };

      const plainToHtml = (plainText) => {
        const escaped = (plainText || "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\"/g, "&quot;")
          .replace(/'/g, "&#39;");
        return escaped.replace(/\n/g, "<br>");
      };

      const getEditorHtml = () => {
        if (currentEditorMode === "plain") {
          return plainToHtml(tmplPlain?.value || "");
        }

        if (currentEditorMode === "preview") {
          try {
            const doc = tmplPreviewFrame?.contentDocument;
            const root = doc?.getElementById("tmpl-edit-root");
            if (root) {
              return root.innerHTML;
            }
          } catch (error) {
          }
        }

        return tmplHtml?.value || "";
      };

      const previewScaffold = (editableHtml) => `<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 16px; background: #f8fafc; color: #1f2937; }
    #tmpl-edit-root { min-height: 260px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
    #tmpl-edit-root:focus { outline: 2px solid #80001F33; }
    .dcore-lock-footer { margin-top: 14px; padding-top: 10px; border-top: 1px solid #e5e7eb; display:flex; align-items:center; gap:8px; color:#64748b; font-size:11px; }
    .dcore-lock-footer img { height:12px; width:auto; }
    .dcore-lock-footer .dcore-logo-white { display:none; }
    .dark-area { margin-top: 10px; background:#140b0f; padding:10px; border-radius:8px; }
    .dark-area .dcore-lock-footer { border-top-color:#3a2a31; color:#d7c6cc; }
    .dark-area .dcore-logo-black { display:none; }
    .dark-area .dcore-logo-white { display:block; }
  </style>
</head>
<body>
  <div id="tmpl-edit-root">${editableHtml || ""}</div>
  <div class="dcore-lock-footer" contenteditable="false">
    <span>Developed by</span>
    <img class="dcore-logo-black" src="/client/assets/images/dcore-logo-black.svg" alt="DCore Systems" />
    <img class="dcore-logo-white" src="/client/assets/images/dcore-logo-white.svg" alt="DCore Systems" />
    <strong>dcoresystems.com</strong>
  </div>
  <div class="dark-area" contenteditable="false">
    <div class="dcore-lock-footer">
      <span>Dark footer sample:</span>
      <img class="dcore-logo-black" src="/client/assets/images/dcore-logo-black.svg" alt="DCore Systems" />
      <img class="dcore-logo-white" src="/client/assets/images/dcore-logo-white.svg" alt="DCore Systems" />
      <strong>dcoresystems.com</strong>
    </div>
  </div>
</body>
</html>`;

      const renderPreview = (html) => {
        if (!(tmplPreviewFrame instanceof HTMLIFrameElement)) {
          return;
        }

        const doc = tmplPreviewFrame.contentDocument;
        if (!doc) {
          return;
        }

        doc.open();
        doc.write(previewScaffold(html));
        doc.close();

        const root = doc.getElementById("tmpl-edit-root");
        if (root) {
          root.setAttribute("contenteditable", previewEditEnabled ? "true" : "false");
        }
      };

      const setEditorMode = (mode) => {
        currentEditorMode = mode;

        if (mode === "plain") {
          tmplPlainWrap.style.display = "block";
          tmplHtmlWrap.style.display = "none";
          tmplPreviewWrap.style.display = "none";
          if (tmplHtml) {
            tmplPlain.value = htmlToPlain(tmplHtml.value);
          }
        } else if (mode === "html") {
          tmplPlainWrap.style.display = "none";
          tmplHtmlWrap.style.display = "block";
          tmplPreviewWrap.style.display = "none";
          if (tmplPlain && tmplHtml && tmplHtml.value.trim() === "") {
            tmplHtml.value = plainToHtml(tmplPlain.value);
          }
        } else {
          tmplPlainWrap.style.display = "none";
          tmplHtmlWrap.style.display = "none";
          tmplPreviewWrap.style.display = "block";
          renderPreview(getEditorHtml());
        }

        tmplModePlain?.classList.toggle("btn--primary", mode === "plain");
        tmplModeHtml?.classList.toggle("btn--primary", mode === "html");
        tmplModePreview?.classList.toggle("btn--primary", mode === "preview");
      };

      const bindTemplateEditor = (item) => {
        selectedTemplate = item;
        if (tmplId) tmplId.value = String(item.id || "");
        if (tmplEventKey) tmplEventKey.value = item.event_key || "";
        if (tmplChannel) tmplChannel.value = item.channel || "";
        if (tmplSubject) tmplSubject.value = item.subject || "";
        if (tmplIsActive) tmplIsActive.checked = Number(item.is_active) === 1;

        if (tmplHtml) tmplHtml.value = item.body_template || "";
        if (tmplPlain) tmplPlain.value = htmlToPlain(item.body_template || "");

        previewEditEnabled = false;
        if (tmplPreviewEditToggle) {
          tmplPreviewEditToggle.textContent = "Enable Preview Edit";
        }
        setEditorMode("html");
        editorStatus.textContent = `Editing template: ${item.event_key}`;
      };

      const loadTemplates = async () => {
        const payload = await utils.apiGet("/api/admin/communication/templates?channel=email");
        const items = payload.data?.items || [];
        templateItems = items;
        if (!items.length) {
          templatesBody.innerHTML = '<tr><td colspan="6">No templates found.</td></tr>';
          return;
        }

        templatesBody.innerHTML = items
          .map(
            (item) => `
              <tr data-id="${item.id}">
                <td>${item.channel}</td>
                <td>${item.event_key}</td>
                <td>${item.subject || "-"}</td>
                <td>${Number(item.is_active) === 1 ? "Yes" : "No"}</td>
                <td>${item.updated_at || "-"}</td>
                <td><button class="btn btn--secondary" type="button" data-action="edit-template">Open Studio</button></td>
              </tr>
            `
          )
          .join("");

        if (!selectedTemplate && items.length > 0) {
          bindTemplateEditor(items[0]);
        }
      };

      const loadLogs = async () => {
        const payload = await utils.apiGet("/api/admin/communication/logs?limit=120");
        const items = payload.data?.items || [];
        if (!items.length) {
          logsBody.innerHTML = '<tr><td colspan="6">No communication logs found.</td></tr>';
          return;
        }

        logsBody.innerHTML = items
          .map(
            (item) => `
              <tr data-id="${item.id}">
                <td>${item.created_at}</td>
                <td>${item.channel}</td>
                <td>${item.event_key}</td>
                <td>${item.recipient}</td>
                <td>${item.status}</td>
                <td>${item.status === "failed" || item.status === "queued" ? '<button class="btn btn--secondary" type="button" data-action="retry-log">Retry</button>' : '-'}</td>
              </tr>
            `
          )
          .join("");
      };

      smtpForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(smtpForm).entries());
        payload.is_active = smtpForm.elements.is_active.checked ? 1 : 0;
        try {
          await utils.apiPatch("/api/admin/settings/smtp", payload);
          smtpStatus.textContent = "SMTP settings saved.";
        } catch (error) {
          smtpStatus.textContent = error.message;
        }
      });

      smtpTestForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(smtpTestForm).entries());
        try {
          await utils.apiPost("/api/admin/settings/smtp/test", payload);
          smtpStatus.textContent = "SMTP test queued.";
          await loadLogs();
        } catch (error) {
          smtpStatus.textContent = error.message;
        }
      });

      waForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(waForm).entries());
        payload.is_active = waForm.elements.is_active.checked ? 1 : 0;
        try {
          await utils.apiPatch("/api/admin/settings/whatsapp", payload);
          waStatus.textContent = "WhatsApp settings saved.";
        } catch (error) {
          waStatus.textContent = error.message;
        }
      });

      templatesBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "edit-template") {
          return;
        }
        const row = target.closest("tr");
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }
        const id = Number(row.dataset.id || 0);
        if (id <= 0) {
          return;
        }
        const item = templateItems.find((entry) => Number(entry.id) === id);
        if (!item) {
          templateStatus.textContent = "Template not found in current list.";
          return;
        }
        bindTemplateEditor(item);
      });

      tmplModePlain?.addEventListener("click", () => setEditorMode("plain"));
      tmplModeHtml?.addEventListener("click", () => setEditorMode("html"));
      tmplModePreview?.addEventListener("click", () => setEditorMode("preview"));

      tmplPreviewEditToggle?.addEventListener("click", () => {
        previewEditEnabled = !previewEditEnabled;
        tmplPreviewEditToggle.textContent = previewEditEnabled ? "Disable Preview Edit" : "Enable Preview Edit";
        renderPreview(getEditorHtml());
      });

      tmplEditorToolbar?.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const button = target.closest("button[data-cmd]");
        if (!(button instanceof HTMLButtonElement)) {
          return;
        }

        event.preventDefault();
        if (currentEditorMode !== "preview") {
          setEditorMode("preview");
          previewEditEnabled = true;
          if (tmplPreviewEditToggle) {
            tmplPreviewEditToggle.textContent = "Disable Preview Edit";
          }
          renderPreview(getEditorHtml());
        }

        try {
          const doc = tmplPreviewFrame?.contentDocument;
          doc?.execCommand(button.dataset.cmd || "", false);
        } catch (error) {
        }
      });

      tmplInsertLink?.addEventListener("click", () => {
        const url = window.prompt("Enter URL:", "https://");
        if (!url) return;
        const doc = tmplPreviewFrame?.contentDocument;
        doc?.execCommand("createLink", false, url);
      });

      tmplInsertImage?.addEventListener("click", () => {
        const src = window.prompt("Enter image URL:", "https://");
        if (!src) return;
        const doc = tmplPreviewFrame?.contentDocument;
        doc?.execCommand("insertImage", false, src);
      });

      tmplInsertVariable?.addEventListener("click", () => {
        const token = window.prompt("Insert variable token:", "{{customer_name}}");
        if (!token) return;
        if (currentEditorMode === "html" && tmplHtml) {
          const start = tmplHtml.selectionStart || 0;
          const end = tmplHtml.selectionEnd || 0;
          const value = tmplHtml.value;
          tmplHtml.value = value.slice(0, start) + token + value.slice(end);
          return;
        }
        if (currentEditorMode === "plain" && tmplPlain) {
          const start = tmplPlain.selectionStart || 0;
          const end = tmplPlain.selectionEnd || 0;
          const value = tmplPlain.value;
          tmplPlain.value = value.slice(0, start) + token + value.slice(end);
          return;
        }
        const doc = tmplPreviewFrame?.contentDocument;
        doc?.execCommand("insertText", false, token);
      });

      tmplReloadBtn?.addEventListener("click", () => {
        if (!selectedTemplate) {
          return;
        }
        bindTemplateEditor(selectedTemplate);
      });

      editorForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const id = Number(tmplId?.value || 0);
        if (id <= 0) {
          editorStatus.textContent = "Pick a template from the list first.";
          return;
        }

        const bodyHtml = getEditorHtml().trim();
        if (!bodyHtml) {
          editorStatus.textContent = "Template body cannot be empty.";
          return;
        }

        try {
          await utils.apiPatch(`/api/admin/communication/templates/${id}`, {
            subject: tmplSubject?.value || "",
            body_template: bodyHtml,
            is_active: tmplIsActive?.checked ? 1 : 0,
          });
          editorStatus.textContent = "Template saved.";
          templateStatus.textContent = "Template updated successfully.";
          await loadTemplates();
          const fresh = templateItems.find((entry) => Number(entry.id) === id);
          if (fresh) {
            bindTemplateEditor(fresh);
          }
        } catch (error) {
          editorStatus.textContent = error.message;
        }
      });

      logsBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "retry-log") {
          return;
        }
        const row = target.closest("tr");
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }
        const id = Number(row.dataset.id || 0);
        if (id <= 0) {
          return;
        }
        try {
          await utils.apiPost(`/api/admin/communication/logs/${id}/retry`, {});
          logsStatus.textContent = "Retry queued.";
          await loadLogs();
        } catch (error) {
          logsStatus.textContent = error.message;
        }
      });

      await loadSettings();
      await loadTemplates();
      setEditorMode("html");
      await loadLogs();
    });
  };

  const initAdminAutomation = () => {
    const page = document.querySelector('[data-page="admin-automation"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const rulesBody = document.querySelector("#automationRulesTable tbody");
      const ruleStatus = document.getElementById("automationRuleStatus");
      const reminderForm = document.getElementById("reminderCreateForm");
      const reminderStatus = document.getElementById("reminderStatus");
      const remindersBody = document.querySelector("#remindersTable tbody");
      const queueBody = document.querySelector("#queueJobsTable tbody");
      const queueRunForm = document.getElementById("queueRunForm");
      const queueRunStatus = document.getElementById("queueRunStatus");

      const loadRules = async () => {
        const payload = await utils.apiGet("/api/admin/automation/rules");
        const items = payload.data?.items || [];
        if (!items.length) {
          rulesBody.innerHTML = '<tr><td colspan="7">No automation rules found.</td></tr>';
          return;
        }
        rulesBody.innerHTML = items
          .map(
            (item) => `
              <tr data-id="${item.id}">
                <td>${item.rule_key}</td>
                <td>${item.channel}</td>
                <td>${item.trigger_event}</td>
                <td>${item.template_event_key || "-"}</td>
                <td><input type="number" data-field="offset_days" value="${item.offset_days}" style="width:85px" /></td>
                <td><input type="checkbox" data-field="is_active" ${Number(item.is_active) === 1 ? "checked" : ""} /></td>
                <td><button class="btn btn--secondary" type="button" data-action="save-rule">Save</button></td>
              </tr>
            `
          )
          .join("");
      };

      const loadReminders = async () => {
        const payload = await utils.apiGet("/api/admin/reminders");
        const items = payload.data?.items || [];
        if (!items.length) {
          remindersBody.innerHTML = '<tr><td colspan="6">No reminders found.</td></tr>';
          return;
        }
        remindersBody.innerHTML = items
          .map(
            (item) => `
              <tr data-id="${item.id}">
                <td>${item.reminder_type}</td>
                <td>${item.title}</td>
                <td>${item.reminder_on}</td>
                <td>${item.customer_name || item.company_name || "-"}</td>
                <td>
                  <select data-field="status">
                    <option value="pending" ${item.status === "pending" ? "selected" : ""}>pending</option>
                    <option value="done" ${item.status === "done" ? "selected" : ""}>done</option>
                    <option value="cancelled" ${item.status === "cancelled" ? "selected" : ""}>cancelled</option>
                  </select>
                </td>
                <td><button class="btn btn--secondary" type="button" data-action="save-reminder">Save</button></td>
              </tr>
            `
          )
          .join("");
      };

      const loadQueue = async () => {
        const payload = await utils.apiGet("/api/admin/queue/jobs");
        const items = payload.data?.items || [];
        if (!items.length) {
          queueBody.innerHTML = '<tr><td colspan="6">Queue is empty.</td></tr>';
          return;
        }
        queueBody.innerHTML = items
          .map(
            (item) => `
              <tr>
                <td>${item.id}</td>
                <td>${item.job_type}</td>
                <td>${item.status}</td>
                <td>${item.available_at}</td>
                <td>${item.attempts}</td>
                <td>${item.last_error || "-"}</td>
              </tr>
            `
          )
          .join("");
      };

      rulesBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "save-rule") {
          return;
        }
        const row = target.closest("tr");
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }
        const id = Number(row.dataset.id || 0);
        if (id <= 0) {
          return;
        }
        const offsetDays = Number(row.querySelector('[data-field="offset_days"]')?.value || 0);
        const isActive = row.querySelector('[data-field="is_active"]')?.checked ? 1 : 0;

        try {
          await utils.apiPatch(`/api/admin/automation/rules/${id}`, {
            offset_days: offsetDays,
            is_active: isActive,
          });
          ruleStatus.textContent = "Automation rule updated.";
        } catch (error) {
          ruleStatus.textContent = error.message;
        }
      });

      reminderForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(reminderForm).entries());
        try {
          await utils.apiPost("/api/admin/reminders", payload);
          reminderStatus.textContent = "Reminder created.";
          reminderForm.reset();
          await loadReminders();
        } catch (error) {
          reminderStatus.textContent = error.message;
        }
      });

      remindersBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "save-reminder") {
          return;
        }
        const row = target.closest("tr");
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }
        const id = Number(row.dataset.id || 0);
        const status = row.querySelector('[data-field="status"]')?.value || "pending";
        if (id <= 0) {
          return;
        }
        try {
          await utils.apiPatch(`/api/admin/reminders/${id}`, { status });
          reminderStatus.textContent = "Reminder updated.";
        } catch (error) {
          reminderStatus.textContent = error.message;
        }
      });

      queueRunForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const maxJobs = Number(new FormData(queueRunForm).get("max_jobs") || 25);
        queueRunStatus.textContent = "Queue processing started...";
        try {
          const payload = await utils.apiPost("/api/admin/queue/process", {
            max_jobs: maxJobs,
          });
          const data = payload.data || {};
          queueRunStatus.textContent = `Queue run completed. Processed: ${data.processed || 0}, Completed: ${data.completed || 0}, Requeued: ${data.requeued || 0}, Failed: ${data.failed || 0}`;
          await loadQueue();
        } catch (error) {
          queueRunStatus.textContent = error.message;
        }
      });

      await loadRules();
      await loadReminders();
      await loadQueue();
    });
  };

  const initAdminBirthdays = () => {
    const page = document.querySelector('[data-page="admin-birthdays"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("birthdayFilterForm");
      const status = document.getElementById("birthdayStatus");
      const tbody = document.querySelector("#birthdaysTable tbody");

      const loadBirthdays = async () => {
        const days = Number(new FormData(form).get("days") || 30);
        const payload = await utils.apiGet(`/api/admin/customers/upcoming-birthdays?days=${encodeURIComponent(days)}`);
        const items = payload.data?.items || [];
        if (!items.length) {
          tbody.innerHTML = '<tr><td colspan="6">No upcoming birthdays in selected range.</td></tr>';
          return;
        }

        tbody.innerHTML = items
          .map(
            (item) => `
              <tr>
                <td>${item.full_name}</td>
                <td>${item.email}</td>
                <td>${item.phone}</td>
                <td>${item.date_of_birth || "-"}</td>
                <td>${item.next_birthday_on || "-"}</td>
                <td>${item.days_left}</td>
              </tr>
            `
          )
          .join("");
      };

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        try {
          await loadBirthdays();
          status.textContent = "Birthday list updated.";
        } catch (error) {
          status.textContent = error.message;
        }
      });

      await loadBirthdays();
    });
  };

  const parseJsonTextarea = (value, fallback = []) => {
    const raw = String(value || "").trim();
    if (!raw) {
      return fallback;
    }
    try {
      return JSON.parse(raw);
    } catch (_error) {
      return fallback;
    }
  };

  const initAdminWhatsAppMeta = () => {
    const page = document.querySelector('[data-page="admin-whatsapp-meta"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("metaWhatsAppSettingsForm");
      const status = document.getElementById("metaWhatsAppStatus");
      const accountInfo = document.getElementById("metaWhatsAppAccountInfo");
      const testBtn = document.getElementById("metaWhatsAppTestBtn");
      const syncBtn = document.getElementById("metaWhatsAppSyncBtn");

      const loadSettings = async () => {
        const settingsPayload = await utils.apiGet("/api/admin/settings/whatsapp");
        const settings = settingsPayload.data?.settings || {};
        if (form && settings) {
          Object.keys(settings).forEach((key) => {
            if (form.elements[key]) {
              if (form.elements[key].type === "checkbox") {
                form.elements[key].checked = Number(settings[key]) === 1;
              } else {
                form.elements[key].value = settings[key] || "";
              }
            }
          });
        }

        const overview = await utils.apiGet("/api/admin/whatsapp/logs/overview");
        document.getElementById("waStatusDraft").textContent = overview.data?.draft || 0;
        document.getElementById("waStatusSubmitted").textContent = overview.data?.submitted || 0;
        document.getElementById("waStatusApproved").textContent = overview.data?.approved || 0;
        document.getElementById("waStatusRejected").textContent = overview.data?.rejected || 0;
        document.getElementById("waStatusFailedQueue").textContent = overview.data?.failed_queue || 0;
        document.getElementById("waStatusSent30").textContent = overview.data?.sent_last_30d || 0;
      };

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.is_active = form.elements.is_active.checked ? 1 : 0;
        try {
          await utils.apiPatch("/api/admin/settings/whatsapp", payload);
          status.textContent = "Meta WhatsApp settings saved.";
        } catch (error) {
          status.textContent = error.message;
        }
      });

      testBtn?.addEventListener("click", async () => {
        try {
          const payload = await utils.apiPost("/api/admin/settings/whatsapp/test", {});
          status.textContent = "Connection successful.";
          accountInfo.innerHTML = `<div><strong>Account:</strong> ${JSON.stringify(payload.data?.account || {})}</div><div><strong>Templates Found:</strong> ${payload.data?.template_count || 0}</div>`;
        } catch (error) {
          status.textContent = error.message;
        }
      });

      syncBtn?.addEventListener("click", async () => {
        try {
          const payload = await utils.apiPost("/api/admin/whatsapp/templates/sync", {});
          status.textContent = `Sync complete. Imported ${payload.data?.imported || 0}, updated ${payload.data?.updated || 0}.`;
          await loadSettings();
        } catch (error) {
          status.textContent = error.message;
        }
      });

      await loadSettings();
    });
  };

  const initAdminWhatsAppTemplates = () => {
    const page = document.querySelector('[data-page="admin-whatsapp-templates"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const tableBody = document.querySelector("#waTemplatesTable tbody");
      const tableStatus = document.getElementById("waTemplateTableStatus");
      const form = document.getElementById("waTemplateForm");
      const formStatus = document.getElementById("waTemplateFormStatus");
      const previewPanel = document.getElementById("waPreviewPanel");
      const versionHistory = document.getElementById("waVersionHistory");
      const approvalLog = document.getElementById("waApprovalLog");
      const autoGenerateBtn = document.getElementById("waAutoGenerateBtn");
      const bulkSubmitBtn = document.getElementById("waBulkSubmitBtn");
      const previewBtn = document.getElementById("waPreviewBtn");
      const cloneFixBtn = document.getElementById("waCloneFixBtn");
      const submitBtn = document.getElementById("waSubmitBtn");
      const testSendForm = document.getElementById("waTestSendForm");
      const testSendStatus = document.getElementById("waTestSendStatus");
      const selectAll = document.getElementById("waTemplateSelectAll");

      const fillForm = (template, buttons) => {
        form.elements.id.value = template.id || "";
        form.elements.internal_name.value = template.internal_name || "";
        form.elements.template_key.value = template.template_key || "";
        form.elements.meta_template_name.value = template.meta_template_name || "";
        form.elements.category.value = template.category || "utility";
        form.elements.language_code.value = template.language_code || "en_US";
        form.elements.header_type.value = template.header_type || "none";
        form.elements.header_text.value = template.header_text || "";
        form.elements.body_text.value = template.body_text || "";
        form.elements.footer_text.value = template.footer_text || "";
        form.elements.mapped_event_key.value = template.mapped_event_key || "";
        form.elements.buttons_json.value = JSON.stringify(buttons || [], null, 2);
        form.elements.is_active.checked = Number(template.is_active) === 1;
      };

      const loadTemplates = async () => {
        const payload = await utils.apiGet("/api/admin/whatsapp/templates");
        const items = payload.data?.items || [];
        if (!items.length) {
          tableBody.innerHTML = '<tr><td colspan="6">No WhatsApp templates found.</td></tr>';
          return;
        }
        tableBody.innerHTML = items.map((item) => `
          <tr data-id="${item.id}">
            <td><input type="checkbox" data-template-select="${item.id}" /></td>
            <td>${item.internal_name}</td>
            <td>${item.category}</td>
            <td>${item.approval_status}</td>
            <td>${item.mapped_event_key || "-"}</td>
            <td>
              <button class="btn btn--secondary" type="button" data-action="edit-template">Edit</button>
              <button class="btn btn--secondary" type="button" data-action="preview-template">Preview</button>
              <button class="btn btn--secondary" type="button" data-action="submit-template">Submit</button>
            </td>
          </tr>
        `).join("");
      };

      const loadTemplateDetail = async (id) => {
        const payload = await utils.apiGet(`/api/admin/whatsapp/templates/${id}`);
        const data = payload.data || {};
        fillForm(data.template || {}, data.buttons || []);
        versionHistory.innerHTML = (data.versions || []).map((item) => `<div><strong>v${item.version_number}</strong> ${item.change_note || ""}<br /><small>${item.created_at}</small></div>`).join("") || '<p class="text-muted">No versions yet.</p>';
        approvalLog.innerHTML = (data.approval_logs || []).map((item) => `<div><strong>${item.new_status}</strong> ${item.meta_reason || ""}<br /><small>${item.created_at}</small></div>`).join("") || '<p class="text-muted">No approval logs yet.</p>';
      };

      tableBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        const row = target.closest("tr");
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }
        const id = Number(row.dataset.id || 0);
        if (!id) {
          return;
        }
        try {
          if (target.dataset.action === "edit-template") {
            await loadTemplateDetail(id);
          }
          if (target.dataset.action === "preview-template") {
            const payload = await utils.apiPost(`/api/admin/whatsapp/templates/${id}/preview`, {});
            const preview = payload.data?.preview || {};
            previewPanel.innerHTML = `<div><strong>Header:</strong> ${preview.header || "-"}</div><div><strong>Body:</strong> ${preview.body || "-"}</div><div><strong>Footer:</strong> ${preview.footer || "-"}</div><div><strong>Missing Variables:</strong> ${(preview.missing_variables || []).join(", ") || "None"}</div>`;
          }
          if (target.dataset.action === "submit-template") {
            await utils.apiPost(`/api/admin/whatsapp/templates/${id}/submit`, {});
            tableStatus.textContent = "Template submitted to Meta.";
            await loadTemplates();
            await loadTemplateDetail(id);
          }
        } catch (error) {
          tableStatus.textContent = error.message;
        }
      });

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.is_active = form.elements.is_active.checked ? 1 : 0;
        payload.buttons = parseJsonTextarea(form.elements.buttons_json.value, []);
        try {
          if (payload.id) {
            await utils.apiPatch(`/api/admin/whatsapp/templates/${payload.id}`, payload);
            formStatus.textContent = "Template updated.";
          } else {
            const response = await utils.apiPost("/api/admin/whatsapp/templates", payload);
            formStatus.textContent = "Template created.";
            form.elements.id.value = response.data?.id || "";
          }
          await loadTemplates();
          if (form.elements.id.value) {
            await loadTemplateDetail(form.elements.id.value);
          }
        } catch (error) {
          formStatus.textContent = error.message;
        }
      });

      previewBtn?.addEventListener("click", async () => {
        const id = Number(form.elements.id.value || 0);
        if (!id) {
          formStatus.textContent = "Save the template before previewing.";
          return;
        }
        try {
          const payload = await utils.apiPost(`/api/admin/whatsapp/templates/${id}/preview`, {});
          const preview = payload.data?.preview || {};
          previewPanel.innerHTML = `<div><strong>Header:</strong> ${preview.header || "-"}</div><div><strong>Body:</strong> ${preview.body || "-"}</div><div><strong>Footer:</strong> ${preview.footer || "-"}</div><div><strong>Buttons:</strong> ${(preview.buttons || []).map((button) => button.text).join(", ") || "None"}</div><div><strong>Missing Variables:</strong> ${(preview.missing_variables || []).join(", ") || "None"}</div>`;
        } catch (error) {
          formStatus.textContent = error.message;
        }
      });

      cloneFixBtn?.addEventListener("click", async () => {
        const id = Number(form.elements.id.value || 0);
        if (!id) {
          formStatus.textContent = "Select a saved template first.";
          return;
        }
        try {
          const payload = await utils.apiPost(`/api/admin/whatsapp/templates/${id}/clone-fix`, {});
          formStatus.textContent = `Template cloned as #${payload.data?.id}`;
          await loadTemplates();
        } catch (error) {
          formStatus.textContent = error.message;
        }
      });

      submitBtn?.addEventListener("click", async () => {
        const id = Number(form.elements.id.value || 0);
        if (!id) {
          formStatus.textContent = "Save the template first.";
          return;
        }
        try {
          await utils.apiPost(`/api/admin/whatsapp/templates/${id}/submit`, {});
          formStatus.textContent = "Template submitted for approval.";
          await loadTemplates();
          await loadTemplateDetail(id);
        } catch (error) {
          formStatus.textContent = error.message;
        }
      });

      autoGenerateBtn?.addEventListener("click", async () => {
        try {
          const payload = await utils.apiPost("/api/admin/whatsapp/templates/auto-generate", {});
          tableStatus.textContent = `Generated: ${(payload.data?.created || []).join(", ") || "No new drafts"}`;
          await loadTemplates();
        } catch (error) {
          tableStatus.textContent = error.message;
        }
      });

      bulkSubmitBtn?.addEventListener("click", async () => {
        const ids = Array.from(document.querySelectorAll("[data-template-select]:checked")).map((node) => Number(node.getAttribute("data-template-select") || 0)).filter(Boolean);
        if (!ids.length) {
          tableStatus.textContent = "Select at least one template.";
          return;
        }
        try {
          const payload = await utils.apiPost("/api/admin/whatsapp/templates/bulk-submit", { template_ids: ids });
          tableStatus.textContent = `Bulk submit completed for ${payload.data?.items?.length || 0} templates.`;
          await loadTemplates();
        } catch (error) {
          tableStatus.textContent = error.message;
        }
      });

      selectAll?.addEventListener("change", () => {
        document.querySelectorAll("[data-template-select]").forEach((checkbox) => {
          checkbox.checked = selectAll.checked;
        });
      });

      testSendForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(testSendForm).entries());
        const context = parseJsonTextarea(payload.context_json, {});
        try {
          await utils.apiPost(`/api/admin/whatsapp/templates/${payload.template_id}/test-send`, {
            recipient: payload.recipient,
            context,
          });
          testSendStatus.textContent = "Test send completed.";
        } catch (error) {
          testSendStatus.textContent = error.message;
        }
      });

      await loadTemplates();
    });
  };

  const initAdminWhatsAppMappings = () => {
    const page = document.querySelector('[data-page="admin-whatsapp-mappings"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const tbody = document.querySelector("#waMappingsTable tbody");
      const status = document.getElementById("waMappingStatus");

      const loadMappings = async () => {
        const payload = await utils.apiGet("/api/admin/whatsapp/mappings");
        const items = payload.data?.items || [];
        const approvedTemplates = payload.data?.approved_templates || [];
        tbody.innerHTML = items.map((item) => `
          <tr data-event-key="${item.event_key}">
            <td>${item.event_key}</td>
            <td>${item.internal_name || item.meta_template_name || "-"}</td>
            <td>
              <select data-field="template_id">
                <option value="">Select template</option>
                ${approvedTemplates.map((option) => `<option value="${option.id}" ${Number(option.id) === Number(item.template_id) ? "selected" : ""}>${option.internal_name}</option>`).join("")}
              </select>
            </td>
            <td><input type="checkbox" data-field="is_active" ${Number(item.is_active) === 1 ? "checked" : ""} /></td>
            <td><button class="btn btn--secondary" type="button" data-action="save-mapping">Save</button></td>
          </tr>
        `).join("") || '<tr><td colspan="5">No mappings found.</td></tr>';
      };

      tbody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "save-mapping") {
          return;
        }
        const row = target.closest("tr");
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }
        const eventKey = row.dataset.eventKey;
        const templateId = Number(row.querySelector('[data-field="template_id"]')?.value || 0);
        const isActive = row.querySelector('[data-field="is_active"]')?.checked ? 1 : 0;
        try {
          await utils.apiPatch(`/api/admin/whatsapp/mappings/${encodeURIComponent(eventKey)}`, { template_id: templateId, is_active: isActive });
          status.textContent = `Mapping saved for ${eventKey}.`;
          await loadMappings();
        } catch (error) {
          status.textContent = error.message;
        }
      });

      await loadMappings();
    });
  };

  const initAdminWhatsAppLogs = () => {
    const page = document.querySelector('[data-page="admin-whatsapp-logs"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const renderList = (id, items, formatter) => {
        const node = document.getElementById(id);
        node.innerHTML = items.length ? items.map(formatter).join("") : '<p class="text-muted">No records found.</p>';
      };
      const usageBody = document.querySelector("#waUsageReportTable tbody");

      const [syncLogs, approvalLogs, sendLogs, failedQueue, usage] = await Promise.all([
        utils.apiGet("/api/admin/whatsapp/logs/sync"),
        utils.apiGet("/api/admin/whatsapp/logs/approval"),
        utils.apiGet("/api/admin/whatsapp/logs/send"),
        utils.apiGet("/api/admin/whatsapp/logs/failed-queue"),
        utils.apiGet("/api/admin/whatsapp/logs/usage-report"),
      ]);

      renderList("waSyncLogs", syncLogs.data?.items || [], (item) => `<div><strong>${item.sync_direction}</strong> ${item.status}<br /><small>${item.message || ""} | ${item.created_at}</small></div>`);
      renderList("waApprovalLogs", approvalLogs.data?.items || [], (item) => `<div><strong>${item.internal_name}</strong>: ${item.previous_status || "-"} -> ${item.new_status}<br /><small>${item.meta_reason || "No reason provided"}</small></div>`);
      renderList("waSendLogs", sendLogs.data?.items || [], (item) => `<div><strong>${item.recipient}</strong> ${item.status}<br /><small>${item.provider_message_id || "-"} | ${item.created_at}</small></div>`);
      renderList("waFailedQueue", failedQueue.data?.items || [], (item) => `<div><strong>#${item.id}</strong> attempts: ${item.attempts}<br /><small>${item.last_error || "-"}</small></div>`);
      usageBody.innerHTML = (usage.data?.items || []).map((item) => `<tr><td>${item.template_name}</td><td>${item.send_count}</td><td>${item.sent_count}</td><td>${item.failed_count}</td></tr>`).join("") || '<tr><td colspan="4">No usage data available.</td></tr>';
    });
  };

  const initAdminCustomers = () => {
    const page = document.querySelector('[data-page="admin-customers"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const tbody = document.querySelector("#adminCustomersTable tbody");
      const payload = await utils.apiGet("/api/admin/customers");
      const items = payload.data?.items || [];
      tbody.innerHTML = items.map((item) => `<tr><td>${item.full_name}</td><td>${item.email}</td><td>${item.phone}</td><td>${item.tags || "-"}</td><td>${item.date_of_birth || "-"}</td><td>${item.order_count}</td><td>${item.invoice_count}</td></tr>`).join("") || '<tr><td colspan="7">No customers found.</td></tr>';
    });
  };

  const initAdminB2bAccounts = () => {
    const page = document.querySelector('[data-page="admin-b2b-accounts"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const tbody = document.querySelector("#adminB2bAccountsTable tbody");
      const status = document.getElementById("adminB2bAccountsStatus");
      const load = async () => {
        const payload = await utils.apiGet("/api/admin/b2b/accounts");
        const items = payload.data?.items || [];
        tbody.innerHTML = items.map((item) => `<tr data-id="${item.id}"><td>${item.company_name}</td><td>${item.owner_name}</td><td><select data-field="account_type"><option value="corporate_client" ${item.account_type === "corporate_client" ? "selected" : ""}>corporate_client</option><option value="business_buyer" ${item.account_type === "business_buyer" ? "selected" : ""}>business_buyer</option><option value="reseller" ${item.account_type === "reseller" ? "selected" : ""}>reseller</option><option value="cake_shop_owner" ${item.account_type === "cake_shop_owner" ? "selected" : ""}>cake_shop_owner</option></select></td><td><select data-field="approval_status"><option value="pending" ${item.approval_status === "pending" ? "selected" : ""}>pending</option><option value="approved" ${item.approval_status === "approved" ? "selected" : ""}>approved</option><option value="rejected" ${item.approval_status === "rejected" ? "selected" : ""}>rejected</option><option value="suspended" ${item.approval_status === "suspended" ? "selected" : ""}>suspended</option></select></td><td><input type="number" step="0.01" data-field="credit_limit" value="${item.credit_limit || ""}" /></td><td>${item.quote_count}</td><td>${item.order_count}</td><td><button class="btn btn--secondary" type="button" data-action="save-account">Save</button></td></tr>`).join("") || '<tr><td colspan="8">No B2B accounts found.</td></tr>';
      };
      tbody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "save-account") {
          return;
        }
        const row = target.closest("tr");
        const id = Number(row?.dataset.id || 0);
        try {
          await utils.apiPatch(`/api/admin/b2b/accounts/${id}`, {
            approval_status: row.querySelector('[data-field="approval_status"]')?.value,
            account_type: row.querySelector('[data-field="account_type"]')?.value,
            credit_limit: row.querySelector('[data-field="credit_limit"]')?.value,
          });
          status.textContent = "B2B account updated.";
          await load();
        } catch (error) {
          status.textContent = error.message;
        }
      });
      await load();
    });
  };

  const initAdminB2bQuotes = () => {
    const page = document.querySelector('[data-page="admin-b2b-quotes"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const tbody = document.querySelector("#adminB2bQuotesTable tbody");
      const status = document.getElementById("adminB2bQuotesStatus");
      const load = async () => {
        const payload = await utils.apiGet("/api/admin/b2b/quotes");
        const items = payload.data?.items || [];
        tbody.innerHTML = items.map((item) => `<tr data-id="${item.id}"><td>${item.quote_number}</td><td>${item.company_name}</td><td>${item.event_type || "-"}</td><td>${item.fulfilment_mode}</td><td><select data-field="status"><option value="requested" ${item.status === "requested" ? "selected" : ""}>requested</option><option value="drafted" ${item.status === "drafted" ? "selected" : ""}>drafted</option><option value="sent" ${item.status === "sent" ? "selected" : ""}>sent</option><option value="accepted" ${item.status === "accepted" ? "selected" : ""}>accepted</option><option value="rejected" ${item.status === "rejected" ? "selected" : ""}>rejected</option><option value="converted_to_order" ${item.status === "converted_to_order" ? "selected" : ""}>converted_to_order</option></select></td><td>${utils.formatInr(item.grand_total)}</td><td><button class="btn btn--secondary" type="button" data-action="save-quote">Save</button><button class="btn btn--secondary" type="button" data-action="convert-quote">Convert</button></td></tr>`).join("") || '<tr><td colspan="7">No B2B quotes found.</td></tr>';
      };
      tbody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        const row = target.closest("tr");
        const id = Number(row?.dataset.id || 0);
        try {
          if (target.dataset.action === "save-quote") {
            await utils.apiPatch(`/api/admin/b2b/quotes/${id}`, { status: row.querySelector('[data-field="status"]')?.value });
            status.textContent = "Quote updated.";
          }
          if (target.dataset.action === "convert-quote") {
            const response = await utils.apiPost(`/api/admin/b2b/quotes/${id}/convert`, {});
            status.textContent = `Quote converted to ${response.data?.order_number}.`;
          }
          await load();
        } catch (error) {
          status.textContent = error.message;
        }
      });
      await load();
    });
  };

  const initAdminB2bOrders = () => {
    const page = document.querySelector('[data-page="admin-b2b-orders"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const tbody = document.querySelector("#adminB2bOrdersTable tbody");
      const status = document.getElementById("adminB2bOrdersStatus");
      const load = async () => {
        const payload = await utils.apiGet("/api/admin/b2b/orders");
        const items = payload.data?.items || [];
        tbody.innerHTML = items.map((item) => `<tr data-id="${item.id}"><td>${item.order_number}</td><td>${item.company_name}</td><td>${item.fulfilment_mode}</td><td><select data-field="order_status"><option value="pending" ${item.order_status === "pending" ? "selected" : ""}>pending</option><option value="confirmed" ${item.order_status === "confirmed" ? "selected" : ""}>confirmed</option><option value="in_production" ${item.order_status === "in_production" ? "selected" : ""}>in_production</option><option value="ready" ${item.order_status === "ready" ? "selected" : ""}>ready</option><option value="completed" ${item.order_status === "completed" ? "selected" : ""}>completed</option><option value="cancelled" ${item.order_status === "cancelled" ? "selected" : ""}>cancelled</option></select></td><td><select data-field="payment_status"><option value="pending" ${item.payment_status === "pending" ? "selected" : ""}>pending</option><option value="paid" ${item.payment_status === "paid" ? "selected" : ""}>paid</option><option value="part_paid" ${item.payment_status === "part_paid" ? "selected" : ""}>part_paid</option><option value="failed" ${item.payment_status === "failed" ? "selected" : ""}>failed</option></select></td><td>${utils.formatInr(item.grand_total)}</td><td><button class="btn btn--secondary" type="button" data-action="save-b2b-order">Save</button></td></tr>`).join("") || '<tr><td colspan="7">No B2B orders found.</td></tr>';
      };
      tbody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "save-b2b-order") {
          return;
        }
        const row = target.closest("tr");
        const id = Number(row?.dataset.id || 0);
        try {
          await utils.apiPatch(`/api/admin/b2b/orders/${id}`, {
            order_status: row.querySelector('[data-field="order_status"]')?.value,
            payment_status: row.querySelector('[data-field="payment_status"]')?.value,
          });
          status.textContent = "B2B order updated.";
          await load();
        } catch (error) {
          status.textContent = error.message;
        }
      });
      await load();
    });
  };

  const initAdminBanners = () => {
    const page = document.querySelector('[data-page="admin-banners"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const form = document.getElementById("adminBannerForm");
      const status = document.getElementById("adminBannerStatus");
      const tbody = document.querySelector("#adminBannersTable tbody");
      const fillForm = (item) => {
        Object.keys(item).forEach((key) => {
          if (form.elements[key]) {
            if (form.elements[key].type === "checkbox") {
              form.elements[key].checked = Number(item[key]) === 1;
            } else {
              form.elements[key].value = item[key] || "";
            }
          }
        });
      };
      const load = async () => {
        const payload = await utils.apiGet("/api/admin/banners");
        const items = payload.data?.items || [];
        tbody.innerHTML = items.map((item) => `<tr data-id="${item.id}"><td>${item.title}</td><td>${item.placement}</td><td>${Number(item.is_active) === 1 ? "Yes" : "No"}</td><td><button class="btn btn--secondary" type="button" data-action="edit-banner">Edit</button><button class="btn btn--secondary" type="button" data-action="delete-banner">Delete</button></td></tr>`).join("") || '<tr><td colspan="4">No banners found.</td></tr>';
        tbody.dataset.items = JSON.stringify(items);
      };
      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.is_active = form.elements.is_active.checked ? 1 : 0;
        try {
          if (payload.id) {
            await utils.apiPatch(`/api/admin/banners/${payload.id}`, payload);
            status.textContent = "Banner updated.";
          } else {
            await utils.apiPost("/api/admin/banners", payload);
            status.textContent = "Banner created.";
          }
          form.reset();
          await load();
        } catch (error) {
          status.textContent = error.message;
        }
      });
      tbody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        const row = target.closest("tr");
        const id = Number(row?.dataset.id || 0);
        const items = JSON.parse(tbody.dataset.items || "[]");
        const item = items.find((entry) => Number(entry.id) === id);
        if (target.dataset.action === "edit-banner" && item) {
          fillForm(item);
        }
        if (target.dataset.action === "delete-banner") {
          try {
            await utils.apiDelete(`/api/admin/banners/${id}`);
            status.textContent = "Banner deleted.";
            await load();
          } catch (error) {
            status.textContent = error.message;
          }
        }
      });
      await load();
    });
  };

  const initAdminContent = () => {
    const page = document.querySelector('[data-page="admin-content"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const form = document.getElementById("adminPageForm");
      const status = document.getElementById("adminPageStatus");
      const tbody = document.querySelector("#adminPagesTable tbody");
      const load = async () => {
        const payload = await utils.apiGet("/api/admin/pages");
        const items = payload.data?.items || [];
        tbody.innerHTML = items.map((item) => `<tr data-id="${item.id}"><td>${item.title}</td><td>${item.slug}</td><td>${Number(item.is_published) === 1 ? "Yes" : "No"}</td><td><button class="btn btn--secondary" type="button" data-action="edit-page">Edit</button></td></tr>`).join("") || '<tr><td colspan="4">No pages found.</td></tr>';
        tbody.dataset.items = JSON.stringify(items);
      };
      tbody?.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== "edit-page") {
          return;
        }
        const row = target.closest("tr");
        const id = Number(row?.dataset.id || 0);
        const items = JSON.parse(tbody.dataset.items || "[]");
        const item = items.find((entry) => Number(entry.id) === id);
        if (item) {
          form.elements.id.value = item.id;
          form.elements.title.value = item.title || "";
          form.elements.seo_title.value = item.seo_title || "";
          form.elements.seo_description.value = item.seo_description || "";
          form.elements.is_published.checked = Number(item.is_published) === 1;
        }
      });
      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.is_published = form.elements.is_published.checked ? 1 : 0;
        try {
          await utils.apiPatch(`/api/admin/pages/${payload.id}`, payload);
          status.textContent = "Page updated.";
          await load();
        } catch (error) {
          status.textContent = error.message;
        }
      });
      await load();
    });
  };

  const initAdminEvents = () => {
    const page = document.querySelector('[data-page="admin-events"]');
    if (!page) {
      return;
    }

    void withAdminGuard(async () => {
      const form = document.getElementById("adminEventForm");
      const status = document.getElementById("adminEventStatus");
      const tableBody = document.querySelector("#adminEventsTable tbody");
      const search = document.getElementById("adminEventSearch");
      const resetBtn = document.getElementById("adminEventResetBtn");
      let editingId = 0;

      const toDatetimeLocal = (raw) => {
        if (!raw) {
          return "";
        }
        const date = new Date(raw);
        if (Number.isNaN(date.getTime())) {
          return "";
        }
        const pad = (value) => String(value).padStart(2, "0");
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
      };

      const clearForm = () => {
        form.reset();
        editingId = 0;
        form.elements.id.value = "";
        form.elements.is_published.checked = true;
        form.elements.event_type.value = "event";
        form.elements.event_status.value = "scheduled";
        form.elements.capacity.value = "30";
        form.elements.seats_available.value = "30";
      };

      const setFormFromItem = (item) => {
        editingId = Number(item.id || 0);
        form.elements.id.value = item.id || "";
        form.elements.title.value = item.title || "";
        form.elements.slug.value = item.slug || "";
        form.elements.short_description.value = item.short_description || "";
        form.elements.full_description.value = item.full_description || item.short_description || "";
        form.elements.event_type.value = item.event_type || "event";
        form.elements.event_status.value = item.event_status || "scheduled";
        form.elements.event_category.value = item.event_category || "";
        form.elements.instructor_name.value = item.instructor_name || "";
        form.elements.starts_at.value = toDatetimeLocal(item.starts_at);
        form.elements.ends_at.value = toDatetimeLocal(item.ends_at);
        form.elements.location_text.value = item.location_text || "";
        form.elements.online_link.value = item.online_link || "";
        form.elements.capacity.value = item.capacity || 30;
        form.elements.seats_available.value = item.seats_available || 0;
        form.elements.banner_image.value = item.banner_image || "";
        form.elements.registration_cta_label.value = item.registration_cta_label || "";
        form.elements.is_published.checked = Number(item.is_published) === 1;
      };

      const loadEvents = async () => {
        const q = search?.value?.trim() || "";
        const payload = await utils.apiGet(`/api/admin/events?limit=120&q=${encodeURIComponent(q)}`);
        const items = payload.data?.items || [];

        if (!items.length) {
          tableBody.innerHTML = '<tr><td colspan="6">No events found.</td></tr>';
          tableBody.dataset.cache = "[]";
          return;
        }

        tableBody.innerHTML = items
          .map((item) => `
            <tr>
              <td>${item.title}</td>
              <td>${item.event_type}</td>
              <td>${item.event_status}</td>
              <td>${item.starts_at || "-"}</td>
              <td>${item.seats_available || 0}/${item.capacity || 0}</td>
              <td>
                <button class="btn btn--secondary" type="button" data-action="edit" data-id="${item.id}">Edit</button>
                <button class="btn btn--secondary" type="button" data-action="archive" data-id="${item.id}">Archive</button>
              </td>
            </tr>
          `)
          .join("");

        tableBody.dataset.cache = JSON.stringify(items);
      };

      tableBody?.addEventListener("click", async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const action = target.dataset.action;
        const id = Number(target.dataset.id || 0);
        if (!action || id <= 0) {
          return;
        }

        if (action === "edit") {
          const payload = await utils.apiGet(`/api/admin/events?limit=120`);
          const item = (payload.data?.items || []).find((entry) => Number(entry.id) === id);
          if (item) {
            setFormFromItem(item);
            status.textContent = "Editing selected event.";
          }
          return;
        }

        if (action === "archive") {
          if (!window.confirm("Archive this event?")) {
            return;
          }
          try {
            await utils.apiDelete(`/api/admin/events/${id}`);
            status.textContent = "Event archived.";
            if (editingId === id) {
              clearForm();
            }
            await loadEvents();
          } catch (error) {
            status.textContent = error.message;
          }
        }
      });

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.slug = payload.slug || toSlug(payload.title);
        payload.is_published = form.elements.is_published.checked ? 1 : 0;

        try {
          if (editingId > 0) {
            await utils.apiPatch(`/api/admin/events/${editingId}`, payload);
            status.textContent = "Event updated.";
          } else {
            await utils.apiPost("/api/admin/events", payload);
            status.textContent = "Event created.";
          }
          clearForm();
          await loadEvents();
        } catch (error) {
          status.textContent = error.message;
        }
      });

      resetBtn?.addEventListener("click", () => {
        clearForm();
        status.textContent = "Form reset.";
      });

      search?.addEventListener("input", () => {
        void loadEvents();
      });

      form?.elements?.title?.addEventListener("blur", () => {
        if (!form.elements.slug.value.trim()) {
          form.elements.slug.value = toSlug(form.elements.title.value);
        }
      });

      await loadEvents();
    });
  };

  const initAdminReports = () => {
    const page = document.querySelector('[data-page="admin-reports"]');
    if (!page) {
      return;
    }
    void withAdminGuard(async () => {
      const payload = await utils.apiGet("/api/admin/reports/summary");
      document.getElementById("reportRetailOrders").textContent = payload.data?.retail_orders || 0;
      document.getElementById("reportB2bOrders").textContent = payload.data?.b2b_orders || 0;
      document.getElementById("reportPendingInvoices").textContent = payload.data?.pending_invoices || 0;
      document.getElementById("reportQueuedComms").textContent = payload.data?.queued_communications || 0;
      document.getElementById("reportFailedComms").textContent = payload.data?.failed_communications || 0;
      document.getElementById("reportApprovedWa").textContent = payload.data?.whatsapp_approved_templates || 0;
    });
  };

  initAdminLogout();
  initAdminLogin();
  initAdminDashboard();
  initAdminProducts();
  initAdminCategories();
  initAdminCourses();
  initAdminEvents();
  initAdminOrders();
  initAdminFinanceDashboard();
  initAdminInvoices();
  initAdminCommunications();
  initAdminWhatsAppMeta();
  initAdminWhatsAppTemplates();
  initAdminWhatsAppMappings();
  initAdminWhatsAppLogs();
  initAdminAutomation();
  initAdminBirthdays();
  initAdminCustomers();
  initAdminB2bAccounts();
  initAdminB2bQuotes();
  initAdminB2bOrders();
  initAdminBanners();
  initAdminContent();
  initAdminReports();
  initBulkImport();
  initMediaManager();
});
