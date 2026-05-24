SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS crm_push_logs;
DROP TABLE IF EXISTS media_assets;
DROP TABLE IF EXISTS admin_permissions;
DROP TABLE IF EXISTS admin_action_logs;
DROP TABLE IF EXISTS queue_jobs;
DROP TABLE IF EXISTS reminders;
DROP TABLE IF EXISTS automation_rules;
DROP TABLE IF EXISTS communication_logs;
DROP TABLE IF EXISTS communication_queue;
DROP TABLE IF EXISTS whatsapp_template_mappings;
DROP TABLE IF EXISTS whatsapp_template_approval_logs;
DROP TABLE IF EXISTS whatsapp_template_sync_logs;
DROP TABLE IF EXISTS whatsapp_template_buttons;
DROP TABLE IF EXISTS whatsapp_template_variables;
DROP TABLE IF EXISTS whatsapp_template_versions;
DROP TABLE IF EXISTS whatsapp_templates;
DROP TABLE IF EXISTS communication_templates;
DROP TABLE IF EXISTS whatsapp_settings;
DROP TABLE IF EXISTS smtp_settings;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS auth_rate_limits;
DROP TABLE IF EXISTS customer_tag_map;
DROP TABLE IF EXISTS customer_tags;
DROP TABLE IF EXISTS payment_status_history;
DROP TABLE IF EXISTS bank_alert_utrs;
DROP TABLE IF EXISTS payment_proofs;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS customer_profiles;
DROP TABLE IF EXISTS b2b_documents;
DROP TABLE IF EXISTS b2b_order_items;
DROP TABLE IF EXISTS b2b_orders;
DROP TABLE IF EXISTS b2b_quote_items;
DROP TABLE IF EXISTS b2b_quotes;
DROP TABLE IF EXISTS b2b_price_lists;
DROP TABLE IF EXISTS b2b_addresses;
DROP TABLE IF EXISTS b2b_accounts;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS wishlist_items;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS product_variants;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS category_occasion_map;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS user_addresses;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS course_batches;
DROP TABLE IF EXISTS event_registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS inquiries;
DROP TABLE IF EXISTS delivery_distance_slabs;
DROP TABLE IF EXISTS delivery_pincodes;
DROP TABLE IF EXISTS delivery_time_slots;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(25) NOT NULL,
  phone_e164 VARCHAR(20) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','b2b_user') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_users_email (email),
  INDEX idx_users_phone_e164 (phone_e164)
) ENGINE=InnoDB;

CREATE TABLE user_addresses (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(60) NULL,
  recipient_name VARCHAR(120) NOT NULL,
  phone VARCHAR(25) NOT NULL,
  line1 VARCHAR(190) NOT NULL,
  line2 VARCHAR(190) NULL,
  landmark VARCHAR(190) NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NOT NULL,
  postal_code VARCHAR(15) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_addresses_user_id (user_id),
  INDEX idx_user_addresses_postal (postal_code)
) ENGINE=InnoDB;

CREATE TABLE admins (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','sales_manager','ops_manager','content_manager') NOT NULL DEFAULT 'admin',
  department_label VARCHAR(40) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admin_email (email)
) ENGINE=InnoDB;

CREATE TABLE admin_permissions (
  admin_id BIGINT UNSIGNED NOT NULL,
  permission_key VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (admin_id, permission_key),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(150) NOT NULL UNIQUE,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  banner_image VARCHAR(255) NULL,
  menu_icon VARCHAR(80) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  show_in_menu TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  seo_title VARCHAR(190) NULL,
  seo_description VARCHAR(260) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_categories_parent_id (parent_id),
  INDEX idx_categories_slug (slug),
  INDEX idx_categories_menu (show_in_menu, is_active)
) ENGINE=InnoDB;

