<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

final class MySqlTechnicalPipelineTasksTest extends MySqlIntegrationTestCase
{
    public function testAssignAndCompletePipelineMaintenanceTaskOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $staffId = $this->seedTechnicalStaff($playerId, $ids['staffId'], 'pipeline_engineer', 'Inzynier Rurociagow', 7, 10000);
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Maintenance', 77, 'A1', 90.0, 'active');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineService = new WellPipelineService($this->db);
        $pipelineService->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 120.0,
        ]);

        $pipelineIdStmt = $this->db->prepare('SELECT id FROM well_pipelines WHERE player_id = ? AND well_id = ? LIMIT 1');
        $pipelineIdStmt->execute([$playerId, $ids['wellId']]);
        $pipelineId = (int)$pipelineIdStmt->fetchColumn();

        $this->db->prepare(
            "UPDATE well_pipelines
                SET condition_pct = 64.00,
                    transport_loss = 3.50,
                    status = 'degraded',
                    last_inspected_at = NULL
              WHERE player_id = ? AND well_id = ?"
        )->execute([$playerId, $ids['wellId']]);

        $service = new TechnicalTeamService($playerId);
        $assign = $service->assignTask($staffId, 'pipeline_maintenance', null, null, null, $pipelineId);

        $this->assertTrue($assign['success']);
        $this->assertArrayNotHasKey('queued', $assign);

        $task = $this->db->prepare(
            "SELECT id, status
               FROM technical_tasks
              WHERE player_id = ? AND staff_id = ? AND task_type = 'pipeline_maintenance'
              ORDER BY id DESC
              LIMIT 1"
        );
        $task->execute([$playerId, $staffId]);
        $taskRow = $task->fetch();

        $this->assertNotFalse($taskRow);
        $this->assertSame('in_progress', $taskRow['status']);

        $this->db->prepare('UPDATE technical_tasks SET end_time = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')
            ->execute([$taskRow['id']]);
        $service->processTick();

        $pipeline = $this->db->prepare(
            'SELECT condition_pct, transport_loss, status, last_inspected_at
               FROM well_pipelines
              WHERE player_id = ? AND well_id = ?'
        );
        $pipeline->execute([$playerId, $ids['wellId']]);
        $pipelineRow = $pipeline->fetch();

        $taskDone = $this->db->prepare('SELECT status FROM technical_tasks WHERE id = ?');
        $taskDone->execute([$taskRow['id']]);

        $this->assertNotFalse($pipelineRow);
        $this->assertGreaterThan(64.0, (float)$pipelineRow['condition_pct']);
        $this->assertLessThan(3.5, (float)$pipelineRow['transport_loss']);
        $this->assertSame('active', $pipelineRow['status']);
        $this->assertNotEmpty($pipelineRow['last_inspected_at']);
        $this->assertSame('completed', $taskDone->fetchColumn());
    }

    public function testAssignAndCompletePipelineRepairTaskOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $staffId = $this->seedTechnicalStaff($playerId, $ids['staffId'], 'pipeline_engineer', 'Inzynier Rurociagow', 8, 11000);
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Repair', 77, 'A1', 90.0, 'active');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineService = new WellPipelineService($this->db);
        $pipelineService->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 120.0,
        ]);

        $pipelineIdStmt = $this->db->prepare('SELECT id FROM well_pipelines WHERE player_id = ? AND well_id = ? LIMIT 1');
        $pipelineIdStmt->execute([$playerId, $ids['wellId']]);
        $pipelineId = (int)$pipelineIdStmt->fetchColumn();

        $this->db->prepare(
            "UPDATE well_pipelines
                SET condition_pct = 12.00,
                    transport_loss = 6.80,
                    status = 'damaged',
                    damaged_at = NOW()
              WHERE player_id = ? AND well_id = ?"
        )->execute([$playerId, $ids['wellId']]);

        $service = new TechnicalTeamService($playerId);
        $assign = $service->assignTask($staffId, 'pipeline_repair', null, null, null, $pipelineId);

        $this->assertTrue($assign['success']);

        $task = $this->db->prepare(
            "SELECT id, status
               FROM technical_tasks
              WHERE player_id = ? AND staff_id = ? AND task_type = 'pipeline_repair'
              ORDER BY id DESC
              LIMIT 1"
        );
        $task->execute([$playerId, $staffId]);
        $taskRow = $task->fetch();

        $this->assertNotFalse($taskRow);

        $this->db->prepare('UPDATE technical_tasks SET end_time = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')
            ->execute([$taskRow['id']]);
        $service->processTick();

        $pipeline = $this->db->prepare(
            'SELECT condition_pct, transport_loss, status, damaged_at, last_inspected_at
               FROM well_pipelines
              WHERE player_id = ? AND well_id = ?'
        );
        $pipeline->execute([$playerId, $ids['wellId']]);
        $pipelineRow = $pipeline->fetch();

        $taskDone = $this->db->prepare('SELECT status FROM technical_tasks WHERE id = ?');
        $taskDone->execute([$taskRow['id']]);

        $this->assertNotFalse($pipelineRow);
        $this->assertSame('active', $pipelineRow['status']);
        $this->assertGreaterThanOrEqual(80.0, (float)$pipelineRow['condition_pct']);
        $this->assertLessThan(6.8, (float)$pipelineRow['transport_loss']);
        $this->assertNull($pipelineRow['damaged_at']);
        $this->assertNotEmpty($pipelineRow['last_inspected_at']);
        $this->assertSame('completed', $taskDone->fetchColumn());
    }

 /**
  * L6: gdy rurociag zniknal (sprzedany) po starcie zadania, ukonczenie NIE moze
  * raportowac falszywego sukcesu "strata zmniejszona" — komunikat = pipeline_gone.
  * L6: if the pipeline is gone (sold) after the task started, completion must NOT report
  * a false "loss reduced" success — the message must be pipeline_gone.
  */
    public function testPipelineMaintenanceReportsNoOpWhenPipelineGone(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $staffId = $this->seedTechnicalStaff($playerId, $ids['staffId'], 'pipeline_engineer', 'Inzynier Rurociagow', 7, 10000);
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1', 'rurociag', 120.0, 50.0);
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Gone', 77, 'A1', 90.0, 'active');
        $this->seedAssignment($ids['hubId'], $ids['wellId']);

        $pipelineService = new WellPipelineService($this->db);
        $pipelineService->createPipelineForWell($playerId, [
            'id' => $ids['wellId'],
            'base_production_per_hour' => 50.0,
            'transport_capacity_pct' => 120.0,
        ]);

        $pipelineIdStmt = $this->db->prepare('SELECT id FROM well_pipelines WHERE player_id = ? AND well_id = ? LIMIT 1');
        $pipelineIdStmt->execute([$playerId, $ids['wellId']]);
        $pipelineId = (int)$pipelineIdStmt->fetchColumn();

        $this->db->prepare(
            "UPDATE well_pipelines
                SET condition_pct = 64.00, transport_loss = 3.50, status = 'degraded', last_inspected_at = NULL
              WHERE id = ?"
        )->execute([$pipelineId]);

        $service = new TechnicalTeamService($playerId);
        $assign = $service->assignTask($staffId, 'pipeline_maintenance', null, null, null, $pipelineId);
        $this->assertTrue($assign['success']);

        $taskStmt = $this->db->prepare(
            "SELECT id FROM technical_tasks
              WHERE player_id = ? AND staff_id = ? AND task_type = 'pipeline_maintenance'
              ORDER BY id DESC LIMIT 1"
        );
        $taskStmt->execute([$playerId, $staffId]);
        $taskId = (int)$taskStmt->fetchColumn();
        $this->assertGreaterThan(0, $taskId);

        // Rurociag zostaje sprzedany/usuniety JUZ po starcie zadania.
        // The pipeline is sold/removed AFTER the task has started.
        $this->db->prepare('DELETE FROM well_pipelines WHERE id = ?')->execute([$pipelineId]);

        $this->db->prepare('UPDATE technical_tasks SET end_time = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')
            ->execute([$taskId]);
        $service->processTick();

        // Zadanie konczy sie (status zaklepany na starcie), ale bez falszywego sukcesu.
        // The task still completes (status claimed at start), but without a false success.
        $statusStmt = $this->db->prepare('SELECT status FROM technical_tasks WHERE id = ?');
        $statusStmt->execute([$taskId]);
        $this->assertSame('completed', $statusStmt->fetchColumn());

        $noteStmt = $this->db->prepare(
            'SELECT message FROM technical_notifications WHERE player_id = ? ORDER BY id DESC LIMIT 1'
        );
        $noteStmt->execute([$playerId]);
        $this->assertSame(
            t('technical.task_msg.pipeline_gone'),
            $noteStmt->fetchColumn(),
            'Komunikat po zniknieciu rurociagu = pipeline_gone (brak falszywego "strata zmniejszona")'
        );
    }
}
