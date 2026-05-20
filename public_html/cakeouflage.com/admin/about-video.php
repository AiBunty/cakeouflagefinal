<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

header('Location: banners.php');
exit;
