<?php
$pageTitle = 'Follow Ups';
require_once __DIR__ . '/layout.php';

require_once __DIR__ . '/includes/crm_settings_helpers.php';

$followUpSettings = fetch_crm_follow_up_settings($conn);
$recentLogs = fetch_crm_push_logs($conn, 12);
$flash = isset($_GET['status']) ? (string) $_GET['status'] : '';
?>
<style>
.follow-ups-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
    gap: 22px;
}
.follow-ups-card {
    background: var(--admin-surface, #fffdfd);
    border-radius: 18px;
    border: 1px solid rgba(128, 0, 31, 0.1);
    box-shadow: 0 14px 30px rgba(96, 18, 45, 0.08);
    overflow: hidden;
}
.follow-ups-card__head {
    padding: 22px 24px 12px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    background: linear-gradient(180deg, #fff8fa 0%, #fff 100%);
}
.follow-ups-card__head h2,
.follow-ups-card__head h3 {
    margin: 0;
    color: #80001F;
    font-family: 'DM Serif Display', Georgia, serif;
    font-weight: 400;
}
.follow-ups-card__body {
    padding: 22px 24px;
}
.follow-ups-note {
    margin: 0;
    color: #8f7681;
}
.follow-ups-kpis {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.follow-ups-kpi {
    border-radius: 14px;
    background: #fff8fa;
    border: 1px solid rgba(128, 0, 31, 0.08);
    padding: 14px;
}
.follow-ups-kpi strong {
    display: block;
    color: #80001F;
    margin-bottom: 4px;
}
.follow-ups-kpi span {
    color: #6e2a3e;
}
.follow-ups-list {
    display: grid;
    gap: 10px;
}
.follow-ups-log {
    border: 1px solid rgba(128, 0, 31, 0.08);
    border-radius: 12px;
    padding: 12px;
    background: #fff8fa;
}
.follow-ups-log strong {
    color: #80001F;
}
.follow-ups-log p {
    margin: 6px 0 0;
    color: #6e2a3e;
    font-size: 0.9rem;
}
.follow-ups-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}
.follow-ups-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 14px;
    border-radius: 12px;
    background: #80001F;
    color: #fff;
    text-decoration: none;
    font-weight: 600;
}
.follow-ups-btn--ghost {
    background: #f8d8de;
    color: #80001F;
}
.follow-ups-flash {
    border-radius: 14px;
    border: 1px solid #ccead4;
    background: #edf9f1;
    color: #1f6b3d;
    padding: 12px 14px;
    margin-bottom: 16px;
}
.follow-ups-form {
    display: grid;
    gap: 12px;
}
.follow-ups-form label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #6e2a3e;
}
.follow-ups-form input,
.follow-ups-form select {
    width: 100%;
    min-height: 42px;
    border-radius: 10px;
    border: 1px solid rgba(128, 0, 31, 0.16);
    padding: 0 12px;
    box-sizing: border-box;
}
.follow-ups-inline-note {
    margin: 6px 0 0;
    color: #8f7681;
    font-size: 0.88rem;
}
@media (max-width: 960px) {
    .follow-ups-grid,
    .follow-ups-kpis {
        grid-template-columns: 1fr;
    }
}
</style>

<?php if ($flash === 'follow_up_saved'): ?>
    <div class="follow-ups-flash">Follow-Up Trigger Settings saved.</div>
<?php endif; ?>

