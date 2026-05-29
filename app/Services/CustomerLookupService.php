<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class CustomerLookupService
{
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /** @return array<string, mixed>|null */
    public static function findCustomerByEmail(PDO $pdo, string $email): ?array
    {
        $normalizedEmail = self::normalizeEmail($email);
        if ($normalizedEmail === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, phone, phone_e164, role
             FROM users
             WHERE email = :email AND role = "customer"
             LIMIT 1'
        );
        $stmt->execute(['email' => $normalizedEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return $user;
        }

        // Compatibility path for legacy rows with non-canonical casing/spacing.
        $fallback = $pdo->prepare(
            'SELECT id, full_name, email, phone, phone_e164, role
             FROM users
             WHERE LOWER(TRIM(email)) = :email AND role = "customer"
             LIMIT 1'
        );
        $fallback->execute(['email' => $normalizedEmail]);
        $row = $fallback->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findActiveAdminByEmail(PDO $pdo, string $email): ?array
    {
        $normalizedEmail = self::normalizeEmail($email);
        if ($normalizedEmail === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, role
             FROM admins
             WHERE email = :email AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['email' => $normalizedEmail]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function createCustomer(PDO $pdo, string $email, string $name, string $phone): int
    {
        $normalizedEmail = self::normalizeEmail($email);
        $fullName = trim($name) !== '' ? trim($name) : 'Guest User';
        $phoneRaw = trim($phone);
        $phoneE164 = PhoneNormalizerService::normalize($phoneRaw);
        $phoneValue = $phoneRaw !== '' ? $phoneRaw : '0000000000';
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $insert = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, phone_e164, password_hash, role)
             VALUES (:full_name, :email, :phone, :phone_e164, :password_hash, "customer")'
        );
        $insert->execute([
            'full_name' => $fullName,
            'email' => $normalizedEmail,
            'phone' => $phoneValue,
            'phone_e164' => $phoneE164 !== '' ? $phoneE164 : null,
            'password_hash' => $hash,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function updateCustomerProfile(PDO $pdo, int $userId, string $name, string $phone): void
    {
        if ($userId <= 0) {
            return;
        }

        $updateParts = [];
        $params = ['id' => $userId];

        $name = trim($name);
        if ($name !== '') {
            $updateParts[] = 'full_name = :name';
            $params['name'] = $name;
        }

        $phone = trim($phone);
        if ($phone !== '') {
            $updateParts[] = 'phone = :phone';
            $params['phone'] = $phone;
            $phoneE164 = PhoneNormalizerService::normalize($phone);
            $updateParts[] = 'phone_e164 = :phone_e164';
            $params['phone_e164'] = $phoneE164 !== '' ? $phoneE164 : null;
        }

        if ($updateParts === []) {
            return;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $updateParts) . ' WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
    }
}
