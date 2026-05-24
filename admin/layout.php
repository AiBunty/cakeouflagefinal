<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

// ── Icon map (SVG, keyed by nav href) ────────────────────────────────
$_navIcons = [
    'dashboard.php'          => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
    'orders.php'             => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>',
    'bank-alerts.php'        => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v2.586l-.707.707A1 1 0 004 13h12a1 1 0 00.707-1.707L16 10.586V8a6 6 0 00-6-6zm-2 13a2 2 0 104 0H8z" clip-rule="evenodd"/></svg>',
    'manual_order.php'       => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>',
    'production_plan.php'    => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2H3V4zm0 4h14v8a1 1 0 01-1 1H4a1 1 0 01-1-1V8zm3 2a1 1 0 000 2h2a1 1 0 100-2H6zm0 3a1 1 0 100 2h5a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
    'slots.php'              => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>',
    'fulfillment_report.php' => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>',
    'kitchen_queue.php'      => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h7a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>',
    'categories.php'         => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>',
    'products.php'           => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>',
    'import-products.php'    => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>',
    'toppers.php'            => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>',
    'sales_register.php'     => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2h14a1 1 0 100-2H3zm0 5a1 1 0 000 2h14a1 1 0 100-2H3zm0 5a1 1 0 000 2h8a1 1 0 100-2H3z" clip-rule="evenodd"/></svg>',
    'coupon_report.php'      => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
    'banners.php'            => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>',
    'follow_ups.php'         => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>',
    'build-your-own-cake.php'=> '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a3 3 0 013 3v1h1a2 2 0 012 2v2a4 4 0 01-4 4H8a4 4 0 01-4-4V8a2 2 0 012-2h1V5a3 3 0 013-3zm1 4V5a1 1 0 10-2 0v1h2zm-1 11a3 3 0 003-3v-1H7v1a3 3 0 003 3z"/></svg>',
    'crm_settings.php'       => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>',
    'communications.php'     => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>',
    'crm_push_logs.php'      => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>',
    'crm_report.php'         => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd"/></svg>',
    'sales_register.php'     => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M3 3a1 1 0 000 2h14a1 1 0 100-2H3zm0 5a1 1 0 000 2h14a1 1 0 100-2H3zm0 5a1 1 0 000 2h8a1 1 0 100-2H3z"/></svg>',
    'collections_queue.php'  => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h9a2 2 0 002-2V9a1 1 0 10-2 0v6H4V5h9a1 1 0 100-2H4zm10.293.293a1 1 0 011.414 0l2 2a1 1 0 010 1.414l-5.5 5.5a1 1 0 01-.39.243l-2 .667a1 1 0 01-1.265-1.265l.667-2a1 1 0 01.243-.39l5.5-5.5z" clip-rule="evenodd"/></svg>',
    'collection_report.php'  => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 7.234 6 8.009 6 9c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V16a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 14.766 14 13.991 14 13c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 10.092V8.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V6z" clip-rule="evenodd"/></svg>',
    'celebration_report.php' => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1.09a5.002 5.002 0 013.535 2.082l.772-.445a1 1 0 111 1.732l-.773.446A4.978 4.978 0 0115 10c0 .703-.145 1.373-.406 1.98l.774.447a1 1 0 11-1 1.732l-.774-.447A5.001 5.001 0 0111 15.91V17a1 1 0 11-2 0v-1.09a5.002 5.002 0 01-3.535-2.082l-.772.445a1 1 0 11-1-1.732l.773-.446A4.978 4.978 0 015 10c0-.703.145-1.373.406-1.98l-.774-.447a1 1 0 011-1.732l.774.447A5.001 5.001 0 019 4.09V3a1 1 0 011-1zm0 4a4 4 0 100 8 4 4 0 000-8z"/></svg>',
    'revenue_report.php'     => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>',
    'credit_report.php'      => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>',
    'business-settings.php'  => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/></svg>',
    'maintenance.php'        => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
    'form-test-harness-report.php' => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V8.414A2 2 0 0017.414 7l-4.414-4.414A2 2 0 0011.586 2H4zm2 6a1 1 0 100 2h8a1 1 0 100-2H6zm0 3a1 1 0 100 2h5a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
    'admin_users.php'        => '<svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>',
];

