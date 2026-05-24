<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): void
    {
        // Discard any buffered output (PHP notices, warnings) before sending
        // the JSON response so the body is always clean and the status code
        // can still be set correctly.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
