<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRepository.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once __DIR__ . '/SqliteIntegrationTestCase.php';

final class EmployeeRepositoryTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private EmployeeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->seedEmployees();
        EmployeeSystemBootstrap::ensure($this->db);
        $this->repository = new EmployeeRepository($this->db);
    }

    public function testNormalizesBothEmployeeSourcesWithoutMergingThem(): void
    {
        $employees = $this->repository->listForPlayer(1);

        $this->assertCount(2, $employees);
        $board = $this->employeeBySource($employees, EmployeeRef::SOURCE_BOARD_MEMBER);
        $technical = $this->employeeBySource($employees, EmployeeRef::SOURCE_TECHNICAL_STAFF);

        $this->assertSame('logistics', $board['department_code']);
        $this->assertSame('planner', $board['specialization_code']);
        $this->assertSame(8, $board['skills']['organization']);
        $this->assertSame(9, $board['traits']['loyalty']);
        $this->assertSame('technical', $technical['department_code']);
        $this->assertSame('maintenance_engineer', $technical['role_code']);
        $this->assertSame(7, $technical['skills']['role_skill']);
        $this->assertSame(7, $technical['skills']['organization']);
        $this->assertSame(5, $technical['traits']['loyalty']);
        $this->assertSame(5, $technical['traits']['corruption_risk']);
        $this->assertSame(5, $technical['traits']['ambition']);
        $this->assertSame(9000.0, $technical['salary_range_min']);
    }

    public function testActiveFilterAndDepartmentFilterAreConsistent(): void
    {
        $this->assertCount(2, $this->repository->listForPlayer(1));
        $this->assertCount(4, $this->repository->listForPlayer(1, null, false));
        $this->assertCount(2, $this->repository->listMissingStateRefs(100));
        $this->assertCount(1, $this->repository->listForPlayer(1, 'logistics'));
        $this->assertCount(1, $this->repository->listForPlayer(1, 'technical'));
        $this->assertSame([], $this->repository->listForPlayer(1, 'legal'));
    }

    public function testFindEnforcesPlayerOwnership(): void
    {
        $ownedRef = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $foreignRef = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 2);
        $owned = $this->repository->find($ownedRef);
        $foreign = $this->repository->find($foreignRef);

        $this->assertNotNull($owned);
        $this->assertNull($foreign);
        $this->assertSame('logistics', $this->repository->resolveDepartment($ownedRef));
        $this->assertSame(9000.0, $this->repository->resolveSalary($ownedRef));
        $this->assertSame(8, $this->repository->resolveSkills($ownedRef)['organization']);
        $this->assertSame(9, $this->repository->resolveTraits($ownedRef)['loyalty']);
        $this->assertTrue($this->repository->isActive($ownedRef));
        $this->assertFalse($this->repository->isActive($foreignRef));
    }

    public function testCanonicalRefMapsOnlyConfirmedHeadhunterMirror(): void
    {
        $boardRef = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $technicalRef = $this->repository->canonicalRef($boardRef);

        $this->assertSame(EmployeeRef::SOURCE_BOARD_MEMBER, $technicalRef->sourceType);

        $this->db->exec("INSERT INTO board_members
            (id, player_id, member_type, role_id, specialization_id, first_name, last_name,
             experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress,
             skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary, status, hired_at)
            VALUES
            (12, 1, 'director', 1, 2, 'Jan', 'Kowalski', 6, 7, 7, 7, 7, 7, 5, 5, 5, 9700, 'active', '2026-01-03 10:00:00')");
        $this->db->exec('UPDATE technical_staff SET manager_id = 12 WHERE id = 20');
        $this->assertSame(1, $this->repository->syncLegacyMirrorLinks(1));

        $mirror = $this->repository->canonicalRef(new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 12, 1));
        $this->assertSame(EmployeeRef::SOURCE_TECHNICAL_STAFF, $mirror->sourceType);
        $this->assertSame(20, $mirror->sourceId);
        $this->assertCount(2, $this->repository->listForPlayer(1));
    }

    /** @param list<array<string, mixed>> $employees @return array<string, mixed> */
    private function employeeBySource(array $employees, string $sourceType): array
    {
        foreach ($employees as $employee) {
            if ($employee['source_type'] === $sourceType) {
                return $employee;
            }
        }
        $this->fail('Expected employee source was not found: ' . $sourceType);
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE hr_specializations (
            id INTEGER PRIMARY KEY,
            code TEXT NOT NULL,
            name TEXT NOT NULL,
            base_salary_min REAL NOT NULL,
            base_salary_max REAL NOT NULL
        )');
        $this->db->exec("CREATE TABLE board_members (
            id INTEGER PRIMARY KEY,
            player_id INTEGER NULL,
            member_type TEXT NOT NULL,
            role_id INTEGER NOT NULL,
            specialization_id INTEGER NULL,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            experience_years INTEGER NOT NULL,
            skill_organization INTEGER NOT NULL,
            skill_negotiation INTEGER NOT NULL,
            skill_analysis INTEGER NOT NULL,
            skill_stress INTEGER NOT NULL,
            skill_ethics INTEGER NOT NULL,
            trait_loyalty INTEGER NOT NULL,
            trait_corruption_risk INTEGER NOT NULL,
            trait_ambition INTEGER NOT NULL,
            salary REAL NOT NULL,
            status TEXT NOT NULL,
            hired_at TEXT NULL
        )");
        $this->db->exec("CREATE TABLE technical_staff (
            id INTEGER PRIMARY KEY,
            player_id INTEGER NOT NULL,
            manager_id INTEGER NOT NULL DEFAULT 0,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            spec_code TEXT NOT NULL,
            specialization TEXT NULL,
            spec_name TEXT NOT NULL,
            experience_years INTEGER NOT NULL,
            skill_level INTEGER NOT NULL,
            salary REAL NOT NULL,
            status TEXT NOT NULL,
            hired_at TEXT NULL
        )");
    }

    private function seedEmployees(): void
    {
        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'logistics')");
        $this->db->exec("INSERT INTO hr_specializations (id, code, name, base_salary_min, base_salary_max)
            VALUES
            (1, 'planner', 'Planner', 8000, 12000),
            (2, 'maintenance_engineer', 'Maintenance Engineer', 9000, 13000)");
        $this->db->exec("INSERT INTO board_members
            (id, player_id, member_type, role_id, specialization_id, first_name, last_name,
             experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress,
             skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary, status, hired_at)
            VALUES
            (10, 1, 'staff', 1, 1, 'Anna', 'Nowak', 10, 8, 7, 9, 6, 8, 9, 2, 7, 9000, 'active', '2026-01-01 10:00:00'),
            (11, 1, 'staff', 1, 1, 'Ewa', 'Kowalska', 5, 5, 5, 5, 5, 5, 5, 5, 5, 8000, 'fired', '2026-01-02 10:00:00')");
        $this->db->exec("INSERT INTO technical_staff
            (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name,
             experience_years, skill_level, salary, status, hired_at)
            VALUES
            (20, 1, 10, 'Jan', 'Kowalski', 'maintenance_engineer', 'pipeline_expert', 'Maintenance Engineer', 6, 7, 9700, 'busy', '2026-01-03 10:00:00'),
            (21, 1, 10, 'Pawel', 'Zwolniony', 'operator', NULL, 'Operator', 3, 5, 7000, 'fired', '2026-01-04 10:00:00')");
    }
}
