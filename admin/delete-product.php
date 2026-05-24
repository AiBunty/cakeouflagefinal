<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

require_once __DIR__ . '/includes/db.php';
if ($conn->connect_error) {
    die("Connection failed");
}

$id = $_GET['id'] ?? 0;
$id = (int)$id;

// 🔥 SOFT DELETE
$conn->query("UPDATE products SET deleted_at = NOW() WHERE id = $id");

header("Location: products.php");
exit;
?>