<?php
declare(strict_types=1);

require __DIR__ . '/../../app/Core/Env.php';

\App\Core\Env::load(__DIR__ . '/../../.env.production');

$email = (string)($argv[1] ?? '');
if ($email === '') {
    fwrite(STDERR, "email argument required\n");
    exit(1);
}

$host = \App\Core\Env::get('DB_HOST', 'mysql.gb.stackcp.com');
$port = (int)\App\Core\Env::get('DB_PORT', '3306');
$db = \App\Core\Env::get('DB_DATABASE', '');
$user = \App\Core\Env::get('DB_USERNAME', '');
$pass = \App\Core\Env::get('DB_PASSWORD', '');

if ($db === '' || $user === '') {
    fwrite(STDERR, "DB config missing\n");
    exit(1);
}

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, $options);
} catch (Throwable $e) {
    $pdo = new PDO("mysql:host=mysql.gb.stackcp.com;port=41209;dbname={$db};charset=utf8mb4", $user, $pass, $options);
}

$stmt = $pdo->prepare('SELECT otp FROM otp_verifications WHERE email = :email ORDER BY id DESC LIMIT 1');
$stmt->execute(['email' => $email]);
$otp = (string)($stmt->fetchColumn() ?: '');

echo $otp;
