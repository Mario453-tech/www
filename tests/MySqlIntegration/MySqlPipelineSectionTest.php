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
}
