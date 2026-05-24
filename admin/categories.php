<?php
// Auth must load before the POST handler so $conn is available and the
// admin session is validated before any form processing occurs.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../app/Services/UnifiedMediaService.php';

function cat_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cat_slugify(string $name): string {
  $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
  return $slug !== '' ? $slug : 'category';
}

function cat_unique_slug(mysqli $conn, string $baseSlug, int $categoryId): string {
  $slug = $baseSlug;
  $suffix = 2;
  while (true) {
    $check = safePrepare($conn, 'SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1');
    $check->bind_param('si', $slug, $categoryId);
    $check->execute();
    $result = $check->get_result();
    $exists = $result ? $result->fetch_assoc() : null;
    if (!$exists) {
      return $slug;
    }
    $slug = $baseSlug . '-' . $suffix;
    $suffix++;
  }
}

function cat_placeholder_image(): string {
  return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="58" height="58" viewBox="0 0 58 58"><rect width="58" height="58" rx="10" fill="%23fdf0f3"/><path d="M14 37l10-10 8 7 6-6 8 9H14z" fill="%23d9a7b6"/><text x="29" y="18" text-anchor="middle" font-size="9" fill="%23b89ca5" font-family="Arial">No Img</text></svg>';
}

function cat_resolve_image_url(string $path): string {
  $value = trim($path);
  if ($value === '') {
    return cat_placeholder_image();
  }
  if (preg_match('#^(data:|https?://)#i', $value)) {
    return $value;
  }

  $normalized = preg_replace('#^/Cakeouflage-E-commerce#', '', $value);
  if (strpos($normalized, '/assets/') === 0) {
    $normalized = '/client' . $normalized;
  } elseif ($normalized !== '' && $normalized[0] !== '/') {
    $normalized = '/client/assets/images/categories/' . ltrim($normalized, '/');
  }

  $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
  $absolutePath = $documentRoot !== '' ? $documentRoot . $normalized : '';
  if ($absolutePath !== '' && is_file($absolutePath)) {
    return $normalized;
  }

  return cat_placeholder_image();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_category') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim((string)($_POST['name'] ?? ''));
  $parentIdRaw = trim((string)($_POST['parent_id'] ?? ''));
  $parentId = $parentIdRaw !== '' ? (int)$parentIdRaw : null;
  $sortOrder = (int)($_POST['sort_order'] ?? 0);
  $isActive = isset($_POST['is_active']) ? 1 : 0;
  $currentImage = trim((string)($_POST['current_image'] ?? ''));

  if ($id <= 0 || $name === '') {
    header('Location: categories.php?updated=0');
    exit;
  }

  if ($parentId !== null && $parentId === $id) {
    $parentId = null;
  }

  $newImagePath = $currentImage !== '' ? $currentImage : null;
  if (!empty($_FILES['image']['name']) && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $upload = \App\Services\UnifiedMediaService::upload(
      $_FILES['image'],
      [
        'module' => 'category',
        'entity_type' => 'category',
        'entity_id' => $id,
        'admin_id' => (int)($_SESSION['admin_id'] ?? 0),
        'allow_svg' => false,
        'replace_paths' => $currentImage !== '' ? [$currentImage] : [],
      ]
    );
    if ($upload['ok']) {
      $newImagePath = $upload['relative_url'];
    } else {
      error_log('[categories.php] category image upload failed: ' . $upload['error']);
    }
  }

  $baseSlug = cat_slugify($name);
  $slug = cat_unique_slug($conn, $baseSlug, $id);

  try {
    $stmt = safePrepare($conn, 'UPDATE categories SET name = ?, slug = ?, parent_id = ?, image = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    $stmt->bind_param('ssisiii', $name, $slug, $parentId, $newImagePath, $sortOrder, $isActive, $id);
    $ok = $stmt->execute();
  } catch (RuntimeException $e) {
    header('Location: categories.php?updated=0');
    exit;
  }

  header('Location: categories.php?updated=' . ($ok ? '1' : '0'));
  exit;
}

// All POST processing is done — now it is safe to output HTML.
$pageTitle = "Categories";
require_once __DIR__ . '/layout.php';

$flashUpdated = isset($_GET['updated']) ? (int)$_GET['updated'] : null;
$focusCategoryId = isset($_GET['focus']) ? (int)$_GET['focus'] : 0;
// Fetch all categories
$result = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");

// store in array
$categories = [];
while($row = $result->fetch_assoc()){
    $categories[] = $row;
}

function renderParentOptions(array $categories, $selected = null, $parent_id = NULL, $level = 0): void {
  foreach ($categories as $cat) {
    if ($cat['parent_id'] == $parent_id) {
      $indent = str_repeat('-- ', $level);
      $isSelected = ((string)$selected !== '' && (int)$selected === (int)$cat['id']) ? 'selected' : '';
      echo '<option value="' . (int)$cat['id'] . '" ' . $isSelected . '>' . cat_h($indent . (string)$cat['name']) . '</option>';
      renderParentOptions($categories, $selected, $cat['id'], $level + 1);
    }
  }
}

// Check if a category has children
function hasChildren($categories, $parent_id) {
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parent_id) return true;
    }
    return false;
}

