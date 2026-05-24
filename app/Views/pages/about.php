<?php
/* Cakeouflage — About Page */
?>
<?php

$aboutVideo = '/client/assets/video/heroabout.MP4';

try {
    $db = \App\Core\Database::getInstance();
    $aboutBanner = $db->fetchOne(
        "SELECT image_url FROM banners WHERE placement = 'about_video' AND is_active = 1 ORDER BY id DESC LIMIT 1"
    );
    if (is_array($aboutBanner) && trim((string)($aboutBanner['image_url'] ?? '')) !== '') {
      $aboutVideo = (string)$aboutBanner['image_url'];
    }
} catch (\Throwable $e) {
    // Keep empty fallback when DB/banner lookup fails.
}

  if (!preg_match('#^https?://#i', $aboutVideo)) {
    if ($aboutVideo[0] !== '/') {
      $aboutVideo = '/' . $aboutVideo;
    }

    if (strpos($aboutVideo, '/uploads/') === 0) {
      $aboutVideo = '/public' . $aboutVideo;
    }
  }

?>

<section class="page-hero hero-video">

<video autoplay muted loop playsinline preload="auto" class="hero-video__bg">
  <source src="<?php echo htmlspecialchars($aboutVideo, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
</video>

</section>


<!-- BRAND STORY 
<section class="section section--white" aria-label="Brand story">
  <div class="container">
    <div class="about-story-grid">
      <div class="about-story__visual">
        <div class="about-story__img-frame">
          <img src="/client/assets/images/about-story.jpg"
               alt="Cakeouflage artisanal bakery kitchen"
               class="about-story__img"
               loading="lazy"
               onerror="this.style.display='none'">
          <div class="about-story__img-placeholder">
            <span class="about-story__year">ESTD.<br>2017</span>
          </div>
        </div>
        <div class="about-story__badge">
          <svg viewBox="0 0 120 120" class="about-story__badge-ring" aria-hidden="true">
            <defs><path id="circle" d="M60,10 a50,50 0 1,1 -0.1,0"/></defs>
            <text class="about-story__badge-text"><textPath href="#circle">We bake sweet wonderful happy memories · </textPath></text>
          </svg>
          <span class="about-story__badge-icon">🎂</span>
        </div>
      </div>

      <div class="about-story__copy">
        <div class="section-label">About Us</div>
        <h2 class="section-title">A Passion Project That Became Nashik's Favourite Patisserie</h2>
        <p>Cakeouflage is an artisanal bakehouse that started in 2017 as a mother-son passion project — with little chef Ansh Mehra still in school and Ritu, a housewife, working side by side.</p>
        <p>After formal training at the Academy of Pastry and Culinary Arts and hands-on experience at the Oberoi Hotel Group, Ansh returned to Nashik in 2022 with one clear vision: to make Cakeouflage the definitive dessert and celebration cake brand of Nashik.</p>
        <p>Today Cakeouflage offers a full range of handcrafted celebration cakes, premium desserts, festive gifting hampers, and cake-making workshops — all made in small batches with the highest quality ingredients.</p>
        <div class="about-story__stats">
          <div class="about-stat">
            <span class="about-stat__num">2017</span>
            <span class="about-stat__label">Established</span>
          </div>
          <div class="about-stat">
            <span class="about-stat__num">5000+</span>
            <span class="about-stat__label">Cakes Delivered</span>
          </div>
          <div class="about-stat">
            <span class="about-stat__num">4.9★</span>
            <span class="about-stat__label">Average Rating</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
