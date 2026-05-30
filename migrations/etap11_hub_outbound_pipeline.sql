-- Etap 11: odcinek 2 transportu na poziomie huba (zamiast per odwiert)
-- Etap 11: second transport leg moved to hub level (instead of per well)
--
-- 1. logistics_hubs.outbound_transport_type  - typ transportu z huba do magazynu
-- 2. well_pipelines: well_id=0 (sentinel) dla rurociagów hubowych, nowy klucz unikalny (well_id, hub_id, leg)
-- 3. Migracja istniejacych ustawien per-odwiert na poziom huba

-- 1. outbound_transport_type w logistics_hubs ----------------------------------------
SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logistics_hubs' AND COLUMN_NAME = 'outbound_transport_type') = 0,
    "ALTER TABLE logistics_hubs
        ADD COLUMN outbound_transport_type ENUM('nieustawiony','rurociag','ciezarowki')
            NOT NULL DEFAULT 'nieustawiony'
            COMMENT 'Typ transportu z hubu do magazynu (odcinek 2)'
            AFTER work_mode",
    "SELECT 'logistics_hubs.outbound_transport_type already exists' AS info"
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- 2. Migracja ustawien z wells.hub_outbound_transport_type do logistics_hubs ---------
UPDATE logistics_hubs lh
  JOIN (
    SELECT a.hub_id,
           MAX(w.hub_outbound_transport_type) AS otype
      FROM logistics_hub_assignments a
      JOIN wells w ON w.id = a.well_id
     WHERE a.status = 'active'
       AND w.hub_outbound_transport_type != 'nieustawiony'
     GROUP BY a.hub_id
  ) x ON x.hub_id = lh.id
   SET lh.outbound_transport_type = x.otype
 WHERE lh.outbound_transport_type = 'nieustawiony';

-- 3. hub_id NOT NULL w well_pipelines (wymagane dla nowego klucza unikalnego) ---------
SET @q = IF(
    (SELECT IS_NULLABLE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'well_pipelines' AND COLUMN_NAME = 'hub_id') = 'YES',
    'ALTER TABLE well_pipelines MODIFY COLUMN hub_id INT NOT NULL DEFAULT 0',
    "SELECT 'well_pipelines.hub_id already NOT NULL' AS info"
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- Ustaw hub_id=0 tam gdzie NULL (stare wiersze) / Set hub_id=0 where NULL (old rows)
UPDATE well_pipelines SET hub_id = 0 WHERE hub_id IS NULL;

-- 4. Przesuń istniejące outbound rows: ustaw well_id=0 (sentinel dla huba) ----------
-- Najpierw ustaw poprawny hub_id z aktywnego przypisania
UPDATE well_pipelines wp
  JOIN logistics_hub_assignments a ON a.well_id = wp.well_id AND a.status = 'active'
   SET wp.hub_id = a.hub_id
 WHERE wp.leg = 'outbound' AND wp.hub_id = 0;

-- Usuń duplikaty outbound per hub (zostaw z najwyższą condition_pct)
DELETE wp1 FROM well_pipelines wp1
  JOIN well_pipelines wp2 ON wp2.leg = 'outbound'
    AND wp2.hub_id = wp1.hub_id
    AND wp2.condition_pct > wp1.condition_pct
 WHERE wp1.leg = 'outbound';

-- Ustaw well_id=0 dla wszystkich outbound rows
UPDATE well_pipelines SET well_id = 0 WHERE leg = 'outbound' AND well_id != 0;

-- 5. Zmień klucz unikalny: (well_id, leg) -> (well_id, hub_id, leg) -----------------
SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'well_pipelines' AND INDEX_NAME = 'uq_well_pipeline_well_leg') > 0,
    'ALTER TABLE well_pipelines DROP INDEX uq_well_pipeline_well_leg',
    "SELECT 'uq_well_pipeline_well_leg already removed' AS info"
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'well_pipelines' AND INDEX_NAME = 'uq_wp_well_hub_leg') = 0,
    'ALTER TABLE well_pipelines ADD UNIQUE KEY uq_wp_well_hub_leg (well_id, hub_id, leg)',
    "SELECT 'uq_wp_well_hub_leg already exists' AS info"
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;
