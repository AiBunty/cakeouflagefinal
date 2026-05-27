<?php
session_name('cakeouflage_sid');
if (session_status() === PHP_SESSION_NONE) {
  $sessionDir = dirname(__DIR__) . '/storage/sessions';
  if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0775, true);
  }
  @session_save_path($sessionDir);
}
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/image_helpers.php';
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Services/UnifiedMediaService.php';

function prod_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function prod_slugify(string $name): string {
  $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
  return $slug !== '' ? $slug : 'product';
}

function prod_unique_slug(mysqli $conn, string $baseSlug, int $productId): string {
  $slug = $baseSlug;
  $i = 2;
  while (true) {
    $check = safePrepare($conn, 'SELECT id FROM products WHERE slug = ? AND id != ? LIMIT 1');
    $check->bind_param('si', $slug, $productId);
    $check->execute();
    $result = $check->get_result();
    $exists = $result ? $result->fetch_assoc() : null;
    if (!$exists) {
      return $slug;
    }
    $slug = $baseSlug . '-' . $i;
    $i++;
  }
}

function prod_log_upload_issue(int $productId, string $stage, string $message): void {
  error_log('[products.php] upload issue: product_id=' . $productId . ' stage=' . $stage . ' message=' . $message);
}

function prod_get_enum_values(mysqli $conn, string $table, string $column, array $fallback): array {
  $sql = 'SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1';
  $stmt = safePrepare($conn, $sql);
  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  if (!$row) {
    return $fallback;
  }

  $columnType = (string)($row['COLUMN_TYPE'] ?? '');
  if ($columnType === '' || stripos($columnType, 'enum(') !== 0) {
    return $fallback;
  }

  if (!preg_match_all("/'([^']+)'/", $columnType, $m) || empty($m[1])) {
    return $fallback;
  }

  $values = array_values(array_unique(array_map('strval', $m[1])));
  return $values !== [] ? $values : $fallback;
}

function prod_column_exists(mysqli $conn, string $table, string $column): bool {
  $sql = 'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?';
  $stmt = safePrepare($conn, $sql);
  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  return (int)($row['c'] ?? 0) > 0;
}

function prod_sync_gallery_slot(mysqli $conn, int $productId, int $sortOrder, ?string $imageUrl): void {
  $deleteStmt = safePrepare($conn, 'DELETE FROM product_images WHERE product_id = ? AND sort_order = ?');
  $deleteStmt->bind_param('ii', $productId, $sortOrder);
  $deleteStmt->execute();
  $deleteStmt->close();

  $url = trim((string)$imageUrl);
  if ($url === '') {
    return;
  }

  $insertStmt = safePrepare($conn, 'INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)');
  $insertStmt->bind_param('isi', $productId, $url, $sortOrder);
  $insertStmt->execute();
  $insertStmt->close();
}

function prod_enforce_two_image_slots(mysqli $conn, int $productId, string $featuredImage, ?string $image2): void {
  $featured = trim($featuredImage);
  if ($featured === '') {
    $featured = '/public/assets/defaults/default-product-image.webp';
  }

  $secondary = trim((string)$image2);
  if ($secondary !== '' && $secondary === $featured) {
    $secondary = '';
  }

  $deleteAllStmt = safePrepare($conn, 'DELETE FROM product_images WHERE product_id = ?');
  $deleteAllStmt->bind_param('i', $productId);
  $deleteAllStmt->execute();
  $deleteAllStmt->close();

  $slot0 = 0;
  $insertFeaturedStmt = safePrepare($conn, 'INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)');
  $insertFeaturedStmt->bind_param('isi', $productId, $featured, $slot0);
  $insertFeaturedStmt->execute();
  $insertFeaturedStmt->close();

  if ($secondary !== '') {
    $slot1 = 1;
    $insertImage2Stmt = safePrepare($conn, 'INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)');
    $insertImage2Stmt->bind_param('isi', $productId, $secondary, $slot1);
    $insertImage2Stmt->execute();
    $insertImage2Stmt->close();
  }
}

function prod_upload_media_field(int $productId, string $fieldName, string $module, int $adminId, array $replacePaths = []): array {
  if (empty($_FILES[$fieldName]['name'])) {
    return ['ok' => true, 'path' => '', 'error' => ''];
  }

  $errorCode = (int)($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($errorCode !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'path' => '', 'error' => 'PHP upload error code: ' . $errorCode];
  }

  $result = \App\Services\UnifiedMediaService::upload(
    $_FILES[$fieldName],
    [
      'module' => $module,
      'entity_type' => 'product',
      'entity_id' => $productId,
      'admin_id' => $adminId,
      'allow_svg' => false,
      'replace_paths' => $replacePaths,
    ]
  );

  if (!(bool)($result['ok'] ?? false)) {
    return ['ok' => false, 'path' => '', 'error' => (string)($result['error'] ?? 'Upload failed')];
  }

  return ['ok' => true, 'path' => (string)($result['relative_url'] ?? ''), 'error' => ''];
}

function prod_placeholder_image(): string {
  return \App\Services\ProductImageService::placeholderForCategory(null);
}

function prod_resolve_image_url(string $path): string {
  $value = trim($path);
  if ($value === '') {
    return prod_placeholder_image();
  }
  if (preg_match('#^(data:|https?://)#i', $value)) {
    return $value;
  }

  $normalized = preg_replace('#^/Cakeouflage-E-commerce#', '', $value);
  if (strpos($normalized, '/assets/images/products/') === 0) {
    $normalized = '/client/assets/images/product/' . basename($normalized);
  } elseif (strpos($normalized, '/assets/images/product/') === 0) {
    $normalized = '/client/assets/images/product/' . basename($normalized);
  } elseif (strpos($normalized, '/assets/') === 0 && strpos($normalized, '/assets/defaults/') !== 0) {
    $normalized = '/client' . $normalized;
  } elseif ($normalized !== '' && $normalized[0] !== '/') {
    $normalized = '/client/assets/images/product/' . ltrim($normalized, '/');
  }

  $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
  $absolutePath = $documentRoot !== '' ? $documentRoot . $normalized : '';
  if ($absolutePath !== '' && is_file($absolutePath)) {
    return $normalized;
  }

  return prod_placeholder_image();
}

