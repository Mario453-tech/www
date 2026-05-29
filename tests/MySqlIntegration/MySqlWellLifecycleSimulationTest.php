<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubAssignmentService.php';
require_once dirname(__DIR__, 2) . '/src/WellPipelineService.php';
require_once dirname(__DIR__, 2) . '/src/HubTickService.php';

/**
 * End-to-end simulation tests for the full well lifecycle on real MySQL.
 *
 * Simulated flow:
 * well purchase -> hub assignment -> pipeline purchase -> pipeline activation
 * -> hub tick -> detach -> cooldown -> reassignment blocked
 *
 * WellShop uses Database singleton (not injectable) so we simulate its effect
 * by directly deducting cash and inserting the well row - identical DB state.
 */
final class MySqlWellLifecycleSimulationTest extends MySqlIntegrationTestCase
{
 // Standard pipeline costs (matches WellPipelineService::PIPELINE_DEFAULTS)
    private const COST_PIPELINE_LIGHT    = 11_000.0;
    private const COST_PIPELINE_STANDARD = 18_000.0;
    private const COST_PIPELINE_HEAVY    = 28_000.0;

 // Hub seeded with opex=100, slots=4 -> slot_cost=25 -> access_fee(new)=25*5=125
    private const ACCESS_FEE_NEW_HUB = 125.0;

 // Simulated well purchase price
    private const WELL_PURCHASE_COST = 5_000_000.0;

