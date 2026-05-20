-- Migration: Create product_import_runs table for tracking import versioning
-- Purpose: Maintain history of product imports with snapshots for version control

CREATE TABLE IF NOT EXISTS product_import_runs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- Import metadata
    run_number INT UNSIGNED NOT NULL UNIQUE,
    import_type ENUM('full', 'partial') NOT NULL DEFAULT 'full',
    source_file VARCHAR(255) NULL,
    uploaded_by_admin_id BIGINT UNSIGNED NULL,
    
    -- Statistics
    total_products_uploaded INT UNSIGNED DEFAULT 0,
    products_inserted INT UNSIGNED DEFAULT 0,
    products_updated INT UNSIGNED DEFAULT 0,
    products_deleted INT UNSIGNED DEFAULT 0,
    validation_errors INT UNSIGNED DEFAULT 0,
    
    -- Status tracking
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    error_message LONGTEXT NULL,
    
    -- Timing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    -- Soft delete for restore functionality
    deleted_at TIMESTAMP NULL,
    
    -- Indexes
    INDEX idx_run_number (run_number DESC),
    INDEX idx_created_at (created_at DESC),
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks all product import operations for version control and restore functionality';
