<?php



$pageTitle = "Order Details";
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/layout.php';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice_helpers.php';
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
$canRefund      = admin_has_permission('can_approve_refund') || admin_has_permission('can_force_refund');

// 🔥 ORDER FETCH
$order = $conn->query("SELECT * FROM orders WHERE id=$order_id")->fetch_assoc();
$canGenerateInvoice = is_array($order) && invoice_is_fully_paid($order);
$canOpenReceipt = is_array($order) && payment_receipt_is_eligible($order);
$receiptHistory = [];
$latestReceipt = null;
if (is_array($order) && (int)($order['id'] ?? 0) > 0) {
  try {
    $paymentReceiptService = new \App\Services\PaymentReceiptService();
    $receiptHistory = $paymentReceiptService->getReceiptHistoryForOrder((int)$order['id']);
    $latestReceipt = $receiptHistory[0] ?? null;
  } catch (\Throwable $receiptErr) {
    error_log('[order_details][receipt-history] ' . $receiptErr->getMessage());
  }
}

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
    <a href="production_plan.php?order_id=<?= (int)$order['id'] ?>" class="btn-back" style="background:#5b1f3a; color:#fff;">🖨 Print Production Plan</a>
    <?php if ($canGenerateInvoice): ?>
      <a href="order_invoice.php?id=<?php echo (int)$order['id']; ?>" class="btn-back" style="background:#111;">🧾 Invoice</a>
    <?php else: ?>
      <span class="btn-back" style="background:#9ca3af; cursor:not-allowed; opacity:.65;" title="Invoice unlocks only after payment is confirmed.">🧾 Invoice</span>
    <?php endif; ?>
    <?php if ($canOpenReceipt || is_array($latestReceipt)): ?>
      <a href="payment_receipt.php?id=<?php echo (int)$order['id']; ?>" class="btn-back" style="background:#5b1f3a; color:#fff;">🧾 Payment Receipt</a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="btn-back">← Back</a>
  </div>
</div>

