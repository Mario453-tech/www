<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/AdminLogs/TickHistoryQuery.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickStatsRepository.php';

final class MySqlTickHistoryQueryTest extends PHPUnit\Framework\TestCase
{
    public function testHistoryUsesNativePreparedQueriesWithoutDiagnosticPayloads(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $db = new PDO('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4', $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        (new ReflectionProperty(TickStatsRepository::class, 'schemaEnsured'))->setValue(null, []);
        new TickStatsRepository($db);
        // Shadow the table on this connection; never change existing records.
        // Zaslon tabele na tym polaczeniu; nigdy nie zmieniaj istniejacych rekordow.
        $schema = $db->query('SHOW CREATE TABLE tick_stats')->fetch(PDO::FETCH_NUM)[1];
        $db->exec(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema));
        $db->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
        $insert = $db->prepare('INSERT INTO tick_stats (ran_at, tick_sequence, source, duration_ms, total_production_bbl, module_stats_data, module_runs_data) VALUES (NOW(), ?, ?, ?, 2, ?, ?)');
        for ($i = 1; $i <= 105; $i++) {
            $insert->execute([$i, $i % 2 ? 'cron' : 'force', $i, str_repeat('x', 10000), str_repeat('y', 10000)]);
        }
        $db->exec("INSERT INTO tick_stats (ran_at, source, duration_ms, total_production_bbl) VALUES ('2000-01-01', 'cron_http', 999999, 999999)");
        $query = new TickHistoryQuery($db);
        $first = $query->page('', 1);
        $second = $query->page('', 2);
        self::assertSame(106, $first['total']);
        self::assertCount(50, $first['rows']);
        self::assertSame(105, (int)$first['rows'][0]['tick_sequence']);
        self::assertSame([], array_intersect(array_column($first['rows'], 'id'), array_column($second['rows'], 'id')));
        self::assertArrayNotHasKey('module_stats_data', $first['rows'][0]);
        self::assertArrayNotHasKey('module_runs_data', $first['rows'][0]);
        $last = $query->page('force', PHP_INT_MAX);
        self::assertSame(52, $last['total']);
        self::assertSame([4, 2], array_map('intval', array_column($last['rows'], 'tick_sequence')));
        self::assertSame(1, $query->page('cron_http', 1)['total']);
        $summary = $query->summary24h();
        self::assertSame(105, (int)$summary['tick_count']);
        self::assertSame(210.0, (float)$summary['total_bbl']);
        self::assertSame(105, (int)$summary['max_duration_ms']);
    }
}
