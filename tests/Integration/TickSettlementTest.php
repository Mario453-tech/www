<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';

final class TickSettlementTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private FinancialTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->db->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, cash NUMERIC, last_tick_at TEXT)");
        $this->db->exec("INSERT INTO players VALUES (1, 10.01, '2026-09-23 10:00:00'), (2, 99, '2026-09-23 10:00:00')");
        $this->service = new FinancialTransactionService($this->db);
    }

    public function testCashFloorAndAuditAgreeWithoutTouchingAnotherPlayer(): void
    {
        $result = $this->service->settleTickCosts(1, '2026-09-23 10:05:00', ['tax' => 3.0, 'tick_opex' => 20.0]);
        self::assertSame(['charged' => 10.01, 'cash_after' => 0.0], $result);
        self::assertSame(10.01, (float) $this->db->query('SELECT SUM(amount) FROM bank_transactions')->fetchColumn());
        self::assertSame(99.0, (float) $this->db->query('SELECT cash FROM players WHERE id = 2')->fetchColumn());
    }

    public function testAuditFailureRollsBackCashAndTick(): void
    {
        $this->db->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON bank_transactions BEGIN SELECT RAISE(ABORT, 'injected'); END");
        try {
            $this->service->settleTickCosts(1, '2026-09-23 10:05:00', ['tax' => 5.0]);
            self::fail('Expected audit failure');
        } catch (RuntimeException $e) {
            self::assertSame('Tick audit write failed', $e->getMessage());
        }
        self::assertSame(10.01, (float) $this->db->query('SELECT cash FROM players WHERE id = 1')->fetchColumn());
        self::assertSame('2026-09-23 10:00:00', $this->db->query('SELECT last_tick_at FROM players WHERE id = 1')->fetchColumn());
    }

    public function testOuterRollbackAlsoRevertsSettlement(): void
    {
        $this->db->beginTransaction();
        $this->service->settleTickCosts(1, '2026-09-23 10:05:00', ['tax' => 1.0]);
        self::assertTrue($this->db->inTransaction());
        $this->db->rollBack();
        self::assertSame(10.01, (float) $this->db->query('SELECT cash FROM players WHERE id = 1')->fetchColumn());
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM bank_transactions')->fetchColumn());
    }
}
