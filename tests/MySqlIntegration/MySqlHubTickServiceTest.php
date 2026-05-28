<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubTickService.php';

final class MySqlHubTickServiceTest extends MySqlIntegrationTestCase
{
    public function testProcessTickAndPersistResultOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'PHPUnit Tick Hub', 77, 'A1', 82.0, 'active', 'used', 'standard', 40.0);

        $hubService = new HubService($this->db);
        $tickService = new HubTickService($this->db, $hubService);

        $hub = $hubService->getHub($ids['hubId']);
        $this->assertIsArray($hub);

        $result = $tickService->processTick($hub, 150.0, 1.0, [
            'active_hse' => 1,
            'degrade_mult' => 0.80,
        ]);

        $this->assertSame(false, $result['overloaded']);
        $this->assertSame(false, $result['incident_flag']);
        $this->assertGreaterThan(0.0, $result['processed_bbl']);
        $this->assertSame(0.0, $result['lost_bbl']);
        $this->assertLessThan(82.0, $result['new_condition']);
        $this->assertGreaterThan(0.0, $result['wear_added']);

        $now = new DateTime('2026-05-18 10:00:00');
        $tickService->persistTickResult($hub, $result, $now);

        $hubStmt = $this->db->prepare(
            'SELECT condition_pct, wear_level, status, buffer_current_bbl, last_processed_at
             FROM logistics_hubs WHERE id = ?'
        );
        $hubStmt->execute([$ids['hubId']]);
        $persistedHub = $hubStmt->fetch();

        $statsStmt = $this->db->prepare(
            'SELECT processed_volume_bbl, buffered_volume_bbl, lost_volume_bbl, condition_before_pct, condition_after_pct, overload_flag, incident_flag
             FROM logistics_hub_tick_stats WHERE hub_id = ? ORDER BY id DESC LIMIT 1'
        );
        $statsStmt->execute([$ids['hubId']]);
        $stats = $statsStmt->fetch();

        $this->assertNotFalse($persistedHub);
        $this->assertNotFalse($stats);
        $this->assertSame((string)$result['new_condition'], (string)$persistedHub['condition_pct']);
        $this->assertGreaterThan(0.0, (float)$persistedHub['wear_level']);
        $this->assertSame($result['new_status'], $persistedHub['status']);
        $this->assertSame('2026-05-18 10:00:00', $persistedHub['last_processed_at']);
        $this->assertEqualsWithDelta((float)$result['processed_bbl'], (float)$stats['processed_volume_bbl'], 0.0001);
        $this->assertEqualsWithDelta((float)$result['buffered_bbl'], (float)$stats['buffered_volume_bbl'], 0.0001);
        $this->assertEqualsWithDelta((float)$result['lost_bbl'], (float)$stats['lost_volume_bbl'], 0.0001);
        $this->assertSame('82.00', $stats['condition_before_pct']);
        $this->assertSame((string)$result['new_condition'], (string)$stats['condition_after_pct']);
        $this->assertSame('0', (string)$stats['overload_flag']);
        $this->assertSame('0', (string)$stats['incident_flag']);
    }

    public function testUsedHubWearsMoreThanNewHubOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'PHPUnit New Hub', 77, 'A1', 78.0, 'active', 'new');
        $this->seedHub($ids['auxHubId'], 'PHPUnit Used Hub', 77, 'A1', 78.0, 'active', 'used');

        $hubService = new HubService($this->db);
        $tickService = new HubTickService($this->db, $hubService);

        $newHub = $hubService->getHub($ids['hubId']);
        $usedHub = $hubService->getHub($ids['auxHubId']);

        $newResult = $tickService->processTick($newHub, 120.0, 1.0);
        $usedResult = $tickService->processTick($usedHub, 120.0, 1.0);

        $this->assertGreaterThan($newResult['wear_added'], $usedResult['wear_added']);
        $this->assertLessThan($newResult['new_condition'], $usedResult['new_condition']);
    }
}
