<?php
$siteTopOffer = null;
$showSiteTopOffer = false;
$nowTs = time();

try {
    $db = \App\Core\Database::getInstance();
  try {
    $siteTopOffer = $db->fetchOne(
      "SELECT
        b.title,
        b.subtitle,
        b.cta_label,
        b.cta_url,
        b.page_scope,
        b.starts_at,
        b.ends_at,
        b.linked_coupon_id,
        c.code AS coupon_code,
        c.is_active AS coupon_is_active,
        c.is_deleted AS coupon_is_deleted,
        c.starts_at AS coupon_starts_at,
        c.ends_at AS coupon_ends_at
       FROM banners b
       LEFT JOIN coupons c ON c.id = b.linked_coupon_id
       WHERE b.placement = 'site_top_offer'
         AND b.is_active = 1
         AND (b.starts_at IS NULL OR b.starts_at <= NOW())
         AND (b.ends_at IS NULL OR b.ends_at >= NOW())
       ORDER BY b.id DESC
       LIMIT 1"
    );
  } catch (\Throwable $schemaErr) {
    // Backward-compat fallback for environments where page_scope isn't available yet.
    $siteTopOffer = $db->fetchOne(
      "SELECT
        b.title,
        b.subtitle,
        b.cta_label,
        b.cta_url,
        b.starts_at,
        b.ends_at,
        b.linked_coupon_id,
        c.code AS coupon_code,
        c.is_active AS coupon_is_active,
        c.is_deleted AS coupon_is_deleted,
        c.starts_at AS coupon_starts_at,
        c.ends_at AS coupon_ends_at
       FROM banners b
       LEFT JOIN coupons c ON c.id = b.linked_coupon_id
       WHERE b.placement IN ('site_top_offer','home_top_offer')
         AND b.is_active = 1
         AND (b.starts_at IS NULL OR b.starts_at <= NOW())
         AND (b.ends_at IS NULL OR b.ends_at >= NOW())
       ORDER BY b.id DESC
       LIMIT 1"
    );
  }

    if (is_array($siteTopOffer)) {
        $linkedCouponId = isset($siteTopOffer['linked_coupon_id']) ? (int)$siteTopOffer['linked_coupon_id'] : 0;
        if ($linkedCouponId > 0) {
            $couponActive = (int)($siteTopOffer['coupon_is_active'] ?? 0) === 1;
            $couponDeleted = (int)($siteTopOffer['coupon_is_deleted'] ?? 0) === 1;
            $couponCode = trim((string)($siteTopOffer['coupon_code'] ?? ''));
            $couponStarts = trim((string)($siteTopOffer['coupon_starts_at'] ?? ''));
            $couponEnds = trim((string)($siteTopOffer['coupon_ends_at'] ?? ''));
            $couponStartsTs = $couponStarts !== '' ? strtotime($couponStarts) : false;
            $couponEndsTs = $couponEnds !== '' ? strtotime($couponEnds) : false;
            // FIXED: Handle NULL start/end times for open-ended coupons
            // - NULL start = valid from beginning
            // - NULL end = valid until disabled
            // - Both NULL = always valid
            $couponWindowValid = true;
            if ($couponStarts !== '') {
                $couponWindowValid = $couponWindowValid && ($nowTs >= $couponStartsTs);
            }
            if ($couponEnds !== '') {
                $couponWindowValid = $couponWindowValid && ($nowTs <= $couponEndsTs);
            }

      $showSiteTopOffer = $couponActive && !$couponDeleted && $couponWindowValid && $couponCode !== '';
        } else {
      $showSiteTopOffer = false;
        }
    }
} catch (\Throwable $e) {
    $showSiteTopOffer = false;
}

if (!$showSiteTopOffer || !is_array($siteTopOffer)) {
    return;
}