function prod_resolve_optional_image_url(string $path): string {
  $value = trim($path);
  if ($value === '') {
    return '';
  }
  if (preg_match('#^(data:|https?://)#i', $value)) {
    return $value;
  }

  $normalized = preg_replace('#^/Cakeouflage-E-commerce#', '', $value);
  if (strpos($normalized, '/assets/images/products/') === 0) {
    $normalized = '/client/assets/images/product/' . basename($normalized);
  } elseif (strpos($normalized, '/assets/images/product/') === 0) {
    $normalized = '/client/assets/images/product/' . basename($normalized);
  } elseif (strpos($normalized, '/assets/') === 0) {
    $normalized = '/client' . $normalized;
  } elseif ($normalized !== '' && $normalized[0] !== '/') {
    $normalized = '/client/assets/images/product/' . ltrim($normalized, '/');
  }

  if ($normalized === '' || $normalized[0] !== '/') {
    return '';
  }

  $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
  $absolutePath = $documentRoot !== '' ? $documentRoot . $normalized : '';
  if ($absolutePath !== '' && is_file($absolutePath)) {
    return $normalized;
  }

  return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_product_inline') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim((string)($_POST['name'] ?? ''));
  $basePrice = (float)($_POST['base_price'] ?? 0);
  $categoryId = (int)($_POST['category_id'] ?? 0);
  $description = trim((string)($_POST['description'] ?? ''));
  $availabilityStatus = trim((string)($_POST['availability_status'] ?? 'in_stock'));
  $currentImage = trim((string)($_POST['current_image'] ?? ''));

  $allowedStatus = array('in_stock', 'out_of_stock', 'preorder', 'draft');
  if (!in_array($availabilityStatus, $allowedStatus, true)) {
    $availabilityStatus = 'in_stock';
  }

  if ($id <= 0 || $name === '' || $basePrice <= 0) {
    header('Location: products.php?updated=0');
    exit;
  }

  $existingStmt = safePrepare($conn, 'SELECT p.id, p.collection_category_id, p.featured_image, (SELECT image_url FROM product_images WHERE product_id = p.id AND sort_order = 1 ORDER BY id DESC LIMIT 1) AS image2_url FROM products p WHERE p.id = ? AND p.deleted_at IS NULL LIMIT 1');
  $existingStmt->bind_param('i', $id);
  $existingStmt->execute();
  $existingRes = $existingStmt->get_result();
  $existing = $existingRes ? $existingRes->fetch_assoc() : null;
  if (!$existing) {
    header('Location: products.php?updated=0');
    exit;
  }

  if ($categoryId <= 0) {
    $categoryId = (int)($existing['collection_category_id'] ?? 0);
  }
  if ($categoryId <= 0) {
    header('Location: products.php?updated=0');
    exit;
  }

  $adminId = (int)($_SESSION['admin'] ?? 0);
  $existingImage1 = trim((string)($existing['featured_image'] ?? ''));
  if ($existingImage1 === '') {
    $existingImage1 = $currentImage;
  }
  $existingImage2 = trim((string)($existing['image2_url'] ?? ''));

  $newImagePath = $existingImage1 !== '' ? $existingImage1 : prod_placeholder_image();
  $newImage2Path = $existingImage2 !== '' ? $existingImage2 : null;

  $upload1 = prod_upload_media_field($id, 'image', 'product', $adminId, $existingImage1 !== '' ? [$existingImage1] : []);
  if (!$upload1['ok']) {
    prod_log_upload_issue($id, 'image1', (string)$upload1['error']);
    header('Location: products.php?updated=0&uploadwarn=1&err=image1');
    exit;
  }
  if ((string)$upload1['path'] !== '') {
    $newImagePath = (string)$upload1['path'];
  }

  $upload2 = prod_upload_media_field($id, 'image2', 'product_image_2', $adminId, $existingImage2 !== '' ? [$existingImage2] : []);
  if (!$upload2['ok']) {
    prod_log_upload_issue($id, 'image2', (string)$upload2['error']);
    header('Location: products.php?updated=0&uploadwarn=1&err=image2');
    exit;
  }
  if ((string)$upload2['path'] !== '') {
    $newImage2Path = (string)$upload2['path'];
  }

  $slug = prod_unique_slug($conn, prod_slugify($name), $id);
  $isChefSpecial = isset($_POST['is_chef_special']) ? 1 : 0;
  $dietaryTag = trim((string)($_POST['dietary_tag'] ?? 'regular'));
  $allowedDietary = prod_get_enum_values($conn, 'products', 'dietary_tag', ['regular', 'eggless', 'vegan', 'sugar_free']);
  if (!in_array($dietaryTag, $allowedDietary, true)) {
    $dietaryTag = in_array('regular', $allowedDietary, true) ? 'regular' : (string)($allowedDietary[0] ?? 'regular');
  }
  $isVeg = isset($_POST['is_veg']) ? 1 : 0;
  $topperEnabled = isset($_POST['topper_enabled']) ? 1 : 0;
  $noteEnabled = isset($_POST['note_enabled']) ? 1 : 0;

  $ok = false;
  try {
    $conn->begin_transaction();

    $updateAssignments = [
      'name = ?',
      'slug = ?',
      'starting_price = ?',
      'collection_category_id = ?',
      'short_description = ?',
      'featured_image = ?',
      'availability_status = ?',
      'is_chef_special = ?',
      'dietary_tag = ?',
    ];
    $types = 'ssdisssis';
    $values = [
      $name,
      $slug,
      $basePrice,
      $categoryId,
      $description,
      $newImagePath,
      $availabilityStatus,
      $isChefSpecial,
      $dietaryTag,
    ];

    if (prod_column_exists($conn, 'products', 'is_veg')) {
      $updateAssignments[] = 'is_veg = ?';
      $types .= 'i';
      $values[] = $isVeg;
    }
    if (prod_column_exists($conn, 'products', 'topper_enabled')) {
      $updateAssignments[] = 'topper_enabled = ?';
      $types .= 'i';
      $values[] = $topperEnabled;
    }
    if (prod_column_exists($conn, 'products', 'note_enabled')) {
      $updateAssignments[] = 'note_enabled = ?';
      $types .= 'i';
      $values[] = $noteEnabled;
    }

    $updateSql = 'UPDATE products SET ' . implode(', ', $updateAssignments) . ', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL LIMIT 1';
    $types .= 'i';
    $values[] = $id;

    $updateStmt = safePrepare($conn, $updateSql);
    $updateStmt->bind_param($types, ...$values);
    $ok = $updateStmt->execute();
    if (!$ok) {
      throw new RuntimeException('Product update failed: ' . $updateStmt->error);
    }

    $weights = ['1', '1.5', '2', '2.5', '3', '4'];
    foreach ($weights as $weight) {
      $variantPrice = $basePrice * (float)$weight;
      $variantStmt = safePrepare($conn, 'UPDATE product_variants SET price = ? WHERE product_id = ? AND weight_or_size = ?');
      $variantStmt->bind_param('dis', $variantPrice, $id, $weight);
      $variantStmt->execute();
      $variantStmt->close();
    }

    prod_enforce_two_image_slots($conn, $id, $newImagePath, $newImage2Path);

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    $ok = false;
    error_log('[products.php] update_product_inline failure: product_id=' . $id . ' message=' . $e->getMessage());
  }

  header('Location: products.php?updated=' . ($ok ? '1' : '0'));
  exit;
}

