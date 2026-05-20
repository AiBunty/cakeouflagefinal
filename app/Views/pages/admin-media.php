<?php /* Cakeouflage Admin — Media Manager */ ?>
<section class="section section--compact" data-page="admin-media">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Media Manager</h1>
      <p class="admin-page-desc">Upload, organise, and attach product images. Images are auto-resized and stored in monthly folders.</p>
    </div>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Upload Media</h2>
      <p class="text-muted">Images are resized and compressed into organized monthly folders.</p>

      <div class="form-grid media-attach-box">
        <label class="form-control">
          <span>Attach Target Product</span>
          <select id="mediaAttachProductId"></select>
        </label>
        <label class="form-control">
          <span>Attach Mode</span>
          <select id="mediaAttachMode">
            <option value="featured">Featured Image</option>
            <option value="gallery">Gallery Image</option>
          </select>
        </label>
      </div>

      <form id="mediaUploadForm" class="form-grid" enctype="multipart/form-data">
        <label class="form-control">
          <span>Select Image</span>
          <input id="mediaUploadInput" type="file" name="file" accept="image/jpeg,image/png,image/webp" required />
        </label>
        <div id="mediaPreviewWrap" class="media-preview-wrap"></div>
        <button class="btn btn--primary" type="submit">Upload Image</button>
      </form>
      <p id="mediaUploadStatus" class="text-muted"></p>
    </article>

    <article class="card">
      <h2>Media Library</h2>
      <div id="mediaLibraryGrid" class="media-grid"></div>
    </article>
  </div>
</section>
