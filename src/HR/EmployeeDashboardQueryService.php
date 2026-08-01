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
     *   events:list<array<string,mixed>>,
     *   event_pagination:array{page:int,pages:int,total:int,per_page:int,unread_count:int}
     * }
     */
    public function forPlayer(int $playerId, int $eventPage = 1, int $eventPerPage = 20): array
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }
        $eventPage = max(1, $eventPage);
        $eventPerPage = max(10, min(50, $eventPerPage));

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
        $eventData = $this->events($playerId, $eventPage, $eventPerPage);

        return [
            'employees' => $employees,
            'morale' => [
                'employee_count' => $count,
                'average_morale' => $count > 0 ? round($moraleTotal / $count, 1) : 0.0,
                'average_leave_risk' => $count > 0 ? round($leaveRiskTotal / $count, 1) : 0.0,
                'average_strike_support' => $count > 0 ? round($strikeSupportTotal / $count, 1) : 0.0,
            ],
            'trainings' => array_values(array_merge(...array_values($trainingMap ?: [[]]))),
            'events' => $eventData['rows'],
            'event_pagination' => $eventData['pagination'],
        ];
    }

    /** @param list<int> $eventIds */
    public function markEventsNotified(int $playerId, array $eventIds): int
    {
        return $this->updateEventDeliveryState($playerId, $eventIds, false);
    }

    /** @param list<int> $eventIds */
    public function markEventsRead(int $playerId, array $eventIds): int
    {
        return $this->updateEventDeliveryState($playerId, $eventIds, true);
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

    /**
     * @return array{
     *   rows:list<array<string,mixed>>,
     *   pagination:array{page:int,pages:int,total:int,per_page:int,unread_count:int}
     * }
     */
    private function events(int $playerId, int $page, int $perPage): array
    {
        $count = $this->db->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN is_read=0 THEN 1 ELSE 0 END), 0) AS unread_count
               FROM employee_events
              WHERE player_id=?'
        );
        $count->execute([$playerId]);
        $counts = $count->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int)($counts['total'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            'SELECT id, source_type, source_id, strike_id, event_key, title_key,
                    message_key, meta_json, is_read, notified_at, created_at
               FROM employee_events
              WHERE player_id = ?
              ORDER BY created_at DESC, id DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $eventId = (int)$row['id'];
            $sourceType = (string)($row['source_type'] ?? '');
            $sourceId = (int)($row['source_id'] ?? 0);
            $row['record_key'] = 'event:' . $eventId;
            $row['deep_link'] = '/hr?tab=history&event_page=' . $page
                . '&record=' . rawurlencode((string)$row['record_key']);
            $row['employee_record_key'] = in_array(
                $sourceType,
                [EmployeeRef::SOURCE_BOARD_MEMBER, EmployeeRef::SOURCE_TECHNICAL_STAFF],
                true
            ) && $sourceId > 0
                ? 'employee:' . $sourceType . ':' . $sourceId
                : null;
            $row['employee_deep_link'] = $row['employee_record_key'] !== null
                ? '/hr?tab=employees&record=' . rawurlencode((string)$row['employee_record_key'])
                : null;
            $row['is_unread'] = (int)($row['is_read'] ?? 0) === 0;
        }
        unset($row);

        return [
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'per_page' => $perPage,
                'unread_count' => (int)($counts['unread_count'] ?? 0),
            ],
        ];
    }

    /** @param list<int> $eventIds */
    private function updateEventDeliveryState(int $playerId, array $eventIds, bool $markRead): int
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $eventIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return 0;
        }
        $ids = array_slice($ids, 0, 50);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $set = $markRead
            ? 'is_read=1, notified_at=COALESCE(notified_at, CURRENT_TIMESTAMP)'
            : 'notified_at=COALESCE(notified_at, CURRENT_TIMESTAMP)';
        $stmt = $this->db->prepare(
            "UPDATE employee_events
                SET {$set}
              WHERE player_id=? AND id IN ({$placeholders})"
        );
        $stmt->execute([$playerId, ...$ids]);
        return $stmt->rowCount();
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
