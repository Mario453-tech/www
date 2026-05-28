-- ============================================================
-- MIGRACJA: etap8_hub_acquisition_columns.sql
-- Cel: dodanie kolumn modelu akwizycji do logistics_hubs,
--      kolumny access_fee_paid do logistics_hub_assignments,
--      naprawa statusow odwiertow (legacy 'sold' -> 'active').
-- Data: 2026-05-28
-- Idempotentna: bezpieczne wielokrotne uruchomienie.
-- ============================================================

-- ============================================================
-- 1. logistics_hubs: kolumny modelu akwizycji
-- ============================================================

-- acquisition_type: 'new' / 'used' / 'rental'
ALTER TABLE `logistics_hubs`
    ADD COLUMN IF NOT EXISTS `acquisition_type` VARCHAR(16) NOT NULL DEFAULT 'new'
    AFTER `hub_type`;

-- initial_condition_pct: kondycja startowa (uzywana do diagnostyki i acquisition mix)
ALTER TABLE `logistics_hubs`
    ADD COLUMN IF NOT EXISTS `initial_condition_pct` DECIMAL(5,2) NOT NULL DEFAULT 100.00
    AFTER `condition_pct`;

-- last_maintenance_at: data ostatniego przegladu
ALTER TABLE `logistics_hubs`
    ADD COLUMN IF NOT EXISTS `last_maintenance_at` DATETIME NULL DEFAULT NULL
    AFTER `initial_condition_pct`;

-- lease_fee_per_tick: czynsz per slot per tick (tylko dla acquisition_type='rental')
ALTER TABLE `logistics_hubs`
    ADD COLUMN IF NOT EXISTS `lease_fee_per_tick` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    AFTER `opex_per_tick`;

-- ============================================================
-- 2. logistics_hub_assignments: kolumna oplaty dostepowej
-- ============================================================

-- access_fee_paid: jednorazowa oplata dostepowa pobrana przy przypisaniu
ALTER TABLE `logistics_hub_assignments`
    ADD COLUMN IF NOT EXISTS `access_fee_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    AFTER `status`;

-- ============================================================
-- 3. Acquisition mix dla istniejacych hubow systemowych
--    (dziala tylko jesli wszystkie huby sa jeszcze 'new' z lease=0)
--    Rozklad: ~30% rental, ~40% used, ~30% new wg id%10
-- ============================================================

-- Ustaw acquisition_type tylko dla hubow systemowych (player_id = 0),
-- ktore nie byly jeszcze zmienione (acquisition_type = 'new' AND lease_fee_per_tick = 0).
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
-- 4. Naprawa legacy statusow odwiertow: 'sold' -> 'active'
--    Dotyczy odwiertow, ktore zostaly zakupione przez graczy
--    (player_id != 0) ale maja status='sold' z poprzedniej wersji WellShop.
--    Odwierty aktywnie przypisane do hubow sa z pewnoscia czynne.
-- ============================================================

-- Tylko odwierty przypisane do aktywnych hubow lub nalezace do graczy
-- ze statusem 'sold' - to legacy data, powinny byc 'active'.
UPDATE `wells` w
   SET w.`status` = 'active'
 WHERE w.`status` = 'sold'
   AND w.`player_id` != 0
   AND EXISTS (
       SELECT 1
         FROM `logistics_hub_assignments` a
        WHERE a.`well_id` = w.`id`
          AND a.`status`  = 'active'
   );

-- Odwierty z player_id != 0 i status='sold' bez aktywnego przypisania
-- (np. kupione przez gracza ale niepodpiniete) rowniez aktywujemy:
UPDATE `wells`
   SET `status` = 'active'
 WHERE `status` = 'sold'
   AND `player_id` != 0;

-- ============================================================
-- Weryfikacja po migracji
-- ============================================================

-- Sprawdz strukture logistics_hubs:
-- SELECT id, name, acquisition_type, condition_pct, initial_condition_pct, lease_fee_per_tick FROM logistics_hubs LIMIT 10;

-- Sprawdz naprawione odwierty:
-- SELECT id, player_id, status, transport_type FROM wells WHERE player_id != 0;

-- Sprawdz access_fee_paid w przypisaniach:
-- SELECT id, hub_id, well_id, status, access_fee_paid FROM logistics_hub_assignments;
