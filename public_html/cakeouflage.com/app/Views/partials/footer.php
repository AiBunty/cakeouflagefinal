<?php
$base = '';
$contactPhoneDisplay = (string)($siteConfig['contact']['phone'] ?? '+91 9673565935');
$contactPhoneHref = preg_replace('/[^0-9+]/', '', $contactPhoneDisplay) ?: '+919673565935';
$whatsappDigits = preg_replace('/\D+/', '', $contactPhoneDisplay) ?: '919673565935';
if (strlen($whatsappDigits) === 10) {
    $whatsappDigits = '91' . $whatsappDigits;
}
$contactEmailDisplay = (string)($siteConfig['contact']['email'] ?? 'cakeouflage@gmail.com');
$contactCityDisplay = (string)($siteConfig['contact']['city'] ?? 'Nashik');
$contactStateDisplay = (string)($siteConfig['business']['state'] ?? 'Maharashtra');
$businessAddressParts = array();
if (!empty($siteConfig['business']['address_line1'])) {
  $businessAddressParts[] = (string)$siteConfig['business']['address_line1'];
}
if (!empty($siteConfig['business']['address_line2'])) {
  $businessAddressParts[] = (string)$siteConfig['business']['address_line2'];
}
if (!empty($siteConfig['business']['postal_code'])) {
  $businessAddressParts[] = 'PIN: ' . (string)$siteConfig['business']['postal_code'];
}
$businessAddressDisplay = implode(', ', $businessAddressParts);
$whatsappLink = 'https://wa.me/' . $whatsappDigits . '?text=' . rawurlencode('Hi Cakeouflage! I would like to place a cake order.');
?>
<div class="floating-contact-rail" aria-label="Quick contact actions">
  <a href="tel:<?= htmlspecialchars($contactPhoneHref, ENT_QUOTES, 'UTF-8') ?>" class="floating-contact-rail__link floating-contact-rail__link--call" aria-label="Call Cakeouflage">
    <span class="floating-contact-rail__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.36 2 2 0 0 1 3.58 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.18 6.18l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
    </span>
    <span class="floating-contact-rail__label">Call</span>
  </a>
  <a href="<?= htmlspecialchars($whatsappLink, ENT_QUOTES, 'UTF-8') ?>" class="floating-contact-rail__link floating-contact-rail__link--whatsapp" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp Cakeouflage">
    <span class="floating-contact-rail__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.86-2.03-.96-.27-.1-.47-.15-.67.15-.2.29-.77.96-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.47-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.18-.29-.02-.45.13-.6.13-.13.3-.35.44-.52.15-.17.2-.29.3-.49.1-.2.05-.37-.03-.52-.07-.15-.66-1.61-.91-2.21-.24-.57-.48-.49-.66-.5l-.57-.01c-.2 0-.52.08-.79.38-.27.29-1.03 1.01-1.03 2.47 0 1.46 1.06 2.87 1.21 3.07.15.2 2.09 3.2 5.08 4.49.71.3 1.26.49 1.69.62.72.23 1.37.19 1.88.12.57-.09 1.76-.72 2-1.41.25-.69.25-1.29.18-1.41-.08-.13-.27-.2-.57-.35Z"/><path d="M20.52 3.49A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.33.16 11.89c0 2.1.55 4.14 1.59 5.95L0 24l6.46-1.69a11.89 11.89 0 0 0 5.59 1.43h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.17-3.43-8.36Zm-8.47 18.24h-.01a9.9 9.9 0 0 1-5.05-1.39l-.36-.21-3.83 1 1.02-3.74-.23-.38a9.84 9.84 0 0 1-1.52-5.23C2.07 6.33 6.5 1.9 11.95 1.9c2.63 0 5.1 1.02 6.96 2.89a9.8 9.8 0 0 1 2.89 6.97c0 5.45-4.43 9.88-9.87 9.88Z"/></svg>
    </span>
    <span class="floating-contact-rail__label">WhatsApp</span>
  </a>
  <a href="https://www.instagram.com/cakeouflage/" class="floating-contact-rail__link floating-contact-rail__link--instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram Cakeouflage">
    <span class="floating-contact-rail__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm-.2 2A3.6 3.6 0 0 0 4 7.6v8.8A3.6 3.6 0 0 0 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6A3.6 3.6 0 0 0 16.4 4H7.6zm9.65 1.45a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
    </span>
    <span class="floating-contact-rail__label">Instagram</span>
  </a>
</div>