-->
<!-- BRAND STORY -->
<section class="section section--white about-story" aria-label="Brand story">
  <div class="container">
    <div class="about-story__doodles" aria-hidden="true">
      <span class="about-story__doodle about-story__doodle--cupcake">🧁</span>
      <span class="about-story__doodle about-story__doodle--slice">🍰</span>
      <span class="about-story__doodle about-story__doodle--sparkle">✨</span>
    </div>
    <div class="about-story__inner about-story--compact">
      <div class="about-story__copy about-story__copy--centered">
        <div class="section-label">About Us</div>
        <h2 class="section-title about-story__title">A Passion Project That Became Nashik's Favourite Patisserie</h2>
        <p>Cakeouflage is an artisanal bakehouse that started in 2014 as a mother-son passion project — with little chef Ansh Mehra still in school and Ritu, a housewife, working side by side.</p>
        <p>After formal training at the Academy of Pastry and Culinary Arts and hands-on experience at the Oberoi Hotel Group, Ansh returned to Nashik in 2023 with one clear vision: to make Cakeouflage the definitive dessert and celebration cake brand of Nashik.</p>
        <p>Cakeouflage is a premium artisanal cake studio from Nashik, creating custom celebration cakes, premium dessert gifts, and designer bakery experiences.</p>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  var section = document.querySelector('.about-story');
  if (!section) {
    return;
  }

  var doodles = section.querySelectorAll('.about-story__doodle');
  if (!doodles.length) {
    return;
  }
  var doodleLayer = section.querySelector('.about-story__doodles');
  if (!doodleLayer) {
    return;
  }

  var targetX = 0;
  var targetY = 0;
  var currentX = 0;
  var currentY = 0;
  var ticking = false;

  function animateParallax() {
    currentX += (targetX - currentX) * 0.08;
    currentY += (targetY - currentY) * 0.08;

    doodleLayer.style.transform = 'translate3d(' + currentX.toFixed(2) + 'px, ' + currentY.toFixed(2) + 'px, 0)';

    if (Math.abs(targetX - currentX) > 0.1 || Math.abs(targetY - currentY) > 0.1) {
      requestAnimationFrame(animateParallax);
    } else {
      ticking = false;
    }
  }

  function scheduleParallax() {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(animateParallax);
    }
  }

  section.addEventListener('mousemove', function (event) {
    var rect = section.getBoundingClientRect();
    var relX = (event.clientX - rect.left) / rect.width - 0.5;
    var relY = (event.clientY - rect.top) / rect.height - 0.5;
    targetX = relX * 14;
    targetY = relY * 10;
    scheduleParallax();
  });

  section.addEventListener('mouseleave', function () {
    targetX = 0;
    targetY = 0;
    scheduleParallax();
  });

  window.addEventListener('scroll', function () {
    var rect = section.getBoundingClientRect();
    var viewportCenter = window.innerHeight * 0.5;
    var sectionCenter = rect.top + rect.height * 0.5;
    var drift = (sectionCenter - viewportCenter) * -0.015;
    targetY = Math.max(-8, Math.min(8, targetY + drift));
    scheduleParallax();
  }, { passive: true });
}());
</script>

