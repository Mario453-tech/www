<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/LogisticsService.php';
require_once dirname(__DIR__, 2) . '/src/WellPipelineService.php';

final class MySqlLogisticsOwnershipFlowTest extends MySqlIntegrationTestCase
{
    public function testCurrentSummaryTreatsLegacyPipelineSelectionAsUnsetWhenNoPipelineExists(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);

        $service = new LogisticsService($playerId);
        $summary = $service->getCurrentSummary();

        $this->assertCount(1, $summary['wells']);
        $this->assertSame('rurociag', $summary['wells'][0]['selected_transport']);
        $this->assertSame('nieustawiony', $summary['wells'][0]['transport']);
    }

    public function testOptimizeSwitchesUnownedLandPipelineToTrucksWithoutCreatingPipeline(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);

        $service = new LogisticsService($playerId);
        $result = $service->optimize('max_prod');

        $this->assertTrue($result['success']);

        $wellStmt = $this->db->prepare('SELECT transport_type FROM wells WHERE id = ? AND player_id = ?');
        $wellStmt->execute([$ids['wellId'], $playerId]);
        $this->assertSame('ciezarowki', $wellStmt->fetchColumn());

        $pipeStmt = $this->db->prepare('SELECT COUNT(*) FROM well_pipelines WHERE player_id = ? AND well_id = ?');
        $pipeStmt->execute([$playerId, $ids['wellId']]);
        $this->assertSame(0, (int)$pipeStmt->fetchColumn());
    }

    public function testCurrentSummaryUsesPipelineWhenOwnedPipelineExists(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Owned');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineService = new WellPipelineService($this->db);
        $pipelineService->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 120.0,
            'pipeline_type' => 'standard',
        ]);

        $service = new LogisticsService($playerId);
        $summary = $service->getCurrentSummary();

        $this->assertCount(1, $summary['wells']);
        $this->assertSame('rurociag', $summary['wells'][0]['transport']);
    }
}
