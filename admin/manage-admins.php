<?php
$pageTitle = 'Manage Admins';
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';
include __DIR__ . '/layout.php';

$status = trim((string)($_GET['status'] ?? ''));
$message = trim((string)($_GET['message'] ?? ''));
$editId = (int)($_GET['edit'] ?? 0);

$admins = $conn->query('SELECT id, full_name, email, role, is_active FROM admins ORDER BY created_at DESC');

$selectedAdmin = null;
$selectedPermissions = array();

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT id, full_name, email, role, is_active FROM admins WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    $selectedAdmin = $result ? $result->fetch_assoc() : null;
    
    if ($selectedAdmin) {
        $permsResult = $conn->query('SELECT permission_key FROM admin_permissions WHERE admin_id = ' . (int)$editId);
        while ($perm = $permsResult->fetch_assoc()) {
            $selectedPermissions[$perm['permission_key']] = true;
        }
    }
}

$allPermissions = array(
    'order_refund' => 'Process Order Refunds',
    'order_reject' => 'Reject/Cancel Orders',
    'order_edit' => 'Edit Orders (Details, Status)',
    'product_edit' => 'Edit Products',
    'product_delete' => 'Delete Products',
    'category_edit' => 'Edit Categories',
    'user_view' => 'View Users',
    'settings_edit' => 'Edit Business Settings',
    'reports_view' => 'View Reports'
);
?>

<style>
  .admin-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 12px;
    overflow: hidden;
  }
  
  .admin-table th {
    background: #fff8fa;
    border-bottom: 1px solid rgba(128, 0, 31, 0.12);
    padding: 12px 14px;
    text-align: left;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    color: #80001F;
  }
  
  .admin-table td {
    padding: 12px 14px;
    border-bottom: 1px solid rgba(128, 0, 31, 0.08);
    font-size: 0.93rem;
  }
  
  .admin-table tr:hover {
    background: #fafaf8;
  }
  
  .badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.78rem;
    font-weight: 600;
  }
  
  .badge.active {
    background: #dcfce7;
    color: #166534;
  }
  
  .badge.inactive {
    background: #fecdd3;
    color: #9f1239;
  }
  
  .btn-edit {
    background: #80001F;
    color: #fff;
    border: 0;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
  }
  
  .perm-panel {
    background: #fff;
    border: 1px solid rgba(128, 0, 31, 0.12);
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 18px;
  }
  
  .perm-panel h3 {
    color: #80001F;
    margin: 0 0 16px;
    font-size: 1.1rem;
  }
  
  .perm-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }
  
  .perm-item {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .perm-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
  }
  
  .perm-item label {
    cursor: pointer;
    font-size: 0.93rem;
    margin: 0;
  }
  
  .actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
  }
  
  .btn-save, .btn-cancel {
    border: 0;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
  }
  
  .btn-save {
    background: #80001F;
    color: #fff;
  }
  
  .btn-cancel {
    background: #f0f0f0;
    color: #333;
  }
  
  .notice {
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 0.9rem;
  }
  
  .notice.success {
    background: #ecfdf3;
    color: #166534;
    border: 1px solid #bbf7d0;
  }
  
  .notice.error {
    background: #fff1f2;
    color: #9f1239;
    border: 1px solid #fecdd3;
  }
  
  @media (max-width: 760px) {
    .perm-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div style="margin-bottom: 24px;">
  <h2>Manage Admins & Permissions</h2>
  
  <?php if ($status === 'success'): ?>
    <div class="notice success">✓ Permissions updated successfully</div>
  <?php elseif ($status === 'error'): ?>
    <div class="notice error">✗ <?= htmlspecialchars($message ?: 'Error updating permissions') ?></div>
  <?php endif; ?>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
  <!-- LEFT: Admin List -->
  <div>
    <h3 style="color: #80001F; margin-bottom: 12px;">All Admins</h3>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Role</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($admin = $admins->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($admin['full_name']) ?></td>
            <td><?= htmlspecialchars($admin['role']) ?></td>
            <td>
              <span class="badge <?= $admin['is_active'] ? 'active' : 'inactive' ?>">
                <?= $admin['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td>
              <a href="?edit=<?= (int)$admin['id'] ?>" class="btn-edit">Edit Perms</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- RIGHT: Permissions Editor -->
  <div>
    <?php if ($selectedAdmin): ?>
      <div class="perm-panel">
        <h3><?= htmlspecialchars($selectedAdmin['full_name']) ?></h3>
        
        <form action="save-admin-permissions.php" method="post">
          <input type="hidden" name="admin_id" value="<?= (int)$selectedAdmin['id'] ?>">
          
          <div class="perm-grid">
            <?php foreach ($allPermissions as $key => $label): ?>
              <div class="perm-item">
                <input 
                  type="checkbox" 
                  id="perm_<?= htmlspecialchars($key) ?>" 
                  name="permissions[]" 
                  value="<?= htmlspecialchars($key) ?>"
                  <?= isset($selectedPermissions[$key]) ? 'checked' : '' ?>
                >
                <label for="perm_<?= htmlspecialchars($key) ?>">
                  <?= htmlspecialchars($label) ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          
          <div class="actions">
            <button type="submit" class="btn-save">Save Permissions</button>
            <a href="manage-admins.php" class="btn-cancel" style="text-decoration: none;">Cancel</a>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="perm-panel" style="text-align: center; color: #999;">
        <p>Select an admin to edit permissions</p>
      </div>
    <?php endif; ?>
  </div>
</div>
