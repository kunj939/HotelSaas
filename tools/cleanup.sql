-- Can run periodically from phpMyAdmin or a secure cron script.
DELETE FROM auth_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY;
-- Don't delete historical orders automatically; they form restaurant reporting history.
