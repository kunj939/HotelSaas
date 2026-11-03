-- Daily token numbers with "call token", counter mode (no tables) and one owner login for several restaurants.
-- Run ONCE on any database created from an earlier schema.sql, in phpMyAdmin
-- (select the database, then the SQL tab, paste this, and click Go).
-- New installs get these from database/schema.sql and don't need this file.
ALTER TABLE restaurants
 MODIFY qr_mode ENUM('common','table','counter') NOT NULL DEFAULT 'common';

ALTER TABLE orders
 ADD COLUMN token_no INT UNSIGNED DEFAULT NULL AFTER table_no,
 ADD COLUMN token_day DATE DEFAULT NULL AFTER token_no,
 ADD COLUMN call_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
 ADD COLUMN called_at TIMESTAMP NULL DEFAULT NULL AFTER call_count,
 ADD UNIQUE KEY uq_token (restaurant_id,token_day,token_no);

CREATE TABLE IF NOT EXISTS token_counters (
 restaurant_id BIGINT UNSIGNED NOT NULL,
 day DATE NOT NULL,
 last_no INT UNSIGNED NOT NULL,
 PRIMARY KEY (restaurant_id,day),
 CONSTRAINT fk_token_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS restaurant_access (
 user_id BIGINT UNSIGNED NOT NULL,
 restaurant_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,restaurant_id),
 INDEX idx_access_rest (restaurant_id),
 CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_access_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every existing owner/staff keeps access to their own restaurant.
INSERT IGNORE INTO restaurant_access(user_id,restaurant_id)
 SELECT id,restaurant_id FROM users WHERE restaurant_id IS NOT NULL;
