<?php

function calculate_product_starting_price(mysqli $conn, int $productId): float
{
    $stmt = $conn->prepare('SELECT MIN(COALESCE(discount_price, price)) as min_price FROM product_variants WHERE product_id = ? AND is_active = 1');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    
    return $row && $row['min_price'] ? (float)$row['min_price'] : 0.00;
}

function sync_product_starting_prices(mysqli $conn): int
{
    $updated = 0;
    
    $productsStmt = $conn->prepare('SELECT id, starting_price FROM products WHERE deleted_at IS NULL');
    $productsStmt->execute();
    $productsResult = $productsStmt->get_result();
    
    if (!$productsResult) {
        return 0;
    }
    
    while ($product = $productsResult->fetch_assoc()) {
        $calculatedPrice = calculate_product_starting_price($conn, (int)$product['id']);
        
        if ($calculatedPrice > 0 && $calculatedPrice !== (float)$product['starting_price']) {
            $updateStmt = $conn->prepare('UPDATE products SET starting_price = ? WHERE id = ?');
            $updateStmt->bind_param('di', $calculatedPrice, $product['id']);
            if ($updateStmt->execute() && $conn->affected_rows > 0) {
                $updated++;
            }
        }
    }
    
    return $updated;
}
