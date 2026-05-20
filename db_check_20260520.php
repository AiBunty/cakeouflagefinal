<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$envFile = __DIR__ . '/.env';
$vars = [];
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $vars[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
}

$host = $vars['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$port = $vars['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbName = $vars['DB_NAME'] ?? $vars['DB_DATABASE'] ?? (getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'cakeouflage'));
$user = $vars['DB_USER'] ?? $vars['DB_USERNAME'] ?? (getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'root'));
$password = $vars['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
$charset = $vars['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );

    $row = $pdo->query('SELECT DATABASE() AS db_name, VERSION() AS mysql_version')->fetch();
    echo json_encode([
        'ok' => true,
        'php_version' => PHP_VERSION,
        'database' => $row['db_name'] ?? $dbName,
        'mysql_version' => $row['mysql_version'] ?? null,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'php_version' => PHP_VERSION,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
}