<section class="section section--compact" data-page="admin-communications">
  <article class="card" style="margin-bottom: var(--space-4);">
    <h2>Communication Control Center</h2>
    <p class="text-muted">SMTP settings below now drive real queued email delivery. WhatsApp template drafting, Meta approval sync, event mappings, test sends, and usage logs are managed from the dedicated WhatsApp admin pages.</p>
    <div class="toolbar-actions">
      <a class="btn btn--secondary" href="/admin/whatsapp/meta-integration">Meta Integration</a>
      <a class="btn btn--secondary" href="/admin/whatsapp/templates">WhatsApp Templates</a>
      <a class="btn btn--secondary" href="/admin/whatsapp/mappings">Event Mappings</a>
      <a class="btn btn--secondary" href="/admin/whatsapp/logs">WhatsApp Logs</a>
    </div>
  </article>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>SMTP Settings</h2>
      <form id="smtpSettingsForm" class="form-grid" novalidate>
        <div class="form-row-two">
          <label class="form-control"><span>Host</span><input type="text" name="host" /></label>
          <label class="form-control"><span>Port</span><input type="number" name="port" min="1" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Username</span><input type="text" name="username" /></label>
          <label class="form-control"><span>Password</span><input type="password" name="password" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>From Name</span><input type="text" name="from_name" /></label>
          <label class="form-control"><span>From Email</span><input type="email" name="from_email" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Encryption</span>
            <select name="encryption">
              <option value="none">None</option>
              <option value="ssl">SSL</option>
              <option value="tls">TLS</option>
            </select>
          </label>
          <label class="checkbox-row"><input type="checkbox" name="is_active" /> Active</label>
        </div>
        <button class="btn btn--primary" type="submit">Save SMTP</button>
      </form>
      <form id="smtpTestForm" class="form-grid" style="margin-top: var(--space-4);" novalidate>
        <label class="form-control"><span>Test Email Recipient</span><input type="email" name="to" required /></label>
        <button class="btn btn--secondary" type="submit">Queue Test Email</button>
      </form>
      <p id="smtpStatus" class="text-muted">Queued SMTP test emails are delivered by the queue worker using the saved host, port, auth, and encryption settings.</p>
    </article>

    <article class="card">
      <h2>WhatsApp Settings</h2>
      <form id="whatsappSettingsForm" class="form-grid" novalidate>
        <label class="form-control"><span>Provider</span><input type="text" name="provider_name" /></label>
        <label class="form-control"><span>API Base URL</span><input type="text" name="api_base_url" /></label>
        <label class="form-control"><span>API Key</span><input type="password" name="api_key" /></label>
        <div class="form-row-two">
          <label class="form-control"><span>Phone Number ID</span><input type="text" name="phone_number_id" /></label>
          <label class="form-control"><span>Business Account ID</span><input type="text" name="business_account_id" /></label>
        </div>
        <label class="form-control"><span>Webhook Verify Token</span><input type="text" name="webhook_verify_token" /></label>
        <label class="checkbox-row"><input type="checkbox" name="is_active" /> Active</label>
        <button class="btn btn--primary" type="submit">Save WhatsApp</button>
      </form>
      <p id="whatsappStatus" class="text-muted">These base settings remain available for compatibility, but Meta-connected template management should be completed from the dedicated WhatsApp screens.</p>
    </article>
  </div>

  <article class="card" style="margin-top: var(--space-4);">
    <h2>Communication Templates</h2>
    <p class="text-muted">Email templates are fully editable with rich controls and source mode. The DCoreSystems developer footer and logo are always appended automatically at send-time and cannot be removed.</p>
    <div class="admin-table-wrap">
      <table class="admin-table" id="commTemplatesTable">
        <thead>
          <tr>
            <th>Channel</th>
            <th>Event</th>
            <th>Subject</th>
            <th>Active</th>
            <th>Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <p id="commTemplateStatus" class="text-muted"></p>
  </article>

  <article class="card" style="margin-top: var(--space-4);" id="commTemplateEditorCard">
    <h2>Email Template Studio</h2>
    <p class="text-muted">World-class editor modes: Plain, HTML Source, and Live Preview (editable). Save writes template HTML; non-removable developer footer is injected at delivery.</p>

    <div class="toolbar-actions" style="margin-bottom: var(--space-3);">
      <button class="btn btn--secondary" type="button" data-mode="plain" id="tmplModePlain">Plain</button>
      <button class="btn btn--secondary" type="button" data-mode="html" id="tmplModeHtml">HTML Source</button>
      <button class="btn btn--secondary" type="button" data-mode="preview" id="tmplModePreview">Live Preview</button>
      <button class="btn btn--secondary" type="button" id="tmplPreviewEditToggle">Enable Preview Edit</button>
    </div>

    <form id="commTemplateEditorForm" class="form-grid" novalidate>
      <input type="hidden" id="tmplId" name="id" value="" />
      <label class="form-control"><span>Event Key</span><input type="text" id="tmplEventKey" name="event_key" readonly /></label>
      <div class="form-row-two">
        <label class="form-control"><span>Channel</span><input type="text" id="tmplChannel" name="channel" readonly /></label>
        <label class="checkbox-row" style="align-self: end;"><input type="checkbox" id="tmplIsActive" name="is_active" /> Active</label>
      </div>
      <label class="form-control"><span>Email Subject</span><input type="text" id="tmplSubject" name="subject" placeholder="Template subject" /></label>

      <div id="tmplEditorToolbar" class="toolbar-actions" style="gap:6px; margin: 0 0 var(--space-2);">
        <button class="btn btn--secondary" type="button" data-cmd="bold"><strong>B</strong></button>
        <button class="btn btn--secondary" type="button" data-cmd="italic"><em>I</em></button>
        <button class="btn btn--secondary" type="button" data-cmd="underline"><u>U</u></button>
        <button class="btn btn--secondary" type="button" data-cmd="insertUnorderedList">• List</button>
        <button class="btn btn--secondary" type="button" data-cmd="insertOrderedList">1. List</button>
        <button class="btn btn--secondary" type="button" data-cmd="justifyLeft">Left</button>
        <button class="btn btn--secondary" type="button" data-cmd="justifyCenter">Center</button>
        <button class="btn btn--secondary" type="button" data-cmd="justifyRight">Right</button>
        <button class="btn btn--secondary" type="button" id="tmplInsertLink">Link</button>
        <button class="btn btn--secondary" type="button" id="tmplInsertImage">Image</button>
        <button class="btn btn--secondary" type="button" id="tmplInsertVariable">Insert Variable</button>
        <button class="btn btn--secondary" type="button" data-cmd="removeFormat">Clear Format</button>
      </div>

      <div id="tmplPlainWrap">
        <label class="form-control"><span>Plain Content</span>
          <textarea id="tmplPlain" rows="14" placeholder="Write plain email content..."></textarea>
        </label>
      </div>

      <div id="tmplHtmlWrap" style="display:none;">
        <label class="form-control"><span>HTML Source</span>
          <textarea id="tmplHtml" rows="16" placeholder="&lt;h2&gt;Hello {{customer_name}}&lt;/h2&gt;..."></textarea>
        </label>
      </div>

      <div id="tmplPreviewWrap" style="display:none;">
        <label class="form-control"><span>Live Preview (editable when enabled)</span>
          <iframe id="tmplPreviewFrame" title="Template preview" style="width:100%; min-height:420px; border:1px solid rgba(0,0,0,0.15); border-radius:10px; background:#fff;"></iframe>
        </label>
      </div>

      <div style="border:1px solid rgba(128,0,31,0.14); border-radius:10px; padding:10px 12px; background:#fff8fa; color:#6e2a3e; font-size:0.88rem;">
        <strong>Locked Footer:</strong> Every sent email always ends with <strong>Developed by dcoresystems.com</strong> and DCore logo. This footer cannot be removed from delivery output.
      </div>

      <div class="toolbar-actions">
        <button class="btn btn--primary" type="submit" id="tmplSaveBtn">Save Template</button>
        <button class="btn btn--secondary" type="button" id="tmplReloadBtn">Reload</button>
      </div>
      <p id="commTemplateEditorStatus" class="text-muted"></p>
    </form>
  </article>

  <article class="card" style="margin-top: var(--space-4);">
    <h2>Communication Logs</h2>
    <div class="admin-table-wrap">
      <table class="admin-table" id="commLogsTable">
        <thead>
          <tr>
            <th>Time</th>
            <th>Channel</th>
            <th>Event</th>
            <th>Recipient</th>
            <th>Status</th>
            <th>Retry</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <p id="commLogsStatus" class="text-muted"></p>
  </article>
</section>
