-- Etap 10: drugi odcinek transportu (hub -> magazyn)
-- Cel:
-- 1. well_pipelines.leg rozroznia odcinki: 'inbound' (odwiert->hub), 'outbound' (hub->magazyn).
-- 2. Klucz unikalny zmienia sie z (well_id) na (well_id, leg) - 1 odwiert moze miec oba odcinki.
-- 3. wells.hub_outbound_transport_type przechowuje wybor typu transportu dla odcinka 2.
--
-- Uwaga: te same zmiany sa stosowane idempotentnie w PHP:
--   WellPipelineService::ensureSchema()       (kolumna leg + klucz unikalny)
--   TransportConfigService::ensureTransportSchema() (wells.hub_outbound_transport_type)

-- 1. well_pipelines.leg ------------------------------------------------------
SET @q = IF(
    (SELECT COUNT(*)
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'well_pipelines'
        AND COLUMN_NAME  = 'leg') = 0,
    'ALTER TABLE well_pipelines
        ADD COLUMN leg ENUM(''inbound'',''outbound'') NOT NULL DEFAULT ''inbound'' AFTER hub_id',
    'SELECT ''well_pipelines.leg already exists'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- 2. Klucz unikalny (well_id, leg) -------------------------------------------
SET @q = IF(
    (SELECT COUNT(*)
       FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'well_pipelines'
        AND INDEX_NAME   = 'uq_well_pipeline_well_leg') = 0,
    'ALTER TABLE well_pipelines
        ADD UNIQUE KEY uq_well_pipeline_well_leg (well_id, leg)',
    'SELECT ''uq_well_pipeline_well_leg already exists'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- Usun stary klucz unikalny na samym well_id (jesli istnieje).
SET @q = IF(
    (SELECT COUNT(*)
       FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'well_pipelines'
        AND INDEX_NAME   = 'uq_well_pipeline_well') > 0,
    'ALTER TABLE well_pipelines DROP INDEX uq_well_pipeline_well',
    'SELECT ''uq_well_pipeline_well already removed'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- 3. wells.hub_outbound_transport_type ---------------------------------------
SET @q = IF(
    (SELECT COUNT(*)
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'wells'
        AND COLUMN_NAME  = 'hub_outbound_transport_type') = 0,
    'ALTER TABLE wells
        ADD COLUMN hub_outbound_transport_type
            ENUM(''nieustawiony'',''rurociag'',''ciezarowki'',''tankowiec'')
            NOT NULL DEFAULT ''nieustawiony''
            COMMENT ''Typ transportu z hubu do magazynu (odcinek 2)''
            AFTER transport_type',
    'SELECT ''wells.hub_outbound_transport_type already exists'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;
