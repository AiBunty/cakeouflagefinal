<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

// DB connection
require __DIR__ . '/includes/db.php';
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
    $check = $conn->query("SELECT id FROM products WHERE slug = '$slug'");
    
    if ($check->num_rows == 0) {
        break; // unique slug मिळाला
    }

    $slug = $base_slug . "-" . $i;
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

    $db_image_path = NULL;

    if (!empty($_FILES['image']['name'])) {

        $image_name = $_FILES['image']['name'];
        $tmp_name = $_FILES['image']['tmp_name'];

        $new_name = time() . "_" . $image_name;

        $upload_path = "../client/assets/images/product/" . $new_name;

        if (move_uploaded_file($tmp_name, $upload_path)) {
            $db_image_path = "/client/assets/images/product/" . $new_name;
        }
    }

    // =========================
    // INSERT QUERY
    // =========================
$child_id_value = ($child_id !== NULL && $child_id != 0) ? "'$child_id'" : "NULL";
$subcategory_id_value = ($subcategory_id !== NULL && $subcategory_id != 0) ? "'$subcategory_id'" : "NULL";
$collection_id_value = ($collection_id !== NULL && $collection_id != 0) ? "'$collection_id'" : "NULL";

  $sql = "INSERT INTO products 
(name, slug, sku, starting_price, collection_category_id, subcategory_id, child_category_id, featured_image, short_description, created_at)
VALUES 
('$name', '$slug', '$sku', '$base_price', $collection_id_value, $subcategory_id_value, $child_id_value, '$db_image_path', '$description', NOW())";

    if ($conn->query($sql) === TRUE) {
   $product_id = $conn->insert_id;

$weights = [1, 1.5, 2, 2.5, 3, 4];

foreach ($weights as $index => $weight) {

    $variant_price = $base_price * $weight;

    $label = $weight . " lb";

    $is_default = ($index == 0) ? 1 : 0; // 🔥 first variant default

    $conn->query("
        INSERT INTO product_variants 
        (product_id, variant_label, weight_or_size, price, stock_quantity, is_default, is_active)
        VALUES 
        ('$product_id', '$label', '$weight', '$variant_price', 100, '$is_default', 1)
    ");
}
        echo "<script>alert('Product Added Successfully'); window.location='products.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}

// =========================
// FETCH CATEGORIES
// =========================

$categories = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");

$pageTitle = "Add Product";
include "layout.php";
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
</style>

<div class="product-form-card">
<h2>Add Product</h2>

<form method="POST" enctype="multipart/form-data">

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
<label>Image</label>
<input class="form-control" type="file" name="image" id="productImageInput" accept="image/*">
<img id="productImagePreview" class="image-preview" alt="Product preview">
</div>

<div class="form-group">
<label>Description</label>
<textarea class="form-control" name="description"></textarea>
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
                productImagePreview.style.display = 'none';
                productImagePreview.removeAttribute('src');
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            productImagePreview.src = objectUrl;
            productImagePreview.style.display = 'block';
        });
    }
</script>

</div>
</div>
</body>
</html>