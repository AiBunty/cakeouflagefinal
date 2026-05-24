<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function buildMultipartBody(array $fields, array $file): array
{
    $boundary = '----CakeouflageBoundary' . bin2hex(random_bytes(8));
    $eol = "\r\n";
    $body = '';

    foreach ($fields as $name => $value) {
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
        $body .= (string)$value . $eol;
    }

    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="' . $file['field'] . '"; filename="' . $file['name'] . '"' . $eol;
    $body .= 'Content-Type: ' . $file['type'] . $eol . $eol;
    $body .= $file['content'] . $eol;
    $body .= '--' . $boundary . '--' . $eol;

    return [$body, $boundary];
}

function postMultipart(string $url, string $cookie, array $fields, array $file): array
{
    [$body, $boundary] = buildMultipartBody($fields, $file);

    $headers = [
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Cookie: ' . $cookie,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'error' => 'curl_error:' . $err, 'status' => 0, 'headers' => '', 'body' => ''];
        }

        $location = '';
        foreach (explode("\r\n", (string)substr($resp, 0, $headerSize)) as $line) {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
                break;
            }
        }

        return [
            'ok' => true,
            'error' => '',
            'status' => $code,
            'headers' => substr($resp, 0, $headerSize),
            'body' => substr($resp, $headerSize),
            'location' => $location,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);

    $respBody = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    $status = 0;
    if (preg_match('/\s(\d{3})\s/', $statusLine, $m)) {
        $status = (int)$m[1];
    }

    $location = '';
    foreach (($http_response_header ?? []) as $line) {
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, 9));
            break;
        }
    }

    return [
        'ok' => $respBody !== false,
        'error' => $respBody === false ? 'stream_post_failed' : '',
        'status' => $status,
        'headers' => implode("\n", $http_response_header ?? []),
        'body' => (string)($respBody ?: ''),
        'location' => $location,
    ];
}

function loginAndGetCookie(string $url): string
{
    $postData = http_build_query([
        'email' => 'admin@cakeouflage.com',
        'password' => 'admin123',
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
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
            throw new RuntimeException('Login curl failed: ' . $err);
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

        throw new RuntimeException('Login succeeded but cakeouflage_sid cookie not found.');
    }

    throw new RuntimeException('cURL extension is required for login in this E2E harness.');
}

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$samplePath = __DIR__ . '/../../storage/test-media/sample-image.png';
if (!is_file($samplePath)) {
    throw new RuntimeException('Sample image not found: ' . $samplePath);
}
$sampleContent = file_get_contents($samplePath);
if ($sampleContent === false) {
    throw new RuntimeException('Unable to read sample image.');
}

$products = $pdo->query(
    "SELECT id, name, COALESCE(NULLIF(starting_price,0), 500) AS starting_price, collection_category_id, COALESCE(short_description,'') AS short_description, COALESCE(availability_status,'in_stock') AS availability_status, COALESCE(featured_image,'') AS featured_image, COALESCE(dietary_tag,'regular') AS dietary_tag
     FROM products
     WHERE deleted_at IS NULL
     ORDER BY id ASC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

if (count($products) < 5) {
    throw new RuntimeException('Need at least 5 products to run upload E2E test. Found: ' . count($products));
}

$cookie = loginAndGetCookie('http://localhost/admin/login_process.php');
$url = 'http://localhost/admin/products.php';

$results = [];
foreach ($products as $index => $product) {
    $id = (int)$product['id'];
    $fields = [
        'action' => 'update_product_inline',
        'id' => (string)$id,
        'name' => (string)$product['name'],
        'base_price' => (string)$product['starting_price'],
        'category_id' => (string)$product['collection_category_id'],
        'description' => (string)$product['short_description'],
        'availability_status' => (string)$product['availability_status'],
        'current_image' => (string)$product['featured_image'],
        'dietary_tag' => (string)$product['dietary_tag'],
    ];

    $file = [
        'field' => 'image',
        'name' => 'sample-image-' . ($index + 1) . '.png',
        'type' => 'image/png',
        'content' => $sampleContent,
    ];

    $resp = postMultipart($url, $cookie, $fields, $file);
    $location = (string)($resp['location'] ?? '');
    $ok = $resp['ok']
        && ($resp['status'] === 302 || $resp['status'] === 200)
        && (strpos($location, 'products.php?updated=1') !== false || strpos((string)$resp['body'], 'updated=1') !== false);

    $results[] = [
        'product_id' => $id,
        'name' => (string)$product['name'],
        'status' => (int)$resp['status'],
        'ok' => $ok,
        'error' => (string)$resp['error'],
        'location' => $location,
        'header_snippet' => substr((string)$resp['headers'], 0, 200),
    ];
}

$verifyStmt = $pdo->query(
    "SELECT id, name, featured_image FROM products WHERE id IN (" . implode(',', array_map(static fn(array $r): string => (string)$r['product_id'], $results)) . ") ORDER BY id ASC"
);
$verifyRows = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
$verifyMap = [];
foreach ($verifyRows as $row) {
    $verifyMap[(int)$row['id']] = (string)$row['featured_image'];
}

$passCount = 0;
foreach ($results as &$result) {
    $finalPath = $verifyMap[(int)$result['product_id']] ?? '';
    $result['featured_image'] = $finalPath;
    $result['db_path_ok'] = strpos($finalPath, '/public/uploads/originals/products/') === 0;
    if ($result['ok'] && $result['db_path_ok']) {
        $passCount++;
    }
}
unset($result);

$report = [
    'run_at' => date('c'),
    'auth_cookie' => $cookie,
    'sample_image' => $samplePath,
    'pass_count' => $passCount,
    'total' => count($results),
    'results' => $results,
];

$outDir = __DIR__ . '/../../storage/recovery';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$reportPath = $outDir . '/product-upload-e2e-' . date('Ymd_His') . '.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

out('Product upload E2E test complete.');
out('Report: ' . $reportPath);
out('Pass: ' . $passCount . '/' . count($results));
foreach ($results as $r) {
    out(' - product_id=' . $r['product_id'] . ' ok=' . ($r['ok'] ? '1' : '0') . ' db_path_ok=' . ($r['db_path_ok'] ? '1' : '0'));
}

exit($passCount === count($results) ? 0 : 1);
