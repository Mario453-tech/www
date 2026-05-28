<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class HubTickServiceTest extends BaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testProcessTickAppliesUsedWearStaleMaintenanceAndHseModifier(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWorkModeMultipliers', 'getAcquisitionDefaults', 'getHubTypeDefaults'])
            ->getMock();

        $hubSvc->method('getWorkModeMultipliers')
            ->with('standard')
            ->willReturn([
                'throughput_mult' => 1.0,
                'wear_mult' => 1.0,
                'efficiency_mod' => 0.0,
                'risk_mult' => 1.0,
            ]);

        $hubSvc->method('getAcquisitionDefaults')
            ->with('used')
            ->willReturn([
                'wear_mult' => 1.45,
                'risk_mult' => 1.55,
            ]);

        $hubSvc->method('getHubTypeDefaults')
            ->with('medium', 1)
            ->willReturn([
                'wear_per_tick' => 0.04,
                'overload_wear_mult' => 2.8,
                'overload_risk_mult' => 2.5,
            ]);

        $service = new HubTickService($this->db, $hubSvc);

        $hub = [
            'status' => 'active',
            'work_mode' => 'standard',
            'acquisition_type' => 'used',
            'condition_pct' => 80.0,
            'efficiency_pct' => 80.0,
            'nominal_capacity_bph' => 100.0,
            'buffer_capacity_bbl' => 100.0,
            'buffer_current_bbl' => 0.0,
            'hub_type' => 'medium',
            'level' => 1,
            'last_maintenance_at' => date('Y-m-d H:i:s', time() - (80 * 3600)),
        ];

        $result = $service->processTick($hub, 50.0, 1.0, ['degrade_mult' => 0.8]);

        $this->assertSame(50.0, $result['processed_bbl']);
        $this->assertSame(0.0, $result['lost_bbl']);
        $this->assertFalse($result['overloaded']);
        $this->assertSame(0.0534, $result['wear_added']);
        $this->assertSame(79.95, $result['new_condition']);
        $this->assertSame('active', $result['new_status']);
        $this->assertFalse($result['incident_flag']);
    }

    public function testDisabledHubUsesFallbackFlow(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFallbackConfig'])
            ->getMock();

        $hubSvc->method('getFallbackConfig')
            ->willReturn([
                'throughput_bph' => 10.0,
                'opex_mult' => 2.0,
            ]);

        $service = new HubTickService($this->db, $hubSvc);

        $hub = [
            'status' => 'disabled',
            'buffer_current_bbl' => 0.0,
            'condition_pct' => 55.0,
            'efficiency_pct' => 55.0,
        ];

        $result = $service->processTick($hub, 25.0, 1.0);

        $this->assertSame(10.0, $result['processed_bbl']);
        $this->assertSame(15.0, $result['lost_bbl']);
        $this->assertSame('disabled', $result['new_status']);
        $this->assertSame(0.0, $result['wear_added']);
    }
}
