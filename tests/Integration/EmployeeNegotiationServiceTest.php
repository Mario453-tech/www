<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeSystemConfigService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeDialogueTemplateService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeNegotiationService.php';

final class EmployeeNegotiationServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private EmployeeSystemConfigService $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSourceSchema();
        $this->config = new EmployeeSystemConfigService($this->db);
    }

    public function testDialogueSeedCreatesAtLeastEightyBilingualTemplates(): void
    {
        $service = new EmployeeDialogueTemplateService($this->db);

        $rows = $service->list();

        $this->assertGreaterThanOrEqual(80, count($rows));
        foreach ($rows as $row) {
            $this->assertNotSame('', trim((string)$row['text_pl']));
            $this->assertNotSame('', trim((string)$row['text_en']));
            $this->assertStringNotContainsString('?', (string)$row['text_pl']);
        }
    }

    public function testDialogueRejectsUnknownPlaceholder(): void
    {
        $service = new EmployeeDialogueTemplateService($this->db);

        $this->expectException(InvalidArgumentException::class);
        $service->save([
            'context_key' => 'counteroffer',
            'tone' => 'formal',
            'text_pl' => 'Nieznany {bad_placeholder}',
            'text_en' => 'Unknown {bad_placeholder}',
            'weight' => 1,
            'is_active' => 1,
        ]);
    }

    public function testDialogueDoesNotRepeatTemplateBeforeUnusedVariantExists(): void
    {
        $service = new EmployeeDialogueTemplateService($this->db);
        $first = $service->choose('counteroffer', 'technical', null, 'firm', 11);
        $this->assertIsArray($first);

        $this->db->prepare(
            'INSERT INTO employee_strike_negotiation_rounds
                (negotiation_id, strike_id, player_id, round_no, idempotency_token, raise_pct,
                 bonus_per_member, random_roll, formula_json, dialogue_template_id, result)
             VALUES (1, 11, 1, 1, ?, 1, 0, 50, ?, ?, \'rejected\')'
        )->execute(['token-1', '{}', (int)$first['id']]);

        $second = $service->choose('counteroffer', 'technical', null, 'firm', 11);
        $this->assertIsArray($second);
        $this->assertNotSame((int)$first['id'], (int)$second['id']);
    }

    public function testHrFinancialTypesAreAllowedAndCashRouted(): void
    {
        $this->assertContains(FinancialTransactionService::TYPE_HR_BONUS, FinancialTransactionService::ALLOWED_TYPES);
        $this->assertContains(FinancialTransactionService::TYPE_HR_STRIKE_SETTLEMENT, FinancialTransactionService::ALLOWED_TYPES);
        $this->assertSame(WalletConfig::POOL_CASH, WalletConfig::TYPE_TO_POOL[FinancialTransactionService::TYPE_HR_BONUS]);
        $this->assertSame(WalletConfig::POOL_CASH, WalletConfig::TYPE_TO_POOL[FinancialTransactionService::TYPE_HR_STRIKE_SETTLEMENT]);
    }

    public function testAcceptedOfferIsIdempotentAndSettlesStrikeAtomically(): void
    {
        $this->config->save([
            'feature_negotiations' => true,
            'negotiation_offer_weight' => 10,
            'negotiation_raise_max' => 30,
            'settlement_morale_gain' => 12,
        ]);
        $this->seedPlayer(1, 200000.0, 0.0);
        $this->seedActiveTechnicalStrike();
        $service = new EmployeeNegotiationService($this->db);

        $first = $service->submitOffer(1, 1, 30.0, 10000.0, 'same-offer-token', new DateTimeImmutable('2026-07-22 10:00:00'));
        $second = $service->submitOffer(1, 1, 30.0, 10000.0, 'same-offer-token', new DateTimeImmutable('2026-07-22 10:01:00'));

        $this->assertSame('accepted', $first['result']);
        $this->assertTrue($second['idempotent']);
        $this->assertSame($first['round_id'], $second['round_id']);
        $this->assertSame(19500.0, $this->salaryOfTechnicalStaff(1));
        $this->assertSame(190000.0, $this->cashOfPlayer(1));
        $this->assertSame(1, $this->countRows('employee_strike_negotiation_rounds'));
        $this->assertSame(1, $this->countRows('bank_transactions'));
        $this->assertSame('resolved', $this->strikeStatus(1));
        $this->assertSame('normal', $this->relationStatus(1));
    }

    private function createSourceSchema(): void
    {
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, cash REAL NOT NULL DEFAULT 0, bank_balance REAL NOT NULL DEFAULT 0)');
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE board_members (
            id INTEGER PRIMARY KEY, player_id INTEGER NULL, role_id INTEGER NOT NULL,
            skill_negotiation INTEGER NOT NULL DEFAULT 5, skill_organization INTEGER NOT NULL DEFAULT 5,
            salary REAL NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'active\'
        )');
        $this->db->exec('CREATE TABLE technical_staff (
            id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL, salary REAL NOT NULL,
            status TEXT NOT NULL DEFAULT \'active\'
        )');
    }

    private function seedPlayer(int $playerId, float $cash, float $bank): void
    {
        $this->db->prepare('INSERT INTO players (id, cash, bank_balance) VALUES (?, ?, ?)')
            ->execute([$playerId, $cash, $bank]);
    }

    private function seedActiveTechnicalStrike(): void
    {
        $this->db->exec("INSERT INTO technical_staff (id, player_id, salary, status) VALUES (1, 1, 15000, 'busy')");
        $this->db->exec("INSERT INTO employee_state
            (player_id, source_type, source_id, department_code, morale, salary_satisfaction, strike_support, workload, relation_status)
            VALUES (1, 'technical_staff', 1, 'technical', 30, 65, 80, 90, 'on_strike')");
        $this->db->exec("INSERT INTO employee_strikes
            (id, player_id, department_code, status, open_key, support_pct, threat_cycles, started_at)
            VALUES (1, 1, 'technical', 'active', '1:technical', 80, 2, '2026-07-22 09:00:00')");
        $this->db->exec("INSERT INTO employee_strike_members
            (strike_id, player_id, source_type, source_id, support_pct)
            VALUES (1, 1, 'technical_staff', 1, 80)");
    }

    private function salaryOfTechnicalStaff(int $staffId): float
    {
        $stmt = $this->db->prepare('SELECT salary FROM technical_staff WHERE id=?');
        $stmt->execute([$staffId]);
        return (float)$stmt->fetchColumn();
    }

    private function cashOfPlayer(int $playerId): float
    {
        $stmt = $this->db->prepare('SELECT cash FROM players WHERE id=?');
        $stmt->execute([$playerId]);
        return (float)$stmt->fetchColumn();
    }

    private function countRows(string $table): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function strikeStatus(int $strikeId): string
    {
        $stmt = $this->db->prepare('SELECT status FROM employee_strikes WHERE id=?');
        $stmt->execute([$strikeId]);
        return (string)$stmt->fetchColumn();
    }

    private function relationStatus(int $sourceId): string
    {
        $stmt = $this->db->prepare("SELECT relation_status FROM employee_state WHERE source_type='technical_staff' AND source_id=?");
        $stmt->execute([$sourceId]);
        return (string)$stmt->fetchColumn();
    }
}
