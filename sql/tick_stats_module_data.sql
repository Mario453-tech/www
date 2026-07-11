DROP PROCEDURE IF EXISTS oilcorp_add_tick_stats_column;

DELIMITER //
CREATE PROCEDURE oilcorp_add_tick_stats_column(
    IN p_column_name VARCHAR(64),
    IN p_definition TEXT,
    IN p_after_column VARCHAR(64)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'tick_stats'
           AND COLUMN_NAME = p_column_name
    ) THEN
        SET @sql = CONCAT(
            'ALTER TABLE `tick_stats` ADD COLUMN `',
            p_column_name,
            '` ',
            p_definition
        );
        IF p_after_column <> '' THEN
            SET @sql = CONCAT(@sql, ' AFTER `', p_after_column, '`');
        END IF;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

CALL oilcorp_add_tick_stats_column('contracts_processed', 'INT NULL DEFAULT NULL', 'incidents_triggered');
CALL oilcorp_add_tick_stats_column('contracts_revenue_pln', 'DECIMAL(16,2) NULL DEFAULT NULL', 'contracts_processed');
CALL oilcorp_add_tick_stats_column('contracts_penalties_pln', 'DECIMAL(16,2) NULL DEFAULT NULL', 'contracts_revenue_pln');
CALL oilcorp_add_tick_stats_column('tick_sequence', 'BIGINT UNSIGNED NOT NULL DEFAULT 0', 'ran_at');
CALL oilcorp_add_tick_stats_column('module_stats_data', 'LONGTEXT NULL', 'contracts_penalties_pln');
CALL oilcorp_add_tick_stats_column('module_runs_data', 'LONGTEXT NULL', 'module_stats_data');

UPDATE `tick_stats`
   SET `tick_sequence` = 0
 WHERE `tick_sequence` IS NULL;

ALTER TABLE `tick_stats`
  MODIFY COLUMN `tick_sequence` BIGINT UNSIGNED NOT NULL DEFAULT 0;

DELETE t1
  FROM `tick_stats` t1
  JOIN `tick_stats` t2
    ON t1.`ran_at` = t2.`ran_at`
   AND t1.`tick_sequence` = t2.`tick_sequence`
   AND t1.`id` < t2.`id`;

SET @idx_exists = (
    SELECT COUNT(*)
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'tick_stats'
       AND INDEX_NAME = 'idx_ran_at'
);
SET @drop_idx_sql = IF(@idx_exists > 0, 'ALTER TABLE `tick_stats` DROP INDEX `idx_ran_at`', 'SELECT 1');
PREPARE stmt FROM @drop_idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `tick_stats`
  ADD UNIQUE KEY `idx_ran_at` (`ran_at`, `tick_sequence`);

DROP PROCEDURE IF EXISTS oilcorp_add_tick_stats_column;
