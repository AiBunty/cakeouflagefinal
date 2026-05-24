<?php
$categories = is_array($categories ?? null) ? $categories : [];
$featuredCategories = is_array($featuredCategories ?? null) ? $featuredCategories : [];
?>

<main data-page="category-index">
  <section class="section">
    <div class="container">
      <nav class="breadcrumb" aria-label="Breadcrumb" style="margin-bottom:10px;">
        <a class="breadcrumb__link" href="/">Home</a>
        <span class="breadcrumb__sep" aria-hidden="true">›</span>
        <span class="breadcrumb__current" aria-current="page">Categories</span>
      </nav>

      <header style="margin-bottom:18px;">
        <h1 style="margin:0;color:#661624;">Browse Categories</h1>
        <p class="text-muted" style="margin-top:6px;">Find cakes by occasion, style, and dietary preference.</p>
      </header>

      <?php if (!empty($featuredCategories)): ?>
        <section style="margin-bottom:20px;">
          <h2 style="margin:0 0 10px;font-size:1.1rem;color:#7a0e1d;">Featured Collections</h2>
          <div class="shop-grid" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;">
            <?php foreach ($featuredCategories as $featured): ?>
              <a class="card" href="<?= htmlspecialchars(\App\Services\CategoryService::categoryUrl((string)($featured['slug'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" style="text-decoration:none;display:block;padding:14px;border-radius:14px;">
                <h3 style="margin:0 0 4px;color:#661624;"><?= htmlspecialchars((string)($featured['name'] ?? 'Category'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="text-muted" style="margin:0;font-size:0.85rem;"><?= htmlspecialchars((string)($featured['description'] ?? 'Explore collection'), ENT_QUOTES, 'UTF-8') ?></p>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (empty($categories)): ?>
        <div class="empty-state">
          <p class="empty-state__title">No categories available</p>
          <p class="empty-state__body">Please check back after categories are published.</p>
        </div>
      <?php else: ?>
        <section>
          <h2 style="margin:0 0 10px;font-size:1.1rem;color:#7a0e1d;">All Categories</h2>
          <div class="shop-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
            <?php foreach ($categories as $category): ?>
              <?php
                $href = \App\Services\CategoryService::categoryUrl((string)($category['slug'] ?? ''));
                $image = trim((string)($category['banner_image'] ?? $category['image'] ?? ''));
                if ($image !== '' && !preg_match('#^https?://#i', $image)) {
                  if ($image[0] !== '/') {
                    $image = '/' . $image;
                  }
                  if (strpos($image, '/uploads/') === 0) {
                    $image = '/public' . $image;
                  } elseif (strpos($image, '/assets/') === 0) {
                    $image = '/client' . $image;
                  }
                }
                $count = (int)($category['product_count'] ?? 0);
              ?>
              <a class="product-card" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" style="text-decoration:none;display:block;">
                <?php if ($image !== ''): ?>
                  <div class="product-card__image-wrap" style="height:180px;">
                    <img class="product-card__image" src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)($category['name'] ?? 'Category'), ENT_QUOTES, 'UTF-8') ?>" loading="lazy" width="360" height="180" style="object-fit:cover;" />
                  </div>
                <?php endif; ?>
                <div class="product-card__body">
                  <h3 class="product-card__name" style="margin:0;"><?= htmlspecialchars((string)($category['name'] ?? 'Category'), ENT_QUOTES, 'UTF-8') ?></h3>
                  <p class="text-muted" style="margin:6px 0 0;"><?= $count ?> products</p>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </section>
</main>