$flashUpdated = isset($_GET['updated']) ? (int)$_GET['updated'] : null;
$flashUploadWarn = isset($_GET['uploadwarn']);
$focusProductId = isset($_GET['focus']) ? (int)$_GET['focus'] : 0;

$categoryOptions = array();
$categoryResult = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
if ($categoryResult) {
  while ($catRow = $categoryResult->fetch_assoc()) {
    $categoryOptions[] = $catRow;
  }
}

$query = "
SELECT 
  p.*, 
  c1.name AS collection,
  c2.name AS subcategory,
  (SELECT image_url FROM product_images WHERE product_id = p.id AND sort_order = 1 ORDER BY id ASC LIMIT 1) AS image2_url
FROM products p
LEFT JOIN categories c1 ON p.collection_category_id = c1.id
LEFT JOIN categories c2 ON p.subcategory_id = c2.id
WHERE p.deleted_at IS NULL
ORDER BY p.id DESC
";

$result = $conn->query($query);

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$collections = [];
$subcategories = [];
foreach ($products as $p) {
    $collectionName = trim((string)($p['collection'] ?? ''));
    $subcategoryName = trim((string)($p['subcategory'] ?? ''));
    if ($collectionName !== '') {
        $collections[$collectionName] = true;
    }
    if ($subcategoryName !== '') {
        $subcategories[$subcategoryName] = true;
    }
}
ksort($collections);
ksort($subcategories);

