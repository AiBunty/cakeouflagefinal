<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Env.php';

\App\Core\Env::load(dirname(__DIR__, 2) . '/.env');

$host = \App\Core\Env::get('DB_HOST', 'localhost') ?? 'localhost';
$user = \App\Core\Env::get('DB_USER', 'root') ?? 'root';
$pass = \App\Core\Env::get('DB_PASSWORD', '') ?? '';
$db = \App\Core\Env::get('DB_NAME', 'cakeouflage') ?? 'cakeouflage';
$port = (int) (\App\Core\Env::get('DB_PORT', '3306') ?? '3306');

$debugMode = strtolower((string) (\App\Core\Env::get('APP_DEBUG', 'false') ?? 'false')) === 'true'
    || strtolower((string) (\App\Core\Env::get('APP_ENV', 'production') ?? 'production')) !== 'production'
    || is_file('/.dockerenv');

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', $debugMode ? '1' : '0');

mysqli_report(MYSQLI_REPORT_OFF);

// In Docker, 'localhost' resolves to a Unix socket path that doesn't exist inside the
// container — emitting a Warning before any output buffer is open.  Skip it entirely
// and connect directly to the Docker Compose service name 'db' via TCP.
$isDockerRuntime = is_file('/.dockerenv') || getenv('APP_USE_DOCKER_DB') === '1';
$hostLower = strtolower(trim((string) $host));

if ($isDockerRuntime && in_array($hostLower, ['', 'localhost', '127.0.0.1'], true)) {
    $candidates = [
        ['db', $user, $pass, $db, $port > 0 ? $port : 3306],
        ['db', 'cakeouflage', 'cakeouflage', 'cakeouflage_local', 3306],
        ['db', 'root', 'root', 'cakeouflage_local', 3306],
    ];
} else {
    $candidates = [
        [$host, $user, $pass, $db, $port],
    ];
    if ($isDockerRuntime) {
        $candidates[] = ['db', 'cakeouflage', 'cakeouflage', 'cakeouflage_local', 3306];
        $candidates[] = ['db', 'root', 'root', 'cakeouflage_local', 3306];
    } elseif ($hostLower === 'localhost') {
        // Shared-hosting stacks may require TCP localhost instead of a Unix socket.
        $candidates[] = ['127.0.0.1', $user, $pass, $db, $port > 0 ? $port : 3306];
    } elseif ($hostLower !== '' && $hostLower !== '127.0.0.1') {
        // Some hosting providers expose DBs differently to local PHP than to external clients.
        $candidates[] = ['127.0.0.1', $user, $pass, $db, 3306];
        $candidates[] = ['localhost', $user, $pass, $db, 3306];
    }
}

$conn = null;
$lastError = 'Unknown connection error';
foreach ($candidates as [$h, $u, $p, $d, $po]) {
    $try = @new mysqli((string) $h, (string) $u, (string) $p, (string) $d, (int) $po);
    if ($try->connect_errno === 0) {
        $conn = $try;
        break;
    }

    $lastError = $try->connect_error ?: $lastError;
}

if (!($conn instanceof mysqli)) {
    if ($debugMode) {
        die('DB Connection Failed: ' . $lastError);
    }

    die('DB Connection Failed. Please check the server log.');
}

$conn->set_charset('utf8mb4');

/**
 * Write structured admin DB errors to the PHP log.
 */
if (!function_exists('adminDbLog')) {
function adminDbLog(string $operation, string $message, ?string $sql = null, array $context = []): void
{
    $payload = [
        'operation' => $operation,
        'message' => $message,
    ];

    if ($sql !== null) {
        $payload['sql'] = $sql;
    }
    if (!empty($context)) {
        $payload['context'] = $context;
    }

    error_log('[admin-db] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
}

/**
 * Prepare a MySQLi statement with structured error handling.
 *
 * Prevents fatal "Call to a member function bind_param() on bool" crashes by
 * validating the prepare() result and throwing a catchable RuntimeException.
 * The SQL and MySQLi error are written to the PHP error log on failure.
 *
 * @throws RuntimeException when $conn->prepare() returns false.
 */
if (!function_exists('safePrepare')) {
function safePrepare(mysqli $conn, string $sql): mysqli_stmt
{
    $startedAt = microtime(true);
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $err = $conn->error;
        adminDbLog('prepare', $err, $sql, [
            'errno' => $conn->errno,
            'sqlstate' => $conn->sqlstate,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);
        throw new RuntimeException('DB prepare failed: ' . $err);
    }

    adminDbLog('prepare_ok', 'Statement prepared', null, [
        'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
    ]);

    return $stmt;
}
}

/**
 * Execute a MySQLi query with consistent logging.
 *
 * @throws RuntimeException when $conn->query() returns false.
 */
if (!function_exists('safeQuery')) {
function safeQuery(mysqli $conn, string $sql): mysqli_result|bool
{
    $startedAt = microtime(true);
    $result = $conn->query($sql);

    if ($result === false) {
        adminDbLog('query', $conn->error, $sql, [
            'errno' => $conn->errno,
            'sqlstate' => $conn->sqlstate,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);
        throw new RuntimeException('DB query failed: ' . $conn->error);
    }

    return $result;
}
}
?>