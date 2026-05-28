<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubTickService.php';
require_once dirname(__DIR__, 2) . '/src/HubIncidentService.php';

final class MySqlHubHseIntegrationTest extends MySqlIntegrationTestCase
{
    public function testGetHseBonusBecomesWorseWhenAssignedHubIncreasesSupervisionLoad(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');

        $this->seedTechnicalStaff($playerId, $this->seed + 20, 'safety_officer', 'Oficer BHP', 10, 9000);
        $this->seedTechnicalStaff($playerId, $this->seed + 21, 'safety_engineer', 'Inżynier BHP', 10, 12000);
        $this->setPlayerProcedures($playerId, 3, 90);

        $service = new TechnicalTeamService($playerId);
        $before = $service->getHSEBonus();

        $this->seedHub($ids['hubId'], 'PHPUnit HSE Hub', 77, 'A1', 88.0, 'active');
        $this->seedAssignment($ids['hubId'], $ids['wellId'], 'active');

        $after = $service->getHSEBonus();

        $this->assertSame(1, $before['total_wells']);
        $this->assertSame(0, $before['total_hubs']);
        $this->assertSame(1, $before['supervised_units']);
        $this->assertSame(1, $after['total_wells']);
        $this->assertSame(1, $after['total_hubs']);
        $this->assertSame(3, $after['supervised_units']);
        $this->assertSame(2, $after['active_hse']);
        $this->assertGreaterThan((float)$before['failure_reduction'], (float)$after['failure_reduction']);
        $this->assertGreaterThan((float)$before['catastrophe_mult'], (float)$after['catastrophe_mult']);
        $this->assertGreaterThan((float)$before['degrade_mult'], (float)$after['degrade_mult']);
    }

    public function testRealHseBonusReducesHubWearAndIncidentRiskOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
        $this->seedHub($ids['hubId'], 'PHPUnit Protected Hub', 77, 'A1', 26.0, 'active', 'used', 'max');
        $this->seedAssignment($ids['hubId'], $ids['wellId'], 'active');

        $this->seedTechnicalStaff($playerId, $this->seed + 30, 'safety_officer', 'Oficer BHP', 10, 9000);
        $this->seedTechnicalStaff($playerId, $this->seed + 31, 'safety_engineer', 'Inżynier BHP', 10, 12000);
        $this->setPlayerProcedures($playerId, 5, 100);

        $technicalService = new TechnicalTeamService($playerId);
        $hseBonus = $technicalService->getHSEBonus();

        $hubService = new HubService($this->db);
        $tickService = new HubTickService($this->db, $hubService);
        $incidentService = new HubIncidentService($this->db, $hubService);
        $hub = $hubService->getHub($ids['hubId']);

        $withoutHse = $tickService->processTick($hub, 140.0, 1.0, []);
        $withHse = $tickService->processTick($hub, 140.0, 1.0, $hseBonus);

        $ref = new ReflectionClass($incidentService);
        $riskMethod = $ref->getMethod('calcRiskMultiplier');
        $riskMethod->setAccessible(true);

        $riskWithoutHse = $riskMethod->invoke($incidentService, $hub, ['load_pct' => 140.0], []);
        $riskWithHse = $riskMethod->invoke($incidentService, $hub, ['load_pct' => 140.0], $hseBonus);

        $this->assertGreaterThan(0, $hseBonus['active_hse']);
        $this->assertLessThan(1.0, (float)$hseBonus['failure_reduction']);
        $this->assertLessThan(1.0, (float)$hseBonus['degrade_mult']);
        $this->assertLessThan($withoutHse['wear_added'], $withHse['wear_added']);
        $this->assertGreaterThan($withoutHse['new_condition'], $withHse['new_condition']);
        $this->assertLessThan($riskWithoutHse, $riskWithHse);
    }
}
