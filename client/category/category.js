(function () {
  'use strict';

  var page = document.querySelector('main[data-page="category"].lux-category-page');
  if (!page) {
    return;
  }

  var body = document.body;
  var sidebar = document.getElementById('shopSidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  var openButtons = [
    document.getElementById('toggleSidebar'),
    document.getElementById('mobileFilterBtnTop'),
    document.getElementById('mobileFilterBtn')
  ].filter(Boolean);
  var closeButton = document.getElementById('sidebarClose');
  var mobileSortBtn = document.getElementById('mobileSortBtn');
  var mobileSearchBtn = document.getElementById('mobileSearchBtn');
  var sortSelect = document.getElementById('shopSort');
  var searchInput = document.getElementById('shopSearch');
  var desktopSearchInput = document.getElementById('shopSearchDesktop');
  var maxPriceInput = document.getElementById('maxPriceInput');
  var maxPriceRange = document.getElementById('maxPriceRange');
  var filterForm = document.getElementById('filterForm');
  var skeleton = document.getElementById('luxCategorySkeleton');
  var quickChips = document.getElementById('shopQuickChips');
  var mobileCartCount = document.getElementById('mobileShopCartCount');
  var mobileCartCountAlt = page.querySelector('.lux-mobile-cart-count-alt');

  function isMobile() {
    return window.matchMedia('(max-width: 767px)').matches;
  }

  function syncSearchInputs(value, origin) {
    if (searchInput && origin !== searchInput) {
      searchInput.value = value;
    }
    if (desktopSearchInput && origin !== desktopSearchInput) {
      desktopSearchInput.value = value;
    }
  }

  function focusSearchField(target) {
    if (!target) {
      return;
    }
    target.focus();
    var headerOffset = isMobile() ? 110 : 124;
    var rect = target.getBoundingClientRect();
    var top = window.pageYOffset + rect.top - headerOffset;
    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
  }

  function saveScrollState() {
    if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.saveState === 'function') {
      window.CakeScrollPreserver.saveState();
    }
  }

  function shouldCaptureLinkNavigation(anchor, event) {
    if (!anchor || !anchor.href) {
      return false;
    }
    if (event.defaultPrevented || event.button !== 0) {
      return false;
    }
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return false;
    }
    if ((anchor.getAttribute('target') || '').toLowerCase() === '_blank') {
      return false;
    }

    var url;
    try {
      url = new URL(anchor.href, window.location.origin);
    } catch (error) {
      return false;
    }

    if (url.origin !== window.location.origin) {
      return false;
    }

    return /^\/category(?:\/|$)/.test(url.pathname || '');
  }

  function setDrawerState(open) {
    if (!sidebar || !backdrop || !isMobile()) {
      return;
    }

    sidebar.classList.toggle('is-open', open);
    backdrop.hidden = !open;
    backdrop.classList.toggle('is-open', open);
    body.classList.toggle('category-drawer-open', open);
    body.style.overflow = open ? 'hidden' : '';

    openButtons.forEach(function (button) {
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  function closeDrawer() {
    setDrawerState(false);
  }

  function openDrawer() {
    setDrawerState(true);
  }

  openButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      if (sidebar && sidebar.classList.contains('is-open')) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });
  });

  if (closeButton) {
    closeButton.addEventListener('click', closeDrawer);
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeDrawer);
  }

  window.addEventListener('resize', function () {
    if (!isMobile()) {
      closeDrawer();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeDrawer();
    }
  });

  var accordionTriggers = page.querySelectorAll('[data-accordion-trigger]');
  accordionTriggers.forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      var id = trigger.getAttribute('data-accordion-trigger');
      var group = page.querySelector('[data-accordion="' + id + '"]');
      if (!group) {
        return;
      }
      group.classList.toggle('is-open');
    });
  });

  page.querySelectorAll('[data-prefill-search]').forEach(function (button) {
    button.addEventListener('click', function () {
      var value = button.getAttribute('data-prefill-search') || '';
      syncSearchInputs(value, null);
      focusSearchField(isMobile() ? searchInput : desktopSearchInput || searchInput);
      if (filterForm) {
        filterForm.submit();
      }
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      syncSearchInputs(searchInput.value, searchInput);
    });
  }

  if (desktopSearchInput) {
    desktopSearchInput.addEventListener('input', function () {
      syncSearchInputs(desktopSearchInput.value, desktopSearchInput);
    });
  }

  if (maxPriceInput && maxPriceRange) {
    maxPriceRange.addEventListener('input', function () {
      maxPriceInput.value = maxPriceRange.value;
    });

    maxPriceInput.addEventListener('input', function () {
      var numeric = parseInt(maxPriceInput.value || '0', 10);
      if (!Number.isFinite(numeric)) {
        return;
      }
      var bounded = Math.min(5000, Math.max(100, numeric));
      maxPriceRange.value = String(bounded);
    });
  }

  if (mobileSortBtn && sortSelect) {
    mobileSortBtn.addEventListener('click', function () {
      sortSelect.focus();
      sortSelect.click();
    });
  }

  if (mobileSearchBtn && searchInput) {
    mobileSearchBtn.addEventListener('click', function () {
      focusSearchField(searchInput);
    });
  }

  if (quickChips) {
    quickChips.addEventListener('click', function (event) {
      var chip = event.target.closest('[data-quick-filter]');
      if (!chip) {
        return;
      }
      quickChips.querySelectorAll('[data-quick-filter]').forEach(function (item) {
        item.classList.remove('is-active');
      });
      chip.classList.add('is-active');
    });
  }

  page.addEventListener('click', function (event) {
    var anchor = event.target.closest('a[href]');
    if (!shouldCaptureLinkNavigation(anchor, event)) {
      return;
    }
    saveScrollState();
  });

  page.addEventListener('submit', function () {
    saveScrollState();
  }, true);

  page.querySelectorAll('[data-wishlist-product]').forEach(function (button) {
    button.addEventListener('click', function () {
      var active = button.classList.toggle('is-active');
      button.textContent = active ? '♥' : '♡';
    });
  });

  page.querySelectorAll('.view-btn').forEach(function (button) {
    button.addEventListener('click', function () {
      var viewMode = button.getAttribute('data-view');
      page.classList.toggle('is-list-view', viewMode === 'list');
      page.querySelectorAll('.view-btn').forEach(function (item) {
        item.classList.toggle('is-active', item === button);
      });
    });
  });

  function hideSkeleton() {
    if (!skeleton) {
      return;
    }
    skeleton.classList.add('is-hidden');
  }

  if (document.readyState === 'complete') {
    hideSkeleton();
  } else {
    window.addEventListener('load', hideSkeleton, { once: true });
    window.setTimeout(hideSkeleton, 700);
  }

  if (filterForm) {
    filterForm.addEventListener('submit', function () {
      saveScrollState();
      if (isMobile()) {
        closeDrawer();
      }
    });
  }

  if (mobileCartCount && mobileCartCountAlt) {
    var syncCount = function () {
      mobileCartCountAlt.textContent = mobileCartCount.textContent || '0';
    };
    syncCount();
    var countObserver = new MutationObserver(syncCount);
    countObserver.observe(mobileCartCount, { childList: true, subtree: true, characterData: true });
  }
})();