$pageTitle = "Products";
require_once __DIR__ . '/layout.php';
?>
<style>
  .products-page {
    display: grid;
    grid-template-columns: 248px minmax(0, 1fr);
    gap: 22px;
    align-items: start;
  }

  .products-sidebar {
    position: sticky;
    top: 14px;
    background: linear-gradient(170deg, rgba(255, 255, 255, 0.97), rgba(255, 246, 248, 0.95));
    border: 1px solid rgba(128, 0, 31, 0.1);
    border-radius: 22px;
    box-shadow: 0 12px 26px rgba(97, 20, 45, 0.08);
    padding: 15px;
  }

  .products-sidebar__title {
    margin: 0 0 6px;
    font-family: 'DM Serif Display', Georgia, serif;
    color: #80001F;
    font-size: 1.08rem;
    font-weight: 400;
  }

  .products-sidebar__hint {
    margin: 0 0 12px;
    color: #866772;
    font-size: 0.76rem;
    line-height: 1.5;
  }

  .products-sidebar__block + .products-sidebar__block {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed rgba(128, 0, 31, 0.16);
  }

  .products-sidebar__label {
    margin: 0 0 7px;
    color: #6e2a3e;
    font-size: 0.69rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-weight: 600;
  }

  .products-sidebar__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 5px;
    max-height: 180px;
    overflow: auto;
    padding-right: 2px;
  }

  .products-sidebar__list::-webkit-scrollbar {
    width: 6px;
  }

  .products-sidebar__list::-webkit-scrollbar-thumb {
    background: rgba(128, 0, 31, 0.26);
    border-radius: 999px;
  }

  .products-sidebar__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 6px 9px;
    border-radius: 9px;
    color: #5d3a46;
    font-size: 0.82rem;
    background: rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(128, 0, 31, 0.06);
    transition: background 160ms ease, border-color 160ms ease;
  }

  .products-sidebar__item {
    width: 100%;
    text-align: left;
    cursor: pointer;
  }

  .products-sidebar__item.is-active {
    background: rgba(248, 216, 222, 0.95);
    border-color: rgba(128, 0, 31, 0.24);
    color: #5f0017;
  }

  .products-sidebar__item:focus-visible {
    outline: 2px solid rgba(128, 0, 31, 0.42);
    outline-offset: 2px;
  }

  .products-sidebar__item:hover {
    background: rgba(255, 248, 250, 0.95);
    border-color: rgba(128, 0, 31, 0.14);
  }

  .products-sidebar__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(128, 0, 31, 0.35);
    flex-shrink: 0;
  }

  .products-content {
    min-width: 0;
  }

  .products-toolbar {
    position: sticky;
    top: 10px;
    z-index: 8;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(128, 0, 31, 0.1);
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(82, 16, 37, 0.07);
    backdrop-filter: blur(9px);
    padding: 12px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    margin-bottom: 14px;
  }

  .products-toolbar__controls {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 170px 170px;
    gap: 9px;
  }

  .products-input,
  .products-select {
    width: 100%;
    min-height: 40px;
    border-radius: 11px;
    border: 1px solid rgba(128, 0, 31, 0.16);
    background: linear-gradient(180deg, #fffefe, #fff8fa);
    color: #432530;
    padding: 0 12px;
    font: inherit;
    font-size: 0.85rem;
    box-sizing: border-box;
    transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
  }

  .products-input:focus,
  .products-select:focus {
    outline: none;
    border-color: rgba(128, 0, 31, 0.46);
    box-shadow: 0 0 0 3px rgba(248, 216, 222, 0.45);
    background: #fff;
  }

  .products-toolbar__actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .products-stats {
    background: linear-gradient(180deg, #fff, #fffafc);
    border: 1px solid rgba(128, 0, 31, 0.09);
    border-radius: 14px;
    padding: 9px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    color: #69424e;
    font-size: 0.8rem;
  }

  .products-stats strong {
    color: #80001F;
    font-size: 0.9rem;
  }

  .add-btn {
    background: linear-gradient(145deg, #80001F 0%, #9f1137 100%);
    color: #fff;
    padding: 10px 15px;
    border-radius: 999px;
    text-decoration: none;
    font-size: 0.77rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    font-weight: 600;
    transition: transform 180ms ease, box-shadow 180ms ease;
    box-shadow: 0 7px 16px rgba(128, 0, 31, 0.2);
    white-space: nowrap;
  }

  .add-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 22px rgba(128, 0, 31, 0.29);
  }

  .products-table-wrap {
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.11);
    border-radius: 16px;
    box-shadow: 0 10px 24px rgba(68, 16, 34, 0.07);
    overflow: auto;
  }

  .products-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 980px;
  }

  .products-table th,
  .products-table td {
    padding: 11px 13px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.09);
    text-align: left;
    vertical-align: middle;
  }

  .products-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #80001F;
    background: #fff6f8;
    font-weight: 700;
  }

  .products-table tbody tr {
    transition: background 140ms ease;
  }

  .products-table tbody tr:hover {
    background: #fff8fb;
  }

  .products-table__thumb {
    width: 54px;
    height: 54px;
    border-radius: 11px;
    object-fit: cover;
    border: 1px solid rgba(128, 0, 31, 0.12);
    background: #f8e6ea;
  }

  .products-title {
    margin: 0;
    font-size: 0.9rem;
    color: #2e1f24;
    font-weight: 600;
    line-height: 1.35;
  }

  .products-subtitle {
    margin: 3px 0 0;
    color: #8b7580;
    font-size: 0.75rem;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 68px;
    min-height: 26px;
    padding: 0 9px;
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    border: 1px solid transparent;
  }

  .status-badge--active {
    background: rgba(34, 197, 94, 0.13);
    color: #166534;
    border-color: rgba(34, 197, 94, 0.34);
  }

  .status-badge--draft {
    background: rgba(245, 158, 11, 0.16);
    color: #92400e;
    border-color: rgba(245, 158, 11, 0.35);
  }

  .status-badge--hidden {
    background: rgba(148, 163, 184, 0.22);
    color: #374151;
    border-color: rgba(107, 114, 128, 0.3);
  }

  .products-price {
    color: #7b122f;
    font-weight: 700;
    font-size: 0.91rem;
  }

  .products-actions {
    display: inline-flex;
    align-items: center;
    gap: 7px;
  }

  .product-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 0 9px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.71rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    border: 0;
    font-family: inherit;
    cursor: pointer;
    transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.22);
  }

  .product-action:hover {
    transform: translateY(-1px);
  }

  .product-action--edit {
    background: #f8d8de;
    color: #7a0e2f;
  }

  .product-action--delete {
    background: #fee2e2;
    color: #9f1239;
  }

  .products-grid {
    display: none;
  }

  .products-flash {
    margin-bottom: 10px;
    border-radius: 12px;
    padding: 10px 12px;
    font-size: 0.84rem;
    font-weight: 600;
  }

  .products-flash--ok {
    background: #ecfdf3;
    color: #166534;
    border: 1px solid #bbf7d0;
  }

  .products-flash--error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
  }

  .products-flash--warn {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
  }

  .prod-editor {
    display: none;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(68, 16, 34, 0.08);
    overflow: hidden;
  }

  .prod-editor.is-open {
    display: block;
  }

  .prod-editor__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
  }

  .prod-editor__title {
    margin: 0;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
    color: #80001F;
    font-size: 1.1rem;
  }

  .prod-editor__back {
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-radius: 10px;
    background: #fff;
    color: #80001F;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 7px 12px;
    cursor: pointer;
  }

  .prod-editor__body {
    padding: 12px 14px 14px;
    display: grid;
    gap: 10px;
  }

  .prod-editor-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
  }

  .prod-editor-field {
    display: grid;
    gap: 6px;
  }

  .prod-editor-field label {
    font-size: 0.71rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #80001F;
    font-weight: 700;
  }

  .prod-editor-field input,
  .prod-editor-field select,
  .prod-editor-field textarea {
    border: 1px solid rgba(128, 0, 31, 0.16);
    border-radius: 10px;
    padding: 8px 10px;
    font: inherit;
    font-size: 0.84rem;
    box-sizing: border-box;
    width: 100%;
  }

  .prod-editor-field textarea {
    min-height: 76px;
    resize: vertical;
  }

  .prod-editor-field--wide {
    grid-column: 1 / -1;
  }

  .prod-editor-section {
    border: 1px solid rgba(128, 0, 31, 0.08);
    border-radius: 12px;
    background: #fff;
    padding: 10px;
    display: grid;
    gap: 8px;
  }

  .prod-editor-section__title {
    margin: 0;
    font-size: 0.84rem;
    font-weight: 700;
    color: #6f1130;
    letter-spacing: 0.02em;
  }

  .prod-editor-section__hint {
    margin: 0;
    font-size: 0.72rem;
    color: #8f6e7b;
  }

  .prod-feature-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  .prod-feature-item {
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 10px;
    padding: 8px 10px;
    background: #fff9fb;
  }

  .prod-feature-item label {
    margin: 0;
    font-size: 0.79rem;
    color: #4f2e39;
    font-weight: 600;
    text-transform: none;
    letter-spacing: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
  }

  .prod-feature-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    margin: 0;
  }

  .prod-editor-actions {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .prod-editor-save {
    border: 0;
    border-radius: 10px;
    background: linear-gradient(135deg, #80001F 0%, #a3002a 100%);
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 9px 14px;
    cursor: pointer;
  }

  .prod-editor-save.is-saving {
    opacity: 0.65;
    cursor: not-allowed;
  }

  .prod-editor-img {
    width: 56px;
    height: 56px;
    border-radius: 10px;
    border: 1px solid rgba(128, 0, 31, 0.14);
    object-fit: cover;
    background: #f8d8de;
  }

  .prod-editor-row td {
    background: #fff8fb;
    padding: 10px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.1);
  }

  .prod-editor-card-slot {
    margin-top: 10px;
  }

  .product-card {
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 18px rgba(68, 16, 34, 0.07);
    transition: transform 160ms ease, box-shadow 160ms ease;
  }

  .product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(68, 16, 34, 0.1);
  }

  .product-card__media {
    height: 170px;
    background: #f7e9ed;
    overflow: hidden;
  }

  .product-card__media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .product-card__body {
    padding: 13px;
  }

  .product-card__body h3 {
    margin: 0 0 6px;
    font-size: 0.95rem;
    color: #2d1f25;
  }

  .product-card__meta {
    font-size: 0.76rem;
    color: #8a7480;
    margin-bottom: 7px;
  }

  .product-card__status {
    margin-bottom: 8px;
  }

  .product-card__price {
    font-weight: 700;
    color: #80001F;
    margin-bottom: 10px;
    font-size: 0.92rem;
  }

  .product-card__actions {
    display: flex;
    gap: 8px;
  }

  .products-pagination {
    margin-top: 12px;
    background: linear-gradient(180deg, #fff, #fffafc);
    border: 1px solid rgba(128, 0, 31, 0.09);
    border-radius: 13px;
    padding: 9px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    color: #7a626c;
    font-size: 0.77rem;
  }

  .products-pagination__controls {
    display: inline-flex;
    gap: 6px;
  }

  .products-page-btn {
    min-height: 28px;
    min-width: 28px;
    border-radius: 8px;
    border: 1px solid rgba(128, 0, 31, 0.17);
    background: #fff;
    color: #7b1a39;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
  }

  .products-page-btn.is-active {
    background: #80001F;
    color: #fff;
    border-color: #80001F;
  }

  .products-page-btn.is-disabled {
    opacity: 0.5;
    pointer-events: none;
  }

  @media (max-width: 1200px) {
    .products-page {
      grid-template-columns: 208px minmax(0, 1fr);
    }

    .products-toolbar__controls {
      grid-template-columns: minmax(0, 1fr);
    }

    .prod-editor-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 960px) {
    .products-page {
      grid-template-columns: 1fr;
    }

    .products-sidebar {
      position: static;
    }

    .products-table-wrap {
      display: none;
    }

    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 12px;
    }

    .products-toolbar {
      position: static;
      grid-template-columns: 1fr;
    }

    .prod-editor-grid {
      grid-template-columns: 1fr;
    }

    .prod-feature-grid {
      grid-template-columns: 1fr;
    }

    .products-toolbar__actions {
      justify-content: flex-end;
    }
  }

  @media (max-width: 580px) {
    .products-grid {
      grid-template-columns: 1fr;
    }

    .products-pagination {
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
    }

    .products-toolbar,
    .products-stats {
      border-radius: 12px;
    }
  }
