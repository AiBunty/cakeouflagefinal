<?php

define('BASE_URL', '');
/*
 * Cakeouflage — Product Detail Page (PDP)
 * Variables injected by WebController::product():
 *   $product   - full row (all columns + joined category names)
 *   $variants  - array of product_variants rows, default first
 *   $images    - array of product_images rows
 *   $related   - array of up to 6 related product rows
 *   $breadcrumbs - [{label, url?}, …]
 */
$defaultVariant = null;

if (!empty($variants)) {
    usort($variants, function ($a, $b) {
        $priceA = (float)($a['discount_price'] ?: $a['price']);
        $priceB = (float)($b['discount_price'] ?: $b['price']);
        return $priceA <=> $priceB;
    });

    $defaultVariant = $variants[0]; // lowest price variant
}
$defaultPrice   = $defaultVariant ? (float)$defaultVariant['price'] : (float)($product['starting_price'] ?? 0);
$origPrice      = $defaultVariant && !empty($defaultVariant['discount_price']) && (float)$defaultVariant['discount_price'] < (float)$defaultVariant['price']
                  ? (float)$defaultVariant['price'] : null;
$displayPrice   = $defaultVariant && !empty($defaultVariant['discount_price'])
                  ? (float)$defaultVariant['discount_price'] : $defaultPrice;
$rawMainImage = '';
$categorySlug = (string)($product['category_slug'] ?? '');
// Filter out blank image slots (e.g. admin saved a blank 2nd image field)
$images = array_values(array_filter($images ?? [], function ($img) {
  return trim((string)($img['image_url'] ?? '')) !== '';
}));

if (!empty($images)) {
  // first image only (sorted already)
  $rawMainImage = (string)($images[0]['image_url'] ?? '');
} elseif (!empty($product['featured_image'])) {
  $rawMainImage = (string)$product['featured_image'];
}

$mainImage = product_image_url($rawMainImage, $categorySlug);


//$mainImage = '' . $mainImage;
$dietary        = $product['dietary_tag'] ?? 'regular';
$is_veg         = (int)($product['is_veg'] ?? 1);
$leadHours      = (int)($product['lead_time_hours'] ?? 24);
$protocol       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$productUrl     = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/product/' . $product['slug'];
$waMsg          = rawurlencode(
    "Hello! \xf0\x9f\x91\x8b I'm interested in ordering a cake from Cakeouflage.\n\n" .
    "\xf0\x9f\x8e\x82 *" . $product['name'] . "*\n" .
    "SKU: " . ($product['sku'] ?? '') . "\n" .
    "\xf0\x9f\x94\x97 " . $productUrl . "\n\n" .
    "Could you please share the availability, pricing and delivery details?\n\n" .
    "Thank you! \xf0\x9f\x98\x8a"
);
$waLink         = "https://wa.me/" . ($businessPhone ?? '919673565935') . "?text={$waMsg}";
?>
<!-- JSON-LD Product Schema -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": <?= json_encode($product['name']) ?>,
  "description": <?= json_encode($product['short_description'] ?? '') ?>,
  "image": <?= json_encode($mainImage) ?>,
  "sku": <?= json_encode($product['sku'] ?? '') ?>,
  "brand": { "@type": "Brand", "name": "Cakeouflage" },
  "offers": {
    "@type": "Offer",
    "priceCurrency": "INR",
    "price": <?= json_encode(number_format($displayPrice, 2, '.', '')) ?>,
    "availability": "<?= $product['availability_status'] === 'in_stock' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>"
  }
}
</script>

<main data-page="product">

  <!-- Product Detail Section -->
  <section class="pdp section">
    <div class="container">

      <!-- Breadcrumb -->
      <?php if (!empty($breadcrumbs)): ?>
      <nav class="pdp__breadcrumb" aria-label="Breadcrumb">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
          <?php if ($i > 0): ?><span class="pdp__breadcrumb-sep" aria-hidden="true">›</span><?php endif; ?>
          <?php if (!empty($crumb['url'])): ?>
            <a href="<?= htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></a>
          <?php else: ?>
            <span aria-current="page"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>

      <div class="pdp__grid" id="pdpGrid">

        <!-- ── Gallery Column ── -->
        <div class="pdp__gallery">
          <div class="pdp__gallery-inner">

         <?php if (count($images) > 1): ?>
          <div class="pdp__thumbs" role="listbox" aria-label="Product images">
           <?php foreach ($images as $idx => $img): 

