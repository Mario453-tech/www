<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeSystemConfigService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeDialogueTemplateService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeNegotiationService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeBonusService.php';

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
        $this->assertSame(1, $first['formula']['participant_count']);
        $this->assertNotSame('', $first['dialogue']['pl']);
        $this->assertNotSame('', $first['dialogue']['en']);
        $this->assertStringNotContainsString('{', $first['dialogue']['pl']);
        $this->assertStringNotContainsString('{', $first['dialogue']['en']);
        $this->assertTrue($second['idempotent']);
        $this->assertSame($first['round_id'], $second['round_id']);
        $this->assertSame(19500.0, $this->salaryOfTechnicalStaff(1));
        $this->assertSame(190000.0, $this->cashOfPlayer(1));
        $this->assertSame(1, $this->countRows('employee_strike_negotiation_rounds'));
        $this->assertSame(1, $this->countRows('bank_transactions'));
        $this->assertSame('resolved', $this->strikeStatus(1));
        $this->assertSame('normal', $this->relationStatus(1));
    }

    public function testHrEffectivenessUsesOnlyNegotiatingPlayersTeam(): void
    {
        $this->config->save(['feature_negotiations' => true]);
        $this->seedPlayer(1, 200000.0, 0.0);
        $this->seedPlayer(2, 200000.0, 0.0);
        $this->seedActiveTechnicalStrike();
        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'hr')");
        $stmt = $this->db->prepare(
            "INSERT INTO board_members
                (id, player_id, role_id, skill_negotiation, skill_organization, salary, status)
             VALUES (?, ?, 1, ?, ?, 10000, 'active')"
        );
        $stmt->execute([1, 1, 1, 1]);
        $stmt->execute([2, 2, 10, 10]);

        $round = (new EmployeeNegotiationService($this->db))->submitOffer(
            1,
            1,
            30.0,
            0.0,
            'player-scoped-hr-team',
            new DateTimeImmutable('2026-07-22 10:00:00')
        );

        $this->assertSame(10.0, (float)$round['formula']['hr_effectiveness']);
    }

    public function testActiveStrikeDashboardIsGroupedAndPlayerScoped(): void
    {
        $this->seedPlayer(1, 200000.0, 0.0);
        $this->seedPlayer(2, 200000.0, 0.0);
        $this->seedActiveTechnicalStrike();
        $this->db->exec("INSERT INTO technical_staff (id, player_id, salary, status) VALUES (2, 2, 18000, 'busy')");
        $this->db->exec("INSERT INTO employee_state
            (player_id, source_type, source_id, department_code, morale, salary_satisfaction, strike_support, workload, relation_status)
            VALUES (2, 'technical_staff', 2, 'technical', 10, 40, 95, 100, 'on_strike')");
        $this->db->exec("INSERT INTO employee_strikes
            (id, player_id, department_code, status, open_key, support_pct, threat_cycles, started_at)
            VALUES (2, 2, 'technical', 'active', '2:technical', 95, 2, '2026-07-22 09:00:00')");
        $this->db->exec("INSERT INTO employee_strike_members
            (strike_id, player_id, source_type, source_id, support_pct)
            VALUES (2, 2, 'technical_staff', 2, 95)");

        $rows = (new EmployeeStrikeService($this->db))->activeForPlayer(1);

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int)$rows[0]['id']);
        $this->assertSame(1, (int)$rows[0]['member_count']);
        $this->assertSame(30.0, (float)$rows[0]['avg_morale']);
    }

    public function testFailedNegotiationCanReopenAfterCooldown(): void
    {
        $this->config->save(['feature_negotiations' => true, 'negotiation_rounds' => 4]);
        $this->seedPlayer(1, 200000.0, 0.0);
        $this->seedActiveTechnicalStrike();
        $this->db->exec("INSERT INTO employee_strike_negotiations
            (id, strike_id, player_id, status, current_round, max_rounds, round_deadline_at)
            VALUES (1, 1, 1, 'failed', 3, 3, '2026-07-21 10:00:00')");

        $result = (new EmployeeNegotiationService($this->db))->openForStrike(
            1,
            1,
            new DateTimeImmutable('2026-07-22 10:00:00')
        );

        $this->assertSame('open', $result['status']);
        $this->assertSame(1, $result['current_round']);
        $this->assertSame(4, $result['max_rounds']);
        $this->assertSame('negotiating', $this->strikeStatus(1));
    }
    public function testTechnicalBonusUsesFinancialServiceAndCanonicalMoraleAtomically(): void
    {
        $this->seedPlayer(1, 20000.0, 0.0);
        $this->db->exec("INSERT INTO technical_staff (id, player_id, salary, status) VALUES (1, 1, 15000, 'active')");
        $service = new EmployeeBonusService($this->db);
        $this->db->exec("INSERT INTO employee_state
            (player_id, source_type, source_id, department_code, morale, salary_satisfaction, strike_support, workload, relation_status)
            VALUES (1, 'technical_staff', 1, 'technical', 50, 90, 20, 20, 'normal')");

        $result = $service->grantTechnicalBonus(1, 1);

        $this->assertTrue($result['success']);
        $this->assertSame(65.0, $result['new_morale']);
        $this->assertSame(5000.0, $this->cashOfPlayer(1));
        $this->assertSame(1, $this->countRows('bank_transactions'));
        $this->assertSame(65.0, $this->moraleOfTechnicalStaff(1));
    }

    public function testTechnicalBonusCannotTargetAnotherPlayersEmployee(): void
    {
        $this->seedPlayer(1, 20000.0, 0.0);
        $this->seedPlayer(2, 20000.0, 0.0);
        $this->db->exec("INSERT INTO technical_staff (id, player_id, salary, status) VALUES (1, 1, 15000, 'active')");
        $service = new EmployeeBonusService($this->db);

        try {
            $service->grantTechnicalBonus(2, 1);
            $this->fail('A player must not grant a bonus to another player employee.');
        } catch (RuntimeException) {
            $this->assertSame(20000.0, $this->cashOfPlayer(1));
            $this->assertSame(20000.0, $this->cashOfPlayer(2));
        }
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
        $this->db->exec('CREATE TABLE hr_specializations (
            id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE,
            base_salary_min REAL NULL, base_salary_max REAL NULL
        )');
        $this->db->exec('CREATE TABLE technical_staff (
            id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL, manager_id INTEGER NULL,
            first_name TEXT NOT NULL DEFAULT \'Test\', last_name TEXT NOT NULL DEFAULT \'Employee\',
            spec_code TEXT NULL, specialization TEXT NULL, spec_name TEXT NULL,
            experience_years INTEGER NOT NULL DEFAULT 0, skill_level INTEGER NOT NULL DEFAULT 5,
            salary REAL NOT NULL, status TEXT NOT NULL DEFAULT \'active\',
            hired_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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

    private function moraleOfTechnicalStaff(int $sourceId): float
    {
        $stmt = $this->db->prepare(
            "SELECT morale FROM employee_state WHERE source_type='technical_staff' AND source_id=?"
        );
        $stmt->execute([$sourceId]);
        return (float)$stmt->fetchColumn();
    }
    private function relationStatus(int $sourceId): string
    {
        $stmt = $this->db->prepare("SELECT relation_status FROM employee_state WHERE source_type='technical_staff' AND source_id=?");
        $stmt->execute([$sourceId]);
        return (string)$stmt->fetchColumn();
    }
}
