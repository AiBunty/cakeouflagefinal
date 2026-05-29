<?php
declare(strict_types=1);

$pageTitle = 'Order Details';
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice_helpers.php';

use App\Services\OrderStateManager;
use App\Services\PaymentReceiptService;

$orderId = max((int)($_GET['id'] ?? 0), 0);
$returnTo = trim((string)($_GET['return_to'] ?? ''));
$backHref = 'orders.php';
if ($returnTo !== '') {
    $parts = parse_url($returnTo);
    if (is_array($parts)) {
        $path = basename((string)($parts['path'] ?? ''));
        $safePages = ['orders.php', 'sales_register.php', 'collection_report.php'];
        if (in_array($path, $safePages, true)) {
            $query = isset($parts['query']) ? trim((string)$parts['query']) : '';
            $backHref = $path . ($query !== '' ? ('?' . $query) : '');
        }
    }
}

$canOrderReject = admin_has_permission('order_reject');
$canOrderRefund = admin_has_permission('order_refund');
$canOrderEdit = admin_has_permission('order_edit');
$canManualOrders = admin_has_permission('manual_orders');
$canRefundApproval = admin_has_permission('can_approve_refund') || admin_has_permission('can_force_refund');
$canOrderDelete = admin_has_permission('order_delete');
$isSuperAdmin = admin_is_super_admin();

function od_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function od_money($value): string
{
    return 'Rs ' . number_format((float)$value, 2);
}

function od_first_non_empty(...$values): string
{
    foreach ($values as $value) {
        if (trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }
    return '';
}

function od_labelize(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '-';
    }
    return ucwords(str_replace('_', ' ', $value));
}

function od_status_label(string $status): string
{
    $map = [
        'pending_payment' => 'Pending Payment',
        'payment_under_review' => 'Payment Review',
        'awaiting_confirmation' => 'Awaiting Confirmation',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready_for_pickup' => 'Ready',
        'out_for_delivery' => 'Out For Delivery',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
        'refund_requested' => 'Refund Requested',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially Refunded',
        'fully_refunded' => 'Fully Refunded',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'credit' => 'Credit',
        'failed' => 'Failed',
        'under_review' => 'Under Review',
    ];
    return $map[$status] ?? od_labelize($status);
}

function od_status_class(string $status): string
{
    return 'od-chip-status-' . preg_replace('/[^a-z_]/', '', strtolower($status));
}

function od_format_datetime(?string $value, string $fallback = '-'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $fallback;
    }
    return date('d M Y, h:i A', $ts);
}

function od_format_date(?string $value, string $fallback = '-'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $fallback;
    }
    return date('d M Y', $ts);
}

function od_build_action_form(int $orderId, string $status, string $label, string $class, string $redirectTo): string
{
    return '<form method="POST" action="update_order_status.php">'
        . '<input type="hidden" name="order_id" value="' . $orderId . '">'
        . '<input type="hidden" name="status" value="' . od_h($status) . '">'
        . '<input type="hidden" name="redirect_to" value="' . od_h($redirectTo) . '">'
        . '<button type="submit" class="od-btn ' . $class . '">' . od_h($label) . '</button>'
        . '</form>';
}

