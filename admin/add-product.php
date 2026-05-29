<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

// DB connection
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/image_helpers.php';
require_once __DIR__ . '/../app/Services/UnifiedMediaService.php';
require_once __DIR__ . '/../app/Support/dietary-mode.php';

$defaultProductImage = \App\Services\ProductImageService::placeholderForCategory(null);
$storeFoodMode = getDietaryMode($conn);
$foodTypeOptions = getDietaryTypeOptions($storeFoodMode);

function add_product_stmt_bind(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || !$params) {
        return true;
    }

    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }

    return $stmt->bind_param($types, ...$refs);
}

function add_product_column_exists(mysqli $conn, string $table, string $column): bool
{
    $sql = 'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?';
    $stmt = safePrepare($conn, $sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return (int)($row['c'] ?? 0) > 0;
}

function add_product_enum_values(mysqli $conn, string $table, string $column, array $fallback): array
{
    $sql = 'SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1';
    $stmt = safePrepare($conn, $sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $type = (string)($row['COLUMN_TYPE'] ?? '');
    if ($type === '' || stripos($type, 'enum(') !== 0) {
        return $fallback;
    }
    if (!preg_match_all("/'([^']+)'/", $type, $m) || empty($m[1])) {
        return $fallback;
    }
    $vals = array_values(array_unique(array_map('strval', $m[1])));
    return $vals !== [] ? $vals : $fallback;
}

function add_product_size_master_rows(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query('SELECT id, label, sort_order, is_active FROM product_size_master ORDER BY sort_order ASC, id ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    if ($rows === []) {
        $rows = [
            ['id' => 0, 'label' => 'Per Pcs', 'sort_order' => 10, 'is_active' => 1],
            ['id' => 0, 'label' => '0.5 kg', 'sort_order' => 20, 'is_active' => 1],
            ['id' => 0, 'label' => '1 kg', 'sort_order' => 30, 'is_active' => 1],
            ['id' => 0, 'label' => '1.5 kg', 'sort_order' => 40, 'is_active' => 1],
            ['id' => 0, 'label' => '2 kg', 'sort_order' => 50, 'is_active' => 1],
        ];
    }

    return $rows;
}

function add_product_normalize_size_label(string $label): string
{
    $value = strtolower(trim($label));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $value;
}

function add_product_parse_matrix_payload(string $raw): array
{
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $rows = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = trim((string)($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }

        $rows[] = [
            'label' => $label,
            'price' => round((float)($row['price'] ?? 0), 2),
            'stock_quantity' => max(0, (int)($row['stock_quantity'] ?? 0)),
            'sku' => trim((string)($row['sku'] ?? '')),
            'is_default' => (int)($row['is_default'] ?? 0) === 1 ? 1 : 0,
            'size_id' => (int)($row['size_id'] ?? 0),
        ];
    }

    return $rows;
}

function add_product_matrix_default_price(array $matrixRows): float
{
    foreach ($matrixRows as $row) {
        $price = (float)($row['price'] ?? 0);
        if ($price > 0) {
            return round($price, 2);
        }
    }

    return 0.0;
}

function add_product_matrix_to_variants(array $matrixRows): array
{
    $variants = [];
    foreach ($matrixRows as $index => $row) {
        $label = trim((string)($row['label'] ?? ''));
        $price = round((float)($row['price'] ?? 0), 2);
        if ($label === '' || $price <= 0) {
            continue;
        }

        $variants[] = [
            'variant_label' => $label,
            'variant_name' => $label,
            'weight_or_size' => $label,
            'unit_type' => 'custom',
            'price' => $price,
            'stock_quantity' => max(0, (int)($row['stock_quantity'] ?? 0)),
            'sku' => trim((string)($row['sku'] ?? '')),
            'is_default' => (int)($row['is_default'] ?? 0) === 1 ? 1 : ($index === 0 ? 1 : 0),
        ];
    }

    return $variants;
}
// =========================
// BACKEND LOGIC
// =========================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $matrixRows = add_product_parse_matrix_payload((string)($_POST['matrix_json'] ?? ''));
    $legacyBasePrice = (float)($_POST['base_price'] ?? 0);
    $base_price = !empty($matrixRows) ? add_product_matrix_default_price($matrixRows) : $legacyBasePrice;
    if ($base_price <= 0) {
        die("Invalid base price");
    }

    // ❌ validation
    if (empty($category_id)) {
        die("Please select category");
    }

    // slug
   $base_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
$slug = $base_slug;

$i = 1;

while (true) {
    $check = safePrepare($conn, 'SELECT id FROM products WHERE slug = ? LIMIT 1');
    $check->bind_param('s', $slug);
    $check->execute();
    $checkResult = $check->get_result();
    if (!$checkResult || $checkResult->num_rows === 0) {
        break;
    }
    $slug = $base_slug . '-' . $i;
    $i++;
}

    // sku
    $sku = "CK" . time();

    // =========================
    // CATEGORY MAPPING LOGIC
    // =========================

    $selected_id = (int)$category_id;

    $collection_id = NULL;
    $subcategory_id = NULL;
    $child_id = NULL;

    // STEP 1
    $res1 = $conn->query("SELECT parent_id FROM categories WHERE id = $selected_id");

    if (!$res1 || $res1->num_rows == 0) {
        die("Invalid category selected");
    }

    $row1 = $res1->fetch_assoc();
    $parent1 = $row1['parent_id'];

    // CASE 1: MAIN
    if ($parent1 == NULL) {

        $collection_id = $selected_id;
    } else {

        // STEP 2
        $res2 = $conn->query("SELECT parent_id FROM categories WHERE id = $parent1");

        if (!$res2 || $res2->num_rows == 0) {
            die("Parent category error");
        }

        $row2 = $res2->fetch_assoc();
        $parent2 = $row2['parent_id'];

        // CASE 2: SUB
        if ($parent2 == NULL) {

            $collection_id = $parent1;
            $subcategory_id = $selected_id;
        }

        // CASE 3: CHILD
        else {

            $collection_id = $parent2;
            $subcategory_id = $parent1;
            $child_id = $selected_id;
        }
    }

    // =========================
    // IMAGE UPLOAD
    // =========================

    // Default to the branded fallback; replaced below if a valid file is uploaded.
    $db_image_path = $defaultProductImage;

    if (!empty($_FILES['image']['name']) && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $imgResult  = \App\Services\UnifiedMediaService::upload(
            $_FILES['image'],
            [
                'module' => 'product',
                'entity_type' => 'product',
                'entity_id' => 0,
                'admin_id' => (int)($_SESSION['admin'] ?? 0),
                'allow_svg' => false,
            ]
        );
        if ($imgResult['ok']) {
            $db_image_path = $imgResult['relative_url'];
        } else {
            error_log('[add-product.php] Image upload failed: ' . $imgResult['error']);
        }
    }

    // IMAGE 2
    $db_image2_path = NULL;
    if (!empty($_FILES['image2']['name']) && (int)($_FILES['image2']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $img2Result  = \App\Services\UnifiedMediaService::upload(
            $_FILES['image2'],
            [
                'module' => 'product_image_2',
                'entity_type' => 'product',
                'entity_id' => 0,
                'admin_id' => (int)($_SESSION['admin'] ?? 0),
                'allow_svg' => false,
            ]
        );
        if ($img2Result['ok']) {
            $db_image2_path = $img2Result['relative_url'];
        } else {
            error_log('[add-product.php] Image2 upload failed: ' . $img2Result['error']);
        }
    }

    if ($db_image2_path !== NULL && trim($db_image2_path) === trim($db_image_path)) {
        $db_image2_path = NULL;
    }

    // =========================
    // INSERT QUERY
    // =========================
$is_chef_special = isset($_POST['is_chef_special']) ? 1 : 0;
$dietary_type    = normalizeDietaryType((string)($_POST['dietary_type'] ?? 'veg'), $storeFoodMode);
$allowedDietary  = add_product_enum_values($conn, 'products', 'dietary_tag', ['regular','eggless','vegan','sugar_free']);
$dietary_tag     = in_array($_POST['dietary_tag'] ?? '', $allowedDietary, true) ? (string)$_POST['dietary_tag'] : (in_array('regular', $allowedDietary, true) ? 'regular' : (string)($allowedDietary[0] ?? 'regular'));
$is_veg          = dietaryTypeToIsVeg($dietary_type);
$topper_enabled  = isset($_POST['topper_enabled']) ? 1 : 0;
$note_enabled    = isset($_POST['note_enabled']) ? 1 : 0;
$base_price_str  = (string)(float)$base_price;

    $insertColumns = [
        'name', 'slug', 'sku', 'starting_price', 'base_price',
        'collection_category_id', 'subcategory_id', 'child_category_id',
        'featured_image', 'short_description', 'long_description', 'is_chef_special'
    ];
    $insertTypes = 'sssssiiisssi';
    $insertParams = [
        $name, $slug, $sku, $base_price_str, $base_price_str,
        $collection_id, $subcategory_id, $child_id,
        $db_image_path, $description, $description, $is_chef_special,
    ];

    if (add_product_column_exists($conn, 'products', 'dietary_tag')) {
        $insertColumns[] = 'dietary_tag';
        $insertTypes .= 's';
        $insertParams[] = $dietary_tag;
    }
    if (add_product_column_exists($conn, 'products', 'description')) {
        $insertColumns[] = 'description';
        $insertTypes .= 's';
        $insertParams[] = $description;
    }
    if (add_product_column_exists($conn, 'products', 'is_veg')) {
        $insertColumns[] = 'is_veg';
        $insertTypes .= 'i';
        $insertParams[] = $is_veg;
    }
    if (add_product_column_exists($conn, 'products', 'dietary_type')) {
        $insertColumns[] = 'dietary_type';
        $insertTypes .= 's';
        $insertParams[] = $dietary_type;
    }
    if (add_product_column_exists($conn, 'products', 'topper_enabled')) {
        $insertColumns[] = 'topper_enabled';
        $insertTypes .= 'i';
        $insertParams[] = $topper_enabled;
    }
    if (add_product_column_exists($conn, 'products', 'note_enabled')) {
        $insertColumns[] = 'note_enabled';
        $insertTypes .= 'i';
        $insertParams[] = $note_enabled;
    }

    $insertColumns[] = 'created_at';
    $placeholderSql = implode(', ', array_fill(0, count($insertColumns) - 1, '?')) . ', NOW()';
    $insertSql = 'INSERT INTO products (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholderSql . ')';
    $insertStmt = safePrepare($conn, $insertSql);
    add_product_stmt_bind($insertStmt, $insertTypes, $insertParams);

    if ($insertStmt->execute()) {
   $product_id = $conn->insert_id;

   // Insert images into product_images for gallery support
   if ($db_image_path !== NULL) {
       $piStmt = $conn->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, 0)');
       $piStmt->bind_param('is', $product_id, $db_image_path);
       $piStmt->execute();
       $piStmt->close();
   }
   if ($db_image2_path !== NULL) {
       $pi2Stmt = $conn->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, 1)');
       $pi2Stmt->bind_param('is', $product_id, $db_image2_path);
       $pi2Stmt->execute();
       $pi2Stmt->close();
   }

    $rows = !empty($matrixRows) ? $matrixRows : [];
   if ($rows === []) {
       $rows = add_product_matrix_to_variants([[ 'label' => '1 lb', 'price' => $base_price, 'stock_quantity' => 100, 'sku' => '', 'is_default' => 1 ]]);
   }
   if (!empty($rows) && !array_filter($rows, static fn(array $row): bool => (int)($row['is_default'] ?? 0) === 1)) {
       $rows[0]['is_default'] = 1;
   }

   $variantRows = !empty($matrixRows) ? add_product_matrix_to_variants($matrixRows) : $rows;
   if ($variantRows === []) {
       $variantRows = [[
           'variant_label' => '1 lb',
           'variant_name' => '1 lb',
           'weight_or_size' => '1 lb',
           'unit_type' => 'custom',
           'price' => round((float)$base_price, 2),
           'stock_quantity' => 100,
           'sku' => '',
           'is_default' => 1,
       ]];
   }

   $hasVariantName = add_product_column_exists($conn, 'product_variants', 'variant_name');
   $hasUnitType = add_product_column_exists($conn, 'product_variants', 'unit_type');
   $hasSku = add_product_column_exists($conn, 'product_variants', 'sku');

   foreach ($variantRows as $rowItem) {
       $variantColumns = ['product_id', 'variant_label'];
       $variantValues = [$product_id, $rowItem['variant_label']];
       $variantTypes = 'is';

       if ($hasVariantName) {
           $variantColumns[] = 'variant_name';
           $variantTypes .= 's';
           $variantValues[] = $rowItem['variant_name'];
       }

       $variantColumns[] = 'weight_or_size';
       $variantTypes .= 's';
       $variantValues[] = $rowItem['weight_or_size'];

       if ($hasUnitType) {
           $variantColumns[] = 'unit_type';
           $variantTypes .= 's';
           $variantValues[] = $rowItem['unit_type'];
       }

       $variantColumns[] = 'price';
       $variantTypes .= 'd';
       $variantValues[] = $rowItem['price'];

       $variantColumns[] = 'stock_quantity';
       $variantTypes .= 'i';
       $variantValues[] = (int)($rowItem['stock_quantity'] ?? 0);

       $variantColumns[] = 'is_default';
       $variantTypes .= 'i';
       $variantValues[] = (int)$rowItem['is_default'];

       if ($hasSku) {
           $variantColumns[] = 'sku';
           $variantTypes .= 's';
           $variantValues[] = $rowItem['sku'];
       }

       $variantColumns[] = 'is_active';
       $variantTypes .= 'i';
       $variantValues[] = 1;

       $variantSql = 'INSERT INTO product_variants (' . implode(', ', $variantColumns) . ') VALUES (' . implode(', ', array_fill(0, count($variantColumns), '?')) . ')';
       $varStmt = safePrepare($conn, $variantSql);
       add_product_stmt_bind($varStmt, $variantTypes, $variantValues);
       $varStmt->execute();
       $varStmt->close();
   }
        echo "<script>alert('Product Added Successfully'); window.location='products.php';</script>";
    } else {
        error_log('[add-product.php] INSERT failed: ' . $conn->error);
        echo "<script>alert('Error saving product. Please try again.'); history.back();</script>";
    }
}

