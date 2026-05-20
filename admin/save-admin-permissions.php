<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-admins.php');
    exit;
}

$adminId = (int)($_POST['admin_id'] ?? 0);
$permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : array();

if ($adminId <= 0) {
    header('Location: manage-admins.php?status=error&message=' . rawurlencode('Invalid admin ID'));
    exit;
}

try {
    $conn->begin_transaction();
    
    $conn->query('DELETE FROM admin_permissions WHERE admin_id = ' . $adminId);
    
    foreach ($permissions as $permKey) {
        $permKey = trim((string)$permKey);
        if ($permKey === '') continue;
        
        $stmt = $conn->prepare('INSERT INTO admin_permissions (admin_id, permission_key) VALUES (?, ?)');
        $stmt->bind_param('is', $adminId, $permKey);
        $stmt->execute();
    }
    
    $conn->commit();
    
    header('Location: manage-admins.php?edit=' . $adminId . '&status=success');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    header('Location: manage-admins.php?status=error&message=' . rawurlencode($e->getMessage()));
    exit;
}
