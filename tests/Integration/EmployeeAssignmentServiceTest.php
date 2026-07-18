<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRepository.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeStateService.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeAssignmentService.php';
require_once dirname(__DIR__, 2) . '/src/Employee/LogisticsStaffingService.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once __DIR__ . '/SqliteIntegrationTestCase.php';

final class EmployeeAssignmentServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private EmployeeAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSourceSchema();
        $this->seedData();
        $this->service = new EmployeeAssignmentService($this->db);
    }

    public function testAssignsActiveEmployeeToOwnedHub(): void
    {
        $result = $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1),
            100,
            60.0
        );

        $this->assertTrue($result['success']);
        $this->assertSame(60.0, $result['allocation_pct']);
        $rows = $this->service->listForHub(1, 100);
        $this->assertCount(1, $rows);
        $this->assertSame('hub', $rows[0]['target_type']);
        $this->assertSame(10, (int)$rows[0]['source_id']);
    }

    public function testRejectsForeignHub(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hub does not belong to this player.');

        $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1),
            200,
            50.0
        );
    }

    public function testAssignsEmployeeToRentedHubControlledByTenant(): void
    {
        $result = $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1),
            400,
            50.0
        );

        $this->assertTrue($result['success']);
        $this->assertCount(1, $this->service->listForHub(1, 400));
    }

    public function testForeignPlayerCannotReleaseAssignment(): void
    {
        $result = $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1),
            100,
            100.0
        );

        $this->assertFalse($this->service->release((int)$result['assignment_id'], 2));
        $this->assertCount(1, $this->service->listForHub(1, 100));
    }

    public function testRejectsInactiveEmployee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Employee is not active.');

        $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 11, 1),
            100,
            50.0
        );
    }

    public function testRejectsAllocationAboveOneHundredPercent(): void
    {
        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->service->assignToHub($ref, 100, 70.0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Employee assignment allocation exceeds 100%.');

        $this->service->assignToHub($ref, 101, 40.0);
    }

    public function testRejectsBlockedRelationStatus(): void
    {
        $state = new EmployeeStateService($this->db);
        $state->ensureState(new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1));
        $this->db->exec("UPDATE employee_state
            SET relation_status = 'on_strike'
            WHERE player_id = 1 AND source_type = 'board_member' AND source_id = 10");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Employee relation status blocks assignment.');

        $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1),
            100,
            50.0
        );
    }

    public function testReleaseClosesActiveAssignment(): void
    {
        $result = $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1),
            100,
            100.0
        );

        $this->assertTrue($this->service->release((int)$result['assignment_id'], 1));
        $this->assertSame([], $this->service->listForHub(1, 100));
    }

    public function testHubStaffingCalculatesCoverageAndMultipliers(): void
    {
        $staffing = new LogisticsStaffingService($this->db);
        $empty = $staffing->hubStaffing($this->hubRow(100));
        $this->assertSame(0.0, $empty['coverage_pct']);
        $this->assertLessThan(1.0, $empty['throughput_mult']);
        $this->assertGreaterThan(1.0, $empty['incident_risk_mult']);
        $this->assertSame(['hub_operator'], $empty['missing_roles']);

        $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1),
            100,
            50.0
        );
        $partial = $staffing->hubStaffing($this->hubRow(100));
        $this->assertSame(25.0, $partial['coverage_pct']);
        $this->assertLessThan(1.0, $partial['throughput_mult']);
        $this->assertSame([], $partial['missing_roles']);
        $this->assertGreaterThan(0.0, $partial['operator_throughput_bonus_pct']);

        $unassigned = $staffing->hubStaffing($this->hubRow(101));
        $this->assertSame(0.0, $unassigned['operator_throughput_bonus_pct']);

        $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 21, 1),
            100,
            100.0
        );
        $full = $staffing->hubStaffing($this->hubRow(100));
        $this->assertSame(75.0, $full['coverage_pct']);
        $this->assertGreaterThan($partial['throughput_mult'], $full['throughput_mult']);
        $this->assertLessThan($partial['incident_risk_mult'], $full['incident_risk_mult']);
        $this->assertArrayHasKey('hub_throughput_pct', $full['runtime_effects']);
        $this->assertArrayHasKey('incident_mult', $full['runtime_incident_mods']);
    }

    public function testHubStaffingUsesConfiguredRequirementForHubType(): void
    {
        $this->db->exec("INSERT INTO well_config (`key`, `value`) VALUES ('employee_hub_staff_required_medium', '4')");
        $this->service->assignToHub(
            new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1),
            100,
            100.0
        );

        $staffing = (new LogisticsStaffingService($this->db))->hubStaffing($this->hubRow(100));

        $this->assertSame(4, $staffing['required_count']);
        $this->assertSame(25.0, $staffing['coverage_pct']);
    }

    public function testHubStaffingReadsDecimalRuntimeFlagFromMySqlCompatibleConfig(): void
    {
        $this->db->exec("INSERT INTO well_config (`key`, `value`) VALUES ('employee_hub_staffing_enabled', '1.00')");

        $staffing = new LogisticsStaffingService($this->db);

        $this->assertTrue($staffing->isRuntimeEnabled());
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
            experience_years INTEGER NOT NULL,
            skill_level INTEGER NOT NULL,
            salary REAL NOT NULL,
            status TEXT NOT NULL,
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
        $this->db->exec("CREATE TABLE well_config (
            `key` TEXT PRIMARY KEY,
            `value` TEXT NOT NULL
        )");
    }

    private function seedData(): void
    {
        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'logistics')");
        $this->db->exec("INSERT INTO hr_specializations (id, code, name, base_salary_min, base_salary_max)
            VALUES (1, 'hub_operator', 'Hub Operator', 8000, 12000)");
        $this->db->exec("INSERT INTO board_members
            (id, player_id, member_type, role_id, specialization_id, first_name, last_name,
             experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress,
             skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary, status, hired_at)
            VALUES
            (10, 1, 'staff', 1, 1, 'Anna', 'Nowak', 5, 8, 7, 8, 6, 8, 9, 2, 7, 9000, 'active', '2026-01-01 10:00:00'),
            (11, 1, 'staff', 1, 1, 'Ewa', 'Inactive', 5, 8, 7, 8, 6, 8, 9, 2, 7, 9000, 'fired', '2026-01-01 10:00:00')");
        $this->db->exec("INSERT INTO technical_staff
            (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name,
             experience_years, skill_level, salary, status, hired_at)
            VALUES
            (20, 1, 0, 'Jan', 'Tech', 'hub_operator', NULL, 'Hub Operator', 4, 7, 9500, 'active', '2026-01-01 10:00:00'),
            (21, 1, 0, 'Piotr', 'Operator', 'hub_operator', NULL, 'Hub Operator', 4, 8, 9800, 'active', '2026-01-01 10:00:00')");
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, tenant_player_id, name, hub_type, slot_limit, status)
            VALUES
            (100, 1, 0, 'Hub A', 'medium', 4, 'active'),
            (101, 1, 0, 'Hub A2', 'medium', 4, 'active'),
            (200, 2, 0, 'Hub B', 'medium', 4, 'active'),
            (300, 1, 0, 'Hub Planned', 'medium', 4, 'planned'),
            (400, 0, 1, 'Hub Rented', 'small', 1, 'active')");
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