</style>

<section class="products-page">
  <aside class="products-sidebar" aria-label="Product category navigation">
    <h2 class="products-sidebar__title">Category Navigation</h2>
    <p class="products-sidebar__hint">Quickly scan collections and subcategories.</p>

    <div class="products-sidebar__block">
      <p class="products-sidebar__label">Collections</p>
      <ul class="products-sidebar__list">
        <li><button type="button" class="products-sidebar__item is-active" data-filter-type="collection" data-filter-value=""><span class="products-sidebar__dot"></span><span>All Collections</span></button></li>
        <?php if (!empty($collections)): ?>
          <?php foreach (array_keys($collections) as $name): ?>
            <li><button type="button" class="products-sidebar__item" data-filter-type="collection" data-filter-value="<?php echo htmlspecialchars((string)$name); ?>"><span class="products-sidebar__dot"></span><span><?php echo htmlspecialchars((string)$name); ?></span></button></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li><button type="button" class="products-sidebar__item" data-filter-type="collection" data-filter-value=""><span class="products-sidebar__dot"></span><span>Uncategorized</span></button></li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="products-sidebar__block">
      <p class="products-sidebar__label">Subcategories</p>
      <ul class="products-sidebar__list">
        <li><button type="button" class="products-sidebar__item is-active" data-filter-type="subcategory" data-filter-value=""><span class="products-sidebar__dot"></span><span>All Subcategories</span></button></li>
        <?php if (!empty($subcategories)): ?>
          <?php foreach (array_keys($subcategories) as $name): ?>
            <li><button type="button" class="products-sidebar__item" data-filter-type="subcategory" data-filter-value="<?php echo htmlspecialchars((string)$name); ?>"><span class="products-sidebar__dot"></span><span><?php echo htmlspecialchars((string)$name); ?></span></button></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li><button type="button" class="products-sidebar__item" data-filter-type="subcategory" data-filter-value=""><span class="products-sidebar__dot"></span><span>None available</span></button></li>
        <?php endif; ?>
      </ul>
    </div>
  </aside>

  <div class="products-content">
    <div class="products-toolbar">
      <div class="products-toolbar__controls">
        <input id="productSearch" class="products-input" type="search" placeholder="Search product name..." aria-label="Search products">
        <select id="collectionFilter" class="products-select" aria-label="Filter by collection">
          <option value="">All Collections</option>
          <?php foreach (array_keys($collections) as $name): ?>
            <option value="<?php echo htmlspecialchars((string)$name); ?>"><?php echo htmlspecialchars((string)$name); ?></option>
          <?php endforeach; ?>
        </select>
        <select id="subcategoryFilter" class="products-select" aria-label="Filter by subcategory">
          <option value="">All Subcategories</option>
          <?php foreach (array_keys($subcategories) as $name): ?>
            <option value="<?php echo htmlspecialchars((string)$name); ?>"><?php echo htmlspecialchars((string)$name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="products-toolbar__actions">
        <a href="add-product.php" class="add-btn">+ Add Product</a>
      </div>
    </div>

    <div class="products-stats">
      <span>Total products</span>
      <strong id="visibleCount"><?php echo count($products); ?></strong>
    </div>

    <?php if ($flashUpdated !== null): ?>
      <div class="products-flash <?php echo $flashUpdated === 1 && !$flashUploadWarn ? 'products-flash--ok' : ($flashUpdated === 1 ? 'products-flash--warn' : 'products-flash--error'); ?>">
        <?php if ($flashUpdated === 1 && $flashUploadWarn): ?>
          Product saved — but the image could not be stored on the server. Check storage permissions.
        <?php elseif ($flashUpdated === 1): ?>
          Product updated successfully.
        <?php else: ?>
          Product update failed. Please try again.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="products-table-wrap">
      <table class="products-table" id="productsTable">
        <thead>
          <tr>
            <th>Image</th>
            <th>Product</th>
            <th>Collection</th>
            <th>Subcategory</th>
            <th>Status</th>
            <th>Price</th>
            <th>Quick Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $row): ?>
            <?php
              $statusRaw = strtolower(trim((string)($row['status'] ?? '')));
              if ($statusRaw === 'draft' || $statusRaw === 'hidden' || $statusRaw === 'active') {
                  $status = $statusRaw;
              } elseif (isset($row['is_active']) && (int)$row['is_active'] === 0) {
                  $status = 'hidden';
              } else {
                  $status = 'active';
              }
              $collection = (string)($row['collection'] ?? '');
              $subcategory = (string)($row['subcategory'] ?? '');
              $price = $row['discount_price'] ?: $row['starting_price'];
            ?>
            <tr class="product-row" data-name="<?php echo htmlspecialchars(strtolower((string)$row['name'])); ?>" data-collection="<?php echo htmlspecialchars(strtolower($collection)); ?>" data-subcategory="<?php echo htmlspecialchars(strtolower($subcategory)); ?>">
              <td><img class="products-table__thumb" src="<?php echo prod_h(prod_resolve_image_url((string)($row['featured_image'] ?? ''))); ?>" alt="<?php echo htmlspecialchars((string)$row['name']); ?>" onerror="this.onerror=null;this.src='<?php echo prod_h(prod_placeholder_image()); ?>';"></td>
              <td>
                <p class="products-title"><?php echo $row['name']; ?></p>
                <p class="products-subtitle">ID #<?php echo (int)$row['id']; ?></p>
              </td>
              <td><?php echo $collection !== '' ? $collection : '—'; ?></td>
              <td><?php echo $subcategory !== '' ? $subcategory : '—'; ?></td>
              <td><span class="status-badge status-badge--<?php echo htmlspecialchars($status); ?>"><?php echo ucfirst($status); ?></span></td>
              <td><span class="products-price">₹<?php echo $price; ?></span></td>
              <td>
                <div class="products-actions">
                  <button type="button" class="product-action product-action--edit js-prod-edit" title="Edit product"
                    data-id="<?php echo (int)$row['id']; ?>"
                    data-name="<?php echo prod_h((string)$row['name']); ?>"
                    data-base-price="<?php echo (float)($row['starting_price'] ?? 0); ?>"
                    data-category-id="<?php echo (int)($row['collection_category_id'] ?? 0); ?>"
                    data-description="<?php echo prod_h((string)($row['short_description'] ?? '')); ?>"
                    data-current-image="<?php echo prod_h((string)($row['featured_image'] ?? '')); ?>"
                    data-preview-image="<?php echo prod_h(prod_resolve_image_url((string)($row['featured_image'] ?? ''))); ?>"
                    data-image2-url="<?php echo prod_h(prod_resolve_optional_image_url((string)($row['image2_url'] ?? ''))); ?>"
                    data-chef-special="<?php echo (int)($row['is_chef_special'] ?? 0); ?>"
                    data-availability="<?php echo prod_h((string)($row['availability_status'] ?? 'in_stock')); ?>"
                    data-topper-enabled="<?php echo (int)($row['topper_enabled'] ?? 1); ?>"
                    data-note-enabled="<?php echo (int)($row['note_enabled'] ?? 1); ?>">✏ Edit</button>
                  <a href="delete-product.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this product?')" class="product-action product-action--delete" title="Delete product">🗑 Delete</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="products-grid" id="productsCards">
      <?php foreach ($products as $row): ?>
        <?php
          $statusRaw = strtolower(trim((string)($row['status'] ?? '')));
          if ($statusRaw === 'draft' || $statusRaw === 'hidden' || $statusRaw === 'active') {
              $status = $statusRaw;
          } elseif (isset($row['is_active']) && (int)$row['is_active'] === 0) {
              $status = 'hidden';
          } else {
              $status = 'active';
          }
          $collection = (string)($row['collection'] ?? '');
          $subcategory = (string)($row['subcategory'] ?? '');
          $price = $row['discount_price'] ?: $row['starting_price'];
        ?>
        <article class="product-card product-row" data-name="<?php echo htmlspecialchars(strtolower((string)$row['name'])); ?>" data-collection="<?php echo htmlspecialchars(strtolower($collection)); ?>" data-subcategory="<?php echo htmlspecialchars(strtolower($subcategory)); ?>">
          <div class="product-card__media">
            <img src="<?php echo prod_h(prod_resolve_image_url((string)($row['featured_image'] ?? ''))); ?>" alt="<?php echo htmlspecialchars((string)$row['name']); ?>" onerror="this.onerror=null;this.src='<?php echo prod_h(prod_placeholder_image()); ?>';">
          </div>
          <div class="product-card__body">
            <h3><?php echo $row['name']; ?></h3>
            <div class="product-card__meta"><?php echo $collection !== '' ? $collection : '—'; ?> / <?php echo $subcategory !== '' ? $subcategory : '—'; ?></div>
            <div class="product-card__status"><span class="status-badge status-badge--<?php echo htmlspecialchars($status); ?>"><?php echo ucfirst($status); ?></span></div>
            <div class="product-card__price">₹<?php echo $price; ?></div>
            <div class="product-card__actions">
              <button type="button" class="product-action product-action--edit js-prod-edit"
                data-id="<?php echo (int)$row['id']; ?>"
                data-name="<?php echo prod_h((string)$row['name']); ?>"
                data-base-price="<?php echo (float)($row['starting_price'] ?? 0); ?>"
                data-category-id="<?php echo (int)($row['collection_category_id'] ?? 0); ?>"
                data-description="<?php echo prod_h((string)($row['short_description'] ?? '')); ?>"
                data-current-image="<?php echo prod_h((string)($row['featured_image'] ?? '')); ?>"
                data-preview-image="<?php echo prod_h(prod_resolve_image_url((string)($row['featured_image'] ?? ''))); ?>"
                data-image2-url="<?php echo prod_h(prod_resolve_optional_image_url((string)($row['image2_url'] ?? ''))); ?>"
                data-chef-special="<?php echo (int)($row['is_chef_special'] ?? 0); ?>"
                data-availability="<?php echo prod_h((string)($row['availability_status'] ?? 'in_stock')); ?>"
                data-dietary="<?php echo prod_h((string)($row['dietary_tag'] ?? 'regular')); ?>"
                data-is-veg="<?php echo (int)($row['is_veg'] ?? 1); ?>"
                data-topper-enabled="<?php echo (int)($row['topper_enabled'] ?? 1); ?>"
                data-note-enabled="<?php echo (int)($row['note_enabled'] ?? 1); ?>">Edit</button>
              <a href="delete-product.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this product?')" class="product-action product-action--delete">Delete</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="products-pagination" aria-label="Pagination">
      <span>Showing <strong id="visibleRange">1-<?php echo count($products); ?></strong> of <?php echo count($products); ?></span>
      <div class="products-pagination__controls">
        <span class="products-page-btn is-disabled">Prev</span>
        <span class="products-page-btn is-active">1</span>
        <span class="products-page-btn is-disabled">Next</span>
      </div>
    </div>

    <section id="prodEditor" class="prod-editor" aria-hidden="true">
      <div class="prod-editor__head">
        <h3 class="prod-editor__title">Edit Product</h3>
        <button type="button" class="prod-editor__back" id="prodEditorBack">Back to List</button>
      </div>
      <form id="prodEditForm" method="POST" enctype="multipart/form-data" class="prod-editor__body">
        <input type="hidden" name="action" value="update_product_inline">
        <input type="hidden" name="id" id="prodEditId" value="0">
        <input type="hidden" name="current_image" id="prodEditCurrentImage" value="">

        <div class="prod-editor-section">
          <h4 class="prod-editor-section__title">Basic Product Info</h4>
          <p class="prod-editor-section__hint">Update name, pricing, category, stock state, and dietary type.</p>
          <div class="prod-editor-grid">
            <div class="prod-editor-field">
              <label for="prodEditName">Product Name</label>
              <input id="prodEditName" name="name" type="text" required>
            </div>

            <div class="prod-editor-field">
              <label for="prodEditPrice">Base Price</label>
              <input id="prodEditPrice" name="base_price" type="number" step="0.01" min="0.01" required>
            </div>

            <div class="prod-editor-field">
              <label for="prodEditCategory">Collection Category</label>
              <select id="prodEditCategory" name="category_id" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categoryOptions as $cat): ?>
                  <option value="<?php echo (int)$cat['id']; ?>"><?php echo prod_h((string)$cat['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="prod-editor-field">
              <label for="prodEditAvailability">Availability</label>
              <select id="prodEditAvailability" name="availability_status">
                <option value="in_stock">In Stock</option>
                <option value="out_of_stock">Out Of Stock</option>
                <option value="preorder">Pre Order</option>
                <option value="draft">Draft</option>
              </select>
            </div>

            <div class="prod-editor-field">
              <label for="prodEditDietary">Dietary Type</label>
              <select id="prodEditDietary" name="dietary_tag">
                <option value="regular">Regular</option>
                <option value="eggless">Eggless</option>
                <option value="vegan">Vegan</option>
                <option value="sugar_free">Sugar Free</option>
                <option value="healthy">Healthy</option>
              </select>
            </div>
          </div>
        </div>

        <div class="prod-editor-section">
          <h4 class="prod-editor-section__title">Product Media</h4>
          <p class="prod-editor-section__hint">Only Image 1 and optional Image 2 are supported.</p>
          <div class="prod-editor-grid">
            <div class="prod-editor-field">
              <label for="prodEditImage">Change Image 1</label>
              <input id="prodEditImage" name="image" type="file" accept="image/*">
            </div>

            <div class="prod-editor-field">
              <label for="prodEditImage2">Change Image 2 <span style="font-weight:400;opacity:.7;">(optional)</span></label>
              <input id="prodEditImage2" name="image2" type="file" accept="image/*">
            </div>
          </div>
        </div>

        <div class="prod-editor-section">
          <h4 class="prod-editor-section__title">Product Features</h4>
          <div class="prod-feature-grid">
            <div class="prod-feature-item">
              <label><input type="checkbox" name="is_chef_special" id="prodEditChefSpecial" value="1"> Chef's Special</label>
            </div>
            <div class="prod-feature-item">
              <label><input type="checkbox" name="is_veg" id="prodEditIsVeg" value="1"> Veg</label>
            </div>
            <div class="prod-feature-item">
              <label><input type="checkbox" name="topper_enabled" id="prodEditTopperEnabled" value="1" checked> Enable Topper Selection</label>
            </div>
            <div class="prod-feature-item">
              <label><input type="checkbox" name="note_enabled" id="prodEditNoteEnabled" value="1" checked> Enable Note on Cake</label>
            </div>
          </div>
        </div>

        <div class="prod-editor-section">
          <h4 class="prod-editor-section__title">Description</h4>
          <div class="prod-editor-grid">
            <div class="prod-editor-field prod-editor-field--wide">
              <label for="prodEditDescription">Description</label>
              <textarea id="prodEditDescription" name="description"></textarea>
            </div>
          </div>
        </div>

        <div class="prod-editor-actions">
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <div>
              <div style="font-size:.75rem;opacity:.6;margin-bottom:4px;">Image 1</div>
              <img id="prodEditPreview" class="prod-editor-img" src="" alt="Product image 1 preview">
            </div>
            <div>
              <div style="font-size:.75rem;opacity:.6;margin-bottom:4px;">Image 2</div>
              <img id="prodEditPreview2" class="prod-editor-img" src="" alt="Product image 2 preview" style="display:none;">
            </div>
          </div>
          <button class="prod-editor-save" type="submit">Update Product</button>
        </div>
      </form>
    </section>
  </div>
</section>

<script>
  (function () {
    const search = document.getElementById('productSearch');
    const collection = document.getElementById('collectionFilter');
    const subcategory = document.getElementById('subcategoryFilter');
    const rows = Array.from(document.querySelectorAll('.product-row'));
    const sidebarFilters = Array.from(document.querySelectorAll('.products-sidebar__item[data-filter-type]'));
    const visibleCount = document.getElementById('visibleCount');
    const visibleRange = document.getElementById('visibleRange');
    const prodEditor = document.getElementById('prodEditor');
    const prodEditorBack = document.getElementById('prodEditorBack');
    const prodEditId = document.getElementById('prodEditId');
    const prodEditName = document.getElementById('prodEditName');
    const prodEditPrice = document.getElementById('prodEditPrice');
    const prodEditCategory = document.getElementById('prodEditCategory');
    const prodEditAvailability = document.getElementById('prodEditAvailability');
    const prodEditDescription = document.getElementById('prodEditDescription');
    const prodEditCurrentImage = document.getElementById('prodEditCurrentImage');
    const prodEditImage = document.getElementById('prodEditImage');
    const prodEditPreview = document.getElementById('prodEditPreview');
    const prodEditImage2 = document.getElementById('prodEditImage2');
    const prodEditPreview2 = document.getElementById('prodEditPreview2');
    const prodEditChefSpecial = document.getElementById('prodEditChefSpecial');
    const prodEditButtons = Array.from(document.querySelectorAll('.js-prod-edit'));
    const prodEditorAnchor = prodEditor ? prodEditor.parentNode : null;
    let prodDropdownRow = null;
    let prodCardSlot = null;

    function normalizeImagePath(path) {
      const value = String(path || '').trim();
      if (!value) {
        return <?php echo json_encode(prod_placeholder_image()); ?>;
      }
      if (/^(data:|https?:\/\/)/i.test(value)) {
        return value;
      }
      let normalized = value.replace(/^\/Cakeouflage-E-commerce/, '');
      if (normalized.indexOf('/assets/images/products/') === 0) {
        normalized = '/client/assets/images/product/' + normalized.split('/').pop();
      } else if (normalized.indexOf('/assets/images/product/') === 0) {
        normalized = '/client/assets/images/product/' + normalized.split('/').pop();
      } else if (normalized.indexOf('/assets/defaults/') === 0) {
        // Brand default image — lives under public/assets/defaults/, no /client/ prefix needed.
      } else if (normalized.indexOf('/assets/') === 0) {
        normalized = '/client' + normalized;
      } else if (normalized.indexOf('/') !== 0) {
        normalized = '/client/assets/images/product/' + normalized.replace(/^\/+/, '');
      }

      if (normalized.indexOf('/') === 0) {
        return normalized;
      }
      return <?php echo json_encode(prod_placeholder_image()); ?>;
    }

    function closeProductEditor() {
      if (!prodEditor || !prodEditorAnchor) return;
      prodEditor.classList.remove('is-open');
      prodEditor.setAttribute('aria-hidden', 'true');
      prodEditorAnchor.appendChild(prodEditor);
      if (prodDropdownRow && prodDropdownRow.parentNode) {
        prodDropdownRow.parentNode.removeChild(prodDropdownRow);
      }
      if (prodCardSlot && prodCardSlot.parentNode) {
        prodCardSlot.parentNode.removeChild(prodCardSlot);
      }
      prodDropdownRow = null;
      prodCardSlot = null;
    }

    function openProductEditor(button) {
      if (!prodEditor) return;
      closeProductEditor();

      const id = Number(button.getAttribute('data-id') || '0');
      prodEditId.value = String(id);
      prodEditName.value = button.getAttribute('data-name') || '';
      prodEditPrice.value = String(button.getAttribute('data-base-price') || '0');
      prodEditCategory.value = String(button.getAttribute('data-category-id') || '');
      prodEditAvailability.value = button.getAttribute('data-availability') || 'in_stock';
      prodEditDescription.value = button.getAttribute('data-description') || '';
      const currentImage = button.getAttribute('data-current-image') || '';
      const previewImage = button.getAttribute('data-preview-image') || '';
      const image2Url = button.getAttribute('data-image2-url') || '';
      prodEditCurrentImage.value = currentImage;
      prodEditPreview.src = previewImage || normalizeImagePath(currentImage);
      if (image2Url && prodEditPreview2) {
        prodEditPreview2.src = image2Url;
        prodEditPreview2.style.display = 'block';
      } else if (prodEditPreview2) {
        prodEditPreview2.removeAttribute('src');
        prodEditPreview2.style.display = 'none';
      }
      if (prodEditImage) prodEditImage.value = '';
      if (prodEditImage2) prodEditImage2.value = '';
      if (prodEditChefSpecial) prodEditChefSpecial.checked = Number(button.getAttribute('data-chef-special') || '0') === 1;
      const prodEditDietary = document.getElementById('prodEditDietary');
      const prodEditIsVeg = document.getElementById('prodEditIsVeg');
      const prodEditTopperEnabled = document.getElementById('prodEditTopperEnabled');
      const prodEditNoteEnabled   = document.getElementById('prodEditNoteEnabled');
      if (prodEditDietary) prodEditDietary.value = button.getAttribute('data-dietary') || 'regular';
      if (prodEditIsVeg) prodEditIsVeg.checked = Number(button.getAttribute('data-is-veg') || '1') === 1;
      if (prodEditTopperEnabled) prodEditTopperEnabled.checked = Number(button.getAttribute('data-topper-enabled') ?? '1') === 1;
      if (prodEditNoteEnabled) prodEditNoteEnabled.checked = Number(button.getAttribute('data-note-enabled') ?? '1') === 1;

      const triggerRow = button.closest('tr');
      if (triggerRow && triggerRow.parentNode) {
        prodDropdownRow = document.createElement('tr');
        prodDropdownRow.className = 'prod-editor-row';
        const td = document.createElement('td');
        td.colSpan = 7;
        prodDropdownRow.appendChild(td);
        triggerRow.parentNode.insertBefore(prodDropdownRow, triggerRow.nextSibling);
        td.appendChild(prodEditor);
      } else {
        const card = button.closest('.product-card');
        if (card) {
          prodCardSlot = document.createElement('div');
          prodCardSlot.className = 'prod-editor-card-slot';
          card.parentNode.insertBefore(prodCardSlot, card.nextSibling);
          prodCardSlot.appendChild(prodEditor);
        } else {
          prodEditorAnchor.appendChild(prodEditor);
        }
      }

      prodEditor.classList.add('is-open');
      prodEditor.setAttribute('aria-hidden', 'false');
      prodEditor.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.addEventListener('click', (event) => {
      if (!prodEditor || !prodEditor.classList.contains('is-open')) {
        return;
      }

      const clickedEditButton = event.target.closest('.js-prod-edit');
      if (clickedEditButton) {
        return;
      }

      if (!prodEditor.contains(event.target)) {
        closeProductEditor();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && prodEditor && prodEditor.classList.contains('is-open')) {
        closeProductEditor();
      }
    });

    prodEditButtons.forEach((button) => {
      button.addEventListener('click', () => openProductEditor(button));
    });

    if (prodEditorBack) {
      prodEditorBack.addEventListener('click', closeProductEditor);
    }

    if (prodEditImage && prodEditPreview) {
      prodEditImage.addEventListener('change', function () {
        const file = prodEditImage.files && prodEditImage.files[0] ? prodEditImage.files[0] : null;
        if (!file) {
          prodEditPreview.src = normalizeImagePath(prodEditCurrentImage.value || '');
          return;
        }
        prodEditPreview.src = URL.createObjectURL(file);
      });
    }

    if (prodEditImage2 && prodEditPreview2) {
      prodEditImage2.addEventListener('change', function () {
        const file = prodEditImage2.files && prodEditImage2.files[0] ? prodEditImage2.files[0] : null;
        if (!file) {
          prodEditPreview2.removeAttribute('src');
          prodEditPreview2.style.display = 'none';
          return;
        }
        prodEditPreview2.src = URL.createObjectURL(file);
        prodEditPreview2.style.display = 'block';
      });
    }

    function normalize(v) {
      return String(v || '').toLowerCase().trim();
    }

    function applyFilters() {
      const q = normalize(search ? search.value : '');
      const c = normalize(collection ? collection.value : '');
      const s = normalize(subcategory ? subcategory.value : '');
      let visible = 0;

      rows.forEach((row) => {
        const name = normalize(row.getAttribute('data-name'));
        const rc = normalize(row.getAttribute('data-collection'));
        const rs = normalize(row.getAttribute('data-subcategory'));
        const ok = (!q || name.includes(q)) && (!c || rc === c) && (!s || rs === s);
        row.style.display = ok ? '' : 'none';
        if (ok) visible += 1;
      });

      if (visibleCount) visibleCount.textContent = String(visible);
      if (visibleRange) visibleRange.textContent = visible > 0 ? ('1-' + visible) : '0-0';

      sidebarFilters.forEach((button) => {
        const type = button.getAttribute('data-filter-type');
        const value = normalize(button.getAttribute('data-filter-value'));
        const current = type === 'collection'
          ? normalize(collection ? collection.value : '')
          : normalize(subcategory ? subcategory.value : '');
        button.classList.toggle('is-active', current === value);
      });
    }

    sidebarFilters.forEach((button) => {
      button.addEventListener('click', () => {
        const type = button.getAttribute('data-filter-type');
        const value = button.getAttribute('data-filter-value') || '';
        if (type === 'collection' && collection) {
          collection.value = value;
        }
        if (type === 'subcategory' && subcategory) {
          subcategory.value = value;
        }
        applyFilters();
      });
    });

    ['input', 'change'].forEach((evt) => {
      if (search) search.addEventListener(evt, applyFilters);
      if (collection) collection.addEventListener(evt, applyFilters);
      if (subcategory) subcategory.addEventListener(evt, applyFilters);
    });

    applyFilters();

    const focusProductId = <?php echo (int)$focusProductId; ?>;
    if (focusProductId > 0) {
      const focusBtn = document.querySelector('.js-prod-edit[data-id="' + String(focusProductId) + '"]');
      if (focusBtn) {
        openProductEditor(focusBtn);
      }
    }

    // Save spinner — disable button while form submits to prevent double-saves
    const prodEditForm = document.getElementById('prodEditForm');
    if (prodEditForm) {
      prodEditForm.addEventListener('submit', function () {
        const saveBtn = prodEditForm.querySelector('.prod-editor-save');
        if (saveBtn) {
          saveBtn.disabled = true;
          saveBtn.classList.add('is-saving');
          saveBtn.textContent = 'Saving…';
        }
      });
    }
  })();
</script>

</div>
</div>
</body>
</html>