-- Tick engine bugfix round 3 migrations
-- B12: wells.technical_condition INT -> DECIMAL(5,1) so sub-1% hourly degradation
--      is not rounded back to the old integer (odwierty nigdy sie nie zuzywaly na MySQL).
--      wells_for_sale.technical_condition too, for consistency with wells.

SET @schema = DATABASE();

-- B12a: wells.technical_condition ------------------------------------------------
SET @is_decimal = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @schema
       AND TABLE_NAME   = 'wells'
       AND COLUMN_NAME  = 'technical_condition'
       AND DATA_TYPE    = 'decimal'
);

SET @sql = IF(
    @is_decimal = 0,
    'ALTER TABLE `wells` MODIFY `technical_condition` DECIMAL(5,1) NOT NULL DEFAULT 100.0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- B12b: wells_for_sale.technical_condition ---------------------------------------
SET @is_decimal_fs = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @schema
       AND TABLE_NAME   = 'wells_for_sale'
       AND COLUMN_NAME  = 'technical_condition'
       AND DATA_TYPE    = 'decimal'
);

SET @sql_fs = IF(
    @is_decimal_fs = 0,
    'ALTER TABLE `wells_for_sale` MODIFY `technical_condition` DECIMAL(5,1) NOT NULL DEFAULT 100.0',
    'SELECT 1'
);
PREPARE stmt_fs FROM @sql_fs;
EXECUTE stmt_fs;
DEALLOCATE PREPARE stmt_fs;
