-- ============================================================
--  E-COMMERCE DATABASE SCHEMA
--  Compatible with: MySQL 8.0+
--  Created for: Full-stack PHP/MySQL E-Commerce Website
-- ============================================================

CREATE DATABASE IF NOT EXISTS artha_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE artha_db;

-- ============================================================
-- 1. ROLES
-- ============================================================
CREATE TABLE roles (
  role_id     TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_name   VARCHAR(50)  NOT NULL UNIQUE,   -- 'admin', 'customer', 'vendor'
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (role_name) VALUES ('admin'), ('customer'), ('vendor');


-- ============================================================
-- 2. USERS
-- ============================================================
CREATE TABLE users (
  user_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id        TINYINT UNSIGNED      NOT NULL DEFAULT 2,  -- default: customer
  first_name     VARCHAR(80)           NOT NULL,
  last_name      VARCHAR(80)           NOT NULL,
  email          VARCHAR(180)          NOT NULL UNIQUE,
  password_hash  VARCHAR(255)          NOT NULL,
  phone          VARCHAR(20),
  avatar_url     VARCHAR(500),
  email_verified TINYINT(1)            NOT NULL DEFAULT 0,
  is_active      TINYINT(1)            NOT NULL DEFAULT 1,
  created_at     TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
);


-- ============================================================
-- 3. USER ADDRESSES
-- ============================================================
CREATE TABLE user_addresses (
  address_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED  NOT NULL,
  label         VARCHAR(50)   DEFAULT 'Home',   -- 'Home', 'Work', etc.
  full_name     VARCHAR(160)  NOT NULL,
  phone         VARCHAR(20),
  address_line1 VARCHAR(255)  NOT NULL,
  address_line2 VARCHAR(255),
  city          VARCHAR(100)  NOT NULL,
  state         VARCHAR(100)  NOT NULL,
  postal_code   VARCHAR(20)   NOT NULL,
  country       VARCHAR(100)  NOT NULL DEFAULT 'India',
  is_default    TINYINT(1)    NOT NULL DEFAULT 0,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);


-- ============================================================
-- 4. PASSWORD RESET TOKENS
-- ============================================================
CREATE TABLE password_resets (
  reset_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  NOT NULL,
  token       VARCHAR(255)  NOT NULL UNIQUE,
  expires_at  DATETIME      NOT NULL,
  used        TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);


-- ============================================================
-- 5. CATEGORIES  (self-referencing for sub-categories)
-- ============================================================
CREATE TABLE categories (
  category_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id     INT UNSIGNED  DEFAULT NULL,   -- NULL = top-level category
  name          VARCHAR(120)  NOT NULL,
  slug          VARCHAR(130)  NOT NULL UNIQUE,
  description   TEXT,
  image_url     VARCHAR(500),
  sort_order    SMALLINT      NOT NULL DEFAULT 0,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE SET NULL
);


-- ============================================================
-- 6. BRANDS
-- ============================================================
CREATE TABLE brands (
  brand_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120)  NOT NULL UNIQUE,
  slug        VARCHAR(130)  NOT NULL UNIQUE,
  logo_url    VARCHAR(500),
  website     VARCHAR(300),
  is_active   TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);


-- ============================================================
-- 7. PRODUCTS
-- ============================================================
CREATE TABLE products (
  product_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id   INT UNSIGNED   NOT NULL,
  brand_id      INT UNSIGNED   DEFAULT NULL,
  name          VARCHAR(255)   NOT NULL,
  slug          VARCHAR(270)   NOT NULL UNIQUE,
  description   TEXT,
  short_desc    VARCHAR(500),
  sku           VARCHAR(100)   NOT NULL UNIQUE,
  price         DECIMAL(10,2)  NOT NULL,
  sale_price    DECIMAL(10,2)  DEFAULT NULL,   -- NULL = no sale
  cost_price    DECIMAL(10,2)  DEFAULT NULL,   -- for margin calculation
  stock_qty     INT            NOT NULL DEFAULT 0,
  low_stock_threshold INT      NOT NULL DEFAULT 5,
  weight_kg     DECIMAL(8,3)   DEFAULT NULL,
  is_active     TINYINT(1)     NOT NULL DEFAULT 1,
  is_featured   TINYINT(1)     NOT NULL DEFAULT 0,
  meta_title    VARCHAR(255),
  meta_desc     VARCHAR(500),
  created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(category_id),
  FOREIGN KEY (brand_id)    REFERENCES brands(brand_id) ON DELETE SET NULL
);


-- ============================================================
-- 8. PRODUCT IMAGES
-- ============================================================
CREATE TABLE product_images (
  image_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED  NOT NULL,
  image_url   VARCHAR(500)  NOT NULL,
  alt_text    VARCHAR(255),
  is_primary  TINYINT(1)    NOT NULL DEFAULT 0,
  sort_order  SMALLINT      NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);


