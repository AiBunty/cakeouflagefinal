<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'import-test';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$categories = [
    'opera-cakes' => [
        'name' => 'Opera Cakes',
        'subcategories' => [
            'chocolate-opera' => 'Chocolate Opera',
            'coffee-opera' => 'Coffee Opera',
            'fruit-opera' => 'Fruit Opera',
            'signature-opera' => 'Signature Opera',
        ],
    ],
    'decorated-cakes' => [
        'name' => 'Decorated Cakes',
        'subcategories' => [
            'birthday-decor' => 'Birthday Decor',
            'anniversary-decor' => 'Anniversary Decor',
            'kids-theme' => 'Kids Theme',
            'wedding-decor' => 'Wedding Decor',
        ],
    ],
    'classic-cakes' => [
        'name' => 'Classic Cakes',
        'subcategories' => [
            'chocolate-classics' => 'Chocolate Classics',
            'vanilla-classics' => 'Vanilla Classics',
            'fruit-classics' => 'Fruit Classics',
            'tea-time-classics' => 'Tea Time Classics',
        ],
    ],
    'cheesecakes' => [
        'name' => 'Cheesecakes',
        'subcategories' => [
            'baked-cheesecakes' => 'Baked Cheesecakes',
            'no-bake-cheesecakes' => 'No Bake Cheesecakes',
            'berry-cheesecakes' => 'Berry Cheesecakes',
            'premium-cheesecakes' => 'Premium Cheesecakes',
        ],
    ],
    'celebration-cakes' => [
        'name' => 'Celebration Cakes',
        'subcategories' => [
            'birthday-celebration' => 'Birthday Celebration',
            'engagement-celebration' => 'Engagement Celebration',
            'baby-shower' => 'Baby Shower',
            'milestone-celebration' => 'Milestone Celebration',
        ],
    ],
    'teacakes-breads' => [
        'name' => 'Tea Cakes & Breads',
        'subcategories' => [
            'loaf-cakes' => 'Loaf Cakes',
            'bundt-cakes' => 'Bundt Cakes',
            'artisan-breads' => 'Artisan Breads',
            'quick-breads' => 'Quick Breads',
        ],
    ],
    'dessert-jars' => [
        'name' => 'Dessert Jars',
        'subcategories' => [
            'chocolate-jars' => 'Chocolate Jars',
            'fruit-jars' => 'Fruit Jars',
            'mousse-jars' => 'Mousse Jars',
            'seasonal-jars' => 'Seasonal Jars',
        ],
    ],
    'brownies-bars' => [
        'name' => 'Brownies & Bars',
        'subcategories' => [
            'fudge-brownies' => 'Fudge Brownies',
            'nutty-brownies' => 'Nutty Brownies',
            'blondies' => 'Blondies',
            'dessert-bars' => 'Dessert Bars',
        ],
    ],
    'cupcakes' => [
        'name' => 'Cupcakes',
        'subcategories' => [
            'classic-cupcakes' => 'Classic Cupcakes',
            'filled-cupcakes' => 'Filled Cupcakes',
            'party-cupcakes' => 'Party Cupcakes',
            'premium-cupcakes' => 'Premium Cupcakes',
        ],
    ],
    'seasonal-specials' => [
        'name' => 'Seasonal Specials',
        'subcategories' => [
            'summer-specials' => 'Summer Specials',
            'monsoon-specials' => 'Monsoon Specials',
            'festive-specials' => 'Festive Specials',
            'winter-specials' => 'Winter Specials',
        ],
    ],
];

$imgPool = [
    '/client/assets/images/placeholder-cake-1.jpg',
    '/client/assets/images/placeholder-cake-2.jpg',
    '/client/assets/images/placeholder-cake-3.jpg',
    '/client/assets/images/placeholder-cake-4.jpg',
    '/client/assets/images/placeholder-cake-5.jpg',
    '/client/assets/images/placeholder-cake-6.jpg',
];

