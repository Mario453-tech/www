<?php
declare(strict_types=1);

final class HubStaffingManagementService
{
    private const BLOCKED_RELATION_STATUSES = ['on_strike', 'leaving', 'inactive'];
    private const HUB_OPERATOR_CODE = 'hub_operator';

    private readonly EmployeeAssignmentService $assignments;
    private readonly EmployeeRepository $employees;
    private readonly EmployeeStateService $states;
    private readonly LogisticsStaffingService $staffing;

    public function __construct(
        private readonly PDO $db,
        ?EmployeeAssignmentService $assignments = null,
        ?EmployeeRepository $employees = null,
        ?EmployeeStateService $states = null,
        ?LogisticsStaffingService $staffing = null
    ) {
        $this->employees = $employees ?? new EmployeeRepository($db);
        $this->states = $states ?? new EmployeeStateService($db, $this->employees);
        $this->assignments = $assignments ?? new EmployeeAssignmentService($db, $this->employees, $this->states);
        $this->staffing = $staffing ?? new LogisticsStaffingService($db, $this->assignments, $this->employees, $this->states);
    }

    /**
     * Builds per-hub staffing data for the logistics UI.
     * Buduje dane obsady per hub dla widoku logistyki.
     *
     * @param list<array<string, mixed>> $hubCards
     * @return array<int, array<string, mixed>>
     */
    public function buildHubStaffingView(int $playerId, array $hubCards): array
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }

        $hubRows = [];
        foreach ($hubCards as $card) {
            $hub = is_array($card['hub'] ?? null) ? $card['hub'] : null;
            if ($hub === null) {
                continue;
            }
            $hubId = (int)($hub['id'] ?? 0);
            if ($hubId <= 0) {
                continue;
            }
            $hubRows[$hubId] = $hub;
        }

        if ($hubRows === []) {
            return [];
        }

        $staffingByHub = $this->staffing->hubStaffingForHubs(array_values($hubRows));
        $assignmentsByHub = $this->assignments->listForHubs($playerId, array_keys($hubRows));
        $employeePool = $this->employeePool($playerId);
        $stateMap = $this->stateMap($playerId);
        $allocationMap = $this->allocationMap($playerId);
        $operatorConfigured = $this->isHubOperatorConfigured();

        $result = [];
        foreach ($hubRows as $hubId => $hub) {
            $activeAssignments = $assignmentsByHub[$hubId] ?? [];
            $currentByEmployee = [];
            foreach ($activeAssignments as $assignmentRow) {
                $currentByEmployee[(string)$assignmentRow['source_type'] . ':' . (int)$assignmentRow['source_id']] = $assignmentRow;
            }

            $candidateRows = [];
            $activeAssignmentRows = [];
            foreach ($employeePool as $employeeKey => $employee) {
                $state = $stateMap[$employeeKey] ?? [
                    'morale' => 65.0,
                    'relation_status' => 'normal',
                ];
                $usedAllocation = (float)($allocationMap[$employeeKey] ?? 0.0);
                $currentAssignment = $currentByEmployee[$employeeKey] ?? null;
                $currentAllocation = (float)($currentAssignment['allocation_pct'] ?? 0.0);
                $freeAllocation = max(0.0, 100.0 - $usedAllocation + $currentAllocation);
                $isBlocked = (string)($employee['status'] ?? '') !== 'active'
                    || in_array((string)($state['relation_status'] ?? 'normal'), self::BLOCKED_RELATION_STATUSES, true);

                $candidateRows[] = [
                    'employee_key' => $employeeKey,
                    'source_type' => (string)$employee['source_type'],
                    'source_id' => (int)$employee['source_id'],
                    'full_name' => trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']),
                    'department_code' => (string)($employee['department_code'] ?? ''),
                    'role_code' => (string)($employee['role_code'] ?? ''),
                    'specialization_code' => $employee['specialization_code'] ?? null,
                    'specialization_name' => $employee['specialization_name'] ?? null,
                    'status' => (string)($employee['status'] ?? ''),
                    'relation_status' => (string)($state['relation_status'] ?? 'normal'),
                    'morale' => round((float)($state['morale'] ?? 65.0), 1),
                    'skill' => $this->employeeLogisticsSkill($employee),
                    'used_allocation_pct' => round($usedAllocation, 2),
                    'free_allocation_pct' => round($freeAllocation, 2),
                    'current_assignment_id' => $currentAssignment !== null ? (int)$currentAssignment['id'] : 0,
                    'current_allocation_pct' => round($currentAllocation, 2),
                    'is_blocked' => $isBlocked,
                    'can_assign' => !$isBlocked && $freeAllocation > 0.0,
                ];

                if ($currentAssignment !== null) {
                    $activeAssignmentRows[] = [
                        'assignment_id' => (int)$currentAssignment['id'],
                        'employee_key' => $employeeKey,
                        'source_type' => (string)$employee['source_type'],
                        'source_id' => (int)$employee['source_id'],
                        'full_name' => trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']),
                        'department_code' => (string)($employee['department_code'] ?? ''),
                        'specialization_code' => $employee['specialization_code'] ?? null,
                        'specialization_name' => $employee['specialization_name'] ?? null,
                        'allocation_pct' => round($currentAllocation, 2),
                        'morale' => round((float)($state['morale'] ?? 65.0), 1),
                        'skill' => $this->employeeLogisticsSkill($employee),
                        'relation_status' => (string)($state['relation_status'] ?? 'normal'),
                    ];
                }
            }

            usort($candidateRows, static function (array $left, array $right): int {
                return [
                    $left['current_assignment_id'] > 0 ? 0 : 1,
                    $left['is_blocked'] ? 1 : 0,
                    -1 * (float)$left['free_allocation_pct'],
                    -1 * (float)$left['skill'],
                    (string)$left['full_name'],
                ] <=> [
                    $right['current_assignment_id'] > 0 ? 0 : 1,
                    $right['is_blocked'] ? 1 : 0,
                    -1 * (float)$right['free_allocation_pct'],
                    -1 * (float)$right['skill'],
                    (string)$right['full_name'],
                ];
            });

            $result[$hubId] = [
                'hub_id' => $hubId,
                'runtime_enabled' => $this->staffing->isRuntimeEnabled(),
                'summary' => $staffingByHub[$hubId] ?? $this->emptyHubSummary($hubId, $hub),
                'active_assignments' => $activeAssignmentRows,
                'candidates' => $candidateRows,
                'assignment_count' => count($activeAssignmentRows),
                'operator_configured' => $operatorConfigured,
            ];
        }

        return $result;
    }

    /** @return array{success:bool, was_update:bool, assignment_id:int} */
    public function assignToHub(int $playerId, string $sourceType, int $sourceId, int $hubId, float $allocationPct): array
    {
        $ref = new EmployeeRef($sourceType, $sourceId, $playerId);
        $canonicalRef = $this->employees->canonicalRef($ref);
        $employee = $this->employees->find($canonicalRef) ?? $this->employees->find($ref);
        if ($employee === null || !$this->isHubOperator($employee)) {
            throw new RuntimeException(t('logistics.hub.staffing.err_operator_required'));
        }
        $result = $this->assignments->assignToHub($ref, $hubId, $allocationPct);

        return [
            'success' => true,
            'was_update' => !empty($result['was_update']),
            'assignment_id' => (int)($result['assignment_id'] ?? 0),
        ];
    }

    public function releaseFromHub(int $playerId, int $assignmentId): bool
    {
        return $this->assignments->releaseHub($assignmentId, $playerId);
    }

    /** @return array<string, array<string, mixed>> */
    private function employeePool(int $playerId): array
    {
        $pool = [];
        foreach ($this->employees->listForPlayer($playerId, null, false) as $employee) {
            if (!$this->isHubOperator($employee)) {
                continue;
            }
            $canonicalKey = $this->canonicalEmployeeKey($playerId, $employee);
            $current = $pool[$canonicalKey] ?? null;
            if ($current === null || (string)$employee['source_type'] === EmployeeRef::SOURCE_TECHNICAL_STAFF) {
                $pool[$canonicalKey] = array_merge($employee, [
                    'source_type' => strtok($canonicalKey, ':'),
                    'source_id' => (int)substr($canonicalKey, strpos($canonicalKey, ':') + 1),
                ]);
            }
        }

        return $pool;
    }

    private function isHubOperatorConfigured(): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
               FROM hr_specializations
              WHERE code = ?
              LIMIT 1"
        );
        $stmt->execute([self::HUB_OPERATOR_CODE]);

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $employee */
    private function isHubOperator(array $employee): bool
    {
        return (string)($employee['role_code'] ?? '') === self::HUB_OPERATOR_CODE;
    }

    /** @return array<string, array<string, mixed>> */
    private function stateMap(int $playerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT *
               FROM employee_state
              WHERE player_id = ?
              ORDER BY id ASC'
        );
        $stmt->execute([$playerId]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $state) {
            $map[(string)$state['source_type'] . ':' . (int)$state['source_id']] = $state;
        }

        return $map;
    }

    /** @return array<string, float> */
    private function allocationMap(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT source_type, source_id, COALESCE(SUM(allocation_pct), 0) AS total_allocation
               FROM employee_assignments
              WHERE player_id = ?
                AND status = 'active'
              GROUP BY source_type, source_id"
        );
        $stmt->execute([$playerId]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string)$row['source_type'] . ':' . (int)$row['source_id']] = (float)$row['total_allocation'];
        }

        return $map;
    }

    /** @param array<string, mixed> $employee */
    private function canonicalEmployeeKey(int $playerId, array $employee): string
    {
        $ref = new EmployeeRef(
            (string)$employee['source_type'],
            (int)$employee['source_id'],
            $playerId
        );
        $canonical = $this->employees->canonicalRef($ref);

        return $canonical->sourceType . ':' . $canonical->sourceId;
    }

    /** @param array<string, mixed> $employee */
    private function employeeLogisticsSkill(array $employee): float
    {
        $skills = is_array($employee['skills'] ?? null) ? $employee['skills'] : [];
        $organization = (float)($skills['organization'] ?? $skills['role_skill'] ?? 5);
        $analysis = (float)($skills['analysis'] ?? $skills['role_skill'] ?? 5);
        $stress = (float)($skills['stress'] ?? $skills['role_skill'] ?? 5);

        return round(max(1.0, min(10.0, ($organization * 0.45) + ($analysis * 0.35) + ($stress * 0.20))), 2);
    }

    /**
     * @param array<string, mixed> $hub
     * @return array<string, mixed>
     */
    private function emptyHubSummary(int $hubId, array $hub): array
    {
        $playerId = (int)($hub['tenant_player_id'] ?? 0);
        if ($playerId <= 0) {
            $playerId = (int)($hub['player_id'] ?? 0);
        }

        return [
            'hub_id' => $hubId,
            'player_id' => $playerId,
            'owner_player_id' => (int)($hub['player_id'] ?? 0),
            'tenant_player_id' => (int)($hub['tenant_player_id'] ?? 0),
            'required_count' => 1,
            'assigned_count' => 0,
            'coverage_pct' => 0.0,
            'average_skill' => 0.0,
            'average_morale' => 0.0,
            'missing_roles' => ['hub_operator'],
            'throughput_mult' => 0.6,
            'incident_risk_mult' => 1.75,
            'maintenance_cost_mult' => 1.25,
            'runtime_effects' => ['hub_throughput_pct' => -40.0],
            'runtime_incident_mods' => ['incident_mult' => 1.75],
            'assignments' => [],
        ];
    }
}
