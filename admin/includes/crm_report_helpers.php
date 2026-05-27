<?php

require_once __DIR__ . '/../../vendor/autoload.php';

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

    $sql .= ' GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at, u.last_login_at '
        . 'ORDER BY u.created_at DESC LIMIT ? OFFSET ?';
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
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header row
    $headers = ['Name', 'Phone', 'Email', 'Orders', 'Completed', 'Pending'];
    foreach ($headers as $colIdx => $label) {
        $sheet->setCellValueByColumnAndRow($colIdx + 1, 1, $label);
    }
    $sheet->getStyle('A1:F1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4F81BD'],
        ],
    ]);
    $sheet->freezePane('A2');

    // Column widths
    foreach ([1 => 25, 2 => 16, 3 => 32, 4 => 10, 5 => 12, 6 => 10] as $col => $width) {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->getColumnDimension($letter)->setWidth($width);
    }

    // Data rows
    $rowNum = 2;
    foreach ($users as $u) {
        $sheet->setCellValueByColumnAndRow(1, $rowNum, (string)($u['full_name'] ?? ''));
        $sheet->setCellValueByColumnAndRow(2, $rowNum, (string)($u['phone'] ?? ''));
        $sheet->setCellValueByColumnAndRow(3, $rowNum, (string)($u['email'] ?? ''));
        $sheet->setCellValueByColumnAndRow(4, $rowNum, (int)($u['order_count'] ?? 0));
        $sheet->setCellValueByColumnAndRow(5, $rowNum, (int)($u['completed_count'] ?? 0));
        $sheet->setCellValueByColumnAndRow(6, $rowNum, (int)($u['pending_count'] ?? 0));
        $rowNum++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="crm_users_export.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    $spreadsheet->disconnectWorksheets();
    exit;
}

function crm_allowed_customer_tags(): array
{
    return array(
        'VIP',
        'Repeat Buyer',
        'High Refund Risk',
        'Corporate',
        'Wedding Client',
        'Bulk Buyer',
    );
}

function crm_normalize_customer_tag(string $tag): string
{
    $tag = trim($tag);
    foreach (crm_allowed_customer_tags() as $allowed) {
        if (strcasecmp($allowed, $tag) === 0) {
            return $allowed;
        }
    }

    return '';
}

function crm_ensure_customer_tag_table(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS crm_customer_tags (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            tag_key VARCHAR(60) NOT NULL,
            tagged_by_admin_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_crm_customer_tag (user_id, tag_key),
            INDEX idx_crm_customer_tags_user (user_id),
            CONSTRAINT fk_crm_customer_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_crm_customer_tags_admin FOREIGN KEY (tagged_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $ensured = true;
}

function crm_build_customer_search_sql(string $q): array
{
    $where = '';
    $params = array();
    $types = '';

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where .= ' AND (
            u.full_name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
            OR EXISTS (
                SELECT 1
                FROM orders so
                LEFT JOIN order_items soi ON soi.order_id = so.id
                WHERE so.user_id = u.id
                  AND (so.order_number LIKE ? OR soi.product_name_snapshot LIKE ?)
            )
        )';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sssss';
    }

    return array($where, $types, $params);
}

function crm_build_customer_segment_sql(string $segment): string
{
    switch ($segment) {
        case 'repeat_customers':
            return ' AND COALESCE(agg.order_count, 0) >= 2';
        case 'refunded_users':
            return ' AND (COALESCE(agg.refund_total, 0) > 0 OR COALESCE(agg.refund_orders, 0) > 0)';
        case 'high_spenders':
            return ' AND COALESCE(agg.total_spend, 0) >= 5000';
        case 'inactive_customers':
            return ' AND (agg.last_order_at IS NULL OR agg.last_order_at < DATE_SUB(NOW(), INTERVAL 90 DAY))';
        case 'pending_payments':
            return ' AND COALESCE(agg.pending_payment_orders, 0) > 0';
        case 'recent_buyers':
            return ' AND agg.last_order_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        case 'birthday_event_buyers':
            return ' AND EXISTS (
                SELECT 1
                FROM orders bo
                INNER JOIN order_items boi ON boi.order_id = bo.id
                WHERE bo.user_id = u.id
                  AND (
                    boi.product_name_snapshot LIKE "%birthday%"
                    OR boi.product_name_snapshot LIKE "%anniversary%"
                    OR boi.product_name_snapshot LIKE "%wedding%"
                    OR boi.product_name_snapshot LIKE "%event%"
                    OR boi.product_name_snapshot LIKE "%baby shower%"
                  )
            )';
        default:
            return '';
    }
}