<!-- VALUES WHY CHOOSE US — PREMIUM RADIAL LAYOUT -->
<section class="section wcu-section" aria-label="Our values">
  <div class="container">

    <div class="section-header section-header--center">
    
      <h2 class="wcu-heading">
        Crafted with Passion,<br>
        <em class="wcu-heading__em">Chosen for You.</em><span class="wcu-heading__heart" aria-hidden="true">♡</span>
      </h2>
     
    </div>

    <div class="wcu-layout">

      <!-- LEFT CARDS -->
      <div class="wcu-col wcu-col--left">

        <div class="wcu-item">
          <div class="wcu-card">
            <div class="wcu-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#80001F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 11l19-9-9 19-2-8-8-2z"/>
              </svg>
            </div>
            <div class="wcu-card__body">
              <h3>Handcrafted Excellence</h3>
              <p>Made fresh from scratch, with love in every detail.</p>
            </div>
          </div>
          <div class="wcu-line"></div>
        </div>

        <div class="wcu-item">
          <div class="wcu-card">
            <div class="wcu-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#80001F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2a4 4 0 0 1 4 4H8a4 4 0 0 1 4-4z"/>
                <rect x="3" y="6" width="18" height="4" rx="1"/>
                <path d="M5 10v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"/>
                <line x1="12" y1="10" x2="12" y2="20"/>
              </svg>
            </div>
            <div class="wcu-card__body">
              <h3>Premium Ingredients</h3>
              <p>Only the finest ingredients for rich flavour and quality.</p>
            </div>
          </div>
          <div class="wcu-line"></div>
        </div>

        <div class="wcu-item">
          <div class="wcu-card">
            <div class="wcu-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#80001F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 20h9"/>
                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
              </svg>
            </div>
            <div class="wcu-card__body">
              <h3>Customised Designs</h3>
              <p>Tailored to your vision, perfect for your moments.</p>
            </div>
          </div>
          <div class="wcu-line"></div>
        </div>

      </div><!-- /wcu-col--left -->

      <!-- CENTER IMAGE -->
      <div class="wcu-center">
        <div class="wcu-center__glow" aria-hidden="true"></div>
        <div class="wcu-center__ring">
       <img src="/client/assets/images/centerimage.png"
     alt="Cakeouflage signature celebration cake"
     class="wcu-center__img"
     loading="lazy">
        </div>
      </div>

      <!-- RIGHT CARDS -->
      <div class="wcu-col wcu-col--right">

        <div class="wcu-item">
          <div class="wcu-line"></div>
          <div class="wcu-card">
            <div class="wcu-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#80001F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg>
            </div>
            <div class="wcu-card__body">
              <h3>Trusted Celebrations</h3>
              <p>From birthdays to weddings, we're part of your memories.</p>
            </div>
          </div>
        </div>

        <div class="wcu-item">
          <div class="wcu-line"></div>
          <div class="wcu-card">
            <div class="wcu-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#80001F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                <line x1="9" y1="9" x2="9.01" y2="9"/>
                <line x1="15" y1="9" x2="15.01" y2="9"/>
              </svg>
            </div>
            <div class="wcu-card__body">
              <h3>Aesthetic &amp; Tasty</h3>
              <p>Stunning designs that taste as amazing as they look.</p>
            </div>
          </div>
        </div>

        <div class="wcu-item">
          <div class="wcu-line"></div>
          <div class="wcu-card">
            <div class="wcu-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#80001F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <div class="wcu-card__body">
              <h3>Personal Experience</h3>
              <p>We listen, we create, we make it yours.</p>
            </div>
          </div>
        </div>

      </div><!-- /wcu-col--right -->

    </div><!-- /wcu-layout -->

    <div class="wcu-cta">
      <a href="<?= $baseUrl ?>/custom-cake-inquiry" class="wcu-btn">LET'S CREATE YOUR CAKE &rarr;</a>
    </div>

  </div>
</section>

<script>
(function () {
  var cards = document.querySelectorAll('.wcu-card');
  if (!cards.length || !('IntersectionObserver' in window)) {
    cards.forEach(function (c) { c.classList.add('is-visible'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
    });
  }, { threshold: 0.12 });
  cards.forEach(function (c) { io.observe(c); });
}());
</script>

<!-- ABOUT CHEF 
<section class="section section--white chef-timeline" aria-label="About the chef">
  <div class="container">

   
    <div class="section-header section-header--center">
      <div class="section-label">About the Chef</div>
      <h2 class="section-title luxury-title">Chef Ansh Mehra</h2>
      <p class="section-desc chef-quote">
        “Every cake is not just baked — it’s crafted to be remembered.”
      </p>
    </div>

    <div class="timeline">

      <div class="timeline-item">
        <div class="timeline-dot">🎂</div>
        <div class="timeline-content">
          <span class="timeline-year">2017</span>
          <h3>The Beginning</h3>
          <p>Started as a passion project by a mother-son duo while Ansh was still in school.</p>
        </div>
      </div>

      <div class="timeline-item">
        <div class="timeline-dot">🎓</div>
        <div class="timeline-content">
          <h3>Formal Training</h3>
          <p>Studied at the Academy of Pastry and Culinary Arts to master professional baking.</p>
        </div>
      </div>

      <div class="timeline-item">
        <div class="timeline-dot">🏨</div>
        <div class="timeline-content">
          <h3>Luxury Experience</h3>
          <p>Worked with the Oberoi Hotel Group, gaining expertise in high-end pastry kitchens.</p>
        </div>
      </div>

      <div class="timeline-item">
        <div class="timeline-dot">📍</div>
        <div class="timeline-content">
          <span class="timeline-year">2022</span>
          <h3>Return to Nashik</h3>
          <p>Returned to expand Cakeouflage into a premium artisan cake brand.</p>
        </div>
      </div>

    </div>

  </div>
