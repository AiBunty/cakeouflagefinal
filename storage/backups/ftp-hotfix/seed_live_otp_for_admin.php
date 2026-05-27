<?php
declare(strict_types=1);
$env = parse_ini_file(__DIR__ . '/../../../.env.production');
$dsn = 'mysql:host=mysql.gb.stackcp.com;port=44087;dbname=' . $env['DB_NAME'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, (string)$env['DB_USER'], (string)$env['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$email = 'aibuntysystems@gmail.com';
$otp = '337329';
$pdo->prepare('DELETE FROM otp_verifications WHERE email = :email')->execute(['email' => $email]);
$pdo->prepare('INSERT INTO otp_verifications (email, otp, expires_at, attempt_count, created_at) VALUES (:email, :otp, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0, NOW())')->execute(['email' => $email, 'otp' => $otp]);
echo $otp . PHP_EOL;
