<?php
declare(strict_types=1);
$env = parse_ini_file(__DIR__ . '/../../../.env.production');
$dsn = 'mysql:host=mysql.gb.stackcp.com;port=44087;dbname=' . $env['DB_NAME'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, (string)$env['DB_USER'], (string)$env['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$stmt = $pdo->query("SELECT id,email,otp,expires_at,attempt_count,created_at FROM otp_verifications ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll();
echo json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
