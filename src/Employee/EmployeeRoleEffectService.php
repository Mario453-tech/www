<?php
declare(strict_types=1);

final class EmployeeRoleEffectService
{
    private const BLOCKED_RELATION_STATUSES = ['on_strike', 'leaving', 'inactive'];
    private const LOGISTICS_MANAGER_FALLBACK_SPECIALIZATION = 'oil_flow_analyst';
    private const EFFECT_TYPES = ['percent', 'flat', 'multiplier', 'bool'];
    private const TARGET_SCOPES = ['department', 'hub', 'pipeline', 'warehouse', 'road_transport', 'port', 'b2b', 'well', 'global'];

    private readonly EmployeeRepository $employees;
    private readonly EmployeeStateService $employeeState;

    public function __construct(
        private readonly PDO $db,
        ?EmployeeRepository $employees = null,
        ?EmployeeStateService $employeeState = null
    ) {
        EmployeeSystemBootstrap::ensure($db);
        $this->employees = $employees ?? new EmployeeRepository($db);
        $this->employeeState = $employeeState ?? new EmployeeStateService($db, $this->employees);
    }

    /** @return list<array<string, mixed>> */
    public function getEffectsForSpecialization(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT *
               FROM employee_role_effects
              WHERE specialization_code = ?
                AND is_active = 1
              ORDER BY target_scope ASC, effect_key ASC, id ASC'
        );
        $stmt->execute([$code]);

