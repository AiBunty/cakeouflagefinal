<main class="section">
  <div class="container card">
    <h1><?= htmlspecialchars((string)($pageTitle ?? 'Page'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p>This page is scaffolded and ready for feature-specific implementation.</p>
    <p>Current route: <strong><?= htmlspecialchars((string)($pagePath ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></p>
  </div>
</main>
