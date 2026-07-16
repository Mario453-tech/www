<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

final class MySqlEmployeeAssignmentServiceTest extends MySqlIntegrationTestCase
{
    protected function tearDown(): void
    {
        if (isset($this->db, $this->seed)) {
            $this->db->prepare('DELETE FROM employee_assignments WHERE player_id = ?')->execute([$this->seed]);
            $this->db->prepare('DELETE FROM employee_state WHERE player_id = ?')->execute([$this->seed]);
            $this->db->prepare('DELETE FROM employee_source_links WHERE player_id = ?')->execute([$this->seed]);
        }
        parent::tearDown();
    }

    public function testMySqlAssignsTechnicalEmployeeToHubAndBlocksOverAllocation(): void
    {
        EmployeeSystemBootstrap::ensure($this->db);
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'Employee assignment hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);
        $this->seedHub($ids['auxHubId'], 'Employee assignment hub 2', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);
        $staffId = $this->seedTechnicalStaff($playerId, $ids['staffId'], 'maintenance_engineer', 'Maintenance Engineer', 7, 9700);

        $service = new EmployeeAssignmentService($this->db);
        $ref = new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId, $playerId);

        $result = $service->assignToHub($ref, $ids['hubId'], 75.0);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $service->listForHub($playerId, $ids['hubId']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Employee assignment allocation exceeds 100%.');
        $service->assignToHub($ref, $ids['auxHubId'], 30.0);
    }

    public function testMySqlReleaseEmployeeAssignmentsClosesAllActiveRows(): void
    {
        EmployeeSystemBootstrap::ensure($this->db);
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'Employee assignment release hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);
        $staffId = $this->seedTechnicalStaff($playerId, $ids['staffId'], 'maintenance_engineer', 'Maintenance Engineer', 7, 9700);

        $service = new EmployeeAssignmentService($this->db);
        $ref = new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId, $playerId);
        $service->assignToHub($ref, $ids['hubId'], 100.0);

        $this->assertSame(1, $service->releaseEmployeeAssignments($ref));
        $this->assertSame([], $service->listForHub($playerId, $ids['hubId']));
    }

    public function testMySqlConcurrentAssignmentsCannotExceedOneHundredPercent(): void
    {
        EmployeeSystemBootstrap::ensure($this->db);
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedHub($ids['hubId'], 'Concurrent employee hub 1', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);
        $this->seedHub($ids['auxHubId'], 'Concurrent employee hub 2', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);
        $staffId = $this->seedTechnicalStaff($playerId, $ids['staffId'], 'maintenance_engineer', 'Maintenance Engineer', 7, 9700);

        $root = dirname(__DIR__, 2);
        $workerFile = $root . '/tests/fixtures/employee_assignment_concurrent_worker.php';
        $gateFile = tempnam(sys_get_temp_dir(), 'employee_assignment_gate_');
        $this->assertIsString($gateFile);
        @unlink($gateFile);

        $workers = [];
        foreach ([$ids['hubId'], $ids['auxHubId']] as $hubId) {
            $readyFile = tempnam(sys_get_temp_dir(), 'employee_assignment_ready_');
            $this->assertIsString($readyFile);
            @unlink($readyFile);
            $pipes = [];
            $process = proc_open(
                [
                    PHP_BINARY,
                    $workerFile,
                    (string)$playerId,
                    (string)$staffId,
                    (string)$hubId,
                    '60',
                    $readyFile,
                    $gateFile,
                ],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $root
            );
            $this->assertIsResource($process);
            $workers[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $readyFile];
        }

        $deadline = microtime(true) + 10.0;
        do {
            $allReady = true;
            foreach ($workers as $worker) {
                if (!is_file($worker['ready'])) {
                    $allReady = false;
                    break;
                }
            }
            if ($allReady) {
                break;
            }
            usleep(25000);
        } while (microtime(true) < $deadline);
        $this->assertTrue($allReady, 'Concurrent employee assignment workers did not become ready.');
        file_put_contents($gateFile, 'go');

        $results = [];
        foreach ($workers as $worker) {
            $stdout = stream_get_contents($worker['pipes'][1]);
            $stderr = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exitCode = proc_close($worker['process']);
            @unlink($worker['ready']);
            $decoded = json_decode(trim((string)$stdout), true);
            $this->assertIsArray($decoded, 'stdout=' . $stdout . ' stderr=' . $stderr . ' exit=' . $exitCode);
            $results[] = $decoded;
        }
        @unlink($gateFile);

        $successCount = count(array_filter(
            $results,
            static fn(array $result): bool => !empty($result['success'])
        ));
        $this->assertSame(1, $successCount);

        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(allocation_pct), 0)
               FROM employee_assignments
              WHERE player_id = ?
                AND source_type = 'technical_staff'
                AND source_id = ?
                AND status = 'active'"
        );
        $stmt->execute([$playerId, $staffId]);
        $this->assertSame(60.0, (float)$stmt->fetchColumn());
    }
}
