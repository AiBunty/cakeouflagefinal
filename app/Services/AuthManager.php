<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuthManager
{
    public static function isCustomerAuthenticated(): bool
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $userRole = (string)($_SESSION['user_role'] ?? '');
        $otpVerified = !empty($_SESSION['otp_verified']);
        $legacyLoggedIn = !empty($_SESSION['logged_in']);

        return $userId > 0
            && $userRole === 'customer'
            && ($otpVerified || $legacyLoggedIn);
    }

    public static function isAdminAuthenticated(): bool
    {
        return (int)($_SESSION['admin_id'] ?? 0) > 0 || (int)($_SESSION['admin'] ?? 0) > 0;
    }

    public static function sendOtp(PDO $pdo, string $email, string $name = 'User'): void
    {
        $normalizedEmail = CustomerLookupService::normalizeEmail($email);
        $otp = OtpService::issueOtp($pdo, $normalizedEmail, 'customer');

        try {
            MailService::sendOtp($normalizedEmail, $otp, $name);
        } catch (\Throwable $e) {
            OtpService::clearOtp($pdo, $normalizedEmail);
            throw new \RuntimeException('Unable to send OTP email right now. Please try again shortly.', 500, $e);
        }
    }

    public static function validateOtp(PDO $pdo, string $email, string $otp, string $scope = 'customer'): void
    {
        OtpService::consumeOtp($pdo, $email, $otp, $scope);
    }

    public static function establishCustomerSession(PDO $pdo, string $email, string $name, string $phone, bool $rememberDevice): int
    {
        $normalizedEmail = CustomerLookupService::normalizeEmail($email);
        $user = CustomerLookupService::findCustomerByEmail($pdo, $normalizedEmail);

        if ($user) {
            $userId = (int)$user['id'];
            CustomerLookupService::updateCustomerProfile($pdo, $userId, $name, $phone);
        } else {
            $userId = CustomerLookupService::createCustomer($pdo, $normalizedEmail, $name, $phone);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = 'customer';
        $_SESSION['otp_verified'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_email'] = $normalizedEmail;
        $_SESSION['remember_device'] = $rememberDevice;
        $_SESSION['authenticated_at'] = time();

        self::refreshSessionCookie($rememberDevice);

        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $userId]);
        self::mergeGuestCartIntoCustomerCart($pdo, $userId);

        return $userId;
    }

    public static function establishAdminSession(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['admin'] = (int)($admin['id'] ?? 0);
        $_SESSION['admin_id'] = (int)($admin['id'] ?? 0);
        $_SESSION['admin_name'] = (string)($admin['full_name'] ?? 'Admin');
        $_SESSION['admin_role'] = (string)($admin['role'] ?? 'admin');
        $_SESSION['admin_otp_verified'] = true;
        $_SESSION['admin_authenticated_at'] = time();
    }

    public static function logoutCustomer(bool $expireCookie = false): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_role'],
            $_SESSION['otp_verified'],
            $_SESSION['logged_in'],
            $_SESSION['user_email'],
            $_SESSION['remember_device'],
            $_SESSION['authenticated_at']
        );
        if ($expireCookie) {
            self::expireSessionCookie();
        }
    }

    public static function logoutAdmin(): void
    {
        unset(
            $_SESSION['admin'],
            $_SESSION['admin_id'],
            $_SESSION['admin_name'],
            $_SESSION['admin_role'],
            $_SESSION['admin_otp_verified'],
            $_SESSION['admin_authenticated_at'],
            $_SESSION['admin_permissions']
        );
        self::expireSessionCookie();
    }

    private static function mergeGuestCartIntoCustomerCart(PDO $pdo, int $userId): void
    {
        $sessionId = session_id();
        if ($sessionId === '') {
            return;
        }

        $stmt = $pdo->prepare('SELECT id FROM carts WHERE session_id = :session_id LIMIT 1');
        $stmt->execute(['session_id' => $sessionId]);
        $guestCartId = (int)($stmt->fetchColumn() ?: 0);

        $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $userCartId = (int)($stmt->fetchColumn() ?: 0);

        if ($guestCartId <= 0) {
            return;
        }

        if ($userCartId > 0) {
            $pdo->prepare('UPDATE cart_items SET cart_id = :user_cart_id WHERE cart_id = :guest_cart_id')
                ->execute(['user_cart_id' => $userCartId, 'guest_cart_id' => $guestCartId]);
            return;
        }

        $pdo->prepare('UPDATE carts SET user_id = :user_id WHERE id = :guest_cart_id')
            ->execute(['user_id' => $userId, 'guest_cart_id' => $guestCartId]);
    }

    private static function refreshSessionCookie(bool $rememberDevice): void
    {
        if (!ini_get('session.use_cookies')) {
            return;
        }

        $params = session_get_cookie_params();
        $defaultLifetime = (int)($params['lifetime'] ?? 0);
        $lifetime = $rememberDevice ? (60 * 60 * 24 * 30) : $defaultLifetime;
        $expires = $lifetime > 0 ? (time() + $lifetime) : 0;

        setcookie(session_name(), session_id(), [
            'expires' => $expires,
            'path' => (string)($params['path'] ?? '/'),
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => (string)($params['samesite'] ?? 'Lax'),
        ]);
    }

    private static function expireSessionCookie(): void
    {
        if (!ini_get('session.use_cookies')) {
            return;
        }

        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string)($params['path'] ?? '/'),
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => (string)($params['samesite'] ?? 'Lax'),
        ]);
    }
}
