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
--
--    ADD COLUMN IF NOT EXISTS to skladnia MariaDB — niedostepna w MySQL 8.
--    Uzywamy information_schema + PREPARE/EXECUTE (MySQL 8 compatible).
SET @exists_col = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name   = 'staff_trainings'
      AND column_name  = 'active_guard'
);
SET @sql_col = IF(@exists_col = 0,
    'ALTER TABLE `staff_trainings` ADD COLUMN `active_guard` TINYINT NULL DEFAULT NULL COMMENT "Enrollment guard: 1 when in_progress, NULL when finished."',
    'SELECT 1'
);
PREPARE stmt_col FROM @sql_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- Dodaj klucz tylko jesli nie istnieje (bezpieczne przy wielokrotnym uruchomieniu).
-- Add key only if missing (safe to run multiple times).
SET @exists_idx = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_trainings'
      AND index_name = 'uq_active_staff'
);
SET @sql_idx = IF(@exists_idx = 0,
    'ALTER TABLE `staff_trainings` ADD UNIQUE KEY `uq_active_staff` (`staff_type`, `staff_id`, `active_guard`)',
    'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- 2. Backfill: usun duplikaty in_progress przed dodaniem UNIQUE KEY.
--    Jesli istnieja dwa active rows dla tego samego (staff_type, staff_id),
--    ADD UNIQUE KEY powyzej by sie nie powiodl. Anulujemy starsze, zostawiamy najnowszy.
--    Dedup in_progress before backfill: cancel all but the most recent active row per staff pair.
UPDATE `staff_trainings` st
INNER JOIN (
    SELECT MIN(id) AS cancel_id
    FROM `staff_trainings`
    WHERE `status` = 'in_progress'
    GROUP BY `staff_type`, `staff_id`
    HAVING COUNT(*) > 1
) dups ON st.id = dups.cancel_id
SET st.`status` = 'cancelled', st.`active_guard` = NULL
WHERE st.`status` = 'in_progress';

--    Zakonczone szkolenia maja active_guard = NULL (domyslne, juz OK).
--    Aktywne szkolenia (in_progress) — ustaw active_guard = 1.
--    Finished trainings keep active_guard = NULL (already default). Set active ones to 1.
UPDATE `staff_trainings` SET `active_guard` = 1 WHERE `status` = 'in_progress' AND `active_guard` IS NULL;
