<?php /* Cakeouflage — Contact Page */
$contactPhone = trim((string)($siteConfig['contact']['phone'] ?? ''));
$contactEmail = trim((string)($siteConfig['contact']['email'] ?? ''));
$contactCity = trim((string)($siteConfig['contact']['city'] ?? 'Nashik'));
$contactWhatsapp = trim((string)($siteConfig['contact']['whatsapp'] ?? $contactPhone));
$contactWebsite = trim((string)($siteConfig['contact']['website'] ?? ''));
$businessHours = trim((string)($siteConfig['contact']['business_hours'] ?? ''));
$mapEmbedUrl = trim((string)($siteConfig['contact']['map_embed_url'] ?? ''));
$contactFormEmbedUrl = trim((string)($siteConfig['contact']['form_embed_url'] ?? ''));

$addressParts = array_filter([
  trim((string)($siteConfig['business']['address_line1'] ?? '')),
  trim((string)($siteConfig['business']['address_line2'] ?? '')),
  trim((string)($siteConfig['business']['address'] ?? '')),
  trim((string)($siteConfig['contact']['city'] ?? '')),
  trim((string)($siteConfig['business']['state'] ?? '')),
]);
$businessAddress = !empty($addressParts) ? implode(', ', $addressParts) : ('Cakeouflage, ' . $contactCity . ', Maharashtra');

if ($businessHours === '') {
  $businessHours = 'Mon-Sun: 10 AM - 8 PM';
}

if ($mapEmbedUrl === '') {
  $mapEmbedUrl = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3749.4992232394516!2d73.76781790000001!3d19.9875517!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bdd955a6817e2e5%3A0xe45effc441329228!2sCakeouflage!5e0!3m2!1sen!2sin!4v1776274709500!5m2!1sen!2sin';
}

if ($contactFormEmbedUrl === '' || !preg_match('#^https?://#i', $contactFormEmbedUrl)) {
  $contactFormEmbedUrl = 'https://admin.aibunty.com/widget/form/69f374e6605e3';
}

$phoneDigits = preg_replace('/\D+/', '', $contactPhone);
$whatsappDigits = preg_replace('/\D+/', '', $contactWhatsapp);
$phoneHref = $phoneDigits !== '' ? ('tel:+' . $phoneDigits) : '';
$emailHref = $contactEmail !== '' ? ('mailto:' . $contactEmail) : '';
$whatsappHref = $whatsappDigits !== '' ? ('https://wa.me/' . $whatsappDigits) : '';

