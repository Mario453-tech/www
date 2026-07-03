-- Tick engine bugfix round 4 migrations (idempotent — safe to run multiple times)
--
-- C5: well_pipelines.condition_pct DECIMAL(6,2) -> DECIMAL(8,4)
--     Degradacja przy tickach 5-min (0.0017-0.0042/tick) ginela w precyzji 2 miejsc
--     i rurociagi nigdy sie nie zuzywaly. / 5-min tick degradation was lost to 2-decimal
--     precision, so pipelines never wore out.
-- H5: wells.ticks_since_incident DEFAULT 999 -> 0 + wyzerowanie odwiertow bez historii
--     non-micro incydentow (nowe odwierty startowaly z maksymalna presja = 2x szansa
--     incydentu). / New wells started at the pressure cap = doubled incident chance.
-- H7: refund + usuniecie martwych wierszy well_pipelines (well_id>0, leg='outbound') —
--     zakupy przez buy_pipeline z leg=outbound tworzyly rurociagi, ktorych zaden kod
--     ticku nie czyta. / Purchases via buy_pipeline with leg=outbound created rows no
--     tick code ever reads; refund build_cost and delete them.

SET @schema = DATABASE();

-- C5: well_pipelines.condition_pct -> DECIMAL(8,4) -------------------------------
SET @needs_precision = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @schema
       AND TABLE_NAME   = 'well_pipelines'
       AND COLUMN_NAME  = 'condition_pct'
       AND (NUMERIC_PRECISION < 8 OR NUMERIC_SCALE < 4)
);

SET @sql_c5 = IF(
    @needs_precision > 0,
    'ALTER TABLE `well_pipelines` MODIFY `condition_pct` DECIMAL(8,4) NOT NULL DEFAULT 100.0000',
    'SELECT 1'
);
PREPARE stmt FROM @sql_c5;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- H5a: wells.ticks_since_incident DEFAULT 999 -> 0 --------------------------------
SET @has_999_default = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @schema
       AND TABLE_NAME   = 'wells'
       AND COLUMN_NAME  = 'ticks_since_incident'
       AND COLUMN_DEFAULT = '999'
);

SET @sql_h5 = IF(
    @has_999_default > 0,
    'ALTER TABLE `wells` ALTER COLUMN `ticks_since_incident` SET DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql_h5;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- H5b: wyzeruj sentinel 999 dla odwiertow bez non-micro historii (idempotentne) ----
-- H5b: zero the 999 sentinel for wells with no non-micro incident history (idempotent)
UPDATE `wells` w
   SET w.`ticks_since_incident` = 0
 WHERE w.`ticks_since_incident` = 999
   AND NOT EXISTS (
        SELECT 1 FROM `well_incidents` wi
         WHERE wi.`well_id` = w.`id` AND wi.`level` <> 'micro'
   );

-- H7a: refund build_cost martwych outbound rurociagow odwiertow (idempotentne przez
--      DELETE w H7b — po usunieciu wierszy refund nie znajdzie juz nic do zwrotu).
-- H7a: refund build_cost of dead well-keyed outbound pipelines (idempotent because
--      H7b deletes the rows — a re-run finds nothing left to refund).
UPDATE `players` p
  JOIN (
        SELECT `player_id`, SUM(`build_cost`) AS refund
          FROM `well_pipelines`
         WHERE `well_id` > 0 AND `leg` = 'outbound'
         GROUP BY `player_id`
       ) dead ON dead.`player_id` = p.`id`
   SET p.`cash` = p.`cash` + dead.refund;

-- H7b: usun martwe wiersze / delete the dead rows ---------------------------------
DELETE FROM `well_pipelines` WHERE `well_id` > 0 AND `leg` = 'outbound';

-- M2: data-fix placeholderow przepustowosci (wczesne zakupy zapisywaly 1.00 bph).
--     Od tej rundy leg-1 egzekwuje absolutny cap real_capacity_bph * deltaHours,
--     wiec placeholder 1.00 zdusilby przeplyw do 1 bbl/h. Przelicz z produkcji
--     bazowej odwiertu i capacity_pct typu (light 100%, standard 120%, heavy 150%).
-- M2: capacity placeholder data-fix (early purchases stored 1.00 bph). This round
--     enforces the absolute leg-1 cap real_capacity_bph * deltaHours, so a 1.00
--     placeholder would throttle flow to 1 bbl/h. Recompute from the well's base
--     production and the type's capacity_pct (light 100%, standard 120%, heavy 150%).
UPDATE `well_pipelines` wp
  JOIN `wells` w ON w.`id` = wp.`well_id`
   SET wp.`nominal_capacity_bph` = GREATEST(1.0, ROUND(w.`base_production_per_hour` *
        CASE wp.`pipeline_type` WHEN 'heavy' THEN 1.50 WHEN 'standard' THEN 1.20 ELSE 1.00 END, 2)),
       wp.`real_capacity_bph` = GREATEST(1.0, ROUND(w.`base_production_per_hour` *
        CASE wp.`pipeline_type` WHEN 'heavy' THEN 1.50 WHEN 'standard' THEN 1.20 ELSE 1.00 END, 2))
 WHERE wp.`leg` = 'inbound'
   AND wp.`real_capacity_bph` <= 1.0
   AND w.`base_production_per_hour` > 1.0;
