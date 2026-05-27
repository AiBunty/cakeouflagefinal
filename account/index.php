<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$redirectTo = \App\Services\AuthManager::isCustomerAuthenticated()
    ? '/account/dashboard.php'
    : '/account/login.php';

header('Location: ' . $redirectTo);
exit;
