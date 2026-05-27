<main data-page="shop" data-browse-page="shop">

  <!-- Shop Hero Banner -->
  <section class="shop-hero">
    <div class="container">
      <div class="shop-hero__text">
        <span class="section-label">Our Collection</span>
        <h1>Every Cake Tells a Story</h1>
        <p>Explore premium handcrafted cakes, desserts, and gifting hampers — made fresh for every occasion in Nashik.</p>
      </div>
    </div>
  </section>

  <!-- Filters + Grid -->
  <section class="shop-main section">
    <div class="container">
      <div class="shop-layout">

        <!-- Sidebar Filters -->
        <aside class="shop-sidebar" id="shopSidebar">
          <div class="shop-sidebar__header">
            <h2 class="shop-sidebar__title">Filter</h2>
            <button class="shop-sidebar__close" id="sidebarClose" aria-label="Close filters">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="filter-group">
            <h3 class="filter-group__title">Category</h3>
            <div id="filterCategories" class="filter-group__options">
              <label class="filter-chip">
                <input type="radio" name="filterCat" value="" checked />
                <span>All</span>
              </label>
            </div>
          </div>

          <div class="filter-group">
            <h3 class="filter-group__title">Veg / Non-Veg</h3>
            <div class="filter-group__options">
              <label class="filter-chip">
                <input type="radio" name="filterVeg" value="" checked />
                <span>All</span>
              </label>
              <label class="filter-chip">
                <input type="radio" name="filterVeg" value="1" />
                <span><span class="veg-dot veg-dot--veg" style="margin-right:5px;vertical-align:middle"></span>Veg</span>
              </label>
              <label class="filter-chip">
                <input type="radio" name="filterVeg" value="0" />
                <span><span class="veg-dot veg-dot--nonveg" style="margin-right:5px;vertical-align:middle"></span>Non-Veg</span>
              </label>
            </div>
          </div>

          <div class="filter-group">
            <h3 class="filter-group__title">Dietary</h3>
            <div class="filter-group__options">
              <label class="filter-chip"><input type="checkbox" name="dietary" value="eggless" /> <span>Eggless</span></label>
              <label class="filter-chip"><input type="checkbox" name="dietary" value="vegan" /> <span>Vegan</span></label>
              <label class="filter-chip"><input type="checkbox" name="dietary" value="sugar_free" /> <span>Sugar-Free</span></label>
            </div>
          </div>

          <div class="filter-group">
            <h3 class="filter-group__title">Price Range</h3>
            <div class="filter-group__options">
              <select id="priceBucket" class="shop-sort-select" aria-label="Price bucket">
                <option value="">All ranges</option>
                <option value="under_500">Under ₹500</option>
                <option value="500_1000">₹500-₹1000</option>
                <option value="1000_2000">₹1000-₹2000</option>
                <option value="above_2000">Above ₹2000</option>
              </select>
              <input type="number" id="maxPriceInput" min="100" step="100" placeholder="Custom max price" class="shop-search-input" style="margin-top:8px;" />
            </div>
          </div>

          <div class="filter-group">
            <h3 class="filter-group__title">Features</h3>
            <div class="filter-group__options">
              <label class="filter-chip"><input type="checkbox" name="featureFlag" value="is_bestseller" /> <span>Bestseller</span></label>
              <label class="filter-chip"><input type="checkbox" name="featureFlag" value="is_chef_special" /> <span>Chef's Special</span></label>
              <label class="filter-chip"><input type="checkbox" name="featureFlag" value="customizable" /> <span>Customizable</span></label>
              <label class="filter-chip"><input type="checkbox" name="featureFlag" value="topper_enabled" /> <span>Topper Available</span></label>
              <label class="filter-chip"><input type="checkbox" name="featureFlag" value="note_enabled" /> <span>Note on Cake</span></label>
              <label class="filter-chip"><input type="checkbox" name="featureFlag" value="same_day" /> <span>Same Day Delivery</span></label>
              <label class="filter-chip"><input type="checkbox" name="featureFlag" value="express" /> <span>Express Delivery</span></label>
            </div>
          </div>

          <button class="btn btn--primary btn--full" id="applyFiltersBtn">Apply Filters</button>
          <button class="btn btn--ghost btn--full mt-sm" id="clearFiltersBtn">Clear All</button>
        </aside>

        <!-- Main Content -->
        <div class="shop-content">
          <!-- Toolbar -->
          <div class="shop-toolbar" id="shopToolbar">
            <div class="shop-toolbar__left">
              <button class="btn btn--outline btn--sm" id="toggleSidebar" aria-expanded="false" aria-controls="shopSidebar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="10" y2="18"/></svg>
                Filter
              </button>
              <span class="shop-count" id="shopCount">Loading...</span>
            </div>
            <div class="shop-toolbar__right">
              <div class="shop-search-wrap">
                <svg class="shop-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input id="shopSearch" data-live-search-input="shop-toolbar" type="search" class="shop-search-input" placeholder="Search cakes..." />
                <div id="shopToolbarSearchDropdown" class="search-dropdown" role="listbox" aria-label="Search suggestions" hidden></div>
              </div>
              <select id="shopSort" class="shop-sort-select">
                <option value="latest">Latest</option>
                <option value="popular">Popularity</option>
                <option value="price_asc">Price ↑</option>
                <option value="price_desc">Price ↓</option>
              </select>
              <div class="view-toggle" role="group">
                <button class="view-btn is-active" data-view="grid" title="Grid view">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8"/><rect x="13" y="3" width="8" height="8"/><rect x="3" y="13" width="8" height="8"/><rect x="13" y="13" width="8" height="8"/></svg>
                </button>
                <button class="view-btn" data-view="list" title="List view">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </button>
              </div>
            </div>
          </div>

          <div class="browse-header">
            <p class="browse-header__crumb">Home / Shop</p>
            <h2 class="browse-header__title">All Cakes & Desserts</h2>
          </div>

          <div class="shop-quick-chips" id="shopQuickChips" aria-label="Quick filters">
            <button type="button" class="shop-quick-chip is-active" data-quick-filter="all">All</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="eggless">Eggless</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="vegan">Vegan</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="under1000">Under ₹1000</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="bestseller">Bestseller</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="sameDay">Same Day Delivery</button>
            <button type="button" class="shop-quick-chip" data-quick-filter="chefSpecial">Chef Special</button>
          </div>

          <!-- Active filter tags -->
          <div class="active-filters" id="activeFilters" hidden></div>

          <!-- Product Grid -->
          <div class="product-grid" id="shopGrid">
            <!-- Skeleton loaders -->
            <?php for ($i = 0; $i < 8; $i++): ?>
            <div class="product-card skeleton" aria-hidden="true">
              <div class="product-card__image skeleton-box" style="height:240px"></div>
              <div class="product-card__body">
                <div class="skeleton-line" style="width:60%;height:14px"></div>
                <div class="skeleton-line" style="width:80%;height:18px;margin-top:8px"></div>
                <div class="skeleton-line" style="width:40%;height:14px;margin-top:6px"></div>
              </div>
            </div>
            <?php endfor; ?>
          </div>

          <!-- Pagination -->
          <div class="pagination" id="shopPagination" hidden>
            <button class="btn btn--outline btn--sm" id="prevPage" disabled>← Prev</button>
            <span class="pagination__info" id="pageInfo">Page 1</span>
            <button class="btn btn--outline btn--sm" id="nextPage">Next →</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="shop-mobile-actions" id="shopMobileActions" aria-label="Shop actions">
    <button type="button" class="shop-mobile-actions__btn" id="mobileFilterBtn">Filter</button>
    <button type="button" class="shop-mobile-actions__btn" id="mobileSortBtn">Sort</button>
    <button type="button" class="shop-mobile-actions__btn" id="mobileSearchBtn">Search</button>
    <a class="shop-mobile-actions__btn shop-mobile-actions__btn--cart" id="mobileCartBtn" href="/cart">
      Cart
      <span class="shop-mobile-actions__count" id="mobileShopCartCount">0</span>
    </a>
  </div>

</main>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>

<!-- Quick-view modal -->
<div class="modal" id="quickViewModal" role="dialog" aria-modal="true" aria-label="Quick view" hidden>
  <div class="modal__box modal__box--wide">
    <button class="modal__close" id="quickViewClose" aria-label="Close">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div id="quickViewContent">Loading...</div>
  </div>
</div>
<div class="modal-backdrop" id="quickViewBackdrop" hidden></div>
