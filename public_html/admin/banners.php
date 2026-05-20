<?php
$pageTitle = 'Media Center';
include 'layout.php';

require __DIR__ . '/includes/db.php';

@ini_set('max_execution_time', '0');
@set_time_limit(0);

function media_ini_size_to_bytes(string $size): int
{
  $value = trim($size);
  if ($value === '') {
    return 0;
  }

  $unit = strtolower(substr($value, -1));
  $bytes = (float)$value;

  if ($unit === 'g') {
    $bytes *= 1024 * 1024 * 1024;
  } elseif ($unit === 'm') {
    $bytes *= 1024 * 1024;
  } elseif ($unit === 'k') {
    $bytes *= 1024;
  }

  return (int)round($bytes);
}

function media_format_bytes(int $bytes): string
{
  if ($bytes <= 0) {
    return '0 B';
  }

  $units = array('B', 'KB', 'MB', 'GB');
  $size = (float)$bytes;
  $index = 0;
  while ($size >= 1024 && $index < count($units) - 1) {
    $size /= 1024;
    $index++;
  }

  return number_format($size, $index === 0 ? 0 : 1) . ' ' . $units[$index];
}

function media_upload_error_message(int $errorCode): string
{
  if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
    $uploadMax = media_ini_size_to_bytes((string)ini_get('upload_max_filesize'));
    $postMax = media_ini_size_to_bytes((string)ini_get('post_max_size'));
    $limit = min($uploadMax > 0 ? $uploadMax : PHP_INT_MAX, $postMax > 0 ? $postMax : PHP_INT_MAX);
    $limitText = $limit === PHP_INT_MAX ? 'server limit' : media_format_bytes((int)$limit);
    return 'File is larger than server upload limit (' . $limitText . ').';
  }

  if ($errorCode === UPLOAD_ERR_PARTIAL) {
    return 'Upload was interrupted. Please retry on stable internet.';
  }

  if ($errorCode === UPLOAD_ERR_NO_TMP_DIR || $errorCode === UPLOAD_ERR_CANT_WRITE) {
    return 'Server storage is not writable for uploads.';
  }

  if ($errorCode === UPLOAD_ERR_EXTENSION) {
    return 'Upload blocked by server extension policy.';
  }

  if ($errorCode === UPLOAD_ERR_NO_FILE) {
    return 'No file selected.';
  }

  return 'Upload failed due to server restrictions.';
}

function media_banner_column_exists(mysqli $conn, string $column): bool
{
  $safe = $conn->real_escape_string($column);
  $result = $conn->query("SHOW COLUMNS FROM banners LIKE '{$safe}'");
  return $result && $result->num_rows > 0;
}

function media_banner_index_exists(mysqli $conn, string $indexName): bool
{
  $safe = $conn->real_escape_string($indexName);
  $result = $conn->query("SHOW INDEX FROM banners WHERE Key_name = '{$safe}'");
  return $result && $result->num_rows > 0;
}

function media_banner_fk_exists(mysqli $conn, string $constraintName): bool
{
  $safe = $conn->real_escape_string($constraintName);
  $result = $conn->query(
    "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'banners'
       AND CONSTRAINT_TYPE = 'FOREIGN KEY'
       AND CONSTRAINT_NAME = '{$safe}'
     LIMIT 1"
  );
  return $result && $result->num_rows > 0;
}

function media_ensure_offer_banner_schema(mysqli $conn): void
{
  // Ensure placement enum supports the top offer strip placement.
  $conn->query("ALTER TABLE banners MODIFY placement ENUM('home_hero','home_mid','site_top_offer','home_top_offer','shop_top','course_top','b2b_top') NOT NULL");

  if (!media_banner_column_exists($conn, 'starts_at')) {
    $conn->query('ALTER TABLE banners ADD COLUMN starts_at DATETIME NULL AFTER cta_url');
  }
  if (!media_banner_column_exists($conn, 'ends_at')) {
    $conn->query('ALTER TABLE banners ADD COLUMN ends_at DATETIME NULL AFTER starts_at');
  }
  if (!media_banner_column_exists($conn, 'linked_coupon_id')) {
    $conn->query('ALTER TABLE banners ADD COLUMN linked_coupon_id BIGINT UNSIGNED NULL AFTER ends_at');
  }
  if (!media_banner_column_exists($conn, 'page_scope')) {
    $conn->query("ALTER TABLE banners ADD COLUMN page_scope ENUM('all_pages','exclude_checkout_auth') NOT NULL DEFAULT 'all_pages' AFTER linked_coupon_id");
  }

  if (!media_banner_index_exists($conn, 'idx_banners_offer_active_window')) {
    $conn->query('ALTER TABLE banners ADD INDEX idx_banners_offer_active_window (placement, is_active, starts_at, ends_at)');
  }
  if (!media_banner_index_exists($conn, 'idx_banners_linked_coupon')) {
    $conn->query('ALTER TABLE banners ADD INDEX idx_banners_linked_coupon (linked_coupon_id)');
  }

  if (!media_banner_fk_exists($conn, 'fk_banners_linked_coupon') && media_banner_column_exists($conn, 'linked_coupon_id')) {
    $conn->query('ALTER TABLE banners ADD CONSTRAINT fk_banners_linked_coupon FOREIGN KEY (linked_coupon_id) REFERENCES coupons(id) ON DELETE SET NULL');
  }

  // Promote legacy offer placement naming to site_top_offer.
  $conn->query("UPDATE banners SET placement = 'site_top_offer' WHERE placement = 'home_top_offer'");
}

