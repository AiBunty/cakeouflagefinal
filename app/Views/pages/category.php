<?php
$totalPages = (isset($totalProducts, $perPage) && (int)$perPage > 0) ? (int)ceil(((int)$totalProducts) / ((int)$perPage)) : 1;
$slug = (string)($category['slug'] ?? '');
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/category'), PHP_URL_PATH) ?: '/category';
$isSearchRoute = $requestPath === '/search';
$baseUrl = $isSearchRoute ? '/search' : ($slug !== '' ? '/category/' . rawurlencode($slug) : '/category');
$activeSort = (string)($_GET['sort'] ?? 'latest');
if ($activeSort === 'newest') {
    $activeSort = 'latest';
}
$activeQuery = trim((string)($_GET['q'] ?? ''));
$activeMaxPrice = trim((string)($_GET['max_price'] ?? ''));
$activePriceBucketRaw = trim((string)($_GET['price_bucket'] ?? ''));
$activePriceBucketMap = [
    'under-500' => 'under_500',
    'under-1000' => '500_1000',
    '1000-2000' => '1000_2000',
    'above-2000' => 'above_2000',
    'under_500' => 'under_500',
    '500_1000' => '500_1000',
    '1000_2000' => '1000_2000',
    'above_2000' => 'above_2000',
];
$activePriceBucket = $activePriceBucketMap[$activePriceBucketRaw] ?? '';
$activeIsVeg = (string)($_GET['is_veg'] ?? '');
$currencySymbol = (string)($siteConfig['currency_symbol'] ?? 'Rs');
$isCustomerAuthenticated = \App\Services\AuthManager::isCustomerAuthenticated();
$foodMode = (string)($siteConfig['business']['store_food_mode'] ?? getDietaryMode());
$showNonVeg = $foodMode === 'veg_nonveg';

$rawDietary = $_GET['dietary'] ?? [];
$activeDiets = is_array($rawDietary) ? $rawDietary : explode(',', (string)$rawDietary);
$activeDiets = array_values(array_filter(array_map(static function ($value): string {
    return trim((string)$value);
}, $activeDiets), static function (string $value): bool {
    return $value !== '';
}));

$activeFlags = [
    'is_bestseller' => (string)($_GET['is_bestseller'] ?? '') === '1',
    'is_chef_special' => (string)($_GET['is_chef_special'] ?? '') === '1',
    'customizable' => (string)($_GET['customizable'] ?? '') === '1',
    'topper_enabled' => (string)($_GET['topper_enabled'] ?? '') === '1',
    'note_enabled' => (string)($_GET['note_enabled'] ?? '') === '1',
    'same_day' => (string)($_GET['same_day'] ?? '') === '1',
    'express' => (string)($_GET['express'] ?? '') === '1',
];

$currentQuery = $_GET;
unset($currentQuery['page']);

$buildUrl = static function (array $overrides) use ($baseUrl, $currentQuery): string {
    $query = array_merge($currentQuery, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || (is_array($value) && count($value) === 0)) {
            unset($query[$key]);
        }
    }
    $queryString = http_build_query($query);
    return $queryString !== '' ? ($baseUrl . '?' . $queryString) : $baseUrl;
};

$activeFilterChips = [];
foreach ($activeDiets as $diet) {
    $activeFilterChips[] = ucfirst(str_replace('_', ' ', $diet));
}
if ($showNonVeg && $activeIsVeg === '1') {
    $activeFilterChips[] = 'Veg';
} elseif ($showNonVeg && $activeIsVeg === '0') {
    $activeFilterChips[] = 'Non-Veg';
}
if ($activePriceBucket !== '') {
    $priceBucketLabels = [
      'under_500' => 'Under ' . $currencySymbol . ' 500',
      '500_1000' => $currencySymbol . ' 500-' . $currencySymbol . ' 1000',
      '1000_2000' => $currencySymbol . ' 1000-' . $currencySymbol . ' 2000',
      'above_2000' => 'Above ' . $currencySymbol . ' 2000',
    ];
    $activeFilterChips[] = $priceBucketLabels[$activePriceBucket] ?? 'Price';
}
if ($activeMaxPrice !== '') {
  $activeFilterChips[] = 'Under ' . $currencySymbol . ' ' . $activeMaxPrice;
}
foreach ($activeFlags as $flag => $enabled) {
    if ($enabled) {
        $activeFilterChips[] = ucwords(str_replace('_', ' ', $flag));
    }
}

