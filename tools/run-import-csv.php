<?php
/**
 * run-import-csv.php
 * CLI tool: import a product CSV into the Cakeouflage DB
 *
 * Usage:
 *   php tools/run-import-csv.php [path/to/file.csv] [--live]
 *
 *   --live   Connect to LIVE_DB_* credentials from .env instead of local DB_*
 *
 * CSV format (17 columns, with header row):
 *   category_name, subcategory_name, product_name,
 *   per_piece, 0.5lb, 1lb, 1.5lb, 2lb, 2.5lb, 3lb, 3.5lb, 4lb, 4.5lb, 5lb,
 *   is_chef_special, dietary_tag, is_veg
 */

define('WEIGHT_KEYS', ['per_piece', '0.5lb', '1lb', '1.5lb', '2lb', '2.5lb', '3lb', '3.5lb', '4lb', '4.5lb', '5lb']);

const DIETARY_MAP = [
    'Regular'    => 'regular',
    'Eggless'    => 'eggless',
    'Vegan'      => 'vegan',
    'Sugar Free' => 'sugar_free',
    'Healthy'    => 'regular',   // fallback: not in DB enum
    'regular'    => 'regular',
    'eggless'    => 'eggless',
    'vegan'      => 'vegan',
    'sugar_free' => 'sugar_free',
    'sugar free' => 'sugar_free',
    'healthy'    => 'regular',
];

// ── Parse CLI args ────────────────────────────────────────────────────────────
$args        = array_slice($argv ?? [], 1);
$liveModeFlag = in_array('--live', $args, true);
$args        = array_values(array_filter($args, fn($a) => $a !== '--live'));
$csvPath     = $args[0] ?? __DIR__ . '/cakeitaway-catalogue.csv';

if (!file_exists($csvPath)) {
    fwrite(STDERR, "ERROR: CSV not found: $csvPath\n");
    exit(1);
}

// ── Load .env ─────────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\n\r\"'");
        if (!array_key_exists($key, $_ENV)) { $_ENV[$key] = $val; putenv("$key=$val"); }
    }
}

function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── DB connection ─────────────────────────────────────────────────────────────
if ($liveModeFlag) {
    $host   = env('LIVE_DB_HOST');
    $port   = (int)(env('LIVE_DB_PORT') ?: 3306);
    $dbname = env('LIVE_DB_NAME') ?: env('LIVE_DB_DATABASE');
    $user   = env('LIVE_DB_USER') ?: env('LIVE_DB_USERNAME');
    $pass   = env('LIVE_DB_PASSWORD');
    fwrite(STDERR, "Mode : LIVE  ($host:$port / $dbname)\n");
} else {
    $host   = env('DB_HOST', 'db');
    $port   = (int)(env('DB_PORT') ?: 3306);
    $dbname = env('DB_NAME');
    $user   = env('DB_USER');
    $pass   = env('DB_PASSWORD');
    fwrite(STDERR, "Mode : LOCAL ($host:$port / $dbname)\n");
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    fwrite(STDERR, "Connected to DB: $dbname at $host:$port\n");
} catch (\PDOException $e) {
    fwrite(STDERR, "DB connect failed: " . $e->getMessage() . "\n");
    exit(1);
}

// ── Weight normaliser ──────────────────────────────────────────────────────────
function normalizeWeight(string $w): string {
    $w = strtolower(trim($w));
    if ($w === 'per_piece' || $w === 'per piece') return 'per_piece';
    $w = str_replace(' ', '', $w);
    $w = str_replace('gm', 'g', $w);
    return $w;
}

