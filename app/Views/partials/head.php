<!DOCTYPE html>
<html lang="en">
<head>
  <?php
  $configuredBase = trim((string) \App\Core\Env::get('APP_URL', ''));
  if ($configuredBase !== '') {
    $canonicalBase = rtrim($configuredBase, '/');
  } elseif (!empty($_SERVER['HTTP_HOST'])) {
    $canonicalBase = 'https://' . $_SERVER['HTTP_HOST'];
  } else {
    $canonicalBase = 'https://cakeouflage.com';
  }

  $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
  if ($requestPath === '/index.php') {
    $requestPath = '/';
  }
  if ($requestPath !== '/') {
    $requestPath = rtrim($requestPath, '/');
    if ($requestPath === '') {
      $requestPath = '/';
    }
  }

  $isCategoryRoute = $requestPath === '/category' || strpos($requestPath, '/category/') === 0;

  $canonicalUrl = $canonicalBase . ($requestPath === '/' ? '/' : $requestPath);
  ?>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="Cakeouflage premium bakery in Nashik. Shop cakes, gifting hampers, and cake-making workshops." />
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="csrf-token" content="<?= htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
  <script>
  window.__csrf = "<?= htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8') ?>";
</script>
<script>
  window.BASE_URL = "";
</script>
  <meta property="og:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:description" content="We bake sweet wonderful happy memories" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-7YYBY657RT"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-7YYBY657RT');
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="icon" type="image/svg+xml" href="/client/assets/images/mainlogo.svg" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Cormorant+Garamond:ital@0;1&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
 <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
 <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/client/assets/css/tokens.css" />
<link rel="stylesheet" href="/client/assets/css/base.css" />
<link rel="stylesheet" href="/client/assets/css/layout.css" />
<link rel="stylesheet" href="/client/assets/css/components.css" />
<link rel="stylesheet" href="/client/assets/css/pages.css?v=<?= filemtime(__DIR__ . '/../../../client/assets/css/pages.css') ?>" />
<link rel="stylesheet" href="/client/assets/css/responsive.css" />
<link rel="stylesheet" href="/client/assets/css/customer-dashboard.css?v=<?= filemtime(__DIR__ . '/../../../client/assets/css/customer-dashboard.css') ?>" />
<?php if ($isCategoryRoute): ?>
<link rel="preload" as="image" href="/client/assets/images/category/hero-bg.webp" fetchpriority="high" />
<link rel="preload" as="image" href="/client/assets/images/category/hero-bg-mobile.webp" media="(max-width: 768px)" />
<link rel="stylesheet" href="/client/category/category.css?v=<?= filemtime(__DIR__ . '/../../../client/category/category.css') ?>" />
<link rel="stylesheet" href="/client/assets/css/category-hero-v2.css?v=<?= filemtime(__DIR__ . '/../../../client/assets/css/category-hero-v2.css') ?>" />
<?php endif; ?>
</head>
<body>
