<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class WellLoopPipelineStateTest extends BaseTestCase
{
    public function testDamagedPipelineIsInvalidatedInOutboundCache(): void
    {
        $ctx = new class extends WellLoopSection {
            public function __construct() {}
        };
        $ctx->hubOutboundPipelineCache = [
            10 => [
                'id' => 77,
                'status' => 'active',
                '_is_operational' => true,
            ],
        ];

        $ctx->markPipelinesUnavailable([77]);

        $this->assertSame('damaged', $ctx->hubOutboundPipelineCache[10]['status']);
        $this->assertFalse($ctx->hubOutboundPipelineCache[10]['_is_operational']);
    }

    public function testExplosionLossConsumesCurrentHubInputAndFinanceCredit(): void
    {
        $ctx = new class extends WellLoopSection {
            public function __construct() {}
        };
        $ctx->wellHubMap = [100 => 10];
        $ctx->hubInputAccum = [10 => 5.0];
        $ctx->hubWellDelivered = [100 => 5.0];
        $ctx->finBbl = 5.0;
        $ctx->deliveredBbl = 5.0;
        $ctx->finRevenue = 350.0;

        $consumed = $ctx->consumeHubInputForLoss(10, 0.25, 70.0);

        $this->assertEqualsWithDelta(0.25, $consumed, 0.0001);
        $this->assertEqualsWithDelta(4.75, $ctx->hubInputAccum[10], 0.0001);
        $this->assertEqualsWithDelta(4.75, $ctx->hubWellDelivered[100], 0.0001);
        $this->assertEqualsWithDelta(4.75, $ctx->finBbl, 0.0001);
        $this->assertEqualsWithDelta(332.5, $ctx->finRevenue, 0.0001);
    }
}
