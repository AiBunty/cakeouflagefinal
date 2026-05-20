<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require __DIR__ . '/save_manual_order.php';
  exit;
}

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
$createdOrderId = max((int)($_GET['order_id'] ?? 0), 0);
$createdPaymentStatus = trim((string)($_GET['payment_status'] ?? ''));
?>

<style>
  .manual-order-wrap {
    max-width: 1000px;
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
    gap: 16px;
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

  .manual-field input.is-locked {
    background: #f3f4f6;
    color: #4b5563;
    border-color: #d1d5db;
  }

  .customer-status {
    font-size: 0.75rem;
    color: #6b7280;
    min-height: 1.1rem;
  }

  .customer-status.match {
    color: #166534;
  }

  .customer-status.new {
    color: #9f1239;
  }

  .customer-lookup-wrap {
    position: relative;
  }

  .customer-lookup-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-radius: 10px;
    box-shadow: 0 12px 24px rgba(68, 16, 34, 0.14);
    max-height: 220px;
    overflow-y: auto;
    z-index: 30;
    display: none;
  }

  .customer-lookup-dropdown.active {
    display: block;
  }

  .customer-option {
    border: 0;
    width: 100%;
    text-align: left;
    background: #fff;
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
  }

  .customer-option:last-child {
    border-bottom: 0;
  }

  .customer-option:hover {
    background: #fff4f7;
  }

  .customer-option strong {
    display: block;
    color: #2e1f25;
    font-size: 0.86rem;
  }

  .customer-option small {
    color: #7f6973;
    font-size: 0.75rem;
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

  .btn-manual-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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

  .btn-icon {
    border: 1px solid rgba(128, 0, 31, 0.24);
    border-radius: 8px;
    padding: 8px 12px;
    background: #fff;
    color: #80001F;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.85rem;
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

  .items-section {
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 12px;
    padding: 14px;
    background: #fafaf8;
  }

  .items-section h4 {
    margin: 0 0 12px;
    color: #80001F;
    font-size: 0.92rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  .search-wrapper {
    position: relative;
    margin-bottom: 12px;
  }

  .search-input {
    width: 100%;
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.94rem;
    color: #2e1f25;
  }

  .search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-top: 0;
    border-radius: 0 0 10px 10px;
    max-height: 320px;
    overflow-y: auto;
    z-index: 10;
    display: none;
  }

  .search-dropdown.active {
    display: block;
  }

  .search-result {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    cursor: pointer;
    display: flex;
    gap: 12px;
    align-items: center;
  }

  .search-result:hover {
    background: #f5f5f5;
  }

  .search-result-thumb {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    background: #efefef;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .search-result-info {
    flex: 1;
    min-width: 0;
  }

  .search-result-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: #111;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .search-result-price {
    font-size: 0.8rem;
    color: #666;
  }

  .items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
  }

  .items-table thead tr {
    background: #efefef;
  }

  .items-table th {
    padding: 8px;
    text-align: left;
    font-weight: 700;
    font-size: 0.8rem;
    color: #80001F;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .items-table td {
    padding: 10px 8px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.1);
  }

  .items-table tr:last-child td {
    border-bottom: 0;
  }

  .item-name-cell {
    font-weight: 600;
    color: #111;
  }

  .item-remove {
    background: #ff6b6b;
    color: #fff;
    border: 0;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 0.75rem;
    cursor: pointer;
  }

  .item-qty-input,
  .item-price-input {
    width: 70px;
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-radius: 6px;
    padding: 6px 8px;
    font-size: 0.85rem;
  }

  .order-total-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    background: #fff8fa;
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-radius: 10px;
    padding: 14px;
    margin-top: 12px;
  }

  .total-row {
    display: flex;
    justify-content: space-between;
  }

  .total-label {
    font-weight: 600;
    color: #80001F;
  }

  .total-value {
    font-weight: 700;
    color: #111;
  }

  .add-buttons {
    display: flex;
    gap: 8px;
    margin-top: 10px;
  }

  @media (max-width: 760px) {
    .manual-grid {
      grid-template-columns: 1fr;
    }
    .order-total-box {
      grid-template-columns: 1fr;
    }
    .item-qty-input,
    .item-price-input {
      width: 60px;
    }
  }
</style>

