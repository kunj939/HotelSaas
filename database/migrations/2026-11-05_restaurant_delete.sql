-- Lets Super Admin delete a restaurant safely: "Delete" hides it (restorable), "Delete permanently" removes it.
-- Run ONCE on any database created from an earlier schema.sql, after 2026-11-04_tokens_counter_outlets.sql.
-- New installs get this from database/schema.sql and don't need this file.
ALTER TABLE restaurants
 ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER created_at,
 ADD INDEX idx_deleted (deleted_at);
