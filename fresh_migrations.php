<?php
/**
 * Fresh migration setup - drops tables in correct order
 */

require 'app/bootstrap.php';
use App\Core\Database;

$pdo = Database::getConnection();

echo "=== Fresh Migration Setup ===\n\n";

// Drop in reverse dependency order
$dropOrder = [
    'product_variant_snapshots',
    'product_snapshots', 
    'product_import_runs'
];

echo "Dropping tables (in order):\n";
foreach ($dropOrder as $table) {
    try {
        $pdo->exec("DROP TABLE IF EXISTS $table");
        echo "✓ $table\n";
    } catch (Exception $e) {
        echo "✗ $table: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Running Migrations ===\n\n";

$migrations = [
    '001_create_product_import_runs_table.sql',
    '002_create_product_snapshots_table.sql',
    '003_create_product_variant_snapshots_table.sql',
    '004_add_soft_delete_to_products.sql'
];

$successCount = 0;
foreach ($migrations as $migration) {
    $filePath = 'database/migrations/' . $migration;
    
    if (!file_exists($filePath)) {
        echo "⚠ $migration - File not found\n";
        continue;
    }
    
    echo "Executing: $migration ... ";
    $sql = file_get_contents($filePath);
    
    try {
        $pdo->exec($sql);
        echo "✓\n";
        $successCount++;
    } catch (Exception $e) {
        echo "✗\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Verification ===\n\n";

// List created tables
$result = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);

$versioningTables = array_filter($result, fn($t) => 
    strpos($t, 'import') !== false || 
    strpos($t, 'snapshot') !== false
);

echo "Versioning tables:\n";
foreach ($versioningTables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "  ✓ $table ($count rows)\n";
}

// Check products table
try {
    $result = $pdo->query("DESCRIBE products")->fetchAll(PDO::FETCH_ASSOC);
    $hasDeletedAt = false;
    foreach ($result as $col) {
        if ($col['Field'] === 'deleted_at') {
            $hasDeletedAt = true;
            break;
        }
    }
    if ($hasDeletedAt) {
        echo "  ✓ products table has deleted_at column\n";
    } else {
        echo "  ✗ products table missing deleted_at column\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error checking products table: " . $e->getMessage() . "\n";
}

echo "\n✓ Setup complete: $successCount migrations executed successfully!\n";
