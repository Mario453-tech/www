<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/SabotageService.php';

final class MySqlSabotageServiceLockTest extends MySqlIntegrationTestCase
{
    private ?PDO $lockDb = null;
    private int $targetPlayerId;

    protected function setUp(): void
    {
        parent::setUp();

        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'];
        $this->lockDb = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->targetPlayerId = $this->seed + 50;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->lockDb !== null) {
                $this->lockDb->prepare('SELECT RELEASE_LOCK(?)')->execute(['sabotage_exec_' . $this->seed]);
            }
        } catch (Throwable $e) {
        }

        try {
            $this->db->prepare('DELETE FROM sabotage_logs WHERE player_id = ? OR target_player_id = ?')->execute([$this->seed, $this->targetPlayerId]);
        } catch (Throwable $e) {
        }
        try {
            $this->db->prepare('DELETE FROM sabotage_attempts WHERE player_id = ? OR target_player_id = ?')->execute([$this->seed, $this->targetPlayerId]);
        } catch (Throwable $e) {
        }
        try {
            $this->db->prepare('DELETE FROM bank_transactions WHERE transaction_type = ? AND (from_player_id IN (?, ?) OR to_player_id IN (?, ?))')
                ->execute([
                    FinancialTransactionService::TYPE_SABOTAGE,
                    $this->seed,
                    $this->targetPlayerId,
                    $this->seed,
                    $this->targetPlayerId,
                ]);
        } catch (Throwable $e) {
        }
        try {
            $this->db->prepare('DELETE FROM players WHERE id = ?')->execute([$this->targetPlayerId]);
        } catch (Throwable $e) {
        }

        unset($this->lockDb);
        parent::tearDown();
    }

    public function testBusyMysqlLockCancelsAttemptWithoutChargingOrRecording(): void
    {
        $attackerId = $this->seedPlayer();
        $this->seedTargetPlayer($this->targetPlayerId);

        $service = new SabotageService($this->db);
        $optionId = (int)$this->db->query(
            "SELECT id FROM sabotage_options
              WHERE target_type = 'player_company'
                AND context = 'player_company_sabotage'
              ORDER BY id ASC
              LIMIT 1"
        )->fetchColumn();

        $this->assertGreaterThan(0, $optionId, 'SabotageSchema should seed at least one player-company option.');

        $lockStmt = $this->lockDb->prepare('SELECT GET_LOCK(?, 5)');
        $lockStmt->execute(['sabotage_exec_' . $attackerId]);
        $this->assertSame(1, (int)$lockStmt->fetchColumn(), 'The helper connection should acquire the attacker lock.');

        $cashBeforeStmt = $this->db->prepare('SELECT cash FROM players WHERE id = ?');
        $cashBeforeStmt->execute([$attackerId]);
        $cashBefore = (float)$cashBeforeStmt->fetchColumn();

        $result = $service->executePlayerSabotage($attackerId, $this->targetPlayerId, $optionId);

        $this->assertFalse($result['success']);
        $this->assertSame('cancelled', $result['status']);
        $this->assertSame(tPlain('sabotage.err_busy'), $result['message']);

        $cashAfterStmt = $this->db->prepare('SELECT cash FROM players WHERE id = ?');
        $cashAfterStmt->execute([$attackerId]);
        $cashAfter = (float)$cashAfterStmt->fetchColumn();
        $this->assertEqualsWithDelta($cashBefore, $cashAfter, 0.001, 'Busy lock must not charge the attacker.');

        $attemptCountStmt = $this->db->prepare('SELECT COUNT(*) FROM sabotage_attempts WHERE player_id = ? OR target_player_id = ?');
        $attemptCountStmt->execute([$attackerId, $this->targetPlayerId]);
        $this->assertSame(0, (int)$attemptCountStmt->fetchColumn(), 'Busy lock must not create sabotage attempts.');

        $txCountStmt = $this->db->prepare(
            'SELECT COUNT(*)
               FROM bank_transactions
              WHERE transaction_type = ?
                AND (from_player_id IN (?, ?) OR to_player_id IN (?, ?))'
        );
        $txCountStmt->execute([
            FinancialTransactionService::TYPE_SABOTAGE,
            $attackerId,
            $this->targetPlayerId,
            $attackerId,
            $this->targetPlayerId,
        ]);
        $this->assertSame(0, (int)$txCountStmt->fetchColumn(), 'Busy lock must not write sabotage bank transactions.');
    }

    private function seedTargetPlayer(int $playerId): void
    {
        $username = 'phpunit_target_' . $playerId;
        $email = $username . '@example.test';

        $stmt = $this->db->prepare(
            'INSERT INTO players (id, username, email, password_hash, cash, status, created_at, last_tick_at, safety_procedures_level, procedure_integrity)
             VALUES (?, ?, ?, ?, 50000000.00, \'active\', NOW(), NOW(), 0, 100)'
        );
        $stmt->execute([$playerId, $username, $email, password_hash('secret', PASSWORD_BCRYPT)]);
    }
}
