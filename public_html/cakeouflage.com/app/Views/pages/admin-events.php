<section class="section section--compact" data-page="admin-events">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Webinars & Events</h1>
      <p class="admin-page-desc">Create, publish, and manage frontend event registrations.</p>
    </div>
  </div>

  <div class="admin-panel-grid">
    <article class="card">
      <h2>Add / Edit Event</h2>
      <form id="adminEventForm" class="form-grid" novalidate>
        <input type="hidden" name="id" />
        <label class="form-control"><span>Title</span><input type="text" name="title" required /></label>
        <label class="form-control"><span>Slug</span><input type="text" name="slug" required /></label>
        <label class="form-control"><span>Short Description</span><input type="text" name="short_description" required /></label>
        <label class="form-control"><span>Full Description</span><textarea name="full_description" rows="4" required></textarea></label>
        <div class="form-row-two">
          <label class="form-control"><span>Type</span>
            <select name="event_type">
              <option value="event">event</option>
              <option value="webinar">webinar</option>
            </select>
          </label>
          <label class="form-control"><span>Status</span>
            <select name="event_status">
              <option value="draft">draft</option>
              <option value="scheduled">scheduled</option>
              <option value="live">live</option>
              <option value="completed">completed</option>
              <option value="cancelled">cancelled</option>
            </select>
          </label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Category</span><input type="text" name="event_category" placeholder="business, seasonal, showcase" /></label>
          <label class="form-control"><span>Instructor / Speaker</span><input type="text" name="instructor_name" required /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Starts At</span><input type="datetime-local" name="starts_at" required /></label>
          <label class="form-control"><span>Ends At</span><input type="datetime-local" name="ends_at" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Location</span><input type="text" name="location_text" placeholder="Nashik Studio / Online" /></label>
          <label class="form-control"><span>Online Link</span><input type="url" name="online_link" placeholder="https://..." /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Capacity</span><input type="number" name="capacity" min="1" value="30" /></label>
          <label class="form-control"><span>Seats Available</span><input type="number" name="seats_available" min="0" value="30" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Banner Image</span><input type="text" name="banner_image" placeholder="/assets/images/events/sample.jpg" /></label>
          <label class="form-control"><span>CTA Label</span><input type="text" name="registration_cta_label" placeholder="Register Now" /></label>
        </div>
        <label class="form-control" style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="is_published" checked />
          <span>Published</span>
        </label>
        <div class="admin-page-actions">
          <button class="btn btn--primary" type="submit">Save Event</button>
          <button class="btn btn--secondary" type="button" id="adminEventResetBtn">Reset</button>
        </div>
      </form>
      <p id="adminEventStatus" class="text-muted"></p>
    </article>

    <article class="card">
      <div class="admin-table-header">
        <h2>Event List</h2>
        <input id="adminEventSearch" type="search" placeholder="Search by title, slug, instructor" />
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="adminEventsTable">
          <thead>
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Status</th>
              <th>Starts</th>
              <th>Seats</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </article>
  </div>
</section>
