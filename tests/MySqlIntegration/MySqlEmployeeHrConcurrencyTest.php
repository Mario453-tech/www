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
        foreach (['feature_negotiations', 'negotiation_rounds'] as $key) {
            $this->originalConfig[$key] = $config->get($key);
        }
        $config->save([
            'feature_negotiations' => true,
            'negotiation_rounds' => 1,
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

            $deadline = microtime(true) + 15.0;
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
        foreach ([
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
        ] as $table) {
            $this->db->prepare("DELETE FROM {$table} WHERE player_id=?")->execute([$playerId]);
        }
        $this->db->prepare('DELETE FROM players WHERE id=?')->execute([$playerId]);
    }

    /** @param array<int,array<string,mixed>> $results */
    private function failureMessage(int $iteration, array $results): string
    {
        return 'Iteration ' . $iteration . ': ' . json_encode($results, JSON_UNESCAPED_SLASHES);
    }
}
