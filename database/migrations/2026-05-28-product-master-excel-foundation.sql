-- Product Master Excel Foundation
-- Date: 2026-05-28
-- Purpose:
--   1) Add dynamic size catalog used by matrix-style import/export/admin UX.
--   2) Add version-history registry aligned to Excel master-of-truth workflow.

SET @schema_name := DATABASE();

CREATE TABLE IF NOT EXISTS product_size_master (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    label VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_size_master_label (label),
    KEY idx_product_size_master_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO product_size_master (label, sort_order, is_active)
VALUES
    ('Per Pcs', 10, 1),
    ('0.5 kg', 20, 1),
    ('1 kg', 30, 1),
    ('1.5 kg', 40, 1),
    ('2 kg', 50, 1),
    ('2.5 kg', 60, 1),
    ('3 kg', 70, 1),
    ('3.5 kg', 80, 1),
    ('4 kg', 90, 1);

CREATE TABLE IF NOT EXISTS product_import_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    version_name VARCHAR(190) NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(500) NULL,
    snapshot_reference VARCHAR(255) NULL,
    is_restorable TINYINT(1) NOT NULL DEFAULT 1,
    metadata_json LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY idx_product_import_versions_uploaded_at (uploaded_at),
    KEY idx_product_import_versions_restorable (is_restorable),
    CONSTRAINT fk_product_import_versions_uploaded_by
        FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
