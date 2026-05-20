<?php /* Cakeouflage Admin — Categories */ ?>
<section class="section section--compact" data-page="admin-categories">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Categories</h1>
      <p class="admin-page-desc">Manage the full catalog hierarchy — core cakes, occasions, gifting, B2B, and course collections.</p>
    </div>
  </div>

  <div class="admin-panel-grid admin-panel-grid--two">
    <article class="card">
      <h2>Add / Edit Category</h2>
      <form id="adminCategoryForm" class="form-grid" novalidate>
        <input type="hidden" name="id" id="adminCategoryId" value="" />

        <label class="form-control"><span>Name <span class="required">*</span></span><input type="text" name="name" id="adminCategoryName" required /></label>
        <label class="form-control"><span>Slug <span class="required">*</span></span><input type="text" name="slug" id="adminCategorySlug" required /></label>
        <label class="form-control"><span>Parent Category</span>
          <select name="parent_id" id="adminParentCategory"><option value="">None (Root)</option></select>
        </label>
        <label class="form-control"><span>Description</span><textarea name="description" id="adminCategoryDesc" rows="3"></textarea></label>
        <label class="form-control"><span>Sort Order</span><input type="number" name="sort_order" id="adminCategorySort" min="0" step="1" value="0" /></label>
        <label class="form-control"><span>Banner Image URL</span><input type="text" name="banner_image" id="adminCategoryBanner" placeholder="/uploads/media/category-banner.jpg" /></label>
        <label class="form-control"><span>Menu Icon (emoji or CSS class)</span><input type="text" name="menu_icon" id="adminCategoryIcon" placeholder="🎂" /></label>
        <label class="form-control"><span>SEO Title</span><input type="text" name="seo_title" id="adminCategorySeoTitle" /></label>
        <label class="form-control" style="grid-column:1/-1"><span>SEO Meta Description</span><textarea name="seo_description" id="adminCategorySeoDesc" rows="2"></textarea></label>

        <div style="display:flex;gap:var(--space-5);align-items:center;flex-wrap:wrap">
          <label class="checkbox-row">
            <input type="checkbox" name="show_in_menu" id="adminCategoryShowMenu" value="1" checked />
            <span>Show in navigation menu</span>
          </label>
          <label class="checkbox-row">
            <input type="checkbox" name="is_featured" id="adminCategoryFeatured" value="1" />
            <span>Featured category</span>
          </label>
        </div>

        <div style="display:flex;gap:var(--space-3);flex-wrap:wrap;grid-column:1/-1">
          <button class="btn btn--primary" type="submit" id="adminCategorySaveBtn">Save Category</button>
          <button class="btn btn--outline" type="button" id="adminCategoryCancelBtn" hidden>Cancel Edit</button>
        </div>
      </form>
      <p id="adminCategoryStatus" class="text-muted" style="margin-top:var(--space-3)"></p>
    </article>

    <article class="card">
      <h2>Category List</h2>
      <div class="admin-table-wrap">
        <table class="admin-table" id="adminCategoryTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Slug</th>
              <th>Parent</th>
              <th>Menu</th>
              <th>Featured</th>
              <th>Sort</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </article>
  </div>
</section>

