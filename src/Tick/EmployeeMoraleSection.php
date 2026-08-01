<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRepository.php';
require_once dirname(__DIR__) . '/Employee/EmployeeStateService.php';
require_once dirname(__DIR__) . '/HR/MoraleServiceV2.php';
require_once dirname(__DIR__) . '/HR/EmployeeStrikeService.php';
require_once dirname(__DIR__) . '/HR/EmployeeDepartureService.php';
require_once dirname(__DIR__) . '/HR/EmployeeRelationLifecycleService.php';
require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';
require_once dirname(__DIR__) . '/CandidateGenerator.php';

final class EmployeeMoraleSection
{
    public int $cycleId = 0;
    public int $examined = 0;
    public int $processed = 0;
    public int $failed = 0;
    public int $alreadyProcessed = 0;
    public int $remaining = 0;
    public int $moraleChanged = 0;
    public int $raiseRequests = 0;
    public int $threatsStarted = 0;
    public int $strikesStarted = 0;
    public int $departures = 0;
    public int $raiseRequestsExpired = 0;
    public int $negotiationsExpired = 0;
    public int $departuresCompleted = 0;
    public int $candidatesExpired = 0;
    public int $deadlineErrors = 0;
    public int $cleanupErrors = 0;
    public bool $cycleCompleted = false;
    private bool $escalationCompleted = false;
    private bool $unitOwnTransaction = false;

    public function __construct(
        private readonly PDO $db,
        private readonly DateTimeInterface $now,
        private readonly int $runSequence,
        private readonly int $limit
    ) {
        EmployeeSystemBootstrap::ensure($db);
    }

