<?php
$pageTitle = 'Revise Order';
require_once __DIR__ . '/layout.php';
require_admin_permission('orders');
require_once __DIR__ . '/includes/db.php';

use App\Services\OrderRevisionService;
use App\Core\Database;

$db  = Database::getInstance();
$svc = new OrderRevisionService($db);

$msg   = '';
$msgOk = true;

// POST: submit revision
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
        $msg   = 'Invalid request token.';
        $msgOk = false;
    } else {
        $orderId      = (int)($_POST['order_id'] ?? 0);
        $newTotal     = round((float)str_replace(',', '', (string)($_POST['new_grand_total'] ?? 0)), 2);
        $revType      = trim((string)($_POST['revision_type'] ?? ''));
        $reason       = trim((string)($_POST['revision_reason'] ?? ''));
        $resolution   = trim((string)($_POST['downgrade_resolution'] ?? ''));
        $adminId      = (int)($_SESSION['admin'] ?? 0);
        $adminName    = (string)($_SESSION['admin_name'] ?? '');

        // Build new_items_snapshot from POST
        $itemNames    = (array)($_POST['item_name']     ?? []);
        $itemPrices   = (array)($_POST['item_price']    ?? []);
        $itemQtys     = (array)($_POST['item_qty']      ?? []);
        $newSnapshot  = [];
        foreach ($itemNames as $i => $name) {
            if (trim($name) === '') continue;
            $newSnapshot[] = [
                'product_name' => trim($name),
                'unit_price'   => round((float)str_replace(',', '', (string)($itemPrices[$i] ?? 0)), 2),
                'quantity'     => max(1, (int)($itemQtys[$i] ?? 1)),
            ];
        }

        $result = $svc->submitRevision([
            'order_id'            => $orderId,
            'revision_type'       => $revType,
            'new_grand_total'     => $newTotal,
            'new_items_snapshot'  => $newSnapshot,
            'revision_reason'     => $reason,
            'downgrade_resolution'=> $resolution,
            'admin_id'            => $adminId,
            'admin_name'          => $adminName,
        ]);
        $msg   = $result['message'] ?? '';
        $msgOk = (bool)($result['success'] ?? false);
        if ($msgOk && isset($result['revision_id'])) {
            header('Location: order_revision_history.php?order_id=' . $orderId . '&highlight=' . $result['revision_id']);
            exit;
        }
    }
}

