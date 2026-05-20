<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function cakeo_env_probe(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    $envFile = __DIR__ . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) === 2 && trim($parts[0]) === $key) {
                    return trim($parts[1]);
                }
            }
        }
    }

    return $default;
}

$host = cakeo_env_probe('DB_HOST', 'localhost');
$port = cakeo_env_probe('DB_PORT', '3306');
$dbName = cakeo_env_probe('DB_NAME', cakeo_env_probe('DB_DATABASE', 'cakeouflage'));
$user = cakeo_env_probe('DB_USER', cakeo_env_probe('DB_USERNAME', 'root'));
$password = cakeo_env_probe('DB_PASSWORD', '');
$charset = cakeo_env_probe('DB_CHARSET', 'utf8mb4');

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);

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