<?php
/**
 * Cakeouflage — Homepage
 * Dependencies: $featuredCategories, $bestsellerProducts, $testimonials
 */
$featuredCategories = $featuredCategories ?? [];
$bestsellerProducts = $bestsellerProducts ?? [];
$testimonials       = $testimonials ?? [];
$homeAssetBase      = $baseUrl . '/client/assets/images/home';
$collectionAssetMap = [
    'cakes' => $homeAssetBase . '/collection-birthday.svg',
    'wedding-cakes' => $homeAssetBase . '/collection-wedding.svg',
    'cupcakes' => $homeAssetBase . '/collection-cupcakes.svg',
    'eggless' => $homeAssetBase . '/collection-eggless.svg',
];


$signatureCollections = [];
foreach (array_slice($featuredCategories, 0, 4) as $cat) {
    if (!is_array($cat)) {
        continue;
    }

    $signatureCollections[] = [
        'name'  => (string)($cat['name'] ?? 'Collection'),
        'slug'  => (string)($cat['slug'] ?? ''),
        'href'  => '/shop?cat=' . rawurlencode((string)($cat['slug'] ?? '')),
        'image' => (string)($cat['image'] ?? ($collectionAssetMap[(string)($cat['slug'] ?? '')] ?? ($homeAssetBase . '/collection-birthday.svg'))),
        'meta'  => !empty($cat['product_count']) ? ((int)$cat['product_count']) . ' creations' : 'Made to order',
        'note'  => 'Elegant finishes, fresh bakes, and detail-led presentation.',
    ];
}

if (empty($signatureCollections)) {
    $signatureCollections = [
        [
            'name' => 'Birthday Cakes',
            'slug' => 'cakes',
            'href' => '/shop?cat=cakes',
            'image' => $homeAssetBase . '/collection-birthday.svg',
            'meta' => 'Most loved',
            'note' => 'Clean designs, playful themes, and celebration-ready delivery.',
        ],
        [
            'name' => 'Wedding Cakes',
            'slug' => 'wedding-cakes',
          'href' => '/category',
            'image' => $homeAssetBase . '/collection-wedding.svg',
            'meta' => 'Statement pieces',
            'note' => 'Tall tiers, floral finishes, and premium dessert-table styling.',
        ],
        [
            'name' => 'Cupcakes',
            'slug' => 'cupcakes',
            'href' => '/shop?cat=cupcakes',
            'image' => $homeAssetBase . '/collection-cupcakes.svg',
            'meta' => 'Party friendly',
            'note' => 'Small-format indulgence for gifting, events, and dessert bars.',
        ],
        [
            'name' => 'Eggless Specials',
            'slug' => 'eggless',
            'href' => '/shop?filter=eggless',
            'image' => $homeAssetBase . '/collection-eggless.svg',
            'meta' => 'Customer favourite',
            'note' => 'Soft textures and full flavour without compromising on finish.',
        ],
    ];
}

$editorialMoments = [
    [
        'eyebrow' => 'Studio',
        'title' => 'Soft-finish celebration cakes',
        'copy' => 'Balanced colour palettes, smooth frosting, and details that photograph beautifully.',
        'image' => $homeAssetBase . '/gallery-studio.svg',
        'class' => 'is-tall',
    ],
    [
        'eyebrow' => 'Detail',
        'title' => 'Textured piping and florals',
        'copy' => 'Built for close-up moments as much as for the full table reveal.',
        'image' => $homeAssetBase . '/gallery-detail.svg',
        'class' => '',
    ],
    [
        'eyebrow' => 'Kitchen',
        'title' => 'Fresh production every day',
        'copy' => 'Batch baking, finishing, and boxing handled in-house.',
        'image' => $homeAssetBase . '/gallery-kitchen.svg',
        'class' => '',
    ],
    [
        'eyebrow' => 'Moments',
        'title' => 'Designed for birthdays, weddings, and gifting',
        'copy' => 'A homepage should feel like a premium cake studio, not a crowded catalogue.',
        'image' => $homeAssetBase . '/gallery-moments.svg',
        'class' => 'is-wide',
    ],
];

$customShowcase = [
    [
        'title' => 'Floral Buttercream',
        'copy' => 'Soft-toned finishes for intimate celebrations and elegant milestone tables.',
        'image' => $homeAssetBase . '/custom-floral.svg',
    ],
    [
        'title' => 'Theme Cakes for Kids',
        'copy' => 'Character-led cakes that still look polished instead of noisy.',
        'image' => $homeAssetBase . '/custom-kids.svg',
    ],
    [
        'title' => 'Luxury Tier Cakes',
        'copy' => 'Clean structure, refined detailing, and statement presentation for high-value events.',
        'image' => $homeAssetBase . '/custom-tier.svg',
    ],
];

