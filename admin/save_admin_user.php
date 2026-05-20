<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

if (!admin_is_super_admin()) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_users.php');
    exit;
}

// ── Helper: redirect with error stored in session ──────────────────────
function redirect_with_error(string $msg, int $editId = 0): void
{
    $_SESSION['au_error'] = $msg;
    $back = $editId > 0 ? 'admin_users.php?edit=' . $editId : 'admin_users.php';
    header('Location: ' . $back);
    exit;
}

// ── Toggle active/inactive ─────────────────────────────────────────────
if (isset($_POST['toggle_id'])) {
    $toggleId = (int) $_POST['toggle_id'];
    if ($toggleId <= 0) {
        header('Location: admin_users.php');
        exit;
    }
    $stmt = $conn->prepare("UPDATE admins SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ? AND role <> 'super_admin'");
    if (!$stmt) {
        redirect_with_error('DB error: ' . $conn->error);
    }
    $stmt->bind_param('i', $toggleId);
    if (!$stmt->execute()) {
        redirect_with_error('Could not toggle user: ' . $stmt->error);
    }
    $stmt->close();
    header('Location: admin_users.php?status=toggled');
    exit;
}

// ── Delete user ───────────────────────────────────────────────────────
if (isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];
    if ($deleteId <= 0) {
        header('Location: admin_users.php');
        exit;
    }
    // Permissions deleted via FK cascade (admin_permissions.admin_id FK)
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ? AND role <> 'super_admin'");
    if (!$stmt) {
        redirect_with_error('DB error: ' . $conn->error);
    }
    $stmt->bind_param('i', $deleteId);
    if (!$stmt->execute()) {
        redirect_with_error('Could not delete user: ' . $stmt->error);
    }
    $stmt->close();
    header('Location: admin_users.php?status=deleted');
    exit;
}

// ── Create / Edit ──────────────────────────────────────────────────────
$adminId          = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;
$isEdit           = $adminId > 0;
$fullName         = trim($_POST['full_name'] ?? '');
$email            = strtolower(trim($_POST['email'] ?? ''));
$password         = $_POST['password'] ?? '';
$departmentLabel  = trim($_POST['department_label'] ?? '');
$isActive         = isset($_POST['is_active']) ? 1 : 0;
$rawPermissions   = (isset($_POST['permissions']) && is_array($_POST['permissions'])) ? $_POST['permissions'] : array();

// Validate required fields
if ($fullName === '') {
    redirect_with_error('Full name is required.', $adminId);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_error('A valid email address is required.', $adminId);
}
if (!$isEdit && $password === '') {
    redirect_with_error('Password is required for new sub users.', $adminId);
}
if ($password !== '' && strlen($password) < 6) {
    redirect_with_error('Password must be at least 6 characters.', $adminId);
}

// Sanitize department label
$allowedDepartmentLabels = array_keys(admin_label_definitions());
if (!in_array($departmentLabel, $allowedDepartmentLabels, true)) {
    $departmentLabel = '';
}

// Sanitize permissions — never grant sub_users via this form
$allowedPermissionKeys  = array_keys(admin_permission_definitions());
$sanitizedPermissions   = array();
foreach ($rawPermissions as $permissionKey) {
    $permissionKey = (string) $permissionKey;
    if ($permissionKey === 'sub_users') {
        continue;
    }
    if (in_array($permissionKey, $allowedPermissionKeys, true)) {
        $sanitizedPermissions[] = $permissionKey;
    }
}
$sanitizedPermissions = array_values(array_unique($sanitizedPermissions));

// ── Database write ─────────────────────────────────────────────────────
if ($isEdit) {
    // Verify the target row exists and is not super_admin
    $chk = $conn->prepare("SELECT id FROM admins WHERE id = ? AND role <> 'super_admin' LIMIT 1");
    if (!$chk) {
        redirect_with_error('DB error: ' . $conn->error, $adminId);
    }
    $chk->bind_param('i', $adminId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        redirect_with_error('Sub user not found.', $adminId);
    }
    $chk->close();

    if ($password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET full_name = ?, email = ?, password_hash = ?, department_label = ?, is_active = ? WHERE id = ? AND role <> 'super_admin'");
        if (!$stmt) {
            redirect_with_error('DB error: ' . $conn->error, $adminId);
        }
        $stmt->bind_param('ssssii', $fullName, $email, $passwordHash, $departmentLabel, $isActive, $adminId);
    } else {
        $stmt = $conn->prepare("UPDATE admins SET full_name = ?, email = ?, department_label = ?, is_active = ? WHERE id = ? AND role <> 'super_admin'");
        if (!$stmt) {
            redirect_with_error('DB error: ' . $conn->error, $adminId);
        }
        $stmt->bind_param('sssii', $fullName, $email, $departmentLabel, $isActive, $adminId);
    }

    if (!$stmt->execute()) {
        $errMsg = $stmt->error;
        $stmt->close();
        if (strpos($errMsg, 'Duplicate entry') !== false) {
            redirect_with_error('That email address is already in use by another admin.', $adminId);
        }
        redirect_with_error('Could not update sub user: ' . $errMsg, $adminId);
    }
    $stmt->close();

} else {
    // INSERT
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (full_name, email, password_hash, role, department_label, is_active) VALUES (?, ?, ?, 'admin', ?, ?)");
    if (!$stmt) {
        redirect_with_error('DB error (prepare): ' . $conn->error);
    }
    $stmt->bind_param('ssssi', $fullName, $email, $passwordHash, $departmentLabel, $isActive);

    if (!$stmt->execute()) {
        $errMsg = $stmt->error;
        $stmt->close();
        if (strpos($errMsg, 'Duplicate entry') !== false) {
            redirect_with_error('An admin account with that email already exists.');
        }
        redirect_with_error('Could not create sub user: ' . $errMsg);
    }

    $adminId = (int) $conn->insert_id;
    $stmt->close();

    if ($adminId <= 0) {
        redirect_with_error('Insert succeeded but no ID returned — contact support.');
    }
}

// ── Sync permissions ───────────────────────────────────────────────────
$delStmt = $conn->prepare("DELETE FROM admin_permissions WHERE admin_id = ?");
if ($delStmt) {
    $delStmt->bind_param('i', $adminId);
    $delStmt->execute();
    $delStmt->close();
}

if ($sanitizedPermissions) {
    $insStmt = $conn->prepare("INSERT IGNORE INTO admin_permissions (admin_id, permission_key) VALUES (?, ?)");
    if ($insStmt) {
        foreach ($sanitizedPermissions as $permissionKey) {
            $insStmt->bind_param('is', $adminId, $permissionKey);
            $insStmt->execute();
        }
        $insStmt->close();
    }
}

// Refresh session permissions if editing own account
if (isset($_SESSION['admin']) && (int) $_SESSION['admin'] === $adminId) {
    admin_load_permissions($conn, $adminId);
}

header('Location: admin_users.php?status=' . ($isEdit ? 'updated' : 'saved'));
exit;
