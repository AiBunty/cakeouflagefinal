function ordersToggleDetails(orderId) {
  var row = document.getElementById('order-row-' + orderId);
  if (!row) return;
  row.classList.toggle('open');
}

function ordersToggleMore(trigger) {
  var wrapper = trigger.closest('.order-more');
  if (!wrapper) return;
  var wasOpen = wrapper.classList.contains('open');
  document.querySelectorAll('.order-more.open').forEach(function(el) { el.classList.remove('open'); });
  if (!wasOpen) wrapper.classList.add('open');
}

function ordersCloseMenusOnOutsideClick(event) {
  var target = event.target;
  if (!target.closest('.order-more')) {
    document.querySelectorAll('.order-more.open').forEach(function(el) { el.classList.remove('open'); });
  }
}

function ordersToggleAdvancedFilters() {
  var panel = document.getElementById('ordersAdvancedFilters');
  var btn = document.getElementById('ordersFilterToggleBtn');
  if (!panel) return;
  var next = !panel.classList.contains('open');
  panel.classList.toggle('open', next);
  if (btn) btn.textContent = next ? 'Hide Filters' : 'Filters';
}

function ordersEnsureUxLayer() {
  if (document.getElementById('orders-ux-style')) {
    return;
  }

  var style = document.createElement('style');
  style.id = 'orders-ux-style';
  style.textContent = '' +
    '.orders-toast-stack{position:fixed;top:16px;right:16px;z-index:2147483000;display:flex;flex-direction:column;gap:8px;max-width:min(420px,calc(100vw - 24px));}' +
    '.orders-toast{border-radius:10px;padding:11px 14px;font-size:13px;font-weight:600;line-height:1.35;box-shadow:0 10px 24px rgba(0,0,0,0.16);border:1px solid transparent;opacity:0;transform:translateY(-6px);transition:all .2s ease;}' +
    '.orders-toast.show{opacity:1;transform:translateY(0);}' +
    '.orders-toast-info{background:#f3f4f6;color:#111827;border-color:#e5e7eb;}' +
    '.orders-toast-success{background:#ecfdf5;color:#166534;border-color:#bbf7d0;}' +
    '.orders-toast-error{background:#fef2f2;color:#991b1b;border-color:#fecaca;}' +
    '.orders-action-backdrop{position:fixed;inset:0;background:rgba(17,24,39,.45);z-index:2147482000;display:flex;align-items:center;justify-content:center;padding:18px;}' +
    '.orders-action-modal{width:min(560px,100%);background:#fff;border-radius:14px;box-shadow:0 24px 48px rgba(0,0,0,.24);overflow:hidden;border:1px solid #e5e7eb;}' +
    '.orders-action-head{padding:14px 16px;background:#fff7ed;border-bottom:1px solid #fed7aa;color:#9a3412;font-weight:700;}' +
    '.orders-action-body{padding:14px 16px;display:grid;gap:10px;}' +
    '.orders-action-label{font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;}' +
    '.orders-action-input,.orders-action-textarea{width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;}' +
    '.orders-action-textarea{min-height:74px;resize:vertical;}' +
    '.orders-action-check{display:flex;gap:8px;align-items:flex-start;font-size:13px;color:#111827;}' +
    '.orders-action-msg{min-height:17px;font-size:12px;color:#b91c1c;}' +
    '.orders-action-foot{padding:12px 16px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;justify-content:flex-end;gap:8px;}' +
    '.orders-action-btn{border:none;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:700;cursor:pointer;}' +
    '.orders-action-btn-secondary{background:#e5e7eb;color:#111827;}' +
    '.orders-action-btn-primary{background:#111827;color:#fff;}';
  document.head.appendChild(style);

  var stack = document.createElement('div');
  stack.id = 'orders-toast-stack';
  stack.className = 'orders-toast-stack';
  document.body.appendChild(stack);
}

