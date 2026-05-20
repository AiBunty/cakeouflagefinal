<?php
$pageTitle = 'System Maintenance';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/product-price-helper.php';
include __DIR__ . '/layout.php';

$action = trim((string)($_POST['action'] ?? ''));
$result = null;

if ($action === 'sync_prices') {
    $updated = sync_product_starting_prices($conn);
    $result = array(
        'success' => true,
        'message' => "✓ Price synchronization complete. Updated $updated product(s)."
    );
}

?>

<style>
  .maintenance-wrap {
    max-width: 700px;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.14);
    border-radius: 18px;
    box-shadow: 0 16px 34px rgba(68, 16, 34, 0.1);
    overflow: hidden;
  }

  .maintenance-head {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.12);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
  }

  .maintenance-head h3 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
    font-size: 1.4rem;
  }

  .maintenance-body {
    padding: 24px;
    display: grid;
    gap: 16px;
  }

  .task-box {
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 12px;
    padding: 16px;
    background: #fafaf8;
  }

  .task-box h4 {
    margin: 0 0 8px;
    color: #80001F;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
  }

  .task-box p {
    margin: 0 0 12px;
    font-size: 0.9rem;
    color: #666;
    line-height: 1.5;
  }

  .task-box form {
    margin: 0;
  }

  .btn-primary {
    border: 0;
    border-radius: 10px;
    padding: 10px 18px;
    background: #80001F;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
    font-size: 0.92rem;
  }

  .btn-primary:hover {
    background: #600018;
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
</style>

<div class="maintenance-wrap">
  <div class="maintenance-head">
    <h3>System Maintenance</h3>
  </div>

  <div class="maintenance-body">
    <?php if ($result && $result['success']): ?>
      <div class="notice success">
        <?= htmlspecialchars($result['message']) ?>
      </div>
    <?php endif; ?>

    <div class="task-box">
      <h4>🔧 Synchronize Product Prices</h4>
      <p>
        If products are showing "Starting from Rs 0.00" even though they have variants with prices, 
        this tool will recalculate and update all product starting prices based on their lowest-priced variant.
      </p>
      <form method="POST" action="maintenance.php">
        <input type="hidden" name="action" value="sync_prices">
        <button type="submit" class="btn-primary" onclick="return confirm('This will scan all products and update their starting prices. Continue?')">
          Sync All Product Prices
        </button>
      </form>
    </div>

    <div style="padding: 14px; background: #fff8fa; border-radius: 10px; font-size: 0.85rem; color: #666; border: 1px solid rgba(128, 0, 31, 0.1);">
      <strong>What this does:</strong><br>
      • Finds the minimum variant price for each product<br>
      • Updates the product's starting_price field<br>
      • Fixes display issues on shop and category pages<br>
      • No data is deleted, only prices updated
    </div>
  </div>
</div>
