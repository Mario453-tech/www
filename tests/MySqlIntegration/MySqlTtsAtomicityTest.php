<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once __DIR__ . '/tts_test_service.php';

final class MySqlTtsAtomicityTest extends MySqlIntegrationTestCase
{
    public function testLockedStaffReadsForDifferentPlayersDoNotLockSharedDictionary(): void
    {
        $firstSeed = $this->seed;
        $secondSeed = $firstSeed + 100;
        $spec = 'tts_isolation_' . $firstSeed;
        $other = null;
        try {
            $this->db->prepare("INSERT INTO staff_specializations (code,name,role,repair_speed) VALUES (?,?,'technician',0.25)")->execute([$spec, $spec]);
            $firstPlayer = $this->seedPlayer();
            $firstStaff = $this->seedTechnicalStaff($firstPlayer, $firstSeed + 5, 'safety_engineer', 'Safety', 8);
            $this->seed = $secondSeed;
            $secondPlayer = $this->seedPlayer();
            $secondStaff = $this->seedTechnicalStaff($secondPlayer, $secondSeed + 5, 'safety_engineer', 'Safety', 8);
            $this->seed = $firstSeed;
            $this->db->prepare('UPDATE technical_staff SET specialization=? WHERE id IN (?,?)')->execute([$spec, $firstStaff, $secondStaff]);
            $cfg = require dirname(__DIR__, 2) . '/config/database.php';
            $other = new PDO('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4', $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $other->exec('SET SESSION innodb_lock_wait_timeout=1');
            $readStaff = new ReflectionMethod(TechnicalTeamService::class, 'getLockedTaskStaff');
            $this->db->beginTransaction();
            $firstRow = $readStaff->invoke(ttsTestService($this->db, $firstPlayer), $firstStaff);
            self::assertSame(0.25, (float)$firstRow['repair_speed']);
            $this->db->prepare('UPDATE technical_staff SET salary=12345 WHERE id=? AND player_id=?')->execute([$firstStaff, $firstPlayer]);
            $other->beginTransaction();
            $secondRow = $readStaff->invoke(ttsTestService($other, $secondPlayer), $secondStaff);
            self::assertSame(0.25, (float)$secondRow['repair_speed']);
            $other->prepare('UPDATE technical_staff SET salary=12345 WHERE id=? AND player_id=?')->execute([$secondStaff, $secondPlayer]);
            self::assertTrue($this->db->inTransaction());
            self::assertTrue($other->inTransaction());
            // Committing player two must not commit player one. / Commit gracza drugiego nie zatwierdza pierwszego.
            $other->commit();
            $this->db->rollBack();
            self::assertNull($readStaff->invoke(ttsTestService($other, $secondPlayer), $firstStaff));
            self::assertSame(9000.0, (float)$this->db->query("SELECT salary FROM technical_staff WHERE id=$firstStaff")->fetchColumn());
            self::assertSame(12345.0, (float)$this->db->query("SELECT salary FROM technical_staff WHERE id=$secondStaff")->fetchColumn());
        } finally {
            if ($other instanceof PDO && $other->inTransaction()) $other->rollBack();
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->seed = $secondSeed;
            $this->db->prepare('DELETE FROM bank_transactions WHERE from_player_id=?')->execute([$secondSeed]);
            $this->cleanupTrackedIds();
            $this->seed = $firstSeed;
            $this->db->prepare('UPDATE technical_staff SET specialization=NULL WHERE player_id=?')->execute([$firstSeed]);
            $this->db->prepare('DELETE FROM staff_specializations WHERE code=?')->execute([$spec]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) $this->db->rollBack();
        $this->db->prepare('DELETE FROM bank_transactions WHERE from_player_id=?')->execute([$this->seed]);
        parent::tearDown();
    }

    public function testPromotionValidatesCurrentTargetDespiteOlderTransactionSnapshot(): void
    {
        $playerId = $this->seedPlayer();
        $staffId = $this->seedTechnicalWorker($playerId);
        $wellId = $this->getTrackedIds()['wellId'];
        $this->seedWell($playerId, $wellId);
        $this->db->prepare("INSERT INTO technical_tasks (player_id,staff_id,task_type,title,status,start_time,end_time) VALUES (?,?,'safety_audit','Previous task','in_progress',NOW(),NOW())")->execute([$playerId, $staffId]);
        $taskId = (int)$this->db->lastInsertId();
        $service = ttsTestService($this->db, $playerId);
        self::assertTrue($service->assignTask($staffId, 'well_maintenance', $wellId)['queued']);
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $other = new PDO('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4', $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->beginTransaction();
        self::assertSame('active', $this->db->query("SELECT status FROM wells WHERE id=$wellId")->fetchColumn());
        $other->prepare("UPDATE wells SET status='sold' WHERE id=? AND player_id=?")->execute([$wellId, $playerId]);
        self::assertTrue($service->cancelTask($taskId)['success']);
        self::assertTrue($this->db->inTransaction());
        self::assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM technical_task_queue WHERE player_id=$playerId")->fetchColumn());
        self::assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM technical_tasks WHERE player_id=$playerId AND status='in_progress'")->fetchColumn());
        self::assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions WHERE from_player_id=$playerId")->fetchColumn());
        $this->db->rollBack();
        self::assertSame('in_progress', $this->db->query("SELECT status FROM technical_tasks WHERE id=$taskId")->fetchColumn());
    }

    public function testConcurrentStartsUseCurrentLockedStateAndChargeOnlyOnce(): void
    {
        $playerId = $this->seedPlayer();
        $staffId = $this->seedTechnicalStaff($playerId, $this->seed + 5, 'safety_engineer', 'Safety', 8);
        new FinancialTransactionService($this->db);
        for ($iteration = 0; $iteration < 3; $iteration++) {
            $before = (float)$this->db->query('SELECT cash+bank_balance FROM players WHERE id=' . $playerId)->fetchColumn();
            $workers = [];
            try {
                $this->db->beginTransaction();
                $this->db->query('SELECT id FROM players WHERE id=' . $playerId . ' FOR UPDATE')->fetchColumn();
                for ($i = 0; $i < 2; $i++) {
                    $pipes = [];
                    $process = proc_open([PHP_BINARY, __DIR__ . '/tts_start_worker.php', (string)$playerId, (string)$staffId], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, dirname(__DIR__, 2));
                    self::assertIsResource($process);
                    $workers[] = [$process, $pipes];
                    stream_set_timeout($pipes[1], 15);
                    self::assertSame("READY\n", fgets($pipes[1]));
                }
                foreach ($workers as [, $pipes]) {
                    fwrite($pipes[0], "GO\n");
                    fflush($pipes[0]);
                }
                usleep(150000);
                $this->db->commit();
                $results = [];
                foreach ($workers as [$process, $pipes]) {
                    $output = stream_get_contents($pipes[1]);
                    $error = stream_get_contents($pipes[2]);
                    foreach ($pipes as $pipe) fclose($pipe);
                    self::assertSame(0, proc_close($process), $error);
                    $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
                }
                $workers = [];
                self::assertTrue($results[0]['success']);
                self::assertTrue($results[1]['success']);
                self::assertSame(1, count(array_filter($results, static fn(array $r): bool => !empty($r['queued']))));
                self::assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM technical_tasks WHERE player_id=$playerId AND status='in_progress'")->fetchColumn());
                self::assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM technical_task_queue WHERE player_id=$playerId")->fetchColumn());
                $cost = (float)$this->db->query("SELECT cost FROM technical_tasks WHERE player_id=$playerId AND status='in_progress'")->fetchColumn();
                $after = (float)$this->db->query("SELECT cash+bank_balance FROM players WHERE id=$playerId")->fetchColumn();
                self::assertEqualsWithDelta($cost, $before - $after, 0.01);
                self::assertSame($iteration + 1, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions WHERE from_player_id=$playerId AND transaction_type='tts_fee'")->fetchColumn());
                $this->db->exec("UPDATE technical_tasks SET status='completed' WHERE player_id=$playerId");
                $this->db->exec("DELETE FROM technical_task_queue WHERE player_id=$playerId");
            } finally {
                if ($this->db->inTransaction()) $this->db->rollBack();
                foreach ($workers as [$process, $pipes]) {
                    if (is_resource($process)) proc_terminate($process);
                    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                    if (is_resource($process)) proc_close($process);
                }
            }
        }
    }
}
