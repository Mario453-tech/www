<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseTestCase.php';
final class WellHistoryTest extends BaseTestCase
{
    private function createDatabase(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('CREATE TABLE wells (id INTEGER, player_id INTEGER); CREATE TABLE well_events (id INTEGER, well_id INTEGER, player_id INTEGER, event_type TEXT, cost REAL, description TEXT, technical_condition_before INTEGER, technical_condition_after INTEGER, created_at TEXT)');
        $db->exec('INSERT INTO wells VALUES(1,7),(2,8)');
        return $db;
    }

    public function testHistoryIsOwnerScopedFilteredAndPaginated(): void
    {
        $file = dirname(__DIR__, 2) . '/src/WellHistoryService.php';
        self::assertFileExists($file);
        require_once $file;
        $db = $this->createDatabase();
        $stmt = $db->prepare("INSERT INTO well_events VALUES(?,1,7,'upgrade',100,'legacy internal text',50,60,'2026-10-10 12:00:00')");
        for ($i = 1; $i <= 25; $i++) $stmt->execute([$i]);
        $db->exec("INSERT INTO well_events VALUES(26,1,8,'failure',0,'foreign event',0,0,'2026-10-10 12:00:00')");
        $service = new WellHistoryService($db);
        $now = new DateTimeImmutable('2026-10-11 12:00:00');
        $page = $service->page(7, 1, 'configuration', 1, $now);
        self::assertCount(20, $page['events']);
        self::assertTrue($page['has_more']);
        self::assertSame(2, $page['total_pages']);
        self::assertSame(25, $page['total_events']);
        self::assertSame(25, (int)$page['events'][0]['id']);
        self::assertCount(5, $service->page(7, 1, 'configuration', 2, $now)['events']);
        self::assertSame([], $service->page(7, 1, 'failures', 1, $now)['events']);
        self::assertSame([], $service->page(7, 1, 'transport', 1, $now)['events']);
        self::assertNull($service->page(8, 1, 'all', 1, $now));
    }

    public function testHistoryKeepsTheFortyEightHourBoundaryAndClampsStalePages(): void
    {
        require_once dirname(__DIR__, 2) . '/src/WellHistoryService.php';
        $db = $this->createDatabase();
        $db->exec("INSERT INTO well_events VALUES
            (1,1,7,'maintenance',0,'expired',50,50,'2026-10-09 11:59:59'),
            (2,1,7,'maintenance',0,'boundary',50,50,'2026-10-09 12:00:00'),
            (3,1,7,'maintenance',0,'fresh',50,50,'2026-10-11 11:59:59')");
        $page = (new WellHistoryService($db))->page(7, 1, 'all', 99, new DateTimeImmutable('2026-10-11 12:00:00'));
        self::assertSame(1, $page['page']);
        self::assertSame(1, $page['total_pages']);
        self::assertSame(2, $page['total_events']);
        self::assertSame([3, 2], array_map('intval', array_column($page['events'], 'id')));
    }

    public function testHistoryUsesTheDatabaseClockWhenNoReferenceTimeIsInjected(): void
    {
        require_once dirname(__DIR__, 2) . '/src/WellHistoryService.php';
        $db = $this->createDatabase();
        $db->exec("INSERT INTO well_events VALUES
            (1,1,7,'maintenance',0,'expired',50,50,datetime(CURRENT_TIMESTAMP, '-49 hours')),
            (2,1,7,'maintenance',0,'fresh',50,50,datetime(CURRENT_TIMESTAMP, '-47 hours'))");
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Pacific/Kiritimati');
        try {
            $page = (new WellHistoryService($db))->page(7, 1, 'all', 1);
        } finally {
            date_default_timezone_set($previousTimezone);
        }
        self::assertSame([2], array_map('intval', array_column($page['events'], 'id')));
    }
}