-- ============================================================
-- 9. PRODUCT ATTRIBUTES  (e.g., Color, Size)
-- ============================================================
CREATE TABLE attributes (
  attribute_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL UNIQUE   -- 'Color', 'Size', 'Material'
);

CREATE TABLE attribute_values (
  value_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attribute_id  INT UNSIGNED  NOT NULL,
  value         VARCHAR(100)  NOT NULL,           -- 'Red', 'XL', 'Cotton'
  FOREIGN KEY (attribute_id) REFERENCES attributes(attribute_id) ON DELETE CASCADE
);

-- link a product to attribute values (e.g. this product comes in Red & Blue)
CREATE TABLE product_attributes (
  product_id  INT UNSIGNED  NOT NULL,
  value_id    INT UNSIGNED  NOT NULL,
  PRIMARY KEY (product_id, value_id),
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
  FOREIGN KEY (value_id)   REFERENCES attribute_values(value_id) ON DELETE CASCADE
);


-- ============================================================
-- 10. PRODUCT VARIANTS  (e.g., Size=M + Color=Red → SKU, price, stock)
-- ============================================================
CREATE TABLE product_variants (
  variant_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id   INT UNSIGNED    NOT NULL,
  sku          VARCHAR(120)    NOT NULL UNIQUE,
  price        DECIMAL(10,2)   DEFAULT NULL,    -- overrides product price if set
  sale_price   DECIMAL(10,2)   DEFAULT NULL,
  stock_qty    INT             NOT NULL DEFAULT 0,
  image_url    VARCHAR(500),
  is_active    TINYINT(1)      NOT NULL DEFAULT 1,
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- which attribute values make up a variant
CREATE TABLE variant_attributes (
  variant_id  INT UNSIGNED  NOT NULL,
  value_id    INT UNSIGNED  NOT NULL,
  PRIMARY KEY (variant_id, value_id),
  FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id) ON DELETE CASCADE,
  FOREIGN KEY (value_id)   REFERENCES attribute_values(value_id)   ON DELETE CASCADE
);


-- ============================================================
-- 11. COUPONS / DISCOUNTS
-- ============================================================
CREATE TABLE coupons (
  coupon_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(50)    NOT NULL UNIQUE,
  description     VARCHAR(255),
  discount_type   ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  discount_value  DECIMAL(10,2)  NOT NULL,
  min_order_amt   DECIMAL(10,2)  DEFAULT 0.00,
  max_discount    DECIMAL(10,2)  DEFAULT NULL,   -- cap for percent discounts
  usage_limit     INT            DEFAULT NULL,   -- NULL = unlimited
  used_count      INT            NOT NULL DEFAULT 0,
  per_user_limit  INT            DEFAULT 1,
  is_active       TINYINT(1)     NOT NULL DEFAULT 1,
  starts_at       DATETIME       DEFAULT NULL,
  expires_at      DATETIME       DEFAULT NULL,
  created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);


-- ============================================================
-- 12. SHOPPING CART
-- ============================================================
CREATE TABLE carts (
  cart_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  DEFAULT NULL,   -- NULL = guest cart
  session_id  VARCHAR(255)  DEFAULT NULL,   -- for guests
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE cart_items (
  cart_item_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id       INT UNSIGNED   NOT NULL,
  product_id    INT UNSIGNED   NOT NULL,
  variant_id    INT UNSIGNED   DEFAULT NULL,
  quantity      SMALLINT       NOT NULL DEFAULT 1,
  unit_price    DECIMAL(10,2)  NOT NULL,   -- snapshot at time of adding
  added_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cart_id)    REFERENCES carts(cart_id)            ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(product_id)      ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id) ON DELETE SET NULL
);


-- ============================================================
-- 13. ORDERS
-- ============================================================
CREATE TABLE orders (
  order_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED     DEFAULT NULL,
  order_number     VARCHAR(30)      NOT NULL UNIQUE,   -- e.g. ORD-20240601-0001
  status           ENUM(
                     'pending','confirmed','processing',
                     'shipped','delivered','cancelled','refunded'
                   ) NOT NULL DEFAULT 'pending',
  -- address snapshot (in case user changes address later)
  shipping_name    VARCHAR(160)     NOT NULL,
  shipping_phone   VARCHAR(20),
  shipping_addr1   VARCHAR(255)     NOT NULL,
  shipping_addr2   VARCHAR(255),
  shipping_city    VARCHAR(100)     NOT NULL,
  shipping_state   VARCHAR(100)     NOT NULL,
  shipping_postal  VARCHAR(20)      NOT NULL,
  shipping_country VARCHAR(100)     NOT NULL DEFAULT 'India',
  -- pricing
  subtotal         DECIMAL(10,2)    NOT NULL,
  discount_amt     DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  shipping_amt     DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  tax_amt          DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  total_amt        DECIMAL(10,2)    NOT NULL,
  coupon_id        INT UNSIGNED     DEFAULT NULL,
  notes            TEXT,
  created_at       TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)   REFERENCES users(user_id)   ON DELETE SET NULL,
  FOREIGN KEY (coupon_id) REFERENCES coupons(coupon_id) ON DELETE SET NULL
);

