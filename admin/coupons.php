<?php
$pageTitle = 'Coupons';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('coupons');
require_once __DIR__ . '/includes/db.php';

function coupon_generate_code(int $length = 8): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $code = 'CKF';
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

function coupon_column_exists(mysqli $conn, string $column): bool
{
    $safe = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM coupons LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function coupon_ensure_schema(mysqli $conn): void
{
    if (!coupon_column_exists($conn, 'per_user_usage_limit')) {
        $conn->query('ALTER TABLE coupons ADD COLUMN per_user_usage_limit INT NULL AFTER usage_limit');
    }
    if (!coupon_column_exists($conn, 'target_mode')) {
        $conn->query('ALTER TABLE coupons ADD COLUMN target_mode ENUM("all_users","specific_users") NOT NULL DEFAULT "all_users" AFTER ends_at');
    }
    if (!coupon_column_exists($conn, 'is_deleted')) {
        $conn->query('ALTER TABLE coupons ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
    }
    if (!coupon_column_exists($conn, 'deleted_at')) {
        $conn->query('ALTER TABLE coupons ADD COLUMN deleted_at DATETIME NULL AFTER is_deleted');
    }
    $conn->query('CREATE TABLE IF NOT EXISTS coupon_target_users (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        coupon_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_coupon_target_user (coupon_id, user_id),
        INDEX idx_coupon_target_user (user_id),
        CONSTRAINT fk_coupon_target_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
        CONSTRAINT fk_coupon_target_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB');
    $conn->query('CREATE TABLE IF NOT EXISTS coupon_redemptions (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        coupon_id BIGINT UNSIGNED NOT NULL,
        order_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        code_snapshot VARCHAR(50) NOT NULL,
        discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_coupon_order (coupon_id, order_id),
        INDEX idx_coupon_redemption_user (coupon_id, user_id),
        CONSTRAINT fk_coupon_redemption_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
        CONSTRAINT fk_coupon_redemption_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        CONSTRAINT fk_coupon_redemption_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB');
}

function coupon_resolve_target_user_ids(mysqli $conn, string $raw): array
{
    $tokens = preg_split('/[\s,]+/', trim($raw)) ?: [];
    $ids = [];
    $emailStmt = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');

    foreach ($tokens as $token) {
        $value = trim((string)$token);
        if ($value === '') {
            continue;
        }
        if (ctype_digit($value)) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[$id] = true;
            }
            continue;
        }
        if ($emailStmt) {
            $emailStmt->bind_param('s', $value);
            $emailStmt->execute();
            $res = $emailStmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if ($row && isset($row['id'])) {
                $ids[(int)$row['id']] = true;
            }
        }
    }

    return array_keys($ids);
}

coupon_ensure_schema($conn);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'create' || $action === 'edit') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        $isEdit = $action === 'edit' && $couponId > 0;

        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $discountType = trim((string)($_POST['discount_type'] ?? 'flat'));
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $maxDiscount = trim((string)($_POST['max_discount'] ?? ''));
        $minOrderAmount = trim((string)($_POST['min_order_amount'] ?? ''));
        $usageLimit = trim((string)($_POST['usage_limit'] ?? ''));
        $perUserUsageLimit = trim((string)($_POST['per_user_usage_limit'] ?? ''));
        $startsAt = trim((string)($_POST['starts_at'] ?? ''));
        $endsAt = trim((string)($_POST['ends_at'] ?? ''));
        $targetMode = trim((string)($_POST['target_mode'] ?? 'all_users'));
        $targetUsersRaw = trim((string)($_POST['target_users'] ?? ''));

        if ($code === '' && !$isEdit) {
            $code = coupon_generate_code(7);
        }

        if (!in_array($discountType, ['flat', 'percentage'], true) || $discountValue <= 0) {
            $messageType = 'error';
            $message = 'Provide valid coupon discount details.';
        } elseif ($endsAt === '') {
            $messageType = 'error';
            $message = 'Coupon end date is required.';
        } elseif ($targetMode !== 'all_users' && $targetMode !== 'specific_users') {
            $messageType = 'error';
            $message = 'Invalid target mode selected.';
        } else {
            $maxDiscountValue = $maxDiscount !== '' ? (float)$maxDiscount : null;
            if ($discountType === 'percentage' && ($maxDiscountValue === null || $maxDiscountValue <= 0)) {
                $messageType = 'error';
                $message = 'Max discount is mandatory for percentage coupons.';
            } else {
                $minOrderValue = $minOrderAmount !== '' ? (float)$minOrderAmount : null;
                $usageLimitValue = $usageLimit !== '' ? (int)$usageLimit : null;
                $perUserUsageLimitValue = $perUserUsageLimit !== '' ? (int)$perUserUsageLimit : null;
                $startsAtValue = $startsAt !== '' ? $startsAt . ' 00:00:00' : null;
                $endsAtValue = $endsAt . ' 23:59:59';
                if ($startsAtValue !== null && strtotime($startsAtValue) > strtotime($endsAtValue)) {
                    $messageType = 'error';
                    $message = 'End date must be after start date.';
                } else {
                    $targetUserIds = [];
                    if ($targetMode === 'specific_users') {
                        $targetUserIds = coupon_resolve_target_user_ids($conn, $targetUsersRaw);
                        if (count($targetUserIds) === 0) {
                            $messageType = 'error';
                            $message = 'Specific-users coupon requires valid user IDs or emails.';
                        }
                    }

                    if ($message === '') {
                        if ($isEdit) {
                            $existingStmt = $conn->prepare('SELECT * FROM coupons WHERE id = ? LIMIT 1');
                            $existingStmt->bind_param('i', $couponId);
                            $existingStmt->execute();
                            $existing = $existingStmt->get_result()->fetch_assoc();

                            if (!$existing) {
                                $messageType = 'error';
                                $message = 'Coupon not found.';
                            } else {
                                $usageCount = (int)($existing['usage_count'] ?? 0);
                                if ($usageCount > 0) {
                                    $code = (string)$existing['code'];
                                    $discountType = (string)$existing['discount_type'];
                                    $discountValue = (float)$existing['discount_value'];
                                    $maxDiscountValue = $existing['max_discount'] !== null ? (float)$existing['max_discount'] : null;
                                    $targetMode = (string)$existing['target_mode'];
                                    $messageType = 'error';
                                    $message = 'Coupon has usage history. Core discount fields are locked; only safe fields were updated.';
                                }

                                $update = $conn->prepare('UPDATE coupons SET code = ?, discount_type = ?, discount_value = ?, max_discount = ?, min_order_amount = ?, usage_limit = ?, per_user_usage_limit = ?, starts_at = ?, ends_at = ?, target_mode = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
                                if ($update) {
                                  $update->bind_param('ssdddiisssi', $code, $discountType, $discountValue, $maxDiscountValue, $minOrderValue, $usageLimitValue, $perUserUsageLimitValue, $startsAtValue, $endsAtValue, $targetMode, $couponId);
                                  $update->execute();

                                    if ($targetMode === 'specific_users' && $usageCount === 0) {
                                        $conn->query('DELETE FROM coupon_target_users WHERE coupon_id = ' . (int)$couponId);
                                        $targetInsert = $conn->prepare('INSERT INTO coupon_target_users (coupon_id, user_id) VALUES (?, ?)');
                                        if ($targetInsert) {
                                            foreach ($targetUserIds as $uid) {
                                                $targetInsert->bind_param('ii', $couponId, $uid);
                                                $targetInsert->execute();
                                            }
                                        }
                                    }

                                    if ($message === '') {
                                        $message = 'Coupon updated successfully.';
                                    }
                                }
                            }
                        } else {
                            $insert = $conn->prepare('INSERT INTO coupons (code, discount_type, discount_value, max_discount, min_order_amount, usage_limit, per_user_usage_limit, starts_at, ends_at, target_mode, is_active, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)');
                            if ($insert) {
                                $insert->bind_param('ssdddiisss', $code, $discountType, $discountValue, $maxDiscountValue, $minOrderValue, $usageLimitValue, $perUserUsageLimitValue, $startsAtValue, $endsAtValue, $targetMode);
                                if ($insert->execute()) {
                                    $couponId = (int)$conn->insert_id;
                                    if ($targetMode === 'specific_users') {
                                        $targetInsert = $conn->prepare('INSERT INTO coupon_target_users (coupon_id, user_id) VALUES (?, ?)');
                                        if ($targetInsert) {
                                            foreach ($targetUserIds as $uid) {
                                                $targetInsert->bind_param('ii', $couponId, $uid);
                                                $targetInsert->execute();
                                            }
                                        }
                                    }
                                    $message = 'Coupon created successfully: ' . $code;
                                } else {
                                    $messageType = 'error';
                                    $message = 'Coupon code already exists or invalid data.';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($action === 'toggle') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        $nextState = (int)($_POST['next_state'] ?? 0) === 1 ? 1 : 0;
        if ($couponId > 0) {
            $update = $conn->prepare('UPDATE coupons SET is_active = ?, updated_at = NOW() WHERE id = ? AND is_deleted = 0 LIMIT 1');
            if ($update) {
                $update->bind_param('ii', $nextState, $couponId);
                $update->execute();
                $message = $nextState === 1 ? 'Coupon activated.' : 'Coupon paused.';
            }
        }
    }

    if ($action === 'delete') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        if ($couponId > 0) {
            $read = $conn->prepare('SELECT usage_count FROM coupons WHERE id = ? LIMIT 1');
            if ($read) {
                $read->bind_param('i', $couponId);
                $read->execute();
                $row = $read->get_result()->fetch_assoc();
                $usageCount = (int)($row['usage_count'] ?? 0);
                if ($usageCount > 0) {
                    $soft = $conn->prepare('UPDATE coupons SET is_active = 0, is_deleted = 1, deleted_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1');
                    if ($soft) {
                        $soft->bind_param('i', $couponId);
                        $soft->execute();
                        $message = 'Coupon archived (soft deleted) because usage exists.';
                    }
                } else {
                    $conn->query('DELETE FROM coupon_target_users WHERE coupon_id = ' . (int)$couponId);
                    $hard = $conn->prepare('DELETE FROM coupons WHERE id = ? LIMIT 1');
                    if ($hard) {
                        $hard->bind_param('i', $couponId);
                        $hard->execute();
                        $message = 'Coupon deleted permanently.';
                    }
                }
            }
        }
    }

        if ($action === 'promote_as_banner') {
          $couponId        = (int)($_POST['coupon_id'] ?? 0);
          $bannerTitle     = trim((string)($_POST['banner_title'] ?? ''));
          $bannerSubtitle  = trim((string)($_POST['banner_subtitle'] ?? ''));
          $bannerCtaLabel  = trim((string)($_POST['banner_cta_label'] ?? 'Shop Now'));
          $bannerCtaUrl    = trim((string)($_POST['banner_cta_url'] ?? '/shop'));
          $bannerPageScope = trim((string)($_POST['banner_page_scope'] ?? 'all_pages'));
          if (!in_array($bannerPageScope, ['all_pages', 'exclude_checkout_auth'], true)) {
            $bannerPageScope = 'all_pages';
          }
          if ($couponId <= 0) {
            $messageType = 'error';
            $message = 'Invalid coupon.';
          } elseif ($bannerTitle === '') {
            $messageType = 'error';
            $message = 'Banner title is required.';
          } else {
            // Inherit the coupon's validity window so the banner stays in sync automatically
            $cqDates = $conn->prepare('SELECT starts_at, ends_at FROM coupons WHERE id = ? LIMIT 1');
            $cqDates->bind_param('i', $couponId);
            $cqDates->execute();
            $cqDatesRow = $cqDates->get_result()->fetch_assoc();
            $cqDates->close();
            $bannerStartsAt = $cqDatesRow ? $cqDatesRow['starts_at'] : null;
            $bannerEndsAt   = $cqDatesRow ? $cqDatesRow['ends_at']   : null;
            $bxRes = $conn->query('SELECT id FROM banners WHERE placement = "site_top_offer" ORDER BY id DESC LIMIT 1');
            $bxRow = $bxRes ? $bxRes->fetch_assoc() : null;
            if ($bxRow) {
              $bid = (int)$bxRow['id'];
              $upd = $conn->prepare('UPDATE banners SET title=?, subtitle=?, cta_label=?, cta_url=?, starts_at=?, ends_at=?, linked_coupon_id=?, page_scope=?, is_active=1, updated_at=NOW() WHERE id=? LIMIT 1');
              $upd->bind_param('ssssssisi', $bannerTitle, $bannerSubtitle, $bannerCtaLabel, $bannerCtaUrl, $bannerStartsAt, $bannerEndsAt, $couponId, $bannerPageScope, $bid);
              $upd->execute();
              $upd->close();
            } else {
              $ins = $conn->prepare('INSERT INTO banners (title, subtitle, image_url, cta_label, cta_url, starts_at, ends_at, linked_coupon_id, page_scope, placement, is_active, sort_order) VALUES (?, ?, "", ?, ?, ?, ?, ?, ?, "site_top_offer", 1, 0)');
              $ins->bind_param('ssssssis', $bannerTitle, $bannerSubtitle, $bannerCtaLabel, $bannerCtaUrl, $bannerStartsAt, $bannerEndsAt, $couponId, $bannerPageScope);
              $ins->execute();
              $ins->close();
            }
            $message = 'Top offer banner saved and activated.';
          }
        }

        if ($action === 'deactivate_banner' || $action === 'activate_banner') {
          $newState = $action === 'activate_banner' ? 1 : 0;
          $bxRes = $conn->query('SELECT id FROM banners WHERE placement = "site_top_offer" ORDER BY id DESC LIMIT 1');
          $bxRow = $bxRes ? $bxRes->fetch_assoc() : null;
          if ($bxRow) {
            $conn->query('UPDATE banners SET is_active=' . $newState . ', updated_at=NOW() WHERE id=' . (int)$bxRow['id'] . ' LIMIT 1');
            $message = $newState === 1 ? 'Top offer banner activated.' : 'Top offer banner paused.';
          }
        }

        if ($action === 'clear_banner_coupon') {
          $bxRes = $conn->query('SELECT id FROM banners WHERE placement = "site_top_offer" ORDER BY id DESC LIMIT 1');
          $bxRow = $bxRes ? $bxRes->fetch_assoc() : null;
          if ($bxRow) {
            $conn->query('UPDATE banners SET linked_coupon_id=NULL, is_active=0, updated_at=NOW() WHERE id=' . (int)$bxRow['id'] . ' LIMIT 1');
            $message = 'Coupon removed from top offer banner.';
          }
        }
}

$coupons = [];
$result = $conn->query('SELECT id, code, discount_type, discount_value, max_discount, min_order_amount, usage_limit, per_user_usage_limit, usage_count, starts_at, ends_at, target_mode, is_active, is_deleted, deleted_at, created_at FROM coupons ORDER BY id DESC');
while ($result && ($row = $result->fetch_assoc())) {
    $coupons[] = $row;
}

$topOfferLink = [
  'id'               => 0,
  'title'            => '',
  'subtitle'         => '',
  'cta_label'        => 'Shop Now',
  'cta_url'          => '/shop',
  'page_scope'       => 'all_pages',
  'linked_coupon_id' => 0,
  'is_active'        => 0,
  'starts_at'        => null,
  'ends_at'          => null,
];
$topOfferStmt = $conn->prepare('SELECT id, title, subtitle, cta_label, cta_url, page_scope, linked_coupon_id, is_active, starts_at, ends_at FROM banners WHERE placement = "site_top_offer" ORDER BY id DESC LIMIT 1');
if ($topOfferStmt) {
  $topOfferStmt->execute();
  $topOfferResult = $topOfferStmt->get_result();
  $topOfferRow = $topOfferResult ? $topOfferResult->fetch_assoc() : null;
  if (is_array($topOfferRow)) {
    $topOfferLink = array_merge($topOfferLink, $topOfferRow);
  }
  $topOfferStmt->close();
}

$targetMap = [];
$targetsResult = $conn->query('SELECT ctu.coupon_id, GROUP_CONCAT(u.email ORDER BY u.email SEPARATOR ", ") AS emails FROM coupon_target_users ctu JOIN users u ON u.id = ctu.user_id GROUP BY ctu.coupon_id');
while ($targetsResult && ($row = $targetsResult->fetch_assoc())) {
    $targetMap[(int)$row['coupon_id']] = (string)($row['emails'] ?? '');
}

$redemptionRowsByCoupon = [];
$inlineRedemptionsResult = $conn->query(
  'SELECT
    cr.id,
    cr.coupon_id,
    cr.order_id,
    cr.user_id,
    cr.code_snapshot,
    cr.discount_total,
    cr.created_at,
    u.full_name AS user_name,
    u.email AS user_email,
    o.order_number,
    o.order_status,
    o.payment_status
   FROM coupon_redemptions cr
   LEFT JOIN users u ON u.id = cr.user_id
   LEFT JOIN orders o ON o.id = cr.order_id
   ORDER BY cr.created_at DESC
   LIMIT 1200'
);
while ($inlineRedemptionsResult && ($row = $inlineRedemptionsResult->fetch_assoc())) {
  $couponId = (int)($row['coupon_id'] ?? 0);
  if ($couponId <= 0) {
    continue;
  }
  if (!isset($redemptionRowsByCoupon[$couponId])) {
    $redemptionRowsByCoupon[$couponId] = [];
  }
  $redemptionRowsByCoupon[$couponId][] = $row;
}

include __DIR__ . '/layout.php';
?>
<style>
  .coupon-shell { display: grid; gap: 16px; }
  .coupon-card {
    background: #fff;
    border: 1px solid rgba(128,0,31,0.12);
    border-radius: 16px;
    box-shadow: 0 12px 26px rgba(68,16,34,0.08);
    overflow: hidden;
  }
  .coupon-card__head {
    padding: 16px 18px;
    border-bottom: 1px solid rgba(128,0,31,0.08);
    background: linear-gradient(180deg,#fff8fa 0%,#fff 100%);
  }
  .coupon-card__head h3 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
  .coupon-card__body { padding: 18px; }
  .coupon-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 10px; }
  .coupon-field { display: grid; gap: 6px; }
  .coupon-field label { font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.08em; color: #80001F; font-weight: 700; }
  .coupon-field input, .coupon-field select, .coupon-field textarea { border: 1px solid rgba(128,0,31,0.2); border-radius: 10px; padding: 9px 11px; font: inherit; }
  .coupon-field textarea { min-height: 64px; resize: vertical; }
  .coupon-actions { margin-top: 10px; display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
  .coupon-btn { border: 0; border-radius: 10px; padding: 9px 14px; background: #80001F; color: #fff; font-weight: 700; cursor: pointer; }
  .coupon-btn.ghost { background: #fff; border: 1px solid rgba(128,0,31,0.2); color: #80001F; }
  .coupon-btn.warn { background: #7f1d1d; }
  .coupon-message { border-radius: 10px; padding: 10px 12px; font-size: 0.86rem; }
  .coupon-message.success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
  .coupon-message.error { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
  .coupon-table-wrap { overflow: auto; }
  .coupon-table { width: 100%; border-collapse: collapse; min-width: 1180px; }
  .coupon-table th, .coupon-table td { padding: 11px 10px; border-bottom: 1px solid rgba(128,0,31,0.08); text-align: left; vertical-align: top; }
  .coupon-table th { font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: #80001F; background: #fff6f8; }
  .coupon-pill { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-right: 6px; }
  .coupon-pill.active { background: #dcfce7; color: #166534; }
  .coupon-pill.paused { background: #fff2cf; color: #9a5b00; }
  .coupon-pill.expired { background: #fee2e2; color: #9f1239; }
  .coupon-pill.deleted { background: #e5e7eb; color: #374151; }
  .coupon-pill.offer-live { background: #dcfce7; color: #166534; }
  .coupon-pill.offer-linked { background: #e0e7ff; color: #3730a3; }
  .coupon-edit-row { background: #fff8fa; }
  .coupon-audit-row { background: #fffdf8; }
  .coupon-inline-audit { border: 1px solid rgba(128,0,31,0.12); border-radius: 12px; padding: 10px; }
  .coupon-inline-audit h4 { margin: 0 0 8px; font-size: 0.9rem; color: #80001F; }
  .coupon-mini-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
  .coupon-mini-table th, .coupon-mini-table td { border-bottom: 1px solid rgba(128,0,31,0.09); padding: 7px 6px; text-align: left; }
  .coupon-mini-table th { color: #80001F; font-size: 0.72rem; letter-spacing: 0.07em; text-transform: uppercase; background: #fff8fa; }
  .coupon-report-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 10px; }
  .coupon-report-stat { border: 1px solid rgba(128,0,31,0.14); border-radius: 12px; background: linear-gradient(180deg,#fff8fa 0%,#fff 100%); padding: 12px; }
  .coupon-report-stat .label { font-size: 0.73rem; text-transform: uppercase; letter-spacing: 0.08em; color: #8f7681; font-weight: 700; }
  .coupon-report-stat .value { font-size: 1.2rem; font-weight: 700; color: #80001F; margin-top: 4px; }
  .coupon-filter-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 10px; margin-bottom: 10px; }
  .coupon-note { color: #8f7681; font-size: 0.84rem; margin: 0; }
  .coupon-banner-status-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; border-radius: 12px; margin-bottom: 14px; font-size: 0.87rem; }
  .coupon-banner-status-bar--live { background: #f0fdf4; border: 1px solid #bbf7d0; }
  .coupon-banner-status-bar--paused { background: #fffbeb; border: 1px solid #fde68a; }
  .coupon-banner-status-bar code { background: rgba(128,0,31,0.08); border-radius: 6px; padding: 2px 7px; font-size: 0.85rem; font-weight: 700; color: #80001F; }
  .coupon-banner-row { background: #f0f7ff; }
  .coupon-banner-panel { padding: 14px; }
  .coupon-banner-panel__head { margin-bottom: 12px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .coupon-banner-panel__head strong { color: #80001F; font-size: 0.96rem; }
  .coupon-banner-panel__head span { font-size: 0.84rem; color: #666; }
  @media (max-width: 1020px) {
    .coupon-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .coupon-filter-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .coupon-report-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
  }
  @media (max-width: 760px) {
    .coupon-grid, .coupon-filter-grid, .coupon-report-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="coupon-shell">
  <?php if ($message !== ''): ?>
    <div class="coupon-message <?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <section class="coupon-card">
    <div class="coupon-card__head">
      <h3>Create Coupon</h3>
    </div>
    <form class="coupon-card__body" method="post">
      <input type="hidden" name="action" value="create">
      <div class="coupon-grid">
        <div class="coupon-field">
          <label for="code">Code (optional)</label>
          <input id="code" name="code" maxlength="50" placeholder="Auto-generated if empty">
        </div>
        <div class="coupon-field">
          <label for="discount_type">Discount Type</label>
          <select id="discount_type" name="discount_type" required>
            <option value="flat">Flat (INR)</option>
            <option value="percentage">Percentage (%)</option>
          </select>
        </div>
        <div class="coupon-field">
          <label for="discount_value">Discount Value</label>
          <input id="discount_value" name="discount_value" type="number" min="0.01" step="0.01" required>
        </div>
        <div class="coupon-field">
          <label for="max_discount">Max Discount (required for %)</label>
          <input id="max_discount" name="max_discount" type="number" min="0.01" step="0.01" placeholder="required for percentage">
        </div>
        <div class="coupon-field">
          <label for="min_order_amount">Min Order Amount</label>
          <input id="min_order_amount" name="min_order_amount" type="number" min="0" step="0.01" placeholder="optional">
        </div>
        <div class="coupon-field">
          <label for="usage_limit">Global Usage Limit</label>
          <input id="usage_limit" name="usage_limit" type="number" min="1" step="1" placeholder="optional">
        </div>
        <div class="coupon-field">
          <label for="per_user_usage_limit">Per-User Usage Limit</label>
          <input id="per_user_usage_limit" name="per_user_usage_limit" type="number" min="1" step="1" placeholder="optional">
        </div>
        <div class="coupon-field">
          <label for="starts_at">Start Date</label>
          <input id="starts_at" name="starts_at" type="date">
        </div>
        <div class="coupon-field">
          <label for="ends_at">End Date (mandatory)</label>
          <input id="ends_at" name="ends_at" type="date" required>
        </div>
        <div class="coupon-field">
          <label for="target_mode">Target Users</label>
          <select id="target_mode" name="target_mode" required>
            <option value="all_users">All users</option>
            <option value="specific_users">Specific users only</option>
          </select>
        </div>
        <div class="coupon-field" style="grid-column: span 2;">
          <label for="target_users">Target user IDs or emails (comma/newline)</label>
          <textarea id="target_users" name="target_users" placeholder="22, 31, customer@example.com"></textarea>
          <p class="coupon-note">Required only when Target Users = Specific users only.</p>
        </div>
      </div>
      <div class="coupon-actions">
        <button class="coupon-btn" type="submit">Create Coupon</button>
      </div>
    </form>
  </section>

  <section class="coupon-card">
    <div class="coupon-card__head">
      <h3>Coupon Library</h3>
    </div>
    <div class="coupon-card__body coupon-table-wrap">
      <?php if ((int)($topOfferLink['id'] ?? 0) > 0): ?>
        <?php
          $bannerIsLive = (int)($topOfferLink['is_active'] ?? 0) === 1;
          $bannerLinkedCode = '';
          foreach ($coupons as $_bc) {
            if ((int)$_bc['id'] === (int)($topOfferLink['linked_coupon_id'] ?? 0)) {
              $bannerLinkedCode = (string)$_bc['code'];
              break;
            }
          }
        ?>
        <div class="coupon-banner-status-bar <?= $bannerIsLive ? 'coupon-banner-status-bar--live' : 'coupon-banner-status-bar--paused' ?>">
          <span class="coupon-pill <?= $bannerIsLive ? 'offer-live' : 'paused' ?>"><?= $bannerIsLive ? 'Banner Live' : 'Banner Paused' ?></span>
          <strong><?= htmlspecialchars((string)($topOfferLink['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
          <?php if ($bannerLinkedCode !== ''): ?>
            &middot; Code: <code><?= htmlspecialchars($bannerLinkedCode, ENT_QUOTES, 'UTF-8') ?></code>
          <?php endif; ?>
          <span style="flex:1"></span>
          <?php if ($bannerIsLive): ?>
            <form method="post" style="margin:0">
              <input type="hidden" name="action" value="deactivate_banner">
              <button class="coupon-btn ghost" type="submit" style="padding:4px 11px;font-size:0.8rem;">Pause Banner</button>
            </form>
          <?php else: ?>
            <form method="post" style="margin:0">
              <input type="hidden" name="action" value="activate_banner">
              <button class="coupon-btn ghost" type="submit" style="padding:4px 11px;font-size:0.8rem;">Activate Banner</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <table class="coupon-table">
        <tr>
          <th>Code</th>
          <th>Discount</th>
          <th>Rules</th>
          <th>Usage</th>
          <th>Window</th>
          <th>Targets</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
        <?php if (!$coupons): ?>
          <tr><td colspan="8">No coupons found.</td></tr>
        <?php endif; ?>
        <?php foreach ($coupons as $coupon): ?>
          <?php
            $couponId = (int)$coupon['id'];
            $discountText = (string)$coupon['discount_type'] === 'percentage'
              ? ((float)$coupon['discount_value']) . '%'
              : 'Rs ' . number_format((float)$coupon['discount_value'], 2);
            $ruleText = 'Min Rs ' . number_format((float)($coupon['min_order_amount'] ?? 0), 2);
            if ($coupon['min_order_amount'] === null) {
              $ruleText = 'No min order';
            }
            if ((string)$coupon['discount_type'] === 'percentage' && $coupon['max_discount'] !== null) {
              $ruleText .= ' | Max Rs ' . number_format((float)$coupon['max_discount'], 2);
            }
            if ($coupon['usage_limit'] !== null) {
              $ruleText .= ' | Global limit ' . (int)$coupon['usage_limit'];
            }
            if ($coupon['per_user_usage_limit'] !== null) {
              $ruleText .= ' | Per-user ' . (int)$coupon['per_user_usage_limit'];
            }
            $isExpired = isset($coupon['ends_at']) && $coupon['ends_at'] !== null && strtotime((string)$coupon['ends_at']) < time();
            $targetMode = (string)($coupon['target_mode'] ?? 'all_users');
            $targetDisplay = $targetMode === 'specific_users' ? ((string)($targetMap[$couponId] ?? 'No mapped users')) : 'All users';
            $isTopOfferLinked = (int)($topOfferLink['linked_coupon_id'] ?? 0) === $couponId;
            $offerStartsTs = !empty($topOfferLink['starts_at']) ? strtotime((string)$topOfferLink['starts_at']) : false;
            $offerEndsTs = !empty($topOfferLink['ends_at']) ? strtotime((string)$topOfferLink['ends_at']) : false;
            $inOfferWindow = ($offerStartsTs === false || time() >= $offerStartsTs) && ($offerEndsTs === false || time() <= $offerEndsTs);
            $isTopOfferLive = $isTopOfferLinked && (int)($topOfferLink['is_active'] ?? 0) === 1 && $inOfferWindow && (int)($coupon['is_active'] ?? 0) === 1 && (int)($coupon['is_deleted'] ?? 0) === 0 && !$isExpired;
          ?>
          <tr>
            <td><strong><?= htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8') ?></strong></td>
            <td><?= htmlspecialchars($discountText, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($ruleText, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$coupon['usage_count'] ?><?= $coupon['usage_limit'] !== null ? ' / ' . (int)$coupon['usage_limit'] : '' ?></td>
            <td><?= htmlspecialchars((string)($coupon['starts_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars((string)($coupon['ends_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($targetDisplay, ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if ((int)$coupon['is_deleted'] === 1): ?><span class="coupon-pill deleted">Deleted</span><?php endif; ?>
              <?php if ($isExpired): ?><span class="coupon-pill expired">Expired</span><?php endif; ?>
              <?php if ((int)$coupon['is_deleted'] === 0): ?><span class="coupon-pill <?= (int)$coupon['is_active'] === 1 ? 'active' : 'paused' ?>"><?= (int)$coupon['is_active'] === 1 ? 'Active' : 'Paused' ?></span><?php endif; ?>
              <?php if ($isTopOfferLive): ?><span class="coupon-pill offer-live">Top Offer Live</span><?php elseif ($isTopOfferLinked): ?><span class="coupon-pill offer-linked">Top Offer Linked</span><?php endif; ?>
            </td>
            <td>
              <div class="coupon-actions" style="margin-top:0; justify-content:flex-start;">
                <?php if ((int)$coupon['is_deleted'] === 0): ?>
                <form method="post" style="margin:0">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="coupon_id" value="<?= $couponId ?>">
                  <input type="hidden" name="next_state" value="<?= (int)$coupon['is_active'] === 1 ? '0' : '1' ?>">
                  <button class="coupon-btn ghost" type="submit"><?= (int)$coupon['is_active'] === 1 ? 'Pause' : 'Activate' ?></button>
                </form>
                <?php endif; ?>
                <button class="coupon-btn ghost" type="button" onclick="toggleCouponEdit(<?= $couponId ?>)">Edit</button>
                <button class="coupon-btn ghost" type="button" onclick="toggleCouponAudit(<?= $couponId ?>)">Audit</button>
                <?php if ($isTopOfferLinked): ?>
                <form method="post" style="margin:0">
                  <input type="hidden" name="action" value="<?= (int)($topOfferLink['is_active'] ?? 0) === 1 ? 'deactivate_banner' : 'activate_banner' ?>">
                  <button class="coupon-btn ghost" type="submit"><?= (int)($topOfferLink['is_active'] ?? 0) === 1 ? 'Pause Banner' : 'Resume Banner' ?></button>
                </form>
                <button class="coupon-btn ghost" type="button" onclick="toggleCouponBanner(<?= $couponId ?>)">Edit Banner</button>
                <form method="post" style="margin:0" onsubmit="return confirm('Remove this coupon from the top offer banner?')">
                  <input type="hidden" name="action" value="clear_banner_coupon">
                  <button class="coupon-btn ghost" type="submit">Remove Banner</button>
                </form>
                <?php elseif ((int)($coupon['is_deleted'] ?? 0) === 0): ?>
                <button class="coupon-btn ghost" type="button" onclick="toggleCouponBanner(<?= $couponId ?>)">&#128226; Set as Top Banner</button>
                <?php endif; ?>
                <form method="post" style="margin:0" onsubmit="return confirm('Delete this coupon?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="coupon_id" value="<?= $couponId ?>">
                  <button class="coupon-btn warn" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <tr id="coupon-audit-<?= $couponId ?>" class="coupon-audit-row" style="display:none;">
            <td colspan="8">
              <div class="coupon-inline-audit">
                <h4>Coupon Redemptions for <?= htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8') ?></h4>
                <?php $inlineRows = $redemptionRowsByCoupon[$couponId] ?? []; ?>
                <?php if (!$inlineRows): ?>
                  <p class="coupon-note">No redemptions found for this coupon yet.</p>
                <?php else: ?>
                  <table class="coupon-mini-table">
                    <tr>
                      <th>Redeemed At</th>
                      <th>Order</th>
                      <th>User</th>
                      <th>Discount</th>
                      <th>Order Status</th>
                      <th>Payment</th>
                    </tr>
                    <?php foreach (array_slice($inlineRows, 0, 8) as $log): ?>
                      <?php
                        $userLabel = trim((string)($log['user_name'] ?? ''));
                        $userEmail = trim((string)($log['user_email'] ?? ''));
                        if ($userLabel === '') {
                          $userLabel = $userEmail !== '' ? $userEmail : ('User #' . (int)($log['user_id'] ?? 0));
                        } elseif ($userEmail !== '') {
                          $userLabel .= ' (' . $userEmail . ')';
                        }
                      ?>
                      <tr>
                        <td><?= htmlspecialchars((string)($log['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($log['order_number'] ?? ('#' . (int)($log['order_id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>Rs <?= number_format((float)($log['discount_total'] ?? 0), 2) ?></td>
                        <td><?= htmlspecialchars((string)($log['order_status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($log['payment_status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </table>
                  <p class="coupon-note" style="margin-top:8px;">Showing latest <?= min(count($inlineRows), 8) ?> entries for this coupon. Use the report section below for full filtered audit.</p>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <tr id="coupon-edit-<?= $couponId ?>" class="coupon-edit-row" style="display:none;">
            <td colspan="8">
              <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="coupon_id" value="<?= $couponId ?>">
                <div class="coupon-grid">
                  <div class="coupon-field">
                    <label>Code</label>
                    <input name="code" value="<?= htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>
                  <div class="coupon-field">
                    <label>Discount Type</label>
                    <select name="discount_type" required>
                      <option value="flat" <?= (string)$coupon['discount_type'] === 'flat' ? 'selected' : '' ?>>Flat</option>
                      <option value="percentage" <?= (string)$coupon['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                    </select>
                  </div>
                  <div class="coupon-field">
                    <label>Discount Value</label>
                    <input name="discount_value" type="number" min="0.01" step="0.01" value="<?= htmlspecialchars((string)$coupon['discount_value'], ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>
                  <div class="coupon-field">
                    <label>Max Discount</label>
                    <input name="max_discount" type="number" min="0" step="0.01" value="<?= htmlspecialchars((string)($coupon['max_discount'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                  <div class="coupon-field">
                    <label>Min Order</label>
                    <input name="min_order_amount" type="number" min="0" step="0.01" value="<?= htmlspecialchars((string)($coupon['min_order_amount'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                  <div class="coupon-field">
                    <label>Global Limit</label>
                    <input name="usage_limit" type="number" min="1" step="1" value="<?= htmlspecialchars((string)($coupon['usage_limit'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                  <div class="coupon-field">
                    <label>Per-user Limit</label>
                    <input name="per_user_usage_limit" type="number" min="1" step="1" value="<?= htmlspecialchars((string)($coupon['per_user_usage_limit'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                  <div class="coupon-field">
                    <label>Start Date</label>
                    <input name="starts_at" type="date" value="<?= htmlspecialchars($coupon['starts_at'] ? substr((string)$coupon['starts_at'], 0, 10) : '', ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                  <div class="coupon-field">
                    <label>End Date (mandatory)</label>
                    <input name="ends_at" type="date" required value="<?= htmlspecialchars($coupon['ends_at'] ? substr((string)$coupon['ends_at'], 0, 10) : '', ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                  <div class="coupon-field">
                    <label>Target Mode</label>
                    <select name="target_mode">
                      <option value="all_users" <?= $targetMode === 'all_users' ? 'selected' : '' ?>>All users</option>
                      <option value="specific_users" <?= $targetMode === 'specific_users' ? 'selected' : '' ?>>Specific users only</option>
                    </select>
                  </div>
                  <div class="coupon-field" style="grid-column: span 2;">
                    <label>Target Users (IDs or emails)</label>
                    <textarea name="target_users"><?= htmlspecialchars((string)($targetMap[$couponId] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                  </div>
                </div>
                <div class="coupon-actions">
                  <button class="coupon-btn" type="submit">Save Edit</button>
                </div>
              </form>
            </td>
          </tr>
          <tr id="coupon-banner-<?= $couponId ?>" class="coupon-banner-row" style="display:none;">
            <td colspan="8">
              <?php
                $bfTitle     = $isTopOfferLinked ? (string)($topOfferLink['title'] ?? '') : 'Use code ' . (string)($coupon['code'] ?? '');
                $bfSubtitle  = $isTopOfferLinked ? (string)($topOfferLink['subtitle'] ?? '') : '';
                $bfCtaLabel  = $isTopOfferLinked ? (string)($topOfferLink['cta_label'] ?? 'Shop Now') : 'Shop Now';
                $bfCtaUrl    = $isTopOfferLinked ? (string)($topOfferLink['cta_url'] ?? '/shop') : '/shop';
                $bfPageScope = $isTopOfferLinked ? (string)($topOfferLink['page_scope'] ?? 'all_pages') : 'all_pages';
                $bfStartsAt  = $isTopOfferLinked && !empty($topOfferLink['starts_at']) ? str_replace(' ', 'T', substr((string)$topOfferLink['starts_at'], 0, 16)) : '';
                $bfEndsAt    = $isTopOfferLinked && !empty($topOfferLink['ends_at']) ? str_replace(' ', 'T', substr((string)$topOfferLink['ends_at'], 0, 16)) : '';
              ?>
              <form method="post" class="coupon-banner-panel">
                <input type="hidden" name="action" value="promote_as_banner">
                <input type="hidden" name="coupon_id" value="<?= $couponId ?>">
                <div class="coupon-banner-panel__head">
                  <strong>Set as Site-wide Top Offer Banner</strong>
                  <span>Coupon: <?= htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if ($isTopOfferLinked): ?><span class="coupon-pill offer-linked">Currently linked</span><?php endif; ?>
                </div>
                <div class="coupon-grid">
                  <div class="coupon-field">
                    <label>Banner Title *</label>
                    <input name="banner_title" type="text" required value="<?= htmlspecialchars($bfTitle, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Limited-time Offer">
                  </div>
                  <div class="coupon-field">
                    <label>Subtitle</label>
                    <input name="banner_subtitle" type="text" value="<?= htmlspecialchars($bfSubtitle, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Use code at checkout">
                  </div>
                  <div class="coupon-field">
                    <label>CTA Label</label>
                    <input name="banner_cta_label" type="text" value="<?= htmlspecialchars($bfCtaLabel, ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                  <div class="coupon-field">
                    <label>CTA URL</label>
                    <input name="banner_cta_url" type="text" value="<?= htmlspecialchars($bfCtaUrl, ENT_QUOTES, 'UTF-8') ?>" placeholder="/shop">
                  </div>
                  <div class="coupon-field">
                    <label>Page Scope</label>
                    <select name="banner_page_scope">
                      <option value="all_pages" <?= $bfPageScope === 'all_pages' ? 'selected' : '' ?>>All public pages</option>
                      <option value="exclude_checkout_auth" <?= $bfPageScope === 'exclude_checkout_auth' ? 'selected' : '' ?>>Exclude checkout + auth</option>
                    </select>
                  </div>

                </div>
                <div class="coupon-actions">
                  <button class="coupon-btn" type="submit">Activate &amp; Save Banner</button>
                  <button class="coupon-btn ghost" type="button" onclick="toggleCouponBanner(<?= $couponId ?>)">Cancel</button>
                </div>
                <p class="coupon-note" style="margin-top:8px;">This replaces any currently live top offer banner and activates immediately.</p>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </section>

  <section class="coupon-card">
    <div class="coupon-card__head">
      <h3>Coupon Reporting</h3>
    </div>
    <div class="coupon-card__body">
      <p class="coupon-note">Coupon redemption analytics are now managed under Reports as the master source of truth.</p>
      <div class="coupon-actions" style="justify-content:flex-start;">
        <a class="coupon-btn" href="coupon_report.php" style="text-decoration:none; display:inline-flex; align-items:center;">Open Coupon Report</a>
      </div>
    </div>
  </section>
</div>

<script>
function toggleCouponEdit(couponId) {
  var row = document.getElementById('coupon-edit-' + couponId);
  if (!row) return;
  row.style.display = row.style.display === 'none' ? '' : 'none';
}

function toggleCouponAudit(couponId) {
  var row = document.getElementById('coupon-audit-' + couponId);
  if (!row) return;
  row.style.display = row.style.display === 'none' ? '' : 'none';
}

function toggleCouponBanner(couponId) {
  var row = document.getElementById('coupon-banner-' + couponId);
  if (!row) return;
  row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>
