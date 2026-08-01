<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

final class MySqlEmployeeHrConcurrencyTest extends MySqlIntegrationTestCase
{
    private const REPETITIONS = 20;

    /** @var list<int> */
    private array $playerIds = [];
    /** @var array<string,float|int|bool> */
    private array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        EmployeeSystemBootstrap::ensure($this->db);
        $config = new EmployeeSystemConfigService($this->db);
        foreach (['feature_negotiations', 'negotiation_rounds', 'negotiation_offer_weight'] as $key) {
            $this->originalConfig[$key] = $config->get($key);
        }
        $config->save([
            'feature_negotiations' => true,
            'negotiation_rounds' => 1,
            'negotiation_offer_weight' => 10.0,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->playerIds) as $playerId) {
            $this->cleanupPlayer($playerId);
        }
        if (isset($this->db) && $this->originalConfig !== []) {
            (new EmployeeSystemConfigService($this->db))->save($this->originalConfig);
        }
        parent::tearDown();
    }

    public function testIdenticalOfferTokensAreIdempotentUnderConcurrency(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedStrikeScenario($iteration);
            $payload = $this->offerPayload($seed, 'same-token-' . $iteration);
            $results = $this->runPair('offer', $payload, 'offer', $payload);

            $this->assertTrue($results[0]['ok'], $this->failureMessage($iteration, $results));
            $this->assertTrue($results[1]['ok'], $this->failureMessage($iteration, $results));
            $this->assertSame(1, $this->roundCount((int)$seed['player_id'], (int)$seed['strike_id']));
            $this->assertSame(
                $results[0]['result']['round_id'] ?? null,
                $results[1]['result']['round_id'] ?? null,
                'Iteration ' . $iteration
            );
        }
    }

    public function testDifferentTokensCannotBothMutateTheSameExpectedRound(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedStrikeScenario($iteration);
            $first = $this->offerPayload($seed, 'round-a-' . $iteration);
            $second = $this->offerPayload($seed, 'round-b-' . $iteration);
            $results = $this->runPair('offer', $first, 'offer', $second);

            $successes = count(array_filter($results, static fn(array $row): bool => $row['ok'] === true));
            $this->assertSame(1, $successes, $this->failureMessage($iteration, $results));
            $this->assertSame(1, $this->roundCount((int)$seed['player_id'], (int)$seed['strike_id']));
        }
    }

    public function testOfferAndNegotiationDeadlineRemainAtomic(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedStrikeScenario($iteration);
            $deadline = new DateTimeImmutable('now');
            $this->db->prepare(
                'UPDATE employee_strike_negotiations SET round_deadline_at=? WHERE id=? AND player_id=?'
            )->execute([
                $deadline->format('Y-m-d H:i:s'),
                $seed['negotiation_id'],
                $seed['player_id'],
            ]);
            $offer = $this->offerPayload($seed, 'deadline-offer-' . $iteration);
            $offer['now'] = $deadline->format('Y-m-d H:i:s');
            $expiry = [
                'player_id' => $seed['player_id'],
                'strike_id' => $seed['strike_id'],
                'negotiation_id' => $seed['negotiation_id'],
                'now' => $deadline->modify('+1 second')->format('Y-m-d H:i:s'),
            ];
            $results = $this->runPair('offer', $offer, 'negotiation_deadline', $expiry);

            $status = $this->negotiationStatus((int)$seed['player_id'], (int)$seed['negotiation_id']);
            $rounds = $this->roundCount((int)$seed['player_id'], (int)$seed['strike_id']);
            $this->assertContains($status, ['accepted', 'failed', 'expired'], $this->failureMessage($iteration, $results));
            $this->assertContains($rounds, [0, 1], 'Iteration ' . $iteration);
            $this->assertFalse($status === 'expired' && $rounds !== 0, 'Iteration ' . $iteration);
            $this->assertFalse($status !== 'expired' && $rounds !== 1, 'Iteration ' . $iteration);
        }
    }

    public function testRaiseDecisionAndDeadlineRemainAtomic(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedRaiseScenario($iteration);
            $deadline = new DateTimeImmutable('now');
            $this->db->prepare(
                'UPDATE employee_raise_requests SET deadline_at=? WHERE id=? AND player_id=?'
            )->execute([$deadline->format('Y-m-d H:i:s'), $seed['request_id'], $seed['player_id']]);
            $decision = [
                'player_id' => $seed['player_id'],
                'request_id' => $seed['request_id'],
                'token' => 'raise-decision-' . $iteration,
            ];
            $expiry = [
                'player_id' => $seed['player_id'],
                'request_id' => $seed['request_id'],
                'now' => $deadline->modify('+1 second')->format('Y-m-d H:i:s'),
            ];
            $results = $this->runPair('raise_accept', $decision, 'raise_deadline', $expiry);

            $row = $this->raiseState((int)$seed['player_id'], (int)$seed['request_id'], (int)$seed['staff_id']);
            $this->assertContains($row['status'], ['accepted', 'expired'], $this->failureMessage($iteration, $results));
            $this->assertSame($row['status'] === 'accepted' ? 11000.0 : 10000.0, $row['salary'], 'Iteration ' . $iteration);
            $this->assertSame(1, $row['terminal_events'], 'Iteration ' . $iteration);
        }
    }

    public function testTwoConcurrentTicksRespectTheGlobalLock(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $results = $this->runPair('tick_lock', [], 'tick_lock', []);
            $acquired = count(array_filter(
                $results,
                static fn(array $row): bool => !empty($row['result']['acquired'])
            ));
            $busy = count(array_filter(
                $results,
                static fn(array $row): bool => !empty($row['result']['busy'])
            ));
            $this->assertSame(1, $acquired, $this->failureMessage($iteration, $results));
            $this->assertSame(1, $busy, $this->failureMessage($iteration, $results));
        }
    }

    public function testBonusRetryChargesOnce(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedEmployee($iteration);
            $before = $this->liquidFunds((int)$seed['player_id']);
            $payload = [
                'player_id'=>$seed['player_id'],
                'staff_id'=>$seed['staff_id'],
                'token'=>'bonus-retry-token-' . $iteration,
            ];
            $results = $this->runPair('bonus', $payload, 'bonus', $payload);

            $this->assertTrue((bool)($results[0]['result']['success'] ?? false), $this->failureMessage($iteration, $results));
            $this->assertTrue((bool)($results[1]['result']['success'] ?? false), $this->failureMessage($iteration, $results));
            $this->assertSame(15000.0, $before - $this->liquidFunds((int)$seed['player_id']), 'Iteration ' . $iteration);
            $this->assertSame(1, $this->receiptCount((int)$seed['player_id'], 'grant_bonus'));
        }
    }

    public function testContractRenewalRetryExtendsOnce(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedEmployee($iteration);
            $contract = $this->seedBoardContract((int)$seed['player_id'], $iteration);
            $payload = [
                'player_id'=>$seed['player_id'],
                'member_id'=>$contract['member_id'],
                'contract_type'=>'1y',
                'token'=>'renew-retry-token-' . $iteration,
            ];
            $results = $this->runPair('renew_contract', $payload, 'renew_contract', $payload);

            $this->assertTrue((bool)($results[0]['result']['success'] ?? false), $this->failureMessage($iteration, $results));
            $this->assertTrue((bool)($results[1]['result']['success'] ?? false), $this->failureMessage($iteration, $results));
            $this->assertSame('2027-12-31', $this->contractEnd((int)$contract['contract_id']));
            $this->assertSame(1, $this->receiptCount((int)$seed['player_id'], 'renew_contract'));
        }
    }

    public function testConcurrentDirectorHiringFillsRoleOnce(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedEmployee($iteration);
            $candidates = $this->seedDirectorCandidates((int)$seed['player_id'], $iteration);
            $left = ['player_id'=>$seed['player_id'], 'candidate_id'=>$candidates['first']];
            $right = ['player_id'=>$seed['player_id'], 'candidate_id'=>$candidates['second']];
            $results = $this->runPair('hire_candidate', $left, 'hire_candidate', $right);

            $successes = count(array_filter(
                $results,
                static fn(array $row): bool => (bool)($row['result']['success'] ?? false)
            ));
            $this->assertSame(1, $successes, $this->failureMessage($iteration, $results));
            $this->assertSame(1, $this->activeDirectorCount((int)$seed['player_id'], $candidates['role_id']));
        }
    }

    public function testConcurrentRecruitmentStartCreatesOneRequest(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedEmployee($iteration);
            $roleId = $this->roleId('legal');
            $payload = ['player_id'=>$seed['player_id'], 'role_id'=>$roleId];
            $results = $this->runPair('start_recruitment', $payload, 'start_recruitment', $payload);

            $successes = count(array_filter(
                $results,
                static fn(array $row): bool => (bool)($row['result']['success'] ?? false)
            ));
            $this->assertSame(1, $successes, $this->failureMessage($iteration, $results));
            $this->assertSame(1, $this->activeRecruitmentCount((int)$seed['player_id'], $roleId));
        }
    }

    public function testDepartureAndSettlementRemainConsistent(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedStrikeScenario($iteration);
            $cycleId = (int)$seed['player_id'];
            $this->db->prepare(
                'UPDATE employee_state
                    SET leave_risk=95, leave_risk_streak=2, last_morale_cycle_id=?
                  WHERE player_id=? AND source_type=\'technical_staff\' AND source_id=?'
            )->execute([$cycleId, $seed['player_id'], $seed['staff_id']]);
            $departure = [
                'cycle_id' => $cycleId,
                'now' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ];
            $offer = $this->offerPayload($seed, 'departure-offer-' . $iteration);
            $offer['raise_pct'] = 30.0;
            $offer['bonus_per_member'] = 100000.0;

            $results = $this->runPair('departure_cycle', $departure, 'offer', $offer);

            $this->assertSame('leaving', $this->relationStatus($seed), $this->failureMessage($iteration, $results));
            $this->assertSame('resolved', $this->strikeStatusForPlayer($seed), $this->failureMessage($iteration, $results));
            $this->assertSame(1, $this->leavingEventCount($seed), $this->failureMessage($iteration, $results));
            $this->assertSame(1, $this->closedStrikeMemberCount($seed), $this->failureMessage($iteration, $results));
        }
    }

    public function testBonusAndSettlementDoNotDoubleCharge(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedStrikeScenario($iteration);
            $before = $this->liquidFunds((int)$seed['player_id']);
            $bonus = [
                'player_id' => $seed['player_id'],
                'staff_id' => $seed['staff_id'],
                'token' => 'settlement-bonus-' . $iteration,
            ];
            $offer = $this->offerPayload($seed, 'bonus-offer-' . $iteration);
            $offer['raise_pct'] = 30.0;
            $offer['bonus_per_member'] = 100000.0;

            $results = $this->runPair('bonus', $bonus, 'offer', $offer);

            $this->assertTrue($results[1]['ok'], $this->failureMessage($iteration, $results));
            $this->assertSame('accepted', $results[1]['result']['result'] ?? null);
            $bonusReceipts = $this->receiptCount((int)$seed['player_id'], 'grant_bonus');
            $this->assertContains($bonusReceipts, [0, 1], $this->failureMessage($iteration, $results));
            $this->assertSame(
                100000.0 + 15000.0 * $bonusReceipts,
                $before - $this->liquidFunds((int)$seed['player_id']),
                'Iteration ' . $iteration
            );
        }
    }

    public function testConcurrentDepartureCyclesStartNoticeOnce(): void
    {
        for ($iteration = 0; $iteration < self::REPETITIONS; $iteration++) {
            $seed = $this->seedEmployee($iteration);
            $cycleId = (int)$seed['player_id'];
            $this->db->prepare(
                'UPDATE employee_state
                    SET leave_risk=95, leave_risk_streak=2, last_morale_cycle_id=?
                  WHERE player_id=? AND source_type=\'technical_staff\' AND source_id=?'
            )->execute([$cycleId, $seed['player_id'], $seed['staff_id']]);
            $payload = [
                'cycle_id' => $cycleId,
                'now' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ];

            $results = $this->runPair('departure_cycle', $payload, 'departure_cycle', $payload);

            $started = array_sum(array_map(
                static fn(array $row): int => (int)($row['result']['started'] ?? 0),
                $results
            ));
            $this->assertSame(1, $started, $this->failureMessage($iteration, $results));
            $this->assertSame('leaving', $this->relationStatus($seed), $this->failureMessage($iteration, $results));
            $this->assertSame(1, $this->leavingEventCount($seed), $this->failureMessage($iteration, $results));
        }
    }

    /** @return array<string,int> */
    private function seedStrikeScenario(int $iteration): array
    {
        $seed = $this->seedEmployee($iteration);
        $playerId = $seed['player_id'];
        $staffId = $seed['staff_id'];
        $this->db->prepare(
            "UPDATE employee_state
                SET department_code='logistics', morale=10, strike_support=90,
                    relation_status='on_strike', workload=90
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        )->execute([$playerId, $staffId]);
        $this->db->prepare(
            "INSERT INTO employee_strikes
                (player_id, department_code, status, open_key, support_pct, started_at)
             VALUES (?, 'logistics', 'active', ?, 90, NOW())"
        )->execute([$playerId, $playerId . ':logistics']);
        $strikeId = (int)$this->db->lastInsertId();
        $this->db->prepare(
            'INSERT INTO employee_strike_members
                (strike_id, player_id, source_type, source_id, support_pct)
             VALUES (?, ?, \'technical_staff\', ?, 90)'
        )->execute([$strikeId, $playerId, $staffId]);
        (new EmployeeNegotiationService($this->db))->openForStrike(
            $playerId,
            $strikeId,
            new DateTimeImmutable('now')
        );
        $stmt = $this->db->prepare(
            'SELECT id FROM employee_strike_negotiations WHERE player_id=? AND strike_id=?'
        );
        $stmt->execute([$playerId, $strikeId]);

        return $seed + [
            'strike_id' => $strikeId,
            'negotiation_id' => (int)$stmt->fetchColumn(),
        ];
    }

    /** @return array<string,int> */
    private function seedRaiseScenario(int $iteration): array
    {
        $seed = $this->seedEmployee($iteration);
        $this->db->prepare(
            "INSERT INTO employee_raise_requests
                (player_id, source_type, source_id, request_no, current_salary,
                 requested_salary, requested_raise_pct, reason_code, status, deadline_at)
             VALUES (?, 'technical_staff', ?, 1, 10000, 11000, 10, 'low_morale', 'open', NOW())"
        )->execute([$seed['player_id'], $seed['staff_id']]);

        return $seed + ['request_id' => (int)$this->db->lastInsertId()];
    }

    /** @return array{player_id:int,staff_id:int} */
    private function seedEmployee(int $iteration): array
    {
        $playerId = $this->seed - 100000000 + ($iteration * 10);
        $staffId = $playerId + 1;
        $this->playerIds[] = $playerId;
        $username = 'phpunit_hr_concurrency_' . $playerId;
        $this->db->prepare(
            "INSERT INTO players
                (id, username, email, password_hash, cash, bank_balance, status, created_at, last_tick_at)
             VALUES (?, ?, ?, ?, 50000000, 50000000, 'active', NOW(), NOW())"
        )->execute([
            $playerId,
            $username,
            $username . '@example.test',
            password_hash('secret', PASSWORD_BCRYPT),
        ]);
        $this->seedTechnicalStaff(
            $playerId,
            $staffId,
            'hub_operator',
            'Hub Operator',
            8,
            10000
        );
        $stateService = new EmployeeStateService($this->db, new EmployeeRepository($this->db));
        $stateService->ensureState(new EmployeeRef('technical_staff', $staffId, $playerId));

        return ['player_id' => $playerId, 'staff_id' => $staffId];
    }

    /** @param array<string,int> $seed @return array<string,mixed> */
    private function offerPayload(array $seed, string $token): array
    {
        return [
            'player_id' => $seed['player_id'],
            'strike_id' => $seed['strike_id'],
            'raise_pct' => 0.0,
            'bonus_per_member' => 1.0,
            'token' => $token,
            'now' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'expected_round' => 1,
        ];
    }

    /** @return array{member_id:int,contract_id:int} */
    private function seedBoardContract(int $playerId, int $iteration): array
    {
        $roleId = $this->roleId('hr');
        $memberId = $playerId + 2 + $iteration;
        $this->db->prepare(
            "INSERT INTO board_members
                (id, player_id, member_type, role_id, first_name, last_name, gender, birth_date,
                 nationality, experience_years, skill_organization, skill_negotiation, skill_analysis,
                 skill_stress, skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition,
                 salary, hired_at, status)
             VALUES (?, ?, 'staff', ?, 'Contract', 'Worker', 'F', '1990-01-01', 'PL',
                     8, 7, 7, 7, 7, 7, 7, 3, 6, 12000, NOW(), 'active')"
        )->execute([$memberId, $playerId, $roleId]);
        $this->db->prepare(
            "INSERT INTO employee_contracts
                (member_id, contract_start, contract_end, salary, contract_type, status)
             VALUES (?, '2026-01-01', '2026-12-31', 12000, '1y', 'active')"
        )->execute([$memberId]);
        $contractId = (int)$this->db->lastInsertId();
        $stateService = new EmployeeStateService($this->db, new EmployeeRepository($this->db));
        $stateService->ensureState(new EmployeeRef('board_member', $memberId, $playerId));
        return ['member_id'=>$memberId, 'contract_id'=>$contractId];
    }

    /** @return array{first:int,second:int,role_id:int} */
    private function seedDirectorCandidates(int $playerId, int $iteration): array
    {
        $roleId = $this->roleId('legal');
        $first = $playerId + 3 + $iteration;
        $second = $playerId + 4 + $iteration;
        $stmt = $this->db->prepare(
            "INSERT INTO candidates
                (id, player_id, director_status, role_id, first_name, last_name, gender, birth_date,
                 nationality, region_code, experience_years, skill_organization, skill_negotiation,
                 skill_analysis, skill_stress, skill_ethics, trait_loyalty, trait_corruption_risk,
                 trait_ambition, expected_salary, expires_at)
             VALUES (?, ?, 'pending', ?, ?, 'Candidate', 'F', '1990-01-01', 'PL', 'PL',
                     8, 7, 7, 7, 7, 7, 7, 3, 6, 12000, DATE_ADD(NOW(), INTERVAL 1 DAY))"
        );
        $stmt->execute([$first, $playerId, $roleId, 'First']);
        $stmt->execute([$second, $playerId, $roleId, 'Second']);
        return ['first'=>$first, 'second'=>$second, 'role_id'=>$roleId];
    }

    private function roleId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM board_roles WHERE code=? LIMIT 1');
        $stmt->execute([$code]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        $this->assertGreaterThan(0, $id);
        return $id;
    }

    private function liquidFunds(int $playerId): float
    {
        $stmt = $this->db->prepare('SELECT cash + bank_balance FROM players WHERE id=?');
        $stmt->execute([$playerId]);
        return (float)$stmt->fetchColumn();
    }

    private function receiptCount(int $playerId, string $action): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM employee_action_receipts WHERE player_id=? AND action_key=?'
        );
        $stmt->execute([$playerId, $action]);
        return (int)$stmt->fetchColumn();
    }

    private function contractEnd(int $contractId): string
    {
        $stmt = $this->db->prepare('SELECT contract_end FROM employee_contracts WHERE id=?');
        $stmt->execute([$contractId]);
        return (string)$stmt->fetchColumn();
    }

    private function activeDirectorCount(int $playerId, int $roleId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM board_members
              WHERE player_id=? AND role_id=? AND member_type='director' AND status='active'"
        );
        $stmt->execute([$playerId, $roleId]);
        return (int)$stmt->fetchColumn();
    }

    private function activeRecruitmentCount(int $playerId, int $roleId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM recruitment_requests
              WHERE player_id=? AND role_id=? AND status IN ('pending','ready')"
        );
        $stmt->execute([$playerId, $roleId]);
        return (int)$stmt->fetchColumn();
    }

    /** @param array<string,int> $seed */
    private function relationStatus(array $seed): string
    {
        $stmt = $this->db->prepare(
            "SELECT relation_status FROM employee_state
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        );
        $stmt->execute([$seed['player_id'], $seed['staff_id']]);
        return (string)$stmt->fetchColumn();
    }

    /** @param array<string,int> $seed */
    private function strikeStatusForPlayer(array $seed): string
    {
        $stmt = $this->db->prepare('SELECT status FROM employee_strikes WHERE id=? AND player_id=?');
        $stmt->execute([$seed['strike_id'], $seed['player_id']]);
        return (string)$stmt->fetchColumn();
    }

    /** @param array<string,int> $seed */
    private function leavingEventCount(array $seed): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM employee_events
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?
                AND event_key='employee_leaving'"
        );
        $stmt->execute([$seed['player_id'], $seed['staff_id']]);
        return (int)$stmt->fetchColumn();
    }

    /** @param array<string,int> $seed */
    private function closedStrikeMemberCount(array $seed): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM employee_strike_members
              WHERE strike_id=? AND player_id=? AND source_type=\'technical_staff\'
                AND source_id=? AND left_at IS NOT NULL'
        );
        $stmt->execute([$seed['strike_id'], $seed['player_id'], $seed['staff_id']]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $leftPayload
     * @param array<string,mixed> $rightPayload
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function runPair(
        string $leftAction,
        array $leftPayload,
        string $rightAction,
        array $rightPayload
    ): array {
        $root = dirname(__DIR__, 2);
        $workerFile = $root . '/tests/fixtures/employee_hr_concurrent_worker.php';
        $gateFile = tempnam(sys_get_temp_dir(), 'employee_hr_gate_');
        $this->assertIsString($gateFile);
        @unlink($gateFile);
        $workers = [];

        try {
            foreach ([[$leftAction, $leftPayload], [$rightAction, $rightPayload]] as [$action, $payload]) {
                $readyFile = tempnam(sys_get_temp_dir(), 'employee_hr_ready_');
                $this->assertIsString($readyFile);
                @unlink($readyFile);
                $pipes = [];
                $process = proc_open(
                    [
                        PHP_BINARY,
                        $workerFile,
                        $action,
                        base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
                        $readyFile,
                        $gateFile,
                    ],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    $root
                );
                $this->assertIsResource($process);
                $workers[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $readyFile];
            }

            $deadline = microtime(true) + 30.0;
            do {
                $allReady = count(array_filter(
                    $workers,
                    static fn(array $worker): bool => is_file($worker['ready'])
                )) === 2;
                if ($allReady) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($allReady, 'Concurrent HR workers did not become ready.');
            file_put_contents($gateFile, 'go');

            $results = [];
            foreach ($workers as $worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $exit = proc_close($worker['process']);
                $decoded = json_decode(trim((string)$stdout), true);
                $this->assertIsArray(
                    $decoded,
                    'stdout=' . $stdout . ' stderr=' . $stderr . ' exit=' . $exit
                );
                $results[] = $decoded;
            }

            /** @var array{0:array<string,mixed>,1:array<string,mixed>} $results */
            return $results;
        } finally {
            foreach ($workers as $worker) {
                @unlink($worker['ready']);
            }
            @unlink($gateFile);
        }
    }

    private function roundCount(int $playerId, int $strikeId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM employee_strike_negotiation_rounds WHERE player_id=? AND strike_id=?'
        );
        $stmt->execute([$playerId, $strikeId]);
        return (int)$stmt->fetchColumn();
    }

    private function negotiationStatus(int $playerId, int $negotiationId): string
    {
        $stmt = $this->db->prepare(
            'SELECT status FROM employee_strike_negotiations WHERE id=? AND player_id=?'
        );
        $stmt->execute([$negotiationId, $playerId]);
        return (string)$stmt->fetchColumn();
    }

    /** @return array{status:string,salary:float,terminal_events:int} */
    private function raiseState(int $playerId, int $requestId, int $staffId): array
    {
        $stmt = $this->db->prepare(
            'SELECT rr.status, ts.salary
               FROM employee_raise_requests rr
               JOIN technical_staff ts ON ts.id=rr.source_id AND ts.player_id=rr.player_id
              WHERE rr.id=? AND rr.player_id=? AND ts.id=?'
        );
        $stmt->execute([$requestId, $playerId, $staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $events = $this->db->prepare(
            "SELECT COUNT(*) FROM employee_events
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?
                AND event_key IN ('raise_request_accept_full','raise_request_expired')"
        );
        $events->execute([$playerId, $staffId]);

        return [
            'status' => (string)$row['status'],
            'salary' => (float)$row['salary'],
            'terminal_events' => (int)$events->fetchColumn(),
        ];
    }

    private function cleanupPlayer(int $playerId): void
    {
        $this->db->prepare(
            'DELETE FROM employment_history WHERE member_id IN (SELECT id FROM board_members WHERE player_id=?)'
        )->execute([$playerId]);
        $this->db->prepare(
            'DELETE FROM employee_contracts WHERE member_id IN (SELECT id FROM board_members WHERE player_id=?)'
        )->execute([$playerId]);
        foreach ([
            'employee_action_receipts',
            'employee_strike_negotiation_rounds',
            'employee_strike_negotiations',
            'employee_strike_members',
            'employee_strikes',
            'employee_raise_requests',
            'employee_events',
            'employee_assignments',
            'employee_state',
            'employee_source_links',
            'technical_staff',
            'candidates',
            'recruitment_requests',
            'board_members',
        ] as $table) {
            $this->db->prepare("DELETE FROM {$table} WHERE player_id=?")->execute([$playerId]);
        }
        $this->db->prepare('DELETE FROM bank_transactions WHERE from_player_id=? OR to_player_id=?')
            ->execute([$playerId, $playerId]);
        $this->db->prepare('DELETE FROM players WHERE id=?')->execute([$playerId]);
    }

    /** @param array<int,array<string,mixed>> $results */
    private function failureMessage(int $iteration, array $results): string
    {
        return 'Iteration ' . $iteration . ': ' . json_encode($results, JSON_UNESCAPED_SLASHES);
    }
}
