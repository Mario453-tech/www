<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

/**
 * Tests that completeTask() is idempotent under concurrent (or sequential duplicate) calls.
 * Verifies the atomic claim guard prevents double-applying gameplay effects.
 */
final class MySqlTechnicalConcurrentTickTest extends MySqlIntegrationTestCase
{
    /**
     * Two calls to completeTask() for the same in_progress task must apply the
     * condition gain exactly once.  The second call must bail after the atomic
     * claim UPDATE returns rowCount=0 (status already flipped by the first call).
     */
    public function testConcurrentCompleteTaskAppliesConditionGainExactlyOnce(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $staffId  = $this->seedTechnicalWorker($playerId, 7); // skill=7 -> gain=30 (deterministic)
        $this->seedWell($playerId, $ids['wellId'], 'active');

        // Set a known starting condition far enough below 100 that even two gains would not cap.
        // skill=7 gain=30, two applications -> 30+30=60 -> still below 100. Start at 30.
        $this->db->prepare("UPDATE wells SET technical_condition = 30 WHERE id = ? AND player_id = ?")
                 ->execute([$ids['wellId'], $playerId]);

        // Directly insert a matured in_progress task (bypass startTask() to avoid cash deduction).
        $this->db->prepare("
            INSERT INTO technical_tasks
                (player_id, staff_id, task_type, well_id, hub_id, pipeline_id,
                 title, module_type, start_time, end_time, duration_hours, cost, status)
            VALUES (?, ?, 'well_maintenance', ?, NULL, NULL,
                    'PHPUnit concurrent test', NULL,
                    DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 MINUTE),
                    2, 0, 'in_progress')
        ")->execute([$playerId, $staffId, $ids['wellId']]);

        $taskId = (int)$this->db->lastInsertId();

        // Mark staff as busy (realistic pre-condition).
        $this->db->prepare("UPDATE technical_staff SET status = 'busy' WHERE id = ? AND player_id = ?")
                 ->execute([$staffId, $playerId]);

        // Fetch the task row exactly as processTick() does (with staff columns joined).
        $taskStmt = $this->db->prepare("
            SELECT tt.*, ts.spec_code, ts.skill_level, ts.first_name, ts.last_name
            FROM technical_tasks tt
            JOIN technical_staff ts ON tt.staff_id = ts.id
            WHERE tt.id = ? AND tt.player_id = ?
        ");
        $taskStmt->execute([$taskId, $playerId]);
        $taskRow = $taskStmt->fetch();
        $this->assertNotFalse($taskRow, 'Task row must exist before calling completeTask()');

        $service = new TechnicalTeamService($playerId);

        // First call: should claim the task and apply the condition gain.
        $service->completeTask($taskRow);

        // Second call with same data: should bail immediately (claim UPDATE -> rowCount=0).
        $service->completeTask($taskRow);

        // Assert condition increased by exactly one gain (30), not two (60).
        $wellStmt = $this->db->prepare("SELECT technical_condition FROM wells WHERE id = ? AND player_id = ?");
        $wellStmt->execute([$ids['wellId'], $playerId]);
        $condition = (float)$wellStmt->fetchColumn();

        $this->assertSame(60.0, $condition,
            "Expected condition 30+30=60 (one application), got {$condition}. " .
            'Double-credit guard may have regressed.'
        );

        // Assert task reached a final status (not stuck in_progress).
        $statusStmt = $this->db->prepare("SELECT status FROM technical_tasks WHERE id = ?");
        $statusStmt->execute([$taskId]);
        $taskStatus = $statusStmt->fetchColumn();

        $this->assertSame('completed', $taskStatus,
            "Task must be 'completed', got '{$taskStatus}'."
        );

        // Assert staff is unblocked.
        $staffStmt = $this->db->prepare("SELECT status FROM technical_staff WHERE id = ? AND player_id = ?");
        $staffStmt->execute([$staffId, $playerId]);
        $staffStatus = $staffStmt->fetchColumn();

        $this->assertSame('active', $staffStatus,
            "Staff must be 'active' after task completion, got '{$staffStatus}'."
        );
    }

    /**
     * When completeTask() is called for a task that is already in a final status
     * (e.g. 'completed' set by a previous tick), it must be a no-op.
     * This covers the case where the task row was fetched before the status flip
     * and completeTask() is called slightly after.
     */
    public function testCompleteTaskIsNoOpWhenTaskAlreadyCompleted(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $staffId  = $this->seedTechnicalWorker($playerId, 6);
        $this->seedWell($playerId, $ids['wellId'], 'active');

        $this->db->prepare("UPDATE wells SET technical_condition = 50 WHERE id = ? AND player_id = ?")
                 ->execute([$ids['wellId'], $playerId]);

        // Insert the task already in 'completed' state (simulates a task that was processed
        // by a concurrent tick milliseconds ago).
        $this->db->prepare("
            INSERT INTO technical_tasks
                (player_id, staff_id, task_type, well_id, hub_id, pipeline_id,
                 title, module_type, start_time, end_time, duration_hours, cost, status)
            VALUES (?, ?, 'well_maintenance', ?, NULL, NULL,
                    'PHPUnit already-done test', NULL,
                    DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR),
                    2, 0, 'completed')
        ")->execute([$playerId, $staffId, $ids['wellId']]);

        $taskId = (int)$this->db->lastInsertId();

        // Build a task array that looks like it was fetched while it was still in_progress.
        $taskRow = [
            'id'         => $taskId,
            'player_id'  => $playerId,
            'staff_id'   => $staffId,
            'task_type'  => 'well_maintenance',
            'well_id'    => $ids['wellId'],
            'hub_id'     => null,
            'pipeline_id'=> null,
            'title'      => 'PHPUnit already-done test',
            'module_type'=> null,
            'skill_level'=> 6,
            'spec_code'  => 'maintenance_engineer',
            'first_name' => 'Jan',
            'last_name'  => 'MySql',
        ];

        $service = new TechnicalTeamService($playerId);
        $service->completeTask($taskRow);

        // Condition must remain exactly 50 — no gain applied.
        $wellStmt = $this->db->prepare("SELECT technical_condition FROM wells WHERE id = ? AND player_id = ?");
        $wellStmt->execute([$ids['wellId'], $playerId]);
        $condition = (float)$wellStmt->fetchColumn();

        $this->assertSame(50.0, $condition,
            "completeTask() must be a no-op when status is already 'completed'. " .
            "Expected 50, got {$condition}."
        );
    }
}
