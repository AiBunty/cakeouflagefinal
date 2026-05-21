<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Env.php';

use App\Core\Env;

Env::load(__DIR__ . '/../.env');

$host = (string)(Env::get('LIVE_DB_HOST', '') ?? '');
$port = (string)(Env::get('LIVE_DB_PORT', '3306') ?? '3306');
$db = (string)(Env::get('LIVE_DB_NAME', Env::get('LIVE_DB_DATABASE', '')) ?? '');
$user = (string)(Env::get('LIVE_DB_USER', Env::get('LIVE_DB_USERNAME', '')) ?? '');
$pass = (string)(Env::get('LIVE_DB_PASSWORD', '') ?? '');

if ($host === '' || $db === '' || $user === '') {
    fwrite(STDERR, "Missing LIVE_DB connection values in .env\n");
    exit(2);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );

    $row = $pdo->query('SELECT @@version AS version, @@version_comment AS version_comment, @@version_compile_os AS version_os')->fetch(PDO::FETCH_ASSOC);
    echo json_encode([
        'connected' => true,
        'version' => $row['version'] ?? null,
        'version_comment' => $row['version_comment'] ?? null,
        'version_os' => $row['version_os'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'connected' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
