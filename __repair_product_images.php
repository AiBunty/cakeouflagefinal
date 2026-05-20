<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\ProductImageService;

$providedKey = (string)($_GET['k'] ?? $_POST['k'] ?? '');
$repairKey = (string)Env::get('IMAGE_REPAIR_KEY', 'CAKEO-IMG-REPAIR-20260402');
$dryRun = ((string)($_GET['dry_run'] ?? '0')) === '1';

if ($providedKey !== $repairKey) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'forbidden'], JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->query(
        'SELECT p.id, p.name, p.slug, p.featured_image, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.collection_category_id
         WHERE p.deleted_at IS NULL'
    );
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedFeatured = 0;
    $insertedGallery = 0;
    $scanned = 0;

    if (!$dryRun) {
        $pdo->beginTransaction();
    }

    $updateFeaturedStmt = $pdo->prepare('UPDATE products SET featured_image = :featured_image WHERE id = :id');
    $galleryCountStmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = :product_id AND image_url IS NOT NULL AND TRIM(image_url) <> ""');
    $insertGalleryStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_url, alt_text, sort_order) VALUES (:product_id, :image_url, :alt_text, 0)');

    foreach ($products as $product) {
        $scanned++;
        $productId = (int)$product['id'];
        $categorySlug = (string)($product['category_slug'] ?? '');
        $currentFeatured = trim((string)($product['featured_image'] ?? ''));

        if ($currentFeatured === '') {
            $newFeatured = ProductImageService::placeholderForCategory($categorySlug);
            if (!$dryRun) {
                $updateFeaturedStmt->execute([
                    'featured_image' => $newFeatured,
                    'id' => $productId,
                ]);
            }
            $currentFeatured = $newFeatured;
            $updatedFeatured++;
        }

        $galleryCountStmt->execute(['product_id' => $productId]);
        $galleryCount = (int)$galleryCountStmt->fetchColumn();

        if ($galleryCount === 0) {
            $imageForGallery = ProductImageService::resolve($currentFeatured, $categorySlug);
            if (!$dryRun) {
                $insertGalleryStmt->execute([
                    'product_id' => $productId,
                    'image_url' => $imageForGallery,
                    'alt_text' => (string)$product['name'],
                ]);
            }
            $insertedGallery++;
        }
    }

    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->commit();
    }

    echo json_encode([
        'success' => true,
        'message' => $dryRun ? 'dry-run completed' : 'image repair completed',
        'data' => [
            'dry_run' => $dryRun,
            'scanned_products' => $scanned,
            'updated_featured_image' => $updatedFeatured,
            'inserted_gallery_rows' => $insertedGallery,
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[image-repair] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'image repair failed',
        'details' => APP_DEBUG ? $e->getMessage() : null,
    ], JSON_UNESCAPED_SLASHES);
}
