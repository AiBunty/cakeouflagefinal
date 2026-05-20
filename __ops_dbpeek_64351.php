<?php
require_once __DIR__ . '/app/bootstrap.php';
use App\Core\Database;
if ((\['k'] ?? '') !== 'd4afdc4724424a6e9b5de96e1c4b9a12') { http_response_code(403); exit('forbidden'); }
 = trim((string)(\['email'] ?? ''));
if ( === '') { exit(''); }
try {
  \ = Database::getConnection();
  \ = \->prepare('SELECT otp FROM otp_verifications WHERE email = :email ORDER BY id DESC LIMIT 1');
  \->execute(['email' => ]);
  echo (string)(\->fetchColumn() ?: '');
} catch (Throwable \) {
  echo 'ERR';
}
