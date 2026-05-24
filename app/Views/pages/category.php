<?php
$totalPages = (isset($totalProducts, $perPage) && (int)$perPage > 0) ? (int)ceil(((int)$totalProducts) / ((int)$perPage)) : 1;
$slug    = (string)($category['slug'] ?? '');
$baseUrl = $slug !== '' ? '/category/' . rawurlencode($slug) : '/category';

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
    $activeFilterChips[] = ['label' => ucfirst(str_replace('_', ' ', $diet)), 'key' => 'dietary'];
}
if ($activeIsVeg === '1') {
    $activeFilterChips[] = ['label' => 'Veg', 'key' => 'is_veg'];
} elseif ($activeIsVeg === '0') {
    $activeFilterChips[] = ['label' => 'Non-Veg', 'key' => 'is_veg'];
}
if ($activePriceBucket !== '') {
  $priceBucketLabels = [
    'under_500' => 'Under ₹500',
    '500_1000' => '₹500-₹1000',
    '1000_2000' => '₹1000-₹2000',
    'above_2000' => 'Above ₹2000',
  ];
  $activeFilterChips[] = ['label' => $priceBucketLabels[$activePriceBucket] ?? 'Price', 'key' => 'price_bucket'];
}
if ($activeMaxPrice !== '') {
    $activeFilterChips[] = ['label' => 'Under ₹' . $activeMaxPrice, 'key' => 'max_price'];
}
foreach ($activeFlags as $flag => $enabled) {
    if ($enabled) {
        $activeFilterChips[] = ['label' => ucwords(str_replace('_', ' ', $flag)), 'key' => $flag];
    }
}
?>

