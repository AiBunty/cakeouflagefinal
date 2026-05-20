<?php

function fetch_crm_report_users($conn, $q = '', $limit = 200, $offset = 0)
{
    $sql = 'SELECT u.id, u.full_name, u.email, u.phone, u.created_at, u.last_login_at, '
        . 'COUNT(o.id) AS order_count, '
        . 'SUM(CASE WHEN o.order_status = "completed" THEN 1 ELSE 0 END) AS completed_count, '
        . 'SUM(CASE WHEN o.order_status = "pending" THEN 1 ELSE 0 END) AS pending_count '
        . 'FROM users u '
        . 'LEFT JOIN orders o ON o.user_id = u.id '
        . 'WHERE u.deleted_at IS NULL';
    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $qLike = '%' . $q . '%';
        $params = [$qLike, $qLike, $qLike];
        $types = 'sss';
    }

    $sql .= ' GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?';
    $stmt = $conn->prepare($sql);
    $limit = max(1, (int) $limit);
    $offset = max(0, (int) $offset);

    if ($params) {
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_crm_report_users_count($conn, $q = '')
{
    $sql = 'SELECT COUNT(*) AS total_rows FROM users u WHERE u.deleted_at IS NULL';
    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $qLike = '%' . $q . '%';
        $params = [$qLike, $qLike, $qLike];
        $types = 'sss';
    }

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return (int)($row['total_rows'] ?? 0);
}

function fetch_crm_report_user($conn, $userId)
{
    $stmt = $conn->prepare('SELECT id, full_name, email, phone, created_at, last_login_at FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_assoc() : null;
}

function fetch_user_orders($conn, $userId)
{
    $sql = 'SELECT o.id, o.order_number, o.grand_total, o.order_status, o.created_at, '
        . '(SELECT GROUP_CONCAT(oi.product_name_snapshot ORDER BY oi.id SEPARATOR ", ") FROM order_items oi WHERE oi.order_id = o.id) AS item_names '
        . 'FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_crm_follow_up_reminders($conn, $limit = 12)
{
    $limit = max(1, (int) $limit);
    $stmt = $conn->prepare('SELECT id, reminder_type, title, reminder_on, status, notes, created_at FROM reminders WHERE reminder_type = "follow_up" ORDER BY reminder_on DESC, id DESC LIMIT ?');
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_skipped_crm_jobs($conn, $limit = 12)
{
    $limit = max(1, (int) $limit);
    $stmt = $conn->prepare('SELECT id, job_type, status, attempts, last_error, payload_json, updated_at, created_at FROM queue_jobs WHERE job_type = "crm_trigger_push" ORDER BY updated_at DESC, id DESC LIMIT ?');
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_crm_queue_push_mode($conn)
{
    $mode = 'paused';
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) {
        return $mode;
    }

    $key = 'crm_queue_push_mode';
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $value = trim((string) ($row['setting_value'] ?? ''));
        if ($value === 'enabled') {
            $mode = 'enabled';
        }
    }

    return $mode;
}

function fetch_crm_report_summary($conn)
{
    $summary = array(
        'users' => 0,
        'orders' => 0,
        'follow_ups' => 0,
        'skipped_jobs' => 0,
    );

    $queries = array(
        'users' => 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL',
        'orders' => 'SELECT COUNT(*) FROM orders',
        'follow_ups' => 'SELECT COUNT(*) FROM reminders WHERE reminder_type = "follow_up"',
        'skipped_jobs' => 'SELECT COUNT(*) FROM queue_jobs WHERE job_type = "crm_trigger_push"',
    );

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        if ($result) {
            $summary[$key] = (int) $result->fetch_row()[0];
        }
    }

    return $summary;
}

function export_users_excel($users)
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="crm_users_export.xls"');
    echo "Name\tPhone\tEmail\tOrders\tCompleted\tPending\n";
    foreach ($users as $u) {
        echo $u['full_name'] . "\t" . $u['phone'] . "\t" . $u['email'] . "\t" . (int) ($u['order_count'] ?? 0) . "\t" . (int) ($u['completed_count'] ?? 0) . "\t" . (int) ($u['pending_count'] ?? 0) . "\n";
    }
    exit;
}
