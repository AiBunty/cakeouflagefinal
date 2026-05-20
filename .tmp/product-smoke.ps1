$ErrorActionPreference = "Stop"
$base = "https://cakeouflage.com"
$resp = Invoke-RestMethod -Uri "$base/api/catalog/products?limit=500" -Method GET
$items = @($resp.data.items)
$results = [ordered]@{
  totalProducts = $items.Count
  pdpOk = 0
  pdpFail = @()
  legacyPdpOk = 0
  legacyPdpFail = @()
  imageOk = 0
  imageFail = @()
  categoryCookiesImageMissing = @()
  categoryCookiesBrokenImage = @()
}

foreach ($it in $items) {
  $slug = [string]$it.slug
  if ([string]::IsNullOrWhiteSpace($slug)) { continue }

  $pdpUrl = "$base/product/$slug"
  try {
    $p = Invoke-WebRequest -Uri $pdpUrl -Method GET -MaximumRedirection 0 -ErrorAction Stop
    if ([int]$p.StatusCode -eq 200) { $results.pdpOk++ }
    elseif ($results.pdpFail.Count -lt 20) { $results.pdpFail += "${slug}:status=$([int]$p.StatusCode)" }
  } catch {
    if ($_.Exception.Response) {
      if ($results.pdpFail.Count -lt 20) { $results.pdpFail += "${slug}:status=$([int]$_.Exception.Response.StatusCode)" }
    } elseif ($results.pdpFail.Count -lt 20) {
      $results.pdpFail += "${slug}:err=$($_.Exception.Message)"
    }
  }

  $legacyUrl = "$base/Cakeouflage-E-commerce/product/$slug"
  try {
    $lp = Invoke-WebRequest -Uri $legacyUrl -Method GET -MaximumRedirection 0 -ErrorAction Stop
    if ([int]$lp.StatusCode -eq 200) { $results.legacyPdpOk++ }
    elseif ($results.legacyPdpFail.Count -lt 20) { $results.legacyPdpFail += "${slug}:status=$([int]$lp.StatusCode)" }
  } catch {
    if ($_.Exception.Response) {
      if ($results.legacyPdpFail.Count -lt 20) { $results.legacyPdpFail += "${slug}:status=$([int]$_.Exception.Response.StatusCode)" }
    } elseif ($results.legacyPdpFail.Count -lt 20) {
      $results.legacyPdpFail += "${slug}:err=$($_.Exception.Message)"
    }
  }

  $img = [string]$it.featured_image
  if (-not [string]::IsNullOrWhiteSpace($img)) {
    if ($img -match '^https?://') { $imgUrl = $img }
    elseif ($img.StartsWith('/')) { $imgUrl = "$base$img" }
    else { $imgUrl = "$base/$img" }

    try {
      $ir = Invoke-WebRequest -Uri $imgUrl -Method GET -MaximumRedirection 0 -ErrorAction Stop
      if ([int]$ir.StatusCode -eq 200) { $results.imageOk++ }
      elseif ($results.imageFail.Count -lt 20) { $results.imageFail += "${slug}:$img status=$([int]$ir.StatusCode)" }
    } catch {
      if ($_.Exception.Response) {
        if ($results.imageFail.Count -lt 20) { $results.imageFail += "${slug}:$img status=$([int]$_.Exception.Response.StatusCode)" }
      } elseif ($results.imageFail.Count -lt 20) {
        $results.imageFail += "${slug}:$img err=$($_.Exception.Message)"
      }
    }
  }
}

$cat = Invoke-WebRequest -Uri "$base/category/cookies" -Method GET
$matches = [regex]::Matches($cat.Content, '<img[^>]*class="product-card__image"[^>]*src="([^"]+)"', 'IgnoreCase')
foreach ($m in $matches) {
  $src = $m.Groups[1].Value
  if ([string]::IsNullOrWhiteSpace($src)) {
    if ($results.categoryCookiesImageMissing.Count -lt 20) { $results.categoryCookiesImageMissing += 'blank-src' }
    continue
  }

  if ($src -match '^https?://') { $srcUrl = $src }
  elseif ($src.StartsWith('/')) { $srcUrl = "$base$src" }
  else { $srcUrl = "$base/$src" }

  try {
    $sr = Invoke-WebRequest -Uri $srcUrl -Method GET -MaximumRedirection 0 -ErrorAction Stop
    if ([int]$sr.StatusCode -ne 200 -and $results.categoryCookiesBrokenImage.Count -lt 20) {
      $results.categoryCookiesBrokenImage += "$src status=$([int]$sr.StatusCode)"
    }
  } catch {
    if ($results.categoryCookiesBrokenImage.Count -lt 20) {
      if ($_.Exception.Response) {
        $results.categoryCookiesBrokenImage += "$src status=$([int]$_.Exception.Response.StatusCode)"
      } else {
        $results.categoryCookiesBrokenImage += "$src err=$($_.Exception.Message)"
      }
    }
  }
}

$results | ConvertTo-Json -Depth 6
