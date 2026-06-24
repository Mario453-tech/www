-- Migracja: tabela tokenow API dla aplikacji mobilnej
-- Migration: API tokens table for mobile app
-- Uruchom na serwerze az.pl przez phpMyAdmin lub SSH.
-- Run on az.pl server via phpMyAdmin or SSH.

CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `player_id`  INT          NOT NULL,
    `token`      VARCHAR(64)  NOT NULL,
    `device`     VARCHAR(200) NULL COMMENT 'opcjonalny opis urzadzenia / optional device description',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME   NULL,
    `expires_at` DATETIME     NULL COMMENT 'NULL = nigdy nie wygasa / NULL = never expires',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token`     (`token`),
    KEY        `idx_player`   (`player_id`),
    CONSTRAINT `fk_api_tokens_player`
        FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