// function to render tree
function renderCategories($categories, $parent_id = NULL, $level = 0) {
    foreach ($categories as $cat) {

        if ($cat['parent_id'] == $parent_id) {

            // indent
            $indent = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $level);

            $isParent = ($level == 0);
            $hasKids  = hasChildren($categories, $cat['id']);
            $rowClass = $isParent ? 'cat-row cat-row--parent' : 'cat-row cat-row--child';
            $dataAttr = (!$isParent && $parent_id !== NULL) ? "data-parent-id=\"{$parent_id}\"" : '';
            $dataId   = "data-cat-id=\"{$cat['id']}\"";

            echo "<tr class=\"{$rowClass}\" {$dataId} {$dataAttr}>";

            // ID
            echo "<td class=\"cat-id\">{$cat['id']}</td>";

            // Name with tree indent
            echo "<td class=\"cat-name\">";
            if ($level > 0) {
                echo "<span class=\"cat-connector\"></span>";
            }
            if ($hasKids) {
                echo "<button class=\"cat-toggle\" data-target=\"{$cat['id']}\" title=\"Toggle children\">
                        <svg width='12' height='12' viewBox='0 0 12 12' fill='none'>
                          <path d='M2 4L6 8L10 4' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/>
                        </svg>
                      </button>";
            }
            if ($isParent) {
                echo "<span class=\"cat-label cat-label--parent\">" . cat_h((string)$cat['name']) . "</span>";
            } else {
                echo "<span class=\"cat-label cat-label--child\">" . cat_h((string)$cat['name']) . "</span>";
            }
            echo "</td>";

            // Image
            echo "<td class=\"cat-img-cell\">";
            if ($cat['image']) {
              echo "<div class=\"cat-img-wrap\"><img src='" . cat_h(cat_resolve_image_url((string)$cat['image'])) . "' alt='" . cat_h((string)$cat['name']) . "'></div>";
            } else {
                echo "<div class=\"cat-img-placeholder\"><svg width='20' height='20' viewBox='0 0 24 24' fill='none'><rect x='3' y='3' width='18' height='18' rx='4' stroke='#c4b0b7' stroke-width='1.5'/><path d='M3 16l5-5 4 4 3-3 6 6' stroke='#c4b0b7' stroke-width='1.5' stroke-linecap='round'/><circle cx='8.5' cy='8.5' r='1.5' fill='#c4b0b7'/></svg><span>No Image</span></div>";
            }
            echo "</td>";

            // Actions

            $isActive = $cat['is_active'] ? 'checked' : '';
            echo "<td class=\"cat-actions\">
            
                    <label style='display:flex;align-items:center;gap:5px;'>
            <input type='checkbox' class='toggle-status' data-id='{$cat['id']}' $isActive>
            <span style='font-size:12px;'>Active</span>
        </label>
                    <button type='button' class='cat-btn cat-btn--edit js-cat-edit'
                      data-id='{$cat['id']}'
                      data-name='" . cat_h((string)$cat['name']) . "'
                      data-parent-id='" . (int)($cat['parent_id'] ?? 0) . "'
                      data-sort-order='" . (int)($cat['sort_order'] ?? 0) . "'
                      data-is-active='" . (int)($cat['is_active'] ?? 0) . "'
                      data-image='" . cat_h((string)($cat['image'] ?? '')) . "'
                      data-preview-image='" . cat_h(cat_resolve_image_url((string)($cat['image'] ?? ''))) . "'>
                      <svg width='13' height='13' viewBox='0 0 24 24' fill='none'><path d='M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7' stroke='currentColor' stroke-width='2' stroke-linecap='round'/><path d='M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z' stroke='currentColor' stroke-width='2' stroke-linecap='round'/></svg>
                      Edit
                    </button>
                    <a href='delete-category.php?id={$cat['id']}' class='cat-btn cat-btn--delete' onclick='return confirm(\"Delete this category?\")'>
                      <svg width='13' height='13' viewBox='0 0 24 24' fill='none'><polyline points='3 6 5 6 21 6' stroke='currentColor' stroke-width='2' stroke-linecap='round'/><path d='M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6' stroke='currentColor' stroke-width='2' stroke-linecap='round'/><path d='M10 11v6M14 11v6' stroke='currentColor' stroke-width='2' stroke-linecap='round'/><path d='M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2' stroke='currentColor' stroke-width='2'/></svg>
                      Delete
                    </a>
                  </td>";

            echo "</tr>";

            // recursive call for child
            renderCategories($categories, $cat['id'], $level + 1);
        }
    }
}
?>

