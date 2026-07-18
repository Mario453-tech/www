<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRepository.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeStateService.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeAssignmentService.php';
require_once dirname(__DIR__, 2) . '/src/Employee/LogisticsStaffingService.php';
require_once dirname(__DIR__, 2) . '/src/Employee/HubStaffingManagementService.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once __DIR__ . '/SqliteIntegrationTestCase.php';

final class HubStaffingManagementServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private HubStaffingManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSourceSchema();
        $this->seedData();
        $this->service = new HubStaffingManagementService($this->db);
    }

    public function testBuildHubStaffingViewIncludesAssignmentsAndCandidates(): void
    {
        $assignments = new EmployeeAssignmentService($this->db);
        $assignments->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1),
            100,
            60.0
        );

        $view = $this->service->buildHubStaffingView(1, [
            ['hub' => $this->hubRow(100)],
        ]);

        $this->assertArrayHasKey(100, $view);
        $this->assertSame(1, $view[100]['assignment_count']);
        $this->assertNotEmpty($view[100]['active_assignments']);
        $this->assertSame('Jan Operator', $view[100]['active_assignments'][0]['full_name']);
        $this->assertNotEmpty($view[100]['candidates']);
        $this->assertSame('Jan Operator', $view[100]['candidates'][0]['full_name']);
        $this->assertNotContains('Anna Nowak', array_column($view[100]['candidates'], 'full_name'));
        $this->assertGreaterThan(0, $view[100]['summary']['coverage_pct']);
    }

    public function testRentedHubStaffingUsesTenantEmployeesWhenOwnerIsPresent(): void
    {
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, tenant_player_id, name, hub_type, slot_limit, status)
            VALUES (101, 2, 1, 'Leased Hub', 'medium', 4, 'active')");

        $assignments = new EmployeeAssignmentService($this->db);
        $assignments->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1),
            101,
            100.0
        );

        $view = $this->service->buildHubStaffingView(1, [
            ['hub' => $this->hubRow(101)],
        ]);

        $this->assertSame(1, $view[101]['summary']['player_id']);
        $this->assertSame(1, $view[101]['summary']['assigned_count']);
        $this->assertGreaterThan(0.0, $view[101]['summary']['coverage_pct']);
        $this->assertCount(1, $view[101]['summary']['assignments']);
    }

    public function testAssignToHubMarksUpdateWhenAssignmentAlreadyExists(): void
    {
        $resultA = $this->service->assignToHub(1, EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 100, 50.0);
        $resultB = $this->service->assignToHub(1, EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 100, 75.0);

        $this->assertFalse($resultA['was_update']);
        $this->assertTrue($resultB['was_update']);

        $rows = (new EmployeeAssignmentService($this->db))->listForHub(1, 100);
        $this->assertCount(1, $rows);
        $this->assertSame(75.0, (float)$rows[0]['allocation_pct']);
    }

    public function testReleaseFromHubClosesAssignment(): void
    {
        $result = $this->service->assignToHub(1, EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 100, 50.0);

        $this->assertTrue($this->service->releaseFromHub(1, (int)$result['assignment_id']));
        $this->assertSame([], (new EmployeeAssignmentService($this->db))->listForHub(1, 100));
    }

    public function testReleaseFromHubDoesNotTouchOtherTargetTypes(): void
    {
        $this->db->exec("INSERT INTO employee_assignments
            (id, player_id, source_type, source_id, target_type, target_id, allocation_pct, status, assigned_at, created_at, updated_at)
            VALUES
            (501, 1, 'board_member', 10, 'pipeline', 900, 25, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $this->assertFalse($this->service->releaseFromHub(1, 501));

        $stmt = $this->db->query("SELECT status FROM employee_assignments WHERE id = 501 LIMIT 1");
        $this->assertSame('active', $stmt->fetchColumn());
    }

    public function testNonOperatorIsNotListedAndCannotBeAssignedToHub(): void
    {
        $view = $this->service->buildHubStaffingView(1, [
            ['hub' => $this->hubRow(100)],
        ]);

        $candidateNames = array_column($view[100]['candidates'], 'full_name');
        $this->assertNotContains('Anna Nowak', $candidateNames);
        $this->assertNotContains('Adam Finance', $candidateNames);

        $this->expectException(RuntimeException::class);
        $this->service->assignToHub(1, EmployeeRef::SOURCE_BOARD_MEMBER, 12, 100, 100.0);
    }

    private function createSourceSchema(): void
    {
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE hr_specializations (
            id INTEGER PRIMARY KEY,
            code TEXT NOT NULL,
            name TEXT NOT NULL,
            department TEXT NOT NULL DEFAULT "technical",
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
            experience_years INTEGER NOT NULL DEFAULT 0,
            skill_level INTEGER NOT NULL DEFAULT 5,
            salary REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'active',
            hired_at TEXT NULL
        )");
        $this->db->exec("CREATE TABLE logistics_hubs (
            id INTEGER PRIMARY KEY,
            player_id INTEGER NOT NULL,
            tenant_player_id INTEGER NOT NULL DEFAULT 0,
            name TEXT NOT NULL,
            hub_type TEXT NOT NULL DEFAULT 'medium',
            slot_limit INTEGER NOT NULL DEFAULT 4,
            status TEXT NOT NULL DEFAULT 'active'
        )");
    }

    private function seedData(): void
    {
        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'logistics')");
        $this->db->exec("INSERT INTO hr_specializations (id, code, name, base_salary_min, base_salary_max)
            VALUES
            (1, 'hub_operator', 'Hub Operator', 8000, 12000),
            (2, 'accountant', 'Accountant', 8000, 12000)");
        $this->db->exec("INSERT INTO board_members
            (id, player_id, member_type, role_id, specialization_id, first_name, last_name,
             experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress,
             skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary, status, hired_at)
            VALUES
            (10, 1, 'staff', 1, 1, 'Anna', 'Nowak', 5, 8, 7, 8, 6, 8, 9, 2, 7, 9000, 'active', '2026-01-01 10:00:00'),
            (11, 1, 'staff', 1, 1, 'Ewa', 'Inactive', 5, 8, 7, 8, 6, 8, 9, 2, 7, 9000, 'fired', '2026-01-01 10:00:00'),
            (12, 1, 'staff', 1, 2, 'Adam', 'Finance', 5, 8, 7, 8, 6, 8, 9, 2, 7, 9000, 'active', '2026-01-01 10:00:00')");
        $this->db->exec("INSERT INTO technical_staff
            (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name,
             experience_years, skill_level, salary, status, hired_at)
            VALUES
            (20, 1, 0, 'Jan', 'Operator', 'hub_operator', NULL, 'Hub Operator', 5, 8, 9500, 'active', '2026-01-01 10:00:00')");
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, tenant_player_id, name, hub_type, slot_limit, status)
            VALUES
            (100, 1, 0, 'Hub A', 'medium', 4, 'active')");
    }

    /** @return array<string, mixed> */
    private function hubRow(int $hubId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM logistics_hubs WHERE id = ? LIMIT 1');
        $stmt->execute([$hubId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);

        return $row;
    }
}
