<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';

/**
 * Testy C3 dla sledzenia totalCosts w WellProductionHandler.
 * C3 tests for totalCosts tracking in WellProductionHandler.
 *
 * totalCosts sluzy do atomowego zapisu gotowki w FinancialStateSection::saveCashAndTick:
 *   cash = GREATEST(0, cash - totalCosts)
 *
 * KRYTYCZNA WLASCIWOSC: totalCosts musi zawierac pelna zamierzona kwote odliczenia,
 * NIE przycietą do aktualnej gotowki. Pozwala to DB poprawnie odliczyc koszty od
 * aktualnego salda (ktore moze wzrosc wspolbieznie w trakcie ticka).
 *
 * totalCosts is used for atomic cash write in FinancialStateSection::saveCashAndTick:
 *   cash = GREATEST(0, cash - totalCosts)
 *
 * CRITICAL PROPERTY: totalCosts must hold the full intended deduction amount,
 * NOT clipped to available cash. This allows the DB to correctly deduct costs from
 * the current balance (which may have grown concurrently during the tick).
 */
final class WellProductionHandlerTotalCostsTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->db->exec("
            CREATE TABLE wells (
                id                       INTEGER PRIMARY KEY,
                player_id                INTEGER NOT NULL,
                status                   TEXT    NOT NULL DEFAULT 'active',
                road_buffer_bbl          REAL    NOT NULL DEFAULT 0.0,
                marine_buffer_bbl        REAL    NOT NULL DEFAULT 0.0,
                reservoir_remaining      REAL    NOT NULL DEFAULT 1000000.0,
                last_production_at       TEXT    NULL,
                upkeep_cost_per_hour     REAL    NOT NULL DEFAULT 1000.0,
                base_production_per_hour REAL    NOT NULL DEFAULT 50.0,
                well_type                TEXT    NOT NULL DEFAULT 'onshore',
                region_tax_rate          REAL    NOT NULL DEFAULT 0.0,
                transport_capacity_pct   REAL    NOT NULL DEFAULT 100.0,
                equipment_tier           TEXT    NOT NULL DEFAULT 'standard',
                equipment_upgrade_level  INTEGER NOT NULL DEFAULT 0
            )
        ");
    }

    private function seedWell(int $id, int $pid, string $status = 'active'): void
    {
        $this->db->prepare(
            "INSERT INTO wells (id, player_id, status) VALUES (?, ?, ?)"
        )->execute([$id, $pid, $status]);
    }

    /** @return array{WellProductionHandler, WellLoopSection} */
    private function makeHandler(float $playerCash): array
    {
        $db = $this->db;

        $loopCtx = new class($playerCash) extends WellLoopSection {
            public function __construct(float $cash)
            {
                $this->playerCash             = $cash;
                $this->totalCosts             = 0.0;
                $this->currentStorage         = 0.0;
                $this->finOpex                = 0.0;
                $this->finTransport           = 0.0;
                $this->finTax                 = 0.0;
                $this->finBbl                 = 0.0;
                $this->finRevenue             = 0.0;
                $this->finGross               = 0.0;
                $this->producedBbl            = 0.0;
                $this->deliveredBbl           = 0.0;
                $this->finLossBbl             = 0.0;
                $this->finLossValue           = 0.0;
                $this->preStorageLossBbl      = 0.0;
                $this->transportLossBbl       = 0.0;
                $this->transportEventLossBbl  = 0.0;
                $this->transportCapacityLossBbl = 0.0;
                $this->storageBlockedBbl      = 0.0;
                $this->roadInTransitBbl       = 0.0;
                $this->marineInTransitBbl     = 0.0;
                $this->wellHubMap             = [];
                $this->hubInputAccum          = [];
                $this->hubWellDelivered       = [];
                $this->hubOutboundType        = [];
            }
            public function applyHubOrFallback(int $wellId, float &$actual, float $deltaHours): void {}
            public function recordPreStorageLoss(float $bbl, float $price): void {}
            public function recordHubWellDelivered(int $wellId, float $bbl): void {}
        };

        $wellSvc = new class extends WellService {
            public function __construct() {}
            public function getOpexPerHour(array $well): float { return 1000.0; }
            public function getEffectiveProduction(array $well): float { return 50.0; }
        };

        $ctx = new class extends WellProductionSection {
            public function __construct() {}
        };
        $ctx->db                   = $db;
        $ctx->loopCtx              = $loopCtx;
        $ctx->wellService          = $wellSvc;
        $ctx->oilPrice             = 70.0;
        $ctx->gBalanceMults        = ['opex' => 1.0, 'production' => 1.0, 'loss' => 1.0, 'tax' => 1.0];
        $ctx->financeTechnicalMods = ['opex_mult' => 1.0];
        $ctx->financeLogisticsMods = ['transport_cost_mult' => 1.0, 'loss_mult' => 1.0];
        $ctx->financeSafetyMods    = [];
        $ctx->transportConfig      = [];
        $ctx->staffCache           = [];
        $ctx->wellPipelineCache    = [];
        $ctx->roadConfigCache      = [];
        $ctx->offshoreConfigCache  = [];
        $ctx->roadTransportSvc     = null;
        $ctx->marineDeliverySvc    = null;
        $ctx->offshoreTransportSvc = null;
        $ctx->geoSvc               = null;
        $ctx->incidentSvc          = null;

        return [new WellProductionHandler($ctx), $loopCtx];
    }

    private function defaultWellRow(int $id, int $pid, string $status = 'active'): array
    {
        return [
            'id'                       => $id,
            'player_id'                => $pid,
            'status'                   => $status,
            'upkeep_cost_per_hour'     => 1000.0,
            'base_production_per_hour' => 50.0,
            'well_type'                => 'onshore',
            'transport_type'           => 'rurociag',
            'transport_capacity_pct'   => 100.0,
            'equipment_tier'           => 'standard',
            'equipment_upgrade_level'  => 0,
            'region_tax_rate'          => 0.0,
            'region_code'              => null,
            'region_political_risk'    => 1,
            'oil_richness'             => 1.0,
            'production_mode'          => 'normal',
            'production_boost_pct'     => 0.0,
            'effective_pressure'       => 1.0,
            'reservoir_remaining'      => 1_000_000.0,
            'road_buffer_bbl'          => 0.0,
            'marine_buffer_bbl'        => 0.0,
        ];
    }

    private function defaultMults(): array
    {
        return [
            'opEfficiencyMult'  => 1.0,
            'eqMults'           => ['prod' => 1.0],
            'opProdPerkMult'    => 1.0,
            'layerRichnessMult' => 1.0,
        ];
    }

    // =========================================================================
    // C3: processOpex sladuje pelny opexTotal w totalCosts
    // C3: processOpex tracks full opexTotal in totalCosts
    // =========================================================================

    /**
     * Gdy cash == 0 (brak gotowki): totalCosts += opexTotal, finOpex += 0.
     * Kluczowa wlasciwosc C3: totalCosts > finOpex gdy wystepuje niedoplata.
     *
     * When cash == 0 (no money): totalCosts += opexTotal, finOpex += 0.
     * Key C3 property: totalCosts > finOpex when there is a shortfall.
     */
    public function testTotalCostsTracksFullOpexWhenZeroCash(): void
    {
        $this->seedWell(1, 1);
        // opex = getOpexPerHour * deltaHours = 1000 * 1.0 = 1000
        [$handler, $loop] = $this->makeHandler(0.0);

        $well = $this->defaultWellRow(1, 1);
        $result = $handler->processOpex($well, 1, 1, 1.0, 1_000_000.0, null);

        $this->assertFalse($result, 'Brak gotowki musi zwrocic false (wstrzymaj tick) / No cash must return false (skip tick)');
        $this->assertEqualsWithDelta(1000.0, $loop->totalCosts, 0.001,
            'totalCosts musi zawierac pelna kwote OPEX (1000), mimo ze gotowka=0');
        $this->assertEqualsWithDelta(0.0, $loop->finOpex, 0.001,
            'finOpex musi byc 0 gdy nie bylo gotowki (min(1000,0)=0)');
        $this->assertGreaterThan($loop->finOpex, $loop->totalCosts,
            'C3: totalCosts > finOpex gdy wystepuje niedoplata');
    }

    /**
     * Gdy cash < opexTotal (cześciowy niedobor): totalCosts nadal == opexTotal.
     * Gdy cash >= opexTotal: totalCosts == opexTotal (identyczny wynik).
     *
     * When cash < opexTotal (partial shortfall): totalCosts still == opexTotal.
     * When cash >= opexTotal: totalCosts == opexTotal (same result).
     */
    public function testTotalCostsTracksFullOpexRegardlessOfCashLevel(): void
    {
        $this->seedWell(1, 1);
        [$handler, $loop] = $this->makeHandler(400.0); // opex=1000, cash=400 (niedobor)

        $well = $this->defaultWellRow(1, 1);
        $handler->processOpex($well, 1, 1, 1.0, 1_000_000.0, null);

        $this->assertEqualsWithDelta(1000.0, $loop->totalCosts, 0.001,
            'totalCosts musi byc 1000 (pelna kwota), nie 400 (przyciety do kasy)');
        $this->assertEqualsWithDelta(400.0, $loop->finOpex, 0.001,
            'finOpex musi byc 400 (min(1000, 400))');

        // Zauwaz roznice: totalCosts != finOpex (to jest sedno C3 fix)
        // Note the difference: totalCosts != finOpex (this is the essence of C3 fix)
        $this->assertGreaterThan($loop->finOpex, $loop->totalCosts,
            'C3: totalCosts(1000) > finOpex(400) — pelnyCoszt vs realniePobrany');
    }

    // =========================================================================
    // C3: processProduction sledzi koszty transportu / tracks transport costs
    // =========================================================================

    /**
     * Koszt rurociagu: totalCosts += pelna kwota, nawet gdy cash < pipeline_cost.
     * Pipeline cost: totalCosts += full amount, even when cash < pipeline_cost.
     *
     * Setup: cash=500, opex(delta=0.001)=1.0, pipeline opex_per_tick=800 > 499
     * Oczekiwane: totalCosts = 1.0 + 800 = 801.0, finTransport = min(800, 499) ≈ 499
     */
    public function testTotalCostsTracksPipelineCostFully(): void
    {
        $this->seedWell(1, 1);
        [$handler, $loop] = $this->makeHandler(500.0);

        $well = $this->defaultWellRow(1, 1);

        // Krok 1: processOpex z malym delta -> OPEX=1.0 (cash=500 > 1.0 -> OK, returns true)
        // Step 1: processOpex with small delta -> OPEX=1.0 (cash=500 > 1.0 -> OK, returns true)
        $opexOk = $handler->processOpex($well, 1, 1, 0.001, 1_000_000.0, null);
        $this->assertTrue($opexOk, 'processOpex musi przejsc (wystarczy kasy) / must pass (enough cash)');
        $opexCost = $loop->totalCosts; // ~= 1.0

        // Krok 2: processProduction z pipeline opex_per_tick=800 > pozostale cash (498.9)
        // Step 2: processProduction with pipeline opex_per_tick=800 > remaining cash (498.9)
        $pipeline = [
            'id'              => 1,
            'real_capacity_bph' => 100000.0, // wysoki cap: te testy mierza koszty, nie przepustowosc / high cap: these tests measure costs, not throughput
            'opex_per_tick'   => 800.0,
            'opex_per_bbl'    => 0.0,
            'transport_loss'  => 0.0,
            'status'          => 'active',
            '_is_operational' => true,
        ];
        $handler->processProduction(
            $well, 1, 1, 0.001, 1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, $pipeline,
            1.0, 0.0, null, [], null
        );

        // totalCosts = opex(1.0) + pipeline(800) = 801.0
        // finTransport = min(800, ~499) ≈ 499 (przyciety)
        $this->assertEqualsWithDelta(800.0 + $opexCost, $loop->totalCosts, 1.0,
            'totalCosts musi zawierac pelny koszt rurociagu (800) bez przycinania');
        $this->assertLessThan($loop->totalCosts - $opexCost, $loop->finTransport + 1.0,
            'finTransport jest przyciety do dostepnej gotowki, totalCosts nie jest');
        $this->assertGreaterThan($loop->finTransport, $loop->totalCosts - $opexCost - 0.01,
            'C3: totalCosts zawiera wiecej niz finTransport (roznica = niedobor)');
    }

    /**
     * Podatek regionalny: totalCosts += pelna kwota podatku.
     * Regional tax: totalCosts += full tax amount.
     *
     * Setup: duza gotowka, 30% podatek. totalCosts musi sie zwiekszyc o kwote podatku,
     * ktora rowna sie finTax (gdy gotowka wystarcza).
     * Setup: large cash, 30% tax. totalCosts must grow by the tax amount,
     * which equals finTax when cash is sufficient.
     */
    public function testTotalCostsTracksRegionalTaxAmount(): void
    {
        $this->seedWell(1, 1);
        [$handler, $loop] = $this->makeHandler(10_000_000.0);

        $well                    = $this->defaultWellRow(1, 1);
        $well['region_tax_rate'] = 0.30; // 30% podatek

        $handler->processOpex($well, 1, 1, 0.001, 1_000_000.0, null);
        $costsBefore = $loop->totalCosts;

        $handler->processProduction(
            $well, 1, 1, 0.001, 1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, null,
            1.0, 0.0, null, [], null
        );

        $taxAdded = $loop->totalCosts - $costsBefore;
        $this->assertGreaterThan(0.0, $taxAdded,
            'totalCosts musi wzrosnac o kwote podatku regionalnego');
        // Przy wystarczajacej gotowce: finTax == totalCosts_delta (brak przycinania)
        // With sufficient cash: finTax == totalCosts_delta (no clipping)
        $this->assertEqualsWithDelta($loop->finTax, $taxAdded, 0.01,
            'Przy wystarczajacej gotowce: totalCosts_delta == finTax (bez przycinania)');
    }

    // =========================================================================
    // C3: gwarancja globalna / C3: global guarantee
    // =========================================================================

    /**
     * totalCosts >= suma fin akumulatorow (opex + transport + tax) zawsze.
     * totalCosts >= sum of fin accumulators (opex + transport + tax) always.
     *
     * Kumulatory fin* uzywaja min(cost, cash) - sa zawsze <= totalCosts.
     * Fin* accumulators use min(cost, cash) - they are always <= totalCosts.
     */
    public function testTotalCostsAlwaysGeAllFinAccumulators(): void
    {
        $this->seedWell(1, 1);
        // Malo gotowki: OPEX wyczerpie wszystko i processOpex zwroci false
        [$handler, $loop] = $this->makeHandler(50.0);

        $well = $this->defaultWellRow(1, 1);
        $handler->processOpex($well, 1, 1, 1.0, 1_000_000.0, null); // opex=1000 >> cash=50

        $sumFin = $loop->finOpex + $loop->finTransport + $loop->finTax;
        $this->assertGreaterThanOrEqual($sumFin, $loop->totalCosts,
            'C3: totalCosts >= finOpex + finTransport + finTax zawsze');
        $this->assertGreaterThan($sumFin, $loop->totalCosts - 0.001,
            'C3: totalCosts > suma fin akumulatorow gdy jest niedoplata');
    }
}
