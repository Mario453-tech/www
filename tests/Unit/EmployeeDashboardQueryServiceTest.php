<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/HR/EmployeeDashboardQueryService.php';

final class EmployeeDashboardQueryServiceTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
    }

    public function testBuildsPlayerScopedCanonicalDashboard(): void
    {
        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'hr')");
        $this->db->exec(
            "INSERT INTO board_members
                (id, player_id, first_name, last_name, member_type, role_id, experience_years,
                 skill_organization, skill_negotiation, skill_analysis, skill_stress, skill_ethics,
                 trait_loyalty, trait_corruption_risk, trait_ambition, salary, status, hired_at)
             VALUES
                (11, 7, 'Anna', 'Nowak', 'staff', 1, 10, 8, 8, 8, 8, 8, 7, 1, 6, 12000, 'active', '2025-01-01'),
                (12, 8, 'Other', 'Player', 'staff', 1, 1, 4, 4, 4, 4, 4, 2, 2, 2, 5000, 'active', '2025-01-01')"
        );
        $this->db->exec(
            "INSERT INTO employee_state
                (player_id, source_type, source_id, department_code, morale, salary_satisfaction,
                 expected_salary, leave_risk, strike_support, workload, relation_status)
             VALUES
                (7, 'board_member', 11, 'hr', 74, 68, 13500, 21, 9, 55, 'normal'),
                (8, 'board_member', 12, 'hr', 10, 10, 9000, 90, 90, 90, 'dispute')"
        );
        $this->db->exec(
            "INSERT INTO employee_assignments
                (player_id, source_type, source_id, target_type, target_id, allocation_pct, status, assigned_at)
             VALUES (7, 'board_member', 11, 'department', 1, 100, 'active', '2026-01-01')"
        );
        $this->db->exec(
            "INSERT INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, created_at)
             VALUES (7, 'board_member', 11, 'morale_changed', 'event.title', 'event.message', '{}', '2026-01-01')"
        );

        $service = new EmployeeDashboardQueryService($this->db);
        $dashboard = $service->forPlayer(7);

        self::assertCount(1, $dashboard['employees']);
        self::assertSame('senior', $dashboard['employees'][0]['seniority']);
        self::assertSame(13500.0, $dashboard['employees'][0]['expected_salary']);
        self::assertCount(1, $dashboard['employees'][0]['assignments']);
        self::assertSame(74.0, $dashboard['morale']['average_morale']);
        self::assertCount(1, $dashboard['events']);
        self::assertSame('event:1', $dashboard['events'][0]['record_key']);
        self::assertSame('employee:board_member:11', $dashboard['events'][0]['employee_record_key']);
        self::assertTrue($dashboard['events'][0]['is_unread']);
        self::assertNull($this->db->query('SELECT notified_at FROM employee_events WHERE id=1')->fetchColumn());
        self::assertSame(1, $dashboard['event_pagination']['unread_count']);

        self::assertSame(1, $service->markEventsNotified(7, [1]));
        self::assertNotFalse($this->db->query('SELECT notified_at FROM employee_events WHERE id=1')->fetchColumn());
        self::assertSame(1, $service->markEventsRead(7, [1]));
        self::assertSame(1, (int)$this->db->query('SELECT is_read FROM employee_events WHERE id=1')->fetchColumn());
    }

    public function testPaginatesEventsAndCountsAllUnreadRows(): void
    {
        $insert = $this->db->prepare(
            "INSERT INTO employee_events
                (player_id, event_key, title_key, message_key, meta_json, created_at)
             VALUES (7, 'morale_changed', 'event.title', 'event.message', '{}', ?)"
        );
        for ($event = 1; $event <= 25; $event++) {
            $insert->execute([sprintf('2026-01-%02d 12:00:00', $event)]);
        }

        $dashboard = (new EmployeeDashboardQueryService($this->db))->forPlayer(7, 2, 10);

        self::assertCount(10, $dashboard['events']);
        self::assertSame(2, $dashboard['event_pagination']['page']);
        self::assertSame(3, $dashboard['event_pagination']['pages']);
        self::assertSame(25, $dashboard['event_pagination']['total']);
        self::assertSame(25, $dashboard['event_pagination']['unread_count']);
        self::assertSame('event:15', $dashboard['events'][0]['record_key']);
        self::assertStringContainsString('event_page=2', $dashboard['events'][0]['deep_link']);
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT)');
        $this->db->exec(
            'CREATE TABLE hr_specializations (
                id INTEGER PRIMARY KEY, code TEXT, name TEXT, base_salary_min REAL, base_salary_max REAL
            )'
        );
        $this->db->exec(
            'CREATE TABLE board_members (
                id INTEGER PRIMARY KEY, player_id INTEGER, first_name TEXT, last_name TEXT,
                member_type TEXT, role_id INTEGER, specialization_id INTEGER, experience_years INTEGER,
                skill_organization INTEGER, skill_negotiation INTEGER, skill_analysis INTEGER,
                skill_stress INTEGER, skill_ethics INTEGER, trait_loyalty INTEGER,
                trait_corruption_risk INTEGER, trait_ambition INTEGER, salary REAL,
                status TEXT, hired_at TEXT
            )'
        );
        $this->db->exec(
            'CREATE TABLE technical_staff (
                id INTEGER PRIMARY KEY, player_id INTEGER, manager_id INTEGER, first_name TEXT,
                last_name TEXT, spec_code TEXT, specialization TEXT, spec_name TEXT,
                experience_years INTEGER, skill_level INTEGER, trait_loyalty INTEGER,
                trait_corruption_risk INTEGER, trait_ambition INTEGER, salary REAL,
                status TEXT, hired_at TEXT
            )'
        );
        $this->db->exec(
            'CREATE TABLE employee_state (
                player_id INTEGER, source_type TEXT, source_id INTEGER, department_code TEXT,
                morale REAL, salary_satisfaction REAL, expected_salary REAL, leave_risk REAL,
                strike_support REAL, workload REAL, relation_status TEXT
            )'
        );
        $this->db->exec(
            'CREATE TABLE employee_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, source_type TEXT,
                source_id INTEGER, target_type TEXT, target_id INTEGER, allocation_pct REAL,
                status TEXT, assigned_at TEXT
            )'
        );
        $this->db->exec(
            'CREATE TABLE employee_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, source_type TEXT,
                source_id INTEGER, strike_id INTEGER, event_key TEXT, title_key TEXT,
                message_key TEXT, meta_json TEXT, is_read INTEGER NOT NULL DEFAULT 0,
                notified_at TEXT NULL, created_at TEXT
            )'
        );
        $this->db->exec(
            'CREATE TABLE training_programs (
                id INTEGER PRIMARY KEY, name_pl TEXT, name_en TEXT, target_skill TEXT
            )'
        );
        $this->db->exec(
            'CREATE TABLE staff_trainings (
                id INTEGER PRIMARY KEY, player_id INTEGER, staff_type TEXT, staff_id INTEGER,
                program_id INTEGER, status TEXT, started_at TEXT, finishes_at TEXT, exam_score INTEGER
            )'
        );
    }
}
