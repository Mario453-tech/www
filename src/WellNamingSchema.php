<?php
declare(strict_types=1);

final class WellNamingSchema
{
    public static function ensure(PDO $db): void
    {
        $sql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'CREATE TABLE IF NOT EXISTS well_name_counters (
                player_id INT NOT NULL,
                region_id INT NOT NULL,
                last_number INT UNSIGNED NOT NULL,
                PRIMARY KEY (player_id, region_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            : 'CREATE TABLE IF NOT EXISTS well_name_counters (
                player_id INTEGER NOT NULL,
                region_id INTEGER NOT NULL,
                last_number INTEGER NOT NULL,
                PRIMARY KEY (player_id, region_id)
            )';
        $db->exec($sql);
    }
}
