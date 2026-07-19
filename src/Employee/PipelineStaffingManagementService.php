<?php
declare(strict_types=1);

final class PipelineStaffingManagementService
{
    private const BLOCKED_RELATION_STATUSES = ['on_strike', 'leaving', 'inactive'];

    private readonly EmployeeAssignmentService $assignments;
    private readonly EmployeeRepository $employees;
    private readonly PipelineStaffingService $staffing;
    private readonly EmployeeStateService $states;

    public function __construct(
        private readonly PDO $db,
        ?EmployeeAssignmentService $assignments = null,
        ?EmployeeRepository $employees = null,
        ?PipelineStaffingService $staffing = null,
        ?EmployeeStateService $states = null
    ) {
        $this->employees = $employees ?? new EmployeeRepository($db);
        $this->states = $states ?? new EmployeeStateService($db, $this->employees);
        $this->assignments = $assignments ?? new EmployeeAssignmentService($db, $this->employees, $this->states);
        $this->staffing = $staffing ?? new PipelineStaffingService($db, $this->employees, null, $this->states);
    }

    /**
     * Builds per-pipeline staffing data for the logistics UI.
     * Buduje dane obsady per rurociag dla widoku logistyki.
     *
     * @param list<array<string, mixed>> $pipelines
     * @return array<int, array<string, mixed>>
     */
    public function buildPipelineStaffingView(int $playerId, array $pipelines): array
    {
        $summaries = $this->staffing->pipelineStaffingForPipelines($playerId, $pipelines);
        if ($summaries === []) {
            return [];
        }

        $assignments = $this->assignments->listForPipelines($playerId, array_keys($summaries));
        $pool = $this->employeePool($playerId);
        $states = $this->stateMap($playerId);
        $allocations = $this->allocationMap($playerId);
        $result = [];

        foreach ($summaries as $pipelineId => $summary) {
            $currentByEmployee = [];
            foreach ($assignments[$pipelineId] ?? [] as $assignment) {
                $currentByEmployee[(string)$assignment['source_type'] . ':' . (int)$assignment['source_id']] = $assignment;
            }

            $candidates = [];
            $active = [];
            foreach ($pool as $key => $employee) {
                $state = $states[$key] ?? ['morale' => 65.0, 'relation_status' => 'normal'];
                $current = $currentByEmployee[$key] ?? null;
                $currentAllocation = (float)($current['allocation_pct'] ?? 0.0);
                $used = (float)($allocations[$key] ?? 0.0);
                $free = max(0.0, 100.0 - $used + $currentAllocation);
                $employeeStatus = (string)($employee['status'] ?? '');
                $allowedStatuses = (string)($employee['source_type'] ?? '') === EmployeeRef::SOURCE_TECHNICAL_STAFF
                    ? ['active', 'busy']
                    : ['active'];
                $blocked = !in_array($employeeStatus, $allowedStatuses, true)
                    || in_array((string)($state['relation_status'] ?? 'normal'), self::BLOCKED_RELATION_STATUSES, true);

                $row = [
                    'source_type' => (string)$employee['source_type'],
                    'source_id' => (int)$employee['source_id'],
                    'full_name' => trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']),
                    'role_code' => $this->staffing->roleCode($employee),
                    'specialization_name' => $employee['specialization_name'] ?? $employee['spec_name'] ?? null,
                    'status' => (string)($employee['status'] ?? ''),
                    'relation_status' => (string)($state['relation_status'] ?? 'normal'),
                    'morale' => round((float)($state['morale'] ?? 65.0), 1),
                    'free_allocation_pct' => round($free, 2),
                    'current_assignment_id' => $current !== null ? (int)$current['id'] : 0,
                    'current_allocation_pct' => round($currentAllocation, 2),
                    'is_blocked' => $blocked,
                    'can_assign' => !empty($summary['is_operational']) && !$blocked && $free > 0.0,
                ];
                $candidates[] = $row;
                if ($current !== null) {
                    $active[] = $row + [
                        'assignment_id' => (int)$current['id'],
                        'allocation_pct' => round($currentAllocation, 2),
                    ];
                }
            }

            $result[$pipelineId] = [
                'pipeline_id' => $pipelineId,
                'summary' => $summary,
                'active_assignments' => $active,
                'candidates' => $candidates,
            ];
        }

        return $result;
    }

