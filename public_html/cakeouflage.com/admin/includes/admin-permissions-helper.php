<?php

function admin_has_permission(mysqli $conn, int $adminId, string $permissionKey): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM admin_permissions WHERE admin_id = ? AND permission_key = ? LIMIT 1');
    $stmt->bind_param('is', $adminId, $permissionKey);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result && $result->num_rows > 0;
}

function admin_can_refund_orders(mysqli $conn, int $adminId): bool
{
    return admin_has_permission($conn, $adminId, 'order_refund');
}

function admin_can_reject_orders(mysqli $conn, int $adminId): bool
{
    return admin_has_permission($conn, $adminId, 'order_reject');
}

function admin_can_edit_orders(mysqli $conn, int $adminId): bool
{
    return admin_has_permission($conn, $adminId, 'order_edit');
}
