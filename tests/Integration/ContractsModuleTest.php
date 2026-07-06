<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/Modules/ContractsModule.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickContext.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/WalletConfig.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

/**
 * Testy Etapu 4 kontraktow: ContractsModule (TickModule).
 * Stage 4 contract tests: ContractsModule (TickModule).
 */
final class ContractsModuleTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private ContractsModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        // ContractService konstruktor tworzy tabele kontraktow (ContractSchema::ensure).
        // ContractService constructor creates contract tables (ContractSchema::ensure).
        (new ContractService($this->db))->setModuleEnabled(true);
        $this->module = new ContractsModule();
    }

    // ================================================================== kontrakt modulu

    public function testKeyIsContracts(): void
    {
        $this->assertSame('contracts', $this->module->key());
    }

    public function testOrderIs45(): void
    {
        $this->assertSame(45, $this->module->order());
    }

    public function testStatsAreZeroBeforeRun(): void
    {
        $stats = $this->module->stats();

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(0.0, $stats['revenue']);
        $this->assertSame(0.0, $stats['penalties']);
    }

    // ================================================================== delegacja do processDueContracts

    public function testRunProcessesDueContractAndReportsStats(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 100.0);
        $this->insertContract(1, [
            'next_delivery_at'   => '2025-06-01 11:00:00',
            'ends_at'            => '2099-12-31 00:00:00',
            'total_bbl'          => 500.0,
            'delivered_bbl'      => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 0.0,
        ]);

        $ctx = new TickContext($this->db, new DateTime('2025-06-01 12:00:00'), 'cron');
        $ctx->setNewPrice(100.0);

        $this->module->run($ctx);

        $stats = $this->module->stats();
        $this->assertSame(1, $stats['processed']);
        // 50 bbl * 100 price = 5000 revenue
        $this->assertSame(5000.0, $stats['revenue']);
        $this->assertSame(0.0, $stats['penalties']);
    }

    // ================================================================== statystyki trafiaja do TickContext

    public function testStatsMergeIntoTickContext(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 100.0);
        $this->insertContract(1, [
            'next_delivery_at'   => '2025-06-01 11:00:00',
            'ends_at'            => '2099-12-31 00:00:00',
            'total_bbl'          => 500.0,
            'delivered_bbl'      => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 0.0,
        ]);

        $ctx = new TickContext($this->db, new DateTime('2025-06-01 12:00:00'), 'cron');
        $ctx->setNewPrice(100.0);

        $this->module->run($ctx);
        $ctx->mergeStats($this->module->key(), $this->module->stats());

        $collected = $ctx->collectStats();
        $this->assertArrayHasKey('contracts', $collected);
        $this->assertSame(1, $collected['contracts']['processed']);
        $this->assertSame(5000.0, $collected['contracts']['revenue']);
    }

    // ================================================================== modul wylaczony

    public function testDisabledModuleReportsZeroStats(): void
    {
        (new ContractService($this->db))->setModuleEnabled(false);

        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 100.0);
        $this->insertContract(1, [
            'next_delivery_at'   => '2025-06-01 11:00:00',
            'ends_at'            => '2099-12-31 00:00:00',
            'total_bbl'          => 500.0,
            'delivered_bbl'      => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct'  => 0.0,
        ]);

        $ctx = new TickContext($this->db, new DateTime('2025-06-01 12:00:00'), 'cron');
        $ctx->setNewPrice(100.0);

        $this->module->run($ctx);

        $stats = $this->module->stats();
        $this->assertSame(0, $stats['processed']);
        $this->assertSame(0.0, $stats['revenue']);
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
        $deliveryBbl     = (float)($opts['terms_delivery_bbl'] ?? 50.0);
        $penaltyPct      = (float)($opts['terms_penalty_pct'] ?? 10.0);
        $intervalMinutes = (int)($opts['terms_delivery_interval_minutes'] ?? 60);
        $totalBbl        = (float)($opts['total_bbl'] ?? 500.0);

        $terms = json_encode([
            'total_bbl'                 => ['type' => 'float',  'value' => $totalBbl,        'text' => null],
            'delivery_bbl'              => ['type' => 'float',  'value' => $deliveryBbl,     'text' => null],
            'delivery_interval_minutes' => ['type' => 'int',    'value' => $intervalMinutes, 'text' => null],
            'duration_minutes'          => ['type' => 'int',    'value' => 43200.0,          'text' => null],
            'price_mode'                => ['type' => 'string', 'value' => 0.0,              'text' => 'market_plus_bonus'],
            'bonus_pct'                 => ['type' => 'float',  'value' => 0.0,              'text' => null],
            'penalty_pct'               => ['type' => 'float',  'value' => $penaltyPct,      'text' => null],
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
