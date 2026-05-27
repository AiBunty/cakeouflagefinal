<?php
require_once __DIR__ . '/../includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
$limit = min((int)($_GET['limit'] ?? 15), 50);
$offset = max((int)($_GET['offset'] ?? 0), 0);

if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Search query required'], JSON_UNESCAPED_SLASHES);
    exit;
}

$searchTerm = '%' . $conn->real_escape_string($q) . '%';

$query = "
    SELECT 
        p.id, 
        p.name, 
        p.sku, 
        p.short_description,
        p.base_price, 
        p.discount_price, 
        p.featured_image,
        p.topper_enabled,
        p.note_enabled,
        (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) as variant_count,
        p.stock_quantity,
        p.availability_status
    FROM products p
    WHERE (p.name LIKE ? OR p.sku LIKE ?) 
      AND p.deleted_at IS NULL
    ORDER BY 
        (p.name LIKE ?) DESC,
        p.is_featured DESC,
        p.name ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Query preparation failed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$exactMatch = $q;
$stmt->bind_param('sssii', $searchTerm, $searchTerm, $exactMatch, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$products = array();
while ($result && ($row = $result->fetch_assoc())) {
    $productId = (int)$row['id'];
    
    $variants = array();
    $variantStmt = $conn->prepare('SELECT id, variant_label, variant_name, weight_or_size, unit_type, price, discount_price, stock_quantity, is_default FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY is_default DESC, id ASC');
    $variantStmt->bind_param('i', $productId);
    $variantStmt->execute();
    $variantResult = $variantStmt->get_result();
    while ($variantResult && ($vRow = $variantResult->fetch_assoc())) {
        $resolvedName = (string)($vRow['variant_name'] ?? '');
        if ($resolvedName === '') {
            $resolvedName = (string)($vRow['variant_label'] ?? ($vRow['weight_or_size'] ?? ''));
        }
        $resolvedUnitType = (string)($vRow['unit_type'] ?? '');
        if ($resolvedUnitType === '') {
            $resolvedUnitType = 'custom';
        }
        $variants[] = array(
            'id' => (int)$vRow['id'],
            'variant_name' => $resolvedName,
            'unit_type' => $resolvedUnitType,
            'is_default' => (int)($vRow['is_default'] ?? 0) === 1,
            'label' => (string)($vRow['variant_label'] ?? $resolvedName),
            'size' => (string)($vRow['weight_or_size'] ?? $resolvedName),
            'price' => (float)$vRow['price'],
            'discount_price' => (float)($vRow['discount_price'] ?? 0),
            'stock' => (int)$vRow['stock_quantity']
        );
    }

    $products[] = array(
        'id' => $productId,
        'name' => (string)$row['name'],
        'sku' => (string)$row['sku'],
        'description' => (string)$row['short_description'],
        'base_price' => (float)$row['base_price'],
        'discount_price' => (float)($row['discount_price'] ?? 0),
        'image' => (string)($row['featured_image'] ?? ''),
        'topper_enabled' => (int)($row['topper_enabled'] ?? 0) === 1,
        'note_enabled' => (int)($row['note_enabled'] ?? 0) === 1,
        'has_variants' => (int)$row['variant_count'] > 0 ? true : false,
        'variants' => $variants,
        'stock' => (int)$row['stock_quantity'],
        'status' => (string)$row['availability_status']
    );
}

echo json_encode([
    'success' => true,
    'products' => $products,
    'count' => count($products),
    'limit' => $limit,
    'offset' => $offset
], JSON_UNESCAPED_SLASHES);