$header = [
    'product_name',
    'category_slug',
    'category_name',
    'subcategory_slug',
    'subcategory_name',
    'description',
    'price',
    'discount_price',
    'sku',
    'stock',
    'tags',
    'variant_info',
    'image_url',
    'image_url_1',
    'image_url_2',
];

$rows = [];
$skuCounter = 1;
foreach ($categories as $categorySlug => $category) {
    foreach ($category['subcategories'] as $subcategorySlug => $subcategoryName) {
        for ($i = 1; $i <= 5; $i++) {
            $sku = 'CKF-MT-' . str_pad((string)$skuCounter, 4, '0', STR_PAD_LEFT);
            $price = 850 + (($skuCounter % 15) * 35);
            $discount = $skuCounter % 4 === 0 ? max(0, $price - 80) : '';
            $stock = 12 + ($skuCounter % 40);
            $tagSet = [];
            if ($skuCounter % 3 === 0) {
                $tagSet[] = 'featured';
            }
            if ($skuCounter % 5 === 0) {
                $tagSet[] = 'bestseller';
            }
            if ($skuCounter % 2 === 0) {
                $tagSet[] = 'eggless';
            }
            $tags = implode('|', $tagSet);

            $variantInfo = implode('|', [
                '0.5 kg:' . max(450, $price - 250),
                '1 lb:' . $price,
                '1.5 lb:' . ($price + 300),
                '2 lb:' . ($price + 600),
                '2.5 lb:' . ($price + 900),
                '3 lb:' . ($price + 1200),
            ]);

            $img1 = $imgPool[$skuCounter % count($imgPool)];
            $img2 = $imgPool[($skuCounter + 2) % count($imgPool)];

            $rows[] = [
                $subcategoryName . ' Signature Cake ' . $i,
                $categorySlug,
                $category['name'],
                $subcategorySlug,
                $subcategoryName,
                'Synthetic test catalog item for master-truth import and restore validation.',
                (string)$price,
                $discount === '' ? '' : (string)$discount,
                $sku,
                (string)$stock,
                $tags,
                $variantInfo,
                '',
                $img1,
                $img2,
            ];

            $skuCounter++;
        }
    }
}

if (count($rows) !== 200) {
    fwrite(STDERR, 'Expected 200 rows but generated ' . count($rows) . PHP_EOL);
    exit(1);
}

$rows150 = array_slice($rows, 0, 150);

$writeCsv = static function (string $filePath, array $header, array $rowsToWrite): void {
    $fp = fopen($filePath, 'wb');
    if ($fp === false) {
        throw new RuntimeException('Unable to open file for writing: ' . $filePath);
    }

    fputcsv($fp, $header);
    foreach ($rowsToWrite as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
};

$path200 = $outDir . DIRECTORY_SEPARATOR . 'cakeitaway-hierarchy-master-200.csv';
$path150 = $outDir . DIRECTORY_SEPARATOR . 'cakeitaway-hierarchy-master-150.csv';
$pathReadme = $outDir . DIRECTORY_SEPARATOR . 'README.txt';

$writeCsv($path200, $header, $rows);
$writeCsv($path150, $header, $rows150);

$removedSkus = array_map(
    static fn(array $row): string => (string)$row[8],
    array_slice($rows, 150)
);

$readme = [
    'Generated files:',
    '- cakeitaway-hierarchy-master-200.csv (200 rows)',
    '- cakeitaway-hierarchy-master-150.csv (150 rows)',
    '',
    'Expected soft-delete outcome:',
    '- Import 200 commit, then 150 commit -> deleted_count should be 50',
    '',
    'Removed SKUs in 150 file:',
    implode(', ', $removedSkus),
    '',
    'Columns:',
    implode(', ', $header),
    '',
    'Note:',
    '- image_url_1 and image_url_2 are primary and secondary image slots.',
    '- image_url kept for backward compatibility and intentionally empty in this fixture.',
];

file_put_contents($pathReadme, implode(PHP_EOL, $readme));

echo "Generated:\n";
echo " - {$path200}\n";
echo " - {$path150}\n";
echo " - {$pathReadme}\n";
