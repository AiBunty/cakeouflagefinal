<?php
/**
 * Mobile drawer — single expandable category navigation
 * $navTree injected by View::render()
 */

declare(strict_types=1);

$base = '';

if (!function_exists('catUrl')) {
    function catUrl(string $slug): string
    {
        return \App\Services\CategoryService::categoryUrl($slug);
    }
}

$currentPath = $currentPath ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$navTree = $navTree ?? [];
$shopActive = $currentPath === '/shop' || $currentPath === '/category';
$customCakeActive = $currentPath === '/custom-cake-inquiry';
?>
<div class="mobile-drawer-backdrop" id="mobileBackdrop" aria-hidden="true"></div>

<aside class="mobile-drawer" id="mobileDrawer" aria-label="Mobile navigation" aria-hidden="true">
  <div class="mobile-drawer__panel">
    <div class="mobile-drawer__head">
    <a href="<?= $base ?>/" class="site-logo mobile-drawer__brand">
  <img src="<?= $base ?>/client/assets/images/mainlogo.svg" alt="Cakeouflage Logo" class="mobile-logo">
  
</a>
   
<button class="mobile-drawer__close" id="mobileClose" type="button" aria-label="Close menu">
        <svg viewBox="0 0 24 24" width="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <nav class="mobile-drawer__nav">
      <ul class="mobile-drawer__list">
        <li class="mobile-drawer__item">
<a href="<?= $base ?>/" class="mobile-drawer__link<?= $currentPath === '/' ? ' is-active' : '' ?>">Home</a>
        </li>
   <li class="mobile-drawer__item">
    <a href="<?= $base ?>/about" class="mobile-drawer__link<?= $currentPath === '/about' ? ' is-active' : '' ?>">About</a>
        </li>
        <?php if (!empty($navTree)): ?>
          <li class="mobile-drawer__item">
            <div class="mobile-acc">
              <button
                class="mobile-acc__toggle"
                type="button"
                aria-expanded="<?= $shopActive ? 'true' : 'false' ?>"
                aria-controls="mobileCategoriesPanel"
                data-accordion-trigger="mobileCategoriesPanel"
              >
                <span>Shop</span>
                <svg class="mobile-acc__chevron" viewBox="0 0 12 7" width="12" aria-hidden="true">
                  <path d="M1 1l5 5 5-5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                </svg>
              </button>

              <div class="mobile-acc__panel mobile-acc__panel--categories" id="mobileCategoriesPanel"<?= $shopActive ? '' : ' hidden' ?>>
                <?php foreach ($navTree as $root): ?>
                  <?php
                    $rootUrl = catUrl((string) $root['slug']);
                    $children = array_filter(
                      $root['children'] ?? [],
                      static function ($child): bool {
                        return is_array($child) && (bool) ($child['is_active'] ?? false);
                      }
                    );
                  ?>
                  <div class="mobile-category-group">
                    <a href="<?= $base . htmlspecialchars($rootUrl) ?>" class="mobile-category-group__heading">
                      <?= htmlspecialchars((string) $root['name']) ?>
                    </a>
                    <?php if (!empty($children)): ?>
                      <div class="mobile-category-group__links">
                        <?php foreach ($children as $child): ?>
                          <a href="<?= $base . htmlspecialchars(catUrl((string) $child['slug'])) ?>" class="mobile-category-group__link">
                            <?= htmlspecialchars((string) $child['name']) ?>
                          </a>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </li>
        <?php else: ?>
          <li class="mobile-drawer__item">
            <a href="<?= $base ?>/shop" class="mobile-drawer__link<?= $shopActive ? ' is-active' : '' ?>">Shop</a>
          </li>
        <?php endif; ?>

        <li class="mobile-drawer__item">
          <a href="<?= $base ?>/custom-cake-inquiry" class="mobile-drawer__link<?= $customCakeActive ? ' is-active' : '' ?>">Build Your Own Cake</a>
        </li>
     
        <li class="mobile-drawer__item">
            <a href="<?= $base ?>/events" class="mobile-drawer__link<?= strpos($currentPath, '/events') === 0 ? ' is-active' : '' ?>">Events</a>
        </li>
        <li class="mobile-drawer__item">
         <a href="<?= $base ?>/contact" class="mobile-drawer__link<?= $currentPath === '/contact' ? ' is-active' : '' ?>">Contact</a>
        </li>
      </ul>
    </nav>

    <div class="mobile-drawer__footer">
      <div class="mobile-drawer__utility-list">
      <a href="<?= $base ?>/account" class="mobile-drawer__utility-link">
  My Account
</a>
      <a href="<?= $base ?>/orders" class="mobile-drawer__utility-link">Track Order</a>
      </div>
      <a href="<?= $base ?>/cart" class="btn btn--primary btn--block mobile-drawer__cta">
        <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        View Cart (<span id="mobileCartCount">0</span>)
      </a>
     <a href="<?=$baseUrl?>/shop" class="nav-mega__view-all">Browse all products</a>
      <a href="<?= $base ?>/custom-cake-inquiry" class="btn btn--secondary btn--block mobile-drawer__cta">Build Your Own Cake</a>
    </div>
  </div>
</aside>
