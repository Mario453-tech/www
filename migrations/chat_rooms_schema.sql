-- -----------------------------------------------------------------------------
-- OilEmpire - Complete Chat System Schema Migration
-- Migracja schematu kompletnego systemu czatu
-- -----------------------------------------------------------------------------

-- 1. Chat rooms table
-- 1. Tabela pokoi czatu
CREATE TABLE IF NOT EXISTS `chat_rooms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('language', 'custom', 'system') NOT NULL DEFAULT 'language',
    `locale_code` VARCHAR(10) NULL DEFAULT NULL,
    `name_pl` VARCHAR(100) NOT NULL,
    `name_en` VARCHAR(100) NOT NULL,
    `name_de` VARCHAR(100) NOT NULL,
    `description_pl` VARCHAR(255) NULL DEFAULT NULL,
    `description_en` VARCHAR(255) NULL DEFAULT NULL,
    `description_de` VARCHAR(255) NULL DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'read_only', 'archived') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `archived_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_chat_rooms_status_sort` (`status`, `sort_order`),
    INDEX `idx_chat_rooms_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed initial language rooms
-- 2. Seed poczatkowych pokoi jezykowych
INSERT INTO `chat_rooms` (`id`, `slug`, `type`, `locale_code`, `name_pl`, `name_en`, `name_de`, `description_pl`, `description_en`, `description_de`, `sort_order`, `status`, `created_at`, `updated_at`)
VALUES
(1, 'polski', 'language', 'pl', 'Polski', 'Polish', 'Polnisch', 'Pokój językowy dla społeczności polskiej', 'Language room for Polish community', 'Sprachraum für die polnische Community', 10, 'active', NOW(), NOW()),
(2, 'english', 'language', 'en', 'English', 'English', 'Englisch', 'Global language room for all players', 'Global language room for all players', 'Globaler Sprachraum für alle Spieler', 20, 'active', NOW(), NOW()),
(3, 'germany', 'language', 'de', 'Germany', 'German', 'Deutsch', 'Sprachraum für deutschsprachige Spieler', 'Language room for German-speaking players', 'Sprachraum für deutschsprachige Spieler', 30, 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `slug` = VALUES(`slug`),
    `name_pl` = VALUES(`name_pl`),
    `name_en` = VALUES(`name_en`),
    `name_de` = VALUES(`name_de`),
    `sort_order` = VALUES(`sort_order`);

-- 3. Read states tracking
-- 3. Sledzenie stanow przeczytania
CREATE TABLE IF NOT EXISTS `chat_read_states` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `player_id` INT UNSIGNED NOT NULL,
    `conversation_type` ENUM('room', 'direct') NOT NULL,
    `conversation_id` INT UNSIGNED NOT NULL,
    `last_read_message_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_read_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uniq_player_conv` (`player_id`, `conversation_type`, `conversation_id`),
    INDEX `idx_chat_read_states_player` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Presence and room tracking
-- 4. Obecnosc i sledzenie aktualnego pokoju
CREATE TABLE IF NOT EXISTS `chat_presence` (
    `player_id` INT UNSIGNED PRIMARY KEY,
    `current_room_slug` VARCHAR(50) NULL DEFAULT 'polski',
    `last_active_at` DATETIME NOT NULL,
    `is_online` TINYINT(1) NOT NULL DEFAULT 1,
    INDEX `idx_chat_presence_active` (`is_online`, `last_active_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Moderation actions audit log
-- 5. Dziennik audytu akcji moderacyjnych
CREATE TABLE IF NOT EXISTS `chat_moderation_actions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `target_type` ENUM('room', 'room_message', 'direct_message', 'player') NOT NULL,
    `target_id` INT UNSIGNED NOT NULL,
    `reason` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_chat_mod_created` (`created_at`),
    INDEX `idx_chat_mod_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Navigation item for Chat
-- 6. Pozycja nawigacji dla Czatu
INSERT INTO `nav_items` (`label`, `url_key`, `location`, `sort_order`, `active`, `lang_key`, `css_class`)
SELECT 'Czat', 'chat', 'header', 85, 1, 'nav.chat', 'secondary'
WHERE NOT EXISTS (SELECT 1 FROM `nav_items` WHERE `url_key` = 'chat');
