<?php if (is_array($breadcrumbs) && count($breadcrumbs) > 0): ?>
<section class="page-banner">
  <div class="container">
    <?php if (!empty($data['pageTitle'])): ?>
      <h1 class="page-banner__title"><?= htmlspecialchars((string)$data['pageTitle'], ENT_QUOTES, 'UTF-8') ?></h1>
    <?php endif; ?>
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="/" class="breadcrumb__item">Home</a>
      <?php foreach ($breadcrumbs as $crumb): ?>
        <?php if (isset($crumb['href'])): ?>
          <a href="<?= htmlspecialchars((string)$crumb['href'], ENT_QUOTES, 'UTF-8') ?>" class="breadcrumb__item">
            <?= htmlspecialchars((string)$crumb['label'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php else: ?>
          <span class="breadcrumb__item breadcrumb__item--active" aria-current="page">
            <?= htmlspecialchars((string)$crumb['label'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>
</section>
<?php endif; ?>