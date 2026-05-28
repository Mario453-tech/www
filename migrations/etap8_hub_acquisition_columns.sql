-- ============================================================
-- MIGRACJA: etap8_hub_acquisition_columns.sql
-- Cel: dodanie kolumn modelu akwizycji do logistics_hubs,
--      kolumny access_fee_paid do logistics_hub_assignments,
--      naprawa statusow odwiertow (legacy 'sold' -> 'active').
-- Data: 2026-05-28
-- Idempotentna: bezpieczne wielokrotne uruchomienie.
-- MySQL 8.0 compatible (bez ADD COLUMN IF NOT EXISTS).
-- ============================================================

-- ============================================================
-- 1. logistics_hubs: acquisition_type
-- ============================================================

SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'logistics_hubs'
           AND COLUMN_NAME  = 'acquisition_type'
    ),
    'SELECT ''acquisition_type already exists''',
    "ALTER TABLE `logistics_hubs` ADD COLUMN `acquisition_type` VARCHAR(16) NOT NULL DEFAULT 'new' AFTER `hub_type`"
) INTO @_sql;
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- 2. logistics_hubs: initial_condition_pct
-- ============================================================

SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'logistics_hubs'
           AND COLUMN_NAME  = 'initial_condition_pct'
    ),
    'SELECT ''initial_condition_pct already exists''',
    'ALTER TABLE `logistics_hubs` ADD COLUMN `initial_condition_pct` DECIMAL(5,2) NOT NULL DEFAULT 100.00 AFTER `condition_pct`'
) INTO @_sql;
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- 3. logistics_hubs: last_maintenance_at
-- ============================================================

SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'logistics_hubs'
           AND COLUMN_NAME  = 'last_maintenance_at'
    ),
    'SELECT ''last_maintenance_at already exists''',
    'ALTER TABLE `logistics_hubs` ADD COLUMN `last_maintenance_at` DATETIME NULL DEFAULT NULL AFTER `initial_condition_pct`'
) INTO @_sql;
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- 4. logistics_hubs: lease_fee_per_tick
-- ============================================================

SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'logistics_hubs'
           AND COLUMN_NAME  = 'lease_fee_per_tick'
    ),
    'SELECT ''lease_fee_per_tick already exists''',
    'ALTER TABLE `logistics_hubs` ADD COLUMN `lease_fee_per_tick` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `opex_per_tick`'
) INTO @_sql;
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- 5. logistics_hub_assignments: access_fee_paid
-- ============================================================

SELECT IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'logistics_hub_assignments'
           AND COLUMN_NAME  = 'access_fee_paid'
    ),
    'SELECT ''access_fee_paid already exists''',
    'ALTER TABLE `logistics_hub_assignments` ADD COLUMN `access_fee_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `status`'
) INTO @_sql;
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- 6. Acquisition mix dla istniejacych hubow systemowych
--    Rozklad: id%10 in {0,1,2} => rental, {3,4,5,6} => used, reszta => new
--    Uruchamia sie tylko gdy wszystkie huby sa jeszcze 'new' z lease=0.
-- ============================================================

UPDATE `logistics_hubs`
   SET
       `acquisition_type` = CASE
           WHEN (`id` % 10) IN (0,1,2) THEN 'rental'
           WHEN (`id` % 10) IN (3,4,5,6) THEN 'used'
           ELSE 'new'
       END,
       `initial_condition_pct` = CASE
           WHEN (`id` % 10) IN (3,4,5,6) THEN ROUND(42 + (`id` % 31), 2)
           ELSE 100.00
       END,
       `condition_pct` = CASE
           WHEN (`id` % 10) IN (3,4,5,6) AND `condition_pct` = 100.00
               THEN ROUND(42 + (`id` % 31), 2)
           ELSE `condition_pct`
       END,
       `lease_fee_per_tick` = CASE
           WHEN (`id` % 10) IN (0,1,2) AND `hub_type` = 'small'  THEN 120.00
           WHEN (`id` % 10) IN (0,1,2) AND `hub_type` = 'medium' THEN 220.00
           WHEN (`id` % 10) IN (0,1,2) AND `hub_type` = 'large'  THEN 380.00
           ELSE 0.00
       END
 WHERE `player_id` = 0
   AND `acquisition_type` = 'new'
   AND `lease_fee_per_tick` = 0.00;

-- ============================================================
-- 7. Naprawa legacy statusow odwiertow: 'sold' -> 'active'
--    Odwierty kupione przez graczy (player_id != 0) z legacy
--    statusem 'sold' nie produkowaly ropy (brak w liscie aktywnych
--    statusow w WellProductionHandler), co skutkowalo 0 bbl do
--    hubow i brakiem incydentow od ~17 maja.
-- ============================================================

UPDATE `wells`
   SET `status` = 'active'
 WHERE `status` = 'sold'
   AND `player_id` != 0;

-- ============================================================
-- Weryfikacja po migracji (odkomentuj i uruchom oddzielnie)
-- ============================================================

-- SELECT id, name, hub_type, acquisition_type, ROUND(condition_pct,1) AS cond,
--        ROUND(lease_fee_per_tick,2) AS lease_fee
--   FROM logistics_hubs LIMIT 20;

-- SELECT id, player_id, status, transport_type
--   FROM wells WHERE player_id != 0;

-- SELECT id, hub_id, well_id, status, access_fee_paid
--   FROM logistics_hub_assignments;
