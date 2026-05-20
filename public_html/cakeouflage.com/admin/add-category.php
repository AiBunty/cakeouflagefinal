<?php
$pageTitle = "Add Category";
include "layout.php";

require __DIR__ . '/includes/db.php';
/* ---------- HANDLE SUBMIT ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $name        = trim($_POST['name'] ?? '');
  $parent_id   = ($_POST['parent_id'] !== '') ? (int)$_POST['parent_id'] : NULL;
  $sort_order  = (int)($_POST['sort_order'] ?? 0);
  $is_active   = isset($_POST['is_active']) ? 1 : 0;

  // slug auto
  $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

  // image upload (optional)
  $db_image_path = NULL;
  if (!empty($_FILES['image']['name'])) {
    $imgName = time() . "_" . basename($_FILES['image']['name']);
    $tmp     = $_FILES['image']['tmp_name'];

    $uploadDir  = "../client/assets/images/categories/";
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }

    $uploadPath = $uploadDir . $imgName;

    if (move_uploaded_file($tmp, $uploadPath)) {
      // DB मध्ये absolute web path
      $db_image_path = "/client/assets/images/categories/" . $imgName;
    }
  }

  // prepare (safe)
  $stmt = $conn->prepare("
    INSERT INTO categories
    (name, slug, parent_id, image, sort_order, is_active, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");

  // bind types: s s i s i i  (parent_id nullable)
  $stmt->bind_param(
    "ssisii",
    $name,
    $slug,
    $parent_id,
    $db_image_path,
    $sort_order,
    $is_active
  );

  if ($stmt->execute()) {
    echo "<script>alert('Category Added'); window.location='categories.php';</script>";
    exit;
  } else {
    echo "Error: " . $stmt->error;
  }
}

/* ---------- FETCH FOR DROPDOWN ---------- */
$res = $conn->query("SELECT id, name, parent_id FROM categories WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");

$all = [];
while ($r = $res->fetch_assoc()) {
  $all[] = $r;
}

/* render options recursively */
function renderOptions($cats, $parent = NULL, $level = 0) {
  foreach ($cats as $c) {
    if ($c['parent_id'] == $parent) {
      $indent = str_repeat("-- ", $level);
      echo "<option value='{$c['id']}'>{$indent}{$c['name']}</option>";
      renderOptions($cats, $c['id'], $level + 1);
    }
  }
}
?>

<div class="card" style="max-width:700px;">
  <h2>Add Category</h2>

  <form method="POST" enctype="multipart/form-data">

    <div class="form-group">
      <label>Category Name</label>
      <input type="text" name="name" required>
    </div>

    <div class="form-group">
      <label>Parent Category</label>
      <select name="parent_id">
        <option value="">-- Main Category --</option>
        <?php renderOptions($all); ?>
      </select>
    </div>

    <div class="form-group">
      <label>Sort Order</label>
      <input type="number" name="sort_order" value="0">
    </div>

    <div class="form-group">
      <label>Image</label>
      <input type="file" name="image">
    </div>

    <div class="form-group">
      <label>
        <input type="checkbox" name="is_active" checked>
        Active
      </label>
    </div>

    <button class="btn">Add Category</button>

  </form>
</div>

<style>
.form-group { margin-bottom:15px; }
input, select {
  width:100%;
  padding:10px;
  border-radius:8px;
  border:1px solid #ddd;
}
.btn {
  background:#6D1B3B;
  color:#fff;
  padding:10px 16px;
  border:none;
  border-radius:8px;
  cursor:pointer;
}
.btn:hover { background:#57142f; }
</style>

</div>
</div>
</body>
</html>