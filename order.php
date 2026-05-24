<?php

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

$command = trim((string)($argv[1] ?? ''));

if ($command === '') {
    fwrite(STDERR, "Usage: php order.php order:repair-states [--dry-run] [--apply] [--limit=5000]\n");
    exit(1);
}

if ($command === 'order:repair-states') {
    require APP_ROOT . '/scripts/repair-order-state.php';
    exit(0);
}

fwrite(STDERR, "Unknown order command: {$command}\n");
exit(1);