$order = null;
if ($orderId > 0) {
    $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

if (!is_array($order)) {
    ?>
    <link rel="stylesheet" href="assets/css/admin-order-details.css?v=<?php echo (int)(@filemtime(__DIR__ . '/assets/css/admin-order-details.css') ?: time()); ?>">
    <div class="od-shell">
      <section class="od-card">
        <h2 class="od-card-title">Order not found</h2>
        <p>The requested order could not be loaded.</p>
        <a href="<?php echo od_h($backHref); ?>" class="od-btn-link od-btn-link-primary">Back to Orders</a>
      </section>
    </div>
    </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$stateManager = new OrderStateManager();
$currentOrderStatus = (string)($order['order_status'] ?? 'pending_payment');
$currentPaymentStatus = (string)($order['payment_status'] ?? 'pending');
$allowedTransitions = $stateManager->getAllowedTransitions($currentOrderStatus);
$governance = $stateManager->getAllowedActions($currentOrderStatus, $currentPaymentStatus);
$canConfirmPayment = (bool)($governance['can_confirm_payment'] ?? false);
$isRefundFinal = (bool)($governance['is_refund_final'] ?? false);

$hasArchivedColumn = false;
$archivedColumnRes = $conn->query("SHOW COLUMNS FROM orders LIKE 'is_archived'");
if ($archivedColumnRes && $archivedColumnRes->fetch_assoc()) {
    $hasArchivedColumn = true;
}
$isArchived = $hasArchivedColumn && (int)($order['is_archived'] ?? 0) === 1;

$canGenerateInvoice = invoice_is_fully_paid($order);
$canOpenReceipt = payment_receipt_is_eligible($order);
$receiptHistory = [];
$latestReceipt = null;
try {
    $paymentReceiptService = new PaymentReceiptService();
    $receiptHistory = $paymentReceiptService->getReceiptHistoryForOrder((int)$order['id']);
    $latestReceipt = $receiptHistory[0] ?? null;
} catch (Throwable $receiptErr) {
    error_log('[order_details][receipt-history] ' . $receiptErr->getMessage());
}

$itemRows = [];
$itemStmt = $conn->prepare(
    'SELECT oi.*, '
    . 'COALESCE(NULLIF(p.featured_image, ""), (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.id ASC LIMIT 1)) AS product_image '
    . 'FROM order_items oi '
    . 'LEFT JOIN products p ON p.id = oi.product_id '
    . 'WHERE oi.order_id = ? '
    . 'ORDER BY oi.id ASC'
);
if ($itemStmt) {
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $itemResult = $itemStmt->get_result();
    while ($itemResult && ($row = $itemResult->fetch_assoc())) {
        $itemRows[] = $row;
    }
    $itemStmt->close();
}

$paymentMethodLabelMap = [
    'upi_manual' => 'UPI / Bank',
    'cod' => 'Cash',
    'gateway' => 'Gateway',
    'credit' => 'Credit',
];
$fulfilmentLabelMap = [
    'delivery' => 'Delivery',
    'pickup' => 'Pickup',
    'custom_delivery' => 'Custom Delivery',
];
$paymentMethodLabel = $paymentMethodLabelMap[(string)($order['payment_method'] ?? '')] ?? od_labelize((string)($order['payment_method'] ?? ''));
$fulfilmentLabel = $fulfilmentLabelMap[(string)($order['fulfilment_mode'] ?? '')] ?? od_labelize((string)($order['fulfilment_mode'] ?? ''));

$slotLabel = od_first_non_empty((string)($order['scheduled_slot_label'] ?? ''), (string)($order['scheduled_slot'] ?? ''));
$deliveryDateLabel = !empty($order['scheduled_slot']) ? od_format_date((string)$order['scheduled_slot']) : '-';
$deliveryTimeLabel = !empty($order['scheduled_slot']) ? date('h:i A', strtotime((string)$order['scheduled_slot'])) : '-';

$customerAddress = trim(implode(', ', array_filter([
    od_first_non_empty((string)($order['billing_address_line1'] ?? ''), (string)($order['address_line1'] ?? ''), (string)($order['delivery_address_line1'] ?? '')),
    od_first_non_empty((string)($order['billing_address_line2'] ?? ''), (string)($order['address_line2'] ?? ''), (string)($order['delivery_address_line2'] ?? '')),
    od_first_non_empty((string)($order['billing_city'] ?? ''), (string)($order['city'] ?? ''), (string)($order['delivery_city'] ?? '')),
    od_first_non_empty((string)($order['billing_state'] ?? ''), (string)($order['state'] ?? ''), (string)($order['delivery_state'] ?? '')),
    od_first_non_empty((string)($order['billing_postal_code'] ?? ''), (string)($order['postal_code'] ?? ''), (string)($order['delivery_postal_code'] ?? '')),
]))) ?: '-';
$mapHref = $customerAddress !== '-' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($customerAddress) : '';

$channelLabel = od_labelize((string)($order['order_source'] ?? 'retail'));
$modeLabel = trim((string)($order['order_mode'] ?? '')) !== '' ? od_labelize((string)$order['order_mode']) : $channelLabel;

$isPaidState = in_array($currentPaymentStatus, ['paid', 'credit'], true);
$refundEligibleStatuses = ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed'];
$alreadyRefundedStatuses = ['partially_refunded', 'fully_refunded', 'refunded'];
$isRefundEligible = in_array($currentOrderStatus, $refundEligibleStatuses, true)
    && $isPaidState
    && !in_array($currentOrderStatus, $alreadyRefundedStatuses, true);
$isAlreadyRefunded = in_array($currentOrderStatus, $alreadyRefundedStatuses, true)
    || in_array($currentPaymentStatus, ['refunded', 'partially_refunded'], true);

$refundAmount = max((float)($order['total_refunded'] ?? 0), (float)($order['refund_amount'] ?? 0));
$discountTotal = (float)($order['discount_total'] ?? 0);
$taxTotal = (float)($order['tax_total'] ?? 0);
$deliveryFee = (float)($order['delivery_fee'] ?? 0);

$specialInstructions = [];
if (trim((string)($order['admin_note'] ?? '')) !== '') {
    $specialInstructions[] = trim((string)$order['admin_note']);
}
foreach ($itemRows as $itemRow) {
    foreach (['cake_message', 'customisation_note'] as $noteKey) {
        $note = trim((string)($itemRow[$noteKey] ?? ''));
        if ($note !== '') {
            $specialInstructions[] = $note;
        }
    }
}
$specialInstructions = array_values(array_unique($specialInstructions));

$paymentReference = od_first_non_empty(
    (string)($order['payment_reference'] ?? ''),
    is_array($latestReceipt) ? (string)($latestReceipt['settlement_reference'] ?? '') : '',
    is_array($latestReceipt) ? (string)($latestReceipt['receipt_number'] ?? '') : ''
);
$collectedByLabel = od_first_non_empty(
    is_array($latestReceipt) ? (string)($latestReceipt['issued_by_name'] ?? '') : '',
    !empty($order['payment_confirmed_by_admin_id']) ? ('Admin #' . (int)$order['payment_confirmed_by_admin_id']) : '',
    'System'
);

$financeBadge = (string)($governance['finance_badge'] ?? '');
$summaryCards = [
    ['icon' => 'OD', 'title' => 'Order Date', 'value' => od_format_date((string)($order['created_at'] ?? '')), 'sub' => od_format_datetime((string)($order['created_at'] ?? ''))],
    ['icon' => 'FUL', 'title' => 'Fulfillment', 'value' => $fulfilmentLabel, 'sub' => $slotLabel !== '' ? $slotLabel : 'Slot pending'],
    ['icon' => 'PMT', 'title' => 'Payment Method', 'value' => $paymentMethodLabel, 'sub' => $channelLabel],
  ['icon' => 'STS', 'title' => 'Payment', 'value' => od_status_label($currentPaymentStatus), 'sub' => $financeBadge !== '' ? od_labelize($financeBadge) : od_status_label($currentOrderStatus)],
  ['icon' => 'TOT', 'title' => 'Order Total', 'value' => od_money((float)($order['grand_total'] ?? 0)), 'sub' => $refundAmount > 0 ? ('Refunded ' . od_money($refundAmount)) : 'Accounting safe'],
];

$timelineSteps = [
    ['label' => 'Created', 'note' => od_format_datetime((string)($order['created_at'] ?? ''))],
    ['label' => 'Payment Verified', 'note' => $currentPaymentStatus === 'paid' || $currentPaymentStatus === 'credit' ? od_status_label($currentPaymentStatus) : 'Pending confirmation'],
    ['label' => 'Preparing', 'note' => 'Kitchen work started'],
    ['label' => 'Ready', 'note' => 'Awaiting pickup / dispatch'],
    ['label' => 'Delivered', 'note' => in_array($currentOrderStatus, ['completed', 'delivered'], true) ? 'Fulfillment completed' : 'Pending delivery'],
];

$timelineCurrentIndex = 0;
$timelineStatusOrder = ['pending_payment', 'payment_under_review', 'awaiting_confirmation', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed'];
$currentIndexLookup = array_search($currentOrderStatus, $timelineStatusOrder, true);
if ($currentIndexLookup !== false) {
    if ($currentIndexLookup >= 7) {
        $timelineCurrentIndex = 4;
    } elseif ($currentIndexLookup >= 5) {
        $timelineCurrentIndex = 3;
    } elseif ($currentIndexLookup >= 4) {
        $timelineCurrentIndex = 2;
    } elseif ($currentIndexLookup >= 3) {
        $timelineCurrentIndex = 1;
    }
}

$orderDetailsCssVersion = @filemtime(__DIR__ . '/assets/css/admin-order-details.css') ?: time();
$orderDetailsJsVersion = @filemtime(__DIR__ . '/assets/js/admin-order-details.js') ?: time();
$currentUri = 'order_details.php?id=' . (int)$orderId . ($returnTo !== '' ? '&return_to=' . rawurlencode($returnTo) : '');

$desktopActions = [];
if (!$isArchived && in_array($currentOrderStatus, ['pending_payment', 'payment_under_review', 'awaiting_confirmation'], true)) {
    if ($canManualOrders) {
        $desktopActions[] = '<a href="manual_order.php?order_id=' . (int)$orderId . '" class="od-btn-link od-btn-link-secondary">Edit Entry</a>';
    }
  if ($canConfirmPayment) {
        $desktopActions[] = '<button type="button" class="od-btn od-btn-success" data-od-confirm-payment="1" data-order-id="' . (int)$orderId . '" data-expected-amount="' . od_h((string)number_format((float)($order['grand_total'] ?? 0), 2, '.', '')) . '">Confirm Payment</button>';
    }
    if ($canOrderReject) {
        $desktopActions[] = '<button type="button" class="od-btn od-btn-warning" data-od-cancel-order="1" data-order-id="' . (int)$orderId . '">Cancel</button>';
    }
}
if (!$isArchived && $currentOrderStatus === 'confirmed' && in_array('preparing', $allowedTransitions, true) && $canOrderEdit) {
    $desktopActions[] = od_build_action_form((int)$orderId, 'preparing', 'Mark Preparing', 'od-btn-primary', $currentUri);
}
if (!$isArchived && $currentOrderStatus === 'preparing' && in_array('ready_for_pickup', $allowedTransitions, true) && $canOrderEdit) {
    $desktopActions[] = od_build_action_form((int)$orderId, 'ready_for_pickup', 'Mark Ready', 'od-btn-primary', $currentUri);
}
if (!$isArchived && in_array($currentOrderStatus, ['ready_for_pickup', 'out_for_delivery'], true) && in_array('delivered', $allowedTransitions, true) && $canOrderEdit) {
    $desktopActions[] = od_build_action_form((int)$orderId, 'delivered', 'Mark Delivered', 'od-btn-primary', $currentUri);
}
if ($isRefundEligible && $canOrderRefund) {
    $desktopActions[] = '<button type="button" class="od-btn od-btn-secondary" data-od-scroll-refund="1">Refund</button>';
}
if ($canGenerateInvoice) {
    $desktopActions[] = '<a href="order_invoice.php?id=' . (int)$orderId . '" class="od-btn-link od-btn-link-muted">Invoice</a>';
}
if ($canOrderDelete) {
    if ($isArchived) {
        $desktopActions[] = '<button type="button" class="od-btn od-btn-secondary" data-od-destructive="restore" data-order-id="' . (int)$orderId . '" data-order-number="' . od_h((string)($order['order_number'] ?? '')) . '">Restore</button>';
    } elseif (in_array($currentOrderStatus, ['cancelled', 'rejected'], true)) {
        $desktopActions[] = '<button type="button" class="od-btn od-btn-secondary" data-od-destructive="archive" data-order-id="' . (int)$orderId . '" data-order-number="' . od_h((string)($order['order_number'] ?? '')) . '">Archive</button>';
    }
    if ($isSuperAdmin) {
        $desktopActions[] = '<button type="button" class="od-btn od-btn-danger" data-od-destructive="force_purge" data-order-id="' . (int)$orderId . '" data-order-number="' . od_h((string)($order['order_number'] ?? '')) . '">Delete Permanently</button>';
    }
}
if ($isAlreadyRefunded || $isRefundFinal) {
    $desktopActions = ['<span class="od-pill">View only - refund already processed</span>'];
}

$stickyPrimary = '<a href="production_plan.php?order_id=' . (int)$orderId . '" class="od-btn-link od-btn-link-primary">Production</a>';
$stickyInvoice = $canGenerateInvoice
    ? '<a href="order_invoice.php?id=' . (int)$orderId . '" class="od-btn-link od-btn-link-secondary">Invoice</a>'
    : '<span class="od-btn-link od-btn-link-muted" aria-disabled="true">Invoice</span>';
$stickyRefund = ($isRefundEligible && $canOrderRefund)
    ? '<button type="button" class="od-btn od-btn-secondary" data-od-scroll-refund="1">Refund</button>'
    : '<span class="od-btn od-btn-muted" aria-disabled="true">Refund</span>';
?>

<link rel="stylesheet" href="assets/css/admin-order-details.css?v=<?php echo (int)$orderDetailsCssVersion; ?>">

<div class="od-shell">
  <section class="od-top">
    <div class="od-top-meta">
      <div class="od-breadcrumb">Orders / <?php echo od_h($channelLabel); ?> / Operational Detail</div>
      <div class="od-order-line">
        <span class="od-order-id" data-order-copy-value="<?php echo od_h((string)($order['order_number'] ?? '')); ?>"><?php echo od_h((string)($order['order_number'] ?? '')); ?></span>
        <button type="button" id="odCopyOrderId" class="od-copy-btn">Copy</button>
        <span class="od-chip <?php echo od_h(od_status_class($currentOrderStatus)); ?>"><?php echo od_h(od_status_label($currentOrderStatus)); ?></span>
      </div>
      <div class="od-customer-line">
        <strong><?php echo od_h((string)($order['customer_name'] ?? '-')); ?></strong>
        <span><?php echo od_h((string)($order['customer_phone'] ?? '-')); ?></span>
        <span><?php echo od_h($modeLabel); ?></span>
      </div>
    </div>

    <div class="od-top-actions">
      <a href="production_plan.php?order_id=<?php echo (int)$orderId; ?>" class="od-btn-link od-btn-link-primary">Production Sheet</a>
      <?php if ($canGenerateInvoice): ?>
        <a href="order_invoice.php?id=<?php echo (int)$orderId; ?>" class="od-btn-link od-btn-link-secondary">Invoice</a>
      <?php else: ?>
        <span class="od-btn-link od-btn-link-muted" aria-disabled="true">Invoice</span>
      <?php endif; ?>
      <a href="<?php echo od_h($backHref); ?>" class="od-btn-link od-btn-link-secondary">Back to Orders</a>
    </div>
  </section>

  <section class="order-summary-strip">
    <?php foreach ($summaryCards as $card): ?>
      <article class="od-summary-card order-summary-strip-card">
        <div class="od-summary-kicker"><span><?php echo od_h($card['icon']); ?></span><span><?php echo od_h($card['title']); ?></span></div>
        <div class="od-summary-value"><?php echo od_h($card['value']); ?></div>
        <div class="od-summary-sub"><?php echo od_h($card['sub']); ?></div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="od-mobile-summary">
    <article class="od-mobile-mini">
      <div class="od-mobile-mini-title">Payment</div>
      <div class="od-mobile-mini-value"><?php echo od_h(od_status_label($currentPaymentStatus)); ?></div>
    </article>
    <article class="od-mobile-mini">
      <div class="od-mobile-mini-title">Fulfillment</div>
      <div class="od-mobile-mini-value"><?php echo od_h($fulfilmentLabel); ?></div>
    </article>
    <article class="od-mobile-mini">
      <div class="od-mobile-mini-title">Total</div>
      <div class="od-mobile-mini-value"><?php echo od_h(od_money((float)($order['grand_total'] ?? 0))); ?></div>
    </article>
  </section>

  <section class="order-actions-bar">
    <span class="od-action-title">Actions</span>
    <?php if ($desktopActions): ?>
      <?php foreach ($desktopActions as $actionHtml): ?>
        <?php echo $actionHtml; ?>
      <?php endforeach; ?>
    <?php else: ?>
      <span class="od-pill">No actions for current state</span>
    <?php endif; ?>
  </section>

  <section class="od-main-grid">
    <div class="od-stack">
      <article class="od-card order-customer-card is-collapsible open">
        <button type="button" class="od-accordion-toggle" data-od-accordion="1" aria-expanded="true">
          <span>Customer &amp; Delivery</span>
          <span data-accordion-icon>Hide</span>
        </button>
        <div class="od-accordion-body">
          <dl class="od-kv">
            <div class="od-kv-row"><dt>Name</dt><dd><?php echo od_h((string)($order['customer_name'] ?? '-')); ?></dd></div>
            <div class="od-kv-row"><dt>Phone</dt><dd><?php echo od_h((string)($order['customer_phone'] ?? '-')); ?></dd></div>
            <div class="od-kv-row"><dt>Email</dt><dd><?php echo od_h((string)($order['customer_email'] ?? '-')); ?></dd></div>
            <div class="od-kv-row"><dt>Address</dt><dd><?php echo od_h($customerAddress); ?></dd></div>
            <div class="od-kv-row"><dt>Map</dt><dd><?php if ($mapHref !== ''): ?><a class="od-map-link" href="<?php echo od_h($mapHref); ?>" target="_blank" rel="noopener">Open Map</a><?php else: ?>-<?php endif; ?></dd></div>
            <div class="od-kv-row"><dt>Delivery Date</dt><dd><?php echo od_h($deliveryDateLabel); ?></dd></div>
            <div class="od-kv-row"><dt>Time Slot</dt><dd><?php echo od_h($slotLabel !== '' ? $slotLabel : $deliveryTimeLabel); ?></dd></div>
          </dl>
        </div>
      </article>
    </div>

    <div class="od-stack od-col-wide">
      <article class="od-card order-items-table-card is-collapsible open">
        <button type="button" class="od-accordion-toggle" data-od-accordion="1" aria-expanded="true">
          <span>Items</span>
          <span data-accordion-icon>Hide</span>
        </button>
        <div class="od-accordion-body">
          <table class="order-items-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Variant</th>
                <th>Topper</th>
                <th>Cake Message</th>
                <th>Custom Note</th>
                <th>Unit Price</th>
                <th>Line Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($itemRows as $itemRow): ?>
                <?php
                $itemImage = trim((string)($itemRow['product_image'] ?? ''));
                $topperText = trim((string)($itemRow['topper_name_snapshot'] ?? ''));
                $cakeNote = trim((string)($itemRow['cake_message'] ?? ''));
                $customNote = trim((string)($itemRow['customisation_note'] ?? ''));
                $variantText = trim((string)($itemRow['variant_snapshot'] ?? ''));
                ?>
                <tr>
                  <td>
                    <div class="od-item-cell">
                      <?php if ($itemImage !== ''): ?>
                        <img class="od-item-thumb" src="<?php echo od_h($itemImage); ?>" alt="<?php echo od_h((string)($itemRow['product_name_snapshot'] ?? 'Item')); ?>">
                      <?php else: ?>
                        <span class="od-item-thumb-fallback">I</span>
                      <?php endif; ?>
                      <div>
                        <div class="od-item-name"><?php echo od_h((string)($itemRow['product_name_snapshot'] ?? 'Item')); ?></div>
                        <div class="od-item-meta"><?php echo od_h($variantText !== '' ? $variantText : 'Standard item'); ?></div>
                      </div>
                    </div>
                  </td>
                  <td><?php echo (int)($itemRow['quantity'] ?? 0); ?></td>
                  <td><?php echo od_h($variantText !== '' ? $variantText : '-'); ?></td>
                  <td><?php echo od_h($topperText !== '' ? $topperText : '-'); ?></td>
                  <td><?php echo od_h($cakeNote !== '' ? $cakeNote : '-'); ?></td>
                  <td><?php echo od_h($customNote !== '' ? $customNote : '-'); ?></td>
                  <td><?php echo od_h(od_money((float)($itemRow['unit_price'] ?? 0))); ?></td>
                  <td><?php echo od_h(od_money((float)($itemRow['line_total'] ?? 0))); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$itemRows): ?>
                <tr>
                  <td colspan="8">No items recorded for this order.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

          <div class="od-special-instructions">
            <strong>Special Instructions:</strong>
            <?php if ($specialInstructions): ?>
              <?php echo od_h(implode(' | ', $specialInstructions)); ?>
            <?php else: ?>
              No special instructions recorded.
            <?php endif; ?>
          </div>
        </div>
      </article>
    </div>

    <div class="od-stack">
      <article class="od-card order-price-card is-collapsible open">
        <button type="button" class="od-accordion-toggle" data-od-accordion="1" aria-expanded="true">
          <span>Price Summary</span>
          <span data-accordion-icon>Hide</span>
        </button>
        <div class="od-accordion-body">
          <dl class="od-kv">
            <div class="od-kv-row"><dt>Subtotal</dt><dd><?php echo od_h(od_money((float)($order['subtotal'] ?? 0))); ?></dd></div>
            <div class="od-kv-row"><dt>Delivery</dt><dd><?php echo od_h(od_money($deliveryFee)); ?></dd></div>
            <div class="od-kv-row"><dt>Discount</dt><dd><?php echo od_h(od_money($discountTotal)); ?></dd></div>
            <div class="od-kv-row"><dt>Tax</dt><dd><?php echo od_h(od_money($taxTotal)); ?></dd></div>
            <div class="od-kv-row"><dt>Refund</dt><dd><?php echo od_h(od_money($refundAmount)); ?></dd></div>
            <div class="od-kv-row od-kv-row--total"><dt>Final Total</dt><dd><?php echo od_h(od_money((float)($order['grand_total'] ?? 0))); ?></dd></div>
          </dl>
        </div>
      </article>

      <article class="od-card order-payment-card is-collapsible open">
        <button type="button" class="od-accordion-toggle" data-od-accordion="1" aria-expanded="true">
          <span>Payment</span>
          <span data-accordion-icon>Hide</span>
        </button>
        <div class="od-accordion-body">
          <div class="od-chip <?php echo od_h(od_status_class($currentOrderStatus)); ?>"><?php echo od_h(od_status_label($currentPaymentStatus)); ?></div>
          <dl class="od-kv">
            <div class="od-kv-row"><dt>Transaction ID</dt><dd><?php echo od_h($paymentReference !== '' ? $paymentReference : '-'); ?></dd></div>
            <div class="od-kv-row"><dt>Payment Mode</dt><dd><?php echo od_h($paymentMethodLabel); ?></dd></div>
            <div class="od-kv-row"><dt>Collected By</dt><dd><?php echo od_h($collectedByLabel); ?></dd></div>
            <div class="od-kv-row"><dt>Receipt</dt><dd><?php echo ($canOpenReceipt || is_array($latestReceipt)) ? '<a class="od-map-link" href="payment_receipt.php?id=' . (int)$orderId . '">Open Receipt</a>' : '-'; ?></dd></div>
            <?php if (!empty($order['payment_proof_url'])): ?>
              <div class="od-kv-row"><dt>Proof</dt><dd><a class="od-map-link" href="<?php echo od_h((string)$order['payment_proof_url']); ?>" target="_blank" rel="noopener">View Upload</a></dd></div>
            <?php endif; ?>
          </dl>
        </div>
      </article>

      <?php if (
        $canConfirmPayment &&
        (string)($order['payment_status'] ?? '') === 'pending' &&
        (string)($order['payment_method'] ?? '') === 'upi_manual'
      ): ?>
      <article class="od-card" id="verify-payment-card" style="border:2px solid #f59e0b;background:#fffbeb;">
        <h2 class="od-card-title" style="color:#92400e;">⚠ UPI Payment Awaiting Verification</h2>
        <?php $proofUrl = (string)($order['payment_proof_url'] ?? ''); ?>
        <?php if ($proofUrl !== ''): ?>
          <?php $proofDisplay = (strncmp($proofUrl, 'http', 4) === 0) ? $proofUrl : '/' . ltrim($proofUrl, '/'); ?>
          <div style="text-align:center;margin:.75rem 0">
            <a href="<?php echo od_h($proofDisplay); ?>" target="_blank" rel="noopener">
              <img src="<?php echo od_h($proofDisplay); ?>"
                   alt="Payment proof"
                   style="max-width:100%;max-height:220px;object-fit:contain;border-radius:.5rem;border:1px solid #fcd34d;cursor:pointer;">
            </a>
            <div style="margin-top:.35rem;font-size:.78rem;color:#78716c;">Click to open full size</div>
          </div>
        <?php else: ?>
          <p style="font-size:.85rem;color:#9ca3af;font-style:italic;">No payment screenshot uploaded yet.</p>
        <?php endif; ?>

        <div style="display:flex;gap:.75rem;margin-top:1rem;flex-wrap:wrap;">
          <!-- Approve -->
          <form method="post" action="verify_payment.php"
                onsubmit="return confirm('Approve payment for order <?php echo od_h((string)$order['order_number']); ?>?\nThis will post to the GL and mark the order as paid.')">
            <input type="hidden" name="csrf_token" value="<?php echo od_h(\App\Core\Csrf::token()); ?>">
            <input type="hidden" name="action"   value="approve">
            <input type="hidden" name="order_id" value="<?php echo (int)$orderId; ?>">
            <button type="submit"
                    style="background:#059669;color:#fff;border:none;border-radius:.45rem;padding:.55rem 1.25rem;font-size:.88rem;font-weight:600;cursor:pointer;">
              ✓ Approve Payment
            </button>
          </form>

          <!-- Reject -->
          <form method="post" action="verify_payment.php"
                onsubmit="return confirm('Reject payment for order <?php echo od_h((string)$order['order_number']); ?>?\nThis will mark the order as cancelled.')">
            <input type="hidden" name="csrf_token"      value="<?php echo od_h(\App\Core\Csrf::token()); ?>">
            <input type="hidden" name="action"          value="reject">
            <input type="hidden" name="order_id"        value="<?php echo (int)$orderId; ?>">
            <input type="hidden" name="rejection_note"  value="">
            <button type="submit"
                    style="background:#dc2626;color:#fff;border:none;border-radius:.45rem;padding:.55rem 1.25rem;font-size:.88rem;font-weight:600;cursor:pointer;">
              ✕ Reject Payment
            </button>
          </form>
        </div>
      </article>
      <?php endif; ?>

      <article class="od-card order-timeline-card is-collapsible open">
        <button type="button" class="od-accordion-toggle" data-od-accordion="1" aria-expanded="true">
          <span>Order Timeline</span>
          <span data-accordion-icon>Hide</span>
        </button>
        <div class="od-accordion-body">
          <ul class="order-timeline order-timeline">
            <?php foreach ($timelineSteps as $timelineIndex => $timelineStep): ?>
              <?php $stepClass = $timelineIndex < $timelineCurrentIndex ? 'is-done' : ($timelineIndex === $timelineCurrentIndex ? 'is-current' : ''); ?>
              <li class="<?php echo od_h($stepClass); ?>">
                <div>
                  <strong><?php echo od_h($timelineStep['label']); ?></strong><br>
                  <span><?php echo od_h($timelineStep['note']); ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </article>
    </div>
  </section>

  <?php if ($isRefundEligible && $canRefundApproval): ?>
    <section id="orderRefundSection" class="od-card">
      <h2 class="od-card-title">Refund Workflow</h2>
      <div class="od-refund-fields">
        <input type="hidden" id="refund-order-id" value="<?php echo (int)$orderId; ?>">
        <input type="hidden" id="refund-grand-total" value="<?php echo od_h((string)($order['grand_total'] ?? 0)); ?>">
        <input type="hidden" id="refund-proof-url" value="">

        <div class="od-field">
          <label>Refund Type</label>
          <div class="od-inline-actions">
            <label><input type="radio" name="refund-type-radio" id="refund-type-full" value="full"> Full Refund</label>
            <label><input type="radio" name="refund-type-radio" id="refund-type-partial" value="partial"> Partial Refund</label>
          </div>
        </div>

        <div class="od-field">
          <label for="refund-amount">Refund Amount</label>
          <input type="number" id="refund-amount" min="0.01" step="0.01" placeholder="Enter amount">
        </div>

        <div class="od-field">
          <label for="refund-reason">Reason</label>
          <select id="refund-reason">
            <option value="">Select reason</option>
            <option value="QUALITY_ISSUE">Quality Issue</option>
            <option value="WRONG_ORDER">Wrong Order</option>
            <option value="ITEM_NOT_DELIVERED">Item Not Delivered</option>
            <option value="DAMAGED_ITEM">Damaged Item</option>
            <option value="DUPLICATE_CHARGE">Duplicate Charge</option>
            <option value="CUSTOMER_CANCELLED">Customer Cancelled</option>
            <option value="OTHER">Other</option>
          </select>
        </div>

        <div class="od-field od-hidden" id="refund-notes-group">
          <label for="refund-notes">Internal Notes</label>
          <textarea id="refund-notes" placeholder="Required when reason is Other"></textarea>
        </div>

        <div class="od-field">
          <label for="refund-settlement-ref">Settlement Reference</label>
          <input type="text" id="refund-settlement-ref" placeholder="UTR / transaction reference">
        </div>

        <div class="od-field">
          <label for="refund-proof-file">Proof Upload</label>
          <input type="file" id="refund-proof-file" accept="image/jpeg,image/png,image/webp,application/pdf">
          <div id="refund-proof-filename" class="od-inline-msg"></div>
        </div>

        <div class="od-inline-actions">
          <button type="button" id="refund-submit-btn" class="od-btn od-btn-primary">Submit Refund Request</button>
        </div>
        <div id="refund-modal-msg" class="od-inline-msg"></div>
      </div>
    </section>
  <?php endif; ?>

  <section class="od-card is-collapsible open">
    <button type="button" class="od-accordion-toggle" data-od-accordion="1" aria-expanded="true">
      <span>Payment Receipts</span>
      <span data-accordion-icon>Hide</span>
    </button>
    <div class="od-accordion-body">
      <div class="od-receipts-grid">
        <?php if ($receiptHistory): ?>
          <?php foreach ($receiptHistory as $receipt): ?>
            <article class="order-receipt-card">
              <div>
                <div class="od-receipt-id"><?php echo od_h((string)($receipt['receipt_number'] ?? 'NA')); ?></div>
                <div class="od-receipt-meta"><?php echo od_h(invoice_format_datetime((string)($receipt['issued_at'] ?? ''))); ?></div>
              </div>
              <div>
                <div class="od-receipt-meta">Method: <?php echo od_h(invoice_payment_method_label((string)($receipt['payment_method'] ?? ($order['payment_method'] ?? 'NA')))); ?></div>
                <div class="od-receipt-meta">Txn: <?php echo od_h(od_first_non_empty((string)($receipt['settlement_reference'] ?? ''), (string)($receipt['financial_transaction_id'] ?? ''), '-')); ?></div>
                <div class="od-receipt-meta">Status: <?php echo od_h(od_status_label((string)($receipt['payment_status_snapshot'] ?? 'pending'))); ?></div>
              </div>
              <div>
                <div class="od-receipt-amount"><?php echo od_h(od_money((float)($receipt['amount'] ?? 0))); ?></div>
                <div class="od-receipt-meta od-receipt-right">Issued by <?php echo od_h((string)($receipt['issued_by_name'] ?? 'System')); ?></div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="od-inline-msg">No payment receipts have been issued for this order yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="od-mobile-sticky-actions">
    <?php echo $stickyPrimary; ?>
    <?php echo $stickyInvoice; ?>
    <?php echo $stickyRefund; ?>
  </section>
</div>

<script src="/client/assets/js/scroll-preserve.js?v=<?php echo (int) (@filemtime(__DIR__ . '/../client/assets/js/scroll-preserve.js') ?: time()); ?>"></script>
<script src="assets/js/admin-order-details.js?v=<?php echo (int)$orderDetailsJsVersion; ?>"></script>

</div>
</div>

</body>
</html>
