<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require APP_ROOT . '/app/bootstrap.php';
require __DIR__ . '/RepairOrderStateCommand.php';

$command = new RepairOrderStateCommand();
$exit = $command->run($argv);
exit($exit);
