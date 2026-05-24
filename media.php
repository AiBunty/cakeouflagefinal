<?php

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

$command = trim((string)($argv[1] ?? ''));

if ($command === '') {
    fwrite(STDERR, "Usage: php media.php media:migrate-optimize [--dry-run] [--limit=2000] [--admin-id=0]\n");
    exit(1);
}

if ($command === 'media:migrate-optimize') {
    require APP_ROOT . '/scripts/media-migrate-optimize.php';
    exit(0);
}

fwrite(STDERR, "Unknown media command: {$command}\n");
exit(1);
