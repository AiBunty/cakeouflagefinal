<?php
$orderItems = $orderItemsByOrder[$oid] ?? [];
$itemCount = count($orderItems);
$firstItemName = $itemCount > 0 ? (string)($orderItems[0]['product_name_snapshot'] ?? 'Item') : 'No items';
$extraItems = max(0, $itemCount - 1);
$toppersCount = 0;
$notesCount = 0;
foreach ($orderItems as $it) {
  if (trim((string)($it['topper_name_snapshot'] ?? '')) !== '') {
    $toppersCount++;
  }
  if (trim((string)($it['cake_message'] ?? '')) !== '' || trim((string)($it['customisation_note'] ?? '')) !== '') {
    $notesCount++;
  }
}

$slotLabel = (string)($row['scheduled_slot_label'] ?? '');
if ($slotLabel === '') {
  $slotLabel = (string)($row['scheduled_slot'] ?? '');
}
$paymentMethodLabel = $payMethodLabels[$row['payment_method'] ?? ''] ?? (string)($row['payment_method'] ?? '-');

$statusClass = 'chip-status-' . preg_replace('/[^a-z_]/', '', strtolower($ostatus));
$paymentClassMap = [
  'paid' => 'chip-payment-paid',
  'pending' => 'chip-payment-unpaid',
  'under_review' => 'chip-payment-unpaid',
  'credit' => 'chip-payment-credit',
  'refunded' => 'chip-payment-refunded',
  'partially_refunded' => 'chip-payment-partial_refund',
];
$paymentChipClass = $paymentClassMap[$pstatus] ?? 'chip-payment-unpaid';
?>
<div class="order-row" id="order-row-<?php echo $oid; ?>">
  <div class="order-row-main">
    <div class="order-meta-1">
      <div class="order-id">
        #<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>
        <?php if (!empty($row['is_revised'])): ?>
          <span style="font-size:.65rem;padding:.1rem .4rem;border-radius:8px;background:#fff3cd;color:#e67e22;font-weight:700;margin-left:.35rem;vertical-align:middle">REV</span>
        <?php endif; ?>
      </div>
      <div class="order-customer"><?php echo htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars((string)$row['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div>
      <div class="order-line"><?php echo (int)$itemCount; ?> Items • Rs <?php echo number_format((float)($row['grand_total'] ?? 0), 2); ?> • <?php echo htmlspecialchars($paymentMethodLabel, ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars(strtoupper((string)($row['fulfilment_mode'] ?? '-')), ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="order-line"><?php echo htmlspecialchars($slotLabel !== '' ? $slotLabel : '-', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div>
      <div class="order-item-preview">
        <?php echo htmlspecialchars($firstItemName, ENT_QUOTES, 'UTF-8'); ?>
        <?php if ($extraItems > 0): ?>
          +<?php echo (int)$extraItems; ?> more
        <?php endif; ?>
      </div>
      <div class="order-prod-preview">Topper: <?php echo (int)$toppersCount; ?> • Notes: <?php echo (int)$notesCount; ?></div>
      <?php if ((int)($refundSummary['count'] ?? 0) > 0): ?>
        <div class="order-prod-preview">Refunds: <?php echo (int)$refundSummary['count']; ?> • Rs <?php echo number_format((float)($refundSummary['total'] ?? 0), 2); ?></div>
      <?php endif; ?>
    </div>

    <div class="order-chips">
      <span class="chip <?php echo htmlspecialchars($paymentChipClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper((string)$pstatus), ENT_QUOTES, 'UTF-8'); ?></span>
      <span class="chip <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper((string)($statusLabels[$ostatus] ?? $ostatus)), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </div>

  <div class="order-actions" style="margin-top:8px;">
    <button type="button" class="btnx btnx-outline" onclick="ordersToggleDetails(<?php echo $oid; ?>)">Details</button>

    <?php if ((bool)$governance['can_mark_preparing'] && $canOrderEdit && !$isArchived): ?>
      <form method="POST" action="update_order_status.php">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="preparing">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btnx btnx-blue">Preparing</button>
      </form>
    <?php endif; ?>

    <?php if ((bool)$governance['can_mark_delivered'] && $canOrderEdit && !$isArchived): ?>
      <form method="POST" action="update_order_status.php">
        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
        <input type="hidden" name="status" value="delivered">
        <input type="hidden" name="redirect_to" value="<?php echo $currentUri; ?>">
        <button type="submit" class="btnx btnx-purple">Delivered</button>
      </form>
    <?php endif; ?>

    <?php if ($canRefundAction && !$isArchived): ?>
      <a href="refunds.php?order_id=<?php echo $oid; ?>" class="btnx btnx-secondary-desktop btnx-outline">Refund</a>
    <?php endif; ?>

    <?php if ((string)$pstatus === 'paid'): ?>
      <a href="order_invoice.php?id=<?php echo $oid; ?>" class="btnx btnx-secondary-desktop btnx-muted">Invoice</a>
    <?php endif; ?>

    <?php if ($canOrderDelete): ?>
      <?php if ($isArchived): ?>
        <button type="button" class="btnx btnx-outline" onclick="ordersRunDestructiveAction(<?php echo $oid; ?>, 'restore', '<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>')">Restore</button>
      <?php else: ?>
        <button type="button" class="btnx btnx-outline" onclick="ordersRunDestructiveAction(<?php echo $oid; ?>, 'archive', '<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>')">Archive</button>
      <?php endif; ?>
      <?php if ($isSuperAdmin): ?>
        <button type="button" class="btnx btnx-danger" onclick="ordersRunDestructiveAction(<?php echo $oid; ?>, 'force_purge', '<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>')">Delete</button>
      <?php endif; ?>
    <?php endif; ?>

    <?php
      $terminalStatuses = ['delivered','completed','cancelled','refunded','partially_refunded','fully_refunded','rejected'];
      $isTerminalOrder = in_array($ostatus, $terminalStatuses, true);
    ?>
    <?php if (!$isTerminalOrder && $canOrderEdit && !$isArchived): ?>
      <a href="order_revision.php?order_id=<?php echo $oid; ?>" class="btnx btnx-outline">Revise</a>
    <?php endif; ?>
    <a href="order_revision_history.php?order_id=<?php echo $oid; ?>" class="btnx btnx-muted" title="Revision History">Revisions</a>

    <div class="order-more">
      <button type="button" class="btnx btnx-outline" onclick="ordersToggleMore(this)">⋮</button>
      <div class="order-more-menu">
        <a href="order_details.php?id=<?php echo $oid; ?>" class="btnx btnx-outline">View</a>
        <?php if ($canRefundAction && !$isArchived): ?>
          <a href="refunds.php?order_id=<?php echo $oid; ?>" class="btnx btnx-outline">Refund</a>
        <?php endif; ?>
        <?php if ((string)$pstatus === 'paid'): ?>
          <a href="order_invoice.php?id=<?php echo $oid; ?>" class="btnx btnx-outline">Invoice</a>
        <?php endif; ?>
        <?php if ($canOrderDelete): ?>
          <?php if ($isArchived): ?>
            <button type="button" class="btnx btnx-outline" onclick="ordersRunDestructiveAction(<?php echo $oid; ?>, 'restore', '<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>')">Restore</button>
          <?php else: ?>
            <button type="button" class="btnx btnx-outline" onclick="ordersRunDestructiveAction(<?php echo $oid; ?>, 'archive', '<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>')">Archive</button>
          <?php endif; ?>
          <?php if ($isSuperAdmin): ?>
            <button type="button" class="btnx btnx-danger" onclick="ordersRunDestructiveAction(<?php echo $oid; ?>, 'force_purge', '<?php echo htmlspecialchars((string)$row['order_number'], ENT_QUOTES, 'UTF-8'); ?>')">Delete</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/order-expanded.php'; ?>
</div>
