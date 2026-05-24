<?php
$pageTitle = 'Sub Users';
require_once __DIR__ . '/layout.php';

if (!admin_is_super_admin()) {
    http_response_code(403);
    echo '<p style="padding:2rem;color:#b00;">Access denied - Super Admin only.</p>';
    exit;
}

$permissionOptions = admin_permission_definitions();
unset($permissionOptions['sub_users']);
$permissionGroups = admin_grouped_permissions();
$labelDefinitions = admin_label_definitions();
$labelPresets = admin_label_presets();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editAdmin = null;
$editPerms = array();

if ($editId > 0) {
    $adminStmt = $conn->prepare('SELECT id, full_name, email, role, department_label, is_active FROM admins WHERE id = ? AND role <> "super_admin" LIMIT 1');
    if ($adminStmt) {
        $adminStmt->bind_param('i', $editId);
        $adminStmt->execute();
        $adminResult = $adminStmt->get_result();
        $editAdmin = $adminResult ? $adminResult->fetch_assoc() : null;
        $adminStmt->close();
    }

    if ($editAdmin) {
        $permStmt = $conn->prepare('SELECT permission_key FROM admin_permissions WHERE admin_id = ?');
        if ($permStmt) {
            $permStmt->bind_param('i', $editId);
            $permStmt->execute();
            $permResult = $permStmt->get_result();
            while ($permResult && ($row = $permResult->fetch_assoc())) {
                $editPerms[] = (string)$row['permission_key'];
            }
            $permStmt->close();
        }
    }
}

$admins = array();
$listResult = $conn->query('SELECT id, full_name, email, role, department_label, is_active, created_at FROM admins WHERE role <> "super_admin" ORDER BY created_at DESC');
while ($listResult && ($row = $listResult->fetch_assoc())) {
    $admins[] = $row;
}

$permissionMap = array();
$permissionRows = $conn->query('SELECT admin_id, permission_key FROM admin_permissions ORDER BY admin_id ASC');
while ($permissionRows && ($row = $permissionRows->fetch_assoc())) {
    $permissionMap[(int)$row['admin_id']][] = (string)$row['permission_key'];
}

