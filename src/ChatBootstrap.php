<?php

// Chat system schema bootstrap
// Bootstrap schematu systemu czatu

if (!function_exists('ensureChatSchema')) {
    function ensureChatSchema(?PDO $connection = null): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new LogicException('Chat schema migration requires CLI.');
        }

        try {
            $db = $connection ?? Database::getInstance()->getConnection();

            $columns = array_column($db->query('SHOW COLUMNS FROM chat_messages')->fetchAll(PDO::FETCH_ASSOC), 'Field');
            foreach (['is_deleted' => 'TINYINT NOT NULL DEFAULT 0', 'is_admin' => 'TINYINT NOT NULL DEFAULT 0',
                'is_pinned' => 'TINYINT NOT NULL DEFAULT 0', 'pinned_at' => 'DATETIME NULL',
                'attachment_path' => 'VARCHAR(255) NULL', 'attachment_name' => 'VARCHAR(255) NULL',
                'attachment_type' => 'VARCHAR(50) NULL', 'attachment_size' => 'INT UNSIGNED NULL',
                'room_id' => 'INT UNSIGNED NULL', 'room_slug' => 'VARCHAR(50) NULL'] as $name => $definition) {
                if (!in_array($name, $columns, true)) {
                    $db->exec("ALTER TABLE chat_messages ADD COLUMN `{$name}` {$definition}");
                }
            }

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
                INSERT INTO `chat_rooms` (`slug`, `type`, `locale_code`, `name_pl`, `name_en`, `name_de`, `description_pl`, `description_en`, `description_de`, `sort_order`, `status`, `created_at`, `updated_at`)
                VALUES
                ('polski', 'language', 'pl', 'Polski', 'Polish', 'Polnisch', 'Pokój językowy dla społeczności polskiej', 'Language room for Polish community', 'Sprachraum für die polnische Community', 10, 'active', NOW(), NOW()),
                ('english', 'language', 'en', 'English', 'English', 'Englisch', 'Pokój anglojęzyczny', 'Global language room for all players', 'Globaler Sprachraum für alle Spieler', 20, 'active', NOW(), NOW()),
                ('germany', 'language', 'de', 'Germany', 'Germany', 'Deutsch', 'Pokój niemieckojęzyczny', 'Language room for German-speaking players', 'Sprachraum für deutschsprachige Spieler', 30, 'active', NOW(), NOW())
                ON DUPLICATE KEY UPDATE `id` = `id`
            ");

            // Store room labels independently from application languages.
            // Przechowuj etykiety pokoi niezaleznie od jezykow aplikacji.
            $db->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_translations` (
                    `room_id` INT UNSIGNED NOT NULL,
                    `locale` VARCHAR(20) NOT NULL,
                    `name` VARCHAR(100) NOT NULL,
                    `description` VARCHAR(255) NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`room_id`, `locale`),
                    INDEX `idx_chat_room_translations_locale` (`locale`, `room_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            foreach (['pl', 'en', 'de'] as $seedLocale) {
                $nameColumn = 'name_' . $seedLocale;
                $descriptionColumn = 'description_' . $seedLocale;
                $db->exec("
                    INSERT INTO chat_room_translations (room_id, locale, name, description, created_at, updated_at)
                    SELECT id, '{$seedLocale}', `{$nameColumn}`, `{$descriptionColumn}`, NOW(), NOW()
                    FROM chat_rooms
                    WHERE `{$nameColumn}` != ''
                    ON DUPLICATE KEY UPDATE room_id = room_id
                ");
            }

            // Backfill legacy global messages with room_id = 1, room_slug = 'polski'
            // Uzupelnij historyczne wiadomosci globalne o room_id = 1, room_slug = 'polski'
            $db->exec("UPDATE chat_messages m JOIN chat_rooms r ON r.slug = 'polski'
                SET m.room_id = r.id, m.room_slug = r.slug WHERE m.channel = 'global' AND m.room_id IS NULL");

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
            $db->exec("ALTER TABLE chat_messages MODIFY channel ENUM('global','private','room') NOT NULL DEFAULT 'global'");
            $column = $db->query("SHOW COLUMNS FROM chat_messages LIKE 'message'")->fetch(PDO::FETCH_ASSOC);
            if ($column && preg_match('/^varchar\((\d+)\)$/i', $column['Type'], $length) && (int) $length[1] < 1000) {
                $db->exec('ALTER TABLE chat_messages MODIFY message TEXT NOT NULL');
            }
            $index = $db->query("SHOW INDEX FROM chat_messages WHERE Key_name = 'idx_chat_room_history'")->fetch();
            if (!$index) {
                $db->exec('CREATE INDEX idx_chat_room_history ON chat_messages (room_id, is_deleted, id)');
            }
            $db->exec("CREATE TABLE IF NOT EXISTS chat_request_limits (
                bucket_key CHAR(64) PRIMARY KEY, started_at BIGINT NOT NULL, hits INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::warn('ChatBootstrap', 'ensureChatSchema failed', [
                    'message' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }
}
