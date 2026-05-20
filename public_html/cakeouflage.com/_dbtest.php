<?php
require "app/bootstrap.php";
use App\Core\Database;
use App\Core\Env;

echo "Host: " . Env::get("DB_HOST") . "\n";
echo "DB:   " . Env::get("DB_NAME") . "\n";
echo "User: " . Env::get("DB_USER") . "\n";

try {
    $pdo = Database::getConnection();
    $v = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "CONNECTED - MySQL: " . $v . "\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . count($tables) . "\n";
    if (count($tables) > 0) {
        echo implode(", ", array_slice($tables, 0, 10)) . "\n";
    }

} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