if (empty($bestsellerProducts)) {
    $bestsellerProducts = [
        [
            'name' => 'Blush Bloom Cake',
            'slug' => 'blush-bloom-cake',
            'href' => '/shop',
            'thumb' => $homeAssetBase . '/product-blush-bloom.svg',
            'min_price' => 1299,
            'short_description' => 'Soft buttercream finish with a premium celebration look.',
            'dietary_tag' => 'eggless',
        ],
        [
            'name' => 'Midnight Cocoa Luxe',
            'slug' => 'midnight-cocoa-luxe',
            'href' => '/shop',
            'thumb' => $homeAssetBase . '/product-midnight-cocoa.svg',
            'min_price' => 1499,
            'short_description' => 'Dark chocolate layers styled for elegant gifting and events.',
            'dietary_tag' => 'regular',
        ],
        [
            'name' => 'Party Swirl Cupcake Box',
            'slug' => 'party-swirl-cupcake-box',
            'href' => '/shop?cat=cupcakes',
            'thumb' => $homeAssetBase . '/product-party-swirl.svg',
            'min_price' => 799,
            'short_description' => 'Colour-balanced cupcakes finished for birthdays and dessert bars.',
            'dietary_tag' => 'eggless',
        ],
        [
            'name' => 'Pearl Tier Celebration',
            'slug' => 'pearl-tier-celebration',
          'href' => '/category',
            'thumb' => $homeAssetBase . '/product-pearl-tier.svg',
            'min_price' => 2199,
            'short_description' => 'A polished statement cake for milestone celebrations and premium orders.',
            'dietary_tag' => 'regular',
        ],
    ];
}

$homeTestimonials = [
  [
    'name' => 'ID. Manali Shadija',
    'role' => 'Birthday Order',
    'text' => 'I’m so happy to have received the cake exactly as ordered — my friends absolutely loved it! ❤️
Even though I’m not a chocolate fan, I couldn’t resist this one — the flavours and buttercream were simply divine.',
    'initial' => 'M',
  ],
  [
    'name' => 'Vishakha',
    'role' => 'Anniversary Surprise',
    'text' => 'Ansh, the cake was superb!!!
It was so yummy...
My husband just loved it! 
The presentation, flavour, everything was just too good and exactly how I thought it should be...
Superb...
Thanks for making our day so special🥰👍🏻👍🏻👍🏻
.',
    'initial' => 'V',
  ],
  [
    'name' => 'Sahil Pahade',
    'role' => 'Engagement Cake',
    'text' => 'We ordered a delicious chocolate truffle cake from Mr. Ansh Mehra and his mother. The cake was excellent in taste and quality. They are both extremely polite, possess outstanding communication skills, and provide exceptional service.',
    'initial' => 'S',
  ],
  [
    'name' => 'sayee',
    'role' => 'Custom Order',
    'text' => 'I first tried their cakes at an exhibition and absolutely loved them! Later, I placed a last-minute order, and within a day, they delivered a stunning cake exactly as requested. The taste was incredible—so fresh with the perfect level of sweetness. Everyone couldn’t stop raving about it! Highly recommend for both beautiful presentation and exceptional flavor.',
    'initial' => 'S',
  ],
  [
    'name' => 'Neha Shah',
    'role' => 'Baby Shower Cake',
    'text' => 'Soft, beautiful, and perfectly themed. Highlight of our event!',
    'initial' => 'N',
  ],
  [
    'name' => 'Rahul Patil',
    'role' => 'Corporate Order',
    'text' => 'Clean design, premium finish, and timely delivery.',
    'initial' => 'R',
  ],
];

$cakeGallery = [
  [
    'image' => $baseUrl . '/client/assets/images/gallery/gallery.jpeg',
    'title' => 'Wedding Elegance',
    'class' => 'is-feature',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/birthday.jpeg',
    'title' => 'Birthday Moments',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/babyshower.jpeg',
    'title' => 'Baby Shower',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/corporate.jpeg',
    'title' => 'Corporate Celebrations',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/pestal.jpeg',
    'title' => 'Pastel Themes',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/classic.jpeg',
    'title' => 'Classic Celebration',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/chocolate.jpeg',
    'title' => 'Chocolate Statements',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/softpink.jpeg',
    'title' => 'Soft Pink Finish',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/signaturelayer.jpeg',
    'title' => 'Signature Layers',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/theme.jpeg',
    'title' => 'Berry Notes',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/mango.jpeg',
    'title' => 'Black Forest Luxe',
    'class' => '',
  ],
  [
    'image' => $baseUrl . '/client/assets/images/gallery/cartoon.jpeg',
    'title' => 'Cartoon',
    'class' => '',
  ],
   [
    'image' => $baseUrl . '/client/assets/images/gallery/birthday2.jpeg',
    'title' => 'Birthday',
    'class' => '',
  ],
   [
    'image' => $baseUrl . '/client/assets/images/gallery/theme2.jpeg',
    'title' => 'Theme',
    'class' => '',
  ],
];
?>
<?php
$banner = [
  'image_url' => '/client/assets/video/Healthcake.MP4',
  'title' => 'Cakeouflage',
  'subtitle' => 'Premium Cakes',
];

$chefMediaUrl = '/client/assets/video/heroabout.MP4';
$healthyMediaUrl = '/client/assets/video/Healthcake.MP4';
$healthyHeadingText = 'Healthy by Cakeouflage';

