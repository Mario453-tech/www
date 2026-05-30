<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/WellPipelineService.php';

final class MySqlWellPipelineServiceTest extends MySqlIntegrationTestCase
{
    public function testCreatePipelineForWellCreatesSingleOwnedPipelineWithNormalizedType(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 120.0, 55.0);

        $service = new WellPipelineService($this->db);
        $expectedProfile = $service->getProfile('heavy');
        $pipeline = $service->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 55.0,
            'transport_capacity_pct' => 120.0,
            'pipeline_type' => 'reinforced',
        ]);
        $service->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 55.0,
            'transport_capacity_pct' => 120.0,
            'pipeline_type' => 'reinforced',
        ]);

        $this->assertSame((string)$ids['wellId'], (string)($pipeline['well_id'] ?? 0));
        $this->assertSame('heavy', $pipeline['pipeline_type'] ?? null);
        $this->assertSame(number_format(55.0 * ((float)$expectedProfile['capacity_pct'] / 100.0), 2, '.', ''), $pipeline['nominal_capacity_bph'] ?? null);
        $this->assertSame(number_format((float)$expectedProfile['build_cost'], 2, '.', ''), $pipeline['build_cost'] ?? null);

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM well_pipelines WHERE player_id = ? AND well_id = ?');
        $countStmt->execute([$playerId, $ids['wellId']]);
        $this->assertSame(1, (int)$countStmt->fetchColumn());
    }

    public function testEnsurePipelinesForPlayerWellsIsLegacyNoOp(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 130.0, 50.0);
        $this->seedWell($playerId, $ids['auxWellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 40.0);
        $this->seedLegacyPipeline($playerId, 'Legacy PHPUnit', 3.75);

        $service = new WellPipelineService($this->db);
        $wells = [
            [
                'id' => $ids['wellId'],
                'transport_type' => 'rurociag',
                'base_production_per_hour' => 50.0,
                'transport_capacity_pct' => 130.0,
            ],
            [
                'id' => $ids['auxWellId'],
                'transport_type' => 'ciezarowki',
                'base_production_per_hour' => 40.0,
                'transport_capacity_pct' => 100.0,
            ],
        ];

        $service->ensurePipelinesForPlayerWells($playerId, $wells);
        $service->ensurePipelinesForPlayerWells($playerId, $wells);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM well_pipelines WHERE player_id = ?');
        $stmt->execute([$playerId]);

        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function testGetPlayerPipelinesReturnsJoinedWellContext(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 37.5);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub A1');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $service = new WellPipelineService($this->db);
        $service->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 37.5,
            'transport_capacity_pct' => 120.0,
        ]);

        $pipelines = $service->getPlayerPipelines($playerId);

        $this->assertCount(1, $pipelines);
        $this->assertSame((string)$ids['wellId'], (string)$pipelines[0]['source_well_id']);
        $this->assertSame('PHPUnit Well ' . $ids['wellId'], $pipelines[0]['well_name']);
        $this->assertSame('PHPUnit Pole ' . $ids['wellId'], $pipelines[0]['location_name']);
        $this->assertSame('rurociag', $pipelines[0]['transport_type']);
        $this->assertSame($pipelines[0]['real_capacity_bph'], $pipelines[0]['capacity_bbl_h']);
        $this->assertSame('standard', $pipelines[0]['pipeline_type']);
        $this->assertSame($pipelines[0]['transport_loss'], $pipelines[0]['transport_loss_pct']);
    }

    public function testEnsureSchemaCreatesPipelineSupportTables(): void
    {
        $service = new WellPipelineService($this->db);
        $this->assertInstanceOf(WellPipelineService::class, $service);

        foreach (['well_pipelines', 'well_pipeline_events', 'well_pipeline_tick_stats'] as $table) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?'
            );
            $stmt->execute([$table]);
            $this->assertSame(1, (int)$stmt->fetchColumn(), $table . ' should exist');
        }
    }

 // --- purchasePipeline ---

    public function testPurchasePipelineCreatesBuildingPipelineAndDeductsCash(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
 // Onshore well with well_type='onshore' (default)
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Purchase');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $service = new WellPipelineService($this->db);
        $result  = $service->purchasePipeline($playerId, $ids['wellId'], 'standard');

        $this->assertTrue($result['success'], 'Purchase should succeed');
        $this->assertSame('standard', $result['pipeline_type']);
        $this->assertSame(18000.00, $result['build_cost']);
        $this->assertSame(8, $result['build_hours']);
        $this->assertArrayHasKey('build_finish_at', $result);

 // Pipeline exists with status=building
        $pipeStmt = $this->db->prepare(
            'SELECT status, build_started_at, build_finish_at FROM well_pipelines WHERE well_id = ?'
        );
        $pipeStmt->execute([$ids['wellId']]);
        $row = $pipeStmt->fetch();
        $this->assertNotFalse($row, 'Pipeline row must exist');
        $this->assertSame('building', $row['status']);
        $this->assertNotNull($row['build_started_at']);
        $this->assertNotNull($row['build_finish_at']);

 // Cash was deducted
        $cashStmt = $this->db->prepare('SELECT cash FROM players WHERE id = ?');
        $cashStmt->execute([$playerId]);
        $cash = (float)$cashStmt->fetchColumn();
        $this->assertEqualsWithDelta(50000000.00 - 18000.00, $cash, 0.01, 'Cash should be reduced by build cost');
    }

    public function testPurchasePipelineFailsWhenInsufficientFunds(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Low Cash');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

 // Set cash too low
        $this->db->prepare('UPDATE players SET cash = 1.00 WHERE id = ?')->execute([$playerId]);

        $service = new WellPipelineService($this->db);
        $result  = $service->purchasePipeline($playerId, $ids['wellId'], 'heavy');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_funds', $result['error']);

 // No pipeline created
        $cnt = $this->db->prepare('SELECT COUNT(*) FROM well_pipelines WHERE well_id = ?');
        $cnt->execute([$ids['wellId']]);
        $this->assertSame(0, (int)$cnt->fetchColumn());
    }

    public function testPurchasePipelineFailsWhenPipelineAlreadyExists(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 100.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Existing');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $service = new WellPipelineService($this->db);
 // First purchase succeeds
        $r1 = $service->purchasePipeline($playerId, $ids['wellId'], 'light');
        $this->assertTrue($r1['success']);

 // Second purchase must fail
        $r2 = $service->purchasePipeline($playerId, $ids['wellId'], 'standard');
        $this->assertFalse($r2['success']);
        $this->assertSame('pipeline_already_exists', $r2['error']);
    }

    public function testPurchasePipelineSupportsBothLegsPerWell(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 100.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Both Legs');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $service = new WellPipelineService($this->db);

 // Inbound (default) and outbound can coexist for the same well.
        $inbound  = $service->purchasePipeline($playerId, $ids['wellId'], 'standard', 'inbound');
        $outbound = $service->purchasePipeline($playerId, $ids['wellId'], 'standard', 'outbound');
        $this->assertTrue($inbound['success'], 'Inbound purchase should succeed');
        $this->assertTrue($outbound['success'], 'Outbound purchase should succeed');
        $this->assertSame('inbound', $inbound['leg']);
        $this->assertSame('outbound', $outbound['leg']);

 // Two distinct rows, one per leg.
        $legStmt = $this->db->prepare('SELECT leg FROM well_pipelines WHERE well_id = ? ORDER BY leg');
        $legStmt->execute([$ids['wellId']]);
        $legs = $legStmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['inbound', 'outbound'], $legs);

 // A second outbound purchase is rejected (one pipeline per leg).
        $dup = $service->purchasePipeline($playerId, $ids['wellId'], 'light', 'outbound');
        $this->assertFalse($dup['success']);
        $this->assertSame('pipeline_already_exists', $dup['error']);
    }

 // --- completeBuildingPipelines ---

    public function testCompleteBuildingPipelinesFlipsStatusToActiveWhenTimeElapsed(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 100.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Building');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $service = new WellPipelineService($this->db);

 // Insert a pipeline in building status with build_finish_at in the past
        $this->db->prepare(
            "INSERT INTO well_pipelines
                (player_id, well_id, hub_id, name, pipeline_type, status, condition_pct, transport_loss,
                 nominal_capacity_bph, real_capacity_bph, degradation_rate_per_hour, incident_risk_mult,
                 opex_per_tick, opex_per_bbl, build_cost, build_started_at, build_finish_at, created_at, updated_at)
             VALUES (?, ?, ?, 'Test', 'standard', 'building', 100.0, 0.0, 50.0, 50.0, 0.05, 1.0,
                     140.00, 0.25, 18000.00, NOW() - INTERVAL 10 HOUR, NOW() - INTERVAL 1 SECOND, NOW(), NOW())"
        )->execute([$playerId, $ids['wellId'], $ids['hubId']]);

        $completed = $service->completeBuildingPipelines($playerId);

        $this->assertCount(1, $completed, 'One pipeline should be completed');

 // Verify status in DB
        $stmt = $this->db->prepare('SELECT status FROM well_pipelines WHERE well_id = ?');
        $stmt->execute([$ids['wellId']]);
        $this->assertSame('active', $stmt->fetchColumn());
    }

    public function testCompleteBuildingPipelinesDoesNotFlipWhenTimeNotElapsed(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 100.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Building');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $service = new WellPipelineService($this->db);

 // Insert pipeline with build_finish_at 1 hour in the future
        $this->db->prepare(
            "INSERT INTO well_pipelines
                (player_id, well_id, hub_id, name, pipeline_type, status, condition_pct, transport_loss,
                 nominal_capacity_bph, real_capacity_bph, degradation_rate_per_hour, incident_risk_mult,
                 opex_per_tick, opex_per_bbl, build_cost, build_started_at, build_finish_at, created_at, updated_at)
             VALUES (?, ?, ?, 'Test', 'standard', 'building', 100.0, 0.0, 50.0, 50.0, 0.05, 1.0,
                     140.00, 0.25, 18000.00, NOW(), NOW() + INTERVAL 1 HOUR, NOW(), NOW())"
        )->execute([$playerId, $ids['wellId'], $ids['hubId']]);

        $completed = $service->completeBuildingPipelines($playerId);

        $this->assertCount(0, $completed, 'No pipeline should be completed yet');

 // Still building
        $stmt = $this->db->prepare('SELECT status FROM well_pipelines WHERE well_id = ?');
        $stmt->execute([$ids['wellId']]);
        $this->assertSame('building', $stmt->fetchColumn());
    }

 // --- getBuildingForPlayer ---

    public function testGetBuildingForPlayerReturnsBuildingPipelinesWithSecondsRemaining(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 100.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Building');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $service = new WellPipelineService($this->db);

 // Insert pipeline building - finish in 2 hours
        $this->db->prepare(
            "INSERT INTO well_pipelines
                (player_id, well_id, hub_id, name, pipeline_type, status, condition_pct, transport_loss,
                 nominal_capacity_bph, real_capacity_bph, degradation_rate_per_hour, incident_risk_mult,
                 opex_per_tick, opex_per_bbl, build_cost, build_started_at, build_finish_at, created_at, updated_at)
             VALUES (?, ?, ?, 'Test', 'light', 'building', 100.0, 0.0, 50.0, 50.0, 0.07, 1.25,
                     95.00, 0.18, 11000.00, NOW(), NOW() + INTERVAL 2 HOUR, NOW(), NOW())"
        )->execute([$playerId, $ids['wellId'], $ids['hubId']]);

        $building = $service->getBuildingForPlayer($playerId);

        $this->assertCount(1, $building);
        $this->assertSame('building', $building[0]['status']);
        $this->assertGreaterThan(0, (int)$building[0]['seconds_remaining']);
        $this->assertLessThanOrEqual(7200, (int)$building[0]['seconds_remaining']);
        $this->assertNotEmpty($building[0]['well_name']);
    }

    public function testGetBuildingForPlayerIgnoresActivePipelines(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 100.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Active');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $service = new WellPipelineService($this->db);
        $service->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 100.0,
        ]);

        $building = $service->getBuildingForPlayer($playerId);
        $this->assertCount(0, $building, 'Active pipelines must not appear in building list');
    }
}
