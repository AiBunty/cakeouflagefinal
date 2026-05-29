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

document.addEventListener('click', ordersCloseMenusOnOutsideClick);
