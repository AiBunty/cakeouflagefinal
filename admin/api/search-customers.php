<?php
require_once __DIR__ . '/../includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function normalize_phone(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

$q = trim((string)($_GET['q'] ?? ''));
$limit = min(max((int)($_GET['limit'] ?? 8), 1), 20);

if ($q === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Search query required'], JSON_UNESCAPED_SLASHES);
    exit;
}

$normalized = normalize_phone($q);
$searchTerm = '%' . $q . '%';
$phoneSearchTerm = '%' . $normalized . '%';

$sql = '
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        COUNT(o.id) AS total_orders,
        MAX(o.created_at) AS last_order_at,
        (
            SELECT o2.order_number
            FROM orders o2
            WHERE o2.user_id = u.id
            ORDER BY o2.created_at DESC, o2.id DESC
            LIMIT 1
        ) AS last_order_number
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE u.deleted_at IS NULL
      AND (
        u.phone LIKE ?
        OR u.full_name LIKE ?
        OR u.email LIKE ?
        OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(u.phone, " ", ""), "-", ""), "+", ""), "(", ""), ")", "") LIKE ?
        OR u.phone_e164 LIKE ?
      )
    GROUP BY u.id
    ORDER BY
      CASE
        WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(u.phone, " ", ""), "-", ""), "+", ""), "(", ""), ")", "") = ? THEN 0
        WHEN u.phone_e164 LIKE ? THEN 1
        ELSE 2
      END,
      last_order_at DESC,
      u.id DESC
    LIMIT ?
';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query preparation failed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$exactPhone = $normalized;
// Params: phone LIKE, name LIKE, email LIKE, stripped-phone LIKE, phone_e164 LIKE,
//         ORDER CASE exact-phone, ORDER CASE phone_e164 LIKE, LIMIT
$stmt->bind_param('sssssssi', $searchTerm, $searchTerm, $searchTerm, $phoneSearchTerm, $phoneSearchTerm, $exactPhone, $phoneSearchTerm, $limit);
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
$exactMatchCustomer = null;
while ($result && ($row = $result->fetch_assoc())) {
    $phoneRaw = (string)($row['phone'] ?? '');
    $normalizedPhone = normalize_phone($phoneRaw);

    $customer = [
        'id' => (int)$row['id'],
        'full_name' => (string)($row['full_name'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'phone' => $phoneRaw,
        'normalized_phone' => $normalizedPhone,
        'total_orders' => (int)($row['total_orders'] ?? 0),
        'last_order_at' => (string)($row['last_order_at'] ?? ''),
        'last_order_number' => (string)($row['last_order_number'] ?? ''),
    ];

    if ($exactMatchCustomer === null && $exactPhone !== '' && $normalizedPhone === $exactPhone) {
        $exactMatchCustomer = $customer;
    }

    $customers[] = $customer;
}

echo json_encode([
    'success' => true,
    'query' => $q,
    'normalized_query' => $normalized,
    'customers' => $customers,
    'count' => count($customers),
    'exact_match' => $exactMatchCustomer !== null,
    'exact_customer' => $exactMatchCustomer,
], JSON_UNESCAPED_SLASHES);
