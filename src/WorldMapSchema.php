<?php
declare(strict_types=1);

final class WorldMapSchema
{
    public static function ensure(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
        if ($db->inTransaction()) {
            throw new LogicException('Map schema bootstrap must run before transactions.');
        }
        if (self::hasIndex($db)) return;
        try {
            $db->exec('ALTER TABLE wells ADD INDEX idx_wells_location_status (location_id, status)');
        } catch (PDOException $e) {
            // Concurrent bootstrap may have created it. / Rownolegly bootstrap mogl go utworzyc.
            if (!self::hasIndex($db)) throw $e;
        }
    }

    /** @phpstan-impure */
    private static function hasIndex(PDO $db): bool
    {
        $indexes = $db->query('SHOW INDEX FROM wells')->fetchAll(PDO::FETCH_ASSOC);
        $columns = [];
        foreach ($indexes as $index) {
            if ((int)$index['Non_unique'] !== 1) continue;
            $columns[$index['Key_name']][(int)$index['Seq_in_index']] = $index['Column_name'];
        }
        foreach ($columns as $index) {
            if (($index[1] ?? '') === 'location_id' && ($index[2] ?? '') === 'status') return true;
        }
        return false;
    }
}
