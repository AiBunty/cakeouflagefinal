<?php
$adminLinks = [
    ['href' => '/admin/dashboard', 'label' => 'Dashboard'],
    ['href' => '/admin/products', 'label' => 'Products'],
    ['href' => '/admin/categories', 'label' => 'Categories'],
    ['href' => '/admin/courses', 'label' => 'Courses'],
    ['href' => '/admin/events', 'label' => 'Events'],
    ['href' => '/admin/bulk-import', 'label' => 'Bulk Import'],
    ['href' => '/admin/media', 'label' => 'Media Manager'],
    ['href' => '/admin/orders', 'label' => 'Orders'],
    ['href' => '/admin/finance', 'label' => 'Finance Dashboard'],
    ['href' => '/admin/invoices', 'label' => 'Invoices'],
    ['href' => '/admin/communications', 'label' => 'Communications'],
    ['href' => '/admin/whatsapp/meta-integration', 'label' => 'Meta Integration'],
    ['href' => '/admin/whatsapp/templates', 'label' => 'WA Templates'],
    ['href' => '/admin/whatsapp/mappings', 'label' => 'WA Mappings'],
    ['href' => '/admin/whatsapp/logs', 'label' => 'WA Logs'],
    ['href' => '/admin/automation', 'label' => 'Automation'],
    ['href' => '/admin/birthdays', 'label' => 'Birthdays'],
    ['href' => '/admin/customers', 'label' => 'Customers'],
    ['href' => '/admin/b2b-accounts', 'label' => 'B2B Accounts'],
    ['href' => '/admin/b2b-quotes', 'label' => 'B2B Quotes'],
    ['href' => '/admin/b2b-orders', 'label' => 'B2B Orders'],
    ['href' => '/admin/banners', 'label' => 'Banners'],
    ['href' => '/admin/content', 'label' => 'Content'],
    ['href' => '/admin/reports', 'label' => 'Reports'],
];
$currentPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/admin/dashboard'), PHP_URL_PATH);
?>
<aside class="admin-sidebar">
  <a class="admin-sidebar__brand" href="/admin/dashboard">
    <span>Cakeouflage</span>
    <small>Admin Panel</small>
  </a>
  <nav class="admin-sidebar__nav" aria-label="Admin navigation">
    <?php foreach ($adminLinks as $link): ?>
      <?php $isActive = $currentPath === $link['href']; ?>
      <a class="admin-sidebar__link<?= $isActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>