// ── Section groupings (href arrays define order within each group) ────
$_navSections = [
    'Sales'    => ['dashboard.php', 'orders.php', 'refunds.php', 'bank-alerts.php', 'manual_order.php', 'production_plan.php', 'slots.php', 'fulfillment_report.php', 'kitchen_queue.php'],
    'Catalog'  => ['products.php', 'categories.php', 'banners.php', 'coupons.php', 'toppers.php', 'import-products.php'],
    'CRM'      => ['follow_ups.php', 'build-your-own-cake.php', 'crm_settings.php', 'communications.php', 'crm_push_logs.php'],
    'Reports'  => ['sales_register.php', 'collections_queue.php', 'sales_report.php', 'cash_report.php', 'bank_report.php', 'collection_report.php', 'crm_report.php', 'coupon_report.php', 'celebration_report.php'],
    'System'   => ['business-settings.php', 'maintenance.php', 'form-test-harness-report.php', 'admin_users.php'],
];

// Build href -> item lookup
$_navByHref = [];
foreach (admin_navigation_items() as $_ni) {
    $_navByHref[$_ni['href']] = $_ni;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Poppins:wght@400;500;600;700&display=swap');

:root {
  --admin-burgundy: #80001F;
  --admin-burgundy-dark: #5f0017;
  --admin-soft-pink: #F8D8DE;
  --admin-ink: #2d1f25;
  --admin-muted: #7a6870;
  --admin-surface: #fffdfd;
  --sidebar-w: 272px;
}

*, *::before, *::after { box-sizing: border-box; }

body {
  margin: 0;
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(145deg, #fffafb 0%, #fdeef1 56%, #fae4ea 100%);
  color: var(--admin-ink);
}

.dashboard {
  display: flex;
  min-height: 100vh;
}

/* ── Sidebar shell ─────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  background: linear-gradient(180deg, #8d0a2d 0%, #740020 54%, #560017 100%);
  color: #fff;
  min-height: 100vh;
  position: fixed;
  inset: 0 auto 0 0;
  border-right: 1px solid rgba(255,255,255,0.1);
  box-shadow: 14px 0 34px rgba(74,8,30,0.22);
  display: flex;
  flex-direction: column;
  z-index: 1001;
}

/* ── Logo header ───────────────────────────────── */
.sidebar__head {
  padding: 22px 20px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.12);
  flex-shrink: 0;
}
.sidebar__head img {
  display: block;
  width: 148px;
  max-width: 100%;
  filter: brightness(0) invert(1);
}
.sidebar__kicker {
  margin: 7px 0 0;
  font-size: 0.67rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.55);
}

/* ── Search ────────────────────────────────────── */
.nav-search-wrap {
  padding: 12px 14px 6px;
  flex-shrink: 0;
}
.nav-search {
  width: 100%;
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 8px;
  padding: 7px 10px 7px 30px;
  color: #fff;
  font-size: 0.8rem;
  font-family: inherit;
  outline: none;
  transition: background 150ms, border-color 150ms;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='rgba(255,255,255,0.45)'%3E%3Cpath fill-rule='evenodd' d='M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z' clip-rule='evenodd'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: 8px 50%;
  background-size: 13px;
}
.nav-search::placeholder { color: rgba(255,255,255,0.4); }
.nav-search:focus { background-color: rgba(255,255,255,0.16); border-color: rgba(255,255,255,0.38); }

/* ── Scrollable body ───────────────────────────── */
.sidebar__body {
  flex: 1;
  overflow-y: auto;
  padding: 6px 0 10px;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.18) transparent;
}
.sidebar__body::-webkit-scrollbar { width: 4px; }
.sidebar__body::-webkit-scrollbar-track { background: transparent; }
.sidebar__body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.18); border-radius: 2px; }

/* ── Nav sections ──────────────────────────────── */
.nav-section { padding: 6px 0 2px; }
.nav-section + .nav-section {
  border-top: 1px solid rgba(255,255,255,0.07);
  margin-top: 2px;
  padding-top: 8px;
}

