<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Core/Env.php';

\App\Core\Env::load(__DIR__ . '/.env');

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

mysqli_report(MYSQLI_REPORT_OFF);

function env_value(string $key, string $fallback = ''): string
{
    return (string) (\App\Core\Env::get($key, $fallback) ?? $fallback);
}

function mask_username(string $value): string
{
    $length = strlen($value);
    if ($length <= 2) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
}

function mask_password(string $value): string
{
    if ($value === '') {
        return '[empty]';
    }

    return '[masked length=' . strlen($value) . ']';
}

function printable(?string $value): string
{
    if ($value === null) {
        return '[null]';
    }

    if ($value === '') {
        return '[empty]';
    }

    return $value;
}

function normalize_port($value): int
{
    $port = (int) $value;
    return $port > 0 ? $port : 0;
}

function add_attempt(array &$attempts, array &$seen, string $label, ?string $host, ?int $port, ?string $socket): void
{
    $key = json_encode([$host, $port, $socket]);
    if ($key === false || isset($seen[$key])) {
        return;
    }

    $seen[$key] = true;
    $attempts[] = [
        'label' => $label,
        'host' => $host,
        'port' => $port,
        'socket' => $socket,
    ];
}

function run_attempt(?string $host, ?int $port, ?string $socket, string $database, string $username, string $password): array
{
    $link = mysqli_init();
    if ($link === false) {
        return [
            'success' => false,
            'errno' => -1,
            'error' => 'mysqli_init failed',
            'thread_id' => null,
            'server_info' => null,
        ];
    }

    mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $connectHost = $host ?? '';
    $connectSocket = $socket ?? '';
    $connected = @mysqli_real_connect(
        $link,
        $connectHost,
        $username,
        $password,
        $database,
        $port ?? 0,
        $connectSocket
    );

    $result = [
        'success' => $connected === true,
        'errno' => mysqli_connect_errno(),
        'error' => mysqli_connect_error() ?: '',
        'thread_id' => null,
        'server_info' => null,
    ];

    if ($connected === true) {
        $result['thread_id'] = mysqli_thread_id($link);
        $result['server_info'] = mysqli_get_server_info($link);
        mysqli_close($link);
    }

    return $result;
}

$configuredHost = env_value('DB_HOST', 'localhost');
$configuredPort = normalize_port(env_value('DB_PORT', '3306'));
$database = env_value('DB_NAME', env_value('DB_DATABASE', ''));
$username = env_value('DB_USER', env_value('DB_USERNAME', ''));
$password = env_value('DB_PASSWORD', '');

$socketCandidates = [];
$defaultSocket = ini_get('mysqli.default_socket');
if (is_string($defaultSocket) && $defaultSocket !== '') {
    $socketCandidates[] = $defaultSocket;
}

$pdoSocket = ini_get('pdo_mysql.default_socket');
if (is_string($pdoSocket) && $pdoSocket !== '') {
    $socketCandidates[] = $pdoSocket;
}

$socketCandidates = array_merge($socketCandidates, [
    '/var/run/mysqld/mysqld.sock',
    '/run/mysqld/mysqld.sock',
    '/tmp/mysql.sock',
    '/tmp/mysqld.sock',
]);

$socketCandidates = array_values(array_unique(array_filter($socketCandidates, static function ($value): bool {
    return is_string($value) && $value !== '';
})));

$attempts = [];
$seen = [];

add_attempt($attempts, $seen, 'configured external endpoint', $configuredHost, $configuredPort, null);
add_attempt($attempts, $seen, 'localhost with configured port', 'localhost', $configuredPort, null);
add_attempt($attempts, $seen, '127.0.0.1 with configured port', '127.0.0.1', $configuredPort, null);
add_attempt($attempts, $seen, 'empty host with configured port', '', $configuredPort, null);
add_attempt($attempts, $seen, 'localhost without port', 'localhost', 0, null);
add_attempt($attempts, $seen, 'localhost on 3306', 'localhost', 3306, null);
add_attempt($attempts, $seen, '127.0.0.1 on 3306', '127.0.0.1', 3306, null);
add_attempt($attempts, $seen, 'empty host without port', '', 0, null);

foreach ($socketCandidates as $socketPath) {
    add_attempt($attempts, $seen, 'socket via localhost', 'localhost', 0, $socketPath);
    add_attempt($attempts, $seen, 'socket via empty host', '', 0, $socketPath);
    add_attempt($attempts, $seen, 'socket via null host', null, 0, $socketPath);
}

header('Content-Type: text/plain; charset=UTF-8');

echo "Cakeouflage DB Runtime Diagnostic\n";
echo "Timestamp: " . gmdate('c') . "\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? '[unknown]') . "\n";
echo "Configured host: " . printable($configuredHost) . "\n";
echo "Configured port: " . $configuredPort . "\n";
echo "Configured database: " . printable($database) . "\n";
echo "Configured username: " . mask_username($username) . "\n";
echo "Configured password: " . mask_password($password) . "\n";
echo "mysqli.default_socket: " . printable(is_string($defaultSocket) ? $defaultSocket : null) . "\n";
echo "pdo_mysql.default_socket: " . printable(is_string($pdoSocket) ? $pdoSocket : null) . "\n";
echo "Loaded .env: " . (is_file(__DIR__ . '/.env') ? 'yes' : 'no') . "\n";
echo "\n";

$successes = 0;
foreach ($attempts as $index => $attempt) {
    $result = run_attempt($attempt['host'], $attempt['port'], $attempt['socket'], $database, $username, $password);
    if ($result['success']) {
        $successes++;
    }

    echo 'Attempt #' . ($index + 1) . "\n";
    echo 'Label: ' . $attempt['label'] . "\n";
    echo 'Host: ' . printable($attempt['host']) . "\n";
    echo 'Port: ' . (($attempt['port'] ?? 0) > 0 ? (string) $attempt['port'] : '[default]') . "\n";
    echo 'Socket: ' . printable($attempt['socket']) . "\n";
    echo 'Success: ' . ($result['success'] ? 'yes' : 'no') . "\n";
    echo 'Connect errno: ' . $result['errno'] . "\n";
    echo 'mysqli error: ' . printable($result['error']) . "\n";
    if ($result['success']) {
        echo 'Thread id: ' . printable($result['thread_id'] !== null ? (string) $result['thread_id'] : null) . "\n";
        echo 'Server info: ' . printable($result['server_info']) . "\n";
    }
    echo "\n";
}

echo 'Summary: ' . $successes . ' successful attempt(s) out of ' . count($attempts) . "\n";