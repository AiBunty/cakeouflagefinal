const BASE_URL = "";
window.CakeouflageUtils = {
  productPlaceholder: "/assets/defaults/default-product-image.webp",
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
  safeImage(url, fallback = "") {
    const value = String(url || "").trim();
    if (!value) {
      return fallback || this.productPlaceholder;
    }
    return value;
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
