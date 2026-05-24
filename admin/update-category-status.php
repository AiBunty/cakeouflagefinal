<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : -1;

if ($id <= 0 || ($status !== 0 && $status !== 1)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid category id or status'
    ]);
    exit;
}

$stmt = $conn->prepare('UPDATE categories SET is_active = ? WHERE id = ? AND deleted_at IS NULL');
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare update query'
    ]);
    exit;
}

$stmt->bind_param('ii', $status, $id);
$ok = $stmt->execute();

if (!$ok) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update category status'
    ]);
    $stmt->close();
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Category status updated'
]);

$stmt->close();
