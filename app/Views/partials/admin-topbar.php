<div class="admin-main">
  <header class="admin-topbar">
    <div>
      <h1><?= htmlspecialchars((string)($adminTitle ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="text-muted"><?= htmlspecialchars((string)($adminSubtitle ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="admin-topbar__actions">
      <button id="adminLogoutBtn" class="btn btn--secondary" type="button">Logout</button>
    </div>
  </header>
  <div class="admin-content">
