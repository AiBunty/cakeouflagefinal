<?php /* Cakeouflage — Shipping & Delivery Page */
$shippingWhatsapp = (string)($siteConfig['contact']['whatsapp_link'] ?? '');
if ($shippingWhatsapp === '') {
  $shippingWhatsapp = build_whatsapp_link((string)($siteConfig['contact']['whatsapp_number'] ?? ''), 'Hi Cakeouflage! I have a delivery question.');
}
?>

<section class="page-hero page-hero--soft" aria-label="Delivery info hero">
  <div class="container">
    <div class="page-hero__inner">
      <span class="page-hero__eyebrow">✦ Delivered Fresh</span>
      <h1 class="page-hero__title">Delivery &amp; Shipping</h1>
      <p class="page-hero__subtitle">We deliver freshly baked cakes across Nashik. Here's everything you need to know about our delivery zones, fees, and slots.</p>
    </div>
  </div>
</section>

<section class="section section--white">
  <div class="container content-prose-grid">

    <!-- Delivery zones -->
    <div class="content-prose">
      <h2>📍 Delivery Coverage</h2>
      <p>We deliver within a <strong>30 km radius</strong> from our bakery in Nashik. Delivery availability is verified by your pincode during checkout. Areas beyond 30 km may be possible on request — contact us to discuss.</p>

      <h3>Delivery Fee by Distance</h3>
      <div class="delivery-table-wrap">
        <table class="delivery-table">
          <thead>
            <tr><th>Distance Zone</th><th>Delivery Fee</th><th>Estimated Time</th></tr>
          </thead>
          <tbody>
            <tr><td>0 – 5 km</td><td>₹40</td><td>1–2 hours</td></tr>
            <tr><td>5 – 10 km</td><td>₹60</td><td>1–3 hours</td></tr>
            <tr><td>10 – 20 km</td><td>₹80</td><td>2–4 hours</td></tr>
            <tr><td>20 – 30 km</td><td>₹100</td><td>3–5 hours</td></tr>
            <tr><td>Beyond 30 km</td><td>On request</td><td>Manual approval required</td></tr>
          </tbody>
        </table>
      </div>

      <h2>🕒 Delivery Slots</h2>
      <p>Choose your preferred delivery date and time slot during checkout. Slots are subject to availability and may vary by delivery zone.</p>
      <ul>
        <li><strong>Morning:</strong> 9:00 AM – 12:00 PM</li>
        <li><strong>Afternoon:</strong> 12:00 PM – 3:00 PM</li>
        <li><strong>Evening:</strong> 3:00 PM – 7:00 PM</li>
      </ul>

      <h2>⚡ Same-Day Delivery</h2>
      <p>Same-day delivery is available for <strong>ready-stock products</strong> ordered before 12 PM, subject to slot availability in your zone. Custom and made-to-order cakes require at least 24–48 hours advance notice.</p>

      <h2>🏪 Store Pickup / Takeaway</h2>
      <p>Prefer to pick up your order? Choose <strong>Store Pickup</strong> during checkout and select your preferred pickup time slot. Our bakery address and directions are shared in your order confirmation message.</p>

      <h2>🎂 Custom &amp; Tiered Cakes</h2>
      <p>Custom cakes, fondant cakes, and tiered cakes typically require <strong>3–5 business days</strong> lead time from design confirmation and payment. Please plan in advance for weddings and large events.</p>

      <h2>💳 Delivery Charges at Checkout</h2>
      <p>Delivery charges are calculated automatically based on your pincode and displayed during the checkout preview. No surprises at delivery.</p>
    </div>

    <!-- Sidebar -->
    <aside class="content-sidebar">
      <div class="card sidebar-card">
        <h3>💬 Need Help?</h3>
        <p>Questions about delivery to a specific area? WhatsApp us for a quick answer.</p>
        <?php if ($shippingWhatsapp !== ''): ?>
          <a href="<?= htmlspecialchars($shippingWhatsapp, ENT_QUOTES, 'UTF-8') ?>" class="btn btn--whatsapp btn--block" target="_blank" rel="noopener">WhatsApp Us</a>
        <?php else: ?>
          <a href="/contact" class="btn btn--primary btn--block">Contact Us</a>
        <?php endif; ?>
      </div>
      <div class="card sidebar-card">
        <h3>📍 Check Delivery</h3>
        <p>Verify whether we deliver to your pincode before ordering.</p>
        <a href="/checkout" class="btn btn--primary btn--block">Go to Checkout</a>
      </div>
      <div class="card sidebar-card">
        <h3>⏰ Order Lead Times</h3>
        <ul class="sidebar-list">
          <li><strong>Ready-stock:</strong> Same-day (before 12 PM)</li>
          <li><strong>Standard cakes:</strong> 1–2 days</li>
          <li><strong>Custom cakes:</strong> 3–5 days</li>
          <li><strong>Tiered/wedding:</strong> 5–7 days</li>
        </ul>
      </div>
    </aside>

  </div>
</section>
