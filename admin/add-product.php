<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

// DB connection
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/image_helpers.php';
require_once __DIR__ . '/../app/Services/UnifiedMediaService.php';

$defaultProductImage = \App\Services\ProductImageService::placeholderForCategory(null);

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
// =========================
// BACKEND LOGIC
// =========================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST['name'];
  $base_price = $_POST['base_price'];
  if ($base_price <= 0) {
    die("Invalid base price");
}
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];

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
$allowedDietary  = add_product_enum_values($conn, 'products', 'dietary_tag', ['regular','eggless','vegan','sugar_free']);
$dietary_tag     = in_array($_POST['dietary_tag'] ?? '', $allowedDietary, true) ? (string)$_POST['dietary_tag'] : (in_array('regular', $allowedDietary, true) ? 'regular' : (string)($allowedDietary[0] ?? 'regular'));
$is_veg          = (isset($_POST['is_veg']) && $_POST['is_veg'] === '0') ? 0 : 1;
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
    if (add_product_column_exists($conn, 'products', 'is_veg')) {
        $insertColumns[] = 'is_veg';
        $insertTypes .= 'i';
        $insertParams[] = $is_veg;
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

$weights = [1, 1.5, 2, 2.5, 3, 4];

foreach ($weights as $index => $weight) {

    $variant_price = $base_price * $weight;

    $label = $weight . " lb";

    $is_default = ($index == 0) ? 1 : 0; // 🔥 first variant default

    $varStmt = safePrepare($conn,
        'INSERT INTO product_variants (product_id, variant_label, weight_or_size, price, stock_quantity, is_default, is_active)
         VALUES (?, ?, ?, ?, 100, ?, 1)'
    );
    $varWeight = (string)$weight;
    $varPrice  = (string)round($variant_price, 2);
    $varStmt->bind_param('isssi', $product_id, $label, $varWeight, $varPrice, $is_default);
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
    }
</style>

<div class="product-form-card">
<h2>Add Product</h2>

<form method="POST" enctype="multipart/form-data">

<div class="form-section">
<h3>Basic Product Info</h3>
<p class="form-section__hint">Set the core product details and category mapping.</p>
<div class="form-group">
<label>Name</label>
<input class="form-control" type="text" name="name" required>
</div>

<div class="form-group">
<label>Base Price (per lb)</label>
<input class="form-control" type="number" step="0.01" name="base_price" required>
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
<label>Dietary Type</label>
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
</script>

</div>
</div>
</body>
</html>