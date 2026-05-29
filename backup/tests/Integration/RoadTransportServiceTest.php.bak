<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/RoadTransportService.php';

/**
 * SQLite integration tests for RoadTransportService.
 *
 * Uywa SQLite :memory:  niezaleny od MySQL, szybki, deterministyczny.
 */
final class RoadTransportServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private RoadTransportService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db  = $this->createSqlitePdo();
        $this->svc = new RoadTransportService($this->db);
        // ensureSchema() tworzy tabele automatycznie w konstruktorze
    }

    // 
    // Schema auto-creation
    // 

    public function testSchemaCreatedOnConstruct(): void
    {
        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='well_road_configs'");
        $this->assertNotFalse($stmt->fetchColumn(), 'Tabela well_road_configs powinna istnie');

        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='well_road_incident_logs'");
        $this->assertNotFalse($stmt->fetchColumn(), 'Tabela well_road_incident_logs powinna istnie');
    }

    // 
    // ensureConfigsForPlayerWells
    // 

    public function testEnsureCreatesConfigOnlyForCiezarowkiWells(): void
    {
        $wells = [
            ['id' => 10, 'transport_type' => 'ciezarowki'],
            ['id' => 11, 'transport_type' => 'rurociag'],
            ['id' => 12, 'transport_type' => 'tankowiec'],
        ];

        $this->svc->ensureConfigsForPlayerWells(1, $wells);
        // Idempotentne  drugi call nie powinien duplikowa
        $this->svc->ensureConfigsForPlayerWells(1, $wells);

        $stmt = $this->db->query("SELECT well_id, truck_type FROM well_road_configs WHERE player_id = 1 ORDER BY well_id");
        $rows = $stmt->fetchAll();

        $this->assertCount(1, $rows, 'Tylko odwiert ciezarowki dostaje config');
        $this->assertSame('10', (string)$rows[0]['well_id']);
        $this->assertSame('standard', $rows[0]['truck_type']);
    }

    public function testEnsureSkipsEmptyWellsList(): void
    {
        $this->svc->ensureConfigsForPlayerWells(1, []);
        $stmt = $this->db->query("SELECT COUNT(*) FROM well_road_configs");
        $this->assertSame('0', (string)$stmt->fetchColumn());
    }

    // 
    // getConfigsByWellIds
    // 

    public function testGetConfigsByWellIdsReturnsCorrectRow(): void
    {
        // Wstaw manualnie konfiguracj heavy
        $this->db->exec(
            "INSERT INTO well_road_configs (player_id, well_id, truck_type, trip_capacity_bbl, cost_per_trip, incident_risk_mult)
             VALUES (1, 20, 'heavy', 50.0, 900.0, 0.8)"
        );

        $configs = $this->svc->getConfigsByWellIds(1, [20, 99999]);

        $this->assertArrayHasKey(20, $configs);
        $this->assertSame('heavy', $configs[20]['truck_type']);
        $this->assertEqualsWithDelta(50.0, (float)$configs[20]['trip_capacity_bbl'], 0.001);
        $this->assertEqualsWithDelta(900.0, (float)$configs[20]['cost_per_trip'], 0.001);
        $this->assertEqualsWithDelta(0.8, (float)$configs[20]['incident_risk_mult'], 0.001);
        $this->assertArrayNotHasKey(99999, $configs);
    }

    public function testGetConfigsByWellIdsEmptyInputReturnsEmpty(): void
    {
        $this->assertSame([], $this->svc->getConfigsByWellIds(1, []));
    }

    // 
    // processTick  brak produkcji (guard clause)
    // 

    public function testProcessTickZeroInputReturnsEmptyResult(): void
    {
        $result = $this->svc->processTick(1, 10, 0.0, 1.0, null, [], 1);

        $this->assertSame(0, $result['trips_total']);
        $this->assertSame(0, $result['trips_delivered']);
        $this->assertSame(0, $result['trips_lost']);
        $this->assertSame(0.0, $result['delivered_bbl']);
        $this->assertSame(0.0, $result['lost_bbl']);
        $this->assertSame(0.0, $result['cost']);
        $this->assertSame([], $result['incidents']);
    }

    public function testProcessTickZeroDeltaHoursReturnsEmptyResult(): void
    {
        $result = $this->svc->processTick(1, 10, 100.0, 0.0, null, [], 1);
        $this->assertSame(0, $result['trips_total']);
    }

    // 
    // processTick  risk_mult = 0  zero incydentw
    // 

    public function testProcessTickZeroRiskDeliversAllVolume(): void
    {
        $config = ['trip_capacity_bbl' => 25.0, 'cost_per_trip' => 500.0, 'incident_risk_mult' => 0.0];
        $result = $this->svc->processTick(1, 10, 100.0, 1.0, $config, ['failure_reduction' => 1.0], 1);

        $this->assertSame(4, $result['trips_total'],                     '100 / 25 = 4 kursy');
        $this->assertSame(4, $result['trips_delivered']);
        $this->assertSame(0, $result['trips_lost']);
        $this->assertEqualsWithDelta(100.0, $result['delivered_bbl'], 0.001);
        $this->assertSame(0.0, $result['lost_bbl']);
        $this->assertSame(2000.0, $result['cost'],                       '4  500 = 2000');
        $this->assertSame([], $result['incidents']);

        // Brak logu incydentw
        $cnt = $this->db->query("SELECT COUNT(*) FROM well_road_incident_logs")->fetchColumn();
        $this->assertSame('0', (string)$cnt);
    }

    // 
    // processTick  bilans wolumenu (delivered + lost = input)
    // 

    public function testProcessTickVolumeBalanceInvariant(): void
    {
        $config = ['trip_capacity_bbl' => 10.0, 'cost_per_trip' => 200.0, 'incident_risk_mult' => 0.5];
        $result = $this->svc->processTick(1, 10, 50.0, 1.0, $config, ['failure_reduction' => 1.0], 2);

        $this->assertEqualsWithDelta(
            50.0,
            $result['delivered_bbl'] + $result['lost_bbl'],
            0.01,
            'delivered + lost = input'
        );

        $this->assertSame(
            $result['trips_total'],
            $result['trips_delivered'] + $result['trips_lost'],
            'trips_delivered + trips_lost = trips_total'
        );

        $expectedCost = $result['trips_total'] * 200.0;
        $this->assertEqualsWithDelta($expectedCost, $result['cost'], 0.01);
    }

    // 
    // processTick  wymuszone incydenty (risk_mult ekstremalny)
    // 

    public function testProcessTickMaxRiskCreatesIncidents(): void
    {
        $config = ['trip_capacity_bbl' => 0.5, 'cost_per_trip' => 10.0, 'incident_risk_mult' => 99999.0];
        $result = $this->svc->processTick(1, 10, 100.0, 1.0, $config, ['failure_reduction' => 1.0], 1);

        // 200 kursw  95% szansie incydentu  P(0 incydentw)  0
        $this->assertGreaterThan(0.0, $result['lost_bbl']);
        $this->assertNotEmpty($result['incidents']);

        foreach ($result['incidents'] as $inc) {
            $this->assertContains($inc['type'], ['theft', 'raid', 'accident', 'sabotage', 'route_block']);
            $this->assertArrayHasKey('trip_idx', $inc);
            $this->assertArrayHasKey('lost_bbl', $inc);
        }

        // Wpis w logu incydentw
        $cnt = (int)$this->db->query("SELECT COUNT(*) FROM well_road_incident_logs")->fetchColumn();
        $this->assertGreaterThan(0, $cnt, 'Powinien by co najmniej 1 wiersz logu incydentw');
    }

    // 
    // processTick  domylne wartoci gdy config = null
    // 

    public function testProcessTickUsesDefaultsWhenConfigIsNull(): void
    {
        // null config  domylne: 25 bbl/kurs, 500/kurs, risk=1.0
        $result = $this->svc->processTick(1, 10, 25.0, 0.0001, null, ['failure_reduction' => 0.0], 1);
        // deltaHours bardzo maa  szansa incydentu bliska 0, 1 kurs
        $this->assertSame(1, $result['trips_total']);
        $this->assertSame(500.0, $result['cost'], '1  domylne 500 = 500');
    }
}