<style>
/* ── Page Layout ─────────────────────────────────────── */
.cat-page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 28px;
}
.cat-page-title {
  font-family: 'DM Serif Display', serif;
  font-size: 1.75rem;
  color: #80001F;
  margin: 0;
  line-height: 1.2;
}
.cat-page-subtitle {
  font-size: 0.82rem;
  color: #a07585;
  margin: 4px 0 0;
  font-family: 'Poppins', sans-serif;
}
.cat-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: linear-gradient(135deg, #80001F 0%, #a3002a 100%);
  color: #fff;
  padding: 10px 20px;
  border-radius: 12px;
  text-decoration: none;
  font-family: 'Poppins', sans-serif;
  font-size: 0.88rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  box-shadow: 0 4px 14px rgba(128,0,31,0.28);
  transition: transform 0.22s ease, box-shadow 0.22s ease, background 0.22s ease;
}
.cat-add-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 7px 20px rgba(128,0,31,0.38);
  background: linear-gradient(135deg, #6b0019 0%, #8c0021 100%);
}

/* ── Card ────────────────────────────────────────────── */
.cat-card {
  background: #fff;
  border-radius: 20px;
  border: 1px solid rgba(128,0,31,0.08);
  box-shadow: 0 4px 24px rgba(128,0,31,0.07), 0 1px 4px rgba(0,0,0,0.04);
  overflow: hidden;
}

/* ── Table ───────────────────────────────────────────── */
.cat-table-wrap {
  overflow-x: auto;
}
.cat-table {
  width: 100%;
  border-collapse: collapse;
  font-family: 'Poppins', sans-serif;
  font-size: 0.88rem;
}
.cat-table thead tr {
  background: linear-gradient(90deg, #80001F 0%, #a3002a 100%);
}
.cat-table thead th {
  color: #fff;
  font-weight: 600;
  font-size: 0.78rem;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  padding: 14px 20px;
  text-align: left;
  white-space: nowrap;
}

/* ── Rows ────────────────────────────────────────────── */
.cat-row {
  border-bottom: 1px solid #fbe8ed;
  transition: background 0.2s ease;
}
.cat-row--parent {
  background: #fffafb;
}
.cat-row--child {
  background: #fff;
}
.cat-row:nth-child(even) {
  background: #fff8fa;
}
.cat-row--child:nth-child(even) {
  background: #fff5f8;
}
.cat-row:hover {
  background: #fdeef2 !important;
}
.cat-row td {
  padding: 13px 20px;
  vertical-align: middle;
  color: #3d2030;
}

/* ── ID cell ─────────────────────────────────────────── */
.cat-id {
  font-weight: 600;
  color: #a07585 !important;
  font-size: 0.82rem;
  width: 60px;
}

/* ── Name cell ───────────────────────────────────────── */
.cat-name {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 200px;
}
.cat-connector {
  display: inline-block;
  width: 20px;
  height: 2px;
  background: linear-gradient(90deg, rgba(128,0,31,0.25) 0%, rgba(128,0,31,0.08) 100%);
  border-radius: 2px;
  flex-shrink: 0;
  margin-left: 12px;
}
.cat-label--parent {
  font-weight: 700;
  font-size: 0.93rem;
  color: #80001F;
  letter-spacing: 0.01em;
}
.cat-label--child {
  font-weight: 500;
  color: #5a3040;
  font-size: 0.88rem;
}

/* ── Toggle button ───────────────────────────────────── */
.cat-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: rgba(128,0,31,0.08);
  border: 1px solid rgba(128,0,31,0.18);
  border-radius: 6px;
  cursor: pointer;
  color: #80001F;
  padding: 0;
  transition: background 0.2s, transform 0.25s ease;
  flex-shrink: 0;
}
.cat-toggle:hover {
  background: rgba(128,0,31,0.16);
}
.cat-toggle.is-collapsed svg {
  transform: rotate(-90deg);
}
.cat-toggle svg {
  transition: transform 0.25s ease;
}

