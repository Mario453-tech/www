-- Etap 7: jawny wybor transportu i powiazanie rurociagu z hubem
-- Cel:
-- 1. Onshore nie startuje juz z automatycznym rurociagiem.
-- 2. Stare rekordy "rurociag bez pipeline" wracaja do stanu nieustawionego.
-- 3. well_pipelines dostaje hub_id i probuje go uzupelnic z aktywnego przypisania odwiertu.

ALTER TABLE wells
    MODIFY transport_type ENUM('nieustawiony','rurociag','ciezarowki','tankowiec')
    NOT NULL DEFAULT 'nieustawiony'
    COMMENT 'Typ transportu ropy z odwiertu';

SET @q = IF(
    (SELECT COUNT(*)
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'well_pipelines'
        AND COLUMN_NAME  = 'hub_id') = 0,
    'ALTER TABLE well_pipelines
        ADD COLUMN hub_id BIGINT UNSIGNED DEFAULT NULL AFTER well_id',
    'SELECT ''well_pipelines.hub_id already exists'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

UPDATE well_pipelines wp
JOIN logistics_hub_assignments a
  ON a.well_id = wp.well_id
 AND a.status = 'active'
SET wp.hub_id = a.hub_id
WHERE wp.hub_id IS NULL;

UPDATE wells w
LEFT JOIN well_pipelines wp
  ON wp.well_id = w.id
SET w.transport_type = 'nieustawiony',
    w.transport_capacity_pct = 0,
    w.transport_opex_pct = 0
WHERE w.well_type <> 'offshore'
  AND w.transport_type = 'rurociag'
  AND wp.id IS NULL;

UPDATE wells
SET transport_type = 'nieustawiony',
    transport_capacity_pct = 0,
    transport_opex_pct = 0
WHERE well_type <> 'offshore'
  AND (transport_type = '' OR transport_type IS NULL);

UPDATE wells
SET transport_type = 'tankowiec'
WHERE well_type = 'offshore'
  AND (transport_type = '' OR transport_type IS NULL OR transport_type = 'nieustawiony');
