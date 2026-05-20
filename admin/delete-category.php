<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
include "includes/db.php";

$id = $_GET['id'];

// check children
$check = $conn->query("SELECT COUNT(*) as total FROM categories WHERE parent_id = $id AND deleted_at IS NULL");
$row = $check->fetch_assoc();

if ($row['total'] > 0) {
    echo "<script>alert('Cannot delete: has subcategories'); window.location='categories.php';</script>";
    exit;
}

// soft delete
$conn->query("UPDATE categories SET deleted_at = NOW() WHERE id=$id");

header("Location: categories.php");