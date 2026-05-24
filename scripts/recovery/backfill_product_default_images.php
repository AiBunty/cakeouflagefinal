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

function isDefaultImagePath(string $rawPath): bool
{
    $resolved = ProductImageService::resolve($rawPath, null);
    return $resolved === ProductImageService::DEFAULT_IMAGE_WEBP || $resolved === ProductImageService::DEFAULT_IMAGE_PNG;
}

function isLegacyDefaultPath(string $rawPath): bool
{
    $path = trim($rawPath);
    return $path === '/assets/defaults/default-product-image.webp' || $path === '/assets/defaults/default-product-image.png';
}

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$products = $pdo->query(
    'SELECT id, featured_image FROM products WHERE deleted_at IS NULL ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$defaultPath = ProductImageService::DEFAULT_IMAGE_WEBP;
$projectRoot = dirname(__DIR__, 2);
$startedAt = date('c');

$updated = 0;
$checked = 0;
$featuredFixed = 0;
$galleryProductsRewritten = 0;
$galleryRowsRemoved = 0;
$galleryRowsAdded = 0;
$skippedValid = 0;
$failures = [];

$updateFeaturedStmt = $pdo->prepare('UPDATE products SET featured_image = :featured_image, updated_at = NOW() WHERE id = :id LIMIT 1');
$galleryFetchStmt = $pdo->prepare('SELECT id, image_url FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC');
$galleryDeleteStmt = $pdo->prepare('DELETE FROM product_images WHERE product_id = :product_id');
$galleryInsertStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (:product_id, :image_url, :sort_order)');

foreach ($products as $row) {
    $checked++;
    $id = (int)($row['id'] ?? 0);
    $current = trim((string)($row['featured_image'] ?? ''));

    if ($id <= 0) {
        continue;
    }

    $targetFeatured = $current;
    if (isLegacyDefaultPath($targetFeatured)) {
        $targetFeatured = $defaultPath;
    }
    if (!isUsableImagePath($current, $projectRoot)) {
        $targetFeatured = $defaultPath;
    }

    try {
        $galleryFetchStmt->execute([':product_id' => $id]);
        $galleryRows = $galleryFetchStmt->fetchAll(PDO::FETCH_ASSOC);

        $targetImage2 = null;
        $targetFeaturedResolved = ProductImageService::resolve($targetFeatured, null);

        foreach ($galleryRows as $galleryRow) {
            $imageUrl = trim((string)($galleryRow['image_url'] ?? ''));
            if ($imageUrl === '') {
                continue;
            }
            if (isLegacyDefaultPath($imageUrl)) {
                $imageUrl = $defaultPath;
            }
            if (!isUsableImagePath($imageUrl, $projectRoot)) {
                continue;
            }

            $resolved = ProductImageService::resolve($imageUrl, null);
            if ($resolved === $targetFeaturedResolved) {
                continue;
            }
            if (isDefaultImagePath($imageUrl)) {
                continue;
            }

            if ($targetImage2 === null) {
                $targetImage2 = $imageUrl;
            }
        }

        $desiredGallery = [$targetFeatured];
        if ($targetImage2 !== null) {
            $desiredGallery[] = $targetImage2;
        }

        $existingGallery = [];
        foreach ($galleryRows as $galleryRow) {
            $existingGallery[] = trim((string)($galleryRow['image_url'] ?? ''));
        }

        $needsFeaturedUpdate = $targetFeatured !== $current;
        $needsGalleryRewrite = count($existingGallery) !== count($desiredGallery);
        if (!$needsGalleryRewrite) {
            foreach ($desiredGallery as $index => $desiredPath) {
                if (($existingGallery[$index] ?? '') !== $desiredPath) {
                    $needsGalleryRewrite = true;
                    break;
                }
            }
        }

        if (!$needsFeaturedUpdate && !$needsGalleryRewrite) {
            $skippedValid++;
            continue;
        }

        $pdo->beginTransaction();

        if ($needsFeaturedUpdate) {
            $updateFeaturedStmt->execute([
                ':featured_image' => $targetFeatured,
                ':id' => $id,
            ]);
            $featuredFixed++;
        }

        if ($needsGalleryRewrite) {
            $galleryDeleteStmt->execute([':product_id' => $id]);
            foreach ($desiredGallery as $sortOrder => $imagePath) {
                $galleryInsertStmt->execute([
                    ':product_id' => $id,
                    ':image_url' => $imagePath,
                    ':sort_order' => $sortOrder,
                ]);
            }
            $galleryProductsRewritten++;
            $galleryRowsRemoved += max(0, count($existingGallery) - count($desiredGallery));
            $galleryRowsAdded += max(0, count($desiredGallery) - count($existingGallery));
        }

        $pdo->commit();
        $updated++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $failures[] = [
            'product_id' => $id,
            'error' => $e->getMessage(),
        ];
    }
}

out('Product image default backfill complete.');
out('Checked: ' . $checked);
out('Updated products: ' . $updated);
out('Featured fixed: ' . $featuredFixed);
out('Gallery rewritten products: ' . $galleryProductsRewritten);
out('Gallery rows removed: ' . $galleryRowsRemoved);
out('Gallery rows added: ' . $galleryRowsAdded);
out('Skipped valid: ' . $skippedValid);
out('Failures: ' . count($failures));

$reportDir = dirname(__DIR__, 2) . '/storage/recovery';
if (!is_dir($reportDir)) {
    @mkdir($reportDir, 0775, true);
}

$reportPath = $reportDir . '/default-image-backfill-' . date('Ymd_His') . '.json';
$report = [
    'started_at' => $startedAt,
    'completed_at' => date('c'),
    'checked_products' => $checked,
    'updated_products' => $updated,
    'featured_fixed' => $featuredFixed,
    'gallery_rewritten_products' => $galleryProductsRewritten,
    'gallery_rows_removed' => $galleryRowsRemoved,
    'gallery_rows_added' => $galleryRowsAdded,
    'skipped_valid' => $skippedValid,
    'failures' => $failures,
    'default_image_path' => $defaultPath,
];

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

out('Report: ' . $reportPath);
out('Product image default backfill complete.');
