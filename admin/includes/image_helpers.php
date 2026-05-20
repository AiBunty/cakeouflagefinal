<?php
/**
 * Image upload helpers — shared by admin/products.php and admin/add-product.php
 *
 * convert_to_webp():
 *   Reads an image from $sourcePath using PHP GD (imagecreatefromstring),
 *   converts it to WebP at quality 85, and writes the result to $destPath.
 *   Returns true on success, false if GD is unavailable or conversion fails.
 *   The source file is NOT moved or deleted — the caller handles that.
 */
function convert_to_webp(string $sourcePath, string $destPath, int $quality = 85): bool
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
        return false;
    }
    $raw = @file_get_contents($sourcePath);
    if ($raw === false || $raw === '') {
        return false;
    }
    $src = @imagecreatefromstring($raw);
    if ($src === false) {
        return false;
    }
    // Preserve alpha channel (PNG / WebP with transparency)
    imagealphablending($src, false);
    imagesavealpha($src, true);
    $ok = imagewebp($src, $destPath, $quality);
    imagedestroy($src);
    return $ok;
}
