-- ============================================================
-- MIGRACJA: etap8_hub_acquisition_columns.sql
-- Cel: dodanie kolumn modelu akwizycji do logistics_hubs,
--      kolumny access_fee_paid do logistics_hub_assignments,
--      naprawa statusow odwiertow (legacy 'sold' -> 'active').
-- Data: 2026-05-28
-- MySQL 8.0 compatible.
-- UWAGA: uruchom jednorazowo. Jesli kolumna juz istnieje,
--        MySQL zglosi blad 1060 (Duplicate column) - mozna zignorować.
-- ============================================================


-- 1. logistics_hubs: typ akwizycji (new / used / rental)
ALTER TABLE `logistics_hubs`
    ADD COLUMN `acquisition_type` VARCHAR(16) NOT NULL DEFAULT 'new'
    AFTER `hub_type`;

-- 2. logistics_hubs: kondycja startowa (do diagnostyki i mix seed)
ALTER TABLE `logistics_hubs`
    ADD COLUMN `initial_condition_pct` DECIMAL(5,2) NOT NULL DEFAULT 100.00
    AFTER `condition_pct`;

-- 3. logistics_hubs: data ostatniego przegladu
ALTER TABLE `logistics_hubs`
    ADD COLUMN `last_maintenance_at` DATETIME NULL DEFAULT NULL
    AFTER `initial_condition_pct`;

-- 4. logistics_hubs: czynsz per slot per tick (tylko rental)
ALTER TABLE `logistics_hubs`
    ADD COLUMN `lease_fee_per_tick` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    AFTER `opex_per_tick`;

-- 5. logistics_hub_assignments: jednorazowa oplata dostepowa
ALTER TABLE `logistics_hub_assignments`
    ADD COLUMN `access_fee_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    AFTER `status`;

-- 6. Acquisition mix dla hubow systemowych (player_id = 0).
--    Rozklad: id%10 in {0,1,2}=rental, {3,4,5,6}=used, reszta=new.
--    Huby 'used' startuja ze zdegradowana kondycja (42-72%).
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
           WHEN (`id` % 10) IN (3,4,5,6)
               THEN ROUND(42 + (`id` % 31), 2)
           ELSE `condition_pct`
       END,
       `lease_fee_per_tick` = CASE
           WHEN (`id` % 10) IN (0,1,2) AND `hub_type` = 'small'  THEN 120.00
           WHEN (`id` % 10) IN (0,1,2) AND `hub_type` = 'medium' THEN 220.00
           WHEN (`id` % 10) IN (0,1,2) AND `hub_type` = 'large'  THEN 380.00
           ELSE 0.00
       END
 WHERE `player_id` = 0;

-- 7. Naprawa legacy statusow odwiertow: 'sold' -> 'active'.
--    Odwierty z player_id != 0 i status='sold' to zakupione przez
--    graczy odwierty, ktore nie produkowaly ropy z powodu blednego
--    statusu (WellProductionHandler wymaga 'active'/'paused_*').
UPDATE `wells`
   SET `status` = 'active'
 WHERE `status` = 'sold'
   AND `player_id` != 0;
