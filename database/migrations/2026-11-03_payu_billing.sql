-- Adds PayU subscription billing: one-time setup fee flag, PayU transactions and 6-month autopay mandates.
-- Run ONCE on any database created from an earlier schema.sql, in phpMyAdmin
-- (select the database, then the SQL tab, paste this, and click Go).
-- New installs get these from database/schema.sql and don't need this file.
-- Every existing restaurant starts with the ₹500 setup fee unpaid; tick "Setup fee paid" in Super Admin for any that already paid it.
ALTER TABLE restaurants
 ADD COLUMN setup_fee_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER monthly_fee;

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
