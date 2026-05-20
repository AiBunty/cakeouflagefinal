<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$target = 'categories.php';
if ($id > 0) {
    $target .= '?focus=' . $id;
}

header('Location: ' . $target);
exit;
