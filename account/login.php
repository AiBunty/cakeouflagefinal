<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

\App\Core\View::render('account-login', [
    'title' => 'Customer Login',
    'breadcrumbs' => [['label' => 'Customer Login']],
]);
