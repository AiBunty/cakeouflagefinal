<?php
$pageTitle = 'Slot Management';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/includes/db.php';

// ── Read today's usage via mysqli for initial page render ─────────────
$today    = date('Y-m-d');
$tableOk  = false;
$initSlots = [];

// Check if order_slots table exists
$tbl = $conn->query("SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_slots' LIMIT 1");
if ($tbl && $tbl->num_rows > 0) {
    $tableOk = true;
    $sql = "SELECT s.*,
                   COALESCE(sc.booked_count, 0)                 AS booked_today,
                   COALESCE(ex.override_capacity, s.max_orders) AS effective_capacity,
                   COALESCE(ex.is_closed, 0)                    AS is_exception_closed,
                   ex.note                                       AS exception_note
            FROM order_slots s
            LEFT JOIN slot_capacities sc
              ON sc.slot_id = s.id AND sc.booking_date = '$today'
            LEFT JOIN order_slot_exceptions ex
              ON ex.slot_id = s.id AND ex.exception_date = '$today'
            ORDER BY s.slot_type, s.display_order, s.start_time";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $row['remaining'] = max(0, (int)$row['effective_capacity'] - (int)$row['booked_today']);
        $row['is_full']   = (int)$row['booked_today'] >= (int)$row['effective_capacity'];
        $initSlots[]      = $row;
    }
}

$deliverySlots = array_filter($initSlots, fn($s) => $s['slot_type'] === 'delivery');
$pickupSlots   = array_filter($initSlots, fn($s) => $s['slot_type'] === 'pickup');
?>

<style>
/* ── Page layout ──────────────────────────────────────────────────── */
.slots-wrap   { max-width: 1100px; margin: 0 auto; padding: 0 16px 40px; }
.slots-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 24px; }
.slots-header h1 { font-family: 'DM Serif Display', serif; font-size: 1.6rem; color: var(--admin-burgundy); margin: 0; }
.slots-header p { font-size: .82rem; color: var(--admin-muted); margin: 2px 0 0; }

