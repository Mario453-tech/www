<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/OffshoreTransportService.php';

/**
 * SQLite integration tests for OffshoreTransportService.
 *
 * Uywa SQLite :memory: niezaleny od MySQL, szybki, deterministyczny.
 */
final class OffshoreTransportServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private OffshoreTransportService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db  = $this->createSqlitePdo();
        $this->svc = new OffshoreTransportService($this->db);
 // ensureSchema() tworzy tabele automatycznie w konstruktorze
    }

 // 
 // Schema auto-creation
 // 

    public function testSchemaCreatedOnConstruct(): void
    {
        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='well_offshore_configs'");
        $this->assertNotFalse($stmt->fetchColumn(), 'Tabela well_offshore_configs powinna istnie');

        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='well_offshore_incident_logs'");
        $this->assertNotFalse($stmt->fetchColumn(), 'Tabela well_offshore_incident_logs powinna istnie');
    }

 // 
 // ensureConfigsForPlayerWells
 // 

    public function testEnsureCreatesConfigOnlyForTankowiecWells(): void
    {
        $wells = [
            ['id' => 10, 'transport_type' => 'tankowiec'],
            ['id' => 11, 'transport_type' => 'rurociag'],
            ['id' => 12, 'transport_type' => 'ciezarowki'],
        ];

        $this->svc->ensureConfigsForPlayerWells(1, $wells);
 // Idempotentne drugi call nie powinien duplikowa
        $this->svc->ensureConfigsForPlayerWells(1, $wells);

        $stmt = $this->db->query("SELECT well_id, tanker_type FROM well_offshore_configs WHERE player_id = 1 ORDER BY well_id");
        $rows = $stmt->fetchAll();

        $this->assertCount(1, $rows, 'Tylko odwiert tankowiec dostaje config');
        $this->assertSame('10', (string)$rows[0]['well_id']);
        $this->assertSame('small', $rows[0]['tanker_type']);
    }

    public function testEnsureSkipsEmptyWellsList(): void
    {
        $this->svc->ensureConfigsForPlayerWells(1, []);
        $stmt = $this->db->query("SELECT COUNT(*) FROM well_offshore_configs");
        $this->assertSame('0', (string)$stmt->fetchColumn());
    }

 // 
 // getConfigsByWellIds
 // 

    public function testGetConfigsByWellIdsReturnsCorrectRow(): void
    {
        $this->db->exec(
            "INSERT INTO well_offshore_configs (player_id, well_id, tanker_type, shipment_capacity_bbl, cost_per_shipment, incident_risk_mult)
             VALUES (1, 20, 'medium', 75.0, 1800.0, 0.85)"
        );

        $configs = $this->svc->getConfigsByWellIds(1, [20, 99999]);

        $this->assertArrayHasKey(20, $configs);
        $this->assertSame('medium', $configs[20]['tanker_type']);
        $this->assertEqualsWithDelta(75.0, (float)$configs[20]['shipment_capacity_bbl'], 0.001);
        $this->assertEqualsWithDelta(1800.0, (float)$configs[20]['cost_per_shipment'], 0.001);
        $this->assertEqualsWithDelta(0.85, (float)$configs[20]['incident_risk_mult'], 0.001);
        $this->assertArrayNotHasKey(99999, $configs);
    }

    public function testGetConfigsByWellIdsEmptyInputReturnsEmpty(): void
    {
        $this->assertSame([], $this->svc->getConfigsByWellIds(1, []));
    }

 // 
 // processTick brak produkcji (guard clause)
 // 

    public function testProcessTickZeroInputReturnsEmptyResult(): void
    {
        $result = $this->svc->processTick(1, 10, 0.0, 1.0, null, [], 1);

        $this->assertSame(0, $result['shipments_total']);
        $this->assertSame(0, $result['shipments_delivered']);
        $this->assertSame(0, $result['shipments_lost']);
        $this->assertSame(0.0, $result['delivered_bbl']);
        $this->assertSame(0.0, $result['lost_bbl']);
        $this->assertSame(0.0, $result['cost']);
        $this->assertSame([], $result['incidents']);
    }

    public function testProcessTickZeroDeltaHoursReturnsEmptyResult(): void
    {
        $result = $this->svc->processTick(1, 10, 100.0, 0.0, null, [], 1);
        $this->assertSame(0, $result['shipments_total']);
    }

 // 
 // processTick risk_mult = 0 zero incydentw
 // 

    public function testProcessTickZeroRiskDeliversAllVolume(): void
    {
        $config = ['shipment_capacity_bbl' => 30.0, 'cost_per_shipment' => 800.0, 'incident_risk_mult' => 0.0];
        $result = $this->svc->processTick(1, 10, 90.0, 1.0, $config, ['failure_reduction' => 1.0], 1);

        $this->assertSame(3, $result['shipments_total'],                  '90 / 30 = 3 rejsy');
        $this->assertSame(0, $result['shipments_lost']);
        $this->assertEqualsWithDelta(90.0, $result['delivered_bbl'], 0.001);
        $this->assertSame(0.0, $result['lost_bbl']);
        $this->assertSame(2400.0, $result['cost'],                        '3  800 = 2400');
        $this->assertSame([], $result['incidents']);

        $cnt = $this->db->query("SELECT COUNT(*) FROM well_offshore_incident_logs")->fetchColumn();
        $this->assertSame('0', (string)$cnt);
    }

 // 
 // processTick bilans wolumenu (delivered + lost = input)
 // 

    public function testProcessTickVolumeBalanceInvariant(): void
    {
        $config = ['shipment_capacity_bbl' => 15.0, 'cost_per_shipment' => 600.0, 'incident_risk_mult' => 0.5];
        $result = $this->svc->processTick(1, 10, 60.0, 1.0, $config, ['failure_reduction' => 1.0], 2);

        $this->assertEqualsWithDelta(
            60.0,
            $result['delivered_bbl'] + $result['lost_bbl'],
            0.01,
            'delivered + lost = input'
        );

        $expectedCost = $result['shipments_total'] * 600.0;
        $this->assertEqualsWithDelta($expectedCost, $result['cost'], 0.01);

        $this->assertSame(4, $result['shipments_total'], '60 / 15 = 4 rejsy');
    }

 // 
 // processTick wymuszone incydenty (risk_mult ekstremalny)
 // 

    public function testProcessTickMaxRiskCreatesIncidents(): void
    {
        $config = ['shipment_capacity_bbl' => 0.5, 'cost_per_shipment' => 10.0, 'incident_risk_mult' => 99999.0];
        $result = $this->svc->processTick(1, 10, 100.0, 1.0, $config, ['failure_reduction' => 1.0], 1);

 // 200 rejsw 95% szansie incydentu P(0 incydentw) 0
        $this->assertGreaterThan(0.0, $result['lost_bbl']);
        $this->assertNotEmpty($result['incidents']);

        foreach ($result['incidents'] as $inc) {
            $this->assertContains($inc['type'], ['storm', 'breakdown', 'delay', 'piracy']);
            $this->assertArrayHasKey('shipment_idx', $inc);
            $this->assertArrayHasKey('lost_bbl', $inc);
 // Strata nigdy nie przekracza wolumenu per rejs
            $this->assertLessThanOrEqual(0.5 + 0.0001, $inc['lost_bbl']);
        }

        $cnt = (int)$this->db->query("SELECT COUNT(*) FROM well_offshore_incident_logs")->fetchColumn();
        $this->assertGreaterThan(0, $cnt, 'Powinien by co najmniej 1 wiersz logu incydentw');
    }

 // 
 // processTick typy incydentw: strata czciowa vs pena
 // 

    public function testStormAndDelayArePossiblyPartialLoss(): void
    {
 // Testujemy computeShipmentLoss przez wiele iteracji przy wymuszonych incydentach
 // storm: 20-60% vol, delay: 10-30% vol (zawsze < 100% jeli brak piracy/breakdown)
 // Uywamy refleksji aby wywoa prywatn metod bezporednio
        $ref = new ReflectionMethod(OffshoreTransportService::class, 'computeShipmentLoss');
        $ref->setAccessible(true);

        $volPerShipment = 100.0;

 // Storm: zakres 20-60%
        $stormLosses = [];
        for ($i = 0; $i < 100; $i++) {
            $stormLosses[] = $ref->invoke($this->svc, 'storm', $volPerShipment);
        }
        $this->assertGreaterThanOrEqual(20.0, min($stormLosses), 'Storm: min strata >= 20%');
        $this->assertLessThanOrEqual(60.01, max($stormLosses), 'Storm: max strata <= 60% (fp epsilon)');

 // Delay: zakres 10-30%
        $delayLosses = [];
        for ($i = 0; $i < 100; $i++) {
            $delayLosses[] = $ref->invoke($this->svc, 'delay', $volPerShipment);
        }
        $this->assertGreaterThanOrEqual(10.0, min($delayLosses), 'Delay: min strata >= 10%');
        $this->assertLessThanOrEqual(30.01, max($delayLosses), 'Delay: max strata <= 30% (fp epsilon)');

 // Piracy + breakdown: zawsze 100%
        $this->assertEqualsWithDelta($volPerShipment, $ref->invoke($this->svc, 'piracy', $volPerShipment), 0.001);
        $this->assertEqualsWithDelta($volPerShipment, $ref->invoke($this->svc, 'breakdown', $volPerShipment), 0.001);
    }

 // 
 // processTick domylne wartoci gdy config = null
 // 

    public function testProcessTickUsesDefaultsWhenConfigIsNull(): void
    {
 // null config domylne: 30 bbl/rejs, 800/rejs, risk=1.0
        $result = $this->svc->processTick(1, 10, 30.0, 0.0001, null, ['failure_reduction' => 0.0], 1);
 // deltaHours bardzo maa szansa incydentu bliska 0, 1 rejs
        $this->assertSame(1, $result['shipments_total']);
        $this->assertSame(800.0, $result['cost'], '1  domylne 800 = 800');
    }
}
