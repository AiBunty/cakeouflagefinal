<?php
$timelineEvents = $timelineByOrder[$oid] ?? [];
$snapshot = $financeSnapshotByOrder[$oid] ?? null;
$orderItems = $orderItemsByOrder[$oid] ?? [];
?>
<div class="order-expanded" id="order-expanded-<?php echo $oid; ?>">
  <div class="order-expanded-grid">
    <div class="exp-block">
      <h5 class="exp-title">Items + Production Notes</h5>
      <?php if (!empty($orderItems)): ?>
        <ul class="order-item-list">
          <?php foreach ($orderItems as $item): ?>
            <li class="exp-line">
              <?php echo (int)($item['quantity'] ?? 1); ?>x
              <?php echo htmlspecialchars((string)($item['product_name_snapshot'] ?? 'Item'), ENT_QUOTES, 'UTF-8'); ?>
              <?php if (!empty($item['variant_snapshot'])): ?>
                • <?php echo htmlspecialchars((string)$item['variant_snapshot'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
              <?php if (!empty($item['topper_name_snapshot'])): ?>
                • Topper: <?php echo htmlspecialchars((string)$item['topper_name_snapshot'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
              <?php if (!empty($item['cake_message'])): ?>
                • Note: <?php echo htmlspecialchars((string)$item['cake_message'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
              <?php if (!empty($item['customisation_note'])): ?>
                • Prod: <?php echo htmlspecialchars((string)$item['customisation_note'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="exp-line">No item snapshot available.</div>
      <?php endif; ?>
    </div>

    <div class="exp-block">
      <h5 class="exp-title">Finance + Refunds</h5>
      <div class="exp-line">Grand Total: Rs <?php echo number_format((float)($row['grand_total'] ?? 0), 2); ?></div>
      <div class="exp-line">Payment: <?php echo htmlspecialchars((string)($row['payment_status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string)($row['payment_method'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>)</div>
      <div class="exp-line">Refund Status: <?php echo htmlspecialchars((string)($row['refund_status'] ?? 'none'), ENT_QUOTES, 'UTF-8'); ?></div>
      <?php if ($snapshot): ?>
        <div class="exp-line">Collected: Rs <?php echo number_format((float)($snapshot['collected_total'] ?? 0), 2); ?></div>
        <div class="exp-line">Balance Due: Rs <?php echo number_format((float)($snapshot['balance_due'] ?? 0), 2); ?></div>
      <?php endif; ?>
    </div>

    <div class="exp-block">
      <h5 class="exp-title">Timeline + CRM</h5>
      <?php if (!empty($timelineEvents)): ?>
        <ul class="order-item-list">
          <?php foreach ($timelineEvents as $ev): ?>
            <li class="exp-line">
              <?php echo htmlspecialchars((string)($ev['new_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              <?php if ((string)($ev['previous_status'] ?? '') !== ''): ?>
                (from <?php echo htmlspecialchars((string)$ev['previous_status'], ENT_QUOTES, 'UTF-8'); ?>)
              <?php endif; ?>
              • <?php echo htmlspecialchars((string)($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="exp-line">No timeline events found.</div>
      <?php endif; ?>
      <div class="exp-line">CRM/Email logs are available in Communications/CRM modules.</div>
    </div>
  </div>
</div>
