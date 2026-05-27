<?php
declare(strict_types=1);
$env = parse_ini_file(__DIR__ . '/../../../.env.production');
if (!is_array($env)) {
    fwrite(STDERR, "Unable to read .env.production\n");
    exit(1);
}
$dsn = 'mysql:host=mysql.gb.stackcp.com;port=44087;dbname=' . $env['DB_NAME'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, (string)$env['DB_USER'], (string)$env['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$columns = [];
foreach ($pdo->query('SHOW COLUMNS FROM otp_verifications') as $column) {
    $columns[] = (string)$column['Field'];
}

fwrite(STDERR, 'Columns: ' . implode(', ', $columns) . PHP_EOL);

$codeColumn = in_array('otp', $columns, true) ? 'otp' : (in_array('otp_code', $columns, true) ? 'otp_code' : '');
if ($codeColumn === '') {
    fwrite(STDERR, "No OTP value column found\n");
    exit(1);
}

$stmt = $pdo->prepare("SELECT {$codeColumn} FROM otp_verifications WHERE email = :email ORDER BY id DESC LIMIT 1");
$stmt->execute(['email' => 'aibuntysystems@gmail.com']);
$code = (string)($stmt->fetchColumn() ?: '');
echo $code;