if (!function_exists('home_normalize_media_url')) {
  function home_normalize_media_url(string $mediaUrl, string $fallback): string
  {
    $mediaUrl = trim($mediaUrl);
    if ($mediaUrl === '') {
      return $fallback;
    }

    if (!preg_match('#^https?://#i', $mediaUrl)) {
      if ($mediaUrl[0] !== '/') {
        $mediaUrl = '/' . $mediaUrl;
      }

      if (strpos($mediaUrl, '/uploads/') === 0) {
        $publicPath = '/public' . $mediaUrl;
        $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($docRoot !== '') {
          $publicFs = rtrim($docRoot, '/\\') . $publicPath;
          $directFs = rtrim($docRoot, '/\\') . $mediaUrl;
          if (file_exists($publicFs)) {
            return $publicPath;
          }
          if (file_exists($directFs)) {
            return $mediaUrl;
          }
        }

        // Keep backward-compatible path where filesystem check is unavailable.
        return $publicPath;
      }
    }

    return $mediaUrl;
  }
}

if (!function_exists('home_append_media_version')) {
  function home_append_media_version(string $mediaUrl): string
  {
    if ($mediaUrl === '' || preg_match('#^https?://#i', $mediaUrl)) {
      return $mediaUrl;
    }

    $path = (string)parse_url($mediaUrl, PHP_URL_PATH);
    if ($path === '') {
      return $mediaUrl;
    }

    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    if ($docRoot === '') {
      return $mediaUrl;
    }

    $absolutePath = $docRoot . $path;
    if (!is_file($absolutePath)) {
      return $mediaUrl;
    }

    $version = (string)((int)filemtime($absolutePath));
    if ($version === '' || $version === '0') {
      return $mediaUrl;
    }

    $delimiter = strpos($mediaUrl, '?') !== false ? '&' : '?';
    return $mediaUrl . $delimiter . 'v=' . rawurlencode($version);
  }
}

if (!function_exists('home_video_mime_from_extension')) {
  function home_video_mime_from_extension(string $ext): string
  {
    $ext = strtolower(trim($ext));
    if ($ext === 'mp4' || $ext === 'm4v') {
      return 'video/mp4';
    }
    if ($ext === 'webm') {
      return 'video/webm';
    }
    if ($ext === 'ogg') {
      return 'video/ogg';
    }
    if ($ext === 'mov') {
      return 'video/quicktime';
    }
    if ($ext === 'avi') {
      return 'video/x-msvideo';
    }
    if ($ext === 'mkv') {
      return 'video/x-matroska';
    }
    if ($ext === 'mpeg' || $ext === 'mpg') {
      return 'video/mpeg';
    }
    return 'video/mp4';
  }
}

try {
  $db = \App\Core\Database::getInstance();
  $row = $db->fetchOne("SELECT image_url, title, subtitle FROM banners WHERE placement = 'home_mid' ORDER BY id DESC LIMIT 1");
  if (is_array($row)) {
    $banner['image_url'] = (string)($row['image_url'] ?? $banner['image_url']);
    $banner['title'] = (string)($row['title'] ?? $banner['title']);
    $banner['subtitle'] = (string)($row['subtitle'] ?? $banner['subtitle']);
  }

  $mediaRows = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('home_chef_video_url','home_healthy_video_url','home_healthy_heading')");
  foreach ($mediaRows as $mediaRow) {
    $key = (string)($mediaRow['setting_key'] ?? '');
    $value = (string)($mediaRow['setting_value'] ?? '');

    if ($key === 'home_chef_video_url' && trim($value) !== '') {
      $chefMediaUrl = $value;
    }
    if ($key === 'home_healthy_video_url' && trim($value) !== '') {
      $healthyMediaUrl = $value;
    }
    if ($key === 'home_healthy_heading' && trim($value) !== '') {
      $healthyHeadingText = $value;
    }
  }

} catch (\Throwable $e) {
  // Keep defaults when banner fetch fails so homepage still renders.
}

$chefMediaUrl = home_normalize_media_url($chefMediaUrl, '/client/assets/video/heroabout.MP4');
$healthyMediaUrl = home_normalize_media_url($healthyMediaUrl, '/client/assets/video/Healthcake.MP4');
$chefMediaUrl = home_append_media_version($chefMediaUrl);
$healthyMediaUrl = home_append_media_version($healthyMediaUrl);

$chefPath = (string)parse_url($chefMediaUrl, PHP_URL_PATH);
$chefExt = strtolower((string)pathinfo($chefPath, PATHINFO_EXTENSION));
$chefIsVideo = in_array($chefExt, array('mp4', 'webm', 'ogg', 'mov', 'm4v'), true);
$chefMime = home_video_mime_from_extension($chefExt);

$heroMediaUrl = (string)($banner['image_url'] ?? '');
$heroMediaUrl = trim($heroMediaUrl);

if ($heroMediaUrl === '') {
  $heroMediaUrl = '/client/assets/video/Healthcake.MP4';
}