<!-- 🔥 ACTION BUTTONS -->
<div class="container-box" style="background: #fef2f2; border: 1px solid #fecdd3;">
  <div class="title" style="margin-bottom:14px; color:#9f1239;">Order Actions</div>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <?php
    $terminalStates = ['completed', 'cancelled', 'rejected', 'refunded', 'partially_refunded', 'fully_refunded'];
    $cancelableUnpaidStates = ['pending_payment', 'payment_under_review'];
    $currentOrderStatus = (string)($order['order_status'] ?? '');
    $currentPayStatus   = (string)($order['payment_status'] ?? '');
    $isPaidState = in_array($currentPayStatus, ['paid', 'credit'], true);
    // Eligible for atomic refund: any paid, unrefunded, non-terminal mid-flow state
    $refundEligibleStatuses = ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed'];
    $alreadyRefundedStatuses = ['partially_refunded', 'fully_refunded', 'refunded'];
    $isRefundEligible = in_array($currentOrderStatus, $refundEligibleStatuses, true)
        && $isPaidState
        && !in_array($currentOrderStatus, $alreadyRefundedStatuses, true);
    $isAlreadyRefunded = in_array($currentOrderStatus, $alreadyRefundedStatuses, true);
    ?>
    <?php if ($canOrderReject && in_array($currentOrderStatus, $cancelableUnpaidStates, true)): ?>
      <button type="button" class="btn-back" style="background:#ef4444; color:#fff;" onclick="cancelOrder(<?php echo (int)$order['id']; ?>)">🚫 Cancel Order</button>
    <?php endif; ?>
    <?php if ($canRefund && $isRefundEligible): ?>
      <button type="button" class="btn-back" style="background:#7c3aed; color:#fff;" data-bs-toggle="modal" data-bs-target="#refundModal" onclick="prepRefundModal(<?php echo (int)$order['id']; ?>, <?php echo (float)$order['grand_total']; ?>)">💰 Process Refund</button>
    <?php elseif ($isAlreadyRefunded): ?>
      <span style="display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #d1d5db;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;color:<?php echo $currentOrderStatus === 'fully_refunded' ? '#166534' : '#86198f'; ?>">
        <?php echo $currentOrderStatus === 'fully_refunded' ? '✅ Fully Refunded' : '🔶 Partially Refunded'; ?>
        <?php if (!empty($order['total_refunded']) && (float)$order['total_refunded'] > 0): ?>
          &mdash; ₹<?php echo number_format((float)$order['total_refunded'], 2); ?>
        <?php endif; ?>
      </span>
    <?php endif; ?>
    <?php if (!$canOrderReject && !$canOrderRefund && !$canRefund): ?>
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
  <?php if (!empty($order['payment_proof_url'])): ?>
    <p>📸 <strong>Payment Screenshot:</strong>
      <a href="<?php echo htmlspecialchars((string)$order['payment_proof_url']); ?>" target="_blank" rel="noopener">
        <img src="<?php echo htmlspecialchars((string)$order['payment_proof_url']); ?>" alt="Payment proof" style="display:block;max-width:220px;max-height:160px;margin-top:6px;border-radius:6px;border:1px solid #ddd;box-shadow:0 1px 6px rgba(0,0,0,.1);">
      </a>
      <?php if (!empty($order['payment_proof_uploaded_at'])): ?>
        <small style="color:#888">Uploaded: <?php echo htmlspecialchars((string)$order['payment_proof_uploaded_at']); ?></small>
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if (!in_array((string)($order['payment_status'] ?? ''), ['paid', 'rejected'], true) && !in_array((string)($order['order_status'] ?? ''), ['cancelled', 'completed', 'delivered'], true)): ?>
  <div id="payment-action-area" style="margin:12px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <button onclick="adminConfirmPayment(<?php echo (int)$order['id']; ?>)"
      style="background:#22c55e;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-weight:600;cursor:pointer;font-size:14px;">
      ✅ Confirm Payment &amp; Reserve Slot
    </button>
    <button onclick="adminRejectPayment(<?php echo (int)$order['id']; ?>)"
      style="background:#ef4444;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-weight:600;cursor:pointer;font-size:14px;">
      ❌ Reject Payment
    </button>
    <span id="payment-action-msg" style="font-size:13px;color:#555;"></span>
  </div>
  <script>
  function adminConfirmPayment(orderId) {
    var grossText = '<?php echo htmlspecialchars((string)number_format((float)($order['grand_total'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>';
    var defaultReceived = String(grossText || '0.00');
    var receivedInput = prompt('Enter amount received (full payment):', defaultReceived);
    if (receivedInput === null) return;
    var receivedAmount = parseFloat(String(receivedInput).trim());
    if (!isFinite(receivedAmount) || receivedAmount <= 0) {
      alert('Please enter a valid received amount.');
      return;
    }

    var expectedAmount = parseFloat(defaultReceived);
    var shortfall = Math.max(0, +(expectedAmount - receivedAmount).toFixed(2));
    var discountReason = '';
    var managerOverride = false;
    if (shortfall > 0) {
      discountReason = prompt('Shortfall will be adjusted as discount. Enter reason:', 'On-call approved adjustment') || '';
      if (!confirm('Apply discount ₹' + shortfall.toFixed(2) + ' and confirm payment?')) return;
      var ratio = expectedAmount > 0 ? (shortfall / expectedAmount) : 0;
      if (ratio > 0.05) {
        managerOverride = confirm('Discount exceeds 5%. Confirm manager override?');
      }
    } else if (!confirm('Confirm full payment and officially reserve the slot for this order?')) {
      return;
    }

    var btn = document.querySelector('#payment-action-area button');
    var msg = document.getElementById('payment-action-msg');
    msg.textContent = 'Processing…';
    fetch('/api/admin/orders/' + orderId + '/confirm-payment', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      credentials: 'same-origin',
      body: JSON.stringify({
        received_amount: receivedAmount,
        discount_reason: discountReason,
        manager_override: managerOverride
      })
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.success) {
        msg.style.color = '#22c55e';
        msg.textContent = '✅ ' + data.message;
        setTimeout(function(){ location.reload(); }, 1500);
      } else {
        msg.style.color = '#ef4444';
        msg.textContent = '❌ ' + (data.message || 'Failed');
      }
    })
    .catch(function(){ msg.style.color='#ef4444'; msg.textContent='Network error'; });
  }
  function adminRejectPayment(orderId) {
    var reason = prompt('Rejection reason (optional):') || '';
    if (reason === null) return; // cancelled
    var msg = document.getElementById('payment-action-msg');
    msg.textContent = 'Processing…';
    fetch('/api/admin/orders/' + orderId + '/reject-payment', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      credentials: 'same-origin',
      body: JSON.stringify({reason: reason}),
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.success) {
        msg.style.color = '#22c55e';
        msg.textContent = '✅ ' + data.message;
        setTimeout(function(){ location.reload(); }, 1500);
      } else {
        msg.style.color = '#ef4444';
        msg.textContent = '❌ ' + (data.message || 'Failed');
      }
    })
    .catch(function(){ msg.style.color='#ef4444'; msg.textContent='Network error'; });
  }
  </script>
  <?php endif; ?>
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

