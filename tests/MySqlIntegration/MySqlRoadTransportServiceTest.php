<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/RoadTransportService.php';

final class MySqlRoadTransportServiceTest extends MySqlIntegrationTestCase
{
 // 
 // ensureConfigsForPlayerWells
 // 

    public function testEnsureCreatesConfigOnlyForCiezarowkiWells(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'],    'active', 77, 'A1', 'ciezarowki', 70.0, 50.0);
        $this->seedWell($playerId, $ids['auxWellId'], 'active', 77, 'A1', 'rurociag',   120.0, 40.0);

        $svc = new RoadTransportService($this->db);
        $wells = [
            ['id' => $ids['wellId'],    'transport_type' => 'ciezarowki'],
            ['id' => $ids['auxWellId'], 'transport_type' => 'rurociag'],
        ];
        $svc->ensureConfigsForPlayerWells($playerId, $wells);
 // Idempotentne drugi call nie powinien duplikowa
        $svc->ensureConfigsForPlayerWells($playerId, $wells);

        $stmt = $this->db->prepare('SELECT well_id, truck_type FROM well_road_configs WHERE player_id = ? ORDER BY id');
        $stmt->execute([$playerId]);
        $rows = $stmt->fetchAll();

        $this->assertCount(1, $rows, 'Tylko odwiert ciezarowki dostaje config');
        $this->assertSame((string)$ids['wellId'], (string)$rows[0]['well_id']);
        $this->assertSame('standard', $rows[0]['truck_type']);
    }

 // 
 // getConfigsByWellIds
 // 

    public function testGetConfigsByWellIdsReturnsKeyedByWellId(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 70.0, 50.0);
        $this->seedRoadConfig($playerId, $ids['wellId'], 'heavy', 50.0, 900.0, 0.8);

        $svc     = new RoadTransportService($this->db);
        $configs = $svc->getConfigsByWellIds($playerId, [$ids['wellId'], 999999]);

