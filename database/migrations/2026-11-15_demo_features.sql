-- Run ONCE for an existing installation before uploading the updated API.
-- New installations already include these columns in schema.sql.
ALTER TABLE restaurants
 ADD COLUMN customer_name_mode ENUM('off','optional','required') NOT NULL DEFAULT 'optional',
 ADD COLUMN customer_phone_mode ENUM('off','optional','required') NOT NULL DEFAULT 'optional';
ALTER TABLE menu_items
 ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0;
