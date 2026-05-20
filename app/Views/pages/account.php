<?php /* Cakeouflage — My Account Dashboard */ ?>
<main class="section" data-page="account">
  <div class="container">
    <div class="account-layout">

      <!-- Sidebar -->
      <aside class="account-sidebar">
        <div class="account-sidebar__avatar" id="accountAvatar">👤</div>
        <div class="account-sidebar__name" id="accountName">Loading…</div>
        <nav class="account-sidebar__nav" aria-label="Account navigation">
          <a href="#profile" class="account-nav-link active" data-tab="profile">👤 My Profile</a>
          <a href="#orders" class="account-nav-link" data-tab="orders">📦 My Orders</a>
            <!--         <a href="#wishlist" class="account-nav-link" data-tab="wishlist">❤️ Wishlist</a> -->
  
          <a href="#addresses" class="account-nav-link" data-tab="addresses">📍 Addresses</a>
        <a href="/logout" class="account-nav-link account-nav-link--logout">🚪 Sign Out</a>
        </nav>
      </aside>

      <!-- Content -->
      <div class="account-content">

        <!-- Auth gate -->
        <div class="account-gate" id="accountGate">
          <div class="account-gate__inner">
            <span class="account-gate__icon">🎂</span>
            <h2>Sign in to Your Account</h2>
            <p>View your orders, wishlist, and manage your profile.</p>
           <a href="/login" class="btn btn--primary btn--lg">Sign In</a>
            <p>New here? <a href="/register" class="link">Create an account →</a></p>
          </div>
        </div>

        <!-- Profile Tab -->
        <section class="account-tab" id="tab-profile" style="display:none">
          <h2 class="account-tab__title">My Profile</h2>
          <form class="form-grid" id="profileForm">
            <div class="form-row-2">
              <label class="form-control">
                <span class="form-label">Full Name</span>
                <input type="text" name="full_name" id="profileFullName">
              </label>
              <label class="form-control">
                <span class="form-label">Phone</span>
                <input type="tel" name="phone" id="profilePhone">
              </label>
            </div>
            <label class="form-control">
              <span class="form-label">Email</span>
              <input type="email" name="email" id="profileEmail" disabled>
            </label>
            <label class="form-control">
              <span class="form-label">Date of Birthday (for birthday surprises 🎂)</span>
              <input type="date" name="dob" id="profileDob">
            </label>
            <label class="form-control">
              <span class="form-label">Date of Anniversary (DOA)</span>
              <input type="date" name="doa" id="profileDoa">
            </label>
            <button class="btn btn--primary" type="submit">Save Changes</button>
            <p id="profileStatus" class="form-feedback" aria-live="polite"></p>
          </form>
        </section>

        <!-- Orders Tab -->
        <section class="account-tab" id="tab-orders" style="display:none">
          <h2 class="account-tab__title">My Orders</h2>
          <div id="ordersListContainer">
            <div class="empty-state">
              <span class="empty-state__icon">📦</span>
              <p>No orders yet. <a href="<?= $baseUrl ?>/shop" class="link">Browse our cakes →</a></p>
            </div>
          </div>
        </section>


        <!-- Wishlist Tab -->
        <section class="account-tab" id="tab-wishlist" style="display:none">
          <h2 class="account-tab__title">My Wishlist</h2>
          <div class="product-grid" id="wishlistContainer">
            <div class="empty-state">
              <span class="empty-state__icon">❤️</span>
              <p>No saved items yet. <a href="<?= $baseUrl ?>/shop" class="link">Start browsing →</a></p>
            </div>
          </div>
        </section>

        <!-- Addresses Tab -->
        <section class="account-tab" id="tab-addresses" style="display:none">
          <h2 class="account-tab__title">Saved Addresses</h2>
          <div id="addressListContainer"></div>
          <button class="btn btn--secondary" id="addAddressBtn" type="button" style="margin-top:var(--space-4)">+ Add New Address</button>
        </section>

      </div>
    </div>
  </div>
</main>

<script>
async function loadAccount() {
  try {
    const res = await fetch("/api/auth/me", {
      credentials: "include"
    });

    const data = await res.json();

    if (!data.success) {
      document.getElementById("accountGate").style.display = "block";
      return;
    }

    const user = data.data.user;

    // ✅ Sidebar name
    document.getElementById("accountName").textContent = user.full_name || "User";

    // ✅ Profile form fill
    document.getElementById("profileFullName").value = user.full_name || "";
    document.getElementById("profilePhone").value = user.phone || "";
    document.getElementById("profileEmail").value = user.email || "";
    const profileDoa = document.getElementById("profileDoa");
    if (profileDoa) {
      profileDoa.value = "";
    }

    // ✅ show content
    document.getElementById("accountGate").style.display = "none";
    document.getElementById("tab-profile").style.display = "block";

  } catch (err) {
    console.error(err);
  }
}

loadAccount();
</script>