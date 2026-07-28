<?php
declare(strict_types=1);

final class EmployeeStateService
{
    private readonly EmployeeRepository $employees;

    public function __construct(
        private readonly PDO $db,
        ?EmployeeRepository $employees = null,
        bool $ensureSchema = true
    )
    {
        if ($ensureSchema) {
            EmployeeSystemBootstrap::ensure($db);
        }
        $this->employees = $employees ?? new EmployeeRepository($db);
    }

    /** @return array<string, mixed>|null */
    public function getState(EmployeeRef $ref): ?array
    {
        $ref = $this->resolvePreferredRef($ref, true);
        return $this->getStateExact($ref);
    }

    /** @return array<string, mixed>|null */
    private function getStateExact(EmployeeRef $ref): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM employee_state
              WHERE player_id = :player_id
                AND source_type = :source_type
                AND source_id = :source_id
              LIMIT 1'
        );
        $stmt->execute([
            'player_id' => $ref->playerId,
            'source_type' => $ref->sourceType,
            'source_id' => $ref->sourceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->castState($row) : null;
    }

    /** @return array<string, mixed> */
    public function ensureState(EmployeeRef $ref): array
    {
        return $this->ensureStateResult($ref)['state'];
    }

    /**
     * Selects the same runtime state as canonical reconciliation without issuing extra queries.
     * Wybiera stan runtime jak migracja kanoniczna, ale bez dodatkowych zapytan.
     *
     * @param array<string, mixed>|null $legacyState
     * @param array<string, mixed>|null $canonicalState
     * @return array<string, mixed>|null
     */
    public function selectPreferredRuntimeState(?array $legacyState, ?array $canonicalState): ?array
    {
        if ($canonicalState === null) {
            return $legacyState;
        }
        if ($legacyState === null) {
            return $canonicalState;
        }

        return $this->shouldCopyLegacyMetrics($legacyState, $canonicalState)
            ? $legacyState
            : $canonicalState;
    }

    /**
     * Creates missing employee states in bounded batches instead of one query per employee.
     * Tworzy brakujace stany pracownikow porcjami zamiast zapytania per pracownik.
     *
     * @param list<array{ref: EmployeeRef, employee: array<string, mixed>}> $entries
     */
    public function ensureStatesForRecords(array $entries): void
    {
        $uniqueEntries = [];
        foreach ($entries as $entry) {
            $ref = $entry['ref'] ?? null;
            $employee = $entry['employee'] ?? null;
            if (!$ref instanceof EmployeeRef || !is_array($employee)) {
                throw new InvalidArgumentException('Invalid employee state batch entry.');
            }
            if ((int)($employee['player_id'] ?? 0) !== $ref->playerId) {
                throw new InvalidArgumentException('Employee state batch entry belongs to another player.');
            }
            $uniqueEntries[$ref->playerId . ':' . $ref->key()] = [
                'ref' => $ref,
                'employee' => $employee,
            ];
        }
        if ($uniqueEntries === []) {
            return;
        }

        $isSqlite = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $conflictClause = $isSqlite
            ? ' ON CONFLICT(source_type, source_id) DO NOTHING'
            : ' ON DUPLICATE KEY UPDATE source_id = VALUES(source_id)';

        foreach (array_chunk(array_values($uniqueEntries), 100) as $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $entry) {
                /** @var EmployeeRef $ref */
                $ref = $entry['ref'];
                /** @var array<string, mixed> $employee */
                $employee = $entry['employee'];
                $expectedSalary = $this->expectedSalaryFromEmployee($employee);
                $values[] = '(?, ?, ?, ?, 65, ?, ?, 0, 0, 0, \'normal\', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
                array_push(
                    $params,
                    $ref->playerId,
                    $ref->sourceType,
                    $ref->sourceId,
                    (string)($employee['department_code'] ?? ''),
                    $this->salarySatisfaction((float)($employee['salary'] ?? 0.0), $expectedSalary),
                    $expectedSalary
                );
            }

            $stmt = $this->db->prepare(
                'INSERT INTO employee_state
                    (player_id, source_type, source_id, department_code, morale,
                     salary_satisfaction, expected_salary, leave_risk, strike_support,
                     workload, relation_status, version, created_at, updated_at)
                 VALUES ' . implode(', ', $values) . $conflictClause
            );
            $stmt->execute($params);
        }
    }

    /**
     * @param array<string, mixed>|null $employee
     * @return array{state: array<string, mixed>, created: bool}
     */
    private function ensureStateResult(
        EmployeeRef $ref,
        ?array $employee = null,
        bool $alreadyCanonical = false
    ): array
    {
        if (!$alreadyCanonical) {
            [$ref, $employee] = $this->resolveRefAndEmployee($ref, $employee, true);
        }
        $employee ??= $this->employees->find($ref);
        if ($employee === null) {
            throw new RuntimeException('Employee source record does not exist or belongs to another player.');
        }

        $expectedSalary = $this->expectedSalaryFromEmployee($employee);
        $satisfaction = $this->salarySatisfaction((float)$employee['salary'], $expectedSalary);
        $isSqlite = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $conflictClause = $isSqlite
            ? ' ON CONFLICT(source_type, source_id) DO NOTHING'
            : ' ON DUPLICATE KEY UPDATE source_id = VALUES(source_id)';
        $stmt = $this->db->prepare(
            'INSERT INTO employee_state
                (player_id, source_type, source_id, department_code, morale,
                 salary_satisfaction, expected_salary, leave_risk, strike_support,
                 workload, relation_status, version, created_at, updated_at)
             VALUES
                (:player_id, :source_type, :source_id, :department_code, 65,
                 :salary_satisfaction, :expected_salary, 0, 0,
                 0, \'normal\', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)' . $conflictClause
        );
        $stmt->execute([
            'player_id' => $ref->playerId,
            'source_type' => $ref->sourceType,
            'source_id' => $ref->sourceId,
            'department_code' => (string)$employee['department_code'],
            'salary_satisfaction' => $satisfaction,
            'expected_salary' => $expectedSalary,
        ]);
        $created = $stmt->rowCount() === 1;

        $state = $this->getStateExact($ref);
        if ($state === null) {
            throw new RuntimeException('Employee state could not be created.');
        }
        return ['state' => $state, 'created' => $created];
    }

    public function calculateExpectedSalary(EmployeeRef $ref): float
    {
        [$ref, $employee] = $this->resolveRefAndEmployee($ref, null, true);
        if ($employee === null) {
            throw new RuntimeException('Employee source record does not exist or belongs to another player.');
        }

        return $this->expectedSalaryFromEmployee($employee);
    }

    /** @return array<string, mixed> */
    public function updateSalarySatisfaction(EmployeeRef $ref): array
    {
        [$ref, $employee] = $this->resolveRefAndEmployee($ref, null, true);
        if ($employee === null) {
            throw new RuntimeException('Employee source record does not exist or belongs to another player.');
        }
        $this->ensureState($ref);

        $expectedSalary = $this->expectedSalaryFromEmployee($employee);
        $satisfaction = $this->salarySatisfaction((float)$employee['salary'], $expectedSalary);
        $stmt = $this->db->prepare(
            'UPDATE employee_state
                SET expected_salary = :expected_salary,
                    salary_satisfaction = :salary_satisfaction,
                    version = version + 1,
                    updated_at = CURRENT_TIMESTAMP
              WHERE player_id = :player_id
                AND source_type = :source_type
                AND source_id = :source_id'
        );
        $stmt->execute([
            'expected_salary' => $expectedSalary,
            'salary_satisfaction' => $satisfaction,
            'player_id' => $ref->playerId,
            'source_type' => $ref->sourceType,
            'source_id' => $ref->sourceId,
        ]);

        return $this->getState($ref) ?? throw new RuntimeException('Employee state disappeared during update.');
    }

    private function resolvePreferredRef(EmployeeRef $ref, bool $repairState = false): EmployeeRef
    {
        $canonical = $this->employees->canonicalRef($ref);
        if ($canonical->key() === $ref->key()) {
            return $canonical;
        }

        if ($repairState) {
            $this->reconcileCanonicalState($ref, $canonical);
        }

        return $this->employees->find($canonical) !== null ? $canonical : $ref;
    }

    /**
     * @param array<string, mixed>|null $employee
     * @return array{0: EmployeeRef, 1: array<string, mixed>|null}
     */
    private function resolveRefAndEmployee(EmployeeRef $ref, ?array $employee = null, bool $repairState = false): array
    {
        $canonical = $this->employees->canonicalRef($ref);
        $canonicalEmployee = $employee;
        if ($canonical->key() !== $ref->key()) {
            if ($repairState) {
                $this->reconcileCanonicalState($ref, $canonical);
            }
            $canonicalEmployee = $employee ?? $this->employees->find($canonical);
            if ($canonicalEmployee !== null) {
                return [$canonical, $canonicalEmployee];
            }
        }

        return [$ref, $employee ?? $this->employees->find($ref)];
    }

    private function reconcileCanonicalState(EmployeeRef $legacyRef, EmployeeRef $canonicalRef): void
    {
        if ($legacyRef->key() === $canonicalRef->key()) {
            return;
        }

        $canonicalEmployee = $this->employees->find($canonicalRef);
        if ($canonicalEmployee === null) {
            return;
        }

        $legacyState = $this->getStateExact($legacyRef);
        if ($legacyState === null) {
            return;
        }

        $canonicalState = $this->getStateExact($canonicalRef);
        if ($canonicalState === null) {
            $stmt = $this->db->prepare(
                'UPDATE employee_state
                    SET source_type = :source_type,
                        source_id = :source_id,
                        updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id
                    AND player_id = :player_id'
            );
            $stmt->execute([
                'source_type' => $canonicalRef->sourceType,
                'source_id' => $canonicalRef->sourceId,
                'id' => (int)$legacyState['id'],
                'player_id' => $legacyRef->playerId,
            ]);
            $this->refreshStateSnapshot($canonicalRef, $canonicalEmployee);
            return;
        }

        if ($this->shouldCopyLegacyMetrics($legacyState, $canonicalState)) {
            $stmt = $this->db->prepare(
                'UPDATE employee_state
                    SET morale = :morale,
                        leave_risk = :leave_risk,
                        strike_support = :strike_support,
                        workload = :workload,
                        relation_status = :relation_status,
                        last_raise_at = :last_raise_at,
                        last_raise_request_at = :last_raise_request_at,
                        last_morale_tick_at = :last_morale_tick_at,
                        version = :version,
                        updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id
                    AND player_id = :player_id'
            );
            $stmt->execute([
                'morale' => $legacyState['morale'],
                'leave_risk' => $legacyState['leave_risk'],
                'strike_support' => $legacyState['strike_support'],
                'workload' => $legacyState['workload'],
                'relation_status' => $legacyState['relation_status'],
                'last_raise_at' => $legacyState['last_raise_at'],
                'last_raise_request_at' => $legacyState['last_raise_request_at'],
                'last_morale_tick_at' => $legacyState['last_morale_tick_at'],
                'version' => max((int)$legacyState['version'], (int)$canonicalState['version']) + 1,
                'id' => (int)$canonicalState['id'],
                'player_id' => $canonicalRef->playerId,
            ]);
        }

        $this->refreshStateSnapshot($canonicalRef, $canonicalEmployee);
        $delete = $this->db->prepare('DELETE FROM employee_state WHERE id = ? AND player_id = ?');
        $delete->execute([(int)$legacyState['id'], $legacyRef->playerId]);
    }

    /**
     * Canonical state keeps relation metrics, but compensation snapshot must match the current source record.
     * Stan kanoniczny zachowuje metryki relacji, ale snapshot wynagrodzenia musi odpowiadac biezacemu rekordowi.
     *
     * @param array<string, mixed> $employee
     */
    private function refreshStateSnapshot(EmployeeRef $ref, array $employee): void
    {
        $expectedSalary = $this->expectedSalaryFromEmployee($employee);
        $salarySatisfaction = $this->salarySatisfaction((float)$employee['salary'], $expectedSalary);

        $stmt = $this->db->prepare(
            'UPDATE employee_state
                SET department_code = :department_code,
                    expected_salary = :expected_salary,
                    salary_satisfaction = :salary_satisfaction,
                    updated_at = CURRENT_TIMESTAMP
              WHERE player_id = :player_id
                AND source_type = :source_type
                AND source_id = :source_id'
        );
        $stmt->execute([
            'department_code' => (string)$employee['department_code'],
            'expected_salary' => $expectedSalary,
            'salary_satisfaction' => $salarySatisfaction,
            'player_id' => $ref->playerId,
            'source_type' => $ref->sourceType,
            'source_id' => $ref->sourceId,
        ]);
    }

    /**
     * Legacy state wins only when canonical data still looks pristine or is older than the legacy snapshot.
     * Stary stan wygrywa tylko wtedy, gdy stan kanoniczny wyglada nadal dziewiczo albo jest starszy od starego snapshotu.
     *
     * @param array<string, mixed> $legacyState
     * @param array<string, mixed> $canonicalState
     */
    private function shouldCopyLegacyMetrics(array $legacyState, array $canonicalState): bool
    {
        if ($this->stateLooksPristine($canonicalState) && !$this->stateLooksPristine($legacyState)) {
            return true;
        }

        $legacyUpdatedAt = strtotime((string)($legacyState['updated_at'] ?? '')) ?: 0;
        $canonicalUpdatedAt = strtotime((string)($canonicalState['updated_at'] ?? '')) ?: 0;

        return $legacyUpdatedAt > $canonicalUpdatedAt;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function stateLooksPristine(array $state): bool
    {
        return (int)($state['version'] ?? 1) <= 1
            && (float)($state['morale'] ?? 65.0) === 65.0
            && (float)($state['leave_risk'] ?? 0.0) === 0.0
            && (float)($state['strike_support'] ?? 0.0) === 0.0
            && (float)($state['workload'] ?? 0.0) === 0.0
            && (string)($state['relation_status'] ?? 'normal') === 'normal';
    }

    /** @return list<array<string, mixed>> */
    public function listAtRiskEmployees(int $playerId, int $limit = 50, int $offset = 0): array
    {
        if ($playerId <= 0 || $limit < 1 || $limit > 500 || $offset < 0) {
            throw new InvalidArgumentException('Invalid employee risk list arguments.');
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM employee_state
              WHERE player_id = :player_id
                AND (morale < 45 OR leave_risk >= 50 OR strike_support >= 50 OR relation_status <> 'normal')
              ORDER BY CASE WHEN leave_risk >= strike_support THEN leave_risk ELSE strike_support END DESC,
                       morale ASC,
                       id ASC
              LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row): array => $this->castState($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Dry-run is the default so existing employee data is never changed implicitly.
     * Tryb raportu jest domyslny, aby istniejace dane pracownikow nie byly zmieniane automatycznie.
     *
     * @return array<string, mixed>
     */
    public function backfillEmployeeState(bool $apply = false, ?int $playerId = null): array
    {
        $employees = $playerId === null
            ? $this->employees->listAll(null, true)
            : $this->employees->listForPlayer($playerId, null, true);
        $legacyMirrorMap = $this->employees->sourceLinkMap($playerId);
        foreach ($this->employees->findLegacyMirrorCandidates($playerId) as $candidate) {
            $legacyMirrorMap[$candidate['player_id'] . ':' . $candidate['board_member_id']] =
                new EmployeeRef(
                    EmployeeRef::SOURCE_TECHNICAL_STAFF,
                    $candidate['technical_staff_id'],
                    $candidate['player_id']
                );
        }
        $linksCreated = $apply ? $this->employees->syncLegacyMirrorLinks($playerId) : 0;
        $duplicateGroups = $this->findSuspectedDuplicateGroups($employees);
        $canonicalEmployees = [];
        $mirroredSkipped = 0;
        foreach ($employees as $employee) {
            $sourceRef = new EmployeeRef(
                (string)$employee['source_type'],
                (int)$employee['source_id'],
                (int)$employee['player_id']
            );
            $mirrorKey = $sourceRef->playerId . ':' . $sourceRef->sourceId;
            $ref = $sourceRef->sourceType === EmployeeRef::SOURCE_BOARD_MEMBER
                && isset($legacyMirrorMap[$mirrorKey])
                    ? $legacyMirrorMap[$mirrorKey]
                    : $sourceRef;
            $canonicalKey = $ref->playerId . ':' . $ref->key();
            if (isset($canonicalEmployees[$canonicalKey])) {
                $mirroredSkipped++;
            }
            if (!isset($canonicalEmployees[$canonicalKey])
                || $employee['source_type'] === EmployeeRef::SOURCE_TECHNICAL_STAFF) {
                $canonicalEmployees[$canonicalKey] = ['ref' => $ref, 'employee' => $employee];
            }
        }
        $existingStates = $this->existingStateKeys($playerId);
        $created = 0;
        $skipped = 0;
        $wouldCreate = 0;
        $errors = [];

        foreach ($canonicalEmployees as $canonicalKey => $entry) {
            /** @var EmployeeRef $ref */
            $ref = $entry['ref'];
            /** @var array<string, mixed> $employee */
            $employee = $entry['employee'];
            if (isset($existingStates[$canonicalKey])) {
                $skipped++;
                continue;
            }
            if (!$apply) {
                $wouldCreate++;
                continue;
            }

            try {
                $result = $this->ensureStateResult($ref, $employee, true);
                if ($result['created']) {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $errors[] = [
                    'employee' => $ref->key(),
                    'player_id' => $ref->playerId,
                    'error' => $exception->getMessage(),
                ];
                GameLog::error('EmployeeStateService', 'employee state backfill FAILED', $exception, [
                    'employee' => $ref->key(),
                    'player_id' => $ref->playerId,
                ]);
            }
        }

        return [
            'applied' => $apply,
            'employees_checked' => count($employees),
            'created' => $created,
            'would_create' => $wouldCreate,
            'skipped' => $skipped,
            'mirrored_records_skipped' => $mirroredSkipped,
            'legacy_links_created' => $linksCreated,
            'errors' => $errors,
            'suspected_duplicate_groups' => $duplicateGroups,
        ];
    }

    /** @return array<string, true> */
    private function existingStateKeys(?int $playerId): array
    {
        $sql = 'SELECT player_id, source_type, source_id FROM employee_state';
        $params = [];
        if ($playerId !== null) {
            $sql .= ' WHERE player_id = :player_id';
            $params['player_id'] = $playerId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $keys[(int)$row['player_id'] . ':' . (string)$row['source_type'] . ':' . (int)$row['source_id']] = true;
        }
        return $keys;
    }

    /** @param array<string, mixed> $employee */
    private function expectedSalaryFromEmployee(array $employee): float
    {
        $currentSalary = max(0.0, (float)$employee['salary']);
        $minimum = isset($employee['salary_range_min']) ? (float)$employee['salary_range_min'] : 0.0;
        $maximum = isset($employee['salary_range_max']) ? (float)$employee['salary_range_max'] : 0.0;
        if ($minimum <= 0.0 || $maximum < $minimum) {
            [$minimum, $maximum] = $this->fallbackSalaryRange($employee, $currentSalary);
        }

        $employeeSkills = (array)$employee['skills'];
        if ($employee['source_type'] === EmployeeRef::SOURCE_TECHNICAL_STAFF && isset($employeeSkills['role_skill'])) {
            $averageSkill = (float)$employeeSkills['role_skill'];
        } else {
            $skills = array_values(array_filter(
                $employeeSkills,
                static fn(mixed $value): bool => is_numeric($value)
            ));
            $averageSkill = $skills === [] ? 5.0 : array_sum($skills) / count($skills);
        }
        $skillFactor = 0.85 + (max(0.0, min(10.0, $averageSkill)) / 10.0) * 0.30;
        $experienceFactor = 1.0 + min(20, max(0, (int)$employee['experience_years'])) * 0.01;
        $expected = (($minimum + $maximum) / 2.0) * $skillFactor * $experienceFactor;

        return round(max($minimum * 0.4, min($maximum * 0.9, $expected)), 2);
    }

    /**
     * Legacy technical roles can miss hr_specializations salary ranges on fresh or older schemas.
     * Starsze role techniczne moga nie miec zakresow pensji w hr_specializations na swiezych albo starszych schematach.
     *
     * @param array<string, mixed> $employee
     * @return array{0:float,1:float}
     */
    private function fallbackSalaryRange(array $employee, float $currentSalary): array
    {
        if ($currentSalary <= 0.0) {
            $currentSalary = $employee['source_type'] === EmployeeRef::SOURCE_TECHNICAL_STAFF ? 9000.0 : 10000.0;
        }

        $floor = $employee['source_type'] === EmployeeRef::SOURCE_TECHNICAL_STAFF ? 6000.0 : 8000.0;
        $minimum = max($floor, $currentSalary * 0.9);
        $maximum = max($minimum + 1000.0, $currentSalary * 1.35);

        return [round($minimum, 2), round($maximum, 2)];
    }

    private function salarySatisfaction(float $salary, float $expectedSalary): float
    {
        if ($expectedSalary <= 0.0) {
            return 100.0;
        }
        return round(max(0.0, min(120.0, ($salary / $expectedSalary) * 100.0)), 2);
    }

    /**
     * Duplicate candidates are reported only; source rows are never merged or deleted here.
     * Podejrzane duplikaty sa tylko raportowane; rekordy zrodlowe nie sa tu laczone ani usuwane.
     *
     * @param list<array<string, mixed>> $employees
     * @return list<array<string, mixed>>
     */
    private function findSuspectedDuplicateGroups(array $employees): array
    {
        $groups = [];
        foreach ($employees as $employee) {
            $name = trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']);
            $normalizedName = function_exists('mb_strtolower')
                ? mb_strtolower($name, 'UTF-8')
                : strtolower($name);
            $key = (int)$employee['player_id'] . '|' . $normalizedName;
            $groups[$key][] = [
                'source_type' => (string)$employee['source_type'],
                'source_id' => (int)$employee['source_id'],
                'department_code' => (string)$employee['department_code'],
                'role_code' => (string)$employee['role_code'],
                'hired_at' => $employee['hired_at'],
            ];
        }

        $duplicates = [];
        foreach ($groups as $key => $members) {
            $sourceTypes = array_unique(array_column($members, 'source_type'));
            if (count($members) > 1 && count($sourceTypes) > 1) {
                $duplicates[] = ['identity' => $key, 'employees' => $members];
            }
        }
        return $duplicates;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function castState(array $row): array
    {
        foreach (['id', 'player_id', 'source_id', 'version'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        foreach (['morale', 'salary_satisfaction', 'expected_salary', 'leave_risk', 'strike_support', 'workload'] as $key) {
            $row[$key] = (float)$row[$key];
        }
        return $row;
    }
}
