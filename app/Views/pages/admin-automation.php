<section class="section section--compact" data-page="admin-automation">
  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Automation Rules</h2>
      <div class="admin-table-wrap">
        <table class="admin-table" id="automationRulesTable">
          <thead>
            <tr>
              <th>Rule</th>
              <th>Channel</th>
              <th>Trigger</th>
              <th>Template</th>
              <th>Offset Days</th>
              <th>Active</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <p id="automationRuleStatus" class="text-muted"></p>
    </article>

    <article class="card">
      <h2>Create Reminder</h2>
      <form id="reminderCreateForm" class="form-grid" novalidate>
        <div class="form-row-two">
          <label class="form-control">
            <span>Reminder Type</span>
            <select name="reminder_type">
              <option value="follow_up">Follow Up</option>
              <option value="payment_due">Payment Due</option>
              <option value="birthday">Birthday</option>
              <option value="production">Production</option>
            </select>
          </label>
          <label class="form-control">
            <span>Reminder On</span>
            <input type="datetime-local" name="reminder_on" required />
          </label>
        </div>
        <label class="form-control"><span>Title</span><input type="text" name="title" required /></label>
        <div class="form-row-two">
          <label class="form-control"><span>User ID (Optional)</span><input type="number" name="user_id" min="1" /></label>
          <label class="form-control"><span>B2B Account ID (Optional)</span><input type="number" name="b2b_account_id" min="1" /></label>
        </div>
        <label class="form-control"><span>Notes</span><textarea name="notes" rows="3"></textarea></label>
        <button class="btn btn--primary" type="submit">Create Reminder</button>
      </form>
      <p id="reminderStatus" class="text-muted"></p>
    </article>
  </div>

  <article class="card" style="margin-top: var(--space-4);">
    <h2>Reminders</h2>
    <div class="admin-table-wrap">
      <table class="admin-table" id="remindersTable">
        <thead>
          <tr>
            <th>Type</th>
            <th>Title</th>
            <th>When</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </article>

  <article class="card" style="margin-top: var(--space-4);">
    <div class="admin-table-header">
      <h2>Queue Monitor</h2>
      <form id="queueRunForm" class="product-card__actions" novalidate>
        <label class="form-control" style="min-width: 130px;">
          <span>Max Jobs</span>
          <input type="number" name="max_jobs" min="1" max="200" value="25" />
        </label>
        <button class="btn btn--secondary" type="submit">Run Queue Now</button>
      </form>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="queueJobsTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Type</th>
            <th>Status</th>
            <th>Available At</th>
            <th>Attempts</th>
            <th>Last Error</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <p id="queueRunStatus" class="text-muted"></p>
  </article>
</section>
