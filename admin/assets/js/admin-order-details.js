(function () {
  'use strict';

  function ensureToastStack() {
    var stack = document.getElementById('odToastStack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'odToastStack';
      stack.className = 'od-toast-stack';
      document.body.appendChild(stack);
    }
    return stack;
  }

  function showToast(message, type, ttlMs) {
    var stack = ensureToastStack();
    var toast = document.createElement('div');
    toast.className = 'od-toast od-toast-' + (type || 'info');
    toast.textContent = message || '';
    stack.appendChild(toast);
    window.setTimeout(function () {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, typeof ttlMs === 'number' ? ttlMs : 3200);
  }

  function showDialog(config) {
    return new Promise(function (resolve) {
      var backdrop = document.createElement('div');
      backdrop.className = 'od-dialog-backdrop';
      backdrop.innerHTML = '' +
        '<div class="od-dialog" role="dialog" aria-modal="true">' +
          '<div class="od-dialog-head">' + config.title + '</div>' +
          '<div class="od-dialog-body">' +
            '<div style="font-size:12px;color:#4b5563;">' + (config.description || '') + '</div>' +
            (config.fields || []).join('') +
            '<div id="odDialogMsg" class="od-inline-msg" style="color:#b91c1c;"></div>' +
          '</div>' +
          '<div class="od-dialog-foot">' +
            '<button type="button" class="od-btn od-btn-secondary" data-dialog-action="cancel">Cancel</button>' +
            '<button type="button" class="od-btn od-btn-primary" data-dialog-action="confirm">Continue</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(backdrop);

      var closed = false;
      function close(result) {
        if (closed) return;
        closed = true;
        if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
        resolve(result || null);
      }

      backdrop.addEventListener('click', function (event) {
        if (event.target === backdrop) {
          close(null);
        }
      });

      backdrop.querySelector('[data-dialog-action="cancel"]').addEventListener('click', function () {
        close(null);
      });

      backdrop.querySelector('[data-dialog-action="confirm"]').addEventListener('click', function () {
        var validator = typeof config.onConfirm === 'function' ? config.onConfirm(backdrop) : { ok: true, data: {} };
        if (!validator || validator.ok === false) {
          var msg = backdrop.querySelector('#odDialogMsg');
          if (msg) msg.textContent = validator && validator.message ? validator.message : 'Please check the form.';
          return;
        }
        close(validator.data || {});
      });

      var autofocus = backdrop.querySelector('[data-autofocus="1"]');
      if (autofocus) autofocus.focus();
    });
  }

  async function destructiveAction(orderId, action, orderNumber) {
    var orderRef = orderNumber ? ('#' + orderNumber) : ('ID ' + orderId);
    var requireFinancial = false;

    if (action === 'force_purge') {
      var previewPayload = new FormData();
      previewPayload.append('action', 'preview');
      previewPayload.append('order_id', String(orderId));
      try {
        var previewResp = await fetch('api/order-destructive-action.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: previewPayload,
        });
        var previewJson = await previewResp.json();
        if (!previewResp.ok || !previewJson.success) {
          showToast((previewJson && previewJson.message) ? previewJson.message : 'Unable to preview destructive action.', 'error', 4200);
          return;
        }
        requireFinancial = !!previewJson.has_financial_entries;
      } catch (err) {
        showToast('Unable to preview destructive action.', 'error', 4200);
        return;
      }
    }

    var dialogResult = await showDialog({
      title: (action === 'archive' ? 'Archive ' : action === 'restore' ? 'Restore ' : 'Delete ') + orderRef,
      description: action === 'force_purge'
        ? 'This permanently deletes the order and related records.'
        : 'This writes an audit event and updates governance state.',
      fields: [
        '<div class="od-field"><label for="odDeletePassword">Order Delete Password *</label><input id="odDeletePassword" type="password" data-autofocus="1"></div>',
        '<div class="od-field"><label for="odReasonNotes">Reason Notes</label><textarea id="odReasonNotes" placeholder="Optional audit note"></textarea></div>',
        requireFinancial ? '<label style="display:flex;gap:8px;align-items:flex-start;font-size:12px;color:#374151;"><input id="odFinancialConfirm" type="checkbox"> <span>I confirm associated financial entries should also be purged.</span></label>' : '',
        '<label style="display:flex;gap:8px;align-items:flex-start;font-size:12px;color:#374151;"><input id="odFinalConfirm" type="checkbox"> <span>I understand and want to continue.</span></label>'
      ],
      onConfirm: function (backdrop) {
        var password = (backdrop.querySelector('#odDeletePassword').value || '').trim();
        var notes = (backdrop.querySelector('#odReasonNotes').value || '').trim();
        var finalConfirm = backdrop.querySelector('#odFinalConfirm');
        var financialConfirm = backdrop.querySelector('#odFinancialConfirm');
        if (!password) {
          return { ok: false, message: 'Delete password is required.' };
        }
        if (!finalConfirm || !finalConfirm.checked) {
          return { ok: false, message: 'Confirmation is required.' };
        }
        if (requireFinancial && financialConfirm && !financialConfirm.checked) {
          return { ok: false, message: 'Financial purge confirmation is required.' };
        }
        return {
          ok: true,
          data: {
            password: password,
            notes: notes,
            confirmFinancial: !!(financialConfirm && financialConfirm.checked),
          }
        };
      }
    });

    if (!dialogResult) {
      return;
    }

    var payload = new FormData();
    payload.append('action', action);
    payload.append('order_id', String(orderId));
    payload.append('delete_password', dialogResult.password);
    payload.append('reason_code', 'other');
    payload.append('reason_notes', dialogResult.notes || '');
    payload.append('final_confirm', '1');
    if (action === 'force_purge' && dialogResult.confirmFinancial) {
      payload.append('confirm_financial_purge', '1');
    }

    showToast('Submitting request for ' + orderRef + '…', 'info', 1600);
    try {
      var response = await fetch('api/order-destructive-action.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: payload,
      });
      var json = await response.json();
      if (!response.ok || !json.success) {
        showToast((json && json.message) ? json.message : 'Request failed.', 'error', 4400);
        return;
      }
      showToast(json.message || 'Action completed.', 'success', 1500);
      window.setTimeout(function () {
        if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
          window.CakeScrollPreserver.reload();
          return;
        }
        window.location.reload();
      }, 900);
    } catch (err) {
      showToast('Request failed. Please try again.', 'error', 4200);
    }
  }

  async function confirmPayment(orderId, options) {
    var expected = parseFloat(options.expectedAmount || '0');
    var dialogResult = await showDialog({
      title: 'Confirm Payment',
      description: 'Confirm received amount and reserve the slot for this order.',
      fields: [
        '<div class="od-field"><label for="odReceivedAmount">Amount Received *</label><input id="odReceivedAmount" type="number" step="0.01" value="' + expected.toFixed(2) + '" data-autofocus="1"></div>',
        '<div class="od-field"><label for="odDiscountReason">Shortfall Reason</label><input id="odDiscountReason" type="text" placeholder="Required if shortfall exists"></div>',
        '<label style="display:flex;gap:8px;align-items:flex-start;font-size:12px;color:#374151;"><input id="odManagerOverride" type="checkbox"> <span>Manager override approved for discounts above 5%.</span></label>'
      ],
      onConfirm: function (backdrop) {
        var received = parseFloat((backdrop.querySelector('#odReceivedAmount').value || '0').trim());
        var reason = (backdrop.querySelector('#odDiscountReason').value || '').trim();
        var override = !!backdrop.querySelector('#odManagerOverride').checked;
        if (!isFinite(received) || received <= 0) {
          return { ok: false, message: 'Enter a valid received amount.' };
        }
        var shortfall = Math.max(0, +(expected - received).toFixed(2));
        if (shortfall > 0 && !reason) {
          return { ok: false, message: 'Shortfall reason is required.' };
        }
        if (expected > 0 && shortfall / expected > 0.05 && !override) {
          return { ok: false, message: 'Manager override is required for discounts above 5%.' };
        }
        return { ok: true, data: { received: received, reason: reason, override: override } };
      }
    });

    if (!dialogResult) {
      return;
    }

    var msg = document.getElementById('order-payment-action-msg');
    if (msg) msg.textContent = 'Processing…';
    try {
      var response = await fetch('/api/admin/orders/' + orderId + '/confirm-payment', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          received_amount: dialogResult.received,
          discount_reason: dialogResult.reason,
          manager_override: dialogResult.override
        })
      });
      var json = await response.json();
      if (!response.ok || !json.success) {
        if (msg) msg.textContent = json && json.message ? json.message : 'Confirmation failed.';
        showToast((json && json.message) ? json.message : 'Confirmation failed.', 'error', 4200);
        return;
      }
      if (msg) msg.textContent = json.message || 'Payment confirmed.';
      showToast(json.message || 'Payment confirmed.', 'success', 1500);
      window.setTimeout(function () {
        if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
          window.CakeScrollPreserver.reload();
          return;
        }
        window.location.reload();
      }, 900);
    } catch (err) {
      if (msg) msg.textContent = 'Network error';
      showToast('Network error while confirming payment.', 'error', 4200);
    }
  }

  async function rejectPayment(orderId) {
    var dialogResult = await showDialog({
      title: 'Reject Payment',
      description: 'Provide a reason for rejecting this payment submission.',
      fields: [
        '<div class="od-field"><label for="odRejectReason">Reason</label><textarea id="odRejectReason" data-autofocus="1" placeholder="Optional rejection note"></textarea></div>'
      ],
      onConfirm: function (backdrop) {
        return { ok: true, data: { reason: (backdrop.querySelector('#odRejectReason').value || '').trim() } };
      }
    });

    if (!dialogResult) {
      return;
    }

    var msg = document.getElementById('order-payment-action-msg');
    if (msg) msg.textContent = 'Processing…';
    try {
      var response = await fetch('/api/admin/orders/' + orderId + '/reject-payment', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ reason: dialogResult.reason })
      });
      var json = await response.json();
      if (!response.ok || !json.success) {
        if (msg) msg.textContent = json && json.message ? json.message : 'Rejection failed.';
        showToast((json && json.message) ? json.message : 'Rejection failed.', 'error', 4200);
        return;
      }
      if (msg) msg.textContent = json.message || 'Payment rejected.';
      showToast(json.message || 'Payment rejected.', 'success', 1500);
      window.setTimeout(function () {
        if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
          window.CakeScrollPreserver.reload();
          return;
        }
        window.location.reload();
      }, 900);
    } catch (err) {
      if (msg) msg.textContent = 'Network error';
      showToast('Network error while rejecting payment.', 'error', 4200);
    }
  }

  async function cancelOrder(orderId) {
    var dialogResult = await showDialog({
      title: 'Cancel Order',
      description: 'Cancelling an unpaid order will mark it as cancelled and update the audit log.',
      fields: [
        '<div class="od-field"><label for="odCancelReason">Reason</label><textarea id="odCancelReason" data-autofocus="1" placeholder="Optional cancellation note"></textarea></div>'
      ],
      onConfirm: function (backdrop) {
        return { ok: true, data: { reason: (backdrop.querySelector('#odCancelReason').value || '').trim() } };
      }
    });

    if (!dialogResult) {
      return;
    }

    var form = new FormData();
    form.append('action', 'cancel');
    form.append('order_id', orderId);
    form.append('reason', dialogResult.reason || '');
    form.append('redirect_to', window.location.href);

    try {
      var response = await fetch('/admin/api/order-refund-cancel.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      });
      var json = await response.json();
      if (!response.ok || !json.success) {
        showToast((json && (json.error || json.message)) ? (json.error || json.message) : 'Cancel failed.', 'error', 4400);
        return;
      }
      showToast('Order cancelled successfully.', 'success', 1500);
      window.setTimeout(function () {
        if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
          window.CakeScrollPreserver.reload();
          return;
        }
        window.location.reload();
      }, 900);
    } catch (err) {
      showToast('Network error while cancelling order.', 'error', 4200);
    }
  }

  function prepRefundModal(orderId, grandTotal) {
    var setValue = function (id, value) {
      var el = document.getElementById(id);
      if (el) el.value = value;
    };
    setValue('refund-order-id', orderId);
    setValue('refund-grand-total', grandTotal);
    setValue('refund-amount', '');
    setValue('refund-reason', '');
    setValue('refund-notes', '');
    setValue('refund-settlement-ref', '');
    setValue('refund-proof-url', '');
    var amountEl = document.getElementById('refund-amount');
    if (amountEl) amountEl.max = String(grandTotal || '');
    var filename = document.getElementById('refund-proof-filename');
    if (filename) filename.textContent = '';
    var msg = document.getElementById('refund-modal-msg');
    if (msg) msg.textContent = '';
    var notesGroup = document.getElementById('refund-notes-group');
    if (notesGroup) notesGroup.style.display = 'none';
    var full = document.getElementById('refund-type-full');
    var partial = document.getElementById('refund-type-partial');
    if (full) full.checked = false;
    if (partial) partial.checked = false;
  }

  function toggleAccordion(button) {
    var card = button.closest('.is-collapsible');
    if (!card) return;
    var open = card.classList.toggle('open');
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    var icon = button.querySelector('[data-accordion-icon]');
    if (icon) {
      icon.textContent = open ? 'Hide' : 'Show';
    }
  }

  function copyOrderNumber() {
    var value = document.querySelector('[data-order-copy-value]');
    if (!value) return;
    var text = value.getAttribute('data-order-copy-value') || value.textContent || '';
    navigator.clipboard.writeText(text).then(function () {
      showToast('Order ID copied.', 'success', 1600);
    }).catch(function () {
      showToast('Unable to copy order ID.', 'error', 2600);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-od-accordion]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        toggleAccordion(btn);
      });
    });

    var copyBtn = document.getElementById('odCopyOrderId');
    if (copyBtn) copyBtn.addEventListener('click', copyOrderNumber);

    document.querySelectorAll('[data-od-destructive]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        destructiveAction(
          parseInt(btn.getAttribute('data-order-id') || '0', 10),
          btn.getAttribute('data-od-destructive') || '',
          btn.getAttribute('data-order-number') || ''
        );
      });
    });

    document.querySelectorAll('[data-od-confirm-payment]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        confirmPayment(
          parseInt(btn.getAttribute('data-order-id') || '0', 10),
          { expectedAmount: btn.getAttribute('data-expected-amount') || '0' }
        );
      });
    });

    document.querySelectorAll('[data-od-reject-payment]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        rejectPayment(parseInt(btn.getAttribute('data-order-id') || '0', 10));
      });
    });

    document.querySelectorAll('[data-od-cancel-order]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        cancelOrder(parseInt(btn.getAttribute('data-order-id') || '0', 10));
      });
    });

    document.querySelectorAll('[data-od-scroll-refund]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var section = document.getElementById('orderRefundSection');
        if (section) {
          section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    var fullRefund = document.getElementById('refund-type-full');
    var partialRefund = document.getElementById('refund-type-partial');
    var refundAmount = document.getElementById('refund-amount');
    var refundGrandTotal = document.getElementById('refund-grand-total');
    if (fullRefund && refundAmount && refundGrandTotal) {
      fullRefund.addEventListener('change', function () {
        if (fullRefund.checked) {
          refundAmount.value = parseFloat(refundGrandTotal.value || '0').toFixed(2);
        }
      });
    }
    if (partialRefund && refundAmount) {
      partialRefund.addEventListener('change', function () {
        if (partialRefund.checked) {
          refundAmount.value = '';
        }
      });
    }

    var refundReason = document.getElementById('refund-reason');
    var refundNotesGroup = document.getElementById('refund-notes-group');
    var refundNotes = document.getElementById('refund-notes');
    if (refundReason && refundNotesGroup && refundNotes) {
      refundReason.addEventListener('change', function () {
        var show = refundReason.value === 'OTHER';
        refundNotesGroup.style.display = show ? 'block' : 'none';
        refundNotes.required = show;
      });
    }

    var refundProof = document.getElementById('refund-proof-file');
    if (refundProof) {
      refundProof.addEventListener('change', async function () {
        var file = refundProof.files && refundProof.files[0] ? refundProof.files[0] : null;
        if (!file) return;
        var fileLabel = document.getElementById('refund-proof-filename');
        if (file.size > 5 * 1024 * 1024) {
          showToast('File must be under 5 MB.', 'error', 3600);
          refundProof.value = '';
          return;
        }
        var allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (allowed.indexOf(file.type) === -1) {
          showToast('Only JPEG, PNG, WebP and PDF are allowed.', 'error', 3600);
          refundProof.value = '';
          return;
        }
        var form = new FormData();
        form.append('proof', file);
        if (fileLabel) fileLabel.textContent = 'Uploading…';
        try {
          var response = await fetch('/api/admin/refunds/upload-proof', {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          var json = await response.json();
          if (!response.ok || !json.success) {
            if (fileLabel) fileLabel.textContent = 'Upload failed';
            showToast((json && json.message) ? json.message : 'Proof upload failed.', 'error', 3600);
            return;
          }
          var hidden = document.getElementById('refund-proof-url');
          if (hidden) hidden.value = json.url || '';
          if (fileLabel) fileLabel.textContent = file.name;
          showToast('Proof uploaded.', 'success', 1800);
        } catch (err) {
          if (fileLabel) fileLabel.textContent = 'Upload failed';
          showToast('Network error while uploading proof.', 'error', 3600);
        }
      });
    }

    var refundSubmit = document.getElementById('refund-submit-btn');
    if (refundSubmit) {
      refundSubmit.addEventListener('click', async function () {
        var orderId = parseInt((document.getElementById('refund-order-id').value || '0'), 10);
        var amount = parseFloat(document.getElementById('refund-amount').value || '0');
        var grandTotal = parseFloat(document.getElementById('refund-grand-total').value || '0');
        var reason = document.getElementById('refund-reason').value || '';
        var notes = (document.getElementById('refund-notes').value || '').trim();
        var settlementReference = (document.getElementById('refund-settlement-ref').value || '').trim();
        var proofUrl = (document.getElementById('refund-proof-url').value || '').trim();
        var msg = document.getElementById('refund-modal-msg');
        if (!orderId || !isFinite(amount) || amount <= 0) {
          if (msg) msg.textContent = 'Enter a valid refund amount.';
          return;
        }
        if (amount > grandTotal) {
          if (msg) msg.textContent = 'Refund amount cannot exceed order total.';
          return;
        }
        if (!reason) {
          if (msg) msg.textContent = 'Select a refund reason.';
          return;
        }
        if (reason === 'OTHER' && !notes) {
          if (msg) msg.textContent = 'Internal notes are required for Other.';
          return;
        }
        refundSubmit.disabled = true;
        if (msg) msg.textContent = 'Submitting…';
        try {
          var response = await fetch('/api/admin/orders/' + orderId + '/refund/process', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              refund_amount: amount,
              reason_code: reason,
              reason_notes: notes,
              settlement_reference: settlementReference,
              settlement_proof_url: proofUrl
            })
          });
          var json = await response.json();
          if (!response.ok || !json.success) {
            if (msg) msg.textContent = (json && json.message) ? json.message : 'Failed to submit refund request.';
            refundSubmit.disabled = false;
            return;
          }
          if (msg) msg.textContent = json.message || 'Refund request submitted.';
          showToast(json.message || 'Refund request submitted.', 'success', 1600);
          window.setTimeout(function () {
            if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
              window.CakeScrollPreserver.reload();
              return;
            }
            window.location.reload();
          }, 900);
        } catch (err) {
          if (msg) msg.textContent = 'Network error';
          refundSubmit.disabled = false;
        }
      });
    }
  });

  window.orderDetailsUi = {
    showToast: showToast,
    destructiveAction: destructiveAction,
    confirmPayment: confirmPayment,
    rejectPayment: rejectPayment,
    cancelOrder: cancelOrder,
    prepRefundModal: prepRefundModal
  };
})();