<main data-page="category" data-browse-page="category">
  <section class="cat-hero cat-hero--with-image">
    <div class="cat-hero__doodles"></div>
    <div class="container">
      <?php if (!empty($breadcrumbs)): ?>
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $index => $crumb): ?>
            <?php if ($index > 0): ?><span class="breadcrumb__sep" aria-hidden="true">›</span><?php endif; ?>
            <?php $crumbHref = (string)($crumb['href'] ?? ($crumb['url'] ?? '')); ?>
            <?php if ($crumbHref !== '' && empty($crumb['active'])): ?>
              <a class="breadcrumb__link" href="<?= htmlspecialchars($crumbHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
              <span class="breadcrumb__current" aria-current="page"><?= htmlspecialchars((string)($crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

      <div class="cat-hero__content">
        <h1 class="cat-hero__title"><?= htmlspecialchars((string)($category['name'] ?? 'Category'), ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($category['description'])): ?>
          <p class="cat-hero__desc"><?= htmlspecialchars((string)$category['description'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <p class="cat-hero__meta"><?= (int)$totalProducts ?> item<?= ((int)$totalProducts !== 1) ? 's' : '' ?> in this collection</p>
      </div>
    </div>
  </section>

  <section class="shop-main section">
    <div class="container">
      <div class="shop-layout">
        <aside class="shop-sidebar" id="shopSidebar" aria-label="Category filters">
          <div class="shop-sidebar__header">
            <h2 class="shop-sidebar__title">Filter</h2>
            <button class="shop-sidebar__close" id="sidebarClose" type="button" aria-label="Close filters">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <form method="get" id="filterForm">
            <div class="filter-group">
              <h3 class="filter-group__title">All Categories</h3>
              <div id="filterCategories" class="filter-group__options filter-group__options--tree" data-current-category="<?= htmlspecialchars((string)($category['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            </div>

            <div class="filter-group">
              <h3 class="filter-group__title">Dietary</h3>
              <div class="filter-group__options">
                <?php foreach (['eggless' => 'Eggless', 'vegan' => 'Vegan', 'sugar_free' => 'Sugar-Free'] as $value => $label): ?>
                  <label class="filter-chip">
                    <input type="checkbox" name="dietary[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($value, $activeDiets, true) ? 'checked' : '' ?> />
                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="filter-group">
              <h3 class="filter-group__title">Veg / Non-Veg</h3>
              <div class="filter-group__options">
                <label class="filter-chip"><input type="radio" name="is_veg" value="" <?= $activeIsVeg === '' ? 'checked' : '' ?> /><span>All</span></label>
                <label class="filter-chip"><input type="radio" name="is_veg" value="1" <?= $activeIsVeg === '1' ? 'checked' : '' ?> /><span>Veg</span></label>
                <label class="filter-chip"><input type="radio" name="is_veg" value="0" <?= $activeIsVeg === '0' ? 'checked' : '' ?> /><span>Non-Veg</span></label>
              </div>
            </div>

            <div class="filter-group">
              <h3 class="filter-group__title">Price Range</h3>
              <div class="filter-group__options">
                <select name="price_bucket" id="priceBucket" class="shop-sort-select" aria-label="Price bucket">
                  <option value="">All ranges</option>
                  <option value="under_500" <?= $activePriceBucket === 'under_500' ? 'selected' : '' ?>>Under ₹500</option>
                  <option value="500_1000" <?= $activePriceBucket === '500_1000' ? 'selected' : '' ?>>₹500-₹1000</option>
                  <option value="1000_2000" <?= $activePriceBucket === '1000_2000' ? 'selected' : '' ?>>₹1000-₹2000</option>
                  <option value="above_2000" <?= $activePriceBucket === 'above_2000' ? 'selected' : '' ?>>Above ₹2000</option>
                </select>
                <input type="number" id="maxPriceInput" name="max_price" min="100" step="100" placeholder="Custom max price" value="<?= htmlspecialchars($activeMaxPrice, ENT_QUOTES, 'UTF-8') ?>" class="shop-search-input" style="margin-top:8px;" />
              </div>
            </div>

            <div class="filter-group">
              <h3 class="filter-group__title">Features</h3>
              <div class="filter-group__options">
                <label class="filter-chip"><input type="checkbox" name="is_bestseller" value="1" <?= $activeFlags['is_bestseller'] ? 'checked' : '' ?> /><span>Bestseller</span></label>
                <label class="filter-chip"><input type="checkbox" name="is_chef_special" value="1" <?= $activeFlags['is_chef_special'] ? 'checked' : '' ?> /><span>Chef's Special</span></label>
                <label class="filter-chip"><input type="checkbox" name="customizable" value="1" <?= $activeFlags['customizable'] ? 'checked' : '' ?> /><span>Customizable</span></label>
                <label class="filter-chip"><input type="checkbox" name="topper_enabled" value="1" <?= $activeFlags['topper_enabled'] ? 'checked' : '' ?> /><span>Topper Available</span></label>
                <label class="filter-chip"><input type="checkbox" name="note_enabled" value="1" <?= $activeFlags['note_enabled'] ? 'checked' : '' ?> /><span>Note on Cake</span></label>
                <label class="filter-chip"><input type="checkbox" name="same_day" value="1" <?= $activeFlags['same_day'] ? 'checked' : '' ?> /><span>Same Day Delivery</span></label>
                <label class="filter-chip"><input type="checkbox" name="express" value="1" <?= $activeFlags['express'] ? 'checked' : '' ?> /><span>Express Delivery</span></label>
              </div>
            </div>

            <?php if ($activeQuery !== ''): ?>
              <input type="hidden" name="q" value="<?= htmlspecialchars($activeQuery, ENT_QUOTES, 'UTF-8') ?>" />
            <?php endif; ?>
            <?php if ($activeSort !== '' && $activeSort !== 'latest'): ?>
              <input type="hidden" name="sort" value="<?= htmlspecialchars($activeSort, ENT_QUOTES, 'UTF-8') ?>" />
            <?php endif; ?>

            <button type="submit" class="btn btn--primary btn--full" id="applyFiltersBtn">Apply Filters</button>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn--ghost btn--full mt-sm" id="clearFiltersBtn">Clear All</a>
          </form>
        </aside>

        <div class="shop-content">
          <div class="shop-toolbar" id="shopToolbar">
            <div class="shop-toolbar__left">
              <button type="button" class="btn btn--outline btn--sm" id="toggleSidebar" aria-expanded="false" aria-controls="shopSidebar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="10" y2="18"/></svg>
                Filter
              </button>
              <span class="shop-count" id="shopCount">
                <?php if ((int)$totalProducts > 0): ?>
                  Showing <?= min((((int)$currentPage - 1) * (int)$perPage) + 1, (int)$totalProducts) ?>-<?= min(((int)$currentPage * (int)$perPage), (int)$totalProducts) ?> of <?= (int)$totalProducts ?>
                <?php else: ?>
                  No products found
                <?php endif; ?>
              </span>
            </div>

            <div class="shop-toolbar__right">
              <form method="get" class="shop-search-wrap" role="search">
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
                <svg class="shop-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input id="shopSearch" type="search" class="shop-search-input" name="q" value="<?= htmlspecialchars($activeQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search this category" />
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
                <select id="shopSort" name="sort" class="shop-sort-select" onchange="document.getElementById('sortForm').submit()">
                  <option value="latest" <?= ($activeSort === 'latest' || $activeSort === '') ? 'selected' : '' ?>>Latest</option>
                  <option value="price_asc" <?= $activeSort === 'price_asc' ? 'selected' : '' ?>>Price ↑</option>
                  <option value="price_desc" <?= $activeSort === 'price_desc' ? 'selected' : '' ?>>Price ↓</option>
                  <option value="popular" <?= $activeSort === 'popular' ? 'selected' : '' ?>>Popularity</option>
                </select>
              </form>

              <div class="view-toggle" role="group" aria-label="View mode">
                <button class="view-btn is-active" type="button" data-view="grid" title="Grid view">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8"/><rect x="13" y="3" width="8" height="8"/><rect x="3" y="13" width="8" height="8"/><rect x="13" y="13" width="8" height="8"/></svg>
                </button>
                <button class="view-btn" type="button" data-view="list" title="List view">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </button>
              </div>
            </div>
          </div>

          <div class="browse-header">
            <p class="browse-header__crumb">Home / Category / <?= htmlspecialchars((string)($category['name'] ?? 'Category'), ENT_QUOTES, 'UTF-8') ?></p>
            <h2 class="browse-header__title"><?= htmlspecialchars((string)($category['name'] ?? 'Category'), ENT_QUOTES, 'UTF-8') ?></h2>
          </div>

          <div class="shop-quick-chips" id="shopQuickChips" aria-label="Quick filters">
            <button type="button" class="shop-quick-chip is-active" data-quick-filter="all">All</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="eggless">Eggless</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="vegan">Vegan</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="under1000">Under ₹1000</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="bestseller">Bestseller</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="sameDay">Same Day Delivery</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="chefSpecial">Chef Special</button>
          </div>

          <?php if (!empty($activeFilterChips)): ?>
            <div class="active-filters" id="activeFilters">
              <?php foreach ($activeFilterChips as $chip): ?>
                <span class="shop-active-filter"><?= htmlspecialchars((string)$chip['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endforeach; ?>
              <a class="shop-filters__clear" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">Clear all</a>
            </div>
          <?php endif; ?>

          <div class="product-grid" id="shopGrid">
        <?php if (empty($products)): ?>
          <div class="empty-state" style="grid-column:1/-1;">
            <p class="empty-state__title">No products found</p>
            <p class="empty-state__body">Try adjusting filters or <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">view all products</a>.</p>
          </div>
        <?php else: ?>
          <?php foreach ($products as $product): ?>
            <?php
              $name = (string)($product['name'] ?? 'Product');
              $productSlug = (string)($product['slug'] ?? '');
              $productUrl = '/product/' . rawurlencode($productSlug);
              $rawThumb = trim((string)($product['thumb'] ?? $product['featured_image'] ?? ''));
              $thumb = product_image_url($rawThumb, (string)($product['category_slug'] ?? $category['slug'] ?? ''));
              $minPrice = number_format((float)($product['min_price'] ?? 0));
              $isVeg = (int)($product['is_veg'] ?? 1) === 1;
              $dietaryTag = strtolower(trim((string)($product['dietary_tag'] ?? '')));
            ?>
            <article class="product-card">
              <a class="product-card__image-wrap" href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>">
                <img class="product-card__image"
                     src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                     loading="lazy" width="400" height="400"
                     onerror="this.onerror=null;this.src='/public/assets/defaults/default-product-image.webp';" />
                <?php if (!empty($product['is_bestseller'])): ?>
                  <span class="product-card__badge product-card__badge--bestseller">Bestseller</span>
                <?php elseif (!empty($product['is_featured'])): ?>
                  <span class="product-card__badge product-card__badge--featured">Featured</span>
                <?php endif; ?>
                <?php if ($dietaryTag !== '' && $dietaryTag !== 'regular'): ?>
                  <span class="product-card__diet"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $dietaryTag)), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </a>
              <div class="product-card__body">
                <h3 class="product-card__name product-card__title"><a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a></h3>
                <span class="veg-dot veg-dot--<?= $isVeg ? 'veg' : 'nonveg' ?>" title="<?= $isVeg ? 'Vegetarian' : 'Non-Vegetarian' ?>"></span>
                <p class="product-card__short-desc"><?= htmlspecialchars((string)($product['short_description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="product-card__footer">
                  <span class="product-card__price">From ₹<?= $minPrice ?></span>
                </div>
                <div class="product-card__actions">
                  <a class="btn-card-secondary product-card__view-btn" href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>">View Details</a>
                  <button class="btn-card-primary product-card__quick-add" type="button" data-add-product="<?= (int)($product['id'] ?? 0) ?>" data-add-variant="">Quick Add</button>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="container">
    <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Category pages">
        <?php if ((int)$currentPage > 1): ?>
          <a class="pagination__btn" href="<?= htmlspecialchars($buildUrl(['page' => (int)$currentPage - 1]), ENT_QUOTES, 'UTF-8') ?>">‹ Prev</a>
        <?php endif; ?>

        <?php $start = max(1, (int)$currentPage - 2); $end = min($totalPages, (int)$currentPage + 2); ?>
        <?php for ($pageNumber = $start; $pageNumber <= $end; $pageNumber++): ?>
          <a class="pagination__btn<?= $pageNumber === (int)$currentPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildUrl(['page' => $pageNumber]), ENT_QUOTES, 'UTF-8') ?>" <?= $pageNumber === (int)$currentPage ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a>
        <?php endfor; ?>

        <?php if ((int)$currentPage < $totalPages): ?>
          <a class="pagination__btn" href="<?= htmlspecialchars($buildUrl(['page' => (int)$currentPage + 1]), ENT_QUOTES, 'UTF-8') ?>">Next ›</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>

  <div class="shop-mobile-actions" id="shopMobileActions" aria-label="Shop actions">
    <button type="button" class="shop-mobile-actions__btn" id="mobileFilterBtn">Filter</button>
    <button type="button" class="shop-mobile-actions__btn" id="mobileSortBtn">Sort</button>
    <button type="button" class="shop-mobile-actions__btn" id="mobileSearchBtn">Search</button>
    <a class="shop-mobile-actions__btn shop-mobile-actions__btn--cart" id="mobileCartBtn" href="/cart">
      Cart
      <span class="shop-mobile-actions__count" id="mobileShopCartCount">0</span>
    </a>
  </div>
</main>

<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
