<?php
declare(strict_types=1);

final class EmployeeAssignmentService
{
    private const TARGET_HUB = 'hub';
    private const TARGET_PIPELINE = 'pipeline';
    private const BLOCKED_RELATION_STATUSES = ['on_strike', 'leaving', 'inactive'];

    private readonly EmployeeRepository $employees;
    private readonly EmployeeStateService $states;

    public function __construct(private readonly PDO $db, ?EmployeeRepository $employees = null, ?EmployeeStateService $states = null)
    {
        EmployeeSystemBootstrap::ensure($db);
        $this->employees = $employees ?? new EmployeeRepository($db);
        $this->states = $states ?? new EmployeeStateService($db, $this->employees);
    }

    /** @return array<string, mixed> */
    public function assignToHub(EmployeeRef $ref, int $hubId, float $allocationPct = 100.0): array
    {
        return $this->assign($ref, self::TARGET_HUB, $hubId, $allocationPct);
    }

    /** @return array<string, mixed> */
    public function assignToPipeline(EmployeeRef $ref, int $pipelineId, float $allocationPct = 100.0): array
    {
        return $this->assign($ref, self::TARGET_PIPELINE, $pipelineId, $allocationPct);
    }

    /** @return array<string, mixed> */
    public function assign(EmployeeRef $ref, string $targetType, int $targetId, float $allocationPct = 100.0): array
    {
        $targetType = $this->normalizeTargetType($targetType);
        $targetId = $this->positiveId($targetId, 'Target identifier must be positive.');
        $allocationPct = $this->normalizeAllocation($allocationPct);
        $ref = $this->employees->canonicalRef($ref);
        $lockName = $this->lockName($ref);

        if ($this->db->inTransaction()) {
            throw new RuntimeException('Employee assignment must own its transaction.');
        }

        $startedTransaction = false;
        $lockAcquired = false;
        try {
            $this->acquireLock($lockName);
            $lockAcquired = true;
            $this->db->beginTransaction();
            $startedTransaction = true;

            $employee = $this->employeeForAssignment($ref);
            $this->assertTargetOwned($ref->playerId, $targetType, $targetId);
            $state = $this->states->ensureState($ref);
            $this->assertRelationAllowsAssignment($state);

            $existing = $this->activeAssignmentForTarget($ref, $targetType, $targetId);
            $excludeId = $existing !== null ? (int)$existing['id'] : null;
            $this->assertAllocationAvailable($ref, $allocationPct, $excludeId);

            if ($existing !== null) {
                $stmt = $this->db->prepare(
                    'UPDATE employee_assignments
                        SET allocation_pct = :allocation_pct,
                            updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id
                        AND player_id = :player_id'
                );
                $stmt->execute([
                    'allocation_pct' => $allocationPct,
                    'id' => $excludeId,
                    'player_id' => $ref->playerId,
                ]);
                $assignmentId = $excludeId;
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO employee_assignments
                        (player_id, source_type, source_id, target_type, target_id,
                         allocation_pct, status, assigned_at, created_at, updated_at)
                     VALUES
                        (:player_id, :source_type, :source_id, :target_type, :target_id,
                         :allocation_pct, \'active\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
                );
                $stmt->execute([
                    'player_id' => $ref->playerId,
                    'source_type' => $ref->sourceType,
                    'source_id' => $ref->sourceId,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'allocation_pct' => $allocationPct,
                ]);
                $assignmentId = (int)$this->db->lastInsertId();
            }

            if ($startedTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'assignment_id' => $assignmentId,
                'employee' => $employee,
                'allocation_pct' => $allocationPct,
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        } finally {
            if ($lockAcquired) {
                $this->releaseLock($lockName);
            }
        }
    }

    public function release(int $assignmentId, int $playerId): bool
    {
        $assignmentId = $this->positiveId($assignmentId, 'Assignment identifier must be positive.');
        $playerId = $this->positiveId($playerId, 'Player identifier must be positive.');
        $stmt = $this->db->prepare(
            "UPDATE employee_assignments
                SET status = 'released',
                    released_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = ?
                AND player_id = ?
                AND status = 'active'"
        );
        $stmt->execute([$assignmentId, $playerId]);

        return $stmt->rowCount() === 1;
    }

    public function releaseHub(int $assignmentId, int $playerId): bool
    {
        return $this->releaseForTarget($assignmentId, $playerId, self::TARGET_HUB);
    }

    public function releasePipeline(int $assignmentId, int $playerId): bool
    {
        return $this->releaseForTarget($assignmentId, $playerId, self::TARGET_PIPELINE);
    }

    public function releaseEmployeeAssignments(EmployeeRef $ref): int
    {
        $ref = $this->employees->canonicalRef($ref);
        $stmt = $this->db->prepare(
            "UPDATE employee_assignments
                SET status = 'released',
                    released_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
              WHERE player_id = ?
                AND source_type = ?
                AND source_id = ?
                AND status = 'active'"
        );
        $stmt->execute([$ref->playerId, $ref->sourceType, $ref->sourceId]);

        return $stmt->rowCount();
    }

    /**
     * Releases active assignments for selected employee source rows.
     * Zamyka aktywne przypisania wskazanych rekordow pracownikow.
     *
     * @param list<int> $sourceIds
     */
    public function releaseAssignmentsForSources(int $playerId, string $sourceType, array $sourceIds): int
    {
        $playerId = $this->positiveId($playerId, 'Player identifier must be positive.');
        if (!in_array($sourceType, [EmployeeRef::SOURCE_BOARD_MEMBER, EmployeeRef::SOURCE_TECHNICAL_STAFF], true)) {
            throw new InvalidArgumentException('Unsupported employee source type.');
        }
        $sourceIds = array_values(array_unique(array_filter(
            array_map('intval', $sourceIds),
            static fn(int $sourceId): bool => $sourceId > 0
        )));
        if ($sourceIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
        $stmt = $this->db->prepare(
            "UPDATE employee_assignments
                SET status = 'released',
                    released_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
              WHERE player_id = ?
                AND source_type = ?
                AND source_id IN ({$placeholders})
                AND status = 'active'"
        );
        $stmt->execute(array_merge([$playerId, $sourceType], $sourceIds));

        return $stmt->rowCount();
    }

    /** @return list<array<string, mixed>> */
    public function listForHub(int $playerId, int $hubId): array
    {
        return $this->listForTarget($playerId, self::TARGET_HUB, $hubId);
    }

    /** @return list<array<string, mixed>> */
    public function listForPipeline(int $playerId, int $pipelineId): array
    {
        return $this->listForTarget($playerId, self::TARGET_PIPELINE, $pipelineId);
    }

    /**
     * Loads active hub assignments in one query.
     * Laduje aktywne przypisania hubow jednym zapytaniem.
     *
     * @param list<int> $hubIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listForHubs(int $playerId, array $hubIds): array
    {
        return $this->listForTargets($playerId, self::TARGET_HUB, $hubIds);
    }

    /**
     * Loads active pipeline assignments in one query.
     * Laduje aktywne przypisania rurociagow jednym zapytaniem.
     *
     * @param list<int> $pipelineIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listForPipelines(int $playerId, array $pipelineIds): array
    {
        return $this->listForTargets($playerId, self::TARGET_PIPELINE, $pipelineIds);
    }

    /**
     * Loads active assignments for one target type in one query.
     * Laduje aktywne przypisania jednego typu celu jednym zapytaniem.
     *
     * @param list<int> $targetIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function listForTargets(int $playerId, string $targetType, array $targetIds): array
    {
        $playerId = $this->positiveId($playerId, 'Player identifier must be positive.');
        $targetType = $this->normalizeTargetType($targetType);
        $targetIds = array_values(array_unique(array_filter(
            array_map('intval', $targetIds),
            static fn(int $targetId): bool => $targetId > 0
        )));
        if ($targetIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT *
               FROM employee_assignments
              WHERE player_id = ?
                AND target_type = ?
                AND target_id IN ({$placeholders})
                AND status = 'active'
              ORDER BY target_id ASC, id ASC"
        );
        $stmt->execute(array_merge([$playerId, $targetType], $targetIds));

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[(int)$row['target_id']][] = $row;
        }

        return $grouped;
    }

    /** @return list<array<string, mixed>> */
    public function listForTarget(int $playerId, string $targetType, int $targetId): array
    {
        $targetType = $this->normalizeTargetType($targetType);
        $stmt = $this->db->prepare(
            "SELECT *
               FROM employee_assignments
              WHERE player_id = ?
                AND target_type = ?
                AND target_id = ?
                AND status = 'active'
              ORDER BY id ASC"
        );
        $stmt->execute([
            $this->positiveId($playerId, 'Player identifier must be positive.'),
            $targetType,
            $this->positiveId($targetId, 'Target identifier must be positive.'),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function releaseForTarget(int $assignmentId, int $playerId, string $targetType): bool
    {
        $assignmentId = $this->positiveId($assignmentId, 'Assignment identifier must be positive.');
        $playerId = $this->positiveId($playerId, 'Player identifier must be positive.');
        $targetType = $this->normalizeTargetType($targetType);
        $stmt = $this->db->prepare(
            "UPDATE employee_assignments
                SET status = 'released',
                    released_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = ?
                AND player_id = ?
                AND target_type = ?
                AND status = 'active'"
        );
        $stmt->execute([$assignmentId, $playerId, $targetType]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<string, mixed> */
    private function employeeForAssignment(EmployeeRef $ref): array
    {
        $employee = $this->employees->find($ref);
        if ($employee === null || (int)($employee['player_id'] ?? 0) !== $ref->playerId) {
            throw new RuntimeException('Employee does not belong to this player.');
        }
        if ((string)($employee['status'] ?? '') !== 'active') {
            throw new RuntimeException('Employee is not active.');
        }

        return $employee;
    }

    private function assertTargetOwned(int $playerId, string $targetType, int $targetId): void
    {
        if ($targetType === self::TARGET_HUB) {
            $this->assertHubOwned($playerId, $targetId);
            return;
        }

        if ($targetType === self::TARGET_PIPELINE) {
            $this->assertPipelineOwned($playerId, $targetId);
            return;
        }

        throw new InvalidArgumentException('Unsupported assignment target type.');
    }

    private function assertHubOwned(int $playerId, int $hubId): void
    {
        $stmt = $this->db->prepare(
            "SELECT status
               FROM logistics_hubs
              WHERE id = ?
                AND (player_id = ? OR tenant_player_id = ?)
              LIMIT 1"
        );
        $stmt->execute([$hubId, $playerId, $playerId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            throw new RuntimeException('Hub does not belong to this player.');
        }
        if (in_array((string)$status, ['planned', 'building', 'disabled', 'paused', 'maintenance'], true)) {
            throw new RuntimeException('Hub is not available for staffing.');
        }
    }

    private function assertPipelineOwned(int $playerId, int $pipelineId): void
    {
        $stmt = $this->db->prepare(
            "SELECT status
               FROM well_pipelines
              WHERE id = ?
                AND player_id = ?
              LIMIT 1"
        );
        $stmt->execute([$pipelineId, $playerId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            throw new RuntimeException('Pipeline does not belong to this player.');
        }
        if (in_array((string)$status, ['planned', 'building', 'disabled'], true)) {
            throw new RuntimeException('Pipeline is not available for staffing.');
        }
    }

    /** @param array<string, mixed> $state */
    private function assertRelationAllowsAssignment(array $state): void
    {
        $status = (string)($state['relation_status'] ?? 'normal');
        if (in_array($status, self::BLOCKED_RELATION_STATUSES, true)) {
            throw new RuntimeException('Employee relation status blocks assignment.');
        }
    }

    private function assertAllocationAvailable(EmployeeRef $ref, float $allocationPct, ?int $excludeAssignmentId = null): void
    {
        $params = [$ref->playerId, $ref->sourceType, $ref->sourceId];
        $extra = '';
        if ($excludeAssignmentId !== null) {
            $extra = ' AND id <> ?';
            $params[] = $excludeAssignmentId;
        }

        $forUpdate = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(allocation_pct), 0)
               FROM employee_assignments
              WHERE player_id = ?
                AND source_type = ?
                AND source_id = ?
                AND status = 'active'{$extra}{$forUpdate}"
        );
        $stmt->execute($params);
        $used = (float)$stmt->fetchColumn();
        if (($used + $allocationPct) > 100.0001) {
            throw new RuntimeException('Employee assignment allocation exceeds 100%.');
        }
    }

    /** @return array<string, mixed>|null */
    private function activeAssignmentForTarget(EmployeeRef $ref, string $targetType, int $targetId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
               FROM employee_assignments
              WHERE player_id = ?
                AND source_type = ?
                AND source_id = ?
                AND target_type = ?
                AND target_id = ?
                AND status = 'active'
              LIMIT 1"
        );
        $stmt->execute([$ref->playerId, $ref->sourceType, $ref->sourceId, $targetType, $targetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function normalizeTargetType(string $targetType): string
    {
        $targetType = trim($targetType);
        if (!in_array($targetType, [self::TARGET_HUB, self::TARGET_PIPELINE, 'port', 'department', 'well'], true)) {
            throw new InvalidArgumentException('Unsupported assignment target type.');
        }

        return $targetType;
    }

    private function normalizeAllocation(float $allocationPct): float
    {
        if ($allocationPct <= 0.0 || $allocationPct > 100.0) {
            throw new InvalidArgumentException('Allocation must be greater than 0 and not greater than 100%.');
        }

        return round($allocationPct, 2);
    }

    private function positiveId(int $id, string $message): int
    {
        if ($id <= 0) {
            throw new InvalidArgumentException($message);
        }

        return $id;
    }

    private function lockName(EmployeeRef $ref): string
    {
        return 'employee_assignment:' . $ref->playerId . ':' . $ref->sourceType . ':' . $ref->sourceId;
    }

    private function acquireLock(string $lockName): void
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 5)');
        $stmt->execute([$lockName]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Employee assignment is busy.');
        }
    }

    private function releaseLock(string $lockName): void
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lockName]);
        } catch (Throwable) {
        }
    }
}
