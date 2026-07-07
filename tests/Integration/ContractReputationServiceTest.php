<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/ContractService.php';
require_once dirname(__DIR__, 2) . '/src/ContractReputationService.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/WalletConfig.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

/**
 * Contract reputation regression tests.
 * Testy regresji reputacji kontraktowej.
 */
final class ContractReputationServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private ContractService $contracts;
    private ContractReputationService $reputation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->contracts = new ContractService($this->db);
        $this->contracts->setModuleEnabled(true);
        $this->reputation = new ContractReputationService($this->db);
    }

    public function testSchemaCreatesReputationTablesAndDefaultScore(): void
    {
        $this->seedPlayer(1, 50);

        $this->assertSame(50, $this->reputation->getScore(1));
        $this->assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM contract_reputation')->fetchColumn());
        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM contract_reputation_log')->fetchColumn());
    }

    public function testScoreIsClampedAndLogged(): void
    {
        $this->seedPlayer(1, 50);

        $this->reputation->changeScore(1, 80, 'manual_boost', null, ['source' => 'test']);
        $this->assertSame(100, $this->reputation->getScore(1));

        $this->reputation->changeScore(1, -150, 'manual_drop');
        $this->assertSame(0, $this->reputation->getScore(1));

        $logs = $this->db->query('SELECT delta, score_after, reason FROM contract_reputation_log ORDER BY id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(50, (int)$logs[0]['delta']);
        $this->assertSame(100, (int)$logs[0]['score_after']);
        $this->assertSame('manual_drop', $logs[1]['reason']);
        $this->assertSame(0, (int)$logs[1]['score_after']);
    }

    public function testContractReputationRequirementBlocksAndThenAllowsSigning(): void
    {
        $this->seedPlayer(1, 80);
        $optionId = $this->optionId('small_local_refinery');
        $this->upsertTerm($optionId, 'min_contract_reputation', 80);

        $options = $this->contracts->getAvailableOptions(1, ContractService::TARGET_STORAGE, ContractService::CONTEXT_STORAGE_DELIVERY);
        $small = $this->byCode($options)['small_local_refinery'];
        $this->assertFalse($small['requirements_met']);
        $this->assertSame('contract_reputation', $small['locked_reason']);

        $blocked = $this->contracts->acceptContract(1, $optionId, ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertFalse($blocked['success']);
        $this->assertSame('requirements_contract_reputation', $blocked['status']);

        $this->reputation->changeScore(1, 30, 'manual_boost');
        $accepted = $this->contracts->acceptContract(1, $optionId, ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertTrue($accepted['success'], $accepted['status']);
    }

    public function testTickUpdatesReputationForSuccessfulAndPerfectContract(): void
    {
        $this->seedPlayer(1, 50);
        $this->seedStorage(1, 100.0);
        $contractId = $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 11:00:00',
            'ends_at' => '2099-12-31 00:00:00',
            'total_bbl' => 50.0,
            'delivered_bbl' => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct' => 0.0,
        ]);

        $result = $this->contracts->processDueContracts(100.0);

        $this->assertSame(1, $result['completed']);
        $this->assertSame(53, $this->reputation->getScore(1));
        $rep = $this->row('SELECT * FROM contract_reputation WHERE player_id = ?', [1]);
        $this->assertSame(1, (int)$rep['total_contracts']);
        $this->assertSame(1, (int)$rep['completed_contracts']);
        $this->assertSame(1, (int)$rep['perfect_contracts']);
        $this->assertSame(0, (int)$rep['missed_deliveries']);

        $reasons = $this->db->query("SELECT reason FROM contract_reputation_log WHERE contract_id = {$contractId} ORDER BY id ASC")
            ->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['delivery_success', 'contract_perfect'], $reasons);
    }

    public function testTickUpdatesReputationForMissAndFailure(): void
    {
        $this->seedPlayer(1, 50);
        $this->seedStorage(1, 0.0);
        $contractId = $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 11:00:00',
            'ends_at' => '2025-06-01 10:00:00',
            'total_bbl' => 50.0,
            'delivered_bbl' => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct' => 0.0,
        ]);

        $result = $this->contracts->processDueContracts(100.0);

        $this->assertSame(1, $result['failed']);
        $this->assertSame(46, $this->reputation->getScore(1));
        $rep = $this->row('SELECT * FROM contract_reputation WHERE player_id = ?', [1]);
        $this->assertSame(1, (int)$rep['total_contracts']);
        $this->assertSame(1, (int)$rep['failed_contracts']);
        $this->assertSame(1, (int)$rep['missed_deliveries']);

        $reasons = $this->db->query("SELECT reason FROM contract_reputation_log WHERE contract_id = {$contractId} ORDER BY id ASC")
            ->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['delivery_missed', 'contract_failed'], $reasons);
    }

    public function testPartialDeliveryUpdatesReputationAndMissedCounter(): void
    {
        $this->seedPlayer(1, 50);
        $this->seedStorage(1, 20.0);
        $contractId = $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 11:00:00',
            'ends_at' => '2099-12-31 00:00:00',
            'total_bbl' => 500.0,
            'delivered_bbl' => 0.0,
            'terms_delivery_bbl' => 50.0,
            'terms_penalty_pct' => 0.0,
        ]);

        $result = $this->contracts->processDueContracts(100.0);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(49, $this->reputation->getScore(1));
        $rep = $this->row('SELECT * FROM contract_reputation WHERE player_id = ?', [1]);
        $this->assertSame(1, (int)$rep['missed_deliveries']);

        $reasons = $this->db->query("SELECT reason FROM contract_reputation_log WHERE contract_id = {$contractId} ORDER BY id ASC")
            ->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['delivery_partial'], $reasons);
    }

    public function testCancelContractUpdatesReputation(): void
    {
        $this->seedPlayer(1, 50);
        $optionId = $this->optionId('small_local_refinery');
        $accepted = $this->contracts->acceptContract(1, $optionId, ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertTrue($accepted['success']);

        $cancelled = $this->contracts->cancelContract(1, (int)$accepted['contract_id']);

        $this->assertTrue($cancelled['success']);
        $this->assertSame(49, $this->reputation->getScore(1));
        $rep = $this->row('SELECT * FROM contract_reputation WHERE player_id = ?', [1]);
        $this->assertSame(1, (int)$rep['cancelled_contracts']);
        $this->assertSame(1, (int)$rep['total_contracts']);
    }

    public function testCancelUsesSignedTermsSnapshotAfterAdminTermChange(): void
    {
        $this->seedPlayer(1, 50);
        $optionId = $this->optionId('small_local_refinery');
        $accepted = $this->contracts->acceptContract(1, $optionId, ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertTrue($accepted['success']);

        $this->upsertTerm($optionId, 'reputation_loss_on_cancel', -10);
        $cancelled = $this->contracts->cancelContract(1, (int)$accepted['contract_id']);

        $this->assertTrue($cancelled['success']);
        $this->assertSame(49, $this->reputation->getScore(1));
        $log = $this->row('SELECT delta, reason FROM contract_reputation_log WHERE contract_id = ?', [(int)$accepted['contract_id']]);
        $this->assertSame(-1, (int)$log['delta']);
        $this->assertSame('contract_cancelled', $log['reason']);
    }

    public function testAdminListScoresIncludesDefaultRowsAndSearch(): void
    {
        $this->seedPlayer(1, 50, 'alpha', 'Alpha Oil');
        $this->seedPlayer(2, 50, 'beta', 'Beta Gas');
        $this->reputation->changeScore(2, -20, 'manual_drop');

        $rows = $this->reputation->listScores('', 10);

        $this->assertSame(2, count($rows));
        $this->assertSame(2, (int)$rows[0]['player_id']);
        $this->assertSame(30, (int)$rows[0]['score']);
        $this->assertSame(1, (int)$rows[1]['player_id']);
        $this->assertSame(50, (int)$rows[1]['score']);

        $filtered = $this->reputation->listScores('Alpha', 10);
        $this->assertSame(1, count($filtered));
        $this->assertSame(1, (int)$filtered[0]['player_id']);
    }

    public function testAdminAdjustmentUsesLedgerAndRecentLogsCanFilterPlayer(): void
    {
        $this->seedPlayer(1, 50, 'alpha', 'Alpha Oil');
        $this->seedPlayer(2, 50, 'beta', 'Beta Gas');

        $result = $this->reputation->adminAdjustScore(1, 12, 'Manual admin correction');
        $this->reputation->changeScore(2, -5, 'manual_drop');

        $this->assertSame(62, $result['score']);
        $this->assertSame(62, $this->reputation->getScore(1));

        $logs = $this->reputation->recentLogs(1, 10);
        $this->assertSame(1, count($logs));
        $this->assertSame(1, (int)$logs[0]['player_id']);
        $this->assertSame('admin_adjustment', $logs[0]['reason']);
        $this->assertSame(12, (int)$logs[0]['delta']);
        $this->assertStringContainsString('Manual admin correction', (string)$logs[0]['meta_json']);
    }

    private function seedPlayer(int $id, int $credibility, string $username = '', string $companyName = ''): void
    {
        $username = $username !== '' ? $username : 'player' . $id;
        $companyName = $companyName !== '' ? $companyName : 'Company ' . $id;
        $this->db->prepare('INSERT INTO players (id, username, company_name, cash, bank_balance, company_credibility) VALUES (?, ?, ?, 0, 0, ?)')
            ->execute([$id, $username, $companyName, $credibility]);
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
        $deliveryBbl = (float)($opts['terms_delivery_bbl'] ?? 50.0);
        $penaltyPct = (float)($opts['terms_penalty_pct'] ?? 10.0);
        $intervalMinutes = (int)($opts['terms_delivery_interval_minutes'] ?? 60);
        $totalBbl = (float)($opts['total_bbl'] ?? 500.0);

        $terms = json_encode([
            'total_bbl' => ['type' => 'float', 'value' => $totalBbl, 'text' => null],
            'delivery_bbl' => ['type' => 'float', 'value' => $deliveryBbl, 'text' => null],
            'delivery_interval_minutes' => ['type' => 'int', 'value' => $intervalMinutes, 'text' => null],
            'duration_minutes' => ['type' => 'int', 'value' => 43200.0, 'text' => null],
            'price_mode' => ['type' => 'string', 'value' => 0.0, 'text' => 'market_plus_bonus'],
            'bonus_pct' => ['type' => 'float', 'value' => 0.0, 'text' => null],
            'penalty_pct' => ['type' => 'float', 'value' => $penaltyPct, 'text' => null],
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

    private function optionId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM contract_options WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        return (int)$stmt->fetchColumn();
    }

    private function upsertTerm(int $optionId, string $key, float $value): void
    {
        $this->db->prepare(
            "INSERT INTO contract_terms (contract_option_id, term_key, term_type, term_value, term_text, created_at, updated_at)
             VALUES (?, ?, 'number', ?, NULL, datetime('now'), datetime('now'))
             ON CONFLICT(contract_option_id, term_key) DO UPDATE SET term_value = excluded.term_value"
        )->execute([$optionId, $key, $value]);
    }

    /** @param list<array<string,mixed>> $options @return array<string,array<string,mixed>> */
    private function byCode(array $options): array
    {
        $out = [];
        foreach ($options as $option) {
            $out[(string)$option['code']] = $option;
        }
        return $out;
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

    private function createSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                username TEXT NULL,
                company_name TEXT NULL,
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
