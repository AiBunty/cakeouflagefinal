<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuthRateLimitService
{
    public function isBlocked(PDO $pdo, string $scopeKey, string $bucketKey): bool
    {
        $stmt = $pdo->prepare('SELECT blocked_until FROM auth_rate_limits WHERE scope_key = :scope_key AND bucket_key = :bucket_key LIMIT 1');
        $stmt->execute([
            'scope_key' => $scopeKey,
            'bucket_key' => $bucketKey,
        ]);
        $blockedUntil = (string)($stmt->fetchColumn() ?: '');
        return $blockedUntil !== '' && strtotime($blockedUntil) !== false && strtotime($blockedUntil) > time();
    }

    public function hit(PDO $pdo, string $scopeKey, string $bucketKey, int $maxAttempts = 5, int $lockMinutes = 15): void
    {
        $stmt = $pdo->prepare('SELECT id, attempts FROM auth_rate_limits WHERE scope_key = :scope_key AND bucket_key = :bucket_key LIMIT 1');
        $stmt->execute([
            'scope_key' => $scopeKey,
            'bucket_key' => $bucketKey,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row) {
            $attempts = (int)($row['attempts'] ?? 0) + 1;
            $blockedUntil = $attempts >= $maxAttempts ? date('Y-m-d H:i:s', strtotime('+' . $lockMinutes . ' minutes')) : null;
            $update = $pdo->prepare('UPDATE auth_rate_limits SET attempts = :attempts, blocked_until = :blocked_until WHERE id = :id');
            $update->execute([
                'attempts' => $attempts,
                'blocked_until' => $blockedUntil,
                'id' => (int)$row['id'],
            ]);
            return;
        }

        $insert = $pdo->prepare('INSERT INTO auth_rate_limits (scope_key, bucket_key, attempts, blocked_until) VALUES (:scope_key, :bucket_key, 1, NULL)');
        $insert->execute([
            'scope_key' => $scopeKey,
            'bucket_key' => $bucketKey,
        ]);
    }

    public function clear(PDO $pdo, string $scopeKey, string $bucketKey): void
    {
        $stmt = $pdo->prepare('DELETE FROM auth_rate_limits WHERE scope_key = :scope_key AND bucket_key = :bucket_key');
        $stmt->execute([
            'scope_key' => $scopeKey,
            'bucket_key' => $bucketKey,
        ]);
    }
}