function crm_customer_agg_subquery(): string
{
    return '
        SELECT
            o.user_id,
            COUNT(*) AS order_count,
            COALESCE(SUM(o.grand_total), 0) AS total_spend,
            COALESCE(AVG(o.grand_total), 0) AS avg_order_value,
            SUM(CASE WHEN o.order_status IN ("pending", "confirmed", "preparing", "out_for_delivery", "pending_payment", "payment_under_review") THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN o.payment_status IN ("pending", "under_review", "failed", "rejected") THEN 1 ELSE 0 END) AS pending_payment_orders,
            SUM(CASE WHEN COALESCE(o.refund_amount, 0) > 0 OR o.payment_status IN ("refunded", "partially_refunded", "refund_pending") OR o.order_status IN ("refund_requested", "partially_refunded", "fully_refunded", "refunded") THEN 1 ELSE 0 END) AS refund_orders,
            COALESCE(SUM(COALESCE(o.refund_amount, 0)), 0) AS refund_total,
            MAX(o.created_at) AS last_order_at
        FROM orders o
        WHERE o.user_id IS NOT NULL
        GROUP BY o.user_id
    ';
}

function fetch_crm_customer_intelligence_count(mysqli $conn, string $q = '', string $segment = 'all'): int
{
    list($searchSql, $types, $params) = crm_build_customer_search_sql($q);
    $segmentSql = crm_build_customer_segment_sql($segment);

    $sql = '
        SELECT COUNT(*) AS total_rows
        FROM users u
        LEFT JOIN (' . crm_customer_agg_subquery() . ') agg ON agg.user_id = u.id
        WHERE u.deleted_at IS NULL
        ' . $searchSql . '
        ' . $segmentSql;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return (int)($row['total_rows'] ?? 0);
}

function fetch_crm_customer_intelligence_rows(mysqli $conn, string $q = '', string $segment = 'all', int $limit = 20, int $offset = 0): array
{
    crm_ensure_customer_tag_table($conn);

    list($searchSql, $types, $params) = crm_build_customer_search_sql($q);
    $segmentSql = crm_build_customer_segment_sql($segment);

    $sql = '
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.created_at,
            u.last_login_at,
            COALESCE(agg.order_count, 0) AS order_count,
            COALESCE(agg.total_spend, 0) AS total_spend,
            COALESCE(agg.avg_order_value, 0) AS avg_order_value,
            COALESCE(agg.pending_orders, 0) AS pending_orders,
            COALESCE(agg.pending_payment_orders, 0) AS pending_payment_orders,
            COALESCE(agg.refund_orders, 0) AS refund_orders,
            COALESCE(agg.refund_total, 0) AS refund_total,
            agg.last_order_at,
            GROUP_CONCAT(DISTINCT ct.tag_key ORDER BY ct.tag_key SEPARATOR "||") AS tags_flat,
            CASE
                WHEN COALESCE(agg.total_spend, 0) >= 15000 OR COALESCE(agg.order_count, 0) >= 8 THEN "VIP"
                WHEN COALESCE(agg.order_count, 0) >= 4 THEN "Repeat"
                WHEN COALESCE(agg.order_count, 0) >= 1 THEN "Active"
                ELSE "New"
            END AS customer_badge
        FROM users u
        LEFT JOIN (' . crm_customer_agg_subquery() . ') agg ON agg.user_id = u.id
        LEFT JOIN crm_customer_tags ct ON ct.user_id = u.id
        WHERE u.deleted_at IS NULL
        ' . $searchSql . '
        ' . $segmentSql . '
        GROUP BY
            u.id, u.full_name, u.email, u.phone, u.created_at, u.last_login_at,
            agg.order_count, agg.total_spend, agg.avg_order_value, agg.pending_orders,
            agg.pending_payment_orders, agg.refund_orders, agg.refund_total, agg.last_order_at
        ORDER BY COALESCE(agg.last_order_at, u.created_at) DESC
        LIMIT ? OFFSET ?';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return array();
    }

    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $typesWithPager = $types . 'ii';
    $paramsWithPager = $params;
    $paramsWithPager[] = $limit;
    $paramsWithPager[] = $offset;
    $stmt->bind_param($typesWithPager, ...$paramsWithPager);

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();

    foreach ($rows as &$row) {
        $tags = trim((string)($row['tags_flat'] ?? ''));
        $row['tags'] = $tags === '' ? array() : explode('||', $tags);
        unset($row['tags_flat']);
    }

    return $rows;
}

