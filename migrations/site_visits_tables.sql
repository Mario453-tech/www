-- -----------------------------------------------------------------------------
-- OilEmpire - Visit Tracking System Schema
-- Schemat systemu sledzenia wizyt
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `site_visits_daily` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `visit_date` DATE NOT NULL UNIQUE,
    `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `player_views` INT UNSIGNED NOT NULL DEFAULT 0,
    `guest_views` INT UNSIGNED NOT NULL DEFAULT 0,
    `bot_views` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_visit_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_visit_country_stats` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `visit_date` DATE NOT NULL,
    `country_code` VARCHAR(5) NOT NULL,
    `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY `uniq_date_country` (`visit_date`, `country_code`),
    INDEX `idx_country` (`country_code`),
    INDEX `idx_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_visit_page_stats` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `visit_date` DATE NOT NULL,
    `page_path` VARCHAR(120) NOT NULL,
    `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY `uniq_date_page` (`visit_date`, `page_path`),
    INDEX `idx_page` (`page_path`),
    INDEX `idx_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_visit_device_stats` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `visit_date` DATE NOT NULL,
    `device_type` ENUM('desktop', 'mobile', 'tablet', 'bot') NOT NULL,
    `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY `uniq_date_device` (`visit_date`, `device_type`),
    INDEX `idx_device` (`device_type`),
    INDEX `idx_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_visit_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `visit_date` DATE NOT NULL,
    `visitor_hash` VARCHAR(32) NOT NULL,
    `player_id` INT UNSIGNED NULL,
    `country_code` VARCHAR(5) NOT NULL DEFAULT 'XX',
    `device_type` ENUM('desktop', 'mobile', 'tablet', 'bot') NOT NULL DEFAULT 'desktop',
    `page_path` VARCHAR(120) NOT NULL,
    `referrer_host` VARCHAR(120) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_date_hash` (`visit_date`, `visitor_hash`),
    INDEX `idx_date_country` (`visit_date`, `country_code`),
    INDEX `idx_date_player` (`visit_date`, `player_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
