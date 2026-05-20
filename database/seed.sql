SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE admin_action_logs;
TRUNCATE TABLE queue_jobs;
TRUNCATE TABLE reminders;
TRUNCATE TABLE automation_rules;
TRUNCATE TABLE communication_logs;
TRUNCATE TABLE communication_queue;
TRUNCATE TABLE whatsapp_template_mappings;
TRUNCATE TABLE whatsapp_template_approval_logs;
TRUNCATE TABLE whatsapp_template_sync_logs;
TRUNCATE TABLE whatsapp_template_buttons;
TRUNCATE TABLE whatsapp_template_variables;
TRUNCATE TABLE whatsapp_template_versions;
TRUNCATE TABLE whatsapp_templates;
TRUNCATE TABLE communication_templates;
TRUNCATE TABLE whatsapp_settings;
TRUNCATE TABLE smtp_settings;
TRUNCATE TABLE password_reset_tokens;
TRUNCATE TABLE auth_rate_limits;
TRUNCATE TABLE customer_tag_map;
TRUNCATE TABLE customer_tags;
TRUNCATE TABLE payment_status_history;
TRUNCATE TABLE payment_proofs;
TRUNCATE TABLE payments;
TRUNCATE TABLE invoice_items;
TRUNCATE TABLE invoices;
TRUNCATE TABLE customer_profiles;
TRUNCATE TABLE b2b_documents;
TRUNCATE TABLE b2b_order_items;
TRUNCATE TABLE b2b_orders;
TRUNCATE TABLE b2b_quote_items;
TRUNCATE TABLE b2b_quotes;
TRUNCATE TABLE b2b_price_lists;
TRUNCATE TABLE b2b_addresses;
TRUNCATE TABLE b2b_accounts;
TRUNCATE TABLE course_batches;
TRUNCATE TABLE courses;
TRUNCATE TABLE event_registrations;
TRUNCATE TABLE events;
TRUNCATE TABLE inquiries;
TRUNCATE TABLE pages;
TRUNCATE TABLE banners;
TRUNCATE TABLE reviews;
TRUNCATE TABLE order_items;
TRUNCATE TABLE orders;
TRUNCATE TABLE wishlist_items;
TRUNCATE TABLE wishlists;
TRUNCATE TABLE cart_items;
TRUNCATE TABLE carts;
TRUNCATE TABLE product_variants;
TRUNCATE TABLE product_images;
TRUNCATE TABLE products;
TRUNCATE TABLE categories;
TRUNCATE TABLE delivery_distance_slabs;
TRUNCATE TABLE delivery_pincodes;
TRUNCATE TABLE delivery_time_slots;
TRUNCATE TABLE coupons;
TRUNCATE TABLE user_addresses;
TRUNCATE TABLE admins;
TRUNCATE TABLE users;
TRUNCATE TABLE settings;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO admins (full_name, email, password_hash, role) VALUES
('Cakeouflage Super Admin', 'admin@cakeouflage.com', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'super_admin');

INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('Retail User Demo', 'customer@cakeouflage.com', '9999990001', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('B2B User Demo', 'b2b@cakeouflage.com', '9999990002', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'b2b_user');

INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('Aarav Joshi', 'aarav.joshi@cakeouflage.demo', '9999990101', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Ishita Kulkarni', 'ishita.kulkarni@cakeouflage.demo', '9999990102', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Rohan Patil', 'rohan.patil@cakeouflage.demo', '9999990103', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Meera Shah', 'meera.shah@cakeouflage.demo', '9999990104', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Nikhil Deshmukh', 'nikhil.deshmukh@cakeouflage.demo', '9999990105', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Pooja Bhave', 'pooja.bhave@cakeouflage.demo', '9999990106', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Karan Naik', 'karan.naik@cakeouflage.demo', '9999990107', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Ananya Damle', 'ananya.damle@cakeouflage.demo', '9999990108', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Soham Wani', 'soham.wani@cakeouflage.demo', '9999990109', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Tanvi More', 'tanvi.more@cakeouflage.demo', '9999990110', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Vivaan Oak', 'vivaan.oak@cakeouflage.demo', '9999990111', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Kriti Sathe', 'kriti.sathe@cakeouflage.demo', '9999990112', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Rahul Tamhane', 'rahul.tamhane@cakeouflage.demo', '9999990113', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Sneha Nene', 'sneha.nene@cakeouflage.demo', '9999990114', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Yash Gadgil', 'yash.gadgil@cakeouflage.demo', '9999990115', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'customer'),
('Corporate Demo Alpha', 'b2b.alpha@cakeouflage.demo', '9999990201', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'b2b_user'),
('Corporate Demo Beta', 'b2b.beta@cakeouflage.demo', '9999990202', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'b2b_user'),
('Corporate Demo Gamma', 'b2b.gamma@cakeouflage.demo', '9999990203', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'b2b_user'),
('Corporate Demo Delta', 'b2b.delta@cakeouflage.demo', '9999990204', '$2y$10$Qmhn6uNsvEtq3sY7NSAWt.AH4JyqWFQL8eSeOL8KaZwXda4OfT2re', 'b2b_user');

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

INSERT INTO delivery_distance_slabs (slab_label, min_km, max_km, delivery_fee, is_available) VALUES
('0-5 km', 0, 5, 60, 1),
('5-10 km', 5, 10, 100, 1),
('10-20 km', 10, 20, 160, 1),
('20-30 km', 20, 30, 260, 1),
('Above 30 km', 30, 99, 0, 0);

INSERT INTO delivery_time_slots (slot_label, start_time, end_time, fulfilment_mode, is_same_day_allowed, sort_order) VALUES
('09:00 AM - 11:00 AM', '09:00:00', '11:00:00', 'both', 0, 1),
('11:00 AM - 01:00 PM', '11:00:00', '13:00:00', 'both', 1, 2),
('01:00 PM - 03:00 PM', '13:00:00', '15:00:00', 'both', 1, 3),
('03:00 PM - 05:00 PM', '15:00:00', '17:00:00', 'both', 1, 4),
('05:00 PM - 07:00 PM', '17:00:00', '19:00:00', 'both', 1, 5),
('07:00 PM - 09:00 PM', '19:00:00', '21:00:00', 'delivery', 0, 6);

INSERT INTO delivery_pincodes (postal_code, area_name, approx_distance_km, is_serviceable, requires_manual_approval) VALUES
('422001', 'Nashik Road', 4.2, 1, 0),
('422002', 'Panchavati', 3.8, 1, 0),
('422003', 'Satpur', 8.4, 1, 0),
('422004', 'Cidco', 6.7, 1, 0),
('422005', 'Gangapur Road', 10.8, 1, 0),
('422006', 'Adgaon', 12.5, 1, 0),
('422007', 'Ambad', 14.2, 1, 0),
('422008', 'Nashik Outskirts', 24.7, 1, 0),
('422009', 'Regional Edge', 32.1, 0, 1);

INSERT INTO banners (title, subtitle, image_url, cta_label, cta_url, placement, sort_order) VALUES
('Handcrafted Celebration Cakes', 'Premium bakery experience in Nashik', '/client/assets/images/home/hero-frame-1.svg', 'Shop Cakes', '/category/cakes', 'home_hero', 1),
('Designer Custom Cake Studio', 'From birthdays to weddings, made to your brief', '/client/assets/images/home/hero-poster.svg', 'Explore Customised', '/category/customised-cakes', 'home_mid', 2),
('Course Admissions Open', 'Beginner to professional cake workshops', '/client/assets/images/home/hero-frame-2.svg', 'View Courses', '/course', 'course_top', 1);

INSERT INTO pages (title, slug, content, seo_title, seo_description) VALUES
('About Cakeouflage', 'about', 'Cakeouflage is a premium artisanal pâtisserie crafting memorable celebration desserts in Nashik.', 'About Cakeouflage', 'Learn about Cakeouflage handcrafted bakery philosophy.'),
('Privacy Policy', 'privacy-policy', 'Privacy policy content managed by admin.', 'Privacy Policy', 'Cakeouflage privacy and data policy.'),
('Terms & Conditions', 'terms', 'Terms content managed by admin.', 'Terms & Conditions', 'Cakeouflage terms and service conditions.'),
('Shipping & Delivery', 'shipping-info', 'Nashik 30 km service radius with delivery and pickup options.', 'Shipping & Delivery', 'Delivery and pickup terms for Cakeouflage orders.');

INSERT INTO courses (title, slug, short_description, description, modules, duration_text, mode, fee_amount, image_url, cta_label, cta_url) VALUES
('Beginner Workshop Program', 'beginner-workshop-program', 'Perfect starting point for first-time bakers.', 'Hands-on sessions focused on sponge chemistry, buttercream control, piping basics and simple festive decorations.', 'Sponge foundations|Buttercream basics|Piping patterns|Simple celebration finish', '2 Days', 'offline', 4200.00, '/assets/images/courses/beginner-workshop.jpg', 'Enroll Now', '/course'),
('Eggless Baking Essentials', 'eggless-baking-essentials', 'Master stable eggless textures and flavor balance.', 'Deep dive into eggless emulsions, rise support systems, shelf life control and premium decoration compatibility.', 'Eggless science|Texture balancing|Frosting stability|Storage protocols', '3 Days', 'offline', 6800.00, '/assets/images/courses/eggless-baking.jpg', 'Book Seat', '/course'),
('Festive Workshop Batches', 'festive-workshop-batches', 'Seasonal showpiece cakes and dessert gifting.', 'Festival-led concepts designed for gifting demand, batch production speed, and premium packaging presentation.', 'Theme planning|Batch production|Decor workflow|Gifting assembly', '3 Days', 'hybrid', 7600.00, '/assets/images/courses/festive-workshops.jpg', 'Enroll Now', '/course'),
('Professional Cake Basics', 'professional-cake-basics-course', 'Structured route for aspiring professionals.', 'A strong foundational curriculum covering costing, scaling recipes, quality controls, production planning and client briefing.', 'Recipe scaling|Costing|Production SOP|Client briefing', '4 Weeks', 'hybrid', 16500.00, '/assets/images/courses/professional-basics.jpg', 'Apply', '/course');

INSERT INTO course_batches (course_id, batch_name, starts_on, ends_on, seats_total, seats_available, fee_amount) VALUES
((SELECT id FROM courses WHERE slug = 'beginner-workshop-program'), 'April Weekend Batch', '2026-04-13', '2026-04-14', 20, 14, 4200.00),
((SELECT id FROM courses WHERE slug = 'eggless-baking-essentials'), 'May Master Batch', '2026-05-05', '2026-05-07', 18, 11, 6800.00),
((SELECT id FROM courses WHERE slug = 'festive-workshop-batches'), 'June Festive Batch', '2026-06-10', '2026-06-12', 22, 17, 7600.00),
((SELECT id FROM courses WHERE slug = 'professional-cake-basics-course'), 'July Pro Cohort', '2026-07-01', '2026-07-28', 16, 9, 16500.00);

INSERT INTO events (
  title, slug, short_description, full_description, banner_image,
  instructor_name, starts_at, ends_at, event_type, event_category,
  event_status, location_text, online_link, capacity, seats_available,
  registration_cta_label, is_published
) VALUES
('Cake Business Pricing Webinar', 'cake-business-pricing-webinar', 'Pricing and margin planning for home bakers and boutique studios.', 'A structured webinar on cost cards, contribution margin, package pricing, and quote strategy for premium celebration cakes.', '/assets/images/events/pricing-webinar.jpg', 'Chef Ansh & Finance Mentor', '2026-05-12 19:00:00', '2026-05-12 20:30:00', 'webinar', 'business', 'scheduled', 'Online', 'https://meet.example.com/cakeouflage-pricing', 120, 87, 'Reserve Webinar Seat', 1),
('Wedding Cake Showcase Evening', 'wedding-cake-showcase-evening', 'Live premium wedding cake showcase and tasting evening.', 'Presentation of signature tier systems, structure techniques, tasting panel, and consultation slots for wedding planners.', '/assets/images/events/wedding-showcase.jpg', 'Chef Ansh', '2026-05-26 17:00:00', '2026-05-26 20:00:00', 'event', 'showcase', 'scheduled', 'Cakeouflage Studio, Nashik', NULL, 60, 34, 'Register for Showcase', 1),
('Festive Dessert Strategy Session', 'festive-dessert-strategy-session', 'Planning festive menu bundles and production calendars.', 'Operational webinar focused on festive demand forecasting, SKU rationalization, and dispatch planning for peak weeks.', '/assets/images/events/festive-strategy.jpg', 'Ops Team Cakeouflage', '2026-06-08 18:30:00', '2026-06-08 19:45:00', 'webinar', 'seasonal', 'scheduled', 'Online', 'https://meet.example.com/festive-strategy', 150, 129, 'Join Session', 1),
('Corporate Gifting Tasting Day', 'corporate-gifting-tasting-day', 'Corporate buyers tasting and packaging preview event.', 'Invite-only tasting and gifting presentation for procurement teams evaluating annual festive and milestone gifting.', '/assets/images/events/corporate-tasting.jpg', 'B2B Team Cakeouflage', '2026-06-20 11:00:00', '2026-06-20 14:00:00', 'event', 'corporate', 'scheduled', 'Nashik Business Park Hall', NULL, 80, 52, 'Request Invitation', 1),
('Professional Track Open House', 'professional-track-open-house', 'Open house for professional baking course admissions.', 'Meet mentors, review curriculum roadmap, and get admission guidance for the Professional Cake Basics track.', '/assets/images/events/prof-open-house.jpg', 'Academic Coordinator', '2026-06-29 16:00:00', '2026-06-29 18:00:00', 'event', 'education', 'scheduled', 'Cakeouflage Studio, Nashik', NULL, 45, 27, 'Book Open House Seat', 1),
('Beginner QnA Live Webinar', 'beginner-qna-live-webinar', 'Live Q&A for first-time workshop participants.', 'Interactive online orientation session covering what to expect in beginner workshop batches and required tools.', '/assets/images/events/beginner-qna.jpg', 'Training Team', '2026-07-05 18:00:00', '2026-07-05 19:00:00', 'webinar', 'education', 'scheduled', 'Online', 'https://meet.example.com/beginner-qna', 100, 71, 'Join Live Q&A', 1);

INSERT INTO event_registrations (event_id, participant_name, participant_email, participant_phone, attendees_count, registration_status, note)
SELECT e.id, u.full_name, u.email, u.phone, 1, 'confirmed', 'Seeded demo registration'
FROM events e
JOIN users u ON u.role = 'customer'
WHERE e.slug IN ('cake-business-pricing-webinar', 'wedding-cake-showcase-evening', 'festive-dessert-strategy-session')
LIMIT 12;

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

INSERT INTO b2b_accounts (user_id, company_name, account_type, gst_number, company_phone, company_email, approval_status, notes)
VALUES
((SELECT id FROM users WHERE email = 'b2b@cakeouflage.com'), 'Nashik Corporate Events LLP', 'corporate_client', '27ABCDE1234F1Z5', '9999901234', 'procurement@nashikevents.com', 'approved', 'Approved for recurring gifting and event orders.');

INSERT INTO b2b_accounts (user_id, company_name, account_type, gst_number, company_phone, company_email, approval_status, notes) VALUES
((SELECT id FROM users WHERE email = 'b2b.alpha@cakeouflage.demo'), 'Suncrest Hospitality Pvt Ltd', 'business_buyer', '27ABCDE2234F1Z4', '9999902231', 'purchase@suncrest.demo', 'approved', 'Priority account for monthly dessert trays.'),
((SELECT id FROM users WHERE email = 'b2b.beta@cakeouflage.demo'), 'Nexa Corporate Gifts LLP', 'corporate_client', '27ABCDE3234F1Z3', '9999902232', 'ops@nexa.demo', 'approved', 'Corporate gifting recurring cycles.'),
((SELECT id FROM users WHERE email = 'b2b.gamma@cakeouflage.demo'), 'SweetStreet Retail Partners', 'reseller', '27ABCDE4234F1Z2', '9999902233', 'owner@sweetstreet.demo', 'pending', 'Pending KYC verification.'),
((SELECT id FROM users WHERE email = 'b2b.delta@cakeouflage.demo'), 'Crumbs & Co Studio', 'cake_shop_owner', '27ABCDE5234F1Z1', '9999902234', 'admin@crumbsco.demo', 'approved', 'Seasonal collaboration orders.');

INSERT INTO b2b_addresses (b2b_account_id, address_type, recipient_name, phone, line1, city, state, postal_code, is_default)
VALUES
((SELECT id FROM b2b_accounts LIMIT 1), 'billing', 'Accounts Team', '9999901234', 'Business Park Tower A', 'Nashik', 'Maharashtra', '422003', 1),
((SELECT id FROM b2b_accounts LIMIT 1), 'shipping', 'Operations Team', '9999901235', 'Warehouse Gate 2', 'Nashik', 'Maharashtra', '422004', 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('catalog.menu.mode', 'mega'),
('catalog.default.sort', 'featured'),
('delivery.radius.km', '30'),
('brand.tagline', 'We bake sweet wonderful happy memories'),
('events.default.cta', 'Register Now'),
('reports.default.period.days', '30'),
('homepage.show.events', '1'),
('celebration_reminder_days_before', '7'),
('celebration_combined_email_on_same_day', '1'),
('payment.instructions.retail', 'Pay via UPI and upload reference for quick verification.')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO orders (
  order_number, user_id, customer_name, customer_email, customer_phone,
  fulfilment_mode, order_status, payment_status, payment_method,
  scheduled_slot, scheduled_slot_label, delivery_postal_code, delivery_distance_km,
  delivery_fee, subtotal, discount_total, tax_total, grand_total, admin_note
) VALUES
('ORD-20260402-001', (SELECT id FROM users WHERE email = 'customer@cakeouflage.com'), 'Retail User Demo', 'customer@cakeouflage.com', '9999990001', 'pickup', 'pending', 'pending', 'upi_manual', '2026-04-05 11:00:00', '11:00 AM - 01:00 PM', NULL, NULL, 0.00, 1499.00, 100.00, 0.00, 1399.00, 'Pending payment confirmation'),
('ORD-20260402-002', (SELECT id FROM users WHERE email = 'aarav.joshi@cakeouflage.demo'), 'Aarav Joshi', 'aarav.joshi@cakeouflage.demo', '9999990101', 'delivery', 'confirmed', 'paid', 'upi_manual', '2026-04-06 15:00:00', '03:00 PM - 05:00 PM', '422003', 8.40, 100.00, 2199.00, 0.00, 0.00, 2299.00, 'Payment verified'),
('ORD-20260402-003', (SELECT id FROM users WHERE email = 'ishita.kulkarni@cakeouflage.demo'), 'Ishita Kulkarni', 'ishita.kulkarni@cakeouflage.demo', '9999990102', 'delivery', 'out_for_delivery', 'paid', 'upi_manual', '2026-04-06 17:00:00', '05:00 PM - 07:00 PM', '422004', 6.70, 80.00, 1899.00, 50.00, 0.00, 1929.00, 'Rider assigned'),
('ORD-20260402-004', (SELECT id FROM users WHERE email = 'rohan.patil@cakeouflage.demo'), 'Rohan Patil', 'rohan.patil@cakeouflage.demo', '9999990103', 'pickup', 'ready_for_pickup', 'pending', 'upi_manual', '2026-04-07 13:00:00', '01:00 PM - 03:00 PM', NULL, NULL, 0.00, 999.00, 0.00, 0.00, 999.00, 'Awaiting pickup'),
('ORD-20260402-005', (SELECT id FROM users WHERE email = 'meera.shah@cakeouflage.demo'), 'Meera Shah', 'meera.shah@cakeouflage.demo', '9999990104', 'delivery', 'cancelled', 'failed', 'upi_manual', '2026-04-08 11:00:00', '11:00 AM - 01:00 PM', '422009', 32.10, 0.00, 1249.00, 0.00, 0.00, 1249.00, 'Out of service radius');

INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note)
SELECT o.id, p.id, pv.id, p.name, pv.variant_label, pv.price, 1, pv.price, 'Seeded demo order item'
FROM orders o
JOIN products p ON p.id = (SELECT id FROM products ORDER BY id LIMIT 1 OFFSET 2)
JOIN product_variants pv ON pv.product_id = p.id AND pv.is_default = 1
WHERE o.order_number = 'ORD-20260402-001'
LIMIT 1;

INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note)
SELECT o.id, p.id, pv.id, p.name, pv.variant_label, pv.price, 1, pv.price, 'Seeded demo order item'
FROM orders o
JOIN products p ON p.id = (SELECT id FROM products ORDER BY id LIMIT 1 OFFSET 5)
JOIN product_variants pv ON pv.product_id = p.id AND pv.is_default = 1
WHERE o.order_number = 'ORD-20260402-002'
LIMIT 1;

INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note)
SELECT o.id, p.id, pv.id, p.name, pv.variant_label, pv.price, 2, pv.price * 2, 'Seeded demo order item'
FROM orders o
JOIN products p ON p.id = (SELECT id FROM products ORDER BY id LIMIT 1 OFFSET 8)
JOIN product_variants pv ON pv.product_id = p.id AND pv.is_default = 1
WHERE o.order_number = 'ORD-20260402-003'
LIMIT 1;

INSERT INTO order_items (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note)
SELECT o.id, p.id, pv.id, p.name, pv.variant_label, pv.price, 1, pv.price, 'Seeded demo order item'
FROM orders o
JOIN products p ON p.id = (SELECT id FROM products ORDER BY id LIMIT 1 OFFSET 12)
JOIN product_variants pv ON pv.product_id = p.id AND pv.is_default = 1
WHERE o.order_number = 'ORD-20260402-004'
LIMIT 1;

INSERT INTO invoices (
  invoice_number, order_id, user_id, customer_type, invoice_status, payment_method,
  subtotal, discount_total, tax_total, grand_total, paid_amount, balance_due,
  due_on, issued_on, internal_note
) VALUES
('INV-20260402-001', (SELECT id FROM orders WHERE order_number = 'ORD-20260402-001'), (SELECT id FROM users WHERE email = 'customer@cakeouflage.com'), 'retail', 'payment_under_verification', 'upi', 1499.00, 100.00, 0.00, 1399.00, 0.00, 1399.00, '2026-04-07', '2026-04-02', 'Awaiting payment verification'),
('INV-20260402-002', (SELECT id FROM orders WHERE order_number = 'ORD-20260402-002'), (SELECT id FROM users WHERE email = 'aarav.joshi@cakeouflage.demo'), 'retail', 'paid', 'upi', 2199.00, 0.00, 0.00, 2299.00, 2299.00, 0.00, '2026-04-06', '2026-04-02', 'Paid and verified'),
('INV-20260402-003', (SELECT id FROM orders WHERE order_number = 'ORD-20260402-003'), (SELECT id FROM users WHERE email = 'ishita.kulkarni@cakeouflage.demo'), 'retail', 'part_paid', 'bank_transfer', 1899.00, 50.00, 0.00, 1929.00, 900.00, 1029.00, '2026-04-09', '2026-04-02', 'Part payment received'),
('INV-20260402-004', (SELECT id FROM orders WHERE order_number = 'ORD-20260402-004'), (SELECT id FROM users WHERE email = 'rohan.patil@cakeouflage.demo'), 'retail', 'overdue', 'upi', 999.00, 0.00, 0.00, 999.00, 0.00, 999.00, '2026-03-30', '2026-03-28', 'Overdue demo case'),
('INV-20260402-005', (SELECT id FROM orders WHERE order_number = 'ORD-20260402-005'), (SELECT id FROM users WHERE email = 'meera.shah@cakeouflage.demo'), 'retail', 'unpaid_rejected', 'upi', 1249.00, 0.00, 0.00, 1249.00, 0.00, 1249.00, '2026-04-04', '2026-04-02', 'Payment proof rejected');

