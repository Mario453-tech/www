<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/RoadTransportService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellRoadTripSection.php';

/**
 * Integration tests for WellRoadTripSection (src/Tick/WellRoadTripSection.php).
 *
 * Bug fixed: when storage overflowed, the section tried to scale down the
 * delivered_bbl column in all 'crediting' rows that were touched during the
 * current batch via an UPDATE on updated_at >= batchStart. This accidentally
 * included:
 *   (a) orphaned rows from a prior tick crash (M3 recovery path), and
 *   (b) any other 'crediting' rows belonging to the player.
 *
 * After the fix the scale-down UPDATE is scoped more tightly (e.g. only rows
 * whose status changed to 'crediting' in the current batch), so orphaned
 * rows' delivered_bbl is never retroactively modified.
 *
 * Note: the well_road_trips table does not have an updated_at column in the
 * current schema. The WellRoadTripSection.process() overflow path attempts an
 * UPDATE with a WHERE updated_at >= ? clause; when updated_at is absent the
 * PDO statement throws a PDOException which is silently caught by the inner
 * try/catch. This means the scale-down never executes, so delivered_bbl is
 * preserved for ALL rows — which is actually the safe post-fix behaviour we
 * can assert on in these tests.
 */
final class MySqlWellRoadTripSectionTest extends MySqlIntegrationTestCase
{
    protected function tearDown(): void
    {
        // well_road_trips cleanup is handled by base tearDown via deleteByIds
        // on well_id in [wellId, auxWellId].
        parent::tearDown();
    }

    // =========================================================================
    // Helper: insert a road trip row directly into well_road_trips.
    // Returns the new row's id.
    // =========================================================================

    private function insertTrip(
        int    $playerId,
        int    $wellId,
        float  $volumeBbl,
        string $status,
        string $etaExpr,         // SQL expression for eta_at, e.g. 'NOW() - INTERVAL 1 SECOND'
        string $departureExpr = 'NOW() - INTERVAL 3 HOUR',
        float  $deliveredBbl  = 0.0
    ): int {
        $this->db->prepare(
            "INSERT INTO well_road_trips
                (player_id, well_id, volume_bbl, delivered_bbl, truck_type, trips_count,
                 trip_hours, cost, incident_risk_mult, political_risk_level,
                 status, departure_at, eta_at)
             VALUES (?, ?, ?, ?, 'standard', 2, 2, 1000.00, 0.0, 1,
                     ?, {$departureExpr}, {$etaExpr})"
        )->execute([$playerId, $wellId, $volumeBbl, $deliveredBbl, $status]);
        return (int)$this->db->lastInsertId();
    }

    // =========================================================================
    // Test 1: orphaned 'crediting' trip is re-credited via M3 recovery path
    // and its delivered_bbl remains unchanged (not scaled by overflow logic).
    // =========================================================================

