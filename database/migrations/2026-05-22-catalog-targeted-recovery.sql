-- Catalog-only targeted recovery with helper temp tables
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM product_images;
DELETE FROM product_variants;
DELETE FROM products;
DELETE FROM categories;
SET FOREIGN_KEY_CHECKS=1;

INSERT INTO categories (id, parent_id, name, slug, description, image, banner_image, menu_icon, sort_order, show_in_menu, is_featured, is_active, seo_title, seo_description) VALUES
(1, NULL, 'Cakes', 'cakes', 'Premium cake collections for daily indulgence and celebrations.', '/client/assets/images/categories/1777053436_case-study.png', '/client/assets/images/categories/1777053436_case-study.png', 'cake', 1, 1, 1, 1, 'Cakes | Cakeouflage', 'Browse premium handcrafted cakes in Nashik.'),
(2, NULL, 'Customised Cakes', 'customised-cakes', 'Occasion-led bespoke cake collections.', '/client/assets/images/categories/1777056457_idea1.png', '/client/assets/images/categories/1777056457_idea1.png', 'sparkles', 2, 1, 1, 1, 'Customised Cakes | Cakeouflage', 'Designer and occasion cakes for every milestone.'),
(3, NULL, 'Small Bakes', 'small-bakes', 'Brownies, cookies and mini desserts.', '/client/assets/images/categories/1777056586_idea1.png', '/client/assets/images/categories/1777056586_idea1.png', 'cookie', 3, 1, 0, 1, 'Small Bakes | Cakeouflage', 'Artisanal baked treats and dessert bites.'),
(4, NULL, 'Gifting', 'gifting', 'Curated gifting edits for family and corporate moments.', '/client/assets/images/categories/1777056673_blog.png', '/client/assets/images/categories/1777056673_blog.png', 'gift', 4, 1, 1, 1, 'Gifting | Cakeouflage', 'Luxury gifting hampers and platters.'),
(5, NULL, 'Cake Making Courses', 'cake-making-courses', 'Hands-on baking workshops and certification tracks.', '/client/assets/images/categories/1777056836_blog.png', '/client/assets/images/categories/1777056836_blog.png', 'graduation-cap', 5, 1, 0, 1, 'Cake Courses | Cakeouflage', 'Beginner to professional cake learning programs.'),
(6, NULL, 'B2B / Bulk Orders', 'b2b-bulk-orders', 'Bulk and business ordering for events and teams.', '/client/assets/images/categories/1777053436_case-study.png', '/client/assets/images/categories/1777053436_case-study.png', 'building', 6, 1, 0, 1, 'B2B Bulk Orders | Cakeouflage', 'Corporate and reseller ordering at scale.'),

