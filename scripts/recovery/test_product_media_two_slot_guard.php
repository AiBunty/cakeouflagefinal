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

function fetchCsrfToken(string $cookie): string
{
    $ch = curl_init('http://localhost/admin/products.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Cookie: ' . $cookie,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('Failed to fetch CSRF token page: ' . $err);
    }

    if (preg_match('/<meta\\s+name="csrf-token"\\s+content="([^"]+)"/i', (string)$resp, $m)) {
        return html_entity_decode((string)$m[1], ENT_QUOTES, 'UTF-8');
    }

    throw new RuntimeException('CSRF token meta tag not found on admin products page.');
}

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$permStmt = $pdo->prepare('INSERT IGNORE INTO admin_permissions (admin_id, permission_key) VALUES (1, :permission_key)');
$permStmt->execute([':permission_key' => 'products']);

$catId = (int)($pdo->query('SELECT id FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
if ($catId <= 0) {
    throw new RuntimeException('No active category found for test setup.');
}

$mediaPaths = $pdo->query(
    "SELECT DISTINCT image_url
     FROM product_images
     WHERE image_url LIKE '/uploads/media/%'
    ORDER BY image_url DESC
     LIMIT 3"
)->fetchAll(PDO::FETCH_COLUMN);

if (count($mediaPaths) < 3) {
    $root = dirname(__DIR__, 2);
    $seedDir = $root . '/uploads/media';
    if (!is_dir($seedDir) && !mkdir($seedDir, 0775, true) && !is_dir($seedDir)) {
        throw new RuntimeException('Unable to create media seed directory: ' . $seedDir);
    }

    $seedSource = $root . '/public/assets/defaults/default-product-image.webp';
    if (!is_file($seedSource)) {
        throw new RuntimeException('Seed source image not found: ' . $seedSource);
    }

    for ($i = 1; $i <= 3; $i++) {
        $name = 'guard-seed-' . $i . '.webp';
        $dest = $seedDir . '/' . $name;
        if (!is_file($dest)) {
            if (!copy($seedSource, $dest)) {
                throw new RuntimeException('Failed to seed media file: ' . $dest);
            }
        }
        $mediaPaths[] = '/uploads/media/' . $name;
    }

    $mediaPaths = array_values(array_unique(array_map('strval', $mediaPaths)));
    if (count($mediaPaths) < 3) {
        throw new RuntimeException('Not enough /uploads/media images found for guard test after seeding.');
    }
}

$cookie = loginCookie();
$csrfToken = fetchCsrfToken($cookie);
$marker = 'AUTO-MEDIA-GUARD-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

$createData = http_build_query([
    'name' => $marker,
    'base_price' => '899',
    'category_id' => (string)$catId,
    'description' => 'Automated two-slot guard test',
    'dietary_tag' => 'regular',
]);

$createCh = curl_init('http://localhost/admin/add-product.php');
curl_setopt_array($createCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $createData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: ' . $cookie,
    ],
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 30,
]);
$createResp = curl_exec($createCh);
$createErr = curl_error($createCh);
$createStatus = (int)curl_getinfo($createCh, CURLINFO_HTTP_CODE);
$createHeaderSize = (int)curl_getinfo($createCh, CURLINFO_HEADER_SIZE);
curl_close($createCh);

if ($createResp === false) {
    throw new RuntimeException('Create product request failed: ' . $createErr);
}

$productStmt = $pdo->prepare('SELECT id FROM products WHERE name = :name ORDER BY id DESC LIMIT 1');
$productStmt->execute([':name' => $marker]);
$productId = (int)($productStmt->fetchColumn() ?: 0);
if ($productId <= 0) {
    $createBody = (string)substr($createResp, $createHeaderSize);
    throw new RuntimeException('Create product failed. status=' . $createStatus . ' body=' . substr($createBody, 0, 180));
}

$path1 = (string)$mediaPaths[0];
$path2 = (string)$mediaPaths[1];
$path3 = (string)$mediaPaths[2];

$pdo->beginTransaction();
try {
    $setFeatured = $pdo->prepare('UPDATE products SET featured_image = :featured_image, updated_at = NOW() WHERE id = :id LIMIT 1');
    $setFeatured->execute([':featured_image' => $path1, ':id' => $productId]);

    $clearGallery = $pdo->prepare('DELETE FROM product_images WHERE product_id = :product_id');
    $clearGallery->execute([':product_id' => $productId]);

    $insertGallery = $pdo->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (:product_id, :image_url, :sort_order)');
    $insertGallery->execute([':product_id' => $productId, ':image_url' => $path1, ':sort_order' => 0]);
    $insertGallery->execute([':product_id' => $productId, ':image_url' => $path2, ':sort_order' => 1]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$attachCh = curl_init('http://localhost/api/admin/products/' . $productId . '/media/attach');
curl_setopt_array($attachCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'path' => $path3,
        'mode' => 'gallery',
        'alt_text' => 'Third image guard test',
        '_csrf' => $csrfToken,
    ], JSON_UNESCAPED_SLASHES),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Cookie: ' . $cookie,
        'X-CSRF-Token: ' . $csrfToken,
    ],
    CURLOPT_HEADER => false,
    CURLOPT_TIMEOUT => 30,
]);
$attachResp = curl_exec($attachCh);
$attachErr = curl_error($attachCh);
$attachStatus = (int)curl_getinfo($attachCh, CURLINFO_HTTP_CODE);
curl_close($attachCh);

if ($attachResp === false) {
    throw new RuntimeException('Attach media request failed: ' . $attachErr);
}

$decoded = json_decode((string)$attachResp, true);
$message = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
$ok = $attachStatus === 422 && stripos($message, 'Only two images are allowed') !== false;

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = :product_id');
$countStmt->execute([':product_id' => $productId]);
$slotCount = (int)$countStmt->fetchColumn();

$cleanup = $pdo->prepare('UPDATE products SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id LIMIT 1');
$cleanup->execute([':id' => $productId]);

$report = [
    'run_at' => date('c'),
    'marker' => $marker,
    'product_id' => $productId,
    'status_code' => $attachStatus,
    'response_message' => $message,
    'slot_count_after_attempt' => $slotCount,
    'path_attempted' => $path3,
    'ok' => $ok,
];

$outDir = __DIR__ . '/../../storage/recovery';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$reportPath = $outDir . '/product-media-two-slot-guard-' . date('Ymd_His') . '.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

out('Product media two-slot guard test complete.');
out('Report: ' . $reportPath);
out('Result: ' . ($ok ? 'PASS' : 'FAIL'));

exit($ok ? 0 : 1);
