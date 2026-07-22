<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRepository.php';
require_once dirname(__DIR__) . '/Employee/EmployeeStateService.php';
require_once dirname(__DIR__) . '/HR/MoraleServiceV2.php';

final class EmployeeMoraleSection
{
    public int $cycleId = 0;
    public int $examined = 0;
    public int $processed = 0;
    public int $failed = 0;
    public int $alreadyProcessed = 0;
    public int $remaining = 0;
    public bool $cycleCompleted = false;
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
        $this->cycleId = $this->openOrCreateCycle();
        $repository = new EmployeeRepository($this->db);
        $stateService = new EmployeeStateService($this->db, $repository);
        $employees = $repository->listAll(null, false);
        $entries = [];
        $employeeMap = [];
        foreach ($employees as $employee) {
            $ref = new EmployeeRef((string)$employee['source_type'], (int)$employee['source_id'], (int)$employee['player_id']);
            $entries[] = ['ref'=>$ref, 'employee'=>$employee];
            $employeeMap[$ref->playerId . ':' . $ref->key()] = $employee;
        }
        $stateService->ensureStatesForRecords($entries);

        $states = $this->loadStateBatch();
        $this->examined = count($states);
        $allocations = $this->loadAllocations();
        $trainingCounts = $this->loadTrainingCounts();
        $financialStates = $this->loadFinancialStates();
        $morale = new MoraleService($this->db);

        foreach ($states as $state) {
            $ref = new EmployeeRef((string)$state['source_type'], (int)$state['source_id'], (int)$state['player_id']);
            $key = $ref->playerId . ':' . $ref->key();
            $employee = $employeeMap[$key] ?? null;
            try {
                $this->beginEmployeeUnit();
                if (!is_array($employee)) {
                    $updated = $this->markMissingSource($ref, (int)$state['id']);
                } else {
                    $workload = $morale->calculateWorkload($employee, (float)($allocations[$key] ?? 0));
                    $metrics = $morale->calculateMetrics(
                        $employee,
                        $state,
                        $workload,
                        (int)($trainingCounts[$key] ?? 0),
                        (string)($financialStates[$ref->playerId] ?? 'normal')
                    );
                    $updated = $morale->persistCycleMetrics(
                        $ref,
                        (int)$state['id'],
                        $this->cycleId,
                        $this->runSequence,
                        $this->now,
                        $metrics
                    );
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
              WHERE last_morale_cycle_id IS NULL OR last_morale_cycle_id <> ?'
        );
        $stmt->execute([$this->cycleId]);
        $this->remaining = (int)$stmt->fetchColumn();
        $this->updateCycle();
    }

    private function openOrCreateCycle(): int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM employee_module_cycles
              WHERE module_key = 'employees' AND status = 'open'
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
    private function loadStateBatch(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM employee_state
              WHERE last_morale_cycle_id IS NULL OR last_morale_cycle_id <> :cycle_id
              ORDER BY CASE WHEN last_morale_tick_at IS NULL THEN 0 ELSE 1 END,
                       last_morale_tick_at ASC, id ASC
              LIMIT :limit'
        );
        $stmt->bindValue(':cycle_id', $this->cycleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $this->limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,float> */
    private function loadAllocations(): array
    {
        $rows = $this->db->query(
            "SELECT player_id, source_type, source_id, SUM(allocation_pct) AS allocation
               FROM employee_assignments WHERE status='active'
              GROUP BY player_id, source_type, source_id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $key = (int)$row['player_id'] . ':' . (string)$row['source_type'] . ':' . (int)$row['source_id'];
            $result[$key] = min(100.0, (float)$row['allocation']);
        }
        return $result;
    }

    /** @return array<string,int> */
    private function loadTrainingCounts(): array
    {
        try {
            $rows = $this->db->query(
                "SELECT player_id, staff_type, staff_id, COUNT(*) AS completed
                   FROM staff_trainings WHERE status='passed'
                  GROUP BY player_id, staff_type, staff_id"
            )->fetchAll(PDO::FETCH_ASSOC);
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

    /** @return array<int,string> */
    private function loadFinancialStates(): array
    {
        try {
            $rows = $this->db->query('SELECT id, financial_state FROM players')->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt = $this->db->prepare(
            "UPDATE employee_state SET relation_status='inactive', workload=0,
                    last_morale_tick_at=:tick_at, last_morale_tick_sequence=:sequence,
                    last_morale_cycle_id=:cycle_id, version=version+1, updated_at=CURRENT_TIMESTAMP
              WHERE id=:id AND player_id=:player_id AND source_type=:source_type AND source_id=:source_id
                AND (last_morale_cycle_id IS NULL OR last_morale_cycle_id <> :cycle_guard)"
        );
        $stmt->execute([
            'tick_at'=>$this->now->format('Y-m-d H:i:s'), 'sequence'=>$this->runSequence,
            'cycle_id'=>$this->cycleId, 'id'=>$stateId, 'player_id'=>$ref->playerId,
            'source_type'=>$ref->sourceType, 'source_id'=>$ref->sourceId, 'cycle_guard'=>$this->cycleId,
        ]);
        return $stmt->rowCount() === 1;
    }

    private function updateCycle(): void
    {
        $this->cycleCompleted = $this->remaining === 0;
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
            'status'=>$this->cycleCompleted ? 'completed' : 'open',
            'complete'=>$this->cycleCompleted ? 1 : 0, 'id'=>$this->cycleId,
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
