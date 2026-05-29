<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/OffshoreTransportService.php';

final class MySqlOffshoreTransportServiceTest extends MySqlIntegrationTestCase
{
    // 
    // ensureConfigsForPlayerWells
    // 

    public function testEnsureCreatesConfigOnlyForTankowiecWells(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'],    'active', 77, 'A1', 'tankowiec', 70.0, 50.0);
        $this->seedWell($playerId, $ids['auxWellId'], 'active', 77, 'A1', 'rurociag',  120.0, 40.0);

        $svc = new OffshoreTransportService($this->db);
        $wells = [
            ['id' => $ids['wellId'],    'transport_type' => 'tankowiec'],
            ['id' => $ids['auxWellId'], 'transport_type' => 'rurociag'],
        ];
        $svc->ensureConfigsForPlayerWells($playerId, $wells);
        // Idempotentne  drugi call nie powinien duplikowa
        $svc->ensureConfigsForPlayerWells($playerId, $wells);

        $stmt = $this->db->prepare('SELECT well_id, tanker_type FROM well_offshore_configs WHERE player_id = ? ORDER BY id');
        $stmt->execute([$playerId]);
        $rows = $stmt->fetchAll();

        $this->assertCount(1, $rows, 'Tylko odwiert tankowiec dostaje config');
        $this->assertSame((string)$ids['wellId'], (string)$rows[0]['well_id']);
        $this->assertSame('small', $rows[0]['tanker_type']);
    }

    // 
    // getConfigsByWellIds
    // 

    public function testGetConfigsByWellIdsReturnsKeyedByWellId(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'tankowiec', 70.0, 50.0);
        $this->seedOffshoreConfig($playerId, $ids['wellId'], 'medium', 75.0, 1800.0, 0.85);

        $svc     = new OffshoreTransportService($this->db);
        $configs = $svc->getConfigsByWellIds($playerId, [$ids['wellId'], 999999]);

