<?php /* Cakeouflage Admin — Media Manager */ ?>
<section class="section section--compact" data-page="admin-media">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Media Manager</h1>
      <p class="admin-page-desc">Upload, organise, and attach product media. Supports up to 100 MB per file. Videos are auto-converted to web-optimized MP4 in background.</p>
    </div>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Processing Health</h2>
      <p class="text-muted">Live optimization queue status and storage savings.</p>
      <div class="media-health-badges">
        <span id="mediaQueueHealthBadge" class="badge badge--neutral">Queue: Unknown</span>
        <span id="mediaSavingsBadge" class="badge badge--neutral">Savings: 0%</span>
      </div>
      <div class="media-trend-chips" aria-label="Queue trend chips">
        <article class="media-trend-chip media-trend-chip--danger" aria-label="Failure trend">
          <div class="media-trend-chip__head">
            <strong>Failures</strong>
            <span id="mediaFailureTrendValue">0</span>
          </div>
          <div id="mediaFailureSparkline" class="media-sparkline" aria-hidden="true"></div>
        </article>
        <article class="media-trend-chip media-trend-chip--success" aria-label="Throughput trend">
          <div class="media-trend-chip__head">
            <strong>Throughput</strong>
            <span id="mediaThroughputTrendValue">+0</span>
          </div>
          <div id="mediaThroughputSparkline" class="media-sparkline" aria-hidden="true"></div>
        </article>
      </div>
      <div class="form-grid media-queue-stats">
        <p><strong>Pending:</strong> <span id="mediaQueuePending">0</span></p>
        <p><strong>Processing:</strong> <span id="mediaQueueProcessing">0</span></p>
        <p><strong>Completed:</strong> <span id="mediaQueueCompleted">0</span></p>
        <p><strong>Failed:</strong> <span id="mediaQueueFailed">0</span></p>
        <p><strong>Original:</strong> <span id="mediaStorageOriginal">0 B</span></p>
        <p><strong>Optimized:</strong> <span id="mediaStorageOptimized">0 B</span></p>
        <p><strong>Savings:</strong> <span id="mediaStorageSavings">0 B</span></p>
        <p><strong>Savings Ratio:</strong> <span id="mediaStorageRatio">0%</span></p>
        <p><strong>Orphans:</strong> <span id="mediaQueueOrphans">0</span></p>
      </div>
      <p id="mediaQueueStatus" class="text-muted"></p>
      <button id="mediaQueueRefresh" class="btn btn--secondary" type="button">Refresh Queue Metrics</button>
      <hr />
      <h3>Recent Queue Jobs</h3>
      <p class="text-muted">Latest pending, processing, and failed jobs.</p>
      <div id="mediaQueueJobs" class="text-muted">No queue jobs to show.</div>
    </article>

    <article class="card">
      <h2>Upload Media</h2>
      <p class="text-muted">Supports JPG, PNG, WEBP, GIF, SVG, MP4, MOV, AVI, MKV, WEBM, M4V, MPG, and MPEG media.</p>

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
          <span>Select Media</span>
          <input id="mediaUploadInput" type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska,video/x-m4v,video/mpeg,video/mp2t,.mov,.avi,.mkv,.webm,.m4v,.mpg,.mpeg" required />
        </label>
        <progress id="mediaUploadProgress" value="0" max="100" hidden>0%</progress>
        <p id="mediaUploadProgressText" class="text-muted" hidden>Uploading: 0%</p>
        <div id="mediaPreviewWrap" class="media-preview-wrap"></div>
        <button class="btn btn--primary" type="submit">Upload Media</button>
      </form>
      <p id="mediaUploadStatus" class="text-muted"></p>
      <p id="mediaActiveVideoInfo" class="text-muted"></p>
    </article>

    <article class="card">
      <h2>Media Library</h2>
      <div id="mediaLibraryGrid" class="media-grid"></div>
    </article>
  </div>
</section>