</section>
-->
<!-- WHAT WE OFFER -->
<section class="section section--pink offer-section" aria-label="What we offer">
  <div class="container">
    <div class="offer-showcase-head">
      <div class="offer-pill" role="presentation">
        <span class="offer-pill__text">WHAT WE OFFER?</span>
        <span class="offer-pill__ornament" aria-hidden="true"></span>
      </div>
      <h2 class="section-title offer-title">More Than Just Cakes</h2>
      <p class="offer-subtitle">Celebration-worthy creations designed to delight every moment.</p>
    </div>
    <div class="offer-grid">
      <a href="/category/birthday-cakes" class="offer-card">
        <span class="offer-card__emoji">🎂</span>
        <h3>Celebration Cakes</h3>
        <p>Birthday, anniversary, baby shower, wedding, and custom occasion cakes — fully personalised.</p>
      </a>
      <a href="/category/cheesecakes" class="offer-card">
        <span class="offer-card__emoji">🍰</span>
        <h3>Dessert Range</h3>
        <p>Cheesecakes, opera cakes, mousse cakes, tiramisu, tarts, and seasonal specials.</p>
      </a>
      <a href="/category/brownies" class="offer-card">
        <span class="offer-card__emoji">🍫</span>
        <h3>Small Bakes &amp; Gifting</h3>
        <p>Brownies, cookies, chocolates, dessert tubs, and premium gifting hampers for every occasion.</p>
      </a>
      <a href="/course" class="offer-card">
        <span class="offer-card__emoji">📚</span>
        <h3>Cake Workshops</h3>
        <p>Beginner to professional-level cake-making classes and festive baking workshops.</p>
      </a>
      <a href="/b2b" class="offer-card">
        <span class="offer-card__emoji">🏢</span>
        <h3>B2B &amp; Corporate</h3>
        <p>Bulk orders, corporate gifting, restaurant dessert supply, and reseller programs.</p>
      </a>
      <a href="/contact" class="offer-card">
        <span class="offer-card__emoji">📍</span>
        <h3>Pop-up &amp; Events</h3>
        <p>Dessert stall setups, event catering, and seasonal pop-ups across Nashik.</p>
      </a>
    </div>
  </div>
</section>
 <?php /*
<!-- CTA STRIP -->
<section class="cta-strip" aria-label="Call to action">
  <div class="container cta-strip__inner">
    <div class="cta-strip__copy">
      <h2>Ready to order your dream cake?</h2>
      <p>Browse our collection or send us a custom enquiry — we'll make it happen.</p>
    </div>
    <div class="cta-strip__actions">
      <a href="/shop" class="btn btn--primary btn--lg">Shop Cakes</a>
      <a href="/contact" class="btn btn--outline-white btn--lg">Custom Enquiry</a>
    </div>
  </div>
</section>*/?>

<?php /*<script>
document.addEventListener("DOMContentLoaded", function () {

  const hero = document.querySelector(".hero-slider");

  const images = [
   "/client/assets/images/slide1.png",
    "/client/assets/images/slide2.png",
      "/client/assets/images/slide3.png",
  ];

  let index = 0;

  function changeHeroImage() {
    hero.style.backgroundImage = `url(${images[index]})`;
    index = (index + 1) % images.length;
  }

  changeHeroImage();
  setInterval(changeHeroImage, 3000);

});
</script>
*/?>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const video = document.querySelector(".hero-video__bg");

  if (video) {
    // autoplay try
    video.play().catch(() => {});

    // 🔥 force loop fix
    video.addEventListener("ended", function () {
      video.currentTime = 0;
      video.play();
    });
  }
});
</script>