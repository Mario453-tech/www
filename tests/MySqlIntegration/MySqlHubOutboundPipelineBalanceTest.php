<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';

final class MySqlHubOutboundPipelineBalanceTest extends MySqlIntegrationTestCase
{
    /**
     * @param array<string,mixed> $hubRow
     */
    private function makeCtx(int $hubId, int $wellId, array $hubRow, float $storageCap, float $storageUsed): WellLoopSection
    {
        return new class($hubId, $wellId, $hubRow, $storageCap, $storageUsed) extends WellLoopSection {
            public function __construct(int $hubId, int $wellId, array $hubRow, float $cap, float $used)
            {
                $this->hubCache                  = [$hubId => $hubRow];
                $this->hubInputAccum             = [$hubId => 0.0];
                $this->wellHubMap                = [$wellId => $hubId];
                $this->hubOutboundType           = [$hubId => 'rurociag'];
                $this->hubOutboundPipelineCache  = [$hubId => [
                    'status' => 'damaged',
                    '_is_operational' => true,
                    'real_capacity_bph' => 200.0,
                    'transport_loss' => 0.0,
                    'opex_per_tick' => 0.0,
                    'opex_per_bbl' => 0.0,
                ]];
                $this->storageCapacity           = $cap;
                $this->currentStorage            = $used;
                $this->playerCash                = 1_000_000.0;
                $this->totalCosts                = 0.0;
                $this->finOpex                   = 0.0;
                $this->finTransport              = 0.0;
                $this->finHubUsageCost           = 0.0;
                $this->finBbl                    = 0.0;
                $this->deliveredBbl              = 0.0;
                $this->finRevenue                = 0.0;
                $this->storageBlockedBbl         = 0.0;
                $this->finLossBbl                = 0.0;
                $this->finLossValue              = 0.0;
                $this->finHubLossBbl             = 0.0;
                $this->finHubLossValue           = 0.0;
                $this->finHubIncidentLossBbl     = 0.0;
                $this->finHubIncidentLossValue   = 0.0;
                $this->finOutboundLossBbl        = 0.0;
                $this->finOutboundLossValue      = 0.0;
                $this->incidentsTriggered        = 0;
            }
        };
    }

    public function testDamagedOutboundPipelineReturnsOilToHubBuffer(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId    = $ids['hubId'];
        $wellId   = $ids['wellId'];

        $this->seedHub($hubId, 'H3 outbound blocked hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 200.0, $playerId);

        $hubRow = $this->db->query("SELECT * FROM logistics_hubs WHERE id = {$hubId}")->fetch();
        $hubRow['region_political_risk'] = 1;
        $initialBuffer = (float)$hubRow['buffer_current_bbl'];

        $ctx = $this->makeCtx($hubId, $wellId, $hubRow, 100000.0, 0.0);
        $section = new WellHubSection(
            $ctx,
            new \DateTime(),
            new HubTickService($this->db, new HubService($this->db)),
            null,
            null,
            [],
            ['opex' => 1.0, 'loss' => 1.0],
            70.0,
            new OutboundLegService([]),
            null
        );

        $section->finalize($playerId, 1.0, []);

        $finalBuffer = (float)$this->db->query("SELECT buffer_current_bbl FROM logistics_hubs WHERE id = {$hubId}")->fetchColumn();

        $this->assertGreaterThan(150.0, $finalBuffer, 'Damaged outbound pipeline should send the drained oil back to the hub buffer.');
        $this->assertLessThan(1.0, $ctx->finBbl, 'Blocked outbound leg should not leave delivered barrels in storage.');
        $this->assertLessThan(1.0, $ctx->deliveredBbl, 'Blocked outbound leg should not count as delivered.');
        $this->assertLessThan(1.0, $ctx->finOutboundLossBbl, 'Damaged outbound pipeline should throttle, not destroy, the oil.');
        $this->assertLessThan(0.01, $ctx->finTransport, 'Blocked outbound pipeline should not charge transport on stopped flow.');

        $balanceLeft = $initialBuffer;
        $balanceRight = $finalBuffer + $ctx->finBbl + $ctx->finHubLossBbl + $ctx->finOutboundLossBbl;
        $this->assertEqualsWithDelta(
            $balanceLeft,
            $balanceRight,
            1.0,
            'Barrel balance must hold when outbound pipeline is damaged and the flow is throttled.'
        );
    }
}
