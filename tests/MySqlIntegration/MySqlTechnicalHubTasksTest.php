<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

final class MySqlTechnicalHubTasksTest extends MySqlIntegrationTestCase
{
    public function testAssignAndCompleteHubMaintenanceTaskOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $staffId = $this->seedTechnicalWorker($playerId, 7);
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Maint', 77, 'A1', 40.0, 'active');
        $this->seedAssignment($ids['hubId'], $ids['wellId'], 'active');

        $service = new TechnicalTeamService($playerId);

        $assign = $service->assignTask($staffId, 'hub_maintenance', null, null, $ids['hubId']);
        $this->assertTrue($assign['success']);
        $this->assertArrayNotHasKey('queued', $assign);

        $task = $this->db->prepare('SELECT * FROM technical_tasks WHERE player_id = ? AND staff_id = ? AND task_type = \'hub_maintenance\' ORDER BY id DESC LIMIT 1');
        $task->execute([$playerId, $staffId]);
        $taskRow = $task->fetch();

        $this->assertNotFalse($taskRow);
        $this->assertSame((string)$ids['hubId'], (string)$taskRow['hub_id']);
        $this->assertSame('in_progress', $taskRow['status']);

        $this->db->prepare('UPDATE technical_tasks SET end_time = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([$taskRow['id']]);
        $service->processTick();

        $hub = $this->db->prepare('SELECT condition_pct, repair_cost_estimate, last_maintenance_at FROM logistics_hubs WHERE id = ?');
        $hub->execute([$ids['hubId']]);
        $hubRow = $hub->fetch();

        $taskDone = $this->db->prepare('SELECT status FROM technical_tasks WHERE id = ?');
        $taskDone->execute([$taskRow['id']]);

        $this->assertGreaterThan(40.0, (float)$hubRow['condition_pct']);
        $this->assertLessThan(200000.0, (float)$hubRow['repair_cost_estimate']);
        $this->assertNotEmpty($hubRow['last_maintenance_at']);
        $this->assertSame('completed', $taskDone->fetchColumn());
    }

    public function testBusyWorkerQueuesSecondHubTaskOnRealMySql(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $staffId = $this->seedTechnicalWorker($playerId, 6);
        $this->seedWell($playerId, $ids['wellId'], 'active', 77, 'A1');
        $this->seedHub($ids['hubId'], 'PHPUnit Hub Main', 77, 'A1', 55.0, 'active');
        $this->seedHub($ids['auxHubId'], 'PHPUnit Hub Queue', 77, 'A1', 33.0, 'damaged');
        $this->seedAssignment($ids['hubId'], $ids['wellId'], 'active');
        $this->seedAssignment($ids['auxHubId'], $ids['wellId'], 'detached');

        $service = new TechnicalTeamService($playerId);

        $first = $service->assignTask($staffId, 'hub_maintenance', null, null, $ids['hubId']);
        $this->assertTrue($first['success']);

        $second = $service->assignTask($staffId, 'hub_repair', null, null, $ids['hubId']);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['queued']);

        $queue = $this->db->prepare('SELECT task_type, hub_id FROM technical_task_queue WHERE player_id = ? AND staff_id = ? ORDER BY id DESC LIMIT 1');
        $queue->execute([$playerId, $staffId]);
        $queueRow = $queue->fetch();

        $this->assertSame('hub_repair', $queueRow['task_type']);
        $this->assertSame((string)$ids['hubId'], (string)$queueRow['hub_id']);
    }
}
