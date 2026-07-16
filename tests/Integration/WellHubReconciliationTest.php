<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubTickService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellLoopSection.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellHubSection.php';

final class WellHubReconciliationTest extends SqliteIntegrationTestCase
{
    /** @param array<string, mixed> $result */
    private function makeTickService(PDO $db, array $result, bool $persist): HubTickService
    {
        $hubSvc = $this->createMock(HubService::class);

        return new class($db, $hubSvc, $result, $persist) extends HubTickService {
            /** @var array<string, mixed> */
            public array $persistedResult = [];
            public float $bufferAdded = 0.0;

            /** @param array<string, mixed> $result */
            public function __construct(
                PDO $db,
                HubService $hubSvc,
                private array $result,
                private bool $persist
            ) {
                parent::__construct($db, $hubSvc);
            }

            public function processTick(
                array $hub,
                float $inputBbl,
                float $deltaHours,
                array $hseBonus = [],
                array $runtimeEffects = []
            ): array {
                return $this->result;
            }

            public function persistTickResult(array $hub, array $result, DateTime $now): bool
            {
                $this->persistedResult = $result;
                return $this->persist;
            }

            public function addBufferBbl(int $hubId, int $playerId, float $bbl): float
            {
                $this->bufferAdded += $bbl;
                return $bbl;
            }

            public function pruneHubTickStats(DateTime $now): void {}
        };
    }

    /** @param array<string, mixed> $hub */
    private function makeContext(array $hub, float $inputBbl): WellLoopSection
    {
        $ctx = new class extends WellLoopSection {
            public function __construct() {}
        };
        $hubId = (int)$hub['id'];
        $ctx->hubCache = [$hubId => $hub];
        $ctx->hubInputAccum = [$hubId => $inputBbl];
        $ctx->wellHubMap = [100 => $hubId];
        $ctx->hubWellDelivered = [100 => $inputBbl];
        $ctx->hubOutboundType = [$hubId => 'nieustawiony'];
        $ctx->storageCapacity = 10000.0;
        $ctx->currentStorage = $inputBbl;
        $ctx->finBbl = $inputBbl;
        $ctx->deliveredBbl = $inputBbl;
        $ctx->finRevenue = $inputBbl * 70.0;
        $ctx->playerCash = 1000000.0;

        return $ctx;
    }

    /** @return array<string, mixed> */
    private function hubRow(): array
    {
        return [
            'id' => 10,
            'player_id' => 1,
            'condition_pct' => 50.0,
            'region_political_risk' => 1,
        ];
    }

    public function testLossMultiplierChangesConditionLossButNotPhysicalOverflow(): void
    {
        $db = $this->createSqlitePdo();
        $hub = $this->hubRow();
        $ctx = $this->makeContext($hub, 300.0);
        $tickService = $this->makeTickService($db, [
            'processed_bbl' => 90.0,
            'buffered_bbl' => 100.0,
            'drained_buffer_bbl' => 0.0,
            'lost_bbl' => 110.0,
            'overflow_lost_bbl' => 100.0,
            'condition_lost_bbl' => 10.0,
            'input_bbl' => 300.0,
            'load_pct' => 100.0,
            'overloaded' => false,
            'new_buffer' => 100.0,
            'wear_added' => 0.0,
            'new_condition' => 50.0,
            'new_efficiency' => 50.0,
            'new_status' => 'active',
            'incident_flag' => false,
        ], true);

        $section = new WellHubSection(
            $ctx,
            new DateTime('2026-07-15 12:00:00'),
            $tickService,
            null,
            null,
            ['loss_mult' => 0.9],
            ['opex' => 1.0, 'loss' => 1.0],
            70.0,
            new OutboundLegService([]),
            null
        );
        $section->finalize(1, 1.0, []);

        $this->assertEqualsWithDelta(9.0, $tickService->persistedResult['condition_lost_bbl'], 0.001);
        $this->assertEqualsWithDelta(109.0, $tickService->persistedResult['lost_bbl'], 0.001);
        $this->assertEqualsWithDelta(91.0, $tickService->persistedResult['processed_bbl'], 0.001);
        $this->assertEqualsWithDelta(91.0, $ctx->currentStorage, 0.001);
        $this->assertEqualsWithDelta(300.0, $ctx->currentStorage + 100.0 + $ctx->finHubLossBbl, 0.001);
    }

