<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Env.php';

\App\Core\Env::load(dirname(__DIR__, 2) . '/.env');

$host = \App\Core\Env::get('DB_HOST', 'localhost') ?? 'localhost';
$user = \App\Core\Env::get('DB_USER', 'root') ?? 'root';
$pass = \App\Core\Env::get('DB_PASSWORD', '') ?? '';
$db = \App\Core\Env::get('DB_NAME', 'cakeouflage') ?? 'cakeouflage';
$port = (int) (\App\Core\Env::get('DB_PORT', '3306') ?? '3306');

mysqli_report(MYSQLI_REPORT_OFF);

$candidates = [
    [$host, $user, $pass, $db, $port],
];

// Docker local fallback for production-sim runs where .env points to host DB values.
$isDockerRuntime = is_file('/.dockerenv') || getenv('APP_USE_DOCKER_DB') === '1';
if ($isDockerRuntime) {
    $candidates[] = ['db', 'cakeouflage', 'cakeouflage', 'cakeouflage_local', 3306];
    $candidates[] = ['db', 'root', 'root', 'cakeouflage_local', 3306];
}

$conn = null;
$lastError = 'Unknown connection error';
foreach ($candidates as [$h, $u, $p, $d, $po]) {
    $try = new mysqli((string) $h, (string) $u, (string) $p, (string) $d, (int) $po);
    if ($try->connect_errno === 0) {
        $conn = $try;
        break;
    }

    $lastError = $try->connect_error ?: $lastError;
}

if (!($conn instanceof mysqli)) {
    die('DB Connection Failed: ' . $lastError);
}

$conn->set_charset('utf8mb4');
?>