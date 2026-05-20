<section class="section section--compact" data-page="admin-products">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Products</h1>
      <p class="admin-page-desc">Add, edit, and manage your full cake catalogue with gallery and variants.</p>
    </div>
    <div class="admin-page-actions">
      <a href="/admin/bulk-import" class="btn btn--outline-burgundy">Bulk Import</a>
      <button class="btn btn--primary" id="scrollToAddProduct">+ Add Product</button>
    </div>
  </div>

  <div class="admin-panel-grid">
    <article class="card">
      <h2>Add Product</h2>
      <form id="adminProductForm" class="form-grid" novalidate>
        <label class="form-control"><span>Name</span><input type="text" name="name" required /></label>
        <label class="form-control"><span>Slug</span><input type="text" name="slug" required /></label>
        <label class="form-control"><span>SKU</span><input type="text" name="sku" required /></label>
        <label class="form-control"><span>Category</span><select name="collection_category_id" id="adminProductCategory" required></select></label>
        <label class="form-control"><span>Short Description</span><input type="text" name="short_description" required /></label>
        <label class="form-control"><span>Long Description</span><textarea name="long_description" rows="4" required></textarea></label>
        <div class="form-row-two">
          <label class="form-control"><span>Starting Price</span><input type="number" name="starting_price" min="1" step="0.01" required /></label>
          <label class="form-control"><span>Base Price</span><input type="number" name="base_price" min="1" step="0.01" required /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Stock</span><input type="number" name="stock_quantity" min="0" step="1" value="0" /></label>
          <label class="form-control"><span>Availability</span><select name="availability_status"><option value="in_stock">In Stock</option><option value="out_of_stock">Out Of Stock</option><option value="preorder">Preorder</option><option value="draft">Draft</option></select></label>
        </div>
        <button class="btn btn--primary" type="submit">Save Product</button>
      </form>
      <p id="adminProductStatus" class="text-muted"></p>

      <div class="admin-inline-panel" id="productGalleryPanel" hidden>
        <h3>Gallery Management</h3>
        <p class="text-muted">Drag and drop gallery images to reorder, or remove unwanted files for the selected product.</p>
        <div id="productGalleryList" class="admin-gallery-list"></div>
      </div>
    </article>

    <article class="card">
      <div class="admin-table-header">
        <h2>Product List</h2>
        <input id="adminProductSearch" type="search" placeholder="Search by name, slug, SKU" />
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="adminProductTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>SKU</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
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
