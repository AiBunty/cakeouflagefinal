<section class="section section--compact" data-page="admin-whatsapp-meta">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">WhatsApp Meta Settings</h1>
      <p class="admin-page-desc">Configure Meta Business API credentials, WABA settings, and monitor template approval status.</p>
    </div>
    <div class="admin-page-actions">
      <button class="btn btn--outline-burgundy" id="metaWhatsAppTestBtn">Test Connection</button>
      <button class="btn btn--primary" id="metaWhatsAppSyncBtn">Sync from Meta</button>
    </div>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Meta WhatsApp Settings</h2>
      <form id="metaWhatsAppSettingsForm" class="form-grid" novalidate>
        <div class="form-row-two">
          <label class="form-control"><span>Provider</span><input type="text" name="provider_name" /></label>
          <label class="form-control"><span>App ID</span><input type="text" name="app_id" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>App Secret</span><input type="password" name="app_secret" /></label>
          <label class="form-control"><span>Access Token</span><input type="password" name="access_token" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>API Base URL</span><input type="text" name="api_base_url" /></label>
          <label class="form-control"><span>WABA ID</span><input type="text" name="business_account_id" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Phone Number ID</span><input type="text" name="phone_number_id" /></label>
          <label class="form-control"><span>Webhook Verify Token</span><input type="text" name="webhook_verify_token" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Webhook Callback URL</span><input type="text" name="webhook_callback_url" /></label>
          <label class="form-control"><span>Namespace</span><input type="text" name="namespace_reference" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Default Language</span><input type="text" name="default_language_code" value="en_US" /></label>
          <label class="form-control"><span>Default Category</span><select name="default_category"><option value="utility">utility</option><option value="marketing">marketing</option><option value="authentication">authentication</option></select></label>
        </div>
        <label class="checkbox-row"><input type="checkbox" name="is_active" /> Integration Active</label>
        <div class="product-card__actions">
          <button class="btn btn--primary" type="submit">Save Settings</button>
        </div>
      </form>
      <p id="metaWhatsAppStatus" class="text-muted"></p>
    </article>

    <article class="card">
      <h2>Approval Status Dashboard</h2>
      <div class="admin-kpi-grid admin-kpi-grid--compact">
        <article class="admin-kpi"><h3>Draft</h3><p id="waStatusDraft">-</p></article>
        <article class="admin-kpi"><h3>Submitted</h3><p id="waStatusSubmitted">-</p></article>
        <article class="admin-kpi"><h3>Approved</h3><p id="waStatusApproved">-</p></article>
        <article class="admin-kpi"><h3>Rejected</h3><p id="waStatusRejected">-</p></article>
        <article class="admin-kpi"><h3>Failed Queue</h3><p id="waStatusFailedQueue">-</p></article>
        <article class="admin-kpi"><h3>Sent 30d</h3><p id="waStatusSent30">-</p></article>
      </div>
      <div id="metaWhatsAppAccountInfo" class="admin-list" style="margin-top: var(--space-4);"></div>
    </article>
  </div>
</section>