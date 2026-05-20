<?php
$quote = isset($quote) && is_array($quote) ? $quote : null;
$meta = isset($inquiryMeta) && is_array($inquiryMeta) ? $inquiryMeta : [];
$tokenValue = isset($token) ? (string)$token : '';
$isExpired = isset($isExpired) ? (bool)$isExpired : false;
$quoteStatus = $quote ? (string)($quote['quote_status'] ?? '') : '';
$linkUsed = $quote && !empty($quote['used_at']);
$orderId = $quote ? (int)($quote['order_id'] ?? 0) : 0;
?>
<main data-page="byoc-quote-accept">
  <section class="section byoc-accept-section">
    <div class="container">
      <div class="byoc-accept-card">
        <span class="byoc-accept-label">Build Your Own Cake</span>
        <h1 class="byoc-accept-title">Quote Acceptance</h1>

        <?php if (!$quote): ?>
          <p class="byoc-accept-status byoc-accept-status--error">This quote link is invalid or no longer available.</p>
        <?php elseif ($linkUsed || $orderId > 0): ?>
          <p class="byoc-accept-status byoc-accept-status--ok">This quote has already been accepted and converted to order #<?= htmlspecialchars((string)$orderId, ENT_QUOTES, 'UTF-8') ?>.</p>
        <?php elseif ($isExpired): ?>
          <p class="byoc-accept-status byoc-accept-status--error">This quote link has expired. Please request a fresh quote link from Cakeouflage.</p>
        <?php else: ?>
          <p class="byoc-accept-subtitle">Review your custom quote details and confirm to place it as a payment-pending order.</p>

          <div class="byoc-accept-grid">
            <div class="byoc-accept-item">
              <span>Quote Number</span>
              <strong><?= htmlspecialchars((string)($quote['quote_number'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Name</span>
              <strong><?= htmlspecialchars((string)($quote['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Event</span>
              <strong><?= htmlspecialchars((string)($meta['event_information'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Event Date</span>
              <strong><?= htmlspecialchars((string)($meta['event_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Guests</span>
              <strong><?= htmlspecialchars((string)($meta['number_of_servings_guests'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="byoc-accept-item">
              <span>Quote Amount</span>
              <strong>INR <?= htmlspecialchars(number_format((float)($quote['quote_amount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
          </div>

          <div class="byoc-accept-note">
            <strong><?= htmlspecialchars((string)($quote['quote_subject'] ?? 'Custom Cake Quote'), ENT_QUOTES, 'UTF-8') ?></strong>
            <p><?= nl2br(htmlspecialchars((string)($quote['quote_message'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
          </div>

          <form id="byocQuoteAcceptForm" data-token="<?= htmlspecialchars($tokenValue, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn--primary" id="byocQuoteAcceptBtn">Accept Quote and Create Order</button>
          </form>
          <p id="byocQuoteAcceptStatus" class="byoc-accept-status" role="status" aria-live="polite"></p>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
