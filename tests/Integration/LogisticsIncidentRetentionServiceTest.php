<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LogisticsIncidentRetentionService.php';

final class LogisticsIncidentRetentionServiceTest extends SqliteIntegrationTestCase
{
    public function testBacklogContinuesAcrossBatchesWithoutRemovingNewerEvents(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE logistics_hub_events (id INTEGER PRIMARY KEY, event_type TEXT, created_at TEXT)');
        $db->exec('CREATE TABLE well_pipeline_events (id INTEGER PRIMARY KEY, event_type TEXT, created_at TEXT)');
        $insert = $db->prepare('INSERT INTO logistics_hub_events VALUES (?, ?, ?)');
        for ($id = 1; $id <= 505; $id++) {
            $insert->execute([$id, 'hub_incident_leak', '2026-09-27 10:00:00']);
        }
        $insert->execute([506, 'hub_incident_leak', '2026-09-29 10:00:00']);

        $service = new LogisticsIncidentRetentionService($db);
        self::assertSame(['hub' => 505, 'pipeline' => 0], $service->pruneBefore('2026-09-28 00:00:00'));
        self::assertSame([506], array_map('intval', $db->query('SELECT id FROM logistics_hub_events')->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testOnlyLogisticsIncidentHistoryOlderThanFortyEightHoursIsRemoved(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE logistics_hub_events (id INTEGER PRIMARY KEY, player_id INTEGER, event_type TEXT, created_at TEXT)');
        $db->exec('CREATE TABLE well_pipeline_events (id INTEGER PRIMARY KEY, player_id INTEGER, event_type TEXT, created_at TEXT)');
        $db->exec("INSERT INTO logistics_hub_events VALUES
            (1, 7, 'hub_incident_leak', '2026-09-27 23:59:59'),
            (2, 8, 'hub_incident_damage', '2026-09-27 11:00:00'),
            (3, 7, 'hub_incident_leak', '2026-09-28 00:00:00'),
            (4, 7, 'hub_built', '2026-09-27 11:00:00'),
            (5, 7, 'hub_incidentXother', '2026-09-27 11:00:00')");
        $db->exec("INSERT INTO well_pipeline_events VALUES
            (1, 7, 'incident', '2026-09-27 23:59:59'),
            (2, 8, 'incident', '2026-09-27 12:00:00'),
            (3, 7, 'incident', '2026-09-28 00:00:00'),
            (4, 7, 'pipeline_build_started', '2026-09-27 11:00:00')");

        $service = new LogisticsIncidentRetentionService($db);
        self::assertSame(['hub' => 2, 'pipeline' => 2], $service->pruneBefore('2026-09-28 00:00:00'));
        self::assertSame([3, 4, 5], array_map('intval', $db->query('SELECT id FROM logistics_hub_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame([3, 4], array_map('intval', $db->query('SELECT id FROM well_pipeline_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(['hub' => 0, 'pipeline' => 0], $service->pruneBefore('2026-09-28 00:00:00'));
    }
}
