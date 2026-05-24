<?php
/**
 * One-time utility: generates default-product-image.webp from
 * public/assets/defaults/default-product-image.png using PHP GD.
 *
 * USAGE:
 *   1. Save the Cakeouflage brand PNG to:
 *          public/assets/defaults/default-product-image.png
 *   2. Run in browser or CLI:  php __generate_default_webp.php
 *   3. Delete this file once done (or leave it — it won't overwrite existing WebP unless forced).
 *
 * CLI:  docker exec cakeouflage-web php /var/www/html/__generate_default_webp.php
 */

$root   = __DIR__;
$png    = $root . '/public/assets/defaults/default-product-image.png';
$webp   = $root . '/public/assets/defaults/default-product-image.webp';
$isWeb  = PHP_SAPI !== 'cli';

header_output('Cakeouflage — Default Product Image WebP Generator');

check_step('GD extension available', function_exists('imagecreatefrompng') && function_exists('imagewebp'));
check_step('PNG source exists: ' . rel($root, $png), file_exists($png));

if (!file_exists($png)) {
    output_error('PNG not found. Please save your brand image to public/assets/defaults/default-product-image.png and run again.');
    exit(1);
}

$force = isset($_GET['force']) || in_array('--force', $argv ?? [], true);

if (file_exists($webp) && !$force) {
    output_info('WebP already exists. Append ?force=1 (browser) or --force (CLI) to regenerate.');
} else {
    $src = @imagecreatefrompng($png);
    if (!$src) {
        output_error('Failed to load PNG. Ensure it is a valid PNG image.');
        exit(1);
    }
    // Preserve transparency
    imagealphablending($src, false);
    imagesavealpha($src, true);

    $ok = imagewebp($src, $webp, 85);
    imagedestroy($src);

    if ($ok) {
        check_step('Generated ' . rel($root, $webp) . ' (' . round(filesize($webp) / 1024, 1) . ' KB)', true);
    } else {
        check_step('Write WebP to ' . rel($root, $webp), false);
        output_error('imagewebp() returned false. Check directory write permissions.');
        exit(1);
    }
}

output_info('Done. You can now delete __generate_default_webp.php or keep it for future regeneration.');

/* ── helpers ─────────────────────────────────────────────────── */
function header_output(string $title): void {
    if (PHP_SAPI === 'cli') { echo "\n=== $title ===\n\n"; return; }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font:14px/1.6 monospace;max-width:700px;margin:40px auto;padding:0 20px;}';
    echo '.ok{color:#1a7d45}.err{color:#c0392b}.info{color:#2c5fba}pre{background:#f5f5f5;padding:10px}</style></head><body>';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
}

function check_step(string $label, bool $ok): void {
    $mark = $ok ? '✅' : '❌';
    if (PHP_SAPI === 'cli') { echo $mark . ' ' . $label . "\n"; return; }
    $cls = $ok ? 'ok' : 'err';
    echo "<p class=\"$cls\">$mark " . htmlspecialchars($label) . "</p>";
}

function output_error(string $msg): void {
    if (PHP_SAPI === 'cli') { echo "\n⚠  ERROR: $msg\n\n"; return; }
    echo '<p class="err">⚠ ' . htmlspecialchars($msg) . '</p>';
}

function output_info(string $msg): void {
    if (PHP_SAPI === 'cli') { echo "\nℹ  $msg\n\n"; return; }
    echo '<p class="info">ℹ ' . htmlspecialchars($msg) . '</p>';
}

function rel(string $base, string $path): string {
    return ltrim(str_replace($base, '', $path), '/\\');
}
