<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Services\ProductImportService;
use PDO;

/**
 * RestoreApiController - Product version restore endpoints
 * 
 * Provides API endpoints for:
 * - Listing import versions
 * - Restoring to a specific version
 * - Getting version details
 */
final class RestoreApiController
{
    private ProductImportService $importService;
    private ?PDO $pdo;
    
    public function __construct()
    {
        try {
            $this->pdo = Database::getConnection();
            $this->importService = new ProductImportService();
        } catch (\Throwable $e) {
            $this->pdo = null;
        }
    }
    
    /**
     * GET /api/admin/restore/versions
     * Get list of available import versions
     */
    public function listVersions(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }
        
        if (!$this->pdo) {
            Response::json(['success' => false, 'message' => 'Database unavailable'], 503);
            return;
        }
        
        try {
            $versions = $this->importService->listRecentImports(50);
            
            Response::json([
                'success' => true,
                'data' => [
                    'versions' => $versions,
                    'total' => count($versions)
                ]
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Failed to fetch versions: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /api/admin/restore/version/:id
     * Get details of a specific import version
     */
    public function getVersion(string $versionId): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }
        
        if (!$this->pdo) {
            Response::json(['success' => false, 'message' => 'Database unavailable'], 503);
            return;
        }
        
        $runId = (int)$versionId;
        if ($runId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid version ID'], 400);
            return;
        }
        
        try {
            $version = $this->importService->getImportRun($runId);
            
            if (!$version) {
                Response::json(['success' => false, 'message' => 'Version not found'], 404);
                return;
            }
            
            $snapshots = $this->importService->getRunSnapshots($runId);
            
            Response::json([
                'success' => true,
                'data' => [
                    'version' => $version,
                    'snapshots' => $snapshots,
                    'snapshot_count' => count($snapshots)
                ]
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Failed to fetch version: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /api/admin/restore/version/:id
     * Restore products to a specific version
     */
    public function restoreVersion(string $versionId): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }
        
        if (!$this->pdo) {
            Response::json(['success' => false, 'message' => 'Database unavailable'], 503);
            return;
        }
        
        $runId = (int)$versionId;
        if ($runId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid version ID'], 400);
            return;
        }
        
        try {
            // Verify version exists
            $version = $this->importService->getImportRun($runId);
            if (!$version) {
                Response::json(['success' => false, 'message' => 'Version not found'], 404);
                return;
            }
            
            // Log the restore action
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_action_logs (admin_id, action, details, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            $restoreCount = $this->importService->restoreImportVersion($runId);
            
            $details = json_encode([
                'source_version_id' => $runId,
                'source_version_run_number' => $version['run_number'],
                'products_restored' => $restoreCount
            ]);
            
            $stmt->execute([
                $adminId,
                'PRODUCT_VERSION_RESTORE',
                $details
            ]);
            
            Response::json([
                'success' => true,
                'message' => "Successfully restored $restoreCount products to version " . $version['run_number'],
                'data' => [
                    'products_restored' => $restoreCount,
                    'source_version' => $version['run_number']
                ]
            ]);
            
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /api/admin/restore/cleanup
     * Clean up old versions (admin only)
     */
    public function cleanupVersions(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }
        
        // Verify admin is superadmin
        if (!$this->isAdminSuperAdmin($adminId)) {
            Response::json(['success' => false, 'message' => 'Only superadmin can cleanup versions'], 403);
            return;
        }
        
        if (!$this->pdo) {
            Response::json(['success' => false, 'message' => 'Database unavailable'], 503);
            return;
        }
        
        try {
            $cleanedCount = $this->importService->cleanupOldVersions();
            
            // Log the action
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_action_logs (admin_id, action, details, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $adminId,
                'PRODUCT_VERSION_CLEANUP',
                json_encode(['versions_deleted' => $cleanedCount])
            ]);
            
            Response::json([
                'success' => true,
                'message' => "Cleaned up $cleanedCount old versions",
                'data' => ['versions_deleted' => $cleanedCount]
            ]);
            
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helper: Require admin authentication
     */
    private function requireAdminId(): ?int
    {
        $adminId = $_SESSION['admin_id'] ?? null;
        
        if ($adminId === null || (int)$adminId <= 0) {
            Response::json(['success' => false, 'message' => 'Admin authentication required'], 401);
            return null;
        }
        
        return (int)$adminId;
    }
    
    /**
     * Helper: Check if admin is superadmin
     */
    private function isAdminSuperAdmin(int $adminId): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("SELECT role FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $admin && strtolower((string)$admin['role']) === 'superadmin';
    }
}