/* ── Tabs ─────────────────────────────────────────────────────────── */
.slot-tabs { display: flex; gap: 4px; border-bottom: 2px solid #ede0e3; margin-bottom: 22px; }
.slot-tab  { padding: 9px 22px; cursor: pointer; font-size: .85rem; font-weight: 600;
             color: var(--admin-muted); border-radius: 6px 6px 0 0; background: none;
             border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all .15s; }
.slot-tab.active { color: var(--admin-burgundy); border-bottom-color: var(--admin-burgundy); background: rgba(128,0,31,.04); }
.slot-tab:hover:not(.active) { background: rgba(128,0,31,.03); }

/* ── Cards grid ───────────────────────────────────────────────────── */
.slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.slot-card {
    border: 1.5px solid #e8dde0; border-radius: 14px; padding: 18px 18px 14px;
    background: #fff; box-shadow: 0 2px 8px rgba(128,0,31,.04); position: relative;
    transition: box-shadow .15s;
}
.slot-card:hover  { box-shadow: 0 4px 18px rgba(128,0,31,.10); }
.slot-card--paused { opacity: .55; }
.slot-card--full  .slot-cap-bar-fill { background: #dc2626; }

.slot-card__head  { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8px; }
.slot-card__name  { font-weight: 700; font-size: .95rem; color: var(--admin-ink); }
.slot-card__label { font-size: .78rem; color: var(--admin-muted); margin-top: 1px; }

.slot-badge { display: inline-flex; align-items: center; font-size: .68rem; font-weight: 700;
              padding: 3px 9px; border-radius: 99px; gap: 4px; }
.slot-badge--active   { background: #dcfce7; color: #15803d; }
.slot-badge--paused   { background: #fee2e2; color: #991b1b; }
.slot-badge--full     { background: #fee2e2; color: #991b1b; }
.slot-badge--fast     { background: #fef3c7; color: #92400e; }
.slot-badge--closed   { background: #e5e7eb; color: #374151; }

.slot-time { font-size: .82rem; color: var(--admin-muted); margin-bottom: 10px; }
.slot-time strong { color: var(--admin-ink); }

/* Capacity bar */
.slot-cap-wrap   { margin: 12px 0 6px; }
.slot-cap-label  { display: flex; justify-content: space-between; font-size: .75rem; color: var(--admin-muted); margin-bottom: 4px; }
.slot-cap-bar    { height: 7px; background: #f1e8ea; border-radius: 99px; overflow: hidden; }
.slot-cap-bar-fill { height: 100%; background: var(--admin-burgundy); border-radius: 99px; transition: width .4s; }

/* Actions */
.slot-actions { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
.btn-sm { padding: 5px 14px; font-size: .78rem; font-weight: 600; border-radius: 8px;
          border: none; cursor: pointer; transition: all .12s; }
.btn-sm--primary  { background: var(--admin-burgundy); color: #fff; }
.btn-sm--primary:hover { background: #5f0017; }
.btn-sm--ghost    { background: transparent; color: var(--admin-burgundy); border: 1.5px solid #dcc8cd; }
.btn-sm--ghost:hover { background: rgba(128,0,31,.05); }
.btn-sm--danger   { background: transparent; color: #b91c1c; border: 1.5px solid #fca5a5; }
.btn-sm--danger:hover { background: #fef2f2; }
.btn-sm--success  { background: #166534; color: #fff; }
.btn-sm--success:hover { background: #14532d; }

/* Notice banner */
.slot-notice { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: .84rem; }
.slot-notice--warn  { background: #fef3c7; border: 1px solid #fde68a; color: #78350f; }
.slot-notice--info  { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

/* ── Modal overlay ────────────────────────────────────────────────── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45);
                 z-index: 9000; align-items: center; justify-content: center; padding: 16px; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 18px; width: 100%; max-width: 520px;
             box-shadow: 0 20px 60px rgba(0,0,0,.22); overflow: hidden; }
.modal-head { padding: 20px 24px 16px; border-bottom: 1px solid #ede0e3;
              display: flex; align-items: center; justify-content: space-between; }
.modal-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--admin-ink); }
.modal-close  { background: none; border: none; cursor: pointer; color: var(--admin-muted);
                font-size: 1.4rem; line-height: 1; padding: 0 4px; }
.modal-body  { padding: 20px 24px; overflow-y: auto; max-height: 70vh; }
.modal-foot  { padding: 14px 24px; border-top: 1px solid #ede0e3; display: flex; justify-content: flex-end; gap: 10px; }

.form-group  { margin-bottom: 16px; }
.form-group label { display: block; font-size: .81rem; font-weight: 600; color: var(--admin-ink); margin-bottom: 5px; }
.form-input  { width: 100%; padding: 9px 12px; border: 1.5px solid #dcc8cd; border-radius: 9px;
               font-family: 'Poppins', sans-serif; font-size: .85rem; color: var(--admin-ink);
               transition: border-color .12s; background: #fffafb; }
.form-input:focus { outline: none; border-color: var(--admin-burgundy); }
.form-row-2  { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* Responsive */
@media (max-width: 520px) {
    .form-row-2 { grid-template-columns: 1fr; }
    .slot-actions { gap: 6px; }
    .holiday-controls { grid-template-columns: 1fr; }
}

/* Exception inline list */
.exc-list { list-style: none; padding: 0; margin: 0; font-size: .8rem; }
.exc-list li { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0e6e9; gap: 8px; }
.exc-list li:last-child { border-bottom: none; }
.exc-del { background: none; border: none; color: #b91c1c; cursor: pointer; font-size: .9rem; padding: 0 4px; }

/* Usage date selector */
.usage-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.usage-toolbar label { font-size: .82rem; font-weight: 600; color: var(--admin-ink); }
.usage-toolbar input[type="date"] { padding: 7px 12px; border: 1.5px solid #dcc8cd; border-radius: 9px;
    font-family: 'Poppins', sans-serif; font-size: .83rem; color: var(--admin-ink); background: #fffafb; }
.btn-refresh { padding: 7px 18px; font-size: .82rem; font-weight: 600; background: var(--admin-burgundy);
               color: #fff; border: none; border-radius: 9px; cursor: pointer; }
.btn-refresh:hover { background: #5f0017; }

/* Holiday panel */
.holiday-panel {
    border: 1.5px solid #e8dde0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(128,0,31,.04);
    padding: 14px;
    margin-bottom: 18px;
}
.holiday-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}
.holiday-panel__title {
    margin: 0;
    color: var(--admin-burgundy);
    font-size: .95rem;
    font-weight: 700;
}
.holiday-panel__sub {
    margin: 2px 0 0;
    font-size: .76rem;
    color: var(--admin-muted);
}
.holiday-controls {
    display: grid;
    grid-template-columns: 1.2fr 1fr 2fr auto auto;
    gap: 8px;
    align-items: end;
    margin-bottom: 10px;
}
.holiday-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.holiday-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #f0e6e9;
    padding: 8px 0;
    font-size: .8rem;
}
.holiday-item:last-child { border-bottom: none; }
.holiday-meta { color: var(--admin-muted); font-size: .75rem; }
.holiday-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: 6px;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: .68rem;
    font-weight: 700;
    border: 1px solid #fecaca;
    color: #991b1b;
    background: #fff1f2;
}
.holiday-remove {
    background: transparent;
    color: #b91c1c;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 4px 8px;
    font-size: .72rem;
    cursor: pointer;
}
.holiday-remove:hover { background: #fff1f2; }

/* Add slot FAB */
.fab { position: fixed; bottom: 28px; right: 28px; width: 52px; height: 52px;
       border-radius: 50%; background: var(--admin-burgundy); color: #fff; border: none;
       font-size: 1.6rem; cursor: pointer; box-shadow: 0 4px 20px rgba(128,0,31,.35);
       display: flex; align-items: center; justify-content: center; z-index: 100;
       transition: background .12s, transform .12s; }
.fab:hover { background: #5f0017; transform: scale(1.07); }

/* Toast */
.toast { position: fixed; bottom: 88px; right: 28px; background: #1a1a2e; color: #fff;
         padding: 11px 20px; border-radius: 10px; font-size: .82rem; font-weight: 600;
         box-shadow: 0 4px 20px rgba(0,0,0,.25); z-index: 9999; opacity: 0;
         transition: opacity .25s, transform .25s; transform: translateY(8px); pointer-events: none; }
.toast.show { opacity: 1; transform: translateY(0); }
.toast.toast--ok  { background: #166534; }
.toast.toast--err { background: #991b1b; }
</style>

<div class="slots-wrap">
    <div class="slots-header">
        <div>
            <h1>Slot Management</h1>
            <p>Manage delivery &amp; pickup time slots. All changes take effect immediately for new bookings.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <button class="btn-sm btn-sm--ghost" onclick="openEmergencyModal()">
                🔴 Emergency Close
            </button>
            <button class="btn-sm btn-sm--primary" onclick="openCreateModal()">
                + Add Slot
            </button>
        </div>
    </div>

    <?php if (!$tableOk): ?>
    <div class="slot-notice slot-notice--warn">
        ⚠️ The <code>order_slots</code> table has not been created yet. Run the slot management migration to enable this feature.<br>
        <small>File: <code>database/migrations/2026-05-22-slot-management.sql</code></small>
    </div>
    <?php else: ?>

    <!-- Usage date selector -->
    <div class="usage-toolbar">
        <label for="usageDatePicker">Viewing usage for:</label>
        <input type="date" id="usageDatePicker" value="<?= htmlspecialchars($today) ?>">
        <button class="btn-refresh" onclick="refreshUsage()">Refresh</button>
        <span id="usageStatus" style="font-size:.78rem;color:var(--admin-muted)"></span>
    </div>

    <div class="holiday-panel">
        <div class="holiday-panel__head">
            <div>
                <h3 class="holiday-panel__title">Holiday Closures (Phase 1)</h3>
                <p class="holiday-panel__sub">Create date-based closures by delivery, pickup, or all slots.</p>
            </div>
            <button class="btn-sm btn-sm--ghost" onclick="loadHolidayList()">Reload</button>
        </div>

        <div class="holiday-controls">
            <div class="form-group" style="margin-bottom:0">
                <label for="holidayDate">Holiday Date</label>
                <input type="date" class="form-input" id="holidayDate">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label for="holidayType">Applies To</label>
                <select class="form-input" id="holidayType">
                    <option value="all">All Slots</option>
                    <option value="delivery">Delivery Only</option>
                    <option value="pickup">Pickup Only</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label for="holidayNote">Note</label>
                <input type="text" class="form-input" id="holidayNote" placeholder="e.g. Public holiday closure">
            </div>
            <button class="btn-sm btn-sm--primary" onclick="addHoliday()">Add Holiday</button>
            <button class="btn-sm btn-sm--ghost" onclick="clearHolidayForm()">Clear</button>
        </div>

        <ul id="holidayListEl" class="holiday-list">
            <li style="color:var(--admin-muted);font-size:.8rem">Loading holidays...</li>
        </ul>
    </div>

    <!-- Tabs -->
    <div class="slot-tabs">
        <button class="slot-tab active" id="tab-delivery" onclick="switchTab('delivery')">
            🚚 Delivery Slots
            <span class="tab-count" id="tabCount-delivery">(<?= count(array_filter($deliverySlots)) ?>)</span>
        </button>
        <button class="slot-tab" id="tab-pickup" onclick="switchTab('pickup')">
            🏪 Pickup Slots
            <span class="tab-count" id="tabCount-pickup">(<?= count(array_filter($pickupSlots)) ?>)</span>
        </button>
    </div>

    <!-- Slot grids (initial render, JS refreshes) -->
    <div id="grid-delivery" class="slot-grid">
        <?php foreach ($deliverySlots as $s): ?>
        <?= renderSlotCard($s) ?>
        <?php endforeach; ?>
        <?php if (empty($deliverySlots)): ?>
        <p style="color:var(--admin-muted);font-size:.85rem">No delivery slots defined yet.</p>
        <?php endif; ?>
    </div>

    <div id="grid-pickup" class="slot-grid" style="display:none">
        <?php foreach ($pickupSlots as $s): ?>
        <?= renderSlotCard($s) ?>
        <?php endforeach; ?>
        <?php if (empty($pickupSlots)): ?>
        <p style="color:var(--admin-muted);font-size:.85rem">No pickup slots defined yet.</p>
        <?php endif; ?>
    </div>

    <?php endif; // tableOk ?>
</div><!-- /slots-wrap -->

<!-- ================================================================
     ADD / EDIT SLOT MODAL
================================================================ -->
<div class="modal-overlay" id="slotModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="slotModalTitle">Add New Slot</h3>
            <button class="modal-close" onclick="closeModal('slotModal')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editSlotId" value="">
            <div class="form-group">
                <label>Slot Type *</label>
                <select class="form-input" id="fSlotType">
                    <option value="delivery">Delivery</option>
                    <option value="pickup">Pickup</option>
                </select>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Slot Name * <small style="color:var(--admin-muted)">(internal ID)</small></label>
                    <input type="text" class="form-input" id="fSlotName" placeholder="e.g. morning_delivery">
                </div>
                <div class="form-group">
                    <label>Display Label *</label>
                    <input type="text" class="form-input" id="fSlotLabel" placeholder="e.g. Morning (9AM – 12PM)">
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Start Time *</label>
                    <input type="time" class="form-input" id="fStartTime">
                </div>
                <div class="form-group">
                    <label>End Time *</label>
                    <input type="time" class="form-input" id="fEndTime">
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Max Orders</label>
                    <input type="number" class="form-input" id="fMaxOrders" value="20" min="1">
                </div>
                <div class="form-group">
                    <label>Prep Buffer (mins)</label>
                    <input type="number" class="form-input" id="fPrepBuffer" value="90" min="0">
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Cutoff Hour (same-day)</label>
                    <input type="number" class="form-input" id="fCutoffHour" value="0" min="0" max="23"
                           placeholder="0 = disabled">
                </div>
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" class="form-input" id="fDisplayOrder" value="0" min="0">
                </div>
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:10px">
                <input type="checkbox" id="fIsRecommended" style="width:16px;height:16px">
                <label for="fIsRecommended" style="margin:0;font-weight:400">Mark as Recommended</label>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-sm btn-sm--ghost" onclick="closeModal('slotModal')">Cancel</button>
            <button class="btn-sm btn-sm--primary" onclick="saveSlot()">Save Slot</button>
        </div>
    </div>
</div>

<!-- ================================================================
     EXCEPTION / HOLIDAY MODAL
================================================================ -->
<div class="modal-overlay" id="exceptionModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="excModalTitle">Exceptions / Holidays</h3>
            <button class="modal-close" onclick="closeModal('exceptionModal')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="excSlotId" value="">
            <p style="font-size:.82rem;color:var(--admin-muted);margin:0 0 14px">
                Override capacity or close a slot for a specific date.
            </p>
            <!-- Add exception form -->
            <div class="form-row-2" style="margin-bottom:6px">
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" class="form-input" id="fExcDate">
                </div>
                <div class="form-group">
                    <label>Override Capacity <small>(leave blank to just close)</small></label>
                    <input type="number" class="form-input" id="fExcCapacity" min="0" placeholder="—">
                </div>
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <input type="checkbox" id="fExcIsClosed" style="width:16px;height:16px">
                <label for="fExcIsClosed" style="margin:0;font-weight:400">Close slot on this date</label>
            </div>
            <div class="form-group">
                <label>Note (optional)</label>
                <input type="text" class="form-input" id="fExcNote" placeholder="e.g. Public holiday — no deliveries">
            </div>
            <button class="btn-sm btn-sm--primary" style="margin-bottom:18px" onclick="addException()">Add Exception</button>
            <hr style="border:none;border-top:1px solid #ede0e3;margin-bottom:14px">
            <p style="font-size:.8rem;font-weight:700;color:var(--admin-ink);margin:0 0 8px">Existing exceptions for this slot:</p>
            <ul class="exc-list" id="excListEl"><li style="color:var(--admin-muted);font-size:.8rem">Loading…</li></ul>
        </div>
        <div class="modal-foot">
            <button class="btn-sm btn-sm--ghost" onclick="closeModal('exceptionModal')">Done</button>
        </div>
    </div>
</div>

<!-- ================================================================
     EMERGENCY CLOSE MODAL
================================================================ -->
<div class="modal-overlay" id="emergencyModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Emergency Close</h3>
            <button class="modal-close" onclick="closeModal('emergencyModal')">×</button>
        </div>
        <div class="modal-body">
            <p style="font-size:.85rem;color:#7f1d1d;background:#fee2e2;padding:12px;border-radius:8px;margin:0 0 16px">
                This will mark ALL active slots as closed on a specific date, preventing any new bookings.
            </p>
            <div class="form-group">
                <label>Date to close *</label>
                <input type="date" class="form-input" id="fEmergencyDate">
            </div>
            <div class="form-group">
                <label>Reason (optional)</label>
                <input type="text" class="form-input" id="fEmergencyNote" placeholder="e.g. Kitchen emergency closure">
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-sm btn-sm--ghost" onclick="closeModal('emergencyModal')">Cancel</button>
            <button class="btn-sm btn-sm--danger" style="background:#dc2626;color:#fff;border:none"
                    onclick="confirmEmergencyClose()">Close All Slots</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toastEl"></div>

<!-- ================================================================
     JAVASCRIPT
================================================================ -->
<script>
/* ── State ──────────────────────────────────────────────────────── */
let _allSlots  = <?= json_encode(array_values($initSlots)) ?>;
let _viewDate  = '<?= $today ?>';
let _activeTab = 'delivery';
const _holidayWindowDays = 60;
const _csrfToken = (() => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? String(meta.getAttribute('content') || '') : '';
})();

/* ── Helpers ────────────────────────────────────────────────────── */
function toast(msg, type = 'ok') {
    const el = document.getElementById('toastEl');
    el.textContent = msg;
    el.className = 'toast show toast--' + type;
    setTimeout(() => { el.className = 'toast'; }, 3400);
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openModal(id)  { document.getElementById(id).classList.add('open'); }

/* ── Tab switching ──────────────────────────────────────────────── */
function switchTab(tab) {
    _activeTab = tab;
    document.querySelectorAll('.slot-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('grid-delivery').style.display = tab === 'delivery' ? '' : 'none';
    document.getElementById('grid-pickup').style.display   = tab === 'pickup'   ? '' : 'none';
}

/* ── Render slot card (client-side) ─────────────────────────────── */
function renderCard(s) {
    const booked = parseInt(s.booked_today) || 0;
    const cap    = parseInt(s.effective_capacity) || parseInt(s.max_orders) || 1;
    const rem    = Math.max(0, cap - booked);
    const pct    = Math.min(100, Math.round(booked / cap * 100));
    const isFull = booked >= cap;
    const isFast = !isFull && rem < Math.ceil(cap * 0.30);
    const isPaused   = parseInt(s.is_active) === 0;
    const isClosed   = parseInt(s.is_exception_closed) === 1;

    let badge = '';
    if (isClosed)      badge = `<span class="slot-badge slot-badge--closed">🔒 Closed Today</span>`;
    else if (isPaused) badge = `<span class="slot-badge slot-badge--paused">⏸ Paused</span>`;
    else if (isFull)   badge = `<span class="slot-badge slot-badge--full">Full</span>`;
    else if (isFast)   badge = `<span class="slot-badge slot-badge--fast">⚡ Selling Fast</span>`;
    else               badge = `<span class="slot-badge slot-badge--active">Active</span>`;

    const toggleBtn = isPaused
        ? `<button class="btn-sm btn-sm--success" onclick="toggleSlot(${s.id}, true)">▶ Resume</button>`
        : `<button class="btn-sm btn-sm--ghost"   onclick="toggleSlot(${s.id}, false)">⏸ Pause</button>`;

    return `
    <div class="slot-card${isPaused ? ' slot-card--paused' : ''}${isFull ? ' slot-card--full' : ''}"
         id="slotCard-${s.id}" data-type="${s.slot_type}">
        <div class="slot-card__head">
            <div>
                <div class="slot-card__name">${esc(s.slot_name)}</div>
                <div class="slot-card__label">${esc(s.slot_label)}</div>
            </div>
            ${badge}
        </div>
        <div class="slot-time">
            <strong>${fmtTime(s.start_time)}</strong> – <strong>${fmtTime(s.end_time)}</strong>
            &nbsp;·&nbsp; max ${cap} orders
            ${s.prep_buffer_minutes > 0 ? `&nbsp;·&nbsp; ${s.prep_buffer_minutes}min buffer` : ''}
            ${s.cutoff_hour > 0 ? `&nbsp;·&nbsp; cutoff ${s.cutoff_hour}:00` : ''}
        </div>
        ${isClosed ? `<div style="font-size:.78rem;color:#6b7280;font-style:italic">Today's exception: ${esc(s.exception_note || 'Closed')}</div>` : ''}
        <div class="slot-cap-wrap">
            <div class="slot-cap-label">
                <span>Booked today: <strong>${booked}</strong> / ${cap}</span>
                <span style="color:${rem <= 0 ? '#dc2626' : rem <= Math.ceil(cap*0.3) ? '#d97706' : '#166534'}">
                    ${rem > 0 ? rem + ' remaining' : 'FULL'}
                </span>
            </div>
            <div class="slot-cap-bar">
                <div class="slot-cap-bar-fill" style="width:${pct}%"></div>
            </div>
        </div>
        <div class="slot-actions">
            <button class="btn-sm btn-sm--ghost" onclick="openEditModal(${s.id})">Edit</button>
            ${toggleBtn}
            <button class="btn-sm btn-sm--ghost" onclick="openExceptionModal(${s.id})">Exceptions</button>
        </div>
    </div>`;
}

function fmtTime(t) {
    if (!t) return '';
    const parts = t.split(':');
    let h = parseInt(parts[0]), m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    if (h > 12) h -= 12; else if (h === 0) h = 12;
    return `${h}:${m} ${ampm}`;
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Refresh usage for a date ───────────────────────────────────── */
async function refreshUsage() {
    const date = document.getElementById('usageDatePicker').value;
    if (!date) return;
    _viewDate = date;
    document.getElementById('usageStatus').textContent = 'Refreshing…';
    try {
        const r   = await fetch(`/api/admin/slots/usage?date=${date}`, {
            headers: _csrfToken ? { 'X-CSRF-Token': _csrfToken } : {}
        });
        const obj = await r.json();
        if (!obj.success) throw new Error(obj.message);
        _allSlots = obj.data.slots.map(s => ({
            ...s,
            booked_today: s.booked_count,
            effective_capacity: s.effective_capacity,
        }));
        rerenderGrids();
        await loadHolidayList();
        document.getElementById('usageStatus').textContent = 'Updated at ' + new Date().toLocaleTimeString();
    } catch (e) {
        document.getElementById('usageStatus').textContent = 'Error: ' + e.message;
        toast('Refresh failed: ' + e.message, 'err');
    }
}

function rerenderGrids() {
    const delivery = _allSlots.filter(s => s.slot_type === 'delivery');
    const pickup   = _allSlots.filter(s => s.slot_type === 'pickup');
    document.getElementById('grid-delivery').innerHTML = delivery.length
        ? delivery.map(renderCard).join('')
        : '<p style="color:var(--admin-muted);font-size:.85rem">No delivery slots defined yet.</p>';
    document.getElementById('grid-pickup').innerHTML = pickup.length
        ? pickup.map(renderCard).join('')
        : '<p style="color:var(--admin-muted);font-size:.85rem">No pickup slots defined yet.</p>';
    document.getElementById('tabCount-delivery').textContent = `(${delivery.length})`;
    document.getElementById('tabCount-pickup').textContent   = `(${pickup.length})`;
}

function clearHolidayForm() {
    document.getElementById('holidayDate').value = _viewDate;
    document.getElementById('holidayType').value = 'all';
    document.getElementById('holidayNote').value = '';
}

async function loadHolidayList() {
    const listEl = document.getElementById('holidayListEl');
    if (!listEl) return;
    listEl.innerHTML = '<li style="color:var(--admin-muted);font-size:.8rem">Loading holidays...</li>';

    const from = _viewDate;
    const toDate = new Date(from + 'T00:00:00');
    toDate.setDate(toDate.getDate() + _holidayWindowDays);
    const to = toDate.toISOString().slice(0, 10);

    try {
        const resp = await fetch(`/api/admin/holidays?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&slot_type=all`, {
            headers: _csrfToken ? { 'X-CSRF-Token': _csrfToken } : {}
        });
        const payload = await resp.json();
        if (!payload.success) {
            throw new Error(payload.message || 'Unable to load holidays');
        }

        const entries = payload.data && Array.isArray(payload.data.entries) ? payload.data.entries : [];
        if (entries.length === 0) {
            listEl.innerHTML = '<li style="color:var(--admin-muted);font-size:.8rem">No holiday closures in the current window.</li>';
            return;
        }

        listEl.innerHTML = entries.map((entry) => {
            const date = String(entry.exception_date || '');
            const type = String(entry.slot_type || 'all');
            const note = String(entry.note || '').trim();
            const affected = parseInt(entry.affected_slots || 0, 10) || 0;
            const isEmergency = /emergency/i.test(note);
            const noteText = note !== '' ? esc(note) : 'No note';
            return `
                <li class="holiday-item">
                    <div>
                        <div>
                            <strong>${esc(date)}</strong> - ${esc(type)}
                            ${isEmergency ? '<span class="holiday-tag">Emergency</span>' : ''}
                        </div>
                        <div class="holiday-meta">${affected} slot(s) closed - ${noteText}</div>
                    </div>
                    <button class="holiday-remove" onclick="deleteHoliday('${esc(date)}','${esc(type)}')">Remove</button>
                </li>
            `;
        }).join('');
    } catch (e) {
        listEl.innerHTML = `<li style="color:#991b1b;font-size:.8rem">Failed to load holidays: ${esc(e.message || 'Unknown error')}</li>`;
    }
}

async function addHoliday() {
    const holidayDate = document.getElementById('holidayDate').value;
    const slotType = document.getElementById('holidayType').value;
    const note = document.getElementById('holidayNote').value.trim();

    if (!holidayDate) {
        toast('Please select a holiday date.', 'err');
        return;
    }

    try {
        const payload = await apiFetch('/api/admin/holidays', 'POST', {
            holiday_date: holidayDate,
            slot_type: slotType,
            note: note || null,
        });
        if (!payload.success) {
            throw new Error(payload.message || 'Unable to add holiday');
        }
        toast(payload.message || 'Holiday closure saved.', 'ok');
        clearHolidayForm();
        await refreshUsage();
    } catch (e) {
        toast('Holiday add failed: ' + e.message, 'err');
    }
}

async function deleteHoliday(holidayDate, slotType) {
    if (!holidayDate) return;
    if (!confirm(`Remove holiday closure on ${holidayDate} (${slotType})?`)) return;

    try {
        const resp = await fetch(`/api/admin/holidays?holiday_date=${encodeURIComponent(holidayDate)}&slot_type=${encodeURIComponent(slotType)}`, {
            method: 'DELETE',
            headers: _csrfToken ? { 'X-CSRF-Token': _csrfToken } : {}
        });
        const payload = await resp.json();
        if (!payload.success) {
            throw new Error(payload.message || 'Unable to remove holiday');
        }
        toast(payload.message || 'Holiday closure removed.', 'ok');
        await refreshUsage();
    } catch (e) {
        toast('Holiday remove failed: ' + e.message, 'err');
    }
}

/* ── Create modal ───────────────────────────────────────────────── */
function openCreateModal() {
    document.getElementById('slotModalTitle').textContent = 'Add New Slot';
    document.getElementById('editSlotId').value   = '';
    document.getElementById('fSlotType').value    = 'delivery';
    document.getElementById('fSlotName').value    = '';
    document.getElementById('fSlotLabel').value   = '';
    document.getElementById('fStartTime').value   = '';
    document.getElementById('fEndTime').value     = '';
    document.getElementById('fMaxOrders').value   = '20';
    document.getElementById('fPrepBuffer').value  = '90';
    document.getElementById('fCutoffHour').value  = '0';
    document.getElementById('fDisplayOrder').value= '0';
    document.getElementById('fIsRecommended').checked = false;
    openModal('slotModal');
}

function openEditModal(id) {
    const s = _allSlots.find(x => parseInt(x.id) === id);
    if (!s) return;
    document.getElementById('slotModalTitle').textContent = 'Edit Slot';
    document.getElementById('editSlotId').value   = s.id;
    document.getElementById('fSlotType').value    = s.slot_type;
    document.getElementById('fSlotName').value    = s.slot_name;
    document.getElementById('fSlotLabel').value   = s.slot_label;
    document.getElementById('fStartTime').value   = s.start_time ? s.start_time.substring(0,5) : '';
    document.getElementById('fEndTime').value     = s.end_time   ? s.end_time.substring(0,5)   : '';
    document.getElementById('fMaxOrders').value   = s.max_orders;
    document.getElementById('fPrepBuffer').value  = s.prep_buffer_minutes;
    document.getElementById('fCutoffHour').value  = s.cutoff_hour || 0;
    document.getElementById('fDisplayOrder').value= s.display_order;
    document.getElementById('fIsRecommended').checked = parseInt(s.is_recommended) === 1;
    openModal('slotModal');
}

async function saveSlot() {
    const id = document.getElementById('editSlotId').value;
    const payload = {
        slot_type:          document.getElementById('fSlotType').value,
        slot_name:          document.getElementById('fSlotName').value.trim(),
        slot_label:         document.getElementById('fSlotLabel').value.trim(),
        start_time:         document.getElementById('fStartTime').value,
        end_time:           document.getElementById('fEndTime').value,
        max_orders:         parseInt(document.getElementById('fMaxOrders').value) || 20,
        prep_buffer_minutes:parseInt(document.getElementById('fPrepBuffer').value) || 90,
        cutoff_hour:        parseInt(document.getElementById('fCutoffHour').value) || 0,
        display_order:      parseInt(document.getElementById('fDisplayOrder').value) || 0,
        is_recommended:     document.getElementById('fIsRecommended').checked ? 1 : 0,
    };
    if (!payload.slot_name || !payload.slot_label || !payload.start_time || !payload.end_time) {
        toast('Please fill in all required fields.', 'err');
        return;
    }
    try {
        const url    = id ? `/api/admin/slots/${id}` : '/api/admin/slots';
        const method = id ? 'PATCH' : 'POST';
        const r      = await apiFetch(url, method, payload);
        if (!r.success) throw new Error(r.message);
        toast(r.message || 'Saved!', 'ok');
        closeModal('slotModal');
        await refreshUsage();
    } catch (e) { toast('Save failed: ' + e.message, 'err'); }
}

/* ── Toggle slot active ─────────────────────────────────────────── */
async function toggleSlot(id, active) {
    try {
        const r = await apiFetch(`/api/admin/slots/${id}/toggle`, 'POST', { is_active: active });
        if (!r.success) throw new Error(r.message);
        toast(r.message, 'ok');
        await refreshUsage();
    } catch (e) { toast('Error: ' + e.message, 'err'); }
}

/* ── Exception modal ────────────────────────────────────────────── */
async function openExceptionModal(slotId) {
    document.getElementById('excSlotId').value = slotId;
    const s = _allSlots.find(x => parseInt(x.id) === slotId);
    document.getElementById('excModalTitle').textContent =
        'Exceptions — ' + (s ? s.slot_name : slotId);
    document.getElementById('fExcDate').value     = _viewDate;
    document.getElementById('fExcCapacity').value = '';
    document.getElementById('fExcIsClosed').checked = false;
    document.getElementById('fExcNote').value     = '';
    openModal('exceptionModal');
    await loadExceptionList(slotId);
}

async function loadExceptionList(slotId) {
    const el = document.getElementById('excListEl');
    el.innerHTML = '<li style="color:var(--admin-muted);font-size:.8rem">Loading…</li>';
    try {
        const r = await fetch('/api/admin/slots/' + slotId + '/exceptions', {
            headers: _csrfToken ? { 'X-CSRF-Token': _csrfToken } : {}
        });
        const obj = await r.json();
        // Currently SlotService.listExceptions is called via a future GET route;
        // fall back to empty list if not yet wired
        const list = obj.data?.exceptions ?? [];
        if (list.length === 0) {
            el.innerHTML = '<li style="color:var(--admin-muted);font-size:.8rem">No exceptions set.</li>';
            return;
        }
        el.innerHTML = list.map(ex => `
            <li>
                <span>${esc(ex.exception_date)} —
                    ${parseInt(ex.is_closed) ? '<strong>Closed</strong>' : 'Cap: ' + (ex.override_capacity ?? '—')}
                    ${ex.note ? ' · ' + esc(ex.note) : ''}
                </span>
                <button class="exc-del" title="Remove" onclick="deleteException(${slotId}, '${esc(ex.exception_date)}')">×</button>
            </li>`).join('');
    } catch (e) {
        el.innerHTML = `<li style="color:var(--admin-muted);font-size:.8rem">Could not load exceptions.</li>`;
    }
}

async function addException() {
    const slotId = document.getElementById('excSlotId').value;
    const payload = {
        exception_date:    document.getElementById('fExcDate').value,
        override_capacity: document.getElementById('fExcCapacity').value || null,
        is_closed:         document.getElementById('fExcIsClosed').checked ? 1 : 0,
        note:              document.getElementById('fExcNote').value.trim() || null,
    };
    if (!payload.exception_date) { toast('Please pick a date.', 'err'); return; }
    try {
        const r = await apiFetch(`/api/admin/slots/${slotId}/exceptions`, 'POST', payload);
        if (!r.success) throw new Error(r.message);
        toast('Exception saved.', 'ok');
        await loadExceptionList(slotId);
        await refreshUsage();
    } catch (e) { toast('Error: ' + e.message, 'err'); }
}

async function deleteException(slotId, date) {
    if (!confirm('Remove exception for ' + date + '?')) return;
    try {
        const r = await apiFetch(
            `/api/admin/slot-exceptions?slot_id=${slotId}&date=${date}`, 'DELETE', null);
        if (!r.success) throw new Error(r.message);
        toast('Exception removed.', 'ok');
        await loadExceptionList(slotId);
        await refreshUsage();
    } catch (e) { toast('Error: ' + e.message, 'err'); }
}

/* ── Emergency close ────────────────────────────────────────────── */
function openEmergencyModal() {
    document.getElementById('fEmergencyDate').value = _viewDate;
    document.getElementById('fEmergencyNote').value = '';
    openModal('emergencyModal');
}

async function confirmEmergencyClose() {
    const date = document.getElementById('fEmergencyDate').value;
    const note = document.getElementById('fEmergencyNote').value.trim() || 'Emergency closure';
    if (!date) { toast('Please pick a date.', 'err'); return; }
    if (!confirm(`Close ALL active slots on ${date}? This cannot be undone automatically.`)) return;

    try {
        const r = await apiFetch('/api/admin/holidays', 'POST', {
            holiday_date: date,
            slot_type: 'all',
            note,
        });
        if (!r.success) throw new Error(r.message || 'Emergency close failed');
        const affected = parseInt(r.data && r.data.affected_slots ? r.data.affected_slots : 0, 10) || 0;
        toast(`Emergency close applied to ${affected} slot(s).`, 'ok');
    } catch (e) {
        toast('Emergency close failed: ' + e.message, 'err');
        return;
    }

    closeModal('emergencyModal');
    await refreshUsage();
}

/* ── Generic API helper ─────────────────────────────────────────── */
async function apiFetch(url, method, body) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            ...(_csrfToken ? { 'X-CSRF-Token': _csrfToken } : {}),
        },
    };
    if (body !== null && body !== undefined) {
        const payload = (typeof body === 'object' && body !== null)
            ? { ...body, _csrf: _csrfToken }
            : body;
        opts.body = JSON.stringify(payload);
    }
    const r   = await fetch(url, opts);
    const obj = await r.json();
    return obj;
}

/* ── Auto-refresh every 60 s ────────────────────────────────────── */
setInterval(refreshUsage, 60000);

document.addEventListener('DOMContentLoaded', () => {
    clearHolidayForm();
    loadHolidayList();
});
</script>

<?php
/* ── Server-side card renderer (initial SSR) ───────────────────── */
function renderSlotCard(array $s): string {
    $id      = (int)$s['id'];
    $name    = htmlspecialchars($s['slot_name'],   ENT_QUOTES);
    $label   = htmlspecialchars($s['slot_label'],  ENT_QUOTES);
    $cap     = max(1, (int)$s['effective_capacity']);
    $booked  = (int)$s['booked_today'];
    $rem     = max(0, $cap - $booked);
    $pct     = min(100, (int)round($booked / $cap * 100));
    $isFull  = $booked >= $cap;
    $isFast  = !$isFull && $rem < ceil($cap * 0.3);
    $isPaused= (int)$s['is_active'] === 0;
    $isClosed= (int)($s['is_exception_closed'] ?? 0) === 1;

    $startFmt = date('g:i A', strtotime($s['start_time']));
    $endFmt   = date('g:i A', strtotime($s['end_time']));

    if ($isClosed)      $badge = '<span class="slot-badge slot-badge--closed">🔒 Closed Today</span>';
    elseif ($isPaused)  $badge = '<span class="slot-badge slot-badge--paused">⏸ Paused</span>';
    elseif ($isFull)    $badge = '<span class="slot-badge slot-badge--full">Full</span>';
    elseif ($isFast)    $badge = '<span class="slot-badge slot-badge--fast">⚡ Selling Fast</span>';
    else                $badge = '<span class="slot-badge slot-badge--active">Active</span>';

    $toggleBtn = $isPaused
        ? "<button class=\"btn-sm btn-sm--success\" onclick=\"toggleSlot($id,true)\">▶ Resume</button>"
        : "<button class=\"btn-sm btn-sm--ghost\"   onclick=\"toggleSlot($id,false)\">⏸ Pause</button>";

    $pausedClass = $isPaused ? ' slot-card--paused' : '';
    $fullClass   = $isFull   ? ' slot-card--full'   : '';
    $excNote     = $isClosed ? '<div style="font-size:.78rem;color:#6b7280;font-style:italic">Today\'s exception: '
                               . htmlspecialchars($s['exception_note'] ?? 'Closed', ENT_QUOTES) . '</div>' : '';

    $remColor = $rem <= 0 ? '#dc2626' : ($rem <= ceil($cap*0.3) ? '#d97706' : '#166534');
    $remText  = $rem > 0 ? "$rem remaining" : 'FULL';

    return "
    <div class=\"slot-card{$pausedClass}{$fullClass}\" id=\"slotCard-{$id}\" data-type=\"{$s['slot_type']}\">
        <div class=\"slot-card__head\">
            <div>
                <div class=\"slot-card__name\">{$name}</div>
                <div class=\"slot-card__label\">{$label}</div>
            </div>
            {$badge}
        </div>
        <div class=\"slot-time\">
            <strong>{$startFmt}</strong> – <strong>{$endFmt}</strong>
            &nbsp;·&nbsp; max {$cap} orders
        </div>
        {$excNote}
        <div class=\"slot-cap-wrap\">
            <div class=\"slot-cap-label\">
                <span>Booked today: <strong>{$booked}</strong> / {$cap}</span>
                <span style=\"color:{$remColor}\">{$remText}</span>
            </div>
            <div class=\"slot-cap-bar\">
                <div class=\"slot-cap-bar-fill\" style=\"width:{$pct}%\"></div>
            </div>
        </div>
        <div class=\"slot-actions\">
            <button class=\"btn-sm btn-sm--ghost\" onclick=\"openEditModal({$id})\">Edit</button>
            {$toggleBtn}
            <button class=\"btn-sm btn-sm--ghost\" onclick=\"openExceptionModal({$id})\">Exceptions</button>
        </div>
    </div>";
}
?>
