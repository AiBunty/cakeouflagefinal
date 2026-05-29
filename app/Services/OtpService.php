<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class OtpService
{
    private const OTP_TTL_MINUTES = 5;
    private const OTP_COOLDOWN_SECONDS = 60;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_ATTEMPT_WINDOW_SECONDS = 600;

    public static function issueOtp(PDO $pdo, string $email, string $scope = 'customer'): string
    {
        $normalizedEmail = CustomerLookupService::normalizeEmail($email);
        if ($normalizedEmail === '') {
            throw new \RuntimeException('Valid email required', 422);
        }

        if (self::otpRecentlyRequested($pdo, $normalizedEmail)) {
            throw new \RuntimeException('Please wait 60 seconds before requesting a new OTP.', 429);
        }

        $otp = (string)random_int(100000, 999999);
        self::clearOtp($pdo, $normalizedEmail);

        $stmt = $pdo->prepare(
            'INSERT INTO otp_verifications (email, otp, expires_at)
             VALUES (:email, :otp, NOW() + INTERVAL ' . self::OTP_TTL_MINUTES . ' MINUTE)'
        );
        if (!$stmt->execute(['email' => $normalizedEmail, 'otp' => $otp])) {
            self::clearOtp($pdo, $normalizedEmail);
            throw new \RuntimeException('Unable to finalize OTP right now. Please request a new OTP.', 500);
        }

        return $otp;
    }

    public static function consumeOtp(PDO $pdo, string $email, string $otp, string $scope = 'customer'): void
    {
        $normalizedEmail = CustomerLookupService::normalizeEmail($email);
        if ($normalizedEmail === '') {
            throw new \RuntimeException('Valid email required', 422);
        }

        $cleanOtp = preg_replace('/\D+/', '', $otp) ?? '';
        if (strlen($cleanOtp) !== 6) {
            throw new \RuntimeException('Invalid or expired OTP', 401);
        }

        $attemptKey = self::attemptKey($scope, $normalizedEmail);
        self::ensureAttemptBucket($attemptKey);

        $attemptInfo = $_SESSION[$attemptKey];
        $now = time();
        if (($now - (int)$attemptInfo['window_started']) > self::OTP_ATTEMPT_WINDOW_SECONDS) {
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
        $stmt->execute(['email' => $normalizedEmail, 'otp' => $cleanOtp]);
        $otpRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otpRow) {
            $_SESSION[$attemptKey] = [
                'count' => ((int)$attemptInfo['count']) + 1,
                'window_started' => (int)$attemptInfo['window_started'],
            ];
            throw new \RuntimeException('Invalid or expired OTP', 401);
        }

        self::clearOtp($pdo, $normalizedEmail);
        unset($_SESSION[$attemptKey]);
    }

    public static function clearOtp(PDO $pdo, string $email): void
    {
        $normalizedEmail = CustomerLookupService::normalizeEmail($email);
        if ($normalizedEmail === '') {
            return;
        }
        $pdo->prepare('DELETE FROM otp_verifications WHERE email = :email')->execute(['email' => $normalizedEmail]);
    }

    private static function attemptKey(string $scope, string $email): string
    {
        return strtolower(trim($scope)) . '_otp_verify_attempts_' . CustomerLookupService::normalizeEmail($email);
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
}
