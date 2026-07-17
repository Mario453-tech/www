<?php
declare(strict_types=1);

final class LogisticsStaffingService
{
    private const BLOCKED_RELATION_STATUSES = ['on_strike', 'leaving', 'inactive'];

    private readonly PDO $db;
    private readonly EmployeeAssignmentService $assignments;
    private readonly EmployeeRepository $employees;
    private readonly EmployeeStateService $states;
    private bool $runtimeEnabled;

    public function __construct(
        PDO $db,
        ?EmployeeAssignmentService $assignments = null,
        ?EmployeeRepository $employees = null,
        ?EmployeeStateService $states = null,
        ?bool $runtimeEnabled = null
    ) {
        EmployeeSystemBootstrap::ensure($db);
        $this->db = $db;
        $this->employees = $employees ?? new EmployeeRepository($db);
        $this->states = $states ?? new EmployeeStateService($db, $this->employees);
        $this->assignments = $assignments ?? new EmployeeAssignmentService($db, $this->employees, $this->states);
        $this->runtimeEnabled = $runtimeEnabled ?? $this->loadRuntimeEnabled();
    }

    public function isRuntimeEnabled(): bool
    {
        return $this->runtimeEnabled;
    }

    /**
     * @param array<string, mixed> $hub
     * @return array<string, mixed>
     */
    public function hubStaffing(array $hub): array
    {
        $hubId = (int)($hub['id'] ?? 0);
        $result = $this->hubStaffingForHubs([$hub]);
        if (!isset($result[$hubId])) {
            throw new InvalidArgumentException('Hub staffing requires hub id and player id.');
        }

        return $result[$hubId];
    }

