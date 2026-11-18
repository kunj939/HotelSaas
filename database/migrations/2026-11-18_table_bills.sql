-- Table tabs and printed bills, staff-entered WhatsApp/phone orders, GSTIN and tax on bills.
-- Run ONCE on any database created from an earlier schema.sql, in phpMyAdmin
-- (select the database, then the SQL tab, paste this, and click Go).
-- New installs get these from database/schema.sql and don't need this file.

ALTER TABLE restaurants
 ADD COLUMN gstin VARCHAR(15) DEFAULT NULL AFTER contact_options_enabled,
 ADD COLUMN tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER gstin;

-- One open bill per table at a time. open_flag is 1 only while open, so closed bills never clash.
CREATE TABLE IF NOT EXISTS table_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 table_no INT NOT NULL,
 status ENUM('open','closed') NOT NULL DEFAULT 'open',
 open_flag TINYINT AS (IF(status='open',1,NULL)) STORED,
 opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 closed_at DATETIME DEFAULT NULL,
 closed_by BIGINT UNSIGNED DEFAULT NULL,
 subtotal DECIMAL(12,2) DEFAULT NULL,
 tax DECIMAL(12,2) DEFAULT NULL,
 total DECIMAL(12,2) DEFAULT NULL,
 UNIQUE KEY uq_open_table (restaurant_id,table_no,open_flag),
 INDEX idx_session_rest (restaurant_id,status),
 CONSTRAINT fk_session_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders
 ADD COLUMN table_session_id BIGINT UNSIGNED DEFAULT NULL AFTER table_no,
 ADD COLUMN source ENUM('qr','whatsapp','phone','walkin') NOT NULL DEFAULT 'qr' AFTER status,
 ADD INDEX idx_order_session (table_session_id);
