<?php
declare(strict_types=1);

final class PipelineStaffingService
{
    public const ROLE_ENGINEER = 'pipeline_engineer';
    public const ROLE_LOGISTICS = 'pipeline_logistics_specialist';
    public const OPERATIONAL_STATUSES = ['active', 'degraded', 'critical', 'leak'];
    private const BLOCKED_RELATION_STATUSES = ['on_strike', 'leaving', 'inactive'];

    private readonly EmployeeRepository $employees;
    private readonly EmployeeRoleEffectService $roleEffects;
    private readonly EmployeeStateService $states;

    public function __construct(
        private readonly PDO $db,
        ?EmployeeRepository $employees = null,
        ?EmployeeRoleEffectService $roleEffects = null,
        ?EmployeeStateService $states = null
    ) {
        EmployeeSystemBootstrap::ensure($db);
        $this->employees = $employees ?? new EmployeeRepository($db);
        $this->states = $states ?? new EmployeeStateService($db, $this->employees);
        $this->roleEffects = $roleEffects ?? new EmployeeRoleEffectService($db, $this->employees, $this->states);
    }

    /**
     * Calculates staffing for selected pipeline rows without per-pipeline queries.
     * Liczy obsade wybranych rurociagow bez zapytan per rurociag.
     *
     * @param list<array<string, mixed>> $pipelines
     * @return array<int, array<string, mixed>>
     */
    public function pipelineStaffingForPipelines(int $playerId, array $pipelines): array
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }

        $pipelineRows = [];
        foreach ($pipelines as $pipeline) {
            $pipelineId = (int)($pipeline['id'] ?? 0);
            if ($pipelineId <= 0 || (int)($pipeline['player_id'] ?? $playerId) !== $playerId) {
                continue;
            }
            $pipelineRows[$pipelineId] = $pipeline;
        }
        if ($pipelineRows === []) {
            return [];
        }

        $assignments = $this->loadAssignments($playerId, array_keys($pipelineRows));
        $employees = $this->employees->listForPlayer($playerId, null, false);
        $linkMap = $this->employees->sourceLinkMap($playerId);
        $employeeMap = $this->employeeMap($employees);
        $stateMap = $this->stateMap($playerId, $linkMap);
        $logisticsEffects = $this->logisticsEffectMap($playerId, $employees, $stateMap, $linkMap);

        $result = [];
        foreach ($pipelineRows as $pipelineId => $pipeline) {
            $status = (string)($pipeline['status'] ?? 'active');
            $operational = in_array($status, self::OPERATIONAL_STATUSES, true);
            $engineerAllocation = 0.0;
            $logisticsAllocation = 0.0;
            $weightedLossEffect = 0.0;
            $activeRows = [];

            foreach ($assignments[$pipelineId] ?? [] as $assignment) {
                $key = (string)$assignment['source_type'] . ':' . (int)$assignment['source_id'];
                $employee = $employeeMap[$key] ?? null;
                $roleCode = is_array($employee) ? $this->roleCode($employee) : '';
                $allocation = max(0.0, (float)($assignment['allocation_pct'] ?? 0.0));
                $employeeOperational = is_array($employee)
                    && $this->employeeOperational($employee, $stateMap[$key] ?? null);

                $assignment['role_code'] = $roleCode;
                $assignment['employee_operational'] = $employeeOperational;
                $activeRows[] = $assignment;
                if (!$operational || !$employeeOperational) {
                    continue;
                }

                if ($roleCode === self::ROLE_ENGINEER) {
                    $engineerAllocation += $allocation;
                    continue;
                }
                if ($roleCode === self::ROLE_LOGISTICS) {
                    $logisticsAllocation += $allocation;
                    $weightedLossEffect += (float)($logisticsEffects[$key] ?? 0.0) * $allocation;
                }
            }

            $engineerCoverage = min(100.0, $engineerAllocation);
            $logisticsCoverage = min(100.0, $logisticsAllocation);
            $lossDivisor = max(100.0, $logisticsAllocation);
            $result[$pipelineId] = [
                'pipeline_id' => $pipelineId,
                'status' => $status,
                'is_operational' => $operational,
                'engineer_allocation_pct' => round($engineerAllocation, 2),
                'logistics_allocation_pct' => round($logisticsAllocation, 2),
                'engineer_coverage_pct' => round($engineerCoverage, 2),
                'logistics_coverage_pct' => round($logisticsCoverage, 2),
                'engineer_degradation_mult' => round(2.0 - ($engineerCoverage / 100.0), 6),
                'engineer_incident_mult' => round(2.0 - ($engineerCoverage / 100.0), 6),
                'pipeline_loss_pct' => round($weightedLossEffect / $lossDivisor, 4),
                'assignments' => $activeRows,
            ];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function pipelineStaffingForPlayer(int $playerId): array
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }

        $stmt = $this->db->prepare(
            'SELECT id, player_id, status
               FROM well_pipelines
              WHERE player_id = ?
              ORDER BY id ASC'
        );
        $stmt->execute([$playerId]);
        return $this->pipelineStaffingForPipelines($playerId, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string, mixed> $employee */
    public function roleCode(array $employee): string
    {
        if ((string)($employee['source_type'] ?? '') !== EmployeeRef::SOURCE_TECHNICAL_STAFF) {
            return '';
        }

        $code = trim((string)($employee['role_code'] ?? ''));
        return in_array($code, [self::ROLE_ENGINEER, self::ROLE_LOGISTICS], true) ? $code : '';
    }

    /**
     * @param list<int> $pipelineIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function loadAssignments(int $playerId, array $pipelineIds): array
    {
        $placeholders = implode(',', array_fill(0, count($pipelineIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT ea.*
               FROM employee_assignments ea
               JOIN well_pipelines wp
                 ON wp.id = ea.target_id
                AND wp.player_id = ea.player_id
              WHERE ea.player_id = ?
                AND ea.target_type = 'pipeline'
                AND ea.target_id IN ({$placeholders})
                AND ea.status = 'active'
              ORDER BY ea.target_id ASC, ea.id ASC"
        );
        $stmt->execute(array_merge([$playerId], $pipelineIds));

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[(int)$row['target_id']][] = $row;
        }
        return $grouped;
    }

    /**
     * @param list<array<string, mixed>> $employees
     * @return array<string, array<string, mixed>>
     */
    private function employeeMap(array $employees): array
    {
        $map = [];
        foreach ($employees as $employee) {
            $key = (string)$employee['source_type'] . ':' . (int)$employee['source_id'];
            $map[$key] = $employee;
        }
        return $map;
    }

    /**
     * @param array<string, EmployeeRef> $linkMap
     * @return array<string, array<string, mixed>>
     */
    private function stateMap(int $playerId, array $linkMap): array
    {
        $stmt = $this->db->prepare('SELECT * FROM employee_state WHERE player_id = ? ORDER BY id ASC');
        $stmt->execute([$playerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $state) {
            $map[(string)$state['source_type'] . ':' . (int)$state['source_id']] = $state;
        }

        foreach ($linkMap as $legacyMapKey => $canonicalRef) {
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

    /**
     * @param list<array<string, mixed>> $employees
     * @param array<string, array<string, mixed>> $states
     * @param array<string, EmployeeRef> $linkMap
     * @return array<string, float>
     */
    private function logisticsEffectMap(int $playerId, array $employees, array $states, array $linkMap): array
    {
        $map = [];
        foreach ($this->roleEffects->calculatePlayerEffects(
            $playerId,
            [self::ROLE_LOGISTICS => 'pipeline'],
            $employees,
            $states,
            $linkMap
        ) as $result) {
            $employee = (array)($result['employee'] ?? []);
            $key = (string)($employee['source_type'] ?? '') . ':' . (int)($employee['source_id'] ?? 0);
            $map[$key] = (float)($result['effects']['pipeline_loss_pct']['final_value'] ?? 0.0);
        }
        return $map;
    }

    /**
     * @param array<string, mixed> $employee
     * @param array<string, mixed>|null $state
     */
    private function employeeOperational(array $employee, ?array $state): bool
    {
        $status = (string)($employee['status'] ?? '');
        if ((string)($employee['source_type'] ?? '') === EmployeeRef::SOURCE_TECHNICAL_STAFF) {
            if (!in_array($status, ['active', 'busy'], true)) {
                return false;
            }
        } elseif ($status !== 'active') {
            return false;
        }

        return !in_array((string)($state['relation_status'] ?? 'normal'), self::BLOCKED_RELATION_STATUSES, true);
    }
}
