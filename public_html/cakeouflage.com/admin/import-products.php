<?php
$pageTitle = "Import Products";
include "includes/auth.php"; // same as other admin pages
require_permission_for_current_admin_page();
require 'includes/db.php';
include 'layout.php';


?>
<?php
if (isset($_POST['upload'])) {

    $file = $_FILES['file']['tmp_name'];
//$file = $backupPath;
    // 🔥 BACKUP SYSTEM START
$originalName = $_FILES['file']['name'];

$backupDir = __DIR__ . "/backups/";

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// timestamp filename
$backupFileName = date("Y-m-d_H-i-s") . "_" . basename($originalName);

$backupPath = $backupDir . $backupFileName;

// move file copy (NOT affecting import)
move_uploaded_file($_FILES['file']['tmp_name'], $backupPath);

// now reopen for import
$file = $backupPath;
// 🔥 BACKUP SYSTEM END
    if (!$file) {
        echo "<div class='msg msg--error'>No file uploaded</div>";
    } else {

        $handle = fopen($file, "r");

        fgetcsv($handle); // skip header

        $success = 0;
        $skipped = 0;

     while (($row = fgetcsv($handle)) !== false) {

       // 🔥 skip empty rows
    if (empty(array_filter($row))) {
        continue;
    }
    $categoryName = trim($row[0]);
    $subcategoryName = trim($row[1]);
    $productName = trim($row[2]);
  
$variantPrices = [
    '500g' => $row[3] ?? '',
    '1kg'  => $row[4] ?? '',
    '2kg'  => $row[5] ?? '',
];
    // ✅ CALL FUNCTION
    $result = processRow($conn, $categoryName, $subcategoryName, $productName, $variantPrices);

    if ($result) {
        $success++;
    } else {
        $skipped++;

                    echo "<div class='msg msg--error'>Skipped row: " . implode(' | ', $row) . "</div>";
    }
}

        fclose($handle);

                echo "<div class='msg msg--success'>Imported: $success rows</div>";
                echo "<div class='msg msg--error'>Skipped: $skipped rows</div>";
    }
}
?>
<style>
.import-page {
    max-width: 1120px;
}

.import-grid {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 20px;
}

.import-card {
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.1);
    border-radius: 18px;
    padding: 22px;
    box-shadow: 0 10px 30px rgba(128, 0, 31, 0.08);
}

.import-card__title {
    margin: 0;
    font-family: 'DM Serif Display', Georgia, serif;
    font-size: 1.4rem;
    color: #80001F;
    line-height: 1.2;
}

.import-card__desc {
    margin: 8px 0 18px;
    color: #805564;
    font-size: 0.86rem;
}

.import-form {
    display: grid;
    gap: 14px;
}

.file-drop {
    display: block;
    border: 2px dashed rgba(128, 0, 31, 0.26);
    background: linear-gradient(160deg, #fff9fb 0%, #fff3f6 100%);
    border-radius: 14px;
    padding: 16px;
}

.file-drop__title {
    display: block;
    color: #6c2d3f;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.86rem;
}

.file-drop__hint {
    display: block;
    margin-top: 8px;
    color: #9a6f7f;
    font-size: 0.76rem;
}

.file-input {
    width: 100%;
    border: 1px solid rgba(128, 0, 31, 0.18);
    background: #fff;
    color: #432530;
    border-radius: 10px;
    font-size: 0.84rem;
    padding: 8px;
}

.import-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.import-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    border-radius: 10px;
    padding: 0 16px;
    text-decoration: none;
    font-size: 0.83rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.import-btn--primary {
    color: #fff;
    background: linear-gradient(135deg, #80001F 0%, #a1002a 100%);
    box-shadow: 0 8px 20px rgba(128, 0, 31, 0.25);
}

.import-btn--primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(128, 0, 31, 0.32);
}

.import-btn--secondary {
    color: #80001F;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.2);
}

.import-btn--secondary:hover {
    transform: translateY(-1px);
    background: #fff4f8;
}

.history-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 10px;
}

.history-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(128, 0, 31, 0.12);
    background: linear-gradient(160deg, #fffdfd 0%, #fff7fa 100%);
}

