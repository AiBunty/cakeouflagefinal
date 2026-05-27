(function () {
  'use strict';

  function fmtCurrency(v) {
    const num = Number(v || 0);
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 0,
    }).format(Number.isFinite(num) ? num : 0);
  }

  function fmtDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return String(v);
    return d.toLocaleString('en-IN', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function statusPill(status) {
    const key = String(status || '').toLowerCase();
    if (['paid', 'delivered', 'completed'].includes(key)) return 'ok';
    if (['pending', 'under_review', 'payment_pending', 'confirmed', 'preparing', 'out_for_delivery'].includes(key)) return 'warn';
    if (['failed', 'rejected', 'cancelled', 'refunded', 'refund_requested', 'fully_refunded'].includes(key)) return 'danger';
    return 'neutral';
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const hidden = document.querySelector('input[name="_csrf"]');
    return hidden ? hidden.value : '';
  }

  async function fetchPayload(url, opts) {
    const res = await fetch(url, opts);
    const json = await res.json().catch(function () {
      return { success: false, message: 'Invalid server response' };
    });
    if (!res.ok || !json.success) {
      throw new Error(json.message || 'Request failed');
    }
    return json;
  }

  function renderMiniList(title, rows, lineFactory) {
    if (!rows || rows.length === 0) {
      return '<div class="crm-mini-panel"><h4>' + esc(title) + '</h4><div class="crm-mini-item">No records.</div></div>';
    }
    const lines = rows.slice(0, 5).map(lineFactory).join('');
    return '<div class="crm-mini-panel"><h4>' + esc(title) + '</h4>' + lines + '</div>';
  }

  function renderHistoryCard(order) {
    const orderNo = esc(order.order_number || ('#' + order.id));
    const payment = esc(order.payment_status || 'unknown');
    const orderStatus = esc(order.order_status || 'unknown');
    const items = esc((order.item_names || '').replace(/ \|\| /g, ', '));
    const refund = Number(order.refund_amount || 0) > 0 ? '<span class="crm-pill crm-pill--danger">Refund ' + esc(fmtCurrency(order.refund_amount)) + '</span>' : '';

    return '<article class="crm-history-card">'
      + '<div class="crm-history-line">'
      + '<a class="crm-order-no" href="orders.php?id=' + encodeURIComponent(order.id) + '" target="_blank" rel="noopener">' + orderNo + '</a>'
      + '<span class="crm-pill crm-pill--' + statusPill(payment) + '">' + payment + '</span>'
      + '<span class="crm-pill crm-pill--' + statusPill(orderStatus) + '">' + orderStatus + '</span>'
      + refund
      + '</div>'
      + '<div class="crm-history-meta">'
      + esc(fmtDate(order.created_at)) + ' • ' + esc(order.fulfilment_mode || 'N/A') + ' • ' + esc(order.payment_method || 'N/A') + ' • ' + esc(fmtCurrency(order.grand_total))
      + '</div>'
      + '<div class="crm-history-items">' + (items || 'No item snapshot') + '</div>'
      + '<div class="crm-history-footer">'
      + '<a class="crm-btn crm-btn--ghost" href="orders.php?id=' + encodeURIComponent(order.id) + '" target="_blank" rel="noopener">View Order</a>'
      + '</div>'
      + '</article>';
  }

  function renderPanelHTML(userId, payload) {
    const customer = payload.customer || {};
    const insights = payload.insights || {};
    const tags = Array.isArray(payload.allowed_tags) ? payload.allowed_tags : [];
    const activeTags = new Set(Array.isArray(payload.tags) ? payload.tags : []);
    const orders = Array.isArray(payload.orders) ? payload.orders : [];
    const page = Number(payload.pagination && payload.pagination.page ? payload.pagination.page : 1);
    const totalPages = Number(payload.pagination && payload.pagination.total_pages ? payload.pagination.total_pages : 1);

    const tagHtml = tags.map(function (t) {
      const active = activeTags.has(t) ? ' is-active' : '';
      return '<button type="button" class="crm-tag' + active + '" data-tag="' + esc(t) + '" data-user-id="' + esc(userId) + '">' + esc(t) + '</button>';
    }).join('');

    const history = orders.length ? orders.map(renderHistoryCard).join('') : '<div class="crm-empty">No orders available.</div>';

    const payments = renderMiniList('Payment History', payload.payments || [], function (row) {
      return '<div class="crm-mini-item">' + esc(row.order_number || ('#' + row.id)) + ' • ' + esc(row.payment_status || 'N/A') + ' • ' + esc(fmtCurrency(row.grand_total)) + '</div>';
    });

    const refunds = renderMiniList('Refund History', payload.refunds || [], function (row) {
      return '<div class="crm-mini-item">' + esc(row.order_number || ('#' + row.id)) + ' • ' + esc(fmtCurrency(row.refund_amount || 0)) + ' • ' + esc(row.payment_status || 'N/A') + '</div>';
    });

    const comms = renderMiniList('Comms & Follow-ups', (payload.communications || []).concat(payload.follow_ups || []).slice(0, 8), function (row) {
      const title = row.title || row.event_key || row.channel || 'activity';
      const state = row.status || '';
      const at = row.created_at || row.reminder_on || '';
      return '<div class="crm-mini-item">' + esc(title) + (state ? ' • ' + esc(state) : '') + (at ? ' • ' + esc(fmtDate(at)) : '') + '</div>';
    });

    const phone = customer.phone ? String(customer.phone).replace(/[^0-9+]/g, '') : '';
    const waText = encodeURIComponent('Hi ' + (customer.full_name || 'there') + ', this is a follow-up from Cakeouflage regarding your recent order.');
    const waHref = phone ? ('https://wa.me/' + phone + '?text=' + waText) : '#';
    const mailHref = customer.email ? ('mailto:' + encodeURIComponent(customer.email)) : '#';
    const callHref = phone ? ('tel:' + phone) : '#';

    return '<div class="crm-insight-grid">'
      + '<div class="crm-insight-item"><div class="crm-insight-item__label">Lifetime Spend</div><div class="crm-insight-item__value">' + esc(fmtCurrency(insights.lifetime_spend)) + '</div></div>'
      + '<div class="crm-insight-item"><div class="crm-insight-item__label">Avg. Order Value</div><div class="crm-insight-item__value">' + esc(fmtCurrency(insights.avg_order_value)) + '</div></div>'
      + '<div class="crm-insight-item"><div class="crm-insight-item__label">Total Refunds</div><div class="crm-insight-item__value">' + esc(fmtCurrency(insights.total_refunds)) + '</div></div>'
      + '<div class="crm-insight-item"><div class="crm-insight-item__label">Last Ordered</div><div class="crm-insight-item__value">' + esc(fmtDate(insights.last_ordered)) + '</div></div>'
      + '<div class="crm-insight-item"><div class="crm-insight-item__label">Favorite Category</div><div class="crm-insight-item__value">' + esc(insights.favorite_category || '—') + '</div></div>'
      + '<div class="crm-insight-item"><div class="crm-insight-item__label">Repeat Score</div><div class="crm-insight-item__value">' + esc((insights.repeat_score || 0) + '/100') + '</div></div>'
      + '</div>'
      + '<div class="crm-tag-wrap">' + tagHtml + '</div>'
      + '<div class="crm-history-list">' + history + '</div>'
      + '<div class="crm-pagination">'
      + '<button type="button" class="crm-btn crm-btn--ghost js-crm-page" data-user-id="' + esc(userId) + '" data-page="' + String(Math.max(1, page - 1)) + '" ' + (page <= 1 ? 'disabled' : '') + '>Prev</button>'
      + '<span>Page ' + esc(page) + ' of ' + esc(totalPages) + '</span>'
      + '<button type="button" class="crm-btn crm-btn--ghost js-crm-page" data-user-id="' + esc(userId) + '" data-page="' + String(Math.min(totalPages, page + 1)) + '" ' + (page >= totalPages ? 'disabled' : '') + '>Next</button>'
      + '</div>'
      + '<div class="crm-follow-actions">'
      + '<button type="button" class="crm-btn crm-btn--primary js-crm-note" data-user-id="' + esc(userId) + '">Add Internal Note</button>'
      + '<button type="button" class="crm-btn crm-btn--soft js-crm-follow-up" data-user-id="' + esc(userId) + '">Schedule Follow-up</button>'
      + '<a class="crm-btn crm-btn--ghost" href="' + esc(waHref) + '" target="_blank" rel="noopener">WhatsApp</a>'
      + '<a class="crm-btn crm-btn--ghost" href="' + esc(callHref) + '">Call</a>'
      + '<a class="crm-btn crm-btn--ghost" href="' + esc(mailHref) + '">Email</a>'
      + '</div>'
      + '<div class="crm-expand-bottom">' + payments + refunds + comms + '</div>';
  }

  async function loadPanel(userId, panel, page) {
    panel.innerHTML = '<div class="crm-expand-loading">Loading customer timeline...</div>';
    const url = 'ajax/customer-order-history.php?user_id=' + encodeURIComponent(userId) + '&page=' + encodeURIComponent(page || 1) + '&per_page=5';
    const res = await fetchPayload(url, { credentials: 'same-origin' });
    panel.dataset.loaded = '1';
    panel.dataset.page = String((res.data && res.data.pagination && res.data.pagination.page) || 1);
    panel.innerHTML = renderPanelHTML(userId, res.data || {});
  }

  async function postAction(userId, body) {
    const csrf = getCsrfToken();
    const payload = Object.assign({ user_id: Number(userId), _csrf: csrf }, body || {});
    return fetchPayload('ajax/customer-order-history.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        ...(csrf ? { 'X-CSRF-Token': csrf } : {}),
      },
      body: JSON.stringify(payload),
    });
  }

  function rowPanelForUser(userId) {
    return document.querySelector('.js-crm-panel[data-user-id="' + String(userId).replace(/"/g, '') + '"]');
  }

  document.addEventListener('click', async function (ev) {
    const expandBtn = ev.target.closest('.js-crm-expand');
    if (expandBtn) {
      const userId = expandBtn.dataset.userId;
      const row = document.querySelector('.js-crm-expand-row[data-user-id="' + String(userId).replace(/"/g, '') + '"]');
      const panel = row ? row.querySelector('.js-crm-panel') : null;
      if (!row || !panel) return;

      const isOpen = row.classList.contains('is-open');
      if (isOpen) {
        row.classList.remove('is-open');
        expandBtn.textContent = 'View History';
        return;
      }

      row.classList.add('is-open');
      expandBtn.textContent = 'Hide History';

      try {
        const page = Number(panel.dataset.page || 1);
        await loadPanel(userId, panel, page);
      } catch (err) {
        panel.innerHTML = '<div class="crm-empty">' + esc(err && err.message ? err.message : 'Unable to load history') + '</div>';
      }
      return;
    }

    const tagBtn = ev.target.closest('.crm-tag');
    if (tagBtn) {
      const userId = tagBtn.dataset.userId;
      const tag = tagBtn.dataset.tag;
      if (!userId || !tag) return;
      try {
        await postAction(userId, { action: 'toggle_tag', tag: tag });
        tagBtn.classList.toggle('is-active');
      } catch (err) {
        alert(err && err.message ? err.message : 'Tag update failed');
      }
      return;
    }

    const pager = ev.target.closest('.js-crm-page');
    if (pager) {
      const userId = pager.dataset.userId;
      const page = Number(pager.dataset.page || 1);
      const panel = rowPanelForUser(userId);
      if (!panel) return;
      try {
        await loadPanel(userId, panel, page);
      } catch (err) {
        panel.innerHTML = '<div class="crm-empty">' + esc(err && err.message ? err.message : 'Unable to load page') + '</div>';
      }
      return;
    }

    const noteBtn = ev.target.closest('.js-crm-note');
    if (noteBtn) {
      const userId = noteBtn.dataset.userId;
      const note = window.prompt('Add internal note for this customer:');
      if (!note) return;
      try {
        await postAction(userId, { action: 'add_note', note: note });
        const panel = rowPanelForUser(userId);
        if (panel) {
          await loadPanel(userId, panel, Number(panel.dataset.page || 1));
        }
      } catch (err) {
        alert(err && err.message ? err.message : 'Unable to save note');
      }
      return;
    }

    const followUpBtn = ev.target.closest('.js-crm-follow-up');
    if (followUpBtn) {
      const userId = followUpBtn.dataset.userId;
      const title = window.prompt('Follow-up title:', 'Customer follow-up');
      if (!title) return;
      const when = window.prompt('Follow-up date/time (YYYY-MM-DD HH:MM):');
      if (!when) return;
      const notes = window.prompt('Optional notes:') || '';

      try {
        await postAction(userId, {
          action: 'schedule_follow_up',
          title: title,
          when: when,
          notes: notes,
        });
        const panel = rowPanelForUser(userId);
        if (panel) {
          await loadPanel(userId, panel, Number(panel.dataset.page || 1));
        }
      } catch (err) {
        alert(err && err.message ? err.message : 'Unable to schedule follow-up');
      }
    }
  });
})();
