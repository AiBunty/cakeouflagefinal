<?php /* Cakeouflage Admin — Banners */ ?>
<section class="section section--compact" data-page="admin-banners">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Banner Management</h1>
      <p class="admin-page-desc">Create and manage hero banners, promotional strips, and page-specific images.</p>
    </div>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Create / Edit Banner</h2>
      <form id="adminBannerForm" class="form-grid" novalidate>
        <input type="hidden" name="id" />
        <label class="form-control"><span>Title</span><input type="text" name="title" required /></label>
        <label class="form-control"><span>Subtitle</span><input type="text" name="subtitle" /></label>
        <label class="form-control"><span>Image URL</span><input type="text" name="image_url" /></label>
        <div class="form-row-two">
          <label class="form-control"><span>CTA Label</span><input type="text" name="cta_label" /></label>
          <label class="form-control"><span>CTA URL</span><input type="text" name="cta_url" /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Placement</span><select name="placement"><option value="home_hero">home_hero</option><option value="home_mid">home_mid</option><option value="shop_top">shop_top</option><option value="course_top">course_top</option><option value="b2b_top">b2b_top</option></select></label>
          <label class="form-control"><span>Sort Order</span><input type="number" name="sort_order" min="0" value="0" /></label>
        </div>
        <label class="checkbox-row"><input type="checkbox" name="is_active" checked /> Active</label>
        <button class="btn btn--primary" type="submit">Save Banner</button>
      </form>
      <p id="adminBannerStatus" class="text-muted"></p>
    </article>
    <article class="card">
      <h2>Banner List</h2>
      <div class="admin-table-wrap">
        <table class="admin-table" id="adminBannersTable">
          <thead><tr><th>Title</th><th>Placement</th><th>Active</th><th>Actions</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </article>
  </div>
</section>