$imgPath = $img['image_url'] ?? '';

    $finalImg = product_image_url((string)$imgPath, $categorySlug);


    //$finalImg = '' . $finalImg;

?>
<button class="pdp__thumb<?= $idx === 0 ? ' is-active' : '' ?>"
        type="button"
        onclick="pdpSetImage(this,'<?= htmlspecialchars($finalImg, ENT_QUOTES, 'UTF-8') ?>')">

    <img src="<?= htmlspecialchars($finalImg, ENT_QUOTES, 'UTF-8') ?>"
         onerror="this.onerror=null;this.src='<?= htmlspecialchars(product_image_placeholder((string)($product['category_slug'] ?? '')), ENT_QUOTES, 'UTF-8') ?>';"
         alt="<?= htmlspecialchars($img['alt_text'] ?? $product['name'], ENT_QUOTES, 'UTF-8') ?>" />

</button>
<?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="pdp__main-image">
            <img id="pdpMainImg"
                 src="<?= htmlspecialchars($mainImage, ENT_QUOTES, 'UTF-8') ?>"
                onerror="this.onerror=null;this.src='<?= htmlspecialchars(product_image_placeholder((string)($product['category_slug'] ?? '')), ENT_QUOTES, 'UTF-8') ?>';"
                 alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                 width="600" height="600"
                 loading="eager" />
          </div>

          </div><!-- /.pdp__gallery-inner -->

          <!-- Dietary badges -->
          <?php if ($dietary && $dietary !== 'regular'): ?>
          <div class="pdp__badges">
            <?php
              $dietMap = ['eggless' => '🥚 Eggless', 'vegan' => '🌱 Vegan', 'sugar_free' => '🍃 Sugar Free', 'gluten_free' => '🌾 Gluten Free'];
              echo '<span class="badge badge--info">' . htmlspecialchars($dietMap[$dietary] ?? ucfirst($dietary), ENT_QUOTES, 'UTF-8') . '</span>';
            ?>
          </div>
          <?php endif; ?>
        </div><!-- /.pdp__gallery -->

        <!-- ── Info Column ── -->
        <div class="pdp__info">

          <div class="pdp__header">
            <h1 class="pdp__title">
              <span class="veg-dot veg-dot--<?= $is_veg ? 'veg' : 'nonveg' ?>" title="<?= $is_veg ? 'Vegetarian' : 'Non-Vegetarian' ?>"></span>
              <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <button class="pdp__wishlist-btn" id="pdpWishlistBtn"
                    data-product-id="<?= (int)$product['id'] ?>"
                    aria-label="Add to wishlist" type="button">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
          </div>

         <?php if (!empty($product['sku'])): ?>
  <p class="pdp__sku">
    SKU: <?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8') ?>
  </p>
<?php endif; ?>

<!-- Price block -->
<div class="pdp__price-block">
  
  <!-- Current Price -->
  <span class="pdp__price" id="currentPrice">
    ₹<?= number_format($displayPrice) ?>
  </span>

  <!-- Original Price (hidden by default) -->
  <span class="pdp__price-orig" id="origPrice" style="display:none;"></span>

  <!-- Discount Badge (hidden by default) -->
  <span class="badge badge--success" id="savingsBadge" style="display:none;"></span>

</div>