CREATE TABLE products (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  short_description VARCHAR(280) NOT NULL,
  long_description TEXT NOT NULL,
  flavour_notes TEXT NULL,
  texture_notes TEXT NULL,
  ingredients_summary TEXT NULL,
  packaging_note TEXT NULL,
  topper_note TEXT NULL,
  sku VARCHAR(80) NOT NULL UNIQUE,
  collection_category_id BIGINT UNSIGNED NOT NULL,
  subcategory_id BIGINT UNSIGNED NULL,
  child_category_id BIGINT UNSIGNED NULL,
  occasion_tag VARCHAR(100) NULL,
  dietary_tag ENUM('regular','eggless','vegan','sugar_free') NOT NULL DEFAULT 'regular',
  is_veg TINYINT(1) NOT NULL DEFAULT 1,
  is_eggless TINYINT(1) NOT NULL DEFAULT 0,
  is_vegan TINYINT(1) NOT NULL DEFAULT 0,
  availability_status ENUM('in_stock','out_of_stock','preorder','draft') NOT NULL DEFAULT 'in_stock',
  lead_time_hours INT NOT NULL DEFAULT 24,
  customisation_note TEXT NULL,
  delivery_eligible TINYINT(1) NOT NULL DEFAULT 1,
  pickup_eligible TINYINT(1) NOT NULL DEFAULT 1,
  featured_image VARCHAR(255) NULL,
  starting_price DECIMAL(10,2) NOT NULL,
  base_price DECIMAL(10,2) NOT NULL,
  discount_price DECIMAL(10,2) NULL,
  stock_quantity INT NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
  is_chef_special TINYINT(1) NOT NULL DEFAULT 0,
  seo_title VARCHAR(190) NULL,
  seo_description VARCHAR(260) NULL,
  rating_average DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  review_count INT NOT NULL DEFAULT 0,
  is_b2b_enabled TINYINT(1) NOT NULL DEFAULT 0,
  b2b_minimum_quantity INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  FOREIGN KEY (collection_category_id) REFERENCES categories(id),
  FOREIGN KEY (subcategory_id) REFERENCES categories(id),
  FOREIGN KEY (child_category_id) REFERENCES categories(id),
  INDEX idx_products_slug (slug),
  INDEX idx_products_sku (sku),
  INDEX idx_products_collection (collection_category_id),
  INDEX idx_products_subcategory (subcategory_id),
  INDEX idx_products_child_category (child_category_id),
  INDEX idx_products_occasion (occasion_tag),
  INDEX idx_products_dietary (dietary_tag),
  INDEX idx_products_is_veg (is_veg),
  INDEX idx_products_is_eggless (is_eggless),
  INDEX idx_products_is_vegan (is_vegan),
  INDEX idx_products_active_filter (availability_status, deleted_at, collection_category_id),
  INDEX idx_products_featured (is_featured),
  INDEX idx_products_bestseller (is_bestseller)
) ENGINE=InnoDB;

