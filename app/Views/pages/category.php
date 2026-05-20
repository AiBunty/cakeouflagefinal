<?php
/*
 * Cakeouflage — Category / Collection Page
 * Variables injected by WebController::category():
 *   $category      - row array (id, name, slug, description, banner_image, seo_title, …)
 *   $children      - direct child category rows
 *   $products      - paginated product rows (min_price, max_price, thumb, …)
 *   $totalProducts - integer count
 *   $breadcrumbs   - [{label, url?}, …]
 *   $currentPage   - integer
 *   $perPage       - integer
 */
$totalPages  = (isset($totalProducts, $perPage) && $perPage > 0) ? (int)ceil($totalProducts / $perPage) : 1;
$activeSort  = htmlspecialchars($_GET['sort'] ?? '', ENT_QUOTES, 'UTF-8');
$activeDiets = [];

if (!empty($_GET['dietary'])) {
    $activeDiets = is_array($_GET['dietary'])
        ? $_GET['dietary']
        : explode(',', $_GET['dietary']);

    $activeDiets = array_map('trim', $activeDiets);
}
$maxPrice    = htmlspecialchars($_GET['max_price'] ?? '', ENT_QUOTES, 'UTF-8');
$baseUrl     = '/category/' . htmlspecialchars($category['slug'] ?? 'category', ENT_QUOTES, 'UTF-8');
$hasBanner   = !empty($category['banner_image']);
?>
<main data-page="category">

  <!-- ── Hero ── -->
