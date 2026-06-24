-- Tick engine bugfix round 2 migrations
-- C12: UNIQUE KEY on tick_stats.ran_at to make INSERT IGNORE race-safe
-- C13: offline_frozen column on players to track freeze state across ticks

-- C12 -------------------------------------------------------------------
-- idx_ran_at byl KEY (nieunikalny); zastepujemy go UNIQUE KEY.
-- idx_ran_at was a non-unique KEY; replace it with UNIQUE KEY.
SET @schema = DATABASE();

SET @has_unique = (
    SELECT COUNT(*)
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @schema
       AND TABLE_NAME   = 'tick_stats'
       AND INDEX_NAME   = 'idx_ran_at'
       AND NON_UNIQUE   = 0
);

SET @sql = IF(
    @has_unique = 0,
    'ALTER TABLE tick_stats DROP INDEX IF EXISTS idx_ran_at, ADD UNIQUE KEY idx_ran_at (ran_at)',
    'SELECT 1 /* idx_ran_at already UNIQUE */'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- C13 -------------------------------------------------------------------
-- Kolumna offline_frozen: 1 gdy przynajmniej jeden tick w biezacej sesji offline
-- byl w freeze mode (gotowka = 0). Resetowana do 0 gdy gracz wraca online.
-- Column offline_frozen: 1 when at least one tick in the current offline session
-- was in freeze mode (cash = 0). Reset to 0 when the player comes back online.
SET @col_exists = (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @schema
       AND TABLE_NAME   = 'players'
       AND COLUMN_NAME  = 'offline_frozen'
);

SET @sql2 = IF(
    @col_exists = 0,
    'ALTER TABLE players ADD COLUMN offline_frozen TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1 /* offline_frozen already exists */'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
