<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubIncidentService.php';

final class MySqlHubIncidentServiceTest extends MySqlIntegrationTestCase
{
    public function testHseProtectionLowersHubIncidentRiskOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'PHPUnit Risk Hub', 77, 'A1', 24.0, 'active', 'used', 'max');

        $hubService = new HubService($this->db);
        $incidentService = new HubIncidentService($this->db, $hubService);
        $hub = $hubService->getHub($ids['hubId']);

        $ref = new ReflectionClass($incidentService);
        $method = $ref->getMethod('calcRiskMultiplier');
        $method->setAccessible(true);

        $tickResult = ['load_pct' => 135.0];

        $plainRisk = $method->invoke($incidentService, $hub, $tickResult, []);
        $protectedRisk = $method->invoke($incidentService, $hub, $tickResult, [
            'active_hse' => 1,
            'failure_reduction' => 0.60,
            'catastrophe_mult' => 0.55,
            'proc_factor' => 2.0,
        ]);

        $this->assertGreaterThan(1.0, $plainRisk);
        $this->assertLessThan($plainRisk, $protectedRisk);
    }

    public function testPausedHubDoesNotGenerateIncidentOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'PHPUnit Paused Hub', 77, 'A1', 18.0, 'paused', 'used', 'max');

        $hubService = new HubService($this->db);
        $incidentService = new HubIncidentService($this->db, $hubService);
        $hub = $hubService->getHub($ids['hubId']);

        $result = $incidentService->processTick($hub, 200.0, ['load_pct' => 150.0], 1.0, $playerId);

        $this->assertNull($result);
    }

    public function testRecentIncidentQueriesReadHubEventsOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'PHPUnit Event Hub', 77, 'A1', 52.0, 'active');

        $hubService = new HubService($this->db);
        $incidentService = new HubIncidentService($this->db, $hubService);

        $stmt = $this->db->prepare(
            'INSERT INTO logistics_hub_events (player_id, hub_id, well_id, event_type, severity, title, message, meta_json, created_at)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $playerId,
            $ids['hubId'],
            'hub_incident_local_leak',
            'medium',
            'Leak',
            'Test leak',
            json_encode(['source' => 'phpunit'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '2026-05-18 08:00:00',
        ]);
        $stmt->execute([
            $playerId,
            $ids['hubId'],
            'hub_incident_storage_jam',
            'low',
            'Jam',
            'Test jam',
            json_encode(['source' => 'phpunit'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '2026-05-18 09:00:00',
        ]);
        $stmt->execute([
            $playerId,
            $ids['hubId'],
            'hub_notice_misc',
            'low',
            'Ignore',
            'Not an incident',
            json_encode(['source' => 'phpunit'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '2026-05-18 10:00:00',
        ]);

        $recentPlayer = $incidentService->getPlayerRecentIncidents($playerId, 10);
        $recentHub = $incidentService->getHubRecentIncidents($ids['hubId'], 10);
        $count = $incidentService->countPlayerIncidents($playerId);

        $this->assertCount(2, $recentPlayer);
        $this->assertCount(2, $recentHub);
        $this->assertSame(2, $count);
        $this->assertSame('hub_incident_storage_jam', $recentPlayer[0]['event_type']);
        $this->assertSame('hub_incident_local_leak', $recentPlayer[1]['event_type']);
        $this->assertSame((string)$ids['hubId'], (string)$recentHub[0]['hub_id']);
        $this->assertSame((string)$ids['hubId'], (string)$recentHub[1]['hub_id']);
    }
}
