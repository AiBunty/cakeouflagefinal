<?php
require_once __DIR__ . '/AdminErrorHandler.php';

// Bootstrap first so admin pages and /api/admin share identical session config.
require_once __DIR__ . '/../../app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('cakeouflage_sid');
    session_start();
}

require_once __DIR__ . '/db.php';

function admin_bootstrap_support_tables($conn)
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    // Suppress exceptions for schema migrations — columns may already exist in MySQL 8
    $prevReport = mysqli_report(MYSQLI_REPORT_OFF);

    $db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    $admins_cols = [];
    $res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='admins'");
    while ($r = $res->fetch_row()) { $admins_cols[] = $r[0]; }
    if (!in_array('department_label', $admins_cols)) {
        $conn->query("ALTER TABLE admins ADD COLUMN department_label VARCHAR(40) NULL AFTER role");
    }

    $orders_cols = [];
    $res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='orders'");
    while ($r = $res->fetch_row()) { $orders_cols[] = $r[0]; }
    $add_cols = [
        'payment_confirmed_at'          => "DATETIME NULL AFTER payment_status",
        'payment_confirmed_by_admin_id' => "BIGINT UNSIGNED NULL AFTER payment_confirmed_at",
        'billing_address_line1'         => "VARCHAR(190) NULL AFTER delivery_postal_code",
        'billing_address_line2'         => "VARCHAR(190) NULL AFTER billing_address_line1",
        'billing_city'                  => "VARCHAR(100) NULL AFTER billing_address_line2",
        'billing_state'                 => "VARCHAR(100) NULL AFTER billing_city",
        'billing_postal_code'           => "VARCHAR(15) NULL AFTER billing_state",
        'credit_collected_at'           => "DATETIME NULL AFTER payment_confirmed_by_admin_id",
        'credit_collected_by_admin_id'  => "BIGINT UNSIGNED NULL AFTER credit_collected_at",
        'customer_phone_e164'           => "VARCHAR(20) NULL AFTER customer_phone",
    ];
    foreach ($add_cols as $col => $def) {
        if (!in_array($col, $orders_cols)) {
            $conn->query("ALTER TABLE orders ADD COLUMN $col $def");
        }
    }
    if (!in_array('customer_phone_e164', $orders_cols)) {
        $conn->query("ALTER TABLE orders ADD INDEX idx_orders_phone_e164 (customer_phone_e164)");
    }

    // Credit + payment state expansion — expand ENUMs (safe to run repeatedly in MySQL 8)
    $conn->query("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('upi_manual','cod','gateway','credit') NOT NULL DEFAULT 'upi_manual'");
    // Updated ENUM matches 2026-05-27-order-state-machine.sql migration
    $conn->query("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('pending','under_review','paid','credit','refund_pending','partially_refunded','refunded','failed','rejected') NOT NULL DEFAULT 'pending'");

    // Add phone_e164 to users if missing
    $users_cols = [];
    $res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='users'");
    while ($r = $res->fetch_row()) { $users_cols[] = $r[0]; }
    if (!in_array('phone_e164', $users_cols)) {
        $conn->query("ALTER TABLE users ADD COLUMN phone_e164 VARCHAR(20) NULL AFTER phone");
        $conn->query("ALTER TABLE users ADD INDEX idx_users_phone_e164 (phone_e164)");
    }

    mysqli_report($prevReport);

    $conn->query("CREATE TABLE IF NOT EXISTS admin_permissions (
        admin_id BIGINT UNSIGNED NOT NULL,
        permission_key VARCHAR(80) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (admin_id, permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS crm_push_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        crm_setting_id BIGINT UNSIGNED NULL,
        name VARCHAR(120) NOT NULL,
        mobile VARCHAR(25) NOT NULL,
        trigger_key VARCHAR(80) NULL,
        endpoint VARCHAR(512) NULL,
        status ENUM('success','fail') NOT NULL,
        automation_id VARCHAR(80) NULL,
        api_token_masked VARCHAR(50) NULL,
        payload_json LONGTEXT NULL,
        http_status SMALLINT NULL,
        execution_status VARCHAR(40) NULL,
        error_message TEXT NULL,
        response_time_ms INT UNSIGNED NULL,
        response TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_crm_push_logs_created_at (created_at),
        KEY idx_crm_push_logs_status (status),
        KEY idx_crm_push_logs_trigger (trigger_key),
        KEY idx_crm_push_logs_exec_status (execution_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migrate pre-existing crm_push_logs tables that are missing new columns
    $crm_log_cols = [];
    $res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='crm_push_logs'");
    while ($res && ($r = $res->fetch_row())) { $crm_log_cols[] = $r[0]; }
    $crm_log_new = [
        'trigger_key'      => 'VARCHAR(80) NULL',
        'endpoint'         => 'VARCHAR(512) NULL',
        'automation_id'    => 'VARCHAR(80) NULL',
        'api_token_masked' => 'VARCHAR(50) NULL',
        'payload_json'     => 'LONGTEXT NULL',
        'http_status'      => 'SMALLINT NULL',
        'execution_status' => 'VARCHAR(40) NULL',
        'error_message'    => 'TEXT NULL',
        'response_time_ms' => 'INT UNSIGNED NULL',
    ];
    foreach ($crm_log_new as $col => $def) {
        if (!in_array($col, $crm_log_cols, true)) {
            $conn->query("ALTER TABLE crm_push_logs ADD COLUMN $col $def");
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS otp_verifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(190) NOT NULL,
        otp VARCHAR(12) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_otp_verifications_email (email),
        KEY idx_otp_verifications_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Ensure attempt_count exists on pre-existing installations
    $otp_cols = [];
    $res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='otp_verifications'");
    while ($res && ($r = $res->fetch_row())) { $otp_cols[] = $r[0]; }
    if (!in_array('attempt_count', $otp_cols, true)) {
        $conn->query("ALTER TABLE otp_verifications ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0");
    }

    $bootstrapped = true;
}

function admin_permission_definitions()
{
    return array(
        'dashboard' => 'Dashboard',
        'banners' => 'Media Center',
        'categories' => 'Categories',
        'about_video' => 'About Hero Video (Legacy)',
        'products' => 'Products',
        'coupons' => 'Coupons',
        'import_products' => 'Import Products',
        'orders' => 'Orders',
        'order_edit' => 'Order Edit',
        'order_reject' => 'Order Reject/Cancel',
        'order_refund' => 'Order Refund',
        'can_cancel_unpaid_orders' => 'Cancel Unpaid Orders',
        'can_approve_refund' => 'Approve Refunds',
        'can_force_refund' => 'Force Refund (High-Value Override)',
        'can_view_refund_reports' => 'View Refund Reports',
        'can_edit_completed_orders' => 'Edit Completed Orders',
        'order_credit' => 'Credit Sales',
        'payment_verification' => 'Payment Verification Queue',
        'order_delete' => 'Order Delete',
        'manual_orders' => 'Manual Order Punch',
        'production_plan' => 'Production Plan',
        'follow_ups' => 'Follow Ups',
        'build_your_cake' => 'Build Your Own Cake Leads',
        'crm_settings' => 'CRM Settings',
        'crm_logs' => 'CRM Push Logs',
        'crm_report' => 'CRM Report',
        'revenue_report' => 'Revenue Report',
        'business_settings' => 'Business Settings',
        'maintenance' => 'System Maintenance',
        'change_password' => 'Change Password',
        'sub_users' => 'Sub Users'
    );
}

function admin_label_definitions()
{
    return array(
        'sales' => array(
            'label' => 'Sales',
            'description' => 'Orders, CRM report, and customer follow-up work.'
        ),
        'crm' => array(
            'label' => 'CRM',
            'description' => 'CRM settings, logs, report, and follow-up operations.'
        ),
        'catalog' => array(
            'label' => 'Catalog',
            'description' => 'Products, categories, imports, and content maintenance.'
        ),
        'operations' => array(
            'label' => 'Operations',
            'description' => 'Dashboard visibility, banners, and order execution.'
        )
    );
}

function admin_permission_groups()
{
    return array(
        'operations' => array(
            'title' => 'Operations',
            'description' => 'Live order flow and business visibility.',
            'permissions' => array('dashboard', 'orders', 'order_edit', 'order_reject', 'order_refund', 'can_cancel_unpaid_orders', 'can_approve_refund', 'can_force_refund', 'can_view_refund_reports', 'can_edit_completed_orders', 'order_credit', 'payment_verification', 'order_delete', 'manual_orders', 'production_plan', 'revenue_report', 'banners')
        ),
        'catalog' => array(
            'title' => 'Catalog',
            'description' => 'Products, categories, and merchandising content.',
            'permissions' => array('products', 'coupons', 'import_products', 'categories')
        ),
        'crm' => array(
            'title' => 'CRM',
            'description' => 'Customer data, CRM integrations, and message logs.',
            'permissions' => array('follow_ups', 'build_your_cake', 'crm_settings', 'crm_logs', 'crm_report')
        ),
        'admin' => array(
            'title' => 'Admin',
            'description' => 'User management and account maintenance.',
            'permissions' => array('business_settings', 'maintenance', 'change_password', 'sub_users')
        )
    );
}

function admin_label_presets()
{
    return array(
        'sales' => array('dashboard', 'orders', 'order_edit', 'order_reject', 'order_refund', 'can_cancel_unpaid_orders', 'can_approve_refund', 'can_view_refund_reports', 'order_credit', 'payment_verification', 'manual_orders', 'production_plan', 'follow_ups', 'build_your_cake', 'crm_report', 'revenue_report', 'crm_logs', 'change_password'),
        'crm' => array('dashboard', 'follow_ups', 'build_your_cake', 'crm_settings', 'crm_logs', 'crm_report', 'change_password'),
        'catalog' => array('dashboard', 'products', 'coupons', 'import_products', 'categories', 'banners', 'change_password'),
        'operations' => array('dashboard', 'orders', 'order_edit', 'order_reject', 'order_refund', 'can_cancel_unpaid_orders', 'can_approve_refund', 'can_view_refund_reports', 'manual_orders', 'production_plan', 'revenue_report', 'banners', 'crm_logs', 'change_password')
    );
}

function admin_grouped_permissions()
{
    $definitions = admin_permission_definitions();
    $groups = admin_permission_groups();

    foreach ($groups as $groupKey => $group) {
        $groups[$groupKey]['permission_labels'] = array();
        foreach ($group['permissions'] as $permissionKey) {
            $groups[$groupKey]['permission_labels'][$permissionKey] = isset($definitions[$permissionKey])
                ? $definitions[$permissionKey]
                : $permissionKey;
        }
    }

    return $groups;
}

function admin_page_permissions()
{
    return array(
        'dashboard.php' => 'dashboard',
        'banners.php' => 'banners',
        'categories.php' => 'categories',
        'add-category.php' => 'categories',
        'edit-category.php' => 'categories',
        'delete-category.php' => 'categories',
        'update-category-status.php' => 'categories',
        'about-video.php' => 'banners',
        'products.php' => 'products',
        'coupons.php' => 'coupons',
        'add-product.php' => 'products',
        'edit-product.php' => 'products',
        'delete-product.php' => 'products',
        'import-products.php' => 'import_products',
        'download_products.php' => 'import_products',
        'toppers.php' => 'products',
        'orders.php'  => 'orders',
        'refunds.php' => 'can_approve_refund',
        'bank-alerts.php' => 'payment_verification',
        'manual_order.php' => 'manual_orders',
        'save_manual_order.php' => 'manual_orders',
        'production_plan.php' => 'production_plan',
        'slots.php' => 'orders',
        'build-your-own-cake.php' => 'build_your_cake',
        'business-settings.php' => 'business_settings',
        'save-business-settings.php' => 'business_settings',
        'maintenance.php' => 'maintenance',
        'form-test-harness-report.php' => 'maintenance',
        'follow_ups.php' => 'follow_ups',
        'order_details.php' => 'orders',
        'order_invoice.php' => 'orders',
        'update_order.php' => 'orders',
        'update_order_payment.php' => 'orders',
        'update_order_status.php' => 'orders',
        'collect_credit.php' => 'order_credit',
        'credit_report.php' => 'order_credit',
        'save_order_edit.php' => 'order_edit',
        'crm_settings.php' => 'crm_settings',
        'communications.php' => 'crm_settings',
        'update_crm_settings.php' => 'crm_settings',
        'test_crm_push.php' => 'crm_settings',
        'crm_push_logs.php' => 'crm_logs',
        'crm_report.php' => 'crm_report',
        'crm_user_history.php' => 'crm_report',
        'revenue_report.php' => 'revenue_report',
        'sales_register.php' => 'revenue_report',
        'collections_queue.php' => 'revenue_report',
        'collections_export.php' => 'revenue_report',
        'collection_report.php' => 'revenue_report',
        'sales_report.php' => 'revenue_report',
        'cash_report.php' => 'revenue_report',
        'bank_report.php' => 'revenue_report',
        'coupon_report.php' => 'revenue_report',
        'celebration_report.php' => 'revenue_report',
        'credit_report.php' => 'order_credit',
        'change-password.php' => 'change_password',
        'admin_users.php' => 'sub_users',
        'save_admin_user.php' => 'sub_users'
    );
}

function require_admin_login()
{
    if (!isset($_SESSION['admin'])) {
        header('Location: login.php');
        exit;
    }
}

function admin_is_super_admin()
{
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
}

function admin_load_permissions($conn, $adminId)
{
    admin_bootstrap_support_tables($conn);

    if (admin_is_super_admin()) {
        $permissions = array_keys(admin_permission_definitions());
        $_SESSION['admin_permissions'] = $permissions;
        return $permissions;
    }

    $permissions = array();
    $stmt = $conn->prepare('SELECT permission_key FROM admin_permissions WHERE admin_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['permission_key'];
        }
    }

    $_SESSION['admin_permissions'] = $permissions;
    return $permissions;
}

function admin_permissions()
{
    return isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])
        ? $_SESSION['admin_permissions']
        : array();
}

function admin_has_permission($permissionKey)
{
    if ($permissionKey === '' || $permissionKey === null) {
        return true;
    }

    if (admin_is_super_admin()) {
        return true;
    }

    if ($permissionKey === 'manual_orders' && in_array('orders', admin_permissions(), true)) {
        return true;
    }

    if ($permissionKey === 'revenue_report' && in_array('orders', admin_permissions(), true)) {
        return true;
    }

    if ($permissionKey === 'banners' && in_array('about_video', admin_permissions(), true)) {
        return true;
    }

    if ($permissionKey === 'about_video' && in_array('banners', admin_permissions(), true)) {
        return true;
    }

    return in_array($permissionKey, admin_permissions(), true);
}

function require_admin_permission($permissionKey)
{
    require_admin_login();

    if (!admin_has_permission($permissionKey)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

function require_permission_for_current_admin_page()
{
    $fileName = basename($_SERVER['PHP_SELF']);
    $pagePermissions = admin_page_permissions();
    if (isset($pagePermissions[$fileName])) {
        require_admin_permission($pagePermissions[$fileName]);
    } else {
        require_admin_login();
    }
}

function admin_navigation_items()
{
    return array(
        array('title' => 'Dashboard', 'href' => 'dashboard.php', 'page' => 'Dashboard', 'permission' => 'dashboard'),
        array('title' => 'Media Center', 'href' => 'banners.php', 'page' => 'Media Center', 'permission' => 'banners'),
        array('title' => 'Categories', 'href' => 'categories.php', 'page' => 'Categories', 'permission' => 'categories'),
        array('title' => 'Products', 'href' => 'products.php', 'page' => 'Products', 'permission' => 'products'),
        array('title' => 'Coupons', 'href' => 'coupons.php', 'page' => 'Coupons', 'permission' => 'coupons'),
        array('title' => 'Import Products', 'href' => 'import-products.php', 'page' => 'Import Products', 'permission' => 'import_products'),
        array('title' => 'Cake Toppers', 'href' => 'toppers.php', 'page' => 'Cake Toppers', 'permission' => 'products'),
        array('title' => 'Orders', 'href' => 'orders.php', 'page' => 'Orders', 'permission' => 'orders'),
        array('title' => 'Refunds', 'href' => 'refunds.php', 'page' => 'Refunds', 'permission' => 'can_approve_refund'),
        array('title' => 'Payment Verification', 'href' => 'bank-alerts.php', 'page' => 'Payment Verification', 'permission' => 'payment_verification'),
        array('title' => 'Manual Order Punch', 'href' => 'manual_order.php', 'page' => 'Manual Order Punch', 'permission' => 'manual_orders'),
        array('title' => 'Production Plan', 'href' => 'production_plan.php', 'page' => 'Production Plan', 'permission' => 'production_plan'),
        array('title' => 'Slot Management', 'href' => 'slots.php', 'page' => 'Slot Management', 'permission' => 'orders'),
        array('title' => 'Follow Ups', 'href' => 'follow_ups.php', 'page' => 'Follow Ups', 'permission' => 'follow_ups'),
        array('title' => 'Build Your Own Cake', 'href' => 'build-your-own-cake.php', 'page' => 'Build Your Own Cake', 'permission' => 'build_your_cake'),
        array('title' => 'CRM Settings', 'href' => 'crm_settings.php', 'page' => 'CRM Settings', 'permission' => 'crm_settings'),
        array('title' => 'Communications', 'href' => 'communications.php', 'page' => 'Communications', 'permission' => 'crm_settings'),
        array('title' => 'CRM Push Logs', 'href' => 'crm_push_logs.php', 'page' => 'CRM Push Logs', 'permission' => 'crm_logs'),
        array('title' => 'CRM Report', 'href' => 'crm_report.php', 'page' => 'CRM Report', 'permission' => 'crm_report'),
        array('title' => 'Sales Report', 'href' => 'sales_report.php', 'page' => 'Sales Report', 'permission' => 'revenue_report'),
        array('title' => 'Cash Report', 'href' => 'cash_report.php', 'page' => 'Cash Report', 'permission' => 'revenue_report'),
        array('title' => 'Bank Report', 'href' => 'bank_report.php', 'page' => 'Bank Report', 'permission' => 'revenue_report'),
        array('title' => 'Coupon Report', 'href' => 'coupon_report.php', 'page' => 'Coupon Report', 'permission' => 'revenue_report'),
        array('title' => 'Celebration Report', 'href' => 'celebration_report.php', 'page' => 'Celebration Report', 'permission' => 'revenue_report'),
        array('title' => 'Sales Register', 'href' => 'sales_register.php', 'page' => 'Sales Register', 'permission' => 'revenue_report'),
        array('title' => 'Collections Queue', 'href' => 'collections_queue.php', 'page' => 'Collections Queue', 'permission' => 'revenue_report'),
        array('title' => 'Collection Report', 'href' => 'collection_report.php', 'page' => 'Collection Report', 'permission' => 'revenue_report'),
        array('title' => 'Business Settings', 'href' => 'business-settings.php', 'page' => 'Business Settings', 'permission' => 'business_settings'),
        array('title' => 'Maintenance', 'href' => 'maintenance.php', 'page' => 'System Maintenance', 'permission' => 'maintenance'),
        array('title' => 'Form Test Harness', 'href' => 'form-test-harness-report.php', 'page' => 'Form Test Harness', 'permission' => 'maintenance'),
        array('title' => 'Sub Users', 'href' => 'admin_users.php', 'page' => 'Sub Users', 'permission' => 'sub_users'),
        array('title' => 'Change Password', 'href' => 'change-password.php', 'page' => 'Change Password', 'permission' => 'change_password')
    );
}

admin_bootstrap_support_tables($conn);
if (isset($_SESSION['admin'])) {
    if (!isset($_SESSION['admin_permissions']) || !is_array($_SESSION['admin_permissions'])) {
        admin_load_permissions($conn, (int) $_SESSION['admin']);
    }
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
if (!defined('SKIP_AUTH_ORDER_AUTO_HANDLER') && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($currentPage, array('update_order.php', 'update_order_status.php'), true)) {
    $orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $allowedStatuses = array('pending_payment', 'payment_under_review', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'rejected', 'refund_requested', 'refunded', 'partially_refunded');

    if ($orderId <= 0 || !in_array($status, $allowedStatuses, true)) {
        die('Invalid order update request');
    }

    try {
        $service = new \App\Services\OrderAutomationService();
        $service->handleStatusChange(\App\Core\Database::getConnection(), $orderId, $status, isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : 0);
    } catch (\Throwable $e) {
        error_log('[admin_order_update] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        echo 'Order update failed';
        exit;
    }

    header('Location: orders.php');
    exit;
}
?>