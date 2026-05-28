<?php

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
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::warn('ChatBootstrap', 'ensureChatSchema failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
