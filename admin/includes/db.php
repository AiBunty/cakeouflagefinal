<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Env.php';

\App\Core\Env::load(dirname(__DIR__, 2) . '/.env');

$host = \App\Core\Env::get('DB_HOST', 'localhost') ?? 'localhost';
$user = \App\Core\Env::get('DB_USER', 'root') ?? 'root';
$pass = \App\Core\Env::get('DB_PASSWORD', '') ?? '';
$db = \App\Core\Env::get('DB_NAME', 'cakeouflage') ?? 'cakeouflage';
$port = (int) (\App\Core\Env::get('DB_PORT', '3306') ?? '3306');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die('DB Connection Failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>