function fetch_crm_order_intelligence_summary_header(mysqli $conn): array
{
    $summary = array(
        'total_customers' => 0,
        'repeat_buyers' => 0,
        'revenue_generated' => 0.0,
        'pending_follow_ups' => 0,
        'refund_customers' => 0,
        'active_today' => 0,
    );

    $queries = array(
        'total_customers' => 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL',
        'repeat_buyers' => 'SELECT COUNT(*) FROM (SELECT user_id FROM orders WHERE user_id IS NOT NULL GROUP BY user_id HAVING COUNT(*) >= 2) t',
        'revenue_generated' => 'SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE user_id IS NOT NULL',
        'pending_follow_ups' => 'SELECT COUNT(*) FROM reminders WHERE reminder_type = "follow_up" AND status = "pending"',
        'refund_customers' => 'SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL AND (COALESCE(refund_amount, 0) > 0 OR payment_status IN ("refunded", "partially_refunded", "refund_pending"))',
        'active_today' => 'SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL AND DATE(created_at) = CURDATE()',
    );

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        if (!$result) {
            continue;
        }
        $value = $result->fetch_row()[0] ?? 0;
        if ($key === 'revenue_generated') {
            $summary[$key] = (float)$value;
        } else {
            $summary[$key] = (int)$value;
        }
    }

    return $summary;
}

function crm_fetch_customer_tags(mysqli $conn, int $userId): array
{
    crm_ensure_customer_tag_table($conn);
    if ($userId <= 0) {
        return array();
    }

    $stmt = $conn->prepare('SELECT tag_key FROM crm_customer_tags WHERE user_id = ? ORDER BY tag_key ASC');
    if (!$stmt) {
        return array();
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) {
        return array();
    }

    $tags = array();
    while ($row = $res->fetch_assoc()) {
        $tag = trim((string)($row['tag_key'] ?? ''));
        if ($tag !== '') {
            $tags[] = $tag;
        }
    }

    return $tags;
}

function crm_toggle_customer_tag(mysqli $conn, int $userId, string $tagKey, int $adminId): array
{
    $normalized = crm_normalize_customer_tag($tagKey);
    if ($userId <= 0 || $normalized === '') {
        return array('success' => false, 'message' => 'Invalid tag request');
    }

    crm_ensure_customer_tag_table($conn);

    $check = $conn->prepare('SELECT id FROM crm_customer_tags WHERE user_id = ? AND tag_key = ? LIMIT 1');
    if (!$check) {
        return array('success' => false, 'message' => 'Unable to check tag state');
    }

    $check->bind_param('is', $userId, $normalized);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();

    if ($existing) {
        $delete = $conn->prepare('DELETE FROM crm_customer_tags WHERE id = ? LIMIT 1');
        if (!$delete) {
            return array('success' => false, 'message' => 'Unable to remove tag');
        }
        $id = (int)$existing['id'];
        $delete->bind_param('i', $id);
        $delete->execute();
        return array('success' => true, 'message' => 'Tag removed', 'active' => false);
    }

    $insert = $conn->prepare('INSERT INTO crm_customer_tags (user_id, tag_key, tagged_by_admin_id) VALUES (?, ?, ?)');
    if (!$insert) {
        return array('success' => false, 'message' => 'Unable to assign tag');
    }
    $insert->bind_param('isi', $userId, $normalized, $adminId);
    $insert->execute();

    return array('success' => true, 'message' => 'Tag assigned', 'active' => true);
}