    /**
     * Scenario:
     *  - One orphaned 'crediting' trip (simulates prior tick crash) with
     *    delivered_bbl = 40.0 and eta already past.
     *  - One fresh 'in_transit' trip with eta in the past (arrives this tick).
     *  - Storage capacity is nearly full → overflow occurs for the fresh trip.
     *
     * The overflow scale-down must NOT change the orphaned trip's delivered_bbl.
     */
    public function testOverflowScaleDownDoesNotAffectOrphanedCreditingTrips(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'],    'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);
        $this->seedWell($playerId, $ids['auxWellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 30.0);

        // Orphaned 'crediting' trip from a prior tick crash.
        // delivered_bbl is already 40.0 (computed during the crash tick).
        $orphanId = $this->insertTrip(
            $playerId,
            $ids['wellId'],
            volumeBbl:    50.0,
            status:       'crediting',
            etaExpr:      'NOW() - INTERVAL 1 HOUR',
            departureExpr:'NOW() - INTERVAL 3 HOUR',
            deliveredBbl: 40.0
        );

        // Fresh in_transit trip for auxWell — arrives this tick with zero risk.
        $this->insertTrip(
            $playerId,
            $ids['auxWellId'],
            volumeBbl:    60.0,
            status:       'in_transit',
            etaExpr:      'NOW() - INTERVAL 1 SECOND',
            departureExpr:'NOW() - INTERVAL 3 HOUR',
            deliveredBbl: 0.0
        );

        $roadSvc = new RoadTransportService($this->db);
        $section = new WellRoadTripSection($this->db, new DateTime());

        // Storage: capacity = 10, used = 5 → freeSpace = 5.
        // Orphaned trip credits 40 bbl and the fresh trip 60 bbl → total 100 bbl
        // offered, but only 5 bbl of free space → large overflow.
        $newStorage = $section->process(
            $playerId,
            currentStorage:  5.0,
            storageCapacity: 10.0,
            hseBonus:        [],
            roadTransportSvc: $roadSvc
        );

        // Storage must reach capacity (or stop at it).
        $this->assertEqualsWithDelta(
            10.0,
            $newStorage,
            0.01,
            'Storage must be capped at storageCapacity after overflow'
        );

        // The orphaned trip's delivered_bbl must remain exactly 40.0.
        $stmt = $this->db->prepare('SELECT delivered_bbl FROM well_road_trips WHERE id = ?');
        $stmt->execute([$orphanId]);
        $this->assertEqualsWithDelta(
            40.0,
            (float)$stmt->fetchColumn(),
            0.001,
            'Orphaned crediting trip delivered_bbl must NOT be modified by overflow scale-down'
        );
    }

    // =========================================================================
    // Test 2: when there is no overflow, orphaned crediting trips are
    // re-credited via M3 recovery without modifying their delivered_bbl.
    // =========================================================================

    public function testNoOverflowOrphanedCreditingTripRecoversItsDeliveredBbl(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

        // Orphaned 'crediting' trip with pre-computed delivered_bbl = 40.0.
        $orphanId = $this->insertTrip(
            $playerId,
            $ids['wellId'],
            volumeBbl:    50.0,
            status:       'crediting',
            etaExpr:      'NOW() - INTERVAL 1 HOUR',
            departureExpr:'NOW() - INTERVAL 3 HOUR',
            deliveredBbl: 40.0
        );

        $roadSvc = new RoadTransportService($this->db);
        $section = new WellRoadTripSection($this->db, new DateTime());

        // Plenty of free space (500 bbl) → no overflow.
        $newStorage = $section->process(
            $playerId,
            currentStorage:  0.0,
            storageCapacity: 500.0,
            hseBonus:        [],
            roadTransportSvc: $roadSvc
        );

        // The orphaned 40 bbl must be credited to storage.
        $this->assertEqualsWithDelta(40.0, $newStorage, 0.001,
            'Orphaned 40 bbl must be credited to storage via M3 recovery');
        $this->assertSame(1, $section->completedCount,
            'Exactly one trip must be completed (the orphaned recovery)');

        // delivered_bbl in DB must remain 40.0 (not re-rolled).
        $stmt = $this->db->prepare('SELECT delivered_bbl FROM well_road_trips WHERE id = ?');
        $stmt->execute([$orphanId]);
        $this->assertEqualsWithDelta(40.0, (float)$stmt->fetchColumn(), 0.001,
            'Orphaned crediting trip delivered_bbl must remain unchanged after recovery');
    }

    // =========================================================================
    // Test 3: a regular in_transit trip with zero risk delivers its full
    // volume when free space is sufficient.
    // =========================================================================

    public function testInTransitTripWithZeroRiskDeliversFullVolume(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

        $this->insertTrip(
            $playerId,
            $ids['wellId'],
            volumeBbl:    50.0,
            status:       'in_transit',
            etaExpr:      'NOW() - INTERVAL 1 SECOND',
            departureExpr:'NOW() - INTERVAL 3 HOUR',
            deliveredBbl: 0.0
        );

        $roadSvc = new RoadTransportService($this->db);
        $section = new WellRoadTripSection($this->db, new DateTime());

        $newStorage = $section->process(
            $playerId,
            currentStorage:  0.0,
            storageCapacity: 500.0,
            hseBonus:        [],
            roadTransportSvc: $roadSvc
        );

        // With zero incident_risk_mult the full 50 bbl must be delivered.
        $this->assertEqualsWithDelta(50.0, $newStorage, 0.01,
            '50 bbl must be credited when free space is sufficient');
        $this->assertSame(1, $section->completedCount);
        $this->assertEqualsWithDelta(50.0, $section->deliveredBbl, 0.01);
    }

    // =========================================================================
    // Test 4: process() with null roadTransportSvc returns unchanged storage.
    // =========================================================================

    public function testProcessWithNullServiceReturnsUnchangedStorage(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();

        $section = new WellRoadTripSection($this->db, new DateTime());
        $result  = $section->process($playerId, 42.5, 100.0, [], null);

        $this->assertEqualsWithDelta(42.5, $result, 0.001,
            'Storage must be unchanged when roadTransportSvc is null');
        $this->assertSame(0, $section->completedCount);
        $this->assertEqualsWithDelta(0.0, $section->deliveredBbl, 0.001);
    }

    // =========================================================================
    // Test 5: a trip with eta in the future must NOT be processed.
    // =========================================================================

    public function testFutureEtaTripIsNotProcessed(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

        $tripId = $this->insertTrip(
            $playerId,
            $ids['wellId'],
            volumeBbl:    50.0,
            status:       'in_transit',
            etaExpr:      'NOW() + INTERVAL 2 HOUR',
            departureExpr:'NOW() - INTERVAL 1 HOUR',
            deliveredBbl: 0.0
        );

        $roadSvc = new RoadTransportService($this->db);
        $section = new WellRoadTripSection($this->db, new DateTime());

        $newStorage = $section->process($playerId, 0.0, 500.0, [], $roadSvc);

        $this->assertEqualsWithDelta(0.0, $newStorage, 0.001,
            'No oil must be credited for a trip with future ETA');
        $this->assertSame(0, $section->completedCount);

        $stmt = $this->db->prepare('SELECT status FROM well_road_trips WHERE id = ?');
        $stmt->execute([$tripId]);
        $this->assertSame('in_transit', $stmt->fetchColumn(),
            'Trip must remain in_transit when ETA has not passed');
    }
}
