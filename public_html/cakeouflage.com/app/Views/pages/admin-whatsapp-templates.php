<section class="section section--compact" data-page="admin-whatsapp-templates">
  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <div class="admin-table-header">
        <h2>Draft Template List</h2>
        <div class="product-card__actions">
          <button class="btn btn--secondary" type="button" id="waAutoGenerateBtn">Auto Create Default WhatsApp Templates</button>
          <button class="btn btn--primary" type="button" id="waBulkSubmitBtn">Submit Selected Templates to Meta</button>
        </div>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="waTemplatesTable">
          <thead>
            <tr>
              <th><input type="checkbox" id="waTemplateSelectAll" /></th>
              <th>Internal Name</th>
              <th>Category</th>
              <th>Status</th>
              <th>Event</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <p id="waTemplateTableStatus" class="text-muted"></p>
    </article>

    <article class="card">
      <h2>Create / Edit Draft Template</h2>
      <form id="waTemplateForm" class="form-grid" novalidate>
        <input type="hidden" name="id" />
        <label class="form-control"><span>Internal Template Title</span><input type="text" name="internal_name" required /></label>
        <div class="form-row-two">
          <label class="form-control"><span>Internal Template Key</span><input type="text" name="template_key" required /></label>
          <label class="form-control"><span>Meta Template Name</span><input type="text" name="meta_template_name" required /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Category</span><select name="category"><option value="utility">utility</option><option value="marketing">marketing</option><option value="authentication">authentication</option></select></label>
          <label class="form-control"><span>Language</span><input type="text" name="language_code" value="en_US" required /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Header Type</span><select name="header_type"><option value="none">none</option><option value="text">text</option><option value="image">image</option><option value="video">video</option><option value="document">document</option></select></label>
          <label class="form-control"><span>Header Content</span><input type="text" name="header_text" /></label>
        </div>
        <label class="form-control"><span>Body Content</span><textarea name="body_text" rows="5" required></textarea></label>
        <label class="form-control"><span>Footer Content</span><input type="text" name="footer_text" /></label>
        <label class="form-control"><span>Mapped Event Key</span><input type="text" name="mapped_event_key" placeholder="order_created" /></label>
        <label class="form-control"><span>Buttons JSON</span><textarea name="buttons_json" rows="4" placeholder='[{"button_type":"quick_reply","button_text":"View Order"}]'></textarea></label>
        <label class="checkbox-row"><input type="checkbox" name="is_active" checked /> Active</label>
        <div class="product-card__actions">
          <button class="btn btn--primary" type="submit">Save Draft</button>
          <button class="btn btn--secondary" type="button" id="waPreviewBtn">Preview</button>
          <button class="btn btn--secondary" type="button" id="waCloneFixBtn">Clone and Fix</button>
          <button class="btn btn--secondary" type="button" id="waSubmitBtn">Send for Meta Approval</button>
        </div>
      </form>
      <p id="waTemplateFormStatus" class="text-muted"></p>
    </article>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two" style="margin-top: var(--space-4);">
    <article class="card">
      <h2>Preview Template</h2>
      <div id="waPreviewPanel" class="admin-list"></div>
    </article>
    <article class="card">
      <h2>Template Version History</h2>
      <div id="waVersionHistory" class="admin-list"></div>
      <h2 style="margin-top: var(--space-4);">Rejection / Approval Log</h2>
      <div id="waApprovalLog" class="admin-list"></div>
    </article>
  </div>

  <article class="card" style="margin-top: var(--space-4);">
    <h2>Test Approved Template Send</h2>
    <form id="waTestSendForm" class="form-grid" novalidate>
      <div class="form-row-two">
        <label class="form-control"><span>Template ID</span><input type="number" name="template_id" min="1" required /></label>
        <label class="form-control"><span>Recipient Number</span><input type="text" name="recipient" placeholder="919999999999" required /></label>
      </div>
      <label class="form-control"><span>Sample Context JSON</span><textarea name="context_json" rows="4" placeholder='{"first_name":"Priya","order_number":"CK1024"}'></textarea></label>
      <button class="btn btn--secondary" type="submit">Send Test</button>
    </form>
    <p id="waTestSendStatus" class="text-muted"></p>
  </article>
</section>