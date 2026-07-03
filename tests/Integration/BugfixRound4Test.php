<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/WellService.php';
require_once dirname(__DIR__, 2) . '/src/WellPipelineService.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubTickService.php';
require_once dirname(__DIR__, 2) . '/src/IncidentService.php';
require_once dirname(__DIR__, 2) . '/src/OutboundLegService.php';
require_once dirname(__DIR__, 2) . '/src/DisasterMessages.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellLoopSection.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellProductionSection.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellProductionHandler.php';
require_once dirname(__DIR__, 2) . '/src/TransportConfigService.php';

/**
 * Testy regresyjne dla partii poprawek rundy 4 (rurociagi / morskie / transport / incydenty).
 * Regression tests for the round-4 bugfix batch (pipelines / marine / transport / incidents).
 */
final class BugfixRound4Test extends SqliteIntegrationTestCase
{
    // ------------------------------------------------------------------
    // C1: triggerSurfaceSpill nie odejmuje ropy z magazynu bezposrednio —
    // jedynym zapisem jest roznicowy w PlayersSection (bylo: podwojna strata).
    // C1: triggerSurfaceSpill must not deduct storage directly — the only
    // write is PlayersSection's differential one (was: double loss).
    // ------------------------------------------------------------------
    public function testSurfaceSpillDoesNotTouchStorageDirectly(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE storage (player_id INTEGER PRIMARY KEY, used REAL NOT NULL, capacity REAL NOT NULL)');
        $db->exec('CREATE TABLE failure_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, well_id INTEGER,
            failure_type TEXT, repair_cost REAL, environmental_fine REAL,
            production_lost_bbl REAL, reservoir_loss_pct REAL, description TEXT, resolved INTEGER
        )');
        $db->exec('CREATE TABLE industrial_disasters (
            id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, disaster_type TEXT,
            severity TEXT, repair_cost REAL, env_fine REAL, oil_lost REAL, hse_active INTEGER,
            hse_skill INTEGER, proc_level INTEGER, proc_integrity REAL, description TEXT, status TEXT
        )');
        $db->exec("INSERT INTO storage (player_id, used, capacity) VALUES (1, 100000, 120000)");

        $svc = (new ReflectionClass(WellService::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($svc, WellService::class, 'db', $db);

        $result = $svc->triggerSurfaceSpill(1, 100000.0, []);

        $this->assertSame('surface_spill', $result['disaster'], 'Spill must be recorded');
        $this->assertGreaterThan(0, (float)$result['oil_lost'], 'Spill must report lost oil');

        $used = (float)$db->query('SELECT used FROM storage WHERE player_id = 1')->fetchColumn();
        $this->assertEqualsWithDelta(100000.0, $used, 0.001,
            'Storage must be untouched by triggerSurfaceSpill (differential write happens in PlayersSection)');
    }

    // ------------------------------------------------------------------
    // C4: togglePipeline — 'damaged' nie do przelaczenia; resume nie kasuje
    // aktywnego wycieku ani nie wskrzesza kondycji 0.
    // C4: togglePipeline — 'damaged' is not toggleable; resume must not clear
    // an active leak nor resurrect zero condition.
    // ------------------------------------------------------------------
    private function makePipelineService(PDO $db): WellPipelineService
    {
        $svc = (new ReflectionClass(WellPipelineService::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($svc, WellPipelineService::class, 'db', $db);
        return $svc;
    }

    private function createPipelineTables(PDO $db): void
    {
        $db->exec('CREATE TABLE well_pipelines (
            id INTEGER PRIMARY KEY, player_id INTEGER, well_id INTEGER,
            condition_pct REAL NOT NULL DEFAULT 100, status TEXT NOT NULL,
            leak_started_at TEXT NULL, updated_at TEXT NULL
        )');
        $db->exec('CREATE TABLE well_pipeline_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, well_id INTEGER,
            pipeline_id INTEGER, event_type TEXT, severity TEXT, level TEXT, message TEXT
        )');
    }

    public function testTogglePipelineRejectsDamaged(): void
    {
        $db = $this->createSqlitePdo();
        $this->createPipelineTables($db);
        $db->exec("INSERT INTO well_pipelines (id, player_id, well_id, condition_pct, status)
                   VALUES (10, 1, 5, 0, 'damaged')");

        $svc = $this->makePipelineService($db);
        $res = $svc->togglePipeline(1, 10);

        $this->assertFalse($res['success'], 'Damaged pipeline must not be toggleable');
        $this->assertSame('pipeline_not_toggleable', $res['error']);
        $status = $db->query('SELECT status FROM well_pipelines WHERE id = 10')->fetchColumn();
        $this->assertSame('damaged', $status, 'Status must remain damaged (repair required)');
    }

    public function testTogglePipelineResumePreservesLeak(): void
    {
        $db = $this->createSqlitePdo();
        $this->createPipelineTables($db);
        $db->exec("INSERT INTO well_pipelines (id, player_id, well_id, condition_pct, status, leak_started_at)
                   VALUES (11, 1, 5, 75, 'suspended', '2026-01-01 10:00:00')");

        $svc = $this->makePipelineService($db);
        $res = $svc->togglePipeline(1, 11);

        $this->assertTrue($res['success']);
        $this->assertSame('leak', $res['new_status'],
            'Resume must restore the leak status, not erase it via condition mapping');
    }

    public function testTogglePipelineResumeZeroConditionBecomesDamaged(): void
    {
        $db = $this->createSqlitePdo();
        $this->createPipelineTables($db);
        // Zawieszony przed poprawka, kondycja spadla do 0 / suspended pre-fix, condition hit 0
        $db->exec("INSERT INTO well_pipelines (id, player_id, well_id, condition_pct, status)
                   VALUES (12, 1, 5, 0, 'suspended')");

        $svc = $this->makePipelineService($db);
        $res = $svc->togglePipeline(1, 12);

        $this->assertTrue($res['success']);
        $this->assertSame('damaged', $res['new_status'],
            'Resume at zero condition must yield damaged, not an operational critical');
    }

    // ------------------------------------------------------------------
    // M3: addBufferBbl capuje bufor do buffer_capacity_bbl i zwraca faktycznie
    // dodana ilosc (bylo: bufor rosl bez ograniczen).
    // M3: addBufferBbl caps the buffer at buffer_capacity_bbl and returns the
    // amount actually added (was: unbounded buffer growth).
    // ------------------------------------------------------------------
    public function testAddBufferBblCapsAtCapacity(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE logistics_hubs (
            id INTEGER PRIMARY KEY, buffer_current_bbl REAL NOT NULL, buffer_capacity_bbl REAL NOT NULL,
            updated_at TEXT NULL
        )');
        $db->exec("INSERT INTO logistics_hubs (id, buffer_current_bbl, buffer_capacity_bbl) VALUES (7, 900, 1000)");

        $hubSvc  = $this->createMock(HubService::class);
        $tickSvc = new HubTickService($db, $hubSvc);

        $added = $tickSvc->addBufferBbl(7, 500.0);

        $this->assertEqualsWithDelta(100.0, $added, 0.001, 'Only the free space (100) may be added');
        $buffer = (float)$db->query('SELECT buffer_current_bbl FROM logistics_hubs WHERE id = 7')->fetchColumn();
        $this->assertEqualsWithDelta(1000.0, $buffer, 0.001, 'Buffer must be capped at capacity');
    }

    // ------------------------------------------------------------------
    // H3: getOngoingProdDrop — spadek produkcji trwa przez okno `hours`
    // (albo do repaired_at), nie tylko w ticku wystapienia.
    // H3: getOngoingProdDrop — the production drop lasts for the `hours`
    // window (or until repaired_at), not only in the firing tick.
    // ------------------------------------------------------------------
    private function makeIncidentService(PDO $db): IncidentService
    {
        $svc = (new ReflectionClass(IncidentService::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($svc, IncidentService::class, 'db', $db);
        return $svc;
    }

    private function createIncidentTable(PDO $db): void
    {
        $db->exec('CREATE TABLE well_incidents (
            id INTEGER PRIMARY KEY AUTOINCREMENT, well_id INTEGER, player_id INTEGER,
            level TEXT, prod_drop INTEGER NOT NULL DEFAULT 0, hours INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL, repaired_at TEXT NULL
        )');
    }

    public function testOngoingProdDropAppliesInsideHoursWindow(): void
    {
        $db = $this->createSqlitePdo();
        $this->createIncidentTable($db);
        // Major sprzed godziny, trwa 24h / A major from an hour ago lasting 24h
        $db->exec("INSERT INTO well_incidents (well_id, player_id, level, prod_drop, hours, created_at)
                   VALUES (100, 1, 'major', 80, 24, datetime('now', '-1 hour'))");

        $svc = $this->makeIncidentService($db);
        $this->assertEqualsWithDelta(80.0, $svc->getOngoingProdDrop(100, 1), 0.001,
            'Unexpired unrepaired incident must keep throttling production');
    }

    public function testOngoingProdDropEndsAfterWindowOrRepair(): void
    {
        $db = $this->createSqlitePdo();
        $this->createIncidentTable($db);
        // Wygasly (3h temu, trwal 1h) / expired (3h ago, lasted 1h)
        $db->exec("INSERT INTO well_incidents (well_id, player_id, level, prod_drop, hours, created_at)
                   VALUES (100, 1, 'minor', 30, 1, datetime('now', '-3 hours'))");
        // Naprawiony / repaired
        $db->exec("INSERT INTO well_incidents (well_id, player_id, level, prod_drop, hours, created_at, repaired_at)
                   VALUES (100, 1, 'major', 90, 48, datetime('now', '-1 hour'), datetime('now'))");

        $svc = $this->makeIncidentService($db);
        $this->assertEqualsWithDelta(0.0, $svc->getOngoingProdDrop(100, 1), 0.001,
            'Expired and repaired incidents must not throttle production');
    }

    // Perf: wersja zbiorcza (jedno zapytanie na gracza) uzywana teraz w ticku musi zwracac
    // te sama semantyke co per-odwiert getOngoingProdDrop — aktywne w mapie, wygasle/naprawione poza.
    // Perf: the batched version (one query per player) now used in the tick must return the same
    // semantics as per-well getOngoingProdDrop — active in the map, expired/repaired excluded.
    public function testOngoingProdDropForPlayerBatchesActiveOnly(): void
    {
        $db = $this->createSqlitePdo();
        $this->createIncidentTable($db);
        // Odwiert 100: aktywny major (80%, 24h, sprzed godziny) / well 100: active major
        $db->exec("INSERT INTO well_incidents (well_id, player_id, level, prod_drop, hours, created_at)
                   VALUES (100, 1, 'major', 80, 24, datetime('now', '-1 hour'))");
        // Odwiert 101: wygasly — poza mapa / well 101: expired — excluded
        $db->exec("INSERT INTO well_incidents (well_id, player_id, level, prod_drop, hours, created_at)
                   VALUES (101, 1, 'minor', 30, 1, datetime('now', '-3 hours'))");
        // Odwiert 102: naprawiony — poza mapa / well 102: repaired — excluded
        $db->exec("INSERT INTO well_incidents (well_id, player_id, level, prod_drop, hours, created_at, repaired_at)
                   VALUES (102, 1, 'major', 90, 48, datetime('now', '-1 hour'), datetime('now'))");
        // Inny gracz — nie moze przeciekac do mapy gracza 1 / another player — must not leak into player 1's map
        $db->exec("INSERT INTO well_incidents (well_id, player_id, level, prod_drop, hours, created_at)
                   VALUES (103, 2, 'major', 70, 24, datetime('now', '-1 hour'))");

        $svc = $this->makeIncidentService($db);
        $map = $svc->getOngoingProdDropForPlayer(1);

        $this->assertSame([100], array_keys($map), 'Only the active incident well is in the map');
        $this->assertEqualsWithDelta(80.0, $map[100], 0.001, 'Active drop value preserved');
        $this->assertArrayNotHasKey(101, $map, 'Expired incident excluded');
        $this->assertArrayNotHasKey(102, $map, 'Repaired incident excluded');
        $this->assertArrayNotHasKey(103, $map, 'Other player\'s incident excluded');
    }

    // ------------------------------------------------------------------
    // L5/B4: leg-2 rurociag z real_capacity_bph=0 = zerowa przepustowosc
    // (bylo: brak capa = nieskonczona).
    // L5/B4: leg-2 pipeline with real_capacity_bph=0 = zero throughput
    // (was: no cap = unlimited).
    // ------------------------------------------------------------------
    public function testOutboundPipelineZeroCapacityBlocksFlow(): void
    {
        $svc  = new OutboundLegService([]);
        $pipe = [
            '_is_operational'   => true,
            'real_capacity_bph' => 0.0,
            'transport_loss'    => 0.0,
            'opex_per_tick'     => 0.0,
            'opex_per_bbl'      => 0.0,
        ];
        $res = $svc->compute('rurociag', $pipe, 500.0, 70.0, [], 1.0, [], 1);

        $this->assertEqualsWithDelta(0.0, (float)$res['capped_bbl'], 0.001, 'Zero-capacity pipe transports nothing');
        $this->assertEqualsWithDelta(500.0, (float)$res['excess_bbl'], 0.001, 'Entire volume returns to the hub buffer');
    }

    // ------------------------------------------------------------------
    // L4: computeRoad stosuje globalny mnoznik balansu opex jak pozostale koszty.
    // L4: computeRoad applies the global opex balance multiplier like other costs.
    // ------------------------------------------------------------------
    public function testOutboundRoadCostUsesGlobalOpexMultiplier(): void
    {
        $svc = new OutboundLegService(['ciezarowki' => ['cost_per_bbl' => 2.0, 'incident' => 0.0]]);

        $full = $svc->compute('ciezarowki', null, 100.0, 70.0,
            ['opex' => 1.0, 'transport_cost_mult' => 1.0], 0.001, ['failure_reduction' => 0.0], 1);
        $half = $svc->compute('ciezarowki', null, 100.0, 70.0,
            ['opex' => 0.5, 'transport_cost_mult' => 1.0], 0.001, ['failure_reduction' => 0.0], 1);

        $this->assertEqualsWithDelta(200.0, (float)$full['cost'], 0.01);
        $this->assertEqualsWithDelta(100.0, (float)$half['cost'], 0.01,
            'Halving the global opex multiplier must halve the leg-2 road cost');
    }

    // ------------------------------------------------------------------
    // H2: uszkodzony/wylaczony rurociag NIE przelacza sie cicho na ciezarowki —
    // zostaje 'rurociag' z capPct=0; 'suspended' przelacza na drogi (obietnica UI).
    // H2: a damaged/disabled pipeline does NOT silently fall back to trucks —
    // it stays 'rurociag' with capPct=0; 'suspended' switches to road (UI promise).
    // ------------------------------------------------------------------
    private function makeProductionCtx(PDO $db, array $pipelineCache): WellProductionSection
    {
        $ctx = new class extends WellProductionSection {
            public function __construct() {}
        };
        $ctx->db                   = $db;
        $ctx->transportConfig      = TransportConfigService::getDefaults();
        $ctx->wellPipelineCache    = $pipelineCache;
        return $ctx;
    }

    public function testDamagedPipelineStopsFlowInsteadOfTruckFallback(): void
    {
        $db  = $this->createSqlitePdo();
        $ctx = $this->makeProductionCtx($db, [
            5 => ['id' => 1, 'status' => 'damaged', '_is_operational' => false, 'real_capacity_bph' => 100.0],
        ]);
        $handler = new WellProductionHandler($ctx);

        $well = ['id' => 5, 'well_type' => 'onshore', 'transport_type' => 'rurociag',
                 'transport_capacity_pct' => 90.0, 'transport_opex_pct' => 5.0];
        [$type, , $capPct] = $handler->resolveTransportConfig($well, 5);

        $this->assertSame('rurociag', $type, 'Damaged pipeline must not reroute to trucks');
        $this->assertEqualsWithDelta(0.0, $capPct, 0.001, 'Damaged pipeline has zero throughput');
    }

    public function testSuspendedPipelineFallsBackToTrucks(): void
    {
        $db  = $this->createSqlitePdo();
        $ctx = $this->makeProductionCtx($db, [
            5 => ['id' => 1, 'status' => 'suspended', '_is_operational' => false, 'real_capacity_bph' => 100.0],
        ]);
        $handler = new WellProductionHandler($ctx);

        $well = ['id' => 5, 'well_type' => 'onshore', 'transport_type' => 'rurociag',
                 'transport_capacity_pct' => 90.0, 'transport_opex_pct' => 5.0];
        [$type] = $handler->resolveTransportConfig($well, 5);

        $this->assertSame('ciezarowki', $type,
            'Suspended pipeline switches the well to road transport (as the toggle message promises)');
    }
}