if (!preg_match('#^https?://#i', $heroMediaUrl)) {
  if ($heroMediaUrl[0] !== '/') {
    $heroMediaUrl = '/' . $heroMediaUrl;
  }

  if (strpos($heroMediaUrl, '/uploads/') === 0) {
    $heroMediaUrl = '/public' . $heroMediaUrl;
  } elseif (strpos($heroMediaUrl, '/assets/') === 0) {
    $heroMediaUrl = '/client' . $heroMediaUrl;
  }

  $heroPath = parse_url($heroMediaUrl, PHP_URL_PATH);
  $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
  if (is_string($heroPath) && $heroPath !== '' && $docRoot !== '' && !file_exists(rtrim($docRoot, '/\\') . $heroPath)) {
    $heroMediaUrl = '/client/assets/video/Healthcake.MP4';
  }
}

$heroExt = strtolower((string)pathinfo((string)parse_url($heroMediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
$isHeroVideo = in_array($heroExt, ['mp4', 'webm', 'ogg', 'mov', 'm4v'], true);
$heroMime = home_video_mime_from_extension($heroExt);
$heroMediaUrl = home_append_media_version($heroMediaUrl);

$healthyPath = (string)parse_url($healthyMediaUrl, PHP_URL_PATH);
$healthyExt = strtolower((string)pathinfo($healthyPath, PATHINFO_EXTENSION));
$healthyMime = home_video_mime_from_extension($healthyExt);
?>
<section class="home-hero-editorial" aria-label="Homepage hero">
  <?php if ($isHeroVideo): ?>
    <video class="home-hero-editorial__video" autoplay muted loop playsinline preload="metadata" webkit-playsinline>
      <source src="<?= htmlspecialchars($heroMediaUrl, ENT_QUOTES, 'UTF-8'); ?>" type="<?= htmlspecialchars($heroMime, ENT_QUOTES, 'UTF-8'); ?>">
    </video>
  <?php else: ?>
    <img class="home-hero-editorial__video" src="<?= htmlspecialchars($heroMediaUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars((string)$banner['title'], ENT_QUOTES, 'UTF-8'); ?>" loading="eager">
  <?php endif; ?>

  <div class="home-hero-editorial__title-wrap">
    <h1 id="heroTitle" class="home-hero-editorial__title"><?= htmlspecialchars($banner['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
  </div>

  <div class="home-hero-editorial__bottom">
    <span id="heroSubtitle" class="home-hero-editorial__subtitle"><?= htmlspecialchars($banner['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
    <a href="<?= $baseUrl ?>/shop" class="home-hero-editorial__btn">SHOP</a>
  </div>

  <div class="home-hero-editorial__fade" aria-hidden="true"></div>
</section>

<script>
  const heroVideo = document.querySelector('.home-hero-editorial__video');
  if (heroVideo) {
    const startHeroVideo = () => {
      const attempt = heroVideo.play();
      if (attempt && typeof attempt.catch === 'function') {
        attempt.catch(() => {});
      }
    };

    ['loadeddata', 'canplay', 'canplaythrough'].forEach((eventName) => {
      heroVideo.addEventListener(eventName, startHeroVideo);
    });

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        startHeroVideo();
      }
    });

    startHeroVideo();
  }
</script>
<section class="about-chef-split" aria-label="About the Chef">
  <div class="container about-chef-split__grid">
    <div class="about-chef-split__media">
      <?php if ($chefIsVideo): ?>
        <video
          class="about-chef-split__video"
          autoplay
          muted
          loop
          playsinline
          webkit-playsinline
          preload="metadata"
          poster="/client/assets/video/thumbnail.png"
        >
          <source src="<?= htmlspecialchars($chefMediaUrl, ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($chefMime, ENT_QUOTES, 'UTF-8') ?>">
        </video>
      <?php else: ?>
        <img class="about-chef-split__video" src="<?= htmlspecialchars($chefMediaUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Chef Ansh Mehra" loading="lazy">
      <?php endif; ?>
    </div>

    <article class="about-chef-split__card">
      <h2 class="about-chef-split__title">Chef Ansh Mehra</h2>
      <p class="about-chef-split__quote">"A cake should carry emotion first, design second, and flavour forever."</p>

      <ul class="about-chef-split__timeline" aria-label="Chef journey timeline">
        <li><strong>2017</strong> Cakeouflage began as a mother-son passion project.</li>
        <li><strong>2021</strong> Joined the Academy of Pastry & Culinary Arts, Mumbai for professional pastry training.</li>
        <li><strong>2022</strong> Joined Oberoi Trident to gain luxury hospitality experience.</li>
        <li><strong>2023</strong> Returned to Nashik to expand Cakeouflage.</li>
         <li><strong>2025</strong> Opened Nashik’s first bespoke cake studio.</li>
        <li><strong>Today</strong> Crafting elegant cakes for every celebration.</li>
      </ul>
    </article>
  </div>
</section>

<script>
  const video = document.querySelector(".about-chef-split__video");

  if (video && video.tagName && video.tagName.toLowerCase() === 'video') {
    video.muted = true;
    video.defaultMuted = true;

    const playVideo = () => {
      video.play().catch(() => {});
    };

    // Restart slightly before the last frame to avoid black flashes on loop.
    const seamlessLoop = () => {
      if (!Number.isFinite(video.duration) || video.duration <= 0) return;
      if (video.currentTime >= video.duration - 0.08) {
        video.currentTime = 0.01;
        playVideo();
      }
    };

    video.addEventListener("loadeddata", playVideo);
    video.addEventListener("canplay", playVideo);
    video.addEventListener("timeupdate", seamlessLoop);
    video.addEventListener("waiting", playVideo);
    video.addEventListener("stalled", playVideo);

    // fallback (in case browser delays)
    setTimeout(playVideo, 300);
  }
</script>

<!-- 

<section class="specialities">
  <div class="container">

   
    <div class="specialities-header">
      <span class="tag">OUR SPECIALITIES</span>
      <h2>Crafted for Every Celebration</h2>
      <p>Beautifully made cakes and desserts for life’s sweetest moments.</p>
    </div>

  
    <div class="specialities-grid">


      <div class="big-card">
        <img src="https://images.unsplash.com/photo-1578985545062-69928b1d9587" alt="">
        <div class="overlay"></div>

        <div class="content">
          <h3>Celebration Cakes</h3>
          <p>Designed for birthdays, anniversaries & unforgettable moments.</p>
          <a href="#" class="btn">Explore Cakes →</a>
        </div>
      </div>

    
      <div class="side-cards">

   
        <div class="small-card">
          <div class="text">
            <div class="icon">🧁</div>
            <h4>Cupcakes & Desserts</h4>
            <p>Mini delights with maximum happiness.</p>
          </div>
          <div class="img-box">
       <img src="/client/assets/images/cupcake.png">
        </div>
          <div class="arrow">→</div>
        </div>

 
        <div class="small-card">
          <div class="text">
            <div class="icon">🎂</div>
            <h4>Custom Designs</h4>
            <p>Tailored cakes that bring your ideas to life.</p>
          </div>
           <div class="img-box">
        <img src="/client/assets/images/cake.png">
          </div>
          <div class="arrow">→</div>
        </div>

      
        <div class="small-card">
          <div class="text">
            <div class="icon">🎁</div>
            <h4>Premium Gifting</h4>
            <p>Elegant hampers & boxes for every occasion.</p>
          </div>
           <div class="img-box">
        <img src="/client/assets/images/gift.png">
      </div>
          <div class="arrow">→</div>
        </div>

      </div>

    </div>
  </div>
</section>

<section class="discover-section">
  <div class="discover-content">
    <h2>Crafted with Passion, Baked to Perfection</h2>
    <p>Every cake is made fresh, designed with care, and delivered with love.</p>
    
    <a href="<?= $baseUrl ?>/about" class="discover-btn">
      Discover More
    </a>
    <div class="menu-circle-wrap">
 <a href="/client/assets/menu/menu.pdf"  class="menu-circle-btn" target="_blank">

    
    <span class="icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
        <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"
              stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M14 2v6h6"
              stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>

  
    <span class="text">View Menu</span>

  </a>
</div>
  </div>
  
</section>
-->
<!-- ── Healthy by Cakeouflage ── -->
<section class="healthy-section" aria-label="Health by Cakeouflage">
  <video class="healthy-section__video" autoplay muted loop playsinline preload="auto">
    <source src="<?= htmlspecialchars($healthyMediaUrl, ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($healthyMime, ENT_QUOTES, 'UTF-8') ?>">
  </video>

  <div class="healthy-section__inner container">
    <h2 class="healthy-heading"><?= htmlspecialchars($healthyHeadingText, ENT_QUOTES, 'UTF-8') ?></h2>
  </div>
</section>

<!-- ── Custom Cake Categories ── -->
<section class="cake-categories-section">
  <div class="container">

    <div class="cake-categories__header">
      <span class="cake-categories__label">Our Specialities</span>
      <h2 class="cake-categories__title">Designed for Every Celebration</h2>
      <p class="cake-categories__sub">From intimate birthdays to grand corporate events — every cake crafted with heart.</p>
    </div>

    <div class="cake-categories__grid">

      <!-- Wedding Cakes -->
<a href="<?= $baseUrl ?>/category" class="cake-cat-card">
  <div class="cake-cat-card__media">
    <img
      src="/client/assets/images/showcase/wedding.jpg"
      alt="Wedding Cakes"
      loading="lazy"
    >
    <div class="cake-cat-card__overlay"></div>
   
  </div>
  <div class="cake-cat-card__body">
    <h3 class="cake-cat-card__name">Wedding Cakes</h3>
    <p class="cake-cat-card__desc">Tiered elegance and floral artistry for your most special day.</p>
 <span class="cake-cat-card__link">Explore <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
  </div>
</a>

      <!-- Birthday & Anniversary -->
      <a href="<?= $baseUrl ?>/category" class="cake-cat-card">
        <div class="cake-cat-card__media">
          <img
           src="/client/assets/images/showcase/birthday.jpg"
            alt="Birthday & Anniversary Cakes"
            loading="lazy"
          >
          <div class="cake-cat-card__overlay"></div>
             <!--   <span class="cake-cat-card__badge">Most Loved</span> -->
        
        </div>
        <div class="cake-cat-card__body">
          <h3 class="cake-cat-card__name">Birthday &amp; Anniversary</h3>
          <p class="cake-cat-card__desc">Celebratory cakes that make every milestone unforgettable.</p>
          <span class="cake-cat-card__link">Explore <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </div>
      </a>

      <!-- Baby Shower -->
  <a href="<?= $baseUrl ?>/category" class="cake-cat-card">
        <div class="cake-cat-card__media">
          <img
            src="/client/assets/images/showcase/babyshower.jpg"
            alt="Baby Shower Cakes"
            loading="lazy"
          >
          <div class="cake-cat-card__overlay"></div>
       
        </div>
        <div class="cake-cat-card__body">
          <h3 class="cake-cat-card__name">Baby Shower</h3>
          <p class="cake-cat-card__desc">Soft pastel designs and charming themes for precious arrivals.</p>
          <span class="cake-cat-card__link">Explore <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </div>
      </a>

      <!-- Corporate Cakes -->
<a href="<?= $baseUrl ?>/category" class="cake-cat-card">
        <div class="cake-cat-card__media">
          <img
            src="/client/assets/images/showcase/corporate.jpg"
            alt="Corporate Cakes"
            loading="lazy"
          >
          <div class="cake-cat-card__overlay"></div>
      
        </div>
        <div class="cake-cat-card__body">
          <h3 class="cake-cat-card__name">Corporate Cakes</h3>
          <p class="cake-cat-card__desc">Branded, polished cakes for corporate events, launches &amp; gifting.</p>
          <span class="cake-cat-card__link">Explore <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </div>
      </a>

    </div>
    <div class="cake-categories__dots" id="cakeCategoriesDots" aria-label="Category slider pagination"></div>
  </div>
</section>

<script>
(function () {
  const mobileQuery = window.matchMedia('(max-width: 767px)');
  const slider = document.querySelector('.cake-categories__grid');
  const dotsWrap = document.getElementById('cakeCategoriesDots');
  if (!slider || !dotsWrap) return;

  function initMobileSlider() {
    if (!mobileQuery.matches) {
      dotsWrap.innerHTML = '';
      return;
    }

    const cards = Array.from(slider.querySelectorAll('.cake-cat-card'));
    if (!cards.length) {
      dotsWrap.innerHTML = '';
      return;
    }

    dotsWrap.innerHTML = '';

    const dots = cards.map(function (_, index) {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'cake-categories__dot' + (index === 0 ? ' is-active' : '');
      dot.setAttribute('aria-label', 'Go to slide ' + (index + 1));
      dot.addEventListener('click', function () {
        const card = cards[index];
        slider.scrollTo({ left: card.offsetLeft, behavior: 'smooth' });
      });
      dotsWrap.appendChild(dot);
      return dot;
    });

    let ticking = false;
    const setActive = function () {
      const sliderCenter = slider.scrollLeft + slider.clientWidth / 2;
      let activeIndex = 0;
      let minDistance = Infinity;

      cards.forEach(function (card, idx) {
        const cardCenter = card.offsetLeft + card.offsetWidth / 2;
        const distance = Math.abs(cardCenter - sliderCenter);
        if (distance < minDistance) {
          minDistance = distance;
          activeIndex = idx;
        }
      });

      dots.forEach(function (dot, idx) {
        dot.classList.toggle('is-active', idx === activeIndex);
      });
    };

    slider.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        setActive();
        ticking = false;
      });
    }, { passive: true });

    setActive();
  }

  initMobileSlider();
  window.addEventListener('resize', initMobileSlider);
})();
</script>

