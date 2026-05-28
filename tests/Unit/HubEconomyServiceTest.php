<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class HubEconomyServiceTest extends BaseTestCase
{
    public function testGetBuildCostAppliesRegionalMultiplier(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getHubTypeDefaults', 'cfg'])
            ->getMock();

        $hubSvc->method('getHubTypeDefaults')
            ->with('large', 1)
            ->willReturn(['build_cost' => 1000.0]);

        $hubSvc->method('cfg')
            ->with('region', '5.build_cost_mult', '1.0')
            ->willReturn('1.35');

        $service = new HubEconomyService($hubSvc);

        $this->assertSame(1350.0, $service->getBuildCost('large', 5));
    }

    public function testGetOpexIncludesConditionPenaltyAcquisitionMultiplierAndLeaseFee(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWorkModeMultipliers', 'getAcquisitionDefaults'])
            ->getMock();

        $hubSvc->method('getWorkModeMultipliers')
            ->with('standard')
            ->willReturn(['opex_mult' => 1.10]);

        $hubSvc->method('getAcquisitionDefaults')
            ->with('rental')
            ->willReturn([
                'opex_mult' => 0.95,
                'lease_fee_per_tick' => 320.0,
            ]);

        $service = new HubEconomyService($hubSvc);

        $hub = [
            'work_mode' => 'standard',
            'condition_pct' => 25.0,
            'acquisition_type' => 'rental',
            'opex_per_tick' => 100.0,
            'lease_fee_per_tick' => 320.0,
        ];

        $this->assertSame(476.75, $service->getOpex($hub));
    }

    public function testGetViabilityRatioUsesAverageProcessedVolume(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWorkModeMultipliers', 'getAcquisitionDefaults'])
            ->getMock();

        $hubSvc->method('getWorkModeMultipliers')
            ->willReturn(['opex_mult' => 1.0]);

        $hubSvc->method('getAcquisitionDefaults')
            ->willReturn([
                'opex_mult' => 1.0,
                'lease_fee_per_tick' => 0.0,
            ]);

        $service = new HubEconomyService($hubSvc);

        $hub = [
            'work_mode' => 'standard',
            'condition_pct' => 90.0,
            'acquisition_type' => 'new',
            'opex_per_tick' => 200.0,
        ];

        $stats = [
            ['processed_volume_bbl' => 100.0],
            ['processed_volume_bbl' => 140.0],
            ['processed_volume_bbl' => 120.0],
        ];

        $this->assertSame(18.0, $service->getViabilityRatio($hub, $stats, 30.0));
    }
}