CREATE TABLE product_images (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  alt_text VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_product_images_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE product_variants (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_label VARCHAR(80) NOT NULL,
  weight_or_size VARCHAR(50) NOT NULL,
  flavor VARCHAR(80) NULL,
  price DECIMAL(10,2) NOT NULL,
  discount_price DECIMAL(10,2) NULL,
  stock_quantity INT NOT NULL DEFAULT 0,
  sku_suffix VARCHAR(40) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_product_variants_product (product_id),
  INDEX idx_product_variants_filter_price (product_id, is_active, price, discount_price)
) ENGINE=InnoDB;

CREATE TABLE carts (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  session_id VARCHAR(128) NULL,
  currency_code CHAR(3) NOT NULL DEFAULT 'INR',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_carts_user (user_id),
  INDEX idx_carts_session (session_id)
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (variant_id) REFERENCES product_variants(id),
  INDEX idx_cart_items_cart (cart_id)
) ENGINE=InnoDB;

CREATE TABLE wishlists (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_wishlist_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE wishlist_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  wishlist_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_wishlist_product (wishlist_id, product_id)
) ENGINE=InnoDB;

CREATE TABLE coupons (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_type ENUM('flat','percentage') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  max_discount DECIMAL(10,2) NULL,
  min_order_amount DECIMAL(10,2) NULL,
  usage_limit INT NULL,
  per_user_usage_limit INT NULL,
  usage_count INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NOT NULL,
  target_mode ENUM('all_users','specific_users') NOT NULL DEFAULT 'all_users',
  auto_apply TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=auto-apply at cart; 0=manual entry only',
  applicable_to VARCHAR(64) NOT NULL DEFAULT 'online' COMMENT 'Comma-delimited modules: online, manual, byoc',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE coupon_target_users (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  coupon_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupon_target_user (coupon_id, user_id),
  INDEX idx_coupon_target_user (user_id),
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE coupon_redemptions (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  coupon_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  code_snapshot VARCHAR(50) NOT NULL,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupon_order (coupon_id, order_id),
  INDEX idx_coupon_redemption_user (coupon_id, user_id),
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE orders (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(25) NOT NULL,
  customer_phone_e164 VARCHAR(20) NULL,
  fulfilment_mode ENUM('delivery','pickup','custom_delivery') NOT NULL,
  order_status ENUM('pending','confirmed','in_preparation','out_for_delivery','ready_for_pickup','completed','cancelled') NOT NULL DEFAULT 'pending',
  payment_status ENUM('pending','paid','failed','refunded','credit','partial','under_review','confirmed','rejected') NOT NULL DEFAULT 'pending',
  payment_method ENUM('upi_manual','cod','gateway','credit') NOT NULL DEFAULT 'upi_manual',
  order_source ENUM('retail','byoc_quote') NOT NULL DEFAULT 'retail',
  order_mode ENUM('ready_pos','scheduled_custom','online','byoc') NULL COMMENT 'POS mode tag',
  byoc_quote_id BIGINT UNSIGNED NULL,
  scheduled_slot DATETIME NULL,
  scheduled_slot_label VARCHAR(120) NULL,
  slot_id BIGINT UNSIGNED NULL COMMENT 'FK to order_slots.id — denormalized',
  requires_kitchen_production TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 for ready_pos',
  production_status ENUM('not_required','pending','in_production','decoration_pending','ready','packed','out_for_delivery','delivered') NOT NULL DEFAULT 'pending' COMMENT 'Kitchen lifecycle',
  delivery_postal_code VARCHAR(15) NULL,
  delivery_distance_km DECIMAL(5,2) NULL,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(10,2) NOT NULL,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(10,2) NOT NULL,
  admin_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_number (order_number),
  INDEX idx_orders_email (customer_email),
  INDEX idx_orders_status (order_status),
  INDEX idx_orders_source (order_source),
  INDEX idx_orders_phone_e164 (customer_phone_e164),
  UNIQUE KEY uq_orders_byoc_quote (byoc_quote_id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  product_name_snapshot VARCHAR(180) NOT NULL,
  variant_snapshot VARCHAR(80) NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  customisation_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (variant_id) REFERENCES product_variants(id),
  INDEX idx_order_items_order (order_id)
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(150) NULL,
  review_text TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reviews_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE banners (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  subtitle VARCHAR(260) NULL,
  image_url VARCHAR(255) NULL,
  cta_label VARCHAR(80) NULL,
  cta_url VARCHAR(190) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  linked_coupon_id BIGINT UNSIGNED NULL,
  page_scope ENUM('all_pages','exclude_checkout_auth') NOT NULL DEFAULT 'all_pages',
  placement ENUM('home_hero','home_mid','site_top_offer','home_top_offer','shop_top','course_top','b2b_top') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_banners_offer_active_window (placement, is_active, starts_at, ends_at),
  INDEX idx_banners_linked_coupon (linked_coupon_id),
  FOREIGN KEY (linked_coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE pages (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  content LONGTEXT NOT NULL,
  seo_title VARCHAR(190) NULL,
  seo_description VARCHAR(260) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pages_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE courses (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  short_description VARCHAR(260) NOT NULL,
  description LONGTEXT NOT NULL,
  modules LONGTEXT NULL,
  duration_text VARCHAR(120) NULL,
  mode ENUM('online','offline','hybrid') NOT NULL DEFAULT 'offline',
  fee_amount DECIMAL(10,2) NOT NULL,
  image_url VARCHAR(255) NULL,
  cta_label VARCHAR(80) NULL,
  cta_url VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE course_batches (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  course_id BIGINT UNSIGNED NOT NULL,
  batch_name VARCHAR(120) NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NULL,
  seats_total INT NOT NULL,
  seats_available INT NOT NULL,
  fee_amount DECIMAL(10,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE events (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  short_description VARCHAR(280) NOT NULL,
  full_description LONGTEXT NOT NULL,
  banner_image VARCHAR(255) NULL,
  instructor_name VARCHAR(140) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  event_type ENUM('webinar','event') NOT NULL DEFAULT 'event',
  event_category VARCHAR(120) NULL,
  event_status ENUM('draft','scheduled','live','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  location_text VARCHAR(190) NULL,
  online_link VARCHAR(255) NULL,
  capacity INT NOT NULL DEFAULT 30,
  seats_available INT NOT NULL DEFAULT 30,
  registration_cta_label VARCHAR(80) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_events_type (event_type),
  INDEX idx_events_status (event_status),
  INDEX idx_events_start (starts_at)
) ENGINE=InnoDB;

CREATE TABLE event_registrations (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  participant_name VARCHAR(120) NOT NULL,
  participant_email VARCHAR(190) NOT NULL,
  participant_phone VARCHAR(25) NOT NULL,
  attendees_count INT NOT NULL DEFAULT 1,
  registration_status ENUM('new','confirmed','cancelled') NOT NULL DEFAULT 'new',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_event_registrations_event (event_id),
  INDEX idx_event_registrations_status (registration_status)
) ENGINE=InnoDB;

CREATE TABLE inquiries (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  inquiry_type ENUM('custom_cake','contact','course','event','b2b_registration','quote_request') NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(25) NOT NULL,
  message TEXT NULL,
  reference_file VARCHAR(255) NULL,
  status ENUM('new','in_review','closed') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE byoc_quotes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  inquiry_id BIGINT UNSIGNED NOT NULL,
  quote_number VARCHAR(50) NOT NULL UNIQUE,
  quote_subject VARCHAR(180) NOT NULL,
  quote_message TEXT NULL,
  quote_amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  status ENUM('sent','accepted','expired','cancelled') NOT NULL DEFAULT 'sent',
  expires_at DATETIME NULL,
  accepted_at DATETIME NULL,
  order_id BIGINT UNSIGNED NULL,
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_byoc_quotes_inquiry (inquiry_id),
  INDEX idx_byoc_quotes_status (status)
) ENGINE=InnoDB;

CREATE TABLE byoc_quote_links (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  byoc_quote_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(120) NOT NULL UNIQUE,
  expires_at DATETIME NULL,
  used_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_byoc_quote_links_quote (byoc_quote_id)
) ENGINE=InnoDB;

ALTER TABLE orders
  ADD CONSTRAINT fk_orders_byoc_quote
  FOREIGN KEY (byoc_quote_id) REFERENCES byoc_quotes(id) ON DELETE SET NULL;

CREATE TABLE delivery_distance_slabs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slab_label VARCHAR(60) NOT NULL,
  min_km DECIMAL(5,2) NOT NULL,
  max_km DECIMAL(5,2) NOT NULL,
  delivery_fee DECIMAL(10,2) NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE delivery_pincodes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  postal_code VARCHAR(15) NOT NULL UNIQUE,
  area_name VARCHAR(120) NOT NULL,
  approx_distance_km DECIMAL(5,2) NOT NULL,
  is_serviceable TINYINT(1) NOT NULL DEFAULT 1,
  requires_manual_approval TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE delivery_time_slots (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slot_label VARCHAR(80) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  fulfilment_mode ENUM('delivery','pickup','both') NOT NULL DEFAULT 'both',
  is_same_day_allowed TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE settings (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value LONGTEXT NULL,
  updated_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE b2b_accounts (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  company_name VARCHAR(180) NOT NULL,
  account_type ENUM('corporate_client','business_buyer','reseller','cake_shop_owner') NOT NULL,
  gst_number VARCHAR(40) NULL,
  company_phone VARCHAR(25) NOT NULL,
  company_email VARCHAR(190) NOT NULL,
  approval_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  credit_limit DECIMAL(12,2) NULL,
  assigned_admin_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_b2b_status (approval_status)
) ENGINE=InnoDB;

CREATE TABLE b2b_addresses (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  b2b_account_id BIGINT UNSIGNED NOT NULL,
  address_type ENUM('billing','shipping') NOT NULL,
  recipient_name VARCHAR(120) NOT NULL,
  phone VARCHAR(25) NOT NULL,
  line1 VARCHAR(190) NOT NULL,
  line2 VARCHAR(190) NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NOT NULL,
  postal_code VARCHAR(15) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (b2b_account_id) REFERENCES b2b_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE b2b_price_lists (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  b2b_account_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  min_quantity INT NOT NULL DEFAULT 1,
  wholesale_price DECIMAL(10,2) NOT NULL,
  retail_price_snapshot DECIMAL(10,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (b2b_account_id) REFERENCES b2b_accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE b2b_quotes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  quote_number VARCHAR(40) NOT NULL UNIQUE,
  b2b_account_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(120) NULL,
  fulfilment_mode ENUM('delivery','pickup') NOT NULL,
  scheduled_date DATE NULL,
  scheduled_slot_id BIGINT UNSIGNED NULL,
  status ENUM('requested','drafted','sent','accepted','rejected','converted_to_order') NOT NULL DEFAULT 'requested',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  admin_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (b2b_account_id) REFERENCES b2b_accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (scheduled_slot_id) REFERENCES delivery_time_slots(id) ON DELETE SET NULL,
  INDEX idx_b2b_quotes_number (quote_number)
) ENGINE=InnoDB;

CREATE TABLE b2b_quote_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  quote_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  customisation_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quote_id) REFERENCES b2b_quotes(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (variant_id) REFERENCES product_variants(id)
) ENGINE=InnoDB;

CREATE TABLE b2b_orders (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  b2b_account_id BIGINT UNSIGNED NOT NULL,
  source_quote_id BIGINT UNSIGNED NULL,
  fulfilment_mode ENUM('delivery','pickup') NOT NULL,
  order_status ENUM('pending','confirmed','in_production','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  payment_status ENUM('pending','paid','part_paid','failed') NOT NULL DEFAULT 'pending',
  subtotal DECIMAL(10,2) NOT NULL,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(10,2) NOT NULL,
  internal_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (b2b_account_id) REFERENCES b2b_accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (source_quote_id) REFERENCES b2b_quotes(id) ON DELETE SET NULL,
  INDEX idx_b2b_orders_number (order_number)
) ENGINE=InnoDB;

CREATE TABLE b2b_order_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  b2b_order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  customisation_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (b2b_order_id) REFERENCES b2b_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (variant_id) REFERENCES product_variants(id)
) ENGINE=InnoDB;

CREATE TABLE b2b_documents (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  b2b_account_id BIGINT UNSIGNED NOT NULL,
  document_type ENUM('gst_certificate','trade_license','purchase_order','invoice','other') NOT NULL,
  file_url VARCHAR(255) NOT NULL,
  uploaded_by ENUM('b2b_user','admin') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (b2b_account_id) REFERENCES b2b_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE customer_profiles (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  date_of_birth DATE NULL,
  anniversary_date DATE NULL,
  celebration_date DATE NULL,
  internal_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_customer_profiles_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE customer_tags (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  tag_name VARCHAR(80) NOT NULL,
  tag_slug VARCHAR(90) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customer_tag_map (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES customer_tags(id) ON DELETE CASCADE,
  UNIQUE KEY uq_customer_tag_map (user_id, tag_id)
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  invoice_number VARCHAR(40) NOT NULL UNIQUE,
  order_id BIGINT UNSIGNED NULL,
  b2b_order_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  b2b_account_id BIGINT UNSIGNED NULL,
  customer_type ENUM('retail','b2b') NOT NULL DEFAULT 'retail',
  invoice_status ENUM('draft','pending_payment','part_paid','paid','overdue','payment_under_verification','unpaid_rejected','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
  payment_method ENUM('upi','bank_transfer','cash','pos_card','payment_link') NOT NULL DEFAULT 'upi',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_on DATE NULL,
  issued_on DATE NULL,
  internal_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  FOREIGN KEY (b2b_order_id) REFERENCES b2b_orders(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (b2b_account_id) REFERENCES b2b_accounts(id) ON DELETE SET NULL,
  INDEX idx_invoices_number (invoice_number),
  INDEX idx_invoices_status (invoice_status),
  INDEX idx_invoices_due_on (due_on)
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  item_label VARCHAR(190) NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  INDEX idx_invoice_items_invoice (invoice_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  payment_method ENUM('upi','bank_transfer','cash','pos_card','payment_link') NOT NULL,
  payment_status ENUM('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
  amount DECIMAL(12,2) NOT NULL,
  payment_reference VARCHAR(120) NULL,
  note TEXT NULL,
  verified_by_admin_id BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_payments_invoice (invoice_id),
  INDEX idx_payments_status (payment_status)
) ENGINE=InnoDB;

CREATE TABLE payment_proofs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  file_url VARCHAR(255) NOT NULL,
  uploaded_by ENUM('customer','b2b_user','admin') NOT NULL DEFAULT 'customer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payment_status_history (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(60) NULL,
  to_status VARCHAR(60) NOT NULL,
  changed_by_admin_id BIGINT UNSIGNED NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_payment_history_invoice (invoice_id)
) ENGINE=InnoDB;

CREATE TABLE bank_alert_utrs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source ENUM('apps_script','customer_submit','admin_manual') NOT NULL DEFAULT 'apps_script',
  parsed_utr VARCHAR(40) NOT NULL,
  parsed_amount DECIMAL(12,2) NULL,
  bank_sender VARCHAR(190) NULL,
  email_subject VARCHAR(255) NULL,
  alert_message TEXT NULL,
  event_time DATETIME NULL,
  status ENUM('pending','matched_auto','confirmed','rejected','duplicate','ignored') NOT NULL DEFAULT 'pending',
  match_confidence ENUM('none','weak','strong') NOT NULL DEFAULT 'none',
  customer_user_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  invoice_id BIGINT UNSIGNED NULL,
  payment_id BIGINT UNSIGNED NULL,
  confirm_note TEXT NULL,
  confirmed_by_admin_id BIGINT UNSIGNED NULL,
  confirmed_at DATETIME NULL,
  raw_payload_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bank_alert_utr (parsed_utr),
  INDEX idx_bank_alert_status (status),
  INDEX idx_bank_alert_order (order_id),
  INDEX idx_bank_alert_created (created_at),
  FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
  FOREIGN KEY (confirmed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE smtp_settings (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  host VARCHAR(190) NULL,
  port INT NULL,
  username VARCHAR(190) NULL,
  password_encrypted TEXT NULL,
  encryption ENUM('none','ssl','tls') NOT NULL DEFAULT 'tls',
  from_name VARCHAR(120) NULL,
  from_email VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  updated_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE whatsapp_settings (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  provider_name VARCHAR(120) NULL,
  app_id VARCHAR(190) NULL,
  app_secret_encrypted TEXT NULL,
  api_base_url VARCHAR(255) NULL,
  api_key_encrypted TEXT NULL,
  access_token_encrypted TEXT NULL,
  phone_number_id VARCHAR(120) NULL,
  business_account_id VARCHAR(120) NULL,
  webhook_callback_url VARCHAR(255) NULL,
  webhook_verify_token VARCHAR(120) NULL,
  default_language_code VARCHAR(12) NULL,
  default_category VARCHAR(40) NULL,
  namespace_reference VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  updated_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE whatsapp_templates (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  internal_name VARCHAR(180) NOT NULL,
  template_key VARCHAR(120) NOT NULL UNIQUE,
  meta_template_name VARCHAR(180) NOT NULL,
  meta_template_id_or_reference VARCHAR(190) NULL,
  waba_id VARCHAR(120) NULL,
  phone_number_id VARCHAR(120) NULL,
  category ENUM('utility','marketing','authentication') NOT NULL DEFAULT 'utility',
  language_code VARCHAR(12) NOT NULL DEFAULT 'en_US',
  header_type ENUM('none','text','image','video','document') NOT NULL DEFAULT 'none',
  header_text VARCHAR(240) NULL,
  header_media_example VARCHAR(255) NULL,
  body_text LONGTEXT NOT NULL,
  footer_text VARCHAR(180) NULL,
  buttons_json JSON NULL,
  variables_json JSON NULL,
  approval_status ENUM('draft','ready_to_submit','submitted','in_review','approved','rejected','paused','disabled','archived') NOT NULL DEFAULT 'draft',
  approval_reason VARCHAR(260) NULL,
  sync_status ENUM('local_only','pending_sync','synced','sync_failed') NOT NULL DEFAULT 'local_only',
  last_synced_at DATETIME NULL,
  mapped_event_key VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_wa_template_status (approval_status),
  INDEX idx_wa_template_event (mapped_event_key),
  INDEX idx_wa_template_meta_name (meta_template_name)
) ENGINE=InnoDB;

CREATE TABLE whatsapp_template_versions (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_id BIGINT UNSIGNED NOT NULL,
  version_number INT NOT NULL,
  snapshot_json JSON NOT NULL,
  change_note VARCHAR(260) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  UNIQUE KEY uq_wa_template_version (template_id, version_number)
) ENGINE=InnoDB;

CREATE TABLE whatsapp_template_variables (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_id BIGINT UNSIGNED NOT NULL,
  variable_key VARCHAR(120) NOT NULL,
  variable_label VARCHAR(120) NOT NULL,
  component_scope ENUM('header','body','footer','button') NOT NULL DEFAULT 'body',
  parameter_order INT NOT NULL,
  fallback_value VARCHAR(180) NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE CASCADE,
  INDEX idx_wa_template_variable_template (template_id)
) ENGINE=InnoDB;

CREATE TABLE whatsapp_template_buttons (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_id BIGINT UNSIGNED NOT NULL,
  button_type ENUM('quick_reply','url','phone') NOT NULL,
  button_text VARCHAR(60) NOT NULL,
  button_value VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE CASCADE,
  INDEX idx_wa_template_buttons_template (template_id)
) ENGINE=InnoDB;

CREATE TABLE whatsapp_template_sync_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_id BIGINT UNSIGNED NULL,
  sync_direction ENUM('push_to_meta','pull_from_meta') NOT NULL,
  status ENUM('success','failed','partial') NOT NULL,
  request_payload_json JSON NULL,
  response_payload_json JSON NULL,
  message VARCHAR(260) NULL,
  synced_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL,
  FOREIGN KEY (synced_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_wa_sync_logs_template (template_id)
) ENGINE=InnoDB;

CREATE TABLE whatsapp_template_approval_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  meta_reason VARCHAR(260) NULL,
  response_payload_json JSON NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_wa_approval_logs_template (template_id)
) ENGINE=InnoDB;

CREATE TABLE whatsapp_template_mappings (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  event_key VARCHAR(120) NOT NULL UNIQUE,
  template_id BIGINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE communication_templates (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  channel ENUM('email','whatsapp') NOT NULL,
  event_key VARCHAR(80) NOT NULL,
  subject VARCHAR(190) NULL,
  body_template LONGTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_comm_template (channel, event_key)
) ENGINE=InnoDB;

CREATE TABLE communication_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  b2b_account_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  invoice_id BIGINT UNSIGNED NULL,
  whatsapp_template_id BIGINT UNSIGNED NULL,
  channel ENUM('email','whatsapp','internal') NOT NULL,
  event_key VARCHAR(80) NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  provider_message_id VARCHAR(190) NULL,
  error_message VARCHAR(260) NULL,
  payload_json JSON NULL,
  retry_count INT NOT NULL DEFAULT 0,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (b2b_account_id) REFERENCES b2b_accounts(id) ON DELETE SET NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
  FOREIGN KEY (whatsapp_template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL,
  INDEX idx_comm_logs_status (status),
  INDEX idx_comm_logs_channel (channel),
  INDEX idx_comm_logs_event (event_key)
) ENGINE=InnoDB;

CREATE TABLE crm_push_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  mobile VARCHAR(25) NOT NULL,
  trigger_key VARCHAR(80) NULL,
  endpoint VARCHAR(512) NULL,
  status ENUM('success','fail') NOT NULL,
  automation_id VARCHAR(80) NULL,
  api_token_masked VARCHAR(50) NULL,
  payload_json LONGTEXT NULL,
  http_status SMALLINT NULL,
  execution_status VARCHAR(40) NULL,
  error_message TEXT NULL,
  response_time_ms INT UNSIGNED NULL,
  response TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_crm_push_logs_created (created_at),
  INDEX idx_crm_push_logs_status (status),
  INDEX idx_crm_push_logs_trigger (trigger_key),
  INDEX idx_crm_push_logs_exec_status (execution_status)
) ENGINE=InnoDB;

CREATE TABLE communication_queue (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  communication_log_id BIGINT UNSIGNED NULL,
  channel ENUM('email','whatsapp','internal') NOT NULL,
  queue_status ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  payload_json JSON NULL,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(260) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (communication_log_id) REFERENCES communication_logs(id) ON DELETE SET NULL,
  INDEX idx_comm_queue_status (queue_status, available_at)
) ENGINE=InnoDB;

CREATE TABLE automation_rules (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  rule_key VARCHAR(80) NOT NULL UNIQUE,
  channel ENUM('email','whatsapp','internal') NOT NULL,
  trigger_event VARCHAR(80) NOT NULL,
  template_id BIGINT UNSIGNED NULL,
  offset_days INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES communication_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reminders (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  b2b_account_id BIGINT UNSIGNED NULL,
  reminder_type ENUM('payment_due','birthday','follow_up','production') NOT NULL,
  title VARCHAR(180) NOT NULL,
  reminder_on DATETIME NOT NULL,
  status ENUM('pending','done','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (b2b_account_id) REFERENCES b2b_accounts(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_reminders_when (reminder_on),
  INDEX idx_reminders_status (status)
) ENGINE=InnoDB;

CREATE TABLE queue_jobs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  job_type VARCHAR(80) NOT NULL,
  payload_json JSON NULL,
  status ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(260) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_queue_status (status, available_at)
) ENGINE=InnoDB;

CREATE TABLE media_assets (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  original_path VARCHAR(255) NOT NULL,
  canonical_path VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  media_type ENUM('image','video') NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  conversion_status ENUM('ready','queued','processing','failed') NOT NULL DEFAULT 'ready',
  conversion_error VARCHAR(260) NULL,
  version_token VARCHAR(40) NOT NULL,
  uploaded_by_admin_id BIGINT UNSIGNED NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_assets_canonical (canonical_path),
  INDEX idx_media_assets_original (original_path),
  INDEX idx_media_assets_status (conversion_status, updated_at),
  FOREIGN KEY (uploaded_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE auth_rate_limits (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  scope_key VARCHAR(80) NOT NULL,
  bucket_key VARCHAR(190) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  blocked_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_rate_limit (scope_key, bucket_key)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_password_reset_email (email),
  INDEX idx_password_reset_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE admin_action_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(120) NOT NULL,
  target_type VARCHAR(120) NOT NULL,
  target_id BIGINT NULL,
  entity_type VARCHAR(120) NULL,
  entity_id VARCHAR(60) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- OTP verification tokens for admin two-factor login flow.
-- Also created at runtime by auth.php bootstrap, but defined here so fresh
-- Docker setups work before the first successful login completes.
CREATE TABLE IF NOT EXISTS otp_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  otp VARCHAR(12) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_otp_verifications_email (email),
  KEY idx_otp_verifications_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
