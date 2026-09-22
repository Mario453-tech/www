<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/AdminBankAdjustment.php';

final class AdminBankAdjustmentTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private AdminBankAdjustment $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, cash REAL DEFAULT 0, bank_balance REAL DEFAULT 0)');
        $this->db->exec('INSERT INTO players VALUES (1, 1000, 1000)');
        $this->db->exec('CREATE TABLE admin_logs (id INTEGER PRIMARY KEY, action TEXT, description TEXT,
            target_player_id INTEGER, target_type TEXT, target_id INTEGER, admin_user TEXT, admin_ip TEXT, created_at TEXT)');
        $this->service = new AdminBankAdjustment($this->db, new FinancialTransactionService($this->db));
    }

    public function testCreditAndDebitAuditActorAndTransaction(): void
    {
        foreach (['admin_credit', 'admin_debit'] as $action) {
            $result = $this->service->adjust(1, $action, '12.34', 'Correction', 'reviewer', '127.0.0.1');
            self::assertTrue($result['success']);
            $audit = $this->db->query('SELECT * FROM admin_logs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            self::assertSame('reviewer', $audit['admin_user']);
            self::assertSame(1, (int)$audit['target_id']);
            self::assertSame('player', $audit['target_type']);
            self::assertStringContainsString('transaction_id=' . $result['transaction_id'] . ';', $audit['description']);
            self::assertSame($action, $audit['action']);
        }
        self::assertSame(2000.0, (float)$this->db->query('SELECT cash + bank_balance FROM players WHERE id = 1')->fetchColumn());
    }

    public function testAuditFailureRollsBackMoneyAndTransaction(): void
    {
        $this->db->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON admin_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try {
            $this->service->adjust(1, 'admin_credit', '25', 'Correction', 'reviewer', '127.0.0.1');
            self::fail('Audit failure must abort adjustment');
        } catch (PDOException $e) {
            self::assertStringContainsString('audit unavailable', $e->getMessage());
        }
        self::assertSame(2000.0, (float)$this->db->query('SELECT cash + bank_balance FROM players WHERE id = 1')->fetchColumn());
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM bank_transactions')->fetchColumn());
        self::assertFalse($this->db->inTransaction());
    }

    public function testInsufficientFundsLeaveNoAudit(): void
    {
        try {
            $this->service->adjust(1, 'admin_debit', '99999', 'Correction', 'reviewer', '127.0.0.1');
            self::fail('Insufficient funds must fail');
        } catch (RuntimeException $e) {
            self::assertSame('Financial adjustment failed', $e->getMessage());
        }
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn());
    }
}
