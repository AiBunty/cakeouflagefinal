<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require __DIR__ . '/save-business-settings.php';
  exit;
}

$pageTitle = 'Business Settings';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';
include __DIR__ . '/layout.php';

$status = trim((string)($_GET['status'] ?? ''));
$message = trim((string)($_GET['message'] ?? ''));

$settingKeys = array(
    'business_name',
    'business_address_line1',
    'business_address_line2',
    'business_city',
    'business_state',
    'business_postal_code',
    'business_phone',
    'business_email',
    'business_gst_number',
    'business_pan_number',
    'upi_apps_script_endpoint_url',
    'upi_apps_script_shared_secret',
    'upi_apps_script_mode',
    'upi_apps_script_sender_allowlist',
    'upi_apps_script_last_test_status',
    'upi_apps_script_last_test_at',
    'upi_apps_script_last_test_message',
    'allow_partial_payment'
);

$settings = array();
foreach ($settingKeys as $key) {
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $settings[$key] = $row ? (string)$row['setting_value'] : '';
}

$adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : 0;
?>

<style>
  .settings-wrap {
    max-width: 900px;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.14);
    border-radius: 18px;
    box-shadow: 0 16px 34px rgba(68, 16, 34, 0.1);
    overflow: hidden;
  }

  .settings-head {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.12);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }

  .settings-head h3 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
    font-size: 1.4rem;
  }

  .settings-head a {
    color: #80001F;
    font-size: 0.86rem;
    text-decoration: none;
  }

  .settings-body {
    padding: 24px;
    display: grid;
    gap: 20px;
  }

  .settings-section {
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 12px;
    padding: 16px;
    background: #fafaf8;
  }

  .settings-section h4 {
    margin: 0 0 14px;
    color: #80001F;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
  }

  .settings-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .settings-field {
    display: grid;
    gap: 6px;
  }

  .settings-field label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #80001F;
    font-weight: 700;
  }

  .settings-field input,
  .settings-field textarea {
    width: 100%;
    border: 1px solid rgba(128, 0, 31, 0.2);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.94rem;
    color: #2e1f25;
    background: #fff;
    font-family: inherit;
  }

  .settings-field textarea {
    min-height: 80px;
    resize: vertical;
  }

  .notice {
    border-radius: 11px;
    padding: 10px 12px;
    font-size: 0.86rem;
    margin-bottom: 16px;
  }

  .notice.success {
    background: #ecfdf3;
    color: #166534;
    border: 1px solid #bbf7d0;
  }

  .notice.error {
    background: #fff1f2;
    color: #9f1239;
    border: 1px solid #fecdd3;
  }

  .settings-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(128, 0, 31, 0.12);
  }

  .btn-settings-primary {
    border: 0;
    border-radius: 10px;
    padding: 10px 18px;
    background: #80001F;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
    font-size: 0.92rem;
  }

  .btn-settings-secondary {
    border: 1px solid rgba(128, 0, 31, 0.24);
    border-radius: 10px;
    padding: 10px 18px;
    background: #fff;
    color: #80001F;
    font-weight: 600;
    text-decoration: none;
    font-size: 0.92rem;
  }

  .preview-box {
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 10px;
    padding: 14px;
    margin-top: 12px;
    font-size: 0.85rem;
    line-height: 1.5;
  }

  .preview-box strong {
    color: #80001F;
  }

  @media (max-width: 760px) {
    .settings-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="settings-wrap">
  <div class="settings-head">
    <h3>Business Settings</h3>
    <a href="dashboard.php">← Back to Dashboard</a>
  </div>

  <form action="business-settings.php" method="post" class="settings-body">
    <?php if ($status === 'success'): ?>
      <div class="notice success">
        ✓ Business settings updated successfully.
      </div>
    <?php elseif ($status === 'error'): ?>
      <div class="notice error">
        ✗ <?= htmlspecialchars($message !== '' ? $message : 'Unable to update business settings.') ?>
      </div>
    <?php endif; ?>

    <!-- Business Identity Section -->
    <div class="settings-section">
      <h4>Business Identity</h4>
      <div class="settings-grid">
        <div class="settings-field" style="grid-column: 1/-1">
          <label for="business_name">Business Name</label>
          <input type="text" id="business_name" name="business_name" required maxlength="180" value="<?= htmlspecialchars($settings['business_name']) ?>">
        </div>
        <div class="settings-field">
          <label for="business_phone">Business Phone</label>
          <input type="text" id="business_phone" name="business_phone" maxlength="25" placeholder="+91 XXXXX XXXXX" value="<?= htmlspecialchars($settings['business_phone']) ?>">
        </div>
        <div class="settings-field">
          <label for="business_email">Business Email</label>
          <input type="email" id="business_email" name="business_email" maxlength="190" placeholder="business@example.com" value="<?= htmlspecialchars($settings['business_email']) ?>">
        </div>
      </div>
    </div>

    <!-- Address Section -->
    <div class="settings-section">
      <h4>Business Address</h4>
      <div class="settings-grid">
        <div class="settings-field" style="grid-column: 1/-1">
          <label for="business_address_line1">Address Line 1</label>
          <input type="text" id="business_address_line1" name="business_address_line1" maxlength="190" placeholder="House / Building / Street" value="<?= htmlspecialchars($settings['business_address_line1']) ?>">
        </div>
        <div class="settings-field" style="grid-column: 1/-1">
          <label for="business_address_line2">Address Line 2</label>
          <input type="text" id="business_address_line2" name="business_address_line2" maxlength="190" placeholder="Area / Landmark / District" value="<?= htmlspecialchars($settings['business_address_line2']) ?>">
        </div>
        <div class="settings-field">
          <label for="business_city">City</label>
          <input type="text" id="business_city" name="business_city" maxlength="100" placeholder="Nashik" value="<?= htmlspecialchars($settings['business_city']) ?>">
        </div>
        <div class="settings-field">
          <label for="business_state">State</label>
          <input type="text" id="business_state" name="business_state" maxlength="100" placeholder="Maharashtra" value="<?= htmlspecialchars($settings['business_state']) ?>">
        </div>
        <div class="settings-field">
          <label for="business_postal_code">Postal Code</label>
          <input type="text" id="business_postal_code" name="business_postal_code" maxlength="15" placeholder="422001" value="<?= htmlspecialchars($settings['business_postal_code']) ?>">
        </div>
      </div>
    </div>

    <!-- Tax & Compliance Section -->
    <div class="settings-section">
      <h4>Tax & Compliance</h4>
      <div class="settings-grid">
        <div class="settings-field">
          <label for="business_gst_number">GST Number</label>
          <input type="text" id="business_gst_number" name="business_gst_number" maxlength="40" placeholder="27XXXXX1Z5" value="<?= htmlspecialchars($settings['business_gst_number']) ?>">
        </div>
        <div class="settings-field">
          <label for="business_pan_number">PAN Number</label>
          <input type="text" id="business_pan_number" name="business_pan_number" maxlength="40" placeholder="XXXXX0000X" value="<?= htmlspecialchars($settings['business_pan_number']) ?>">
        </div>
      </div>
    </div>

    <!-- Apps Script Integration Section -->
    <div class="settings-section">
      <h4>UPI Bank Alert Integration (Apps Script)</h4>
      <div class="settings-grid">
        <div class="settings-field" style="grid-column: 1/-1">
          <label for="upi_apps_script_endpoint_url">Apps Script Web App Endpoint URL</label>
          <input
            type="url"
            id="upi_apps_script_endpoint_url"
            name="upi_apps_script_endpoint_url"
            maxlength="500"
            placeholder="https://script.google.com/macros/s/.../exec"
            value="<?= htmlspecialchars($settings['upi_apps_script_endpoint_url']) ?>"
          >
        </div>
        <div class="settings-field">
          <label for="upi_apps_script_mode">Integration Mode</label>
          <?php $integrationMode = in_array($settings['upi_apps_script_mode'], ['disabled', 'test', 'live'], true) ? $settings['upi_apps_script_mode'] : 'disabled'; ?>
          <select id="upi_apps_script_mode" name="upi_apps_script_mode">
            <option value="disabled" <?= $integrationMode === 'disabled' ? 'selected' : '' ?>>Disabled</option>
            <option value="test" <?= $integrationMode === 'test' ? 'selected' : '' ?>>Test (dry run)</option>
            <option value="live" <?= $integrationMode === 'live' ? 'selected' : '' ?>>Live</option>
          </select>
        </div>
        <div class="settings-field">
          <label for="upi_apps_script_shared_secret">Apps Script Shared Secret</label>
          <input
            type="password"
            id="upi_apps_script_shared_secret"
            name="upi_apps_script_shared_secret"
            maxlength="255"
            placeholder="<?= $settings['upi_apps_script_shared_secret'] !== '' ? 'Configured. Enter new secret only to replace.' : 'Enter shared secret used by Apps Script.' ?>"
            value=""
            autocomplete="new-password"
          >
        </div>
        <div class="settings-field" style="grid-column: 1/-1">
          <label for="upi_apps_script_sender_allowlist">Sender Allowlist (comma-separated)</label>
          <textarea
            id="upi_apps_script_sender_allowlist"
            name="upi_apps_script_sender_allowlist"
            placeholder="alerts@hdfcbank.com,alerts@icicibank.com"
          ><?= htmlspecialchars($settings['upi_apps_script_sender_allowlist']) ?></textarea>
        </div>
      </div>
      <div class="preview-box" style="margin-top:14px;">
        <div><strong>Connection Status:</strong> <?= htmlspecialchars($settings['upi_apps_script_last_test_status'] !== '' ? strtoupper($settings['upi_apps_script_last_test_status']) : 'NOT_TESTED') ?></div>
        <?php if ($settings['upi_apps_script_last_test_at'] !== ''): ?>
          <div><strong>Last Test At:</strong> <?= htmlspecialchars($settings['upi_apps_script_last_test_at']) ?></div>
        <?php endif; ?>
        <?php if ($settings['upi_apps_script_last_test_message'] !== ''): ?>
          <div><strong>Last Test Message:</strong> <?= htmlspecialchars($settings['upi_apps_script_last_test_message']) ?></div>
        <?php endif; ?>
        <div style="margin-top:8px; color:#5a2b37; font-size:0.82rem;">
          Use <strong>Test Connection</strong> first. Set mode to <strong>Live</strong> only after a successful test.
        </div>
      </div>
    </div>

    <!-- Payment Settings -->
    <div class="settings-section">
      <h4>Payment Settings</h4>
      <div class="settings-grid">
        <div class="settings-field">
          <label for="allow_partial_payment">Allow Partial Payment (50% Advance)</label>
          <select id="allow_partial_payment" name="allow_partial_payment">
            <option value="1" <?= ($settings['allow_partial_payment'] ?? '1') !== '0' ? 'selected' : '' ?>>Enabled — customers can pay 50% advance at checkout</option>
            <option value="0" <?= ($settings['allow_partial_payment'] ?? '1') === '0' ? 'selected' : '' ?>>Disabled — full payment required at checkout</option>
          </select>
          <p class="settings-hint">When disabled, the 50% advance option is hidden from the checkout page. BYOC quote orders are unaffected.</p>
        </div>
      </div>
    </div>

    <!-- Preview -->
    <div class="settings-section">
      <h4>Live Preview (Footer)</h4>
      <div class="preview-box">
        <div><strong><?= htmlspecialchars($settings['business_name'] ?: 'Business Name') ?></strong></div>
        <div>
          <?php 
            $addressParts = array_filter([
              $settings['business_address_line1'],
              $settings['business_address_line2'],
              $settings['business_city'] . ($settings['business_state'] ? ', ' . $settings['business_state'] : ''),
              $settings['business_postal_code'] ? 'PIN: ' . $settings['business_postal_code'] : ''
            ]);
            echo htmlspecialchars(implode(' | ', $addressParts) ?: 'Address not configured');
          ?>
        </div>
        <div><?= htmlspecialchars($settings['business_phone'] ?: 'Phone') ?> | <?= htmlspecialchars($settings['business_email'] ?: 'Email') ?></div>
        <?php if ($settings['business_gst_number']): ?>
          <div>GST: <?= htmlspecialchars($settings['business_gst_number']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="settings-actions">
      <a href="dashboard.php" class="btn-settings-secondary">Cancel</a>
      <button type="submit" class="btn-settings-secondary" name="settings_action" value="test_apps_script">Test Connection</button>
      <button type="submit" class="btn-settings-primary">Save Business Settings</button>
    </div>
  </form>
</div>
