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

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'create') {
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $discountType = trim((string)($_POST['discount_type'] ?? 'flat'));
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $maxDiscount = trim((string)($_POST['max_discount'] ?? ''));
        $minOrderAmount = trim((string)($_POST['min_order_amount'] ?? ''));
        $usageLimit = trim((string)($_POST['usage_limit'] ?? ''));
        $startsAt = trim((string)($_POST['starts_at'] ?? ''));
        $endsAt = trim((string)($_POST['ends_at'] ?? ''));

        if ($code === '') {
            $code = coupon_generate_code(7);
        }

        if (!in_array($discountType, array('flat', 'percentage'), true) || $discountValue <= 0) {
            $messageType = 'error';
            $message = 'Provide valid coupon discount details.';
        } else {
            $maxDiscountValue = $maxDiscount !== '' ? (float)$maxDiscount : null;
            $minOrderValue = $minOrderAmount !== '' ? (float)$minOrderAmount : null;
            $usageLimitValue = $usageLimit !== '' ? (int)$usageLimit : null;
            $startsAtValue = $startsAt !== '' ? $startsAt . ' 00:00:00' : null;
            $endsAtValue = $endsAt !== '' ? $endsAt . ' 23:59:59' : null;

            $insert = $conn->prepare('INSERT INTO coupons (code, discount_type, discount_value, max_discount, min_order_amount, usage_limit, starts_at, ends_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
            if ($insert) {
                $insert->bind_param('ssdddiss', $code, $discountType, $discountValue, $maxDiscountValue, $minOrderValue, $usageLimitValue, $startsAtValue, $endsAtValue);
                if ($insert->execute()) {
                    $message = 'Coupon created successfully: ' . $code;
                } else {
                    $messageType = 'error';
                    $message = 'Coupon code already exists or invalid data.';
                }
            }
        }
    }

    if ($action === 'toggle') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        $nextState = (int)($_POST['next_state'] ?? 0) === 1 ? 1 : 0;
        if ($couponId > 0) {
            $update = $conn->prepare('UPDATE coupons SET is_active = ? WHERE id = ? LIMIT 1');
            $update->bind_param('ii', $nextState, $couponId);
            $update->execute();
            $message = $nextState === 1 ? 'Coupon activated.' : 'Coupon paused.';
        }
    }
}

$coupons = array();
$result = $conn->query('SELECT id, code, discount_type, discount_value, max_discount, min_order_amount, usage_limit, usage_count, starts_at, ends_at, is_active, created_at FROM coupons ORDER BY id DESC');
while ($result && ($row = $result->fetch_assoc())) {
    $coupons[] = $row;
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
  .coupon-field input, .coupon-field select { border: 1px solid rgba(128,0,31,0.2); border-radius: 10px; padding: 9px 11px; font: inherit; }
  .coupon-actions { margin-top: 10px; display: flex; justify-content: flex-end; gap: 8px; }
  .coupon-btn { border: 0; border-radius: 10px; padding: 9px 14px; background: #80001F; color: #fff; font-weight: 700; cursor: pointer; }
  .coupon-btn.ghost { background: #fff; border: 1px solid rgba(128,0,31,0.2); color: #80001F; }
  .coupon-message { border-radius: 10px; padding: 10px 12px; font-size: 0.86rem; }
  .coupon-message.success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
  .coupon-message.error { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
  .coupon-table-wrap { overflow: auto; }
  .coupon-table { width: 100%; border-collapse: collapse; min-width: 980px; }
  .coupon-table th, .coupon-table td { padding: 11px 10px; border-bottom: 1px solid rgba(128,0,31,0.08); text-align: left; }
  .coupon-table th { font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: #80001F; background: #fff6f8; }
  .coupon-pill { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
  .coupon-pill.active { background: #dcfce7; color: #166534; }
  .coupon-pill.paused { background: #fff2cf; color: #9a5b00; }
  @media (max-width: 1020px) { .coupon-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } }
  @media (max-width: 760px) { .coupon-grid { grid-template-columns: 1fr; } }
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
          <select id="discount_type" name="discount_type">
            <option value="flat">Flat (INR)</option>
            <option value="percentage">Percentage (%)</option>
          </select>
        </div>
        <div class="coupon-field">
          <label for="discount_value">Discount Value</label>
          <input id="discount_value" name="discount_value" type="number" min="0.01" step="0.01" required>
        </div>
        <div class="coupon-field">
          <label for="max_discount">Max Discount</label>
          <input id="max_discount" name="max_discount" type="number" min="0" step="0.01" placeholder="optional">
        </div>
        <div class="coupon-field">
          <label for="min_order_amount">Min Order Amount</label>
          <input id="min_order_amount" name="min_order_amount" type="number" min="0" step="0.01" placeholder="optional">
        </div>
        <div class="coupon-field">
          <label for="usage_limit">Usage Limit</label>
          <input id="usage_limit" name="usage_limit" type="number" min="1" step="1" placeholder="optional">
        </div>
        <div class="coupon-field">
          <label for="starts_at">Start Date</label>
          <input id="starts_at" name="starts_at" type="date">
        </div>
        <div class="coupon-field">
          <label for="ends_at">End Date</label>
          <input id="ends_at" name="ends_at" type="date">
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
      <table class="coupon-table">
        <tr>
          <th>Code</th>
          <th>Discount</th>
          <th>Rules</th>
          <th>Usage</th>
          <th>Window</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
        <?php if (!$coupons): ?>
          <tr><td colspan="7">No coupons found.</td></tr>
        <?php endif; ?>
        <?php foreach ($coupons as $coupon): ?>
          <?php
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
              $ruleText .= ' | Limit ' . (int)$coupon['usage_limit'];
            }
          ?>
          <tr>
            <td><strong><?= htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8') ?></strong></td>
            <td><?= htmlspecialchars($discountText, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($ruleText, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$coupon['usage_count'] ?><?= $coupon['usage_limit'] !== null ? ' / ' . (int)$coupon['usage_limit'] : '' ?></td>
            <td><?= htmlspecialchars((string)($coupon['starts_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars((string)($coupon['ends_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="coupon-pill <?= (int)$coupon['is_active'] === 1 ? 'active' : 'paused' ?>"><?= (int)$coupon['is_active'] === 1 ? 'Active' : 'Paused' ?></span></td>
            <td>
              <form method="post" style="margin:0">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="coupon_id" value="<?= (int)$coupon['id'] ?>">
                <input type="hidden" name="next_state" value="<?= (int)$coupon['is_active'] === 1 ? '0' : '1' ?>">
                <button class="coupon-btn ghost" type="submit"><?= (int)$coupon['is_active'] === 1 ? 'Pause' : 'Activate' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </section>
</div>
