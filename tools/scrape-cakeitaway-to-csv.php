<?php
/**
 * scrape-cakeitaway-to-csv.php
 * Fetches the full product catalogue from cakeitaway.co.in via Shopify JSON API
 * and converts it to the Cakeouflage import CSV format.
 *
 * CSV columns (17 total):
 *   0  category_name
 *   1  subcategory_name
 *   2  product_name
 *   3  per_piece  price
 *   4  0.5lb  price
 *   5  1lb    price
 *   6  1.5lb  price
 *   7  2lb    price
 *   8  2.5lb  price
 *   9  3lb    price
 *   10 3.5lb  price
 *   11 4lb    price
 *   12 4.5lb  price
 *   13 5lb    price
 *   14 is_chef_special (0/1)
 *   15 dietary_tag (Eggless / Regular / ...)
 *   16 is_veg (0/1)
 *
 * Usage (inside Docker container):
 *   php /var/www/html/tools/scrape-cakeitaway-to-csv.php
 * Writes: /var/www/html/tools/cakeitaway-catalogue.csv
 */

// ---------------------------------------------------------------------------
// Category / Subcategory mapping  (product_type → [category, subcategory])
// ---------------------------------------------------------------------------
const TYPE_MAP = [
    // ── Cakes ────────────────────────────────────────────────────────────
    'floral birthday'          => ['Cakes', 'Floral Birthday'],
    'floral anniversary'       => ['Cakes', 'Floral Anniversary'],
    'non floral birthday'      => ['Cakes', 'Non Floral Birthday'],
    'classic chocolate cakes'  => ['Cakes', 'Classic Chocolate'],
    'baby shower cakes'        => ['Cakes', 'Baby Shower'],
    'classic tiered cakes'     => ['Cakes', 'Classic Tiered'],
    'themed birthday'          => ['Cakes', 'Themed Birthday'],
    'kids birthday'            => ['Cakes', 'Kids Birthday'],
    'deconstructed tiered'     => ['Cakes', 'Deconstructed Tiered'],
    'acrylic tier cakes'       => ['Cakes', 'Acrylic Tier'],
    'chandelier cakes'         => ['Cakes', 'Chandelier'],
    'mini cake platters'       => ['Cakes', 'Mini Cake Platters'],
    // ── Signature Cakes ──────────────────────────────────────────────────
    'tart cake'                => ['Signature Cakes', 'Tart Cake'],
    'opera'                    => ['Signature Cakes', 'Opera'],
    'operas'                   => ['Signature Cakes', 'Opera'],
    'chocolate cheesecakes'    => ['Signature Cakes', 'Chocolate Cheesecake'],
    'non chocolate cheesecakes'=> ['Signature Cakes', 'Non Chocolate Cheesecake'],
    'non chocolate tea cakes'          => ['Signature Cakes', 'Tea Cakes'],
    'chocolate tea cakes'              => ['Signature Cakes', 'Chocolate Tea Cakes'],
    'classic non chocolate cakes'      => ['Signature Cakes', 'Classic Non Chocolate'],
    'classic chocolate mousse cakes'   => ['Signature Cakes', 'Chocolate Mousse'],
    // ── Small Bakes ──────────────────────────────────────────────────────
    'cookies'                  => ['Small Bakes', 'Cookies'],
    'chocolates'               => ['Small Bakes', 'Chocolates'],
    // ── Gifting Hampers ──────────────────────────────────────────────────
    'hampers'                  => ['Gifting Hampers', 'Hampers'],
    // fallback handled below
];

// ---------------------------------------------------------------------------
// Weight-key normaliser
// Maps a Shopify variant option1 label → our WEIGHT_KEYS index (0-based, 0=per_piece)
// WEIGHT_KEYS = ['per_piece','0.5lb','1lb','1.5lb','2lb','2.5lb','3lb','3.5lb','4lb','4.5lb','5lb']
// ---------------------------------------------------------------------------
function variantToWeightIndex(string $label): ?int
{
    $raw = strtolower(trim($label));
    $raw = str_replace(' ', '', $raw); // "1 lb" → "1lb"

    // Map weight strings to column index
    $map = [
        'defaulttitle' => 0,  // per_piece
        'default'      => 0,
        '0.5lb'        => 1,
        '1lb'          => 2,
        '1lbs'         => 2,
        '1.5lb'        => 3,
        '1.5lbs'       => 3,
        '2lb'          => 4,
        '2lbs'         => 4,
        '2.5lb'        => 5,
        '2.5lbs'       => 5,
        '3lb'          => 6,
        '3lbs'         => 6,
        '3.5lb'        => 7,
        '3.5lbs'       => 7,
        '4lb'          => 8,
        '4lbs'         => 8,
        '4.5lb'        => 9,
        '4.5lbs'       => 9,
        '5lb'          => 10,
        '5lbs'         => 10,
    ];

    if (isset($map[$raw])) {
        return $map[$raw];
    }

    // Strip everything after "/" for compound variants like "1 lb / Regular Topper"
    if (str_contains($raw, '/')) {
        $part = explode('/', $raw)[0];
        $part = str_replace(' ', '', $part);
        if (isset($map[$part])) {
            return $map[$part];
        }
    }

    // Any other non-weight variant (pieces, quantity etc.) → per_piece slot if first
    // returning null means: skip (don't map to any standard column)
    return null;
}

