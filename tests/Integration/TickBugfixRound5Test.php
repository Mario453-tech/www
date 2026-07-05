<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';

/**
 * Testy regresyjne rundy 5 (analiza systemu ticku).
 * Round-5 regression tests (tick engine review).
 *
 * C1 — koszt incydentu i koszt katastrofy przemyslowej musza trafiac do loopCtx->totalCosts
 *      (realny zapis DB w saveCashAndTick: cash = GREATEST(0, cash - totalCosts)).
 *      Wczesniej ruszaly tylko finIncident/playerCash, wiec katastrofy byly finansowo darmowe.
 * H2 — zdarzenie transportowe 'leak' skaluje sie do biezacej wysylki ($actual), nie do calego
 *      magazynu gracza; handleTransportEvent nie moze samodzielnie modyfikowac currentStorage.
 *
 * C1 — incident cost and industrial-disaster cost must be booked into loopCtx->totalCosts
 *      (the real DB write in saveCashAndTick). Previously they only touched finIncident/
 *      playerCash, so disasters were financially free.
 * H2 — transport 'leak' event scales with the current shipment ($actual), not the whole player
 *      storage; handleTransportEvent must not mutate currentStorage on its own.
 */
final class TickBugfixRound5Test extends SqliteIntegrationTestCase
{
    /** @return array{WellRiskHandler, WellLoopSection, WellProductionSection} */
    private function makeRiskHandler(float $playerCash, ?object $incidentSvc, WellService $wellSvc): array
    {
        $loopCtx = new class($playerCash) extends WellLoopSection {
            public function __construct(float $cash)
            {
                $this->playerCash         = $cash;
                $this->totalCosts         = 0.0;
                $this->finIncident        = 0.0;
                $this->incidentsTriggered = 0;
                $this->disastersTriggered = 0;
                $this->currentStorage     = 0.0;
                $this->ongoingDropCache   = [];
            }
        };

        $ctx = new class extends WellProductionSection {
            public function __construct() {}
        };
        $ctx->loopCtx              = $loopCtx;
        $ctx->wellService          = $wellSvc;
        $ctx->oilPrice             = 70.0;
        $ctx->gBalanceMults        = ['incident' => 1.0, 'disaster' => 1.0];
        $ctx->financeTechnicalMods = [];
        $ctx->financeLogisticsMods = ['incident_mult' => 1.0];
        $ctx->financeSafetyMods    = ['incident_mult' => 1.0, 'disaster_mult' => 1.0];
        $ctx->incidentSvc          = $incidentSvc;

        return [new WellRiskHandler($ctx), $loopCtx, $ctx];
    }

    /** @return array<string, mixed> */
    private function wellRow(): array
    {
        return [
            'id'                       => 1,
            'player_id'                => 1,
            'status'                   => 'active',
            'base_production_per_hour' => 50.0,
            'oil_richness'             => 1.0,
        ];
    }

    // =====================================================================
    // C1 — koszt incydentu do totalCosts / incident cost into totalCosts
    // =====================================================================
    public function testIncidentCostIsBookedIntoTotalCosts(): void
    {
        $incidentSvc = new class extends IncidentService {
            public function __construct() {}
            public function processTick(
                int $wellId, int $playerId, float $deltaHours,
                array $wellData, array $staffData = [], array $hseBonus = []
            ): array {
                return ['incident' => [
                    'prod_drop' => 30, 'cost' => 5000.0,
                    'level' => 'major', 'message' => 'test incident',
                ]];
            }
        };
        $wellSvc = new class extends WellService { public function __construct() {} };

        [$risk, $loop] = $this->makeRiskHandler(1_000_000.0, $incidentSvc, $wellSvc);

        $drop = $risk->processIncidents(
            $this->wellRow(), 1, 1, 1.0, [], null, null,
            null, null, null, null,
            ['spiralMultEffective' => 1.0, 'wearDegMult' => 1.0], 1.0, null
        );

        $this->assertEqualsWithDelta(5000.0, $loop->totalCosts, 0.001,
            'C1: koszt incydentu (5000) musi trafic do totalCosts (realny zapis DB)');
        $this->assertEqualsWithDelta(5000.0, $loop->finIncident, 0.001,
            'finIncident tez rosnie o 5000 (raport)');
        $this->assertEqualsWithDelta(995_000.0, $loop->playerCash, 0.001,
            'playerCash (wyplacalnosc) pomniejszona o 5000');
        $this->assertEqualsWithDelta(0.30, $drop, 0.0001, 'prod_drop 30% zwrocony jako 0.30');
    }

