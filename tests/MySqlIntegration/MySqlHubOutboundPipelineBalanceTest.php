<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubTickService.php';
require_once dirname(__DIR__, 2) . '/src/HubIncidentService.php';
require_once dirname(__DIR__, 2) . '/src/OutboundLegService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellHubSection.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellLoopSection.php';

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

    public function testActiveOutboundPipelinePreservesBarrelsAcrossMultipleTicks(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId    = $ids['hubId'];
        $wellId   = $ids['wellId'];

        $this->seedHub($hubId, 'Multitick outbound hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 200.0, $playerId);
        $initialHub = $this->db->query("SELECT * FROM logistics_hubs WHERE id = {$hubId}")->fetch();
        $initialBuffer = (float)$initialHub['buffer_current_bbl'];
        $newInput = 100.0;
        $storage = 0.0;
        $losses = 0.0;

        for ($tick = 0; $tick < 10; $tick++) {
            $input = $tick === 0 ? $newInput : 0.0;
            $hubRow = $this->db->query("SELECT * FROM logistics_hubs WHERE id = {$hubId}")->fetch();
            $hubRow['region_political_risk'] = 1;

            $ctx = $this->makeCtx($hubId, $wellId, $hubRow, 100000.0, $storage + $input);
            $ctx->hubInputAccum[$hubId] = $input;
            $ctx->finBbl = $input;
            $ctx->deliveredBbl = $input;
            $ctx->finRevenue = $input * 70.0;
            $ctx->hubOutboundPipelineCache[$hubId] = [
                'status' => 'active',
                '_is_operational' => true,
                'real_capacity_bph' => 50.0,
                'transport_loss' => 10.0,
                'opex_per_tick' => 0.0,
                'opex_per_bbl' => 0.0,
            ];

            $section = new WellHubSection(
                $ctx,
                new DateTime(sprintf('2026-07-15 %02d:00:00', 10 + $tick)),
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

            $storage = $ctx->currentStorage;
            $losses += $ctx->finHubLossBbl + $ctx->finOutboundLossBbl;
        }

        $finalBuffer = (float)$this->db->query("SELECT buffer_current_bbl FROM logistics_hubs WHERE id = {$hubId}")->fetchColumn();

        $this->assertLessThan(0.1, $finalBuffer, 'The outbound backlog should clear after enough ticks.');
        $this->assertGreaterThan(0.0, $losses, 'Configured pipeline loss should be recorded.');
        $this->assertEqualsWithDelta(
            $initialBuffer + $newInput,
            $storage + $finalBuffer + $losses,
            0.5,
            'Multitick outbound transport must neither create nor destroy unclassified barrels.'
        );
    }

    public function testHubIncidentUsesVolumeRemainingAfterOutboundLoss(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId = $ids['hubId'];
        $wellId = $ids['wellId'];

        $this->seedHub($hubId, 'Incident base hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 200.0, $playerId);
        $hubRow = $this->db->query("SELECT * FROM logistics_hubs WHERE id = {$hubId}")->fetch();
        $hubRow['region_political_risk'] = 1;
        $ctx = $this->makeCtx($hubId, $wellId, $hubRow, 100000.0, 0.0);
        $ctx->hubOutboundPipelineCache[$hubId] = [
            'status' => 'active',
            '_is_operational' => true,
            'real_capacity_bph' => 100000.0,
            'transport_loss' => 100.0,
            'opex_per_tick' => 0.0,
            'opex_per_bbl' => 0.0,
        ];

        $incidentSvc = new class($this->db) extends HubIncidentService {
            public float $lastProcessedBbl = -1.0;

            public function processTick(
                array $hub,
                float $inputBbl,
                array $tickResult,
                float $deltaHours,
                int $playerId,
                array $hseBonus = [],
                ?ProtectionService $protection = null,
                array $runtimeMods = []
            ): ?array {
                $this->lastProcessedBbl = (float)($tickResult['processed_bbl'] ?? 0.0);
                return ['extra_loss' => $this->lastProcessedBbl];
            }
        };

        $section = new WellHubSection(
            $ctx,
            new DateTime('2026-07-15 12:00:00'),
            new HubTickService($this->db, new HubService($this->db)),
            $incidentSvc,
            null,
            [],
            ['opex' => 1.0, 'loss' => 1.0],
            70.0,
            new OutboundLegService([]),
            null
        );
        $section->finalize($playerId, 1.0, []);

        $this->assertEqualsWithDelta(0.0, $incidentSvc->lastProcessedBbl, 0.001);
        $this->assertEqualsWithDelta(0.0, $ctx->finHubIncidentLossBbl, 0.001);
        $this->assertGreaterThanOrEqual(0.0, $ctx->finBbl);
        $this->assertGreaterThanOrEqual(0.0, $ctx->deliveredBbl);
    }
}