function media_is_video_extension(string $ext): bool
{
  return in_array($ext, array('mp4', 'webm', 'ogg', 'm4v', 'mov'), true);
}

function media_can_run_exec(): bool
{
  if (!function_exists('exec')) {
    return false;
  }

  $disabled = (string)ini_get('disable_functions');
  if ($disabled === '') {
    return true;
  }

  $disabledList = array_map('trim', explode(',', $disabled));
  return !in_array('exec', $disabledList, true);
}

function media_find_ffmpeg_binary(): ?string
{
  static $cached = null;
  static $checked = false;

  if ($checked) {
    return $cached;
  }
  $checked = true;

  if (!media_can_run_exec()) {
    return null;
  }

  $candidates = array('ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg');
  foreach ($candidates as $candidate) {
    $output = array();
    $exitCode = 1;
    @exec(escapeshellcmd($candidate) . ' -version 2>&1', $output, $exitCode);
    if ($exitCode === 0) {
      $cached = $candidate;
      return $cached;
    }
  }

  return null;
}

function media_try_optimize_video(string $absoluteFilePath, string &$infoMessage = ''): void
{
  $ffmpeg = media_find_ffmpeg_binary();
  if ($ffmpeg === null) {
    $infoMessage = 'Video saved as uploaded. FFmpeg not available on server for optimization.';
    return;
  }

  $originalSize = @filesize($absoluteFilePath);
  if ($originalSize === false || $originalSize < (40 * 1024 * 1024)) {
    return;
  }

  $optimizedPath = preg_replace('/\.[a-zA-Z0-9]+$/', '', $absoluteFilePath) . '_optimized.mp4';
  if (!is_string($optimizedPath) || $optimizedPath === '') {
    return;
  }

  $command = escapeshellcmd($ffmpeg)
    . ' -y -i ' . escapeshellarg($absoluteFilePath)
    . ' -c:v libx264 -preset veryfast -crf 20 -c:a aac -b:a 128k -movflags +faststart '
    . escapeshellarg($optimizedPath)
    . ' 2>&1';

  $output = array();
  $exitCode = 1;
  @exec($command, $output, $exitCode);
  if ($exitCode !== 0 || !is_file($optimizedPath)) {
    @unlink($optimizedPath);
    $infoMessage = 'Video saved, but server optimization could not complete.';
    return;
  }

  $optimizedSize = @filesize($optimizedPath);
  if ($optimizedSize !== false && $optimizedSize > 0 && $optimizedSize < $originalSize) {
    @unlink($absoluteFilePath);
    @rename($optimizedPath, $absoluteFilePath);
    $saved = $originalSize - $optimizedSize;
    $infoMessage = 'Video optimized for web streaming. Reduced by ' . media_format_bytes($saved) . '.';
    return;
  }

  @unlink($optimizedPath);
  $infoMessage = 'Video saved as uploaded (already web-optimized).';
}

function media_upload_file(array $file, string &$errorMessage = '', string &$infoMessage = ''): ?string
{
  $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($errorCode !== UPLOAD_ERR_OK) {
    $errorMessage = media_upload_error_message($errorCode);
        return null;
    }

    $name = (string)($file['name'] ?? '');
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($name === '' || $tmp === '') {
      $errorMessage = 'Missing uploaded file payload.';
        return null;
    }

    $uploadSize = (int)($file['size'] ?? 0);
    if ($uploadSize <= 0) {
      $errorMessage = 'Uploaded file appears empty.';
      return null;
    }

    $uploadMax = media_ini_size_to_bytes((string)ini_get('upload_max_filesize'));
    $postMax = media_ini_size_to_bytes((string)ini_get('post_max_size'));
    $serverMax = min($uploadMax > 0 ? $uploadMax : PHP_INT_MAX, $postMax > 0 ? $postMax : PHP_INT_MAX);
    if ($serverMax !== PHP_INT_MAX && $uploadSize > $serverMax) {
      $errorMessage = 'File is larger than current server limit (' . media_format_bytes((int)$serverMax) . ').';
      return null;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = array('mp4', 'webm', 'ogg', 'm4v', 'mov', 'jpg', 'jpeg', 'png', 'webp');
    if (!in_array($ext, $allowed, true)) {
      $errorMessage = 'Only MP4/WEBM/OGG/MOV videos and JPG/PNG/WEBP images are allowed.';
        return null;
    }

    $safeName = 'media_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $publicRoot = realpath(__DIR__ . '/../public');
    if ($publicRoot === false) {
      $errorMessage = 'Public upload directory is not available.';
      return null;
    }

    $targetDir = $publicRoot . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $target = $targetDir . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($tmp, $target)) {
      $errorMessage = 'Upload move failed. Please retry.';
        return null;
    }

    if (media_is_video_extension($ext)) {
      media_try_optimize_video($target, $infoMessage);
    }

    return '/uploads/' . $safeName;
}