.nav-section__toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  background: none;
  border: none;
  padding: 2px 18px 4px;
  cursor: pointer;
  color: rgba(255,255,255,0.45);
  font-family: inherit;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  transition: color 140ms;
}
.nav-section__toggle:hover { color: rgba(255,255,255,0.75); }

.nav-section__chevron {
  font-size: 0.55rem;
  transition: transform 220ms ease;
  display: inline-block;
  line-height: 1;
}
.nav-section.is-collapsed .nav-section__chevron { transform: rotate(-90deg); }

.nav-section__items {
  overflow: hidden;
  transition: max-height 260ms ease;
  max-height: 800px;
}
.nav-section.is-collapsed .nav-section__items { max-height: 0; }

/* ── Nav items ─────────────────────────────────── */
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 14px 9px 16px;
  margin: 1px 8px;
  color: rgba(255,255,255,0.82);
  text-decoration: none;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
  transition: background 160ms, color 160ms, transform 160ms;
  position: relative;
}
.nav-item:hover {
  background: rgba(255,255,255,0.11);
  color: #fff;
  transform: translateX(2px);
}
.nav-item.is-active {
  background: rgba(255,255,255,0.17);
  color: #fff;
  font-weight: 600;
}
.nav-item.is-active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 58%;
  background: rgba(255,255,255,0.88);
  border-radius: 0 3px 3px 0;
}
.nav-item.is-hidden { display: none; }

.nav-item__icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  opacity: 0.8;
  display: flex;
  align-items: center;
}
.nav-item__icon svg { width: 16px; height: 16px; display: block; }
.nav-item.is-active .nav-item__icon,
.nav-item:hover .nav-item__icon { opacity: 1; }

/* ── Footer ────────────────────────────────────── */
.sidebar__footer {
  flex-shrink: 0;
  border-top: 1px solid rgba(255,255,255,0.12);
  padding: 13px 14px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.sidebar__user-info { flex: 1; min-width: 0; }
.sidebar__user-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sidebar__user-role {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.5);
  margin-top: 1px;
}
.sidebar__logout-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: rgba(255,255,255,0.09);
  border: 1px solid rgba(255,255,255,0.18);
  color: rgba(255,255,255,0.8);
  text-decoration: none;
  flex-shrink: 0;
  transition: background 160ms, color 160ms, border-color 160ms;
}
.sidebar__logout-btn:hover {
  background: rgba(220,50,50,0.3);
  color: #fff;
  border-color: rgba(220,50,50,0.5);
}
.sidebar__logout-btn svg { width: 16px; height: 16px; }

/* ── Mobile toggle ─────────────────────────────── */
.sidebar__toggle { display: none; }

/* ── Main content ──────────────────────────────── */
.main {
  margin-left: var(--sidebar-w);
  padding: 30px;
  width: 100%;
  box-sizing: border-box;
}

/* ── Header ────────────────────────────────────── */
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  background: rgba(255,255,255,0.82);
  border: 1px solid rgba(128,0,31,0.1);
  border-radius: 18px;
  padding: 16px 20px;
  backdrop-filter: blur(6px);
}
.header h1 {
  margin: 0;
  font-family: 'DM Serif Display', Georgia, serif;
  font-weight: 400;
  letter-spacing: 0.01em;
  color: var(--admin-burgundy);
  font-size: clamp(1.7rem, 2.7vw, 2.2rem);
}
.header__meta {
  margin: 2px 0 0;
  color: var(--admin-muted);
  font-size: 0.85rem;
}
.logout {
  background: var(--admin-burgundy);
  color: white;
  padding: 9px 16px;
  border-radius: 999px;
  text-decoration: none;
  font-size: 0.84rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 600;
  transition: background 180ms ease, transform 180ms ease;
}
.logout:hover {
  background: var(--admin-burgundy-dark);
  transform: translateY(-1px);
}

