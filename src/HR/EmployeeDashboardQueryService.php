<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Employee/EmployeeRepository.php';
require_once dirname(__DIR__) . '/Training/TrainingService.php';

final class EmployeeDashboardQueryService
{
    private readonly EmployeeRepository $employees;
    private readonly TrainingService $training;

    public function __construct(
        private readonly PDO $db,
        ?EmployeeRepository $employees = null,
        ?TrainingService $training = null
    ) {
        $this->employees = $employees ?? new EmployeeRepository($db);
        $this->training = $training ?? new TrainingService($db);
    }

    /**
     * Builds one player-scoped data model for the HR dashboard.
     * Buduje jeden model danych panelu HR ograniczony do gracza.
     *
     * @return array{
     *   employees:list<array<string,mixed>>,
     *   morale:array<string,float|int>,
     *   trainings:list<array<string,mixed>>,
     *   events:list<array<string,mixed>>
     * }
     */
    public function forPlayer(int $playerId): array
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }

        $employees = $this->employees->listForPlayer($playerId);
        $stateMap = $this->stateMap($playerId);
        $assignmentMap = $this->assignmentMap($playerId);
        $trainingMap = [];
        foreach ($this->trainings($playerId) as $training) {
            $key = $this->trainingSourceKey($training);
            if ($key !== null) {
                $trainingMap[$key][] = $training;
            }
        }

        $moraleTotal = 0.0;
        $leaveRiskTotal = 0.0;
        $strikeSupportTotal = 0.0;
        foreach ($employees as &$employee) {
            $key = $this->sourceKey((string)$employee['source_type'], (int)$employee['source_id']);
            $state = $stateMap[$key] ?? [];
            $employee['state'] = $state;
            $employee['assignments'] = $assignmentMap[$key] ?? [];
            $employee['trainings'] = $trainingMap[$key] ?? [];
            $employee['seniority'] = $this->seniority($employee);
            $employee['morale'] = (float)($state['morale'] ?? 65.0);
            $employee['salary_satisfaction'] = (float)($state['salary_satisfaction'] ?? 70.0);
            $employee['expected_salary'] = (float)($state['expected_salary'] ?? $employee['salary']);
            $employee['workload'] = (float)($state['workload'] ?? 0.0);
            $employee['leave_risk'] = (float)($state['leave_risk'] ?? 0.0);
            $employee['strike_support'] = (float)($state['strike_support'] ?? 0.0);
            $employee['relation_status'] = (string)($state['relation_status'] ?? 'normal');

            $moraleTotal += $employee['morale'];
            $leaveRiskTotal += $employee['leave_risk'];
            $strikeSupportTotal += $employee['strike_support'];
        }
        unset($employee);

        $count = count($employees);
        return [
            'employees' => $employees,
            'morale' => [
                'employee_count' => $count,
                'average_morale' => $count > 0 ? round($moraleTotal / $count, 1) : 0.0,
                'average_leave_risk' => $count > 0 ? round($leaveRiskTotal / $count, 1) : 0.0,
                'average_strike_support' => $count > 0 ? round($strikeSupportTotal / $count, 1) : 0.0,
            ],
            'trainings' => array_values(array_merge(...array_values($trainingMap ?: [[]]))),
            'events' => $this->events($playerId),
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function stateMap(int $playerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT source_type, source_id, department_code, morale, salary_satisfaction,
                    expected_salary, leave_risk, strike_support, workload, relation_status
               FROM employee_state
              WHERE player_id = ?'
        );
        $stmt->execute([$playerId]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$this->sourceKey((string)$row['source_type'], (int)$row['source_id'])] = $row;
        }
        return $map;
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function assignmentMap(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, source_type, source_id, target_type, target_id, allocation_pct, assigned_at
               FROM employee_assignments
              WHERE player_id = ? AND status = 'active'
              ORDER BY assigned_at DESC, id DESC"
        );
        $stmt->execute([$playerId]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$this->sourceKey((string)$row['source_type'], (int)$row['source_id'])][] = $row;
        }
        return $map;
    }

    /** @return list<array<string,mixed>> */
    private function trainings(int $playerId): array
    {
        try {
            return array_merge(
                $this->training->getActiveTrainings($playerId, 'board'),
                $this->training->getHistory($playerId, 'board', 50),
                $this->training->getActiveTrainings($playerId, 'technical'),
                $this->training->getHistory($playerId, 'technical', 50)
            );
        } catch (Throwable $e) {
            GameLog::warn('EmployeeDashboardQueryService', 'Training dashboard data unavailable', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    private function events(int $playerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, source_type, source_id, strike_id, event_key, title_key,
                    message_key, meta_json, created_at
               FROM employee_events
              WHERE player_id = ?
              ORDER BY created_at DESC, id DESC
              LIMIT 100'
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $employee */
    private function seniority(array $employee): string
    {
        $experience = max(0.0, (float)($employee['experience_years'] ?? 0));
        $skills = array_map('floatval', (array)($employee['skills'] ?? []));
        $skillAverage = $skills !== [] ? array_sum($skills) / count($skills) : 5.0;
        $score = $experience + max(0.0, ($skillAverage - 5.0) * 2.0);

        if ($score >= 12.0 || ($experience >= 9.0 && $skillAverage >= 7.0)) {
            return 'senior';
        }
        if ($score >= 6.0 || ($experience >= 4.0 && $skillAverage >= 6.0)) {
            return 'mid';
        }
        return 'junior';
    }

    /** @param array<string,mixed> $training */
    private function trainingSourceKey(array $training): ?string
    {
        $type = (string)($training['staff_type'] ?? '');
        $sourceType = match ($type) {
            'board' => EmployeeRef::SOURCE_BOARD_MEMBER,
            'technical' => EmployeeRef::SOURCE_TECHNICAL_STAFF,
            default => null,
        };
        $sourceId = (int)($training['staff_id'] ?? 0);
        return $sourceType !== null && $sourceId > 0 ? $this->sourceKey($sourceType, $sourceId) : null;
    }

    private function sourceKey(string $sourceType, int $sourceId): string
    {
        return $sourceType . ':' . $sourceId;
    }
}
