<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/TechnicalTeamService.php';

final class TechnicalStrikeEffectsTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $_SESSION = [];
    }

    public function testBlockedRelationsCannotStartTasksAndRespectPlayerOwnership(): void
    {
        foreach (['on_strike', 'leaving', 'inactive'] as $index => $status) {
            $staffId = $index + 1;
            $this->seedStaff($staffId, 'safety_engineer');
            $this->db->prepare(
                "INSERT INTO employee_state (player_id, source_type, source_id, relation_status)
                 VALUES (1, 'technical_staff', ?, ?)"
            )->execute([$staffId, $status]);

            $result = $this->makeService()->assignTask($staffId, 'safety_audit');

            $this->assertFalse($result['success'], $status);
        }

        $this->seedStaff(10, 'safety_engineer');
        $this->db->exec(
            "INSERT INTO employee_state (player_id, source_type, source_id, relation_status)
             VALUES (2, 'technical_staff', 10, 'on_strike')"
        );

        $result = $this->makeService()->assignTask(10, 'safety_audit');

        $this->assertTrue($result['success'], $result['message']);
    }

    public function testStartedTaskPausesAndResumesWithPreservedRemainingTime(): void
    {
        $this->seedStaff(1, 'safety_engineer');
        $this->db->exec(
            "INSERT INTO employee_state (player_id, source_type, source_id, relation_status)
             VALUES (1, 'technical_staff', 1, 'on_strike')"
        );
        $this->db->exec(
            "INSERT INTO technical_tasks
                (id, player_id, staff_id, task_type, title, start_time, end_time, duration_hours, cost, status)
             VALUES
                (1, 1, 1, 'safety_audit', 'Audit', datetime('now'), datetime('now', '+4 hours'), 4, 0, 'in_progress')"
        );
        $service = $this->makeService();

        $service->processTick();

        $paused = $this->db->query(
            'SELECT status, end_time, strike_paused_at FROM technical_tasks WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('paused_strike', $paused['status']);
        $this->assertNotEmpty($paused['strike_paused_at']);

        $this->db->exec("UPDATE technical_tasks SET strike_paused_at = datetime('now', '-2 hours') WHERE id = 1");
        $this->db->exec(
            "UPDATE employee_state SET relation_status = 'normal'
             WHERE player_id = 1 AND source_type = 'technical_staff' AND source_id = 1"
        );
        $service->processTick();

        $resumed = $this->db->query(
            'SELECT status, end_time, strike_paused_at FROM technical_tasks WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        $shift = strtotime((string)$resumed['end_time']) - strtotime((string)$paused['end_time']);
        $this->assertSame('in_progress', $resumed['status']);
        $this->assertNull($resumed['strike_paused_at']);
        $this->assertGreaterThanOrEqual(7195, $shift);
        $this->assertLessThanOrEqual(7205, $shift);
    }

    public function testRepairDurationAndEmergencyCostAreSnapshottedAtStart(): void
    {
        $this->seedStaff(1, 'drilling_engineer');
        $this->db->exec(
            "INSERT INTO employee_state (player_id, source_type, source_id, relation_status)
             VALUES (1, 'technical_staff', 1, 'normal')"
        );
        $this->db->exec(
            "INSERT INTO wells (id, player_id, location_name, status)
             VALUES (10, 1, 'Test well', 'blowout')"
        );
        $definition = TechnicalTeamService::getTaskDefinition('blowout_control');

        $result = $this->makeService([
            'repair_time_mult' => 1.40,
            'emergency_cost_mult' => 1.15,
        ])->assignTask(1, 'blowout_control', 10);

        $this->assertTrue($result['success'], $result['message']);
        $task = $this->db->query(
            "SELECT duration_hours, cost FROM technical_tasks WHERE task_type = 'blowout_control'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertGreaterThanOrEqual((int)round($definition['hours_min'] * 1.40), (int)$task['duration_hours']);
        $this->assertLessThanOrEqual((int)round($definition['hours_max'] * 1.40), (int)$task['duration_hours']);
        $this->assertGreaterThanOrEqual((int)round($definition['cost_min'] * 1.15), (int)$task['cost']);
        $this->assertLessThanOrEqual((int)round($definition['cost_max'] * 1.15), (int)$task['cost']);
    }

    public function testEmergencyCostMultiplierDoesNotAffectRegularTask(): void
    {
        $this->seedStaff(1, 'safety_engineer');
        $this->db->exec(
            "INSERT INTO employee_state (player_id, source_type, source_id, relation_status)
             VALUES (1, 'technical_staff', 1, 'normal')"
        );
        $definition = TechnicalTeamService::getTaskDefinition('safety_audit');

        $result = $this->makeService([
            'repair_time_mult' => 1.40,
            'emergency_cost_mult' => 3.00,
        ])->assignTask(1, 'safety_audit');

        $this->assertTrue($result['success'], $result['message']);
        $task = $this->db->query(
            "SELECT duration_hours, cost FROM technical_tasks WHERE task_type = 'safety_audit'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertGreaterThanOrEqual((int)$definition['hours_min'], (int)$task['duration_hours']);
        $this->assertLessThanOrEqual((int)$definition['hours_max'], (int)$task['duration_hours']);
        $this->assertGreaterThanOrEqual((int)$definition['cost_min'], (int)$task['cost']);
        $this->assertLessThanOrEqual((int)$definition['cost_max'], (int)$task['cost']);
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, cash REAL NOT NULL DEFAULT 100000000, bank_balance REAL NOT NULL DEFAULT 0, safety_procedures_level INTEGER DEFAULT 0, procedure_integrity REAL DEFAULT 100, procedures_last_decay_at TEXT NULL)');
        $this->db->exec('CREATE TABLE technical_staff (id INTEGER PRIMARY KEY, player_id INTEGER, first_name TEXT, last_name TEXT, spec_code TEXT, specialization TEXT NULL, spec_name TEXT, skill_level INTEGER, salary REAL DEFAULT 0, status TEXT, fired_at TEXT NULL)');
        $this->db->exec('CREATE TABLE employee_state (id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, source_type TEXT, source_id INTEGER, relation_status TEXT)');
        $this->db->exec('CREATE TABLE technical_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, staff_id INTEGER, task_type TEXT, well_id INTEGER NULL, hub_id INTEGER NULL, pipeline_id INTEGER NULL, title TEXT, module_type TEXT NULL, start_time TEXT NULL, end_time TEXT NULL, strike_paused_at TEXT NULL, duration_hours INTEGER DEFAULT 0, cost REAL DEFAULT 0, status TEXT, result_data TEXT NULL, notified INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE technical_task_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, staff_id INTEGER, task_type TEXT, well_id INTEGER NULL, hub_id INTEGER NULL, pipeline_id INTEGER NULL, module_type TEXT NULL, priority INTEGER DEFAULT 0, queued_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE technical_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, well_id INTEGER NULL, type TEXT, message TEXT, is_read INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, player_id INTEGER, location_name TEXT, status TEXT, paused_staff_reason TEXT NULL, service_prev_status TEXT NULL)');
        $this->db->exec('CREATE TABLE logistics_hubs (id INTEGER PRIMARY KEY, player_id INTEGER, tenant_player_id INTEGER DEFAULT 0, name TEXT, status TEXT)');
        $this->db->exec('CREATE TABLE logistics_hub_assignments (id INTEGER PRIMARY KEY, hub_id INTEGER, well_id INTEGER, status TEXT)');
        $this->db->exec('CREATE TABLE well_pipelines (id INTEGER PRIMARY KEY, player_id INTEGER, name TEXT, status TEXT, service_prev_status TEXT NULL)');
        $this->db->exec('CREATE TABLE staff_specializations (code TEXT PRIMARY KEY, name TEXT, repair_speed REAL DEFAULT 0)');
    }

    private function seedStaff(int $id, string $specCode): void
    {
        $this->db->exec('INSERT OR IGNORE INTO players (id, cash) VALUES (1, 100000000)');
        $stmt = $this->db->prepare(
            "INSERT INTO technical_staff
                (id, player_id, first_name, last_name, spec_code, spec_name, skill_level, status)
             VALUES (?, 1, 'Jan', 'Test', ?, ?, 6, 'active')"
        );
        $stmt->execute([$id, $specCode, $specCode]);
    }

    /**
     * @param array<string,float|bool> $effect
     */
    private function makeService(array $effect = []): TechnicalTeamService
    {
        $service = new class($effect) extends TechnicalTeamService {
            /** @param array<string,float|bool> $effect */
            public function __construct(private readonly array $effect)
            {
            }

            public function getManager(): ?array
            {
                return null;
            }

            public function getManagerBonus(?array $manager): array
            {
                return ['skill' => 0, 'time_mult' => 1.0, 'cost_mult' => 1.0, 'label' => 'neutral'];
            }

            public function getStaffBonus(array $staff, ?string $taskType = null): array
            {
                return ['time_mult' => 1.0, 'cost_mult' => 1.0, 'success_bonus' => 0.0];
            }

            protected function getTechnicalStrikeEffect(): array
            {
                return $this->effect;
            }
        };
        $this->setPrivateProperty($service, TechnicalTeamService::class, 'db', $this->db);
        $this->setPrivateProperty($service, TechnicalTeamService::class, 'playerId', 1);
        return $service;
    }
}
