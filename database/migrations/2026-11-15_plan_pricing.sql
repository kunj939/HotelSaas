-- Safe to re-run. Shares the key/value settings table with UPI billing.
CREATE TABLE IF NOT EXISTS platform_settings (
 k VARCHAR(60) NOT NULL PRIMARY KEY,
 v VARCHAR(500) NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO platform_settings(k,v) VALUES('default_monthly_fee','599.00');
