<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function getJson(string $url): array
{
    $raw = @file_get_contents($url);
    if ($raw === false) {
        throw new RuntimeException('Failed to fetch URL: ' . $url);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON from URL: ' . $url);
    }
    return $data;
}

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$uploadedProduct = $pdo->query(
    "SELECT id, slug, featured_image
     FROM products
     WHERE deleted_at IS NULL
       AND featured_image LIKE '/public/uploads/originals/products/%'
     ORDER BY id ASC
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!is_array($uploadedProduct)) {
    throw new RuntimeException('No uploaded-image product found for smoke test.');
}

$defaultFixture = $pdo->query(
    "SELECT id, slug, featured_image
     FROM products
     WHERE deleted_at IS NULL
       AND id <> " . (int)$uploadedProduct['id'] . "
     ORDER BY id ASC
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!is_array($defaultFixture)) {
    throw new RuntimeException('No product available for default fixture smoke test.');
}

$baseUrl = 'http://localhost/api/catalog';
$listing = getJson($baseUrl . '/products?limit=30');
$items = $listing['data']['items'] ?? [];
if (!is_array($items)) {
    throw new RuntimeException('Listing payload missing items.');
}

$uploadedSlug = (string)$uploadedProduct['slug'];
$uploadedItem = null;
foreach ($items as $item) {
    if ((string)($item['slug'] ?? '') === $uploadedSlug) {
        $uploadedItem = $item;
        break;
    }
}

if (!is_array($uploadedItem)) {
    throw new RuntimeException('Uploaded fixture slug not found in listing payload: ' . $uploadedSlug);
}

$uploadedImageResolved = (string)($uploadedItem['image'] ?? '');
$uploadedImageRaw = (string)($uploadedItem['featured_image'] ?? '');
$uploadedOk = strpos($uploadedImageResolved, '/public/uploads/originals/products/') === 0;

$defaultId = (int)$defaultFixture['id'];
$defaultSlug = (string)$defaultFixture['slug'];
$defaultOriginal = (string)$defaultFixture['featured_image'];
$defaultSeedPath = '/public/assets/defaults/default-product-image.webp';

$defaultPathObserved = '';
$defaultOk = false;

try {
    $upd = $pdo->prepare('UPDATE products SET featured_image = :path, updated_at = NOW() WHERE id = :id LIMIT 1');
    $upd->execute([':path' => $defaultSeedPath, ':id' => $defaultId]);

    $detail = getJson($baseUrl . '/products/' . rawurlencode($defaultSlug));
    $detailProduct = $detail['data']['product'] ?? [];
    if (!is_array($detailProduct)) {
        throw new RuntimeException('Detail payload missing product for slug: ' . $defaultSlug);
    }

    $defaultPathObserved = (string)($detailProduct['featured_image'] ?? '');
    $defaultOk = ($defaultPathObserved === $defaultSeedPath);
} finally {
    $restore = $pdo->prepare('UPDATE products SET featured_image = :path, updated_at = NOW() WHERE id = :id LIMIT 1');
    $restore->execute([':path' => $defaultOriginal, ':id' => $defaultId]);
}

$report = [
    'run_at' => date('c'),
    'uploaded_fixture' => [
        'id' => (int)$uploadedProduct['id'],
        'slug' => $uploadedSlug,
        'db_featured_image' => (string)$uploadedProduct['featured_image'],
        'api_featured_image' => $uploadedImageRaw,
        'api_image' => $uploadedImageResolved,
        'ok' => $uploadedOk,
    ],
    'default_fixture' => [
        'id' => $defaultId,
        'slug' => $defaultSlug,
        'seeded_default_path' => $defaultSeedPath,
        'api_featured_image' => $defaultPathObserved,
        'ok' => $defaultOk,
    ],
    'overall_ok' => ($uploadedOk && $defaultOk),
];

$outDir = __DIR__ . '/../../storage/recovery';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$reportPath = $outDir . '/frontend-image-api-smoke-' . date('Ymd_His') . '.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

out('Frontend image API smoke complete.');
out('Report: ' . $reportPath);
out('Uploaded fixture ok: ' . ($uploadedOk ? '1' : '0'));
out('Default fixture ok: ' . ($defaultOk ? '1' : '0'));
out('Overall: ' . (($uploadedOk && $defaultOk) ? 'PASS' : 'FAIL'));

exit(($uploadedOk && $defaultOk) ? 0 : 1);