$websiteHref = $contactWebsite;
if ($websiteHref !== '' && !preg_match('#^https?://#i', $websiteHref)) {
  $websiteHref = 'https://' . ltrim($websiteHref, '/');
}
?>
<style>
.contact-form-embed-wrapper {
  position: relative;
  min-height: 600px;
}
.contact-form-loader {
  position: absolute;
  inset: 0;
  background: #fff;
  padding: 20px 4px 0;
  z-index: 2;
  border-radius: 10px;
  pointer-events: none;
}
.cfl-row { margin-bottom: 20px; }
.cfl-label {
  height: 13px;
  width: 110px;
  border-radius: 5px;
  margin-bottom: 8px;
  background: linear-gradient(90deg, #f3e0e0 25%, #fdf0f0 50%, #f3e0e0 75%);
  background-size: 300% 100%;
  animation: cfl-shimmer 1.5s infinite;
}
.cfl-input {
  height: 44px;
  width: 100%;
  border-radius: 7px;
  background: linear-gradient(90deg, #f3e0e0 25%, #fdf0f0 50%, #f3e0e0 75%);
  background-size: 300% 100%;
  animation: cfl-shimmer 1.5s infinite;
}
.cfl-textarea {
  height: 110px;
  width: 100%;
  border-radius: 7px;
  background: linear-gradient(90deg, #f3e0e0 25%, #fdf0f0 50%, #f3e0e0 75%);
  background-size: 300% 100%;
  animation: cfl-shimmer 1.5s infinite;
}
.cfl-btn {
  height: 44px;
  width: 140px;
  border-radius: 22px;
  background: linear-gradient(90deg, #d4a0a0 25%, #e8c0c0 50%, #d4a0a0 75%);
  background-size: 300% 100%;
  animation: cfl-shimmer 1.5s infinite;
  margin-top: 4px;
}
@keyframes cfl-shimmer {
  0%   { background-position: 300% 0; }
  100% { background-position: -300% 0; }
}
.contact-form-embed {
  opacity: 0;
  transition: opacity 0.45s ease;
  display: block;
}
.contact-form-embed.cfl-ready {
  opacity: 1;
}
</style>

<section class="page-hero page-hero--contact" aria-label="Contact hero" data-page="contact">
  <div class="container">
    <div class="page-hero__inner">
  
      <h1 class="page-hero__title">Contact Us</h1>
    
    </div>
  </div>
</section>

<section class="section section--white contact-section" aria-label="Contact">
  <div class="container">
    <div class="contact-panel">
      <div class="contact-panel__grid">
        <div class="contact-panel__aside">
          <div class="section-intro contact-intro">
            
            <h2 class="section-title">Reach our bakery team</h2>
            <p class="section-copy">We’re here to help with wedding cakes, custom orders, tastings and same-day delivery from Nashik.</p>
          </div>

          <div class="contact-info-grid">
            <article class="contact-card contact-card--highlight">
              <div class="contact-card__icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
              </div>
              <div>
                <h3 class="contact-card__title">Visit Our Bakery</h3>
                <p class="contact-card__text"><?= htmlspecialchars($businessAddress, ENT_QUOTES, 'UTF-8') ?></p>
              
              </div>
            </article>

            <article class="contact-card">
              <div class="contact-card__icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
              </div>
              <div>
                <h3 class="contact-card__title">Call or WhatsApp</h3>
                <?php if ($phoneHref !== ''): ?>
                  <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>" class="contact-card__link"><?= htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                  <p class="contact-card__text"><?= htmlspecialchars($contactPhone !== '' ? $contactPhone : 'Contact number unavailable', ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($whatsappHref !== ''): ?>
                  <a href="<?= htmlspecialchars($whatsappHref, ENT_QUOTES, 'UTF-8') ?>" class="contact-card__link" target="_blank" rel="noopener">WhatsApp Chat</a>
                <?php endif; ?>
              </div>
            </article>

            <article class="contact-card">
              <div class="contact-card__icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </div>
              <div>
                <h3 class="contact-card__title">Email Us</h3>
                <?php if ($emailHref !== ''): ?>
                  <a href="<?= htmlspecialchars($emailHref, ENT_QUOTES, 'UTF-8') ?>" class="contact-card__link"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                  <p class="contact-card__text">Email unavailable</p>
                <?php endif; ?>
              </div>
            </article>

            <article class="contact-card">
              <div class="contact-card__icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="12,6 12,12 16,14"/>
                </svg>
              </div>
              <div>
                <h3 class="contact-card__title">Business Hours</h3>
                <p class="contact-card__text"><?= htmlspecialchars($businessHours, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($websiteHref !== ''): ?>
                  <a href="<?= htmlspecialchars($websiteHref, ENT_QUOTES, 'UTF-8') ?>" class="contact-card__link" target="_blank" rel="noopener">Visit Website</a>
                <?php endif; ?>
              
              </div>
            </article>
          </div>
        </div>

        <div class="contact-panel__form">
          <div class="contact-form-card">
            <div class="contact-form-header">
              <h2 class="contact-form-title">Send a Message</h2>
              <p class="contact-form-subtitle">Fill out the form below and we'll get back to you within 24 hours.</p>
            </div>

          <div class="contact-form-embed-wrapper">
            <div class="contact-form-loader" id="contactFormLoader" aria-hidden="true">
              <div class="cfl-row"><div class="cfl-label"></div><div class="cfl-input"></div></div>
              <div class="cfl-row"><div class="cfl-label"></div><div class="cfl-input"></div></div>
              <div class="cfl-row"><div class="cfl-label"></div><div class="cfl-input"></div></div>
              <div class="cfl-row"><div class="cfl-label"></div><div class="cfl-textarea"></div></div>
              <div class="cfl-btn"></div>
            </div>
            <iframe class="contact-form-embed" src="<?= htmlspecialchars($contactFormEmbedUrl, ENT_QUOTES, 'UTF-8') ?>"
          width="100%"
          height="600"
          frameborder="0"
          onload="(function(el){var loader=document.getElementById('contactFormLoader');if(loader)loader.style.display='none';el.classList.add('cfl-ready');})(this)">
  </iframe>
          </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section contact-map-section" aria-label="Map">
  <div class="container">
    <div class="contact-map">
      <div class="contact-map__header">
        <h2 class="contact-map__title">Find us in Nashik</h2>
        <p class="contact-map__text">Visit our bakery for cake tastings, pickups and custom consultations.</p>
      </div>
     <iframe class="contact-map__iframe" src="<?= htmlspecialchars($mapEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
  </div>
</section>