<section class="cake-gallery-intro" aria-label="Gallery Heading">
  <div class="container">
    <div class="cake-gallery-intro__header">
      <span class="cake-gallery-intro__label">GALLERY</span>
      <h2 class="cake-gallery-intro__title">Our Signature Creations</h2>
    </div>
  </div>
</section>

<section class="cake-gallery-section" aria-label="Cake Gallery">
  <div class="container">
    <div class="cake-gallery__grid">
      <?php foreach ($cakeGallery as $item): ?>
        <article class="cake-gallery__card <?= htmlspecialchars((string)($item['class'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <img
            src="<?= htmlspecialchars((string)$item['image'], ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') ?>"
            loading="lazy"
          >
          <div class="cake-gallery__overlay">
            <span><?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php /*
<section class="home-section home-section--collections" aria-label="Signature collections">
  <div class="container">
    <div class="home-section-heading home-section-heading--split">
      <div>
        <span class="home-kicker">Signature Collections</span>
        <h2>More image-led, less cluttered, and easier to browse on mobile.</h2>
      </div>
      <p>
        The homepage now leads with visuals first. These category cards do the heavy lifting
        without forcing users through too much text before they can shop.
      </p>
    </div>

    <div class="collection-grid">
      <?php foreach ($signatureCollections as $collection): ?>
        <a href="<?= htmlspecialchars((string)($collection['href'] ?? ('/shop?cat=' . rawurlencode((string)$collection['slug']))), ENT_QUOTES, 'UTF-8') ?>" class="collection-card">
          <div class="collection-card__media">
            <img
              src="<?= htmlspecialchars((string)$collection['image'], ENT_QUOTES, 'UTF-8') ?>"
              alt="<?= htmlspecialchars((string)$collection['name'], ENT_QUOTES, 'UTF-8') ?>"
              loading="lazy"
              onerror="this.remove()"
            >
          </div>
          <div class="collection-card__body">
            <div class="collection-card__meta"><?= htmlspecialchars((string)$collection['meta'], ENT_QUOTES, 'UTF-8') ?></div>
            <h3><?= htmlspecialchars((string)$collection['name'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars((string)$collection['note'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
*/ ?>
 <?php /*
<section class="home-section home-section--story" aria-label="Craft story">
  <div class="container home-story">
    <div class="home-story__intro">
      <span class="home-kicker">The Atelier Feel</span>
      <h2>A bakery homepage should feel like stepping into the studio.</h2>
      <p>
        Instead of stacking every feature equally, this layout gives priority to the work itself:
        rich visuals, refined spacing, and a small amount of copy that helps visitors choose fast.
      </p>
      <div class="home-story__points">
        <div>
          <strong>Minimal first impression</strong>
          <span>One hero message, two actions, zero visual noise.</span>
        </div>
        <div>
          <strong>Photo-led storytelling</strong>
          <span>Large frames let the cakes sell the brand before the copy does.</span>
        </div>
        <div>
          <strong>Mobile-first browsing</strong>
          <span>Every section stacks cleanly and keeps buttons within thumb reach.</span>
        </div>
      </div>
    </div>

    <div class="editorial-grid">
      <?php foreach ($editorialMoments as $moment): ?>
        <article class="editorial-card <?= htmlspecialchars((string)$moment['class'], ENT_QUOTES, 'UTF-8') ?>">
          <div class="editorial-card__media">
            <img
              src="<?= htmlspecialchars((string)$moment['image'], ENT_QUOTES, 'UTF-8') ?>"
              alt="<?= htmlspecialchars((string)$moment['title'], ENT_QUOTES, 'UTF-8') ?>"
              loading="lazy"
              onerror="this.remove()"
            >
          </div>
          <div class="editorial-card__content">
            <span><?= htmlspecialchars((string)$moment['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
            <h3><?= htmlspecialchars((string)$moment['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars((string)$moment['copy'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
*/ ?>

 <?php /*
<section class="home-section home-section--products" aria-label="Bestsellers">
  <div class="container">
    <div class="home-section-heading home-section-heading--split">
      <div>
        <span class="home-kicker">Customer Favourites</span>
        <h2>Only the strongest products should appear here.</h2>
      </div>
      <p>
        Four cards are enough for the homepage. The goal is to create momentum into the shop,
        not recreate the full catalogue above the fold.
      </p>
    </div>

    <div class="product-grid home-product-grid">
      <?php if (!empty($bestsellerProducts)): ?>
        <?php foreach (array_slice($bestsellerProducts, 0, 4) as $product): ?>
          <?php
          $thumb = product_image_url((string)($product['thumb'] ?? $product['featured_image'] ?? ''), (string)($product['category_slug'] ?? ''));
          $productName = htmlspecialchars((string)($product['name'] ?? 'Cake'), ENT_QUOTES, 'UTF-8');
          $productSlug = htmlspecialchars((string)($product['slug'] ?? ''), ENT_QUOTES, 'UTF-8');
          $productHref = htmlspecialchars((string)($product['href'] ?? ('/product/' . $productSlug)), ENT_QUOTES, 'UTF-8');
          $productPrice = number_format((float)($product['min_price'] ?? $product['starting_price'] ?? 0));
          $dietaryTag = (string)($product['dietary_tag'] ?? '');
          ?>
          <article class="product-card">
            <a class="product-card__image-wrap" href="<?= $productHref ?>">
              <img
                class="product-card__image"
                src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= $productName ?>"
                loading="lazy"
                width="400"
                height="300"
                onerror="this.onerror=null;this.src='<?= htmlspecialchars(product_image_placeholder((string)($product['category_slug'] ?? '')), ENT_QUOTES, 'UTF-8') ?>';"
              >
            </a>
            <div class="product-card__body">
              <div class="product-card__meta">
                <span class="badge badge--neutral">Bestseller</span>
                <?php if ($dietaryTag !== '' && $dietaryTag !== 'regular'): ?>
                  <span class="tag"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $dietaryTag)), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>
              <h3 class="product-card__name">
                <a href="<?= $productHref ?>"><?= $productName ?></a>
              </h3>
              <p class="product-card__desc"><?= htmlspecialchars((string)($product['short_description'] ?? 'Freshly baked and finished for celebration-ready gifting.'), ENT_QUOTES, 'UTF-8') ?></p>
              <div class="product-card__price-row">
                <span class="product-card__price-label">From</span>
                <span class="price-text price-text--sm">₹<?= $productPrice ?></span>
              </div>
              <div class="product-card__actions">
                <a class="btn btn--primary btn--sm" href="<?= $productHref ?>">View Details</a>
                <a class="btn btn--secondary btn--sm" href="/custom-cake-inquiry">Customise</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($i = 1; $i <= 4; $i++): ?>
          <article class="product-card product-card--placeholder" aria-hidden="true">
            <div class="product-card__image-wrap"></div>
            <div class="product-card__body">
              <div class="product-card__meta">
                <span class="badge badge--neutral">Preview</span>
              </div>
              <h3 class="product-card__name">Signature Cake <?= $i ?></h3>
              <p class="product-card__desc">Upload real product photography to make this grid feel premium immediately.</p>
              <div class="product-card__price-row">
                <span class="product-card__price-label">From</span>
                <span class="price-text price-text--sm">₹1,200</span>
              </div>
            </div>
          </article>
        <?php endfor; ?>
      <?php endif; ?>
    </div>

    <div class="home-products__footer">
      <a href="/shop" class="btn btn--secondary btn--lg">Browse Full Collection</a>
    </div>
  </div>
</section>
*/ ?>
 <?php /*
<section class="home-section home-section--proof" aria-label="Social proof">
  <div class="container home-proof">
    <div class="home-proof__stats">
      <span class="home-kicker">Quiet Proof</span>
      <h2>Keep trust visible, but don’t let it overpower the visuals.</h2>
      <div class="home-proof__grid">
        <article>
          <strong>Fresh daily</strong>
          <p>Everyday production instead of freezer-first stock.</p>
        </article>
        <article>
          <strong>Custom-led</strong>
          <p>Birthday, wedding, gifting, and event-focused orders.</p>
        </article>
        <article>
          <strong>Direct support</strong>
          <p>Quick contact through phone and WhatsApp for fast decisions.</p>
        </article>
      </div>
    </div>
    </section>


    <div class="home-proof__quotes">
      <?php foreach ($homeTestimonials as $quote): ?>
        <article class="home-quote">
          <p><?= htmlspecialchars((string)$quote['text'], ENT_QUOTES, 'UTF-8') ?></p>
          <div class="home-quote__author">
            <span><?= htmlspecialchars(strtoupper(substr((string)($quote['initial'] ?? $quote['name'] ?? 'C'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
            <div>
              <strong><?= htmlspecialchars((string)($quote['name'] ?? 'Cakeouflage customer'), ENT_QUOTES, 'UTF-8') ?></strong>
              <small><?= htmlspecialchars((string)($quote['role'] ?? 'Customer'), ENT_QUOTES, 'UTF-8') ?></small>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
*/ ?>
<section class="testimonial-section">

  <div class="container">

    <h2>Sweet Words from Our Customers</h2>
    <p>Every cake tells a story — here’s what our clients say 💕</p>

    <div class="testimonial-slider">

      <div class="testimonial-track">

        <?php foreach ($homeTestimonials as $quote): ?>

          <div class="testimonial-card">
            <div class="card-inner">

              <div class="quote-icon">“</div>

              <p class="text">
                <?= htmlspecialchars((string)$quote['text'], ENT_QUOTES, 'UTF-8') ?>
              </p>

              <div class="user">
                <div class="avatar">
                  <?= htmlspecialchars(strtoupper(substr((string)($quote['name'] ?? 'C'), 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div>
                  <h4><?= htmlspecialchars((string)($quote['name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8') ?></h4>
                  <span><?= htmlspecialchars((string)($quote['role'] ?? 'Happy Client'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>

            </div>
          </div>

        <?php endforeach; ?>

      </div>

      <div class="dots"></div>

    </div>

  </div>

</section>


<script>
const track = document.querySelector('.testimonial-track');
const cards = document.querySelectorAll('.testimonial-card');
const dotsContainer = document.querySelector('.dots');

let index = 0;

function getVisibleCards() {
  if (window.innerWidth <= 600) return 1;
  if (window.innerWidth <= 900) return 2;
  return 3;
}

let visibleCards = getVisibleCards();
let totalSlides = Math.ceil(cards.length / visibleCards);

/* DOTS */
function createDots() {
  dotsContainer.innerHTML = "";
  totalSlides = Math.ceil(cards.length / visibleCards);

  for (let i = 0; i < totalSlides; i++) {
    let dot = document.createElement('span');

    if (i === 0) dot.classList.add('active');

    dot.addEventListener('click', () => {
      index = i;
      updateSlider();
    });

    dotsContainer.appendChild(dot);
  }
}

/* SLIDE */
function updateSlider() {
  let move = index * (100 / visibleCards);
  track.style.transform = `translateX(-${move}%)`;

  document.querySelectorAll('.dots span').forEach((dot, i) => {
    dot.classList.toggle('active', i === index);
  });
}

/* AUTO */
setInterval(() => {
  index = (index + 1) % totalSlides;
  updateSlider();
}, 4000);

/* RESIZE */
window.addEventListener('resize', () => {
  visibleCards = getVisibleCards();
  createDots();
  updateSlider();
});

/* INIT */
createDots();
updateSlider();
</script>

<script>
if (window.innerWidth < 768) {
  document.getElementById("heroTitle").style.fontSize = "28px";
  document.getElementById("heroSubtitle").style.fontSize = "12px";
}
</script>