function crm_create_follow_up(mysqli $conn, int $userId, string $title, string $notes, string $whenIso, int $adminId): array
{
    if ($userId <= 0 || trim($title) === '' || trim($whenIso) === '') {
        return array('success' => false, 'message' => 'Title and follow-up date are required');
    }

    $when = date('Y-m-d H:i:s', strtotime($whenIso));
    if ($when === false || $when === '1970-01-01 00:00:00') {
        return array('success' => false, 'message' => 'Invalid follow-up date');
    }

    $stmt = $conn->prepare('INSERT INTO reminders (user_id, reminder_type, title, reminder_on, status, notes, created_by_admin_id) VALUES (?, "follow_up", ?, ?, "pending", ?, ?)');
    if (!$stmt) {
        return array('success' => false, 'message' => 'Unable to schedule follow-up');
    }

    $safeNotes = trim($notes);
    $stmt->bind_param('isssi', $userId, $title, $when, $safeNotes, $adminId);
    $stmt->execute();

    return array('success' => true, 'message' => 'Follow-up scheduled');
}

function crm_add_internal_note(mysqli $conn, int $userId, string $note): array
{
    $note = trim($note);
    if ($userId <= 0 || $note === '') {
        return array('success' => false, 'message' => 'Note text is required');
    }

    $userStmt = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    if (!$userStmt) {
        return array('success' => false, 'message' => 'Unable to load user');
    }
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    if (!$user) {
        return array('success' => false, 'message' => 'User not found');
    }

    $recipient = (string)($user['email'] ?? 'internal');
    $payload = json_encode(array('note' => $note), JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare('INSERT INTO communication_logs (user_id, channel, event_key, recipient, status, payload_json) VALUES (?, "internal", "crm_note", ?, "sent", ?)');
    if (!$stmt) {
        return array('success' => false, 'message' => 'Unable to save note');
    }
    $stmt->bind_param('iss', $userId, $recipient, $payload);
    $stmt->execute();

    return array('success' => true, 'message' => 'Note saved');
}

function fetch_crm_customer_timeline_payload(mysqli $conn, int $userId, int $page = 1, int $perPage = 5): ?array
{
    crm_ensure_customer_tag_table($conn);

    if ($userId <= 0) {
        return null;
    }

    $page = max(1, $page);
    $perPage = max(1, min(20, $perPage));
    $offset = ($page - 1) * $perPage;

    $sql = '
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.phone,
            COALESCE(agg.order_count, 0) AS order_count,
            COALESCE(agg.total_spend, 0) AS total_spend,
            COALESCE(agg.avg_order_value, 0) AS avg_order_value,
            COALESCE(agg.pending_orders, 0) AS pending_orders,
            COALESCE(agg.pending_payment_orders, 0) AS pending_payment_orders,
            COALESCE(agg.refund_orders, 0) AS refund_orders,
            COALESCE(agg.refund_total, 0) AS refund_total,
            agg.last_order_at
        FROM users u
        LEFT JOIN (' . crm_customer_agg_subquery() . ') agg ON agg.user_id = u.id
        WHERE u.deleted_at IS NULL AND u.id = ?
        LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    if (!$customer) {
        return null;
    }

    $countStmt = $conn->prepare('SELECT COUNT(*) AS total_orders FROM orders WHERE user_id = ?');
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $totalOrders = (int)($countRow['total_orders'] ?? 0);
    $totalPages = max(1, (int)ceil($totalOrders / $perPage));

    $ordersStmt = $conn->prepare(
        'SELECT
            o.id,
            o.order_number,
            o.created_at,
            o.grand_total,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.fulfilment_mode,
            COALESCE(o.refund_amount, 0) AS refund_amount,
            COALESCE((SELECT GROUP_CONCAT(oi.product_name_snapshot ORDER BY oi.id SEPARATOR " || ") FROM order_items oi WHERE oi.order_id = o.id), "") AS item_names
         FROM orders o
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $ordersStmt->bind_param('iii', $userId, $perPage, $offset);
    $ordersStmt->execute();
    $orders = $ordersStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $favoriteCategory = '—';
    $favoriteStmt = $conn->prepare(
        'SELECT c.name, COUNT(*) AS cnt
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
         LEFT JOIN products p ON p.id = oi.product_id
         LEFT JOIN categories c ON c.id = COALESCE(p.child_category_id, p.subcategory_id, p.collection_category_id)
         WHERE o.user_id = ?
         GROUP BY c.id, c.name
         ORDER BY cnt DESC
         LIMIT 1'
    );
    if ($favoriteStmt) {
        $favoriteStmt->bind_param('i', $userId);
        $favoriteStmt->execute();
        $favoriteRow = $favoriteStmt->get_result()->fetch_assoc();
        if ($favoriteRow && trim((string)($favoriteRow['name'] ?? '')) !== '') {
            $favoriteCategory = (string)$favoriteRow['name'];
        }
    }

    $followUpsStmt = $conn->prepare('SELECT id, title, reminder_on, status, notes, created_at FROM reminders WHERE user_id = ? AND reminder_type = "follow_up" ORDER BY reminder_on DESC, id DESC LIMIT 8');
    $followUpsStmt->bind_param('i', $userId);
    $followUpsStmt->execute();
    $followUps = $followUpsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $commStmt = $conn->prepare('SELECT id, channel, event_key, recipient, status, order_id, error_message, created_at FROM communication_logs WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10');
    $commStmt->bind_param('i', $userId);
    $commStmt->execute();
    $communications = $commStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $paymentsStmt = $conn->prepare('SELECT id, order_number, payment_method, payment_status, grand_total, updated_at FROM orders WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 10');
    $paymentsStmt->bind_param('i', $userId);
    $paymentsStmt->execute();
    $payments = $paymentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $refundStmt = $conn->prepare('SELECT id, order_number, refund_amount, payment_status, order_status, updated_at FROM orders WHERE user_id = ? AND (COALESCE(refund_amount, 0) > 0 OR payment_status IN ("refunded", "partially_refunded", "refund_pending") OR order_status IN ("refund_requested", "partially_refunded", "fully_refunded", "refunded")) ORDER BY updated_at DESC, id DESC LIMIT 10');
    $refundStmt->bind_param('i', $userId);
    $refundStmt->execute();
    $refunds = $refundStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $tags = array();
    $tagsStmt = $conn->prepare('SELECT tag_key FROM crm_customer_tags WHERE user_id = ? ORDER BY tag_key ASC');
    if ($tagsStmt) {
        $tagsStmt->bind_param('i', $userId);
        $tagsStmt->execute();
        $tagsRows = $tagsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($tagsRows as $tagRow) {
            $tag = trim((string)($tagRow['tag_key'] ?? ''));
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
    }

    $orderCount = (int)($customer['order_count'] ?? 0);
    $totalSpend = (float)($customer['total_spend'] ?? 0);
    $recentBoost = 0;
    if (!empty($customer['last_order_at'])) {
        $lastTs = strtotime((string)$customer['last_order_at']);
        if ($lastTs !== false && $lastTs >= strtotime('-30 days')) {
            $recentBoost = 20;
        }
    }
    $repeatScore = (int)min(100, round(min(45, $orderCount * 9) + min(35, $totalSpend / 500) + $recentBoost));

    return array(
        'customer' => $customer,
        'insights' => array(
            'lifetime_spend' => $totalSpend,
            'avg_order_value' => (float)($customer['avg_order_value'] ?? 0),
            'total_refunds' => (float)($customer['refund_total'] ?? 0),
            'last_ordered' => (string)($customer['last_order_at'] ?? ''),
            'favorite_category' => $favoriteCategory,
            'repeat_score' => $repeatScore,
        ),
        'orders' => $orders,
        'pagination' => array(
            'page' => $page,
            'per_page' => $perPage,
            'total_orders' => $totalOrders,
            'total_pages' => $totalPages,
        ),
        'payments' => $payments,
        'refunds' => $refunds,
        'follow_ups' => $followUps,
        'communications' => $communications,
        'tags' => $tags,
        'allowed_tags' => crm_allowed_customer_tags(),
    );
}