        $this->assertArrayHasKey($ids['wellId'], $configs);
        $this->assertSame('heavy', $configs[$ids['wellId']]['truck_type']);
        $this->assertSame('50.00', $configs[$ids['wellId']]['trip_capacity_bbl']);
        $this->assertSame('900.00', $configs[$ids['wellId']]['cost_per_trip']);
        $this->assertSame('0.800', $configs[$ids['wellId']]['incident_risk_mult']);
        $this->assertArrayNotHasKey(999999, $configs);
    }

 // 
 // processTick cieka zero incydentw (risk_mult = 0)
 // 

    public function testProcessTickWithZeroRiskDeliversAllVolume(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 70.0, 50.0);

 // risk_mult = 0.0 szansa incydentu = 0 wszystkie kursy dostarczone
        $config = [
            'trip_capacity_bbl'  => 25.0,
            'cost_per_trip'      => 500.0,
            'incident_risk_mult' => 0.0,
        ];

        $svc    = new RoadTransportService($this->db);
        $result = $svc->processTick(
            $playerId, $ids['wellId'], 100.0, 1.0,
            $config, ['failure_reduction' => 1.0], 1
        );

        $this->assertSame(4, $result['trips_total'],                '100 bbl / 25 bbl = 4 kursy');
        $this->assertSame(4, $result['trips_delivered']);
        $this->assertSame(0, $result['trips_lost']);
        $this->assertEqualsWithDelta(100.0, $result['delivered_bbl'], 0.001);
        $this->assertSame(0.0, $result['lost_bbl']);
        $this->assertSame(2000.0, $result['cost'],                    '4  500 = 2000');
        $this->assertSame([], $result['incidents']);

 // Brak incydentw brak wpisw w logu
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM well_road_incident_logs WHERE well_id = ?');
        $stmt->execute([$ids['wellId']]);
        $this->assertSame('0', (string)$stmt->fetchColumn());
    }

 // 
 // processTick wymuszone incydenty (bardzo duy risk_mult)
 // 

    public function testProcessTickWithMaxRiskCreatesIncidentLogs(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 70.0, 50.0);

 // Bardzo maa pojemno kursu = duo kursw przy 95% szansie prawie na pewno incydent
        $config = [
            'trip_capacity_bbl'  => 0.5,   // 100 bbl / 0.5 = 200 kursw
            'cost_per_trip'      => 10.0,
            'incident_risk_mult' => 99999.0, // wymusza szans = min(0.95, ...) = 0.95
        ];

        $svc    = new RoadTransportService($this->db);
        $result = $svc->processTick(
            $playerId, $ids['wellId'], 100.0, 1.0,
            $config, ['failure_reduction' => 1.0], 1
        );

 // Przy 200 kursach i 95% szansie incydentu P(0 incydentw) (0.05)^200 0
        $this->assertGreaterThan(0, $result['trips_lost'],  'Powinien by co najmniej 1 utracony kurs');
        $this->assertGreaterThan(0.0, $result['lost_bbl'],  'Powinna by strata bbl');
        $this->assertNotEmpty($result['incidents']);

 // Weryfikacja struktury incydentw
        foreach ($result['incidents'] as $inc) {
            $this->assertArrayHasKey('type', $inc);
            $this->assertArrayHasKey('trip_idx', $inc);
            $this->assertArrayHasKey('lost_bbl', $inc);
            $this->assertContains($inc['type'], ['theft', 'raid', 'accident', 'sabotage', 'route_block']);
        }

 // Wpisy w logu incydentw w bazie
        $stmt = $this->db->prepare(
            'SELECT SUM(trips_lost) AS total_lost, SUM(vol_lost_bbl) AS total_vol
               FROM well_road_incident_logs WHERE well_id = ? AND player_id = ?'
        );
        $stmt->execute([$ids['wellId'], $playerId]);
        $logRow = $stmt->fetch();

        $this->assertGreaterThan(0, (int)$logRow['total_lost']);
        $this->assertGreaterThan(0.0, (float)$logRow['total_vol']);
    }

 // 
 // processTick bilans: delivered + lost = input
 // 

    public function testProcessTickVolumeBalance(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 70.0, 50.0);

        $config = [
            'trip_capacity_bbl'  => 10.0,
            'cost_per_trip'      => 200.0,
            'incident_risk_mult' => 0.5,
        ];

        $svc    = new RoadTransportService($this->db);
        $result = $svc->processTick(
            $playerId, $ids['wellId'], 50.0, 1.0,
            $config, ['failure_reduction' => 1.0], 2
        );

 // Niezmiennik: delivered + lost = input (z tolerancj zaokrgle)
        $this->assertEqualsWithDelta(
            50.0,
            $result['delivered_bbl'] + $result['lost_bbl'],
            0.01,
            'delivered + lost powinno sumowa si do inputBbl'
        );

 // Koszt = trips_total * cost_per_trip
        $expectedCost = $result['trips_total'] * 200.0;
        $this->assertEqualsWithDelta($expectedCost, $result['cost'], 0.01);

 // trips_delivered + trips_lost = trips_total
        $this->assertSame(
            $result['trips_total'],
            $result['trips_delivered'] + $result['trips_lost']
        );
    }

 //
 // processTick pusty input
 //

    public function testProcessTickWithZeroInputReturnsEmpty(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();

        $svc    = new RoadTransportService($this->db);
        $result = $svc->processTick($playerId, $ids['wellId'], 0.0, 1.0, null, [], 1);

        $this->assertSame(0, $result['trips_total']);
        $this->assertSame(0.0, $result['delivered_bbl']);
        $this->assertSame(0.0, $result['cost']);
        $this->assertSame([], $result['incidents']);
    }

 //
 // dispatchTrips (P1.2 model czasowy / time-based model)
 //

    public function testDispatchTripsCreatesInTransitRecord(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

        $svc    = new RoadTransportService($this->db);
        $result = $svc->dispatchTrips($playerId, $ids['wellId'], 75.0, null, 1);

        $this->assertSame(3, $result['trips_count']); // ceil(75 / 25) = 3
        $this->assertSame(75.0, $result['volume_bbl']);
        $this->assertSame(1500.0, $result['cost']);   // 3 * 500
        $this->assertSame('standard', $result['truck_type']);
        $this->assertNotEmpty($result['eta_at']);

        $stmt = $this->db->prepare(
            'SELECT volume_bbl, truck_type, trips_count, trip_hours, cost, status
               FROM well_road_trips WHERE player_id = ? AND well_id = ?'
        );
        $stmt->execute([$playerId, $ids['wellId']]);
        $row = $stmt->fetch();
        $this->assertNotFalse($row, 'Rekord kursu musi istnic w DB');
        $this->assertSame('75.0000', $row['volume_bbl']);
        $this->assertSame('standard', $row['truck_type']);
        $this->assertSame('3', (string)$row['trips_count']);
        $this->assertSame('2', (string)$row['trip_hours']);  // standard = 2h
        $this->assertSame('1500.00', $row['cost']);
        $this->assertSame('in_transit', $row['status']);
    }

    public function testDispatchTripsUsesHeavyConfigAndTripHours(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);
        $this->seedRoadConfig($playerId, $ids['wellId'], 'heavy', 50.0, 900.0, 0.8);

        $svc    = new RoadTransportService($this->db);
        $config = $svc->getConfigsByWellIds($playerId, [$ids['wellId']])[$ids['wellId']];
        $result = $svc->dispatchTrips($playerId, $ids['wellId'], 100.0, $config, 1);

        $this->assertSame(2, $result['trips_count']); // ceil(100 / 50) = 2
        $this->assertSame(1800.0, $result['cost']);   // 2 * 900

        $stmt = $this->db->prepare('SELECT trip_hours FROM well_road_trips WHERE player_id = ? AND well_id = ?');
        $stmt->execute([$playerId, $ids['wellId']]);
        $this->assertSame('3', (string)$stmt->fetchColumn()); // heavy = 3h
    }

    public function testDispatchTripsWithZeroVolumeCreatesNoRecord(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();

        $svc    = new RoadTransportService($this->db);
        $svc->dispatchTrips($playerId, $ids['wellId'], 0.0, null, 1);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM well_road_trips WHERE player_id = ?');
        $stmt->execute([$playerId]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

 //
 // processCompletedTrips (P1.2)
 //

    public function testProcessCompletedTripsDeliversOilWhenEtaPassed(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

 // Kurs z eta w przeszlosci, incident_risk_mult = 0 => zero strat
        $this->db->prepare(
            "INSERT INTO well_road_trips
                (player_id, well_id, volume_bbl, truck_type, trips_count, trip_hours, cost,
                 incident_risk_mult, political_risk_level, status, eta_at)
             VALUES (?, ?, 50.0, 'standard', 2, 2, 1000.00, 0.0, 1, 'in_transit', NOW() - INTERVAL 1 SECOND)"
        )->execute([$playerId, $ids['wellId']]);

        $svc    = new RoadTransportService($this->db);
        $result = $svc->processCompletedTrips($playerId, []);

        $this->assertSame(1, $result['completed_count']);
        $this->assertEqualsWithDelta(50.0, $result['delivered_bbl'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['lost_bbl'], 0.001);

        $stmt = $this->db->prepare('SELECT status, delivered_bbl FROM well_road_trips WHERE player_id = ?');
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        $this->assertSame('delivered', $row['status']);
        $this->assertSame('50.0000', $row['delivered_bbl']);
    }

    public function testProcessCompletedTripsIgnoresFutureEta(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

        $this->db->prepare(
            "INSERT INTO well_road_trips
                (player_id, well_id, volume_bbl, truck_type, trips_count, trip_hours, cost,
                 incident_risk_mult, political_risk_level, status, eta_at)
             VALUES (?, ?, 50.0, 'standard', 2, 2, 1000.00, 1.0, 1, 'in_transit', NOW() + INTERVAL 2 HOUR)"
        )->execute([$playerId, $ids['wellId']]);

        $svc    = new RoadTransportService($this->db);
        $result = $svc->processCompletedTrips($playerId, []);

        $this->assertSame(0, $result['completed_count']);

        $stmt = $this->db->prepare('SELECT status FROM well_road_trips WHERE player_id = ?');
        $stmt->execute([$playerId]);
        $this->assertSame('in_transit', $stmt->fetchColumn(), 'Kurs z przyszla eta nie moze byc przetworzony');
    }

    public function testProcessCompletedTripsAggregatesMultipleWells(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'],    'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);
        $this->seedWell($playerId, $ids['auxWellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 30.0);

 // Dwa minione kursy, zero ryzyka incydentow
        $insert = $this->db->prepare(
            "INSERT INTO well_road_trips
                (player_id, well_id, volume_bbl, truck_type, trips_count, trip_hours, cost,
                 incident_risk_mult, political_risk_level, status, eta_at)
             VALUES (?, ?, ?, 'standard', ?, 2, 500.00, 0.0, 1, 'in_transit', NOW() - INTERVAL 1 MINUTE)"
        );
        $insert->execute([$playerId, $ids['wellId'],    50.0, 2]);
        $insert->execute([$playerId, $ids['auxWellId'], 25.0, 1]);

        $svc    = new RoadTransportService($this->db);
        $result = $svc->processCompletedTrips($playerId, []);

        $this->assertSame(2, $result['completed_count']);
        $this->assertEqualsWithDelta(75.0, $result['delivered_bbl'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['lost_bbl'], 0.001);
    }

 //
 // getActiveTripsForPlayer (P1.2)
 //

    public function testGetActiveTripsForPlayerReturnsInTransitWithSecondsRemaining(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

        $svc = new RoadTransportService($this->db);
        $svc->dispatchTrips($playerId, $ids['wellId'], 50.0, null, 1);

        $trips = $svc->getActiveTripsForPlayer($playerId);

        $this->assertCount(1, $trips);
        $this->assertSame((string)$ids['wellId'], (string)$trips[0]['well_id']);
        $this->assertSame('in_transit', $trips[0]['status']);
        $this->assertGreaterThan(0, (int)$trips[0]['seconds_remaining']);
        $this->assertLessThanOrEqual(7200, (int)$trips[0]['seconds_remaining']); // <= 2h
        $this->assertNotEmpty($trips[0]['well_name']);
    }

    public function testGetActiveTripsForPlayerIgnoresDeliveredTrips(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

        $this->db->prepare(
            "INSERT INTO well_road_trips
                (player_id, well_id, volume_bbl, delivered_bbl, truck_type, trips_count, trip_hours,
                 cost, incident_risk_mult, political_risk_level, status, eta_at, arrived_at)
             VALUES (?, ?, 50.0, 50.0, 'standard', 2, 2, 1000.00, 1.0, 1, 'delivered',
                     NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 MINUTE)"
        )->execute([$playerId, $ids['wellId']]);

        $svc   = new RoadTransportService($this->db);
        $trips = $svc->getActiveTripsForPlayer($playerId);

        $this->assertCount(0, $trips, 'Dostarczone kursy nie powinny pojawiac sie na liscie aktywnych');
    }

    public function testEnsureSchemaCreatesWellRoadTripsTable(): void
    {
        $svc = new RoadTransportService($this->db);
        $this->assertInstanceOf(RoadTransportService::class, $svc);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'well_road_trips'"
        );
        $stmt->execute();
        $this->assertSame(1, (int)$stmt->fetchColumn(), 'Tabela well_road_trips musi istniec');
    }
}