    public function run(): void
    {
        $strikeService = new EmployeeStrikeService($this->db);
        $remainingBudget = max(1, $this->limit);
        $this->processDeadlineBudget($strikeService, $remainingBudget);
        $this->cleanupExpiredCandidates($remainingBudget);

        $this->cycleId = $this->openOrCreateCycle();
        $repository = new EmployeeRepository($this->db);
        $stateService = new EmployeeStateService($this->db, $repository);
        $states = $remainingBudget > 0 ? $this->loadStateBatch($remainingBudget) : [];
        $missingSlots = max(0, $remainingBudget - count($states));
        if ($missingSlots > 0) {
            $missingRefs = $repository->listMissingStateRefs($missingSlots);
            $missingEmployees = $repository->listByRefs($missingRefs);
            $entries = [];
            foreach ($missingEmployees as $employee) {
                $entries[] = [
                    'ref' => new EmployeeRef(
                        (string)$employee['source_type'],
                        (int)$employee['source_id'],
                        (int)$employee['player_id']
                    ),
                    'employee' => $employee,
                ];
            }
            $stateService->ensureStatesForRecords($entries);
            $states = $this->loadStateBatch($remainingBudget);
        }

        $refs = [];
        foreach ($states as $state) {
            $refs[] = new EmployeeRef(
                (string)$state['source_type'],
                (int)$state['source_id'],
                (int)$state['player_id']
            );
        }
        $employees = $repository->listByRefs($refs);
        $employeeMap = [];
        foreach ($employees as $employee) {
            $ref = new EmployeeRef((string)$employee['source_type'], (int)$employee['source_id'], (int)$employee['player_id']);
            $employeeMap[$ref->playerId . ':' . $ref->key()] = $employee;
        }

        $this->examined = count($states);
        $allocations = $this->loadAllocations($refs);
        $trainingCounts = $this->loadTrainingCounts($refs);
        $financialStates = $this->loadFinancialStates($refs);
        $morale = new MoraleService($this->db);
        $morale->prefetchStrikeEffects(array_values(array_unique(array_map(
            static fn(EmployeeRef $ref): int => $ref->playerId,
            $refs
        ))));

        foreach ($states as $state) {
            $remainingBudget = max(0, $remainingBudget - 1);
            $ref = new EmployeeRef((string)$state['source_type'], (int)$state['source_id'], (int)$state['player_id']);
            $key = $ref->playerId . ':' . $ref->key();
            $employee = $employeeMap[$key] ?? null;
            try {
                $this->beginEmployeeUnit();
                if (!is_array($employee)) {
                    $updated = $this->markMissingSource($ref, (int)$state['id']);
                } else {
                    $currentState = $this->lockState((int)$state['id'], $ref);
                    if ($currentState === null
                        || (int)($currentState['last_morale_cycle_id'] ?? 0) === $this->cycleId) {
                        $updated = false;
                        $this->commitEmployeeUnit();
                        $this->alreadyProcessed++;
                        continue;
                    }
                    $workload = $morale->calculateWorkload($employee, (float)($allocations[$key] ?? 0));
                    $metrics = $morale->calculateMetrics(
                        $employee,
                        $currentState,
                        $workload,
                        (int)($trainingCounts[$key] ?? 0),
                        (string)($financialStates[$ref->playerId] ?? 'normal')
                    );
                    $updated = $morale->persistCycleMetrics(
                        $ref,
                        (int)$currentState['id'],
                        (int)$currentState['version'],
                        $this->cycleId,
                        $this->runSequence,
                        $this->now,
                        $metrics
                    );
                    if ($updated && round((float)$currentState['morale'], 2) !== round((float)$metrics['morale'], 2)) {
                        $this->recordMoraleChange(
                            $currentState,
                            (float)$currentState['morale'],
                            (float)$metrics['morale']
                        );
                        $this->moraleChanged++;
                    }
                }
                if ($updated) {
                    $this->processed++;
                } else {
                    $this->alreadyProcessed++;
                }
                $this->commitEmployeeUnit();
            } catch (Throwable $exception) {
                $this->rollbackEmployeeUnit();
                $this->failed++;
                if (class_exists('GameLog', false)) {
                    GameLog::error('employees', 'Employee morale recalculation failed', $exception, [
                        'player_id'=>$ref->playerId,
                        'source_type'=>$ref->sourceType,
                        'source_id'=>$ref->sourceId,
                        'cycle_id'=>$this->cycleId,
                    ]);
                }
            }
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM employee_state
              WHERE relation_status <> \'inactive\'
                AND (last_morale_cycle_id IS NULL OR last_morale_cycle_id <> ?)'
        );
        $stmt->execute([$this->cycleId]);
        $this->remaining = (int)$stmt->fetchColumn() + $repository->countMissingStateRefs();
        if ($this->remaining === 0 && $remainingBudget > 0) {
            $this->markCycleReady();
            $escalation = $strikeService->processCycleEscalations(
                $this->now,
                $this->cycleId,
                $remainingBudget
            );
            $this->raiseRequests = (int)($escalation['raise_requests'] ?? 0);
            $this->threatsStarted = (int)($escalation['threats_started'] ?? 0);
            $this->strikesStarted = (int)($escalation['strikes_started'] ?? 0);
            $this->departures = (int)($escalation['departures'] ?? 0);
            $this->escalationCompleted = (int)($escalation['complete'] ?? 0) === 1;
        }
        $this->updateCycle();
    }

