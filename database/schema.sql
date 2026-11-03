-- MySQL 8 / MariaDB 10.5+, utf8mb4, InnoDB. Run once using phpMyAdmin.
CREATE TABLE IF NOT EXISTS restaurants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(90) NOT NULL UNIQUE,
 name VARCHAR(160) NOT NULL,
 tagline VARCHAR(250) DEFAULT 'Freshly made. Happily served.',
 welcome_message VARCHAR(500) DEFAULT 'Welcome! Your next favourite meal awaits.',
 logo_url VARCHAR(500) DEFAULT NULL,
 cover_url VARCHAR(500) DEFAULT NULL,
 primary_color CHAR(7) NOT NULL DEFAULT '#186953',
 accent_color CHAR(7) NOT NULL DEFAULT '#E4B866',
 theme_mode ENUM('light','dark') NOT NULL DEFAULT 'light',
 menu_layout ENUM('grid','list') NOT NULL DEFAULT 'list',
 phone VARCHAR(40) DEFAULT NULL,
 address VARCHAR(400) DEFAULT NULL,
 opening_hours VARCHAR(180) DEFAULT NULL,
 table_count INT NOT NULL DEFAULT 20,
 qr_mode ENUM('common','table','counter') NOT NULL DEFAULT 'common',
 ordering_enabled TINYINT(1) NOT NULL DEFAULT 1,
 status ENUM('trial','active','suspended') NOT NULL DEFAULT 'trial',
 monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 599.00,
 setup_fee_paid TINYINT(1) NOT NULL DEFAULT 0,
 subscription_ends DATE NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_at DATETIME DEFAULT NULL,
 INDEX idx_status (status,subscription_ends),
 INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED DEFAULT NULL,
 role ENUM('super','owner','staff') NOT NULL,
 full_name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_users_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS token_counters (
 restaurant_id BIGINT UNSIGNED NOT NULL,
 day DATE NOT NULL,
 last_no INT UNSIGNED NOT NULL,
 PRIMARY KEY (restaurant_id,day),
 CONSTRAINT fk_token_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which restaurants each owner/staff login can open (owners can have several outlets)
CREATE TABLE IF NOT EXISTS restaurant_access (
 user_id BIGINT UNSIGNED NOT NULL,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,restaurant_id),
 INDEX idx_access_rest (restaurant_id),
 CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_access_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(100) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 UNIQUE KEY uq_cat (restaurant_id,name),
 CONSTRAINT fk_cat_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED DEFAULT NULL,
 name VARCHAR(160) NOT NULL,
 description VARCHAR(500) DEFAULT NULL,
 food_type ENUM('veg','nonveg','egg','unspecified') NOT NULL DEFAULT 'unspecified',
 base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
 options_json JSON DEFAULT NULL,
 addons_json JSON DEFAULT NULL,
 image_url VARCHAR(800) DEFAULT NULL,
 image_credit VARCHAR(500) DEFAULT NULL,
 image_license VARCHAR(100) DEFAULT NULL,
 image_source_url VARCHAR(800) DEFAULT NULL,
 available TINYINT(1) NOT NULL DEFAULT 1,
 deleted_at DATETIME DEFAULT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_menu (restaurant_id,deleted_at,available),
 CONSTRAINT fk_menu_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
 CONSTRAINT fk_menu_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 table_no INT NOT NULL,
 token_no INT UNSIGNED DEFAULT NULL,
 token_day DATE DEFAULT NULL,
 status ENUM('new','accepted','preparing','ready','served','rejected') NOT NULL DEFAULT 'new',
 call_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
 called_at TIMESTAMP NULL DEFAULT NULL,
 total DECIMAL(12,2) NOT NULL,
 note VARCHAR(500) DEFAULT NULL,
 customer_name VARCHAR(60) DEFAULT NULL,
 customer_phone VARCHAR(10) DEFAULT NULL,
 client_request_id VARCHAR(80) NOT NULL,
 tracking_key CHAR(64) NOT NULL,
 ip_hash CHAR(64) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_request (restaurant_id,client_request_id),
 UNIQUE KEY uq_tracking_key (tracking_key),
 UNIQUE KEY uq_token (restaurant_id,token_day,token_no),
 INDEX idx_order_live (restaurant_id,status,created_at),
 INDEX idx_order_date (restaurant_id,created_at),
 CONSTRAINT fk_orders_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 item_id BIGINT UNSIGNED DEFAULT NULL,
 dish_name VARCHAR(160) NOT NULL,
 variant_name VARCHAR(100) DEFAULT NULL,
 addons_json JSON DEFAULT NULL,
 quantity SMALLINT NOT NULL,
 unit_price DECIMAL(10,2) NOT NULL,
 line_total DECIMAL(12,2) NOT NULL,
 CONSTRAINT fk_orderitem_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_orderitem_menu FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(10,2) NOT NULL,
 paid_on DATE NOT NULL,
 method VARCHAR(50) NOT NULL DEFAULT 'UPI',
 reference_no VARCHAR(120) DEFAULT NULL,
 note VARCHAR(300) DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_payments (restaurant_id,paid_on),
 CONSTRAINT fk_payments_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS autopay_mandates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 authpayuid VARCHAR(40) NOT NULL,
 mode VARCHAR(20) DEFAULT NULL,
 amount DECIMAL(10,2) NOT NULL,
 cycles_total TINYINT UNSIGNED NOT NULL DEFAULT 6,
 cycles_done TINYINT UNSIGNED NOT NULL DEFAULT 1,
 next_debit_date DATE NOT NULL,
 predebit_debit_on DATE DEFAULT NULL,
 invoice_no VARCHAR(40) DEFAULT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 last_attempt_on DATE DEFAULT NULL,
 status ENUM('active','completed','cancelled','failed') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_mandate_status (status,next_debit_date),
 INDEX idx_mandate_rest (restaurant_id,id),
 CONSTRAINT fk_mandate_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payu_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 mandate_id BIGINT UNSIGNED DEFAULT NULL,
 txnid VARCHAR(40) NOT NULL UNIQUE,
 kind ENUM('checkout','recurring') NOT NULL,
 plan ENUM('monthly','autopay6') NOT NULL,
 amount DECIMAL(10,2) NOT NULL,
 setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
 status ENUM('initiated','pending','success','failed') NOT NULL DEFAULT 'initiated',
 mihpayid VARCHAR(40) DEFAULT NULL,
 mode VARCHAR(20) DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_payu_status (status,created_at),
 INDEX idx_payu_rest (restaurant_id,created_at),
 INDEX idx_payu_mandate (mandate_id,status),
 CONSTRAINT fk_payu_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
 CONSTRAINT fk_payu_mandate FOREIGN KEY (mandate_id) REFERENCES autopay_mandates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_settings (
 k VARCHAR(60) NOT NULL PRIMARY KEY,
 v VARCHAR(500) NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS upi_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 plan ENUM('monthly','upfront6') NOT NULL,
 months TINYINT UNSIGNED NOT NULL,
 amount DECIMAL(10,2) NOT NULL,
 setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
 monthly_fee DECIMAL(10,2) NOT NULL,
 utr VARCHAR(40) NOT NULL UNIQUE,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 note VARCHAR(300) DEFAULT NULL,
 submitted_by BIGINT UNSIGNED DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME DEFAULT NULL,
 INDEX idx_upi_status (status,created_at),
 INDEX idx_upi_rest (restaurant_id,created_at),
 CONSTRAINT fk_upi_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED DEFAULT NULL,
 restaurant_id BIGINT UNSIGNED DEFAULT NULL,
 action VARCHAR(90) NOT NULL,
 detail VARCHAR(400) DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit (restaurant_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ip_hash CHAR(64) NOT NULL,
 attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_login (ip_hash,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
