<?php
/**
 * Database Migration Runner
 * Executes all SQL migration files in order
 */

require 'app/bootstrap.php';
use App\Core\Database;

echo "=== Database Migration Runner ===\n\n";

$pdo = Database::getConnection();
$migrations = [
    '001_create_product_import_runs_table.sql',
    '002_create_product_snapshots_table.sql',
    '003_create_product_variant_snapshots_table.sql',
    '004_add_soft_delete_to_products.sql'
];

$executed = 0;
$failed = 0;

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
        $executed++;
    } catch (Exception $e) {
        echo "✗ Error\n";
        echo "   Message: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=== Results ===\n";
echo "Executed: $executed migrations\n";
echo "Failed: $failed migrations\n";

if ($failed === 0) {
    echo "\n✓ All migrations completed successfully!\n";
    
    echo "\n=== New/Modified Tables ===\n";
    $result = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $versioningTables = array_filter($result, fn($t) => 
        strpos($t, 'import') !== false || 
        strpos($t, 'snapshot') !== false || 
        $t === 'products'
    );
    
    foreach ($versioningTables as $table) {
        echo "- $table\n";
    }
    
    echo "\n=== Product Import Runs Table Structure ===\n";
    $cols = $pdo->query('DESCRIBE product_import_runs')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "- {$col['Field']}: {$col['Type']} {$col['Null']}\n";
    }
}