// =========================
// FETCH CATEGORIES
// =========================

$categories = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
$sizeMasterRows = add_product_size_master_rows($conn);

$pageTitle = "Add Product";
require_once __DIR__ . '/layout.php';
?>
<style>
    .product-form-card {
        background: #fff;
        border: 1px solid rgba(128, 0, 31, 0.12);
        border-radius: 20px;
        box-shadow: 0 14px 30px rgba(68, 16, 34, 0.09);
        max-width: 760px;
        padding: 24px;
    }

    .product-form-card h2 {
        margin: 0 0 16px;
        font-family: 'DM Serif Display', Georgia, serif;
        color: #80001F;
        font-weight: 400;
    }

    .form-group {
        margin-bottom: 14px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-size: 0.85rem;
        color: #5f4a53;
        font-weight: 600;
    }

    .form-control {
        width: 100%;
        padding: 11px 12px;
        border-radius: 12px;
        border: 1px solid rgba(128, 0, 31, 0.16);
        background: #fffafb;
        font: inherit;
        box-sizing: border-box;
        transition: border-color 180ms ease, box-shadow 180ms ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #80001F;
        box-shadow: 0 0 0 3px rgba(128, 0, 31, 0.14);
    }

    textarea.form-control {
        min-height: 96px;
        resize: vertical;
    }

    .form-actions {
        margin-top: 8px;
    }

    .form-section {
        border: 1px solid rgba(128, 0, 31, 0.1);
        border-radius: 14px;
        padding: 14px;
        margin-bottom: 14px;
        background: #fff;
    }

    .form-section h3 {
        margin: 0 0 6px;
        font-size: 0.95rem;
        color: #6f1130;
    }

    .form-section__hint {
        margin: 0 0 10px;
        font-size: 0.78rem;
        color: #8f6e7b;
    }

    .feature-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .feature-item {
        border: 1px solid rgba(128, 0, 31, 0.12);
        border-radius: 10px;
        padding: 8px 10px;
        background: #fff9fb;
    }

    .feature-item label {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.84rem;
        color: #4f2e39;
        cursor: pointer;
    }

    .feature-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        margin: 0;
    }

    .matrix-grid {
        display: grid;
        gap: 10px;
    }

    .matrix-row {
        display: grid;
        grid-template-columns: minmax(120px, 1.4fr) repeat(3, minmax(0, 1fr)) auto;
        gap: 10px;
        padding: 12px;
        border: 1px solid rgba(128, 0, 31, 0.11);
        border-radius: 14px;
        background: linear-gradient(180deg, #fff, #fff9fb);
        align-items: end;
    }

    .matrix-row__label {
        display: grid;
        gap: 4px;
    }

    .matrix-row__label strong {
        color: #2d1f25;
        font-size: 0.94rem;
    }

    .matrix-row__label span {
        color: #8a7480;
        font-size: 0.72rem;
    }

    .matrix-row__field {
        display: grid;
        gap: 6px;
    }

    .matrix-row__field label {
        font-size: 0.72rem;
        font-weight: 600;
        color: #7b1a39;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .matrix-row__field input {
        width: 100%;
        padding: 11px 12px;
        border-radius: 12px;
        border: 1px solid rgba(128, 0, 31, 0.16);
        background: #fffafb;
        font: inherit;
        box-sizing: border-box;
    }

    .matrix-row__field--default {
        align-self: center;
        padding-bottom: 4px;
    }

    .matrix-row__field--default label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-transform: none;
        letter-spacing: 0;
        color: #4f2e39;
        font-size: 0.8rem;
    }

    .matrix-summary {
        margin-top: 8px;
        color: #8f6e7b;
        font-size: 0.78rem;
    }

    .btn {
        background: #80001F;
        color: #fff;
        padding: 11px 16px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        letter-spacing: 0.03em;
    }

    .btn:hover {
        background: #5f0017;
    }

    .image-preview {
        margin-top: 10px;
        display: none;
        width: 170px;
        height: 170px;
        border-radius: 14px;
        border: 1px solid rgba(128, 0, 31, 0.18);
        object-fit: cover;
        background: #f8d8de;
        box-shadow: 0 10px 22px rgba(96, 18, 45, 0.14);
    }

    @media (max-width: 760px) {
        .feature-grid {
            grid-template-columns: 1fr;
        }

        .matrix-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="product-form-card">
<h2>Add Product</h2>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="matrix_json" id="matrixJson" value="[]">
<input type="hidden" name="base_price" id="matrixBasePrice" value="0">

<div class="form-section">
<h3>Basic Product Info</h3>
<p class="form-section__hint">Set the core product details and category mapping.</p>
<div class="form-group">
<label>Name</label>
<input class="form-control" type="text" name="name" required>
</div>

<div class="form-group">
<label>Category</label>
<select class="form-control" name="category_id" required>
<option value="">Select Category</option>
<?php while($cat = $categories->fetch_assoc()): ?>
<option value="<?= $cat['id']; ?>"><?= $cat['name']; ?></option>
<?php endwhile; ?>
</select>
</div>

<div class="form-group">
<label>Food Type</label>
<select class="form-control" name="dietary_type">
        <?php foreach ($foodTypeOptions as $foodType): ?>
            <option value="<?= htmlspecialchars($foodType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($foodType === 'nonveg' ? 'Non-Veg' : 'Veg', ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label>Dietary Tag</label>
<select class="form-control" name="dietary_tag">
    <option value="regular">Regular</option>
    <option value="eggless">Eggless</option>
    <option value="vegan">Vegan</option>
    <option value="sugar_free">Sugar Free</option>
    <option value="healthy">Healthy</option>
</select>
</div>
</div>

<div class="form-section">
<h3>Product Media</h3>
<p class="form-section__hint">Use Image 1 as primary image, and Image 2 as optional secondary image. If no image is uploaded, the Cakeouflage brand default will be used automatically.</p>
<div class="form-group">
<label>Image 1</label>
<input class="form-control" type="file" name="image" id="productImageInput" accept="image/*">
<img id="productImagePreview" class="image-preview" src="<?php echo htmlspecialchars($defaultProductImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Product preview" style="display:block;" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($defaultProductImage, ENT_QUOTES, 'UTF-8'); ?>';">
</div>

<div class="form-group">
<label>Image 2 <span style="font-weight:400;opacity:.7;">(optional)</span></label>
<input class="form-control" type="file" name="image2" id="productImage2Input" accept="image/*">
<img id="productImage2Preview" class="image-preview" alt="Product image 2 preview">
</div>
</div>

<div class="form-section">
<h3>Product Features</h3>
<div class="feature-grid">
    <div class="feature-item">
        <label><input type="checkbox" name="is_chef_special" value="1"> Chef's Special</label>
    </div>
    <div class="feature-item">
        <label><input type="checkbox" name="is_veg" value="0"> Mark as Non-Veg</label>
    </div>
    <div class="feature-item">
        <label><input type="checkbox" name="topper_enabled" value="1" checked> Enable Topper Selection</label>
    </div>
    <div class="feature-item">
        <label><input type="checkbox" name="note_enabled" value="1" checked> Enable Note on Cake</label>
    </div>
</div>
</div>

<div class="form-section">
<h3>Description</h3>
<div class="form-group">
<label>Description</label>
<textarea class="form-control" name="description"></textarea>
</div>
</div>

<div class="form-section">
<h3>Pricing Matrix</h3>
<p class="form-section__hint">Each active size is editable here. The first priced row becomes the compatibility base price.</p>
<div id="matrixRows" class="matrix-grid"></div>
<div class="form-actions" style="margin-top:10px;">
    <button class="btn btn--secondary" id="addVariantBtn" type="button">+ Add Custom Size</button>
</div>
</div>

<div class="form-actions">
    <button class="btn">Add Product</button>
</div>

</form>

</div>

</div>

<script>
    const productImageInput = document.getElementById('productImageInput');
    const productImagePreview = document.getElementById('productImagePreview');

    if (productImageInput && productImagePreview) {
        productImageInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                productImagePreview.src = <?php echo json_encode($defaultProductImage, JSON_UNESCAPED_SLASHES); ?>;
                productImagePreview.style.display = 'block';
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            productImagePreview.src = objectUrl;
            productImagePreview.style.display = 'block';
        });
    }

    const productImage2Input = document.getElementById('productImage2Input');
    const productImage2Preview = document.getElementById('productImage2Preview');
    if (productImage2Input && productImage2Preview) {
        productImage2Input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                productImage2Preview.style.display = 'none';
                productImage2Preview.removeAttribute('src');
                return;
            }
            productImage2Preview.src = URL.createObjectURL(file);
            productImage2Preview.style.display = 'block';
        });
    }

    const matrixRows = document.getElementById('matrixRows');
    const addVariantBtn = document.getElementById('addVariantBtn');
    const matrixJson = document.getElementById('matrixJson');
    const matrixBasePrice = document.getElementById('matrixBasePrice');
    const sizeMasterRows = <?php echo json_encode(array_map(static function (array $row): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'label' => (string)($row['label'] ?? ''),
            'is_active' => (int)($row['is_active'] ?? 1),
        ];
    }, $sizeMasterRows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function addVariantRow(seed) {
        if (!matrixRows) {
            return;
        }
        const rowIndex = matrixRows.children.length;
        const row = document.createElement('div');
        row.className = 'matrix-row';
        row.dataset.matrixRow = '1';
        row.dataset.sizeId = String(seed?.sizeId || 0);
        row.dataset.sizeLabel = seed?.name || '';
        row.innerHTML = `
            <div class="matrix-row__label">
                <strong>${seed?.name || ''}</strong>
                <span>${seed?.active ? 'Active size' : 'Disabled size'}</span>
            </div>
            <div class="matrix-row__field">
                <label>Price</label>
                <input type="number" step="0.01" min="0" data-matrix-price value="${seed?.price || ''}">
            </div>
            <div class="matrix-row__field">
                <label>Stock</label>
                <input type="number" step="1" min="0" data-matrix-stock value="${seed?.stock || '0'}">
            </div>
            <div class="matrix-row__field">
                <label>SKU (optional)</label>
                <input type="text" data-matrix-sku value="${seed?.sku || ''}" placeholder="CK-CHOC-1LB">
            </div>
            <div class="matrix-row__field matrix-row__field--default">
                <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;margin:0;">
                    <input type="radio" name="matrix_default_row" value="${rowIndex}" ${seed?.isDefault ? 'checked' : ''} data-matrix-default>
                    Default
                </label>
                <button class="btn btn--danger" type="button">Remove</button>
            </div>
        `;

        const removeBtn = row.querySelector('.btn--danger');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                row.remove();
                const defaultInputs = matrixRows.querySelectorAll('input[name="matrix_default_row"]');
                defaultInputs.forEach((input, idx) => {
                    input.value = String(idx);
                });
                if (![...defaultInputs].some((i) => i.checked) && defaultInputs[0]) {
                    defaultInputs[0].checked = true;
                }
            });
        }

        matrixRows.appendChild(row);
    }

    function syncMatrixPayload() {
        if (!matrixRows || !matrixJson || !matrixBasePrice) {
            return;
        }

        const rows = Array.from(matrixRows.querySelectorAll('[data-matrix-row]')).map((row) => {
            const priceInput = row.querySelector('[data-matrix-price]');
            const stockInput = row.querySelector('[data-matrix-stock]');
            const skuInput = row.querySelector('[data-matrix-sku]');
            const defaultInput = row.querySelector('[data-matrix-default]');
            return {
                size_id: Number(row.dataset.sizeId || '0'),
                label: row.dataset.sizeLabel || '',
                price: Number(priceInput && priceInput.value ? priceInput.value : '0'),
                stock_quantity: Number(stockInput && stockInput.value ? stockInput.value : '0'),
                sku: String(skuInput && skuInput.value ? skuInput.value : '').trim(),
                is_default: defaultInput && defaultInput.checked ? 1 : 0,
            };
        });

        const firstPriced = rows.find((row) => Number(row.price || 0) > 0);
        matrixBasePrice.value = firstPriced ? Number(firstPriced.price).toFixed(2) : '0';
        matrixJson.value = JSON.stringify(rows);
    }

    if (matrixRows) {
        matrixRows.addEventListener('input', syncMatrixPayload);
        matrixRows.addEventListener('change', syncMatrixPayload);
    }

    if (addVariantBtn) {
        addVariantBtn.addEventListener('click', function () {
            addVariantRow({ name: '', price: '', stock: '0', sku: '', isDefault: false, sizeId: 0, active: true });
        });
    }

    sizeMasterRows.forEach(function (row, index) {
        addVariantRow({
            name: row.label,
            price: '',
            stock: '0',
            sku: '',
            isDefault: index === 0,
            sizeId: row.id,
            active: Number(row.is_active || 1) === 1,
        });
    });
    syncMatrixPayload();

    const form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', function () {
            syncMatrixPayload();
        });
    }
</script>

</div>
</div>
</body>
</html>