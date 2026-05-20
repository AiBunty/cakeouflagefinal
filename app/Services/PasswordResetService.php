<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class PasswordResetService
{
    /** @return array{token:string,expires_at:string} */
    public function createToken(PDO $pdo, int $userId, string $email): array
    {
        $token = bin2hex(random_bytes(24));
        $hash = password_hash($token, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+2 hours'));

        $cleanup = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id');
        $cleanup->execute(['user_id' => $userId]);

        $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, email, token_hash, expires_at) VALUES (:user_id, :email, :token_hash, :expires_at)');
        $insert->execute([
            'user_id' => $userId,
            'email' => $email,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function consume(PDO $pdo, string $email, string $token, string $newPassword): bool
    {
        $stmt = $pdo->prepare('SELECT id, user_id, token_hash, expires_at, used_at FROM password_reset_tokens WHERE email = :email ORDER BY id DESC LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return false;
        }

        if ((string)($row['used_at'] ?? '') !== '' || strtotime((string)$row['expires_at']) < time()) {
            return false;
        }

        if (!password_verify($token, (string)$row['token_hash'])) {
            return false;
        }

        $pdo->beginTransaction();
        try {
            $updateUser = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $updateUser->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                'id' => (int)$row['user_id'],
            ]);

            $markUsed = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id');
            $markUsed->execute(['id' => (int)$row['id']]);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException('Failed to reset password');
        }
    }
}