function media_upsert_banner(mysqli $conn, string $placement, ?string $title, ?string $subtitle, ?string $imageUrl): void
{
    $check = $conn->prepare('SELECT id FROM banners WHERE placement = ? ORDER BY id DESC LIMIT 1');
    $check->bind_param('s', $placement);
    $check->execute();
    $result = $check->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if ($row) {
        $id = (int)$row['id'];
        $current = $conn->prepare('SELECT title, subtitle, image_url FROM banners WHERE id = ? LIMIT 1');
        $current->bind_param('i', $id);
        $current->execute();
        $currentResult = $current->get_result();
        $currentRow = $currentResult ? ($currentResult->fetch_assoc() ?: array()) : array();

        $finalTitle = $title !== null ? $title : (string)($currentRow['title'] ?? '');
        $finalSubtitle = $subtitle !== null ? $subtitle : (string)($currentRow['subtitle'] ?? '');
        $finalImage = $imageUrl !== null ? $imageUrl : (string)($currentRow['image_url'] ?? '');

        $update = $conn->prepare('UPDATE banners SET title = ?, subtitle = ?, image_url = ?, is_active = 1, updated_at = NOW() WHERE id = ? LIMIT 1');
        $update->bind_param('sssi', $finalTitle, $finalSubtitle, $finalImage, $id);
        $update->execute();
        return;
    }

    $finalTitle = $title ?? '';
    $finalSubtitle = $subtitle ?? '';
    $finalImage = $imageUrl ?? '';

    $insert = $conn->prepare('INSERT INTO banners (title, subtitle, image_url, cta_label, cta_url, placement, is_active, sort_order) VALUES (?, ?, ?, "", "", ?, 1, 0)');
    $insert->bind_param('ssss', $finalTitle, $finalSubtitle, $finalImage, $placement);
    $insert->execute();
}

function media_upsert_offer_banner(
  mysqli $conn,
  string $title,
  string $subtitle,
  string $ctaLabel,
  string $ctaUrl,
  ?string $startsAt,
  ?string $endsAt,
  ?int $linkedCouponId,
  string $pageScope
): void {
  $placement = 'site_top_offer';
  $check = $conn->prepare('SELECT id, image_url FROM banners WHERE placement = ? ORDER BY id DESC LIMIT 1');
  $check->bind_param('s', $placement);
  $check->execute();
  $result = $check->get_result();
  $row = $result ? $result->fetch_assoc() : null;

  if ($row) {
    $id = (int)$row['id'];
    $imageUrl = (string)($row['image_url'] ?? '');
    $update = $conn->prepare('UPDATE banners SET title = ?, subtitle = ?, cta_label = ?, cta_url = ?, starts_at = ?, ends_at = ?, linked_coupon_id = ?, page_scope = ?, image_url = ?, is_active = 1, updated_at = NOW() WHERE id = ? LIMIT 1');
    $update->bind_param('ssssssissi', $title, $subtitle, $ctaLabel, $ctaUrl, $startsAt, $endsAt, $linkedCouponId, $pageScope, $imageUrl, $id);
    $update->execute();
    return;
  }

  $emptyImage = '';
  $insert = $conn->prepare('INSERT INTO banners (title, subtitle, image_url, cta_label, cta_url, starts_at, ends_at, linked_coupon_id, page_scope, placement, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)');
  $insert->bind_param('sssssssiss', $title, $subtitle, $emptyImage, $ctaLabel, $ctaUrl, $startsAt, $endsAt, $linkedCouponId, $pageScope, $placement);
  $insert->execute();
}

function media_set_setting(mysqli $conn, string $key, string $value): void
{
    $adminId = !empty($_SESSION['admin']) ? (int)$_SESSION['admin'] : null;
    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value, updated_by_admin_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_admin_id = VALUES(updated_by_admin_id), updated_at = NOW()');
    $stmt->bind_param('ssi', $key, $value, $adminId);
    $stmt->execute();
}

function media_get_setting(mysqli $conn, string $key, string $fallback = ''): string
{
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? (string)($row['setting_value'] ?? $fallback) : $fallback;
}

function media_preview_placeholder(): string
{
  return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360"><rect width="640" height="360" rx="24" fill="%23f9eef1"/><path d="M154 237l86-86 62 62 50-50 90 98H154z" fill="%23d7a7b6"/><text x="320" y="322" text-anchor="middle" font-family="Arial" font-size="20" fill="%23b98ea0">Preview unavailable</text></svg>';
}

