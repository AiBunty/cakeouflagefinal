(function () {
  'use strict';

  var STORAGE_KEY = 'cakeouflage_recent_searches_v1';
  var DEFAULT_MIN_CHARS = 2;
  var DEFAULT_DEBOUNCE_MS = 280;
  var DEFAULT_LIMIT = 8;

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[char] || char;
    });
  }

  function escapeRegExp(value) {
    return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function highlightMatch(text, query) {
    var safeText = escapeHtml(text || '');
    var normalizedQuery = String(query || '').trim();
    if (normalizedQuery.length < 2) {
      return safeText;
    }

    try {
      var matcher = new RegExp('(' + escapeRegExp(normalizedQuery) + ')', 'ig');
      return safeText.replace(matcher, '<mark class="search-dropdown__mark">$1</mark>');
    } catch (error) {
      return safeText;
    }
  }

  function parseRecentSearches() {
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      var parsed = JSON.parse(raw || '[]');
      if (!Array.isArray(parsed)) {
        return [];
      }
      return parsed
        .map(function (item) { return String(item || '').trim(); })
        .filter(function (item) { return item.length > 0; })
        .slice(0, 8);
    } catch (error) {
      return [];
    }
  }

  function saveRecentSearches(items) {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(0, 8)));
    } catch (error) {
      // ignore storage errors
    }
  }

  function addRecentSearch(query) {
    var trimmed = String(query || '').trim();
    if (trimmed.length < DEFAULT_MIN_CHARS) {
      return;
    }
    var current = parseRecentSearches().filter(function (item) {
      return item.toLowerCase() !== trimmed.toLowerCase();
    });
    current.unshift(trimmed);
    saveRecentSearches(current);
  }

  function formatInr(value) {
    var num = Number(value || 0);
    return num > 0 ? 'Rs ' + num.toLocaleString('en-IN') : '';
  }

  function normalizeSearchUrl(url) {
    if (!url) {
      return '/search';
    }
    return String(url);
  }

  function buildSearchUrl(query, options) {
    var q = String(query || '').trim();
    var base = normalizeSearchUrl(options.searchPage);

    if (typeof options.buildSearchUrl === 'function') {
      var customUrl = options.buildSearchUrl(q);
      if (customUrl) {
        return customUrl;
      }
    }

    var params = new URLSearchParams();
    if (q !== '') {
      params.set('q', q);
    }

    if (typeof options.getExtraParams === 'function') {
      var extra = options.getExtraParams() || {};
      if (extra instanceof URLSearchParams) {
        extra.forEach(function (value, key) {
          if (key !== 'page' && key !== 'q' && String(value).trim() !== '') {
            params.set(key, String(value));
          }
        });
      } else {
        Object.keys(extra).forEach(function (key) {
          var value = extra[key];
          if (key !== 'page' && key !== 'q' && value !== null && typeof value !== 'undefined' && String(value).trim() !== '') {
            params.set(key, String(value));
          }
        });
      }
    }

    var queryString = params.toString();
    return queryString ? (base + '?' + queryString) : base;
  }

  function renderLoading(dropdown) {
    dropdown.innerHTML = '<p class="search-dropdown__state search-dropdown__state--loading">Searching products...</p>';
    dropdown.hidden = false;
  }

  function renderError(dropdown, message) {
    dropdown.innerHTML = '<p class="search-dropdown__state search-dropdown__state--error">' + escapeHtml(message) + '</p>';
    dropdown.hidden = false;
  }

  function renderRecent(dropdown, recentItems, query, options) {
    var filtered = recentItems.filter(function (item) {
      return query === '' || item.toLowerCase().indexOf(query.toLowerCase()) !== -1;
    }).slice(0, 5);

    if (!filtered.length) {
      return '';
    }

    var rows = filtered.map(function (item) {
      var url = buildSearchUrl(item, options);
      return '<a class="search-dropdown__item search-dropdown__item--recent" href="' + escapeHtml(url) + '" data-recent-search="1" data-query="' + escapeHtml(item) + '" role="option" tabindex="-1">'
        + '<span class="search-dropdown__thumb search-dropdown__thumb--icon" aria-hidden="true">R</span>'
        + '<span class="search-dropdown__meta">'
        + '<span class="search-dropdown__name">' + highlightMatch(item, query) + '</span>'
        + '<span class="search-dropdown__category">Recent search</span>'
        + '</span>'
        + '</a>';
    }).join('');

    return '<div class="search-dropdown__section">'
      + '<p class="search-dropdown__section-title">Recent searches</p>'
      + rows
      + '</div>';
  }

  function renderResults(dropdown, payload, query, options) {
    var products = Array.isArray(payload && payload.products) ? payload.products : [];
    var categories = Array.isArray(payload && payload.categories) ? payload.categories : [];
    var recent = parseRecentSearches();

    var productRows = products.slice(0, options.limit).map(function (product) {
      var image = String(product.image || '').trim();
      var price = formatInr(product.price || 0);
      var href = String(product.url || '/search?q=' + encodeURIComponent(query));
      return '<a class="search-dropdown__item" href="' + escapeHtml(href) + '" data-query="' + escapeHtml(query) + '" role="option" tabindex="-1">'
        + (image
          ? '<img class="search-dropdown__thumb" src="' + escapeHtml(image) + '" alt="" loading="lazy">'
          : '<span class="search-dropdown__thumb search-dropdown__thumb--icon" aria-hidden="true">P</span>')
        + '<span class="search-dropdown__meta">'
        + '<span class="search-dropdown__name">' + highlightMatch(product.name || '', query) + '</span>'
        + '<span class="search-dropdown__category">Category: ' + escapeHtml(product.category_name || 'Products') + '</span>'
        + '</span>'
        + (price ? '<span class="search-dropdown__price">' + escapeHtml(price) + '</span>' : '')
        + '</a>';
    }).join('');

    var categoryRows = categories.slice(0, 4).map(function (category) {
      var href = String(category.url || '/category');
      return '<a class="search-dropdown__item search-dropdown__item--category" href="' + escapeHtml(href) + '" data-query="' + escapeHtml(query) + '" role="option" tabindex="-1">'
        + '<span class="search-dropdown__thumb search-dropdown__thumb--icon" aria-hidden="true">C</span>'
        + '<span class="search-dropdown__meta">'
        + '<span class="search-dropdown__name">' + highlightMatch(category.name || '', query) + '</span>'
        + '<span class="search-dropdown__category">Browse category</span>'
        + '</span>'
        + '</a>';
    }).join('');

    var recentMarkup = renderRecent(dropdown, recent, query, options);

    if (!productRows && !categoryRows && !recentMarkup) {
      dropdown.innerHTML = '<p class="search-dropdown__state">No results found. Try another keyword.</p>';
      dropdown.hidden = false;
      return;
    }

    var viewAllHref = buildSearchUrl(query, options);
    dropdown.innerHTML = ''
      + (productRows ? ('<div class="search-dropdown__section"><p class="search-dropdown__section-title">Products</p>' + productRows + '</div>') : '')
      + (categoryRows ? ('<div class="search-dropdown__section"><p class="search-dropdown__section-title">Categories</p>' + categoryRows + '</div>') : '')
      + recentMarkup
      + '<a class="search-dropdown__view-all" href="' + escapeHtml(viewAllHref) + '">View all results</a>';
    dropdown.hidden = false;
  }

  function attach(input, config) {
    if (!input) {
      return null;
    }

    var options = Object.assign({
      dropdown: null,
      searchPage: '/search',
      minChars: DEFAULT_MIN_CHARS,
      debounceMs: DEFAULT_DEBOUNCE_MS,
      limit: DEFAULT_LIMIT,
      buildSearchUrl: null,
      getExtraParams: null
    }, config || {});

    var dropdown = options.dropdown;
    if (!dropdown) {
      return null;
    }

    if (input.dataset.liveSearchBound === '1') {
      return null;
    }
    input.dataset.liveSearchBound = '1';

    var debounceTimer = null;
    var activeAbort = null;
    var focusIndex = -1;
    var latestItems = [];

    var close = function () {
      if (activeAbort) {
        activeAbort.abort();
        activeAbort = null;
      }
      dropdown.hidden = true;
      dropdown.innerHTML = '';
      focusIndex = -1;
      latestItems = [];
    };

    var openRecent = function () {
      var recentMarkup = renderRecent(dropdown, parseRecentSearches(), '', options);
      if (!recentMarkup) {
        close();
        return;
      }
      dropdown.innerHTML = recentMarkup;
      dropdown.hidden = false;
      latestItems = Array.prototype.slice.call(dropdown.querySelectorAll('.search-dropdown__item, .search-dropdown__view-all'));
      focusIndex = -1;
    };

    var performSearch = function (query) {
      if (activeAbort) {
        activeAbort.abort();
      }
      activeAbort = new AbortController();

      if (!navigator.onLine) {
        renderError(dropdown, 'No internet connection');
        return;
      }

      renderLoading(dropdown);

      fetch('/api/search?q=' + encodeURIComponent(query) + '&limit=' + encodeURIComponent(String(options.limit)), {
        credentials: 'include',
        signal: activeAbort.signal,
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Search failed');
          }
          return response.json();
        })
        .then(function (payload) {
          if (!payload || payload.success !== true) {
            renderError(dropdown, payload && payload.message ? payload.message : 'Search failed');
            return;
          }
          renderResults(dropdown, payload.data || {}, query, options);
          latestItems = Array.prototype.slice.call(dropdown.querySelectorAll('.search-dropdown__item, .search-dropdown__view-all'));
          focusIndex = -1;
        })
        .catch(function (error) {
          if (error && error.name === 'AbortError') {
            return;
          }
          renderError(dropdown, navigator.onLine ? 'Search is temporarily unavailable' : 'No internet connection');
        });
    };

    var focusItem = function (direction) {
      if (dropdown.hidden || !latestItems.length) {
        return;
      }
      latestItems.forEach(function (item) { item.classList.remove('is-focused'); });
      focusIndex = (focusIndex + direction + latestItems.length) % latestItems.length;
      latestItems[focusIndex].classList.add('is-focused');
      latestItems[focusIndex].focus();
      if (typeof latestItems[focusIndex].scrollIntoView === 'function') {
        latestItems[focusIndex].scrollIntoView({ block: 'nearest' });
      }
    };

    var navigateSearch = function (query) {
      var normalized = String(query || '').trim();
      if (normalized.length < options.minChars) {
        return;
      }
      addRecentSearch(normalized);
      window.location.href = buildSearchUrl(normalized, options);
    };

    input.addEventListener('focus', function () {
      if (String(input.value || '').trim().length < options.minChars) {
        openRecent();
      }
    });

    input.addEventListener('input', function () {
      var query = String(input.value || '').trim();
      if (debounceTimer) {
        window.clearTimeout(debounceTimer);
      }
      if (query.length < options.minChars) {
        openRecent();
        return;
      }
      debounceTimer = window.setTimeout(function () {
        performSearch(query);
      }, options.debounceMs);
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        focusItem(1);
        return;
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        focusItem(-1);
        return;
      }
      if (event.key === 'Escape') {
        close();
        return;
      }
      if (event.key === 'Enter') {
        if (!dropdown.hidden && focusIndex >= 0 && latestItems[focusIndex]) {
          event.preventDefault();
          latestItems[focusIndex].click();
          return;
        }
        event.preventDefault();
        navigateSearch(input.value);
      }
    });

    dropdown.addEventListener('click', function (event) {
      var anchor = event.target.closest('a[href]');
      if (!anchor) {
        return;
      }
      if (anchor.hasAttribute('data-recent-search')) {
        addRecentSearch(anchor.getAttribute('data-query') || '');
      } else {
        addRecentSearch(anchor.getAttribute('data-query') || input.value);
      }
      close();
    });

    document.addEventListener('click', function (event) {
      if (event.target === input || input.contains(event.target) || dropdown.contains(event.target)) {
        return;
      }
      close();
    });

    window.addEventListener('offline', function () {
      if (!dropdown.hidden) {
        renderError(dropdown, 'No internet connection');
      }
    });

    return {
      close: close,
      navigateSearch: navigateSearch
    };
  }

  window.CakeouflageLiveSearch = {
    attach: attach,
    addRecentSearch: addRecentSearch,
    readRecentSearches: parseRecentSearches,
    buildSearchUrl: buildSearchUrl
  };

  function autoBindMarkedInputs() {
    var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-live-search-input]'));
    nodes.forEach(function (input) {
      if (!(input instanceof HTMLInputElement) || input.dataset.liveSearchBound === '1') {
        return;
      }

      var dropdownId = input.getAttribute('aria-controls') || (input.id ? (input.id + 'Dropdown') : '');
      var dropdown = dropdownId ? document.getElementById(dropdownId) : null;
      if (!dropdown) {
        return;
      }

      attach(input, {
        dropdown: dropdown,
        searchPage: '/search',
        minChars: 2,
        limit: 8
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoBindMarkedInputs);
  } else {
    autoBindMarkedInputs();
  }
})();
