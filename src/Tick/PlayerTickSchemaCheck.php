<?php
declare(strict_types=1);

final class PlayerTickSchemaCheck
{
    /** @var WeakMap<PDO, bool>|null */
    private static ?WeakMap $checked = null;

    public static function assertTransactional(PDO $db): void
    {
        self::$checked ??= new WeakMap();
        if (isset(self::$checked[$db]) || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }
        $tables = ['players', 'storage', 'wells', 'well_pipelines', 'well_road_trips',
            'port_queue', 'marine_deliveries', 'finance_logs', 'bank_transactions',
            'technical_tasks', 'technical_task_queue', 'technical_staff', 'technical_notifications',
            'industrial_disasters', 'failure_log', 'logistics_hubs', 'logistics_hub_events',
            'logistics_hub_tick_stats', 'player_meta', 'offline_reports'];
        $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND ENGINE <> ? AND TABLE_NAME IN ('
            . implode(',', array_fill(0, count($tables), '?')) . ')');
        $stmt->execute(['InnoDB', ...$tables]);
        $unsafe = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($unsafe !== []) {
            // Never convert live tables silently; nigdy nie konwertuj tabel produkcyjnych po cichu.
            throw new RuntimeException('Player tick requires transactional tables: ' . implode(', ', $unsafe));
        }
        self::$checked[$db] = true;
    }
}
