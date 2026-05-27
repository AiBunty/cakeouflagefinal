<?php
declare(strict_types=1);
$query = $_GET;
$target = 'follow_ups.php';
if (!empty($query['user_id']) || !empty($query['customer_id']) || !empty($query['phone']) || !empty($query['email'])) {
    $target = 'crm_user_history.php';
}
header('Location: ' . $target . (!empty($query) ? ('?' . http_build_query($query)) : ''));
exit;