    public function testPersistenceFailureRemovesOptimisticHubCredit(): void
    {
        $db = $this->createSqlitePdo();
        $hub = $this->hubRow();
        $ctx = $this->makeContext($hub, 100.0);
        $tickService = $this->makeTickService($db, [
            'processed_bbl' => 100.0,
            'buffered_bbl' => 0.0,
            'drained_buffer_bbl' => 0.0,
            'lost_bbl' => 0.0,
            'overflow_lost_bbl' => 0.0,
            'condition_lost_bbl' => 0.0,
            'input_bbl' => 100.0,
            'load_pct' => 100.0,
            'overloaded' => false,
            'new_buffer' => 0.0,
            'wear_added' => 0.0,
            'new_condition' => 50.0,
            'new_efficiency' => 50.0,
            'new_status' => 'active',
            'incident_flag' => false,
        ], false);

        $section = new WellHubSection(
            $ctx,
            new DateTime('2026-07-15 12:00:00'),
            $tickService,
            null,
            null,
            [],
            ['opex' => 1.0, 'loss' => 1.0],
            70.0,
            new OutboundLegService([]),
            null
        );
        $section->finalize(1, 1.0, []);

        $this->assertEqualsWithDelta(0.0, $ctx->currentStorage, 0.001);
        $this->assertEqualsWithDelta(0.0, $ctx->finBbl, 0.001);
        $this->assertEqualsWithDelta(100.0, $ctx->finHubLossBbl, 0.001);
        $this->assertEqualsWithDelta(0.0, $ctx->hubInputAccum[10], 0.001);
    }

    public function testConditionLossFromDrainedBufferDoesNotDebitExistingStorage(): void
    {
        $db = $this->createSqlitePdo();
        $hub = $this->hubRow();
        $ctx = $this->makeContext($hub, 0.0);
        $ctx->storageCapacity = 100.0;
        $ctx->currentStorage = 100.0;
        $ctx->finBbl = 0.0;
        $ctx->deliveredBbl = 0.0;
        $ctx->finRevenue = 0.0;

        $tickService = $this->makeTickService($db, [
            'processed_bbl' => 45.0,
            'buffered_bbl' => 0.0,
            'drained_buffer_bbl' => 50.0,
            'lost_bbl' => 5.0,
            'overflow_lost_bbl' => 0.0,
            'condition_lost_bbl' => 5.0,
            'input_bbl' => 0.0,
            'load_pct' => 50.0,
            'overloaded' => false,
            'new_buffer' => 0.0,
            'wear_added' => 0.0,
            'new_condition' => 50.0,
            'new_efficiency' => 50.0,
            'new_status' => 'active',
            'incident_flag' => false,
        ], true);

        $section = new WellHubSection(
            $ctx,
            new DateTime('2026-07-15 12:00:00'),
            $tickService,
            null,
            null,
            [],
            ['opex' => 1.0, 'loss' => 1.0],
            70.0,
            new OutboundLegService([]),
            null
        );
        $section->finalize(1, 1.0, []);

        $this->assertEqualsWithDelta(100.0, $ctx->currentStorage, 0.001);
        $this->assertEqualsWithDelta(0.0, $ctx->finBbl, 0.001);
        $this->assertEqualsWithDelta(0.0, $ctx->deliveredBbl, 0.001);
        $this->assertEqualsWithDelta(5.0, $ctx->finHubLossBbl, 0.001);
        $this->assertEqualsWithDelta(45.0, $tickService->bufferAdded, 0.001);
    }

    public function testGlobalLossMultiplierScalesHubIncidentLoss(): void
    {
        $db = $this->createSqlitePdo();
        $hub = $this->hubRow();
        $ctx = $this->makeContext($hub, 100.0);

        $tickService = $this->makeTickService($db, [
            'processed_bbl' => 100.0,
            'buffered_bbl' => 0.0,
            'drained_buffer_bbl' => 0.0,
            'lost_bbl' => 0.0,
            'overflow_lost_bbl' => 0.0,
            'condition_lost_bbl' => 0.0,
            'input_bbl' => 100.0,
            'load_pct' => 100.0,
            'overloaded' => false,
            'new_buffer' => 0.0,
            'wear_added' => 0.0,
            'new_condition' => 50.0,
            'new_efficiency' => 50.0,
            'new_status' => 'active',
            'incident_flag' => false,
        ], true);
        $incidentService = new class($db, $this->createMock(HubService::class)) extends HubIncidentService {
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
                return ['extra_loss' => 10.0, 'type' => 'local_leak'];
            }
        };

        $section = new WellHubSection(
            $ctx,
            new DateTime('2026-07-15 12:00:00'),
            $tickService,
            $incidentService,
            null,
            ['loss_mult' => 1.0, 'incident_mult' => 1.0],
            ['opex' => 1.0, 'loss' => 2.0],
            70.0,
            new OutboundLegService([]),
            null
        );
        $section->finalize(1, 1.0, []);

        $this->assertEqualsWithDelta(20.0, $ctx->finHubIncidentLossBbl, 0.001);
        $this->assertEqualsWithDelta(80.0, $ctx->currentStorage, 0.001);
    }
}
