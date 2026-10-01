<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Unit/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickStatsRepository.php';

final class TickHistoryQueryTest extends BaseTestCase
{
    private function database(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Reset the writer's connection-id cache for fresh test databases.
        // Wyzeruj cache identyfikatorow polaczen dla nowych baz testowych.
        (new ReflectionProperty(TickStatsRepository::class, 'schemaEnsured'))->setValue(null, []);
        new TickStatsRepository($db);
        $stmt = $db->prepare('INSERT INTO tick_stats (ran_at, tick_sequence, source, duration_ms, total_production_bbl, module_stats_data, module_runs_data) VALUES (?, ?, ?, ?, ?, ?, ?)');
        for ($i = 1; $i <= 105; $i++) {
            $stmt->execute(['2099-01-01 12:00:00', $i, $i % 2 ? 'cron' : 'force', $i, 2, str_repeat('x', 10000), str_repeat('y', 10000)]);
        }
        return $db;
    }

    private function query(PDO $db): object
    {
        require_once dirname(__DIR__, 2) . '/src/AdminLogs/TickHistoryQuery.php';
        return new TickHistoryQuery($db);
    }

    public function testHistoryWorksWithReadOnlyDatabaseAndOmitsDiagnosticPayloads(): void
    {
        $db = $this->database();
        $db->exec('PRAGMA query_only = ON');
        $query = $this->query($db);
        $page = $query->page('', 1);
        self::assertSame(105, $page['total']);
        self::assertSame(3, $page['pages']);
        self::assertCount(50, $page['rows']);
        self::assertSame(105, (int)$page['rows'][0]['tick_sequence']);
        self::assertArrayNotHasKey('module_stats_data', $page['rows'][0]);
        self::assertArrayNotHasKey('module_runs_data', $page['rows'][0]);
        self::assertSame(105, (int)$query->summary24h()['tick_count']);
    }

    public function testFiltersPaginationAndLastPageClamping(): void
    {
        $query = $this->query($this->database());
        $page = $query->page('force', PHP_INT_MAX);
        self::assertSame(52, $page['total']);
        self::assertSame(2, $page['page']);
        self::assertCount(2, $page['rows']);
        self::assertSame([4, 2], array_map('intval', array_column($page['rows'], 'tick_sequence')));
        self::assertSame(['force'], array_values(array_unique(array_column($page['rows'], 'source'))));
        self::assertSame(105, $query->page("' OR 1=1 --", 1)['total']);
        self::assertSame(0, $query->page('cron_http', 1)['total']);
    }

    public function testSummaryExcludesOldEntriesAndReturnsRealTotals(): void
    {
        $db = $this->database();
        $db->exec("INSERT INTO tick_stats (ran_at, source, duration_ms, total_production_bbl) VALUES ('2000-01-01', 'cron', 999999, 999999)");
        $summary = $this->query($db)->summary24h();
        self::assertSame(105, (int)$summary['tick_count']);
        self::assertSame(105, (int)$summary['max_duration_ms']);
        self::assertSame(210.0, (float)$summary['total_bbl']);
    }
}
