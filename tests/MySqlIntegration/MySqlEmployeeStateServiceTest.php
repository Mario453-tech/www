<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRepository.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeStateService.php';

final class MySqlEmployeeStateServiceTest extends MySqlIntegrationTestCase
{
    protected function tearDown(): void
    {
        if (isset($this->db, $this->seed)) {
            $this->db->prepare('DELETE FROM employee_state WHERE player_id = ?')->execute([$this->seed]);
            $this->db->prepare('DELETE FROM employee_source_links WHERE player_id = ?')->execute([$this->seed]);
        }
        parent::tearDown();
    }

    public function testMySqlSchemaAndIdempotentTechnicalStateCreation(): void
    {
        EmployeeSystemBootstrap::ensure($this->db);
        $playerId = $this->seedPlayer();
        $staffId = $this->seedTechnicalStaff(
            $playerId,
            $this->getTrackedIds()['staffId'],
            'maintenance_engineer',
            'Maintenance Engineer',
            7,
            9700
        );
        $repository = new EmployeeRepository($this->db);
        $service = new EmployeeStateService($this->db, $repository);
        $ref = new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId, $playerId);

        $employee = $repository->find($ref);
        $first = $service->ensureState($ref);
        $second = $service->ensureState($ref);

        $this->assertNotNull($employee);
        $this->assertSame('technical', $employee['department_code']);
        $this->assertSame($first['id'], $second['id']);
        $this->assertGreaterThan(9700.0, $first['expected_salary']);
        $this->assertLessThan(100.0, $first['salary_satisfaction']);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM employee_state
              WHERE player_id = ? AND source_type = ? AND source_id = ?'
        );
        $stmt->execute([$playerId, EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId]);
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    public function testMySqlEnsureStateMigratesLegacyBoardStateToCanonicalTechnicalState(): void
    {
        EmployeeSystemBootstrap::ensure($this->db);
        $playerId = $this->seedPlayer();
        $boardMemberId = $this->seedBoardLogisticsMember($playerId, 'pipeline_logistics_specialist', 'Anna', 'Nowak');
        $staffId = $this->seedNamedTechnicalStaff($playerId, $this->getTrackedIds()['staffId'], 'pipeline_logistics_specialist', 'Specjalista logistyki rurociągów', 'Anna', 'Nowak', $boardMemberId);

        $repository = new EmployeeRepository($this->db);
        $service = new EmployeeStateService($this->db, $repository);
        $boardRef = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, $boardMemberId, $playerId);

        $service->ensureState($boardRef);
        $this->db->prepare("UPDATE employee_state
            SET morale = 29, leave_risk = 66, relation_status = 'dispute'
            WHERE player_id = ? AND source_type = 'board_member' AND source_id = ?")
            ->execute([$playerId, $boardMemberId]);
        $repository->syncLegacyMirrorLinks($playerId);

        $state = $service->ensureState($boardRef);

        $this->assertSame(EmployeeRef::SOURCE_TECHNICAL_STAFF, $state['source_type']);
        $this->assertSame($staffId, $state['source_id']);
        $this->assertSame(29.0, $state['morale']);
        $this->assertSame(66.0, $state['leave_risk']);
        $this->assertSame('dispute', $state['relation_status']);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM employee_state WHERE player_id = ?');
        $stmt->execute([$playerId]);
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    public function testMySqlInvalidLegacyLinkDoesNotCanonicalizeBoardMemberState(): void
    {
        EmployeeSystemBootstrap::ensure($this->db);
        $playerId = $this->seedPlayer();
        $boardMemberId = $this->seedBoardLogisticsMember($playerId, 'transport_dispatcher', 'Anna', 'Nowak');
        $staffId = $this->seedTechnicalStaff($playerId, $this->getTrackedIds()['staffId'], 'pipeline_logistics_specialist', 'Specjalista logistyki rurociągów', 7, 9700);
        $this->db->prepare(
            "INSERT INTO employee_source_links (player_id, board_member_id, technical_staff_id, link_type, created_at)
             VALUES (?, ?, ?, 'legacy_headhunter_mirror', NOW())"
        )->execute([$playerId, $boardMemberId, $staffId]);

        $repository = new EmployeeRepository($this->db);
        $service = new EmployeeStateService($this->db, $repository);
        $state = $service->ensureState(new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, $boardMemberId, $playerId));

        $this->assertSame(EmployeeRef::SOURCE_BOARD_MEMBER, $state['source_type']);
        $this->assertSame($boardMemberId, $state['source_id']);
    }

    private function seedBoardLogisticsMember(int $playerId, string $specializationCode, string $firstName, string $lastName): int
    {
        $roleId = $this->ensureRoleExists('logistics', 'Logistics');
        $specId = $this->findSpecializationId($specializationCode);
        $boardMemberId = $this->seed + 40;

        $stmt = $this->db->prepare(
            'INSERT INTO board_members
                (id, player_id, member_type, role_id, specialization_id, status, first_name, last_name, birth_date, nationality,
                 experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress, skill_ethics,
                 trait_loyalty, trait_corruption_risk, trait_ambition, salary, hired_at)
             VALUES (?, ?, \'staff\', ?, ?, \'active\', ?, ?, \'1985-01-01\', \'PL\',
                 12, 8, 7, 9, 6, 8, 9, 2, 6, 12000.00, NOW())'
        );
        $stmt->execute([$boardMemberId, $playerId, $roleId, $specId, $firstName, $lastName]);

        return $boardMemberId;
    }

    private function seedNamedTechnicalStaff(
        int $playerId,
        int $staffId,
        string $specCode,
        string $specName,
        string $firstName,
        string $lastName,
        int $managerId,
        int $skill = 6,
        int $salary = 9000
    ): int {
        $this->db->prepare(
            'INSERT INTO technical_staff (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name, skill_level, salary, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\')'
        )->execute([$staffId, $playerId, $managerId, $firstName, $lastName, $specCode, $specCode, $specName, $skill, $salary]);

        return $staffId;
    }

    private function ensureRoleExists(string $code, string $name): int
    {
        $stmt = $this->db->prepare('SELECT id FROM board_roles WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $roleId = $stmt->fetchColumn();
        if ($roleId !== false) {
            return (int)$roleId;
        }

        $roleId = $this->seed + 41;
        $this->db->prepare(
            'INSERT INTO board_roles (id, code, name, created_at) VALUES (?, ?, ?, NOW())'
        )->execute([$roleId, $code, $name]);

        return $roleId;
    }

    private function findSpecializationId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM hr_specializations WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Expected seeded specialization was not found.');
        }

        return (int)$id;
    }
}
