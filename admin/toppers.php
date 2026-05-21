<?php
$pageTitle = "Cake Toppers";
include "layout.php";
require __DIR__ . '/includes/db.php';

function topper_h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// ── POST: add ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'add_topper') {
        $name      = trim((string)($_POST['name'] ?? ''));
        $price     = max(0.00, (float)($_POST['price'] ?? 0));
        $desc      = trim((string)($_POST['description'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($name !== '') {
            $stmt = $conn->prepare('INSERT INTO cake_toppers (name, price, description, sort_order) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sdsi', $name, $price, $desc, $sortOrder);
            $stmt->execute();
        }
        header('Location: toppers.php?saved=1');
        exit;
    }

    if ($action === 'update_topper') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim((string)($_POST['name'] ?? ''));
        $price     = max(0.00, (float)($_POST['price'] ?? 0));
        $desc      = trim((string)($_POST['description'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;
        if ($id > 0 && $name !== '') {
            $stmt = $conn->prepare('UPDATE cake_toppers SET name=?, price=?, description=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=? LIMIT 1');
            $stmt->bind_param('sdsiii', $name, $price, $desc, $sortOrder, $isActive, $id);
            $stmt->execute();
        }
        header('Location: toppers.php?saved=1');
        exit;
    }

    if ($action === 'delete_topper') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM cake_toppers WHERE id=? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }
        header('Location: toppers.php?deleted=1');
        exit;
    }

    if ($action === 'toggle_topper') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $tgl = $conn->prepare('UPDATE cake_toppers SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ? LIMIT 1');
            $tgl->bind_param('i', $id);
            $tgl->execute();
        }
        header('Location: toppers.php?saved=1');
        exit;
    }
}

// ── Fetch toppers ──────────────────────────────────────────────────────────
$toppers = [];
$res = $conn->query('SELECT * FROM cake_toppers ORDER BY sort_order ASC, id ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $toppers[] = $row;
    }
}

