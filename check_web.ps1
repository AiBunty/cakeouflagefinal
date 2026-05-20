$baseUrl = "https://cakeouflage.com"
Write-Host "--- Step 1: Fetching products list ---"
$res = Invoke-RestMethod -Uri "$baseUrl/api/catalog/products?limit=5" -Method Get
$products = @()
if ($res.data) { $products = $res.data } elseif ($res.products) { $products = $res.products } else { $products = $res }
$imageUrls = @()
foreach ($p in $products) {
    $name = if ($p.name) { $p.name } else { $p.title }
    $img = if ($p.image) { $p.image } elseif ($p.thumbnail) { $p.thumbnail } else { $p.featured_image }
    Write-Host "Product: $name"
    Write-Host "Image URL: $img"
    if ($img) { $imageUrls += $img }
}

Write-Host "`n--- Step 2: Fetching dark-chocklate product ---"
try {
    $singleRes = Invoke-RestMethod -Uri "$baseUrl/api/catalog/products/dark-chocklate" -Method Get
    $p = if ($singleRes.product) { $singleRes.product } else { $singleRes }
    $feat = if ($p.featured_image) { $p.featured_image } else { $p.image }
    Write-Host "Featured Image: $feat"
    if ($feat) { $imageUrls += $feat }
    
    $gallery = if ($p.images) { $p.images } elseif ($p.gallery) { $p.gallery }
    if ($gallery -and $gallery.Count -gt 0) {
        $firstImg = if ($gallery[0].image) { $gallery[0].image } else { $gallery[0] }
        Write-Host "First Gallery Image: $firstImg"
        if ($firstImg) { $imageUrls += $firstImg }
    }
} catch {
    Write-Host "Failed to fetch dark-chocklate: $($_.Exception.Message)"
}

Write-Host "`n--- Step 3: Checking image URLs ---"
$brokenUrls = @()
foreach ($url in ($imageUrls | Where-Object {$_} | Select-Object -Unique)) {
    try {
        $fullUrl = if ($url -like "http*") { $url } else { "$baseUrl$url" }
        $check = Invoke-WebRequest -Uri $fullUrl -Method Head -ErrorAction Stop
        Write-Host "URL: $fullUrl | Status: $($check.StatusCode)"
    } catch {
        Write-Host "URL: $fullUrl | FAILED: $($_.Exception.Message)"
        $brokenUrls += $fullUrl
    }
}

Write-Host "`n--- Summary ---"
if ($brokenUrls.Count -gt 0) {
    Write-Host "Broken URLs found: $($brokenUrls.Count)"
    $brokenUrls | ForEach-Object { Write-Host "- $_" }
} else {
    Write-Host "No broken URLs found."
}