<script>
(function () {
  const form      = document.getElementById('adminCategoryForm');
  const statusEl  = document.getElementById('adminCategoryStatus');
  const cancelBtn = document.getElementById('adminCategoryCancelBtn');
  const saveBtn   = document.getElementById('adminCategorySaveBtn');
  const tbody     = document.querySelector('#adminCategoryTable tbody');
  const parentSel = document.getElementById('adminParentCategory');

  let editingId = null;

  // ── Load category list + populate parent select ──────────────
  function loadCategories() {
    fetch('/api/admin/categories')
      .then(r => r.json())
      .then(d => {
        if (!d.success) return;
        const cats = d.categories ?? [];

        // Populate parent select
        parentSel.innerHTML = '<option value="">None (Root)</option>';
        cats.forEach(c => {
          if (c.id == editingId) return; // can't set self as parent
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = (c.depth > 0 ? '└ '.repeat(c.depth) : '') + c.name;
          parentSel.appendChild(opt);
        });

        // Populate table
        tbody.innerHTML = '';
        if (!cats.length) {
          tbody.innerHTML = '<tr><td colspan="7" class="text-muted">No categories yet.</td></tr>';
          return;
        }
        cats.forEach(c => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td style="padding-left:${(c.depth ?? 0) * 16 + 8}px">
              ${c.menu_icon ? `<span>${escHtml(c.menu_icon)}</span> ` : ''}
              <strong>${escHtml(c.name)}</strong>
            </td>
            <td><code>${escHtml(c.slug)}</code></td>
            <td>${escHtml(c.parent_name ?? '—')}</td>
            <td>${c.show_in_menu ? '<span class="badge badge--success">Yes</span>' : '<span class="badge badge--muted">No</span>'}</td>
            <td>${c.is_featured ? '<span class="badge badge--info">Yes</span>' : '—'}</td>
            <td>${c.sort_order ?? 0}</td>
            <td>
              <button class="btn btn--xs btn--outline" onclick="adminCatEdit(${c.id})">Edit</button>
              <button class="btn btn--xs btn--danger"  onclick="adminCatDelete(${c.id}, '${escHtml(c.name).replace(/'/g,"\\'")}')">Delete</button>
            </td>`;
          tbody.appendChild(tr);
        });
      })
      .catch(() => { statusEl.textContent = 'Failed to load categories.'; });
  }

  // ── Save ───────────────────────────────────────────────────────
  form.addEventListener('submit', e => {
    e.preventDefault();
    const fd   = new FormData(form);
    const data = Object.fromEntries(fd.entries());
    data.show_in_menu = form.querySelector('[name="show_in_menu"]').checked ? 1 : 0;
    data.is_featured  = form.querySelector('[name="is_featured"]').checked  ? 1 : 0;
    if (editingId) data.id = editingId;

    const method = editingId ? 'PUT' : 'POST';
    const url    = editingId ? `/api/admin/categories/${editingId}` : '/api/admin/categories';

    saveBtn.disabled = true;
    fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__csrf ?? '' },
      body: JSON.stringify(data),
    })
    .then(r => r.json())
    .then(d => {
      statusEl.textContent = d.message ?? (d.success ? 'Saved.' : 'Error saving category.');
      statusEl.style.color = d.success ? 'var(--color-success)' : 'var(--color-error)';
      if (d.success) { resetForm(); loadCategories(); }
    })
    .catch(() => { statusEl.textContent = 'Network error.'; })
    .finally(() => { saveBtn.disabled = false; });
  });

  // Auto-generate slug
  document.getElementById('adminCategoryName')?.addEventListener('input', function () {
    if (!editingId) {
      document.getElementById('adminCategorySlug').value = this.value
        .toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
  });

  // ── Edit ───────────────────────────────────────────────────────
  window.adminCatEdit = function (id) {
    fetch(`/api/admin/categories/${id}`)
      .then(r => r.json())
      .then(d => {
        if (!d.success || !d.category) return;
        const c = d.category;
        editingId = id;
        document.getElementById('adminCategoryId').value      = c.id;
        document.getElementById('adminCategoryName').value    = c.name;
        document.getElementById('adminCategorySlug').value    = c.slug;
        document.getElementById('adminCategoryDesc').value    = c.description ?? '';
        document.getElementById('adminCategorySort').value    = c.sort_order ?? 0;
        document.getElementById('adminCategoryBanner').value  = c.banner_image ?? '';
        document.getElementById('adminCategoryIcon').value    = c.menu_icon ?? '';
        document.getElementById('adminCategorySeoTitle').value= c.seo_title ?? '';
        document.getElementById('adminCategorySeoDesc').value = c.seo_description ?? '';
        form.querySelector('[name="show_in_menu"]').checked = !!parseInt(c.show_in_menu);
        form.querySelector('[name="is_featured"]').checked  = !!parseInt(c.is_featured);
        loadCategories(); // refresh parent select to exclude self
        parentSel.value = c.parent_id ?? '';
        cancelBtn.hidden = false;
        saveBtn.textContent = 'Update Category';
        form.scrollIntoView({ behavior: 'smooth' });
      });
  };

  // ── Delete ─────────────────────────────────────────────────────
  window.adminCatDelete = function (id, name) {
    if (!confirm(`Delete "${name}"? Child categories will be unlinked.`)) return;
    fetch(`/api/admin/categories/${id}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-Token': window.__csrf ?? '' },
    })
    .then(r => r.json())
    .then(d => {
      statusEl.textContent = d.message ?? (d.success ? 'Deleted.' : 'Delete failed.');
      statusEl.style.color = d.success ? 'var(--color-success)' : 'var(--color-error)';
      if (d.success) loadCategories();
    });
  };

  // ── Cancel edit ────────────────────────────────────────────────
  cancelBtn?.addEventListener('click', () => { resetForm(); loadCategories(); });

  function resetForm() {
    editingId = null;
    form.reset();
    document.getElementById('adminCategoryId').value = '';
    cancelBtn.hidden = true;
    saveBtn.textContent = 'Save Category';
    statusEl.textContent = '';
  }

  function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  loadCategories();
})();
</script>
