<?php
declare(strict_types=1);

final class EmployeeRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(EmployeeRef $ref): ?array
    {
        $rows = $ref->sourceType === EmployeeRef::SOURCE_BOARD_MEMBER
            ? $this->fetchBoardMembers($ref->playerId, null, false, $ref->sourceId)
            : $this->fetchTechnicalStaff($ref->playerId, null, false, $ref->sourceId);

        return $rows[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function listForPlayer(int $playerId, ?string $departmentCode = null, bool $activeOnly = true): array
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Player identifier must be positive.');
        }

        return $this->deduplicateLinkedRows($this->mergeAndSort(
            $this->fetchBoardMembers($playerId, $departmentCode, $activeOnly),
            $this->fetchTechnicalStaff($playerId, $departmentCode, $activeOnly)
        ), $playerId);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(?string $departmentCode = null, bool $activeOnly = true): array
    {
        return $this->deduplicateLinkedRows($this->mergeAndSort(
            $this->fetchBoardMembers(null, $departmentCode, $activeOnly),
            $this->fetchTechnicalStaff(null, $departmentCode, $activeOnly)
        ));
    }

    /**
     * Load only source records referenced by the current processing batch.
     * Laduje tylko rekordy zrodlowe wskazane przez biezaca partie przetwarzania.
     *
     * @param list<EmployeeRef> $refs
     * @return list<array<string,mixed>>
     */
    public function listByRefs(array $refs): array
    {
        $boardRefs = [];
        $technicalRefs = [];
        foreach ($refs as $ref) {
            if (!$ref instanceof EmployeeRef) {
                throw new InvalidArgumentException('Employee reference list contains an invalid value.');
            }
            if ($ref->sourceType === EmployeeRef::SOURCE_BOARD_MEMBER) {
                $boardRefs[] = $ref;
            } else {
                $technicalRefs[] = $ref;
            }
        }

        return $this->deduplicateLinkedRows($this->mergeAndSort(
            $boardRefs === [] ? [] : $this->fetchBoardMembers(null, null, false, null, $boardRefs),
            $technicalRefs === [] ? [] : $this->fetchTechnicalStaff(null, null, false, null, $technicalRefs)
        ));
    }

    /**
     * Find source records which still need a canonical state, bounded for tick batching.
     * Wyszukuje rekordy zrodlowe bez stanu kanonicznego, z limitem partii ticka.
     *
     * @return list<EmployeeRef>
     */
    public function listMissingStateRefs(int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT source_type, source_id, player_id
               FROM (
                    SELECT 'board_member' AS source_type, bm.id AS source_id, bm.player_id
                     FROM board_members bm
                     WHERE bm.player_id IS NOT NULL
                       AND bm.status <> 'fired'
                       AND NOT EXISTS (
                            SELECT 1 FROM employee_source_links esl
                             WHERE esl.player_id=bm.player_id AND esl.board_member_id=bm.id
                       )
                       AND NOT EXISTS (
                            SELECT 1 FROM employee_state es
                             WHERE es.player_id=bm.player_id
                               AND es.source_type='board_member'
                               AND es.source_id=bm.id
                       )
                    UNION ALL
                    SELECT 'technical_staff' AS source_type, ts.id AS source_id, ts.player_id
                      FROM technical_staff ts
                     WHERE ts.status <> 'fired'
                       AND NOT EXISTS (
                            SELECT 1 FROM employee_state es
                             WHERE es.player_id=ts.player_id
                               AND es.source_type='technical_staff'
                               AND es.source_id=ts.id
                       )
               ) missing
              ORDER BY player_id, source_type, source_id
              LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn(array $row): EmployeeRef => new EmployeeRef(
                (string)$row['source_type'],
                (int)$row['source_id'],
                (int)$row['player_id']
            ),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function countMissingStateRefs(): int
    {
        $stmt = $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM board_members bm
                  WHERE bm.player_id IS NOT NULL
                    AND bm.status <> 'fired'
                    AND NOT EXISTS (
                        SELECT 1 FROM employee_source_links esl
                         WHERE esl.player_id=bm.player_id AND esl.board_member_id=bm.id
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM employee_state es
                         WHERE es.player_id=bm.player_id
                           AND es.source_type='board_member' AND es.source_id=bm.id
                    ))
                +
                (SELECT COUNT(*) FROM technical_staff ts
                  WHERE ts.status <> 'fired'
                    AND NOT EXISTS (
                        SELECT 1 FROM employee_state es
                         WHERE es.player_id=ts.player_id
                           AND es.source_type='technical_staff' AND es.source_id=ts.id
                  ))"
        );
        return (int)$stmt->fetchColumn();
    }

    public function resolveDepartment(EmployeeRef $ref): ?string
    {
        $employee = $this->find($ref);
        return $employee !== null ? (string)$employee['department_code'] : null;
    }

    public function resolveSalary(EmployeeRef $ref): float
    {
        $employee = $this->find($ref);
        return $employee !== null ? (float)$employee['salary'] : 0.0;
    }

    /** @return array<string, int> */
    public function resolveSkills(EmployeeRef $ref): array
    {
        $employee = $this->find($ref);
        return $employee !== null ? (array)$employee['skills'] : [];
    }

    /** @return array<string, int> */
    public function resolveTraits(EmployeeRef $ref): array
    {
        $employee = $this->find($ref);
        return $employee !== null ? (array)$employee['traits'] : [];
    }

    public function isActive(EmployeeRef $ref): bool
    {
        $employee = $this->find($ref);
        if ($employee === null) {
            return false;
        }

        return $ref->sourceType === EmployeeRef::SOURCE_BOARD_MEMBER
            ? $employee['status'] === 'active'
            : in_array($employee['status'], ['active', 'busy', 'on_leave'], true);
    }

    public function canonicalRef(EmployeeRef $ref): EmployeeRef
    {
        if ($ref->sourceType !== EmployeeRef::SOURCE_BOARD_MEMBER) {
            return $ref;
        }

        $links = $this->validatedSourceLinks($ref->playerId, $ref->sourceId);
        if ($links === []) {
            return $ref;
        }

        return new EmployeeRef(
            EmployeeRef::SOURCE_TECHNICAL_STAFF,
            (int)$links[0]['technical_staff_id'],
            $ref->playerId
        );
    }

    /** @return list<array{player_id: int, board_member_id: int, technical_staff_id: int}> */
    public function findLegacyMirrorCandidates(?int $playerId = null): array
    {
        $where = ['bm.player_id IS NOT NULL', "bm.status <> 'fired'"];
        $params = [];
        if ($playerId !== null) {
            $where[] = 'bm.player_id = :player_id';
            $params['player_id'] = $playerId;
        }

        $stmt = $this->db->prepare(
            'SELECT bm.player_id, bm.id AS board_member_id, ts.id AS technical_staff_id
               FROM board_members bm
               JOIN technical_staff ts
                 ON ts.manager_id = bm.id
                AND ts.player_id = bm.player_id
                AND ts.first_name = bm.first_name
                AND ts.last_name = bm.last_name
               JOIN hr_specializations hs
                 ON hs.id = bm.specialization_id
                AND hs.code = ts.spec_code
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY bm.id ASC'
        );
        $stmt->execute($params);

        return array_map(
            static fn(array $row): array => [
                'player_id' => (int)$row['player_id'],
                'board_member_id' => (int)$row['board_member_id'],
                'technical_staff_id' => (int)$row['technical_staff_id'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function syncLegacyMirrorLinks(?int $playerId = null): int
    {
        $candidates = $this->findLegacyMirrorCandidates($playerId);
        $isSqlite = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $conflictClause = $isSqlite
            ? ' ON CONFLICT DO NOTHING'
            : ' ON DUPLICATE KEY UPDATE board_member_id = VALUES(board_member_id)';
        $stmt = $this->db->prepare(
            "INSERT INTO employee_source_links
                (player_id, board_member_id, technical_staff_id, link_type, created_at)
             VALUES
                (:player_id, :board_member_id, :technical_staff_id, 'legacy_headhunter_mirror', CURRENT_TIMESTAMP)"
            . $conflictClause
        );
        $created = 0;
        foreach ($candidates as $candidate) {
            $stmt->execute($candidate);
            if ($stmt->rowCount() === 1) {
                $created++;
            }
        }
        return $created;
    }

    /** @return array<string, EmployeeRef> */
    public function sourceLinkMap(?int $playerId = null): array
    {
        $map = [];
        foreach ($this->validatedSourceLinks($playerId) as $row) {
            $ownerId = (int)$row['player_id'];
            $map[$ownerId . ':' . (int)$row['board_member_id']] = new EmployeeRef(
                EmployeeRef::SOURCE_TECHNICAL_STAFF,
                (int)$row['technical_staff_id'],
                $ownerId
            );
        }
        return $map;
    }

    /**
     * Legacy links are accepted only when both sides still describe the same mirrored employee.
     * Stare linki sa uznawane tylko wtedy, gdy obie strony nadal opisuja tego samego pracownika.
     *
     * @return list<array{player_id:int,board_member_id:int,technical_staff_id:int}>
     */
    private function validatedSourceLinks(?int $playerId = null, ?int $boardMemberId = null): array
    {
        $where = [];
        $params = [];
        if ($playerId !== null) {
            $where[] = 'esl.player_id = :player_id';
            $params['player_id'] = $playerId;
        }
        if ($boardMemberId !== null) {
            $where[] = 'esl.board_member_id = :board_member_id';
            $params['board_member_id'] = $boardMemberId;
        }

        $sql = "SELECT esl.player_id, esl.board_member_id, esl.technical_staff_id
                  FROM employee_source_links esl
                  JOIN board_members bm
                    ON bm.id = esl.board_member_id
                   AND bm.player_id = esl.player_id
                  JOIN technical_staff ts
                    ON ts.id = esl.technical_staff_id
                   AND ts.player_id = esl.player_id
             LEFT JOIN hr_specializations hs
                    ON hs.id = bm.specialization_id
                 WHERE ts.manager_id = bm.id
                   AND ts.first_name = bm.first_name
                   AND ts.last_name = bm.last_name
                   AND (hs.code IS NULL OR hs.code = ts.spec_code)";
        if ($where !== []) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY esl.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn(array $row): array => [
                'player_id' => (int)$row['player_id'],
                'board_member_id' => (int)$row['board_member_id'],
                'technical_staff_id' => (int)$row['technical_staff_id'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param list<EmployeeRef>|null $refs
     * @return list<array<string,mixed>>
     */
    private function fetchBoardMembers(
        ?int $playerId,
        ?string $departmentCode,
        bool $activeOnly,
        ?int $sourceId = null,
        ?array $refs = null
    ): array {
        $where = ['bm.player_id IS NOT NULL'];
        $params = [];

        if ($playerId !== null) {
            $where[] = 'bm.player_id = :player_id';
            $params['player_id'] = $playerId;
        }
        if ($departmentCode !== null && $departmentCode !== '') {
            $where[] = 'br.code = :department_code';
            $params['department_code'] = $departmentCode;
        }
        if ($activeOnly) {
            $where[] = "bm.status = 'active'";
        }
        if ($sourceId !== null) {
            $where[] = 'bm.id = :source_id';
            $params['source_id'] = $sourceId;
        }
        if ($refs !== null) {
            $pairs = [];
            foreach ($refs as $index => $ref) {
                $pairs[] = "(bm.player_id = :ref_player_{$index} AND bm.id = :ref_source_{$index})";
                $params["ref_player_{$index}"] = $ref->playerId;
                $params["ref_source_{$index}"] = $ref->sourceId;
            }
            $where[] = '(' . implode(' OR ', $pairs) . ')';
        }

        $sql = "SELECT bm.id AS source_id,
                       bm.player_id,
                       bm.first_name,
                       bm.last_name,
                       bm.member_type,
                       COALESCE(br.code, 'board') AS department_code,
                       COALESCE(br.code, bm.member_type) AS role_code,
                       hs.code AS specialization_code,
                       hs.name AS specialization_name,
                       bm.experience_years,
                       bm.skill_organization,
                       bm.skill_negotiation,
                       bm.skill_analysis,
                       bm.skill_stress,
                       bm.skill_ethics,
                       bm.trait_loyalty,
                       bm.trait_corruption_risk,
                       bm.trait_ambition,
                       bm.salary,
                       bm.status,
                       bm.hired_at,
                       hs.base_salary_min,
                       hs.base_salary_max
                  FROM board_members bm
             LEFT JOIN board_roles br ON br.id = bm.role_id
             LEFT JOIN hr_specializations hs ON hs.id = bm.specialization_id
                 WHERE " . implode(' AND ', $where) . '
              ORDER BY bm.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->normalizeBoardMember($row);
        }
        return $rows;
    }

    /**
     * @param list<EmployeeRef>|null $refs
     * @return list<array<string,mixed>>
     */
    private function fetchTechnicalStaff(
        ?int $playerId,
        ?string $departmentCode,
        bool $activeOnly,
        ?int $sourceId = null,
        ?array $refs = null
    ): array {
        if ($departmentCode !== null && $departmentCode !== '' && $departmentCode !== 'technical') {
            return [];
        }

        $where = ['1 = 1'];
        $params = [];
        if ($playerId !== null) {
            $where[] = 'ts.player_id = :player_id';
            $params['player_id'] = $playerId;
        }
        if ($activeOnly) {
            $where[] = "ts.status IN ('active', 'busy', 'on_leave')";
        }
        if ($sourceId !== null) {
            $where[] = 'ts.id = :source_id';
            $params['source_id'] = $sourceId;
        }
        if ($refs !== null) {
            $pairs = [];
            foreach ($refs as $index => $ref) {
                $pairs[] = "(ts.player_id = :ref_player_{$index} AND ts.id = :ref_source_{$index})";
                $params["ref_player_{$index}"] = $ref->playerId;
                $params["ref_source_{$index}"] = $ref->sourceId;
            }
            $where[] = '(' . implode(' OR ', $pairs) . ')';
        }

        $sql = "SELECT ts.id AS source_id,
                       ts.player_id,
                       ts.manager_id,
                       ts.first_name,
                       ts.last_name,
                       ts.spec_code,
                       ts.specialization,
                       ts.spec_name,
                       ts.experience_years,
                       ts.skill_level,
                       ts.trait_loyalty,
                       ts.trait_corruption_risk,
                       ts.trait_ambition,
                       ts.salary,
                       ts.status,
                       ts.hired_at,
                       hs.base_salary_min,
                       hs.base_salary_max
                  FROM technical_staff ts
             LEFT JOIN hr_specializations hs ON hs.code = ts.spec_code
                 WHERE " . implode(' AND ', $where) . '
              ORDER BY ts.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->normalizeTechnicalStaff($row);
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeBoardMember(array $row): array
    {
        return [
            'source_type' => EmployeeRef::SOURCE_BOARD_MEMBER,
            'source_id' => (int)$row['source_id'],
            'player_id' => (int)$row['player_id'],
            'first_name' => (string)$row['first_name'],
            'last_name' => (string)$row['last_name'],
            'department_code' => (string)$row['department_code'],
            'role_code' => (string)$row['role_code'],
            'specialization_code' => $row['specialization_code'] !== null
                ? (string)$row['specialization_code']
                : null,
            'specialization_name' => $row['specialization_name'] !== null
                ? (string)$row['specialization_name']
                : null,
            'experience_years' => (int)$row['experience_years'],
            'salary' => (float)$row['salary'],
            'status' => (string)$row['status'],
            'skills' => [
                'organization' => (int)$row['skill_organization'],
                'negotiation' => (int)$row['skill_negotiation'],
                'analysis' => (int)$row['skill_analysis'],
                'stress' => (int)$row['skill_stress'],
                'ethics' => (int)$row['skill_ethics'],
            ],
            'traits' => [
                'loyalty' => (int)$row['trait_loyalty'],
                'corruption_risk' => (int)$row['trait_corruption_risk'],
                'ambition' => (int)$row['trait_ambition'],
            ],
            'salary_range_min' => $row['base_salary_min'] !== null ? (float)$row['base_salary_min'] : null,
            'salary_range_max' => $row['base_salary_max'] !== null ? (float)$row['base_salary_max'] : null,
            'hired_at' => $row['hired_at'] !== null ? (string)$row['hired_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeTechnicalStaff(array $row): array
    {
        $skill = (int)$row['skill_level'];

        return [
            'source_type' => EmployeeRef::SOURCE_TECHNICAL_STAFF,
            'source_id' => (int)$row['source_id'],
            'player_id' => (int)$row['player_id'],
            'manager_id' => (int)$row['manager_id'],
            'first_name' => (string)$row['first_name'],
            'last_name' => (string)$row['last_name'],
            'department_code' => 'technical',
            'role_code' => (string)$row['spec_code'],
            'specialization_code' => $row['specialization'] !== null && $row['specialization'] !== ''
                ? (string)$row['specialization']
                : null,
            'specialization_name' => (string)$row['spec_name'],
            'experience_years' => (int)$row['experience_years'],
            'salary' => (float)$row['salary'],
            'status' => (string)$row['status'],
            'skills' => [
                'role_skill' => $skill,
                'organization' => $skill,
                'negotiation' => $skill,
                'analysis' => $skill,
                'stress' => $skill,
                'ethics' => $skill,
            ],
            'traits' => [
                'loyalty' => (int)$row['trait_loyalty'],
                'corruption_risk' => (int)$row['trait_corruption_risk'],
                'ambition' => (int)$row['trait_ambition'],
            ],
            'salary_range_min' => $row['base_salary_min'] !== null ? (float)$row['base_salary_min'] : null,
            'salary_range_max' => $row['base_salary_max'] !== null ? (float)$row['base_salary_max'] : null,
            'hired_at' => $row['hired_at'] !== null ? (string)$row['hired_at'] : null,
        ];
    }

    /**
     * Keep one deterministic order across both legacy employee sources.
     * Zachowuje jeden deterministyczny porzadek dla obu zrodel pracownikow.
     *
     * @param list<array<string, mixed>> $board
     * @param list<array<string, mixed>> $technical
     * @return list<array<string, mixed>>
     */
    private function mergeAndSort(array $board, array $technical): array
    {
        $rows = array_merge($board, $technical);
        usort($rows, static function (array $left, array $right): int {
            return [
                (int)$left['player_id'],
                (string)$left['department_code'],
                (string)$left['source_type'],
                (int)$left['source_id'],
            ] <=> [
                (int)$right['player_id'],
                (string)$right['department_code'],
                (string)$right['source_type'],
                (int)$right['source_id'],
            ];
        });

        return $rows;
    }

    /**
     * Linked legacy mirrors represent one canonical technical employee.
     * Powiazane lustra legacy oznaczaja jednego kanonicznego pracownika technicznego.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function deduplicateLinkedRows(array $rows, ?int $playerId = null): array
    {
        $linkedBoards = [];
        try {
            foreach ($this->validatedSourceLinks($playerId) as $link) {
                $linkedBoards[(int)$link['player_id'] . ':' . (int)$link['board_member_id']] = true;
            }
        } catch (PDOException) {
            return $rows;
        }
        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string)$row['source_type'] !== EmployeeRef::SOURCE_BOARD_MEMBER
                || !isset($linkedBoards[(int)$row['player_id'] . ':' . (int)$row['source_id']])
        ));
    }
}