function ordersShowToast(message, type, ttlMs) {
  ordersEnsureUxLayer();
  var stack = document.getElementById('orders-toast-stack');
  if (!stack) return;

  var toast = document.createElement('div');
  var variant = type || 'info';
  toast.className = 'orders-toast orders-toast-' + variant;
  toast.textContent = message || '';
  stack.appendChild(toast);

  requestAnimationFrame(function() {
    toast.classList.add('show');
  });

  var timeout = typeof ttlMs === 'number' ? ttlMs : 3200;
  window.setTimeout(function() {
    toast.classList.remove('show');
    window.setTimeout(function() {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 220);
  }, timeout);
}

function ordersShowDestructiveDialog(config) {
  ordersEnsureUxLayer();

  return new Promise(function(resolve) {
    var backdrop = document.createElement('div');
    backdrop.className = 'orders-action-backdrop';
    backdrop.innerHTML = '' +
      '<div class="orders-action-modal" role="dialog" aria-modal="true" aria-label="Destructive order action">' +
        '<div class="orders-action-head">' + config.title + '</div>' +
        '<div class="orders-action-body">' +
          '<div style="font-size:13px;color:#374151;">' + config.description + '</div>' +
          '<div>' +
            '<label class="orders-action-label" for="orders-delete-password">Order Delete Password *</label>' +
            '<input id="orders-delete-password" class="orders-action-input" type="password" autocomplete="current-password" />' +
          '</div>' +
          '<div>' +
            '<label class="orders-action-label" for="orders-reason-notes">Reason notes (optional)</label>' +
            '<textarea id="orders-reason-notes" class="orders-action-textarea" placeholder="Add context for audit trail"></textarea>' +
          '</div>' +
          (config.requireFinancialConfirm ? '<label class="orders-action-check"><input id="orders-financial-confirm" type="checkbox" /> <span>I confirm financial entries, if any, must also be purged.</span></label>' : '') +
          '<label class="orders-action-check"><input id="orders-final-confirm" type="checkbox" /> <span>I understand this action and want to continue.</span></label>' +
          '<div id="orders-action-msg" class="orders-action-msg"></div>' +
        '</div>' +
        '<div class="orders-action-foot">' +
          '<button type="button" class="orders-action-btn orders-action-btn-secondary" data-action="cancel">Cancel</button>' +
          '<button type="button" class="orders-action-btn orders-action-btn-primary" data-action="submit">Continue</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(backdrop);

    var passwordInput = backdrop.querySelector('#orders-delete-password');
    var reasonInput = backdrop.querySelector('#orders-reason-notes');
    var finalConfirm = backdrop.querySelector('#orders-final-confirm');
    var financialConfirm = backdrop.querySelector('#orders-financial-confirm');
    var messageBox = backdrop.querySelector('#orders-action-msg');
    var closed = false;

    function close(result) {
      if (closed) return;
      closed = true;
      if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
      resolve(result || null);
    }

    backdrop.addEventListener('click', function(event) {
      if (event.target === backdrop) {
        close(null);
      }
    });

    backdrop.querySelector('[data-action="cancel"]').addEventListener('click', function() {
      close(null);
    });

    backdrop.querySelector('[data-action="submit"]').addEventListener('click', function() {
      var password = (passwordInput.value || '').trim();
      if (!password) {
        messageBox.textContent = 'Delete password is required.';
        passwordInput.focus();
        return;
      }
      if (!finalConfirm.checked) {
        messageBox.textContent = 'Please confirm to continue.';
        finalConfirm.focus();
        return;
      }
      if (config.requireFinancialConfirm && financialConfirm && !financialConfirm.checked) {
        messageBox.textContent = 'Financial purge confirmation is required for this order.';
        financialConfirm.focus();
        return;
      }

      close({
        password: password,
        reasonNotes: (reasonInput.value || '').trim(),
        confirmFinancialPurge: !!(financialConfirm && financialConfirm.checked),
      });
    });

    if (passwordInput) {
      passwordInput.focus();
    }
  });
}

async function ordersRunDestructiveAction(orderId, action, orderNumber) {
  var orderRef = orderNumber ? ('#' + orderNumber) : ('ID ' + orderId);
  var actionLabel = action === 'archive' ? 'Archive' : (action === 'restore' ? 'Restore' : 'Delete permanently');

  var requireFinancialConfirm = false;
  if (action === 'force_purge') {
    try {
      var previewPayload = new FormData();
      previewPayload.append('action', 'preview');
      previewPayload.append('order_id', String(orderId));
      var previewResp = await fetch('api/order-destructive-action.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: previewPayload
      });
      var previewJson = await previewResp.json();
      if (!previewJson || !previewJson.success) {
        ordersShowToast((previewJson && previewJson.message) ? previewJson.message : 'Unable to preview destructive impact.', 'error', 4200);
        return;
      }
      requireFinancialConfirm = !!previewJson.has_financial_entries;
    } catch (e) {
      ordersShowToast('Unable to verify financial impact before delete.', 'error', 4200);
      return;
    }
  }

  var destructiveInput = await ordersShowDestructiveDialog({
    title: actionLabel + ' Order ' + orderRef,
    description: action === 'force_purge'
      ? 'This action permanently deletes the order and related records. It cannot be undone.'
      : 'This action updates the order governance state and writes an audit entry.',
    requireFinancialConfirm: requireFinancialConfirm,
  });

  if (!destructiveInput) {
    return;
  }

  var payload = new FormData();
  payload.append('action', action);
  payload.append('order_id', String(orderId));
  payload.append('delete_password', destructiveInput.password);
  payload.append('reason_code', 'other');
  payload.append('reason_notes', destructiveInput.reasonNotes || '');
  payload.append('final_confirm', '1');
  if (action === 'force_purge' && destructiveInput.confirmFinancialPurge) {
    payload.append('confirm_financial_purge', '1');
  }

  ordersShowToast('Submitting ' + actionLabel.toLowerCase() + ' request for ' + orderRef + '…', 'info', 1700);

  try {
    var resp = await fetch('api/order-destructive-action.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: payload
    });
    var data = await resp.json();
    if (!resp.ok || !data.success) {
      ordersShowToast((data && data.message) ? data.message : 'Destructive action failed.', 'error', 5200);
      return;
    }
    ordersShowToast(data.message || 'Action completed successfully.', 'success', 1600);
    window.setTimeout(function() {
      if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
        window.CakeScrollPreserver.reload();
        return;
      }
      window.location.reload();
    }, 900);
  } catch (err) {
    ordersShowToast('Request failed. Please try again.', 'error', 4800);
  }
}

document.addEventListener('click', ordersCloseMenusOnOutsideClick);