<p class="pdp__short-desc">
  <?= htmlspecialchars($product['short_description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
</p>

          <!-- Availability -->
          <?php if ($product['availability_status'] === 'out_of_stock'): ?>
          <p class="badge badge--error" style="display:inline-flex;margin-bottom:.75rem">Currently Out of Stock</p>
          <?php elseif ($product['availability_status'] === 'pre_order'): ?>
          <p class="badge badge--warning" style="display:inline-flex;margin-bottom:.75rem">Pre-Order</p>
          <?php endif; ?>

<!-- ── Variant Selector ── -->
<?php if (!empty($variants)): ?>

<div class="pdp__variants">
  <p class="pdp__variants-label">Select Size / Weight</p>

  <div class="variant-pills">

    <?php foreach ($variants as $v): ?>
      
      <button type="button"
        class="variant-pill<?= !empty($v['is_default']) ? ' is-active' : '' ?>"
        role="option"
        aria-selected="<?= !empty($v['is_default']) ? 'true' : 'false' ?>"
        data-variant-id="<?= (int)$v['id'] ?>"
        data-price="<?= number_format((float)($v['discount_price'] ?: $v['price']), 2, '.', '') ?>"
        data-orig-price="<?= (float)$v['discount_price'] && (float)$v['discount_price'] < (float)$v['price'] ? number_format((float)$v['price'], 2, '.', '') : '' ?>"
        <?= $v['stock_quantity'] <= 0 ? 'disabled aria-disabled="true"' : '' ?>
        onclick="pdpSelectVariant(this)">

        <?= htmlspecialchars($v['variant_label'], ENT_QUOTES, 'UTF-8') ?>

        <?php if ($v['stock_quantity'] <= 0): ?>
          <small> (sold out)</small>
        <?php endif; ?>

      </button>

    <?php endforeach; ?>

  </div>
</div>

<?php endif; ?>

<!-- ── Customisation ── -->
<div class="product-customise" style="margin:1rem 0 .5rem;">
  <?php if (!empty($product['note_enabled'])): ?>
  <div class="form-group" style="margin-bottom:.75rem;">
    <label for="pdpCakeMsg" style="font-weight:600;font-size:.9rem;display:block;margin-bottom:6px;">🎂 Note on the Cake <small style="font-weight:400;opacity:.7;">(optional, max 200 chars)</small></label>
    <input type="text" id="pdpCakeMsg" maxlength="200" placeholder="e.g. Happy Birthday Sarah!" style="width:100%;padding:8px 12px;border:1.5px solid #e5d5d9;border-radius:10px;font-size:.9rem;outline:none;" />
    <span id="pdpCakeMsgCount" style="font-size:.72rem;opacity:.55;display:block;text-align:right;margin-top:3px;">0/200</span>
  </div>
  <?php endif; ?>
  <?php if (!empty($product['topper_enabled'])): ?>
  <div class="form-group" id="topperGroup" style="margin-bottom:.75rem;">
    <label for="pdpTopper" style="font-weight:600;font-size:.9rem;display:block;margin-bottom:6px;">🎀 Select Topper <small style="font-weight:400;opacity:.7;">(optional)</small></label>
    <select id="pdpTopper" style="width:100%;padding:8px 12px;border:1.5px solid #e5d5d9;border-radius:10px;font-size:.9rem;background:#fff;outline:none;">
      <option value="">Loading…</option>
    </select>
    <span id="pdpTopperPriceNote" style="font-size:.78rem;color:#80001f;margin-top:4px;display:none;"></span>
  </div>
  <?php endif; ?>
</div>

          <!-- Delivery strip -->
          <div class="pdp__delivery-strip">
            <div class="pdp__delivery-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              <span>Ready in <strong><?= $leadHours >= 48 ? floor($leadHours / 24) . ' days' : $leadHours . ' hrs' ?></strong></span>
            </div>
            <?php if (!empty($product['delivery_eligible'])): ?>
            <div class="pdp__delivery-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
              <span>Home delivery</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($product['pickup_eligible'])): ?>
            <div class="pdp__delivery-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              <span>Store pickup</span>
            </div>
            <?php endif; ?>
          </div>

          <!-- ── Qty + CTA ── -->
          <div class="pdp__cta-row">
            <div class="qty-stepper">
              <button class="qty-stepper__btn" id="qtyMinus" type="button" aria-label="Decrease quantity">−</button>
              <input class="qty-stepper__input" id="pdpQty" type="number" value="1" min="1" max="99" aria-label="Quantity" />
              <button class="qty-stepper__btn" id="qtyPlus"  type="button" aria-label="Increase quantity">+</button>
            </div>
            <button class="btn btn--primary btn--lg pdp__add-btn" id="addToCartBtn"
                    type="button"
                    data-product-id="<?= (int)$product['id'] ?>"
                    data-variant-id="<?= !empty($variants) ? (int)$variants[0]['id'] : 0 ?>"
                    <?= $product['availability_status'] === 'out_of_stock' ? 'disabled aria-disabled="true"' : '' ?>>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              Add to Cart
            </button>
          </div>

          <!-- WhatsApp enquiry -->
          <a class="btn btn--whatsapp" href="<?= htmlspecialchars($waLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
            Enquire on WhatsApp
          </a>

          <!-- Share product -->
          <button class="btn btn--share" type="button" onclick="cakeoShareProduct()" aria-label="Share this product">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Share
          </button>

          <?php if (!empty($product['customisation_note']) && ($product['customisation_allowed'] ?? 1)): ?>
          <div class="pdp__custom-note">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($product['customisation_note'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <?php endif; ?>

        </div><!-- /.pdp__info -->
      </div><!-- /.pdp__grid -->
   <?php /*
      <!-- ── Long Desc Tabs ── -->
      <div class="pdp__tabs">
        <nav class="pdp__tab-nav" role="tablist">
          <button class="pdp__tab-btn is-active" role="tab" aria-selected="true"  data-tab="desc"       type="button">Description</button>
          <button class="pdp__tab-btn"            role="tab" aria-selected="false" data-tab="details"    type="button">Details</button>
          <button class="pdp__tab-btn"            role="tab" aria-selected="false" data-tab="packaging"  type="button">Packaging</button>
          <button class="pdp__tab-btn"            role="tab" aria-selected="false" data-tab="delivery"   type="button">Delivery</button>
        </nav>

        <div class="pdp__tab-panel is-active" id="pdpTab-desc" role="tabpanel">
          <div class="prose">
            <?= !empty($product['long_description']) ? nl2br(htmlspecialchars($product['long_description'], ENT_QUOTES, 'UTF-8')) : '<p>Crafted in small batches with premium ingredients.</p>' ?>
          </div>
        </div>

        <div class="pdp__tab-panel" id="pdpTab-details" role="tabpanel" hidden>
       
          <?php if (!empty($product['flavour_notes']) || !empty($product['texture_notes']) || !empty($product['ingredients_summary'])): ?>
          <dl class="pdp__details-list">
            <?php if (!empty($product['flavour_notes'])): ?>
            <dt>Flavour Notes</dt><dd><?= htmlspecialchars($product['flavour_notes'], ENT_QUOTES, 'UTF-8') ?></dd>
            <?php endif; ?>
            <?php if (!empty($product['texture_notes'])): ?>
            <dt>Texture</dt><dd><?= htmlspecialchars($product['texture_notes'], ENT_QUOTES, 'UTF-8') ?></dd>
            <?php endif; ?>
            <?php if (!empty($product['ingredients_summary'])): ?>
            <dt>Key Ingredients</dt><dd><?= htmlspecialchars($product['ingredients_summary'], ENT_QUOTES, 'UTF-8') ?></dd>
            <?php endif; ?>
            <dt>Dietary</dt><dd><?= htmlspecialchars(ucfirst(str_replace('_',' ', $dietary)), ENT_QUOTES, 'UTF-8') ?></dd>
          </dl>
          <?php else: ?>
          <p class="prose">Please inform us of any allergies at the time of ordering. Cakes should be stored refrigerated and consumed within 2 days of delivery.</p>
          <?php endif; ?>
        </div>

        <div class="pdp__tab-panel" id="pdpTab-packaging" role="tabpanel" hidden>
          <div class="prose">
            <?php if (!empty($product['packaging_note'])): ?>
            <p><?= htmlspecialchars($product['packaging_note'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if (!empty($product['topper_note'])): ?>
            <p><strong>Topper:</strong> <?= htmlspecialchars($product['topper_note'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if (empty($product['packaging_note']) && empty($product['topper_note'])): ?>
            <p>All cakes come securely packaged in our signature Cakeouflage box with a complimentary ribbon and personalised card slot.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="pdp__tab-panel" id="pdpTab-delivery" role="tabpanel" hidden>
          <p class="prose">We deliver across Nashik within a 30 km radius. Delivery slots can be selected at checkout. Same-day delivery is available on select products ordered before 12 noon.</p>
        </div>
      </div><!-- /.pdp__tabs -->

    </div><!-- /.container -->
  </section>

  <!-- ── Related Products ── -->
  <?php if (!empty($related)): ?>
  <section class="section section--tinted">
    <div class="container">
      <div class="section-heading section-heading--center">
        <span class="section-label">You May Also Like</span>
        <h2>More from this Collection</h2>
      </div>
      <div class="product-grid product-grid--<?= min(count($related), 4) ?>">
        <?php foreach ($related as $rp):
        $rThumb = product_image_url((string)($rp['thumb'] ?? $rp['featured_image'] ?? ''), (string)($rp['category_slug'] ?? $categorySlug));
          $rPrice = number_format((float)($rp['min_price'] ?? $rp['starting_price'] ?? 0));
        ?>
        <article class="product-card">
          <a class="product-card__image-wrap" href="/product/<?= htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8') ?>">
            <img class="product-card__image"
                 src="<?= htmlspecialchars($rThumb, ENT_QUOTES, 'UTF-8') ?>"
                onerror="this.onerror=null;this.src='<?= htmlspecialchars(product_image_placeholder((string)($product['category_slug'] ?? '')), ENT_QUOTES, 'UTF-8') ?>';"
                 alt="<?= htmlspecialchars($rp['name'], ENT_QUOTES, 'UTF-8') ?>"
                 loading="lazy" width="400" height="400" />
          </a>
          <div class="product-card__body">
            <h3 class="product-card__name">
              <a href="/product/<?= htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rp['name'], ENT_QUOTES, 'UTF-8') ?></a>
            </h3>
            <div class="product-card__footer">
              <span class="product-card__price">From ₹<?= $rPrice ?></span>
              <a class="btn btn--primary btn--sm" href="/product/<?= htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8') ?>">View</a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
*/ ?>
<script>
(function () {
  var _name = <?= json_encode($product['name']) ?>;
  var _url  = <?= json_encode($productUrl) ?>;
  window.cakeoShareProduct = function () {
    if (navigator.share) {
      navigator.share({ title: _name, text: 'Check out ' + _name + ' on Cakeouflage! \ud83c\udf82', url: _url })
        .catch(function () {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(_url).then(function () { cakeoShowToast('Link copied to clipboard!'); });
    } else {
      cakeoShowToast('Copy this link: ' + _url);
    }
  };
  window.cakeoShowToast = function (msg) {
    var el = document.createElement('div');
    el.className = 'pdp-share-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('pdp-share-toast--visible'); });
    setTimeout(function () {
      el.classList.remove('pdp-share-toast--visible');
      setTimeout(function () { el.remove(); }, 300);
    }, 2800);
  };
})();
</script>
</main>

<!-- Mobile sticky CTA -->
<div class="pdp-mobile-cta" id="pdpMobileCta">
  <div class="pdp-mobile-cta__price" id="pdpMobilePrice">₹<?= number_format($displayPrice) ?></div>
  <button class="btn btn--primary" id="pdpMobileAddBtn" type="button">Add to Cart</button>
</div>

<script>
(function () {
  // ── Variant selection ──────────────────────────────────────────
  const VARIANTS = <?= json_encode(array_map(function ($v) {
    return [
      'id'           => (int)$v['id'],
      'label'        => $v['variant_label'],
      'price'        => (float)($v['discount_price'] ?: $v['price']),
      'orig_price'   => (float)$v['discount_price'] && (float)$v['discount_price'] < (float)$v['price'] ? (float)$v['price'] : null,
      'stock'        => (int)$v['stock_quantity'],
      'is_default'   => !empty($v['is_default']),
    ];
  }, $variants), JSON_UNESCAPED_UNICODE) ?>;

  let selectedVariantId = <?= !empty($variants) ? (int)$variants[0]['id'] : 'null' ?>;

  // ── Topper & customisation state ──────────────────────────────
  const TOPPER_ENABLED = <?= !empty($product['topper_enabled']) ? 'true' : 'false' ?>;
  const NOTE_ENABLED   = <?= !empty($product['note_enabled'])   ? 'true' : 'false' ?>;
  let _currentTopper = { id: null, price: 0 };
  let _currentVariantPrice = <?= !empty($variants) ? (float)($variants[0]['discount_price'] ?: $variants[0]['price']) : 0 ?>;

  function _pdpUpdateTotalPrice() {
    const base = _currentVariantPrice + _currentTopper.price;
    document.getElementById('currentPrice').textContent = '₹' + Math.round(base).toLocaleString('en-IN');
    document.getElementById('pdpMobilePrice').textContent = '₹' + Math.round(base).toLocaleString('en-IN');
  }

  window.pdpSelectVariant = function (btn) {
    document.querySelectorAll('.variant-pill').forEach(b => {
      b.classList.remove('is-active');
      b.setAttribute('aria-selected', 'false');
    });
    btn.classList.add('is-active');
    btn.setAttribute('aria-selected', 'true');
    selectedVariantId = parseInt(btn.dataset.variantId);

    const v = VARIANTS.find(x => x.id === selectedVariantId);
    if (!v) return;

    const price = v.price;
    _currentVariantPrice = price;
    document.getElementById('currentPrice').textContent = '₹' + Math.round(_currentVariantPrice + _currentTopper.price).toLocaleString('en-IN');
    document.getElementById('pdpMobilePrice').textContent = '₹' + Math.round(_currentVariantPrice + _currentTopper.price).toLocaleString('en-IN');

    const origEl = document.getElementById('origPrice');
    const saveEl = document.getElementById('savingsBadge');
   

    const cartBtn = document.getElementById('addToCartBtn');
    if (cartBtn) cartBtn.dataset.variantId = selectedVariantId;
    const mobileBtn = document.getElementById('pdpMobileAddBtn');
    if (mobileBtn) mobileBtn.dataset.variantId = selectedVariantId;
  };

  // ── Image gallery ──────────────────────────────────────────────
  window.pdpSetImage = function (thumb, src) {
    document.getElementById('pdpMainImg').src = src;
    document.querySelectorAll('.pdp__thumb').forEach(t => {
      t.classList.remove('is-active');
      t.setAttribute('aria-selected', 'false');
    });
    thumb.classList.add('is-active');
    thumb.setAttribute('aria-selected', 'true');
  };

  // ── Qty stepper ────────────────────────────────────────────────
  const qtyInput = document.getElementById('pdpQty');
  document.getElementById('qtyMinus')?.addEventListener('click', () => {
    if (qtyInput && +qtyInput.value > 1) qtyInput.value = +qtyInput.value - 1;
  });
  document.getElementById('qtyPlus')?.addEventListener('click', () => {
    if (qtyInput && +qtyInput.value < 99) qtyInput.value = +qtyInput.value + 1;
  });

  // ── Add to Cart ────────────────────────────────────────────────
  function doAddToCart() {
    const qty = parseInt(qtyInput?.value ?? 1, 10);
    const cakeMsg = NOTE_ENABLED ? (document.getElementById('pdpCakeMsg')?.value ?? '').trim().substring(0, 200) : '';
    const topperId = TOPPER_ENABLED ? (document.getElementById('pdpTopper')?.value || null) : null;
 window.CakeouflageCart.addItem(
  <?= (int)$product['id'] ?>,
  selectedVariantId,
  qty,
  { cake_message: cakeMsg || undefined, topper_id: topperId || undefined }
)
.then(() => {
  const btn = document.getElementById('addToCartBtn');
  if (btn) {
    const orig = btn.innerHTML;
    btn.textContent = '✓ Added!';
    setTimeout(() => btn.innerHTML = orig, 1800);
  }
})
.catch((err) => {
  console.error("FULL ERROR:", err);
  alert(err.message || 'Error adding to cart');
});
  }

  document.getElementById('addToCartBtn')?.addEventListener('click', doAddToCart);
  document.getElementById('pdpMobileAddBtn')?.addEventListener('click', doAddToCart);

  // ── Char counter for cake message ──────────────────────────────
  const pdpCakeMsg = document.getElementById('pdpCakeMsg');
  const pdpCakeMsgCount = document.getElementById('pdpCakeMsgCount');
  pdpCakeMsg?.addEventListener('input', function () {
    if (pdpCakeMsgCount) pdpCakeMsgCount.textContent = this.value.length + '/200';
  });

  // ── Fetch & render toppers ─────────────────────────────────────
  if (TOPPER_ENABLED) {
    fetch('/api/toppers')
      .then(r => r.json())
      .then(d => {
        const sel = document.getElementById('pdpTopper');
        if (!sel || !d.success) return;
        sel.innerHTML = '<option value="">— No Topper —</option>' + d.data.map(t =>
          `<option value="${t.id}" data-price="${t.price}">${t.name}${parseFloat(t.price) > 0 ? ' (+\u20b9' + Math.round(t.price) + ')' : ''}</option>`
        ).join('');
        sel.addEventListener('change', function () {
          const opt = this.options[this.selectedIndex];
          const tp = parseFloat(opt?.dataset?.price ?? '0') || 0;
          _currentTopper = { id: this.value || null, price: tp };
          const note = document.getElementById('pdpTopperPriceNote');
          if (note) {
            if (tp > 0) { note.textContent = '+\u20b9' + Math.round(tp) + ' for topper'; note.style.display = 'block'; }
            else { note.style.display = 'none'; }
          }
          _pdpUpdateTotalPrice();
        });
      })
      .catch(() => {
        const sel = document.getElementById('pdpTopper');
        if (sel) sel.innerHTML = '<option value="">— No Topper —</option>';
      });
  }

  // ── Wishlist ───────────────────────────────────────────────────
  document.getElementById('pdpWishlistBtn')?.addEventListener('click', function () {
    fetch('/api/wishlist/toggle', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__csrf ?? '' },
      body   : JSON.stringify({ product_id: <?= (int)$product['id'] ?> }),
    })
    .then(r => r.json())
    .then(d => {
      this.classList.toggle('is-active', d.added === true);
    });
  });

  // ── Tabs ───────────────────────────────────────────────────────
  document.querySelectorAll('.pdp__tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const tab = this.dataset.tab;
      document.querySelectorAll('.pdp__tab-btn').forEach(b => {
        b.classList.remove('is-active'); b.setAttribute('aria-selected','false');
      });
      document.querySelectorAll('.pdp__tab-panel').forEach(p => {
        p.classList.remove('is-active'); p.hidden = true;
      });
      this.classList.add('is-active'); this.setAttribute('aria-selected','true');
      const panel = document.getElementById('pdpTab-' + tab);
      if (panel) { panel.classList.add('is-active'); panel.hidden = false; }
    });
  });

  // ── Mobile sticky CTA: show on scroll ─────────────────────────
  const mobileCta = document.getElementById('pdpMobileCta');
  if (mobileCta) {
    const addBtn = document.getElementById('addToCartBtn');
    const observer = new IntersectionObserver(entries => {
      mobileCta.classList.toggle('is-visible', !entries[0].isIntersecting);
    }, { threshold: 0.1 });
    if (addBtn) observer.observe(addBtn);
  }
})();
</script>
