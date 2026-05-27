const BASE_URL = "";
window.CakeouflageUtils = {
  productPlaceholder: "/public/assets/defaults/default-product-image.webp",
  getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  },
  qs(selector, scope = document) {
    return scope.querySelector(selector);
  },
  qsa(selector, scope = document) {
    return Array.from(scope.querySelectorAll(selector));
  },
  formatInr(value) {
    const amount = Number(value || 0);
    return `Rs ${amount.toFixed(2)}`;
  },
  getValidImage(url, fallback = "") {
    const value = String(url || "").trim();
    const normalized = value.toLowerCase();
    const safeFallback = String(fallback || this.productPlaceholder || "").trim() || this.productPlaceholder;

    if (!value || value === "#" || normalized.startsWith("javascript:")) {
      return safeFallback;
    }

    const staleDefaults = [
      "/public/assets/defaults/default-product-image.jpg",
      "/public/assets/defaults/default-product-image.png",
      "/public/assets/defaults/default-product-image.webp",
      "/assets/default-product-image.jpg",
      "/assets/default-product-image.png",
      "/assets/default-product-image.webp"
    ];

    if (staleDefaults.includes(normalized)) {
      return safeFallback;
    }

    return value;
  },
  safeImage(url, fallback = "") {
    return this.getValidImage(url, fallback);
  },
  applyImageFallback(img, fallback = "") {
    if (!(img instanceof HTMLImageElement)) {
      return;
    }
    const fallbackSrc = this.getValidImage(fallback || img.getAttribute("data-fallback-src") || "", this.productPlaceholder);
    if (!img.src || img.src.trim() === "") {
      img.src = fallbackSrc;
    }
    img.addEventListener("error", () => {
      if (img.dataset.fallbackApplied === "1") {
        return;
      }
      img.dataset.fallbackApplied = "1";
      img.src = fallbackSrc;
    });
  },
  bindImageFallbacks(scope = document) {
    const root = scope && typeof scope.querySelectorAll === "function" ? scope : document;
    root.querySelectorAll("img").forEach((img) => {
      if (img.dataset.fallbackBound === "1") {
        return;
      }
      img.dataset.fallbackBound = "1";
      this.applyImageFallback(img, img.getAttribute("data-fallback-src") || "");
    });
  },
  showToast(message, options = {}) {
    const text = String(message || "").trim();
    if (!text) {
      return;
    }
    const type = String(options.type || "info").toLowerCase();
    const timeoutMs = Number(options.timeout || 1800);

    let container = document.getElementById("cakeToastContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "cakeToastContainer";
      container.style.cssText = "position:fixed;right:16px;bottom:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:320px;";
      document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    const color = type === "error" ? "#8f1f1f" : type === "success" ? "#1e7a3b" : "#7a1d3f";
    toast.style.cssText = "padding:10px 12px;border-radius:10px;color:#fff;font-size:13px;line-height:1.4;box-shadow:0 10px 24px rgba(0,0,0,.2);background:" + color + ";";
    toast.textContent = text;
    container.appendChild(toast);

    window.setTimeout(() => {
      toast.remove();
      if (container && container.childElementCount === 0) {
        container.remove();
      }
    }, Math.max(900, timeoutMs));
  },
  async apiRequest(url, options = {}) {
    const timeoutMs = Number(options.timeout || 12000);
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const csrfToken = this.getCsrfToken();
    const method = String(options.method || "GET").toUpperCase();
    let response;

    try {
      response = await fetch(BASE_URL + url, {
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          ...(method !== "GET" && csrfToken ? { "X-CSRF-Token": csrfToken } : {}),
          ...(options.headers || {})
        },
        ...options,
        signal: controller.signal
      });
    } catch (error) {
      if (error && error.name === "AbortError") {
        throw new Error("Request timed out. Please try again.");
      }
      throw error;
    } finally {
      clearTimeout(timer);
    }

    let payload = null;
    try {
      payload = await response.json();
    } catch (error) {
      payload = null;
    }

    if (!response.ok || !payload || payload.success === false) {
      const message = payload?.message || `Request failed with status ${response.status}`;
      throw new Error(message);
    }

    return payload;
  },
  apiGet(url) {
    return this.apiRequest(url, { method: "GET", timeout: 10000 });
  },
  apiPost(url, body = {}) {
    return this.apiRequest(url, {
      method: "POST",
      body: JSON.stringify(body)
    });
  },
  apiPatch(url, body = {}) {
    return this.apiRequest(url, {
      method: "PATCH",
      body: JSON.stringify(body)
    });
  },
  apiDelete(url) {
    return this.apiRequest(url, { method: "DELETE" });
  },
  appendCsrfToFormData(formData) {
    const csrfToken = this.getCsrfToken();
    if (csrfToken && !formData.has("_csrf")) {
      formData.append("_csrf", csrfToken);
    }
    return formData;
  }
};

document.addEventListener("DOMContentLoaded", () => {
  window.CakeouflageUtils?.bindImageFallbacks(document);
});