// Load order for the form
$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$order   = null;
$items   = [];
if ($orderId > 0) {
    $order = $db->fetchOne(
        "SELECT id, order_number, customer_name, customer_phone,
                grand_total, COALESCE(revised_grand_total, grand_total) AS effective_total,
                order_status, payment_status, current_revision_no
           FROM orders WHERE id = :id LIMIT 1",
        ['id' => $orderId]
    );
    if ($order) {
        $items = $db->fetchAll(
            'SELECT * FROM order_items WHERE order_id = :oid ORDER BY id',
            ['oid' => $orderId]
        ) ?: [];
    }
}
?>
<div class="page-content">
  <div class="page-header">
    <h1 class="page-title">Revise Order</h1>
  </div>

  <?php if ($msg !== ''): ?>
    <div class="alert <?= $msgOk ? 'alert-success' : 'alert-danger' ?>" style="margin-bottom:1rem">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <!-- Order search -->
  <form method="get" class="card mb-3" style="padding:.75rem 1rem">
    <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
      <div style="flex:1;min-width:160px">
        <label style="font-size:.8rem;font-weight:600">Order ID</label>
        <input type="number" name="order_id" value="<?= $orderId ?: '' ?>" placeholder="Enter order ID"
               style="display:block;width:100%;padding:.4rem .6rem;border:1px solid #ccc;border-radius:5px">
      </div>
      <button type="submit" class="btn btn-primary">Load Order</button>
    </div>
  </form>

  <?php if ($orderId > 0 && $order === null): ?>
    <div class="card" style="padding:1.5rem;text-align:center;color:#c0392b">Order #<?= $orderId ?> not found.</div>
  <?php endif; ?>

  <?php if ($order): ?>
  <!-- Order summary -->
  <div class="card mb-3">
    <div class="card-body" style="display:flex;gap:2rem;flex-wrap:wrap">
      <div><strong style="font-size:.78rem;color:#888">ORDER #</strong><br><?= htmlspecialchars((string)$order['order_number']) ?></div>
      <div><strong style="font-size:.78rem;color:#888">CUSTOMER</strong><br><?= htmlspecialchars((string)$order['customer_name']) ?></div>
      <div><strong style="font-size:.78rem;color:#888">PHONE</strong><br><?= htmlspecialchars((string)$order['customer_phone']) ?></div>
      <div><strong style="font-size:.78rem;color:#888">STATUS</strong><br><?= htmlspecialchars((string)$order['order_status']) ?></div>
      <div><strong style="font-size:.78rem;color:#888">ORIGINAL TOTAL</strong><br>₹<?= number_format((float)$order['grand_total'], 2) ?></div>
      <div><strong style="font-size:.78rem;color:#888">EFFECTIVE TOTAL</strong><br><strong>₹<?= number_format((float)$order['effective_total'], 2) ?></strong></div>
      <div><strong style="font-size:.78rem;color:#888">REVISION #</strong><br><?= (int)$order['current_revision_no'] ?></div>
    </div>
  </div>

  <!-- Revision form -->
  <form method="post" id="revisionForm" class="card">
    <div class="card-header"><strong>Submit Revision</strong></div>
    <div class="card-body" style="padding:1.25rem">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <input type="hidden" name="order_id" value="<?= $orderId ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div>
          <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.3rem">Revision Type *</label>
          <select name="revision_type" required
                  style="width:100%;padding:.45rem .7rem;border:1px solid #ccc;border-radius:6px">
            <option value="">— Select —</option>
            <option value="upgrade">Upgrade (price increase)</option>
            <option value="downgrade">Downgrade (price decrease)</option>
            <option value="topper_addition">Topper Addition</option>
            <option value="flavor_change">Flavor Change</option>
            <option value="delivery_change">Delivery Change</option>
            <option value="customer_request">Customer Request</option>
            <option value="admin_adjustment">Admin Adjustment</option>
          </select>
        </div>
        <div>
          <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.3rem">Downgrade Resolution</label>
          <select name="downgrade_resolution" id="downgradeResolution"
                  style="width:100%;padding:.45rem .7rem;border:1px solid #ccc;border-radius:6px">
            <option value="">— N/A (upgrade) —</option>
            <option value="refund">Cash Refund</option>
            <option value="store_credit">Issue Store Credit</option>
          </select>
          <div id="resolutionHint" style="font-size:.75rem;color:#888;margin-top:.25rem"></div>
        </div>
      </div>

      <div style="margin-bottom:1rem">
        <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.3rem">Revision Reason *</label>
        <textarea name="revision_reason" required rows="2"
                  style="width:100%;padding:.45rem .7rem;border:1px solid #ccc;border-radius:6px;font-family:inherit;resize:vertical"
                  placeholder="Why is this order being revised?"></textarea>
      </div>

      <!-- Line items editor -->
      <div style="margin-bottom:1rem">
        <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.5rem">Updated Items (new snapshot)</label>
        <table id="itemTable" style="width:100%;border-collapse:collapse;font-size:.875rem">
          <thead>
            <tr style="background:#f5f0f1">
              <th style="padding:.4rem .6rem;text-align:left">Item Name</th>
              <th style="padding:.4rem .6rem;text-align:right;width:120px">Unit Price (₹)</th>
              <th style="padding:.4rem .6rem;text-align:right;width:80px">Qty</th>
              <th style="padding:.4rem .6rem;text-align:right;width:110px">Line Total</th>
              <th style="padding:.4rem .6rem;width:44px"></th>
            </tr>
          </thead>
          <tbody id="itemBody">
            <?php foreach ($items as $item): ?>
            <tr class="item-row">
              <td style="padding:.3rem .4rem">
                <input type="text" name="item_name[]" value="<?= htmlspecialchars((string)$item['product_name']) ?>"
                       required style="width:100%;padding:.3rem .4rem;border:1px solid #ddd;border-radius:4px" class="item-name">
              </td>
              <td style="padding:.3rem .4rem">
                <input type="number" name="item_price[]" value="<?= number_format((float)$item['unit_price'], 2, '.', '') ?>"
                       step="0.01" min="0" required
                       style="width:100%;padding:.3rem .4rem;border:1px solid #ddd;border-radius:4px;text-align:right" class="item-price">
              </td>
              <td style="padding:.3rem .4rem">
                <input type="number" name="item_qty[]" value="<?= (int)$item['quantity'] ?>"
                       step="1" min="1" required
                       style="width:100%;padding:.3rem .4rem;border:1px solid #ddd;border-radius:4px;text-align:right" class="item-qty">
              </td>
              <td style="padding:.3rem .6rem;text-align:right;font-weight:600" class="line-total">
                ₹<?= number_format((float)$item['unit_price'] * max(1,(int)$item['quantity']), 2) ?>
              </td>
              <td style="padding:.3rem .4rem;text-align:center">
                <button type="button" class="btn btn-xs btn-danger rm-row">×</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="button" id="addItem" class="btn btn-sm btn-secondary" style="margin-top:.5rem">+ Add Item</button>
      </div>

      <!-- New total -->
      <div style="display:flex;align-items:center;gap:1.5rem;padding:1rem;background:#fdf5f7;border-radius:8px;margin-bottom:1rem">
        <div>
          <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.25rem">New Grand Total *</label>
          <div style="display:flex;align-items:center;gap:.5rem">
            <span style="font-size:1.1rem;font-weight:700;color:#80001F">₹</span>
            <input type="number" id="newGrandTotal" name="new_grand_total" step="0.01" min="0" required
                   value="<?= number_format((float)$order['effective_total'], 2, '.', '') ?>"
                   style="padding:.45rem .7rem;border:1px solid #ccc;border-radius:6px;font-size:1.1rem;font-weight:700;width:180px">
          </div>
          <div style="font-size:.75rem;color:#888;margin-top:.2rem">
            Current effective total: <strong>₹<?= number_format((float)$order['effective_total'], 2) ?></strong>
          </div>
        </div>
        <div id="diffDisplay" style="font-size:1.1rem;font-weight:700;padding:.5rem 1rem;border-radius:6px"></div>
      </div>

      <div style="display:flex;gap:.75rem">
        <button type="submit" class="btn btn-primary">Submit Revision</button>
        <a href="orders.php" class="btn btn-secondary">Cancel</a>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
