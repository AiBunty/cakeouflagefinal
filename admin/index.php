<?php
declare(strict_types=1);

session_name('cakeouflage_sid');
session_start();

if (isset($_SESSION['admin']) || isset($_SESSION['admin_id'])) {
	header('Location: dashboard.php');
	exit;
}

header('Location: login.php');
exit;
