<?php
if ((\['key'] ?? '') !== '5477f162-2f35-4f74-9e58-fd6b2a5e2c9c') die('Unauthorized');
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';
try {
    \ = \App\Core\Database::getConnection();
    \ = \['email'] ?? '';
    \ = \->prepare("SELECT otp_code FROM otp_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    \->execute([\]);
    echo \->fetchColumn() ?: 'NOT_FOUND';
} catch (Exception \) {
    echo 'ERROR: ' . \->getMessage();
}