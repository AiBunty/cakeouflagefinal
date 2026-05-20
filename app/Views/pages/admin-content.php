<?php /* Cakeouflage Admin — Content Pages */ ?>
<section class="section section--compact" data-page="admin-content">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Content Pages</h1>
      <p class="admin-page-desc">Manage static pages: About, FAQ, Terms, Privacy, Shipping, and custom pages.</p>
    </div>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Pages</h2>
      <div class="admin-table-wrap">
        <table class="admin-table" id="adminPagesTable">
          <thead><tr><th>Title</th><th>Slug</th><th>Published</th><th>Actions</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </article>
    <article class="card">
      <h2>Edit Page</h2>
      <form id="adminPageForm" class="form-grid" novalidate>
        <input type="hidden" name="id" />
        <label class="form-control"><span>Title</span><input type="text" name="title" required /></label>
        <label class="form-control"><span>SEO Title</span><input type="text" name="seo_title" /></label>
        <label class="form-control"><span>SEO Description</span><textarea name="seo_description" rows="2"></textarea></label>
        <label class="form-control"><span>Content</span><textarea name="content" rows="8" required></textarea></label>
        <label class="checkbox-row"><input type="checkbox" name="is_published" checked /> Published</label>
        <button class="btn btn--primary" type="submit">Save Page</button>
      </form>
      <p id="adminPageStatus" class="text-muted"></p>
    </article>
  </div>
</section>