        return array_map([$this, 'normalizeEffectRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function calculateEffects(EmployeeRef $employee, string $targetScope, array $context = []): array
    {
        $targetScope = $this->normalizeTargetScope($targetScope);
        $originalEmployee = $employee;
        $employee = $this->employees->canonicalRef($employee);
        $usesCanonicalEmployee = $employee->key() !== $originalEmployee->key();
        $record = $this->employees->find($employee);
        if ($record === null && $usesCanonicalEmployee) {
            $employee = $originalEmployee;
            $record = $this->employees->find($employee);
            $usesCanonicalEmployee = false;
        }
        if ($record === null) {
            throw new RuntimeException('Employee source record does not exist or belongs to another player.');
        }

        $specializationCode = $this->resolveEffectSpecializationCode($record);
        if ($specializationCode === null) {
            return [
                'employee' => $this->employeeMeta($employee, $record),
                'specialization_code' => null,
                'target_scope' => $targetScope,
                'morale_factor' => $this->moraleFactor(65.0),
                'morale' => 65.0,
                'effects' => [],
                'context' => $context,
            ];
        }

        $state = $usesCanonicalEmployee
            ? $this->employeeState->ensureState($originalEmployee)
            : $this->employeeState->ensureState($employee);
        $morale = (float)($state['morale'] ?? 65.0);
        $moraleFactor = $this->moraleFactor($morale);
        if (!$this->isOperationalForEffects($record, $state)) {
            return $this->buildResultPayload(
                $employee,
                $record,
                $specializationCode,
                $targetScope,
                $morale,
                $moraleFactor,
                [],
                $context
            );
        }

        $scopes = $this->relatedScopes($targetScope);
        $effects = $this->compileEffects($record, $specializationCode, $scopes, $moraleFactor);

        return $this->buildResultPayload(
            $employee,
            $record,
            $specializationCode,
            $targetScope,
            $morale,
            $moraleFactor,
            $effects,
            $context
        );
    }

    /**
     * Loads runtime effects for selected specializations without per-employee read queries.
     * Laduje efekty runtime wybranych specjalizacji bez zapytan odczytu per pracownik.
     *
     * @param array<string, string|list<string>> $scopeBySpecialization
     * @param list<array<string, mixed>>|null $employees
     * @param array<string, array<string, mixed>>|null $states
     * @param array<string, EmployeeRef>|null $linkMap
     * @return list<array<string, mixed>>
     */
    public function calculatePlayerEffects(
        int $playerId,
        array $scopeBySpecialization,
        ?array $employees = null,
        ?array $states = null,
        ?array $linkMap = null
    ): array {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }

        $normalizedScopes = [];
        foreach ($scopeBySpecialization as $specializationCode => $targetScopes) {
            $specializationCode = trim((string)$specializationCode);
            if ($specializationCode === '') {
                continue;
            }
            $targetScopes = is_array($targetScopes) ? $targetScopes : [$targetScopes];
            $scopes = [];
            foreach ($targetScopes as $targetScope) {
                $scopes[] = $this->normalizeTargetScope((string)$targetScope);
            }
            if ($scopes !== []) {
                $normalizedScopes[$specializationCode] = array_values(array_unique($scopes));
            }
        }
        if ($normalizedScopes === []) {
            return [];
        }

        $employees ??= $this->employees->listForPlayer($playerId, null, true);
        $employeeByKey = [];
        foreach ($employees as $employee) {
            $employeeByKey[$this->employeeRecordKey($employee)] = $employee;
        }

        $linkMap ??= $this->employees->sourceLinkMap($playerId);
        $states ??= $this->loadPlayerStates($playerId);
        $selected = [];
        foreach ($employees as $employee) {
            $sourceRef = new EmployeeRef(
                (string)$employee['source_type'],
                (int)$employee['source_id'],
                $playerId
            );
            $canonicalRef = $sourceRef;
            $legacyRef = null;
            if ($sourceRef->sourceType === EmployeeRef::SOURCE_BOARD_MEMBER) {
                $linkedRef = $linkMap[$playerId . ':' . $sourceRef->sourceId] ?? null;
                if ($linkedRef instanceof EmployeeRef) {
                    if (!isset($employeeByKey[$linkedRef->key()])) {
                        continue;
                    }
                    $legacyRef = $sourceRef;
                    $canonicalRef = $linkedRef;
                    $employee = $employeeByKey[$linkedRef->key()];
                }
            }

            if (isset($selected[$canonicalRef->key()])) {
                continue;
            }
            $roleCode = trim((string)($employee['role_code'] ?? ''));
            $specializationCode = isset($normalizedScopes[$roleCode])
                ? $roleCode
                : $this->resolveEffectSpecializationCode($employee);
            if ($specializationCode === null || !isset($normalizedScopes[$specializationCode])) {
                continue;
            }

            $selected[$canonicalRef->key()] = [
                'ref' => $canonicalRef,
                'legacy_ref' => $legacyRef,
                'record' => $employee,
                'specialization_code' => $specializationCode,
                'target_scopes' => $normalizedScopes[$specializationCode],
            ];
        }

        $missingStateEntries = [];
        foreach ($selected as $entry) {
            /** @var EmployeeRef $employeeRef */
            $employeeRef = $entry['ref'];
            /** @var EmployeeRef|null $legacyRef */
            $legacyRef = $entry['legacy_ref'];
            $hasCanonicalState = isset($states[$employeeRef->key()]);
            $hasLegacyState = $legacyRef instanceof EmployeeRef && isset($states[$legacyRef->key()]);
            if (!$hasCanonicalState && !$hasLegacyState) {
                $missingStateEntries[] = [
                    'ref' => $employeeRef,
                    'employee' => $entry['record'],
                ];
            }
        }
        if ($missingStateEntries !== []) {
            $this->employeeState->ensureStatesForRecords($missingStateEntries);
            $states = $this->loadPlayerStates($playerId);
        }

        $effectsBySpecialization = $this->loadEffectsForSpecializations(array_keys($normalizedScopes));
        $results = [];
        foreach ($selected as $entry) {
            /** @var EmployeeRef $employeeRef */
            $employeeRef = $entry['ref'];
            /** @var EmployeeRef|null $legacyRef */
            $legacyRef = $entry['legacy_ref'];
            /** @var array<string, mixed> $record */
            $record = $entry['record'];
            $state = $this->employeeState->selectPreferredRuntimeState(
                $legacyRef instanceof EmployeeRef ? ($states[$legacyRef->key()] ?? null) : null,
                $states[$employeeRef->key()] ?? null
            ) ?? ['morale' => 65.0, 'relation_status' => 'normal'];

            $specializationCode = (string)$entry['specialization_code'];
            $morale = (float)($state['morale'] ?? 65.0);
            $moraleFactor = $this->moraleFactor($morale);
            foreach ((array)$entry['target_scopes'] as $targetScope) {
                $effects = $this->isOperationalForEffects($record, $state)
                    ? $this->compileEffectsFromRows(
                        $record,
                        $effectsBySpecialization[$specializationCode] ?? [],
                        $this->relatedScopes((string)$targetScope),
                        $moraleFactor
                    )
                    : [];

                $results[] = $this->buildResultPayload(
                    $employeeRef,
                    $record,
                    $specializationCode,
                    (string)$targetScope,
                    $morale,
                    $moraleFactor,
                    $effects,
                    []
                );
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveEffect(array $data): int
    {
        $payload = $this->validateEffectPayload($data);
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $this->assertUniqueEffectTuple(
            $payload['specialization_code'],
            $payload['effect_key'],
            $payload['target_scope'],
            $id > 0 ? $id : null
        );

        if ($id > 0) {
            $exists = $this->db->prepare('SELECT COUNT(*) FROM employee_role_effects WHERE id = ?');
            $exists->execute([$id]);
            if ((int)$exists->fetchColumn() < 1) {
                throw new RuntimeException('Employee role effect does not exist.');
            }

            $stmt = $this->db->prepare(
                'UPDATE employee_role_effects
                    SET specialization_code = :specialization_code,
                        effect_key = :effect_key,
                        effect_type = :effect_type,
                        effect_value = :effect_value,
                        target_scope = :target_scope,
                        skill_weights_json = :skill_weights_json,
                        description_key = :description_key,
                        description_pl = :description_pl,
                        is_active = :is_active,
                        updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO employee_role_effects
                (specialization_code, effect_key, effect_type, effect_value, target_scope, skill_weights_json, description_key, description_pl, is_active)
             VALUES
                (:specialization_code, :effect_key, :effect_type, :effect_value, :target_scope, :skill_weights_json, :description_key, :description_pl, :is_active)'
        );
        $stmt->execute($payload);

        return (int)$this->db->lastInsertId();
    }

    public function deleteEffect(int $effectId): void
    {
        if ($effectId <= 0) {
            throw new InvalidArgumentException('Effect identifier must be positive.');
        }

        $stmt = $this->db->prepare('DELETE FROM employee_role_effects WHERE id = ?');
        $stmt->execute([$effectId]);
    }

    /** @return array<string, mixed> */
    public function getLogisticsManagerBonus(int $playerId): array
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }

        $stmt = $this->db->prepare(
            "SELECT bm.id
               FROM board_members bm
               JOIN board_roles br ON br.id = bm.role_id
              WHERE bm.player_id = ?
                AND bm.status = 'active'
                AND bm.member_type = 'director'
                AND br.code = 'logistics'
              ORDER BY bm.id ASC
              LIMIT 1"
        );
        $stmt->execute([$playerId]);
        $managerId = $stmt->fetchColumn();

        if ($managerId === false) {
            return [
                'has_manager' => false,
                'score' => 0.0,
                'morale_factor' => $this->moraleFactor(65.0),
                'effects' => [],
            ];
        }

        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, (int)$managerId, $playerId);
        $record = $this->employees->find($ref);
        if ($record === null) {
            return [
                'has_manager' => false,
                'score' => 0.0,
                'morale_factor' => $this->moraleFactor(65.0),
                'effects' => [],
            ];
        }

        $state = $this->employeeState->ensureState($ref);
        $morale = (float)($state['morale'] ?? 65.0);
        $moraleFactor = $this->moraleFactor($morale);
        $skills = (array)($record['skills'] ?? []);
        if (!$this->isOperationalForEffects($record, $state)) {
            return [
                'has_manager' => true,
                'employee' => $this->employeeMeta($ref, $record),
                'morale' => $morale,
                'morale_factor' => $moraleFactor,
                'score' => 0.0,
                'effects' => [],
            ];
        }

        $score = (
            ((float)($skills['organization'] ?? 5) * 0.40) +
            ((float)($skills['analysis'] ?? 5) * 0.35) +
            ((float)($skills['negotiation'] ?? 5) * 0.25)
        ) * $moraleFactor;
        $specializationCode = $this->resolveManagerEffectSpecializationCode($record);
        $effects = $specializationCode !== null
            ? $this->compileEffects($record, $specializationCode, ['department', 'global'], $moraleFactor)
            : [];

        return [
            'has_manager' => true,
            'employee' => $this->employeeMeta($ref, $record),
            'morale' => $morale,
            'morale_factor' => $moraleFactor,
            'score' => round($score, 4),
            'effects' => $effects,
        ];
    }

    private function normalizeTargetScope(string $targetScope): string
    {
        $targetScope = trim($targetScope);
        if (!in_array($targetScope, self::TARGET_SCOPES, true)) {
            throw new InvalidArgumentException('Unsupported target scope.');
        }

        return $targetScope;
    }

    /**
     * @param array<string, mixed> $employee
     */
    private function resolveEffectSpecializationCode(array $employee): ?string
    {
        $specializationCode = $employee['specialization_code'] ?? null;
        if (is_string($specializationCode) && trim($specializationCode) !== '') {
            return trim($specializationCode);
        }

        $roleCode = $employee['role_code'] ?? null;
        if (is_string($roleCode) && trim($roleCode) !== '') {
            return trim($roleCode);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $employee
     */
    private function resolveManagerEffectSpecializationCode(array $employee): ?string
    {
        $specializationCode = $employee['specialization_code'] ?? null;
        if (is_string($specializationCode) && trim($specializationCode) !== '') {
            return trim($specializationCode);
        }

        return (string)($employee['role_code'] ?? '') === 'logistics'
            ? self::LOGISTICS_MANAGER_FALLBACK_SPECIALIZATION
            : null;
    }

    /**
     * @param list<string> $scopes
     * @return list<array<string, mixed>>
     */
    private function loadEffectsForScopes(string $specializationCode, array $scopes): array
    {
        $placeholders = implode(',', array_fill(0, count($scopes), '?'));
        $params = array_merge([$specializationCode], $scopes);

        $stmt = $this->db->prepare(
            'SELECT *
               FROM employee_role_effects
              WHERE specialization_code = ?
                AND is_active = 1
                AND target_scope IN (' . $placeholders . ')
              ORDER BY CASE target_scope
                    WHEN ? THEN 0
                    WHEN \'department\' THEN 1
                    WHEN \'global\' THEN 2
                    ELSE 3
                  END,
                  effect_key ASC,
                  id ASC'
        );
        $stmt->execute(array_merge($params, [$scopes[0]]));

        return array_map([$this, 'normalizeEffectRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param list<string> $specializationCodes
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadEffectsForSpecializations(array $specializationCodes): array
    {
        $specializationCodes = array_values(array_unique(array_filter(
            array_map(static fn(string $code): string => trim($code), $specializationCodes),
            static fn(string $code): bool => $code !== ''
        )));
        if ($specializationCodes === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($specializationCodes), '?'));
        $stmt = $this->db->prepare(
            'SELECT *
               FROM employee_role_effects
              WHERE specialization_code IN (' . $placeholders . ')
                AND is_active = 1
              ORDER BY specialization_code ASC, effect_key ASC, id ASC'
        );
        $stmt->execute($specializationCodes);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $normalized = $this->normalizeEffectRow($row);
            $grouped[(string)$normalized['specialization_code']][] = $normalized;
        }

        return $grouped;
    }

    /** @return array<string, array<string, mixed>> */
    private function loadPlayerStates(int $playerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM employee_state WHERE player_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$playerId]);

        $states = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $state) {
            $key = (string)$state['source_type'] . ':' . (int)$state['source_id'];
            $states[$key] = $state;
        }

        return $states;
    }

    /** @param array<string, mixed> $employee */
    private function employeeRecordKey(array $employee): string
    {
        return (string)$employee['source_type'] . ':' . (int)$employee['source_id'];
    }

    /**
     * @param array<string, int> $skills
     * @param array<string, float> $weights
     */
    private function skillFactor(array $skills, array $weights): float
    {
        if ($weights === []) {
            return 1.0;
        }

        $weightedValue = 0.0;
        $totalWeight = 0.0;
        foreach ($weights as $key => $weight) {
            $weight = (float)$weight;
            if ($weight <= 0) {
                continue;
            }

            $skillValue = isset($skills[$key]) ? (float)$skills[$key] : 5.0;
            $skillValue = max(0.0, min(10.0, $skillValue));
            $weightedValue += ($skillValue / 10.0) * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0.0) {
            return 1.0;
        }

        $normalized = $weightedValue / $totalWeight;
        return round(0.5 + $normalized, 4);
    }

    private function moraleFactor(float $morale): float
    {
        $morale = max(0.0, min(100.0, $morale));
        if ($morale <= 20.0) {
            return 0.70;
        }
        if ($morale <= 40.0) {
            return 0.85;
        }
        if ($morale <= 60.0) {
            return 1.00;
        }
        if ($morale <= 80.0) {
            return 1.05;
        }

        return 1.10;
    }

    private function applyFactors(string $effectType, float $baseValue, float $skillFactor, float $moraleFactor): float
    {
        if ($effectType === 'bool') {
            return $baseValue > 0.0 ? 1.0 : 0.0;
        }

        return round($baseValue * $skillFactor * $moraleFactor, 4);
    }

    /**
     * @param array<string, mixed> $record
     * @param list<string> $scopes
     * @return array<string, array<string, mixed>>
     */
    private function compileEffects(array $record, string $specializationCode, array $scopes, float $moraleFactor): array
    {
        $rows = $this->loadEffectsForScopes($specializationCode, $scopes);
        return $this->compileEffectsFromRows($record, $rows, $scopes, $moraleFactor);
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array<string, mixed>> $rows
     * @param list<string> $scopes
     * @return array<string, array<string, mixed>>
     */
    private function compileEffectsFromRows(array $record, array $rows, array $scopes, float $moraleFactor): array
    {
        $scopePriority = array_flip($scopes);
        $rows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => isset($scopePriority[(string)$row['target_scope']])
        ));
        usort($rows, static function (array $left, array $right) use ($scopePriority): int {
            return [
                $scopePriority[(string)$left['target_scope']] ?? PHP_INT_MAX,
                (string)$left['effect_key'],
                (int)$left['id'],
            ] <=> [
                $scopePriority[(string)$right['target_scope']] ?? PHP_INT_MAX,
                (string)$right['effect_key'],
                (int)$right['id'],
            ];
        });

        $skills = (array)($record['skills'] ?? []);
        $effects = [];
        foreach ($rows as $row) {
            $effectKey = (string)$row['effect_key'];
            if (isset($effects[$effectKey])) {
                continue;
            }

            $skillFactor = $this->skillFactor($skills, (array)$row['skill_weights']);
            $effects[$effectKey] = [
                'id' => (int)$row['id'],
                'specialization_code' => (string)$row['specialization_code'],
                'effect_key' => $effectKey,
                'effect_type' => (string)$row['effect_type'],
                'target_scope' => (string)$row['target_scope'],
                'description_key' => (string)$row['description_key'],
                'description_pl' => (string)$row['description_pl'],
                'description' => $this->translateDescription(
                    (string)$row['description_key'],
                    (string)$row['description_pl']
                ),
                'base_value' => (float)$row['effect_value'],
                'skill_factor' => $skillFactor,
                'morale_factor' => $moraleFactor,
                'final_value' => $this->applyFactors(
                    (string)$row['effect_type'],
                    (float)$row['effect_value'],
                    $skillFactor,
                    $moraleFactor
                ),
                'skill_weights' => (array)$row['skill_weights'],
            ];
        }

        return $effects;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $state
     */
    private function isOperationalForEffects(array $record, array $state): bool
    {
        $status = trim((string)($record['status'] ?? ''));
        $sourceType = (string)($record['source_type'] ?? '');
        if ($sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF) {
            if (!in_array($status, ['active', 'busy'], true)) {
                return false;
            }
        } elseif ($status !== 'active') {
            return false;
        }

        $relationStatus = trim((string)($state['relation_status'] ?? 'normal'));

        return !in_array($relationStatus, self::BLOCKED_RELATION_STATUSES, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{specialization_code:string,effect_key:string,effect_type:string,effect_value:float,target_scope:string,skill_weights_json:string,description_key:string,description_pl:string,is_active:int}
     */
    private function validateEffectPayload(array $data): array
    {
        $specializationCode = trim((string)($data['specialization_code'] ?? ''));
        $effectKey = trim((string)($data['effect_key'] ?? ''));
        $effectType = trim((string)($data['effect_type'] ?? 'percent'));
        $targetScope = $this->normalizeTargetScope((string)($data['target_scope'] ?? 'department'));
        $descriptionKey = trim((string)($data['description_key'] ?? ''));
        $descriptionPl = trim((string)($data['description_pl'] ?? ''));
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $effectValue = (float)($data['effect_value'] ?? 0.0);

        if ($specializationCode === '' || $effectKey === '') {
            throw new InvalidArgumentException('Specialization code and effect key are required.');
        }
        if (!preg_match('/^[a-z0-9_]+$/', $specializationCode)) {
            throw new InvalidArgumentException('Specialization code must be snake_case.');
        }
        if (!preg_match('/^[a-z0-9_]+$/', $effectKey)) {
            throw new InvalidArgumentException('Effect key must be snake_case.');
        }
        if (!in_array($effectType, self::EFFECT_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported effect type.');
        }

        $weights = $this->normalizeWeights($data['skill_weights'] ?? $data['skill_weights_json'] ?? null);
        return [
            'specialization_code' => $specializationCode,
            'effect_key' => $effectKey,
            'effect_type' => $effectType,
            'effect_value' => $effectValue,
            'target_scope' => $targetScope,
            'skill_weights_json' => json_encode($weights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'description_key' => $descriptionKey,
            'description_pl' => $descriptionPl,
            'is_active' => $isActive,
        ];
    }

    /**
     * @param mixed $weights
     * @return array<string, float>
     */
    private function normalizeWeights(mixed $weights): array
    {
        if (is_string($weights) && trim($weights) !== '') {
            $decoded = json_decode($weights, true);
            if (is_array($decoded)) {
                $weights = $decoded;
            }
        }

        if (!is_array($weights)) {
            return [];
        }

        $normalized = [];
        foreach ($weights as $key => $value) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }
            $weight = max(0.0, (float)$value);
            if ($weight <= 0.0) {
                continue;
            }
            $normalized[$key] = $weight;
        }

        return $normalized;
    }

    private function assertUniqueEffectTuple(
        string $specializationCode,
        string $effectKey,
        string $targetScope,
        ?int $ignoreId = null
    ): void {
        $sql = 'SELECT id
                  FROM employee_role_effects
                 WHERE specialization_code = ?
                   AND effect_key = ?
                   AND target_scope = ?';
        $params = [$specializationCode, $effectKey, $targetScope];
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            throw new RuntimeException('Employee role effect already exists for this specialization and scope.');
        }
    }

    /**
     * @return list<string>
     */
    private function relatedScopes(string $targetScope): array
    {
        if ($targetScope === 'global') {
            return ['global'];
        }

        $scopes = [$targetScope];
        if ($targetScope !== 'department') {
            $scopes[] = 'department';
        }
        $scopes[] = 'global';

        return array_values(array_unique($scopes));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeEffectRow(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'specialization_code' => (string)$row['specialization_code'],
            'effect_key' => (string)$row['effect_key'],
            'effect_type' => (string)$row['effect_type'],
            'effect_value' => (float)$row['effect_value'],
            'target_scope' => (string)$row['target_scope'],
            'skill_weights' => $this->normalizeWeights($row['skill_weights_json'] ?? null),
            'description_key' => (string)($row['description_key'] ?? ''),
            'description_pl' => (string)($row['description_pl'] ?? ''),
            'description' => $this->translateDescription(
                isset($row['description_key']) ? (string)$row['description_key'] : '',
                (string)($row['description_pl'] ?? '')
            ),
            'is_active' => (int)($row['is_active'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, array<string, mixed>> $effects
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildResultPayload(
        EmployeeRef $employee,
        array $record,
        ?string $specializationCode,
        string $targetScope,
        float $morale,
        float $moraleFactor,
        array $effects,
        array $context
    ): array {
        return [
            'employee' => $this->employeeMeta($employee, $record),
            'specialization_code' => $specializationCode,
            'target_scope' => $targetScope,
            'morale' => $morale,
            'morale_factor' => $moraleFactor,
            'effects' => $effects,
            'context' => $context,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function employeeMeta(EmployeeRef $employee, array $record): array
    {
        return [
            'source_type' => $employee->sourceType,
            'source_id' => $employee->sourceId,
            'player_id' => $employee->playerId,
            'department_code' => (string)($record['department_code'] ?? ''),
            'role_code' => (string)($record['role_code'] ?? ''),
            'specialization_code' => $record['specialization_code'] ?? null,
            'first_name' => (string)($record['first_name'] ?? ''),
            'last_name' => (string)($record['last_name'] ?? ''),
        ];
    }

    private function translateDescription(string $descriptionKey, string $fallback): string
    {
        if ($descriptionKey !== '' && function_exists('tPlain')) {
            $translated = tPlain($descriptionKey);
            if (is_string($translated) && $translated !== '' && $translated !== $descriptionKey) {
                return $translated;
            }
        }

        return $fallback;
    }
}
