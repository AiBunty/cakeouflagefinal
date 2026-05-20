<?php
if (($_GET['token'] ?? '') !== 'byocmig20260518') {
    die('Unauthorized');
}

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    die('Missing .env');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$config = [];
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($name, $value) = explode('=', $line, 2);
    $config[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
}

$dsn = sprintf("mysql:host=%s;dbname=%s", $config['DB_HOST'], $config['DB_DATABASE']);
try {
    $pdo = new PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

$sqlFile = __DIR__ . '/2026-05-18-byoc-quote-acceptance.sql';
if (!file_exists($sqlFile)) {
    die('Missing migration file');
}

$sql = file_get_contents($sqlFile);
$count = 0;

$statement = '';
$lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];

foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
        continue;
    }

    $statement .= $line . "\n";
    if (preg_match('/;\s*$/', $trimmed) !== 1) {
        continue;
    }

    try {
        $pdo->exec($statement);
        $count++;
        $statement = '';
    } catch (Exception $e) {
        die("Error executing: " . substr(trim($statement), 0, 120) . "... Error: " . $e->getMessage());
    }
}

if (trim($statement) !== '') {
    try {
        $pdo->exec($statement);
        $count++;
    } catch (Exception $e) {
        die("Error executing: " . substr(trim($statement), 0, 120) . "... Error: " . $e->getMessage());
    }
}

echo "MIGRATION_OK: $count";
?>