// ---------------------------------------------------------------------------
// Fetch products JSON from Shopify (all pages)
// ---------------------------------------------------------------------------
$allProducts = [];
$page = 1;
$baseUrl = 'https://cakeitaway.co.in/products.json';

do {
    $url = $baseUrl . '?limit=250&page=' . $page;
    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 30,
            'user_agent'    => 'Mozilla/5.0 (compatible; CakeouflageBot/1.0)',
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $raw = file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        fwrite(STDERR, "ERROR: Could not fetch page $page from $url\n");
        break;
    }
    $json = json_decode($raw, true);
    if (!isset($json['products'])) {
        fwrite(STDERR, "ERROR: Unexpected JSON structure on page $page\n");
        break;
    }
    $batch = $json['products'];
    $allProducts = array_merge($allProducts, $batch);
    fwrite(STDERR, "Fetched page $page — " . count($batch) . " products (total " . count($allProducts) . ")\n");
    $page++;
} while (count($batch) === 250); // if fewer than 250, we've reached the last page

fwrite(STDERR, "Total products fetched: " . count($allProducts) . "\n");

// ---------------------------------------------------------------------------
// Build CSV rows
// ---------------------------------------------------------------------------
$csvRows = [];
$skipped = [];

foreach ($allProducts as $product) {
    $title       = trim($product['title'] ?? '');
    $productType = strtolower(trim($product['product_type'] ?? ''));
    $variants    = $product['variants'] ?? [];

    // Skip the "Build Your Own" / customise placeholder (price=0)
    if (empty($title) || $title === '') continue;
    $allZero = array_filter($variants, fn($v) => (float)($v['price'] ?? 0) > 0);
    if (empty($allZero)) {
        $skipped[] = "$title (all variants ₹0)";
        continue;
    }

    // Resolve category / subcategory
    [$category, $subcategory] = TYPE_MAP[$productType] ?? ['Other', ucwords($productType) ?: 'General'];

    // Initialise 11 price columns (indices 0–10 matching WEIGHT_KEYS)
    $prices = array_fill(0, 11, '');

    $hasMappedVariant = false;
    $firstNonZeroPrice = null;

    foreach ($variants as $variant) {
        $priceVal = round((float)($variant['price'] ?? 0), 2);
        if ($priceVal <= 0) continue;

        $option1 = trim($variant['option1'] ?? '');
        $idx = variantToWeightIndex($option1);

        if ($idx !== null) {
            // Standard weight key — only store lowest price if collision
            if ($prices[$idx] === '' || $priceVal < (float)$prices[$idx]) {
                $prices[$idx] = $priceVal;
            }
            $hasMappedVariant = true;
        } else {
            // Non-standard (pieces, quantity, etc.) → per_piece if slot empty
            if ($firstNonZeroPrice === null) {
                $firstNonZeroPrice = $priceVal;
            }
        }
    }

    // If no standard weight matched at all, use lowest price as per_piece
    if (!$hasMappedVariant && $firstNonZeroPrice !== null) {
        $prices[0] = $firstNonZeroPrice;
    } elseif ($prices[0] === '' && $firstNonZeroPrice !== null) {
        // Keep per_piece slot if already set; otherwise fill with non-std price
    }

    // Skip if literally no prices resolved
    if (array_filter($prices, fn($p) => $p !== '' && $p > 0) === []) {
        $skipped[] = "$title (no usable prices)";
        continue;
    }

    // Build row: [category, subcategory, name, p0..p10, chef_special, dietary, veg]
    $row = [$category, $subcategory, $title];
    foreach ($prices as $p) {
        $row[] = $p === '' ? '' : number_format((float)$p, 2, '.', '');
    }
    $row[] = 0;        // is_chef_special
    $row[] = 'Eggless'; // dietary_tag — cakeitaway is an eggless bakery
    $row[] = 1;        // is_veg

    $csvRows[] = $row;
}

// ---------------------------------------------------------------------------
// Write CSV
// ---------------------------------------------------------------------------
$outputFile = __DIR__ . '/cakeitaway-catalogue.csv';
$fh = fopen($outputFile, 'w');

// Header row
fputcsv($fh, [
    'category_name', 'subcategory_name', 'product_name',
    'per_piece', '0.5lb', '1lb', '1.5lb', '2lb', '2.5lb', '3lb', '3.5lb', '4lb', '4.5lb', '5lb',
    'is_chef_special', 'dietary_tag', 'is_veg',
]);

foreach ($csvRows as $row) {
    fputcsv($fh, $row);
}
fclose($fh);

fwrite(STDERR, "\n--- RESULT ---\n");
fwrite(STDERR, "Products written : " . count($csvRows) . "\n");
fwrite(STDERR, "Products skipped : " . count($skipped) . "\n");
if ($skipped) {
    foreach ($skipped as $s) {
        fwrite(STDERR, "  SKIP: $s\n");
    }
}
fwrite(STDERR, "Output CSV       : $outputFile\n");
echo "OK: " . count($csvRows) . " products written to $outputFile\n";
