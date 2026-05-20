<?php
/**
 * One-time deployment ZIP extractor — 2026-05-20
 * DELETE THIS FILE after use.
 * Usage: https://cakeouflage.com/__extract_20260520.php?token=762a93f01159f8fef204afc33a95c2eadf39a9ec8560412f5e28e1f4953d3452
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

header('Content-Type: text/plain; charset=utf-8');

// ── Token guard ───────────────────────────────────────────────────────────────
$EXPECTED_TOKEN = '762a93f01159f8fef204afc33a95c2eadf39a9ec8560412f5e28e1f4953d3452';
$provided = $_GET['token'] ?? '';
if (!hash_equals($EXPECTED_TOKEN, $provided)) {
    http_response_code(403);
    exit("Forbidden.\n");
}

echo "=== Cakeouflage Deployment Extractor — 2026-05-20 ===\n\n";
flush();

$zipFile = __DIR__ . '/cakeouflage_deploy_20260520.zip';
$extractTo = __DIR__;

// ── Check ZIP exists ──────────────────────────────────────────────────────────
if (!file_exists($zipFile)) {
    http_response_code(500);
    exit("ERROR: ZIP file not found at: $zipFile\n");
}

echo "ZIP: $zipFile (" . number_format(filesize($zipFile)) . " bytes)\n";
echo "Extract to: $extractTo\n\n";
flush();

// ── Check ZipArchive extension ────────────────────────────────────────────────
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit("ERROR: ZipArchive extension is not available on this server.\n");
}

// ── Extract ───────────────────────────────────────────────────────────────────
$zip = new ZipArchive();
$result = $zip->open($zipFile);
if ($result !== true) {
    http_response_code(500);
    exit("ERROR: Could not open ZIP file. ZipArchive error code: $result\n");
}

$total   = $zip->numFiles;
$success = 0;
$failed  = [];

echo "Files in ZIP: $total\n\n";
flush();

for ($i = 0; $i < $total; $i++) {
    $name = $zip->getNameIndex($i);

    // Skip directory entries (ends with /)
    if (substr($name, -1) === '/') {
        continue;
    }

    $dest = $extractTo . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir  = dirname($dest);

    // Create directory if needed
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $failed[] = "$name (mkdir failed)";
            continue;
        }
    }

    // Extract file
    $content = $zip->getFromIndex($i);
    if ($content === false) {
        $failed[] = "$name (getFromIndex failed)";
        continue;
    }

    if (file_put_contents($dest, $content) === false) {
        $failed[] = "$name (write failed)";
        continue;
    }

    $success++;

    // Progress every 50 files
    if ($success % 50 === 0) {
        echo "  Extracted $success / $total ...\n";
        flush();
    }
}

$zip->close();

echo "\n";
echo "✓ Extracted: $success files\n";

if (count($failed) > 0) {
    echo "✗ Failed (" . count($failed) . "):\n";
    foreach ($failed as $f) {
        echo "  - $f\n";
    }
}

echo "\nDone. PHP version on this server: " . PHP_VERSION . "\n";
echo "\n--- NEXT STEPS ---\n";
echo "1. Delete this file and cakeouflage_deploy_20260520.zip via File Manager\n";
echo "2. Run DB import: https://cakeouflage.com/__db_import_20260520.php?token=762a93f01159f8fef204afc33a95c2eadf39a9ec8560412f5e28e1f4953d3452\n";
echo "3. Delete __db_import_20260520.php and cakeouflage_deploy.sql via File Manager\n";
