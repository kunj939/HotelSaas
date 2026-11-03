-- Manual UPI payments for subscriptions (works before PayU is set up): the owner pays to the platform UPI ID
-- and submits the UTR; Super Admin approves it, which extends the subscription.
-- Run ONCE on any database created from an earlier schema.sql, after 2026-11-05_restaurant_delete.sql.
-- New installs get this from database/schema.sql and don't need this file.
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
