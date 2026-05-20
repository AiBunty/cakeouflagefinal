<section class="section section--compact" data-page="admin-courses">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Courses</h1>
      <p class="admin-page-desc">Create and manage workshop/course listings shown on the public course pages.</p>
    </div>
  </div>

  <div class="admin-panel-grid">
    <article class="card">
      <h2>Add / Edit Course</h2>
      <form id="adminCourseForm" class="form-grid" novalidate>
        <input type="hidden" name="id" />
        <label class="form-control"><span>Title</span><input type="text" name="title" required /></label>
        <label class="form-control"><span>Slug</span><input type="text" name="slug" required /></label>
        <label class="form-control"><span>Short Description</span><input type="text" name="short_description" required /></label>
        <label class="form-control"><span>Description</span><textarea name="description" rows="4" required></textarea></label>
        <label class="form-control"><span>Modules (optional)</span><textarea name="modules" rows="3"></textarea></label>
        <div class="form-row-two">
          <label class="form-control"><span>Duration</span><input type="text" name="duration_text" placeholder="e.g. 1 Day (6 hrs)" /></label>
          <label class="form-control"><span>Mode</span>
            <select name="mode">
              <option value="offline">offline</option>
              <option value="online">online</option>
              <option value="hybrid">hybrid</option>
            </select>
          </label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Fee Amount</span><input type="number" name="fee_amount" min="1" step="0.01" required /></label>
          <label class="form-control"><span>Image URL</span><input type="text" name="image_url" placeholder="/uploads/media/courses/demo.jpg" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>CTA Label</span><input type="text" name="cta_label" placeholder="Enquire Now" /></label>
          <label class="form-control"><span>CTA URL</span><input type="text" name="cta_url" placeholder="/course" /></label>
        </div>
        <label class="form-control" style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" checked />
          <span>Active</span>
        </label>
        <div class="admin-page-actions">
          <button class="btn btn--primary" type="submit">Save Course</button>
          <button class="btn btn--secondary" type="button" id="adminCourseResetBtn">Reset</button>
        </div>
      </form>
      <p id="adminCourseStatus" class="text-muted"></p>
    </article>

    <article class="card">
      <div class="admin-table-header">
        <h2>Course List</h2>
        <input id="adminCourseSearch" type="search" placeholder="Search by title or slug" />
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="adminCoursesTable">
          <thead>
            <tr>
              <th>Title</th>
              <th>Mode</th>
              <th>Fee</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </article>
  </div>
</section>