/* ── Image cell ──────────────────────────────────────── */
.cat-img-cell {
  width: 80px;
}
.cat-img-wrap {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  overflow: hidden;
  border: 2px solid #f5d6de;
  box-shadow: 0 2px 8px rgba(128,0,31,0.1);
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.cat-img-wrap:hover {
  transform: scale(1.12);
  box-shadow: 0 5px 16px rgba(128,0,31,0.2);
}
.cat-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.cat-img-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  width: 50px;
  height: 50px;
  border-radius: 10px;
  background: #fdf0f3;
  border: 1.5px dashed #e0b0bc;
  justify-content: center;
}
.cat-img-placeholder span {
  font-size: 0.6rem;
  color: #c4a0ab;
  text-align: center;
  line-height: 1;
}

/* ── Action buttons ──────────────────────────────────── */
.cat-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  white-space: nowrap;
}
.cat-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 13px;
  border: 0;
  background: transparent;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  font-family: 'Poppins', sans-serif;
  text-decoration: none;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}
.cat-btn--edit {
  background: transparent;
  color: #80001F;
  border: 1.5px solid rgba(128,0,31,0.35);
}
.cat-btn--edit:hover {
  background: rgba(128,0,31,0.08);
  border-color: #80001F;
  transform: translateY(-1px);
}
.cat-btn--delete {
  background: transparent;
  color: #c0392b;
  border: 1.5px solid rgba(192,57,43,0.35);
}
.cat-btn--delete:hover {
  background: rgba(192,57,43,0.08);
  border-color: #c0392b;
  transform: translateY(-1px);
}

/* ── Empty state ─────────────────────────────────────── */
.cat-empty {
  text-align: center;
  padding: 48px 20px;
  color: #b09098;
  font-size: 0.9rem;
}

.cat-flash {
  margin-bottom: 12px;
  border-radius: 12px;
  padding: 10px 12px;
  font-size: 0.84rem;
  font-weight: 600;
}
.cat-flash--ok {
  background: #ecfdf3;
  color: #166534;
  border: 1px solid #bbf7d0;
}
.cat-flash--error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.cat-editor {
  display: none;
  margin: 0;
  background: #fff;
  border: 1px solid rgba(128,0,31,0.12);
  border-radius: 16px;
  box-shadow: 0 8px 20px rgba(128,0,31,0.08);
}
.cat-editor.is-open {
  display: block;
}
.cat-editor__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  border-bottom: 1px solid rgba(128,0,31,0.08);
  background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
}
.cat-editor__title {
  margin: 0;
  font-family: 'DM Serif Display', serif;
  color: #80001F;
  font-size: 1.2rem;
}
.cat-editor__back {
  border: 1px solid rgba(128,0,31,0.24);
  background: #fff;
  color: #80001F;
  border-radius: 10px;
  padding: 7px 12px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}