    private function openOrCreateCycle(): int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM employee_module_cycles
              WHERE module_key = 'employees' AND status IN ('open','ready_for_escalation')
              ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute();
        $openId = (int)($stmt->fetchColumn() ?: 0);
        if ($openId > 0) {
            return $openId;
        }
        $key = 'employees:' . $this->runSequence;
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? "INSERT INTO employee_module_cycles (module_key, cycle_key, run_sequence, status)
               VALUES ('employees', ?, ?, 'open') ON CONFLICT(module_key, cycle_key) DO NOTHING"
            : "INSERT IGNORE INTO employee_module_cycles (module_key, cycle_key, run_sequence, status)
               VALUES ('employees', ?, ?, 'open')";
        $this->db->prepare($sql)->execute([$key, $this->runSequence]);
        $stmt = $this->db->prepare(
            "SELECT id FROM employee_module_cycles WHERE module_key='employees' AND cycle_key=? LIMIT 1"
        );
        $stmt->execute([$key]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id <= 0) {
            throw new RuntimeException('Employee morale cycle could not be created.');
        }
        return $id;
    }

    /** @return list<array<string,mixed>> */
    private function loadStateBatch(int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM employee_state
              WHERE relation_status <> \'inactive\'
                AND (last_morale_cycle_id IS NULL OR last_morale_cycle_id <> :cycle_id)
              ORDER BY CASE WHEN last_morale_tick_at IS NULL THEN 0 ELSE 1 END,
                       last_morale_tick_at ASC, id ASC
              LIMIT :limit'
        );
        $stmt->bindValue(':cycle_id', $this->cycleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<EmployeeRef> $refs
     * @return array<string,float>
     */
    private function loadAllocations(array $refs): array
    {
        if ($refs === []) {
            return [];
        }
        [$where, $params] = $this->refPredicate($refs, 'employee_assignments');
        $stmt = $this->db->prepare(
            "SELECT player_id, source_type, source_id, SUM(allocation_pct) AS allocation
               FROM employee_assignments WHERE status='active'
                AND ({$where})
              GROUP BY player_id, source_type, source_id"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $key = (int)$row['player_id'] . ':' . (string)$row['source_type'] . ':' . (int)$row['source_id'];
            $result[$key] = min(100.0, (float)$row['allocation']);
        }
        return $result;
    }

    /** @param list<EmployeeRef> $refs
     * @return array<string,int>
     */
    private function loadTrainingCounts(array $refs): array
    {
        if ($refs === []) {
            return [];
        }
        $conditions = [];
        $params = [];
        foreach ($refs as $index => $ref) {
            $staffType = $ref->sourceType === EmployeeRef::SOURCE_BOARD_MEMBER ? 'board' : 'technical';
            $conditions[] = "(player_id=:player_{$index} AND staff_type=:type_{$index} AND staff_id=:source_{$index})";
            $params["player_{$index}"] = $ref->playerId;
            $params["type_{$index}"] = $staffType;
            $params["source_{$index}"] = $ref->sourceId;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT player_id, staff_type, staff_id, COUNT(*) AS completed
                   FROM staff_trainings WHERE status='passed'
                    AND (" . implode(' OR ', $conditions) . ")
                  GROUP BY player_id, staff_type, staff_id"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $sourceType = (string)$row['staff_type'] === 'board' ? 'board_member' : 'technical_staff';
            $result[(int)$row['player_id'] . ':' . $sourceType . ':' . (int)$row['staff_id']] = (int)$row['completed'];
        }
        return $result;
    }

    /** @param list<EmployeeRef> $refs
     * @return array<int,string>
     */
    private function loadFinancialStates(array $refs): array
    {
        $playerIds = array_values(array_unique(array_map(
            static fn(EmployeeRef $ref): int => $ref->playerId,
            $refs
        )));
        if ($playerIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        try {
            $stmt = $this->db->prepare("SELECT id, financial_state FROM players WHERE id IN ({$placeholders})");
            $stmt->execute($playerIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['id']] = (string)($row['financial_state'] ?? 'normal');
        }
        return $result;
    }

    private function markMissingSource(EmployeeRef $ref, int $stateId): bool
    {
        $current = $this->lockState($stateId, $ref);
        if ($current === null
            || (string)$current['relation_status'] === 'inactive'
            || (int)($current['last_morale_cycle_id'] ?? 0) === $this->cycleId) {
            return false;
        }
        (new EmployeeRelationLifecycleService($this->db))->deactivate($ref, $this->now);
        $stmt = $this->db->prepare(
            "UPDATE employee_state SET last_morale_tick_at=:tick_at, last_morale_tick_sequence=:sequence,
                    last_morale_cycle_id=:cycle_id, updated_at=CURRENT_TIMESTAMP
              WHERE id=:id AND player_id=:player_id AND source_type=:source_type AND source_id=:source_id
                AND relation_status='inactive'
                AND (last_morale_cycle_id IS NULL OR last_morale_cycle_id <> :cycle_guard)"
        );
        $stmt->execute([
            'tick_at'=>$this->now->format('Y-m-d H:i:s'), 'sequence'=>$this->runSequence,
            'cycle_id'=>$this->cycleId, 'id'=>$stateId, 'player_id'=>$ref->playerId,
            'source_type'=>$ref->sourceType, 'source_id'=>$ref->sourceId, 'cycle_guard'=>$this->cycleId,
        ]);
        return $stmt->rowCount() === 1;
    }

    private function cleanupExpiredCandidates(int &$remainingBudget): void
    {
        if ($remainingBudget <= 0) {
            return;
        }
        $quota = min($remainingBudget, max(1, intdiv(max(1, $this->limit), 10)));
        try {
            $this->candidatesExpired = (new CandidateGenerator($this->db))->cleanupExpired($quota);
            $remainingBudget = max(0, $remainingBudget - $this->candidatesExpired);
        } catch (Throwable $exception) {
            $this->cleanupErrors++;
            if (class_exists('GameLog', false)) {
                GameLog::error('employees', 'Expired candidate cleanup failed', $exception);
            }
        }
    }

    private function processDeadlineBudget(
        EmployeeStrikeService $strikeService,
        int &$remainingBudget
    ): void {
        if ($remainingBudget <= 0) {
            return;
        }

        try {
            $deadlines = $this->loadDueDeadlines($remainingBudget);
        } catch (Throwable $exception) {
            $this->deadlineErrors++;
            if (class_exists('GameLog', false)) {
                GameLog::error('employees', 'Employee deadline loading failed', $exception);
            }
            return;
        }

        $departures = new EmployeeDepartureService($this->db);
        foreach ($deadlines as $deadline) {
            if ($remainingBudget <= 0) {
                break;
            }
            $remainingBudget--;
            try {
                $processed = match ($deadline['type']) {
                    'raise' => $strikeService->expireRaiseRequest(
                        (int)$deadline['player_id'],
                        (int)$deadline['id'],
                        $this->now
                    ),
                    'negotiation' => $strikeService->expireNegotiation(
                        (int)$deadline['player_id'],
                        (int)$deadline['strike_id'],
                        (int)$deadline['id'],
                        $this->now
                    ),
                    'departure' => $departures->processDue($this->now, 1) === 1,
                    default => false,
                };
                if (!$processed) {
                    continue;
                }
                match ($deadline['type']) {
                    'raise' => $this->raiseRequestsExpired++,
                    'negotiation' => $this->negotiationsExpired++,
                    'departure' => $this->departuresCompleted++,
                    default => null,
                };
            } catch (Throwable $exception) {
                $this->deadlineErrors++;
                if (class_exists('GameLog', false)) {
                    GameLog::error('employees', 'Employee deadline processing failed', $exception, [
                        'deadline_type'=>$deadline['type'],
                        'record_id'=>$deadline['id'],
                        'player_id'=>$deadline['player_id'],
                    ]);
                }
            }
        }
    }

    /** @return list<array{type:string,id:int,player_id:int,strike_id:int,due_at:string}> */
    private function loadDueDeadlines(int $limit): array
    {
        $limit = max(1, $limit);
        $now = $this->now->format('Y-m-d H:i:s');
        $rows = [];

        $raise = $this->db->prepare(
            "SELECT id, player_id, 0 AS strike_id, deadline_at AS due_at
               FROM employee_raise_requests
              WHERE status IN ('open','postponed') AND deadline_at<?
              ORDER BY deadline_at, id LIMIT {$limit}"
        );
        $raise->execute([$now]);
        foreach ($raise->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->deadlineRow('raise', $row);
        }

        $negotiation = $this->db->prepare(
            "SELECT n.id, n.player_id, n.strike_id, n.round_deadline_at AS due_at
               FROM employee_strike_negotiations n
               JOIN employee_strikes s ON s.id=n.strike_id AND s.player_id=n.player_id
              WHERE n.status='open' AND n.round_deadline_at<? AND s.open_key IS NOT NULL
              ORDER BY n.round_deadline_at, n.id LIMIT {$limit}"
        );
        $negotiation->execute([$now]);
        foreach ($negotiation->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->deadlineRow('negotiation', $row);
        }

        $noticeHours = (new EmployeeSystemConfigService($this->db))->getInt('leave_notice_hours');
        $departureCutoff = DateTimeImmutable::createFromInterface($this->now)
            ->modify("-{$noticeHours} hours")
            ->format('Y-m-d H:i:s');
        $departure = $this->db->prepare(
            "SELECT id, player_id, 0 AS strike_id, leaving_at AS due_at
               FROM employee_state
              WHERE relation_status='leaving' AND leaving_at IS NOT NULL AND leaving_at<=?
              ORDER BY leaving_at, id LIMIT {$limit}"
        );
        $departure->execute([$departureCutoff]);
        foreach ($departure->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['due_at'] = date(
                'Y-m-d H:i:s',
                strtotime((string)$row['due_at']) + ($noticeHours * 3600)
            );
            $rows[] = $this->deadlineRow('departure', $row);
        }

        usort(
            $rows,
            static fn(array $left, array $right): int =>
                [$left['due_at'], $left['type'], $left['id']]
                <=> [$right['due_at'], $right['type'], $right['id']]
        );
        return array_slice($rows, 0, $limit);
    }

    /** @param array<string,mixed> $row
     * @return array{type:string,id:int,player_id:int,strike_id:int,due_at:string}
     */
    private function deadlineRow(string $type, array $row): array
    {
        return [
            'type'=>$type,
            'id'=>(int)$row['id'],
            'player_id'=>(int)$row['player_id'],
            'strike_id'=>(int)($row['strike_id'] ?? 0),
            'due_at'=>(string)$row['due_at'],
        ];
    }

    /** @return array<string,mixed>|null */
    private function lockState(int $stateId, EmployeeRef $ref): ?array
    {
        $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM employee_state
              WHERE id=? AND player_id=? AND source_type=? AND source_id=? LIMIT 1{$suffix}"
        );
        $stmt->execute([$stateId, $ref->playerId, $ref->sourceType, $ref->sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function updateCycle(): void
    {
        $this->cycleCompleted = $this->remaining === 0 && $this->escalationCompleted;
        $status = $this->cycleCompleted
            ? 'completed'
            : ($this->remaining === 0 ? 'ready_for_escalation' : 'open');
        $stmt = $this->db->prepare(
            "UPDATE employee_module_cycles
                SET processed_count=processed_count+:processed,
                    error_count=error_count+:errors,
                    status=:status,
                    completed_at=CASE WHEN :complete=1 THEN CURRENT_TIMESTAMP ELSE completed_at END,
                    updated_at=CURRENT_TIMESTAMP
              WHERE id=:id AND module_key='employees'"
        );
        $stmt->execute([
            'processed'=>$this->processed, 'errors'=>$this->failed,
            'status'=>$status,
            'complete'=>$this->cycleCompleted ? 1 : 0, 'id'=>$this->cycleId,
        ]);
    }

    private function markCycleReady(): void
    {
        $stmt = $this->db->prepare(
            "UPDATE employee_module_cycles
                SET status='ready_for_escalation', updated_at=CURRENT_TIMESTAMP
              WHERE id=? AND module_key='employees' AND status IN ('open','ready_for_escalation')"
        );
        $stmt->execute([$this->cycleId]);
    }

    /**
     * @param list<EmployeeRef> $refs
     * @return array{0:string,1:array<string,int|string>}
     */
    private function refPredicate(array $refs, string $table): array
    {
        $conditions = [];
        $params = [];
        foreach ($refs as $index => $ref) {
            $conditions[] = "({$table}.player_id=:player_{$index}
                AND {$table}.source_type=:type_{$index}
                AND {$table}.source_id=:source_{$index})";
            $params["player_{$index}"] = $ref->playerId;
            $params["type_{$index}"] = $ref->sourceType;
            $params["source_{$index}"] = $ref->sourceId;
        }
        return [implode(' OR ', $conditions), $params];
    }

    /** @param array<string,mixed> $state */
    private function recordMoraleChange(array $state, float $before, float $after): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? "INSERT OR IGNORE INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, ?, ?, 'morale_changed', 'hr.event.morale.title', 'hr.event.morale.message', ?, ?)"
            : "INSERT IGNORE INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, ?, ?, 'morale_changed', 'hr.event.morale.title', 'hr.event.morale.message', ?, ?)";
        $this->db->prepare($sql)->execute([
            (int)$state['player_id'],
            (string)$state['source_type'],
            (int)$state['source_id'],
            json_encode([
                'before' => round($before, 2),
                'after' => round($after, 2),
                'amount' => round($after - $before, 2),
                'cycle_id' => $this->cycleId,
            ], JSON_THROW_ON_ERROR),
            'morale-cycle:' . $this->cycleId . ':' . (int)$state['id'],
        ]);
    }

    private function beginEmployeeUnit(): void
    {
        $this->unitOwnTransaction = !$this->db->inTransaction();
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    private function commitEmployeeUnit(): void
    {
        if ($this->unitOwnTransaction && $this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    private function rollbackEmployeeUnit(): void
    {
        if ($this->unitOwnTransaction && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
