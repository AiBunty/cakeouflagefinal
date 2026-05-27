<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuthManager;

final class CustomerAuthMiddleware
{
    public static function requireAuthenticated(string $redirectTo = '/account/login.php'): bool
    {
        if (AuthManager::isCustomerAuthenticated()) {
            return true;
        }

        header('Location: ' . $redirectTo);
        return false;
    }
}
