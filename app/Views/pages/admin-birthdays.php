<?php /* Cakeouflage Admin — Birthdays CRM */ ?>
<section class="section section--compact" data-page="admin-birthdays">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Birthday Pipeline</h1>
      <p class="admin-page-desc">Upcoming customer birthdays — send greetings, offers, and cake recommendations on time.</p>
    </div>
    <div class="admin-page-actions">
      <form id="birthdayFilterForm" class="admin-inline-filter" novalidate>
        <label class="form-control form-control--inline">
          <span class="form-label">Days Ahead</span>
          <input type="number" name="days" value="30" min="1" max="90" class="admin-input-sm" />
        </label>
        <button class="btn btn--secondary" type="submit">↻ Refresh</button>
      </form>
    </div>
  </div>

  <!-- Quick stats -->
  <div class="admin-kpi-grid admin-kpi-grid--3" id="birthdayKpiRow">
    <article class="admin-kpi">
      <div class="admin-kpi__icon">🎂</div>
      <div><h3>Today</h3><p id="bdToday">-</p><span class="admin-kpi__sub">Birthdays today</span></div>
    </article>
    <article class="admin-kpi">
      <div class="admin-kpi__icon">📅</div>
      <div><h3>This Week</h3><p id="bdWeek">-</p><span class="admin-kpi__sub">Next 7 days</span></div>
    </article>
    <article class="admin-kpi">
      <div class="admin-kpi__icon">🗓</div>
      <div><h3>This Month</h3><p id="bdMonth">-</p><span class="admin-kpi__sub">Next 30 days</span></div>
    </article>
  </div>

  <article class="card" style="margin-top:var(--space-4)">
    <div class="admin-table-header">
      <h2>Upcoming Birthdays</h2>
      <div class="admin-page-actions">
        <input type="text" id="bdSearch" class="admin-search-input" placeholder="Search customer…">
      </div>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="birthdaysTable">
        <thead>
          <tr>
            <th>Customer</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Date of Birth</th>
            <th>Next Birthday</th>
            <th>Days Left</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="7" class="admin-table-empty">Loading upcoming birthdays…</td></tr>
        </tbody>
      </table>
    </div>
    <p id="birthdayStatus" class="form-feedback" aria-live="polite"></p>
  </article>

</section>
