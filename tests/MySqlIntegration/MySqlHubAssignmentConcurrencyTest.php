<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

final class MySqlHubAssignmentConcurrencyTest extends MySqlIntegrationTestCase
{
    public function testTwoConcurrentAssignmentsCannotTakeTheSameLastHubSlot(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedWell($playerId, $ids['wellId']);
        $this->seedWell($playerId, $ids['auxWellId']);
        $this->seedHub($ids['hubId'], 'Concurrent assignment hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 0.0, $playerId);
        $this->db->prepare('UPDATE logistics_hubs SET slot_limit = 1 WHERE id = ?')
            ->execute([$ids['hubId']]);

        $root = dirname(__DIR__, 2);
        $workerFile = $root . '/tests/fixtures/hub_assignment_concurrent_worker.php';
        $gateFile = tempnam(sys_get_temp_dir(), 'hub_gate_');
        $this->assertIsString($gateFile);
        @unlink($gateFile);

        $workers = [];
        foreach ([$ids['wellId'], $ids['auxWellId']] as $wellId) {
            $readyFile = tempnam(sys_get_temp_dir(), 'hub_ready_');
            $this->assertIsString($readyFile);
            @unlink($readyFile);
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, $workerFile, (string)$playerId, (string)$ids['hubId'], (string)$wellId, $readyFile, $gateFile],
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
        $this->assertTrue($allReady, 'Concurrent workers did not become ready.');
        file_put_contents($gateFile, 'go');

        $results = [];
        foreach ($workers as $worker) {
            $stdout = stream_get_contents($worker['pipes'][1]);
            $stderr = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exit = proc_close($worker['process']);
            @unlink($worker['ready']);
            $decoded = json_decode(trim((string)$stdout), true);
            $this->assertIsArray($decoded, 'stdout=' . $stdout . ' stderr=' . $stderr . ' exit=' . $exit);
            $results[] = $decoded;
        }
        @unlink($gateFile);

        $successCount = count(array_filter($results, static fn(array $result): bool => !empty($result['success'])));
        $this->assertSame(1, $successCount);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM logistics_hub_assignments WHERE hub_id = ? AND status = 'active'"
        );
        $stmt->execute([$ids['hubId']]);
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }
}
