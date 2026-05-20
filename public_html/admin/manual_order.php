<?php
$pageTitle = "Manual Order Punch";
include "layout.php";

require __DIR__ . '/includes/db.php';

if (!isset($_SESSION['manual_order_idempotency_key']) || !is_string($_SESSION['manual_order_idempotency_key'])) {
    $_SESSION['manual_order_idempotency_key'] = bin2hex(random_bytes(16));
}

$idempotencyKey = $_SESSION['manual_order_idempotency_key'];

$status = trim((string)($_GET['status'] ?? ''));
$message = trim((string)($_GET['message'] ?? ''));
$orderNumber = trim((string)($_GET['order_number'] ?? ''));
?>

<style>
  .manual-order-wrap {
    max-width: 860px;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.14);
    border-radius: 18px;
    box-shadow: 0 16px 34px rgba(68, 16, 34, 0.1);
    overflow: hidden;
  }

  .manual-order-head {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.12);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }

  .manual-order-head h3 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
  }

  .manual-order-head a {
    color: #80001F;
    font-size: 0.86rem;
    text-decoration: none;
  }

  .manual-order-body {
    padding: 20px;
    display: grid;
    gap: 14px;
  }

  .manual-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .manual-field {
    display: grid;
    gap: 6px;
  }

  .manual-field label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #80001F;
    font-weight: 700;
  }

  .manual-field input,
  .manual-field select,
  .manual-field textarea {
    width: 100%;
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.94rem;
    color: #2e1f25;
    background: #fff;
  }

  .manual-field textarea {
    min-height: 96px;
    resize: vertical;
  }

  .manual-note {
    font-size: 0.82rem;
    color: #7f6973;
  }

  .manual-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 4px;
  }

  .btn-manual-primary {
    border: 0;
    border-radius: 10px;
    padding: 10px 18px;
    background: #80001F;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
  }

  .btn-manual-secondary {
    border: 1px solid rgba(128, 0, 31, 0.24);
    border-radius: 10px;
    padding: 10px 18px;
    background: #fff;
    color: #80001F;
    font-weight: 600;
    text-decoration: none;
  }

  .notice {
    border-radius: 11px;
    padding: 10px 12px;
    font-size: 0.86rem;
  }

  .notice.success {
    background: #ecfdf3;
    color: #166534;
    border: 1px solid #bbf7d0;
  }

  .notice.error {
    background: #fff1f2;
    color: #9f1239;
    border: 1px solid #fecdd3;
  }

  @media (max-width: 760px) {
    .manual-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="manual-order-wrap">
  <div class="manual-order-head">
    <h3>Create Manual Order</h3>
    <a href="orders.php">Back to Orders</a>
  </div>

  <form action="save_manual_order.php" method="post" class="manual-order-body">
    <?php if ($status === 'success'): ?>
      <div class="notice success">
        Manual order created successfully.
        <?php if ($orderNumber !== ''): ?>
          Order ID: <strong><?= htmlspecialchars($orderNumber) ?></strong>
        <?php endif; ?>
      </div>
    <?php elseif ($status === 'error'): ?>
      <div class="notice error">
        <?= htmlspecialchars($message !== '' ? $message : 'Unable to create manual order.') ?>
      </div>
    <?php endif; ?>

    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($idempotencyKey) ?>">

    <div class="manual-grid">
      <div class="manual-field">
        <label for="customer_name">Customer Name</label>
        <input type="text" id="customer_name" name="customer_name" required maxlength="120">
      </div>
      <div class="manual-field">
        <label for="customer_phone">Customer Mobile</label>
        <input type="text" id="customer_phone" name="customer_phone" required maxlength="25" placeholder="10-digit number">
      </div>
      <div class="manual-field">
        <label for="customer_email">Customer Email</label>
        <input type="email" id="customer_email" name="customer_email" required maxlength="190" placeholder="name@example.com">
      </div>
      <div class="manual-field">
        <label for="amount">Amount (INR)</label>
        <input type="number" id="amount" name="amount" required min="1" step="0.01" placeholder="0.00">
      </div>
      <div class="manual-field">
        <label for="fulfilment_mode">Fulfilment Mode</label>
        <select id="fulfilment_mode" name="fulfilment_mode">
          <option value="pickup">Pickup</option>
          <option value="delivery">Delivery</option>
          <option value="custom_delivery">Custom Delivery</option>
        </select>
      </div>
      <div class="manual-field">
        <label for="order_status">Order Status</label>
        <select id="order_status" name="order_status">
          <option value="pending">Pending</option>
          <option value="confirmed" selected>Confirmed</option>
          <option value="in_preparation">In Preparation</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="manual-field">
        <label for="payment_status">Payment Status</label>
        <select id="payment_status" name="payment_status">
          <option value="pending">Pending</option>
          <option value="paid" selected>Paid</option>
          <option value="failed">Failed</option>
          <option value="refunded">Refunded</option>
        </select>
      </div>
    </div>

    <div class="manual-field">
      <label for="item_name">Items</label>
      <textarea id="item_name" name="item_name" required maxlength="350" placeholder="Example: 1 Kg Chocolate Truffle Cake with custom topper"></textarea>
    </div>

    <div class="manual-field">
      <label for="admin_note">Internal Note (Optional)</label>
      <textarea id="admin_note" name="admin_note" maxlength="800" placeholder="Any fulfilment details for operations team."></textarea>
    </div>

    <p class="manual-note">On submit, the system will auto-create/reuse the customer by email, create order records, queue customer/admin email, and queue CRM push job with event key <strong>manual_order_received</strong>.</p>

    <div class="manual-actions">
      <a href="orders.php" class="btn-manual-secondary">Cancel</a>
      <button type="submit" class="btn-manual-primary">Create Manual Order</button>
    </div>
  </form>
</div>