    // =====================================================================
    // C1 — koszt katastrofy do totalCosts / disaster cost into totalCosts
    // =====================================================================
    public function testDisasterCostIsBookedIntoTotalCosts(): void
    {
        $wellSvc = new class extends WellService {
            public function __construct() {}
            public function processDisasterRoll(int $wellId, float $deltaHours, array $hseBonus, float $combinedMult = 1.0): array
            {
                return ['disaster' => 'blowout', 'cost' => 8000.0, 'env_fine' => 2000.0];
            }
        };

        [$risk, $loop] = $this->makeRiskHandler(1_000_000.0, null, $wellSvc);

        $hit = $risk->processDisasterRoll(
            $this->wellRow(), 1, 1, 1.0, [],
            ['techSpecCatMult' => 1.0], 1.0, 1.0, null
        );

        $this->assertTrue($hit, 'processDisasterRoll musi zwrocic true gdy katastrofa wystapila');
        $this->assertEqualsWithDelta(10_000.0, $loop->totalCosts, 0.001,
            'C1: koszt katastrofy (8000 + 2000 kara) musi trafic do totalCosts');
        $this->assertEqualsWithDelta(10_000.0, $loop->finIncident, 0.001,
            'finIncident rosnie o pelny koszt katastrofy');
        $this->assertEqualsWithDelta(990_000.0, $loop->playerCash, 0.001,
            'playerCash pomniejszona o 10000');
    }

    // =====================================================================
    // C1 — brak katastrofy => zero kosztow / no disaster => no cost
    // =====================================================================
    public function testNoDisasterLeavesCostsUntouched(): void
    {
        $wellSvc = new class extends WellService {
            public function __construct() {}
            public function processDisasterRoll(int $wellId, float $deltaHours, array $hseBonus, float $combinedMult = 1.0): array
            {
                return ['disaster' => null];
            }
        };

        [$risk, $loop] = $this->makeRiskHandler(1_000_000.0, null, $wellSvc);
        $hit = $risk->processDisasterRoll($this->wellRow(), 1, 1, 1.0, [], ['techSpecCatMult' => 1.0], 1.0, 1.0, null);

        $this->assertFalse($hit);
        $this->assertEqualsWithDelta(0.0, $loop->totalCosts, 0.001, 'brak katastrofy => totalCosts bez zmian');
        $this->assertEqualsWithDelta(1_000_000.0, $loop->playerCash, 0.001, 'brak katastrofy => gotowka bez zmian');
    }

    // =====================================================================
    // H2 — leak nie rusza calego magazynu / leak does not touch whole storage
    // =====================================================================
    public function testTransportLeakNeverDrainsWholeStorage(): void
    {
        $loopCtx = new class extends WellLoopSection {
            public function __construct() { $this->currentStorage = 100000.0; }
        };
        $ctx = new class extends WellProductionSection { public function __construct() {} };
        $ctx->loopCtx  = $loopCtx;
        $ctx->oilPrice = 70.0;
        $handler       = new WellProductionHandler($ctx);

        $well = ['region_political_risk' => 1];
        $initialStorage = $loopCtx->currentStorage;

        // Wysoka szansa zdarzenia (deltaHours=5 => 0.55 dla rurociagu) przez wiele iteracji:
        // statystycznie wiele leakow wystapi. Po fixie handleTransportEvent NIGDY nie rusza
        // currentStorage, a strata leaku ($actual) jest ograniczona wysylka (<= 10 bbl).
        // High event chance over many iterations: statistically many leaks fire. After the fix
        // handleTransportEvent NEVER touches currentStorage and the leak loss is bounded by the
        // shipment ($actual, <= 10 bbl).
        for ($i = 0; $i < 400; $i++) {
            mt_srand($i);
            $actual = 10.0;
            $handler->handleTransportEvent(1, 1, 'rurociag', 5.0, [], $well, $actual, null);

            $this->assertEqualsWithDelta($initialStorage, $loopCtx->currentStorage, 0.001,
                "H2: handleTransportEvent nie moze modyfikowac currentStorage (iter {$i})");
            $this->assertGreaterThanOrEqual(0.0, $actual, 'wysylka nie moze zejsc ponizej 0');
            $this->assertLessThanOrEqual(10.0, $actual, 'wysylka nie moze wzrosnac');
        }
    }
}
