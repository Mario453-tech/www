<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class HubIncidentServiceTest extends BaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testRiskMultiplierIncludesAcquisitionModeLoadAndHseProtection(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAcquisitionDefaults'])
            ->getMock();

        $hubSvc->method('getAcquisitionDefaults')
            ->with('used')
            ->willReturn(['risk_mult' => 1.55]);

        $service = new HubIncidentService($this->db, $hubSvc);

        $method = new ReflectionMethod(HubIncidentService::class, 'calcRiskMultiplier');
        $method->setAccessible(true);

        $hub = [
            'condition_pct' => 20.0,
            'work_mode' => 'max',
            'acquisition_type' => 'used',
        ];
        $tickResult = ['load_pct' => 130.0];

        $withoutHse = $method->invoke($service, $hub, $tickResult, []);
        $withHse = $method->invoke($service, $hub, $tickResult, [
            'active_hse' => 2,
            'failure_reduction' => 0.50,
            'catastrophe_mult' => 0.40,
            'proc_factor' => 0.50,
        ]);

        $this->assertSame(41.85, round($withoutHse, 4));
        $this->assertSame(7.8678, round($withHse, 4));
        $this->assertLessThan($withoutHse, $withHse);
    }

    public function testPausedHubDoesNotGenerateIncident(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAcquisitionDefaults'])
            ->getMock();

        $hubSvc->method('getAcquisitionDefaults')
            ->willReturn(['risk_mult' => 1.0]);

        $service = new HubIncidentService($this->db, $hubSvc);

        $result = $service->processTick(
            ['status' => 'paused', 'condition_pct' => 100.0, 'work_mode' => 'standard', 'acquisition_type' => 'new'],
            100.0,
            ['load_pct' => 120.0],
            1.0,
            1,
            []
        );

        $this->assertNull($result);
    }
}
