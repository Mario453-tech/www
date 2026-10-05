<?php

// Chat system schema bootstrap
// Bootstrap schematu systemu czatu

if (!function_exists('ensureChatSchema')) {
    function ensureChatSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $db = Database::getInstance()->getConnection();

            Database::addColumnIfMissing('chat_messages', 'is_deleted', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `message`");
            Database::addColumnIfMissing('chat_messages', 'is_admin', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_deleted`");
            Database::addColumnIfMissing('chat_messages', 'is_pinned', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_admin`");
            Database::addColumnIfMissing('chat_messages', 'pinned_at', "DATETIME NULL DEFAULT NULL AFTER `is_pinned`");
            Database::addColumnIfMissing('chat_messages', 'attachment_path', "VARCHAR(255) NULL DEFAULT NULL AFTER `pinned_at`");
            Database::addColumnIfMissing('chat_messages', 'attachment_name', "VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_path`");
            Database::addColumnIfMissing('chat_messages', 'attachment_type', "VARCHAR(50) NULL DEFAULT NULL AFTER `attachment_name`");
            Database::addColumnIfMissing('chat_messages', 'attachment_size', "INT UNSIGNED NULL DEFAULT NULL AFTER `attachment_type`");

            // Add room_id and room_slug to chat_messages if missing
            // Dodaj room_id i room_slug do chat_messages jesli brak
            Database::addColumnIfMissing('chat_messages', 'room_id', "INT UNSIGNED NULL DEFAULT NULL AFTER `channel`");
            Database::addColumnIfMissing('chat_messages', 'room_slug', "VARCHAR(50) NULL DEFAULT NULL AFTER `room_id`");

            // Create chat_rooms
            // Utworz chat_rooms
            $db->exec("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Seed default language rooms idempotently
            // Zasil domyslne pokoje jezykowe w sposob idempotentny
            $db->exec("
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
                    `sort_order` = VALUES(`sort_order`)
            ");

            // Backfill legacy global messages with room_id = 1, room_slug = 'polski'
            // Uzupelnij historyczne wiadomosci globalne o room_id = 1, room_slug = 'polski'
            try {
                $db->exec("
                    UPDATE `chat_messages`
                    SET `room_id` = 1, `room_slug` = 'polski'
                    WHERE `channel` = 'global' AND `room_id` IS NULL
                ");
            } catch (Throwable) {
                // Ignore if chat_messages does not exist yet
                // Ignoruj jesli chat_messages jeszcze nie istnieje
            }

            // Create chat_reports
            // Utworz chat_reports
            $db->exec("
                CREATE TABLE IF NOT EXISTS `chat_reports` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `message_id` INT NOT NULL,
                    `reporter_id` INT NOT NULL,
                    `reason` ENUM('spam','obraza','inne') NOT NULL DEFAULT 'inne',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `status` ENUM('open','resolved') NOT NULL DEFAULT 'open',
                    INDEX `idx_chat_reports_status` (`status`, `created_at`),
                    INDEX `idx_chat_reports_message` (`message_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create chat_conversation_reads
            // Utworz chat_conversation_reads
            $db->exec("
                CREATE TABLE IF NOT EXISTS `chat_conversation_reads` (
                    `player_id` INT NOT NULL,
                    `partner_id` INT NOT NULL,
                    `last_read_message_id` INT NOT NULL DEFAULT 0,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`player_id`, `partner_id`),
                    INDEX `idx_chat_conv_reads_partner` (`partner_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create chat_read_states
            // Utworz chat_read_states
            $db->exec("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create chat_presence
            // Utworz chat_presence
            $db->exec("
                CREATE TABLE IF NOT EXISTS `chat_presence` (
                    `player_id` INT UNSIGNED PRIMARY KEY,
                    `current_room_slug` VARCHAR(50) NULL DEFAULT 'polski',
                    `last_active_at` DATETIME NOT NULL,
                    `is_online` TINYINT(1) NOT NULL DEFAULT 1,
                    INDEX `idx_chat_presence_active` (`is_online`, `last_active_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create chat_moderation_actions
            // Utworz chat_moderation_actions
            $db->exec("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            // Ensure Czat is in nav_items if table exists
            // Upewnij sie ze Czat jest w nav_items jesli tabela istnieje
            try {
                $db->exec("
                    INSERT INTO `nav_items` (`label`, `url_key`, `location`, `sort_order`, `active`, `lang_key`, `css_class`)
                    SELECT 'Czat', 'chat', 'header', 85, 1, 'nav.chat', 'secondary'
                    WHERE NOT EXISTS (SELECT 1 FROM `nav_items` WHERE `url_key` = 'chat')
                ");
            } catch (Throwable) {
                // Ignore if nav_items table does not exist
                // Ignoruj jesli tabela nav_items nie istnieje
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::warn('ChatBootstrap', 'ensureChatSchema failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
