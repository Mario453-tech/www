-- ============================================================
-- PATCH: training_v2_fixes.sql
-- Cel: poprawki po code review — uruchom na istniejacych bazach
--      jesli training_module.sql zostal juz zastosowany.
-- Data: 2026-06-23
-- MySQL 8.0 compatible.
-- UWAGA: uruchom jednorazowo.
-- ============================================================

-- 1. Kolumna active_guard + UNIQUE KEY na staff_trainings
--    Zapobiega rownoleglelmu podwojnemu zapisowi na kurs dla tego samego pracownika.
--    Prevents concurrent double-enrollment for the same staff member.
ALTER TABLE `staff_trainings`
    ADD COLUMN IF NOT EXISTS `active_guard` TINYINT NULL DEFAULT NULL
        COMMENT 'Enrollment guard: 1 when in_progress, NULL when finished.';

-- Dodaj klucz tylko jesli nie istnieje (bezpieczne przy wielokrotnym uruchomieniu).
-- Add key only if missing (safe to run multiple times).
SET @exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_trainings'
      AND index_name = 'uq_active_staff'
);
SET @sql = IF(@exists = 0,
    'ALTER TABLE `staff_trainings` ADD UNIQUE KEY `uq_active_staff` (`staff_type`, `staff_id`, `active_guard`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Backfill: zakonczone szkolenia powinny miec active_guard = NULL (domyslne, juz OK).
--    Aktywne szkolenia (in_progress) powinny miec active_guard = 1.
--    Backfill: finished trainings keep active_guard = NULL (already default). Set active ones to 1.
UPDATE `staff_trainings` SET `active_guard` = 1 WHERE `status` = 'in_progress' AND `active_guard` IS NULL;