$heroDescription = trim((string)($category['description'] ?? ''));
if ($heroDescription === '') {
    $heroDescription = 'Handcrafted with premium ingredients for every celebration.';
}
$heroEyebrow = $activeQuery !== '' ? ('Search results for "' . $activeQuery . '"') : 'Luxury handcrafted collection';
$mobileHeaderLogo = (string)($siteConfig['branding']['navbar_logo_url'] ?? '/client/assets/images/mainlogo.svg');
$mobileHeaderLogoFallback = (string)($siteConfig['branding']['navbar_logo_fallback'] ?? '/client/assets/images/mainlogo.svg');
$productFallbackImage = trim((string)($siteConfig['branding']['default_product_image_url'] ?? '/public/assets/defaults/default-product-image.webp'));
if ($productFallbackImage === '' || !preg_match('#^(https?://|/)#i', $productFallbackImage)) {
  $productFallbackImage = '/public/assets/defaults/default-product-image.webp';
}
?>

<main data-page="category" class="lux-category-page" data-browse-page="category">
  <header class="lux-mobile-header" aria-label="Category mobile header">
    <button type="button" class="lux-mobile-header__icon" id="toggleSidebar" aria-expanded="false" aria-controls="shopSidebar" aria-label="Open filters">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="7" x2="20" y2="7"></line><line x1="4" y1="12" x2="16" y2="12"></line><line x1="4" y1="17" x2="12" y2="17"></line></svg>
    </button>
    <a class="lux-mobile-header__logo" href="/" aria-label="Cakeouflage home">
      <img src="<?= htmlspecialchars($mobileHeaderLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cakeouflage" onerror="this.onerror=null;this.src='<?= htmlspecialchars($mobileHeaderLogoFallback, ENT_QUOTES, 'UTF-8') ?>';" />
    </a>
    <a class="lux-mobile-header__icon" href="/wishlist" aria-label="Wishlist">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-6.7-4.35-9.33-8.03C1.43 11.26 2 8.3 4.2 6.88c2.09-1.35 4.47-.72 5.8.88 1.33-1.6 3.71-2.23 5.8-.88 2.2 1.42 2.77 4.38 1.53 6.09C18.7 16.65 12 21 12 21z"></path></svg>
    </a>
    <a class="lux-mobile-header__icon" href="/cart" aria-label="Cart">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="20" r="1.5"></circle><circle cx="18" cy="20" r="1.5"></circle><path d="M2 3h3l3.6 11.3a1 1 0 0 0 .95.7H18a1 1 0 0 0 .95-.68L21 7H7"></path></svg>
      <span class="lux-mobile-header__count" id="mobileShopCartCount">0</span>
    </a>
  </header>

  <section class="lux-mobile-search container">
    <form method="get" action="/search" class="lux-mobile-search__form" role="search">
      <?php foreach ($_GET as $paramKey => $paramValue): ?>
        <?php if ($paramKey === 'q' || $paramKey === 'page') { continue; } ?>
        <?php if (is_array($paramValue)): ?>
          <?php foreach ($paramValue as $arrayValue): ?>
            <input type="hidden" name="<?= htmlspecialchars((string)$paramKey, ENT_QUOTES, 'UTF-8') ?>[]" value="<?= htmlspecialchars((string)$arrayValue, ENT_QUOTES, 'UTF-8') ?>" />
          <?php endforeach; ?>
        <?php else: ?>
          <input type="hidden" name="<?= htmlspecialchars((string)$paramKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$paramValue, ENT_QUOTES, 'UTF-8') ?>" />
        <?php endif; ?>
      <?php endforeach; ?>
      <input id="shopSearch" data-search-input="mobile" data-live-search-input="category-mobile" type="search" name="q" value="<?= htmlspecialchars($activeQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search cakes..." autocomplete="off" />
      <button type="button" id="shopSearchClearMobile" class="lux-search-clear" aria-label="Clear search" <?= $activeQuery === '' ? 'hidden' : '' ?>>
        <span aria-hidden="true">×</span>
      </button>
      <button type="submit" aria-label="Search">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><line x1="16.7" y1="16.7" x2="21" y2="21"></line></svg>
      </button>
      <div id="shopSearchMobileDropdown" class="search-dropdown lux-search-dropdown" role="listbox" aria-label="Search suggestions" hidden></div>
    </form>
  </section>

  <section class="category-hero-v2 container" aria-label="Category hero">
    <div class="category-hero-v2__content">
      <div class="category-hero-v2__left">
        <?php if (!empty($breadcrumbs)): ?>
          <nav class="category-hero-v2__breadcrumb" aria-label="Breadcrumb">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
              <?php if ($index > 0): ?><span>›</span><?php endif; ?>
              <?php $crumbHref = (string)($crumb['href'] ?? ($crumb['url'] ?? '')); ?>
              <?php if ($crumbHref !== '' && empty($crumb['active'])): ?>
                <a href="<?= htmlspecialchars($crumbHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
              <?php else: ?>
                <strong><?= htmlspecialchars((string)($crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
              <?php endif; ?>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>

        <span class="category-hero-v2__badge"><?= htmlspecialchars($heroEyebrow, ENT_QUOTES, 'UTF-8') ?></span>
        <h1 class="category-hero-v2__title">Explore Our <span><?= htmlspecialchars((string)($category['name'] ?? 'Cake Collection'), ENT_QUOTES, 'UTF-8') ?></span></h1>
        <p class="category-hero-v2__desc"><?= htmlspecialchars($heroDescription, ENT_QUOTES, 'UTF-8') ?></p>

        <div class="category-hero-v2__actions">
          <a class="category-hero-v2__btn category-hero-v2__btn--primary" href="#shopGrid">Shop Now</a>
          <a class="category-hero-v2__btn category-hero-v2__btn--ghost" href="/custom-cake-inquiry">Build Custom Cake</a>
        </div>
      </div>

      <div class="category-hero-v2__right">
        <img
          src="/client/assets/images/cake.png"
          alt="Featured premium cake"
          class="category-hero-v2__media"
          loading="lazy"
          decoding="async"
        />
      </div>
    </div>
  </section>

  <section class="category-hero-v2-services container" aria-label="Service highlights">
    <article class="category-hero-v2-services__item"><span>🧁</span><strong>Freshly Baked</strong><small>Made To Order</small></article>
    <article class="category-hero-v2-services__item"><span>💎</span><strong>Premium Ingredients</strong><small>100% Pure and Fresh</small></article>
    <article class="category-hero-v2-services__item"><span>🎨</span><strong>Custom Designs</strong><small>Made Just For You</small></article>
    <article class="category-hero-v2-services__item"><span>🚚</span><strong>Timely Delivery</strong><small>On Time, Every Time</small></article>
  </section>

  <?php if (!empty($children)): ?>
    <section class="lux-mobile-category-shortcuts container" aria-label="Category shortcuts">
      <?php foreach ($children as $child): ?>
        <?php $childName = (string)($child['name'] ?? 'Category'); ?>
        <?php $childSlug = (string)($child['slug'] ?? ''); ?>
        <?php $childId = (int)($child['id'] ?? 0); ?>
        <?php $childUrl = '/category/' . rawurlencode($childSlug . '-' . $childId); ?>
        <a class="lux-category-pill" href="<?= htmlspecialchars($childUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($childName, ENT_QUOTES, 'UTF-8') ?></a>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section class="lux-catalog container">
    <aside class="lux-filter-sidebar" id="shopSidebar" aria-label="Category filters">
      <div class="lux-filter-sidebar__head">
        <h2>Filter By</h2>
        <button id="sidebarClose" type="button" aria-label="Close filters">×</button>
      </div>

      <form method="get" id="filterForm" class="lux-filter-form">
        <section class="lux-filter-accordion is-open" data-accordion="categories">
          <button type="button" class="lux-filter-accordion__trigger" data-accordion-trigger="categories">Categories</button>
          <div class="lux-filter-accordion__panel" data-accordion-panel="categories">
            <div id="filterCategories" class="lux-filter-tree" data-current-category="<?= htmlspecialchars((string)($category['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
          </div>
        </section>

        <section class="lux-filter-accordion" data-accordion="occasion">
          <button type="button" class="lux-filter-accordion__trigger" data-accordion-trigger="occasion">Occasion</button>
          <div class="lux-filter-accordion__panel" data-accordion-panel="occasion">
            <div class="lux-filter-tags">
              <button type="button" class="lux-filter-tag" data-prefill-search="birthday">Birthday</button>
              <button type="button" class="lux-filter-tag" data-prefill-search="anniversary">Anniversary</button>
              <button type="button" class="lux-filter-tag" data-prefill-search="wedding">Wedding</button>
              <button type="button" class="lux-filter-tag" data-prefill-search="corporate">Corporate</button>
            </div>
          </div>
        </section>

        <section class="lux-filter-accordion" data-accordion="flavour">
          <button type="button" class="lux-filter-accordion__trigger" data-accordion-trigger="flavour">Flavour</button>
          <div class="lux-filter-accordion__panel" data-accordion-panel="flavour">
            <div class="lux-filter-tags">
              <button type="button" class="lux-filter-tag" data-prefill-search="chocolate">Chocolate</button>
              <button type="button" class="lux-filter-tag" data-prefill-search="red velvet">Red Velvet</button>
              <button type="button" class="lux-filter-tag" data-prefill-search="fruit">Fruit</button>
              <button type="button" class="lux-filter-tag" data-prefill-search="vanilla">Vanilla</button>
            </div>
          </div>
        </section>

        <section class="lux-filter-accordion" data-accordion="dietary">
          <button type="button" class="lux-filter-accordion__trigger" data-accordion-trigger="dietary">Dietary</button>
          <div class="lux-filter-accordion__panel" data-accordion-panel="dietary">
            <label><input type="checkbox" name="dietary[]" value="eggless" <?= in_array('eggless', $activeDiets, true) ? 'checked' : '' ?> /> Eggless</label>
            <label><input type="checkbox" name="dietary[]" value="vegan" <?= in_array('vegan', $activeDiets, true) ? 'checked' : '' ?> /> Vegan</label>
            <label><input type="checkbox" name="dietary[]" value="sugar_free" <?= in_array('sugar_free', $activeDiets, true) ? 'checked' : '' ?> /> Sugar Free</label>
            <?php if ($showNonVeg): ?>
              <hr />
              <label><input type="radio" name="is_veg" value="" <?= $activeIsVeg === '' ? 'checked' : '' ?> /> All</label>
              <label><input type="radio" name="is_veg" value="1" <?= $activeIsVeg === '1' ? 'checked' : '' ?> /> Veg</label>
              <label><input type="radio" name="is_veg" value="0" <?= $activeIsVeg === '0' ? 'checked' : '' ?> /> Non-Veg</label>
            <?php endif; ?>
          </div>
        </section>

        <section class="lux-filter-accordion" data-accordion="price">
          <button type="button" class="lux-filter-accordion__trigger" data-accordion-trigger="price">Price Range</button>
          <div class="lux-filter-accordion__panel" data-accordion-panel="price">
            <select name="price_bucket" id="priceBucket" aria-label="Price range">
              <option value="">All ranges</option>
              <option value="under_500" <?= $activePriceBucket === 'under_500' ? 'selected' : '' ?>>Under <?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?> 500</option>
              <option value="500_1000" <?= $activePriceBucket === '500_1000' ? 'selected' : '' ?>><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?> 500 - <?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?> 1000</option>
              <option value="1000_2000" <?= $activePriceBucket === '1000_2000' ? 'selected' : '' ?>><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?> 1000 - <?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?> 2000</option>
              <option value="above_2000" <?= $activePriceBucket === 'above_2000' ? 'selected' : '' ?>>Above <?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?> 2000</option>
            </select>
            <input type="range" id="maxPriceRange" min="200" max="5000" step="100" value="<?= htmlspecialchars($activeMaxPrice !== '' ? $activeMaxPrice : '5000', ENT_QUOTES, 'UTF-8') ?>" />
            <input type="number" id="maxPriceInput" name="max_price" min="100" step="100" value="<?= htmlspecialchars($activeMaxPrice, ENT_QUOTES, 'UTF-8') ?>" placeholder="Custom max price" />
          </div>
        </section>

        <section class="lux-filter-accordion" data-accordion="features">
          <button type="button" class="lux-filter-accordion__trigger" data-accordion-trigger="features">Product Features</button>
          <div class="lux-filter-accordion__panel" data-accordion-panel="features">
            <label><input type="checkbox" name="is_bestseller" value="1" <?= $activeFlags['is_bestseller'] ? 'checked' : '' ?> /> Bestseller</label>
            <label><input type="checkbox" name="is_chef_special" value="1" <?= $activeFlags['is_chef_special'] ? 'checked' : '' ?> /> Chef Special</label>
            <label><input type="checkbox" name="customizable" value="1" <?= $activeFlags['customizable'] ? 'checked' : '' ?> /> Customizable</label>
            <label><input type="checkbox" name="topper_enabled" value="1" <?= $activeFlags['topper_enabled'] ? 'checked' : '' ?> /> Topper Available</label>
            <label><input type="checkbox" name="note_enabled" value="1" <?= $activeFlags['note_enabled'] ? 'checked' : '' ?> /> Note on Cake</label>
            <label><input type="checkbox" name="same_day" value="1" <?= $activeFlags['same_day'] ? 'checked' : '' ?> /> Same Day Delivery</label>
            <label><input type="checkbox" name="express" value="1" <?= $activeFlags['express'] ? 'checked' : '' ?> /> Express Delivery</label>
          </div>
        </section>

        <?php if ($activeQuery !== ''): ?>
          <input type="hidden" name="q" value="<?= htmlspecialchars($activeQuery, ENT_QUOTES, 'UTF-8') ?>" />
        <?php endif; ?>
        <?php if ($activeSort !== '' && $activeSort !== 'latest'): ?>
          <input type="hidden" name="sort" value="<?= htmlspecialchars($activeSort, ENT_QUOTES, 'UTF-8') ?>" />
        <?php endif; ?>

        <div class="lux-filter-actions">
          <button type="submit" id="applyFiltersBtn">Apply Filters</button>
          <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" id="clearFiltersBtn">Clear All</a>
        </div>
      </form>
    </aside>

    <div class="lux-catalog-content">
      <div class="lux-toolbar" id="shopToolbar">
        <div class="lux-toolbar__top">
          <p class="lux-toolbar__count" id="shopCount">
            <?php if ((int)$totalProducts > 0): ?>
              Showing <?= min((((int)$currentPage - 1) * (int)$perPage) + 1, (int)$totalProducts) ?>-<?= min(((int)$currentPage * (int)$perPage), (int)$totalProducts) ?> of <?= (int)$totalProducts ?> cakes
            <?php else: ?>
              No cakes found
            <?php endif; ?>
          </p>
          <p class="lux-search-status" id="categorySearchStatus" aria-live="polite" hidden></p>

          <div class="lux-toolbar__controls">
            <button type="button" class="lux-filter-open" id="mobileFilterBtnTop">Filter</button>
            <form method="get" action="/search" class="lux-toolbar__search" role="search">
              <?php foreach ($_GET as $paramKey => $paramValue): ?>
                <?php if ($paramKey === 'q' || $paramKey === 'page') { continue; } ?>
                <?php if (is_array($paramValue)): ?>
                  <?php foreach ($paramValue as $arrayValue): ?>
                    <input type="hidden" name="<?= htmlspecialchars((string)$paramKey, ENT_QUOTES, 'UTF-8') ?>[]" value="<?= htmlspecialchars((string)$arrayValue, ENT_QUOTES, 'UTF-8') ?>" />
                  <?php endforeach; ?>
                <?php else: ?>
                  <input type="hidden" name="<?= htmlspecialchars((string)$paramKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$paramValue, ENT_QUOTES, 'UTF-8') ?>" />
                <?php endif; ?>
              <?php endforeach; ?>
              <label class="lux-sr-only" for="shopSearchDesktop">Search cakes</label>
              <input id="shopSearchDesktop" data-search-input="desktop" data-live-search-input="category-desktop" type="search" name="q" value="<?= htmlspecialchars($activeQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search cakes, flavors, occasions..." autocomplete="off" />
              <button type="button" id="shopSearchClearDesktop" class="lux-search-clear" aria-label="Clear search" <?= $activeQuery === '' ? 'hidden' : '' ?>>
                <span aria-hidden="true">×</span>
              </button>
              <button type="submit" aria-label="Search cakes">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><line x1="16.7" y1="16.7" x2="21" y2="21"></line></svg>
              </button>
              <div id="shopSearchDesktopDropdown" class="search-dropdown lux-search-dropdown" role="listbox" aria-label="Search suggestions" hidden></div>
            </form>
            <form method="get" id="sortForm">
              <?php foreach ($_GET as $paramKey => $paramValue): ?>
                <?php if ($paramKey === 'sort' || $paramKey === 'page') { continue; } ?>
                <?php if (is_array($paramValue)): ?>
                  <?php foreach ($paramValue as $arrayValue): ?>
                    <input type="hidden" name="<?= htmlspecialchars((string)$paramKey, ENT_QUOTES, 'UTF-8') ?>[]" value="<?= htmlspecialchars((string)$arrayValue, ENT_QUOTES, 'UTF-8') ?>" />
                  <?php endforeach; ?>
                <?php else: ?>
                  <input type="hidden" name="<?= htmlspecialchars((string)$paramKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$paramValue, ENT_QUOTES, 'UTF-8') ?>" />
                <?php endif; ?>
              <?php endforeach; ?>
              <select id="shopSort" name="sort" onchange="window.CakeScrollPreserver ? window.CakeScrollPreserver.submitForm(document.getElementById('sortForm')) : document.getElementById('sortForm').submit()">
                <option value="popular" <?= $activeSort === 'popular' ? 'selected' : '' ?>>Sort by Popularity</option>
                <option value="latest" <?= ($activeSort === 'latest' || $activeSort === '') ? 'selected' : '' ?>>Latest</option>
                <option value="price_asc" <?= $activeSort === 'price_asc' ? 'selected' : '' ?>>Price Low to High</option>
                <option value="price_desc" <?= $activeSort === 'price_desc' ? 'selected' : '' ?>>Price High to Low</option>
              </select>
            </form>
            <div class="lux-view-toggle" role="group" aria-label="View mode">
              <button class="view-btn is-active" type="button" data-view="grid" title="Grid view">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8"></rect><rect x="13" y="3" width="8" height="8"></rect><rect x="3" y="13" width="8" height="8"></rect><rect x="13" y="13" width="8" height="8"></rect></svg>
              </button>
              <button class="view-btn" type="button" data-view="list" title="List view">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
              </button>
            </div>
          </div>
        </div>

        <div class="lux-chip-row" id="shopQuickChips" aria-label="Quick filters">
          <button type="button" class="lux-chip is-active" data-quick-filter="all">All Cakes</button>
          <button type="button" class="lux-chip" data-quick-filter="eggless">Premium Cakes</button>
          <button type="button" class="lux-chip" data-quick-filter="chefSpecial">Designer Cakes</button>
          <button type="button" class="lux-chip" data-quick-filter="sameDay">Kids Cakes</button>
          <button type="button" class="lux-chip" data-quick-filter="under1000">Photo Cakes</button>
          <button type="button" class="lux-chip" data-quick-filter="bestseller">Cheesecakes</button>
          <button type="button" class="lux-chip" data-quick-filter="vegan">Cup Cakes</button>
        </div>
      </div>

      <?php if (!empty($activeFilterChips)): ?>
        <div class="lux-active-filters" id="activeFilters">
          <?php foreach ($activeFilterChips as $chip): ?>
            <span><?= htmlspecialchars((string)$chip, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endforeach; ?>
          <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">Clear All</a>
        </div>
      <?php endif; ?>

      <div class="lux-grid-skeleton" id="luxCategorySkeleton" aria-hidden="true">
        <?php for ($s = 0; $s < 8; $s++): ?>
          <article class="lux-skeleton-card"></article>
        <?php endfor; ?>
      </div>

      <div class="lux-product-grid" id="shopGrid">
        <?php if (empty($products)): ?>
          <article class="lux-empty-state">
            <h3>No cakes found</h3>
            <p>Try adjusting filters, changing search terms, or exploring the full collection.</p>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">Reset Filters</a>
          </article>
        <?php else: ?>
          <?php foreach ($products as $product): ?>
            <?php
              $productId = (int)($product['id'] ?? 0);
              $name = (string)($product['name'] ?? 'Product');
              $productSlug = (string)($product['slug'] ?? '');
              $productUrl = '/product/' . rawurlencode($productSlug);
              $customCakeUrl = '/custom-cake-inquiry';
              $rawThumb = trim((string)($product['thumb'] ?? $product['featured_image'] ?? ''));
              $thumb = product_image_url($rawThumb, (string)($product['category_slug'] ?? $category['slug'] ?? ''));
              $priceValue = (float)($product['min_price'] ?? 0);
              $priceLabel = number_format($priceValue);
              $strikeValue = $priceValue > 0 ? number_format((float)round($priceValue * 1.1)) : '';
              $showDiscount = (!empty($product['is_featured']) || !empty($product['is_bestseller'])) && $strikeValue !== '';
              $isVeg = (int)($product['is_veg'] ?? 1) === 1;
              $dietaryTag = strtolower(trim((string)($product['dietary_tag'] ?? '')));
              $shortDesc = trim((string)($product['short_description'] ?? ''));
              if ($shortDesc === '') {
                $shortDesc = 'Fresh handcrafted cake delight';
              }
              $isCustomizable = !empty($product['customizable']);
            ?>
            <article class="lux-product-card">
              <a class="lux-product-card__image-wrap" href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" width="360" height="260" data-fallback-src="<?= htmlspecialchars($productFallbackImage, ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null;this.src='<?= htmlspecialchars($productFallbackImage, ENT_QUOTES, 'UTF-8') ?>';" />
                <button class="lux-product-card__wish" type="button" data-wishlist-product="<?= $productId ?>" aria-label="Add to wishlist">♡</button>
                <?php if (!empty($product['is_bestseller'])): ?>
                  <span class="lux-badge lux-badge--bestseller">Bestseller</span>
                <?php endif; ?>
                <?php if ($showDiscount): ?>
                  <span class="lux-badge lux-badge--discount">10% OFF</span>
                <?php endif; ?>
              </a>

              <div class="lux-product-card__body">
                <div class="lux-product-card__title-line">
                  <h3><a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a></h3>
                  <?php if ($showNonVeg): ?>
                    <span class="lux-veg-dot <?= $isVeg ? 'is-veg' : 'is-nonveg' ?>" title="<?= $isVeg ? 'Vegetarian' : 'Non-Vegetarian' ?>"></span>
                  <?php endif; ?>
                </div>

                <p><?= htmlspecialchars($shortDesc, ENT_QUOTES, 'UTF-8') ?></p>

                <div class="lux-rating-row">
                  <span>★ 4.8</span>
                  <small>(12)</small>
                  <?php if ($dietaryTag !== '' && $dietaryTag !== 'regular'): ?>
                    <small class="lux-diet-tag"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $dietaryTag)), ENT_QUOTES, 'UTF-8') ?></small>
                  <?php endif; ?>
                </div>

                <div class="lux-price-row">
                  <strong>From <?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                  <?php if ($showDiscount): ?><del><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($strikeValue, ENT_QUOTES, 'UTF-8') ?></del><?php endif; ?>
                </div>

                <div class="lux-product-card__actions">
                  <a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>">View</a>
                  <?php if ($isCustomizable): ?>
                    <a class="lux-product-card__custom-link" href="<?= htmlspecialchars($customCakeUrl, ENT_QUOTES, 'UTF-8') ?>">Customize</a>
                  <?php else: ?>
                    <button type="button" data-add-product="<?= $productId ?>" data-add-variant="">Quick Add</button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="lux-pagination" aria-label="Category pages">
          <?php if ((int)$currentPage > 1): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => (int)$currentPage - 1]), ENT_QUOTES, 'UTF-8') ?>">‹</a>
          <?php endif; ?>
          <?php $start = max(1, (int)$currentPage - 2); $end = min($totalPages, (int)$currentPage + 2); ?>
          <?php for ($pageNumber = $start; $pageNumber <= $end; $pageNumber++): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => $pageNumber]), ENT_QUOTES, 'UTF-8') ?>" class="<?= $pageNumber === (int)$currentPage ? 'is-active' : '' ?>" <?= $pageNumber === (int)$currentPage ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a>
          <?php endfor; ?>
          <?php if ((int)$currentPage < $totalPages): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => (int)$currentPage + 1]), ENT_QUOTES, 'UTF-8') ?>">›</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </section>

  <div class="lux-mobile-actions" id="shopMobileActions" aria-label="Shop actions">
    <button type="button" id="mobileFilterBtn">Filters</button>
    <button type="button" id="mobileSortBtn">Sort</button>
    <button type="button" id="mobileSearchBtn">Search</button>
    <a id="mobileCartBtn" href="/cart">Cart <span class="lux-mobile-cart-count-alt">0</span></a>
  </div>

  <nav class="lux-mobile-bottom-nav" aria-label="Mobile bottom navigation">
    <a href="/">Home</a>
    <a href="/category" class="is-active">Categories</a>
    <a href="/orders">Orders</a>
    <a href="/wishlist">Wishlist</a>
    <a href="<?= $isCustomerAuthenticated ? '/account/dashboard.php' : '/account/login.php' ?>"><?= $isCustomerAuthenticated ? 'Dashboard' : 'Sign In' ?></a>
  </nav>
</main>

<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