.history-name {
    color: #5a2f3d;
    font-size: 0.82rem;
    font-weight: 500;
    word-break: break-word;
}

.history-download {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border-radius: 8px;
    text-decoration: none;
    color: #fff;
    font-size: 0.76rem;
    font-weight: 600;
    background: linear-gradient(135deg, #80001F 0%, #9e0028 100%);
}

.history-empty {
    margin: 0;
    color: #987281;
    font-size: 0.82rem;
}

.msg {
    margin: 0 0 12px;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.83rem;
    font-weight: 500;
}

.msg--success {
    border: 1px solid #c8ead6;
    background: #effcf4;
    color: #1a6c3f;
}

.msg--error {
    border: 1px solid #f0c3c3;
    background: #fff3f3;
    color: #922f2f;
}

@media (max-width: 980px) {
    .import-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .import-card {
        padding: 16px;
    }

    .history-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .history-download {
        width: 100%;
    }
}
</style>

<div class="import-page">
    <div class="import-grid">
        <section class="import-card">
            <h2 class="import-card__title">Import Products CSV</h2>
            <p class="import-card__desc">Upload your CSV file to import categories, products, and variants.</p>

            <form method="POST" enctype="multipart/form-data" class="import-form">
                <label class="file-drop">
                    <span class="file-drop__title">Choose CSV file</span>
                    <input type="file" name="file" accept=".csv" required class="file-input">
                    <span class="file-drop__hint">Only .csv files are supported.</span>
                </label>

                <div class="import-actions">
                    <button type="submit" name="upload" class="import-btn import-btn--primary">Upload CSV</button>
                    <a href="download_products.php" class="import-btn import-btn--secondary">⬇ Download Products CSV</a>
                </div>
            </form>
        </section>

        <section class="import-card">
            <h3 class="import-card__title">Import History</h3>
            <p class="import-card__desc">Recent backup files generated before import processing.</p>

            <ul class="history-list">
            <?php
            $backupDir = __DIR__ . "/backups/";

            if (is_dir($backupDir)) {
                    $files = array_diff(scandir($backupDir), ['.', '..']);

                    foreach (array_reverse($files) as $file) {
                            $filePath = "backups/" . $file;

                            echo "<li class='history-item'>
                                            <span class='history-name'>{$file}</span>
                                            <a class='history-download' href='{$filePath}' download>Download</a>
                                        </li>";
                    }

                    if (count($files) === 0) {
                            echo "<li class='history-empty'>No backup files found.</li>";
                    }
            } else {
                    echo "<li class='history-empty'>No backup files found.</li>";
            }
            ?>
            </ul>
        </section>
    </div>
</div>

</div> <!-- main -->
</div> <!-- dashboard -->

</body>
</html>
<?php
function normalizeWeight($weight) {
    $weight = strtolower(trim($weight));
    $weight = str_replace(' ', '', $weight);
    $weight = str_replace('gm', 'g', $weight);
    return $weight;
}

//function processRow($conn, $categoryName, $subcategoryName, $productName, $weight, $price) {
// 🔥 AUTO SUBCATEGORY MAPPING

function processRow($conn, $categoryName, $subcategoryName, $productName, $variantPrices){

// 🔒 SQL safety
$productName = mysqli_real_escape_string($conn, $productName);
$categoryName = mysqli_real_escape_string($conn, $categoryName);
$subcategoryName = mysqli_real_escape_string($conn, $subcategoryName);
// 🚫 skip empty rows
if (!$categoryName || !$subcategoryName || !$productName) {
    return false;
}
//$price = floatval(preg_replace('/[^0-9.]/', '', $price));
    // 1. CATEGORY CHECK
    $catQuery = $conn->query("
        SELECT id FROM categories 
        WHERE LOWER(TRIM(name)) = LOWER(TRIM('$categoryName')) 
        AND deleted_at IS NULL
    ");

  if ($catQuery && $catQuery->num_rows > 0) {
    $cat = $catQuery->fetch_assoc();
    $categoryId = $cat['id'];
} else {

    // ✅ SAFE SLUG GENERATE
    $catSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $categoryName)));
    $catSlug = trim($catSlug, '-');

    if (empty($catSlug)) {
        $catSlug = 'category';
    }

  $catSlug = $catSlug;

    // 🔥 INSERT WITH SLUG
    $conn->query("
        INSERT INTO categories (name, slug, parent_id) 
        VALUES ('$categoryName', '$catSlug', NULL)
    ");

    $categoryId = $conn->insert_id;
}



    // 2. SUBCATEGORY CHECK
$subQuery = $conn->query("
    SELECT id FROM categories 
WHERE LOWER(TRIM(name)) = LOWER(TRIM('$subcategoryName'))
    AND parent_id = $categoryId 
    AND deleted_at IS NULL
");

if ($subQuery && $subQuery->num_rows > 0) {
    $sub = $subQuery->fetch_assoc();
    $subcategoryId = $sub['id'];
} else {

    // ✅ SAFE SLUG GENERATE
    $subSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $subcategoryName)));
    $subSlug = trim($subSlug, '-');

    if (empty($subSlug)) {
        $subSlug = 'subcategory';
    }

   $subSlug = $subSlug;

    // 🔥 INSERT WITH SLUG
    $conn->query("
        INSERT INTO categories (name, slug, parent_id) 
        VALUES ('$subcategoryName', '$subSlug', $categoryId)
    ");

    $subcategoryId = $conn->insert_id;
}
// 3. PRODUCT CHECK (ADD THIS HERE)
$prodQuery = $conn->query("
    SELECT id FROM products 
    WHERE LOWER(name)=LOWER('$productName')
    AND subcategory_id=$subcategoryId
    AND deleted_at IS NULL
");
if ($prodQuery && $prodQuery->num_rows > 0) {

    // ✅ already exists
    $prod = $prodQuery->fetch_assoc();
    $productId = $prod['id'];

} else {

    // ✅ SAFE SLUG
 $baseSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $productName)));
    $baseSlug = trim($baseSlug, '-');

    if (empty($baseSlug)) {
        $baseSlug = 'product';
    }

  $slug = $baseSlug;

    // 🔥 EXTRA SAFETY
    if (empty($slug)) {
        echo "❌ SLUG ERROR: $productName <br>";
        return false;
    }

 $sku = uniqid('SKU-');

