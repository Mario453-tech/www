-- ============================================================
-- Etap 13: P2a — zezwolenia na prace lokalne (huby, rurociagi)
-- Etap 13: P2a — local works permits (hubs, pipelines)
--
-- Idempotentna migracja (bezpieczna do wielokrotnego uruchomienia).
-- Idempotent migration (safe to run multiple times).
--
-- Co robi:
--   1. Dodaje 3 kolumny do legal_region_config:
--      hub_permit_enabled  TINYINT(1) DEFAULT 0
--      hub_permit_cost     DECIMAL(14,2) DEFAULT 500000.00
--      hub_review_minutes  INT UNSIGNED DEFAULT 120
--   2. Tworzy tabele hub_permit_applications jesli nie istnieje.
--
-- What this does:
--   1. Adds 3 columns to legal_region_config:
--      hub_permit_enabled  TINYINT(1) DEFAULT 0
--      hub_permit_cost     DECIMAL(14,2) DEFAULT 500000.00
--      hub_review_minutes  INT UNSIGNED DEFAULT 120
--   2. Creates hub_permit_applications table if missing.
--
-- Po migracji:
--   - hub_permit_enabled domyslnie 0 dla wszystkich regionow (brak wymagania)
--   - Wlacz per region w panelu admina: Admin -> Dzial prawny -> Regiony
--     -> "Pokaz zaawansowane kolumny" -> zaznacz "Zezwolenie na prace lokalne"
--
-- After migration:
--   - hub_permit_enabled defaults to 0 for all regions (no requirement)
--   - Enable per region in admin panel: Admin -> Legal -> Regions
--     -> "Show advanced columns" -> check "Local works permit"
-- ============================================================

-- 1a. Dodaj hub_permit_enabled jesli brak / Add hub_permit_enabled if missing
ALTER TABLE `legal_region_config`
    ADD COLUMN IF NOT EXISTS `hub_permit_enabled` TINYINT(1) NOT NULL DEFAULT 0;

-- 1b. Dodaj hub_permit_cost jesli brak / Add hub_permit_cost if missing
ALTER TABLE `legal_region_config`
    ADD COLUMN IF NOT EXISTS `hub_permit_cost` DECIMAL(14,2) NOT NULL DEFAULT 500000.00;

-- 1c. Dodaj hub_review_minutes jesli brak / Add hub_review_minutes if missing
ALTER TABLE `legal_region_config`
    ADD COLUMN IF NOT EXISTS `hub_review_minutes` INT UNSIGNED NOT NULL DEFAULT 120;

-- 2. Tabela wnioskow o zezwolenia na prace lokalne / Local works permit applications table
CREATE TABLE IF NOT EXISTS `hub_permit_applications` (
    `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `player_id`              INT UNSIGNED NOT NULL,
    `region_id`              INT UNSIGNED NOT NULL,
    `status`                 ENUM('pending','delayed','no_decision','granted','refused')
                                 NOT NULL DEFAULT 'pending',
    `cost`                   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `submitted_at`           DATETIME NULL DEFAULT NULL,
    `decision_due_at`        DATETIME NULL DEFAULT NULL,
    `decided_at`             DATETIME NULL DEFAULT NULL,
    `refusal_cooldown_until` DATETIME NULL DEFAULT NULL,
    `delay_count`            INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_player_region` (`player_id`, `region_id`),
    KEY `idx_status_due`     (`status`, `decision_due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Koniec migracji / End of migration
