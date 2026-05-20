-- Migration: Create product_snapshots table for capturing product state at import time
-- Purpose: Store complete product data before/after import for restore capability

CREATE TABLE IF NOT EXISTS product_snapshots (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- Link to import run
    run_id BIGINT UNSIGNED NOT NULL,
    
    -- Product reference
    product_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL,
    
    -- Complete product data snapshot (JSON for flexibility)
    product_data LONGTEXT NOT NULL COMMENT 'JSON snapshot of complete product record',
    
    -- Metadata
    operation ENUM('insert', 'update', 'delete', 'restore') NOT NULL,
    sequence_number INT UNSIGNED NOT NULL COMMENT 'Order within the import run',
    
    -- Optional variant snapshots reference
    has_variants BOOLEAN DEFAULT FALSE,
    variant_count INT UNSIGNED DEFAULT 0,
    
    -- Timing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Soft delete
    deleted_at TIMESTAMP NULL,
    
    -- Indexes
    INDEX idx_run_id (run_id),
    INDEX idx_product_id (product_id),
    INDEX idx_sku (sku),
    INDEX idx_operation (operation),
    INDEX idx_created_at (created_at DESC),
    INDEX idx_run_product (run_id, product_id),
    INDEX idx_product_latest (product_id, run_id DESC),
    FOREIGN KEY (run_id) REFERENCES product_import_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Snapshot of product data at each import operation for version history and restore';
