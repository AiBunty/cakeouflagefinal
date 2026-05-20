<?php
/**
 * Clean up existing migration tables and re-run migrations
 */

require 'app/bootstrap.php';
use App\Core\Database;

$pdo = Database::getConnection();

echo "=== Cleaning up old tables ===\n";
$pdo->exec("DROP TABLE IF EXISTS product_variant_snapshots");
echo "✓ Dropped product_variant_snapshots\n";

$pdo->exec("DROP TABLE IF EXISTS product_snapshots");
echo "✓ Dropped product_snapshots\n";

$pdo->exec("DROP TABLE IF EXISTS product_import_runs");
echo "✓ Dropped product_import_runs\n";

echo "\n=== Running migrations ===\n";

$migrations = [
    '001_create_product_import_runs_table.sql',
    '002_create_product_snapshots_table.sql',
    '003_create_product_variant_snapshots_table.sql',
    '004_add_soft_delete_to_products.sql'
];

foreach ($migrations as $migration) {
    $filePath = 'database/migrations/' . $migration;
    
    if (!file_exists($filePath)) {
        echo "⚠ SKIPPED: $migration (file not found)\n";
        continue;
    }
    
    echo "Executing: $migration ... ";
    $sql = file_get_contents($filePath);
    
    try {
        $pdo->exec($sql);
        echo "✓ Done\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Verification ===\n";

// Check if tables exist
$tables = $pdo->query('SHOW TABLES LIKE "%import%" OR LIKE "%snapshot%"')->fetchAll(PDO::FETCH_COLUMN);
echo "Versioning tables created: " . count($tables) . "\n";
foreach ($tables as $table) {
    echo "  - $table\n";
}

// Check if products has deleted_at
$cols = $pdo->query('DESCRIBE products')->fetchAll(PDO::FETCH_ASSOC);
$hasDeletedAt = false;
foreach ($cols as $col) {
    if ($col['Field'] === 'deleted_at') {
        $hasDeletedAt = true;
        echo "\n✓ Products table has deleted_at column\n";
        break;
    }
}

if (!$hasDeletedAt) {
    echo "\n✗ Products table missing deleted_at column\n";
}

echo "\n✓ Migration setup complete!\n";
