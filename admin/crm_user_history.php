<?php
$pageTitle = 'CRM Customer History';
require_once __DIR__ . '/includes/auth.php';
require_admin_permission('crm_report');
require_once __DIR__ . '/includes/crm_report_helpers.php';

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$q = trim((string)($_GET['q'] ?? ''));
$user = $userId > 0 ? fetch_crm_report_user($conn, $userId) : null;
$orders = $userId > 0 ? fetch_user_orders($conn, $userId) : [];

require_once __DIR__ . '/layout.php';
?>
<style>
  .history-shell {
    display: grid;
    gap: 22px;
  }

  .history-card {
    background: var(--admin-surface, #fffdfd);
    border-radius: 18px;
    border: 1px solid rgba(128, 0, 31, 0.1);
    box-shadow: 0 14px 30px rgba(96, 18, 45, 0.08);
    overflow: hidden;
  }

  .history-card__head {
    padding: 18px 20px 12px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
  }

  .history-card__head h2,
  .history-card__head h3 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
  }

  .history-card__head p {
    margin: 6px 0 0;
    color: #8f7681;
    font-size: 0.92rem;
  }

  .history-card__body {
    padding: 20px;
  }

  .history-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }

  .history-stat {
    border: 1px solid rgba(128, 0, 31, 0.08);
    border-radius: 14px;
    background: #fff8fa;
    padding: 14px;
  }

  .history-stat strong {
    display: block;
    color: #80001F;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 6px;
  }

  .history-stat span {
    color: #2d1f25;
    font-size: 1.05rem;
    font-weight: 600;
  }

  .history-table-wrap {
    overflow: auto;
  }

  .history-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 860px;
  }

  .history-table th,
  .history-table td {
    padding: 12px 10px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    text-align: left;
    vertical-align: top;
  }

  .history-table th {
    background: #fff6f8;
    color: #80001F;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.76rem;
  }

  .history-pill {
    display: inline-flex;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .history-pill--completed { background: #dcfce7; color: #166534; }
  .history-pill--pending { background: #fff2cf; color: #9a5b00; }
  .history-pill--cancelled { background: #fee2e2; color: #991b1b; }

  .history-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
  }

  .history-btn,
  .history-btn:link,
  .history-btn:visited {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 14px;
    border-radius: 12px;
    background: #80001F;
    color: #fff;
    text-decoration: none;
    border: none;
    font-weight: 600;
    cursor: pointer;
  }

  .history-btn--ghost {
    background: #f8d8de;
    color: #80001F;
  }

  @media (max-width: 1080px) {
    .history-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 760px) {
    .history-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="history-shell">
  <section class="history-card">
    <div class="history-card__head">
      <h2>Customer Order History</h2>
      <p>All orders placed by this user are shown here.</p>
    </div>
    <div class="history-card__body">
      <?php if (!$user): ?>
        <p>No customer found for this history view.</p>
        <div class="history-actions">
          <a class="history-btn history-btn--ghost" href="crm_report.php?q=<?= urlencode($q) ?>">Back to CRM Report</a>
        </div>
      <?php else: ?>
        <div class="history-grid">
          <div class="history-stat"><strong>Name</strong><span><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="history-stat"><strong>Email</strong><span><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="history-stat"><strong>Phone</strong><span><?= htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="history-stat"><strong>Total Orders</strong><span><?= count($orders) ?></span></div>
        </div>

        <div class="history-actions">
          <a class="history-btn history-btn--ghost" href="crm_report.php?q=<?= urlencode($q) ?>">Back to CRM Report</a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($user): ?>
    <section class="history-card">
      <div class="history-card__head">
        <h3>All Orders</h3>
        <p>Most recent orders first. Open the order details page for the full admin workflow.</p>
      </div>
      <div class="history-card__body">
        <div class="history-table-wrap">
          <table class="history-table">
            <tr>
              <th>Order #</th><th>Items</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr>
            <?php if (!$orders): ?>
              <tr><td colspan="6">No orders found for this customer.</td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $order): ?>
              <?php
                $status = (string) ($order['order_status'] ?? '');
                $statusClass = 'history-pill--pending';
                if ($status === 'completed') {
                    $statusClass = 'history-pill--completed';
                } elseif ($status === 'cancelled') {
                    $statusClass = 'history-pill--cancelled';
                }
              ?>
              <tr>
                <td><?= htmlspecialchars((string) ($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($order['item_names'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>₹<?= htmlspecialchars((string) ($order['grand_total'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="history-pill <?= $statusClass ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><?= htmlspecialchars((string) ($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><a class="history-btn history-btn--ghost" href="order_details.php?id=<?= (int) ($order['id'] ?? 0) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
    </section>
  <?php endif; ?>
</div>
