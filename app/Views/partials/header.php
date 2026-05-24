<?php
/**
 * Site header — consolidated desktop nav + single category mega menu
 * $navTree is injected by View::render() via CategoryService::getNavTree()
 */
declare(strict_types=1);

use App\Services\CategoryService;

if (!function_exists('catUrl')) {
    function catUrl(string $slug): string
    {
        return CategoryService::categoryUrl($slug);
    }
}

if (!function_exists('navNodeHasActivePath')) {
    /**
     * @param array<string, mixed> $node
     */
    function navNodeHasActivePath(array $node, string $currentPath): bool
    {
        $nodeUrl = parse_url(catUrl((string) ($node['slug'] ?? '')), PHP_URL_PATH) ?: '/';
        if ($currentPath === $nodeUrl || ($nodeUrl !== '/' && strpos($currentPath, rtrim($nodeUrl, '/') . '/') === 0)) {
            return true;
        }

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child) && navNodeHasActivePath($child, $currentPath)) {
                return true;
            }
        }

        return false;
    }
}

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$baseUrl= '';
$currentPath =  str_replace($baseUrl, '' , $currentPath);

/** @var list<array<string,mixed>> $navTree */
$navTree = $navTree ?? [];
$shopActive = strpos($currentPath, '/shop') === 0 || strpos($currentPath, '/category') === 0;
foreach ($navTree as $root) {
    if (navNodeHasActivePath($root, $currentPath)) {
        $shopActive = true;
        break;
    }
}

$customCakeActive = $currentPath === '/custom-cake-inquiry';
?>


<header class="site-header" id="siteHeader">
  <div class="site-header__inner container">
  <a href="<?= $baseUrl ?>/" class="site-logo" aria-label="Cakeouflage home">
<img src="<?= htmlspecialchars($siteConfig['navbar_logo_url'] ?? '/client/assets/images/mainlogo.svg', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brand['name'] ?? 'Cakeouflage', ENT_QUOTES, 'UTF-8') ?> Logo">
</a>

    <nav class="site-nav" aria-label="Primary navigation">
      <ul class="site-nav__list">
        <li class="site-nav__item">
          <a href="<?=$baseUrl?>/" class="site-nav__link<?= $currentPath === '/' ? ' is-active' : '' ?>">Home</a>
        </li>
      
           <li class="site-nav__item">
          <a href="<?=$baseUrl?>/about" class="site-nav__link<?= $currentPath === '/about' ? ' is-active' : '' ?>">About</a>
        </li>
        <?php if (!empty($navTree)): ?>
          <li class="site-nav__item site-nav__item--mega has-mega">
            <button
              type="button"
              class="site-nav__link site-nav__toggle<?= $shopActive ? ' is-active' : '' ?>"
              id="desktopCategoriesToggle"
              aria-expanded="false"
              aria-haspopup="true"
              aria-controls="desktopCategoriesMenu"
            >
              Shop
              <svg class="nav-chevron" viewBox="0 0 12 7" width="10" aria-hidden="true">
                <path d="M1 1l5 5 5-5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
              </svg>
            </button>

            <div class="nav-mega" id="desktopCategoriesMenu" role="region" aria-label="Category navigation">
              <div class="nav-mega__inner">
                <div class="nav-mega__content">
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
                    <div class="nav-mega__col">
                      <a href="<?=$baseUrl . htmlspecialchars($rootUrl) ?>" class="nav-mega__heading">
                        <?= htmlspecialchars((string) $root['name']) ?>
                      </a>

                      <?php if (!empty($children)): ?>
                        <ul class="nav-mega__links">
                          <?php foreach ($children as $child):?>
                            <li>
                              <a href="<?=$baseUrl . htmlspecialchars(catUrl((string) $child['slug'])) ?>" class="nav-mega__link">
                                <?= htmlspecialchars((string) $child['name']) ?>
                              </a>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?>
                        <a href="<?= $baseUrl . htmlspecialchars($rootUrl) ?>" class="nav-mega__link">Browse category</a>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="nav-mega__footer">
                  <a href="<?=$baseUrl?>/category" class="nav-mega__view-all">Browse all products</a>
                  <a href="<?=$baseUrl?>/custom-cake-inquiry" class="btn btn--sm btn--primary">Build Your Own Cake</a>
                </div>
              </div>
            </div>
          </li>
        <?php else: ?>
          <li class="site-nav__item">
            <a href="<?=$baseUrl?>/category" class="site-nav__link<?= $shopActive ? ' is-active' : '' ?>">Shop</a>
          </li>
        <?php endif; ?>

        <li class="site-nav__item">
          <a href="<?=$baseUrl?>/custom-cake-inquiry" class="site-nav__link<?= $customCakeActive ? ' is-active' : '' ?>">Build Your Own Cake</a>
        </li>
      
       
        <li class="site-nav__item">
          <a href="<?=$baseUrl?>/contact" class="site-nav__link<?= $currentPath === '/contact' ? ' is-active' : '' ?>">Contact</a>
        </li>
      </ul>
    </nav>

    <div class="site-header__actions">
    
      
     
      
      <a href="<?= $baseUrl ?>/account" class="header-action-btn header-action-btn--desktop-only" aria-label="Account">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </a>
      <a href="<?= $baseUrl ?>/cart" class="header-action-btn header-cart-btn" aria-label="Cart">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <span class="cart-bubble" id="cartCount">0</span>
      </a>
      <a href="<?= $baseUrl ?>/category" class="btn btn--sm btn--primary header-cta">Order Now</a>
      <button class="header-action-btn mobile-menu-toggle" id="mobileMenuToggle" type="button" aria-label="Open menu" aria-expanded="false">
        <span class="hamburger-icon" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
        </span>
      </button>
    </div>
  </div>

  <div class="search-overlay" id="searchOverlay" role="dialog" aria-modal="true" aria-hidden="true" hidden>
    <div class="search-overlay__inner container">
      <form class="search-overlay__form" action="<?=$baseUrl?>/category" method="get" role="search">
        <input
          type="search"
          name="q"
          id="searchInput"
          class="search-overlay__input"
          placeholder="Search for cakes, cupcakes, hampers..."
          autocomplete="off"
          aria-label="Search products"
        >
        <button type="submit" class="search-overlay__submit" aria-label="Submit search">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        </button>
        <button type="button" class="search-overlay__close" id="searchClose" aria-label="Close search">×</button>
      </form>
    </div>
  </div>
</header>

<?php include __DIR__ . '/top-offer-banner.php'; ?>