<div class="container-box">
  <div class="title">🧾 Payment Receipt History</div>
  <?php if ($receiptHistory): ?>
    <div style="display:grid;gap:10px;">
      <?php foreach ($receiptHistory as $receipt): ?>
        <div style="border:1px solid #ead9df;border-radius:10px;padding:12px;background:#fff8fa;">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
            <div>
              <div style="font-weight:700;color:#5b1f3a;"><?= htmlspecialchars((string)($receipt['receipt_number'] ?? 'NA'), ENT_QUOTES, 'UTF-8') ?></div>
              <div style="font-size:13px;color:#6b7280;">Issued <?= htmlspecialchars(invoice_format_datetime((string)($receipt['issued_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div style="text-align:right;">
              <div style="font-weight:700;">₹<?= number_format((float)($receipt['amount'] ?? 0), 2) ?></div>
              <div style="font-size:12px;color:#6b7280;">Balance due: ₹<?= number_format((float)($receipt['balance_due'] ?? 0), 2) ?></div>
            </div>
          </div>
          <div style="margin-top:8px;font-size:13px;color:#374151;display:grid;gap:4px;">
            <div>Method: <strong><?= htmlspecialchars(invoice_payment_method_label((string)($receipt['payment_method'] ?? ($order['payment_method'] ?? 'NA'))), ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div>Status Snapshot: <strong><?= htmlspecialchars(strtoupper((string)($receipt['payment_status_snapshot'] ?? 'pending')), ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div>Issued By: <strong><?= htmlspecialchars((string)($receipt['issued_by_name'] ?? 'System'), ENT_QUOTES, 'UTF-8') ?></strong></div>
            <?php if (!empty($receipt['financial_transaction_type']) || !empty($receipt['financial_narration'])): ?>
              <div>Accounting Link: <strong><?= htmlspecialchars((string)($receipt['financial_transaction_type'] ?? 'transaction'), ENT_QUOTES, 'UTF-8') ?></strong><?php if (!empty($receipt['financial_amount'])): ?> · ₹<?= number_format((float)$receipt['financial_amount'], 2) ?><?php endif; ?></div>
              <div style="color:#6b7280;"><?= htmlspecialchars((string)($receipt['financial_narration'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p style="margin:0;color:#6b7280;font-size:14px;">No payment receipts have been issued for this order yet.</p>
  <?php endif; ?>
</div>

<!-- 🧁 ITEMS -->
<div class="container-box">
  <div class="title">🧁 Items</div>

  <?php while($item = $items->fetch_assoc()): ?>
    <div class="item-row">
      <span><?php echo htmlspecialchars((string)($item['product_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
      <span>x<?php echo (int)$item['quantity']; ?></span>
    </div>
    <?php if (!empty($item['variant_snapshot'])): ?>
      <div style="font-size:12px;color:#7a5060;margin-bottom:4px;">Variant: <?= htmlspecialchars((string)$item['variant_snapshot'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($item['cake_message'])): ?>
      <div style="font-size:12px;color:#5b1f3a;background:#fff0f4;border-radius:6px;padding:4px 8px;margin-bottom:4px;">🎂 Note on Cake: <?= htmlspecialchars((string)$item['cake_message'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($item['topper_name_snapshot']) && $item['topper_name_snapshot'] !== 'No Topper'): ?>
      <div style="font-size:12px;color:#5b1f3a;background:#fff0f4;border-radius:6px;padding:4px 8px;margin-bottom:4px;">🎀 Topper: <?= htmlspecialchars((string)$item['topper_name_snapshot'], ENT_QUOTES, 'UTF-8') ?><?= (float)($item['topper_price_snapshot'] ?? 0) > 0 ? ' (+₹' . number_format((float)$item['topper_price_snapshot'], 0) . ')' : '' ?></div>
    <?php endif; ?>
    <?php if (!empty($item['customisation_note'])): ?>
      <div style="font-size:12px;color:#5b1f3a;background:#fff8f0;border-radius:6px;padding:4px 8px;margin-bottom:4px;">📝 Note: <?= htmlspecialchars((string)$item['customisation_note'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  <?php endwhile; ?>
</div>

<!-- 📍 TIMELINE -->
<!-- 📍 TIMELINE -->
<div class="container-box">
  <div class="title">📍 Order Timeline</div>

  <ul class="timeline">

    <li>Order Created</li>

    <?php if($order['order_status'] == 'pending_payment'): ?>

      <li>Waiting For Payment Verification</li>

    <?php elseif($order['order_status'] == 'payment_under_review'): ?>

      <li>Payment Under Review</li>

    <?php elseif($order['order_status'] == 'confirmed'): ?>

      <li>Payment Verified</li>
      <li>Preparing</li>

    <?php elseif($order['order_status'] == 'preparing'): ?>

      <li>Payment Verified</li>
      <li>Preparing</li>
      <li>Ready / Dispatching</li>

    <?php elseif($order['order_status'] == 'ready_for_pickup'): ?>

      <li>Payment Verified</li>
      <li>Prepared</li>
      <li>Ready For Pickup</li>

    <?php elseif($order['order_status'] == 'out_for_delivery'): ?>

      <li>Payment Verified</li>
      <li>Prepared</li>
      <li>Out For Delivery</li>

    <?php elseif($order['order_status'] == 'delivered'): ?>

      <li>Payment Verified</li>
      <li>Prepared</li>
      <li>Out for Delivery</li>
      <li>Delivered</li>

    <?php elseif($order['order_status'] == 'completed'): ?>

      <li>Payment Verified</li>
      <li>Prepared</li>
      <li>Delivered</li>
      <li style="color:#166534;">Order Completed ✓</li>

    <?php elseif($order['order_status'] == 'refund_requested'): ?>

      <li>Payment Verified</li>
      <li>Prepared / Delivered</li>
      <li style="color:#9a3412;">Refund Requested</li>

    <?php elseif($order['order_status'] == 'refunded'): ?>

      <li>Payment Verified</li>
      <li>Delivered</li>
      <li style="color:#6b21a8;">Refund Processed ✓</li>

    <?php elseif($order['order_status'] == 'partially_refunded'): ?>

      <li>Payment Verified</li>
      <li>Delivered</li>
      <li style="color:#86198f;">Partial Refund Processed</li>

    <?php elseif($order['order_status'] == 'fully_refunded'): ?>

      <li>Payment Verified</li>
      <li>Delivered</li>
      <li style="color:#166534;">Full Refund Processed ✓</li>

    <?php elseif($order['order_status'] == 'cancelled'): ?>

      <li style="color:red;">Order Cancelled</li>

    <?php elseif($order['order_status'] == 'rejected'): ?>

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

// ── Refund Modal helpers ───────────────────────────────────────────────────
function prepRefundModal(orderId, grandTotal) {
  document.getElementById('refund-order-id').value = orderId;
  document.getElementById('refund-grand-total').value = grandTotal;
  document.getElementById('refund-amount').value = '';
  document.getElementById('refund-amount').max = grandTotal;
  document.getElementById('refund-reason').value = '';
  document.getElementById('refund-notes').value = '';
  document.getElementById('refund-settlement-ref').value = '';
  document.getElementById('refund-proof-url').value = '';
  document.getElementById('refund-proof-filename').textContent = '';
  document.getElementById('refund-modal-msg').textContent = '';
  document.getElementById('refund-type-full').checked = false;
  document.getElementById('refund-type-partial').checked = false;
  document.getElementById('refund-notes-group').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
  // Auto-fill amount on Full refund selection
  document.getElementById('refund-type-full').addEventListener('change', function() {
    if (this.checked) {
      var gt = parseFloat(document.getElementById('refund-grand-total').value || 0);
      document.getElementById('refund-amount').value = gt.toFixed(2);
    }
  });
  document.getElementById('refund-type-partial').addEventListener('change', function() {
    if (this.checked) {
      document.getElementById('refund-amount').value = '';
    }
  });

  // Show/hide notes when reason = OTHER
  document.getElementById('refund-reason').addEventListener('change', function() {
    var show = this.value === 'OTHER';
    document.getElementById('refund-notes-group').style.display = show ? 'block' : 'none';
    document.getElementById('refund-notes').required = show;
  });

  // Settlement proof upload
  document.getElementById('refund-proof-file').addEventListener('change', async function() {
    var file = this.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
      alert('File must be under 5 MB');
      this.value = '';
      return;
    }
    var allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    if (!allowed.includes(file.type)) {
      alert('Only JPEG, PNG, WebP and PDF files are allowed');
      this.value = '';
      return;
    }
    var form = new FormData();
    form.append('proof', file);
    document.getElementById('refund-proof-filename').textContent = 'Uploading…';
    try {
      var r = await fetch('/api/admin/refunds/upload-proof', {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      var data = await r.json();
      if (data.success) {
        document.getElementById('refund-proof-url').value = data.url;
        document.getElementById('refund-proof-filename').textContent = '✓ ' + file.name;
      } else {
        document.getElementById('refund-proof-filename').textContent = '✗ Upload failed';
      }
    } catch (e) {
      document.getElementById('refund-proof-filename').textContent = '✗ Network error';
    }
  });

  // Submit refund form
  document.getElementById('refund-submit-btn').addEventListener('click', async function() {
    var orderId    = parseInt(document.getElementById('refund-order-id').value, 10);
    var amount     = parseFloat(document.getElementById('refund-amount').value || 0);
    var grandTotal = parseFloat(document.getElementById('refund-grand-total').value || 0);
    var reason     = document.getElementById('refund-reason').value;
    var notes      = document.getElementById('refund-notes').value.trim();
    var ref        = document.getElementById('refund-settlement-ref').value.trim();
    var proofUrl   = document.getElementById('refund-proof-url').value.trim();
    var msg        = document.getElementById('refund-modal-msg');

    if (!orderId || amount <= 0) { msg.style.color='#dc2626'; msg.textContent='Please enter a valid refund amount.'; return; }
    if (amount > grandTotal)     { msg.style.color='#dc2626'; msg.textContent='Amount cannot exceed order total ₹' + grandTotal.toFixed(2); return; }
    if (!reason)                  { msg.style.color='#dc2626'; msg.textContent='Please select a refund reason.'; return; }
    if (reason === 'OTHER' && !notes) { msg.style.color='#dc2626'; msg.textContent='Internal notes are required for "Other" reason.'; return; }

    msg.style.color = '#555'; msg.textContent = 'Processing…';
    this.disabled = true;

    try {
      var r = await fetch('/api/admin/orders/' + orderId + '/refund/process', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        credentials: 'same-origin',
        body: JSON.stringify({
          refund_amount: amount,
          reason_code: reason,
          reason_notes: notes,
          settlement_reference: ref,
          settlement_proof_url: proofUrl
        })
      });
      var data = await r.json();
      if (data.success) {
        msg.style.color='#16a34a';
        msg.textContent = '✅ ' + data.message;
        setTimeout(function(){ location.reload(); }, 1600);
      } else {
        msg.style.color='#dc2626';
        msg.textContent = '✗ ' + (data.message || 'Failed to process refund');
        document.getElementById('refund-submit-btn').disabled = false;
      }
    } catch (e) {
      msg.style.color='#dc2626';
      msg.textContent = 'Network error';
      document.getElementById('refund-submit-btn').disabled = false;
    }
  });
});
</script>

<!-- ── Refund Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:16px;overflow:hidden;">
      <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border:none;">
        <h5 class="modal-title" id="refundModalLabel">💰 Process Refund</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <input type="hidden" id="refund-order-id">
        <input type="hidden" id="refund-grand-total">
        <input type="hidden" id="refund-proof-url">

        <div class="mb-3">
          <label style="font-weight:600;font-size:14px;display:block;margin-bottom:8px;">Refund Type</label>
          <div style="display:flex;gap:16px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
              <input type="radio" name="refund-type-radio" id="refund-type-full" value="full">
              <span style="font-size:14px;">Full Refund</span>
            </label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
              <input type="radio" name="refund-type-radio" id="refund-type-partial" value="partial">
              <span style="font-size:14px;">Partial Refund</span>
            </label>
          </div>
        </div>

        <div class="mb-3">
          <label for="refund-amount" style="font-weight:600;font-size:14px;display:block;margin-bottom:4px;">Refund Amount (₹)</label>
          <input type="number" id="refund-amount" min="0.01" step="0.01" placeholder="e.g. 500.00" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;">
        </div>

        <div class="mb-3">
          <label for="refund-reason" style="font-weight:600;font-size:14px;display:block;margin-bottom:4px;">Reason</label>
          <select id="refund-reason" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;">
            <option value="">— Select reason —</option>
            <option value="QUALITY_COMPLAINT">Quality Complaint</option>
            <option value="WRONG_CAKE_DELIVERED">Wrong Cake Delivered</option>
            <option value="DELAYED_DELIVERY">Delayed Delivery</option>
            <option value="DAMAGED_CAKE">Damaged Cake</option>
            <option value="CUSTOMER_COMPLAINT">Customer Complaint</option>
            <option value="DUPLICATE_ORDER">Duplicate Order</option>
            <option value="KITCHEN_ISSUE">Kitchen Issue</option>
            <option value="STAFF_ISSUE">Staff Issue</option>
            <option value="FRAUD_PREVENTION">Fraud Prevention</option>
            <option value="ADMIN_ADJUSTMENT">Admin Adjustment</option>
            <option value="OTHER">Other</option>
          </select>
        </div>

        <div class="mb-3" id="refund-notes-group" style="display:none;">
          <label for="refund-notes" style="font-weight:600;font-size:14px;display:block;margin-bottom:4px;">Internal Notes <span style="color:#dc2626;">*</span></label>
          <textarea id="refund-notes" rows="3" placeholder="Required when reason is Other" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;resize:vertical;"></textarea>
        </div>

        <div class="mb-3">
          <label for="refund-settlement-ref" style="font-weight:600;font-size:14px;display:block;margin-bottom:4px;">Settlement Reference <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
          <input type="text" id="refund-settlement-ref" placeholder="UTR / transaction ID" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;">
        </div>

        <div class="mb-3">
          <label style="font-weight:600;font-size:14px;display:block;margin-bottom:4px;">Settlement Proof <span style="color:#9ca3af;font-weight:400;">(optional — image or PDF, max 5 MB)</span></label>
          <input type="file" id="refund-proof-file" accept="image/jpeg,image/png,image/webp,application/pdf" style="font-size:14px;">
          <span id="refund-proof-filename" style="display:block;margin-top:4px;font-size:12px;color:#16a34a;"></span>
        </div>

        <div id="refund-modal-msg" style="font-size:13px;min-height:18px;"></div>
      </div>
      <div class="modal-footer" style="border:none;padding:16px 24px;background:#f9fafb;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="refund-submit-btn" style="background:#7c3aed;color:#fff;border:none;padding:9px 20px;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;">Process Refund</button>
      </div>
    </div>
  </div>
</div>