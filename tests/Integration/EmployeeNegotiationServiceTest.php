<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeSystemConfigService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeDialogueTemplateService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeNegotiationService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeBonusService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/EmployeeMoraleSection.php';

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

    public function testRaiseRequestSchemaUpgradeAndConfigDefaults(): void
    {
        $legacy = $this->createSqlitePdo();
        $legacy->exec('CREATE TABLE employee_state (id INTEGER PRIMARY KEY)');
        $legacy->exec('CREATE TABLE employee_raise_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER NOT NULL,
            source_type TEXT NOT NULL, source_id INTEGER NOT NULL, request_no INTEGER NOT NULL DEFAULT 1,
            requested_raise_pct REAL NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'open\'
        )');
        $legacy->exec('CREATE TABLE employee_schema_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL UNIQUE,
            version INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $legacy->exec("INSERT INTO employee_schema_versions (module_key, version) VALUES ('employee_system', 3)");

        EmployeeSystemSchema::ensure($legacy);

        $stateColumns = $legacy->query('PRAGMA table_info(employee_state)')->fetchAll(PDO::FETCH_COLUMN, 1);
        $this->assertContains('loyalty_modifier', $stateColumns);
        $this->assertContains('leave_risk_streak', $stateColumns);
        $this->assertContains('leaving_at', $stateColumns);
        $this->assertContains('inactive_at', $stateColumns);
        $eventColumns = $legacy->query('PRAGMA table_info(employee_events)')->fetchAll(PDO::FETCH_COLUMN, 1);
        $this->assertContains('is_read', $eventColumns);
        $this->assertContains('notified_at', $eventColumns);
        $columns = $legacy->query('PRAGMA table_info(employee_raise_requests)')->fetchAll(PDO::FETCH_COLUMN, 1);
        $this->assertContains('current_salary', $columns);
        $this->assertContains('requested_salary', $columns);
        $this->assertContains('negotiated_salary', $columns);
        $this->assertContains('reason_code', $columns);
        $this->assertContains('postponed_count', $columns);
        $this->assertSame(EmployeeSystemSchema::VERSION, EmployeeSystemSchema::currentVersion($legacy));

        $expected = [
            'raise_accept_morale_gain' => 20.0,
            'raise_negotiated_morale_gain' => 8.0,
            'raise_negotiation_fail_morale_penalty' => 5.0,
            'raise_accept_loyalty_gain' => 5.0,
            'raise_accept_leave_risk_reduction' => 15.0,
            'raise_salary_negotiator_chance_bonus' => 10.0,
            'raise_reject_morale_penalty' => 20.0,
            'raise_reject_support_gain' => 15.0,
            'raise_reject_leave_risk_gain' => 15.0,
            'raise_postpone_morale_penalty' => 5.0,
            'raise_postpone_leave_risk_gain' => 5.0,
            'raise_postpone_hours' => 24,
            'raise_max_postponements' => 1,
        ];
        foreach ($expected as $key => $value) {
            $this->assertSame($value, $this->config->get($key));
        }

        $this->expectException(InvalidArgumentException::class);
        $this->config->save(['raise_max_postponements' => 11]);
    }

    public function testRaiseRequestStoresSalaryAndTreatsPostponedAsActive(): void
    {
        $this->seedPlayer(1, 100000.0, 0.0);
        $this->db->exec("INSERT INTO technical_staff (id, player_id, salary, status) VALUES (1, 1, 15000, 'active')");
        $this->db->exec("INSERT INTO employee_state
            (player_id, source_type, source_id, department_code, morale, salary_satisfaction,
             strike_support, workload, relation_status)
            VALUES (1, 'technical_staff', 1, 'technical', 30, 60, 20, 80, 'raise_requested')");
        $service = new EmployeeStrikeService($this->db);
        $now = new DateTimeImmutable('2026-07-22 10:00:00');

        $first = $service->processEscalations($now);
        $this->db->exec("UPDATE employee_raise_requests SET status='postponed',
            deadline_at='2026-07-23 10:00:00' WHERE player_id=1 AND source_id=1");
        $second = $service->processEscalations($now->modify('+1 hour'));

        $row = $this->db->query(
            'SELECT player_id, current_salary, requested_salary, negotiated_salary,
                    reason_code, postponed_count, status
               FROM employee_raise_requests'
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, $first['raise_requests']);
        $this->assertSame(0, $second['raise_requests']);
        $this->assertSame(1, $this->countRows('employee_raise_requests'));
        $this->assertSame(1, (int)$row['player_id']);
        $this->assertSame(15000.0, (float)$row['current_salary']);
        $this->assertSame(16500.0, (float)$row['requested_salary']);
        $this->assertNull($row['negotiated_salary']);
        $this->assertSame('low_morale', $row['reason_code']);
        $this->assertSame(0, (int)$row['postponed_count']);
        $this->assertSame('postponed', $row['status']);

        $expired = $service->processEscalations($now->modify('+2 days'));
        $this->assertSame(0, $expired['raise_requests']);
        $this->assertSame('expired', (string)$this->db->query(
            'SELECT status FROM employee_raise_requests WHERE player_id=1 AND source_id=1'
        )->fetchColumn());
        $this->assertSame('dispute', $this->relationStatus(1));
        $this->assertSame(1, (int)$this->db->query(
            "SELECT COUNT(*) FROM employee_events WHERE event_key='raise_request_expired'"
        )->fetchColumn());
    }

    public function testInactiveEmployeeDoesNotCreateRaiseRequest(): void
    {
        $this->seedPlayer(1, 100000.0, 0.0);
        $this->db->exec("INSERT INTO technical_staff (id, player_id, salary, status) VALUES (1, 1, 15000, 'fired')");
        $this->db->exec("INSERT INTO employee_state
            (player_id, source_type, source_id, department_code, morale, salary_satisfaction,
             strike_support, workload, relation_status)
            VALUES (1, 'technical_staff', 1, 'technical', 30, 60, 20, 80, 'raise_requested')");

        $result = (new EmployeeStrikeService($this->db))->processEscalations(new DateTimeImmutable('2026-07-22 10:00:00'));

        $this->assertSame(0, $result['raise_requests']);
        $this->assertSame(0, $this->countRows('employee_raise_requests'));
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

    public function testZeroStrikeOfferIsRejectedBeforeRoundInsert(): void
    {
        $this->config->save(['feature_negotiations' => true]);
        $this->seedPlayer(1, 200000.0, 0.0);
        $this->seedActiveTechnicalStrike();

        try {
            (new EmployeeNegotiationService($this->db))->submitOffer(
                1,
                1,
                0.0,
                0.0,
                'zero-offer-token',
                new DateTimeImmutable('2026-07-22 10:00:00')
            );
            $this->fail('Zero raise and zero bonus must not be a valid strike offer.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, $this->countRows('employee_strike_negotiation_rounds'));
            $this->assertSame('active', $this->strikeStatus(1));
        }
    }

    public function testVeryWeakRejectedOfferDoesNotIncreaseStrikeSupport(): void
    {
        $this->config->save(['negotiation_reject_support_gain' => 8]);
        $service = new EmployeeNegotiationService($this->db);
        $method = new ReflectionMethod($service, 'rejectedOfferEffects');

        $effects = $method->invoke($service, 4.0);

        $this->assertSame(0.0, $effects['support_delta']);
        $this->assertLessThan(0.0, $effects['morale_delta']);
    }

    public function testExpiredNegotiationRoundReturnsStrikeToCooldown(): void
    {
        $this->config->save(['negotiation_cooldown_hours' => 6]);
        $this->seedPlayer(1, 200000.0, 0.0);
        $this->seedActiveTechnicalStrike();
        $this->db->exec("UPDATE employee_strikes SET status='negotiating' WHERE id=1");
        $this->db->exec("INSERT INTO employee_strike_negotiations
            (id, strike_id, player_id, status, current_round, max_rounds, round_deadline_at)
            VALUES (1, 1, 1, 'open', 1, 3, '2026-07-22 09:00:00')");

        (new EmployeeStrikeService($this->db))->processEscalations(new DateTimeImmutable('2026-07-22 10:00:00'));

        $this->assertSame('expired', (string)$this->db->query('SELECT status FROM employee_strike_negotiations WHERE id=1')->fetchColumn());
        $this->assertSame('active', $this->strikeStatus(1));
        $this->assertSame('2026-07-22 16:00:00', (string)$this->db->query('SELECT negotiation_cooldown_until FROM employee_strikes WHERE id=1')->fetchColumn());
    }

    public function testSubmittingAfterDeadlineCommitsExpiryBeforeReturningError(): void
    {
        $this->config->save(['feature_negotiations' => true, 'negotiation_cooldown_hours' => 6]);
        $this->seedPlayer(1, 200000.0, 0.0);
        $this->seedActiveTechnicalStrike();
        $this->db->exec("UPDATE employee_strikes SET status='negotiating' WHERE id=1");
        $this->db->exec("INSERT INTO employee_strike_negotiations
            (id, strike_id, player_id, status, current_round, max_rounds, round_deadline_at)
            VALUES (1, 1, 1, 'open', 1, 3, '2026-07-22 09:00:00')");

        try {
            (new EmployeeNegotiationService($this->db))->submitOffer(
                1,
                1,
                10.0,
                0.0,
                'expired-round',
                new DateTimeImmutable('2026-07-22 10:00:00')
            );
            $this->fail('An expired negotiation must reject the offer.');
        } catch (RuntimeException) {
            $this->assertSame(
                'expired',
                (string)$this->db->query('SELECT status FROM employee_strike_negotiations WHERE id=1')->fetchColumn()
            );
            $this->assertSame('active', $this->strikeStatus(1));
            $this->assertSame(
                '2026-07-22 16:00:00',
                (string)$this->db->query('SELECT negotiation_cooldown_until FROM employee_strikes WHERE id=1')->fetchColumn()
            );
        }
    }

    public function testHighLeaveRiskNeedsThreeCyclesAndNoticePeriod(): void
    {
        $this->seedPlayer(1, 200000.0, 0.0);
        $this->db->exec("INSERT INTO technical_staff (id, player_id, salary, status)
            VALUES (1, 1, 15000, 'active')");
        $this->db->exec("INSERT INTO employee_state
            (id, player_id, source_type, source_id, department_code, morale, leave_risk,
             leave_risk_streak, relation_status, last_morale_cycle_id)
            VALUES (1, 1, 'technical_staff', 1, 'technical', 20, 95, 0, 'normal', 101)");
        $service = new EmployeeDepartureService($this->db);
        $now = new DateTimeImmutable('2026-07-22 10:00:00');

        $this->assertSame(0, $service->processCycle(101, $now));
        $this->db->exec('UPDATE employee_state SET last_morale_cycle_id=102');
        $this->assertSame(0, $service->processCycle(102, $now->modify('+1 day')));
        $this->db->exec('UPDATE employee_state SET last_morale_cycle_id=103');
        $this->assertSame(1, $service->processCycle(103, $now->modify('+2 days')));
        $this->assertSame('leaving', $this->relationStatus(1));
        $this->assertSame(0, $service->processDue($now->modify('+3 days')));
        $this->assertSame(1, $service->processDue($now->modify('+5 days')));
        $this->assertSame('inactive', $this->relationStatus(1));
        $this->assertSame(
            'fired',
            (string)$this->db->query('SELECT status FROM technical_staff WHERE id=1 AND player_id=1')->fetchColumn()
        );
        $this->assertSame(1, (int)$this->db->query(
            "SELECT COUNT(*) FROM employee_events WHERE event_key='employee_departed'"
        )->fetchColumn());
    }

    public function testMoraleCycleProcessesOnlyConfiguredBatchBeforeEscalation(): void
    {
        $this->seedPlayer(1, 100000.0, 0.0);
        $this->db->exec("INSERT INTO technical_staff (id, player_id, salary, status)
            VALUES (1, 1, 15000, 'active'), (2, 1, 16000, 'active')");

        $first = new EmployeeMoraleSection(
            $this->db,
            new DateTimeImmutable('2026-07-22 10:00:00'),
            1,
            1
        );
        $first->run();

        $this->assertSame(1, $first->processed);
        $this->assertSame(1, $first->remaining);
        $this->assertFalse($first->cycleCompleted);

        $second = new EmployeeMoraleSection(
            $this->db,
            new DateTimeImmutable('2026-07-22 11:00:00'),
            2,
            1
        );
        $second->run();

        $this->assertSame($first->cycleId, $second->cycleId);
        $this->assertSame(1, $second->processed);
        $this->assertSame(0, $second->remaining);
        $this->assertTrue($second->cycleCompleted);
        $this->assertSame(2, $this->countRows('employee_state'));
    }

    public function testStrikeEffectsAreBatchLoadedOnlyForActiveAndNegotiatingStatuses(): void
    {
        $this->config->save(['feature_strike_effects' => true]);
        $this->seedPlayer(1, 100000.0, 0.0);
        $this->seedPlayer(2, 100000.0, 0.0);
        $this->db->exec("INSERT INTO employee_strikes
            (id, player_id, department_code, status, open_key, support_pct)
            VALUES
            (1, 1, 'logistics', 'active', '1:logistics', 80),
            (2, 1, 'hr', 'negotiating', '1:hr', 75),
            (3, 2, 'technical', 'threat', '2:technical', 60),
            (4, 2, 'legal', 'active', '2:legal', 85)");
        $service = new StrikeEffectService($this->db, $this->config);

        $effects = $service->forPlayers([1, 2]);

        $this->assertSame(0.70, $effects[1]['logistics']['capacity_cap']);
        $this->assertSame(0.80, $effects[1]['hr']['negotiation_effectiveness_mult']);
        $this->assertArrayNotHasKey('roles_active', $effects[1]['logistics']);
        $this->assertArrayNotHasKey('technical', $effects[2]);
        $this->assertSame(0.85, $effects[2]['legal']['effectiveness_mult']);
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