(10, 1, 'Classic Cakes', 'classic-cakes', 'Evergreen sponge and layered classics.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(11, 1, 'Cheesecakes', 'cheesecakes', 'Creamy baked and chilled cheesecakes.', NULL, NULL, NULL, 2, 1, 1, 1, NULL, NULL),
(12, 1, 'Dessert Cakes', 'dessert-cakes', 'Dessert-inspired pastry cakes.', NULL, NULL, NULL, 3, 1, 1, 1, NULL, NULL),
(13, 1, 'Tart Cakes', 'tart-cakes', 'Crunchy-base tart style cakes.', NULL, NULL, NULL, 4, 1, 0, 1, NULL, NULL),
(14, 1, 'Tea Cakes / Travel Cakes', 'tea-cakes-travel-cakes', 'Travel-safe loaves and tea cakes.', NULL, NULL, NULL, 5, 1, 0, 1, NULL, NULL),
(15, 1, 'Mini Cake Platters', 'mini-cake-platters', 'Mini cake assortments for gatherings.', NULL, NULL, NULL, 6, 1, 0, 1, NULL, NULL),
(16, 1, 'Liquor Cakes', 'liquor-cakes', 'Matured spirit-infused cakes.', NULL, NULL, NULL, 7, 1, 0, 1, NULL, NULL),

(20, 2, 'Birthday Cakes', 'birthday-cakes', 'Birthday-ready premium edits.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(21, 2, 'Anniversary Cakes', 'anniversary-cakes', 'Elegant anniversary themes.', NULL, NULL, NULL, 2, 1, 1, 1, NULL, NULL),
(22, 2, 'Baby Shower Cakes', 'baby-shower-cakes', 'Soft pastel celebration cakes.', NULL, NULL, NULL, 3, 1, 1, 1, NULL, NULL),
(23, 2, 'Engagement / Wedding Cakes', 'engagement-wedding-cakes', 'Luxury engagement and wedding tiers.', NULL, NULL, NULL, 4, 1, 1, 1, NULL, NULL),
(24, 2, 'Pinatas', 'pinatas', 'Smashable surprise pinata cakes.', NULL, NULL, NULL, 5, 1, 0, 1, NULL, NULL),
(25, 2, 'Cake Smash Cakes', 'cake-smash-cakes', 'Safe-texture smash cakes.', NULL, NULL, NULL, 6, 1, 0, 1, NULL, NULL),
(26, 2, 'Bride To Be / Groom To Be', 'bride-to-be-groom-to-be', 'Pre-wedding themed cakes.', NULL, NULL, NULL, 7, 1, 0, 1, NULL, NULL),
(27, 2, 'Congratulatory / Graduation Cakes', 'congratulatory-graduation-cakes', 'Achievement celebration designs.', NULL, NULL, NULL, 8, 1, 0, 1, NULL, NULL),
(28, 2, 'Trending', 'trending', 'Latest social-favorite styles.', NULL, NULL, NULL, 9, 1, 1, 1, NULL, NULL),
(29, 2, 'Graphic Cakes', 'graphic-cakes', 'Printed and graphic-forward cakes.', NULL, NULL, NULL, 10, 1, 0, 1, NULL, NULL),

(30, 3, 'Brownies', 'brownies', 'Fudgy brownie boxes.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(31, 3, 'Cookies', 'cookies', 'Freshly baked premium cookies.', NULL, NULL, NULL, 2, 1, 0, 1, NULL, NULL),
(32, 3, 'Chocolates', 'chocolates', 'Handmade chocolate bites.', NULL, NULL, NULL, 3, 1, 0, 1, NULL, NULL),
(33, 3, 'Mini Tarts', 'mini-tarts', 'Fruit and ganache mini tarts.', NULL, NULL, NULL, 4, 1, 0, 1, NULL, NULL),
(34, 3, 'Dessert Tubs', 'dessert-tubs', 'Layered spoon desserts.', NULL, NULL, NULL, 5, 1, 0, 1, NULL, NULL),

(40, 4, 'Hampers', 'hampers', 'Curated premium hamper boxes.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(41, 4, 'Platters', 'platters', 'Sharing dessert platters.', NULL, NULL, NULL, 2, 1, 0, 1, NULL, NULL),
(42, 4, 'Festive Gifting', 'festive-gifting', 'Festival special gifting edits.', NULL, NULL, NULL, 3, 1, 1, 1, NULL, NULL),
(43, 4, 'Corporate Gifting', 'corporate-gifting', 'Bulk-ready corporate packs.', NULL, NULL, NULL, 4, 1, 1, 1, NULL, NULL),

(50, 5, 'Beginner Workshops', 'beginner-workshops', 'Foundational workshop modules.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(51, 5, 'Eggless Baking', 'eggless-baking', 'Eggless baking science and recipes.', NULL, NULL, NULL, 2, 1, 1, 1, NULL, NULL),
(52, 5, 'Festive Workshops', 'festive-workshops', 'Seasonal cake workshop tracks.', NULL, NULL, NULL, 3, 1, 0, 1, NULL, NULL),
(53, 5, 'Professional Basics', 'professional-basics', 'Production-level foundational program.', NULL, NULL, NULL, 4, 1, 1, 1, NULL, NULL),

(60, 6, 'Corporate Orders', 'corporate-orders', 'Bulk event and employee orders.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(61, 6, 'Reseller / Cake Shop Owners', 'reseller-cake-shop-owners', 'Reseller and wholesale ordering.', NULL, NULL, NULL, 2, 1, 1, 1, NULL, NULL),
(62, 6, 'Bulk Dessert Orders', 'bulk-dessert-orders', 'Dessert trays and high-volume packs.', NULL, NULL, NULL, 3, 1, 1, 1, NULL, NULL),
(63, 6, 'Event Orders', 'event-orders', 'Dessert tables and event bundles.', NULL, NULL, NULL, 4, 1, 1, 1, NULL, NULL),
(64, 6, 'Request Quote', 'request-quote', 'Quote-based custom bulk requirements.', NULL, NULL, NULL, 5, 1, 0, 1, NULL, NULL),

(100, 10, 'Chocolate', 'classic-chocolate', 'Classic chocolate cake edits.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(101, 10, 'Non Chocolate', 'classic-non-chocolate', 'Classic non-chocolate cake edits.', NULL, NULL, NULL, 2, 1, 0, 1, NULL, NULL),
(102, 11, 'Chocolate', 'cheesecakes-chocolate', 'Chocolate cheesecake range.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(103, 11, 'Non Chocolate', 'cheesecakes-non-chocolate', 'Fruit and vanilla cheesecake range.', NULL, NULL, NULL, 2, 1, 0, 1, NULL, NULL),
(104, 12, 'Opera Cakes', 'opera-cakes', 'Layered opera-style cakes.', NULL, NULL, NULL, 1, 1, 0, 1, NULL, NULL),
(105, 12, 'Mousse Cakes', 'mousse-cakes', 'Airy mousse textures.', NULL, NULL, NULL, 2, 1, 1, 1, NULL, NULL),
(106, 12, 'Tiramisu', 'tiramisu', 'Coffee mascarpone dessert cakes.', NULL, NULL, NULL, 3, 1, 0, 1, NULL, NULL),
(107, 14, 'Chocolate', 'tea-cakes-chocolate', 'Chocolate tea and travel cakes.', NULL, NULL, NULL, 1, 1, 0, 1, NULL, NULL),
(108, 14, 'Non Chocolate', 'tea-cakes-non-chocolate', 'Non-chocolate tea and travel cakes.', NULL, NULL, NULL, 2, 1, 0, 1, NULL, NULL),

(200, 20, 'Florals (Chocolate Based)', 'birthday-florals-chocolate', 'Chocolate floral birthday designs.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(201, 20, 'Florals (Non Chocolate Based)', 'birthday-florals-non-chocolate', 'Vanilla and berry floral birthday designs.', NULL, NULL, NULL, 2, 1, 1, 1, NULL, NULL),
(202, 20, 'Non Floral (Chocolate Based)', 'birthday-non-floral-chocolate', 'Modern chocolate birthday themes.', NULL, NULL, NULL, 3, 1, 0, 1, NULL, NULL),
(203, 20, 'Non Floral (Non Chocolate Based)', 'birthday-non-floral-non-chocolate', 'Modern non-chocolate birthday themes.', NULL, NULL, NULL, 4, 1, 0, 1, NULL, NULL),
(204, 20, 'Themed Cakes (Kids)', 'birthday-themed-kids', 'Kids character and playful themes.', NULL, NULL, NULL, 5, 1, 1, 1, NULL, NULL),
(205, 20, 'Themed Cakes (Adults)', 'birthday-themed-adults', 'Adult milestone and minimal themes.', NULL, NULL, NULL, 6, 1, 0, 1, NULL, NULL),
(206, 20, 'Half Birthday Cakes', 'birthday-half-birthday', 'Half birthday mini format edits.', NULL, NULL, NULL, 7, 1, 0, 1, NULL, NULL),
(207, 20, 'Dual / Triple Cakes', 'birthday-dual-triple', 'Split-flavor dual and triple concepts.', NULL, NULL, NULL, 8, 1, 0, 1, NULL, NULL),
(208, 20, 'Top Forward Cakes', 'birthday-top-forward', 'Top-forward sculptural presentation.', NULL, NULL, NULL, 9, 1, 0, 1, NULL, NULL),
(209, 20, 'Rotating Tier Cakes', 'birthday-rotating-tier', 'Rotating stand premium builds.', NULL, NULL, NULL, 10, 1, 0, 1, NULL, NULL),

(210, 21, 'Floral (Chocolate Based)', 'anniversary-floral-chocolate', 'Chocolate floral anniversary cakes.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(211, 21, 'Floral (Non Chocolate Based)', 'anniversary-floral-non-chocolate', 'Floral non-chocolate anniversary cakes.', NULL, NULL, NULL, 2, 1, 1, 1, NULL, NULL),
(212, 21, 'Non Floral (Chocolate Based)', 'anniversary-non-floral-chocolate', 'Minimal chocolate anniversary cakes.', NULL, NULL, NULL, 3, 1, 0, 1, NULL, NULL),
(213, 21, 'Non Floral (Non Chocolate Based)', 'anniversary-non-floral-non-chocolate', 'Minimal non-chocolate anniversary cakes.', NULL, NULL, NULL, 4, 1, 0, 1, NULL, NULL),

(220, 23, 'Chandelier Cakes', 'wedding-chandelier-cakes', 'Crystal and chandelier inspired tiers.', NULL, NULL, NULL, 1, 1, 1, 1, NULL, NULL),
(221, 23, 'Grand Wedding Cakes', 'wedding-grand-cakes', 'Grand scale wedding centerpieces.', NULL, NULL, NULL, 2, 1, 1, 1, NULL, NULL),
(222, 23, 'Acrylic Tier / Spacer Cakes', 'wedding-acrylic-tier-spacer', 'Acrylic spacer and floating tier builds.', NULL, NULL, NULL, 3, 1, 0, 1, NULL, NULL),
(223, 23, 'Classic Tiered', 'wedding-classic-tiered', 'Classic tiered wedding designs.', NULL, NULL, NULL, 4, 1, 1, 1, NULL, NULL),
(224, 23, 'Deconstructed Tiered', 'wedding-deconstructed-tiered', 'Modern separated-tier concepts.', NULL, NULL, NULL, 5, 1, 0, 1, NULL, NULL);

DROP TEMPORARY TABLE IF EXISTS seed_plan;

CREATE TEMPORARY TABLE seed_plan (
  leaf_slug VARCHAR(180) NOT NULL,
  item_count INT NOT NULL,
  title_prefix VARCHAR(140) NOT NULL,
  slug_prefix VARCHAR(160) NOT NULL,
  dietary_tag ENUM('regular','eggless','vegan','sugar_free') NOT NULL DEFAULT 'regular',
  occasion_tag VARCHAR(120) NULL,
  is_b2b_enabled TINYINT(1) NOT NULL DEFAULT 0
);

INSERT INTO seed_plan (leaf_slug, item_count, title_prefix, slug_prefix, dietary_tag, occasion_tag, is_b2b_enabled) VALUES
('classic-chocolate', 12, 'Belgian Velvet Hazelnut Gateau', 'belgian-velvet-hazelnut-gateau', 'regular', NULL, 0),
('classic-non-chocolate', 10, 'Madagascar Vanilla Bloom Cake', 'madagascar-vanilla-bloom-cake', 'eggless', NULL, 0),
('cheesecakes-chocolate', 8, 'Cocoa Silk Cheesecake', 'cocoa-silk-cheesecake', 'regular', NULL, 0),
('cheesecakes-non-chocolate', 8, 'Berry Cloud Cheesecake', 'berry-cloud-cheesecake', 'eggless', NULL, 0),
('opera-cakes', 4, 'Opera Noir Layer Cake', 'opera-noir-layer-cake', 'regular', NULL, 0),
('mousse-cakes', 6, 'Mousse Eclipse Cake', 'mousse-eclipse-cake', 'regular', NULL, 0),
('tiramisu', 4, 'Mascarpone Tiramisu Indulgence', 'mascarpone-tiramisu-indulgence', 'regular', NULL, 0),
('tart-cakes', 6, 'Citrus Cacao Tart Cake', 'citrus-cacao-tart-cake', 'regular', NULL, 0),
('tea-cakes-chocolate', 5, 'Chocolate Travel Loaf Luxe', 'chocolate-travel-loaf-luxe', 'regular', NULL, 0),
('tea-cakes-non-chocolate', 5, 'Honey Almond Travel Loaf', 'honey-almond-travel-loaf', 'eggless', NULL, 0),
('mini-cake-platters', 6, 'Mini Celebration Platter', 'mini-celebration-platter', 'regular', NULL, 0),
('liquor-cakes', 5, 'Reserve Barrel Infusion Cake', 'reserve-barrel-infusion-cake', 'regular', NULL, 0),

('birthday-florals-chocolate', 3, 'Floral Cocoa Birthday Cake', 'floral-cocoa-birthday-cake', 'regular', 'birthday', 0),
('birthday-florals-non-chocolate', 3, 'Floral Vanilla Birthday Cake', 'floral-vanilla-birthday-cake', 'eggless', 'birthday', 0),
('birthday-non-floral-chocolate', 3, 'Modern Cocoa Birthday Cake', 'modern-cocoa-birthday-cake', 'regular', 'birthday', 0),
('birthday-non-floral-non-chocolate', 3, 'Modern Vanilla Birthday Cake', 'modern-vanilla-birthday-cake', 'eggless', 'birthday', 0),
('birthday-themed-kids', 3, 'Kids Theme Party Cake', 'kids-theme-party-cake', 'regular', 'birthday', 0),
('birthday-themed-adults', 3, 'Adult Theme Signature Cake', 'adult-theme-signature-cake', 'regular', 'birthday', 0),
('birthday-half-birthday', 3, 'Half Birthday Petite Cake', 'half-birthday-petite-cake', 'regular', 'birthday', 0),
('birthday-dual-triple', 3, 'Dual Triple Flavor Cake', 'dual-triple-flavor-cake', 'regular', 'birthday', 0),
('birthday-top-forward', 3, 'Top Forward Accent Cake', 'top-forward-accent-cake', 'regular', 'birthday', 0),
('birthday-rotating-tier', 3, 'Rotating Tier Luxe Cake', 'rotating-tier-luxe-cake', 'regular', 'birthday', 0),

('anniversary-floral-chocolate', 3, 'Anniversary Floral Cocoa Cake', 'anniversary-floral-cocoa-cake', 'regular', 'anniversary', 0),
('anniversary-floral-non-chocolate', 3, 'Anniversary Floral Vanilla Cake', 'anniversary-floral-vanilla-cake', 'eggless', 'anniversary', 0),
('anniversary-non-floral-chocolate', 3, 'Anniversary Cocoa Minimal Cake', 'anniversary-cocoa-minimal-cake', 'regular', 'anniversary', 0),
('anniversary-non-floral-non-chocolate', 3, 'Anniversary Vanilla Minimal Cake', 'anniversary-vanilla-minimal-cake', 'eggless', 'anniversary', 0),

('baby-shower-cakes', 10, 'Moonbeam Baby Shower Cake', 'moonbeam-baby-shower-cake', 'regular', 'baby_shower', 0),

('wedding-chandelier-cakes', 3, 'Chandelier Wedding Luxe Cake', 'chandelier-wedding-luxe-cake', 'regular', 'wedding', 0),
('wedding-grand-cakes', 3, 'Grand Wedding Statement Cake', 'grand-wedding-statement-cake', 'regular', 'wedding', 0),
('wedding-acrylic-tier-spacer', 3, 'Acrylic Spacer Tier Cake', 'acrylic-spacer-tier-cake', 'regular', 'wedding', 0),
('wedding-classic-tiered', 3, 'Classic Tier Wedding Cake', 'classic-tier-wedding-cake', 'regular', 'wedding', 0),
('wedding-deconstructed-tiered', 3, 'Deconstructed Tier Wedding Cake', 'deconstructed-tier-wedding-cake', 'regular', 'wedding', 0),

('pinatas', 5, 'Pinata Surprise Smash Cake', 'pinata-surprise-smash-cake', 'regular', 'themed', 0),
('cake-smash-cakes', 5, 'Cake Smash Portrait Cake', 'cake-smash-portrait-cake', 'regular', 'themed', 0),
('bride-to-be-groom-to-be', 4, 'Bride Groom Party Cake', 'bride-groom-party-cake', 'regular', 'wedding', 0),
('congratulatory-graduation-cakes', 4, 'Graduation Glory Cake', 'graduation-glory-cake', 'regular', 'graduation', 0),
('trending', 6, 'Trending Artisan Cake', 'trending-artisan-cake', 'regular', NULL, 0),
('graphic-cakes', 6, 'Graphic Statement Cake', 'graphic-statement-cake', 'regular', NULL, 0),

('brownies', 6, 'Cocoa Fudge Brownie Box', 'cocoa-fudge-brownie-box', 'vegan', NULL, 0),
('cookies', 6, 'Buttercrisp Cookie Jar', 'buttercrisp-cookie-jar', 'regular', NULL, 0),
('chocolates', 6, 'Artisan Chocolate Collection', 'artisan-chocolate-collection', 'regular', NULL, 0),
('mini-tarts', 5, 'Mini Tart Indulgence Box', 'mini-tart-indulgence-box', 'regular', NULL, 0),
('dessert-tubs', 5, 'Dessert Tub Delight', 'dessert-tub-delight', 'eggless', NULL, 0),

('hampers', 6, 'Mini Dessert Indulgence Hamper', 'mini-dessert-indulgence-hamper', 'regular', 'gifting', 1),
('platters', 4, 'Celebration Dessert Platter', 'celebration-dessert-platter', 'regular', 'gifting', 1),
('festive-gifting', 4, 'Festive Luxe Gifting Box', 'festive-luxe-gifting-box', 'regular', 'gifting', 1),
('corporate-gifting', 4, 'Corporate Signature Gifting Box', 'corporate-signature-gifting-box', 'regular', 'gifting', 1),

('beginner-workshops', 2, 'Workshop Seat Package', 'workshop-seat-package', 'regular', 'course', 0),
('eggless-baking', 2, 'Eggless Masterclass Seat', 'eggless-masterclass-seat', 'regular', 'course', 0),
('festive-workshops', 2, 'Festive Workshop Seat', 'festive-workshop-seat', 'regular', 'course', 0),
('professional-basics', 2, 'Professional Basics Seat', 'professional-basics-seat', 'regular', 'course', 0),

('corporate-orders', 3, 'Corporate Bulk Cake Pack', 'corporate-bulk-cake-pack', 'regular', 'b2b', 1),
('reseller-cake-shop-owners', 3, 'Reseller Stock Cake Pack', 'reseller-stock-cake-pack', 'regular', 'b2b', 1),
('bulk-dessert-orders', 3, 'Bulk Dessert Service Pack', 'bulk-dessert-service-pack', 'regular', 'b2b', 1),
('event-orders', 3, 'Event Dessert Table Pack', 'event-dessert-table-pack', 'regular', 'b2b', 1),
('request-quote', 2, 'Custom Quote Product Set', 'custom-quote-product-set', 'regular', 'b2b', 1);

SET @sku = 100000;

DROP TEMPORARY TABLE IF EXISTS seq;

CREATE TEMPORARY TABLE seq (
  n INT NOT NULL PRIMARY KEY
);

INSERT INTO seq (n) VALUES
(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),
(11),(12),(13),(14),(15),(16),(17),(18),(19),(20);

INSERT INTO products (
  name, slug, short_description, long_description,
  flavour_notes, texture_notes, ingredients_summary, packaging_note, topper_note,
  sku, collection_category_id, subcategory_id, child_category_id,
  occasion_tag, dietary_tag, availability_status, lead_time_hours, customisation_note,
  delivery_eligible, pickup_eligible, featured_image, starting_price, base_price, discount_price,
  stock_quantity, is_featured, is_bestseller, seo_title, seo_description, is_b2b_enabled, b2b_minimum_quantity
)
SELECT
  CONCAT(sp.title_prefix, ' ', LPAD(seq.n, 2, '0')) AS name,
  CONCAT(sp.slug_prefix, '-', LPAD(seq.n, 2, '0')) AS slug,
  CONCAT('Premium ', lc.name, ' selection with signature finish and balanced sweetness.') AS short_description,
  CONCAT(
    'This handcrafted creation is prepared in small batches using premium couverture, fresh dairy, and house-made components to maintain a refined finish and clean flavor profile. ',
    'Each order is layered, rested, and finished by our pastry team for stable transport and elegant presentation. ',
    'The profile is designed for celebrations where visual detail and reliable texture both matter. ',
    'You can share preferred color palette, message plaque copy, and topper requirements at checkout. ',
    'Lead time and slot selection rules are applied before final confirmation for fresh production scheduling.'
  ) AS long_description,
  'Balanced sweetness with cocoa depth and aromatic dairy notes.' AS flavour_notes,
  'Soft sponge structure with smooth frosting and stable slicing texture.' AS texture_notes,
  'Premium flour, cultured dairy, couverture chocolate, cane sugar, natural flavors.' AS ingredients_summary,
  'Delivered in rigid premium box with food-safe support board and care card.' AS packaging_note,
  'Custom topper text supported where design format permits.' AS topper_note,
  CONCAT('CK', LPAD((@sku := @sku + 1), 8, '0')) AS sku,
  CASE WHEN p2.id IS NOT NULL THEN p2.id WHEN p1.id IS NOT NULL THEN p1.id ELSE lc.id END AS collection_category_id,
  CASE WHEN p2.id IS NOT NULL THEN p1.id WHEN p1.id IS NOT NULL THEN lc.id ELSE NULL END AS subcategory_id,
  CASE WHEN p2.id IS NOT NULL THEN lc.id ELSE NULL END AS child_category_id,
  sp.occasion_tag,
  sp.dietary_tag,
  'in_stock' AS availability_status,
  CASE
    WHEN lc.slug LIKE 'wedding-%' THEN 72
    WHEN lc.slug LIKE 'birthday-%' OR lc.slug LIKE 'anniversary-%' OR lc.slug LIKE 'baby-shower-%' THEN 36
    WHEN lc.slug IN ('request-quote', 'event-orders', 'corporate-orders') THEN 48
    ELSE 24
  END AS lead_time_hours,
  'Please include preferred flavor notes, color palette, writing text, and topper request while placing the order.' AS customisation_note,
  1,
  1,
  CONCAT('/assets/images/products/', lc.slug, '/', sp.slug_prefix, '-', LPAD(seq.n, 2, '0'), '-01.jpg') AS featured_image,
  ROUND(
    CASE
      WHEN lc.slug LIKE 'wedding-%' OR lc.slug LIKE 'corporate-%' THEN 1699 + (seq.n * 70)
      WHEN lc.slug LIKE 'birthday-%' OR lc.slug LIKE 'anniversary-%' THEN 1199 + (seq.n * 55)
      WHEN lc.slug IN ('hampers', 'platters', 'festive-gifting', 'corporate-gifting') THEN 1499 + (seq.n * 60)
      WHEN lc.slug IN ('brownies','cookies','chocolates','mini-tarts','dessert-tubs') THEN 549 + (seq.n * 30)
      ELSE 899 + (seq.n * 45)
    END, 2
  ) AS starting_price,
  ROUND(
    CASE
      WHEN lc.slug LIKE 'wedding-%' OR lc.slug LIKE 'corporate-%' THEN 1699 + (seq.n * 70)
      WHEN lc.slug LIKE 'birthday-%' OR lc.slug LIKE 'anniversary-%' THEN 1199 + (seq.n * 55)
      WHEN lc.slug IN ('hampers', 'platters', 'festive-gifting', 'corporate-gifting') THEN 1499 + (seq.n * 60)
      WHEN lc.slug IN ('brownies','cookies','chocolates','mini-tarts','dessert-tubs') THEN 549 + (seq.n * 30)
      ELSE 899 + (seq.n * 45)
    END, 2
  ) AS base_price,
  ROUND(
    CASE
      WHEN lc.slug LIKE 'wedding-%' OR lc.slug LIKE 'corporate-%' THEN 1599 + (seq.n * 66)
      WHEN lc.slug LIKE 'birthday-%' OR lc.slug LIKE 'anniversary-%' THEN 1129 + (seq.n * 52)
      WHEN lc.slug IN ('hampers', 'platters', 'festive-gifting', 'corporate-gifting') THEN 1419 + (seq.n * 57)
      WHEN lc.slug IN ('brownies','cookies','chocolates','mini-tarts','dessert-tubs') THEN 519 + (seq.n * 28)
      ELSE 849 + (seq.n * 42)
    END, 2
  ) AS discount_price,
  60,
  IF(seq.n % 6 = 0, 1, 0) AS is_featured,
  IF(seq.n % 5 = 0, 1, 0) AS is_bestseller,
  CONCAT(sp.title_prefix, ' | Cakeouflage') AS seo_title,
  CONCAT('Order ', sp.title_prefix, ' with delivery or pickup in Nashik. Variant pricing and custom notes supported.') AS seo_description,
  sp.is_b2b_enabled,
  CASE WHEN sp.is_b2b_enabled = 1 THEN 10 ELSE NULL END AS b2b_minimum_quantity
FROM seed_plan sp
JOIN categories lc ON lc.slug = sp.leaf_slug
LEFT JOIN categories p1 ON p1.id = lc.parent_id
LEFT JOIN categories p2 ON p2.id = p1.parent_id
JOIN seq ON seq.n <= sp.item_count;

INSERT INTO product_images (product_id, image_url, alt_text, sort_order)
SELECT p.id, CONCAT('/assets/images/products/', lc.slug, '/', p.slug, '-01.jpg'), CONCAT(p.name, ' hero image'), 1
FROM products p
JOIN categories lc ON lc.id = COALESCE(p.child_category_id, p.subcategory_id, p.collection_category_id);

INSERT INTO product_images (product_id, image_url, alt_text, sort_order)
SELECT p.id, CONCAT('/assets/images/products/', lc.slug, '/', p.slug, '-02.jpg'), CONCAT(p.name, ' gallery image'), 2
FROM products p
JOIN categories lc ON lc.id = COALESCE(p.child_category_id, p.subcategory_id, p.collection_category_id);

INSERT INTO product_images (product_id, image_url, alt_text, sort_order)
SELECT p.id, CONCAT('/assets/images/products/', lc.slug, '/', p.slug, '-03.jpg'), CONCAT(p.name, ' detail image'), 3
FROM products p
JOIN categories lc ON lc.id = COALESCE(p.child_category_id, p.subcategory_id, p.collection_category_id);

DROP TEMPORARY TABLE IF EXISTS variant_template;

CREATE TEMPORARY TABLE variant_template (
  variant_label VARCHAR(40),
  weight_or_size VARCHAR(40),
  multiplier DECIMAL(8,3)
);

INSERT INTO variant_template (variant_label, weight_or_size, multiplier) VALUES
('1 lb', '1 lb', 1.00),
('1.5 lb', '1.5 lb', 1.28),
('2 lb', '2 lb', 1.56),
('2.5 lb', '2.5 lb', 1.86),
('3 lb', '3 lb', 2.18),
('4 lb', '4 lb', 2.72);

INSERT INTO product_variants (
  product_id, variant_label, weight_or_size, flavor, price, discount_price,
  stock_quantity, sku_suffix, is_default, is_active
)
SELECT
  p.id,
  vt.variant_label,
  vt.weight_or_size,
  'Signature',
  ROUND(p.base_price * vt.multiplier, 2),
  ROUND(COALESCE(p.discount_price, p.base_price) * vt.multiplier, 2),
  24,
  REPLACE(vt.weight_or_size, ' ', ''),
  IF(vt.variant_label = '1 lb', 1, 0),
  1
FROM products p
CROSS JOIN variant_template vt;
