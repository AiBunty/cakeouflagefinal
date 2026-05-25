(function () {
  'use strict';

  var STORAGE_KEY = 'cakeouflage:scroll-restore';

  function safeSessionStorage() {
    try {
      return window.sessionStorage;
    } catch (error) {
      return null;
    }
  }

  function currentPathname() {
    return window.location.pathname || '/';
  }

  function isCategoryPath(pathname) {
    return /^\/category(?:\/|$)/.test(String(pathname || ''));
  }

  function saveState() {
    var storage = safeSessionStorage();
    if (!storage) {
      return;
    }

    var payload = {
      path: currentPathname(),
      x: window.scrollX || window.pageXOffset || 0,
      y: window.scrollY || window.pageYOffset || 0,
      at: Date.now()
    };

    storage.setItem(STORAGE_KEY, JSON.stringify(payload));
  }

  function restoreState() {
    var storage = safeSessionStorage();
    if (!storage) {
      return false;
    }

    var raw = storage.getItem(STORAGE_KEY);
    if (!raw) {
      return false;
    }

    var payload;
    try {
      payload = JSON.parse(raw);
    } catch (error) {
      storage.removeItem(STORAGE_KEY);
      return false;
    }

    var currentPath = currentPathname();
    var samePath = !!payload && payload.path === currentPath;
    var sameCategoryScope = !!payload && isCategoryPath(payload.path) && isCategoryPath(currentPath);

    if (!samePath && !sameCategoryScope) {
      return false;
    }

    storage.removeItem(STORAGE_KEY);
    window.scrollTo(payload.x || 0, payload.y || 0);
    return true;
  }

  function submitForm(form) {
    if (!form || typeof form.submit !== 'function') {
      return false;
    }
    saveState();
    form.submit();
    return true;
  }

  function reload() {
    saveState();
    window.location.reload();
  }

  function navigate(url) {
    if (!url) {
      return false;
    }
    saveState();
    window.location.assign(url);
    return true;
  }

  window.CakeScrollPreserver = {
    saveState: saveState,
    restoreState: restoreState,
    submitForm: submitForm,
    reload: reload,
    navigate: navigate
  };

  document.addEventListener('DOMContentLoaded', function () {
    restoreState();
  }, { once: true });

  window.addEventListener('load', restoreState, { once: true });
})();
