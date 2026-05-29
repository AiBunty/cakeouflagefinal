<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;

require_once __DIR__ . '/Core/Env.php';

$rootConfig = __DIR__ . '/../config.php';
if (is_file($rootConfig)) {
    require_once $rootConfig;
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

Env::load(__DIR__ . '/../.env');

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', Env::get('APP_DEBUG', Env::get('APP_ENV', 'production') !== 'production' ? '1' : '0') === '1');
}

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$errorLogPath = $logDir . '/php-error.log';
ini_set('log_errors', '1');
ini_set('error_log', $errorLogPath);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(static function (\Throwable $e): void {
    error_log(sprintf(
        '[%s] %s in %s:%d\n%s',
        date('c'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    http_response_code(500);
    if (APP_DEBUG) {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo "Unhandled exception: " . $e->getMessage() . "\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n";
        return;
    }

    echo 'Server error. Please check storage/logs/php-error.log';
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    error_log(sprintf(
        '[%s] Fatal error: %s in %s:%d',
        date('c'),
        $error['message'] ?? 'Unknown fatal error',
        $error['file'] ?? 'unknown',
        (int)($error['line'] ?? 0)
    ));
});

// Load Composer autoloader (PhpSpreadsheet + any future packages).
// On some shared hosts, Composer platform checks can throw at runtime.
// Keep app bootable for core routes even when optional vendor packages cannot be loaded.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    ob_start();
    $displayErrorsPrev = ini_get('display_errors');
    ini_set('display_errors', '1');
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $autoloadOutput = (string)ob_get_clean();
        if ($autoloadOutput !== '') {
            error_log(sprintf('[%s] Optional vendor autoload output suppressed', date('c')));
        }
    } catch (\Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        error_log(sprintf('[%s] Optional vendor autoload skipped: %s', date('c'), $e->getMessage()));
        if (!headers_sent()) {
            http_response_code(200);
        }
    } finally {
        ini_set('display_errors', (string)$displayErrorsPrev);
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/Support/dietary-mode.php';
require_once __DIR__ . '/Support/business-contact-links.php';

if (!function_exists('is_https_request')) {
    function is_https_request(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwardedProto === 'https';
    }
}

if (!function_exists('resolve_session_cookie_domain')) {
    function resolve_session_cookie_domain(): string
    {
        $configured = trim((string)Env::get('SESSION_COOKIE_DOMAIN', ''));
        if ($configured !== '') {
            return $configured;
        }

        $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/:\\d+$/', '', $host) ?? $host;
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return '';
        }

        if (substr_count($host, '.') < 1 || strpos($host, 'localhost') !== false) {
            return '';
        }

        if (strpos($host, 'www.') === 0) {
            return '.' . substr($host, 4);
        }

        return '';
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = __DIR__ . '/../storage/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }
    @session_save_path($sessionDir);

    $secureByEnv = Env::get('SESSION_COOKIE_SECURE', Env::get('APP_ENV', 'development') === 'production' ? '1' : '0') === '1';
    $secure = $secureByEnv || is_https_request();
    $sameSite = Env::get('SESSION_COOKIE_SAMESITE', 'Lax') ?: 'Lax';
    $lifetime = (int)(Env::get('SESSION_COOKIE_LIFETIME', '86400') ?: '86400');
    $cookieDomain = resolve_session_cookie_domain();

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    } else {
        session_set_cookie_params($lifetime, '/; samesite=' . $sameSite, $cookieDomain, $secure, true);
    }

    session_name('cakeouflage_sid');

    session_start();

}

date_default_timezone_set('Asia/Kolkata');

Csrf::token();

if (!function_exists('product_image_url')) {
    function product_image_url($path, $categorySlug = null)
    {
        return \App\Services\ProductImageService::resolve((string)$path, $categorySlug !== null ? (string)$categorySlug : null);
    }
}

if (!function_exists('product_image_placeholder')) {
    function product_image_placeholder($categorySlug = null)
    {
        return \App\Services\ProductImageService::placeholderForCategory($categorySlug !== null ? (string)$categorySlug : null);
    }
}

if (!function_exists('media_url')) {
    function media_url($path, $variant = 'optimized', $categorySlug = null)
    {
        return \App\Services\MediaUrlService::resolve(
            (string)$path,
            (string)$variant,
            $categorySlug !== null ? (string)$categorySlug : null
        );
    }
}