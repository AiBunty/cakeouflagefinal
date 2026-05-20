<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$expectedToken = 'cakeo_migrate_20260515';
$providedToken = isset($_GET['token']) ? (string)$_GET['token'] : '';
if ($providedToken !== $expectedToken) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

@set_time_limit(300);

$envPath = __DIR__ . '/.env';
if (!is_file($envPath)) {
    http_response_code(500);
    echo "Missing .env in web root\n";
    exit;
}

$env = [];
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $parts = explode('=', $line, 2);
    if (count($parts) !== 2) {
        continue;
    }
    $env[trim($parts[0])] = trim($parts[1]);
}

$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$user = $env['DB_USER'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
$dbName = $env['DB_NAME'] ?? '';

if ($host === '' || $user === '' || $dbName === '') {
    http_response_code(500);
    echo "DB settings missing in .env\n";
    exit;
}

$sqlPath = __DIR__ . '/cakeouflage.sql';
if (!is_file($sqlPath)) {
    http_response_code(500);
    echo "Missing SQL dump at /cakeouflage.sql\n";
    exit;
}

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Connected to database: {$dbName}\n";

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);

    foreach ($tables as $row) {
        $table = (string)$row[0];
        $safe = str_replace('`', '``', $table);
        $pdo->exec("DROP TABLE IF EXISTS `{$safe}`");
    }

    echo 'Dropped tables: ' . count($tables) . "\n";

    $sql = file_get_contents($sqlPath);
    if ($sql === false) {
        throw new RuntimeException('Failed to read SQL file');
    }

    $statement = '';
    $executed = 0;
    $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') {
            continue;
        }

        $statement .= $line . "\n";

        if (preg_match('/;\s*$/', $trimmed) !== 1) {
            continue;
        }

        $pdo->exec($statement);
        $executed++;
        $statement = '';
    }

    if (trim($statement) !== '') {
        $pdo->exec($statement);
        $executed++;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    $newTableCount = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();

    echo "Executed statements: {$executed}\n";
    echo "Table count after import: {$newTableCount}\n";
    echo "MIGRATION_OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'MIGRATION_FAILED: ' . $e->getMessage() . "\n";
    exit;
}
