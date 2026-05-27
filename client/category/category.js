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
  var searchClearMobile = document.getElementById('shopSearchClearMobile');
  var searchClearDesktop = document.getElementById('shopSearchClearDesktop');
  var searchStatus = document.getElementById('categorySearchStatus');
  var maxPriceInput = document.getElementById('maxPriceInput');
  var maxPriceRange = document.getElementById('maxPriceRange');
  var filterForm = document.getElementById('filterForm');
  var skeleton = document.getElementById('luxCategorySkeleton');
  var quickChips = document.getElementById('shopQuickChips');
  var mobileCartCount = document.getElementById('mobileShopCartCount');
  var mobileCartCountAlt = page.querySelector('.lux-mobile-cart-count-alt');
  var showToast = function (message, type) {
    if (window.CakeouflageUtils && typeof window.CakeouflageUtils.showToast === 'function') {
      window.CakeouflageUtils.showToast(message, { type: type || 'info' });
    }
  };

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
    toggleSearchClearButtons(value);
  }

  function toggleSearchClearButtons(value) {
    var hasValue = String(value || '').trim() !== '';
    if (searchClearMobile) {
      searchClearMobile.hidden = !hasValue;
    }
    if (searchClearDesktop) {
      searchClearDesktop.hidden = !hasValue;
    }
  }

  function setSearchStatus(message) {
    if (!searchStatus) {
      return;
    }
    if (!message) {
      searchStatus.hidden = true;
      searchStatus.textContent = '';
      return;
    }
    searchStatus.hidden = false;
    searchStatus.textContent = message;
  }

  function getSearchForm(originInput) {
    if (originInput && originInput.form) {
      return originInput.form;
    }
    if (desktopSearchInput && desktopSearchInput.form) {
      return desktopSearchInput.form;
    }
    if (searchInput && searchInput.form) {
      return searchInput.form;
    }
    return null;
  }

  function buildSearchUrl(originInput) {
    var sourceInput = originInput || searchInput || desktopSearchInput;
    var query = String((sourceInput && sourceInput.value) || '').trim();
    var form = getSearchForm(originInput);
    var params = new URLSearchParams();

    if (form && window.FormData) {
      var formData = new window.FormData(form);
      formData.forEach(function (value, key) {
        var normalizedKey = String(key || '');
        var normalizedValue = String(value || '').trim();
        if (!normalizedKey || normalizedKey === 'page' || normalizedKey === 'q' || normalizedValue === '') {
          return;
        }
        params.append(normalizedKey, normalizedValue);
      });
    }

    if (query !== '') {
      params.set('q', query);
    }

    var queryString = params.toString();
    return queryString ? ('/search?' + queryString) : '/search';
  }

  function navigateToSearch(originInput) {
    var targetUrl = buildSearchUrl(originInput);
    saveScrollState();
    setSearchStatus('Opening full results...');
    window.location.href = targetUrl;
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
      navigateToSearch(isMobile() ? searchInput : desktopSearchInput || searchInput);
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      syncSearchInputs(searchInput.value, searchInput);
      setSearchStatus('');
    });
  }

  if (desktopSearchInput) {
    desktopSearchInput.addEventListener('input', function () {
      syncSearchInputs(desktopSearchInput.value, desktopSearchInput);
      setSearchStatus('');
    });
  }

  if (searchClearMobile) {
    searchClearMobile.addEventListener('click', function () {
      syncSearchInputs('', null);
      setSearchStatus('');
      focusSearchField(searchInput || desktopSearchInput);
    });
  }

  if (searchClearDesktop) {
    searchClearDesktop.addEventListener('click', function () {
      syncSearchInputs('', null);
      setSearchStatus('');
      focusSearchField(desktopSearchInput || searchInput);
    });
  }

  var liveSearch = window.CakeouflageLiveSearch;
  if (liveSearch) {
    if (searchInput) {
      liveSearch.attach(searchInput, {
        dropdown: document.getElementById('shopSearchMobileDropdown'),
        searchPage: '/search',
        minChars: 2,
        limit: 8,
        buildSearchUrl: function () {
          return buildSearchUrl(searchInput);
        }
      });
    }

    if (desktopSearchInput) {
      liveSearch.attach(desktopSearchInput, {
        dropdown: document.getElementById('shopSearchDesktopDropdown'),
        searchPage: '/search',
        minChars: 2,
        limit: 8,
        buildSearchUrl: function () {
          return buildSearchUrl(desktopSearchInput);
        }
      });
    }
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
    var clearQuickFilterFlags = function () {
      if (!filterForm) {
        return;
      }
      ['is_bestseller', 'is_chef_special', 'same_day'].forEach(function (name) {
        var flag = filterForm.querySelector('input[name="' + name + '"]');
        if (flag) {
          flag.checked = false;
        }
      });
      ['eggless', 'vegan'].forEach(function (dietaryValue) {
        var dietaryInput = filterForm.querySelector('input[name="dietary[]"][value="' + dietaryValue + '"]');
        if (dietaryInput) {
          dietaryInput.checked = false;
        }
      });
      var vegAll = filterForm.querySelector('input[name="is_veg"][value=""]');
      if (vegAll) {
        vegAll.checked = true;
      }
      var priceBucketSelect = filterForm.querySelector('#priceBucket');
      if (priceBucketSelect) {
        priceBucketSelect.value = '';
      }
      if (maxPriceInput) {
        maxPriceInput.value = '';
      }
      if (maxPriceRange) {
        maxPriceRange.value = '5000';
      }
    };

    var applyQuickFilter = function (filterKey) {
      if (!filterForm) {
        return;
      }

      clearQuickFilterFlags();

      if (filterKey === 'all') {
        return;
      }

      if (filterKey === 'eggless') {
        var eggless = filterForm.querySelector('input[name="dietary[]"][value="eggless"]');
        if (eggless) {
          eggless.checked = true;
        }
        return;
      }

      if (filterKey === 'vegan') {
        var vegan = filterForm.querySelector('input[name="dietary[]"][value="vegan"]');
        if (vegan) {
          vegan.checked = true;
        }
        return;
      }

      if (filterKey === 'chefSpecial') {
        var chefSpecial = filterForm.querySelector('input[name="is_chef_special"]');
        if (chefSpecial) {
          chefSpecial.checked = true;
        }
        return;
      }

      if (filterKey === 'sameDay') {
        var sameDay = filterForm.querySelector('input[name="same_day"]');
        if (sameDay) {
          sameDay.checked = true;
        }
        return;
      }

      if (filterKey === 'bestseller') {
        var bestseller = filterForm.querySelector('input[name="is_bestseller"]');
        if (bestseller) {
          bestseller.checked = true;
        }
        return;
      }

      if (filterKey === 'under1000') {
        var priceBucket = filterForm.querySelector('#priceBucket');
        if (priceBucket) {
          priceBucket.value = '500_1000';
        }
      }
    };

    quickChips.addEventListener('click', function (event) {
      var chip = event.target.closest('[data-quick-filter]');
      if (!chip) {
        return;
      }
      event.preventDefault();
      quickChips.querySelectorAll('[data-quick-filter]').forEach(function (item) {
        item.classList.remove('is-active');
      });
      chip.classList.add('is-active');

      var filterKey = chip.getAttribute('data-quick-filter') || 'all';
      applyQuickFilter(filterKey);
      setSearchStatus('Applying quick filter...');
      saveScrollState();
      if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.submitForm === 'function') {
        window.CakeScrollPreserver.submitForm(filterForm);
      } else {
        filterForm.submit();
      }
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
      setSearchStatus('Applying filters...');
      if (isMobile()) {
        closeDrawer();
      }
    });
  }

  page.querySelectorAll('form[role="search"]').forEach(function (form) {
    form.addEventListener('submit', function () {
      setSearchStatus('Searching cakes...');
    });
  });

  toggleSearchClearButtons(String((searchInput && searchInput.value) || (desktopSearchInput && desktopSearchInput.value) || '').trim());

  if (mobileCartCount && mobileCartCountAlt) {
    var syncCount = function () {
      mobileCartCountAlt.textContent = mobileCartCount.textContent || '0';
    };
    syncCount();
    var countObserver = new MutationObserver(syncCount);
    countObserver.observe(mobileCartCount, { childList: true, subtree: true, characterData: true });
  }
})();