/* ── Cards / Table ─────────────────────────────── */
.card {
  background: var(--admin-surface);
  padding: 22px;
  border-radius: 18px;
  border: 1px solid rgba(128,0,31,0.1);
  box-shadow: 0 14px 30px rgba(96,18,45,0.08);
}
.cards {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
}
.table {
  background: var(--admin-surface);
  padding: 20px;
  border-radius: 18px;
  margin-top: 20px;
  border: 1px solid rgba(128,0,31,0.1);
  box-shadow: 0 12px 26px rgba(96,18,45,0.08);
}
.table h3 {
  margin: 0 0 14px;
  font-family: 'DM Serif Display', Georgia, serif;
  font-size: 1.35rem;
  font-weight: 400;
  color: var(--admin-burgundy);
}
table { width: 100%; border-collapse: collapse; background: transparent; }
th, td { padding: 12px 10px; text-align: left; }
th { color: var(--admin-burgundy); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
tr { border-bottom: 1px solid rgba(128,0,31,0.08); }

/* ── Responsive ────────────────────────────────── */
@media (max-width: 1080px) {
  .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 860px) {
  .sidebar__toggle {
    display: block;
    position: fixed;
    top: 14px;
    left: 14px;
    z-index: 1100;
    background: var(--admin-burgundy);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 1.1rem;
    box-shadow: 0 2px 8px rgba(128,0,31,0.24);
    cursor: pointer;
  }
  .sidebar {
    width: 88vw;
    max-width: 300px;
    min-height: 100vh;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
    box-shadow: 14px 0 34px rgba(74,8,30,0.24);
  }
  .sidebar.is-open { transform: translateX(0); }
  .main { margin-left: 0; padding: 68px 16px 28px; }
  .header { flex-direction: column; align-items: flex-start; }
  .cards { grid-template-columns: 1fr; }
}
</style>
</head>

<body>

<button class="sidebar__toggle" id="sidebarToggle" aria-label="Toggle menu">&#9776;</button>

<div class="dashboard">

<aside class="sidebar" id="adminSidebar" role="navigation" aria-label="Admin Sidebar">

  <div class="sidebar__head">
    <?php
      $_sidebarLogoUrl = '../client/assets/images/mainlogo.svg';
      try {
          $_lr = $conn->query("SELECT setting_value FROM settings WHERE setting_key='navbar_logo_url' LIMIT 1");
          if ($_lr) { $_lrow = $_lr->fetch_assoc(); if (!empty($_lrow['setting_value'])) { $_sidebarLogoUrl = $_lrow['setting_value']; } }
      } catch (\Throwable $_le) {}
    ?>
    <img src="<?= htmlspecialchars($_sidebarLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cakeouflage">
    <p class="sidebar__kicker">Admin Studio</p>
  </div>

  <div class="nav-search-wrap">
    <input type="search" id="navSearch" class="nav-search" placeholder="Find a page&hellip;" autocomplete="off" aria-label="Search navigation">
  </div>

  <div class="sidebar__body" id="sidebarNav">
    <?php foreach ($_navSections as $_sectionName => $_sectionHrefs): ?>
      <?php
        $_sectionItems = [];
        foreach ($_sectionHrefs as $_href) {
            if (isset($_navByHref[$_href]) && admin_has_permission($_navByHref[$_href]['permission'])) {
                $_sectionItems[] = $_navByHref[$_href];
            }
        }
        if (empty($_sectionItems)) { continue; }
        $_sid = 'navSec_' . strtolower(preg_replace('/\W+/', '_', $_sectionName));
      ?>
      <div class="nav-section" id="<?= htmlspecialchars($_sid) ?>">
        <button class="nav-section__toggle" data-section="<?= htmlspecialchars($_sid) ?>" aria-expanded="true">
          <?= htmlspecialchars($_sectionName) ?><span class="nav-section__chevron">&#9660;</span>
        </button>
        <div class="nav-section__items">
          <?php foreach ($_sectionItems as $_ni): ?>
            <?php
              $_active = (($pageTitle ?? '') === $_ni['page']);
              $_icon   = $_navIcons[$_ni['href']] ?? '';
            ?>
            <a href="<?= htmlspecialchars($_ni['href']) ?>"
               class="nav-item<?= $_active ? ' is-active' : '' ?>"
               data-nav-title="<?= htmlspecialchars(strtolower($_ni['title'])) ?>"
               <?= $_active ? 'aria-current="page"' : '' ?>>
              <span class="nav-item__icon" aria-hidden="true"><?= $_icon ?></span>
              <span class="nav-item__text"><?= htmlspecialchars($_ni['title']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="sidebar__footer">
    <div class="sidebar__user-info">
      <div class="sidebar__user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
      <div class="sidebar__user-role"><?= htmlspecialchars(ucfirst($_SESSION['admin_department_label'] ?? 'Administrator')) ?></div>
    </div>
    <a href="logout.php" class="sidebar__logout-btn" title="Logout" aria-label="Logout">
      <svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    </a>
  </div>

</aside>

<div class="main">

<div class="header">
  <div>
    <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
    <p class="header__meta">
      Cakeouflage Admin Panel
      <?php if (!empty($_SESSION['admin_department_label'])): ?>
        &middot; <?= htmlspecialchars(ucfirst($_SESSION['admin_department_label'])) ?>
      <?php endif; ?>
    </p>
  </div>
  <a href="logout.php" class="logout">Logout</a>
</div>

<script>
(function () {
  'use strict';

  /* Mobile toggle */
  var sidebar = document.getElementById('adminSidebar');
  var toggle  = document.getElementById('sidebarToggle');
  if (sidebar && toggle) {
    toggle.addEventListener('click', function () { sidebar.classList.toggle('is-open'); });
    document.addEventListener('click', function (e) {
      if (window.innerWidth > 860 || !sidebar.classList.contains('is-open')) { return; }
      if (!sidebar.contains(e.target) && !toggle.contains(e.target)) { sidebar.classList.remove('is-open'); }
    });
  }

  /* Section collapse — persisted in localStorage */
  var LS_KEY = 'cakeo_nav_collapsed_v1';
  function getCollapsed() { try { return JSON.parse(localStorage.getItem(LS_KEY) || '[]'); } catch(e) { return []; } }
  function setCollapsed(arr) { try { localStorage.setItem(LS_KEY, JSON.stringify(arr)); } catch(e) {} }

  // Restore on load
  getCollapsed().forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) { return; }
    el.classList.add('is-collapsed');
    var btn = el.querySelector('.nav-section__toggle');
    if (btn) { btn.setAttribute('aria-expanded', 'false'); }
  });

  document.querySelectorAll('.nav-section__toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id  = btn.getAttribute('data-section');
      var sec = document.getElementById(id);
      if (!sec) { return; }
      var now = sec.classList.toggle('is-collapsed');
      btn.setAttribute('aria-expanded', now ? 'false' : 'true');
      var list = getCollapsed();
      if (now) { if (list.indexOf(id) < 0) { list.push(id); } }
      else { list = list.filter(function (x) { return x !== id; }); }
      setCollapsed(list);
    });
  });

  /* Sidebar scroll persistence — save before leaving, restore on load */
  var SCROLL_KEY = 'cakeo_sidebar_scroll_v1';
  var sidebarBody = document.querySelector('.sidebar__body');
  if (sidebarBody) {
    var savedScroll = parseInt(sessionStorage.getItem(SCROLL_KEY) || '0', 10);
    if (savedScroll > 0) { sidebarBody.scrollTop = savedScroll; }
    window.addEventListener('beforeunload', function () {
      sessionStorage.setItem(SCROLL_KEY, String(sidebarBody.scrollTop));
    });
  }

  /* Nav search */
  var searchEl = document.getElementById('navSearch');
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var q = searchEl.value.trim().toLowerCase();
      var items = document.querySelectorAll('#sidebarNav .nav-item');
      items.forEach(function (item) {
        item.classList.toggle('is-hidden', q.length > 0 && (item.getAttribute('data-nav-title') || '').indexOf(q) < 0);
      });
      document.querySelectorAll('.nav-section').forEach(function (sec) {
        if (q.length === 0) {
          sec.style.display = '';
          var id = sec.id;
          var collapsed = getCollapsed().indexOf(id) >= 0;
          sec.classList.toggle('is-collapsed', collapsed);
        } else {
          sec.classList.remove('is-collapsed');
          sec.style.display = sec.querySelectorAll('.nav-item:not(.is-hidden)').length === 0 ? 'none' : '';
        }
      });
    });
  }

})();
</script>
