-- ============================================================
-- BFM Clothing - database schema + sample data
-- Import in phpMyAdmin (Import tab) or: mysql -u root < bfm_clothing.sql
-- Requires MySQL 5.7+ / MariaDB 10.3+ (XAMPP default is fine)
-- ============================================================
CREATE DATABASE IF NOT EXISTS `bfm_clothing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bfm_clothing`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS login_attempts, contact_messages, newsletter_subscribers, settings, reviews,
  order_items, orders, coupons, wishlist, cart, addresses, product_variants, product_images,
  products, categories, admins, users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- USERS ----------
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','staff','admin','super_admin') NOT NULL DEFAULT 'customer',
  phone VARCHAR(30) DEFAULT NULL,
  remember_selector CHAR(24) DEFAULT NULL,
  remember_token_hash CHAR(64) DEFAULT NULL,
  remember_expires DATETIME DEFAULT NULL,
  reset_token_hash CHAR(64) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_remember (remember_selector),
  KEY idx_users_reset (reset_token_hash)
) ENGINE=InnoDB;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','manager') NOT NULL DEFAULT 'super_admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB;

CREATE TABLE addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT 'Home',
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line VARCHAR(255) NOT NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(80) NOT NULL,
  country VARCHAR(80) NOT NULL,
  postal_code VARCHAR(20) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_addresses_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- CATALOG ----------
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_active (is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  sku VARCHAR(40) NOT NULL,
  gender ENUM('men','women','unisex') NOT NULL DEFAULT 'unisex',
  short_description VARCHAR(255) DEFAULT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  sale_price DECIMAL(10,2) DEFAULT NULL,
  tags VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  sold_count INT UNSIGNED NOT NULL DEFAULT 0,
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_slug (slug),
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_category (category_id, is_active),
  KEY idx_products_flags (is_active, is_featured, is_new),
  KEY idx_products_sold (sold_count),
  KEY idx_products_price (price),
  FULLTEXT KEY ft_products_search (name, short_description, description, tags),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(190) DEFAULT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_images_product (product_id, is_primary, sort_order),
  CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  size VARCHAR(20) NOT NULL,
  color VARCHAR(40) NOT NULL,
  color_hex CHAR(7) NOT NULL DEFAULT '#111111',
  sku VARCHAR(60) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_variant_combo (product_id, size, color),
  UNIQUE KEY uq_variant_sku (sku),
  KEY idx_variants_stock (stock),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- CART / WISHLIST ----------
-- Guests keep their cart in $_SESSION['cart'] (variant_id => qty).
-- This table stores the cart of logged-in users so it survives logout/login.
CREATE TABLE cart (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_item (user_id, variant_id),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE wishlist (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wishlist_item (user_id, product_id),
  CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- COUPONS / ORDERS ----------
CREATE TABLE coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL,
  min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_discount DECIMAL(10,2) DEFAULT NULL,
  usage_limit INT UNSIGNED DEFAULT NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupons_code (code)
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(24) DEFAULT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  customer_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line VARCHAR(255) NOT NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(80) NOT NULL,
  country VARCHAR(80) NOT NULL,
  postal_code VARCHAR(20) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  coupon_code VARCHAR(40) DEFAULT NULL,
  payment_method ENUM('cod','online') NOT NULL DEFAULT 'cod',
  payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  status ENUM('pending','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  notes VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_number (order_number),
  KEY idx_orders_user (user_id),
  KEY idx_orders_status (status, created_at),
  KEY idx_orders_email (email),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  variant_id INT UNSIGNED DEFAULT NULL,
  product_name VARCHAR(160) NOT NULL,
  sku VARCHAR(60) NOT NULL,
  size VARCHAR(20) NOT NULL,
  color VARCHAR(40) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  KEY idx_items_order (order_id),
  KEY idx_items_product (product_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(120) DEFAULT NULL,
  comment TEXT,
  is_approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_once (product_id, user_id),
  KEY idx_reviews_product (product_id, is_approved),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- MISC ----------
CREATE TABLE newsletter_subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(160) DEFAULT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB;

-- Throttles brute-force logins (customers and admins)
CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope ENUM('user','admin') NOT NULL,
  identifier VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts (scope, identifier, attempted_at),
  KEY idx_attempts_ip (scope, ip_address, attempted_at)
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Admin: admin@bfmclothing.com / Admin@12345  (CHANGE IMMEDIATELY - see README)
INSERT INTO admins (name, email, password_hash, role) VALUES
('Store Admin', 'admin@bfmclothing.com', '$2y$12$hNUH74ujZ087XxyJYxAQqOAn3iicvZVCdJwf96Hq2tWIQl7GzFhUG', 'super_admin');

-- Demo customers: customer@example.com / Customer@123
INSERT INTO users (first_name, last_name, email, password_hash, phone) VALUES
('Alex', 'Morgan', 'customer@example.com', '$2y$12$s1ju8Kse5KxlzzaLVb8XU.0Uq/aiNxOSvxT4cu21Wf4InsBCCEGGG', '+1 555 0100'),
('Sam', 'Rivera', 'sam@example.com', '$2y$12$s1ju8Kse5KxlzzaLVb8XU.0Uq/aiNxOSvxT4cu21Wf4InsBCCEGGG', '+1 555 0101');

INSERT INTO categories (id, name, slug, description, sort_order) VALUES
(1, 'T-Shirts',    't-shirts',    'Everyday tees in heavyweight cotton.', 1),
(2, 'Hoodies',     'hoodies',     'Hoodies and sweatshirts built for layering.', 2),
(3, 'Shirts',      'shirts',      'Relaxed shirts and polos.', 3),
(4, 'Jeans',       'jeans',       'Denim with a modern fit.', 4),
(5, 'Pants',       'pants',       'Cargo, straight and tailored pants.', 5),
(6, 'Jackets',     'jackets',     'Outerwear for every season.', 6),
(7, 'Accessories', 'accessories', 'Caps and finishing touches.', 7);

INSERT INTO products (id, category_id, name, slug, sku, gender, short_description, description, price, sale_price, tags, is_featured, is_new, sold_count) VALUES
(1, 1, 'Essential Oversized T-Shirt', 'essential-oversized-t-shirt', 'BFM-TS-001', 'unisex',
 'Heavyweight 240gsm cotton tee with a boxy, dropped-shoulder fit.',
 'The tee we wear every day. Cut from 240gsm combed cotton with a ribbed collar that holds its shape wash after wash. Boxy fit, dropped shoulders and a slightly cropped length. Pre-washed for a soft hand-feel from the first wear.', 950.00, NULL, 'tee,oversized,cotton,essentials', 1, 1, 214),
(2, 2, 'Premium Cotton Hoodie', 'premium-cotton-hoodie', 'BFM-HD-002', 'unisex',
 '420gsm brushed-back fleece hoodie with a double-layer hood.',
 'A proper heavyweight hoodie. 420gsm brushed-back cotton fleece, a structured double-layer hood, kangaroo pocket and ribbed cuffs and hem. Garment dyed for depth of colour and a lived-in softness.', 2400.00, 1999.00, 'hoodie,fleece,winter', 1, 0, 188),
(3, 3, 'Classic Relaxed Shirt', 'classic-relaxed-shirt', 'BFM-SH-003', 'men',
 'Washed cotton poplin shirt with a relaxed, easy drape.',
 'A shirt that works open over a tee or buttoned to the top. Washed cotton poplin, a soft collar, curved hem and a chest pocket. Relaxed through the body with a slightly longer back.', 1800.00, NULL, 'shirt,poplin,casual', 0, 0, 96),
(4, 6, 'BFM Signature Jacket', 'bfm-signature-jacket', 'BFM-JK-004', 'unisex',
 'Water-resistant coach jacket with a quilted lining and embroidered logo.',
 'Our signature outerwear piece. A water-resistant shell, quilted lining, snap-button front and embroidered BFM mark on the chest. Two welt pockets and an adjustable hem.', 5200.00, 4499.00, 'jacket,outerwear,signature', 1, 1, 74),
(5, 5, 'Essential Cargo Pants', 'essential-cargo-pants', 'BFM-PT-005', 'men',
 'Cotton ripstop cargo pants with a tapered leg.',
 'Utility, refined. Cotton ripstop with a tapered leg, six pockets and an adjustable drawcord ankle. Mid-rise with a zip fly.', 2600.00, NULL, 'cargo,pants,utility', 1, 0, 121),
(6, 4, 'Urban Denim Jeans', 'urban-denim-jeans', 'BFM-JN-006', 'men',
 '12oz stretch denim in a modern straight fit.',
 'Mid-weight 12oz denim with 2% stretch for comfort. Straight leg, mid-rise, five-pocket styling and a branded patch at the back waist.', 3200.00, 2799.00, 'denim,jeans,straight', 0, 0, 143),
(7, 1, 'Minimal Logo Tee', 'minimal-logo-tee', 'BFM-TS-007', 'unisex',
 'Classic-fit tee with a small embroidered BFM chest logo.',
 'Understated and versatile. 200gsm cotton jersey, classic fit, with a small tonal embroidered logo on the left chest.', 850.00, NULL, 'tee,logo,minimal', 0, 1, 167),
(8, 2, 'Premium Sweatshirt', 'premium-sweatshirt', 'BFM-SW-008', 'unisex',
 'Crew-neck sweatshirt in loop-back French terry.',
 'A year-round layer. Loop-back French terry cotton, crew neck, ribbed trims and a relaxed fit. Soft inside, clean outside.', 2200.00, NULL, 'sweatshirt,crewneck,terry', 0, 0, 82),
(9, 3, 'Everyday Polo', 'everyday-polo', 'BFM-PL-009', 'men',
 'Cotton pique polo with a two-button placket.',
 'A polo that does not try too hard. Textured cotton pique, flat knit collar, two-button placket and a regular fit.', 1400.00, 1199.00, 'polo,pique,smart casual', 0, 0, 109),
(10, 2, 'BFM Oversized Hoodie', 'bfm-oversized-hoodie', 'BFM-HD-010', 'unisex',
 'Oversized hoodie with a drop shoulder and back print.',
 'Roomy and relaxed. Heavyweight fleece, drop shoulders, extended sleeves and a large BFM back print. Designed to be worn big.', 2600.00, NULL, 'hoodie,oversized,graphic', 1, 1, 133),
(11, 5, 'Classic Straight Pants', 'classic-straight-pants', 'BFM-PT-011', 'women',
 'High-rise straight pants in a stretch twill.',
 'Clean lines, comfortable all day. Stretch cotton twill, high-rise waist, straight leg with a full-length hem. Side pockets and a hidden zip.', 2400.00, NULL, 'pants,twill,tailored', 0, 0, 91),
(12, 7, 'Essential Cap', 'essential-cap', 'BFM-CP-012', 'unisex',
 'Six-panel cotton twill cap with an adjustable strap.',
 'The finishing touch. Six-panel unstructured cotton twill, curved brim, tonal embroidered logo and a metal-buckle adjustable strap.', 750.00, NULL, 'cap,hat,accessory', 0, 0, 205);

-- Variants: tops (S-XL x Black/White/Sand)
INSERT INTO product_variants (product_id, size, color, color_hex, sku, stock)
SELECT p.id, s.size, c.color, c.hex,
       CONCAT(p.sku, '-', UPPER(LEFT(c.color, 3)), '-', s.size),
       ((p.id * 7 + s.ord * 5 + c.ord * 3) % 28) + 3
FROM products p
JOIN (SELECT 'S' size, 1 ord UNION ALL SELECT 'M', 2 UNION ALL SELECT 'L', 3 UNION ALL SELECT 'XL', 4) s
JOIN (SELECT 'Black' color, '#111111' hex, 1 ord UNION ALL SELECT 'White', '#F5F5F2', 2 UNION ALL SELECT 'Sand', '#CDBFA6', 3) c
WHERE p.sku IN ('BFM-TS-001','BFM-TS-007','BFM-HD-002','BFM-HD-010','BFM-SW-008','BFM-SH-003','BFM-PL-009');

-- Variants: jacket
INSERT INTO product_variants (product_id, size, color, color_hex, sku, stock)
SELECT p.id, s.size, c.color, c.hex,
       CONCAT(p.sku, '-', UPPER(LEFT(c.color, 3)), '-', s.size),
       ((p.id * 7 + s.ord * 5 + c.ord * 3) % 20) + 2
FROM products p
JOIN (SELECT 'S' size, 1 ord UNION ALL SELECT 'M', 2 UNION ALL SELECT 'L', 3 UNION ALL SELECT 'XL', 4) s
JOIN (SELECT 'Black' color, '#111111' hex, 1 ord UNION ALL SELECT 'Olive', '#5B6049', 2) c
WHERE p.sku = 'BFM-JK-004';

-- Variants: pants (waist sizes)
INSERT INTO product_variants (product_id, size, color, color_hex, sku, stock)
SELECT p.id, s.size, c.color, c.hex,
       CONCAT(p.sku, '-', UPPER(LEFT(c.color, 3)), '-', s.size),
       ((p.id * 7 + s.ord * 5 + c.ord * 3) % 22) + 4
FROM products p
JOIN (SELECT '30' size, 1 ord UNION ALL SELECT '32', 2 UNION ALL SELECT '34', 3 UNION ALL SELECT '36', 4) s
JOIN (SELECT 'Black' color, '#111111' hex, 1 ord UNION ALL SELECT 'Khaki', '#B3A47F', 2) c
WHERE p.sku IN ('BFM-PT-005','BFM-PT-011');

-- Variants: jeans
INSERT INTO product_variants (product_id, size, color, color_hex, sku, stock)
SELECT p.id, s.size, c.color, c.hex,
       CONCAT(p.sku, '-', UPPER(LEFT(c.color, 3)), '-', s.size),
       ((p.id * 7 + s.ord * 5 + c.ord * 3) % 18) + 1
FROM products p
JOIN (SELECT '30' size, 1 ord UNION ALL SELECT '32', 2 UNION ALL SELECT '34', 3 UNION ALL SELECT '36', 4) s
JOIN (SELECT 'Indigo' color, '#2B3A55' hex, 1 ord UNION ALL SELECT 'Washed Black', '#2A2A2A', 2) c
WHERE p.sku = 'BFM-JN-006';

-- Variants: cap (one size; Olive is sold out on purpose to demo the out-of-stock state)
INSERT INTO product_variants (product_id, size, color, color_hex, sku, stock) VALUES
(12, 'One Size', 'Black', '#111111', 'BFM-CP-012-BLA-OS', 40),
(12, 'One Size', 'Sand',  '#CDBFA6', 'BFM-CP-012-SAN-OS', 25),
(12, 'One Size', 'Olive', '#5B6049', 'BFM-CP-012-OLI-OS', 0);

-- Product images are optional: products without rows use a generated placeholder.
-- Upload real photos from Admin > Products, or insert rows such as:
-- INSERT INTO product_images (product_id, image_path, alt_text, is_primary) VALUES (1, 'products/tee-1.jpg', 'Essential Oversized T-Shirt', 1);

INSERT INTO reviews (product_id, user_id, rating, title, comment) VALUES
(1, 1, 5, 'Perfect fit', 'Heavy, soft and the oversized cut is spot on.'),
(1, 2, 4, 'Great tee', 'Quality is excellent. Size down if you want it less boxy.'),
(2, 1, 5, 'Best hoodie I own', 'Thick fleece and the hood sits properly. Worth the price.'),
(2, 2, 5, 'Warm and well made', 'Stitching is clean and it has barely shrunk.'),
(3, 1, 4, 'Easy shirt', 'Drapes nicely and goes with everything.'),
(4, 2, 5, 'Looks premium', 'Fits true to size and feels far more expensive than it is.'),
(5, 1, 4, 'Comfortable cargos', 'Great pockets. A little long for me.'),
(6, 2, 5, 'Good denim', 'Stretch is just enough. The wash looks great.'),
(7, 1, 5, 'Clean and simple', 'The small logo is subtle and the cotton is soft.'),
(8, 2, 4, 'Cosy', 'Soft inside and holds its shape.'),
(9, 1, 4, 'Smart enough', 'Nice texture, collar sits flat.'),
(10, 2, 5, 'Big and comfy', 'Exactly the oversized look I wanted.'),
(11, 1, 4, 'Nice cut', 'Flattering high rise. Wish there were more colours.'),
(12, 2, 5, 'Great cap', 'Sits well and the strap is solid.');

UPDATE products p SET
  rating_avg = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1), 0),
  rating_count = (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1);

INSERT INTO coupons (code, type, value, min_order, max_discount, usage_limit, expires_at) VALUES
('WELCOME10', 'percent', 10.00, 0,      50.00, NULL, DATE_ADD(NOW(), INTERVAL 1 YEAR)),
('BFM20',     'percent', 20.00, 100.00, 60.00, 500,  DATE_ADD(NOW(), INTERVAL 6 MONTH)),
('SAVE15',    'fixed',   15.00, 75.00,  NULL,  NULL, DATE_ADD(NOW(), INTERVAL 6 MONTH));

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'BFM Clothing'),
('tagline', 'Modern essentials for everyday confidence.'),
('contact_email', 'hello@bfmclothing.com'),
('contact_phone', '+1 555 010 0200'),
('contact_address', '12 Studio Lane, Kathmandu, Nepal'),
('currency_symbol', '$'),
('shipping_flat_rate', '8.00'),
('free_shipping_threshold', '100.00'),
('instagram_url', 'https://instagram.com/'),
('facebook_url', 'https://facebook.com/'),
('tiktok_url', 'https://tiktok.com/'),
('promo_banner', 'Free shipping on orders over $100');
