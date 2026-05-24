<?php
/** @var array|null $order */
/** @var string $orderNumber */
$order = isset($order) && is_array($order) ? $order : null;
$orderNumber = isset($orderNumber) ? (string)$orderNumber : '';

$grandTotal   = $order ? (float)($order['grand_total'] ?? 0) : 0;
$advanceAmount = $order ? (float)($order['advance_amount'] ?? 0) : 0;
$paymentStatus = $order ? (string)($order['payment_status'] ?? '') : '';
$remaining = max(0, $grandTotal - $advanceAmount);
$isPartial = ($paymentStatus === 'under_review' && $advanceAmount > 0 && $remaining > 0);
?>
<main data-page="order-confirmation">
  <section class="section" style="padding:40px 0 60px;">
    <div class="container" style="max-width:640px; margin:0 auto;">

      <?php if (!$order): ?>
        <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:14px; padding:28px; text-align:center;">
          <p style="font-size:1.1rem; font-weight:700; color:#9f1239; margin:0 0 8px;">Order Not Found</p>
          <p style="color:#6b7280; font-size:0.9rem; margin:0;">
            This confirmation link is invalid or has expired. Please check your order number or contact Cakeouflage.
          </p>
        </div>
      <?php else: ?>

        <!-- Success banner -->
        <div style="background:linear-gradient(135deg,#dcfce7,#f0fdf4); border:1px solid #bbf7d0; border-radius:14px; padding:28px 24px; text-align:center; margin-bottom:24px;">
          <div style="font-size:2.8rem; line-height:1; margin-bottom:10px;">🎂</div>
          <h1 style="font-family:'DM Serif Display',Georgia,serif; font-weight:400; color:#166534; font-size:1.7rem; margin:0 0 8px;">
            Order Confirmed!
          </h1>
          <p style="color:#4b5563; margin:0; font-size:0.95rem;">
            Your custom cake order has been received. Our team will be in touch shortly.
          </p>
        </div>

        <!-- Order card -->
        <div style="background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:14px; overflow:hidden; margin-bottom:20px;">

          <div style="background:linear-gradient(135deg,#80001F,#a0002a); padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
            <span style="font-family:'DM Serif Display',Georgia,serif; color:#fff; font-size:1.05rem;">Order Summary</span>
            <span style="background:rgba(255,255,255,.2); color:#fff; border-radius:999px; padding:4px 12px; font-size:0.8rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase;">
              <?= htmlspecialchars(strtoupper($order['order_status'] ?? 'pending'), ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>

          <div style="padding:18px 20px; display:grid; gap:12px;">

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px;">
              <span style="font-size:0.82rem; color:#7f6973; text-transform:uppercase; letter-spacing:.06em;">Order Number</span>
              <strong style="font-size:0.95rem; color:#80001F;">#<?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px;">
              <span style="font-size:0.82rem; color:#7f6973; text-transform:uppercase; letter-spacing:.06em;">Customer</span>
              <strong style="font-size:0.9rem;"><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <?php if (!empty($order['customer_phone'])): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px;">
              <span style="font-size:0.82rem; color:#7f6973; text-transform:uppercase; letter-spacing:.06em;">Phone</span>
              <span style="font-size:0.9rem;"><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>

            <div style="border-top:1px solid rgba(128,0,31,.08); padding-top:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px;">
              <span style="font-size:0.82rem; color:#7f6973; text-transform:uppercase; letter-spacing:.06em;">Order Total</span>
              <strong style="font-size:1.15rem; color:#80001F;">₹<?= htmlspecialchars(number_format($grandTotal, 0), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <?php if ($isPartial): ?>
            <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:12px 14px;">
              <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:6px;">
                <span style="font-size:0.82rem; color:#9a3412; font-weight:600;">Advance Paid (50%)</span>
                <strong style="color:#9a3412;">₹<?= htmlspecialchars(number_format($advanceAmount, 0), ENT_QUOTES, 'UTF-8') ?></strong>
              </div>
              <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px;">
                <span style="font-size:0.82rem; color:#78716c;">Remaining on delivery</span>
                <strong style="color:#78716c;">₹<?= htmlspecialchars(number_format($remaining, 0), ENT_QUOTES, 'UTF-8') ?></strong>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Delivery info -->
        <?php if (!empty($order['delivery_street'])): ?>
        <div style="background:#f8fafc; border:1px solid rgba(128,0,31,.1); border-radius:12px; padding:16px 18px; margin-bottom:20px;">
          <p style="margin:0 0 8px; font-size:0.8rem; color:#7f6973; text-transform:uppercase; letter-spacing:.06em; font-weight:700;">🚚 Delivery Address</p>
          <p style="margin:0; font-size:0.9rem; color:#2d1f25; line-height:1.6;">
            <?= htmlspecialchars($order['delivery_street'], ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($order['delivery_postal_code'])): ?>
              &nbsp;— <?= htmlspecialchars($order['delivery_postal_code'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
          </p>
          <?php if (!empty($order['scheduled_slot_label'])): ?>
            <p style="margin:6px 0 0; font-size:0.84rem; color:#7f6973;">📅 <?= htmlspecialchars($order['scheduled_slot_label'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- What's next -->
        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; padding:16px 18px; margin-bottom:20px;">
          <p style="margin:0 0 10px; font-size:0.82rem; color:#1e40af; text-transform:uppercase; letter-spacing:.06em; font-weight:700;">What happens next?</p>
          <ul style="margin:0; padding-left:18px; font-size:0.88rem; color:#374151; line-height:1.9;">
            <li>Our team will <strong>verify your payment</strong> within a few hours.</li>
            <li>You'll receive a <strong>confirmation call or WhatsApp</strong> from Cakeouflage.</li>
            <?php if ($isPartial): ?>
            <li>The <strong>remaining ₹<?= htmlspecialchars(number_format($remaining, 0), ENT_QUOTES, 'UTF-8') ?></strong> is due at the time of delivery.</li>
            <?php endif; ?>
            <li>Preparation begins once your order is <strong>fully confirmed</strong>.</li>
          </ul>
        </div>

        <!-- Contact CTA -->
        <div style="text-align:center; padding:8px 0;">
          <p style="font-size:0.84rem; color:#7f6973; margin:0 0 12px;">Questions? Reach us on WhatsApp or call.</p>
          <a href="https://wa.me/91XXXXXXXXXX" style="display:inline-flex; align-items:center; gap:8px; background:#25d366; color:#fff; font-weight:600; font-size:0.85rem; padding:10px 20px; border-radius:999px; text-decoration:none;">
            💬 WhatsApp Cakeouflage
          </a>
        </div>

      <?php endif; ?>

    </div>
  </section>
</main>