function media_resolve_preview_url(string $value, string $fallback = ''): string
{
  $candidate = trim($value);
  if ($candidate === '') {
    $candidate = trim($fallback);
  }

  if ($candidate === '') {
    return media_preview_placeholder();
  }

  if (preg_match('#^(data:|https?://)#i', $candidate)) {
    return $candidate;
  }

  $candidate = preg_replace('#^/Cakeouflage-E-commerce#', '', $candidate);
  if (strpos($candidate, '/uploads/') === 0) {
    $candidate = '/public' . $candidate;
  } elseif (strpos($candidate, '/assets/') === 0) {
    $candidate = '/client' . $candidate;
  } elseif ($candidate !== '' && $candidate[0] !== '/') {
    $candidate = '/' . ltrim($candidate, '/');
  }

  $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
  $absolutePath = $documentRoot !== '' ? $documentRoot . $candidate : '';
  if ($absolutePath !== '' && is_file($absolutePath)) {
    return $candidate;
  }

  $fallbackCandidate = trim($fallback);
  if ($fallbackCandidate !== '' && !preg_match('#^(data:|https?://)#i', $fallbackCandidate)) {
    $fallbackCandidate = preg_replace('#^/Cakeouflage-E-commerce#', '', $fallbackCandidate);
    if (strpos($fallbackCandidate, '/uploads/') === 0) {
      $fallbackCandidate = '/public' . $fallbackCandidate;
    } elseif (strpos($fallbackCandidate, '/assets/') === 0) {
      $fallbackCandidate = '/client' . $fallbackCandidate;
    } elseif ($fallbackCandidate !== '' && $fallbackCandidate[0] !== '/') {
      $fallbackCandidate = '/' . ltrim($fallbackCandidate, '/');
    }
    $fallbackAbsolute = $documentRoot !== '' ? $documentRoot . $fallbackCandidate : '';
    if ($fallbackAbsolute !== '' && is_file($fallbackAbsolute)) {
      return $fallbackCandidate;
    }
  }

  return media_preview_placeholder();
}

function media_offer_visibility_status(array $topOffer, bool $hasTopOfferRow, array $couponOptions): array
{
  if (!$hasTopOfferRow) {
    return array(
      'state' => 'inactive',
      'reason' => 'No top-offer banner saved yet.',
    );
  }

  if ((int)($topOffer['is_active'] ?? 1) !== 1) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because this banner is manually set to inactive.',
    );
  }

  $now = time();
  $startsAt = trim((string)($topOffer['starts_at'] ?? ''));
  $endsAt = trim((string)($topOffer['ends_at'] ?? ''));
  $startsTs = $startsAt !== '' ? strtotime($startsAt) : false;
  $endsTs = $endsAt !== '' ? strtotime($endsAt) : false;

  if ($startsTs !== false && $now < $startsTs) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because schedule has not started yet (' . $startsAt . ').',
    );
  }
  if ($endsTs !== false && $now > $endsTs) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because schedule ended on ' . $endsAt . '.',
    );
  }

  $linkedCouponId = (int)($topOffer['linked_coupon_id'] ?? 0);
  if ($linkedCouponId <= 0) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because no active coupon is linked to the top offer banner.',
    );
  }

  $coupon = null;
  foreach ($couponOptions as $item) {
    if ((int)($item['id'] ?? 0) === $linkedCouponId) {
      $coupon = $item;
      break;
    }
  }

  if (!is_array($coupon)) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because linked coupon is missing or deleted.',
    );
  }

  if ((int)($coupon['is_active'] ?? 0) !== 1) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because linked coupon is paused.',
    );
  }

  if ((int)($coupon['is_deleted'] ?? 0) === 1) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because linked coupon was deleted.',
    );
  }

  $couponStartsAt = trim((string)($coupon['starts_at'] ?? ''));
  $couponEndsAt = trim((string)($coupon['ends_at'] ?? ''));
  $couponStartsTs = $couponStartsAt !== '' ? strtotime($couponStartsAt) : false;
  $couponEndsTs = $couponEndsAt !== '' ? strtotime($couponEndsAt) : false;

  if ($couponStartsTs !== false && $now < $couponStartsTs) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because linked coupon has not started yet (' . $couponStartsAt . ').',
    );
  }
  if ($couponEndsTs !== false && $now > $couponEndsTs) {
    return array(
      'state' => 'inactive',
      'reason' => 'Hidden because linked coupon expired on ' . $couponEndsAt . '.',
    );
  }

  return array(
    'state' => 'active',
    'reason' => 'Visible now. Schedule and linked coupon are both currently valid across the site.',
  );
}

function media_render_preview_markup(string $value, string $fallback = ''): string
{
  $resolved = media_resolve_preview_url($value, $fallback);
  $escaped = htmlspecialchars($resolved, ENT_QUOTES, 'UTF-8');
  if ($resolved !== '' && !preg_match('#^data:image/#i', $resolved)) {
    $path = parse_url($resolved, PHP_URL_PATH);
    $ext = strtolower(pathinfo((string)($path ?? $resolved), PATHINFO_EXTENSION));
    if (in_array($ext, array('mp4', 'webm', 'ogg', 'm4v', 'mov'), true)) {
      return '<video src="' . $escaped . '" controls muted playsinline></video>';
    }
  }

  return '<img src="' . $escaped . '" alt="Media preview">';
}

$message = '';
$messageType = 'success';

