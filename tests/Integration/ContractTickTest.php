<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/ContractService.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/WalletConfig.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

/**
 * Testy Etapu 3 kontraktow: processDueContracts / processOneDueContract.
 * Stage 3 contract tests: processDueContracts / processOneDueContract.
 */
final class ContractTickTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private ContractService $service;

    /** Fixed "now" used in all tests — past enough to be deterministic. */
    private \DateTime $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->service = new ContractService($this->db);
        $this->service->setModuleEnabled(true);
        $this->now = new \DateTime('2025-06-01 12:00:00');
    }

    // ================================================================== disabled / no-op

    public function testModuleDisabledReturnsZeroStats(): void
    {
        $this->service->setModuleEnabled(false);

        $result = $this->service->processDueContracts($this->now, 100.0);

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0.0, $result['revenue']);
        $this->assertSame(0.0, $result['penalties']);
    }

    public function testNoDueContractsReturnsZeroStats(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        // Contract is not yet due.
        $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 18:00:00',
            'ends_at'          => '2025-06-30 00:00:00',
            'total_bbl'        => 500.0,
            'delivered_bbl'    => 0.0,
        ]);

        $result = $this->service->processDueContracts($this->now, 100.0);

        $this->assertSame(0, $result['processed']);
    }

    // ================================================================== full delivery

    public function testFullDeliveryCreditsRevenueAndDeductsStorage(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 100.0);
        $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 11:00:00',
            'ends_at'          => '2025-06-30 00:00:00',
            'total_bbl'        => 500.0,
            'delivered_bbl'    => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 10.0,
        ]);

        $result = $this->service->processDueContracts($this->now, 100.0);

        $this->assertSame(1, $result['processed']);
        // 50 bbl * 100 price = 5000 revenue
        $this->assertSame(5000.0, $result['revenue']);
        $this->assertSame(0.0, $result['penalties']);
        // Storage reduced by 50
        $this->assertSame(50.0, $this->storageUsed(1));
        // Bank balance received the revenue
        $this->assertSame(5000.0, $this->bankOf(1));
    }

    // ================================================================== partial delivery

    public function testPartialDeliveryGeneratesRevenueAndPenalty(): void
    {
        $this->seedPlayer(1, 1000.0, 0.0);
        $this->seedStorage(1, 20.0); // Only 20 bbl, need 50
        $this->insertContract(1, [
            'next_delivery_at'  => '2025-06-01 11:00:00',
            'ends_at'           => '2025-06-30 00:00:00',
            'total_bbl'         => 500.0,
            'delivered_bbl'     => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 10.0,
        ]);

        $result = $this->service->processDueContracts($this->now, 100.0);

        $this->assertSame(1, $result['processed']);
        // 20 delivered * 100 = 2000, 30 missed * 100 * 10% = 300
        $this->assertSame(2000.0, $result['revenue']);
        $this->assertSame(300.0, $result['penalties']);
        $this->assertSame(0.0, $this->storageUsed(1));
        // credit 2000 then debit penalty 300 from bank; bank net = 1700, cash untouched
        $this->assertSame(1700.0, $this->bankOf(1));
        $this->assertSame(1000.0, $this->cashOf(1));
    }

    // ================================================================== missed delivery (no oil)

    public function testMissedDeliveryAppliesPenaltyOnly(): void
    {
        $this->seedPlayer(1, 1000.0, 200.0);
        $this->seedStorage(1, 0.0);
        $this->insertContract(1, [
            'next_delivery_at'  => '2025-06-01 11:00:00',
            'ends_at'           => '2025-06-30 00:00:00',
            'total_bbl'         => 500.0,
            'delivered_bbl'     => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 10.0,
        ]);

        $result = $this->service->processDueContracts($this->now, 100.0);

        $this->assertSame(0.0, $result['revenue']);
        // 50 missed * 100 * 10% = 500
        $this->assertSame(500.0, $result['penalties']);
        // Bank (200) fully drained, remaining 300 from cash (1000-300=700)
        $this->assertSame(0.0, $this->bankOf(1));
        $this->assertSame(700.0, $this->cashOf(1));
    }

    // ================================================================== delivery record

    public function testDeliveryRecordIsWrittenToTable(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 50.0);
        $contractId = $this->insertContract(1, [
            'next_delivery_at'  => '2025-06-01 11:00:00',
            'ends_at'           => '2025-06-30 00:00:00',
            'total_bbl'         => 500.0,
            'delivered_bbl'     => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 0.0,
        ]);

        $this->service->processDueContracts($this->now, 100.0);

        $delivery = $this->db->query(
            "SELECT * FROM contract_deliveries WHERE player_contract_id = {$contractId}"
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($delivery);
        $this->assertSame('delivered', $delivery['status']);
        $this->assertSame(50.0, (float)$delivery['delivered_bbl']);
        $this->assertSame(0.0,  (float)$delivery['missed_bbl']);
        $this->assertSame(100.0, (float)$delivery['price_per_bbl']);
        $this->assertSame(5000.0, (float)$delivery['revenue']);
        $this->assertSame('2025-06-01 11:00:00', (string)$delivery['due_at']);
    }

    // ================================================================== contract completion

    public function testContractCompletesWhenAllBblDelivered(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 200.0); // plenty of oil
        $contractId = $this->insertContract(1, [
            'next_delivery_at'  => '2025-06-01 11:00:00',
            'ends_at'           => '2025-06-30 00:00:00',
            'total_bbl'         => 100.0,
            'delivered_bbl'     => 60.0, // 40 remaining
            'terms_delivery_bbl' => 50.0, // but only 40 needed → deliver 40
            'terms_penalty_pct'  => 0.0,
        ]);

        $result = $this->service->processDueContracts($this->now, 100.0);

        $this->assertSame(1, $result['completed']);
        $this->assertSame(0, $result['failed']);

        $status = $this->db->query(
            "SELECT status FROM player_contracts WHERE id = {$contractId}"
        )->fetchColumn();
        $this->assertSame('completed', $status);
    }

    // ================================================================== contract failure

    public function testContractFailsWhenPastEndDate(): void
    {
        $this->seedPlayer(1, 1000.0, 0.0);
        $this->seedStorage(1, 0.0);
        $contractId = $this->insertContract(1, [
            'next_delivery_at'  => '2025-06-01 11:00:00',
            'ends_at'           => '2025-06-01 10:00:00', // ends_at < now
            'total_bbl'         => 500.0,
            'delivered_bbl'     => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 0.0,
        ]);

        $result = $this->service->processDueContracts($this->now, 100.0);

        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['completed']);

        $status = $this->db->query(
            "SELECT status FROM player_contracts WHERE id = {$contractId}"
        )->fetchColumn();
        $this->assertSame('failed', $status);
    }

    // ================================================================== log event

    public function testLogEventIsWrittenAfterDelivery(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 50.0);
        $contractId = $this->insertContract(1, [
            'next_delivery_at'  => '2025-06-01 11:00:00',
            'ends_at'           => '2025-06-30 00:00:00',
            'total_bbl'         => 500.0,
            'delivered_bbl'     => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 0.0,
        ]);

        $this->service->processDueContracts($this->now, 100.0);

        $events = $this->db->query(
            "SELECT event_key FROM contract_logs WHERE player_contract_id = {$contractId}"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('contract_delivery', $events);
    }

    // ================================================================== next_delivery advances

    public function testNextDeliveryAtAdvancesAfterTick(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 100.0);
        $contractId = $this->insertContract(1, [
            'next_delivery_at'           => '2025-06-01 11:00:00',
            'ends_at'                    => '2025-06-30 00:00:00',
            'total_bbl'                  => 500.0,
            'delivered_bbl'              => 0.0,
            'terms_delivery_bbl'         => 50.0,
            'terms_penalty_pct'          => 0.0,
            'terms_delivery_interval_minutes' => 120,
        ]);

        $this->service->processDueContracts($this->now, 100.0);

        $nextDelivery = $this->db->query(
            "SELECT next_delivery_at FROM player_contracts WHERE id = {$contractId}"
        )->fetchColumn();
        // now + 120 minutes = 2025-06-01 14:00:00
        $this->assertSame('2025-06-01 14:00:00', (string)$nextDelivery);
    }

    // ================================================================== helpers

    private function seedPlayer(int $id, float $cash, float $bank): void
    {
        $this->db->prepare(
            'INSERT INTO players (id, cash, bank_balance, company_credibility) VALUES (?, ?, ?, 50)'
        )->execute([$id, $cash, $bank]);
    }

    private function seedStorage(int $playerId, float $used, float $capacity = 1000.0): void
    {
        $this->db->prepare(
            "INSERT INTO storage (player_id, used, capacity, updated_at) VALUES (?, ?, ?, '2025-01-01 00:00:00')"
        )->execute([$playerId, $used, $capacity]);
    }

    /** @param array<string,mixed> $opts */
    private function insertContract(int $playerId, array $opts): int
    {
        $deliveryBbl      = (float)($opts['terms_delivery_bbl'] ?? 50.0);
        $penaltyPct       = (float)($opts['terms_penalty_pct'] ?? 10.0);
        $intervalMinutes  = (int)($opts['terms_delivery_interval_minutes'] ?? 60);
        $totalBbl         = (float)($opts['total_bbl'] ?? 500.0);

        $terms = json_encode([
            'total_bbl'                   => ['type' => 'float', 'value' => $totalBbl,        'text' => null],
            'delivery_bbl'                => ['type' => 'float', 'value' => $deliveryBbl,     'text' => null],
            'delivery_interval_minutes'   => ['type' => 'int',   'value' => $intervalMinutes, 'text' => null],
            'duration_minutes'            => ['type' => 'int',   'value' => 43200.0,           'text' => null],
            'price_mode'                  => ['type' => 'string','value' => 0.0,               'text' => 'market_plus_bonus'],
            'bonus_pct'                   => ['type' => 'float', 'value' => 0.0,               'text' => null],
            'penalty_pct'                 => ['type' => 'float', 'value' => $penaltyPct,       'text' => null],
        ]);

        $this->db->prepare(
            "INSERT INTO player_contracts
                (player_id, contract_option_id, target_type, target_id, context, buyer_name, contract_name,
                 status, total_bbl, delivered_bbl, missed_bbl, next_delivery_at, starts_at, ends_at,
                 terms_json, created_at, updated_at)
             VALUES (?, 1, 'storage', NULL, 'storage_oil_delivery', 'Test Buyer', 'Test Contract',
                     'active', ?, ?, 0, ?, '2025-01-01 00:00:00', ?, ?, '2025-01-01 00:00:00', '2025-01-01 00:00:00')"
        )->execute([
            $playerId,
            $totalBbl,
            (float)($opts['delivered_bbl'] ?? 0.0),
            (string)$opts['next_delivery_at'],
            (string)$opts['ends_at'],
            $terms,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function cashOf(int $id): float
    {
        return (float)$this->db->query("SELECT cash FROM players WHERE id = {$id}")->fetchColumn();
    }

    private function bankOf(int $id): float
    {
        return (float)$this->db->query("SELECT bank_balance FROM players WHERE id = {$id}")->fetchColumn();
    }

    private function storageUsed(int $playerId): float
    {
        return (float)$this->db->query("SELECT used FROM storage WHERE player_id = {$playerId}")->fetchColumn();
    }

    private function createSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                cash REAL NOT NULL DEFAULT 0,
                bank_balance REAL NOT NULL DEFAULT 0,
                company_credibility INTEGER NOT NULL DEFAULT 50
            )'
        );
        $this->db->exec(
            'CREATE TABLE well_config (
                "key" TEXT PRIMARY KEY,
                "value" TEXT NOT NULL,
                label TEXT NULL,
                category TEXT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE board_roles (
                id INTEGER PRIMARY KEY,
                code TEXT NOT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE board_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                skill_organization INTEGER NOT NULL DEFAULT 0,
                skill_analysis INTEGER NOT NULL DEFAULT 0,
                skill_ethics INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->db->exec(
            'CREATE TABLE storage (
                player_id INTEGER PRIMARY KEY,
                used REAL NOT NULL DEFAULT 0,
                capacity REAL NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE bank_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                from_player_id INTEGER NULL,
                to_player_id INTEGER NULL,
                amount REAL NOT NULL,
                transaction_type TEXT NOT NULL,
                description TEXT NULL,
                reference_type TEXT NULL,
                reference_id INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }
}
