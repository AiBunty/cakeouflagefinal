<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

$query = $_GET;
$query['view'] = 'collection';

$target = 'sales_register.php';
$q = http_build_query($query);
if ($q !== '') {
    $target .= '?' . $q;
}

header('Location: ' . $target);
exit;
