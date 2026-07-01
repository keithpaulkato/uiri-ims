-- One-time MySQL setup for UIRI IMS (Laravel).
-- Run once with:  sudo mysql < database/setup-mysql.sql
-- Creates the app + test databases and a dedicated local user.

CREATE DATABASE IF NOT EXISTS uiri_ims
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS uiri_ims_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'uiri'@'localhost' IDENTIFIED BY 'uiri_dev_pass';

GRANT ALL PRIVILEGES ON uiri_ims.*      TO 'uiri'@'localhost';
GRANT ALL PRIVILEGES ON uiri_ims_test.* TO 'uiri'@'localhost';

FLUSH PRIVILEGES;
