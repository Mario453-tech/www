<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseTestCase.php';
final class WellHistoryTest extends BaseTestCase
{
    public function testHistoryIsOwnerScopedFilteredAndPaginated(): void
    {
        $file = dirname(__DIR__, 2) . '/src/WellHistoryService.php';
        self::assertFileExists($file);
        require_once $file;
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('CREATE TABLE wells (id INTEGER, player_id INTEGER); CREATE TABLE well_events (id INTEGER, well_id INTEGER, player_id INTEGER, event_type TEXT, cost REAL, description TEXT, technical_condition_before INTEGER, technical_condition_after INTEGER, created_at TEXT)');
        $db->exec('INSERT INTO wells VALUES(1,7),(2,8)');
        $stmt = $db->prepare("INSERT INTO well_events VALUES(?,1,7,'upgrade',100,'legacy internal text',50,60,'2026-01-01 12:00:00')");
        for ($i = 1; $i <= 25; $i++) $stmt->execute([$i]);
        $db->exec("INSERT INTO well_events VALUES(26,1,8,'failure',0,'foreign event',0,0,'2026-01-02 12:00:00')");
        $service = new WellHistoryService($db);
        $page = $service->page(7, 1, 'configuration', 1);
        self::assertCount(20, $page['events']);
        self::assertTrue($page['has_more']);
        self::assertSame(25, (int)$page['events'][0]['id']);
        self::assertCount(5, $service->page(7, 1, 'configuration', 2)['events']);
        self::assertSame([], $service->page(7, 1, 'failures', 1)['events']);
        self::assertSame([], $service->page(7, 1, 'transport', 1)['events']);
        self::assertNull($service->page(8, 1, 'all', 1));
    }
}
