<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/PlayerTickTransaction.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';

final class PlayerTickTransactionTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private FinancialTransactionService $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, cash NUMERIC, last_tick_at TEXT)');
        $this->db->exec('CREATE TABLE storage (player_id INTEGER PRIMARY KEY, used NUMERIC)');
        $this->db->exec('CREATE TABLE effects (player_id INTEGER, volume NUMERIC)');
        $this->db->exec("INSERT INTO players VALUES (1, 100, '2026-09-23 10:00:00')");
        $this->db->exec('INSERT INTO storage VALUES (1, 10)');
        $this->finance = new FinancialTransactionService($this->db);
    }

    public function testFailureAfterEffectsRollsBackEntirePeriodAndRetryAppliesOnce(): void
    {
        $boundary = new PlayerTickTransaction($this->db);
        try {
            $boundary->run(1, function (array $player): void {
                $this->process($player);
                throw new RuntimeException('Injected finance history failure');
            });
            self::fail('Expected rollback');
        } catch (RuntimeException $e) {
            self::assertSame('Injected finance history failure', $e->getMessage());
        }
        self::assertSame(10.0, (float) $this->db->query('SELECT used FROM storage')->fetchColumn());
        self::assertSame(100.0, (float) $this->db->query('SELECT cash FROM players')->fetchColumn());
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM effects')->fetchColumn());
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM bank_transactions')->fetchColumn());
        $boundary->run(1, $this->process(...));
        $boundary->run(1, $this->process(...));
        self::assertSame(15.0, (float) $this->db->query('SELECT used FROM storage')->fetchColumn());
        self::assertSame(90.0, (float) $this->db->query('SELECT cash FROM players')->fetchColumn());
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM effects')->fetchColumn());
    }

    /** @param array<string, mixed> $player */
    private function process(array $player): void
    {
        if ($player['last_tick_at'] >= '2026-09-23 10:05:00') {
            return;
        }
        $this->db->exec('INSERT INTO effects VALUES (1, 5)');
        $this->db->exec('UPDATE storage SET used = used + 5 WHERE player_id = 1');
        $this->finance->settleTickCosts(1, '2026-09-23 10:05:00', ['tick_opex' => 10.0]);
    }
}