<div class="manual-order-wrap">
  <div class="manual-order-head">
    <h3>Create Manual Order</h3>
    <a href="orders.php">Back to Orders</a>
  </div>

  <form action="manual_order.php" method="post" class="manual-order-body" id="manualOrderForm">
    <?php if ($status === 'success'): ?>
      <div class="notice success">
        Manual order created successfully.
        <?php if ($orderNumber !== ''): ?>
          Order: <strong><?= htmlspecialchars($orderNumber) ?></strong>
        <?php endif; ?>
      </div>
    <?php elseif ($status === 'error'): ?>
      <div class="notice error">
        <?= htmlspecialchars($message !== '' ? $message : 'Unable to create manual order.') ?>
      </div>
    <?php endif; ?>

    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($idempotencyKey) ?>">
    <input type="hidden" name="order_items" id="orderItemsInput" value="[]">
    <input type="hidden" name="matched_user_id" id="matchedUserId" value="0">

    <div class="manual-grid">
      <div class="manual-field">
        <label for="customer_phone">Customer Mobile (Master)</label>
        <div class="customer-lookup-wrap">
          <input type="tel" id="customer_phone" name="customer_phone" required maxlength="15" placeholder="Enter mobile number" inputmode="numeric" pattern="[0-9]{10,15}" autocomplete="off">
          <div id="customerLookupDropdown" class="customer-lookup-dropdown"></div>
        </div>
        <div id="customerLookupStatus" class="customer-status">Enter mobile first. Existing customer data auto-fills.</div>
      </div>
      <div class="manual-field">
        <label for="customer_name">Customer Name</label>
        <input type="text" id="customer_name" name="customer_name" required maxlength="120" autocomplete="off">
      </div>
      <div class="manual-field">
        <label for="customer_email">Customer Email</label>
        <input type="email" id="customer_email" name="customer_email" required maxlength="190" placeholder="name@example.com" autocomplete="off">
      </div>
      <div class="manual-field">
        <label for="fulfilment_mode">Fulfilment Mode</label>
        <select id="fulfilment_mode" name="fulfilment_mode" onchange="togglePorterNotice(this.value)">
          <option value="pickup">Pickup</option>
          <option value="delivery">Delivery</option>
          <option value="custom_delivery">Custom Delivery</option>
        </select>
      </div>
      <div id="porterNoticeManual" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:10px 14px; font-size:0.85rem; margin-bottom:8px; color:#0c4a6e;">
        🚚 <strong>Porter Delivery:</strong> Charges are collected directly by the Porter partner at delivery. Estimated &#x20b9;80–₹150 within Nashik.
      </div>
      <div class="manual-field">
        <label for="order_status">Order Status</label>
        <select id="order_status" name="order_status">
          <option value="pending">Pending</option>
          <option value="confirmed" selected>Confirmed</option>
          <option value="in_preparation">Order Ready</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="manual-field">
        <label for="payment_status">Payment Status</label>
        <select id="payment_status" name="payment_status" onchange="toggleAdvanceAmountField(this.value)">
          <option value="paid" selected>Paid</option>
          <option value="credit">Credit</option>
          <option value="partial">Partial (Advance)</option>
        </select>
      </div>
      <div class="manual-field" id="advanceAmountField" style="display:none;">
        <label for="advance_amount">Advance Amount Received (&#x20b9;)</label>
        <input type="number" id="advance_amount" name="advance_amount" min="0" step="0.01" placeholder="e.g. 500">
      </div>
      <div class="manual-field">
        <label for="payment_method">Payment Mode</label>
        <select id="payment_method" name="payment_method">
          <option value="cod">Cash</option>
          <option value="upi_manual" selected>Bank</option>
          <option value="credit">Credit</option>
        </select>
      </div>
      <div class="manual-field">
        <label for="scheduled_slot">Delivery Date & Time</label>
        <input type="datetime-local" id="scheduled_slot" name="scheduled_slot">
      </div>
      <div class="manual-field">
        <label for="scheduled_slot_label">Slot Label</label>
        <input type="text" id="scheduled_slot_label" name="scheduled_slot_label" maxlength="120" placeholder="e.g. 2 PM - 4 PM">
      </div>
    </div>

    <div class="items-section">
      <h4>Order Items</h4>
      <div class="search-wrapper">
        <input type="text" id="productSearch" class="search-input" placeholder="Search products by name or SKU...">
        <div class="search-dropdown" id="searchDropdown"></div>
      </div>
      <table class="items-table" id="itemsTable" style="display:none">
        <thead>
          <tr>
            <th>Item</th>
            <th style="width:170px">Variant</th>
            <th style="width:80px">Qty</th>
            <th style="width:100px">Unit Price</th>
            <th style="width:100px">Line Total</th>
            <th style="width:50px"></th>
          </tr>
        </thead>
        <tbody id="itemsTableBody"></tbody>
      </table>
      <div class="add-buttons">
        <button type="button" class="btn-icon" onclick="toggleCustomItem()">+ Custom Item</button>
      </div>
      <div id="customItemForm" style="display:none; margin-top:12px; border: 1px solid rgba(128, 0, 31, 0.2); padding: 10px; border-radius: 8px;">
        <div style="display:grid; grid-template-columns: 1fr 80px 100px; gap: 8px;">
          <input type="text" id="customItemName" placeholder="Item name" maxlength="180">
          <input type="number" id="customItemQty" placeholder="Qty" min="1" value="1">
          <input type="number" id="customItemPrice" placeholder="Price" min="0" step="0.01">
        </div>
        <div style="display:flex; gap:8px; margin-top:8px;">
          <button type="button" class="btn-icon" onclick="addCustomItem()">Add Item</button>
          <button type="button" class="btn-icon" onclick="toggleCustomItem()" style="background:#fff1f2;color:#9f1239;">Cancel</button>
        </div>
      </div>
      <div class="order-total-box">
        <div style="grid-column:1/-1">
          <div class="total-row">
            <span class="total-label">Order Total:</span>
            <span class="total-value" id="orderTotal">Rs 0.00</span>
          </div>
        </div>
      </div>
    </div>

    <div class="manual-grid">
      <div class="manual-field">
        <label for="billing_address_line1">Address Line 1</label>
        <input type="text" id="billing_address_line1" name="billing_address_line1" maxlength="190" placeholder="House / Flat / Street">
      </div>
      <div class="manual-field">
        <label for="billing_address_line2">Address Line 2</label>
        <input type="text" id="billing_address_line2" name="billing_address_line2" maxlength="190" placeholder="Area / Landmark">
      </div>
      <div class="manual-field">
        <label for="billing_city">City</label>
        <input type="text" id="billing_city" name="billing_city" maxlength="100" placeholder="Nashik">
      </div>
      <div class="manual-field">
        <label for="billing_state">State</label>
        <input type="text" id="billing_state" name="billing_state" maxlength="100" placeholder="Maharashtra">
      </div>
      <div class="manual-field">
        <label for="billing_postal_code">Postal Code</label>
        <input type="text" id="billing_postal_code" name="billing_postal_code" maxlength="15" placeholder="422001">
      </div>
      <div class="manual-field">
        <label for="delivery_maps_link">Google Maps Link (optional)</label>
        <input type="url" id="delivery_maps_link" name="delivery_maps_link" maxlength="600" placeholder="https://maps.app.goo.gl/...">
      </div>
    </div>

    <div class="manual-field">
      <label for="admin_note">Internal Note (Optional)</label>
      <textarea id="admin_note" name="admin_note" maxlength="800" placeholder="Any fulfilment details for operations team."></textarea>
    </div>

    <p class="manual-note">Mobile number is mandatory and acts as source of truth for customer identity. Existing mobile auto-locks customer name/email for faster POS-style punching.</p>

    <div class="manual-actions">
      <a href="orders.php" class="btn-manual-secondary">Cancel</a>
      <button type="submit" class="btn-manual-primary" id="submitBtn">Create Manual Order</button>
    </div>
  </form>
</div>

<!-- Invoice Print Modal -->
<div id="invoiceModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9000; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:18px; box-shadow:0 20px 60px rgba(0,0,0,0.25); max-width:420px; width:90%; padding:32px 28px; text-align:center;">
    <div style="font-size:2.8rem; margin-bottom:10px;">🎂</div>
    <h3 style="margin:0 0 6px; color:#80001F; font-family:'DM Serif Display',Georgia,serif; font-size:1.4rem;">Order Created!</h3>
    <p id="invoiceModalOrderNum" style="margin:0 0 20px; color:#5f4c55; font-size:0.95rem;"></p>
    <p id="invoiceModalHint" style="margin:0 0 24px; color:#7f6973; font-size:0.88rem;">Would you like to print the invoice now?</p>
    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
      <button id="invoicePrintBtn" onclick="printInvoiceNow()" class="btn-manual-primary" style="min-width:140px; font-size:0.95rem;">🖨 Print Invoice</button>
      <a id="invoiceGoOrders" href="orders.php" class="btn-manual-secondary" style="min-width:140px; text-align:center; font-size:0.95rem;">Go to Orders</a>
    </div>
  </div>
</div>

<script>
  let orderItems = [];
  let searchDebounceTimer;
  let customerLookupTimer;
  let latestSearchResults = [];

  function togglePorterNotice(value) {
    const notice = document.getElementById('porterNoticeManual');
    if (notice) notice.style.display = (value === 'delivery' || value === 'custom_delivery') ? 'block' : 'none';
  }
  function toggleAdvanceAmountField(value) {
    const field = document.getElementById('advanceAmountField');
    if (field) field.style.display = value === 'partial' ? 'block' : 'none';
  }

  const productSearch = document.getElementById('productSearch');
  const searchDropdown = document.getElementById('searchDropdown');
  const itemsTableBody = document.getElementById('itemsTableBody');
  const itemsTable = document.getElementById('itemsTable');
  const orderItemsInput = document.getElementById('orderItemsInput');
  const submitBtn = document.getElementById('submitBtn');
  const customerPhoneInput = document.getElementById('customer_phone');
  const customerNameInput = document.getElementById('customer_name');
  const customerEmailInput = document.getElementById('customer_email');
  const matchedUserIdInput = document.getElementById('matchedUserId');
  const customerLookupStatus = document.getElementById('customerLookupStatus');
  const customerLookupDropdown = document.getElementById('customerLookupDropdown');
  const paymentStatusInput = document.getElementById('payment_status');
  const paymentMethodInput = document.getElementById('payment_method');

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizeVariantOptions(variants) {
    if (!Array.isArray(variants)) {
      return [];
    }

    return variants
      .filter(v => v && Number(v.id) > 0)
      .map(v => {
        const base = Number(v.price) || 0;
        const discounted = Number(v.discount_price) || 0;
        const finalPrice = discounted > 0 && discounted < base ? discounted : base;
        const parts = [String(v.label || '').trim(), String(v.size || '').trim()].filter(Boolean);
        return {
          id: Number(v.id),
          label: parts.join(' - ') || 'Variant',
          price: finalPrice,
        };
      });
  }

  function normalizePhoneInput(value) {
    return String(value || '').replace(/\D+/g, '').slice(0, 15);
  }

  function lockCustomerIdentity() {
    customerNameInput.readOnly = true;
    customerEmailInput.readOnly = true;
    customerNameInput.classList.add('is-locked');
    customerEmailInput.classList.add('is-locked');
  }

  function unlockCustomerIdentity() {
    matchedUserIdInput.value = '0';
    customerNameInput.readOnly = false;
    customerEmailInput.readOnly = false;
    customerNameInput.classList.remove('is-locked');
    customerEmailInput.classList.remove('is-locked');
  }

  function updateCustomerStatus(message, type) {
    customerLookupStatus.textContent = message;
    customerLookupStatus.classList.remove('match', 'new');
    if (type === 'match') {
      customerLookupStatus.classList.add('match');
    }
    if (type === 'new') {
      customerLookupStatus.classList.add('new');
    }
  }

  function closeCustomerDropdown() {
    customerLookupDropdown.classList.remove('active');
    customerLookupDropdown.innerHTML = '';
  }

  function applyCustomerMatch(customer) {
    if (!customer || Number(customer.id) <= 0) {
      return;
    }
    matchedUserIdInput.value = String(customer.id);
    customerPhoneInput.value = normalizePhoneInput(customer.phone || customer.normalized_phone || customerPhoneInput.value);
    customerNameInput.value = String(customer.full_name || '');
    customerEmailInput.value = String(customer.email || '');
    lockCustomerIdentity();
    const ordersInfo = Number(customer.total_orders || 0) > 0
      ? ` | Orders: ${Number(customer.total_orders || 0)}`
      : '';
    updateCustomerStatus(`Customer matched and locked (${customer.phone || customer.normalized_phone || ''})${ordersInfo}`, 'match');
    closeCustomerDropdown();
  }

  function renderCustomerCandidates(customers) {
    if (!Array.isArray(customers) || customers.length === 0) {
      closeCustomerDropdown();
      return;
    }

    customerLookupDropdown.innerHTML = customers.map((c, idx) => `
      <button type="button" class="customer-option" data-customer-index="${idx}">
        <strong>${escapeHtml(c.full_name || 'Unnamed Customer')} | ${escapeHtml(c.phone || '')}</strong>
        <small>${escapeHtml(c.email || 'No email')} | Orders: ${Number(c.total_orders || 0)}${c.last_order_number ? ' | Last: ' + escapeHtml(c.last_order_number) : ''}</small>
      </button>
    `).join('');
    customerLookupDropdown.classList.add('active');

    customerLookupDropdown.onclick = function (e) {
      const option = e.target.closest('.customer-option[data-customer-index]');
      if (!option) {
        return;
      }
      const idx = Number(option.getAttribute('data-customer-index'));
      if (!Number.isInteger(idx) || idx < 0 || idx >= customers.length) {
        return;
      }
      applyCustomerMatch(customers[idx]);
    };
  }

  function lookupCustomerByPhone(rawValue) {
    const phone = normalizePhoneInput(rawValue);
    customerPhoneInput.value = phone;

    if (phone.length < 7) {
      unlockCustomerIdentity();
      updateCustomerStatus('Enter at least 7 digits to search existing customers.', 'new');
      closeCustomerDropdown();
      return;
    }

    fetch(`api/search-customers.php?q=${encodeURIComponent(phone)}&limit=6`)
      .then(r => r.json())
      .then(data => {
        if (!data || data.success !== true) {
          unlockCustomerIdentity();
          updateCustomerStatus('Unable to search customer now. Continue with manual entry.', 'new');
          closeCustomerDropdown();
          return;
        }

        const customers = Array.isArray(data.customers) ? data.customers : [];
        if (data.exact_match && data.exact_customer) {
          applyCustomerMatch(data.exact_customer);
          return;
        }

        if (customers.length > 0) {
          unlockCustomerIdentity();
          updateCustomerStatus('Multiple matches found. Select customer to lock details.', 'new');
          renderCustomerCandidates(customers);
          return;
        }

        unlockCustomerIdentity();
        updateCustomerStatus('New mobile number. Enter customer name and email.', 'new');
        closeCustomerDropdown();
      })
      .catch(() => {
        unlockCustomerIdentity();
        updateCustomerStatus('Lookup failed. Continue manually.', 'new');
        closeCustomerDropdown();
      });
  }

  customerPhoneInput.addEventListener('input', function () {
    clearTimeout(customerLookupTimer);
    customerLookupTimer = setTimeout(() => lookupCustomerByPhone(customerPhoneInput.value), 260);
  });

  customerPhoneInput.addEventListener('blur', function () {
    lookupCustomerByPhone(customerPhoneInput.value);
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.customer-lookup-wrap')) {
      closeCustomerDropdown();
    }
  });

  paymentStatusInput.addEventListener('change', function () {
    if (paymentStatusInput.value === 'credit') {
      paymentMethodInput.value = 'credit';
    } else if (paymentMethodInput.value === 'credit') {
      paymentMethodInput.value = 'upi_manual';
    }
  });

  paymentMethodInput.addEventListener('change', function () {
    if (paymentMethodInput.value === 'credit') {
      paymentStatusInput.value = 'credit';
    } else if (paymentStatusInput.value === 'credit') {
      paymentStatusInput.value = 'paid';
    }
  });

  productSearch.addEventListener('input', function() {
    clearTimeout(searchDebounceTimer);
    const query = this.value.trim();

    if (query.length < 2) {
      searchDropdown.classList.remove('active');
      return;
    }

    searchDebounceTimer = setTimeout(() => {
      fetch(`api/search-products.php?q=${encodeURIComponent(query)}&limit=10`)
        .then(r => r.json())
        .then(data => {
          if (data.success && data.products.length > 0) {
            latestSearchResults = Array.isArray(data.products) ? data.products : [];
            searchDropdown.innerHTML = latestSearchResults.map((p, idx) => `
              <div class="search-result" data-result-index="${idx}">
                <div class="search-result-thumb">
                  ${p.image ? '<img src="' + escapeHtml(p.image) + '" style="width:100%; height:100%; border-radius:4px; object-fit:cover;">' : '📦'}
                </div>
                <div class="search-result-info">
                  <div class="search-result-name">${escapeHtml(p.name)}</div>
                  <div class="search-result-price">Rs ${(Number(p.base_price) || 0).toFixed(2)}</div>
                </div>
              </div>
            `).join('');
            searchDropdown.classList.add('active');
          } else {
            latestSearchResults = [];
            searchDropdown.innerHTML = '<div style="padding:12px; text-align:center; color:#999;">No products found</div>';
            searchDropdown.classList.add('active');
          }
        })
        .catch(e => {
          console.error('Search error:', e);
          searchDropdown.classList.remove('active');
        });
    }, 300);
  });

  document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-wrapper')) {
      searchDropdown.classList.remove('active');
    }
  });

  searchDropdown.addEventListener('click', function(e) {
    const resultNode = e.target.closest('.search-result[data-result-index]');
    if (!resultNode) {
      return;
    }

    const index = Number(resultNode.getAttribute('data-result-index'));
    if (!Number.isInteger(index) || index < 0 || index >= latestSearchResults.length) {
      return;
    }

    addProduct(latestSearchResults[index]);
  });

  function addProduct(product) {
    const productId = Number(product?.id) || 0;
    const productName = String(product?.name || '').trim();
    const basePrice = Number(product?.base_price) || 0;

    if (productId <= 0 || productName === '') {
      return;
    }

    const variantOptions = normalizeVariantOptions(product?.variants);
    const defaultVariant = variantOptions.length > 0 ? variantOptions[0] : null;
    const selectedVariantId = defaultVariant ? defaultVariant.id : null;

    const effectivePrice = defaultVariant
      ? Number(defaultVariant.price)
      : basePrice;

    const existingIndex = orderItems.findIndex((entry) =>
      Number(entry.product_id) === productId &&
      Number(entry.variant_id || 0) === Number(selectedVariantId || 0)
    );
    if (existingIndex >= 0) {
      orderItems[existingIndex].quantity = Math.max(Number(orderItems[existingIndex].quantity) + 1, 1);
      renderItemsTable();
      productSearch.value = '';
      searchDropdown.classList.remove('active');
      return;
    }

    const item = {
      product_id: productId,
      name: productName,
      unit_price: effectivePrice,
      quantity: 1,
      variant_id: selectedVariantId,
      variant_label: defaultVariant ? defaultVariant.label : null,
      variant_options: variantOptions,
      note: ''
    };

    orderItems.push(item);
    renderItemsTable();
    productSearch.value = '';
    searchDropdown.classList.remove('active');
  }

  function toggleCustomItem() {
    const form = document.getElementById('customItemForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
  }

  function addCustomItem() {
    const name = document.getElementById('customItemName').value.trim();
    const qty = parseInt(document.getElementById('customItemQty').value) || 1;
    const price = parseFloat(document.getElementById('customItemPrice').value) || 0;

    if (!name || price <= 0) {
      alert('Please enter item name and valid price');
      return;
    }

    orderItems.push({
      product_id: 0,
      name: name,
      unit_price: price,
      quantity: qty,
      variant_id: null,
      variant_label: null,
      note: ''
    });

    document.getElementById('customItemName').value = '';
    document.getElementById('customItemQty').value = '1';
    document.getElementById('customItemPrice').value = '';
    
    renderItemsTable();
    toggleCustomItem();
  }

  function removeItem(index) {
    orderItems.splice(index, 1);
    renderItemsTable();
  }

  function updateItem(index, field, value) {
    if (field === 'quantity') {
      orderItems[index].quantity = Math.max(parseInt(value) || 1, 1);
    } else if (field === 'unit_price') {
      orderItems[index].unit_price = Math.max(parseFloat(value) || 0, 0);
    }
    renderItemsTable();
  }

  function updateVariant(index, selectedVariantId) {
    if (!orderItems[index]) {
      return;
    }

    const options = Array.isArray(orderItems[index].variant_options)
      ? orderItems[index].variant_options
      : [];

    const normalizedId = Number(selectedVariantId) || 0;
    if (normalizedId <= 0) {
      orderItems[index].variant_id = null;
      orderItems[index].variant_label = null;
      renderItemsTable();
      return;
    }

    const option = options.find(v => Number(v.id) === normalizedId);
    if (!option) {
      return;
    }

    orderItems[index].variant_id = option.id;
    orderItems[index].variant_label = option.label;
    orderItems[index].unit_price = Number(option.price) || 0;
    renderItemsTable();
  }

  function renderItemsTable() {
    if (orderItems.length === 0) {
      itemsTable.style.display = 'none';
      itemsTableBody.innerHTML = '';
      orderItemsInput.value = '[]';
      document.getElementById('orderTotal').textContent = 'Rs 0.00';
      submitBtn.disabled = true;
      return;
    }

    submitBtn.disabled = false;
    itemsTable.style.display = 'table';

    let total = 0;
    itemsTableBody.innerHTML = orderItems.map((item, idx) => {
      const lineTotal = item.quantity * item.unit_price;
      total += lineTotal;
      const variantOptions = Array.isArray(item.variant_options) ? item.variant_options : [];
      const hasVariants = variantOptions.length > 0;
      const selectedVariant = Number(item.variant_id || 0);
      const variantCell = hasVariants
        ? `<select class="item-price-input" style="width:100%;" onchange="updateVariant(${idx}, this.value)">
            ${variantOptions.map(v => `<option value="${v.id}" ${Number(v.id) === selectedVariant ? 'selected' : ''}>${escapeHtml(v.label)}</option>`).join('')}
          </select>`
        : '<span style="font-size:0.8rem;color:#8f7681;">Default</span>';
      return `
        <tr>
          <td class="item-name-cell">${item.name}</td>
          <td>${variantCell}</td>
          <td><input type="number" class="item-qty-input" value="${item.quantity}" min="1" onchange="updateItem(${idx}, 'quantity', this.value)"></td>
          <td><input type="number" class="item-price-input" value="${item.unit_price.toFixed(2)}" min="0" step="0.01" onchange="updateItem(${idx}, 'unit_price', this.value)"></td>
          <td>Rs ${lineTotal.toFixed(2)}</td>
          <td><button type="button" class="item-remove" onclick="removeItem(${idx})">Remove</button></td>
        </tr>
      `;
    }).join('');

    document.getElementById('orderTotal').textContent = 'Rs ' + total.toFixed(2);
    orderItemsInput.value = JSON.stringify(orderItems);
  }

  // Form submission
  document.getElementById('manualOrderForm').addEventListener('submit', function(e) {
    const normalizedPhone = normalizePhoneInput(customerPhoneInput.value);
    customerPhoneInput.value = normalizedPhone;
    if (normalizedPhone.length < 10) {
      e.preventDefault();
      alert('Customer mobile number is mandatory (minimum 10 digits).');
      return;
    }

    if (orderItems.length === 0) {
      e.preventDefault();
      alert('Please add at least one item to the order');
    }
  });

  // Auto-open invoice modal after successful order creation
  (function () {
    const status = <?= json_encode($status) ?>;
    const orderId = <?= json_encode($createdOrderId) ?>;
    const orderNum = <?= json_encode($orderNumber) ?>;
    const paymentStatus = <?= json_encode($createdPaymentStatus) ?>;
    const invoiceAllowed = paymentStatus === 'paid';
    if (status === 'success' && orderId > 0) {
      const modal = document.getElementById('invoiceModal');
      const numEl = document.getElementById('invoiceModalOrderNum');
      const hintEl = document.getElementById('invoiceModalHint');
      const printBtn = document.getElementById('invoicePrintBtn');
      if (numEl && orderNum) {
        numEl.textContent = 'Order: ' + orderNum;
      }
      if (hintEl && !invoiceAllowed) {
        hintEl.textContent = 'Payment is not confirmed yet. Invoice will unlock only after payment is marked Paid.';
      }
      if (printBtn && !invoiceAllowed) {
        printBtn.disabled = true;
        printBtn.style.opacity = '0.45';
        printBtn.style.cursor = 'not-allowed';
        printBtn.title = 'Invoice unlocks only after payment is confirmed.';
      }
      modal.style.display = 'flex';

      window.printInvoiceNow = function () {
        if (!invoiceAllowed) {
          return;
        }
        window.open('order_invoice.php?id=' + orderId, '_blank');
        modal.style.display = 'none';
        window.location.href = 'orders.php';
      };

      document.getElementById('invoiceGoOrders').addEventListener('click', function () {
        modal.style.display = 'none';
      });

      // Close modal on backdrop click
      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          window.location.href = 'orders.php';
        }
      });
    }
  })();
</script>
