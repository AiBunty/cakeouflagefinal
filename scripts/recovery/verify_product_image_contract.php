<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;
use App\Services\ProductImageService;

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function isExternalImagePath(string $path): bool
{
    return (bool)preg_match('#^https?://#i', $path);
}

function webPathReadable(string $webPath, string $projectRoot): bool
{
    $normalized = '/' . ltrim($webPath, '/');
    $candidatePaths = [
        $projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $normalized),
    ];

    if (str_starts_with($normalized, '/assets/')) {
        $candidatePaths[] = $projectRoot . DIRECTORY_SEPARATOR . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    foreach ($candidatePaths as $fsPath) {
        if (is_file($fsPath) && is_readable($fsPath)) {
            return true;
        }
    }

    return false;
}

function isUsableImagePath(string $rawPath, string $projectRoot): bool
{
    $path = trim($rawPath);
    if ($path === '') {
        return false;
    }
    if (isExternalImagePath($path)) {
        return true;
    }

    $resolved = ProductImageService::resolve($path, null);
    if ($resolved === '') {
        return false;
    }

    return webPathReadable($resolved, $projectRoot);
}

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$projectRoot = dirname(__DIR__, 2);
$products = $pdo->query('SELECT id, featured_image FROM products WHERE deleted_at IS NULL ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

$missingFeatured = 0;
$brokenFeatured = 0;
$overTwoSlots = 0;
$brokenGalleryRows = 0;
$withoutSlot0 = 0;

$galleryStmt = $pdo->prepare('SELECT image_url, sort_order FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC');

foreach ($products as $product) {
    $productId = (int)($product['id'] ?? 0);
    $featuredImage = trim((string)($product['featured_image'] ?? ''));

    if ($featuredImage === '') {
        $missingFeatured++;
    } elseif (!isUsableImagePath($featuredImage, $projectRoot)) {
        $brokenFeatured++;
    }

    $galleryStmt->execute([':product_id' => $productId]);
    $rows = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 2) {
        $overTwoSlots++;
    }

    $hasSlot0 = false;
    foreach ($rows as $row) {
        $sortOrder = (int)($row['sort_order'] ?? -1);
        $path = trim((string)($row['image_url'] ?? ''));
        if ($sortOrder === 0) {
            $hasSlot0 = true;
        }
        if (!isUsableImagePath($path, $projectRoot)) {
            $brokenGalleryRows++;
        }
    }

    if (!$hasSlot0) {
        $withoutSlot0++;
    }
}

$ok = $missingFeatured === 0
    && $brokenFeatured === 0
    && $overTwoSlots === 0
    && $brokenGalleryRows === 0
    && $withoutSlot0 === 0;

$report = [
    'run_at' => date('c'),
    'total_products' => count($products),
    'missing_featured' => $missingFeatured,
    'broken_featured' => $brokenFeatured,
    'products_over_two_slots' => $overTwoSlots,
    'broken_gallery_rows' => $brokenGalleryRows,
    'products_without_slot0' => $withoutSlot0,
    'ok' => $ok,
];

$outDir = __DIR__ . '/../../storage/recovery';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$reportPath = $outDir . '/verify-product-image-contract-' . date('Ymd_His') . '.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

out('Product image contract verification complete.');
out('Report: ' . $reportPath);
out('Result: ' . ($ok ? 'PASS' : 'FAIL'));

exit($ok ? 0 : 1);
