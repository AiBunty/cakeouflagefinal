<?php
$pageTitle = 'Order Invoice';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice_helpers.php';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo 'Invalid order id';
    exit;
}

$order = invoice_fetch_order($conn, $orderId);
if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

$autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
$isPaymentConfirmed = (string)($order['payment_status'] ?? '') === 'paid';

$statusMessage = '';
$statusType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invoice_email'])) {
    if ((string)($order['payment_status'] ?? '') !== 'paid') {
        $statusType = 'error';
        $statusMessage = 'Invoice email is allowed only after payment is confirmed as paid.';
    } else {
        $html = invoice_render_html($order);
        $queued = invoice_queue_email($conn, $order, $html);
        if ($queued) {
            $statusType = 'success';
            $statusMessage = 'Invoice email queued successfully.';
        } else {
            $statusType = 'error';
            $statusMessage = 'Customer email is missing. Could not queue invoice.';
        }
    }
}

$invoiceHtml = $isPaymentConfirmed ? invoice_render_html($order) : '';
include __DIR__ . '/layout.php';
?>
<style>
  .invoice-shell { display: grid; gap: 14px; }
  .invoice-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .invoice-btn {
    border: 1px solid #111;
    background: #111;
    color: #fff;
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
  }
  .invoice-btn.is-disabled,
  .invoice-btn:disabled {
    opacity: .45;
    background: #9ca3af;
    border-color: #9ca3af;
    cursor: not-allowed;
    pointer-events: none;
  }
  .invoice-btn.ghost {
    color: #111;
    background: #fff;
  }
  .invoice-note {
    border-radius: 10px;
    border: 1px solid #d4d4d4;
    padding: 10px 12px;
    font-size: 0.85rem;
    background: #fafafa;
    color: #1f2937;
  }
  .invoice-note.error {
    border-color: #fecaca;
    background: #fef2f2;
    color: #991b1b;
  }
  .invoice-note.success {
    border-color: #bbf7d0;
    background: #ecfdf3;
    color: #166534;
  }
  .invoice-frame {
    border: 1px solid rgba(0, 0, 0, 0.25);
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
  }
  .invoice-frame iframe {
    width: 100%;
    min-height: 860px;
    border: 0;
    display: block;
  }
  .invoice-frame--locked {
    min-height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    background: #f3f4f6;
    font-weight: 600;
    text-align: center;
    padding: 20px;
  }
</style>

<div class="invoice-shell">
  <div class="invoice-actions">
    <a class="invoice-btn ghost" href="order_details.php?id=<?= (int)$orderId ?>">Back to Order</a>
    <button class="invoice-btn <?= $isPaymentConfirmed ? '' : 'is-disabled' ?>" type="button" id="printInvoiceBtn" <?= $isPaymentConfirmed ? '' : 'disabled title="Invoice unlocks only after payment is confirmed."' ?>>Print Invoice</button>
    <form method="post" style="margin:0">
      <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
      <button class="invoice-btn <?= $isPaymentConfirmed ? '' : 'is-disabled' ?>" type="submit" name="send_invoice_email" value="1" <?= $isPaymentConfirmed ? '' : 'disabled title="Invoice unlocks only after payment is confirmed."' ?>>Send Invoice to Email</button>
    </form>
  </div>

  <?php if (!$isPaymentConfirmed): ?>
    <div class="invoice-note error">Payment is not confirmed yet. Invoice preview and print are locked until payment status is marked Paid.</div>
  <?php endif; ?>

  <?php if ($statusMessage !== ''): ?>
    <div class="invoice-note <?= htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($isPaymentConfirmed): ?>
    <div class="invoice-frame">
      <iframe id="invoicePreview"></iframe>
    </div>
  <?php else: ?>
    <div class="invoice-frame invoice-frame--locked">Invoice preview is unavailable until payment confirmation.</div>
  <?php endif; ?>
</div>

<script>
  (function() {
    var isPaymentConfirmed = <?= $isPaymentConfirmed ? 'true' : 'false' ?>;
    if (!isPaymentConfirmed) {
      return;
    }

    var html = <?= json_encode($invoiceHtml, JSON_UNESCAPED_SLASHES) ?>;
    var autoPrint = <?= $autoPrint ? 'true' : 'false' ?>;
    var frame = document.getElementById('invoicePreview');
    var doc = frame.contentDocument || frame.contentWindow.document;
    doc.open();
    doc.write(html);
    doc.close();

    function openPrintDialog() {
      frame.contentWindow.focus();
      frame.contentWindow.print();
    }

    if (autoPrint) {
      setTimeout(openPrintDialog, 250);
    }

    var printBtn = document.getElementById('printInvoiceBtn');
    printBtn.addEventListener('click', function() {
      openPrintDialog();
    });
  })();
</script>