$flash = trim((string)($_GET['status'] ?? ''));
$errorMsg = '';
if (isset($_SESSION['au_error'])) {
    $errorMsg = (string)$_SESSION['au_error'];
    unset($_SESSION['au_error']);
}
?>
<style>
.au-wrap { display: grid; gap: 18px; }
.au-grid { display: grid; grid-template-columns: 380px minmax(0,1fr); gap: 18px; }
.au-card { background: #fff; border: 1px solid rgba(128,0,31,.12); border-radius: 16px; box-shadow: 0 10px 24px rgba(68,16,34,.08); overflow: hidden; }
.au-head { padding: 14px 16px; border-bottom: 1px solid rgba(128,0,31,.09); background: linear-gradient(180deg,#fff8fa 0%,#fff 100%); display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.au-head h2,.au-head h3 { margin: 0; color: #80001F; font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; }
.au-body { padding: 16px; }
.au-alert { border-radius: 10px; padding: 10px 12px; font-size: .85rem; }
.au-alert--ok { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
.au-alert--err { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
.au-form { display: grid; gap: 12px; }
.au-form label { display: grid; gap: 6px; font-size: .79rem; font-weight: 700; color: #6e2a3e; text-transform: uppercase; letter-spacing: .07em; }
.au-form input,.au-form select { border: 1px solid rgba(128,0,31,.2); border-radius: 10px; padding: 10px 12px; font: inherit; }
.au-presets { display: flex; flex-wrap: wrap; gap: 8px; }
.au-preset { border: 1px solid rgba(128,0,31,.24); border-radius: 999px; padding: 6px 10px; background: #fff8fa; color: #80001F; cursor: pointer; font-size: .76rem; font-weight: 700; }
.au-matrix { border: 1px solid rgba(128,0,31,.14); border-radius: 12px; overflow: hidden; }
.au-group { border-top: 1px solid rgba(128,0,31,.08); }
.au-group:first-child { border-top: none; }
.au-group-title { background: #fff6f8; color: #80001F; font-size: .78rem; font-weight: 700; padding: 8px 10px; text-transform: uppercase; letter-spacing: .06em; }
.au-group-items { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 8px; padding: 10px; }
.au-group-items label { font-size: .8rem; text-transform: none; letter-spacing: 0; font-weight: 600; color: #2d1f25; display: flex; align-items: center; gap: 6px; }
.au-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.au-btn { border: 0; border-radius: 10px; padding: 9px 12px; font-weight: 700; text-decoration: none; cursor: pointer; font-size: .8rem; }
.au-btn--primary { background: #80001F; color: #fff; }
.au-btn--ghost { background: #fff; border: 1px solid rgba(128,0,31,.22); color: #80001F; }
.au-btn--danger { background: #dc2626; color: #fff; }
.au-users { display: grid; gap: 10px; }
.au-user { border: 1px solid rgba(128,0,31,.12); border-radius: 12px; background: #fff; }
.au-user-top { display: flex; justify-content: space-between; align-items: start; gap: 10px; padding: 12px; }
.au-user-name { font-weight: 700; color: #2d1f25; }
.au-user-email { color: #7f6973; font-size: .8rem; }
.au-badges { display: flex; gap: 6px; flex-wrap: wrap; padding: 0 12px 10px; }
.au-badge { padding: 3px 8px; border-radius: 999px; font-size: .7rem; font-weight: 700; background: #fff1f4; color: #9d174d; border: 1px solid rgba(128,0,31,.1); }
.au-user-actions { display: flex; gap: 8px; flex-wrap: wrap; padding: 10px 12px; border-top: 1px solid rgba(128,0,31,.08); background: #fff8fa; }
@media (max-width: 960px) {
  .au-grid { grid-template-columns: 1fr; }
  .au-group-items { grid-template-columns: 1fr; }
}
</style>

<div class="au-wrap">
  <?php if ($errorMsg !== ''): ?><div class="au-alert au-alert--err"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  <?php if ($flash === 'saved'): ?><div class="au-alert au-alert--ok">Sub user created.</div><?php endif; ?>
  <?php if ($flash === 'updated'): ?><div class="au-alert au-alert--ok">Sub user updated.</div><?php endif; ?>
  <?php if ($flash === 'toggled'): ?><div class="au-alert au-alert--ok">Sub user status updated.</div><?php endif; ?>
  <?php if ($flash === 'deleted'): ?><div class="au-alert au-alert--ok">Sub user deleted.</div><?php endif; ?>

  <div class="au-grid">
    <section class="au-card">
      <div class="au-head">
        <h2><?php echo $editAdmin ? 'Edit Sub User' : 'Create Sub User'; ?></h2>
        <?php if ($editAdmin): ?><a class="au-btn au-btn--ghost" href="admin_users.php">Cancel</a><?php endif; ?>
      </div>
      <div class="au-body">
        <form class="au-form" method="POST" action="save_admin_user.php" autocomplete="off">
          <input type="hidden" name="admin_id" value="<?php echo $editAdmin ? (int)$editAdmin['id'] : 0; ?>">

          <label>Full Name
            <input type="text" name="full_name" required maxlength="120" value="<?php echo htmlspecialchars($editAdmin ? (string)$editAdmin['full_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>

          <label>Email
            <input type="email" name="email" required maxlength="190" value="<?php echo htmlspecialchars($editAdmin ? (string)$editAdmin['email'] : '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>

          <label>Password <?php if ($editAdmin): ?><span style="font-weight:400; text-transform:none; letter-spacing:0; color:#8f7681;">(leave blank to keep current)</span><?php endif; ?>
            <input type="password" name="password" <?php echo $editAdmin ? '' : 'required'; ?> minlength="6" autocomplete="new-password">
          </label>

          <label>Department Label
            <select name="department_label" id="au_department_label">
              <option value="">None</option>
              <?php foreach ($labelDefinitions as $labelKey => $labelMeta): ?>
                <option value="<?php echo htmlspecialchars((string)$labelKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($editAdmin && (string)$editAdmin['department_label'] === (string)$labelKey) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string)$labelMeta['label'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <div>
            <div style="font-size:.79rem; font-weight:700; color:#6e2a3e; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">Quick Presets</div>
            <div class="au-presets">
              <?php foreach ($labelDefinitions as $labelKey => $labelMeta): ?>
                <button type="button" class="au-preset" data-preset="<?php echo htmlspecialchars((string)$labelKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$labelMeta['label'], ENT_QUOTES, 'UTF-8'); ?></button>
              <?php endforeach; ?>
              <button type="button" class="au-preset" data-preset="__clear">Clear</button>
            </div>
          </div>

          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
              <div style="font-size:.79rem; font-weight:700; color:#6e2a3e; text-transform:uppercase; letter-spacing:.07em;">Permissions</div>
              <button type="button" id="au_select_all" class="au-preset" style="font-size:.72rem;">Select All</button>
            </div>
            <div class="au-matrix">
              <?php foreach ($permissionGroups as $groupKey => $groupData): ?>
                <?php if ($groupKey === 'admin') { continue; } ?>
                <div class="au-group">
                  <div class="au-group-title"><?php echo htmlspecialchars((string)$groupData['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="au-group-items">
                    <?php foreach ($groupData['permission_labels'] as $permissionKey => $permissionLabel): ?>
                      <?php if ($permissionKey === 'sub_users') { continue; } ?>
                      <label>
                        <input class="au-perm" type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars((string)$permissionKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($permissionKey, $editPerms, true) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars((string)$permissionLabel, ENT_QUOTES, 'UTF-8'); ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <label style="display:flex; align-items:center; gap:8px; text-transform:none; letter-spacing:0; font-weight:600; color:#2d1f25;">
            <input type="checkbox" name="is_active" value="1" <?php echo (!$editAdmin || (int)$editAdmin['is_active'] === 1) ? 'checked' : ''; ?>>
            Account active
          </label>

          <div class="au-actions">
            <button class="au-btn au-btn--primary" type="submit"><?php echo $editAdmin ? 'Update Sub User' : 'Create Sub User'; ?></button>
            <?php if ($editAdmin): ?><a class="au-btn au-btn--ghost" href="admin_users.php">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>
    </section>

    <section class="au-card">
      <div class="au-head"><h3>Sub Users (<?php echo count($admins); ?>)</h3></div>
      <div class="au-body">
        <?php if (!$admins): ?>
          <p style="margin:0; color:#8f7681;">No sub users created yet.</p>
        <?php else: ?>
          <div class="au-users">
            <?php foreach ($admins as $adminRow): ?>
              <?php
              $uid = (int)$adminRow['id'];
              $rowPerms = isset($permissionMap[$uid]) ? $permissionMap[$uid] : array();
              $isActive = (int)$adminRow['is_active'] === 1;
              ?>
              <article class="au-user">
                <div class="au-user-top">
                  <div>
                    <div class="au-user-name"><?php echo htmlspecialchars((string)$adminRow['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="au-user-email"><?php echo htmlspecialchars((string)$adminRow['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <span class="au-badge" style="background:<?php echo $isActive ? '#dcfce7' : '#fee2e2'; ?>; color:<?php echo $isActive ? '#166534' : '#991b1b'; ?>; border-color:transparent;">
                    <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                  </span>
                </div>

                <div class="au-badges">
                  <?php if (!empty($adminRow['department_label']) && isset($labelDefinitions[$adminRow['department_label']])): ?>
                    <span class="au-badge"><?php echo htmlspecialchars((string)$labelDefinitions[$adminRow['department_label']]['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endif; ?>
                  <?php if (!$rowPerms): ?>
                    <span class="au-badge">No module access</span>
                  <?php endif; ?>
                  <?php foreach ($rowPerms as $permissionKey): ?>
                    <span class="au-badge"><?php echo htmlspecialchars((string)($permissionOptions[$permissionKey] ?? $permissionKey), ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endforeach; ?>
                </div>

                <div class="au-user-actions">
                  <a class="au-btn au-btn--ghost" href="admin_users.php?edit=<?php echo $uid; ?>">Edit</a>
                  <form method="POST" action="save_admin_user.php" style="display:inline;">
                    <input type="hidden" name="toggle_id" value="<?php echo $uid; ?>">
                    <button class="au-btn au-btn--ghost" type="submit"><?php echo $isActive ? 'Deactivate' : 'Activate'; ?></button>
                  </form>
                  <form method="POST" action="save_admin_user.php" style="display:inline;" onsubmit="return confirm('Delete this sub user? This cannot be undone.');">
                    <input type="hidden" name="delete_id" value="<?php echo $uid; ?>">
                    <button class="au-btn au-btn--danger" type="submit">Delete</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<script>
(function () {
  var presetMap = <?php echo json_encode($labelPresets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var departmentSelect = document.getElementById('au_department_label');
  var permissionChecks = document.querySelectorAll('.au-perm');

  function applyPreset(key) {
    if (key === '__clear') {
      permissionChecks.forEach(function (cb) { cb.checked = false; });
      return;
    }

    var allowed = presetMap[key] || [];
    permissionChecks.forEach(function (cb) {
      cb.checked = allowed.indexOf(cb.value) !== -1;
    });

    if (departmentSelect) {
      departmentSelect.value = key;
    }
  }

  document.querySelectorAll('.au-preset').forEach(function (btn) {
    btn.addEventListener('click', function () {
      applyPreset(btn.getAttribute('data-preset'));
    });
  });

  var selectAllBtn = document.getElementById('au_select_all');
  function syncSelectAllLabel() {
    if (!selectAllBtn) return;
    selectAllBtn.textContent = Array.from(permissionChecks).every(function (c) { return c.checked; }) ? 'Deselect All' : 'Select All';
  }
  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function () {
      var allChecked = Array.from(permissionChecks).every(function (cb) { return cb.checked; });
      permissionChecks.forEach(function (cb) { cb.checked = !allChecked; });
      syncSelectAllLabel();
    });
    permissionChecks.forEach(function (cb) { cb.addEventListener('change', syncSelectAllLabel); });
    syncSelectAllLabel();
  }
}());
</script>
