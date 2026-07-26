<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRepository.php';
require_once dirname(__DIR__) . '/Employee/EmployeeStateService.php';
require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';

final class MoraleService
{
    private readonly EmployeeStateService $states;
    private readonly EmployeeSystemConfigService $config;

    public function __construct(private readonly PDO $db)
    {
        EmployeeSystemBootstrap::ensure($db);
        $repository = new EmployeeRepository($db);
        $this->states = new EmployeeStateService($db, $repository);
        $this->config = new EmployeeSystemConfigService($db);
    }

    public static function modifyMorale(int $staffId, int $amount, string $reason): void
    {
        if ($staffId <= 0 || $amount === 0) {
            return;
        }
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT player_id FROM technical_staff WHERE id = ? LIMIT 1');
        $stmt->execute([$staffId]);
        $playerId = (int)($stmt->fetchColumn() ?: 0);
        if ($playerId > 0) {
            (new self($db))->changeMorale(
                new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId, $playerId),
                (float)$amount,
                $reason
            );
        }
    }

    /** @return list<array<string,mixed>> */
    public static function getMoraleHistory(int $staffId, int $limit = 20): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT player_id FROM technical_staff WHERE id = ? LIMIT 1');
        $stmt->execute([$staffId]);
        $playerId = (int)($stmt->fetchColumn() ?: 0);
        if ($playerId <= 0) {
            return [];
        }
        $stmt = $db->prepare(
            "SELECT meta_json, created_at FROM employee_events
              WHERE player_id = :player_id AND source_type = 'technical_staff'
                AND source_id = :source_id AND event_key = 'morale_changed'
              ORDER BY created_at DESC LIMIT :limit"
        );
        $stmt->bindValue(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue(':source_id', $staffId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static function (array $row): array {
            $meta = json_decode((string)($row['meta_json'] ?? ''), true);
            return [
                'change_amount' => (float)($meta['amount'] ?? 0),
                'reason' => (string)($meta['reason'] ?? ''),
                'created_at' => (string)$row['created_at'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function changeMorale(EmployeeRef $ref, float $amount, string $reason): float
    {
        $state = $this->states->ensureState($ref);
        $newMorale = round(max(0.0, min(100.0, (float)$state['morale'] + $amount)), 2);
        $actual = round($newMorale - (float)$state['morale'], 2);
        if ($actual === 0.0) {
            return $newMorale;
        }
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $stmt = $this->db->prepare(
                'UPDATE employee_state SET morale=:morale, version=version+1, updated_at=CURRENT_TIMESTAMP
                  WHERE id=:id AND player_id=:player_id AND source_type=:source_type AND source_id=:source_id'
            );
            $stmt->execute([
                'morale'=>$newMorale, 'id'=>(int)$state['id'], 'player_id'=>$ref->playerId,
                'source_type'=>$ref->sourceType, 'source_id'=>$ref->sourceId,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Canonical employee morale update did not affect exactly one row.');
            }
            $event = $this->db->prepare(
                "INSERT INTO employee_events
                    (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
                 VALUES (?, ?, ?, 'morale_changed', 'hr.event.morale.title', 'hr.event.morale.message', ?, ?)"
            );
            $event->execute([
                $ref->playerId, $ref->sourceType, $ref->sourceId,
                json_encode(['amount'=>$actual, 'reason'=>$reason], JSON_THROW_ON_ERROR),
                'morale:' . $ref->playerId . ':' . $ref->key() . ':' . bin2hex(random_bytes(12)),
            ]);
            if ($ownTransaction) {
                $this->db->commit();
            }
            return $newMorale;
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $employee */
    public function calculateExpectedSalary(array $employee, int $trainingCount, float $workload): float
    {
        $salary = max(0.0, (float)($employee['salary'] ?? 0));
        $minimum = (float)($employee['salary_range_min'] ?? 0);
        $maximum = (float)($employee['salary_range_max'] ?? 0);
        if ($minimum <= 0 || $maximum < $minimum) {
            $minimum = max(1.0, $salary * 0.9);
            $maximum = max($minimum, $salary * 1.35);
        }
        $skills = array_values(array_filter((array)($employee['skills'] ?? []), 'is_numeric'));
        $averageSkill = $skills === [] ? 5.0 : array_sum($skills) / count($skills);
        $skillMin = $this->config->getFloat('skill_factor_min');
        $skillFactor = $skillMin + (max(0.0, min(10.0, $averageSkill)) / 10.0)
            * ($this->config->getFloat('skill_factor_max') - $skillMin);
        $experience = max(0, min(20, (int)($employee['experience_years'] ?? 0)));
        $experienceFactor = 1.0 + ($experience / 20.0)
            * ($this->config->getFloat('experience_factor_max') - 1.0);
        $ambition = max(0.0, min(10.0, (float)(($employee['traits']['ambition'] ?? 5))));
        $ambitionMin = $this->config->getFloat('ambition_factor_min');
        $ambitionFactor = $ambitionMin + ($ambition / 10.0)
            * ($this->config->getFloat('ambition_factor_max') - $ambitionMin);
        $hiredAt = strtotime((string)($employee['hired_at'] ?? '')) ?: time();
        $tenureYears = max(0.0, (time() - $hiredAt) / 31557600);
        $tenurePct = min($this->config->getFloat('tenure_pct_max'), $tenureYears * $this->config->getFloat('tenure_year_pct'));
        $trainingPct = min($this->config->getFloat('training_pct_max'), max(0, $trainingCount) * $this->config->getFloat('training_pct_each'));
        $responsibilityPct = (max(0.0, min(100.0, $workload)) / 100.0) * $this->config->getFloat('responsibility_pct_max');
        $expected = (($minimum + $maximum) / 2.0) * $skillFactor * $experienceFactor * $ambitionFactor
            * (1.0 + ($tenurePct + $trainingPct + $responsibilityPct) / 100.0);
        return round(max(
            $minimum * $this->config->getFloat('salary_min_factor'),
            min($maximum * $this->config->getFloat('salary_max_factor'), $expected)
        ), 2);
    }

    /** @param array<string,mixed> $employee */
    public function calculateWorkload(array $employee, float $allocation): float
    {
        $status = (string)($employee['status'] ?? 'inactive');
        if (in_array($status, ['on_leave','fired','inactive','dismissed','resigned'], true)) {
            return 0.0;
        }
        if ($status === 'busy') {
            return 100.0;
        }
        $allocation = max(0.0, min(100.0, $allocation));
        if ($allocation > 0) {
            return $allocation;
        }
        return ($employee['source_type'] ?? '') === EmployeeRef::SOURCE_TECHNICAL_STAFF ? 20.0 : 40.0;
    }

    /**
     * @param array<string,mixed> $employee
     * @param array<string,mixed> $state
     * @return array<string,float|string|int>
     */
    public function calculateMetrics(array $employee, array $state, float $workload, int $trainingCount, string $financialState): array
    {
        $expected = $this->calculateExpectedSalary($employee, $trainingCount, $workload);
        $satisfaction = $expected > 0 ? round(max(0.0, min(120.0, ((float)$employee['salary'] / $expected) * 100.0)), 2) : 100.0;
        $relation = $this->nextRelationStatus($state, $satisfaction);
        $salaryAdjustment = max(-25.0, min(7.5, ($satisfaction - 90.0) * 0.25));
        $workloadAdjustment = $workload <= 60 ? 5.0 : ($workload <= 80 ? 0.0 : -15.0 * (($workload - 80.0) / 20.0));
        $financeAdjustment = $financialState === 'crisis' ? -15.0 : ($financialState === 'warning' ? -5.0 : 0.0);
        $relationAdjustment = match ($relation) {
            'unhappy'=>-5.0, 'raise_requested'=>-8.0, 'dispute'=>-15.0,
            'strike_threat'=>-22.0, 'on_strike'=>-30.0, default=>0.0,
        };
        $loyalty = max(0.0, min(10.0, (float)(($employee['traits']['loyalty'] ?? 5))));
        $stress = max(0.0, min(10.0, (float)(($employee['skills']['stress'] ?? 5))));
        $penaltyReduction = (($loyalty + $stress) / 20.0) * 0.30;
        $negative = min(0.0, $workloadAdjustment) + $financeAdjustment + $relationAdjustment;
        $target = 65.0 + $salaryAdjustment + max(0.0, $workloadAdjustment) + $negative * (1.0 - $penaltyReduction);
        $current = (float)$state['morale'];
        $delta = max(-$this->config->getFloat('morale_cycle_down_max'), min($this->config->getFloat('morale_cycle_up_max'), $target - $current));
        $morale = round(max(0.0, min(100.0, $current + $delta)), 2);
        $leaveRisk = round(max(0.0, min(100.0,
            (100.0-$morale)*0.45 + max(0.0,90.0-$satisfaction)*0.35 + max(0.0,$workload-70.0)*0.35 - $loyalty*1.5
        )), 2);
        $strikeSupport = round(max(0.0, min(100.0,
            (100.0-$morale)*0.40 + max(0.0,85.0-$satisfaction)*0.45 + max(0.0,$workload-60.0)*0.30 - $loyalty
        )), 2);
        return [
            'expected_salary'=>$expected, 'salary_satisfaction'=>$satisfaction, 'workload'=>round($workload,2),
            'morale'=>$morale, 'leave_risk'=>$leaveRisk, 'strike_support'=>$strikeSupport,
            'relation_status'=>$relation, 'low_morale_streak'=>$morale < 45 ? (int)$state['low_morale_streak']+1 : 0,
            'dispute_ticks'=>in_array($relation,['dispute','strike_threat','on_strike'],true) ? (int)$state['dispute_ticks']+1 : 0,
        ];
    }

    /** @param array<string,mixed> $state */
    private function nextRelationStatus(array $state, float $satisfaction): string
    {
        $current = (string)($state['relation_status'] ?? 'normal');
        if (in_array($current, ['raise_requested','dispute','strike_threat','on_strike','leaving','inactive'], true)) {
            return $current;
        }
        $morale = (float)$state['morale'];
        if ($morale < $this->config->getFloat('raise_morale_threshold') && $satisfaction < $this->config->getFloat('raise_satisfaction_threshold')) {
            return 'raise_requested';
        }
        if ($morale < $this->config->getFloat('unhappy_morale_threshold') || $satisfaction < $this->config->getFloat('unhappy_satisfaction_threshold')) {
            return 'unhappy';
        }
        return 'normal';
    }

    /** @param array<string,float|string|int> $metrics */
    public function persistCycleMetrics(EmployeeRef $ref, int $stateId, int $cycleId, int $runSequence, DateTimeInterface $now, array $metrics): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE employee_state SET expected_salary=:expected_salary, salary_satisfaction=:salary_satisfaction,
                workload=:workload, morale=:morale, leave_risk=:leave_risk, strike_support=:strike_support,
                relation_status=:relation_status, low_morale_streak=:low_morale_streak, dispute_ticks=:dispute_ticks,
                last_morale_tick_at=:tick_at, last_morale_tick_sequence=:tick_sequence,
                last_morale_cycle_id=:cycle_id, version=version+1, updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND player_id=:player_id AND source_type=:source_type AND source_id=:source_id
               AND (last_morale_cycle_id IS NULL OR last_morale_cycle_id <> :cycle_guard)'
        );
        $stmt->execute([
            'expected_salary'=>$metrics['expected_salary'], 'salary_satisfaction'=>$metrics['salary_satisfaction'],
            'workload'=>$metrics['workload'], 'morale'=>$metrics['morale'], 'leave_risk'=>$metrics['leave_risk'],
            'strike_support'=>$metrics['strike_support'], 'relation_status'=>$metrics['relation_status'],
            'low_morale_streak'=>$metrics['low_morale_streak'], 'dispute_ticks'=>$metrics['dispute_ticks'],
            'tick_at'=>$now->format('Y-m-d H:i:s'), 'tick_sequence'=>$runSequence, 'cycle_id'=>$cycleId,
            'id'=>$stateId, 'player_id'=>$ref->playerId, 'source_type'=>$ref->sourceType,
            'source_id'=>$ref->sourceId, 'cycle_guard'=>$cycleId,
        ]);
        return $stmt->rowCount() === 1;
    }
}
