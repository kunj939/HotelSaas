-- Adds the customer name (required) and optional mobile number to orders.
-- Run ONCE on any database created from an earlier schema.sql, in phpMyAdmin
-- (select the database, then the SQL tab, paste this, and click Go).
-- New installs get these columns from database/schema.sql and don't need this file.
ALTER TABLE orders
 ADD COLUMN customer_name VARCHAR(60) DEFAULT NULL AFTER note,
 ADD COLUMN customer_phone VARCHAR(10) DEFAULT NULL AFTER customer_name;
