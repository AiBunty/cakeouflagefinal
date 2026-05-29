<?php
$pageTitle = 'Customer Ledger';
require_once __DIR__ . '/layout.php';
require_admin_permission('revenue_report');
require_once __DIR__ . '/includes/db.php';

use App\Services\CustomerLedgerService;

$svc = new CustomerLedgerService();

$identifier = trim((string)($_GET['q'] ?? ''));
$by         = in_array(trim((string)($_GET['by'] ?? 'phone')), ['phone', 'email', 'id'], true)
                ? trim((string)($_GET['by'] ?? 'phone'))
                : 'phone';

$statement = null;
if ($identifier !== '') {
    $statement = $svc->getStatement($identifier, $by);
}
?>
<div class="page-content">
  <div class="page-header">
    <h1 class="page-title">Customer Ledger</h1>
  </div>

  <!-- Search form -->
  <form method="get" class="card mb-4" style="padding:1rem">
    <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label style="font-size:.8rem;font-weight:600">Search by</label>
        <select name="by" style="display:block;padding:.4rem .6rem;border:1px solid #ccc;border-radius:5px">
          <option value="phone" <?= $by === 'phone' ? 'selected' : '' ?>>Phone</option>
          <option value="email" <?= $by === 'email' ? 'selected' : '' ?>>Email</option>
          <option value="id"    <?= $by === 'id'    ? 'selected' : '' ?>>Order ID</option>
        </select>
      </div>
      <div style="flex:1;min-width:220px">
        <label style="font-size:.8rem;font-weight:600">Value</label>
        <input type="text" name="q" value="<?= htmlspecialchars($identifier) ?>"
               placeholder="Enter phone / email / order ID"
               style="display:block;width:100%;padding:.4rem .6rem;border:1px solid #ccc;border-radius:5px">
      </div>
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
  </form>

  <?php if ($identifier !== '' && ($statement === null || empty($statement['orders']))): ?>
    <div class="card" style="padding:1.5rem;text-align:center;color:#888">
      No records found for <strong><?= htmlspecialchars($identifier) ?></strong>.
    </div>
  <?php endif; ?>

  <?php if ($statement && !empty($statement['orders'])): ?>
  <?php $customer = $statement['customer'] ?? []; $summary = $statement['summary'] ?? []; ?>

  <!-- Customer card -->
  <div class="card mb-3">
    <div class="card-body" style="display:flex;gap:2rem;flex-wrap:wrap">
      <div><strong style="font-size:.8rem;color:#888">NAME</strong><br><?= htmlspecialchars((string)($customer['name'] ?? '—')) ?></div>
      <div><strong style="font-size:.8rem;color:#888">PHONE</strong><br><?= htmlspecialchars((string)($customer['phone'] ?? '—')) ?></div>
      <div><strong style="font-size:.8rem;color:#888">EMAIL</strong><br><?= htmlspecialchars((string)($customer['email'] ?? '—')) ?></div>
      <div><strong style="font-size:.8rem;color:#888">TOTAL ORDERS</strong><br><?= (int)($summary['total_orders'] ?? 0) ?></div>
      <div><strong style="font-size:.8rem;color:#888">TOTAL BILLED</strong><br>₹<?= number_format((float)($summary['total_billed'] ?? 0), 2) ?></div>
      <div><strong style="font-size:.8rem;color:#888">TOTAL PAID (GL)</strong><br>₹<?= number_format((float)($summary['total_paid'] ?? 0), 2) ?></div>
      <div>
        <strong style="font-size:.8rem;color:#888">OUTSTANDING</strong><br>
        <span style="font-weight:700;color:<?= (float)($summary['outstanding'] ?? 0) > 0 ? '#c0392b' : '#27ae60' ?>">
          ₹<?= number_format((float)($summary['outstanding'] ?? 0), 2) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Timeline -->
  <div class="card">
    <div class="card-header"><strong>Account Timeline</strong></div>
    <div class="card-body" style="padding:.75rem 1rem">
      <?php $events = $statement['events'] ?? []; ?>
      <?php if (empty($events)): ?>
        <p style="color:#aaa">No GL events found.</p>
      <?php else: ?>
      <div style="position:relative;padding-left:2rem">
        <div style="position:absolute;left:.55rem;top:0;bottom:0;width:2px;background:#e8d0d5"></div>
        <?php foreach ($events as $ev): ?>
        <?php
          $dir = $ev['direction'] ?? 'neutral';
          $dotColor = $dir === 'credit' ? '#27ae60' : ($dir === 'debit' ? '#c0392b' : '#95a5a6');
          $amount = isset($ev['amount']) ? '₹' . number_format((float)$ev['amount'], 2) : '';
        ?>
        <div style="position:relative;margin-bottom:1.25rem">
          <div style="position:absolute;left:-1.7rem;top:.25rem;width:.8rem;height:.8rem;border-radius:50%;background:<?= $dotColor ?>;border:2px solid #fff;box-shadow:0 0 0 2px <?= $dotColor ?>22"></div>
          <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.25rem">
            <div>
              <span style="font-weight:600;font-size:.9rem"><?= htmlspecialchars((string)($ev['type'] ?? '')) ?></span>
              <?php if (isset($ev['order_number'])): ?>
                <span style="font-size:.8rem;color:#888;margin-left:.4rem">
                  Order #<?= htmlspecialchars((string)$ev['order_number']) ?>
                </span>
              <?php endif; ?>
              <?php if (isset($ev['narration']) && $ev['narration']): ?>
                <div style="font-size:.8rem;color:#777;margin-top:.15rem"><?= htmlspecialchars((string)$ev['narration']) ?></div>
              <?php endif; ?>
            </div>
            <div style="text-align:right">
              <?php if ($amount): ?>
              <span style="font-weight:700;color:<?= $dotColor ?>;font-size:.95rem"><?= $amount ?></span>
              <?php endif; ?>
              <div style="font-size:.75rem;color:#aaa"><?= htmlspecialchars((string)($ev['event_date'] ?? '')) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Orders table -->
  <div class="card mt-3">
    <div class="card-header"><strong>Orders</strong></div>
    <div class="card-body" style="padding:0;overflow-x:auto">
      <table class="admin-table" style="width:100%">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Status</th>
            <th>Original Total</th>
            <th>Effective Total</th>
            <th>Payment</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($statement['orders'] as $order): ?>
          <tr>
            <td><strong><?= htmlspecialchars((string)$order['order_number']) ?></strong></td>
            <td style="font-size:.85rem"><?= htmlspecialchars((string)$order['created_at']) ?></td>
            <td><span class="badge"><?= htmlspecialchars((string)$order['order_status']) ?></span></td>
            <td>₹<?= number_format((float)$order['grand_total'], 2) ?></td>
            <td>₹<?= number_format((float)($order['revised_grand_total'] ?? $order['grand_total']), 2) ?></td>
            <td><span class="badge"><?= htmlspecialchars((string)$order['payment_status']) ?></span></td>
            <td>
              <a href="order_details.php?order_id=<?= (int)$order['id'] ?>" class="btn btn-xs btn-secondary">View</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
