<?php
/**
 * Run migration: backfill NULL/empty featured_image to the branded default.
 *
 * USAGE:
 *   CLI:     docker exec cakeouflage-web php /var/www/html/__run_image_migration.php
 *   Browser: Visit __run_image_migration.php in your browser while logged in locally.
 *
 * SAFE TO RE-RUN — updates only rows where featured_image IS NULL or ''.
 * Delete this file after confirming the migration ran successfully.
 */

// Restrict browser access to local/docker only
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1', '172.17.0.1'], true)) {
    http_response_code(403);
    exit('Access denied — run via CLI only.');
}

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Env.php';

use App\Core\Database;
use App\Core\Env;

$isWeb = PHP_SAPI !== 'cli';
if ($isWeb) {
    header('Content-Type: text/plain; charset=UTF-8');
}

$defaultImage = '/public/assets/defaults/default-product-image.webp';

echo "=== Cakeouflage: Default Product Image Migration ===\n\n";

try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    echo "ERROR: Cannot connect to database — " . $e->getMessage() . "\n";
    exit(1);
}

// Count rows that need updating
$countStmt = $pdo->query(
    "SELECT COUNT(*) FROM products WHERE (featured_image IS NULL OR featured_image = '') AND deleted_at IS NULL"
);
$toFix = (int)($countStmt ? $countStmt->fetchColumn() : 0);
echo "Products with NULL/empty featured_image (active): {$toFix}\n\n";

if ($toFix === 0) {
    echo "Nothing to do — all active products already have a featured_image.\n";
    exit(0);
}

// Run migration
$stmt = $pdo->prepare(
    "UPDATE products SET featured_image = :img WHERE (featured_image IS NULL OR featured_image = '') AND deleted_at IS NULL"
);
$stmt->execute(['img' => $defaultImage]);
$affected = $stmt->rowCount();

echo "Updated {$affected} product(s) → featured_image = '{$defaultImage}'\n\n";

// Verify
$verifyStmt = $pdo->query(
    "SELECT COUNT(*) FROM products WHERE (featured_image IS NULL OR featured_image = '') AND deleted_at IS NULL"
);
$remaining = (int)($verifyStmt ? $verifyStmt->fetchColumn() : -1);
echo "Remaining NULL/empty rows: {$remaining}\n";

if ($remaining === 0) {
    echo "\n✅ Migration complete.\n";
} else {
    echo "\n⚠  {$remaining} rows still need attention — check deleted_at or other conditions.\n";
}
