<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/ContractService.php';

/**
 * Testy ContractReputationService.
 * Tests for ContractReputationService.
 */
final class ContractReputationServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private ContractReputationService $rep;
    private ContractService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->db->prepare('INSERT INTO players (id, cash, bank_balance, company_credibility) VALUES (?, 0, 0, ?)')
            ->execute([1, 50]);
        // ContractService::ensure() creates all contract tables including contract_reputation.
        $this->service = new ContractService($this->db);
        $this->rep     = $this->service->reputation();
    }

    public function testDefaultScoreIs50WhenNoRow(): void
    {
        $this->assertSame(50, $this->rep->getScore(1));
    }

    public function testGetStatsReturnsDefaultsWhenNoRow(): void
    {
        $stats = $this->rep->getStats(1);
        $this->assertSame(50, $stats['score']);
        $this->assertSame(0, $stats['total_contracts']);
        $this->assertSame(0, $stats['completed_contracts']);
        $this->assertSame(0, $stats['failed_contracts']);
        $this->assertSame(0, $stats['cancelled_contracts']);
        $this->assertSame(0, $stats['missed_deliveries']);
        $this->assertSame(0, $stats['perfect_contracts']);
    }

    public function testChangeScoreClampsAt0And100(): void
    {
        $this->rep->changeScore(1, -100, 'test_big_loss');
        $this->assertSame(0, $this->rep->getScore(1));

        $this->rep->changeScore(1, +200, 'test_big_gain');
        $this->assertSame(100, $this->rep->getScore(1));

        $log = (int)$this->db->query('SELECT COUNT(*) FROM contract_reputation_log WHERE player_id = 1')->fetchColumn();
        $this->assertGreaterThanOrEqual(2, $log, 'Both changes must be logged');
    }

    public function testChangeScoreLogsEntryWithContractId(): void
    {
        $this->rep->changeScore(1, +5, 'delivery_success', 42);

        $row = $this->db->query(
            "SELECT * FROM contract_reputation_log WHERE player_id = 1 ORDER BY id DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame(5, (int)$row['delta']);
        $this->assertSame(42, (int)$row['contract_id']);
        $this->assertSame('delivery_success', $row['reason']);
        $this->assertSame(55, (int)$row['score_after']);
    }

    public function testOnDeliverySuccessAppliesGain(): void
    {
        $terms = ['reputation_gain_on_delivery' => ['type' => 'number', 'value' => 2.0, 'text' => null]];
        $this->rep->onDeliverySuccess(1, 99, $terms);
        $this->assertSame(52, $this->rep->getScore(1));
    }

    public function testOnDeliveryMissAppliesLossAndIncrementsMissedDeliveries(): void
    {
        $terms = ['reputation_loss_on_missed_delivery' => ['type' => 'number', 'value' => -3.0, 'text' => null]];
        $this->rep->onDeliveryMiss(1, 99, $terms);
        $this->assertSame(47, $this->rep->getScore(1));

        $stats = $this->rep->getStats(1);
        $this->assertSame(1, $stats['missed_deliveries']);
    }

    public function testOnDeliveryPartialAppliesLossAndIncrementsMissedDeliveries(): void
    {
        $terms = ['reputation_loss_on_partial_delivery' => ['type' => 'number', 'value' => -1.0, 'text' => null]];
        $this->rep->onDeliveryPartial(1, 99, $terms);
        $this->assertSame(49, $this->rep->getScore(1));

        $stats = $this->rep->getStats(1);
        $this->assertSame(1, $stats['missed_deliveries']);
    }

    public function testOnContractCompletedPerfectGrantsGainAndIncrementsCounters(): void
    {
        $terms = ['reputation_gain_on_perfect_contract' => ['type' => 'number', 'value' => 6.0, 'text' => null]];
        $this->rep->onContractCompleted(1, 7, true, $terms);
        $this->assertSame(56, $this->rep->getScore(1));

        $stats = $this->rep->getStats(1);
        $this->assertSame(1, $stats['completed_contracts']);
        $this->assertSame(1, $stats['perfect_contracts']);
    }

    public function testOnContractCompletedImperfectNoGain(): void
    {
        $terms = ['reputation_gain_on_perfect_contract' => ['type' => 'number', 'value' => 6.0, 'text' => null]];
        $this->rep->onContractCompleted(1, 7, false, $terms);
        $this->assertSame(50, $this->rep->getScore(1));

        $stats = $this->rep->getStats(1);
        $this->assertSame(1, $stats['completed_contracts']);
        $this->assertSame(0, $stats['perfect_contracts']);
    }

    public function testOnContractFailedAppliesLossAndIncrementsCounter(): void
    {
        $terms = ['reputation_loss_on_contract_failed' => ['type' => 'number', 'value' => -8.0, 'text' => null]];
        $this->rep->onContractFailed(1, 5, $terms);
        $this->assertSame(42, $this->rep->getScore(1));

        $stats = $this->rep->getStats(1);
        $this->assertSame(1, $stats['failed_contracts']);
    }

    public function testOnContractCancelledAppliesLossAndIncrementsCounter(): void
    {
        $terms = ['reputation_loss_on_cancel' => ['type' => 'number', 'value' => -5.0, 'text' => null]];
        $this->rep->onContractCancelled(1, 3, $terms);
        $this->assertSame(45, $this->rep->getScore(1));

        $stats = $this->rep->getStats(1);
        $this->assertSame(1, $stats['cancelled_contracts']);
    }

    public function testMinContractReputationBlocksSigning(): void
    {
        // Seed a high-reputation-required option.
        $this->db->exec(
            "INSERT INTO contract_options
                (code, name, description, buyer_name, target_type, context, is_active, price_mode,
                 price_multiplier, severity, min_credibility, requires_legal_level,
                 max_active_per_player, sort_order, created_at, updated_at)
             VALUES ('high_rep_contract', 'High rep contract', '', 'TestBuyer', 'storage', 'storage_oil_delivery',
                     1, 'market_plus_bonus', 1.0, 'high', 0, 0, 3, 90, datetime('now'), datetime('now'))"
        );
        $optionId = (int)$this->db->query("SELECT id FROM contract_options WHERE code = 'high_rep_contract'")->fetchColumn();
        $terms = [
            ['total_bbl', 1000.0],
            ['delivery_bbl', 500.0],
            ['delivery_interval_minutes', 60.0],
            ['duration_minutes', 120.0],
            ['penalty_pct', 5.0],
            ['min_contract_reputation', 80.0],
        ];
        foreach ($terms as [$key, $val]) {
            $type = str_contains($key, 'minutes') ? 'minutes' : (str_contains($key, 'pct') ? 'percent' : 'number');
            $this->db->prepare(
                "INSERT OR IGNORE INTO contract_terms
                    (contract_option_id, term_key, term_type, term_value, term_text, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NULL, datetime('now'), datetime('now'))"
            )->execute([$optionId, $key, $type, $val]);
        }

        $this->service->setModuleEnabled(true);

        // Player has default score 50, needs 80.
        $result = $this->service->acceptContract(1, $optionId, ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertFalse($result['success']);
        $this->assertSame('requirements_reputation', $result['status']);

        // Boost reputation to 80 and try again.
        $this->rep->changeScore(1, +30, 'test_boost');
        $result2 = $this->service->acceptContract(1, $optionId, ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertTrue($result2['success'], $result2['status']);
    }

    public function testCancelContractTriggersReputationLoss(): void
    {
        $this->service->setModuleEnabled(true);
        $accepted = $this->service->acceptContract(
            1,
            $this->optionId('small_local_refinery'),
            ContractService::TARGET_STORAGE,
            null,
            ContractService::CONTEXT_STORAGE_DELIVERY
        );
        $this->assertTrue($accepted['success']);

        $scoreBefore = $this->rep->getScore(1);
        $this->service->cancelContract(1, (int)$accepted['contract_id']);

        // small_local_refinery has reputation_loss_on_cancel = -2
        $this->assertSame($scoreBefore - 2, $this->rep->getScore(1));
        $stats = $this->rep->getStats(1);
        $this->assertSame(1, $stats['cancelled_contracts']);
    }

    private function optionId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM contract_options WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        return (int)$stmt->fetchColumn();
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
