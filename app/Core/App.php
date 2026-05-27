<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\ApiController;
use App\Controllers\AdminApiController;
use App\Controllers\CronController;
use App\Controllers\WebController;

final class App
{
    /** @var array<int, array{method:string, path:string, handler:array{0:object,1:string}}> */
    private $routes = [];

    public function __construct()
    {
        $web = WebController::class;
        $api = ApiController::class;
        $adminApi = AdminApiController::class;
        $cron = CronController::class;

        $this->routes = [
            ['method' => 'GET', 'path' => '/', 'handler' => [$web, 'home']],
            ['method' => 'GET', 'path' => '/robots.txt', 'handler' => [$web, 'robots'] ],
            ['method' => 'GET', 'path' => '/sitemap.xml', 'handler' => [$web, 'sitemap'] ],
            ['method' => 'GET', 'path' => '/sitemap_index.xml', 'handler' => [$web, 'sitemapIndex'] ],
            ['method' => 'GET', 'path' => '/llms.txt', 'handler' => [$web, 'llms'] ],
            
            ['method' => 'GET', 'path' => '/shop', 'handler' => [$web, 'shop']],
            ['method' => 'GET', 'path' => '/course', 'handler' => [$web, 'course']],
            ['method' => 'GET', 'path' => '/events', 'handler' => [$web, 'events']],
            ['method' => 'GET', 'path' => '/b2b', 'handler' => [$web, 'b2b']],
            ['method' => 'GET', 'path' => '/contact', 'handler' => [$web, 'contact']],
            ['method' => 'GET', 'path' => '/login', 'handler' => [$web, 'login']],
            ['method' => 'GET', 'path' => '/register', 'handler' => [$web, 'register']],
            ['method' => 'GET', 'path' => '/forgot-password', 'handler' => [$web, 'forgotPassword']],
            ['method' => 'GET', 'path' => '/reset-password', 'handler' => [$web, 'resetPassword']],
            ['method' => 'GET', 'path' => '/faq', 'handler' => [$web, 'faq']],
            ['method' => 'GET', 'path' => '/privacy-policy', 'handler' => [$web, 'privacy']],
            ['method' => 'GET', 'path' => '/terms', 'handler' => [$web, 'terms']],
            ['method' => 'GET', 'path' => '/shipping-info', 'handler' => [$web, 'shipping']],
            ['method' => 'GET', 'path' => '/category', 'handler' => [$web, 'categories']],
            ['method' => 'GET', 'path' => '/search', 'handler' => [$web, 'categories']],
            ['method' => 'GET', 'path' => '/category/:slug', 'handler' => [$web, 'category']],
            ['method' => 'GET', 'path' => '/product/:slug', 'handler' => [$web, 'product']],
            ['method' => 'GET', 'path' => '/cart', 'handler' => [$web, 'cart']],
            ['method' => 'GET', 'path' => '/checkout', 'handler' => [$web, 'checkout']],
            ['method' => 'GET', 'path' => '/account', 'handler' => [$web, 'account']],
            ['method' => 'GET', 'path' => '/account/login.php', 'handler' => [$web, 'accountLogin']],
            ['method' => 'GET', 'path' => '/account/dashboard.php', 'handler' => [$web, 'accountDashboard']],
            ['method' => 'GET', 'path' => '/account/logout.php', 'handler' => [$web, 'accountLogout']],
            ['method' => 'GET', 'path' => '/orders', 'handler' => [$web, 'orders']],
            ['method' => 'GET', 'path' => '/wishlist', 'handler' => [$web, 'wishlist']],
            ['method' => 'GET', 'path' => '/about', 'handler' => [$web, 'about']],
            ['method' => 'GET', 'path' => '/course/:slug', 'handler' => [$web, 'courseDetail']],
            ['method' => 'GET', 'path' => '/events/:slug', 'handler' => [$web, 'eventDetail']],
            ['method' => 'GET', 'path' => '/b2b/corporate-orders', 'handler' => [$web, 'placeholder']],
            ['method' => 'GET', 'path' => '/b2b/bulk-orders', 'handler' => [$web, 'placeholder']],
            ['method' => 'GET', 'path' => '/b2b/corporate-gifting', 'handler' => [$web, 'placeholder']],
            ['method' => 'GET', 'path' => '/b2b/reseller', 'handler' => [$web, 'placeholder']],
            ['method' => 'GET', 'path' => '/b2b/login', 'handler' => [$web, 'b2bLogin']],
            ['method' => 'GET', 'path' => '/b2b/register', 'handler' => [$web, 'b2bRegister']],
            ['method' => 'GET', 'path' => '/b2b/dashboard', 'handler' => [$web, 'b2bDashboard']],
            ['method' => 'GET', 'path' => '/b2b/quote-request', 'handler' => [$web, 'placeholder']],
            ['method' => 'GET', 'path' => '/b2b/order-builder', 'handler' => [$web, 'placeholder']],
            ['method' => 'GET', 'path' => '/custom-cake-inquiry', 'handler' => [$web, 'customCakeInquiry']],
            ['method' => 'GET', 'path' => '/quote/accept/:token', 'handler' => [$web, 'customCakeQuoteAccept']],
            ['method' => 'GET', 'path' => '/order-confirmation/:orderNumber', 'handler' => [$web, 'byocOrderConfirmation']],
            ['method' => 'GET', 'path' => '/admin/login', 'handler' => [$web, 'adminLogin']],
            ['method' => 'GET', 'path' => '/admin/dashboard', 'handler' => [$web, 'adminDashboard']],
            ['method' => 'GET', 'path' => '/admin/products', 'handler' => [$web, 'adminProducts']],
            ['method' => 'GET', 'path' => '/admin/categories', 'handler' => [$web, 'adminCategories']],
            ['method' => 'GET', 'path' => '/admin/courses', 'handler' => [$web, 'adminCourses']],
            ['method' => 'GET', 'path' => '/admin/events', 'handler' => [$web, 'adminEvents']],
            ['method' => 'GET', 'path' => '/admin/orders', 'handler' => [$web, 'adminOrders']],
            ['method' => 'GET', 'path' => '/admin/customers', 'handler' => [$web, 'adminCustomers']],
            ['method' => 'GET', 'path' => '/admin/banners', 'handler' => [$web, 'adminBanners']],
            ['method' => 'GET', 'path' => '/admin/bulk-import', 'handler' => [$web, 'adminBulkImport']],
            ['method' => 'GET', 'path' => '/admin/media', 'handler' => [$web, 'adminMedia']],
            ['method' => 'GET', 'path' => '/admin/coupons', 'handler' => [$web, 'placeholder']],
            ['method' => 'GET', 'path' => '/admin/content', 'handler' => [$web, 'adminContent']],
            ['method' => 'GET', 'path' => '/admin/b2b-accounts', 'handler' => [$web, 'adminB2bAccounts']],
            ['method' => 'GET', 'path' => '/admin/b2b-quotes', 'handler' => [$web, 'adminB2bQuotes']],
            ['method' => 'GET', 'path' => '/admin/b2b-orders', 'handler' => [$web, 'adminB2bOrders']],
            ['method' => 'GET', 'path' => '/admin/reports', 'handler' => [$web, 'adminReports']],
            ['method' => 'GET', 'path' => '/admin/finance', 'handler' => [$web, 'adminFinanceDashboard']],
            ['method' => 'GET', 'path' => '/admin/invoices', 'handler' => [$web, 'adminInvoices']],
            ['method' => 'GET', 'path' => '/admin/communications', 'handler' => [$web, 'adminComms']],
            ['method' => 'GET', 'path' => '/admin/whatsapp/meta-integration', 'handler' => [$web, 'adminWhatsAppMeta']],
            ['method' => 'GET', 'path' => '/admin/whatsapp/templates', 'handler' => [$web, 'adminWhatsAppTemplates']],
            ['method' => 'GET', 'path' => '/admin/whatsapp/mappings', 'handler' => [$web, 'adminWhatsAppMappings']],
            ['method' => 'GET', 'path' => '/admin/whatsapp/logs', 'handler' => [$web, 'adminWhatsAppLogs']],
            ['method' => 'GET', 'path' => '/admin/automation', 'handler' => [$web, 'adminAutomation']],
            ['method' => 'GET', 'path' => '/admin/birthdays', 'handler' => [$web, 'adminBirthdays']],
            ['method' => 'GET', 'path' => '/api/health', 'handler' => [$api, 'health']],
            ['method' => 'GET', 'path' => '/api/health/db', 'handler' => [$api, 'healthDb']],
            ['method' => 'GET', 'path' => '/api/banners', 'handler' => [$api, 'banners']],
            ['method' => 'GET', 'path' => '/api/site-top-offer', 'handler' => [$api, 'siteTopOffer']],
            
            ['method' => 'GET', 'path' => '/api/catalog/categories', 'handler' => [$api, 'categories']],
            ['method' => 'GET', 'path' => '/api/catalog/products', 'handler' => [$api, 'products']],
            ['method' => 'GET', 'path' => '/api/search', 'handler' => [$api, 'search']],
            ['method' => 'GET', 'path' => '/api/catalog/products/:slug', 'handler' => [$api, 'product']],
            ['method' => 'GET', 'path' => '/api/catalog/courses', 'handler' => [$api, 'courses']],
            ['method' => 'GET', 'path' => '/api/catalog/courses/:slug', 'handler' => [$api, 'courseDetail']],
            ['method' => 'GET', 'path' => '/api/catalog/courses/:slug/batches', 'handler' => [$api, 'courseBatches']],
            ['method' => 'GET', 'path' => '/api/catalog/events', 'handler' => [$api, 'events']],
            ['method' => 'GET', 'path' => '/api/catalog/events/:slug', 'handler' => [$api, 'eventDetail']],
            ['method' => 'POST', 'path' => '/api/auth/register', 'handler' => [$api, 'authRegister']],
            ['method' => 'POST', 'path' => '/api/auth/login', 'handler' => [$api, 'authLogin']],
            ['method' => 'POST', 'path' => '/api/send-otp', 'handler' => [$api, 'sendOtp']],
            ['method' => 'POST', 'path' => '/api/verify-otp', 'handler' => [$api, 'verifyOtp']],
            ['method' => 'POST', 'path' => '/api/auth/forgot-password', 'handler' => [$api, 'authForgotPassword']],
            ['method' => 'POST', 'path' => '/api/auth/reset-password', 'handler' => [$api, 'authResetPassword']],
            ['method' => 'GET', 'path' => '/api/auth/me', 'handler' => [$api, 'authMe']],
            ['method' => 'POST', 'path' => '/api/auth/logout', 'handler' => [$api, 'authLogout']],
            ['method' => 'GET', 'path' => '/api/cart', 'handler' => [$api, 'cartGet']],
            ['method' => 'GET', 'path' => '/api/toppers', 'handler' => [$api, 'toppers']],
            ['method' => 'POST', 'path' => '/api/cart/items', 'handler' => [$api, 'cartAddItem']],
            ['method' => 'PATCH', 'path' => '/api/cart/items/:id', 'handler' => [$api, 'cartUpdateItem']],
            ['method' => 'DELETE', 'path' => '/api/cart/items/:id', 'handler' => [$api, 'cartDeleteItem']],
            ['method' => 'POST', 'path' => '/api/cart/coupon', 'handler' => [$api, 'cartApplyCoupon']],
            ['method' => 'GET', 'path' => '/api/fulfilment/pincode/:postalCode', 'handler' => [$api, 'fulfilmentByPincode']],
            ['method' => 'GET', 'path' => '/api/fulfilment/slots', 'handler' => [$api, 'fulfilmentSlots']],
            ['method' => 'POST', 'path' => '/api/checkout/preview', 'handler' => [$api, 'checkoutPreview']],
            ['method' => 'POST', 'path' => '/api/orders/place', 'handler' => [$api, 'placeOrder']],
            ['method' => 'POST', 'path' => '/api/webhooks/bank-alerts', 'handler' => [$api, 'bankAlertsWebhookIngest']],
            ['method' => 'GET', 'path' => '/api/orders', 'handler' => [$api, 'ordersList']],
            ['method' => 'GET', 'path' => '/api/orders/:id', 'handler' => [$api, 'orderDetail']],
            ['method' => 'GET', 'path' => '/api/orders/:id/invoice', 'handler' => [$api, 'orderInvoiceDownload']],
            ['method' => 'POST', 'path' => '/api/orders/:id/utr', 'handler' => [$api, 'submitOrderUtr']],
            ['method' => 'GET', 'path' => '/api/wishlist', 'handler' => [$api, 'wishlistList']],
            ['method' => 'POST', 'path' => '/api/wishlist/items', 'handler' => [$api, 'wishlistAddItem']],
            ['method' => 'DELETE', 'path' => '/api/wishlist/items/:productId', 'handler' => [$api, 'wishlistDeleteItem']],
            ['method' => 'GET', 'path' => '/api/account/profile', 'handler' => [$api, 'accountProfileGet']],
            ['method' => 'PATCH', 'path' => '/api/account/profile', 'handler' => [$api, 'accountProfileUpdate']],
            ['method' => 'GET', 'path' => '/api/account/addresses', 'handler' => [$api, 'accountAddressesList']],
            ['method' => 'POST', 'path' => '/api/account/addresses', 'handler' => [$api, 'accountAddressCreate']],
            ['method' => 'PATCH', 'path' => '/api/account/addresses/:id', 'handler' => [$api, 'accountAddressUpdate']],
            ['method' => 'DELETE', 'path' => '/api/account/addresses/:id', 'handler' => [$api, 'accountAddressDelete']],
            ['method' => 'POST', 'path' => '/api/inquiries/contact', 'handler' => [$api, 'contactInquirySubmit']],
            ['method' => 'POST', 'path' => '/api/inquiries/course', 'handler' => [$api, 'courseInquirySubmit']],
            ['method' => 'POST', 'path' => '/api/inquiries/event', 'handler' => [$api, 'eventInquirySubmit']],
            ['method' => 'POST', 'path' => '/api/inquiries/custom-cake', 'handler' => [$api, 'customCakeInquirySubmit']],
            ['method' => 'POST', 'path' => '/api/inquiries/custom-cake/quote-accept/:token', 'handler' => [$api, 'customCakeQuoteAccept']],
            ['method' => 'POST', 'path' => '/api/b2b/inquiry', 'handler' => [$api, 'b2bInquiry']],
            ['method' => 'POST', 'path' => '/api/b2b/quote', 'handler' => [$api, 'b2bQuoteRequest']],
            ['method' => 'POST', 'path' => '/api/b2b/auth/register', 'handler' => [$api, 'b2bAuthRegister']],
            ['method' => 'POST', 'path' => '/api/b2b/auth/login', 'handler' => [$api, 'b2bAuthLogin']],
            ['method' => 'POST', 'path' => '/api/b2b/auth/logout', 'handler' => [$api, 'b2bAuthLogout']],
            ['method' => 'GET', 'path' => '/api/b2b/auth/me', 'handler' => [$api, 'b2bAuthMe']],
            ['method' => 'GET', 'path' => '/api/b2b/dashboard/summary', 'handler' => [$api, 'b2bDashboardSummary']],
            ['method' => 'GET', 'path' => '/api/b2b/dashboard/quotes', 'handler' => [$api, 'b2bDashboardQuotes']],
            ['method' => 'POST', 'path' => '/api/b2b/dashboard/quote-request', 'handler' => [$api, 'b2bDashboardQuoteRequest']],
            ['method' => 'GET', 'path' => '/api/admin/dashboard/summary', 'handler' => [$api, 'adminDashboardSummary']],
            ['method' => 'POST', 'path' => '/api/admin/auth/login', 'handler' => [$adminApi, 'authLogin']],
            ['method' => 'POST', 'path' => '/api/admin/auth/send-otp', 'handler' => [$adminApi, 'authSendOtp']],
            ['method' => 'POST', 'path' => '/api/admin/auth/verify-otp', 'handler' => [$adminApi, 'authVerifyOtp']],
            ['method' => 'POST', 'path' => '/api/admin/auth/logout', 'handler' => [$adminApi, 'authLogout']],
            ['method' => 'GET', 'path' => '/api/admin/auth/me', 'handler' => [$adminApi, 'authMe']],
            ['method' => 'GET', 'path' => '/api/admin/products', 'handler' => [$adminApi, 'productsList']],
            ['method' => 'POST', 'path' => '/api/admin/products', 'handler' => [$adminApi, 'productsCreate']],
            ['method' => 'PATCH', 'path' => '/api/admin/products/:id', 'handler' => [$adminApi, 'productsUpdate']],
            ['method' => 'DELETE', 'path' => '/api/admin/products/:id', 'handler' => [$adminApi, 'productsDelete']],
            ['method' => 'GET', 'path' => '/api/admin/categories', 'handler' => [$adminApi, 'categoriesList']],
            ['method' => 'POST', 'path' => '/api/admin/categories', 'handler' => [$adminApi, 'categoriesCreate']],
            ['method' => 'PATCH', 'path' => '/api/admin/categories/:id', 'handler' => [$adminApi, 'categoriesUpdate']],
            ['method' => 'DELETE', 'path' => '/api/admin/categories/:id', 'handler' => [$adminApi, 'categoriesDelete']],
            ['method' => 'GET', 'path' => '/api/admin/courses', 'handler' => [$adminApi, 'coursesList']],
            ['method' => 'POST', 'path' => '/api/admin/courses', 'handler' => [$adminApi, 'coursesCreate']],
            ['method' => 'PATCH', 'path' => '/api/admin/courses/:id', 'handler' => [$adminApi, 'coursesUpdate']],
            ['method' => 'DELETE', 'path' => '/api/admin/courses/:id', 'handler' => [$adminApi, 'coursesDelete']],
            ['method' => 'GET', 'path' => '/api/admin/events', 'handler' => [$adminApi, 'eventsList']],
            ['method' => 'POST', 'path' => '/api/admin/events', 'handler' => [$adminApi, 'eventsCreate']],
            ['method' => 'PATCH', 'path' => '/api/admin/events/:id', 'handler' => [$adminApi, 'eventsUpdate']],
            ['method' => 'DELETE', 'path' => '/api/admin/events/:id', 'handler' => [$adminApi, 'eventsDelete']],
            ['method' => 'GET', 'path' => '/api/admin/orders', 'handler' => [$adminApi, 'ordersList']],
            ['method' => 'GET', 'path' => '/api/admin/orders/export', 'handler' => [$adminApi, 'ordersExportCsv']],
            ['method' => 'GET', 'path' => '/api/admin/orders/:id', 'handler' => [$adminApi, 'ordersDetail']],
            ['method' => 'PATCH', 'path' => '/api/admin/orders/:id/status', 'handler' => [$adminApi, 'ordersUpdateStatus']],
            ['method' => 'POST', 'path' => '/api/admin/orders/:id/confirm-payment', 'handler' => [$adminApi, 'ordersConfirmPayment']],
            ['method' => 'POST', 'path' => '/api/admin/orders/:id/reject-payment', 'handler' => [$adminApi, 'ordersRejectPayment']],
            ['method' => 'POST', 'path' => '/api/admin/orders/:id/refund/process', 'handler' => [$adminApi, 'refundProcess']],
            ['method' => 'POST', 'path' => '/api/admin/refunds/upload-proof', 'handler' => [$adminApi, 'refundUploadProof']],
            ['method' => 'POST', 'path' => '/api/admin/orders/:id/refund/request', 'handler' => [$adminApi, 'refundRequest']],
            ['method' => 'GET', 'path' => '/api/admin/orders/:id/refund-history', 'handler' => [$adminApi, 'refundHistory']],
            ['method' => 'GET', 'path' => '/api/admin/refunds', 'handler' => [$adminApi, 'refundsList']],
            ['method' => 'POST', 'path' => '/api/admin/refunds/:id/approve', 'handler' => [$adminApi, 'refundApprove']],
            ['method' => 'POST', 'path' => '/api/admin/refunds/:id/reject', 'handler' => [$adminApi, 'refundReject']],
            ['method' => 'GET', 'path' => '/api/admin/refunds/report', 'handler' => [$adminApi, 'refundReport']],
            ['method' => 'GET', 'path' => '/api/admin/import/template', 'handler' => [$adminApi, 'bulkTemplate']],
            ['method' => 'GET', 'path' => '/api/admin/import/products/export', 'handler' => [$adminApi, 'productsExportCsv']],
            ['method' => 'POST', 'path' => '/api/admin/import/products', 'handler' => [$adminApi, 'bulkImportProducts']],
            ['method' => 'GET', 'path' => '/api/admin/import/logs', 'handler' => [$adminApi, 'bulkImportLogs']],
            ['method' => 'GET', 'path' => '/api/admin/import/logs/:file/failed-rows', 'handler' => [$adminApi, 'bulkImportFailedRowsCsv']],
            ['method' => 'GET', 'path' => '/api/admin/media', 'handler' => [$adminApi, 'mediaList']],
            ['method' => 'POST', 'path' => '/api/admin/media/upload', 'handler' => [$adminApi, 'mediaUpload']],
            ['method' => 'POST', 'path' => '/api/admin/media/delete', 'handler' => [$adminApi, 'mediaDelete']],
            ['method' => 'GET', 'path' => '/api/admin/media/processing/summary', 'handler' => [$adminApi, 'mediaProcessingSummary']],
            ['method' => 'GET', 'path' => '/api/admin/media/processing/jobs', 'handler' => [$adminApi, 'mediaProcessingJobs']],
            ['method' => 'POST', 'path' => '/api/admin/branding/upload', 'handler' => [$adminApi, 'brandingUpload']],
            ['method' => 'POST', 'path' => '/api/admin/products/:id/media/attach', 'handler' => [$adminApi, 'productMediaAttach']],
            ['method' => 'GET', 'path' => '/api/admin/products/:id/media', 'handler' => [$adminApi, 'productMediaList']],
            ['method' => 'PATCH', 'path' => '/api/admin/products/:id/media/reorder', 'handler' => [$adminApi, 'productMediaReorderAll']],
            ['method' => 'PATCH', 'path' => '/api/admin/products/:id/media/:imageId/reorder', 'handler' => [$adminApi, 'productMediaReorder']],
            ['method' => 'DELETE', 'path' => '/api/admin/products/:id/media/:imageId', 'handler' => [$adminApi, 'productMediaDelete']],
            ['method' => 'GET', 'path' => '/api/admin/finance/summary', 'handler' => [$adminApi, 'financeSummary']],
            ['method' => 'GET', 'path' => '/api/admin/invoices', 'handler' => [$adminApi, 'invoicesList']],
            ['method' => 'GET', 'path' => '/api/admin/invoices/:id', 'handler' => [$adminApi, 'invoiceDetail']],
            ['method' => 'PATCH', 'path' => '/api/admin/invoices/:id/status', 'handler' => [$adminApi, 'invoiceUpdateStatus']],
            ['method' => 'POST', 'path' => '/api/admin/invoices/:id/payments', 'handler' => [$adminApi, 'invoiceRecordPayment']],
            ['method' => 'GET', 'path' => '/api/admin/finance/ageing', 'handler' => [$adminApi, 'financeAgeingReport']],
            ['method' => 'GET', 'path' => '/api/admin/bank-alerts', 'handler' => [$adminApi, 'bankAlertsQueueList']],
            ['method' => 'POST', 'path' => '/api/admin/bank-alerts/:id/confirm', 'handler' => [$adminApi, 'bankAlertsConfirm']],
            ['method' => 'POST', 'path' => '/api/admin/bank-alerts/:id/reject', 'handler' => [$adminApi, 'bankAlertsReject']],
            ['method' => 'GET', 'path' => '/api/admin/settings/smtp', 'handler' => [$adminApi, 'smtpSettingsGet']],
            ['method' => 'PATCH', 'path' => '/api/admin/settings/smtp', 'handler' => [$adminApi, 'smtpSettingsUpdate']],
            ['method' => 'POST', 'path' => '/api/admin/settings/smtp/test', 'handler' => [$adminApi, 'smtpSendTest']],
            ['method' => 'GET', 'path' => '/api/admin/settings/whatsapp', 'handler' => [$adminApi, 'whatsappSettingsGet']],
            ['method' => 'PATCH', 'path' => '/api/admin/settings/whatsapp', 'handler' => [$adminApi, 'whatsappSettingsUpdate']],
            ['method' => 'POST', 'path' => '/api/admin/settings/whatsapp/test', 'handler' => [$adminApi, 'whatsappSettingsTest']],
            ['method' => 'GET', 'path' => '/api/admin/communication/templates', 'handler' => [$adminApi, 'communicationTemplatesList']],
            ['method' => 'PATCH', 'path' => '/api/admin/communication/templates/:id', 'handler' => [$adminApi, 'communicationTemplateUpdate']],
            ['method' => 'POST', 'path' => '/api/admin/communication/templates/:id/test', 'handler' => [$adminApi, 'communicationTemplateSendTest']],
            ['method' => 'GET', 'path' => '/api/admin/communication/logs', 'handler' => [$adminApi, 'communicationLogsList']],
            ['method' => 'POST', 'path' => '/api/admin/communication/logs/:id/retry', 'handler' => [$adminApi, 'communicationRetry']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/templates', 'handler' => [$adminApi, 'whatsappTemplatesList']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/templates/:id', 'handler' => [$adminApi, 'whatsappTemplateDetail']],
            ['method' => 'POST', 'path' => '/api/admin/whatsapp/templates', 'handler' => [$adminApi, 'whatsappTemplateCreate']],
            ['method' => 'PATCH', 'path' => '/api/admin/whatsapp/templates/:id', 'handler' => [$adminApi, 'whatsappTemplateUpdate']],
            ['method' => 'POST', 'path' => '/api/admin/whatsapp/templates/auto-generate', 'handler' => [$adminApi, 'whatsappTemplatesAutoGenerate']],
            ['method' => 'POST', 'path' => '/api/admin/whatsapp/templates/sync', 'handler' => [$adminApi, 'whatsappTemplatesSync']],
            ['method' => 'POST', 'path' => '/api/admin/whatsapp/templates/bulk-submit', 'handler' => [$adminApi, 'whatsappTemplatesBulkSubmit']],
            ['method' => 'POST', 'path' => '/api/admin/whatsapp/templates/:id/preview', 'handler' => [$adminApi, 'whatsappTemplatePreview']],
            ['method' => 'POST', 'path' => '/api/admin/whatsapp/templates/:id/submit', 'handler' => [$adminApi, 'whatsappTemplateSubmit']],
            ['method' => 'POST', 'path' => '/api/admin/whatsapp/templates/:id/clone-fix', 'handler' => [$adminApi, 'whatsappTemplateCloneFix']],
            ['method' => 'POST', 'path' => '/api/admin/whatsapp/templates/:id/test-send', 'handler' => [$adminApi, 'whatsappTemplateTestSend']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/templates/:id/versions', 'handler' => [$adminApi, 'whatsappTemplateVersionsList']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/mappings', 'handler' => [$adminApi, 'whatsappTemplateMappingsList']],
            ['method' => 'PATCH', 'path' => '/api/admin/whatsapp/mappings/:eventKey', 'handler' => [$adminApi, 'whatsappTemplateMappingUpdate']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/logs/overview', 'handler' => [$adminApi, 'whatsappLogsOverview']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/logs/sync', 'handler' => [$adminApi, 'whatsappSyncLogsList']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/logs/approval', 'handler' => [$adminApi, 'whatsappApprovalLogsList']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/logs/send', 'handler' => [$adminApi, 'whatsappSendLogsList']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/logs/failed-queue', 'handler' => [$adminApi, 'whatsappFailedQueueList']],
            ['method' => 'GET', 'path' => '/api/admin/whatsapp/logs/usage-report', 'handler' => [$adminApi, 'whatsappUsageReport']],
            ['method' => 'GET', 'path' => '/api/admin/automation/rules', 'handler' => [$adminApi, 'automationRulesList']],
            ['method' => 'PATCH', 'path' => '/api/admin/automation/rules/:id', 'handler' => [$adminApi, 'automationRuleUpdate']],
            ['method' => 'GET', 'path' => '/api/admin/reminders', 'handler' => [$adminApi, 'remindersList']],
            ['method' => 'POST', 'path' => '/api/admin/reminders', 'handler' => [$adminApi, 'reminderCreate']],
            ['method' => 'PATCH', 'path' => '/api/admin/reminders/:id', 'handler' => [$adminApi, 'reminderUpdate']],
            ['method' => 'GET', 'path' => '/api/admin/customers/upcoming-birthdays', 'handler' => [$adminApi, 'upcomingBirthdays']],
            ['method' => 'GET', 'path' => '/api/admin/customers', 'handler' => [$adminApi, 'customersList']],
            ['method' => 'GET', 'path' => '/api/admin/b2b/accounts', 'handler' => [$adminApi, 'b2bAccountsList']],
            ['method' => 'PATCH', 'path' => '/api/admin/b2b/accounts/:id', 'handler' => [$adminApi, 'b2bAccountUpdate']],
            ['method' => 'GET', 'path' => '/api/admin/b2b/quotes', 'handler' => [$adminApi, 'b2bQuotesList']],
            ['method' => 'PATCH', 'path' => '/api/admin/b2b/quotes/:id', 'handler' => [$adminApi, 'b2bQuoteUpdate']],
            ['method' => 'POST', 'path' => '/api/admin/b2b/quotes/:id/convert', 'handler' => [$adminApi, 'b2bQuoteConvertToOrder']],
            ['method' => 'GET', 'path' => '/api/admin/b2b/orders', 'handler' => [$adminApi, 'b2bOrdersList']],
            ['method' => 'PATCH', 'path' => '/api/admin/b2b/orders/:id', 'handler' => [$adminApi, 'b2bOrderUpdate']],
            ['method' => 'GET', 'path' => '/api/admin/banners', 'handler' => [$adminApi, 'bannersList']],
            ['method' => 'POST', 'path' => '/api/admin/banners', 'handler' => [$adminApi, 'bannerCreate']],
            ['method' => 'PATCH', 'path' => '/api/admin/banners/:id', 'handler' => [$adminApi, 'bannerUpdate']],
            ['method' => 'DELETE', 'path' => '/api/admin/banners/:id', 'handler' => [$adminApi, 'bannerDelete']],
            ['method' => 'GET', 'path' => '/api/admin/pages', 'handler' => [$adminApi, 'pagesList']],
            ['method' => 'PATCH', 'path' => '/api/admin/pages/:id', 'handler' => [$adminApi, 'pageUpdate']],
            ['method' => 'GET', 'path' => '/api/admin/reports/summary', 'handler' => [$adminApi, 'reportsSummary']],
            ['method' => 'GET', 'path' => '/api/admin/queue/jobs', 'handler' => [$adminApi, 'queueJobsList']],
            ['method' => 'POST', 'path' => '/api/admin/queue/process', 'handler' => [$adminApi, 'queueProcessNow']],
            ['method' => 'GET', 'path' => '/cron/queue/process', 'handler' => [$cron, 'queueProcess']],
            ['method' => 'GET', 'path' => '/cron/whatsapp/templates/sync', 'handler' => [$cron, 'whatsappTemplateSync']],

            // ── Slot Management ───────────────────────────────────────────────
            ['method' => 'GET',    'path' => '/api/admin/slots',                    'handler' => [$adminApi, 'slotsList']],
            ['method' => 'POST',   'path' => '/api/admin/slots',                    'handler' => [$adminApi, 'slotCreate']],
            ['method' => 'GET',    'path' => '/api/admin/slots/usage',              'handler' => [$adminApi, 'slotUsage']],
            ['method' => 'PATCH',  'path' => '/api/admin/slots/:id',                'handler' => [$adminApi, 'slotUpdate']],
            ['method' => 'DELETE', 'path' => '/api/admin/slots/:id',                'handler' => [$adminApi, 'slotDelete']],
            ['method' => 'POST',   'path' => '/api/admin/slots/:id/toggle',         'handler' => [$adminApi, 'slotToggle']],
            ['method' => 'GET',    'path' => '/api/admin/slots/:id/exceptions',     'handler' => [$adminApi, 'slotExceptionsList']],
            ['method' => 'POST',   'path' => '/api/admin/slots/:id/exceptions',     'handler' => [$adminApi, 'slotExceptionCreate']],
            ['method' => 'DELETE', 'path' => '/api/admin/slot-exceptions',          'handler' => [$adminApi, 'slotExceptionDelete']],
            ['method' => 'GET',    'path' => '/api/admin/holidays',                 'handler' => [$adminApi, 'holidaysList']],
            ['method' => 'POST',   'path' => '/api/admin/holidays',                 'handler' => [$adminApi, 'holidayCreate']],
            ['method' => 'DELETE', 'path' => '/api/admin/holidays',                 'handler' => [$adminApi, 'holidayDelete']],
        ];
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') {
            // Serve the same routes as GET while PHP naturally suppresses the body for HEAD requests.
            $method = 'GET';
        }
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $configuredBasePath = trim((string) Env::get('APP_BASE_PATH', ''));
        $basePath = '';

        if ($configuredBasePath !== '') {
            $basePath = '/' . trim($configuredBasePath, '/');
        } elseif (isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])) {
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            if ($scriptDir !== '/' && $scriptDir !== '.') {
                $basePath = rtrim($scriptDir, '/');
            }
        }

        if ($basePath !== '' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        // Backward compatibility for legacy deployed links.
        $legacyBasePath = '/Cakeouflage-E-commerce';
        if (strpos($uri, $legacyBasePath . '/') === 0) {
            $uri = substr($uri, strlen($legacyBasePath)) ?: '/';
        }

        $uri = rtrim($uri, '/') ?: '/';

 
        if ($this->requiresCsrfProtection($method, $uri) && !Csrf::validateRequest()) {
            Response::json([
                'success' => false,
                'message' => 'Invalid CSRF token',
            ], 403);
            return;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('#:([\w]+)#', '([^/]+)', $route['path']);
            $pattern = '#^' . str_replace('/', '\/', $pattern) . '$#';

            if ($pattern === null) {
                continue;
            }

            if (preg_match($pattern, $uri, $matches) === 1) {
                array_shift($matches);
                $handler = $route['handler'];
                if (is_string($handler[0])) {
                    $handler[0] = new $handler[0]();
                }
                call_user_func_array($handler, $matches);
                return;
            }
        }

        http_response_code(404);
        View::render('placeholder', [
            'title' => 'Page Not Found',
            'breadcrumbs' => [['label' => '404']],
            'pageTitle' => 'Page Not Found',
            'pagePath' => $uri,
        ]);
    }

    private function requiresCsrfProtection(string $method, string $uri): bool
    {
        if (!in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return false;
        }

        if (!(strpos($uri, '/api/') === 0) && !(strpos($uri, '/cron/') === 0)) {
            return false;
        }

        if (strpos($uri, '/cron/') === 0) {
            return false;
        }

        return true;
    }
}
