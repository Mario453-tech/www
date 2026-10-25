<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/IncidentService.php';

final class TechnicalIncidentFilterTest extends SqliteIntegrationTestCase
{
    public function testStatusFilterCountsAndPagesOnlyMatchingPlayerIncidents(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, well_name TEXT, location_name TEXT)');
        $db->exec('CREATE TABLE well_incidents (
            id INTEGER PRIMARY KEY, well_id INTEGER, player_id INTEGER, repaired_at TEXT,
            auto_repair INTEGER, created_at TEXT
        )');
        $db->exec("INSERT INTO wells VALUES (10, 'E-10', 'Pole 10')");
        $db->exec("INSERT INTO well_incidents VALUES
            (1,10,1,NULL,0,'2026-10-01 10:00:00'),
            (2,10,1,NULL,1,'2026-10-02 10:00:00'),
            (3,10,1,'2026-10-03 10:00:00',0,'2026-10-03 10:00:00'),
            (4,10,2,NULL,0,'2026-10-04 10:00:00'),
            (5,10,1,NULL,0,'2026-10-05 10:00:00')");
        $svc = (new ReflectionClass(IncidentService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(IncidentService::class, 'db'))->setValue($svc, $db);

        self::assertSame(4, $svc->countPlayerIncidents(1));
        self::assertSame(2, $svc->countPlayerIncidents(1, 'active'));
        self::assertSame(2, $svc->countPlayerIncidents(1, 'resolved'));
        self::assertSame([5], array_map('intval', array_column($svc->getPlayerIncidents(1, 1, 0, 'active'), 'id')));
        self::assertSame([1], array_map('intval', array_column($svc->getPlayerIncidents(1, 1, 1, 'active'), 'id')));
        self::assertSame([3, 2], array_map('intval', array_column($svc->getPlayerIncidents(1, 10, 0, 'resolved'), 'id')));
    }
}