INSERT INTO payments (invoice_id, payment_method, payment_status, amount, payment_reference, note, verified_by_admin_id, verified_at)
VALUES
((SELECT id FROM invoices WHERE invoice_number = 'INV-20260402-001'), 'upi', 'submitted', 1399.00, 'UPIREF001', 'Verification pending', NULL, NULL),
((SELECT id FROM invoices WHERE invoice_number = 'INV-20260402-002'), 'upi', 'verified', 2299.00, 'UPIREF002', 'Verified', (SELECT id FROM admins LIMIT 1), NOW()),
((SELECT id FROM invoices WHERE invoice_number = 'INV-20260402-003'), 'bank_transfer', 'verified', 900.00, 'BANKREF003', 'Part-paid accepted', (SELECT id FROM admins LIMIT 1), NOW()),
((SELECT id FROM invoices WHERE invoice_number = 'INV-20260402-005'), 'upi', 'rejected', 1249.00, 'UPIREF005', 'Screenshot invalid', (SELECT id FROM admins LIMIT 1), NOW());

INSERT INTO communication_templates (channel, event_key, subject, body_template, is_active) VALUES
('email', 'online_order_received_customer', 'Order Received - {{order_number}}', '<div style="background:#f5eef2;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#80001F;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Online Order Received</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">Thank you for your order with Cakeouflage. We have received it and our team is preparing your celebration.</p><div style="margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Total:</strong> &#8377;{{grand_total}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">We will keep you posted as your order moves forward.</div></div><div style="background:#140b0f;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'online_order_received_admin', 'New Online Order - {{order_number}}', '<div style="background:#f5eef2;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#80001F;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">New Online Order</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">A new online order has been received and is ready for team review.</p><div style="margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Phone:</strong> {{customer_phone}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Please review fulfilment details and continue the workflow.</div></div><div style="background:#140b0f;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'manual_order_received_customer', 'Manual Order Received - {{order_number}}', '<div style="background:#f5eef2;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#80001F;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Manual Order Received</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">Your order has been recorded by the Cakeouflage team and is now in processing.</p><div style="margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Total:</strong> &#8377;{{grand_total}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">If we need any clarification, we will contact you shortly.</div></div><div style="background:#140b0f;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'manual_order_received_admin', 'New Manual Order - {{order_number}}', '<div style="background:#f5eef2;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#80001F;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Manual Order Alert</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">A manual order has been punched in from admin and needs fulfilment review.</p><div style="margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Phone:</strong> {{customer_phone}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Please verify the order details and continue the workflow.</div></div><div style="background:#140b0f;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'payment_confirmed_customer', 'Payment Confirmed - {{order_number}}', '<div style="background:#eef8f1;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#166534;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Payment Confirmed</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">We have received your payment and your order is now confirmed.</p><div style="margin-top:28px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Paid:</strong> &#8377;{{grand_total}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Our team has started preparing your order.</div></div><div style="background:#052e16;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'payment_confirmed_admin', 'Payment Confirmed - {{order_number}}', '<div style="background:#eef8f1;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#166534;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Payment Confirmed</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">Payment has been confirmed and the order can move into production.</p><div style="margin-top:28px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Customer Email:</strong> {{customer_email}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Please update the operations timeline as needed.</div></div><div style="background:#052e16;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'ready_order_customer', 'Order Ready - {{order_number}}', '<div style="background:#eff6ff;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#1d4ed8;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Order Ready</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">Great news, your Cakeouflage order is now ready.</p><div style="margin-top:28px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Your order is ready for pickup or delivery.</div></div><div style="background:#172554;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'ready_order_admin', 'Order Ready - {{order_number}}', '<div style="background:#eff6ff;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#1d4ed8;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Order Ready</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">The order is ready and the team should coordinate dispatch or pickup.</p><div style="margin-top:28px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Customer Email:</strong> {{customer_email}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Please update the fulfilment status in the admin flow.</div></div><div style="background:#172554;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'order_delivered_customer', 'Order Delivered - {{order_number}}', '<div style="background:#ecfdf5;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#0f766e;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Order Delivered</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">Your Cakeouflage order has been delivered. Thank you for celebrating with us.</p><div style="margin-top:28px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">We hope you loved every bite.</div></div><div style="background:#134e4a;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'order_delivered_admin', 'Order Delivered - {{order_number}}', '<div style="background:#ecfdf5;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#0f766e;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Delivery Alert</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">The order is marked delivered and follow-up tracking can begin.</p><div style="margin-top:28px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Please make any follow-up updates required for operations.</div></div><div style="background:#134e4a;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'reject_order_customer', 'Order Rejected - {{order_number}}', '<div style="background:#fff1f2;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#991b1b;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Order Rejected</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">We could not verify your payment successfully, so the order could not be processed.</p><div style="margin-top:28px;background:#fef2f2;border:1px solid #fecaca;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">If you would like to place the order again, please try again when ready.</div><div style="margin-top:30px;"><a href="https://cakeouflage.com" style="background:#991b1b;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;">Place Order Again</a></div></div><div style="background:#450a0a;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'reject_order_admin', 'Order Rejected - {{order_number}}', '<div style="background:#fff1f2;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#991b1b;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Order Rejected</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">The order was rejected after payment verification and needs admin visibility.</p><div style="margin-top:28px;background:#fef2f2;border:1px solid #fecaca;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Please review the rejection details in the admin workflow.</div></div><div style="background:#450a0a;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'follow_up_review_email', 'We''d Love Your Feedback', '<div style="background:#f5eef2;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#80001F;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">We Value Your Feedback</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">Thank you for choosing Cakeouflage. We would love to hear how your celebration went.</p><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Please take a moment to share your review and help us improve.</div><div style="margin-top:30px;"><a href="{{review_link}}" style="background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;">Leave a Review</a></div></div><div style="background:#140b0f;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'annual_reorder_email', 'It''s Time to Celebrate Again', '<div style="background:#eef8f1;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#166534;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Celebrating One Year With You</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">It has been a year since your celebration with Cakeouflage. We have a special offer waiting for you.</p><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">Thank you for being a valued customer. We look forward to your next celebration.</div><div style="margin-top:30px;"><a href="{{profile_link}}" style="background:#166534;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;">View Your Profile</a></div></div><div style="background:#052e16;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'password_reset', 'Password Reset Request', '<div style="background:#f5eef2;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#80001F;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Password Reset</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">We received a request to reset your password. If this was you, use the secure link below.</p><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">If you did not request this, you can safely ignore this email.</div><div style="margin-top:30px;"><a href="{{reset_link}}" style="background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;">Reset Password</a></div></div><div style="background:#140b0f;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('email', 'invoice_paid', 'Invoice - {{order_number}}', '<div style="background:#ecfdf5;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#0f766e;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="https://i.ibb.co/hRytXC3F/whitelogo.png" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Invoice Paid</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">Thank you for your payment. Your invoice is attached below.</p><div style="margin-top:28px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><div>{{invoice_html}}</div></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">If you need help with billing, please contact Team Cakeouflage.</div></div><div style="background:#134e4a;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>', 1),
('whatsapp', 'order_created', NULL, 'Hi {{customer_name}}, your order {{order_number}} is confirmed.', 1),
('whatsapp', 'order_ready_for_pickup', NULL, 'Your order {{order_number}} is ready for pickup.', 1);

INSERT INTO communication_templates (channel, event_key, subject, body_template, is_active) VALUES
('email', 'birthday_greeting_email', 'Happy Birthday from Cakeouflage', '<p>Hello {{customer_name}},</p><p>Wishing you a very happy birthday from Team Cakeouflage.</p><p>Celebrate with your favorite cake from us.</p><p><a href="https://cakeouflage.com/shop">Order now</a></p>', 1),
('email', 'birthday_preorder_email', 'Your birthday is coming up - plan your cake', '<p>Hello {{customer_name}},</p><p>Your birthday is approaching soon.</p><p>Pre-book your cake to get your preferred slot and design.</p><p><a href="https://cakeouflage.com/shop">Pre-order birthday cake</a></p>', 1),
('email', 'anniversary_greeting_email', 'Happy Anniversary from Cakeouflage', '<p>Hello {{customer_name}},</p><p>Wishing you a wonderful anniversary celebration.</p><p>Make your day sweeter with a Cakeouflage signature cake.</p><p><a href="https://cakeouflage.com/shop">Explore cakes</a></p>', 1),
('email', 'anniversary_preorder_email', 'Your anniversary is near - order cake in advance', '<p>Hello {{customer_name}},</p><p>Your anniversary celebration is coming soon.</p><p>Order in advance for smooth delivery and perfect presentation.</p><p><a href="https://cakeouflage.com/shop">Pre-order anniversary cake</a></p>', 1),
('email', 'celebration_combined_email', 'Special celebration wishes from Cakeouflage', '<p>Hello {{customer_name}},</p><p>Sending warm wishes from Team Cakeouflage for your special celebration.</p><p>We would love to craft your celebration cake.</p><p><a href="https://cakeouflage.com/shop">Plan your cake</a></p>', 1)
ON DUPLICATE KEY UPDATE
subject = VALUES(subject),
body_template = VALUES(body_template),
is_active = VALUES(is_active);

INSERT INTO communication_logs (
  user_id, order_id, invoice_id, channel, event_key, recipient, status, provider_message_id, payload_json, error_message, sent_at
) VALUES
((SELECT id FROM users WHERE email = 'customer@cakeouflage.com'), (SELECT id FROM orders WHERE order_number = 'ORD-20260402-001'), (SELECT id FROM invoices WHERE invoice_number = 'INV-20260402-001'), 'email', 'order_created', 'customer@cakeouflage.com', 'sent', 'MSG-EMAIL-001', JSON_OBJECT('order_number','ORD-20260402-001'), NULL, NOW()),
((SELECT id FROM users WHERE email = 'customer@cakeouflage.com'), (SELECT id FROM orders WHERE order_number = 'ORD-20260402-001'), (SELECT id FROM invoices WHERE invoice_number = 'INV-20260402-001'), 'whatsapp', 'order_created', '+919999990001', 'sent', 'MSG-WA-001', JSON_OBJECT('order_number','ORD-20260402-001'), NULL, NOW()),
((SELECT id FROM users WHERE email = 'rohan.patil@cakeouflage.demo'), (SELECT id FROM orders WHERE order_number = 'ORD-20260402-004'), (SELECT id FROM invoices WHERE invoice_number = 'INV-20260402-004'), 'email', 'payment_overdue', 'rohan.patil@cakeouflage.demo', 'failed', NULL, JSON_OBJECT('invoice_number','INV-20260402-004'), 'Mailbox temporarily unavailable', NULL);


