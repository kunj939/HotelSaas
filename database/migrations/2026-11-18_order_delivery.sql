-- Dine-in, takeaway or delivery on every order, plus the delivery address.
-- Run ONCE after 2026-11-18_table_bills.sql, in phpMyAdmin (SQL tab, paste, Go).
-- New installs get these columns from database/schema.sql and don't need this file.

ALTER TABLE orders
 ADD COLUMN fulfilment ENUM('dine_in','takeaway','delivery') NOT NULL DEFAULT 'dine_in' AFTER source,
 ADD COLUMN delivery_address VARCHAR(400) DEFAULT NULL AFTER customer_phone;

-- Counter (no table) orders placed before this change are takeaway orders.
UPDATE orders SET fulfilment='takeaway' WHERE table_no=0;
