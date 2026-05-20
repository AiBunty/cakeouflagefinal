<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function rawBody(): string
    {
        if (!array_key_exists('_cakeouflage_raw_body', $GLOBALS)) {
            $GLOBALS['_cakeouflage_raw_body'] = file_get_contents('php://input') ?: '';
        }

        return (string)$GLOBALS['_cakeouflage_raw_body'];
    }

    /** @return array<string,mixed> */
    public static function json(): array
    {
        $raw = self::rawBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}