<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

require 'includes/db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="products-export-' . date('Ymd-His') . '.csv"');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit;
}

fputcsv($output, ['Category', 'Subcategory', 'Product Name', 'Variants']);

$sql = "
    SELECT
        COALESCE(parent_cat.name, '') AS category_name,
        COALESCE(sub_cat.name, '') AS subcategory_name,
        p.name AS product_name,
        COALESCE(
            GROUP_CONCAT(
                CONCAT(COALESCE(NULLIF(pv.variant_label, ''), pv.weight_or_size), ':', ROUND(pv.price, 2))
                ORDER BY pv.is_default DESC, pv.id ASC
                SEPARATOR ' | '
            ),
            ''
        ) AS variants
    FROM products p
    LEFT JOIN categories parent_cat ON parent_cat.id = p.collection_category_id
    LEFT JOIN categories sub_cat ON sub_cat.id = p.subcategory_id
    LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
    WHERE p.deleted_at IS NULL
    GROUP BY p.id, parent_cat.name, sub_cat.name, p.name
    ORDER BY p.name ASC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['category_name'],
            $row['subcategory_name'],
            $row['product_name'],
            $row['variants'],
        ]);
    }
}

fclose($output);
exit;
