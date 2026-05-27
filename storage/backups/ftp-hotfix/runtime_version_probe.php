<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=UTF-8');
echo 'PHP_VERSION=' . PHP_VERSION . "\n";
echo 'PHP_SAPI=' . PHP_SAPI . "\n";
echo 'LOADED_INI=' . (php_ini_loaded_file() ?: '') . "\n";
echo 'SCANNED_INI=' . (php_ini_scanned_files() ?: '') . "\n";
