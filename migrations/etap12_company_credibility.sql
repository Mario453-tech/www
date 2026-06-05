-- ============================================================
-- MIGRACJA: etap12_company_credibility.sql
-- Cel: fundament systemu wiarygodnosci firmy (company_credibility).
--      1. nowe pole players.company_credibility (0-100, default 50),
--      2. tabela historii zmian company_credibility_log.
-- Goal: foundation of the company credibility system.
-- Data / Date: 2026-06-05
-- MySQL 8.0 compatible.
-- UWAGA: uruchom jednorazowo. Operacja jest tez wykonywana automatycznie
--        przez CompanyCredibilityService::ensureSchema() przy pierwszym
--        wejsciu na dashboard / panel admina (poza transakcja).
-- NOTE: run once. Also performed automatically by
--       CompanyCredibilityService::ensureSchema() on first dashboard/admin load.
--       Jesli kolumna juz istnieje, MySQL zglosi blad 1060 - mozna zignorowac.
-- ============================================================

-- 1. players: ogolna wiarygodnosc firmy (0-100, start 50)
--    players: general company credibility (0-100, starts at 50)
ALTER TABLE `players`
    ADD COLUMN `company_credibility` INT UNSIGNED NOT NULL DEFAULT 50;

-- 2. Historia zmian wiarygodnosci (obowiazkowa wg briefu, sekcja 3)
--    Credibility change history (mandatory per brief, section 3)
CREATE TABLE IF NOT EXISTS `company_credibility_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `player_id` INT UNSIGNED NOT NULL,
    `event_key` VARCHAR(64) NOT NULL,
    `delta` INT NOT NULL,
    `score_before` INT UNSIGNED NOT NULL,
    `score_after` INT UNSIGNED NOT NULL,
    `note` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_player_created` (`player_id`, `created_at`),
    KEY `idx_event` (`event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Rozszerzenie ENUM director_notifications.type o 'credibility' (sekcja 9).
--    Bez tego INSERT powiadomienia z notify() wywala sie w trybie strict (blad 1265).
--    Extend director_notifications.type ENUM with 'credibility' (section 9).
--    Without it the notify() INSERT fails in MySQL strict mode (error 1265).
ALTER TABLE `director_notifications`
    MODIFY COLUMN `type`
    ENUM('bank','hr','technical','market','legal','urgent','info','credibility')
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info';