<footer class="site-footer" aria-label="Site Footer">
  <div class="container site-footer__inner">

    <!-- Brand column -->
    <div class="site-footer__brand">
    <div class="site-footer__logo">
  

  <img src="/client/assets/images/whitelogo.png" alt="Cakeouflage Logo" style="height:140px;max-width:100%;width:auto;object-fit:contain;">

</div>
      <p class="site-footer__tagline"><?= htmlspecialchars((string)$brand['tagline'], ENT_QUOTES, 'UTF-8') ?></p>
      <div class="site-footer__social">
        <a href="https://www.instagram.com/cakeouflage/" class="social-btn" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
        </a>
        <a href="https://www.facebook.com/Cakeouflage/" class="social-btn" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        </a>
        <a href="<?= htmlspecialchars($whatsappLink, ENT_QUOTES, 'UTF-8') ?>" class="social-btn" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
        </a>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="site-footer__col">
      <h4 class="site-footer__col-title">Shop</h4>
      <ul class="site-footer__links">
        <li><a href="<?= $base ?>/shop">All Products</a></li>
        <li><a href="<?= $base ?>/custom-cake-inquiry">Custom Orders</a></li>
   <li>
  <a 
    href="<?= htmlspecialchars($whatsappLink, ENT_QUOTES, 'UTF-8') ?>" 
    target="_blank" 
    rel="noopener noreferrer"
  >
    WhatsApp Orders
  </a>
</li>
       
        <li><a href="<?= $base ?>/orders">Track Orders</a></li>
      </ul>
    </div>

   <div class="site-footer__col">
  <h4 class="site-footer__col-title">Support</h4>

  <ul class="site-footer__links">

    <li>
      <a href="<?= $base ?>/about">About Us</a>
    </li>

   

    <li>
      <a href="<?= $base ?>/contact">
        Contact Us
      </a>
    </li>

  </ul>
</div>

    <!-- Newsletter + Contact -->
    <div class="site-footer__col site-footer__col--newsletter">
      <h4 class="site-footer__col-title">Stay in the Loop</h4>
      <p class="site-footer__newsletter-desc">New flavours, seasonal drops &amp; exclusive offers — right to your inbox.</p>
      <form class="footer-newsletter" action="<?= $base ?>/api/newsletter/subscribe" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      
      </form>
      <div class="site-footer__contact">
        <a href="tel:<?= htmlspecialchars($contactPhoneHref, ENT_QUOTES, 'UTF-8') ?>" class="site-footer__contact-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.36 2 2 0 0 1 3.58 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.09 6.09l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <?= htmlspecialchars($contactPhoneDisplay, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <a href="mailto:<?= htmlspecialchars($contactEmailDisplay, ENT_QUOTES, 'UTF-8') ?>" class="site-footer__contact-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <?= htmlspecialchars($contactEmailDisplay, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <span class="site-footer__contact-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?= htmlspecialchars($contactCityDisplay, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($contactStateDisplay, ENT_QUOTES, 'UTF-8') ?>, India
        </span>
        <?php if ($businessAddressDisplay !== ''): ?>
        <span class="site-footer__contact-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-7 9 7"></path><path d="M9 22V12h6v10"></path></svg>
          <?= htmlspecialchars($businessAddressDisplay, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($siteConfig['business']['gst_number'])): ?>
        <span class="site-footer__contact-item">
          GST: <?= htmlspecialchars((string)$siteConfig['business']['gst_number'], ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Bottom bar -->
  <div class="site-footer__bottom">
    <div class="container site-footer__bottom-inner">
      <p>© <?= date('Y') ?> <?= htmlspecialchars((string)$brand['name'], ENT_QUOTES, 'UTF-8') ?>. All rights reserved. Made with ♥ in <?= htmlspecialchars($contactCityDisplay, ENT_QUOTES, 'UTF-8') ?>.</p>
      <div class="site-footer__dev-credit" aria-label="Developer credit">
        <span class="site-footer__dev-label">Developed by</span>
        <a href="https://dcoresystems.com" target="_blank" rel="noopener noreferrer" class="site-footer__dev-link" title="DCore Systems">
          <img class="site-footer__dev-logo site-footer__dev-logo--white" src="/client/assets/images/dcore-logo-white.svg" alt="DCore Systems">
          <img class="site-footer__dev-logo site-footer__dev-logo--black" src="/client/assets/images/dcore-logo-black.svg" alt="DCore Systems">
          <span class="site-footer__dev-domain">dcoresystems.com</span>
        </a>
      </div>
      <nav class="site-footer__policy-links" aria-label="Policy Links">
        <?php foreach ($siteConfig['footerLinks'] as $link): ?>
          <a href="<?= htmlspecialchars((string)$link['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$link['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>
</footer>