    /** @return array{success:bool,was_update:bool,assignment_id:int} */
    public function assignToPipeline(int $playerId, string $sourceType, int $sourceId, int $pipelineId, float $allocationPct): array
    {
        $ref = new EmployeeRef($sourceType, $sourceId, $playerId);
        $canonical = $this->employees->canonicalRef($ref);
        $employee = $this->employees->find($canonical) ?? $this->employees->find($ref);
        if ($employee === null || $this->staffing->roleCode($employee) === '') {
            throw new RuntimeException('Employee specialization is not allowed for pipeline staffing.');
        }

        $existing = $this->findAssignment($playerId, $canonical, $pipelineId);
        $result = $this->assignments->assignToPipeline($canonical, $pipelineId, $allocationPct);
        return [
            'success' => true,
            'was_update' => $existing !== null,
            'assignment_id' => (int)($result['assignment_id'] ?? 0),
        ];
    }

    public function releaseFromPipeline(int $playerId, int $assignmentId): bool
    {
        return $this->assignments->releasePipeline($assignmentId, $playerId);
    }

    /** @return array<string, array<string, mixed>> */
    private function employeePool(int $playerId): array
    {
        $pool = [];
        foreach ($this->employees->listForPlayer($playerId, null, false) as $employee) {
            if ($this->staffing->roleCode($employee) === '') {
                continue;
            }
            $ref = $this->employees->canonicalRef(new EmployeeRef(
                (string)$employee['source_type'],
                (int)$employee['source_id'],
                $playerId
            ));
            $key = $ref->sourceType . ':' . $ref->sourceId;
            if (!isset($pool[$key]) || (string)$employee['source_type'] === EmployeeRef::SOURCE_TECHNICAL_STAFF) {
                $pool[$key] = array_merge($employee, [
                    'source_type' => $ref->sourceType,
                    'source_id' => $ref->sourceId,
                ]);
            }
        }
        return $pool;
    }

    /** @return array<string, array<string, mixed>> */
    private function stateMap(int $playerId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM employee_state WHERE player_id = ? ORDER BY id ASC');
        $stmt->execute([$playerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $state) {
            $map[(string)$state['source_type'] . ':' . (int)$state['source_id']] = $state;
        }

        foreach ($this->employees->sourceLinkMap($playerId) as $legacyMapKey => $canonicalRef) {
            $legacyId = (int)substr($legacyMapKey, strrpos($legacyMapKey, ':') + 1);
            $legacyKey = EmployeeRef::SOURCE_BOARD_MEMBER . ':' . $legacyId;
            $canonicalKey = $canonicalRef->key();
            $preferred = $this->states->selectPreferredRuntimeState(
                $map[$legacyKey] ?? null,
                $map[$canonicalKey] ?? null
            );
            if ($preferred !== null) {
                $map[$canonicalKey] = $preferred;
            }
        }
        return $map;
    }

    /** @return array<string, float> */
    private function allocationMap(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT source_type, source_id, COALESCE(SUM(allocation_pct), 0) AS total_allocation
               FROM employee_assignments
              WHERE player_id = ? AND status = 'active'
              GROUP BY source_type, source_id"
        );
        $stmt->execute([$playerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string)$row['source_type'] . ':' . (int)$row['source_id']] = (float)$row['total_allocation'];
        }
        return $map;
    }

    /** @return array<string, mixed>|null */
    private function findAssignment(int $playerId, EmployeeRef $ref, int $pipelineId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM employee_assignments
              WHERE player_id = ? AND source_type = ? AND source_id = ?
                AND target_type = 'pipeline' AND target_id = ? AND status = 'active'
              LIMIT 1"
        );
        $stmt->execute([$playerId, $ref->sourceType, $ref->sourceId, $pipelineId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
