<?php
$pageTitle = 'CRM Settings';
include 'layout.php';

require_once __DIR__ . '/includes/crm_settings_helpers.php';

$rows = fetch_crm_settings($conn);
$flash = isset($_GET['status']) ? $_GET['status'] : '';
?>
<style>
.crm-settings-shell {
    display: grid;
    gap: 22px;
}
.crm-settings-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(320px, 0.85fr);
    gap: 22px;
}
.crm-settings-stack {
    display: grid;
    gap: 22px;
}
.crm-settings-card {
    background: var(--admin-surface, #fffdfd);
    border-radius: 22px;
    box-shadow: 0 14px 30px rgba(96, 18, 45, 0.08);
    border: 1px solid rgba(128, 0, 31, 0.10);
    overflow: hidden;
}
.crm-settings-card__head {
    padding: 24px 28px 10px 28px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
}
.crm-settings-card__head h2 {
    margin: 0 0 6px 0;
    font-family: 'DM Serif Display', Georgia, serif;
    color: var(--admin-burgundy, #80001F);
    font-size: 1.45rem;
    font-weight: 400;
}
.crm-settings-card__desc {
    color: #8f7681;
    font-size: 0.98rem;
}
.crm-settings-form {
    padding: 22px 28px 24px 28px;
    display: grid;
    gap: 16px;
}
.crm-settings-form label {
    font-size: 0.97rem;
    color: #6e2a3e;
    font-weight: 600;
    margin-bottom: 6px;
    display: block;
}
.crm-settings-input,
.crm-settings-form input[type="text"],
.crm-settings-form input[type="url"],
.crm-settings-form input[type="password"],
.crm-settings-form select {
    width: 100%;
    min-height: 44px;
    border-radius: 11px;
    border: 1px solid rgba(128, 0, 31, 0.16);
    background: linear-gradient(180deg, #fffefe, #fff8fa);
    color: #432530;
    padding: 0 12px;
    font: inherit;
    font-size: 1rem;
    box-sizing: border-box;
}
.crm-settings-input--configured {
    border: 2px solid #1f9d55;
    background: #edf9f1;
}
.crm-settings-input--missing {
    border: 2px solid #d64545;
    background: #fff4f4;
}
.crm-settings-status {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 0.82rem;
    font-weight: 600;
}
.crm-settings-status--configured {
    background: #edf9f1;
    color: #1f7a44;
}
.crm-settings-status--missing {
    background: #fff3f3;
    color: #b03a2e;
}
.crm-switch {
    display: flex;
    align-items: center;
    gap: 10px;
}
.crm-switch input[type="checkbox"] {
    width: 38px;
    height: 22px;
    appearance: none;
    background: #f8d8de;
    border-radius: 999px;
    position: relative;
    cursor: pointer;
    border: 1.5px solid #e7b1c0;
}
.crm-switch input[type="checkbox"]:checked {
    background: var(--admin-burgundy, #80001F);
    border-color: var(--admin-burgundy, #80001F);
}
.crm-switch input[type="checkbox"]::before {
    content: '';
    position: absolute;
    top: 2.5px;
    left: 3px;
    width: 17px;
    height: 17px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
}
.crm-switch input[type="checkbox"]:checked::before {
    transform: translateX(15px);
}
.crm-settings-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.crm-btn,
.crm-btn:link,
.crm-btn:visited {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 12px;
    min-height: 44px;
    padding: 0 16px;
    background: var(--admin-burgundy, #80001F);
    color: #fff;
    text-decoration: none;
    font-size: 0.98rem;
    font-weight: 600;
    cursor: pointer;
}
.crm-btn--secondary {
    background: #f8d8de;
    color: #80001F;
}
.crm-flash {
    border-radius: 16px;
    padding: 14px 16px;
    font-size: 0.95rem;
    border: 1px solid #ccead4;
    background: #edf9f1;
    color: #1f6b3d;
}
.crm-helper-list {
    display: grid;
    gap: 10px;
}
.crm-helper-chip {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid rgba(128, 0, 31, 0.08);
    border-radius: 12px;
    padding: 10px 12px;
    background: #fff8fa;
    color: #6e2a3e;
    font-size: 0.9rem;
}
.crm-helper-chip strong {
    color: #80001F;
}
.crm-inline-note {
    margin: 0;
    color: #8f7681;
    font-size: 0.88rem;
}
.crm-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(26, 6, 13, 0.36);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 18px;
}
.crm-modal__panel {
    width: min(460px, 100%);
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 16px 36px rgba(38, 10, 18, 0.18);
    padding: 24px;
}
.crm-modal__log {
    margin-top: 16px;
    max-height: 240px;
    overflow: auto;
    border-radius: 12px;
    background: #fff8fa;
    border: 1px solid rgba(128, 0, 31, 0.08);
    padding: 12px;
    color: #5e3a45;
}
@media (max-width: 900px) {
    .crm-settings-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 700px) {
    .crm-settings-card__head,
    .crm-settings-form {
        padding-left: 16px;
        padding-right: 16px;
    }
}
</style>

<div class="crm-settings-shell">
    <?php if ($flash === 'saved'): ?>
        <div class="crm-flash">CRM settings saved.</div>
    <?php elseif ($flash === 'reset'): ?>
        <div class="crm-flash">CRM token reset.</div>
    <?php endif; ?>

    <div class="crm-settings-grid">
        <div class="crm-settings-stack">
            <section class="crm-settings-card">
                <div class="crm-settings-card__head">
                    <h2>CRM Sync Settings</h2>
                    <div class="crm-settings-card__desc">The token is never shown back in the browser. Leave the token field empty to keep the current token, or use Reset to remove it.</div>
                </div>

                <?php foreach ($rows as $row): ?>
                    <?php $configured = is_crm_token_configured($row); ?>
                    <form class="crm-settings-form" method="post" action="update_crm_settings.php">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                        <div>
                            <label>Setting</label>
                            <input class="crm-settings-input" type="text" value="<?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['setting_key']))) ?>" readonly>
                        </div>

                        <div>
                            <label for="endpoint_<?= (int) $row['id'] ?>">Endpoint URL</label>
                            <input type="url" id="endpoint_<?= (int) $row['id'] ?>" name="endpoint" value="<?= htmlspecialchars($row['endpoint']) ?>" required>
                        </div>

                        <div>
                            <label for="api_token_<?= (int) $row['id'] ?>">API Token</label>
                            <input type="password" id="api_token_<?= (int) $row['id'] ?>" name="api_token" value="" placeholder="<?= $configured ? 'Configured. Enter a new token only to replace it.' : 'Not configured. Enter token here.' ?>" autocomplete="new-password" class="crm-settings-input <?= $configured ? 'crm-settings-input--configured' : 'crm-settings-input--missing' ?>">
                            <div style="margin-top:8px;">
                                <span class="crm-settings-status <?= $configured ? 'crm-settings-status--configured' : 'crm-settings-status--missing' ?>"><?= $configured ? 'Token configured' : 'Token not configured' ?></span>
                            </div>
                        </div>

                        <div class="crm-switch">
                            <input type="checkbox" id="is_enabled_<?= (int) $row['id'] ?>" name="is_enabled" value="1" <?= !empty($row['is_enabled']) ? 'checked' : '' ?>>
                            <label for="is_enabled_<?= (int) $row['id'] ?>">Enabled</label>
                        </div>

                        <div class="crm-settings-actions">
                            <button type="submit" class="crm-btn">Save Settings</button>
                            <button type="button" class="crm-btn crm-btn--secondary" onclick="resetToken(<?= (int) $row['id'] ?>)">Reset</button>
                            <button type="button" class="crm-btn crm-btn--secondary" onclick="openTestPush(<?= (int) $row['id'] ?>, '<?= htmlspecialchars($row['setting_key']) ?>')">Test Push</button>
                            <a class="crm-btn crm-btn--secondary" href="crm_push_logs.php">View Logs</a>
                        </div>
                    </form>
                <?php endforeach; ?>
            </section>

        </div>

        <section class="crm-settings-card">
            <div class="crm-settings-card__head">
                <h2>Trigger Variable Guide</h2>
                <div class="crm-settings-card__desc">Use these variables inside CRM trigger definitions. Name, mobile, and email are compulsory for every push.</div>
            </div>
            <div class="crm-settings-form">
                <div class="crm-helper-list">
                    <div class="crm-helper-chip"><strong>contact.name</strong><span>Full customer name</span></div>
                    <div class="crm-helper-chip"><strong>contact.first_name</strong><span>Customer first name</span></div>
                    <div class="crm-helper-chip"><strong>contact.mobile</strong><span>Customer mobile number</span></div>
                    <div class="crm-helper-chip"><strong>contact.email</strong><span>Customer email address</span></div>
                    <div class="crm-helper-chip"><strong>contact.orderid</strong><span>Order number</span></div>
                    <div class="crm-helper-chip"><strong>contact.item</strong><span>Ordered item summary</span></div>
                    <div class="crm-helper-chip"><strong>contact.amount</strong><span>Order amount</span></div>
                    <div class="crm-helper-chip"><strong>contact.upi_link</strong><span>Payment link or UPI link</span></div>
                </div>
                <p class="crm-inline-note">Recommended CRM trigger keys: <strong>follow_up_review</strong> for recurring quarterly follow-ups and <strong>annual_reorder</strong> for yearly pre-celebration reminders.</p>
            </div>
        </section>
    </div>
</div>

<div class="crm-modal" id="crmTestModal">
    <div class="crm-modal__panel">
        <h3 style="margin-top:0;color:#80001F;font-family:'DM Serif Display', Georgia, serif;font-weight:400;">Test Push to CRM</h3>
        <p id="crmTestModalLabel" style="margin-top:0;color:#8f7681;"></p>
        <form id="crmTestForm" class="crm-settings-form" style="padding:0;">
            <input type="hidden" name="crm_id" id="crmTestCrmId" value="">
            <div>
                <label for="crm_test_name">Name</label>
                <input type="text" id="crm_test_name" name="name" required>
            </div>
            <div>
                <label for="crm_test_mobile">Mobile</label>
                <input type="text" id="crm_test_mobile" name="mobile" required>
            </div>
            <div class="crm-settings-actions">
                <button type="submit" class="crm-btn">Run Test Push</button>
                <button type="button" class="crm-btn crm-btn--secondary" onclick="closeTestPush()">Close</button>
            </div>
        </form>
        <div class="crm-modal__log" id="crmTestLog">Run a test push to see the result and recent logs here.</div>
    </div>
</div>

<script>
function resetToken(id) {
    if (!window.confirm('Reset the configured CRM token?')) {
        return;
    }

    fetch('update_crm_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + encodeURIComponent(id) + '&reset_token=1'
    }).then(function () {
        window.location.href = 'crm_settings.php?status=reset';
    });
}

function openTestPush(id, key) {
    document.getElementById('crmTestForm').reset();
    document.getElementById('crmTestCrmId').value = id;
    document.getElementById('crmTestModalLabel').textContent = 'Testing setting: ' + key;
    document.getElementById('crmTestLog').innerHTML = 'Run a test push to see the result and recent logs here.';
    document.getElementById('crmTestModal').style.display = 'flex';
}

function closeTestPush() {
    document.getElementById('crmTestModal').style.display = 'none';
}

document.getElementById('crmTestForm').addEventListener('submit', function (event) {
    event.preventDefault();
    document.getElementById('crmTestLog').innerHTML = 'Sending test push...';
    fetch('test_crm_push.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(function (response) {
        return response.text();
    })
    .then(function (html) {
        document.getElementById('crmTestLog').innerHTML = html;
    })
    .catch(function () {
        document.getElementById('crmTestLog').innerHTML = '<span style="color:#b03a2e;">Test push failed before a response was received.</span>';
    });
});
</script>