<?php
require __DIR__ . '/../../app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/../../.env.production');
$email = $argv[1] ?? '';
$host = \App\Core\Env::get('DB_HOST', 'mysql.gb.stackcp.com');
$port = (int)\App\Core\Env::get('DB_PORT', '3306');
$db = \App\Core\Env::get('DB_DATABASE', '');
$user = \App\Core\Env::get('DB_USERNAME', '');
$pass = \App\Core\Env::get('DB_PASSWORD', '');
$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
try {
  $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, $opts);
} catch (Throwable $e) {
  $pdo = new PDO("mysql:host=mysql.gb.stackcp.com;port=41209;dbname={$db};charset=utf8mb4", $user, $pass, $opts);
}
$stmt = $pdo->prepare('SELECT otp FROM otp_verifications WHERE email = :email ORDER BY id DESC LIMIT 1');
$stmt->execute(['email' => $email]);
echo (string)($stmt->fetchColumn() ?: '');
