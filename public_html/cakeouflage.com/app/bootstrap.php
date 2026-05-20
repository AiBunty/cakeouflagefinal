<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;

require_once __DIR__ . '/Core/Env.php';

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

define('APP_DEBUG', Env::get('APP_DEBUG', Env::get('APP_ENV', 'production') !== 'production' ? '1' : '0') === '1');

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
        header('Content-Type: text/plain; charset=UTF-8');
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

if (session_status() === PHP_SESSION_NONE) {
    $secure = Env::get('SESSION_COOKIE_SECURE', Env::get('APP_ENV', 'development') === 'production' ? '1' : '0') === '1';
    $sameSite = Env::get('SESSION_COOKIE_SAMESITE', 'Lax') ?: 'Lax';
    $lifetime = (int)(Env::get('SESSION_COOKIE_LIFETIME', '7200') ?: '7200');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    } else {
        session_set_cookie_params($lifetime, '/; samesite=' . $sameSite, '', $secure, true);
    }

    session_name('cakeouflage_sid');

    // 🔥 ADD THIS BLOCK HERE (IMPORTANT)

session_start();

    // 🔥 ADD THIS ALSO
    //setcookie('cakeouflage_sid', session_id(), time() + (86400 * 30), "/");

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