$flashSaved   = isset($_GET['saved']);
$flashDeleted = isset($_GET['deleted']);
$editId       = (int)($_GET['edit'] ?? 0);
$editTopper   = null;
foreach ($toppers as $t) {
    if ((int)$t['id'] === $editId) {
        $editTopper = $t;
        break;
    }
}
?>
<style>
.toppers-wrap { max-width: 900px; }
.flash { padding:10px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; }
.flash--success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.flash--error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.toppers-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.06); }
.toppers-table th { background:#80001F; color:#fff; padding:10px 14px; text-align:left; font-size:12px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; }
.toppers-table td { padding:10px 14px; border-bottom:1px solid #f3e8ec; font-size:13px; vertical-align:middle; }
.toppers-table tr:last-child td { border-bottom:none; }
.toppers-table tr:hover td { background:#fff8f9; }
.badge-active   { background:#dcfce7; color:#166534; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.badge-inactive { background:#fee2e2; color:#991b1b; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.btn-sm { padding:5px 12px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; border:none; }
.btn-edit   { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.btn-toggle { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
.btn-delete { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.btn-primary { background:#80001F; color:#fff; padding:10px 22px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; }
.card { background:#fff; border-radius:12px; padding:24px; box-shadow:0 4px 16px rgba(0,0,0,.06); margin-bottom:24px; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.form-group { display:flex; flex-direction:column; gap:6px; }
.form-group label { font-size:12px; font-weight:600; color:#5f4c55; }
.form-group input, .form-group textarea, .form-group select { padding:8px 12px; border:1px solid #e5d8dc; border-radius:8px; font-size:13px; font-family:inherit; outline:none; }
.form-group input:focus, .form-group textarea:focus { border-color:#80001F; box-shadow:0 0 0 3px rgba(128,0,31,.1); }
.form-actions { display:flex; gap:10px; align-items:center; margin-top:4px; }
.section-title { font-size:18px; font-weight:700; color:#2d1f25; margin:0 0 18px; }
</style>

<div class="toppers-wrap">
  <h2 style="margin-bottom:20px;">🎀 Cake Toppers Manager</h2>

  <?php if ($flashSaved): ?>
    <div class="flash flash--success">✓ Topper saved successfully.</div>
  <?php endif; ?>
  <?php if ($flashDeleted): ?>
    <div class="flash flash--success">✓ Topper deleted.</div>
  <?php endif; ?>

  <!-- Add / Edit form -->
  <div class="card">
    <div class="section-title"><?= $editTopper ? '✏ Edit Topper' : '➕ Add New Topper' ?></div>
    <form method="POST">
      <input type="hidden" name="action" value="<?= $editTopper ? 'update_topper' : 'add_topper' ?>">
      <?php if ($editTopper): ?>
        <input type="hidden" name="id" value="<?= (int)$editTopper['id'] ?>">
      <?php endif; ?>

      <div class="form-grid">
        <div class="form-group">
          <label>Topper Name *</label>
          <input type="text" name="name" required maxlength="100"
                 value="<?= $editTopper ? topper_h((string)$editTopper['name']) : '' ?>"
                 placeholder="e.g. Happy Birthday">
        </div>
        <div class="form-group">
          <label>Price Add-on (₹) — use 0 for free</label>
          <input type="number" name="price" step="0.01" min="0"
                 value="<?= $editTopper ? number_format((float)$editTopper['price'], 2, '.', '') : '0.00' ?>">
        </div>
        <div class="form-group">
          <label>Description <span style="font-weight:400;opacity:.6">(optional)</span></label>
          <input type="text" name="description" maxlength="200"
                 value="<?= $editTopper ? topper_h((string)($editTopper['description'] ?? '')) : '' ?>"
                 placeholder="Brief note for admin reference">
        </div>
        <div class="form-group">
          <label>Sort Order <span style="font-weight:400;opacity:.6">(lower = first in dropdown)</span></label>
          <input type="number" name="sort_order" min="0"
                 value="<?= $editTopper ? (int)$editTopper['sort_order'] : '0' ?>">
        </div>
        <?php if ($editTopper): ?>
        <div class="form-group" style="justify-content:flex-end;padding-top:8px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1"
              <?= (int)($editTopper['is_active'] ?? 1) ? 'checked' : '' ?>> Active
          </label>
        </div>
        <?php endif; ?>
      </div>

      <div class="form-actions" style="margin-top:16px;">
        <button type="submit" class="btn-primary">
          <?= $editTopper ? '💾 Update Topper' : '➕ Add Topper' ?>
        </button>
        <?php if ($editTopper): ?>
          <a href="toppers.php" style="font-size:13px;color:#80001F;text-decoration:none;">✕ Cancel edit</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Toppers list -->
  <div class="card">
    <div class="section-title">All Toppers (<?= count($toppers) ?>)</div>
    <?php if (empty($toppers)): ?>
      <p style="color:#8b5c67;font-size:13px;">No toppers yet. Add one above.</p>
    <?php else: ?>
    <table class="toppers-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Price Add-on</th>
          <th>Description</th>
          <th>Sort</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($toppers as $t): ?>
        <tr>
          <td><?= (int)$t['id'] ?></td>
          <td><strong><?= topper_h((string)$t['name']) ?></strong></td>
          <td>₹<?= number_format((float)$t['price'], 2) ?></td>
          <td style="color:#7a6870;max-width:200px;"><?= topper_h((string)($t['description'] ?? '')) ?></td>
          <td><?= (int)$t['sort_order'] ?></td>
          <td>
            <?php if ((int)$t['is_active']): ?>
              <span class="badge-active">Active</span>
            <?php else: ?>
              <span class="badge-inactive">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
              <a href="toppers.php?edit=<?= (int)$t['id'] ?>" class="btn-sm btn-edit">✏ Edit</a>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle_topper">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn-sm btn-toggle">
                  <?= (int)$t['is_active'] ? '⏸ Disable' : '▶ Enable' ?>
                </button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete topper «<?= topper_h((string)$t['name']) ?>»? This cannot be undone.')">
                <input type="hidden" name="action" value="delete_topper">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn-sm btn-delete">🗑 Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="card" style="font-size:12px;color:#7a6870;background:#fffaf9;">
    <strong>💡 How it works:</strong><br>
    Active toppers appear in the "Select Your Topper" dropdown on each product page (for products with Topper Enabled = ON).<br>
    Set a price add-on greater than ₹0 to charge customers for premium toppers.<br>
    Toppers with Sort Order 0 appear first. "No Topper" at sort 0 is the safe default.
  </div>
</div>
