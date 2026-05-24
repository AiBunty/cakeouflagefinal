<?php
$pageTitle = 'Order Details';
require_once __DIR__ . '/../app/Core/App.php';
require_once __DIR__ . '/../app/Core/Database.php';

$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $currentUser = (int)$_SESSION['user_id'];
}

if (!$currentUser) {
    header('Location: /auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: /account/orders.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, order_number, user_id, order_status, payment_status, created_at, customer_name, customer_email, customer_phone, delivery_postal_code, subtotal, discount_total, tax_total, delivery_fee, grand_total FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('ii', $orderId, $currentUser);
$stmt->execute();
$orderResult = $stmt->get_result();
$order = $orderResult ? $orderResult->fetch_assoc() : null;

if (!$order) {
    header('Location: /account/orders.php');
    exit;
}

$itemsStmt = $conn->prepare('SELECT id, product_name_snapshot, quantity, unit_price, line_total FROM order_items WHERE order_id = ? ORDER BY id ASC');
$itemsStmt->bind_param('i', $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = $itemsResult ? $itemsResult->fetch_all(MYSQLI_ASSOC) : array();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($order['order_number']) ?> - Cakeouflage</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
        .navbar { background: #fff; border-bottom: 1px solid #eee; padding: 12px 20px; }
        .navbar a { color: #80001F; text-decoration: none; font-weight: 600; }
        .container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
        .order-header { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #eee; }
        .order-header h1 { color: #80001F; margin-bottom: 12px; font-size: 1.8rem; }
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-badge.confirmed { background: #dcfce7; color: #166534; }
        .status-badge.pending_payment { background: #fef3c7; color: #92400e; }
        .status-badge.payment_under_review { background: #fff2cf; color: #9a5b00; }
        .status-badge.preparing { background: #fef3c7; color: #92400e; }
        .status-badge.out_for_delivery { background: #e0f2fe; color: #0c4a6e; }
        .status-badge.ready_for_pickup { background: #ede9fe; color: #5b21b6; }
        .status-badge.delivered { background: #e0e7ff; color: #3730a3; }
        .status-badge.completed { background: #d1fae5; color: #065f46; }
        .status-badge.cancelled { background: #fecdd3; color: #9f1239; }
        .status-badge.rejected { background: #fecaca; color: #7f1d1d; }
        .status-badge.refund_requested { background: #ffedd5; color: #9a3412; }
        .status-badge.refunded { background: #f3e8ff; color: #6b21a8; }
        .status-badge.partially_refunded { background: #fae8ff; color: #86198f; }
        .meta-info { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 16px; }
        .meta-field { border-top: 1px solid #f0f0f0; padding-top: 12px; }
        .meta-field strong { color: #80001F; display: block; margin-bottom: 4px; font-size: 0.85rem; }
        .meta-field span { color: #666; }
        .order-box { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #eee; }
        .order-box h2 { color: #80001F; font-size: 1.2rem; margin-bottom: 16px; border-bottom: 1px solid #f0f0f0; padding-bottom: 12px; }
        .item-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #f0f0f0; }
        .item-row:last-child { border-bottom: none; }
        .item-name { font-weight: 500; }
        .item-price { text-align: right; color: #80001F; font-weight: 600; }
        .totals-box { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .total-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .total-row strong { color: #80001F; }
        .total-row.grand { border-bottom: 2px solid #80001F; padding-top: 12px; padding-bottom: 12px; font-size: 1.2rem; font-weight: 700; }
        .timeline { list-style: none; margin-top: 16px; }
        .timeline li { position: relative; padding-left: 24px; margin-bottom: 12px; color: #666; }
        .timeline li::before { content: "✓"; position: absolute; left: 0; color: #80001F; font-weight: 700; }
        .back-link { color: #80001F; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px; }
        .back-link:hover { text-decoration: underline; }
        .cta-buttons { display: flex; gap: 12px; margin-top: 16px; }
        .btn { padding: 10px 16px; border-radius: 8px; border: 0; cursor: pointer; font-size: 0.95rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: #80001F; color: #fff; }
        .btn-primary:hover { background: #600018; }
        .btn-secondary { background: #f0f0f0; color: #333; }
        .btn-secondary:hover { background: #e0e0e0; }
        @media (max-width: 760px) {
            .meta-info { grid-template-columns: 1fr; }
            .totals-box { grid-template-columns: 1fr; }
            .order-header h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="/">← Back to Shop</a>
    </div>
    
    <div class="container">
        <a href="/account/orders.php" class="back-link">← My Orders</a>
        
        <!-- Order Header -->
        <div class="order-header">
            <h1>Order <?= htmlspecialchars($order['order_number']) ?></h1>
            <span class="status-badge <?= htmlspecialchars($order['order_status']) ?>">
                <?= strtoupper(str_replace('_', ' ', $order['order_status'])) ?>
            </span>
            
            <div class="meta-info">
                <div class="meta-field">
                    <strong>Order Date</strong>
                    <span><?= date('M d, Y · h:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="meta-field">
                    <strong>Payment Status</strong>
                    <span><?= strtoupper(str_replace('_', ' ', $order['payment_status'])) ?></span>
                </div>
                <div class="meta-field">
                    <strong>Contact</strong>
                    <span><?= htmlspecialchars($order['customer_phone']) ?><br><?= htmlspecialchars($order['customer_email']) ?></span>
                </div>
                <div class="meta-field">
                    <strong>Delivery Location</strong>
                    <span><?= htmlspecialchars($order['delivery_postal_code'] ?: 'Not specified') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Items -->
        <div class="order-box">
            <h2>Items Ordered</h2>
            <?php foreach ($items as $item): ?>
                <div class="item-row">
                    <div>
                        <div class="item-name"><?= htmlspecialchars($item['product_name_snapshot']) ?></div>
                        <div style="font-size: 0.9rem; color: #999;">Qty: <?= (int)$item['quantity'] ?></div>
                    </div>
                    <div class="item-price">
                        Rs <?= number_format((float)$item['line_total'], 2) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Price Breakdown -->
        <div class="order-box">
            <h2>Price Breakdown</h2>
            <div class="totals-box">
                <div>
                    <div class="total-row">
                        <span>Subtotal</span>
                        <strong>Rs <?= number_format((float)$order['subtotal'], 2) ?></strong>
                    </div>
                    <div class="total-row">
                        <span>Discount</span>
                        <strong>- Rs <?= number_format((float)$order['discount_total'], 2) ?></strong>
                    </div>
                    <div class="total-row">
                        <span>Tax</span>
                        <strong>Rs <?= number_format((float)$order['tax_total'], 2) ?></strong>
                    </div>
                    <div class="total-row">
                        <span>Delivery Fee</span>
                        <strong>Rs <?= number_format((float)$order['delivery_fee'], 2) ?></strong>
                    </div>
                </div>
                <div>
                    <div class="total-row grand">
                        <span>Total Paid</span>
                        <strong>Rs <?= number_format((float)$order['grand_total'], 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="order-box">
            <h2>Order Status Timeline</h2>
            <ul class="timeline">
                <li>Order Placed</li>
                <?php if ($order['order_status'] === 'payment_under_review'): ?>
                    <li>Payment Under Review</li>
                <?php elseif ($order['order_status'] !== 'pending_payment' && $order['payment_status'] === 'paid'): ?>
                    <li>Payment Confirmed</li>
                <?php endif; ?>
                <?php if (in_array($order['order_status'], ['preparing', 'out_for_delivery', 'ready_for_pickup', 'delivered', 'completed', 'refund_requested', 'refunded', 'partially_refunded'])): ?>
                    <li>Preparing Your Order</li>
                <?php endif; ?>
                <?php if (in_array($order['order_status'], ['out_for_delivery', 'delivered', 'completed', 'refund_requested', 'refunded', 'partially_refunded'])): ?>
                    <li>Out for Delivery</li>
                <?php endif; ?>
                <?php if (in_array($order['order_status'], ['delivered', 'completed', 'refund_requested', 'refunded', 'partially_refunded'])): ?>
                    <li>Delivered</li>
                <?php endif; ?>
                <?php if ($order['order_status'] === 'completed'): ?>
                    <li style="color: #166534; font-weight: 600;">Order Completed &#10003;</li>
                <?php endif; ?>
                <?php if ($order['order_status'] === 'refund_requested'): ?>
                    <li style="color: #9a3412;">Refund Requested</li>
                <?php endif; ?>
                <?php if ($order['order_status'] === 'refunded'): ?>
                    <li style="color: #6b21a8;">Refund Processed</li>
                <?php endif; ?>
                <?php if ($order['order_status'] === 'partially_refunded'): ?>
                    <li style="color: #86198f;">Partial Refund Processed</li>
                <?php endif; ?>
                <?php if ($order['order_status'] === 'cancelled'): ?>
                    <li style="color: #e11d48;">Order Cancelled</li>
                <?php endif; ?>
                <?php if ($order['order_status'] === 'rejected'): ?>
                    <li style="color: #e11d48;">Order Rejected</li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Actions -->
        <div class="cta-buttons">
            <a href="/" class="btn btn-primary">Continue Shopping</a>
            <a href="/account/orders.php" class="btn btn-secondary">View All Orders</a>
        </div>
    </div>
</body>
</html>
