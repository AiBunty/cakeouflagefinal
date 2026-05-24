<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Core/Env.php';

\App\Core\Env::load(__DIR__ . '/.env');

error_reporting(E_ALL);
ini_set('display_errors', '1');

function envv(string $key, string $fallback = ''): string
{
    return (string) (\App\Core\Env::get($key, $fallback) ?? $fallback);
}

function showv(?string $value): string
{
    if ($value === null) {
        return '[null]';
    }

    return $value === '' ? '[empty]' : $value;
}

$host = envv('DB_HOST', '');
$port = envv('DB_PORT', '3306');
$dbName = envv('DB_NAME', envv('DB_DATABASE', ''));
$user = envv('DB_USER', envv('DB_USERNAME', ''));
$pass = envv('DB_PASSWORD', '');
$charset = envv('DB_CHARSET', 'utf8mb4');

$attempts = [
    ['label' => 'configured env', 'host' => $host, 'port' => $port],
    ['label' => 'stackcp server host', 'host' => 'sdb-77.hosting.stackcp.net', 'port' => '3306'],
    ['label' => 'stackcp remote host', 'host' => 'mysql.gb.stackcp.com', 'port' => '41209'],
    ['label' => 'loopback tcp', 'host' => '127.0.0.1', 'port' => '3306'],
    ['label' => 'localhost tcp', 'host' => 'localhost', 'port' => '3306'],
];

header('Content-Type: text/plain; charset=UTF-8');

echo "PDO Runtime Diagnostic\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'pdo_mysql loaded: ' . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
echo 'Configured host: ' . showv($host) . "\n";
echo 'Configured port: ' . showv($port) . "\n";
echo 'Configured database: ' . showv($dbName) . "\n";
echo "\n";

foreach ($attempts as $index => $attempt) {
    $dsn = 'mysql:host=' . $attempt['host']
        . ';port=' . $attempt['port']
        . ';dbname=' . $dbName
        . ';charset=' . $charset;

    echo 'Attempt #' . ($index + 1) . "\n";
    echo 'Label: ' . $attempt['label'] . "\n";
    echo 'Host: ' . showv($attempt['host']) . "\n";
    echo 'Port: ' . showv($attempt['port']) . "\n";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $server = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
        echo "Success: yes\n";
        echo 'Server hostname: ' . showv($server) . "\n\n";
    } catch (Throwable $e) {
        echo "Success: no\n";
        echo 'Exception: ' . get_class($e) . "\n";
        echo 'Message: ' . $e->getMessage() . "\n\n";
    }
}