<?php



$pageTitle = "Order Details";
include "layout.php";

require __DIR__ . '/includes/db.php';
$order_id = intval($_GET['id'] ?? 0);
$returnTo = trim((string)($_GET['return_to'] ?? ''));
$backHref = 'orders.php';
if ($returnTo !== '') {
  $parts = parse_url($returnTo);
  if (is_array($parts)) {
    $path = basename((string)($parts['path'] ?? ''));
    $safePages = array('orders.php', 'sales_register.php', 'collection_report.php');
    if (in_array($path, $safePages, true)) {
      $query = isset($parts['query']) ? trim((string)$parts['query']) : '';
      $backHref = $path . ($query !== '' ? ('?' . $query) : '');
    }
  }
}
$canOrderReject = admin_has_permission('order_reject');
$canOrderRefund = admin_has_permission('order_refund');

// 🔥 ORDER FETCH
$order = $conn->query("SELECT * FROM orders WHERE id=$order_id")->fetch_assoc();
$canGenerateInvoice = is_array($order) && (string)($order['payment_status'] ?? '') === 'paid';

// 🔥 ITEMS FETCH (IMPORTANT: table name check kar)
$items = $conn->query("SELECT * FROM order_items WHERE order_id=$order_id");
?>
<?php
function getStatus($s){
  if($s=="pending") return "confirmed";
  return $s;
}
?>
<style>
.container-box {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.06);
  margin-bottom: 20px;
}

.title {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 10px;
}