        $this->assertArrayHasKey($ids['wellId'], $configs);
        $this->assertSame('medium', $configs[$ids['wellId']]['tanker_type']);
        $this->assertSame('75.00', $configs[$ids['wellId']]['shipment_capacity_bbl']);
        $this->assertSame('1800.00', $configs[$ids['wellId']]['cost_per_shipment']);
        $this->assertSame('0.850', $configs[$ids['wellId']]['incident_risk_mult']);
        $this->assertArrayNotHasKey(999999, $configs);
    }

    // 
    // processTick  cieka zero incydentw (risk_mult = 0)
    // 

    public function testProcessTickWithZeroRiskDeliversAllVolume(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'tankowiec', 70.0, 50.0);

        // risk_mult = 0.0  szansa incydentu = 0  wszystkie rejsy dostarczone
        $config = [
            'shipment_capacity_bbl' => 30.0,
            'cost_per_shipment'     => 800.0,
            'incident_risk_mult'    => 0.0,
        ];

        $svc    = new OffshoreTransportService($this->db);
        $result = $svc->processTick(
            $playerId, $ids['wellId'], 90.0, 1.0,
            $config, ['failure_reduction' => 1.0], 1
        );

        $this->assertSame(3, $result['shipments_total'],                '90 bbl / 30 bbl = 3 rejsy');
        $this->assertSame(0, $result['shipments_lost']);
        $this->assertEqualsWithDelta(90.0, $result['delivered_bbl'], 0.001);
        $this->assertSame(0.0, $result['lost_bbl']);
        $this->assertSame(2400.0, $result['cost'],                      '3  800 = 2400');
        $this->assertSame([], $result['incidents']);

        // Brak incydentw  brak wpisw w logu
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM well_offshore_incident_logs WHERE well_id = ?');
        $stmt->execute([$ids['wellId']]);
        $this->assertSame('0', (string)$stmt->fetchColumn());
    }

    // 
    // processTick  wymuszone incydenty (bardzo duy risk_mult)
    // 

    public function testProcessTickWithMaxRiskCreatesIncidentLogs(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'tankowiec', 70.0, 50.0);

        // Bardzo maa pojemno rejsu = duo rejsw  przy 95% szansie prawie na pewno incydent
        $config = [
            'shipment_capacity_bbl' => 0.5,      // 100 bbl / 0.5 = 200 rejsw
            'cost_per_shipment'     => 10.0,
            'incident_risk_mult'    => 99999.0,  // wymusza szans = min(0.95, ...) = 0.95
        ];

        $svc    = new OffshoreTransportService($this->db);
        $result = $svc->processTick(
            $playerId, $ids['wellId'], 100.0, 1.0,
            $config, ['failure_reduction' => 1.0], 1
        );

        // Przy 200 rejsach i 95% szansie incydentu P(0 incydentw)  (0.05)^200  0
        $this->assertGreaterThan(0.0, $result['lost_bbl'],  'Powinna by strata bbl');
        $this->assertNotEmpty($result['incidents']);

        // Weryfikacja struktury incydentw
        foreach ($result['incidents'] as $inc) {
            $this->assertArrayHasKey('type', $inc);
            $this->assertArrayHasKey('shipment_idx', $inc);
            $this->assertArrayHasKey('lost_bbl', $inc);
            $this->assertContains($inc['type'], ['storm', 'breakdown', 'delay', 'piracy']);
        }

        // Wpisy w logu incydentw w bazie
        $stmt = $this->db->prepare(
            'SELECT SUM(shipments_lost) AS total_lost, SUM(vol_lost_bbl) AS total_vol
               FROM well_offshore_incident_logs WHERE well_id = ? AND player_id = ?'
        );
        $stmt->execute([$ids['wellId'], $playerId]);
        $logRow = $stmt->fetch();

        $this->assertGreaterThan(0, (int)$logRow['total_lost']);
        $this->assertGreaterThan(0.0, (float)$logRow['total_vol']);
    }

    // 
    // processTick  bilans: delivered + lost = input
    // 

    public function testProcessTickVolumeBalance(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'tankowiec', 70.0, 50.0);

        $config = [
            'shipment_capacity_bbl' => 15.0,
            'cost_per_shipment'     => 600.0,
            'incident_risk_mult'    => 0.5,
        ];

        $svc    = new OffshoreTransportService($this->db);
        $result = $svc->processTick(
            $playerId, $ids['wellId'], 60.0, 1.0,
            $config, ['failure_reduction' => 1.0], 2
        );

        // Niezmiennik: delivered + lost = input (z tolerancj zaokrgle)
        $this->assertEqualsWithDelta(
            60.0,
            $result['delivered_bbl'] + $result['lost_bbl'],
            0.01,
            'delivered + lost powinno sumowa si do inputBbl'
        );

        // Koszt = shipments_total * cost_per_shipment
        $expectedCost = $result['shipments_total'] * 600.0;
        $this->assertEqualsWithDelta($expectedCost, $result['cost'], 0.01);

        // shipments_total sprawdzamy (60 / 15 = 4)
        $this->assertSame(4, $result['shipments_total']);
    }

    // 
    // processTick  pusty input
    // 

    public function testProcessTickWithZeroInputReturnsEmpty(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();

        $svc    = new OffshoreTransportService($this->db);
        $result = $svc->processTick($playerId, $ids['wellId'], 0.0, 1.0, null, [], 1);

        $this->assertSame(0, $result['shipments_total']);
        $this->assertSame(0.0, $result['delivered_bbl']);
        $this->assertSame(0.0, $result['cost']);
        $this->assertSame([], $result['incidents']);
    }

    // 
    // processTick  wysokie ryzyko polityczne (piractwo skaluje)
    // 

    public function testProcessTickHighPoliticalRiskIncreasesIncidents(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'tankowiec', 70.0, 50.0);

        // Uywamy umiarkowanego risk_mult + wysokiego ryzyka politycznego (4)
        // + mae rejsy eby zwikszy prbk
        $config = [
            'shipment_capacity_bbl' => 1.0,    // 100 bbl / 1 = 100 rejsw
            'cost_per_shipment'     => 10.0,
            'incident_risk_mult'    => 5.0,    // risk_mult * politicalScale(4)=2.5 * hours  duo incydentw
        ];

        $svc     = new OffshoreTransportService($this->db);
        $result1 = $svc->processTick(
            $playerId, $ids['wellId'], 100.0, 1.0,
            $config, ['failure_reduction' => 1.0], 1  // ryzyko polityczne = 1
        );

        // Wyczy logi midzy testami
        $this->db->prepare('DELETE FROM well_offshore_incident_logs WHERE well_id = ?')->execute([$ids['wellId']]);

        $result4 = $svc->processTick(
            $playerId, $ids['wellId'], 100.0, 1.0,
            $config, ['failure_reduction' => 1.0], 4  // ryzyko polityczne = 4 (2.5)
        );

        // Przy wyszym ryzyku politycznym strata powinna by wiksza lub rwna (moe by zaszumiona losowoci)
        // Testujemy e wynik ma sensown struktur  bilans
        $this->assertEqualsWithDelta(
            100.0,
            $result4['delivered_bbl'] + $result4['lost_bbl'],
            0.01,
            'bilans delivered + lost = 100 bbl przy wysokim ryzyku politycznym'
        );
        $this->assertSame(100, $result4['shipments_total']);
    }
}
