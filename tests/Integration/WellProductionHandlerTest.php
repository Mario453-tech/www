<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';

/**
 * Integration tests for WellProductionHandler security fixes (Round 10).
 *
 * Pokrywa nastepujace bugi / Covers the following bugs:
 * - Fix #3: brakujace AND player_id = ? w UPDATE wells (izolacja gracza)
 *   Fix #3: missing AND player_id = ? in UPDATE wells (player isolation)
 * - Fix #4: fin* += cost zamiast fin* += min(cost, playerCash) (zawyzone kumulatory)
 *   Fix #4: fin* += cost instead of fin* += min(cost, playerCash) (overstated accumulators)
 * - Fix #5: martwy klucz regional_tax_rate zamiast region_tax_rate
 *   Fix #5: dead key regional_tax_rate instead of region_tax_rate
 * - Fix #6: bezposredni UPDATE storage w zdarzeniu leak (podwojne odjecie)
 *   Fix #6: direct UPDATE storage in leak event (double deduction)
 *
 * Testy SQLite uzywaja anonimowych podklas by obejsc Database singleton.
 * SQLite tests use anonymous subclasses to bypass the Database singleton.
 */
final class WellProductionHandlerTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
    }

    // =========================================================================
    // Schema / Helper
    // =========================================================================

    private function createSchema(): void
    {
        $this->db->exec("
            CREATE TABLE wells (
                id                    INTEGER PRIMARY KEY,
                player_id             INTEGER NOT NULL,
                status                TEXT    NOT NULL DEFAULT 'active',
                road_buffer_bbl       REAL    NOT NULL DEFAULT 0.0,
                marine_buffer_bbl     REAL    NOT NULL DEFAULT 0.0,
                reservoir_remaining   REAL    NOT NULL DEFAULT 1000000.0,
                last_production_at    TEXT    NULL,
                upkeep_cost_per_hour  REAL    NOT NULL DEFAULT 1000.0,
                base_production_per_hour REAL NOT NULL DEFAULT 50.0,
                well_type             TEXT    NOT NULL DEFAULT 'onshore',
                region_tax_rate       REAL    NOT NULL DEFAULT 0.0,
                transport_capacity_pct REAL   NOT NULL DEFAULT 100.0,
                equipment_tier        TEXT    NOT NULL DEFAULT 'standard',
                equipment_upgrade_level INTEGER NOT NULL DEFAULT 0
            )
        ");
        $this->db->exec("
            CREATE TABLE storage (
                id        INTEGER PRIMARY KEY,
                player_id INTEGER NOT NULL,
                used      REAL    NOT NULL DEFAULT 0.0
            )
        ");
    }

    private function seedWell(int $id, int $pid, string $status = 'active',
                               float $roadBuf = 0.0, float $marineBuf = 0.0,
                               float $reservoir = 1_000_000.0): void
    {
        $this->db->prepare(
            "INSERT INTO wells (id, player_id, status, road_buffer_bbl, marine_buffer_bbl, reservoir_remaining)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$id, $pid, $status, $roadBuf, $marineBuf, $reservoir]);
    }

    /** @return array{WellProductionHandler, object} */
    private function makeHandler(float $playerCash = 10_000_000.0): array
    {
        $db = $this->db;

        // Anonimowy WellLoopSection - omija konstruktor wymagajacy DB singleton / Anonymous WellLoopSection - bypasses constructor requiring DB singleton
        $loopCtx = new class($playerCash) extends WellLoopSection {
            public function __construct(float $cash) {
                $this->playerCash    = $cash;
                $this->currentStorage = 0.0;
                $this->finOpex        = 0.0;
                $this->finTransport   = 0.0;
                $this->finTax         = 0.0;
                $this->finBbl         = 0.0;
                $this->finRevenue     = 0.0;
                $this->finGross       = 0.0;
                $this->producedBbl    = 0.0;
                $this->deliveredBbl   = 0.0;
                $this->finLossBbl     = 0.0;
                $this->finLossValue   = 0.0;
                $this->preStorageLossBbl   = 0.0;
                $this->transportLossBbl    = 0.0;
                $this->transportEventLossBbl = 0.0;
                $this->transportCapacityLossBbl = 0.0;
                $this->storageBlockedBbl   = 0.0;
                $this->roadInTransitBbl    = 0.0;
                $this->marineInTransitBbl  = 0.0;
                $this->wellHubMap          = [];
                $this->hubInputAccum       = [];
                $this->hubWellDelivered    = [];
                $this->hubOutboundType     = [];
            }
            public function applyHubOrFallback(int $wellId, float &$actual, float $deltaHours): void {}
            public function recordPreStorageLoss(float $bbl, float $price): void {}
            public function recordHubWellDelivered(int $wellId, float $bbl): void {}
        };

        // Anonimowy WellService - omija Database::getInstance() / Anonymous WellService - bypasses Database::getInstance()
        $wellSvc = new class extends WellService {
            public function __construct() {}
            public function getOpexPerHour(array $well): float { return 1000.0; }
            public function getEffectiveProduction(array $well): float { return 50.0; }
        };

        // Anonimowy WellProductionSection - omija konstruktor / Anonymous WellProductionSection - bypasses constructor
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
            'id'                     => $id,
            'player_id'              => $pid,
            'status'                 => $status,
            'upkeep_cost_per_hour'   => 1000.0,
            'base_production_per_hour' => 50.0,
            'well_type'              => 'onshore',
            'transport_type'         => 'rurociag',
            'transport_capacity_pct' => 100.0,
            'equipment_tier'         => 'standard',
            'equipment_upgrade_level' => 0,
            'region_tax_rate'        => 0.0,
            'region_code'            => null,
            'region_political_risk'  => 1,
            'oil_richness'           => 1.0,
            'production_mode'        => 'normal',
            'production_boost_pct'   => 0.0,
            'effective_pressure'     => 1.0,
            'reservoir_remaining'    => 1_000_000.0,
            'road_buffer_bbl'        => 0.0,
            'marine_buffer_bbl'      => 0.0,
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
    // Fix #3: izolacja gracza w processOpex / Fix #3: player isolation in processOpex
    // =========================================================================

    /**
     * Gdy gotowka jest za mala na OPEX, status=paused_cash ustawiany TYLKO dla wlasciciela.
     * When cash is too low for OPEX, paused_cash set ONLY for the owner's well.
     */
    public function testProcessOpexPausesCashOnlyForOwnerWell(): void
    {
        $this->seedWell(100, 1); // gracz 1, odwiert 100 / player 1, well 100
        $this->seedWell(200, 2); // gracz 2, odwiert 200 / player 2, well 200

        [$handler, $loopCtx] = $this->makeHandler(0.0); // playerCash=0 -> brak OPEX / playerCash=0 -> no OPEX

        $well = $this->defaultWellRow(100, 1);
        $handler->processOpex($well, 100, 1, 1.0, 1_000_000.0, null);

        $this->assertSame('paused_cash', $this->db->query("SELECT status FROM wells WHERE id=100")->fetchColumn(),
            'Well 100 (owner) musi zostac wstrzymany / must be paused');
        $this->assertSame('active', $this->db->query("SELECT status FROM wells WHERE id=200")->fetchColumn(),
            'Well 200 (inny gracz) nie moze byc zmieniony / must not be changed');
    }

    /**
     * Wznowienie po pelnym magazynie: tylko odwiert wlasciciela dostaje status=active.
     * Resume after full storage: only owner's well gets status=active.
     */
    public function testProcessOpexResumeStorageOnlyForOwnerWell(): void
    {
        $this->seedWell(100, 1, 'paused_storage');
        $this->seedWell(200, 2, 'paused_storage');

        [$handler, $loopCtx] = $this->makeHandler(10_000_000.0);
        $loopCtx->currentStorage = 0.0; // jest miejsce / there is space

        $well = $this->defaultWellRow(100, 1, 'paused_storage');
        $handler->processOpex($well, 100, 1, 1.0, 1_000_000.0, null);

        $this->assertSame('active', $this->db->query("SELECT status FROM wells WHERE id=100")->fetchColumn(),
            'Well 100 (owner) musi byc wznowiony / must be resumed');
        $this->assertSame('paused_storage', $this->db->query("SELECT status FROM wells WHERE id=200")->fetchColumn(),
            'Well 200 (inny gracz) nie moze byc zmieniony / must not be changed');
    }

    /**
     * Wznowienie po braku kasy: tylko odwiert wlasciciela dostaje status=active.
     * Resume after no cash: only owner's well gets status=active.
     */
    public function testProcessOpexResumeCashOnlyForOwnerWell(): void
    {
        $this->seedWell(100, 1, 'paused_cash');
        $this->seedWell(200, 2, 'paused_cash');

        [$handler] = $this->makeHandler(10_000_000.0);

        $well = $this->defaultWellRow(100, 1, 'paused_cash');
        $handler->processOpex($well, 100, 1, 1.0, 1_000_000.0, null);

        $this->assertSame('active', $this->db->query("SELECT status FROM wells WHERE id=100")->fetchColumn(),
            'Well 100 (owner) musi byc wznowiony / must be resumed');
        $this->assertSame('paused_cash', $this->db->query("SELECT status FROM wells WHERE id=200")->fetchColumn(),
            'Well 200 (inny gracz) nie moze byc zmieniony / must not be changed');
    }

    // =========================================================================
    // Fix #3: izolacja gracza w processProduction / Fix #3: player isolation in processProduction
    // =========================================================================

    /**
     * Uszczuplenie rezerwuaru: UPDATE tylko dla odwiertu wlasciciela.
     * Reservoir depletion: UPDATE only for owner's well.
     */
    public function testReservoirDepletionOnlyForOwnerWell(): void
    {
        $this->seedWell(100, 1, 'active', 0.0, 0.0, 500_000.0);
        $this->seedWell(200, 2, 'active', 0.0, 0.0, 500_000.0);

        [$handler, $loopCtx] = $this->makeHandler(10_000_000.0);
        $loopCtx->currentStorage = 0.0;

        $well = $this->defaultWellRow(100, 1);
        $handler->processProduction(
            $well, 100, 1, 0.001,   // deltaHours=0.001 — minimalna produkcja / minimal production
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, null,
            1.0, 0.0, null, [], null
        );

        $res100 = (float)$this->db->query("SELECT reservoir_remaining FROM wells WHERE id=100")->fetchColumn();
        $res200 = (float)$this->db->query("SELECT reservoir_remaining FROM wells WHERE id=200")->fetchColumn();

        $this->assertLessThan(500_000.0, $res100, 'Rezerwuar well 100 (owner) musi sie zmniejszyc / must decrease');
        $this->assertEqualsWithDelta(500_000.0, $res200, 0.001, 'Rezerwuar well 200 (inny gracz) niezmieniony / must not change');
    }

    /**
     * Pelny magazyn: status=paused_storage ustawiany TYLKO dla wlasciciela.
     * Full storage: paused_storage set ONLY for owner's well.
     */
    public function testPausedStorageOnlyForOwnerWell(): void
    {
        $this->seedWell(100, 1);
        $this->seedWell(200, 2);

        [$handler, $loopCtx] = $this->makeHandler(10_000_000.0);
        $loopCtx->currentStorage = 1_000_000.0; // magazyn pelny / storage full

        $well = $this->defaultWellRow(100, 1);
        $handler->processProduction(
            $well, 100, 1, 1.0,
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, null,
            1.0, 0.0, null, [], null
        );

        $this->assertSame('paused_storage', $this->db->query("SELECT status FROM wells WHERE id=100")->fetchColumn(),
            'Well 100 (owner) musi byc wstrzymany storage / must be paused storage');
        $this->assertSame('active', $this->db->query("SELECT status FROM wells WHERE id=200")->fetchColumn(),
            'Well 200 (inny gracz) nie moze byc zmieniony / must not be changed');
    }

    /**
     * Aktywna produkcja: last_production_at i status=active ustawiane TYLKO dla wlasciciela.
     * Active production: last_production_at and status=active set ONLY for owner's well.
     */
    public function testLastProductionAtOnlyForOwnerWell(): void
    {
        $this->seedWell(100, 1);
        $this->seedWell(200, 2);

        [$handler, $loopCtx] = $this->makeHandler(10_000_000.0);
        $loopCtx->currentStorage = 0.0;

        $well = $this->defaultWellRow(100, 1);
        $handler->processProduction(
            $well, 100, 1, 0.0001,   // deltaHours maly = mala szansa zdarzenia / small = small event chance
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, null,
            1.0, 0.0, null, [], null
        );

        $ts100 = $this->db->query("SELECT last_production_at FROM wells WHERE id=100")->fetchColumn();
        $ts200 = $this->db->query("SELECT last_production_at FROM wells WHERE id=200")->fetchColumn();

        $this->assertNotNull($ts100, 'Well 100 (owner) musi miec last_production_at / must have last_production_at set');
        $this->assertNull($ts200, 'Well 200 (inny gracz) musi miec NULL last_production_at / must remain NULL');
    }

    // =========================================================================
    // Fix #3: izolacja SQL dla buforow (bezposredni test SQL)
    // Fix #3: SQL isolation for buffers (direct SQL test)
    // =========================================================================

    /**
     * SQL: UPDATE road_buffer_bbl z AND player_id nie modyfikuje cudzego odwiertu.
     * SQL: UPDATE road_buffer_bbl with AND player_id does not modify another player's well.
     *
     * Sprawdza dokladny ksztalt zapytania naprawionego w Fix #3.
     * Verifies the exact query shape fixed in Fix #3.
     */
    public function testRoadBufferSqlDoesNotTouchOtherPlayerWell(): void
    {
        $this->seedWell(100, 1, 'active', 0.0);
        $this->seedWell(200, 2, 'active', 0.0);

        // Normalna operacja: gracz 1, odwiert 100 / Normal operation: player 1, well 100
        $this->db->prepare(
            "UPDATE wells SET road_buffer_bbl = COALESCE(road_buffer_bbl, 0) + ? WHERE id = ? AND player_id = ?"
        )->execute([50.0, 100, 1]);

        $this->assertEqualsWithDelta(50.0, (float)$this->db->query("SELECT road_buffer_bbl FROM wells WHERE id=100")->fetchColumn(), 0.001);
        $this->assertEqualsWithDelta(0.0,  (float)$this->db->query("SELECT road_buffer_bbl FROM wells WHERE id=200")->fetchColumn(), 0.001,
            'Odwiert innego gracza nie moze byc zmieniony / Other player\'s well must not be changed');

        // Probus z blednym player_id: 0 wierszy dotkniete / Attempt with wrong player_id: 0 rows affected
        $stmt = $this->db->prepare(
            "UPDATE wells SET road_buffer_bbl = COALESCE(road_buffer_bbl, 0) + ? WHERE id = ? AND player_id = ?"
        );
        $stmt->execute([999.0, 100, 2]); // gracz 2 probuje ustawic bufor odwiertu gracza 1 / player 2 tries to touch player 1's well
        $this->assertSame(0, $stmt->rowCount(), 'UPDATE z blednym player_id musi dotyczyc 0 wierszy / UPDATE with wrong player_id must affect 0 rows');
        $this->assertEqualsWithDelta(50.0, (float)$this->db->query("SELECT road_buffer_bbl FROM wells WHERE id=100")->fetchColumn(), 0.001,
            'Bufor nie moze sie zmienic / Buffer must not change');
    }

    /**
     * SQL: UPDATE marine_buffer_bbl z AND player_id nie modyfikuje cudzego odwiertu.
     * SQL: UPDATE marine_buffer_bbl with AND player_id does not modify another player's well.
     */
    public function testMarineBufferSqlDoesNotTouchOtherPlayerWell(): void
    {
        $this->seedWell(100, 1, 'active', 0.0, 0.0);
        $this->seedWell(200, 2, 'active', 0.0, 0.0);

        $this->db->prepare(
            "UPDATE wells SET marine_buffer_bbl = COALESCE(marine_buffer_bbl, 0) + ? WHERE id = ? AND player_id = ?"
        )->execute([120.0, 100, 1]);

        $this->assertEqualsWithDelta(120.0, (float)$this->db->query("SELECT marine_buffer_bbl FROM wells WHERE id=100")->fetchColumn(), 0.001);
        $this->assertEqualsWithDelta(0.0,   (float)$this->db->query("SELECT marine_buffer_bbl FROM wells WHERE id=200")->fetchColumn(), 0.001,
            'Bufor morski innego gracza niezmieniony / Other player\'s marine buffer must not change');

        $stmt = $this->db->prepare(
            "UPDATE wells SET marine_buffer_bbl = COALESCE(marine_buffer_bbl, 0) + ? WHERE id = ? AND player_id = ?"
        );
        $stmt->execute([999.0, 100, 2]);
        $this->assertSame(0, $stmt->rowCount(), 'UPDATE z blednym player_id musi byc no-op / must be a no-op');
    }

    /**
     * SQL: UPDATE reservoir_remaining z AND player_id nie modyfikuje cudzego odwiertu.
     * SQL: UPDATE reservoir_remaining with AND player_id does not touch another player's well.
     */
    public function testReservoirSqlDoesNotTouchOtherPlayerWell(): void
    {
        $this->seedWell(100, 1, 'active', 0.0, 0.0, 500_000.0);
        $this->seedWell(200, 2, 'active', 0.0, 0.0, 500_000.0);

        $stmt = $this->db->prepare(
            "UPDATE wells SET reservoir_remaining = GREATEST(0, reservoir_remaining - ?) WHERE id = ? AND player_id = ?"
        );
        $stmt->execute([100.0, 100, 2]); // bledny player_id / wrong player_id
        $this->assertSame(0, $stmt->rowCount(), 'Bledny player_id: 0 wierszy / Wrong player_id: 0 rows');

        $this->assertEqualsWithDelta(500_000.0, (float)$this->db->query("SELECT reservoir_remaining FROM wells WHERE id=100")->fetchColumn(), 0.001,
            'Rezerwuar niezmieniony / Reservoir must not change');
    }

    /**
     * SQL: accident event UPDATE z AND player_id nie wstrzymuje cudzego odwiertu.
     * SQL: accident event UPDATE with AND player_id does not pause another player's well.
     */
    public function testAccidentSqlDoesNotTouchOtherPlayerWell(): void
    {
        $this->seedWell(100, 1, 'active');
        $this->seedWell(200, 2, 'active');

        // Symuluje naprawiony SQL wypadku / Simulates fixed accident SQL
        $stmt = $this->db->prepare("UPDATE wells SET status = 'paused_cash' WHERE id = ? AND player_id = ? AND status = 'active'");

        // Attempt from wrong player
        $stmt->execute([100, 2]);
        $this->assertSame(0, $stmt->rowCount(), 'Bledny player_id: odwiert nie moze byc wstrzymany / Wrong player_id: well must not be paused');
        $this->assertSame('active', $this->db->query("SELECT status FROM wells WHERE id=100")->fetchColumn());

        // Correct player
        $stmt->execute([100, 1]);
        $this->assertSame(1, $stmt->rowCount(), 'Poprawny player_id: 1 wiersz zmieniony / Correct player_id: 1 row affected');
        $this->assertSame('paused_cash', $this->db->query("SELECT status FROM wells WHERE id=100")->fetchColumn());

        // Well 200 untouched
        $this->assertSame('active', $this->db->query("SELECT status FROM wells WHERE id=200")->fetchColumn(),
            'Well 200 (inny gracz) niezmieniony / must remain untouched');
    }

    // =========================================================================
    // Fix #4: fin* nie zawyza sie powyzej dostepnej gotowki
    // Fix #4: fin* does not overstate beyond available cash
    // =========================================================================

    /**
     * Koszt rurociagu: finTransport += min(cost, cash), nie += cost.
     * Pipeline cost: finTransport += min(cost, cash), not += cost.
     */
    public function testPipelineCostClampsAccumulatorToAvailableCash(): void
    {
        $this->seedWell(100, 1);

        [$handler, $loopCtx] = $this->makeHandler(500.0); // cash < pipeline cost
        $loopCtx->currentStorage = 0.0;

        $well = $this->defaultWellRow(100, 1);

        // wellPipeline z opex_per_tick=1000 > playerCash=500 / with opex_per_tick=1000 > playerCash=500
        $pipeline = [
            'id'             => 1,
            'real_capacity_bph' => 100000.0, // wysoki cap: te testy mierza koszty, nie przepustowosc / high cap: these tests measure costs, not throughput
            'opex_per_tick'  => 1000.0,
            'opex_per_bbl'   => 0.0,
            'transport_loss' => 0.0,
            'status'         => 'active',
            '_is_operational' => true,
        ];
        $handler->processProduction(
            $well, 100, 1, 0.0001,
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, $pipeline,
            1.0, 0.0, null, [], null
        );

        $this->assertLessThanOrEqual(500.0, $loopCtx->finTransport,
            'finTransport musi byc <= dostepna gotowka / must be <= available cash');
        $this->assertGreaterThan(0.0, $loopCtx->finTransport,
            'finTransport musi byc > 0 (cos zostalo naliczone) / must be > 0 (something was charged)');
        $this->assertEqualsWithDelta(0.0, $loopCtx->playerCash, 0.001,
            'playerCash musi byc 0 (wyczerpany) / must be 0 (exhausted)');
    }

    /**
     * Zerowa gotowka: finTransport += 0, nie += pipelineCost.
     * Zero cash: finTransport += 0, not += pipelineCost.
     */
    public function testPipelineCostZeroCashAddsZeroToAccumulator(): void
    {
        $this->seedWell(100, 1);

        [$handler, $loopCtx] = $this->makeHandler(0.0); // brak kasy / no cash
        $loopCtx->currentStorage = 0.0;

        $well = $this->defaultWellRow(100, 1);
        $pipeline = [
            'id'             => 1,
            'real_capacity_bph' => 100000.0, // wysoki cap: te testy mierza koszty, nie przepustowosc / high cap: these tests measure costs, not throughput
            'opex_per_tick'  => 1000.0,
            'opex_per_bbl'   => 0.0,
            'transport_loss' => 0.0,
            'status'         => 'active',
            '_is_operational' => true,
        ];
        $handler->processProduction(
            $well, 100, 1, 0.0001,
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, $pipeline,
            1.0, 0.0, null, [], null
        );

        $this->assertEqualsWithDelta(0.0, $loopCtx->finTransport, 0.001,
            'finTransport musi byc 0 gdy brak gotowki / must be 0 when no cash');
    }

    /**
     * Transport OPEX%: finTransport += min(opex, cash).
     */
    public function testTransportOpexClampsAccumulator(): void
    {
        $this->seedWell(100, 1);

        [$handler, $loopCtx] = $this->makeHandler(10.0); // mala gotowka / small cash
        $loopCtx->currentStorage = 0.0;

        $well = $this->defaultWellRow(100, 1);
        // transportOpexPct=50 -> opex = actual*price*0.5 >> 10 / transportOpexPct=50 -> opex >> 10
        $handler->processProduction(
            $well, 100, 1, 0.0001,
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 50.0, null, // transportOpexPct=50%
            1.0, 0.0, null, [], null
        );

        $this->assertLessThanOrEqual(10.0, $loopCtx->finTransport,
            'finTransport OPEX nie moze przekraczac dostepnej gotowki / must not exceed available cash');
    }

    /**
     * Podatek regionalny: finTax += min(tax, cash).
     */
    public function testRegionalTaxClampsAccumulator(): void
    {
        $this->seedWell(100, 1);

        [$handler, $loopCtx] = $this->makeHandler(5.0); // 5 USD gotowki / 5 USD cash
        $loopCtx->currentStorage = 0.0;

        $well = $this->defaultWellRow(100, 1);
        $well['region_tax_rate'] = 0.50; // 50% podatek >> gotowka / 50% tax >> cash

        $handler->processProduction(
            $well, 100, 1, 0.0001,
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, null,
            1.0, 0.0, null, [], null
        );

        $this->assertLessThanOrEqual(5.0, $loopCtx->finTax,
            'finTax nie moze przekraczac dostepnej gotowki / must not exceed available cash');
    }

    // =========================================================================
    // Fix #5: region_tax_rate (poprawny klucz) zamiast martwego regional_tax_rate
    // Fix #5: region_tax_rate (correct key) instead of dead regional_tax_rate
    // =========================================================================

    /**
     * Podatek pobierany z region_tax_rate (jedyny poprawny klucz).
     * Tax read from region_tax_rate (the only correct key).
     */
    public function testRegionTaxRateKeyUsed(): void
    {
        $this->seedWell(100, 1);

        [$handler, $loopCtx] = $this->makeHandler(10_000_000.0);
        $loopCtx->currentStorage = 0.0;

        $well = $this->defaultWellRow(100, 1);
        $well['region_tax_rate'] = 1.0; // 100% podatek / 100% tax

        $handler->processProduction(
            $well, 100, 1, 0.0001,
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, null,
            1.0, 0.0, null, [], null
        );

        $this->assertGreaterThan(0.0, $loopCtx->finTax,
            'finTax > 0: region_tax_rate musi byc odczytane / region_tax_rate must be read');
    }

    /**
     * Martwy klucz regional_tax_rate jest ignorowany (zero podatku jesli tylko ten klucz).
     * Dead key regional_tax_rate is ignored (zero tax if only that key present).
     */
    public function testDeadRegionalTaxRateKeyProducesZeroTax(): void
    {
        $this->seedWell(100, 1);

        [$handler, $loopCtx] = $this->makeHandler(10_000_000.0);
        $loopCtx->currentStorage = 0.0;

        $well = $this->defaultWellRow(100, 1);
        // Tylko stary martwy klucz - poprawny region_tax_rate nieobecny / Only old dead key - correct region_tax_rate absent
        unset($well['region_tax_rate']);
        $well['regional_tax_rate'] = 1.0; // stary martwy klucz / old dead key

        $handler->processProduction(
            $well, 100, 1, 0.0001,
            1_000_000.0, [], null, $this->defaultMults(),
            'rurociag', [], 100.0, 0.0, null,
            1.0, 0.0, null, [], null
        );

        $this->assertEqualsWithDelta(0.0, $loopCtx->finTax, 0.001,
            'Martwy klucz regional_tax_rate musi byc ignorowany / Dead key must be ignored');
    }

    // =========================================================================
    // Fix #6: zdarzenie leak nie robi bezposredniego UPDATE storage
    // Fix #6: leak event does not perform direct UPDATE storage
    // =========================================================================

    /**
     * Po zdarzeniu leak tabela storage w DB pozostaje nienaruszona.
     * After a leak event the storage table in DB remains untouched.
     * In-memory currentStorage jest redukowany (poprawne zachowanie).
     * In-memory currentStorage is reduced (correct behaviour).
     */
    public function testLeakEventDoesNotDirectlyUpdateStorageTable(): void
    {
        $this->db->prepare("INSERT INTO storage (id, player_id, used) VALUES (?, ?, ?)")
            ->execute([1, 1, 5000.0]);

        [$handler, $loopCtx] = $this->makeHandler(10_000_000.0);
        $loopCtx->currentStorage = 5000.0;

        // Wywolujemy handleTransportEvent bezposrednio dla pewnosci / Call handleTransportEvent directly for certainty
        // Ustawiamy actual=100 aby zdarzenie leak naliczalo od currentStorage
        // Set actual=100 so the leak event charges from currentStorage
        $well = $this->defaultWellRow(100, 1);

        // Symulujemy wiele prob zeby trafic na zdarzenie leak / Simulate many tries to hit the leak event
        // Glowne sprawdzenie: NIEZALEZNIE od rodzaju zdarzenia, tabela storage nie jest zmieniana.
        // Main check: REGARDLESS of event type, the storage table must not be modified.
        for ($i = 0; $i < 50; $i++) {
            $a = 100.0;
            $handler->handleTransportEvent(1, 100, 'rurociag', 1.0, [], $well, $a, null);
        }

        $storedUsed = (float)$this->db->query("SELECT used FROM storage WHERE player_id = 1")->fetchColumn();
        $this->assertEqualsWithDelta(5000.0, $storedUsed, 0.001,
            'Tabela storage nie moze byc bezposrednio zmieniona w tiku / Storage table must not be directly changed mid-tick');
    }
}
