<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuthManager
{
    private const OTP_TTL_MINUTES = 5;
    private const OTP_COOLDOWN_SECONDS = 60;
    private const OTP_MAX_ATTEMPTS = 5;

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
        if (self::otpRecentlyRequested($pdo, $email)) {
            throw new \RuntimeException('Please wait 60 seconds before requesting a new OTP.', 429);
        }

        $otp = (string)random_int(100000, 999999);

        try {
            MailService::sendOtp($email, $otp, $name);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to send OTP email right now. Please try again shortly.', 500, $e);
        }

        $pdo->prepare('DELETE FROM otp_verifications WHERE email = :email')->execute(['email' => $email]);

        $stmt = $pdo->prepare(
            'INSERT INTO otp_verifications (email, otp, expires_at)
             VALUES (:email, :otp, NOW() + INTERVAL ' . self::OTP_TTL_MINUTES . ' MINUTE)'
        );
        if (!$stmt->execute(['email' => $email, 'otp' => $otp])) {
            $pdo->prepare('DELETE FROM otp_verifications WHERE email = :email')->execute(['email' => $email]);
            throw new \RuntimeException('Unable to finalize OTP right now. Please request a new OTP.', 500);
        }
    }

    public static function validateOtp(PDO $pdo, string $email, string $otp, string $scope = 'customer'): void
    {
        $attemptKey = self::attemptKey($scope, $email);
        self::ensureAttemptBucket($attemptKey);

        $attemptInfo = $_SESSION[$attemptKey];
        $now = time();
        if (($now - (int)$attemptInfo['window_started']) > 600) {
            $_SESSION[$attemptKey] = ['count' => 0, 'window_started' => $now];
            $attemptInfo = $_SESSION[$attemptKey];
        }

        if ((int)$attemptInfo['count'] >= self::OTP_MAX_ATTEMPTS) {
            throw new \RuntimeException('Too many OTP verification attempts. Please wait 10 minutes and try again.', 429);
        }

        $stmt = $pdo->prepare(
            'SELECT id FROM otp_verifications
             WHERE email = :email AND otp = :otp AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['email' => $email, 'otp' => $otp]);
        $otpRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otpRow) {
            $_SESSION[$attemptKey] = [
                'count' => ((int)$attemptInfo['count']) + 1,
                'window_started' => (int)$attemptInfo['window_started'],
            ];
            throw new \RuntimeException('Invalid or expired OTP', 401);
        }

        $pdo->prepare('DELETE FROM otp_verifications WHERE email = :email')->execute(['email' => $email]);
        unset($_SESSION[$attemptKey]);
    }

    public static function establishCustomerSession(PDO $pdo, string $email, string $name, string $phone, bool $rememberDevice): int
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $userId = (int)$user['id'];
            $updateParts = [];
            $params = ['id' => $userId];

            if ($name !== '') {
                $updateParts[] = 'full_name = :name';
                $params['name'] = $name;
            }
            if ($phone !== '') {
                $updateParts[] = 'phone = :phone';
                $params['phone'] = $phone;
            }
            if ($updateParts !== []) {
                $pdo->prepare('UPDATE users SET ' . implode(', ', $updateParts) . ' WHERE id = :id')->execute($params);
            }
        } else {
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $insert = $pdo->prepare(
                'INSERT INTO users (full_name, email, phone, password_hash, role)
                 VALUES (:full_name, :email, :phone, :password_hash, "customer")'
            );
            $insert->execute([
                'full_name' => $name !== '' ? $name : 'Guest User',
                'email' => $email,
                'phone' => $phone !== '' ? $phone : '0000000000',
                'password_hash' => $hash,
            ]);
            $userId = (int)$pdo->lastInsertId();
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = 'customer';
        $_SESSION['otp_verified'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_email'] = $email;
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

    public static function logoutCustomer(): void
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
        self::expireSessionCookie();
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

    private static function attemptKey(string $scope, string $email): string
    {
        return strtolower(trim($scope)) . '_otp_verify_attempts_' . strtolower(trim($email));
    }

    private static function ensureAttemptBucket(string $key): void
    {
        if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'window_started' => time()];
        }
    }

    private static function otpRecentlyRequested(PDO $pdo, string $email): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM otp_verifications
             WHERE email = :email AND created_at > (NOW() - INTERVAL :cooldown SECOND)
             LIMIT 1'
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':cooldown', self::OTP_COOLDOWN_SECONDS, PDO::PARAM_INT);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();
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
