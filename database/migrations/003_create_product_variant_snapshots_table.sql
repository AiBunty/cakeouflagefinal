-- Migration: Create product_variant_snapshots table for variant tracking
-- Purpose: Capture variant data for products that have variants

CREATE TABLE IF NOT EXISTS product_variant_snapshots (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- Link to product snapshot
    snapshot_id BIGINT UNSIGNED NOT NULL,
    
    -- Link to import run (for direct querying)
    run_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    
    -- Variant reference
    variant_id BIGINT UNSIGNED NOT NULL,
    variant_sku VARCHAR(100) NULL,
    
    -- Variant data snapshot
    variant_data LONGTEXT NOT NULL COMMENT 'JSON snapshot of variant record',
    
    -- Variant-specific metadata
    variant_option_values VARCHAR(255) NULL COMMENT 'e.g., "Size: Large, Color: Red"',
    variant_price DECIMAL(10, 2) NULL,
    variant_stock INT UNSIGNED DEFAULT 0,
    
    -- Sequence
    sequence_number INT UNSIGNED NOT NULL,
    
    -- Timing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_snapshot_id (snapshot_id),
    INDEX idx_run_id (run_id),
    INDEX idx_product_id (product_id),
    INDEX idx_variant_id (variant_id),
    INDEX idx_created_at (created_at DESC),
    FOREIGN KEY (snapshot_id) REFERENCES product_snapshots(id) ON DELETE CASCADE,
    FOREIGN KEY (run_id) REFERENCES product_import_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Snapshot of product variants at import time for complete version history';
