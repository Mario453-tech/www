<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/WellPipelineService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/PipelineSection.php';

final class MySqlPipelineSectionTest extends MySqlIntegrationTestCase
{
    public function testPlayerPipelineListIncludesOperationalOutboundHubPipeline(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'PHPUnit Outbound Hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);

        $pipelineService = new WellPipelineService($this->db);
        $purchase = $pipelineService->purchaseHubOutboundPipeline($playerId, $ids['hubId'], 'standard');
        $this->assertTrue($purchase['success'], $purchase['error'] ?? '');

        $this->db->prepare(
            "UPDATE well_pipelines
                SET status = 'active',
                    build_finish_at = NOW()
              WHERE id = ?"
        )->execute([(int)$purchase['pipeline_id']]);

        $pipelines = $pipelineService->getPlayerPipelines($playerId);
        $outbound = null;
        foreach ($pipelines as $pipeline) {
            if ((int)($pipeline['id'] ?? 0) === (int)$purchase['pipeline_id']) {
                $outbound = $pipeline;
                break;
            }
        }

        $this->assertNotNull($outbound, 'Outbound hub pipeline should be visible in player pipeline list.');
        $this->assertSame('outbound', $outbound['leg']);
        $this->assertSame(0, (int)$outbound['well_id']);
        $this->assertSame($ids['hubId'], (int)$outbound['hub_id']);
        $this->assertSame(77, (int)$outbound['region_id']);
        $this->assertTrue((bool)$outbound['_is_operational']);
    }

    public function testCannotBuyOutboundPipelineForPausedHub(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'PHPUnit Paused Hub', 77, 'A1', 90.0, 'paused', 'new', 'standard', 0.0, $playerId);

        $cashBefore = (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();
        $pipelineService = new WellPipelineService($this->db);
        $purchase = $pipelineService->purchaseHubOutboundPipeline($playerId, $ids['hubId'], 'standard');
        $cashAfter = (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();

        $this->assertFalse($purchase['success']);
        $this->assertSame('hub_not_found', $purchase['error']);
        $this->assertEqualsWithDelta($cashBefore, $cashAfter, 0.001);
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM well_pipelines WHERE hub_id = {$ids['hubId']}")->fetchColumn());
    }

    public function testCannotBuyInboundPipelineForWellAssignedToMaintenanceHub(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Maintenance Hub', 77, 'A1', 90.0, 'maintenance', 'new', 'standard', 0.0, $playerId);
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $cashBefore = (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();
        $pipelineService = new WellPipelineService($this->db);
        $purchase = $pipelineService->purchasePipeline($playerId, $ids['wellId'], 'standard');
        $cashAfter = (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();

        $this->assertFalse($purchase['success']);
        $this->assertSame('hub_required', $purchase['error']);
        $this->assertEqualsWithDelta($cashBefore, $cashAfter, 0.001);
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM well_pipelines WHERE well_id = {$ids['wellId']}")->fetchColumn());
    }

    public function testPipelineTickDegradesRaisesLossAndWritesTickStatsWithoutEngineer(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Tick Hub');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineService = new WellPipelineService($this->db);
        $pipelineService->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 120.0,
        ]);

        $this->db->prepare(
            'UPDATE well_pipelines
                SET condition_pct = 70.50,
                    transport_loss = 2.00,
                    degradation_rate_per_hour = 0.0500,
                    incident_risk_mult = 0.0000,
                    status = \'active\'
              WHERE player_id = ? AND well_id = ?'
        )->execute([$playerId, $ids['wellId']]);

        $section = new PipelineSection($this->db, new DateTime('2026-05-18 12:00:00'), new WellService());
        $section->process($playerId, 1000.0, ['degrade_mult' => 1.0, 'catastrophe_mult' => 1.0], 10.0, null);

        $stmt = $this->db->prepare(
            'SELECT id, condition_pct, transport_loss, status
               FROM well_pipelines
              WHERE player_id = ? AND well_id = ?'
        );
        $stmt->execute([$playerId, $ids['wellId']]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row);
        $this->assertEqualsWithDelta(69.5, (float)$row['condition_pct'], 0.001);
        $this->assertEqualsWithDelta(3.0, (float)$row['transport_loss'], 0.001);
        $this->assertSame('degraded', $row['status']);
        $this->assertSame(0, $section->disastersTriggered);
        $this->assertSame(0.0, $section->cashDelta);

        $tickStmt = $this->db->prepare(
            'SELECT condition_before, condition_after, loss_pct_before, loss_pct_after, opex_tick_cost, status_after
               FROM well_pipeline_tick_stats
              WHERE player_id = ? AND pipeline_id = ?
              ORDER BY id DESC
              LIMIT 1'
        );
        $tickStmt->execute([$playerId, (int)$row['id']]);
        $tickRow = $tickStmt->fetch();

        $this->assertNotFalse($tickRow);
        $this->assertEqualsWithDelta(70.5, (float)$tickRow['condition_before'], 0.001);
        $this->assertEqualsWithDelta(69.5, (float)$tickRow['condition_after'], 0.001);
        $this->assertEqualsWithDelta(2.0, (float)$tickRow['loss_pct_before'], 0.001);
        $this->assertEqualsWithDelta(3.0, (float)$tickRow['loss_pct_after'], 0.001);
        $this->assertEqualsWithDelta(140.0, (float)$tickRow['opex_tick_cost'], 0.001);
        $this->assertSame('degraded', $tickRow['status_after']);
    }

    public function testPipelineEngineerKeepsWearLowerLossStableAndLogsStatusChange(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedTechnicalStaff($playerId, $ids['staffId'], 'pipeline_engineer', 'Inzynier Rurociagow', 7, 10000);
        $this->seedHub($ids['hubId'], 'PHPUnit Tick Hub');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineService = new WellPipelineService($this->db);
        $pipelineService->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 120.0,
        ]);

