<?php /* Cakeouflage — B2B / Corporate Landing Page */
$b2bWhatsapp = build_whatsapp_link(
  (string)($siteConfig['contact']['whatsapp_number'] ?? ''),
  "Hi Cakeouflage! I'm interested in B2B ordering."
);
?>

<!-- B2B HERO -->
<section class="page-hero page-hero--b2b" aria-label="B2B hero">
  <div class="container">
    <div class="page-hero__inner">
      <span class="page-hero__eyebrow">✦ Business Ordering</span>
      <h1 class="page-hero__title">Premium Cakes &amp;<br>Desserts at Scale</h1>
      <p class="page-hero__subtitle">Corporate gifting. Restaurant supply. Event dessert tables. Reseller programs. All through one approved B2B account.</p>
      <div class="hero-cta-row">
        <a href="/b2b/register" class="btn btn--primary btn--lg">Apply for B2B Access</a>
        <a href="/b2b/login" class="btn btn--outline btn--lg">B2B Login</a>
      </div>
    </div>
  </div>
</section>

<!-- WHO WE SERVE -->
<section class="section section--white" aria-label="Who we serve">
  <div class="container">
    <div class="section-header section-header--center">
      <div class="section-label">Who We Serve</div>
      <h2 class="section-title">Built for Businesses That Celebrate</h2>
    </div>
    <div class="b2b-segments-grid">
      <div class="b2b-segment-card">
        <span class="b2b-segment-card__icon">🏢</span>
        <h3>Corporate Clients</h3>
        <p>Employee celebrations, client gifting, festival hampers, and bulk event cakes for companies and organisations.</p>
      </div>
      <div class="b2b-segment-card">
        <span class="b2b-segment-card__icon">🎪</span>
        <h3>Event Planners</h3>
        <p>Dessert tables, wedding cake supply, birthday setups, and venue-ready packaging for event management teams.</p>
      </div>
      <div class="b2b-segment-card">
        <span class="b2b-segment-card__icon">🍽️</span>
        <h3>Hotels &amp; Restaurants</h3>
        <p>Consistent supply of premium cheesecakes, mousse cakes, tarts, and brownies for cafe and restaurant menus.</p>
      </div>
      <div class="b2b-segment-card">
        <span class="b2b-segment-card__icon">🧁</span>
        <h3>Cake Shop Owners</h3>
        <p>Reseller and wholesale programs for cake shops and home bakers who need consistent product supply.</p>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section section--tinted" aria-label="How B2B works">
  <div class="container">
    <div class="section-header section-header--center">
      <div class="section-label">Simple Process</div>
      <h2 class="section-title">How B2B Ordering Works</h2>
    </div>
    <div class="process-steps">
      <div class="process-step">
        <div class="process-step__num">01</div>
        <h3 class="process-step__title">Apply for B2B Access</h3>
        <p class="process-step__desc">Submit your business details, GST number, and ordering requirements through our B2B registration form.</p>
      </div>
      <div class="process-step">
        <div class="process-step__num">02</div>
        <h3 class="process-step__title">Account Approval</h3>
        <p class="process-step__desc">Our sales team reviews your application within 24–48 hours and activates your approved account.</p>
      </div>
      <div class="process-step">
        <div class="process-step__num">03</div>
        <h3 class="process-step__title">Browse &amp; Request Quote</h3>
        <p class="process-step__desc">Access B2B pricing, request a custom quote, or build your bulk order directly from your dashboard.</p>
      </div>
      <div class="process-step">
        <div class="process-step__num">04</div>
        <h3 class="process-step__title">Order &amp; Fulfilment</h3>
        <p class="process-step__desc">Place your order, choose delivery or pickup, and track your order status in the B2B dashboard.</p>
      </div>
    </div>
  </div>
</section>

<!-- B2B BENEFITS -->
<section class="section section--white" aria-label="B2B benefits">
  <div class="container">
    <div class="b2b-benefits-grid">
      <div class="b2b-benefits__copy">
        <div class="section-label">Your Advantage</div>
        <h2 class="section-title">Why Businesses Choose Cakeouflage</h2>
        <ul class="b2b-feature-list">
          <li><span>✦</span> <strong>Wholesale &amp; tiered pricing</strong> — Better rates for regular and high-volume orders</li>
          <li><span>✦</span> <strong>GST invoices</strong> — Business-ready invoicing for corporate compliance</li>
          <li><span>✦</span> <strong>Minimum order flexibility</strong> — MOQ designed for different business sizes</li>
          <li><span>✦</span> <strong>Custom order builder</strong> — Build large multi-product orders with notes and references</li>
          <li><span>✦</span> <strong>Dedicated dashboard</strong> — Track orders, invoices, quotes, and history in one place</li>
          <li><span>✦</span> <strong>Reorder with one click</strong> — Fast reorder from your previous orders</li>
          <li><span>✦</span> <strong>Delivery slot planning</strong> — Book delivery dates and slots for bulk orders in advance</li>
        </ul>
      </div>
      <div class="b2b-benefits__cta-card">
        <h3>Ready to get started?</h3>
        <p>Apply for a B2B account and get access to wholesale pricing, bulk ordering, and a dedicated business dashboard.</p>
        <a href="/b2b/register" class="btn btn--primary btn--lg btn--block">Apply for B2B Access</a>
        <p class="text-center" style="margin-top:var(--space-3)">
          Already approved? <a href="/b2b/login" class="link">Sign In →</a>
        </p>
        <div class="b2b-cta-card__contact">
          <p>Have questions first?</p>
          <?php if ($b2bWhatsapp !== ''): ?>
            <a href="<?= htmlspecialchars($b2bWhatsapp, ENT_QUOTES, 'UTF-8') ?>" class="btn btn--whatsapp btn--block" target="_blank" rel="noopener">WhatsApp Our Sales Team</a>
          <?php else: ?>
            <a href="/contact" class="btn btn--primary btn--block">Contact Sales Team</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="section section--pink" aria-label="B2B testimonials">
  <div class="container">
    <div class="section-header section-header--center">
      <div class="section-label">What They Say</div>
      <h2 class="section-title">Trusted by Businesses Across Nashik</h2>
    </div>
    <div class="testimonials-grid">
      <blockquote class="testimonial-card">
        <p class="testimonial-card__text">"We order brownies and cheesecakes for our cafe every week. Consistent quality, professional packaging, and always on time."</p>
        <footer class="testimonial-card__footer">
          <strong>Rohit K.</strong>
          <span>Cafe Owner, Nashik</span>
        </footer>
      </blockquote>
      <blockquote class="testimonial-card">
        <p class="testimonial-card__text">"Cakeouflage handled all our Diwali gifting hampers for 200 employees. Beautifully presented and delivered on schedule."</p>
        <footer class="testimonial-card__footer">
          <strong>Meeta S.</strong>
          <span>HR Manager, Corporate Client</span>
        </footer>
      </blockquote>
      <blockquote class="testimonial-card">
        <p class="testimonial-card__text">"The B2B dashboard makes reordering simple. I can place next week's order in minutes and track everything in one place."</p>
        <footer class="testimonial-card__footer">
          <strong>Priya N.</strong>
          <span>Event Planner, Nashik</span>
        </footer>
      </blockquote>
    </div>
  </div>
</section>