media_ensure_offer_banner_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'save_home_hero') {
        $title = trim((string)($_POST['home_title'] ?? 'Cakeouflage'));
        $subtitle = trim((string)($_POST['home_subtitle'] ?? 'Premium Cakes'));
    $uploadError = '';
    $uploadInfo = '';
    $uploaded = media_upload_file($_FILES['home_hero_media'] ?? array(), $uploadError, $uploadInfo);
    if ($uploaded === null && $uploadError !== '' && (int)(($_FILES['home_hero_media']['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE) {
      $messageType = 'error';
      $message = $uploadError;
    } else {
      media_upsert_banner($conn, 'home_mid', $title, $subtitle, $uploaded);
      $message = 'Home hero media saved.' . ($uploadInfo !== '' ? ' ' . $uploadInfo : '');
    }
    }

    if ($action === 'save_about_hero') {
    $uploadError = '';
    $uploadInfo = '';
    $uploaded = media_upload_file($_FILES['about_hero_media'] ?? array(), $uploadError, $uploadInfo);
        if ($uploaded !== null) {
            media_upsert_banner($conn, 'about_video', null, null, $uploaded);
      $message = 'About hero video saved.' . ($uploadInfo !== '' ? ' ' . $uploadInfo : '');
        } else {
            $messageType = 'error';
      $message = $uploadError !== '' ? $uploadError : 'Please upload a valid file for About hero video.';
        }
    }

    if ($action === 'save_chef_video') {
    $uploadError = '';
    $uploadInfo = '';
    $uploaded = media_upload_file($_FILES['chef_video_media'] ?? array(), $uploadError, $uploadInfo);
        if ($uploaded !== null) {
            media_set_setting($conn, 'home_chef_video_url', $uploaded);
      $message = 'Chef video saved.' . ($uploadInfo !== '' ? ' ' . $uploadInfo : '');
        } else {
            $messageType = 'error';
      $message = $uploadError !== '' ? $uploadError : 'Please upload a valid file for Chef video.';
        }
    }

    if ($action === 'save_healthy_block') {
        $heading = trim((string)($_POST['healthy_heading'] ?? 'Healthy by Cakeouflage'));
    $uploadError = '';
    $uploadInfo = '';
    $uploaded = media_upload_file($_FILES['healthy_video_media'] ?? array(), $uploadError, $uploadInfo);

        if ($uploaded !== null) {
            media_set_setting($conn, 'home_healthy_video_url', $uploaded);
      $message = 'Healthy section content saved.' . ($uploadInfo !== '' ? ' ' . $uploadInfo : '');
    } elseif ($uploadError !== '' && (int)(($_FILES['healthy_video_media']['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE) {
      $messageType = 'error';
      $message = $uploadError;
        }
        media_set_setting($conn, 'home_healthy_heading', $heading);
    if ($message === '') {
      $message = 'Healthy section content saved.';
    }
    }

    if ($action === 'save_top_offer_banner') {
      $offerTitle = trim((string)($_POST['offer_title'] ?? 'Limited-time Offer'));
      $offerSubtitle = trim((string)($_POST['offer_subtitle'] ?? 'Use code at checkout'));
      $offerCtaLabel = trim((string)($_POST['offer_cta_label'] ?? 'Shop Now'));
      $offerCtaUrl = trim((string)($_POST['offer_cta_url'] ?? '/shop'));
      $offerPageScope = trim((string)($_POST['offer_page_scope'] ?? 'all_pages'));
      if (!in_array($offerPageScope, array('all_pages', 'exclude_checkout_auth'), true)) {
        $offerPageScope = 'all_pages';
      }
      $offerStartsAtRaw = trim((string)($_POST['offer_starts_at'] ?? ''));
      $offerEndsAtRaw = trim((string)($_POST['offer_ends_at'] ?? ''));
      $linkedCouponIdRaw = trim((string)($_POST['linked_coupon_id'] ?? ''));
      $linkedCouponId = ctype_digit($linkedCouponIdRaw) && (int)$linkedCouponIdRaw > 0 ? (int)$linkedCouponIdRaw : null;

      $startsAt = $offerStartsAtRaw !== '' ? str_replace('T', ' ', $offerStartsAtRaw) . ':00' : null;
      $endsAt = $offerEndsAtRaw !== '' ? str_replace('T', ' ', $offerEndsAtRaw) . ':59' : null;

      if ($offerTitle === '') {
        $messageType = 'error';
        $message = 'Top offer title is required.';
      } elseif ($endsAt !== null && $startsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
        $messageType = 'error';
        $message = 'Offer end datetime must be after start datetime.';
      } else {
        media_upsert_offer_banner($conn, $offerTitle, $offerSubtitle, $offerCtaLabel, $offerCtaUrl, $startsAt, $endsAt, $linkedCouponId, $offerPageScope);
        $message = 'Top offer banner settings saved.';
      }
    }
}

$homeHero = array('title' => 'Cakeouflage', 'subtitle' => 'Premium Cakes', 'image_url' => '/client/assets/video/Healthcake.MP4');
$homeStmt = $conn->prepare('SELECT title, subtitle, image_url FROM banners WHERE placement = "home_mid" ORDER BY id DESC LIMIT 1');
$homeStmt->execute();
$homeResult = $homeStmt->get_result();
if ($homeResult && ($row = $homeResult->fetch_assoc())) {
    $homeHero['title'] = (string)($row['title'] ?? $homeHero['title']);
    $homeHero['subtitle'] = (string)($row['subtitle'] ?? $homeHero['subtitle']);
    if (trim((string)($row['image_url'] ?? '')) !== '') {
        $homeHero['image_url'] = (string)$row['image_url'];
    }
}

$aboutVideo = '/client/assets/video/heroabout.MP4';
$aboutStmt = $conn->prepare('SELECT image_url FROM banners WHERE placement = "about_video" ORDER BY id DESC LIMIT 1');
$aboutStmt->execute();
$aboutResult = $aboutStmt->get_result();
if ($aboutResult && ($row = $aboutResult->fetch_assoc())) {
    if (trim((string)($row['image_url'] ?? '')) !== '') {
        $aboutVideo = (string)$row['image_url'];
    }
}

$chefVideo = media_get_setting($conn, 'home_chef_video_url', '/client/assets/video/heroabout.MP4');
$healthyVideo = media_get_setting($conn, 'home_healthy_video_url', '/client/assets/video/Healthcake.MP4');
$healthyHeading = media_get_setting($conn, 'home_healthy_heading', 'Healthy by Cakeouflage');

$topOffer = array(
  'title' => 'Limited-time Offer',
  'subtitle' => 'Use code at checkout',
  'cta_label' => 'Shop Now',
  'cta_url' => '/shop',
  'page_scope' => 'all_pages',
  'starts_at' => null,
  'ends_at' => null,
  'linked_coupon_id' => null,
  'is_active' => 1,
);
$hasTopOfferRow = false;
$topOfferStmt = $conn->prepare('SELECT title, subtitle, cta_label, cta_url, page_scope, starts_at, ends_at, linked_coupon_id, is_active FROM banners WHERE placement = "site_top_offer" ORDER BY id DESC LIMIT 1');
$topOfferStmt->execute();
$topOfferResult = $topOfferStmt->get_result();
if ($topOfferResult && ($row = $topOfferResult->fetch_assoc())) {
  $topOffer = array_merge($topOffer, $row);
  $hasTopOfferRow = true;
}

$couponOptions = array();
$couponResult = $conn->query('SELECT id, code, is_active, is_deleted, starts_at, ends_at FROM coupons WHERE is_deleted = 0 ORDER BY code ASC');
while ($couponResult && ($coupon = $couponResult->fetch_assoc())) {
  $couponOptions[] = $coupon;
}

$offerVisibility = media_offer_visibility_status($topOffer, $hasTopOfferRow, $couponOptions);

$uploadMaxBytes = media_ini_size_to_bytes((string)ini_get('upload_max_filesize'));
$postMaxBytes = media_ini_size_to_bytes((string)ini_get('post_max_size'));
$effectiveUploadLimitBytes = min($uploadMaxBytes > 0 ? $uploadMaxBytes : PHP_INT_MAX, $postMaxBytes > 0 ? $postMaxBytes : PHP_INT_MAX);
$effectiveUploadLimitLabel = $effectiveUploadLimitBytes === PHP_INT_MAX ? 'Server default' : media_format_bytes((int)$effectiveUploadLimitBytes);
?>
<style>
  .media-shell { display: grid; gap: 16px; max-width: 1120px; }
  .media-msg { border-radius: 10px; padding: 10px 12px; font-size: 0.86rem; }
  .media-msg.success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
  .media-msg.error { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
  .media-msg.media-msg--pulse { animation: mediaPulse 1.6s ease-out 1; }
  .media-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
  .media-card { background: #fff; border: 1px solid rgba(128,0,31,0.12); border-radius: 16px; box-shadow: 0 12px 24px rgba(68,16,34,0.08); overflow: hidden; }
  .media-card__head { padding: 14px 16px; border-bottom: 1px solid rgba(128,0,31,0.08); background: linear-gradient(180deg,#fff8fa 0%,#fff 100%); }
  .media-card__head h3 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
  .media-card__body { padding: 14px 16px; display: grid; gap: 10px; }
  .media-field { display: grid; gap: 6px; }
  .media-field label { font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.08em; color: #80001F; font-weight: 700; }
  .media-field input, .media-field select { border: 1px solid rgba(128,0,31,0.18); border-radius: 10px; padding: 9px 11px; font: inherit; }
  .media-preview { border: 1px solid rgba(128,0,31,0.12); border-radius: 12px; overflow: hidden; min-height: 160px; background: #faf7f8; }
  .media-preview video, .media-preview img { width: 100%; max-height: 220px; object-fit: cover; display: block; }
  .media-btn { border: 0; border-radius: 10px; padding: 9px 14px; background: #80001F; color: #fff; font-weight: 700; cursor: pointer; justify-self: start; }
  .media-btn[disabled] { opacity: 0.75; cursor: progress; }
  .media-help { font-size: 0.8rem; color: #7f6973; }
  .media-status {
    border-radius: 10px;
    padding: 9px 10px;
    font-size: 0.82rem;
    border: 1px solid;
    margin-top: 2px;
  }
  .media-status--active {
    background: #ecfdf3;
    border-color: #bbf7d0;
    color: #166534;
  }
  .media-status--inactive {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #9f1239;
  }
  .media-upload-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(21, 8, 13, 0.68);
    backdrop-filter: blur(4px);
    padding: 18px;
  }
  .media-upload-overlay.is-visible { display: flex; }
  .media-upload-card {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 16px;
    border: 1px solid rgba(128,0,31,0.15);
    box-shadow: 0 20px 40px rgba(68,16,34,0.28);
    padding: 20px;
    display: grid;
    gap: 12px;
  }
  .media-upload-title {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
    font-size: 1.4rem;
  }
  .media-upload-text {
    margin: 0;
    color: #6f4d57;
    font-size: 0.92rem;
    line-height: 1.4;
  }
  .media-upload-spinner-wrap { display: flex; align-items: center; gap: 12px; }
  .media-upload-spinner {
    width: 30px;
    height: 30px;
    border: 3px solid #f4d4dd;
    border-top-color: #80001F;
    border-radius: 50%;
    animation: mediaSpin 1s linear infinite;
    flex: 0 0 auto;
  }
  .media-upload-file {
    color: #6e2a3e;
    font-size: 0.84rem;
    word-break: break-word;
  }
  @keyframes mediaSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
  @keyframes mediaPulse {
    0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.35); }
    100% { box-shadow: 0 0 0 12px rgba(34, 197, 94, 0); }
  }
  @media (max-width: 940px) { .media-grid { grid-template-columns: 1fr; } }
</style>

<div class="media-shell" data-max-upload-bytes="<?= (int)$effectiveUploadLimitBytes ?>">
  <?php if ($message !== ''): ?>
    <div class="media-msg <?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <p class="media-help">Current server upload limit: <strong><?= htmlspecialchars($effectiveUploadLimitLabel, ENT_QUOTES, 'UTF-8') ?></strong>. Large videos are auto-optimized for web streaming when FFmpeg is available on server.</p>

  <div class="media-grid">
    <form class="media-card" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_home_hero">
      <div class="media-card__head"><h3>Home Hero Banner</h3></div>
      <div class="media-card__body">
        <div class="media-field"><label>Title</label><input type="text" name="home_title" value="<?= htmlspecialchars($homeHero['title'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="media-field"><label>Subtitle</label><input type="text" name="home_subtitle" value="<?= htmlspecialchars($homeHero['subtitle'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="media-field"><label>Hero Media (video/image)</label><input type="file" name="home_hero_media" accept="video/*,image/*"></div>
        <div class="media-preview"><?= media_render_preview_markup($homeHero['image_url'], '/client/assets/images/home/hero-poster.svg') ?></div>
        <button class="media-btn" type="submit">Save Home Hero</button>
      </div>
    </form>

    <form class="media-card" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_about_hero">
      <div class="media-card__head"><h3>About Hero Video</h3></div>
      <div class="media-card__body">
        <div class="media-field"><label>Upload Video</label><input type="file" name="about_hero_media" accept="video/*,image/*"></div>
        <div class="media-preview"><?= media_render_preview_markup($aboutVideo, '/client/assets/images/home/hero-poster.svg') ?></div>
        <button class="media-btn" type="submit">Save About Hero Video</button>
      </div>
    </form>

    <form class="media-card" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_chef_video">
      <div class="media-card__head"><h3>Chef Ansh Mehra Video</h3></div>
      <div class="media-card__body">
        <div class="media-field"><label>Upload Video</label><input type="file" name="chef_video_media" accept="video/*,image/*"></div>
        <div class="media-preview"><?= media_render_preview_markup($chefVideo, '/client/assets/images/home/hero-poster.svg') ?></div>
        <button class="media-btn" type="submit">Save Chef Video</button>
      </div>
    </form>

    <form class="media-card" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_healthy_block">
      <div class="media-card__head"><h3>Healthy Section</h3></div>
      <div class="media-card__body">
        <div class="media-field"><label>Heading Text</label><input type="text" name="healthy_heading" value="<?= htmlspecialchars($healthyHeading, ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="media-field"><label>Upload Video</label><input type="file" name="healthy_video_media" accept="video/*,image/*"></div>
        <div class="media-preview"><?= media_render_preview_markup($healthyVideo, '/client/assets/images/home/hero-poster.svg') ?></div>
        <button class="media-btn" type="submit">Save Healthy Section</button>
        <p class="media-help">This controls the "Healthy by Cakeouflage" area on homepage.</p>
      </div>
    </form>

    <form class="media-card" method="post">
      <input type="hidden" name="action" value="save_top_offer_banner">
      <div class="media-card__head"><h3>Site-wide Top Offer Banner</h3></div>
      <div class="media-card__body">
        <div class="media-field"><label>Offer Title</label><input type="text" name="offer_title" value="<?= htmlspecialchars((string)($topOffer['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="media-field"><label>Offer Subtitle</label><input type="text" name="offer_subtitle" value="<?= htmlspecialchars((string)($topOffer['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="media-field"><label>CTA Label</label><input type="text" name="offer_cta_label" value="<?= htmlspecialchars((string)($topOffer['cta_label'] ?? 'Shop Now'), ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="media-field"><label>CTA URL</label><input type="text" name="offer_cta_url" value="<?= htmlspecialchars((string)($topOffer['cta_url'] ?? '/shop'), ENT_QUOTES, 'UTF-8') ?>" placeholder="/shop"></div>
        <div class="media-field">
          <label>Page Scope</label>
          <select name="offer_page_scope">
            <option value="all_pages" <?= (string)($topOffer['page_scope'] ?? 'all_pages') === 'all_pages' ? 'selected' : '' ?>>All public pages</option>
            <option value="exclude_checkout_auth" <?= (string)($topOffer['page_scope'] ?? 'all_pages') === 'exclude_checkout_auth' ? 'selected' : '' ?>>Exclude checkout and auth pages</option>
          </select>
        </div>
        <div class="media-field"><label>Starts At (optional)</label><input type="datetime-local" name="offer_starts_at" value="<?= htmlspecialchars(!empty($topOffer['starts_at']) ? str_replace(' ', 'T', substr((string)$topOffer['starts_at'], 0, 16)) : '', ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="media-field"><label>Ends At (optional)</label><input type="datetime-local" name="offer_ends_at" value="<?= htmlspecialchars(!empty($topOffer['ends_at']) ? str_replace(' ', 'T', substr((string)$topOffer['ends_at'], 0, 16)) : '', ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="media-field">
          <label>Linked Coupon</label>
          <select name="linked_coupon_id">
            <option value="">None (message only)</option>
            <?php foreach ($couponOptions as $coupon): ?>
              <?php
                $cid = (int)($coupon['id'] ?? 0);
                $isSelected = (int)($topOffer['linked_coupon_id'] ?? 0) === $cid;
                $status = ((int)($coupon['is_active'] ?? 0) === 1 ? 'active' : 'paused');
                $endsAtText = trim((string)($coupon['ends_at'] ?? ''));
              ?>
              <option value="<?= $cid ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars((string)$coupon['code'] . ' [' . $status . ($endsAtText !== '' ? ' | ends ' . $endsAtText : '') . ']', ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="media-btn" type="submit">Save Top Offer Banner</button>
        <div class="media-status <?= ($offerVisibility['state'] ?? 'inactive') === 'active' ? 'media-status--active' : 'media-status--inactive' ?>">
          <strong>Status preview:</strong>
          <?= htmlspecialchars((string)($offerVisibility['reason'] ?? 'Visibility state unavailable.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <p class="media-help">This controls a premium offer strip across the site. You can optionally exclude checkout and auth pages.</p>
      </div>
    </form>
  </div>
</div>

<div class="media-upload-overlay" id="mediaUploadOverlay" aria-hidden="true">
  <div class="media-upload-card" role="status" aria-live="assertive" aria-atomic="true">
    <h4 class="media-upload-title">Uploading Video</h4>
    <p class="media-upload-text">Please wait while your file is uploaded and optimized for web delivery. Do not close this tab.</p>
    <div class="media-upload-spinner-wrap">
      <span class="media-upload-spinner" aria-hidden="true"></span>
      <span class="media-upload-file" id="mediaUploadFileMeta">Preparing upload...</span>
    </div>
  </div>
</div>

<script>
  (function () {
    var overlay = document.getElementById('mediaUploadOverlay');
    var fileMeta = document.getElementById('mediaUploadFileMeta');
    var forms = document.querySelectorAll('.media-card');
    var shell = document.querySelector('.media-shell');
    var maxUploadBytes = shell ? Number(shell.getAttribute('data-max-upload-bytes') || '0') : 0;
    var topMessage = document.querySelector('.media-msg.success');

    if (topMessage) {
      topMessage.classList.add('media-msg--pulse');
    }

    function formatBytes(bytes) {
      var num = Number(bytes || 0);
      if (!num || num < 1) {
        return 'Unknown size';
      }

      var units = ['B', 'KB', 'MB', 'GB'];
      var idx = 0;
      while (num >= 1024 && idx < units.length - 1) {
        num = num / 1024;
        idx += 1;
      }
      return num.toFixed(idx === 0 ? 0 : 1) + ' ' + units[idx];
    }

    forms.forEach(function (form) {
      form.addEventListener('submit', function () {
        var fileInput = form.querySelector('input[type="file"]');
        var file = fileInput && fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;
        if (file && maxUploadBytes > 0 && file.size > maxUploadBytes) {
          alert('Selected file is larger than current server upload limit. Please compress before upload or increase server limit.');
          return false;
        }

        var submitBtn = form.querySelector('.media-btn[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.dataset.originalText = submitBtn.textContent;
          submitBtn.textContent = 'Uploading...';
        }

        var isVideo = !!(file && typeof file.type === 'string' && file.type.indexOf('video/') === 0);

        if (overlay && isVideo) {
          var meta = file.name + ' (' + formatBytes(file.size) + ')';
          fileMeta.textContent = meta;
          overlay.classList.add('is-visible');
          overlay.setAttribute('aria-hidden', 'false');
        }
      });
    });
  })();
</script>
