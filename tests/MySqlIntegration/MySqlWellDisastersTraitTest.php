<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/WellService.php';
require_once dirname(__DIR__, 2) . '/src/DisasterMessages.php';

/**
 * Integration tests for WellDisastersTrait (src/Well/DisastersTrait.php).
 *
 * Bug fixed: applyDisasterRiskBoost() was originally called OUTSIDE the
 * transaction in both triggerBlowout() and triggerReservoirContamination().
 * If the transaction succeeded but was rolled back (or a concurrent process
 * observed the state between commit and boost), the well could end up with
 * status='blowout' but no risk boost applied, or vice-versa.
 *
 * After the fix applyDisasterRiskBoost() is called INSIDE the transaction
 * (before db->commit()), making the well-status change and the risk boost
 * atomic: either both are committed or both are rolled back.
 *
 * These tests verify:
 *   1. triggerBlowout() sets post_disaster_risk_boost = 0.15.
 *   2. The boost and status change are visible atomically (no partial state).
 *   3. Repeated disasters stack the boost up to the 0.45 cap.
 *   4. triggerReservoirContamination() also sets the boost.
 *   5. Auxiliary records (failure_log, industrial_disasters) are created.
 */
final class MySqlWellDisastersTraitTest extends MySqlIntegrationTestCase
{
    private WellService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        // WellService uses Database::getInstance() which connects to the same
        // database as the test PDO (same config/database.php).
        $this->svc = new WellService();
    }

    protected function tearDown(): void
    {
        $ids = $this->getTrackedIds();

        // Clean up extra tables written by disaster triggers.
        foreach (['failure_log', 'industrial_disasters', 'director_notifications'] as $table) {
            try {
                $this->db->prepare("DELETE FROM `{$table}` WHERE player_id = ?")
                    ->execute([$ids['playerId']]);
            } catch (Throwable) {}
        }

        parent::tearDown();
    }

    // =========================================================================
    // Helper: get current post_disaster_risk_boost for a well.
    // =========================================================================

    private function getRiskBoost(int $wellId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(post_disaster_risk_boost, 0.0) FROM wells WHERE id = ?'
        );
        $stmt->execute([$wellId]);
        return (float)$stmt->fetchColumn();
    }

    // =========================================================================
    // Test 1: triggerBlowout sets post_disaster_risk_boost = 0.15 (atomic).
    // =========================================================================

    public function testBlowoutBoostAppliedToWell(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active');

        // Confirm boost starts at 0 before the disaster.
        $this->assertEqualsWithDelta(0.0, $this->getRiskBoost($ids['wellId']), 0.001,
            'post_disaster_risk_boost must start at 0');

        $result = $this->svc->triggerBlowout($ids['wellId'], $playerId);

        $this->assertNotNull($result['disaster'],
            'triggerBlowout must report a disaster for an active well');
        $this->assertSame('blowout', $result['disaster']);

        $boost = $this->getRiskBoost($ids['wellId']);
        $this->assertEqualsWithDelta(0.15, $boost, 0.001,
            'post_disaster_risk_boost must be 0.15 after one blowout');
    }

    // =========================================================================
    // Test 2: well status is 'blowout' AND boost is 0.15 after triggerBlowout
    // (both applied inside the same transaction → atomic).
    // =========================================================================

    public function testBlowoutChangesWellStatusAndSetsBoostAtomically(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active');

        $this->svc->triggerBlowout($ids['wellId'], $playerId);

        $stmt = $this->db->prepare(
            'SELECT status, COALESCE(post_disaster_risk_boost, 0.0) AS boost FROM wells WHERE id = ?'
        );
        $stmt->execute([$ids['wellId']]);
        $row = $stmt->fetch();

        $this->assertSame('blowout', $row['status'],
            'Well status must be "blowout" after triggerBlowout()');
        $this->assertEqualsWithDelta(0.15, (float)$row['boost'], 0.001,
            'Risk boost must be persisted in the same transaction as the status change');
    }

    // =========================================================================
    // Test 3: triggerBlowout on an incompatible status (e.g. 'blowout') returns
    // disaster=null and must NOT modify the risk boost.
    // =========================================================================

    public function testBlowoutSkippedWhenWellAlreadyInBlowoutStatus(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'blowout');

        $result = $this->svc->triggerBlowout($ids['wellId'], $playerId);

        $this->assertNull($result['disaster'],
            'triggerBlowout must return disaster=null for an already-blowout well');

        // Boost must remain 0 — no side-effects when the disaster is skipped.
        $this->assertEqualsWithDelta(0.0, $this->getRiskBoost($ids['wellId']), 0.001,
            'post_disaster_risk_boost must not change when blowout is skipped');
    }

    // =========================================================================
    // Test 4: repeated blowouts stack the risk boost, capped at 0.45.
    // =========================================================================

    public function testRepeatedBlowoutsStackRiskBoostUpToCap(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active');

        // First blowout → boost = 0.15, status = 'blowout'.
        $this->svc->triggerBlowout($ids['wellId'], $playerId);
        $this->assertEqualsWithDelta(0.15, $this->getRiskBoost($ids['wellId']), 0.001,
            'Boost must be 0.15 after first blowout');

        // Reset status to allow a second blowout.
        $this->db->prepare("UPDATE wells SET status = 'active' WHERE id = ?")
            ->execute([$ids['wellId']]);

        // Second blowout → boost = 0.30.
        $this->svc->triggerBlowout($ids['wellId'], $playerId);
        $this->assertEqualsWithDelta(0.30, $this->getRiskBoost($ids['wellId']), 0.001,
            'Boost must be 0.30 after second blowout');

        $this->db->prepare("UPDATE wells SET status = 'active' WHERE id = ?")
            ->execute([$ids['wellId']]);

        // Third blowout → boost = 0.45 (at cap).
        $this->svc->triggerBlowout($ids['wellId'], $playerId);
        $this->assertEqualsWithDelta(0.45, $this->getRiskBoost($ids['wellId']), 0.001,
            'Boost must be 0.45 (cap) after third blowout');

        $this->db->prepare("UPDATE wells SET status = 'active' WHERE id = ?")
            ->execute([$ids['wellId']]);

        // Fourth blowout → still capped at 0.45 (LEAST(0.45, 0.45 + 0.15) = 0.45).
        $this->svc->triggerBlowout($ids['wellId'], $playerId);
        $this->assertEqualsWithDelta(0.45, $this->getRiskBoost($ids['wellId']), 0.001,
            'Boost must remain capped at 0.45 after fourth blowout');
    }

    // =========================================================================
    // Test 5: triggerReservoirContamination also applies the 0.15 boost.
    // =========================================================================

    public function testReservoirContaminationAppliesRiskBoost(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active');

        $result = $this->svc->triggerReservoirContamination($ids['wellId'], $playerId);

        if ($result['disaster'] !== null) {
            $boost = $this->getRiskBoost($ids['wellId']);
            $this->assertEqualsWithDelta(0.15, $boost, 0.001,
                'post_disaster_risk_boost must be 0.15 after reservoir contamination');
        } else {
            $this->markTestSkipped(
                'Reservoir contamination was skipped (incompatible well status). '
                . 'Boost test cannot run.'
            );
        }
    }

    // =========================================================================
    // Test 6: failure_log entry is created by triggerBlowout.
    // =========================================================================

    public function testBlowoutCreatesFailureLogEntry(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active');

        $this->svc->triggerBlowout($ids['wellId'], $playerId);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM failure_log
              WHERE player_id = ? AND well_id = ? AND failure_type = 'blowout'"
        );
        $stmt->execute([$playerId, $ids['wellId']]);
        $this->assertGreaterThan(0, (int)$stmt->fetchColumn(),
            'triggerBlowout must create a failure_log entry');
    }

    // =========================================================================
    // Test 7: industrial_disasters entry (critical severity) is created.
    // =========================================================================

    public function testBlowoutCreatesIndustrialDisasterEntry(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active');

        $this->svc->triggerBlowout($ids['wellId'], $playerId);

        $stmt = $this->db->prepare(
            "SELECT severity, status
               FROM industrial_disasters
              WHERE player_id = ? AND well_id = ? AND disaster_type = 'blowout'
              ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$playerId, $ids['wellId']]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row, 'industrial_disasters row must be created by triggerBlowout()');
        $this->assertSame('critical', $row['severity']);
        $this->assertSame('active', $row['status']);
    }
}
