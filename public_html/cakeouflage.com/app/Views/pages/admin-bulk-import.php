<section class="section section--compact" data-page="admin-bulk-import">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Bulk Import</h1>
      <p class="admin-page-desc">Upload a CSV to create or update products in bulk. Supports dry-run and strict variant enforcement.</p>
    </div>
    <div class="admin-page-actions">
      <a href="/api/admin/import/template" class="btn btn--outline-burgundy">Download Sample CSV</a>
    </div>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Import Products CSV</h2>
      <p class="text-muted">Upload product rows in CSV format. Existing SKUs are updated and reported as duplicates.</p>
      <div class="product-card__actions">
        <a class="btn btn--secondary" href="/api/admin/import/template">Download Sample CSV</a>
      </div>

      <form id="bulkImportForm" class="form-grid" enctype="multipart/form-data">
        <label class="form-control">
          <span>CSV File</span>
          <input type="file" id="bulkImportFile" name="file" accept=".csv,text/csv" required />
        </label>
        <label class="checkbox-row">
          <input type="checkbox" name="strict_variants" value="1" checked />
          <span>Enforce strict required variants (0.5 kg, 1 lb, 1.5 lb, 2 lb, 2.5 lb, 3 lb)</span>
        </label>
        <label class="checkbox-row">
          <input type="checkbox" name="dry_run" value="1" />
          <span>Dry run only (validate and report, no database writes)</span>
        </label>
        <label class="checkbox-row">
          <input type="checkbox" name="abort_on_error" value="1" />
          <span>Abort import on first failed row</span>
        </label>
        <button class="btn btn--primary" type="submit">Run Import</button>
      </form>
      <p id="bulkImportStatus" class="text-muted"></p>
      <pre id="bulkImportReport" class="admin-report-box"></pre>
    </article>

    <article class="card">
      <h2>Recent Import Logs</h2>
      <div class="admin-table-wrap">
        <table class="admin-table" id="importLogsTable">
          <thead>
            <tr>
              <th>Generated At</th>
              <th>Mode</th>
              <th>Created</th>
              <th>Updated</th>
              <th>Failed</th>
              <th>Failed Rows CSV</th>
              <th>Log File</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </article>
  </div>
</section>