// ── processRow (mirrors admin/import-products.php exactly) ────────────────────
function processRow(
    PDO    $pdo,
    string $categoryName,
    string $subcategoryName,
    string $productName,
    array  $variantPrices,
    int    $isChefSpecial,
    string $dietaryTag,
    int    $isVeg,
    string &$action = ''
): int|false {

    if ($categoryName === '' || $subcategoryName === '' || $productName === '') return false;

    // 1. Upsert parent category
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(TRIM(name))=LOWER(TRIM(?)) AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$categoryName]);
    $catId = $stmt->fetchColumn();
    if (!$catId) {
        $baseSlug = trim(preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($categoryName)), '-') ?: 'category';
        $slug = $baseSlug;
        $slugChk = $pdo->prepare("SELECT id FROM categories WHERE slug=? LIMIT 1");
        $slugChk->execute([$slug]);
        $n = 1;
        while ($slugChk->fetchColumn()) { $slug = $baseSlug . '-' . $n++; $slugChk->execute([$slug]); }
        $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, NULL)")->execute([$categoryName, $slug]);
        $catId = (int)$pdo->lastInsertId();
    } else { $catId = (int)$catId; }

    // 2. Upsert subcategory
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(TRIM(name))=LOWER(TRIM(?)) AND parent_id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$subcategoryName, $catId]);
    $subId = $stmt->fetchColumn();
    if (!$subId) {
        $baseSlug = trim(preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($subcategoryName)), '-') ?: 'subcategory';
        $slug = $baseSlug;
        $slugChk = $pdo->prepare("SELECT id FROM categories WHERE slug=? LIMIT 1");
        $slugChk->execute([$slug]);
        $n = 1;
        while ($slugChk->fetchColumn()) { $slug = $baseSlug . '-' . $n++; $slugChk->execute([$slug]); }
        $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)")->execute([$subcategoryName, $slug, $catId]);
        $subId = (int)$pdo->lastInsertId();
    } else { $subId = (int)$subId; }

    // 3. Upsert product
    $stmt = $pdo->prepare("SELECT id FROM products WHERE LOWER(name)=LOWER(?) AND subcategory_id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$productName, $subId]);
    $prodId = $stmt->fetchColumn();

    $basePrice = !empty($variantPrices) ? (float)array_values($variantPrices)[0] : 0.0;

    if ($prodId) {
        $action = 'update';
        $prodId = (int)$prodId;
        $pdo->prepare("UPDATE products SET collection_category_id=?,subcategory_id=?,is_chef_special=?,dietary_tag=?,is_veg=?,base_price=?,starting_price=?,availability_status='in_stock',updated_at=NOW() WHERE id=?")
            ->execute([$catId, $subId, $isChefSpecial, $dietaryTag, $isVeg, $basePrice, $basePrice, $prodId]);
    } else {
        $action   = 'insert';
        $baseSlug = trim(preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($productName)), '-') ?: 'product';
        $slug     = $baseSlug;
        $chk      = $pdo->prepare("SELECT id FROM products WHERE slug=? LIMIT 1");
        $chk->execute([$slug]);
        $n = 1;
        while ($chk->fetchColumn()) { $slug = $baseSlug . '-' . $n++; $chk->execute([$slug]); }

        $sku = 'SKU-' . strtoupper(substr(uniqid(), -8));
        $pdo->prepare("INSERT INTO products (name,slug,sku,subcategory_id,collection_category_id,base_price,starting_price,availability_status,is_chef_special,dietary_tag,is_veg,lead_time_hours,created_at) VALUES (?,?,?,?,?,?,?,'in_stock',?,?,?,24,NOW())")
            ->execute([$productName, $slug, $sku, $subId, $catId, $basePrice, $basePrice, $isChefSpecial, $dietaryTag, $isVeg]);
        $prodId = (int)$pdo->lastInsertId();
    }

    // 4. Upsert variants
    $varCheck = $pdo->prepare("SELECT id FROM product_variants WHERE product_id=? AND weight_or_size=? LIMIT 1");
    $defCheck = $pdo->prepare("SELECT id FROM product_variants WHERE product_id=? AND is_default=1 LIMIT 1");

    foreach ($variantPrices as $weightKey => $price) {
        $norm  = normalizeWeight((string)$weightKey);
        $price = round((float)$price, 2);
        $varCheck->execute([$prodId, $norm]);
        $vid = $varCheck->fetchColumn();
        if ($vid) {
            $pdo->prepare("UPDATE product_variants SET price=?,is_active=1,stock_quantity=10 WHERE id=?")->execute([$price, (int)$vid]);
        } else {
            $defCheck->execute([$prodId]);
            $isDef = $defCheck->fetchColumn() ? 0 : 1;
            $pdo->prepare("INSERT INTO product_variants (product_id,variant_label,weight_or_size,price,is_default,is_active,stock_quantity,created_at) VALUES (?,?,?,?,?,1,10,NOW())")
                ->execute([$prodId, $norm, $norm, $price, $isDef]);
        }
    }

    return $prodId;
}

// ── Read CSV ──────────────────────────────────────────────────────────────────
$fh = fopen($csvPath, 'r');
if (!$fh) { fwrite(STDERR, "ERROR: Cannot open $csvPath\n"); exit(1); }
fgetcsv($fh); // skip header

$inserted    = 0;
$updated     = 0;
$failed      = 0;
$upsertedIds = [];
$rowIdx      = 1;

fwrite(STDERR, "Processing CSV: $csvPath\n\n");

while (($row = fgetcsv($fh)) !== false) {
    $rowIdx++;
    if (empty(array_filter(array_map('strval', $row)))) continue;

    $categoryName    = trim((string)($row[0] ?? ''));
    $subcategoryName = trim((string)($row[1] ?? ''));
    $productName     = trim((string)($row[2] ?? ''));

    $variantPrices = [];
    foreach (WEIGHT_KEYS as $i => $wKey) {
        $raw = trim((string)($row[3 + $i] ?? ''));
        if ($raw !== '' && (float)$raw > 0) {
            $variantPrices[$wKey] = (float)$raw;
        }
    }

    $isChefSpecial = (trim((string)($row[14] ?? '')) === '1') ? 1 : 0;
    $rawDietary    = trim((string)($row[15] ?? ''));
    $dietaryTag    = DIETARY_MAP[$rawDietary] ?? 'regular';
    $isVeg         = (trim((string)($row[16] ?? '')) === '0') ? 0 : 1;

    $action    = '';
    $productId = processRow($pdo, $categoryName, $subcategoryName, $productName, $variantPrices, $isChefSpecial, $dietaryTag, $isVeg, $action);

    if ($productId !== false) {
        $upsertedIds[] = $productId;
        if ($action === 'insert') {
            $inserted++;
            echo "+ [$categoryName / $subcategoryName] $productName\n";
        } else {
            $updated++;
        }
    } else {
        $failed++;
        fwrite(STDERR, "SKIP row $rowIdx: $categoryName / $subcategoryName / $productName\n");
    }
}
fclose($fh);

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n========================================\n";
echo "IMPORT COMPLETE\n";
echo "  Inserted : $inserted\n";
echo "  Updated  : $updated\n";
echo "  Failed   : $failed\n";
echo "  Total    : " . ($inserted + $updated + $failed) . "\n";
echo "========================================\n";
