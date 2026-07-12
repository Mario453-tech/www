<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/ContractService.php';

/**
 * ContractService P1 tests - storage contracts foundation only.
 * Testy ContractService P1 - tylko fundament kontraktow magazynowych.
 */
final class ContractServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private ContractService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->seedPlayer(1, 50);
        $this->service = new ContractService($this->db);
    }

    public function testSchemaCreatesTablesAndSeedsDefaultOptions(): void
    {
        $tables = [
            'contract_options',
            'contract_terms',
            'player_contracts',
            'contract_deliveries',
            'contract_logs',
            'contract_reputation',
            'contract_reputation_log',
        ];
        foreach ($tables as $table) {
            $stmt = $this->db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$table]);
            $this->assertSame($table, $stmt->fetchColumn(), "Missing table {$table}");
        }

        $this->assertSame(3, (int)$this->db->query('SELECT COUNT(*) FROM contract_options')->fetchColumn());
        $this->assertGreaterThanOrEqual(42, (int)$this->db->query('SELECT COUNT(*) FROM contract_terms')->fetchColumn());
    }

    public function testSchemaMigratesLegacySqlitePlayerContractsDepositColumns(): void
    {
        $this->db->exec('DROP TABLE player_contracts');
        $this->db->exec(
            "CREATE TABLE player_contracts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                contract_option_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NULL,
                context TEXT NOT NULL,
                buyer_name TEXT NOT NULL,
                contract_name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                total_bbl REAL NOT NULL DEFAULT 0.0,
                delivered_bbl REAL NOT NULL DEFAULT 0.0,
                missed_bbl REAL NOT NULL DEFAULT 0.0,
                next_delivery_at TEXT NOT NULL,
                starts_at TEXT NOT NULL,
                ends_at TEXT NOT NULL,
                completed_at TEXT NULL,
                cancelled_at TEXT NULL,
                terms_json TEXT NULL,
                created_at TEXT,
                updated_at TEXT
            )"
        );

        $cache = new ReflectionProperty(ContractSchema::class, 'ensured');
        $cache->setValue(null, null);

        ContractSchema::ensure($this->db);
        $columns = array_column($this->db->query('PRAGMA table_info(player_contracts)')->fetchAll(PDO::FETCH_ASSOC), 'name');

        $this->assertContains('security_deposit', $columns);
        $this->assertContains('security_deposit_status', $columns);
        $this->assertContains('security_deposit_refunded', $columns);
    }

    public function testModuleIsDisabledByDefaultAndCanBeToggled(): void
    {
        $this->assertFalse($this->service->isModuleEnabled());
        $this->assertSame([], $this->service->getAvailableOptions(1, ContractService::TARGET_STORAGE, ContractService::CONTEXT_STORAGE_DELIVERY));

        $this->service->setModuleEnabled(true);

        $this->assertTrue($this->service->isModuleEnabled());
        $this->assertNotSame([], $this->service->getAvailableOptions(1, ContractService::TARGET_STORAGE, ContractService::CONTEXT_STORAGE_DELIVERY));

        $this->service->setModuleEnabled(false);
        $this->assertFalse($this->service->isModuleEnabled());
    }

    public function testDeliveryAndLogListsSupportPagination(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $createdAt = sprintf('2026-07-12 10:%02d:00', $i);
            $this->db->prepare(
                "INSERT INTO contract_deliveries
                    (player_contract_id, player_id, due_at, required_bbl, delivered_bbl, missed_bbl, price_per_bbl, revenue, penalty, status, created_at)
                 VALUES (1, 1, ?, 10, ?, 0, 100, 1000, 0, 'delivered', ?)"
            )->execute([$createdAt, $i, $createdAt]);
            $this->db->prepare(
                "INSERT INTO contract_logs
                    (player_contract_id, player_id, target_type, target_id, context, event_key, message, created_at)
                 VALUES (1, 1, 'storage', NULL, 'storage_delivery', ?, 'test', ?)"
            )->execute(['event_' . $i, $createdAt]);
        }

        $this->assertSame(7, $this->service->countDeliveries(1));
        $this->assertSame(7, $this->service->countLogs(1));

        $firstDeliveryPage = $this->service->listDeliveries(1, 5, 0);
        $secondDeliveryPage = $this->service->listDeliveries(1, 5, 5);
        $firstLogPage = $this->service->listLogs(1, 5, 0);
        $secondLogPage = $this->service->listLogs(1, 5, 5);

        $this->assertCount(5, $firstDeliveryPage);
        $this->assertCount(2, $secondDeliveryPage);
        $this->assertSame(7.0, (float)$firstDeliveryPage[0]['delivered_bbl']);
        $this->assertSame(2.0, (float)$secondDeliveryPage[0]['delivered_bbl']);
        $this->assertCount(5, $firstLogPage);
        $this->assertCount(2, $secondLogPage);
        $this->assertSame('event_7', $firstLogPage[0]['event_key']);
        $this->assertSame('event_2', $secondLogPage[0]['event_key']);
    }

    public function testCleanupRemovesContractHistoryOlderThanTwoDays(): void
    {
        foreach (['2020-01-01 00:00:00', date('Y-m-d H:i:s')] as $idx => $createdAt) {
            $this->db->prepare(
                "INSERT INTO contract_deliveries
                    (player_contract_id, player_id, due_at, required_bbl, delivered_bbl, missed_bbl, price_per_bbl, revenue, penalty, status, created_at)
                 VALUES (1, 1, ?, 10, 10, 0, 100, 1000, 0, 'delivered', ?)"
            )->execute([$createdAt, $createdAt]);
            $this->db->prepare(
                "INSERT INTO contract_logs
                    (player_contract_id, player_id, target_type, target_id, context, event_key, message, created_at)
                 VALUES (1, 1, 'storage', NULL, 'storage_delivery', ?, 'test', ?)"
            )->execute(['cleanup_' . $idx, $createdAt]);
        }

        $this->assertSame(2, $this->service->cleanupHistoryOlderThanDays(2));
        $this->assertSame(1, $this->service->countDeliveries(1));
        $this->assertSame(1, $this->service->countLogs(1));
    }

    public function testAvailableOptionsContainTermsAndRequirementFlags(): void
    {
        $this->service->setModuleEnabled(true);

        $options = $this->service->getAvailableOptions(1, ContractService::TARGET_STORAGE, ContractService::CONTEXT_STORAGE_DELIVERY, 100.0);
        $byCode = $this->byCode($options);

        $this->assertArrayHasKey('small_local_refinery', $byCode);
        $this->assertArrayHasKey('medium_fuel_network', $byCode);
        $this->assertArrayHasKey('large_industrial_buyer', $byCode);

        $small = $byCode['small_local_refinery'];
        $this->assertTrue($small['requirements_met']);
        $this->assertNull($small['locked_reason']);
        $this->assertSame(5000.0, $small['terms']['total_bbl']['value']);
        $this->assertSame(360.0, $small['terms']['delivery_interval_minutes']['value']);
        $this->assertSame(100.0, $small['reference_value']);

        $changedReference = $this->service->getAvailableOptions(1, ContractService::TARGET_STORAGE, ContractService::CONTEXT_STORAGE_DELIVERY, 250.0);
        $this->assertSame(250.0, $this->byCode($changedReference)['small_local_refinery']['reference_value']);

        $large = $byCode['large_industrial_buyer'];
        $this->assertFalse($large['requirements_met']);
        $this->assertContains($large['locked_reason'], ['credibility', 'legal_level']);
    }

    public function testAcceptContractCreatesSnapshotAndLog(): void
    {
        $this->service->setModuleEnabled(true);
        $optionId = $this->optionId('small_local_refinery');

        $result = $this->service->acceptContract(
            1,
            $optionId,
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );

        $this->assertTrue($result['success'], $result['status']);
        $this->assertSame('signed', $result['status']);
        $contractId = (int)$result['contract_id'];

        $contract = $this->row('SELECT * FROM player_contracts WHERE id = ?', [$contractId]);
        $this->assertSame('active', $contract['status']);
        $this->assertSame(5000.0, (float)$contract['total_bbl']);
        $this->assertNotEmpty($contract['next_delivery_at']);
        $this->assertNotEmpty($contract['ends_at']);
        $this->assertStringContainsString('delivery_bbl', (string)$contract['terms_json']);

        $log = $this->row('SELECT * FROM contract_logs WHERE player_contract_id = ?', [$contractId]);
        $this->assertSame('contract_signed', $log['event_key']);
        $this->assertStringContainsString('small_local_refinery', (string)$log['meta_json']);
    }

    public function testDisabledModuleBlocksSigning(): void
    {
        $optionId = $this->optionId('small_local_refinery');

        $result = $this->service->acceptContract(
            1,
            $optionId,
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );

        $this->assertFalse($result['success']);
        $this->assertSame('module_disabled', $result['status']);
        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM player_contracts')->fetchColumn());
    }

    public function testMissingPlayerCannotSignContract(): void
    {
        $this->service->setModuleEnabled(true);

        $result = $this->service->acceptContract(
            999,
            $this->optionId('small_local_refinery'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );

        $this->assertFalse($result['success']);
        $this->assertSame('player_not_found', $result['status']);
        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM player_contracts')->fetchColumn());
    }

    public function testRequirementsAndActiveLimitAreEnforced(): void
    {
        $this->service->setModuleEnabled(true);
        $this->db->exec('UPDATE players SET bank_balance = 1000000 WHERE id = 1');

        $medium = $this->service->acceptContract(
            1,
            $this->optionId('medium_fuel_network'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertTrue($medium['success']);

        $large = $this->service->acceptContract(
            1,
            $this->optionId('large_industrial_buyer'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertFalse($large['success']);
        $this->assertSame('requirements_credibility', $large['status']);

        $this->db->exec("UPDATE contract_options SET max_active_per_player = 1 WHERE code = 'small_local_refinery'");
        $limited = $this->service->acceptContract(
            1,
            $this->optionId('small_local_refinery'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertFalse($limited['success']);
        $this->assertSame('limit_reached', $limited['status']);
    }

    public function testLegalRequirementIsEnforcedAndPassesWithLegalDirector(): void
    {
        $this->seedPlayer(2, 80, 0.0, 1000000.0);
        $this->service->setModuleEnabled(true);

        $blocked = $this->service->acceptContract(
            2,
            $this->optionId('large_industrial_buyer'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertFalse($blocked['success']);
        $this->assertSame('requirements_legal_level', $blocked['status']);

        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'legal')");
        $this->db->exec(
            "INSERT INTO board_members
                (player_id, role_id, status, skill_organization, skill_analysis, skill_ethics)
             VALUES (2, 1, 'active', 8, 8, 8)"
        );
        (new ContractReputationService($this->db))->changeScore(2, 10, 'test_boost');

        $accepted = $this->service->acceptContract(
            2,
            $this->optionId('large_industrial_buyer'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertTrue($accepted['success'], $accepted['status']);
    }

    public function testDepositIsDebitedWhenContractIsAccepted(): void
    {
        $this->service->setModuleEnabled(true);
        $this->db->exec('UPDATE players SET bank_balance = 200000 WHERE id = 1');

        $accepted = $this->service->acceptContract(
            1,
            $this->optionId('medium_fuel_network'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );

        $this->assertTrue($accepted['success'], $accepted['status']);
        $contract = $this->row('SELECT security_deposit, security_deposit_status FROM player_contracts WHERE id = ?', [(int)$accepted['contract_id']]);
        $this->assertSame(115500.0, (float)$contract['security_deposit']);
        $this->assertSame('paid', $contract['security_deposit_status']);
        $this->assertSame(84500.0, (float)$this->db->query('SELECT bank_balance FROM players WHERE id = 1')->fetchColumn());
        $this->assertSame('contract_deposit', (string)$this->db->query('SELECT transaction_type FROM bank_transactions WHERE reference_id = ' . (int)$accepted['contract_id'])->fetchColumn());
    }

    public function testDepositBlocksContractWhenFundsAreMissing(): void
    {
        $this->service->setModuleEnabled(true);

        $blocked = $this->service->acceptContract(
            1,
            $this->optionId('medium_fuel_network'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );

        $this->assertFalse($blocked['success']);
        $this->assertSame('insufficient_deposit_funds', $blocked['status']);
        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM player_contracts')->fetchColumn());
    }

    public function testDepositFailureDoesNotLeakInsertInsideOuterTransaction(): void
    {
        $this->service->setModuleEnabled(true);
        $this->db->beginTransaction();

        $blocked = $this->service->acceptContract(
            1,
            $this->optionId('medium_fuel_network'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );

        $this->assertFalse($blocked['success']);
        $this->assertSame('insufficient_deposit_funds', $blocked['status']);
        $this->assertTrue($this->db->inTransaction());
        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM player_contracts')->fetchColumn());
        $this->db->rollBack();
    }

    public function testDepositIsRefundedOnCompletedContract(): void
    {
        $this->service->setModuleEnabled(true);
        $this->db->exec('UPDATE players SET bank_balance = 200000 WHERE id = 1');
        $accepted = $this->service->acceptContract(
            1,
            $this->optionId('medium_fuel_network'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertTrue($accepted['success'], $accepted['status']);
        $contractId = (int)$accepted['contract_id'];
        $this->seedStorage(1, 5000.0);
        $this->db->prepare("UPDATE player_contracts SET total_bbl = 5000, next_delivery_at = '2025-06-01 11:00:00' WHERE id = ?")
            ->execute([$contractId]);

        $this->service->processDueContracts(100.0);

        $contract = $this->row('SELECT security_deposit_status, security_deposit_refunded FROM player_contracts WHERE id = ?', [$contractId]);
        $this->assertSame('refunded', $contract['security_deposit_status']);
        $this->assertSame(115500.0, (float)$contract['security_deposit_refunded']);
    }

    public function testDepositPartialRefundOnFailedContract(): void
    {
        $this->service->setModuleEnabled(true);
        $this->db->exec('UPDATE players SET bank_balance = 200000 WHERE id = 1');
        $accepted = $this->service->acceptContract(
            1,
            $this->optionId('medium_fuel_network'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertTrue($accepted['success'], $accepted['status']);
        $contractId = (int)$accepted['contract_id'];
        $this->seedStorage(1, 15000.0);
        $this->db->prepare(
            "UPDATE player_contracts
                SET total_bbl = 30000,
                    delivered_bbl = 10000,
                    next_delivery_at = '2025-06-01 11:00:00',
                    ends_at = '2025-06-01 10:00:00'
              WHERE id = ?"
        )->execute([$contractId]);

        $this->service->processDueContracts(100.0);

        $contract = $this->row('SELECT security_deposit_status, security_deposit_refunded FROM player_contracts WHERE id = ?', [$contractId]);
        $this->assertSame('partial_refund', $contract['security_deposit_status']);
        $this->assertSame(57750.0, (float)$contract['security_deposit_refunded']);
    }

    public function testAcceptContractDoesNotCommitOuterTransaction(): void
    {
        $this->service->setModuleEnabled(true);
        $this->db->beginTransaction();

        $result = $this->service->acceptContract(
            1,
            $this->optionId('small_local_refinery'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );

        $this->assertTrue($result['success'], $result['status']);
        $this->assertTrue($this->db->inTransaction(), 'Service must leave the caller transaction open.');
        $this->db->rollBack();

        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM player_contracts')->fetchColumn());
    }

    public function testCancelContractDoesNotRollbackOuterTransactionOnNotFound(): void
    {
        $this->db->beginTransaction();

        $result = $this->service->cancelContract(1, 999);

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['status']);
        $this->assertTrue($this->db->inTransaction(), 'Service must not roll back caller transaction.');
        $this->db->rollBack();
    }

    public function testCancelContractUpdatesStatusAndWritesLog(): void
    {
        $this->service->setModuleEnabled(true);
        $accepted = $this->service->acceptContract(
            1,
            $this->optionId('small_local_refinery'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );

        $result = $this->service->cancelContract(1, (int)$accepted['contract_id']);

        $this->assertTrue($result['success']);
        $this->assertSame('cancelled', $result['status']);

        $status = $this->db->query('SELECT status FROM player_contracts WHERE id = ' . (int)$accepted['contract_id'])->fetchColumn();
        $this->assertSame('cancelled', $status);

        $logs = $this->db->query("SELECT event_key FROM contract_logs ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['contract_signed', 'contract_cancelled'], $logs);
    }

    public function testCancelContractChargesPenaltyForfeitsDepositAndBlocksNewContracts(): void
    {
        $this->service->setModuleEnabled(true);
        $this->db->exec('UPDATE players SET bank_balance = 300000 WHERE id = 1');
        $accepted = $this->service->acceptContract(
            1,
            $this->optionId('medium_fuel_network'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertTrue($accepted['success'], $accepted['status']);
        $contractId = (int)$accepted['contract_id'];

        $result = $this->service->cancelContract(1, $contractId);

        $this->assertTrue($result['success'], $result['status']);
        $contract = $this->row('SELECT status, cancel_penalty, cancelled_reason, security_deposit_status FROM player_contracts WHERE id = ?', [$contractId]);
        $this->assertSame('cancelled', $contract['status']);
        $this->assertSame(69300.0, (float)$contract['cancel_penalty']);
        $this->assertSame('player_cancelled', $contract['cancelled_reason']);
        $this->assertSame('forfeited', $contract['security_deposit_status']);
        $this->assertSame(115200.0, (float)$this->db->query('SELECT bank_balance FROM players WHERE id = 1')->fetchColumn());

        $types = $this->db->query('SELECT transaction_type FROM bank_transactions ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['contract_deposit', 'contract_penalty'], $types);
        $this->assertSame(45, (int)$this->db->query('SELECT score FROM contract_reputation WHERE player_id = 1')->fetchColumn());
        $this->assertNotEmpty((string)$this->db->query('SELECT contract_blocked_until FROM contract_reputation WHERE player_id = 1')->fetchColumn());

        $blocked = $this->service->acceptContract(
            1,
            $this->optionId('small_local_refinery'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertFalse($blocked['success']);
        $this->assertSame('contracts_blocked', $blocked['status']);
    }

    public function testCancelContractCanBeDisabledByTerms(): void
    {
        $this->service->setModuleEnabled(true);
        $optionId = $this->optionId('small_local_refinery');
        $this->upsertTerm($optionId, 'allow_cancel', 0);
        $accepted = $this->service->acceptContract(
            1,
            $optionId,
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertTrue($accepted['success'], $accepted['status']);

        $result = $this->service->cancelContract(1, (int)$accepted['contract_id']);

        $this->assertFalse($result['success']);
        $this->assertSame('cancel_not_allowed', $result['status']);
        $this->assertSame('active', $this->db->query('SELECT status FROM player_contracts WHERE id = ' . (int)$accepted['contract_id'])->fetchColumn());
    }

    /**
     * @param list<array<string,mixed>> $options
     * @return array<string,array<string,mixed>>
     */
    private function byCode(array $options): array
    {
        $out = [];
        foreach ($options as $option) {
            $out[(string)$option['code']] = $option;
        }
        return $out;
    }

    private function optionId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM contract_options WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        return (int)$stmt->fetchColumn();
    }

    /** @param list<mixed> $params @return array<string,mixed> */
    private function row(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        return $row;
    }

    private function seedPlayer(int $id, int $credibility, float $cash = 0.0, float $bank = 0.0): void
    {
        $this->db->prepare('INSERT INTO players (id, cash, bank_balance, company_credibility) VALUES (?, ?, ?, ?)')
            ->execute([$id, $cash, $bank, $credibility]);
    }

    private function seedStorage(int $playerId, float $used, float $capacity = 1000.0): void
    {
        $this->db->prepare(
            "INSERT INTO storage (player_id, used, capacity, updated_at) VALUES (?, ?, ?, '2025-01-01 00:00:00')
             ON CONFLICT(player_id) DO UPDATE SET used = excluded.used, capacity = excluded.capacity"
        )->execute([$playerId, $used, $capacity]);
    }

    private function upsertTerm(int $optionId, string $key, float $value): void
    {
        $this->db->prepare(
            "INSERT INTO contract_terms (contract_option_id, term_key, term_type, term_value, term_text, created_at, updated_at)
             VALUES (?, ?, 'number', ?, NULL, '2025-01-01 00:00:00', '2025-01-01 00:00:00')
             ON CONFLICT(contract_option_id, term_key) DO UPDATE SET term_value = excluded.term_value"
        )->execute([$optionId, $key, $value]);
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
    }
}
