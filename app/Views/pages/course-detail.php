<section class="section section--compact" data-page="course-detail" data-course-slug="<?= htmlspecialchars((string)($courseSlug ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <div class="container">
    <article class="card" style="margin-bottom:var(--space-4)">
      <p class="section-label">Cakeouflage Course</p>
      <h1 id="courseDetailTitle" class="section-title" style="margin:0 0 12px 0">Loading course...</h1>
      <p id="courseDetailShort" class="text-muted"></p>
      <div class="product-card__actions" style="margin-top:12px">
        <a id="courseDetailCta" class="btn btn--primary" href="#courseDetailInquiry">Enquire Now</a>
        <a class="btn btn--secondary" href="/course">Back to Courses</a>
      </div>
    </article>

    <article class="card" style="margin-bottom:var(--space-4)">
      <h2>About This Course</h2>
      <p id="courseDetailDescription" class="text-muted"></p>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:8px">
        <p><strong>Mode:</strong> <span id="courseDetailMode">-</span></p>
        <p><strong>Duration:</strong> <span id="courseDetailDuration">-</span></p>
        <p><strong>Starting Fee:</strong> <span id="courseDetailFee">-</span></p>
      </div>
      <div style="margin-top:10px">
        <strong>Modules</strong>
        <p id="courseDetailModules" class="text-muted" style="margin-top:6px">-</p>
      </div>
    </article>

    <article class="card" style="margin-bottom:var(--space-4)">
      <h2>Upcoming Batches</h2>
      <div class="admin-table-wrap">
        <table class="admin-table" id="courseDetailBatchesTable">
          <thead>
            <tr>
              <th>Batch</th>
              <th>Starts</th>
              <th>Ends</th>
              <th>Seats</th>
              <th>Fee</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </article>

    <article class="card" id="courseDetailInquiry">
      <h2>Course Enquiry</h2>
      <form id="courseDetailEnquiryForm" class="form-grid" novalidate>
        <input type="hidden" name="workshop" id="courseDetailWorkshop" />
        <div class="form-row-two">
          <label class="form-control"><span>Name</span><input type="text" name="name" required /></label>
          <label class="form-control"><span>Phone</span><input type="tel" name="phone" required /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Email</span><input type="email" name="email" /></label>
          <label class="form-control"><span>Preferred Date</span><input type="text" name="preferred_date" /></label>
        </div>
        <label class="form-control"><span>Message</span><textarea name="message" rows="3"></textarea></label>
        <button class="btn btn--primary" type="submit">Submit Enquiry</button>
      </form>
      <p id="courseDetailEnquiryStatus" class="text-muted"></p>
    </article>
  </div>
</section>