(function(){
  var currentTotal = <?= $order ? number_format((float)$order['effective_total'], 2, '.', '') : '0' ?>;

  function recomputeLineTotals() {
    var rows = document.querySelectorAll('#itemBody .item-row');
    var sum = 0;
    rows.forEach(function(row) {
      var price = parseFloat(row.querySelector('.item-price').value) || 0;
      var qty   = parseInt(row.querySelector('.item-qty').value)   || 1;
      var lt    = price * qty;
      sum += lt;
      row.querySelector('.line-total').textContent = '₹' + lt.toFixed(2);
    });
    // Update grand total field with sum suggestion
    return sum;
  }

  function updateDiff() {
    var newTotal = parseFloat(document.getElementById('newGrandTotal').value) || 0;
    var diff     = newTotal - currentTotal;
    var el       = document.getElementById('diffDisplay');
    if (Math.abs(diff) < 0.01) {
      el.textContent = 'No change';
      el.style.background = '#eee';
      el.style.color = '#666';
    } else if (diff > 0) {
      el.textContent = '+₹' + diff.toFixed(2) + ' (Upgrade)';
      el.style.background = '#e8f5e9';
      el.style.color = '#27ae60';
    } else {
      el.textContent = '−₹' + Math.abs(diff).toFixed(2) + ' (Downgrade)';
      el.style.background = '#fdecea';
      el.style.color = '#c0392b';
    }
    // Show resolution hint
    var resHint = document.getElementById('resolutionHint');
    if (diff < -0.01) {
      resHint.textContent = 'Required: choose refund or store credit for the ₹' + Math.abs(diff).toFixed(2) + ' decrease.';
      document.getElementById('downgradeResolution').required = true;
    } else {
      resHint.textContent = '';
      document.getElementById('downgradeResolution').required = false;
    }
  }

  document.getElementById('newGrandTotal').addEventListener('input', updateDiff);
  updateDiff();

  // Auto-fill grand total from item sum
  document.getElementById('itemBody').addEventListener('input', function(e) {
    if (e.target.classList.contains('item-price') || e.target.classList.contains('item-qty')) {
      var sum = recomputeLineTotals();
      document.getElementById('newGrandTotal').value = sum.toFixed(2);
      updateDiff();
    }
  });

  // Add row
  document.getElementById('addItem').addEventListener('click', function() {
    var tbody = document.getElementById('itemBody');
    var tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML = '<td style="padding:.3rem .4rem"><input type="text" name="item_name[]" required class="item-name" style="width:100%;padding:.3rem .4rem;border:1px solid #ddd;border-radius:4px" placeholder="Item name"></td>'
      + '<td style="padding:.3rem .4rem"><input type="number" name="item_price[]" value="0" step="0.01" min="0" required class="item-price" style="width:100%;padding:.3rem .4rem;border:1px solid #ddd;border-radius:4px;text-align:right"></td>'
      + '<td style="padding:.3rem .4rem"><input type="number" name="item_qty[]" value="1" step="1" min="1" required class="item-qty" style="width:100%;padding:.3rem .4rem;border:1px solid #ddd;border-radius:4px;text-align:right"></td>'
      + '<td style="padding:.3rem .6rem;text-align:right;font-weight:600" class="line-total">₹0.00</td>'
      + '<td style="padding:.3rem .4rem;text-align:center"><button type="button" class="btn btn-xs btn-danger rm-row">×</button></td>';
    tbody.appendChild(tr);
  });

  // Remove row
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('rm-row')) {
      var row = e.target.closest('.item-row');
      if (document.querySelectorAll('#itemBody .item-row').length > 1) {
        row.remove();
        var sum = recomputeLineTotals();
        document.getElementById('newGrandTotal').value = sum.toFixed(2);
        updateDiff();
      }
    }
  });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