        $this->db->prepare(
            'UPDATE well_pipelines
                SET condition_pct = 70.50,
                    transport_loss = 2.00,
                    degradation_rate_per_hour = 0.0500,
                    incident_risk_mult = 0.0000,
                    status = \'active\'
              WHERE player_id = ? AND well_id = ?'
        )->execute([$playerId, $ids['wellId']]);

        $section = new PipelineSection($this->db, new DateTime('2026-05-18 12:00:00'), new WellService());
        $section->process($playerId, 1000.0, ['degrade_mult' => 1.0, 'catastrophe_mult' => 1.0], 10.0, null);

        $stmt = $this->db->prepare(
            'SELECT id, condition_pct, transport_loss, status
               FROM well_pipelines
              WHERE player_id = ? AND well_id = ?'
        );
        $stmt->execute([$playerId, $ids['wellId']]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row);
        $this->assertEqualsWithDelta(70.0, (float)$row['condition_pct'], 0.001);
        $this->assertEqualsWithDelta(2.0, (float)$row['transport_loss'], 0.001);
        $this->assertSame('active', $row['status']);
        $this->assertSame(0, $section->disastersTriggered);
        $this->assertSame(0.0, $section->cashDelta);

        $eventStmt = $this->db->prepare(
            'SELECT COUNT(*)
               FROM well_pipeline_events
              WHERE player_id = ?
                AND pipeline_id = ?
                AND event_type = \'status_change\''
        );
        $eventStmt->execute([$playerId, (int)$row['id']]);
        $this->assertSame(0, (int)$eventStmt->fetchColumn());
    }

    public function testPipelineExplosionReportsSingleDeductibleStorageLoss(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Explosion Hub');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineService = new WellPipelineService($this->db);
        $pipelineService->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 120.0,
        ]);
        $this->db->prepare(
            "UPDATE well_pipelines
                SET condition_pct = 10.0,
                    degradation_rate_per_hour = 0.0,
                    incident_risk_mult = 100000.0,
                    status = 'active'
              WHERE player_id = ? AND well_id = ?"
        )->execute([$playerId, $ids['wellId']]);
        $pipelineId = (int)$this->db->query(
            "SELECT id FROM well_pipelines WHERE player_id = {$playerId} AND well_id = {$ids['wellId']}"
        )->fetchColumn();

        $section = new PipelineSection($this->db, new DateTime('2026-07-15 12:00:00'), new WellService());
        $section->process(
            $playerId,
            1000.0,
            ['degrade_mult' => 1.0, 'catastrophe_mult' => 1.0],
            1.0,
            null
        );

        $this->assertSame(1, $section->disastersTriggered);
        $this->assertEqualsWithDelta(50.0, $section->oilLostBbl, 0.001);
        $this->assertContains($pipelineId, $section->unavailablePipelineIds);
        $this->assertEqualsWithDelta(50.0, $section->oilLostByHubBbl[$ids['hubId']] ?? 0.0, 0.001);

        $stmt = $this->db->prepare(
            "SELECT oil_lost FROM industrial_disasters
              WHERE player_id = ? AND disaster_type = 'pipeline_explosion'
              ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$playerId]);
        $this->assertEqualsWithDelta(50.0, (float)$stmt->fetchColumn(), 0.001);
    }

    public function testFinishedBuildActivatesBeforeProcessingAndUsesOnlyActiveTime(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Build Hub');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineService = new WellPipelineService($this->db);
        $pipelineService->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 120.0,
        ]);
        $this->db->prepare(
            "UPDATE well_pipelines
                SET status = 'building',
                    build_finish_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE),
                    condition_pct = 100.0,
                    degradation_rate_per_hour = 0.05,
                    incident_risk_mult = 0.0
              WHERE player_id = ? AND well_id = ?"
        )->execute([$playerId, $ids['wellId']]);

        $section = new PipelineSection($this->db, new DateTime(), new WellService());
        $section->completeBuilds($playerId, null);

        $activeHoursProperty = new ReflectionProperty($section, 'completedActiveHours');
        $activeHours = $activeHoursProperty->getValue($section);
        $this->assertGreaterThan(0.45, (float)reset($activeHours));
        $this->assertLessThan(0.55, (float)reset($activeHours));

        $statusStmt = $this->db->prepare(
            'SELECT status FROM well_pipelines WHERE player_id = ? AND well_id = ?'
        );
        $statusStmt->execute([$playerId, $ids['wellId']]);
        $this->assertSame('active', $statusStmt->fetchColumn());

        $section->process($playerId, 1000.0, ['degrade_mult' => 1.0], 10.0, null);

        $rowStmt = $this->db->prepare(
            'SELECT condition_pct FROM well_pipelines WHERE player_id = ? AND well_id = ?'
        );
        $rowStmt->execute([$playerId, $ids['wellId']]);
        $this->assertEqualsWithDelta(99.95, (float)$rowStmt->fetchColumn(), 0.02);
    }

    public function testTwoHubsCanOwnSeparateOutboundPipelines(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'Outbound Hub One', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);
        $this->seedHub($ids['auxHubId'], 'Outbound Hub Two', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);

        $service = new WellPipelineService($this->db);
        $first = $service->purchaseHubOutboundPipeline($playerId, $ids['hubId'], 'standard');
        $second = $service->purchaseHubOutboundPipeline($playerId, $ids['auxHubId'], 'standard');

        $this->assertTrue($first['success'], $first['error'] ?? '');
        $this->assertTrue($second['success'], $second['error'] ?? '');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM well_pipelines WHERE player_id = ? AND well_id = 0 AND leg = 'outbound'"
        );
        $stmt->execute([$playerId]);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }
}
