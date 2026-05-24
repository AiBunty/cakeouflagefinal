<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function loginCookie(): string
{
    $postData = http_build_query([
        'email' => 'admin@cakeouflage.com',
        'password' => 'admin123',
    ]);

    $ch = curl_init('http://localhost/admin/login_process.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('Login failed: ' . $err);
    }

    $headers = (string)substr($resp, 0, $headerSize);
    foreach (explode("\r\n", $headers) as $line) {
        if (stripos($line, 'Set-Cookie:') !== 0) {
            continue;
        }
        $cookiePart = trim(substr($line, 11));
        if (stripos($cookiePart, 'cakeouflage_sid=') === 0) {
            $semi = strpos($cookiePart, ';');
            return $semi === false ? $cookiePart : substr($cookiePart, 0, $semi);
        }
    }

    throw new RuntimeException('Admin cookie not found in login response.');
}

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$permStmt = $pdo->prepare('INSERT IGNORE INTO admin_permissions (admin_id, permission_key) VALUES (1, :permission_key)');
$permStmt->execute([':permission_key' => 'products']);

$catId = (int)($pdo->query('SELECT id FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
if ($catId <= 0) {
    throw new RuntimeException('No active category found for add-product test.');
}

$cookie = loginCookie();
$marker = 'AUTO-DEFAULT-IMG-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

$postData = http_build_query([
    'name' => $marker,
    'base_price' => '799',
    'category_id' => (string)$catId,
    'description' => 'Automated default image smoke test',
    'dietary_tag' => 'regular',
]);

$ch = curl_init('http://localhost/admin/add-product.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: ' . $cookie,
    ],
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 30,
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if ($resp === false) {
    throw new RuntimeException('Add product request failed: ' . $err);
}

$body = (string)substr($resp, $headerSize);

$productStmt = $pdo->prepare('SELECT id, featured_image FROM products WHERE name = :name ORDER BY id DESC LIMIT 1');
$productStmt->execute([':name' => $marker]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    throw new RuntimeException('Product row was not created by add-product flow. status=' . $status . ' body_snippet=' . substr($body, 0, 180));
}

$productId = (int)$product['id'];
$featuredImage = (string)($product['featured_image'] ?? '');

$galleryStmt = $pdo->prepare('SELECT image_url FROM product_images WHERE product_id = :product_id AND sort_order = 0 ORDER BY id ASC LIMIT 1');
$galleryStmt->execute([':product_id' => $productId]);
$galleryImage = (string)($galleryStmt->fetchColumn() ?: '');

$ok = $featuredImage === '/public/assets/defaults/default-product-image.webp' && $galleryImage === '/public/assets/defaults/default-product-image.webp';

$cleanupStmt = $pdo->prepare('UPDATE products SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id LIMIT 1');
$cleanupStmt->execute([':id' => $productId]);

$report = [
    'run_at' => date('c'),
    'marker' => $marker,
    'http_status' => $status,
    'product_id' => $productId,
    'featured_image' => $featuredImage,
    'gallery_image' => $galleryImage,
    'ok' => $ok,
];

$outDir = __DIR__ . '/../../storage/recovery';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$reportPath = $outDir . '/new-product-default-image-test-' . date('Ymd_His') . '.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

out('New product default-image test complete.');
out('Report: ' . $reportPath);
out('Result: ' . ($ok ? 'PASS' : 'FAIL'));

exit($ok ? 0 : 1);
