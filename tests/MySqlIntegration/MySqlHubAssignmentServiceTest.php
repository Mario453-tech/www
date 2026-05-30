<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubAssignmentService.php';

final class MySqlHubAssignmentServiceTest extends MySqlIntegrationTestCase
{
    public function testAssignDetachAndTransferFlowWorksOnRealMySql(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
        $this->seedHub($ids['hubId'],    'PHPUnit Hub A', 77, 'A1', 92.0, 'active');
        $this->seedHub($ids['auxHubId'], 'PHPUnit Hub B', 77, 'A1', 65.0, 'active');

        $hubSvc        = new HubService($this->db);
        $assignmentSvc = new HubAssignmentService($this->db, $hubSvc);

        $assign = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);
        $this->assertTrue($assign['success'], 'assignWell should succeed');

        $stmt = $this->db->prepare("SELECT hub_id, status FROM logistics_hub_assignments WHERE well_id = ? AND status = 'active'");
        $stmt->execute([$ids['wellId']]);
        $this->assertSame((string)$ids['hubId'], (string)$stmt->fetchColumn());

        $transfer = $assignmentSvc->transferWell($playerId, $ids['wellId'], $ids['auxHubId']);
        $this->assertTrue($transfer['success'], 'transferWell should succeed');

        $stmt = $this->db->prepare('SELECT hub_id, status FROM logistics_hub_assignments WHERE well_id = ? ORDER BY id');
        $stmt->execute([$ids['wellId']]);
        $assignments = $stmt->fetchAll();

        $this->assertCount(2, $assignments);
        $this->assertSame('detached', $assignments[0]['status']);
        $this->assertSame((string)$ids['auxHubId'], (string)$assignments[1]['hub_id']);
        $this->assertSame('active', $assignments[1]['status']);

        $detach = $assignmentSvc->detachWell($playerId, $ids['wellId']);
        $this->assertTrue($detach['success'], 'detachWell should succeed');

        $stmt = $this->db->prepare('SELECT status, cooldown_until FROM logistics_hub_assignments WHERE well_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$ids['wellId']]);
        $row = $stmt->fetch();

        $this->assertSame('detached', $row['status']);
        $this->assertNotEmpty($row['cooldown_until']);
    }

    public function testAssignWellDeductsAccessFeeFromPlayerCash(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer(); // cash = 50 000 000
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
 // opex_per_tick = 100, slot_limit = 4 -> slot_cost = 25 -> fee(new) = 25*5 = 125
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Fee', 77, 'A1', 100.0, 'active', 'new');

        $cashBefore = (float)$this->db->prepare('SELECT cash FROM players WHERE id = ?')
            ->execute([$playerId]) ?: 0;
        $cashBefore = (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();

        $hubSvc        = new HubService($this->db);
        $assignmentSvc = new HubAssignmentService($this->db, $hubSvc);

        $result = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);
        $this->assertTrue($result['success'], 'assignWell should succeed');

        $accessFee = (float)($result['access_fee'] ?? 0);
        $this->assertGreaterThan(0.0, $accessFee, 'access_fee must be > 0 for new hub');

        $cashAfter = (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();
        $this->assertEqualsWithDelta($cashBefore - $accessFee, $cashAfter, 0.01,
            'Player cash should be reduced by exactly the access fee');

        $feeInDb = (float)$this->db->prepare("SELECT access_fee_paid FROM logistics_hub_assignments WHERE well_id = ? AND status='active'")
            ->execute([$ids['wellId']]) ?: 0;
        $stmt = $this->db->prepare("SELECT access_fee_paid FROM logistics_hub_assignments WHERE well_id = ? AND status='active'");
        $stmt->execute([$ids['wellId']]);
        $feeInDb = (float)$stmt->fetchColumn();
        $this->assertEqualsWithDelta($accessFee, $feeInDb, 0.01,
            'access_fee_paid column must match returned access_fee');
    }

    public function testGetUnassignedWellsIncludesNullCooldownWhenNoCooldownActive(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');

        $hubSvc = new HubService($this->db);
        $rows   = $hubSvc->getUnassignedWells($playerId);

        $found = array_filter($rows, fn($r) => (int)$r['id'] === $ids['wellId']);
        $this->assertNotEmpty($found, 'Unassigned well must appear in list');

        $row = reset($found);
        $this->assertNull($row['cooldown_until'], 'cooldown_until must be NULL when no detach happened');
    }

    public function testGetUnassignedWellsReturnsCooldownUntilAfterDetach(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');

 // Insert detached assignment with a future cooldown directly (no UPDATE needed)
        $future = date('Y-m-d H:i:s', time() + 14400); // 4 hours ahead - far enough to survive any TZ offset
        $this->db->prepare(
            "INSERT INTO logistics_hub_assignments
                (hub_id, well_id, status, access_fee_paid, assigned_at, detached_at, cooldown_until, created_at, updated_at)
             VALUES (?, ?, 'detached', 0, NOW(), NOW(), ?, NOW(), NOW())"
        )->execute([$ids['hubId'], $ids['wellId'], $future]);

        $hubSvc = new HubService($this->db);
        $rows   = $hubSvc->getUnassignedWells($playerId);

        $found = array_filter($rows, fn($r) => (int)$r['id'] === $ids['wellId']);
        $this->assertNotEmpty($found, 'Unassigned well must appear in list');

        $row = reset($found);
        $this->assertNotNull($row['cooldown_until'],
            'cooldown_until must be non-NULL when there is an active detach cooldown');
        $this->assertGreaterThan(time(), strtotime((string)$row['cooldown_until']),
            'cooldown_until must be a future timestamp');
    }

    public function testGetUnassignedWellsIncludesNoOperatorStatus(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'],    'no_operator', 77, 'A1');
        $this->seedWell($playerId, $ids['auxWellId'], 'active',      77, 'A1');
        $this->seedHub($ids['hubId'], 'PHPUnit Hub A', 77, 'A1', 95.0, 'active');
        $this->seedAssignment($ids['hubId'], $ids['auxWellId'], 'active');

        $hubSvc  = new HubService($this->db);
        $rows    = $hubSvc->getUnassignedWells($playerId);
        $foundIds = array_map(static fn(array $row): int => (int)$row['id'], $rows);

        $this->assertContains($ids['wellId'],    $foundIds);
        $this->assertNotContains($ids['auxWellId'], $foundIds);
    }
}
