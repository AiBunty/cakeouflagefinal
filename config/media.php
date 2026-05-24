<?php
/**
 * Media configuration — centralized defaults for all image fallbacks.
 *
 * Web paths are relative to the document root (public/).
 * Physical files live at:   public/assets/defaults/
 *
 * To regenerate the WebP from the master PNG, run:
 *   php __generate_default_webp.php
 */
return [
    /*
     * Primary fallback for any product without an uploaded image.
     * Served as WebP (modern browsers) with PNG as <noscript> / onerror fallback.
     */
    'default_product_image'     => '/public/assets/defaults/default-product-image.webp',
    'default_product_image_png' => '/public/assets/defaults/default-product-image.png',
    'default_product_image_alt' => 'Cakeouflage Signature Cake',

    /*
     * Maximum pixel dimension applied when resizing uploaded product images.
     * Affects ImageUploadService — images larger than this are scaled down.
     */
    'upload_max_dimension' => 1200,

    /*
     * WebP quality (0–100) used when converting / resizing uploads.
     */
    'upload_webp_quality' => 85,
];
