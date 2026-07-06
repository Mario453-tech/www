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
        $this->seedPlayer(2, 80);
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

    private function seedPlayer(int $id, int $credibility): void
    {
        $this->db->prepare('INSERT INTO players (id, cash, bank_balance, company_credibility) VALUES (?, 0, 0, ?)')
            ->execute([$id, $credibility]);
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
    }
}