$base_price = 0;

foreach ($variantPrices as $price) {
    if (!empty($price)) {
        $base_price = (float)$price;
        break;
    }
}

  $insert = $conn->query("
    INSERT INTO products 
    (
        name, 
        slug, 
        sku, 
        subcategory_id, 
        collection_category_id, 
        base_price, 
        starting_price,
        availability_status
    ) 
    VALUES 
    (
        '$productName', 
        '$slug', 
        '$sku', 
        $subcategoryId, 
        $categoryId, 
        $base_price, 
        $base_price,
        'in_stock'
    )
");

    if (!$insert) {
        echo "❌ Product insert error: " . $conn->error . "<br>";
        return false;
    }

    $productId = $conn->insert_id;
}
// 4. VARIANT CHECK (FINAL CLEAN VERSION)

// 4. VARIANTS LOOP (NEW LOGIC)



foreach ($variantPrices as $weight => $price) {

    if ($price === '' || $price === null) {
        continue;
    }

    $weight = normalizeWeight($weight);

    $price = (float)$price;

    $weight = mysqli_real_escape_string($conn, $weight);

   

    // check existing variant
    $varQuery = $conn->query("
        SELECT id FROM product_variants 
        WHERE product_id = $productId 
        AND weight_or_size = '$weight'
    ");

    if ($varQuery && $varQuery->num_rows > 0) {

        $var = $varQuery->fetch_assoc();

        $conn->query("
            UPDATE product_variants 
            SET price = $price, stock_quantity = 10
            WHERE id = ".$var['id']
        );

    } else {

        $label = $weight;

        $checkDefault = $conn->query("
            SELECT id FROM product_variants 
            WHERE product_id = $productId AND is_default = 1
        ");

        $isDefault = ($checkDefault && $checkDefault->num_rows == 0) ? 1 : 0;

        $conn->query("
            INSERT INTO product_variants 
            (product_id, variant_label, weight_or_size, price, is_default, stock_quantity) 
            VALUES 
            ($productId, '$label', '$weight', $price, $isDefault, 10)
        ");
    }
}

return true;
}
?>