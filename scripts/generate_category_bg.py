#!/usr/bin/env python3
"""Generate optimized WebP background images for category hero."""
from PIL import Image, ImageDraw
import os

output_dir = "client/assets/images/category"
os.makedirs(output_dir, exist_ok=True)

# Create desktop version (1920x1080)
# Pastel pink gradient background with subtle heart pattern
img_desktop = Image.new('RGB', (1920, 1080))
draw_desktop = ImageDraw.Draw(img_desktop, 'RGBA')

# Base gradient: soft pink to light blush
for y in range(1080):
    # Interpolate from #FFB3D9 (top) to #FFF0F3 (bottom)
    r = int(255 - (255 - 255) * (y / 1080))
    g = int(179 + (240 - 179) * (y / 1080))
    b = int(217 + (243 - 217) * (y / 1080))
    draw_desktop.line([(0, y), (1920, y)], fill=(r, g, b))

# Add subtle heart shapes
heart_color = (255, 220, 235, 40)  # Semi-transparent white hearts
for i in range(0, 1920, 320):
    for j in range(0, 1080, 360):
        # Draw subtle hearts
        x, y = i + 100, j + 150
        size = 80
        # Heart shape approximation
        draw_desktop.ellipse([x - size//3, y - size//2, x, y - size//4], fill=heart_color)
        draw_desktop.ellipse([x, y - size//2, x + size//3, y - size//4], fill=heart_color)
        draw_desktop.polygon([(x - size//3, y - size//4), (x + size//3, y - size//4), 
                               (x, y + size//3)], fill=heart_color)

img_desktop.save(output_dir + "/hero-bg.webp", 'WEBP', quality=85, method=6)
print(f"✓ Desktop WebP: hero-bg.webp (1920x1080)")

# Create mobile version (750x800)
# Compressed, lighter version optimized for mobile
img_mobile = Image.new('RGB', (750, 800))
draw_mobile = ImageDraw.Draw(img_mobile, 'RGBA')

# Lighter gradient for mobile
for y in range(800):
    r = int(255 - (255 - 255) * (y / 800))
    g = int(195 + (245 - 195) * (y / 800))
    b = int(225 + (250 - 225) * (y / 800))
    draw_mobile.line([(0, y), (750, y)], fill=(r, g, b))

# Fewer, smaller hearts on mobile
heart_color_mobile = (255, 230, 240, 35)
for i in range(0, 750, 250):
    for j in range(0, 800, 300):
        x, y = i + 80, j + 120
        size = 50
        draw_mobile.ellipse([x - size//3, y - size//2, x, y - size//4], fill=heart_color_mobile)
        draw_mobile.ellipse([x, y - size//2, x + size//3, y - size//4], fill=heart_color_mobile)
        draw_mobile.polygon([(x - size//3, y - size//4), (x + size//3, y - size//4), 
                              (x, y + size//3)], fill=heart_color_mobile)

img_mobile.save(output_dir + "/hero-bg-mobile.webp", 'WEBP', quality=78, method=6)
print(f"✓ Mobile WebP: hero-bg-mobile.webp (750x800)")

# Report file sizes
desktop_kb = os.path.getsize(output_dir + "/hero-bg.webp") / 1024
mobile_kb = os.path.getsize(output_dir + "/hero-bg-mobile.webp") / 1024
print(f"\nFile Sizes:")
print(f"  Desktop: {desktop_kb:.1f} KB")
print(f"  Mobile:  {mobile_kb:.1f} KB")
print(f"  Total:   {desktop_kb + mobile_kb:.1f} KB")
