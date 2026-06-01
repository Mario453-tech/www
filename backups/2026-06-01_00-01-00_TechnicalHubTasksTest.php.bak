<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/TechnicalTeamService.php';

final class TechnicalHubTasksTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
    }

    public function testAssignTaskRejectsHubTaskWithoutHubId(): void
    {
        $this->seedPlayerAndStaff();
        $service = $this->makeService();

        $result = $service->assignTask(1, 'hub_maintenance');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('wymaga wskazania huba', $result['message']);
    }

    public function testAssignTaskRejectsHubNotUsedByPlayer(): void
    {
        $this->seedPlayerAndStaff();
        $this->db->exec("INSERT INTO logistics_hubs (id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES (99, 'Hub Obcy', 70, 100000, 'active', datetime('now'))");
        $service = $this->makeService();

        $result = $service->assignTask(1, 'hub_maintenance', null, null, 99);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Hub nie istnieje albo nie jest jeszcze', $result['message']);
    }

    public function testAssignTaskQueuesBusyWorkerAndKeepsHubId(): void
    {
        $this->seedPlayerAndStaff();
        $this->seedUsedHub(10);
        $this->db->exec("INSERT INTO technical_tasks (id, player_id, staff_id, task_type, hub_id, title, status, start_time, end_time, duration_hours, cost) VALUES (1, 1, 1, 'hub_maintenance', 10, 'Busy task', 'in_progress', datetime('now'), datetime('now', '+1 hour'), 1, 0)");
        $service = $this->makeService();

        $result = $service->assignTask(1, 'hub_repair', null, null, 10);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['queued']);

        $queue = $this->db->query("SELECT task_type, hub_id FROM technical_task_queue")->fetch();
        $this->assertSame(['task_type' => 'hub_repair', 'hub_id' => 10], $queue);
    }

    public function testCompleteTaskHubMaintenanceImprovesConditionAndMaintenanceTimestamp(): void
    {
        $this->seedPlayerAndStaff(7);
        $this->db->exec("INSERT INTO logistics_hubs (id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES (10, 'Hub A', 40, 500000, 'active', datetime('now'))");
        $service = $this->makeService();

        $task = [
            'id' => 2,
            'staff_id' => 1,
            'hub_id' => 10,
            'well_id' => null,
            'player_id' => 1,
            'task_type' => 'hub_maintenance',
            'title' => 'Konserwacja',
            'skill_level' => 7,
        ];

        $this->db->exec("INSERT INTO technical_tasks (id, player_id, staff_id, task_type, hub_id, title, status, start_time, end_time, duration_hours, cost) VALUES (2, 1, 1, 'hub_maintenance', 10, 'Konserwacja', 'in_progress', datetime('now'), datetime('now'), 1, 0)");
        $this->db->exec("UPDATE technical_staff SET status = 'busy' WHERE id = 1");

        $service->completeTask($task);

        $hub = $this->db->query("SELECT condition_pct, repair_cost_estimate, last_maintenance_at FROM logistics_hubs WHERE id = 10")->fetch();
        $taskRow = $this->db->query("SELECT status FROM technical_tasks WHERE id = 2")->fetchColumn();
        $staffStatus = $this->db->query("SELECT status FROM technical_staff WHERE id = 1")->fetchColumn();

        $this->assertSame(58.0, (float) $hub['condition_pct']);
        $this->assertSame(50000.0, (float) $hub['repair_cost_estimate']);
        $this->assertNotEmpty($hub['last_maintenance_at']);
        $this->assertSame('completed', $taskRow);
        $this->assertSame('active', $staffStatus);
    }

    public function testCompleteTaskHubRepairRestoresOperationalState(): void
    {
        $this->seedPlayerAndStaff(8);
        $this->db->exec("INSERT INTO logistics_hubs (id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES (11, 'Hub B', 15, 750000, 'damaged', datetime('now'))");
        $service = $this->makeService();

        $task = [
            'id' => 3,
            'staff_id' => 1,
            'hub_id' => 11,
            'well_id' => null,
            'player_id' => 1,
            'task_type' => 'hub_repair',
            'title' => 'Naprawa',
            'skill_level' => 8,
        ];

        $this->db->exec("INSERT INTO technical_tasks (id, player_id, staff_id, task_type, hub_id, title, status, start_time, end_time, duration_hours, cost) VALUES (3, 1, 1, 'hub_repair', 11, 'Naprawa', 'in_progress', datetime('now'), datetime('now'), 1, 0)");
        $this->db->exec("UPDATE technical_staff SET status = 'busy' WHERE id = 1");

        $service->completeTask($task);

        $hub = $this->db->query("SELECT condition_pct, repair_cost_estimate, status, last_maintenance_at FROM logistics_hubs WHERE id = 11")->fetch();

        $this->assertSame(92.0, (float) $hub['condition_pct']);
        $this->assertSame(0.0, (float) $hub['repair_cost_estimate']);
        $this->assertSame('active', $hub['status']);
        $this->assertNotEmpty($hub['last_maintenance_at']);
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, cash REAL, safety_procedures_level INTEGER DEFAULT 0, procedure_integrity REAL DEFAULT 100, procedures_last_decay_at TEXT NULL)');
        $this->db->exec('CREATE TABLE technical_staff (id INTEGER PRIMARY KEY, player_id INTEGER, first_name TEXT, last_name TEXT, spec_code TEXT, spec_name TEXT, skill_level INTEGER, salary REAL DEFAULT 0, status TEXT, fired_at TEXT NULL)');
        $this->db->exec('CREATE TABLE technical_tasks (id INTEGER PRIMARY KEY, player_id INTEGER, staff_id INTEGER, task_type TEXT, well_id INTEGER NULL, hub_id INTEGER NULL, title TEXT, module_type TEXT NULL, start_time TEXT NULL, end_time TEXT NULL, duration_hours INTEGER DEFAULT 0, cost REAL DEFAULT 0, status TEXT, result_data TEXT NULL, notified INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE technical_task_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, staff_id INTEGER, task_type TEXT, well_id INTEGER NULL, hub_id INTEGER NULL, module_type TEXT NULL, priority INTEGER DEFAULT 0, queued_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE technical_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, well_id INTEGER NULL, type TEXT, message TEXT, is_read INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, player_id INTEGER, status TEXT)');
        $this->db->exec('CREATE TABLE logistics_hubs (id INTEGER PRIMARY KEY, name TEXT, condition_pct REAL, repair_cost_estimate REAL, status TEXT, last_maintenance_at TEXT NULL, updated_at TEXT NULL)');
        $this->db->exec('CREATE TABLE logistics_hub_assignments (id INTEGER PRIMARY KEY AUTOINCREMENT, hub_id INTEGER, well_id INTEGER, status TEXT)');
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT)');
        $this->db->exec('CREATE TABLE board_members (id INTEGER PRIMARY KEY, role_id INTEGER, status TEXT, specialization_id INTEGER NULL, skill_organization INTEGER DEFAULT 5)');
        $this->db->exec('CREATE TABLE hr_specializations (id INTEGER PRIMARY KEY, code TEXT, name TEXT)');
        $this->db->exec('CREATE TABLE staff_specializations (code TEXT PRIMARY KEY, name TEXT, rarity TEXT, prod_bonus REAL DEFAULT 0, wear_reduction REAL DEFAULT 0, incident_reduction REAL DEFAULT 0, spiral_reduction REAL DEFAULT 0, catastrophe_reduction REAL DEFAULT 0)');
        $this->db->exec('CREATE TABLE pipelines (id INTEGER PRIMARY KEY, player_id INTEGER, transport_loss REAL DEFAULT 0, condition_pct REAL DEFAULT 100, status TEXT DEFAULT "active", damaged_at TEXT NULL)');
        $this->db->exec('CREATE TABLE industrial_disasters (id INTEGER PRIMARY KEY, player_id INTEGER, well_id INTEGER NULL, disaster_type TEXT, status TEXT, resolved_at TEXT NULL)');
        $this->db->exec('CREATE TABLE failure_log (id INTEGER PRIMARY KEY, player_id INTEGER, well_id INTEGER NULL, failure_type TEXT, resolved INTEGER DEFAULT 0, resolved_at TEXT NULL)');
    }

    private function seedPlayerAndStaff(int $skill = 6): void
    {
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 100000000)");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, first_name, last_name, spec_code, spec_name, skill_level, status) VALUES (1, 1, 'Jan', 'Test', 'maintenance_engineer', 'Inżynier Utrzymania Ruchu', {$skill}, 'active')");
    }

    private function seedUsedHub(int $hubId): void
    {
        $this->db->exec("INSERT INTO logistics_hubs (id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES ({$hubId}, 'Hub Używany', 55, 250000, 'active', datetime('now'))");
        $this->db->exec("INSERT INTO wells (id, player_id, status) VALUES (201, 1, 'active')");
        $this->db->exec("INSERT INTO logistics_hub_assignments (hub_id, well_id, status) VALUES ({$hubId}, 201, 'active')");
    }

    private function makeService(): TechnicalTeamService
    {
        $service = new class extends TechnicalTeamService {
            public function __construct() {}
            public function getManager(): ?array { return null; }
            public function getManagerBonus(?array $manager): array
            {
                return [
                    'skill' => 0,
                    'time_mult' => 1.0,
                    'cost_mult' => 1.0,
                    'label' => 'Test manager neutral',
                ];
            }
        };

        $this->setPrivateProperty($service, TechnicalTeamService::class, 'db', $this->db);
        $this->setPrivateProperty($service, TechnicalTeamService::class, 'playerId', 1);

        return $service;
    }
}
