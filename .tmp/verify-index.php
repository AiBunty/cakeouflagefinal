<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

// Normalize deployed subdirectory base path from env or script location.
$configuredBasePath = trim((string) App\Core\Env::get('APP_BASE_PATH', ''));
$basePath = '';

if ($configuredBasePath !== '') {
    $basePath = '/' . trim($configuredBasePath, '/');
} elseif (isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if ($scriptDir !== '/' && $scriptDir !== '.') {
        $basePath = rtrim($scriptDir, '/');
    }
}

if ($basePath !== '' && isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], $basePath) === 0) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($basePath)) ?: '/';
}

// Backward compatibility for legacy deployed links.
$legacyBasePath = '/Cakeouflage-E-commerce';
if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], $legacyBasePath . '/') === 0) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($legacyBasePath)) ?: '/';
}

// ✅ CLI support
if (PHP_SAPI === 'cli' && isset($argv[1]) && is_string($argv[1]) && strpos($argv[1], '/') === 0) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $argv[1];

    $query = parse_url($argv[1], PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        $_SERVER['QUERY_STRING'] = $query;
        parse_str($query, $_GET);
    }
}

// ✅ Run app once
$app = new App\Core\App();
$app->run();