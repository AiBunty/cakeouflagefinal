<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (!\App\Core\CustomerAuthMiddleware::requireAuthenticated('/account/login.php')) {
    exit;
}

\App\Core\View::render('account-dashboard', [
    'title' => 'My Dashboard',
    'breadcrumbs' => [['label' => 'My Dashboard']],
]);