.cat-editor__body {
  padding: 14px 16px 16px;
  display: grid;
  gap: 12px;
}
.cat-editor-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.cat-editor-field {
  display: grid;
  gap: 6px;
}
.cat-editor-field label {
  font-size: 0.74rem;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  font-weight: 700;
  color: #80001F;
}
.cat-editor-field input,
.cat-editor-field select {
  border: 1px solid rgba(128,0,31,0.18);
  border-radius: 10px;
  padding: 9px 11px;
  font: inherit;
}
.cat-editor-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}
.cat-editor-save {
  background: linear-gradient(135deg, #80001F 0%, #a3002a 100%);
  color: #fff;
  border: 0;
  border-radius: 10px;
  padding: 9px 14px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}
.cat-editor-img {
  width: 58px;
  height: 58px;
  border-radius: 11px;
  border: 2px solid #f5d6de;
  object-fit: cover;
  background: #fff6f8;
}

.cat-editor-row td {
  padding: 12px 14px;
  background: #fff8fa;
  border-bottom: 1px solid #f4d9e1;
}

@media (max-width: 860px) {
  .cat-editor-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<!-- ── Page Header ─────────────────────────────────── -->
<div class="cat-page-header">
  <div>
    <h1 class="cat-page-title">Categories</h1>
    <p class="cat-page-subtitle">Manage product categories &amp; hierarchy</p>
  </div>
  <a href="add-category.php" class="cat-add-btn">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
    Add Category
  </a>
</div>

<?php if ($flashUpdated !== null): ?>
  <div class="cat-flash <?= $flashUpdated === 1 ? 'cat-flash--ok' : 'cat-flash--error' ?>">
    <?= $flashUpdated === 1 ? 'Category updated successfully.' : 'Category update failed. Please try again.' ?>
  </div>
<?php endif; ?>

<!-- ── Table Card ──────────────────────────────────── -->
<div class="cat-card">
  <div class="cat-table-wrap">
    <table class="cat-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Image</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php renderCategories($categories); ?>
      </tbody>
    </table>
  </div>
</div>

<section id="catEditor" class="cat-editor" aria-hidden="true">
  <div class="cat-editor__head">
    <h3 class="cat-editor__title">Edit Category</h3>
    <button type="button" class="cat-editor__back" id="catEditorBack">Back to List</button>
  </div>
  <form method="POST" enctype="multipart/form-data" class="cat-editor__body">
    <input type="hidden" name="action" value="update_category">
    <input type="hidden" name="id" id="editCatId" value="0">
    <input type="hidden" name="current_image" id="editCatCurrentImage" value="">

    <div class="cat-editor-grid">
      <div class="cat-editor-field">
        <label for="editCatName">Category Name</label>
        <input id="editCatName" name="name" type="text" required>
      </div>

      <div class="cat-editor-field">
        <label for="editCatParent">Parent Category</label>
        <select id="editCatParent" name="parent_id">
          <option value="">-- Main Category --</option>
          <?php renderParentOptions($categories); ?>
        </select>
      </div>

      <div class="cat-editor-field">
        <label for="editCatSortOrder">Sort Order</label>
        <input id="editCatSortOrder" name="sort_order" type="number" value="0">
      </div>

      <div class="cat-editor-field">
        <label for="editCatImage">Category Image</label>
        <input id="editCatImage" name="image" type="file" accept="image/*">
      </div>
    </div>

    <div class="cat-editor-actions">
      <label style="display:flex;align-items:center;gap:6px;font-size:0.84rem;color:#5a3040;">
        <input id="editCatActive" type="checkbox" name="is_active" value="1"> Active
      </label>
      <img id="editCatPreview" class="cat-editor-img" src="" alt="Category preview">
      <button class="cat-editor-save" type="submit">Update Category</button>
    </div>
  </form>
</section>

<script>
// Expand / collapse child rows
document.querySelectorAll('.cat-toggle').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var parentId = this.dataset.target;
    var collapsed = this.classList.toggle('is-collapsed');
    toggleChildren(parentId, collapsed);
  });
});

function toggleChildren(parentId, hide) {
  document.querySelectorAll('[data-parent-id="' + parentId + '"]').forEach(function(row) {
    row.style.display = hide ? 'none' : '';
    // Also collapse nested children
    var childId = row.dataset.catId;
    if (childId) {
      var childToggle = row.querySelector('.cat-toggle');
      if (childToggle && !hide) {
        // keep nested state as-is on expand
      } else if (hide) {
        toggleChildren(childId, true);
        var t = row.querySelector('.cat-toggle');
        if (t) t.classList.add('is-collapsed');
      }
    }
  });
}

// Update category active/inactive without page reload
document.querySelectorAll('.toggle-status').forEach(function(checkbox) {
  checkbox.addEventListener('change', function() {
    var categoryId = this.dataset.id;
    var status = this.checked ? 1 : 0;
    var currentCheckbox = this;

    var body = 'id=' + encodeURIComponent(categoryId) + '&status=' + encodeURIComponent(status);

    fetch('update-category-status.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body
    })
      .then(function(response) {
        return response.json();
      })
      .then(function(data) {
        if (!data.success) {
          currentCheckbox.checked = !currentCheckbox.checked;
          alert(data.message || 'Failed to update category status');
        }
      })
      .catch(function() {
        currentCheckbox.checked = !currentCheckbox.checked;
        alert('Network error while updating status');
      });
  });
});

