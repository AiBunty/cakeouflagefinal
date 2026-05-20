<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || $_SESSION['_csrf_token'] === '') {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public static function validateRequest(): bool
    {

    
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        $headerToken = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($headerToken !== '') {
            return hash_equals($sessionToken, $headerToken);
        }

        $postedToken = trim((string)($_POST['_csrf'] ?? ''));
        if ($postedToken !== '') {
            return hash_equals($sessionToken, $postedToken);
        }

        $decoded = Request::json();
        if ($decoded === []) {
            return false;
        }

        $bodyToken = trim((string)($decoded['_csrf'] ?? ''));
        return $bodyToken !== '' && hash_equals($sessionToken, $bodyToken);
    }
}