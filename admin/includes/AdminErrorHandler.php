<?php
declare(strict_types=1);

if (!class_exists('AdminErrorHandler')) {
final class AdminErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $e): void
    {
        self::log('exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        self::renderSafeResponse();
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::log('php_error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]);

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleShutdown(): void
    {
        $lastError = error_get_last();
        if ($lastError === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)$lastError['type'], $fatalTypes, true)) {
            return;
        }

        self::log('shutdown_fatal', $lastError);
        self::renderSafeResponse();
    }

    private static function log(string $event, array $context): void
    {
        $payload = [
            'event' => $event,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'admin_id' => (int)($_SESSION['admin'] ?? 0),
            'time' => gmdate('c'),
            'post_keys' => array_keys($_POST ?? []),
            'get_keys' => array_keys($_GET ?? []),
        ] + $context;

        error_log('[admin-fatal] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function renderSafeResponse(): void
    {
        if (headers_sent() === false) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $message = '<h2>Admin request failed</h2><p>A technical error was logged. Please retry. If the issue persists, review the server log with the current request path.</p>';

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Admin request failed. Review the PHP error log for details.\n");
            return;
        }

        if (!self::responseAlreadyContainsBody()) {
            echo $message;
        }
    }

    private static function responseAlreadyContainsBody(): bool
    {
        return ob_get_length() !== false && ob_get_length() > 0;
    }
}
}

AdminErrorHandler::register();