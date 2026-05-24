<?php
$pageTitle = 'Payment Receipt';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/../app/bootstrap.php';
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

$receiptService = new \App\Services\PaymentReceiptService();
$receiptRecord = $receiptService->getLatestReceiptForOrder($orderId);
$isFullyPaid = invoice_is_fully_paid($order);
$isEligible = payment_receipt_is_eligible($order);

if (!$isEligible && !is_array($receiptRecord)) {
    http_response_code(422);
  echo 'Payment receipt is available only for advance-payment orders when 50% partial payment is enabled.';
    exit;
}

$autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';

$statusMessage = '';
$statusType = 'info';

if (!$receiptRecord && $isEligible) {
  $issued = $receiptService->issueAdvanceReceipt($orderId, [
    'source_event' => 'admin_receipt_page',
    'source_reference' => 'admin-receipt-page:' . $orderId,
    'issued_by_admin_id' => isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : null,
    'metadata' => [
      'channel' => 'admin_receipt_page',
      'trigger' => 'manual_open',
    ],
  ]);
  if ($issued['success'] || $issued['existing']) {
    $receiptRecord = is_array($issued['receipt'] ?? null) ? $issued['receipt'] : $receiptService->getLatestReceiptForOrder($orderId);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_receipt_email'])) {
  $receiptHtmlForEmail = payment_receipt_render_html($order, $receiptRecord);
    $queued = payment_receipt_queue_email($conn, $order, $receiptHtmlForEmail);
    if ($queued) {
        $statusType = 'success';
        $statusMessage = 'Payment receipt email queued successfully.';
    } else {
        $statusType = 'error';
        $statusMessage = 'Customer email is missing. Could not queue receipt.';
    }
}

$receiptHtml = payment_receipt_render_html($order, $receiptRecord);
require_once __DIR__ . '/layout.php';
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
</style>

<div class="invoice-shell">
  <div class="invoice-actions">
    <a class="invoice-btn ghost" href="order_details.php?id=<?= (int)$orderId ?>">Back to Order</a>
    <a class="invoice-btn ghost" href="order_invoice.php?id=<?= (int)$orderId ?>">Open Invoice</a>
    <button class="invoice-btn" type="button" id="printReceiptBtn">Print Receipt</button>
    <form method="post" style="margin:0">
      <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
      <button class="invoice-btn" type="submit" name="send_receipt_email" value="1">Send Receipt to Email</button>
    </form>
  </div>

  <?php if (!$isFullyPaid): ?>
    <div class="invoice-note">This is an advance payment receipt. Invoice remains locked until full payment is received.</div>
  <?php endif; ?>

  <?php if (is_array($receiptRecord)): ?>
    <div class="invoice-note">Receipt <?= htmlspecialchars((string)($receiptRecord['receipt_number'] ?? 'NA'), ENT_QUOTES, 'UTF-8') ?> issued on <?= htmlspecialchars(invoice_format_datetime((string)($receiptRecord['issued_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?>.</div>
  <?php endif; ?>

  <?php if ($statusMessage !== ''): ?>
    <div class="invoice-note <?= htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="invoice-frame">
    <iframe id="receiptPreview"></iframe>
  </div>
</div>

<script>
  (function() {
    var html = <?= json_encode($receiptHtml, JSON_UNESCAPED_SLASHES) ?>;
    var autoPrint = <?= $autoPrint ? 'true' : 'false' ?>;
    var frame = document.getElementById('receiptPreview');
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

    var printBtn = document.getElementById('printReceiptBtn');
    printBtn.addEventListener('click', function() {
      openPrintDialog();
    });
  })();
</script>