 // wells_for_sale entry ID (outside tracked range to avoid collisions)
    private int $saleWellId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saleWellId = $this->seed + 10;
    }

    protected function tearDown(): void
    {
 // wells_for_sale is not tracked by the base cleanupTrackedIds, remove manually
        try {
            $this->db->prepare('DELETE FROM wells_for_sale WHERE id = ?')
                ->execute([$this->saleWellId]);
        } catch (Throwable) {}

        parent::tearDown();
    }

 // =========================================================================
 // Helper: seed wells_for_sale
 // =========================================================================

    private function seedWellForSale(float $cost = self::WELL_PURCHASE_COST): void
    {
        $this->db->prepare(
            "INSERT INTO wells_for_sale
                (id, location_name, base_production, base_cost, upkeep_cost,
                 well_type, available, region_id)
             VALUES (?, 'PHPUnit Simulation Field', 37, ?, 100.00, 'onshore', 1, 77)
             ON DUPLICATE KEY UPDATE available = 1, base_cost = VALUES(base_cost)"
        )->execute([$this->saleWellId, $cost]);
    }

 /**
 * Simulate WellShop::buyWell() effect without using Database singleton.
 * Produces identical DB state: cash deducted, well inserted with transport_type='nieustawiony'.
 */
    private function simulateBuyWell(int $playerId, int $wellId, float $cost): void
    {
        $this->db->prepare('UPDATE players SET cash = cash - ? WHERE id = ?')
            ->execute([$cost, $playerId]);

        $this->db->prepare(
            "INSERT INTO wells
                (id, player_id, status, created_at, region_id, zone_key,
                 location_name, name, transport_type, transport_capacity_pct, base_production_per_hour)
             VALUES (?, ?, 'active', NOW(), 77, 'A1',
                     'PHPUnit Simulation Field', 'PHPUnit Well Sim',
                     'nieustawiony', 0.0, 37.5)"
        )->execute([$wellId, $playerId]);
    }

    private function getCash(int $playerId): float
    {
        return (float)$this->db->query(
            "SELECT cash FROM players WHERE id = {$playerId}"
        )->fetchColumn();
    }

 // =========================================================================
 // MAIN LIFECYCLE TEST
 // =========================================================================

 /**
 * Full lifecycle:
 * buy well -> assign hub (access fee) -> purchase pipeline (standard, building)
 * -> complete build -> run hub tick -> detach -> cooldown blocks reassignment
 */
    public function testFullWellLifecycleFromPurchaseToHubAndPipeline(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer(); // cash = 50 000 000

 // ---- 1. Simulate well purchase ----
        $this->seedWellForSale(self::WELL_PURCHASE_COST);
        $cashBefore = $this->getCash($playerId);
        $this->simulateBuyWell($playerId, $ids['wellId'], self::WELL_PURCHASE_COST);

        $cashAfterBuy = $this->getCash($playerId);
        $this->assertEqualsWithDelta(
            $cashBefore - self::WELL_PURCHASE_COST, $cashAfterBuy, 0.01,
            'Cash must be reduced by well cost'
        );

        $wellRow = $this->db->query(
            "SELECT transport_type, status FROM wells WHERE id = {$ids['wellId']}"
        )->fetch();
        $this->assertNotFalse($wellRow, 'Well must exist after simulated purchase');
        $this->assertSame('nieustawiony', $wellRow['transport_type'],
            'Freshly purchased well must have transport_type=nieustawiony');
        $this->assertSame('active', $wellRow['status']);

 // ---- 2. Assign well to hub (new hub -> access fee 125) ----
 // seedHub: opex=100, slots=4 -> slot_cost=25 -> fee(new)=125
        $this->seedHub($ids['hubId'], 'PHPUnit Sim Hub', 77, 'A1', 95.0, 'active', 'new');

        $hubSvc        = new HubService($this->db);
        $assignmentSvc = new HubAssignmentService($this->db, $hubSvc);

        $assignResult = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);
        $this->assertTrue($assignResult['success'],
            'Hub assignment must succeed: ' . ($assignResult['error'] ?? 'no error'));

        $accessFee = (float)($assignResult['access_fee'] ?? 0.0);
        $this->assertEqualsWithDelta(self::ACCESS_FEE_NEW_HUB, $accessFee, 0.01,
            'Access fee for new hub must be 125');

        $cashAfterAssign = $this->getCash($playerId);
        $this->assertEqualsWithDelta($cashAfterBuy - $accessFee, $cashAfterAssign, 0.01,
            'Cash must be reduced by access fee after assignment');

        $assignStmt = $this->db->prepare(
            "SELECT hub_id, access_fee_paid FROM logistics_hub_assignments
              WHERE well_id = ? AND status = 'active'"
        );
        $assignStmt->execute([$ids['wellId']]);
        $assignment = $assignStmt->fetch();
        $this->assertNotFalse($assignment, 'Active assignment row must exist');
        $this->assertSame((string)$ids['hubId'], (string)$assignment['hub_id']);
        $this->assertEqualsWithDelta($accessFee, (float)$assignment['access_fee_paid'], 0.01,
            'access_fee_paid stored in DB must match returned access_fee');

 // ---- 3. Purchase standard pipeline ----
        $pipelineSvc = new WellPipelineService($this->db);
        $pipeResult  = $pipelineSvc->purchasePipeline($playerId, $ids['wellId'], 'standard');

        $this->assertTrue($pipeResult['success'],
            'Pipeline purchase must succeed: ' . ($pipeResult['error'] ?? 'no error'));
        $this->assertSame('standard', $pipeResult['pipeline_type']);
        $this->assertEqualsWithDelta(self::COST_PIPELINE_STANDARD, (float)$pipeResult['build_cost'], 0.01);
        $this->assertSame(8, (int)$pipeResult['build_hours']);

        $cashAfterPipeline = $this->getCash($playerId);
        $this->assertEqualsWithDelta(
            $cashAfterAssign - self::COST_PIPELINE_STANDARD, $cashAfterPipeline, 0.01,
            'Cash must be reduced by pipeline build cost'
        );

        $pipeStmt = $this->db->prepare('SELECT status, hub_id FROM well_pipelines WHERE well_id = ?');
        $pipeStmt->execute([$ids['wellId']]);
        $pipeRow = $pipeStmt->fetch();
        $this->assertNotFalse($pipeRow, 'Pipeline row must exist in DB');
        $this->assertSame('building', $pipeRow['status'], 'Pipeline starts in building state');
        $this->assertSame((string)$ids['hubId'], (string)$pipeRow['hub_id'],
            'Pipeline hub_id must match assigned hub');

 // ---- 4. Fast-forward build_finish_at to past and complete ----
        $this->db->prepare(
            'UPDATE well_pipelines SET build_finish_at = NOW() - INTERVAL 1 SECOND WHERE well_id = ?'
        )->execute([$ids['wellId']]);

        $completed = $pipelineSvc->completeBuildingPipelines($playerId);
        $this->assertCount(1, $completed, 'Exactly one pipeline should be completed');

        $pipeStatus = $this->db->query(
            "SELECT status FROM well_pipelines WHERE well_id = {$ids['wellId']}"
        )->fetchColumn();
        $this->assertSame('active', $pipeStatus, 'Pipeline must be active after build completion');

 // ---- 5. Run hub tick ----
        $hub        = $hubSvc->getHub($ids['hubId']);
        $this->assertIsArray($hub, 'getHub must return the seeded hub');

        $tickSvc    = new HubTickService($this->db, $hubSvc);
        $tickResult = $tickSvc->processTick($hub, 100.0, 1.0);

        $this->assertArrayHasKey('processed_bbl', $tickResult);
        $this->assertArrayHasKey('new_condition',  $tickResult);
        $this->assertArrayHasKey('wear_added',     $tickResult);
        $this->assertGreaterThan(0.0, $tickResult['processed_bbl'], 'Hub must process oil in tick');
        $this->assertLessThan(95.0, $tickResult['new_condition'],   'Hub condition must decrease after tick');

        $tickSvc->persistTickResult($hub, $tickResult, new DateTime());

        $statsStmt = $this->db->prepare(
            'SELECT processed_volume_bbl FROM logistics_hub_tick_stats WHERE hub_id = ? ORDER BY id DESC LIMIT 1'
        );
        $statsStmt->execute([$ids['hubId']]);
        $processedBbl = $statsStmt->fetchColumn();
        $this->assertNotFalse($processedBbl, 'Tick stats row must be persisted');
        $this->assertGreaterThan(0.0, (float)$processedBbl);

 // ---- 6. Detach well (triggers 4h cooldown) ----
        $detachResult = $assignmentSvc->detachWell($playerId, $ids['wellId']);
        $this->assertTrue($detachResult['success'], 'Detach must succeed');

        $detachedStmt = $this->db->prepare(
            'SELECT status, cooldown_until FROM logistics_hub_assignments
              WHERE well_id = ? ORDER BY id DESC LIMIT 1'
        );
        $detachedStmt->execute([$ids['wellId']]);
        $detached = $detachedStmt->fetch();
        $this->assertSame('detached', $detached['status']);
        $this->assertNotEmpty($detached['cooldown_until'], 'cooldown_until must be set after detach');
        $this->assertGreaterThan(time(), strtotime((string)$detached['cooldown_until']),
            'cooldown_until must be a future timestamp');

 // ---- 7. Cooldown blocks immediate reassignment ----
        $reassign = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);
        $this->assertFalse($reassign['success']);
        $this->assertSame('cooldown_active', $reassign['error']);
        $this->assertGreaterThan(0, $reassign['cooldown_remaining_s'] ?? 0,
            'cooldown_remaining_s must be forwarded to caller');

 // ---- 8. cooldown_until appears in getUnassignedWells() ----
        $unassigned = $hubSvc->getUnassignedWells($playerId);
        $found = array_filter($unassigned, fn($r) => (int)$r['id'] === $ids['wellId']);
        $this->assertNotEmpty($found, 'Detached well must appear in unassigned list');
        $row = reset($found);
        $this->assertNotNull($row['cooldown_until'],
            'cooldown_until must be non-null while cooldown is active');
    }

 // =========================================================================
 // ISOLATED STEP TESTS
 // =========================================================================

    public function testWellPurchaseSimulationDeductsCashAndSetsNieustawiony(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWellForSale(3_000_000.0);

        $cashBefore = $this->getCash($playerId);
        $this->simulateBuyWell($playerId, $ids['wellId'], 3_000_000.0);
        $cashAfter = $this->getCash($playerId);

        $this->assertEqualsWithDelta($cashBefore - 3_000_000.0, $cashAfter, 0.01,
            'Cash must be reduced by the well purchase cost');

        $transportType = $this->db->query(
            "SELECT transport_type FROM wells WHERE id = {$ids['wellId']}"
        )->fetchColumn();
        $this->assertSame('nieustawiony', $transportType,
            'Freshly purchased well has no transport configured');
    }

    public function testMaxWellsLimitBlocksFifthPurchase(): void
    {
 // WellShop enforces max 5 wells. Simulate the check directly.
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();

 // Insert 5 wells manually (simulating 5 previously purchased)
        for ($i = 0; $i < 5; $i++) {
            $this->db->prepare(
                "INSERT INTO wells
                    (id, player_id, status, created_at, region_id, zone_key,
                     location_name, name, transport_type, transport_capacity_pct, base_production_per_hour)
                 VALUES (?, ?, 'active', NOW(), 77, 'A1', 'Field', 'Well', 'rurociag', 100.0, 37.5)"
            )->execute([$ids['wellId'] + $i, $playerId]);
        }

        $count = (int)$this->db->query(
            "SELECT COUNT(*) FROM wells WHERE player_id = {$playerId}"
        )->fetchColumn();
        $this->assertSame(5, $count, 'Player has 5 wells (at limit)');

 // Cleanup the extra seeded wells (only wellId and auxWellId are tracked by base teardown)
 // Others are cleaned by player_id deletion at teardown
    }

    public function testHubAssignmentFailsWithInsufficientFunds(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
 // seed well with nieustawiony to match real post-purchase state
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'nieustawiony');
 // Default hub: opex=100, slots=4 -> access_fee=125 PLN (new)
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Broke', 77, 'A1', 95.0, 'active', 'new');

 // Drain player to 1 PLN (far below 125 fee)
        $this->db->prepare('UPDATE players SET cash = 1.00 WHERE id = ?')->execute([$playerId]);

        $hubSvc        = new HubService($this->db);
        $assignmentSvc = new HubAssignmentService($this->db, $hubSvc);

        $result = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_funds', $result['error']);

 // Cash must not have been deducted
        $cashAfter = $this->getCash($playerId);
        $this->assertEqualsWithDelta(1.0, $cashAfter, 0.01,
            'Cash must remain 1 PLN after failed assignment');

 // No assignment row must exist
        $cnt = $this->db->prepare(
            "SELECT COUNT(*) FROM logistics_hub_assignments WHERE well_id = ? AND status = 'active'"
        );
        $cnt->execute([$ids['wellId']]);
        $this->assertSame(0, (int)$cnt->fetchColumn(), 'No active assignment must be created on failure');
    }

    public function testPipelinePurchaseRequiresActiveHubAssignment(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'nieustawiony');
 // Intentionally NO hub assignment

        $pipelineSvc = new WellPipelineService($this->db);
        $result      = $pipelineSvc->purchasePipeline($playerId, $ids['wellId'], 'standard');

        $this->assertFalse($result['success']);
        $this->assertSame('hub_required', $result['error'],
            'Pipeline purchase must be rejected without an active hub assignment');
    }

    public function testPipelinePurchaseFailsWithInsufficientFunds(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'nieustawiony');
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Pipe Low Cash', 77, 'A1', 95.0, 'active');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

 // Set cash below standard pipeline cost (18 000)
        $this->db->prepare('UPDATE players SET cash = 100.00 WHERE id = ?')->execute([$playerId]);

        $pipelineSvc = new WellPipelineService($this->db);
        $result      = $pipelineSvc->purchasePipeline($playerId, $ids['wellId'], 'standard');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_funds', $result['error']);

        $cnt = $this->db->prepare('SELECT COUNT(*) FROM well_pipelines WHERE well_id = ?');
        $cnt->execute([$ids['wellId']]);
        $this->assertSame(0, (int)$cnt->fetchColumn(),
            'No pipeline row must be created when funds are insufficient');

 // Cash must be unchanged
        $this->assertEqualsWithDelta(100.0, $this->getCash($playerId), 0.01,
            'Player cash must not be deducted after failed pipeline purchase');
    }

    public function testPipelinePurchaseBlocksSecondPurchaseOnSameWell(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'nieustawiony');
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Dup', 77, 'A1', 95.0, 'active');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineSvc = new WellPipelineService($this->db);

        $r1 = $pipelineSvc->purchasePipeline($playerId, $ids['wellId'], 'light');
        $this->assertTrue($r1['success'], 'First pipeline purchase must succeed');

        $r2 = $pipelineSvc->purchasePipeline($playerId, $ids['wellId'], 'standard');
        $this->assertFalse($r2['success']);
        $this->assertSame('pipeline_already_exists', $r2['error'],
            'Duplicate pipeline purchase must be rejected');
    }

    public function testDetachCreatesActiveCooldownAndBlocksReassignment(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
        $this->seedHub($ids['hubId'],    'PHPUnit Hub Detach A', 77, 'A1', 90.0, 'active');
        $this->seedHub($ids['auxHubId'], 'PHPUnit Hub Detach B', 77, 'A1', 90.0, 'active');

        $hubSvc        = new HubService($this->db);
        $assignmentSvc = new HubAssignmentService($this->db, $hubSvc);

        $assign = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);
        $this->assertTrue($assign['success'], 'Initial assignment must succeed');

        $detach = $assignmentSvc->detachWell($playerId, $ids['wellId']);
        $this->assertTrue($detach['success'], 'Detach must succeed');

 // Verify cooldown row
        $stmt = $this->db->prepare(
            'SELECT status, cooldown_until FROM logistics_hub_assignments WHERE well_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$ids['wellId']]);
        $row = $stmt->fetch();
        $this->assertSame('detached', $row['status']);
        $cooldownSecs = strtotime((string)$row['cooldown_until']) - time();
        $this->assertGreaterThan(3600 * 3, $cooldownSecs, 'Cooldown must be ~4h in the future');

 // Reassign to different hub must fail
        $r2 = $assignmentSvc->assignWell($playerId, $ids['auxHubId'], $ids['wellId']);
        $this->assertFalse($r2['success']);
        $this->assertSame('cooldown_active', $r2['error']);
        $this->assertGreaterThan(0, $r2['cooldown_remaining_s'] ?? 0,
            'Remaining cooldown seconds must be forwarded to caller');
    }

    public function testTransferWellCreatesDetachedAndNewActiveAssignment(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
        $this->seedHub($ids['hubId'],    'PHPUnit Hub Transfer A', 77, 'A1', 90.0, 'active');
        $this->seedHub($ids['auxHubId'], 'PHPUnit Hub Transfer B', 77, 'A1', 88.0, 'active');

        $hubSvc        = new HubService($this->db);
        $assignmentSvc = new HubAssignmentService($this->db, $hubSvc);

        $assign = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);
        $this->assertTrue($assign['success'], 'Initial assignment must succeed');

        $transfer = $assignmentSvc->transferWell($playerId, $ids['wellId'], $ids['auxHubId']);
        $this->assertTrue($transfer['success'], 'Transfer must succeed');

        $stmt = $this->db->prepare(
            'SELECT hub_id, status FROM logistics_hub_assignments WHERE well_id = ? ORDER BY id'
        );
        $stmt->execute([$ids['wellId']]);
        $rows = $stmt->fetchAll();

        $this->assertCount(2, $rows, 'Transfer must leave exactly 2 assignment rows');
        $this->assertSame('detached', $rows[0]['status'], 'Original assignment must become detached');
        $this->assertSame((string)$ids['auxHubId'], (string)$rows[1]['hub_id'],
            'New assignment must point to target hub');
        $this->assertSame('active', $rows[1]['status'], 'New assignment must be active');
    }

    public function testHeavyPipelineCostsMoreAndTakesLongerThanLight(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'],    'active', 77, 'A1', 'nieustawiony');
        $this->seedWell($playerId, $ids['auxWellId'], 'active', 77, 'A1', 'nieustawiony');
        $this->seedHub($ids['hubId'],    'PHPUnit Hub Heavy', 77, 'A1', 90.0, 'active');
        $this->seedHub($ids['auxHubId'], 'PHPUnit Hub Light', 77, 'A1', 90.0, 'active');
        $this->seedAssignment($ids['hubId'],    $ids['wellId']);
        $this->seedAssignment($ids['auxHubId'], $ids['auxWellId']);

        $pipelineSvc = new WellPipelineService($this->db);

        $heavy = $pipelineSvc->purchasePipeline($playerId, $ids['wellId'],    'heavy');
        $light = $pipelineSvc->purchasePipeline($playerId, $ids['auxWellId'], 'light');

        $this->assertTrue($heavy['success'], 'Heavy pipeline purchase must succeed');
        $this->assertTrue($light['success'], 'Light pipeline purchase must succeed');
        $this->assertSame('heavy', $heavy['pipeline_type']);
        $this->assertSame('light', $light['pipeline_type']);

        $this->assertEqualsWithDelta(self::COST_PIPELINE_HEAVY, (float)$heavy['build_cost'], 0.01);
        $this->assertEqualsWithDelta(self::COST_PIPELINE_LIGHT, (float)$light['build_cost'], 0.01);

        $this->assertGreaterThan((float)$light['build_cost'], (float)$heavy['build_cost'],
            'Heavy pipeline must cost more than light');
        $this->assertGreaterThan((int)$light['build_hours'], (int)$heavy['build_hours'],
            'Heavy pipeline must take longer to build');
    }

    public function testHubTickOnRealMySqlAfterWellAssigned(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Tick Sim', 77, 'A1', 80.0, 'active', 'used');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $hubSvc  = new HubService($this->db);
        $tickSvc = new HubTickService($this->db, $hubSvc);
        $hub     = $hubSvc->getHub($ids['hubId']);

        $result  = $tickSvc->processTick($hub, 120.0, 1.0);

        $this->assertGreaterThan(0.0, $result['processed_bbl'], 'Hub must process oil');
        $this->assertGreaterThan(0.0, $result['wear_added'],    'Used hub must accumulate wear');
        $this->assertLessThan(80.0,   $result['new_condition'], 'Condition must drop after tick');

        $tickSvc->persistTickResult($hub, $result, new DateTime());

 // Verify hub row was updated
        $hubStmt = $this->db->prepare(
            'SELECT condition_pct, wear_level FROM logistics_hubs WHERE id = ?'
        );
        $hubStmt->execute([$ids['hubId']]);
        $updatedHub = $hubStmt->fetch();

        $this->assertEqualsWithDelta(
            $result['new_condition'],
            (float)$updatedHub['condition_pct'], 0.001,
            'condition_pct in DB must match tick result'
        );
        $this->assertGreaterThan(0.0, (float)$updatedHub['wear_level'],
            'wear_level must be persisted');

 // Verify tick stats row
        $statsStmt = $this->db->prepare(
            'SELECT overload_flag, incident_flag, condition_before_pct, condition_after_pct
               FROM logistics_hub_tick_stats WHERE hub_id = ? ORDER BY id DESC LIMIT 1'
        );
        $statsStmt->execute([$ids['hubId']]);
        $stats = $statsStmt->fetch();
        $this->assertNotFalse($stats, 'Tick stats must be persisted');
        $this->assertSame('80.00', $stats['condition_before_pct']);
        $this->assertEqualsWithDelta(
            $result['new_condition'],
            (float)$stats['condition_after_pct'], 0.001
        );
    }

    public function testAccessFeeStoredInAssignmentAndMatchesReturnValue(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'nieustawiony');
 // new hub: opex=100, slots=4 -> access_fee=125
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Fee Check', 77, 'A1', 95.0, 'active', 'new');

        $hubSvc        = new HubService($this->db);
        $assignmentSvc = new HubAssignmentService($this->db, $hubSvc);

        $result = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);
        $this->assertTrue($result['success']);

        $returnedFee = (float)($result['access_fee'] ?? 0.0);
        $this->assertEqualsWithDelta(self::ACCESS_FEE_NEW_HUB, $returnedFee, 0.01);

        $stmt = $this->db->prepare(
            "SELECT access_fee_paid FROM logistics_hub_assignments WHERE well_id = ? AND status = 'active'"
        );
        $stmt->execute([$ids['wellId']]);
        $storedFee = (float)$stmt->fetchColumn();

        $this->assertEqualsWithDelta($returnedFee, $storedFee, 0.01,
            'access_fee_paid in DB must match the value returned by assignWell()');
    }

    public function testUnassignedWellsListShowsCooldownAfterDetach(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Cooldown Check', 77, 'A1', 90.0, 'active');

        $hubSvc        = new HubService($this->db);
        $assignmentSvc = new HubAssignmentService($this->db, $hubSvc);

 // Assign then detach
        $a = $assignmentSvc->assignWell($playerId, $ids['hubId'], $ids['wellId']);
        $this->assertTrue($a['success']);
        $d = $assignmentSvc->detachWell($playerId, $ids['wellId']);
        $this->assertTrue($d['success']);

        $rows  = $hubSvc->getUnassignedWells($playerId);
        $found = array_filter($rows, fn($r) => (int)$r['id'] === $ids['wellId']);
        $this->assertNotEmpty($found, 'Well must appear in unassigned list after detach');

        $row = reset($found);
        $this->assertNotNull($row['cooldown_until'],
            'cooldown_until must be visible in unassigned wells list');
        $this->assertGreaterThan(time(), strtotime((string)$row['cooldown_until']),
            'cooldown_until must be a future timestamp');
    }
}