CREATE TABLE order_items (
  order_item_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id       INT UNSIGNED    NOT NULL,
  product_id     INT UNSIGNED    DEFAULT NULL,
  variant_id     INT UNSIGNED    DEFAULT NULL,
  product_name   VARCHAR(255)    NOT NULL,   -- snapshot
  variant_info   VARCHAR(255),              -- e.g. "Color: Red, Size: M"
  sku            VARCHAR(120)    NOT NULL,
  quantity       SMALLINT        NOT NULL,
  unit_price     DECIMAL(10,2)   NOT NULL,
  total_price    DECIMAL(10,2)   NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES orders(order_id)              ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(product_id)           ON DELETE SET NULL,
  FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id)   ON DELETE SET NULL
);


-- ============================================================
-- 14. ORDER STATUS HISTORY
-- ============================================================
CREATE TABLE order_status_history (
  history_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED  NOT NULL,
  status      VARCHAR(50)   NOT NULL,
  comment     VARCHAR(500),
  changed_by  INT UNSIGNED  DEFAULT NULL,   -- user_id (admin)
  changed_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id)   REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(user_id)  ON DELETE SET NULL
);


-- ============================================================
-- 15. PAYMENTS
-- ============================================================
CREATE TABLE payments (
  payment_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id         INT UNSIGNED    NOT NULL,
  payment_method   VARCHAR(50)     NOT NULL,   -- 'stripe','paypal','razorpay','cod'
  transaction_id   VARCHAR(255)    DEFAULT NULL,
  gateway_response TEXT            DEFAULT NULL,   -- raw JSON from gateway
  amount           DECIMAL(10,2)   NOT NULL,
  currency         CHAR(3)         NOT NULL DEFAULT 'INR',
  status           ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  paid_at          DATETIME        DEFAULT NULL,
  created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);


-- ============================================================
-- 16. SHIPPING / TRACKING
-- ============================================================
CREATE TABLE shipments (
  shipment_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id         INT UNSIGNED  NOT NULL,
  carrier          VARCHAR(100),    -- 'BlueDart', 'Delhivery', etc.
  tracking_number  VARCHAR(200),
  tracking_url     VARCHAR(500),
  shipped_at       DATETIME       DEFAULT NULL,
  estimated_delivery DATETIME     DEFAULT NULL,
  delivered_at     DATETIME       DEFAULT NULL,
  created_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);


-- ============================================================
-- 17. REVIEWS & RATINGS
-- ============================================================
CREATE TABLE reviews (
  review_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED  NOT NULL,
  user_id     INT UNSIGNED  DEFAULT NULL,
  order_id    INT UNSIGNED  DEFAULT NULL,   -- verify purchase
  rating      TINYINT       NOT NULL CHECK (rating BETWEEN 1 AND 5),
  title       VARCHAR(200),
  body        TEXT,
  is_approved TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE SET NULL,
  FOREIGN KEY (order_id)   REFERENCES orders(order_id)     ON DELETE SET NULL
);


-- ============================================================
-- 18. WISHLIST
-- ============================================================
CREATE TABLE wishlists (
  wishlist_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  NOT NULL,
  product_id  INT UNSIGNED  NOT NULL,
  added_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_product (user_id, product_id),
  FOREIGN KEY (user_id)    REFERENCES users(user_id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);


-- ============================================================
-- 19. NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
  notif_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  NOT NULL,
  type        VARCHAR(80)   NOT NULL,   -- 'order_shipped', 'review_approved', etc.
  message     VARCHAR(500)  NOT NULL,
  link        VARCHAR(500)  DEFAULT NULL,
  is_read     TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);


-- ============================================================
-- 20. SITE SETTINGS (key-value store for admin config)
-- ============================================================
CREATE TABLE settings (
  setting_key    VARCHAR(100)  PRIMARY KEY,
  setting_value  TEXT,
  updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
  ('site_name',           'MyShop'),
  ('site_email',          'support@myshop.com'),
  ('currency',            'INR'),
  ('currency_symbol',     '₹'),
  ('tax_rate_percent',    '18'),
  ('free_shipping_above', '500'),
  ('flat_shipping_rate',  '60'),
  ('orders_per_page',     '20'),
  ('maintenance_mode',    '0');


-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================
CREATE INDEX idx_products_category   ON products(category_id);
CREATE INDEX idx_products_brand      ON products(brand_id);
CREATE INDEX idx_products_active     ON products(is_active);
CREATE INDEX idx_orders_user         ON orders(user_id);
CREATE INDEX idx_orders_status       ON orders(status);
CREATE INDEX idx_order_items_order   ON order_items(order_id);
CREATE INDEX idx_cart_items_cart     ON cart_items(cart_id);
CREATE INDEX idx_reviews_product     ON reviews(product_id);
CREATE INDEX idx_payments_order      ON payments(order_id);
CREATE INDEX idx_shipments_order     ON shipments(order_id);