<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRepository.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeStateService.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeLegacyMigrationService.php';
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
        $this->assertSame(0, $repeated['mirrored_records_skipped']);
        $this->assertSame(2, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());
    }

    public function testSourceOwnershipPreventsCrossPlayerStateCreation(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->ensureState(new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 2));
    }

    public function testExplicitReconciliationMigratesCompleteLegacyStateToCanonicalTechnicalState(): void
    {
        $boardRef = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->service->ensureState($boardRef);
        $this->db->exec("UPDATE employee_state
            SET morale = 33, leave_risk = 71, relation_status = 'leaving',
                loyalty_modifier = 7, leave_risk_streak = 3,
                last_morale_cycle_id = 44, leaving_at = '2026-07-28 10:00:00'
            WHERE player_id = 1 AND source_type = 'board_member' AND source_id = 10");
        $this->assertSame(1, $this->repository->syncLegacyMirrorLinks(1));

        $readOnlyState = $this->service->getState($boardRef);
        $this->assertSame(EmployeeRef::SOURCE_BOARD_MEMBER, $readOnlyState['source_type']);
        $this->assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());

        $state = $this->service->reconcileCanonicalState($boardRef);

        $this->assertSame(EmployeeRef::SOURCE_TECHNICAL_STAFF, $state['source_type']);
        $this->assertSame(21, $state['source_id']);
        $this->assertSame(33.0, $state['morale']);
        $this->assertSame(71.0, $state['leave_risk']);
        $this->assertSame('leaving', $state['relation_status']);
        $this->assertSame(7.0, $state['loyalty_modifier']);
        $this->assertSame(3, $state['leave_risk_streak']);
        $this->assertSame(44, $state['last_morale_cycle_id']);
        $this->assertSame('2026-07-28 10:00:00', $state['leaving_at']);
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

    public function testLegacyMigratorSupportsDryRunApplyRetryAndPlayerFilter(): void
    {
        $this->db->exec('CREATE TABLE staff_strikes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            technical_staff_id INTEGER NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NULL
        )');
        $this->db->exec("INSERT INTO staff_strikes (technical_staff_id, start_time, end_time)
            VALUES (20, '2026-07-20 10:00:00', NULL)");
        $migration = new EmployeeLegacyMigrationService($this->db);

        $dryRun = $migration->run(false, 1);
        $this->assertFalse($dryRun['applied']);
        $this->assertSame(2, $dryRun['state_backfill']['would_create']);
        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());

        $applied = $migration->run(true, 1);
        $this->db->exec(
            "UPDATE employee_strike_members SET left_at='2026-07-21 10:00:00'
              WHERE player_id=1 AND source_type='technical_staff' AND source_id=20"
        );
        $this->db->exec(
            "UPDATE employee_state SET relation_status='normal'
              WHERE player_id=1 AND source_type='technical_staff' AND source_id=20"
        );
        $repeated = $migration->run(true, 1);
        $otherPlayer = $migration->run(false, 2);

        $this->assertSame(2, $applied['state_backfill']['created']);
        $this->assertSame(1, $applied['active_strike_groups']);
        $this->assertSame(1, $applied['strikes_created']);
        $this->assertSame(0, $repeated['state_backfill']['created']);
        $this->assertSame(0, $repeated['strikes_created']);
        $this->assertSame(1, $repeated['members_reactivated']);
        $this->assertNull($this->db->query(
            "SELECT left_at FROM employee_strike_members
              WHERE player_id=1 AND source_type='technical_staff' AND source_id=20"
        )->fetchColumn() ?: null);
        $this->assertSame('on_strike', (string)$this->db->query(
            "SELECT relation_status FROM employee_state
              WHERE player_id=1 AND source_type='technical_staff' AND source_id=20"
        )->fetchColumn());
        $this->assertSame(1, $otherPlayer['state_backfill']['would_create']);
        $this->assertSame(0, $otherPlayer['active_strike_groups']);
        $this->assertSame(1, (int)$this->db->query(
            'SELECT COUNT(*) FROM employee_strikes WHERE player_id=1'
        )->fetchColumn());
    }

    public function testLegacyMigratorConstructionDoesNotCreateSchema(): void
    {
        $db = $this->createSqlitePdo();
        $before = (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();

        new EmployeeLegacyMigrationService($db);

        $after = (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
        $this->assertSame($before, $after);
    }

    public function testSchemaUpgradePreservesDefaultTechnicalTraits(): void
    {
        $this->assertSame(
            [5, 5, 5],
            $this->db->query(
                'SELECT trait_loyalty, trait_corruption_risk, trait_ambition
                   FROM technical_staff WHERE id=20'
            )->fetch(PDO::FETCH_NUM)
        );
    }

    public function testLegacyMigratorPreservesMixedCanonicalState(): void
    {
        $existing = $this->service->ensureState(
            new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1)
        );
        $this->db->exec("UPDATE employee_state SET morale=41 WHERE id=" . (int)$existing['id']);

        $report = (new EmployeeLegacyMigrationService($this->db))->run(true, 1);

        $this->assertSame(1, $report['state_backfill']['created']);
        $this->assertSame(1, $report['state_backfill']['skipped']);
        $this->assertSame(41.0, (float)$this->db->query(
            "SELECT morale FROM employee_state
              WHERE player_id=1 AND source_type='technical_staff' AND source_id=20"
        )->fetchColumn());
    }

    public function testLegacyMigratorRollsBackPartialBackfillOnError(): void
    {
        $this->db->exec(
            "CREATE TRIGGER fail_second_employee_state
             BEFORE INSERT ON employee_state
             WHEN NEW.source_id=21
             BEGIN SELECT RAISE(ABORT, 'forced migration failure'); END"
        );

        try {
            (new EmployeeLegacyMigrationService($this->db))->run(true, 1);
            $this->fail('A failed backfill must abort the migration.');
        } catch (RuntimeException) {
            $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());
        }
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