$offerTitle = trim((string)($siteTopOffer['title'] ?? 'Limited-time Offer'));
$offerSubtitle = trim((string)($siteTopOffer['subtitle'] ?? ''));
$offerCode = trim((string)($siteTopOffer['coupon_code'] ?? ''));
$offerCtaLabel = trim((string)($siteTopOffer['cta_label'] ?? 'Shop Now'));
$offerCtaUrl = trim((string)($siteTopOffer['cta_url'] ?? '/shop'));
$offerEndsAt = trim((string)($siteTopOffer['ends_at'] ?? ''));
$couponEndsAt = trim((string)($siteTopOffer['coupon_ends_at'] ?? ''));
$offerExpiryAt = '';
if ($offerEndsAt !== '' && $couponEndsAt !== '') {
  $offerExpiryAt = strtotime($offerEndsAt) <= strtotime($couponEndsAt) ? $offerEndsAt : $couponEndsAt;
} elseif ($offerEndsAt !== '') {
  $offerExpiryAt = $offerEndsAt;
} elseif ($couponEndsAt !== '') {
  $offerExpiryAt = $couponEndsAt;
}
if ($offerCtaUrl === '') {
    $offerCtaUrl = '/shop';
}
?>
<style>
  .site-top-offer {
    background: linear-gradient(90deg, #7c1227 0%, #a31f3c 52%, #de6f4c 100%);
    color: #fff8f4;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
  z-index: var(--z-base);
  }
  .site-top-offer__inner {
    max-width: 1220px;
    margin: 0 auto;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    text-align: center;
    font-family: "Manrope", "Segoe UI", sans-serif;
  }
  .site-top-offer__title {
    font-weight: 800;
    letter-spacing: 0.02em;
    font-size: 0.95rem;
    text-transform: uppercase;
  }
  .site-top-offer__subtitle {
    opacity: 0.92;
    font-size: 0.92rem;
  }
  .site-top-offer__countdown {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    background: rgba(255, 255, 255, 0.15);
  }
  .site-top-offer__code {
    display: inline-flex;
    align-items: center;
    border: 1px dashed rgba(255, 255, 255, 0.7);
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    background: rgba(0, 0, 0, 0.14);
    cursor: pointer;
    color: inherit;
    font-family: inherit;
    transition: background-color .2s ease;
  }
  .site-top-offer__code:hover {
    background: rgba(0, 0, 0, 0.28);
  }
  @media (max-width: 640px) {
    .site-top-offer__inner {
      padding: 10px 12px;
      gap: 8px;
    }
    .site-top-offer__title { font-size: 0.85rem; }
    .site-top-offer__subtitle { font-size: 0.84rem; }
  }
</style>
<section class="site-top-offer" aria-label="Limited time offer">
  <div class="site-top-offer__inner">
    <span class="site-top-offer__title"><?= htmlspecialchars($offerTitle, ENT_QUOTES, 'UTF-8'); ?></span>
    <?php if ($offerSubtitle !== ''): ?>
      <span class="site-top-offer__subtitle"><?= htmlspecialchars($offerSubtitle, ENT_QUOTES, 'UTF-8'); ?></span>
    <?php endif; ?>
    <?php if ($offerExpiryAt !== ''): ?>
      <span class="site-top-offer__countdown" data-offer-countdown data-offer-expires-at="<?= htmlspecialchars($offerExpiryAt, ENT_QUOTES, 'UTF-8'); ?>">Loading offer timer...</span>
    <?php endif; ?>
    <?php if ($offerCode !== ''): ?>
      <button type="button" class="site-top-offer__code" data-copy-code="<?= htmlspecialchars($offerCode, ENT_QUOTES, 'UTF-8'); ?>" title="Click to copy code" aria-label="Copy coupon code <?= htmlspecialchars($offerCode, ENT_QUOTES, 'UTF-8'); ?>">Code: <?= htmlspecialchars($offerCode, ENT_QUOTES, 'UTF-8'); ?></button>
    <?php endif; ?>
  </div>
</section>
<?php if ($offerExpiryAt !== ''): ?>
<script>
(function () {
  const countdownEl = document.querySelector('[data-offer-countdown]');
  if (!countdownEl) {
    return;
  }

  const rawExpiry = countdownEl.getAttribute('data-offer-expires-at') || '';
  const expiryAt = new Date(rawExpiry.replace(' ', 'T')).getTime();
  if (!Number.isFinite(expiryAt)) {
    countdownEl.textContent = 'Limited-time offer';
    return;
  }

  let timerId = null;

  const formatCountdown = (remainingMs) => {
    const totalSeconds = Math.max(0, Math.floor(remainingMs / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (totalSeconds <= 0) {
      return { text: 'Offer expired', expired: true };
    }
    if (hours >= 24) {
      const days = Math.floor(hours / 24);
      return { text: `Ends in ${days}d ${String(hours % 24).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`, expired: false };
    }
    if (hours >= 1) {
      return { text: `Ends in ${hours}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`, expired: false };
    }
    return { text: `Ends in ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`, expired: false };
  };

  const tick = () => {
    const remaining = expiryAt - Date.now();
    const view = formatCountdown(remaining);
    countdownEl.textContent = view.text;
    countdownEl.setAttribute('data-expired', view.expired ? '1' : '0');
    if (view.expired) {
      if (timerId !== null) {
        window.clearInterval(timerId);
      }
      countdownEl.closest('.site-top-offer')?.classList.add('is-expired');
    }
  };

  tick();
  timerId = window.setInterval(tick, 1000);
})();
</script>
<?php endif; ?>

