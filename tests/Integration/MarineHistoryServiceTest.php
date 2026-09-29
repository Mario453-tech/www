<?php
declare(strict_types=1);
require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/MarineHistoryService.php';
final class MarineHistoryServiceTest extends SqliteIntegrationTestCase
{
    public function testPaginationIsolationStableOrderAndRetention(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE marine_deliveries (id INTEGER PRIMARY KEY, player_id INTEGER, status TEXT, port_id INTEGER, well_id INTEGER, delivered_at TEXT, arrived_at TEXT, eta_at TEXT, created_at TEXT)');
        $db->exec('CREATE TABLE ports (id INTEGER PRIMARY KEY, name TEXT)');
        $db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, player_id INTEGER, name TEXT, location_name TEXT)');
        $insert = $db->prepare('INSERT INTO marine_deliveries VALUES (?, ?, ?, NULL, NULL, ?, NULL, NULL, ?)');
        for ($id = 1; $id <= 12; $id++) $insert->execute([$id, 1, 'delivered', '2026-09-30 12:00:00', '2026-09-25 12:00:00']);
        $insert->execute([20, 2, 'lost', '2026-09-30 12:00:00', '2026-09-25 12:00:00']);
        $insert->execute([21, 1, 'waiting_for_port', null, '2026-09-25 12:00:00']);
        $service = new MarineHistoryService($db);
        $first = $service->page(1, 1);
        $this->assertSame(12, $first['total']);
        $this->assertSame(3, $first['pages']);
        $this->assertSame([12,11,10,9,8], array_column($first['items'], 'id'));
        $this->assertSame([7,6,5,4,3], array_column($service->page(1, 2)['items'], 'id'));
        $this->assertSame([2,1], array_column($service->page(1, 999)['items'], 'id'));
        $insert->execute([22, 1, 'lost', '2026-09-28 11:59:59', '2026-09-25 12:00:00']);
        $insert->execute([23, 1, 'delivered', '2026-09-28 12:00:00', '2026-09-25 12:00:00']);
        $this->assertSame(1, $service->pruneBefore('2026-09-28 12:00:00'));
        $this->assertSame(1, (int)$db->query('SELECT COUNT(*) FROM marine_deliveries WHERE id=21')->fetchColumn());
        $this->assertSame(1, (int)$db->query('SELECT COUNT(*) FROM marine_deliveries WHERE id=23')->fetchColumn());
        $this->assertSame(0, $service->pruneBefore('2026-09-28 12:00:00'));
    }
}
