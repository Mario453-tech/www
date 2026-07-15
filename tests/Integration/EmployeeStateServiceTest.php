<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRepository.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeStateService.php';
require_once __DIR__ . '/SqliteIntegrationTestCase.php';

final class EmployeeStateServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private EmployeeRepository $repository;
    private EmployeeStateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSourceSchema();
        $this->seedEmployees();
        $this->repository = new EmployeeRepository($this->db);
        $this->service = new EmployeeStateService($this->db, $this->repository);
    }

    public function testEnsureStateIsIdempotentAndCalculatesSalarySatisfaction(): void
    {
        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->assertSame(1, $this->repository->syncLegacyMirrorLinks(1));
        $first = $this->service->ensureState($ref);
        $second = $this->service->ensureState($ref);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());
        $this->assertSame(65.0, $first['morale']);
        $this->assertGreaterThan(9000.0, $first['expected_salary']);
        $this->assertLessThan(100.0, $first['salary_satisfaction']);
    }

    public function testTechnicalSalaryUsesCurrentSalaryWhenNoRangeExists(): void
    {
        $ref = new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1);
        $state = $this->service->ensureState($ref);

        $this->assertGreaterThan(9700.0, $state['expected_salary']);
        $this->assertLessThan(100.0, $state['salary_satisfaction']);
    }

    public function testDryRunDoesNotWriteAndApplyCreatesMissingStatesOnlyOnce(): void
    {
        $dryRun = $this->service->backfillEmployeeState(false, 1);

        $this->assertFalse($dryRun['applied']);
        $this->assertSame(2, $dryRun['would_create']);
        $this->assertSame(1, $dryRun['mirrored_records_skipped']);
        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());
        $this->assertCount(1, $dryRun['suspected_duplicate_groups']);

        $applied = $this->service->backfillEmployeeState(true, 1);
        $repeated = $this->service->backfillEmployeeState(true, 1);

        $this->assertSame(2, $applied['created']);
        $this->assertSame(0, $applied['would_create']);
        $this->assertSame(0, $repeated['created']);
        $this->assertSame(2, $repeated['skipped']);
        $this->assertSame(1, $repeated['mirrored_records_skipped']);
        $this->assertSame(2, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());
    }

    public function testSourceOwnershipPreventsCrossPlayerStateCreation(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->ensureState(new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 2));
    }

    public function testEnsureStateMigratesLegacyBoardStateToCanonicalTechnicalState(): void
    {
        $boardRef = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->service->ensureState($boardRef);
        $this->db->exec("UPDATE employee_state
            SET morale = 33, leave_risk = 71, relation_status = 'dispute'
            WHERE player_id = 1 AND source_type = 'board_member' AND source_id = 10");
        $this->assertSame(1, $this->repository->syncLegacyMirrorLinks(1));

        $state = $this->service->ensureState($boardRef);

        $this->assertSame(EmployeeRef::SOURCE_TECHNICAL_STAFF, $state['source_type']);
        $this->assertSame(21, $state['source_id']);
        $this->assertSame(33.0, $state['morale']);
        $this->assertSame(71.0, $state['leave_risk']);
        $this->assertSame('dispute', $state['relation_status']);
        $this->assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());
    }

    public function testInvalidLegacyLinkDoesNotCanonicalizeBoardMemberState(): void
    {
        $this->db->exec("INSERT INTO employee_source_links
            (player_id, board_member_id, technical_staff_id, link_type)
            VALUES (1, 10, 20, 'legacy_headhunter_mirror')");

        $state = $this->service->ensureState(new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1));

        $this->assertSame(EmployeeRef::SOURCE_BOARD_MEMBER, $state['source_type']);
        $this->assertSame(10, $state['source_id']);
    }

    public function testRiskListIsLimitedToRequestedPlayer(): void
    {
        $this->service->backfillEmployeeState(true);
        $this->db->exec("UPDATE employee_state SET morale = 20, leave_risk = 80 WHERE player_id = 1 AND source_type = 'technical_staff' AND source_id = 21");
        $this->db->exec("UPDATE employee_state SET morale = 10, leave_risk = 90 WHERE player_id = 2");

        $risk = $this->service->listAtRiskEmployees(1, 10, 0);

        $this->assertCount(1, $risk);
        $this->assertSame(1, $risk[0]['player_id']);
        $this->assertSame(21, $risk[0]['source_id']);
    }

    private function createSourceSchema(): void
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
            (2, 'maintenance_engineer', 'Maintenance Engineer', 9000, 13000),
            (3, 'logistics_coordinator', 'Logistics Coordinator', 8000, 11000)");
        $this->db->exec("INSERT INTO board_members
            (id, player_id, member_type, role_id, specialization_id, first_name, last_name,
             experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress,
             skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary, status, hired_at)
            VALUES
            (10, 1, 'staff', 1, 3, 'Anna', 'Nowak', 10, 8, 7, 9, 6, 8, 9, 2, 7, 9000, 'active', '2026-01-01 10:00:00'),
            (11, 2, 'staff', 1, 1, 'Maria', 'Inna', 4, 5, 5, 5, 5, 5, 5, 5, 5, 8500, 'active', '2026-01-02 10:00:00')");
        $this->db->exec("INSERT INTO technical_staff
            (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name,
             experience_years, skill_level, salary, status, hired_at)
            VALUES
            (20, 1, 10, 'Jan', 'Kowalski', 'maintenance_engineer', NULL, 'Maintenance Engineer', 6, 7, 9700, 'busy', '2026-01-03 10:00:00'),
            (21, 1, 10, 'Anna', 'Nowak', 'logistics_coordinator', NULL, 'Logistics Coordinator', 5, 6, 9000, 'active', '2026-01-01 10:05:00')");
    }
}