<section class="cat-hero cat-hero--with-image">
  
  <div class="cat-hero__doodles"></div>
    <div class="container">
      <!-- Breadcrumb -->
      <?php if (!empty($breadcrumbs)): ?>
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
          <?php if ($i > 0): ?><span class="breadcrumb__sep" aria-hidden="true">›</span><?php endif; ?>
          <?php if (!empty($crumb['url'])): ?>
            <a class="breadcrumb__link" href="<?= htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></a>
          <?php else: ?>
            <span class="breadcrumb__current" aria-current="page"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>

      <div class="cat-hero__content">
        <?php if (!empty($category['menu_icon'])): ?>
          <span class="cat-hero__icon" aria-hidden="true"><?= htmlspecialchars($category['menu_icon'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <h1 class="cat-hero__title"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($category['description'])): ?>
          <p class="cat-hero__desc"><?= htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <p class="cat-hero__meta">
          <?= (int)$totalProducts ?> item<?= $totalProducts != 1 ? 's' : '' ?> in this collection
        </p>
      </div>
    </div>
  </section>

  <div class="container">

    <!-- ── Subcategory Strip 
    <?php if (!empty($children)): ?>
    <section class="subcat-section">
      <h2 class="subcat-section__title">Shop by Style</h2>
      <div class="subcat-grid">
        <?php foreach ($children as $child): ?>
        <a class="subcat-card"
           href="/category/<?= htmlspecialchars($child['slug'], ENT_QUOTES, 'UTF-8') ?>-<?= (int)$child['id'] ?>"
   title="View <?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?>">
          <?php if (!empty($child['menu_icon'])): ?>
            <span class="subcat-card__icon" aria-hidden="true"><?= htmlspecialchars($child['menu_icon'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <span class="subcat-card__name"><?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    ── -->
    <?php endif; ?>

    <!-- ── Toolbar ── -->
    <div class="shop-toolbar">
      <p class="shop-toolbar__count">
        <?php if ($totalProducts > 0): ?>
          Showing <?= min(($currentPage - 1) * $perPage + 1, $totalProducts) ?>–<?= min($currentPage * $perPage, $totalProducts) ?> of <?= (int)$totalProducts ?>
        <?php else: ?>
          No products found
        <?php endif; ?>
      </p>

      <div class="shop-toolbar__actions">
        <!-- Mobile filter toggle -->
        <button class="btn btn--outline btn--sm" id="filterToggle" aria-expanded="false" aria-controls="shopFilters">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
          Filters
        </button>

        <!-- Sort -->
        <form method="get" id="sortForm">
          <?php if ($activeDiets): ?><input type="hidden" name="dietary" value="<?= htmlspecialchars(implode(',', $activeDiets)) ?>" /><?php endif; ?>
          <?php if ($maxPrice): ?><input type="hidden" name="max_price" value="<?= $maxPrice ?>" /><?php endif; ?>
          <select name="sort" class="form-select form-select--sm" onchange="document.getElementById('sortForm').submit()">
            <option value="">Sort: Default</option>
            <option value="newest"     <?= $activeSort === 'newest'     ? 'selected' : '' ?>>Newest</option>
            <option value="price_asc"  <?= $activeSort === 'price_asc'  ? 'selected' : '' ?>>Price: Low → High</option>
            <option value="price_desc" <?= $activeSort === 'price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
            <option value="popular"    <?= $activeSort === 'popular'    ? 'selected' : '' ?>>Most Popular</option>
          </select>
        </form>
      </div>
    </div><!-- /.shop-toolbar -->

    <!-- ── Two-column: Filters + Grid ── -->
    <div class="shop-layout">

      <!-- Filters Aside 
      <aside class="shop-filters" id="shopFilters">
        <form method="get" id="filterForm">
          <?php if ($activeSort): ?><input type="hidden" name="sort" value="<?= $activeSort ?>" /><?php endif; ?>

         <div class="shop-filters__group">
            <h3 class="shop-filters__title">Dietary</h3>
            <?php foreach (['eggless' => 'Eggless', 'vegan' => 'Vegan', 'sugar_free' => 'Sugar Free', 'gluten_free' => 'Gluten Free'] as $val => $label): ?>
            <label class="checkbox-row">
              <input type="checkbox" name="dietary[]" value="<?= $val ?>"
                     <?= in_array($val, $activeDiets, true) ? 'checked' : '' ?>
                     onchange="document.getElementById('filterForm').submit()" />
              <span><?= $label ?></span>
            </label>
            <?php endforeach; ?>
          </div>

          <div class="shop-filters__group">
            <h3 class="shop-filters__title">Max Price</h3>
            <input type="range" name="max_price" min="200" max="8000" step="100"
                   value="<?= $maxPrice ?: 8000 ?>"
                   id="priceRange" oninput="document.getElementById('priceRangeVal').textContent=this.value" />
            <p class="shop-filters__range-val">Up to ₹<span id="priceRangeVal"><?= $maxPrice ?: 8000 ?></span></p>
            <button type="submit" class="btn btn--outline btn--sm" style="width:100%;margin-top:.5rem">Apply</button>
          </div>
          <?php if ($activeDiets || $maxPrice): ?>
          <a href="<?= $baseUrl ?>" class="shop-filters__clear">✕ Clear filters</a>
          <?php endif; ?>
        </form>
      </aside>
-->
      <!-- Product Grid -->
      <div class="shop-grid">
        <?php if (empty($products)): ?>
        <div class="empty-state" style="grid-column:1/-1">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="var(--color-pink-200)" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <p class="empty-state__title">No products found</p>
          <p class="empty-state__body">Try adjusting your filters or <a href="<?= $baseUrl ?>">viewing all</a>.</p>
        </div>
        <?php else: ?>
<?php foreach ($products as $p):

$rawThumb = trim((string)($p['thumb'] ?? $p['featured_image'] ?? ''));
if ($rawThumb === '') {
  $thumb = '/client/assets/images/hamper1.jpg';
} elseif (preg_match('#^https?://#i', $rawThumb)) {
  $thumb = $rawThumb;
} else {
  if ($rawThumb[0] !== '/') {
    $rawThumb = '/' . $rawThumb;
  }

  if (strpos($rawThumb, '/assets/') === 0) {
    $rawThumb = '/client' . $rawThumb;
  }

  $thumb = $rawThumb;
}

$name = htmlspecialchars((string)($p['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$slug = trim((string)($p['slug'] ?? ''));
$pSlug = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');
$minPrice = number_format((float)($p['min_price'] ?? $p['starting_price'] ?? 0));
$productUrl = '/product/' . rawurlencode($slug);
$isBest = !empty($p['is_bestseller']);
$isFeatured = !empty($p['is_featured']);
$dietary = strtolower(trim((string)($p['dietary_tag'] ?? '')));
$is_veg = (int)($p['is_veg'] ?? 1);
?>
        <article class="product-card" data-id="<?= (int)$p['id'] ?>">
      <a class="product-card__image-wrap" href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true">
             <img class="product-card__image"
     src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
     alt="<?= $name ?>"
     loading="lazy"
     width="400" height="400" />
            <?php if ($isBest): ?>
              <span class="product-card__badge product-card__badge--bestseller">Bestseller</span>
            <?php elseif ($isFeatured): ?>
              <span class="product-card__badge product-card__badge--featured">Featured</span>
            <?php endif; ?>
            <?php if ($dietary && $dietary !== 'regular'): ?>
              <span class="product-card__diet"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$dietary)), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          </a>
          <div class="product-card__body">
      <h3 class="product-card__name">
  <a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?= $name ?>
  </a>
</h3>
            <span class="veg-dot veg-dot--<?= $is_veg ? 'veg' : 'nonveg' ?>" title="<?= $is_veg ? 'Vegetarian' : 'Non-Vegetarian' ?>"></span>
            <p class="product-card__short-desc"><?= htmlspecialchars($p['short_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <div class="product-card__footer">
              <span class="product-card__price">From ₹<?= $minPrice ?></span>
           <a class="btn btn--primary btn--sm" href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>">View Details</a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>
      </div><!-- /.shop-grid -->

    </div><!-- /.shop-layout -->

    <!-- ── Pagination ── -->
    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Category pages">
      <?php if ($currentPage > 1): ?>
        <a class="pagination__btn" href="<?= $baseUrl ?>?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>">‹ Prev</a>
      <?php endif; ?>

      <?php
        $start = max(1, $currentPage - 2);
        $end   = min($totalPages, $currentPage + 2);
        for ($pg = $start; $pg <= $end; $pg++):
      ?>
        <a class="pagination__btn<?= $pg === $currentPage ? ' is-active' : '' ?>"
           href="<?= $baseUrl ?>?<?= http_build_query(array_merge($_GET, ['page' => $pg])) ?>"
           <?= $pg === $currentPage ? 'aria-current="page"' : '' ?>>
          <?= $pg ?>
        </a>
      <?php endfor; ?>

      <?php if ($currentPage < $totalPages): ?>
        <a class="pagination__btn" href="<?= $baseUrl ?>?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>">Next ›</a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>

  </div><!-- /.container -->
</main>

<script>
// Mobile filter panel toggle
(function () {
  const btn   = document.getElementById('filterToggle');
  const panel = document.getElementById('shopFilters');
  if (!btn || !panel) return;
  btn.addEventListener('click', () => {
    const open = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', !open);
    panel.classList.toggle('is-open', !open);
  });
})();
</script>
