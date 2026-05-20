<?php
// Category color map for themed SVGs
$categoryColors = [
    'classic-cakes' => ['#f8d8de', '#6d1b3b'],
    'cheesecakes' => ['#ffe9c7', '#bfa14a'],
    'dessert-cakes' => ['#e0e7fa', '#2a3a6d'],
    'tart-cakes' => ['#f7e6d2', '#a67c52'],
    'tea-cakes-travel-cakes' => ['#e6f7e6', '#2e7d32'],
    'baby-shower-cakes' => ['#e3f2fd', '#1976d2'],
    'birthday-cakes' => ['#fff3e0', '#f57c00'],
    'anniversary-cakes' => ['#fce4ec', '#ad1457'],
    'engagement-wedding-cakes' => ['#ede7f6', '#512da8'],
    'brownies' => ['#efebe9', '#4e342e'],
    'cookies' => ['#fffde7', '#fbc02d'],
    'chocolates' => ['#fbe9e7', '#6d4c41'],
    'mini-tarts' => ['#e1f5fe', '#0288d1'],
    'dessert-tubs' => ['#f3e5f5', '#8e24aa'],
    'gifting' => ['#f9fbe7', '#689f38'],
    'gift-hampers' => ['#f9fbe7', '#689f38'],
    'platters' => ['#e0f2f1', '#00897b'],
    'courses' => ['#fffde7', '#fbc02d'],
    'events' => ['#e1f5fe', '#0288d1'],
    // fallback
    'default' => ['#f8d8de', '#6d1b3b'],
];

// ...existing code...
// (The rest of the script is the same as before, but when generating the SVG, use $categoryColors[$category] or $categoryColors['default'] for background/accent)