var catEditor = document.getElementById('catEditor');
var catEditorBack = document.getElementById('catEditorBack');
var editId = document.getElementById('editCatId');
var editName = document.getElementById('editCatName');
var editParent = document.getElementById('editCatParent');
var editSort = document.getElementById('editCatSortOrder');
var editActive = document.getElementById('editCatActive');
var editCurrentImage = document.getElementById('editCatCurrentImage');
var editPreview = document.getElementById('editCatPreview');
var editImage = document.getElementById('editCatImage');
var catEditorAnchor = catEditor.parentNode;
var catEditorDropdownRow = null;

function openCategoryEditor(button) {
  var id = Number(button.getAttribute('data-id') || '0');
  var name = button.getAttribute('data-name') || '';
  var parentId = Number(button.getAttribute('data-parent-id') || '0');
  var sortOrder = Number(button.getAttribute('data-sort-order') || '0');
  var isActive = Number(button.getAttribute('data-is-active') || '0') === 1;
  var image = button.getAttribute('data-image') || '';
  var previewImage = button.getAttribute('data-preview-image') || '';

  editId.value = String(id);
  editName.value = name;
  editSort.value = String(sortOrder);
  editActive.checked = isActive;
  editCurrentImage.value = image;
  editParent.value = parentId > 0 ? String(parentId) : '';

  Array.prototype.forEach.call(editParent.options, function(option) {
    option.disabled = Number(option.value || '0') === id;
  });

  editPreview.src = previewImage || <?php echo json_encode(cat_placeholder_image()); ?>;

  closeCategoryEditor();

  var triggerRow = button.closest('tr');
  if (triggerRow && triggerRow.parentNode) {
    catEditorDropdownRow = document.createElement('tr');
    catEditorDropdownRow.className = 'cat-editor-row';
    var dropCell = document.createElement('td');
    dropCell.colSpan = 4;
    catEditorDropdownRow.appendChild(dropCell);
    triggerRow.parentNode.insertBefore(catEditorDropdownRow, triggerRow.nextSibling);
    dropCell.appendChild(catEditor);
  } else {
    catEditorAnchor.appendChild(catEditor);
  }

  catEditor.classList.add('is-open');
  catEditor.setAttribute('aria-hidden', 'false');
  catEditor.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closeCategoryEditor() {
  catEditor.classList.remove('is-open');
  catEditor.setAttribute('aria-hidden', 'true');
  catEditorAnchor.appendChild(catEditor);
  if (catEditorDropdownRow && catEditorDropdownRow.parentNode) {
    catEditorDropdownRow.parentNode.removeChild(catEditorDropdownRow);
  }
  catEditorDropdownRow = null;
}

document.addEventListener('click', function (event) {
  if (!catEditor.classList.contains('is-open')) {
    return;
  }

  var clickedEditButton = event.target.closest('.js-cat-edit');
  if (clickedEditButton) {
    return;
  }

  if (!catEditor.contains(event.target)) {
    closeCategoryEditor();
  }
});

document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape' && catEditor.classList.contains('is-open')) {
    closeCategoryEditor();
  }
});

document.querySelectorAll('.js-cat-edit').forEach(function(button) {
  button.addEventListener('click', function() {
    openCategoryEditor(button);
  });
});

if (catEditorBack) {
  catEditorBack.addEventListener('click', closeCategoryEditor);
}

if (editImage) {
  editImage.addEventListener('change', function() {
    var file = editImage.files && editImage.files[0] ? editImage.files[0] : null;
    if (!file) {
      return;
    }
    var url = URL.createObjectURL(file);
    editPreview.src = url;
  });
}

var focusCategoryId = <?= (int)$focusCategoryId ?>;
if (focusCategoryId > 0) {
  var focusBtn = document.querySelector('.js-cat-edit[data-id="' + String(focusCategoryId) + '"]');
  if (focusBtn) {
    openCategoryEditor(focusBtn);
  }
}
</script>

</div>
</div>

</body>
</html>