    /**
     * Calculates staffing for all hubs with bounded queries per controlling player.
     * Oblicza obsade wszystkich hubow ograniczona liczba zapytan per gracz.
     *
     * @param list<array<string, mixed>> $hubs
     * @return array<int, array<string, mixed>>
     */
    public function hubStaffingForHubs(array $hubs): array
    {
        $hubsByPlayer = [];
        foreach ($hubs as $hub) {
            $hubId = (int)($hub['id'] ?? 0);
            $playerId = $this->controllingPlayerId($hub);
            if ($hubId <= 0 || $playerId <= 0) {
                continue;
            }
            $hubsByPlayer[$playerId][$hubId] = $hub;
        }

        $result = [];
        foreach ($hubsByPlayer as $playerId => $playerHubs) {
            $assignmentsByHub = $this->assignments->listForHubs((int)$playerId, array_keys($playerHubs));
            $employeeMap = $this->employeeMap((int)$playerId);
            $stateMap = $this->stateMap((int)$playerId);

            foreach ($playerHubs as $hubId => $hub) {
                $result[(int)$hubId] = $this->calculateHubStaffing(
                    $hub,
                    $assignmentsByHub[(int)$hubId] ?? [],
                    $employeeMap,
                    $stateMap
                );
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $hub
     * @param list<array<string, mixed>> $rows
     * @param array<string, array<string, mixed>> $employeeMap
     * @param array<string, array<string, mixed>> $stateMap
     * @return array<string, mixed>
     */
    private function calculateHubStaffing(
        array $hub,
        array $rows,
        array $employeeMap,
        array $stateMap
    ): array {
        $hubId = (int)$hub['id'];
        $ownerPlayerId = (int)($hub['player_id'] ?? 0);
        $tenantPlayerId = (int)($hub['tenant_player_id'] ?? 0);
        $playerId = $this->controllingPlayerId($hub);
        $requiredCount = $this->requiredHubStaffCount($hub);
        $activeRows = [];
        $allocationUnits = 0.0;
        $skillWeighted = 0.0;
        $moraleWeighted = 0.0;
        $hasHubOperator = false;

        foreach ($rows as $row) {
            $employeeKey = (string)$row['source_type'] . ':' . (int)$row['source_id'];
            $employee = $employeeMap[$employeeKey] ?? null;
            if ($employee === null || (string)($employee['status'] ?? '') !== 'active') {
                continue;
            }
            $state = $stateMap[$employeeKey] ?? [
                'morale' => 65.0,
                'relation_status' => 'normal',
            ];
            if (in_array((string)($state['relation_status'] ?? 'normal'), self::BLOCKED_RELATION_STATUSES, true)) {
                continue;
            }

            $allocation = max(0.0, min(100.0, (float)($row['allocation_pct'] ?? 0.0))) / 100.0;
            if ($allocation <= 0.0) {
                continue;
            }
            $skill = $this->employeeLogisticsSkill($employee);
            $morale = max(0.0, min(100.0, (float)($state['morale'] ?? 65.0)));
            $allocationUnits += $allocation;
            $skillWeighted += $skill * $allocation;
            $moraleWeighted += $morale * $allocation;
            $hasHubOperator = $hasHubOperator || (string)($employee['specialization_code'] ?? '') === 'hub_operator';
            $activeRows[] = [
                'assignment_id' => (int)$row['id'],
                'source_type' => (string)$row['source_type'],
                'source_id' => (int)$row['source_id'],
                'allocation_pct' => round($allocation * 100.0, 2),
                'skill' => $skill,
                'morale' => $morale,
                'specialization_code' => $employee['specialization_code'] ?? null,
            ];
        }

        $coveragePct = $requiredCount > 0
            ? min(100.0, round(($allocationUnits / $requiredCount) * 100.0, 2))
            : 100.0;
        $avgSkill = $allocationUnits > 0.0 ? round($skillWeighted / $allocationUnits, 2) : 0.0;
        $avgMorale = $allocationUnits > 0.0 ? round($moraleWeighted / $allocationUnits, 2) : 0.0;
        $base = $this->multipliersForCoverage($coveragePct);
        $qualityFactor = $this->qualityFactor($avgSkill, $avgMorale, $coveragePct);

        $throughputMult = round(max(0.35, min(1.15, $base['throughput_mult'] * $qualityFactor)), 4);
        $incidentRiskMult = round(max(0.60, min(2.50, $base['incident_risk_mult'] / max(0.75, $qualityFactor))), 4);
        $maintenanceCostMult = round(max(0.80, min(1.50, $base['maintenance_cost_mult'] / max(0.85, $qualityFactor))), 4);

        return [
            'hub_id' => $hubId,
            'player_id' => $playerId,
            'owner_player_id' => $ownerPlayerId,
            'tenant_player_id' => $tenantPlayerId,
            'required_count' => $requiredCount,
            'assigned_count' => count($activeRows),
            'coverage_pct' => $coveragePct,
            'average_skill' => $avgSkill,
            'average_morale' => $avgMorale,
            'missing_roles' => $hasHubOperator ? [] : ['hub_operator'],
            'throughput_mult' => $throughputMult,
            'incident_risk_mult' => $incidentRiskMult,
            'maintenance_cost_mult' => $maintenanceCostMult,
            'runtime_effects' => [
                'hub_throughput_pct' => round(($throughputMult - 1.0) * 100.0, 4),
            ],
            'runtime_incident_mods' => [
                'incident_mult' => $incidentRiskMult,
            ],
            'assignments' => $activeRows,
        ];
    }

    /**
     * @param array<string, mixed> $hub
     */
    private function controllingPlayerId(array $hub): int
    {
        $tenantPlayerId = (int)($hub['tenant_player_id'] ?? 0);
        if ($tenantPlayerId > 0) {
            return $tenantPlayerId;
        }

        $ownerPlayerId = (int)($hub['player_id'] ?? 0);
        return $ownerPlayerId > 0 ? $ownerPlayerId : 0;
    }

    /** @return array<string, array<string, mixed>> */
    private function employeeMap(int $playerId): array
    {
        $map = [];
        foreach ($this->employees->listForPlayer($playerId, null, false) as $employee) {
            $key = (string)$employee['source_type'] . ':' . (int)$employee['source_id'];
            $map[$key] = $employee;
        }
        return $map;
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
            $key = (string)$state['source_type'] . ':' . (int)$state['source_id'];
            $map[$key] = $state;
        }
        return $map;
    }

    private function loadRuntimeEnabled(): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT `value`
                   FROM well_config
                  WHERE `key` = 'employee_hub_staffing_enabled'
                  LIMIT 1"
            );
            $stmt->execute();
            $value = $stmt->fetchColumn();
            if ($value === false) {
                return false;
            }
            return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
        } catch (Throwable $e) {
            GameLog::error('LogisticsStaffingService', 'runtime flag read FAILED', $e);
            return false;
        }
    }

    /** @param array<string, mixed> $hub */
    private function requiredHubStaffCount(array $hub): int
    {
        $type = (string)($hub['hub_type'] ?? 'medium');
        $byType = ['small' => 1, 'medium' => 2, 'large' => 3];
        $slotLimit = max(1, (int)($hub['slot_limit'] ?? ($byType[$type] ?? 2)));
        return max(1, min(4, max($byType[$type] ?? 2, (int)ceil($slotLimit / 2))));
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

    /** @return array{throughput_mult: float, incident_risk_mult: float, maintenance_cost_mult: float} */
    private function multipliersForCoverage(float $coveragePct): array
    {
        if ($coveragePct <= 0.0) {
            return ['throughput_mult' => 0.60, 'incident_risk_mult' => 1.75, 'maintenance_cost_mult' => 1.25];
        }
        if ($coveragePct < 34.0) {
            return ['throughput_mult' => 0.70, 'incident_risk_mult' => 1.50, 'maintenance_cost_mult' => 1.18];
        }
        if ($coveragePct < 67.0) {
            return ['throughput_mult' => 0.85, 'incident_risk_mult' => 1.25, 'maintenance_cost_mult' => 1.10];
        }
        if ($coveragePct < 100.0) {
            return ['throughput_mult' => 0.95, 'incident_risk_mult' => 1.10, 'maintenance_cost_mult' => 1.04];
        }

        return ['throughput_mult' => 1.00, 'incident_risk_mult' => 1.00, 'maintenance_cost_mult' => 1.00];
    }

    private function qualityFactor(float $avgSkill, float $avgMorale, float $coveragePct): float
    {
        if ($coveragePct <= 0.0) {
            return 1.0;
        }

        $skillFactor = 0.90 + (max(1.0, min(10.0, $avgSkill)) / 10.0) * 0.20;
        $moraleFactor = 0.90 + (max(0.0, min(100.0, $avgMorale)) / 100.0) * 0.20;

        return max(0.75, min(1.15, $skillFactor * $moraleFactor));
    }
}
