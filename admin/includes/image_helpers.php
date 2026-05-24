<?php
/**
 * Image upload helpers — shared by admin/products.php and admin/add-product.php
 *
 * convert_to_webp():
 *   Reads an image from $sourcePath using PHP GD (imagecreatefromstring),
 *   converts it to WebP at quality 85, and writes the result to $destPath.
 *   Returns true on success, false if GD is unavailable or conversion fails.
 *   The source file is NOT moved or deleted — the caller handles that.
 *
 * resize_and_convert_to_webp():
 *   Like convert_to_webp() but also scales the image so neither dimension exceeds
 *   $maxDimension pixels (aspect-ratio preserved). Use for product uploads to cap
 *   file size without distortion.
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

/**
 * Resize an image so neither side exceeds $maxDimension, then write as WebP.
 * If the source is already within bounds it is simply converted without upscaling.
 *
 * @param int $maxDimension  Maximum width or height in pixels (default 1200).
 * @param int $quality       WebP quality 0–100 (default 85).
 * @return bool              True on success.
 */
function resize_and_convert_to_webp(
    string $sourcePath,
    string $destPath,
    int $maxDimension = 1200,
    int $quality = 85
): bool {
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

    $origW = imagesx($src);
    $origH = imagesy($src);

    // Compute target dimensions — scale down only, never upscale
    if ($origW <= $maxDimension && $origH <= $maxDimension) {
        $newW = $origW;
        $newH = $origH;
    } elseif ($origW >= $origH) {
        $newW = $maxDimension;
        $newH = (int)round($origH * $maxDimension / $origW);
    } else {
        $newH = $maxDimension;
        $newW = (int)round($origW * $maxDimension / $origH);
    }

    if ($newW === $origW && $newH === $origH) {
        // No resize needed — convert directly
        imagealphablending($src, false);
        imagesavealpha($src, true);
        $ok = imagewebp($src, $destPath, $quality);
        imagedestroy($src);
        return $ok;
    }

    $dst = imagecreatetruecolor($newW, $newH);
    if ($dst === false) {
        imagedestroy($src);
        return false;
    }

    // Preserve transparency
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    if ($transparent !== false) {
        imagefill($dst, 0, 0, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($src);

    $ok = imagewebp($dst, $destPath, $quality);
    imagedestroy($dst);
    return $ok;
}