<div class="follow-ups-grid">
    <section class="follow-ups-card">
        <div class="follow-ups-card__head">
            <h2>Follow-Up Automation</h2>
        </div>
        <div class="follow-ups-card__body">
            <div class="follow-ups-actions">
                <a class="follow-ups-btn" href="crm_settings.php">Open CRM Settings</a>
                <a class="follow-ups-btn follow-ups-btn--ghost" href="crm_push_logs.php">Open CRM Push Logs</a>
            </div>
        </div>
    </section>

    <section class="follow-ups-card">
        <div class="follow-ups-card__head">
            <h3>Follow-Up Trigger Settings</h3>
        </div>
        <div class="follow-ups-card__body">
            <form class="follow-ups-form" method="post" action="update_crm_settings.php">
                <input type="hidden" name="settings_form" value="follow_up_settings">

                <div>
                    <label for="google_review_link">Google review link</label>
                    <input type="url" id="google_review_link" name="google_review_link" value="<?= htmlspecialchars($followUpSettings['google_review_link']) ?>" placeholder="https://www.google.com/search?...#lrd=...,3,,,">
                    <p class="follow-ups-inline-note">Use direct review deep-link so customers land directly in the Google review flow.</p>
                </div>

                <div>
                    <label for="review_delay_days">Review follow-up delay in days</label>
                    <input type="text" id="review_delay_days" name="review_delay_days" value="<?= htmlspecialchars($followUpSettings['review_delay_days']) ?>">
                </div>

                <div>
                    <label for="quarterly_follow_up_interval_months">Delivered-order follow-up interval in months</label>
                    <input type="text" id="quarterly_follow_up_interval_months" name="quarterly_follow_up_interval_months" value="<?= htmlspecialchars($followUpSettings['quarterly_follow_up_interval_months'] ?? '3') ?>">
                    <p class="follow-ups-inline-note">Default: every 3 months after an order is delivered.</p>
                </div>

                <div>
                    <label for="annual_reminder_days_before">Annual celebration reminder lead days</label>
                    <input type="text" id="annual_reminder_days_before" name="annual_reminder_days_before" value="<?= htmlspecialchars($followUpSettings['annual_reminder_days_before']) ?>">
                    <p class="follow-ups-inline-note">Recommended: 7 days before the yearly celebration date.</p>
                </div>

                <div>
                    <label for="annual_reminder_basis">Annual reminder basis</label>
                    <select id="annual_reminder_basis" name="annual_reminder_basis">
                        <option value="last_completed_order" <?= $followUpSettings['annual_reminder_basis'] === 'last_completed_order' ? 'selected' : '' ?>>Last completed order anniversary</option>
                        <option value="customer_profile_date" <?= $followUpSettings['annual_reminder_basis'] === 'customer_profile_date' ? 'selected' : '' ?>>Customer profile date</option>
                    </select>
                </div>

                <div>
                    <label for="celebration_reminder_days_before">Birthday/anniversary preorder lead days</label>
                    <input type="text" id="celebration_reminder_days_before" name="celebration_reminder_days_before" value="<?= htmlspecialchars($followUpSettings['celebration_reminder_days_before'] ?? '7') ?>">
                    <p class="follow-ups-inline-note">Default: send preorder reminder 7 days before DOB/DOA date.</p>
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="celebration_combined_email_on_same_day" value="1" <?= ($followUpSettings['celebration_combined_email_on_same_day'] ?? '1') === '1' ? 'checked' : '' ?>>
                        Send one combined email when birthday and anniversary fall on the same date
                    </label>
                </div>

                <div>
                    <label for="whatsapp_send_mode">WhatsApp send mode</label>
                    <select id="whatsapp_send_mode" name="whatsapp_send_mode">
                        <option value="crm_trigger" <?= $followUpSettings['whatsapp_send_mode'] === 'crm_trigger' ? 'selected' : '' ?>>CRM trigger push</option>
                        <option value="internal_whatsapp" <?= $followUpSettings['whatsapp_send_mode'] === 'internal_whatsapp' ? 'selected' : '' ?>>Internal WhatsApp</option>
                    </select>
                    <p class="follow-ups-inline-note">Recommended default: CRM trigger push.</p>
                </div>

                <div>
                    <label for="crm_queue_push_mode">CRM queue trigger execution mode</label>
                    <select id="crm_queue_push_mode" name="crm_queue_push_mode">
                        <option value="paused" <?= $followUpSettings['crm_queue_push_mode'] === 'paused' ? 'selected' : '' ?>>Paused (skip queued CRM trigger jobs)</option>
                        <option value="enabled" <?= $followUpSettings['crm_queue_push_mode'] === 'enabled' ? 'selected' : '' ?>>Enabled (process queued CRM trigger jobs)</option>
                    </select>
                    <p class="follow-ups-inline-note">Keep this on paused until the external endpoint issue is fixed. Switch to enabled to re-activate CRM trigger job execution.</p>
                </div>

                <div>
                    <label for="required_fields_note">Required fields note</label>
                    <input type="text" id="required_fields_note" name="required_fields_note" value="<?= htmlspecialchars($followUpSettings['required_fields_note']) ?>">
                </div>

                <div class="follow-ups-actions">
                    <button type="submit" class="follow-ups-btn">Save Follow-Up Trigger Settings</button>
                    <a class="follow-ups-btn follow-ups-btn--ghost" href="<?= htmlspecialchars($followUpSettings['google_review_link']) !== '' ? $followUpSettings['google_review_link'] : 'follow_ups.php' ?>" <?= htmlspecialchars($followUpSettings['google_review_link']) !== '' ? 'target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($followUpSettings['google_review_link']) !== '' ? 'Open Review Link' : 'Set Review Link' ?></a>
                </div>
            </form>
        </div>
    </section>

    <section class="follow-ups-card" style="grid-column: 1 / -1;">
        <div class="follow-ups-card__head">
            <h3>Recent CRM Trigger Activity</h3>
        </div>
        <div class="follow-ups-card__body">
            <div class="follow-ups-list">
                <?php if (!$recentLogs): ?>
                    <div class="follow-ups-log">
                        <strong>No trigger logs yet</strong>
                        <p>Run a CRM test push or wait for automated follow-up scheduling in the next phase.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($recentLogs as $log): ?>
                    <div class="follow-ups-log">
                        <strong><?= htmlspecialchars($log['name']) ?> · <?= htmlspecialchars($log['status']) ?></strong>
                        <p><?= htmlspecialchars($log['mobile']) ?> · <?= htmlspecialchars($log['created_at']) ?></p>
                        <p><?= htmlspecialchars($log['response']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
