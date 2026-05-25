<?php
/**
 * Product Import Version History
 * Admin page to view and manage product import versions
 */

$pageTitle = "Import Version History";
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/layout.php';

// Get recent imports from API (for modern approach)
// For now, query directly from DB
$query = "
    SELECT 
        id,
        run_number,
        import_type,
        total_products_uploaded,
        products_inserted,
        products_updated,
        products_deleted,
        status,
        created_at,
        completed_at
    FROM product_import_runs
    ORDER BY run_number DESC
    LIMIT 50
";

$result = $mysqli->query($query);
$versions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<style>
.version-history-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.version-header {
    margin-bottom: 30px;
}

.version-header h1 {
    font-size: 28px;
    margin-bottom: 10px;
    color: #333;
}

.version-header p {
    color: #666;
    font-size: 14px;
}

.versions-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}

.versions-table thead {
    background: #f5f5f5;
}

.versions-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
    font-size: 13px;
}

.versions-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}

.versions-table tr:hover {
    background: #f9f9f9;
}

.version-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.version-badge--full {
    background: #e3f2fd;
    color: #1976d2;
}

.version-badge--partial {
    background: #fff3e0;
    color: #f57c00;
}

.version-badge--restore {
    background: #f3e5f5;
    color: #7b1fa2;
}

.version-status {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-completed {
    background: #4caf50;
}

.status-failed {
    background: #f44336;
}

.status-processing {
    background: #ff9800;
}

.version-stats {
    display: flex;
    gap: 20px;
    font-size: 13px;
}

.stat {
    display: flex;
    flex-direction: column;
}

.stat-label {
    color: #999;
    font-size: 11px;
    text-transform: uppercase;
}

.stat-value {
    font-weight: 600;
    color: #333;
}

.version-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.action-btn--restore {
    background: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #81c784;
}

.action-btn--restore:hover {
    background: #c8e6c9;
}

.action-btn--view {
    background: #e3f2fd;
    color: #1976d2;
    border: 1px solid #64b5f6;
}

.action-btn--view:hover {
    background: #bbdefb;
}

.no-versions {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.restore-confirm-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.restore-confirm-modal.active {
    display: flex;
}

.restore-modal-content {
    background: white;
    padding: 30px;
    border-radius: 8px;
    max-width: 400px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.restore-modal-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #333;
}

.restore-modal-message {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
}

.restore-modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.restore-modal-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
}

.restore-modal-btn--cancel {
    background: #f0f0f0;
    color: #333;
}

.restore-modal-btn--confirm {
    background: #4caf50;
    color: white;
}

@media (max-width: 768px) {
    .version-history-page {
        padding: 10px;
    }
    
    .versions-table {
        font-size: 12px;
    }
    
    .versions-table th,
    .versions-table td {
        padding: 8px;
    }
    
    .version-stats {
        gap: 10px;
    }
    
    .version-actions {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="version-history-page">
    <div class="version-header">
        <h1>Product Import Version History</h1>
        <p>View and restore previous product imports</p>
    </div>
    
    <?php if (empty($versions)): ?>
        <div class="no-versions">
            <p>No import versions found</p>
        </div>
    <?php else: ?>
        <table class="versions-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Statistics</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $version): ?>
                    <tr>
                        <td>
                            <strong>#<?php echo $version['run_number']; ?></strong>
                        </td>
                        <td>
                            <span class="version-badge version-badge--<?php echo strtolower($version['import_type']); ?>">
                                <?php echo ucfirst($version['import_type']); ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo date('M d, Y H:i', strtotime($version['created_at'])); ?></small>
                        </td>
                        <td>
                            <div class="version-status">
                                <span class="status-indicator status-<?php echo strtolower($version['status']); ?>"></span>
                                <?php echo ucfirst($version['status']); ?>
                            </div>
                        </td>
                        <td>
                            <div class="version-stats">
                                <div class="stat">
                                    <span class="stat-label">Uploaded</span>
                                    <span class="stat-value"><?php echo $version['total_products_uploaded']; ?></span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Added</span>
                                    <span class="stat-value"><?php echo $version['products_inserted']; ?></span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Updated</span>
                                    <span class="stat-value"><?php echo $version['products_updated']; ?></span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Deleted</span>
                                    <span class="stat-value"><?php echo $version['products_deleted']; ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="version-actions">
                                <button class="action-btn action-btn--view" onclick="viewVersionDetails(<?php echo $version['id']; ?>)">
                                    View
                                </button>
                                <button class="action-btn action-btn--restore" onclick="confirmRestore(<?php echo $version['id']; ?>, <?php echo $version['run_number']; ?>)">
                                    Restore
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Restore Confirmation Modal -->
<div class="restore-confirm-modal" id="restoreModal">
    <div class="restore-modal-content">
        <div class="restore-modal-title">Restore to Version?</div>
        <div class="restore-modal-message" id="restoreMessage">
            This will restore all products to this version's state. <strong>This action cannot be undone.</strong>
        </div>
        <div class="restore-modal-actions">
            <button class="restore-modal-btn restore-modal-btn--cancel" onclick="cancelRestore()">Cancel</button>
            <button class="restore-modal-btn restore-modal-btn--confirm" onclick="performRestore()">Restore</button>
        </div>
    </div>
</div>

<script>
let restoreVersionId = null;
let restoreVersionNumber = null;

function viewVersionDetails(versionId) {
    // Could open a modal or navigate to detail page
    alert('View details for version ' + versionId + ' - To be implemented');
}

function confirmRestore(versionId, versionNumber) {
    restoreVersionId = versionId;
    restoreVersionNumber = versionNumber;
    document.getElementById('restoreMessage').innerHTML = 
        'This will restore all products to version <strong>#' + versionNumber + '</strong>.<br>' +
        'This action cannot be undone.';
    document.getElementById('restoreModal').classList.add('active');
}

function cancelRestore() {
    document.getElementById('restoreModal').classList.remove('active');
    restoreVersionId = null;
    restoreVersionNumber = null;
}

function performRestore() {
    if (!restoreVersionId) return;
    
    const button = event.target;
    button.disabled = true;
    button.textContent = 'Restoring...';
    
    fetch('/api/admin/restore/version/' + restoreVersionId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Restored ' + data.data.products_restored + ' products to version #' + restoreVersionNumber);
            if (window.CakeScrollPreserver && typeof window.CakeScrollPreserver.reload === 'function') {
                window.CakeScrollPreserver.reload();
            } else {
                location.reload();
            }
        } else {
            alert('✗ Restore failed: ' + data.message);
            button.disabled = false;
            button.textContent = 'Restore';
        }
    })
    .catch(error => {
        alert('✗ Error: ' + error.message);
        button.disabled = false;
        button.textContent = 'Restore';
    });
}

</script>
<script src="/client/assets/js/scroll-preserve.js"></script>
