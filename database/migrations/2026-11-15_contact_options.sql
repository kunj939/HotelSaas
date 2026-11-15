-- Run ONCE on existing installations; new installs use schema.sql.
ALTER TABLE restaurants
 ADD COLUMN whatsapp_number VARCHAR(15) DEFAULT NULL,
 ADD COLUMN contact_email VARCHAR(190) DEFAULT NULL,
 ADD COLUMN contact_options_enabled TINYINT(1) NOT NULL DEFAULT 0;
