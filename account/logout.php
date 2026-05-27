<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

\App\Services\AuthManager::logoutCustomer();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: /account/login.php');
exit;