.status {
  display: inline-block;
  padding: 5px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

/* status colors */
.status.confirmed { background: #dcfce7; color: #166534; }
.status.preparing { background: #fef3c7; color: #92400e; }
.status.delivered { background: #e0e7ff; color: #3730a3; }

.item-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  padding-bottom: 6px;
  border-bottom: 1px dashed #eee;
}

.timeline {
  list-style: none;
  padding-left: 0;
}

.timeline li {
  position: relative;
  padding-left: 18px;
  margin-bottom: 6px;
}

.timeline li::before {
  content: "●";
  position: absolute;
  left: 0;
  color: #e11d48;
}
.btn-back {
  background: #111;
  color: #fff;
  padding: 6px 12px;
  border-radius: 6px;
  text-decoration: none;
  font-size: 13px;
}

.btn-back:hover {
  background: #333;
}

.container-box {
  transition: 0.2s;
}

.container-box:hover {
  transform: translateY(-3px);
}

.title {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.price-highlight {
  font-size: 18px;
  color: #e11d48;
  font-weight: bold;
}
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
  <h2>Order Details</h2>

  <div style="display:flex; gap:8px; align-items:center;">
    <?php if ($canGenerateInvoice): ?>
      <a href="order_invoice.php?id=<?php echo (int)$order['id']; ?>" class="btn-back" style="background:#111;">🧾 Invoice</a>
    <?php else: ?>
      <span class="btn-back" style="background:#9ca3af; cursor:not-allowed; opacity:.65;" title="Invoice unlocks only after payment is confirmed.">🧾 Invoice</span>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="btn-back">← Back</a>
  </div>
</div>

<!-- 🔥 ACTION BUTTONS -->
<div class="container-box" style="background: #fef2f2; border: 1px solid #fecdd3;">
  <div class="title" style="margin-bottom:14px; color:#9f1239;">Order Actions</div>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <?php if ($canOrderReject && $order['order_status'] !== 'completed' && $order['order_status'] !== 'cancelled'): ?>
      <button type="button" class="btn-back" style="background:#ef4444; color:#fff;" onclick="cancelOrder(<?php echo (int)$order['id']; ?>)">🚫 Cancel Order</button>
    <?php endif; ?>
    <?php if ($canOrderRefund && $order['payment_status'] === 'paid'): ?>
      <button type="button" class="btn-back" style="background:#8b5cf6; color:#fff;" onclick="refundOrder(<?php echo (int)$order['id']; ?>)">💰 Process Refund</button>
    <?php endif; ?>
    <?php if (!$canOrderReject && !$canOrderRefund): ?>
      <span style="font-size:13px;color:#8b5c67;">No action permissions assigned for this account.</span>
    <?php endif; ?>
  </div>
</div>

<!-- 🔥 ORDER INFO -->
<div class="container-box">
  <div class="title">#<?php echo $order['order_number']; ?></div>

  <p><strong><?php echo $order['customer_name']; ?></strong></p>
  <p>📞 <?php echo $order['customer_phone']; ?></p>
  <p>📧 <?php echo $order['customer_email']; ?></p>
  <p>💳 Payment: <strong><?php echo htmlspecialchars((string)$order['payment_method']); ?></strong> | Status: <strong><?php echo htmlspecialchars((string)$order['payment_status']); ?></strong></p>
  <?php if (!empty($order['billing_address_line1']) || !empty($order['billing_city']) || !empty($order['billing_postal_code'])): ?>
    <p>🏠 <?php echo htmlspecialchars(trim(($order['billing_address_line1'] ?? '') . ' ' . ($order['billing_address_line2'] ?? ''))); ?><br>
      <?php echo htmlspecialchars(trim(($order['billing_city'] ?? '') . ', ' . ($order['billing_state'] ?? '') . ' ' . ($order['billing_postal_code'] ?? ''))); ?></p>
  <?php endif; ?>

 <span class="status <?php echo getStatus($order['order_status']); ?>">
  <?php echo strtoupper(getStatus($order['order_status'])); ?>
  </span>
</div>

<!-- 💰 PRICE -->
<div class="container-box">
  <div class="title">💰 Price Details</div>

  <p>Subtotal: ₹<?php echo $order['subtotal']; ?></p>
  <p>Delivery: ₹<?php echo $order['delivery_fee']; ?></p>
<p class="price-highlight">Total: ₹<?php echo $order['grand_total']; ?></p>
</div>

<!-- 🧁 ITEMS -->
<div class="container-box">
  <div class="title">🧁 Items</div>

  <?php while($item = $items->fetch_assoc()): ?>
    <div class="item-row">
      <span><?php echo $item['product_name_snapshot']; ?></span>
      <span>x<?php echo $item['quantity']; ?></span>
    </div>
  <?php endwhile; ?>
</div>

<!-- 📍 TIMELINE -->
<!-- 📍 TIMELINE -->
<div class="container-box">
  <div class="title">📍 Order Timeline</div>

  <ul class="timeline">

    <li>Order Created</li>

    <?php if($order['order_status'] == 'pending'): ?>

      <li>Waiting For Payment Verification</li>

    <?php elseif($order['order_status'] == 'confirmed'): ?>

      <li>Payment Verified</li>
      <li>Preparing</li>

    <?php elseif($order['order_status'] == 'in_preparation'): ?>

      <li>Payment Verified</li>
      <li>Preparing</li>
      <li>Out for Delivery</li>

    <?php elseif($order['order_status'] == 'completed'): ?>

      <li>Payment Verified</li>
      <li>Preparing</li>
      <li>Out for Delivery</li>
      <li>Delivered</li>

    <?php elseif($order['order_status'] == 'cancelled'): ?>

      <li style="color:red;">Payment Not Received</li>
      <li style="color:red;">Order Rejected</li>

    <?php endif; ?>

  </ul>
</div>

<script>
function cancelOrder(orderId) {
  const reason = prompt('Enter cancellation reason (optional):', '');
  if (reason === null) return;

  const form = new FormData();
  form.append('action', 'cancel');
  form.append('order_id', orderId);
  form.append('reason', reason);
  form.append('redirect_to', window.location.href);

  fetch('/admin/api/order-refund-cancel.php', {
    method: 'POST',
    body: form
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('✓ Order cancelled successfully');
      location.reload();
    } else {
      alert('✗ ' + (data.error || 'Failed to cancel order'));
    }
  })
  .catch(err => alert('Error: ' + err.message));
}

function refundOrder(orderId) {
  const reason = prompt('Enter refund reason (optional):', '');
  if (reason === null) return;

  const form = new FormData();
  form.append('action', 'refund');
  form.append('order_id', orderId);
  form.append('reason', reason);
  form.append('redirect_to', window.location.href);

  fetch('/admin/api/order-refund-cancel.php', {
    method: 'POST',
    body: form
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('✓ Refund processed successfully\nRefund Amount: Rs ' + data.refund_amount);
      location.reload();
    } else {
      alert('✗ ' + (data.error || 'Failed to process refund'));
    }
  })
  .catch(err => alert('Error: ' + err.message));
}
</script>