<?php
$pageTitle = 'Payment Verification Queue';
require_once __DIR__ . '/layout.php';

$statusFilter = trim((string)($_GET['status'] ?? 'open'));
if (!in_array($statusFilter, ['open', 'pending', 'matched_auto', 'confirmed', 'rejected', 'duplicate', 'ignored'], true)) {
    $statusFilter = 'open';
}
?>

<style>
.queue-shell { background:#fff; border:1px solid rgba(128,0,31,.12); border-radius:18px; box-shadow:0 14px 28px rgba(68,16,34,.08); overflow:hidden; }
.queue-head { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:16px 18px; border-bottom:1px solid rgba(128,0,31,.09); background:linear-gradient(180deg,#fff8fa 0%,#fff 100%); flex-wrap:wrap; }
.queue-title { margin:0; font-family:'DM Serif Display',Georgia,serif; font-weight:400; color:#80001F; font-size:1.4rem; }
.queue-meta { color:#7f6973; font-size:.85rem; margin-top:4px; }
.queue-filter { display:flex; gap:8px; align-items:center; }
.queue-filter select { border:1px solid rgba(128,0,31,.2); border-radius:10px; padding:8px 10px; }
.queue-list { padding:10px 18px 18px; display:grid; gap:12px; }
.queue-card { border:1px solid rgba(128,0,31,.12); border-radius:14px; padding:14px; background:#fff; }
.queue-card__top { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap; }
.queue-utr { font-weight:700; color:#80001F; letter-spacing:.03em; font-size:.95rem; }
.queue-order { font-size:.84rem; color:#5a3f49; margin-top:4px; }
.queue-status { display:inline-block; border-radius:999px; padding:4px 10px; font-size:.72rem; font-weight:700; text-transform:uppercase; }
.queue-status--pending { background:#fff2cf; color:#9a5b00; }
.queue-status--matched_auto { background:#dcfce7; color:#166534; }
.queue-status--confirmed { background:#dbeafe; color:#1e3a8a; }
.queue-status--rejected { background:#fee2e2; color:#991b1b; }
.queue-status--duplicate { background:#fef3c7; color:#92400e; }
.queue-status--ignored { background:#e5e7eb; color:#374151; }
.queue-info { margin-top:10px; display:grid; gap:5px; font-size:.82rem; color:#4b343d; }
.queue-actions { margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
.btn { background:#80001F; color:#fff; padding:8px 12px; border:0; border-radius:10px; cursor:pointer; font-size:.78rem; font-weight:600; }
.btn:hover { background:#5f0017; }
.btn-secondary { background:#2563eb; }
.btn-secondary:hover { background:#1d4ed8; }
.btn-danger { background:#dc2626; }
.btn-danger:hover { background:#b91c1c; }
.queue-empty { padding:24px 18px; color:#7f6973; font-size:.88rem; }
.queue-note { margin-top:8px; color:#7f6973; font-size:.8rem; }
</style>

<div class="queue-shell">
  <div class="queue-head">
    <div>
      <h2 class="queue-title">Payment Verification Queue</h2>
      <div class="queue-meta">Bank alerts + customer UTR submissions waiting for confirmation.</div>
    </div>
    <form class="queue-filter" method="get">
      <label for="status">Filter</label>
      <select id="status" name="status" onchange="this.form.submit()">
        <?php foreach (['open' => 'Open', 'pending' => 'Pending', 'matched_auto' => 'Matched Auto', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'duplicate' => 'Duplicate', 'ignored' => 'Ignored'] as $value => $label): ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div id="queueList" class="queue-list"></div>
  <div id="queueEmpty" class="queue-empty" style="display:none">No alerts found for this filter.</div>
</div>

<script>
(function () {
  const filter = <?= json_encode($statusFilter, JSON_UNESCAPED_SLASHES) ?>;
  const list = document.getElementById('queueList');
  const empty = document.getElementById('queueEmpty');

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function statusClass(status) {
    const normalized = String(status || 'pending').toLowerCase();
    if (['pending', 'matched_auto', 'confirmed', 'rejected', 'duplicate', 'ignored'].indexOf(normalized) === -1) {
      return 'pending';
    }
    return normalized;
  }

  function render(items) {
    if (!Array.isArray(items) || items.length === 0) {
      list.innerHTML = '';
      empty.style.display = 'block';
      return;
    }

    empty.style.display = 'none';
    list.innerHTML = items.map(function (item) {
      const status = statusClass(item.status);
      const amount = item.parsed_amount !== null && item.parsed_amount !== undefined
        ? 'Rs ' + Number(item.parsed_amount).toFixed(2)
        : 'N/A';
      const orderAmount = item.order_amount !== null && item.order_amount !== undefined
        ? 'Rs ' + Number(item.order_amount).toFixed(2)
        : 'N/A';
      const canAction = status === 'pending' || status === 'matched_auto';

      return '' +
        '<article class="queue-card">' +
          '<div class="queue-card__top">' +
            '<div>' +
              '<div class="queue-utr">UTR: ' + escapeHtml(item.parsed_utr) + '</div>' +
              '<div class="queue-order">Order: ' + escapeHtml(item.order_number || '-') + ' | Customer: ' + escapeHtml(item.customer_name || '-') + '</div>' +
            '</div>' +
            '<span class="queue-status queue-status--' + status + '">' + escapeHtml(status.replace('_', ' ')) + '</span>' +
          '</div>' +
          '<div class="queue-info">' +
            '<div><strong>Alert Amount:</strong> ' + escapeHtml(amount) + ' | <strong>Order Amount:</strong> ' + escapeHtml(orderAmount) + '</div>' +
            '<div><strong>Sender:</strong> ' + escapeHtml(item.bank_sender || '-') + '</div>' +
            '<div><strong>Subject:</strong> ' + escapeHtml(item.email_subject || '-') + '</div>' +
            '<div><strong>Confidence:</strong> ' + escapeHtml(item.match_confidence || 'none') + '</div>' +
            '<div><strong>Received:</strong> ' + escapeHtml(item.created_at || '-') + '</div>' +
            (item.confirm_note ? '<div><strong>Note:</strong> ' + escapeHtml(item.confirm_note) + '</div>' : '') +
          '</div>' +
          (canAction ?
            '<div class="queue-actions">' +
              '<button class="btn btn-secondary" data-action="confirm" data-id="' + Number(item.id) + '">Confirm Payment</button>' +
              '<button class="btn btn-danger" data-action="reject" data-id="' + Number(item.id) + '">Reject</button>' +
            '</div>'
            : '<div class="queue-note">Reviewed by: ' + escapeHtml(item.confirmed_by || '-') + '</div>') +
        '</article>';
    }).join('');
  }

  async function loadQueue() {
    const response = await fetch('/api/admin/bank-alerts?status=' + encodeURIComponent(filter), {
      credentials: 'include'
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Failed to load queue');
    }
    render(payload.data && payload.data.items ? payload.data.items : []);
  }

  async function takeAction(id, action) {
    const note = window.prompt(action === 'confirm' ? 'Optional confirmation note:' : 'Optional rejection reason:') || '';
    const response = await fetch('/api/admin/bank-alerts/' + encodeURIComponent(id) + '/' + action, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ note: note })
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Action failed');
    }
  }

  list.addEventListener('click', async function (event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const action = target.getAttribute('data-action');
    const id = Number(target.getAttribute('data-id') || 0);
    if (!action || !id) {
      return;
    }

    try {
      target.setAttribute('disabled', 'disabled');
      await takeAction(id, action);
      await loadQueue();
    } catch (error) {
      window.alert(error.message || 'Failed to update alert');
    } finally {
      target.removeAttribute('disabled');
    }
  });

  loadQueue().catch(function (error) {
    list.innerHTML = '<article class="queue-card">Unable to load queue: ' + escapeHtml(error.message || 'Unknown error') + '</article>';
  });
})();
</script>
