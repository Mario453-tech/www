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
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES (99, 2, 'Hub Obcy', 70, 100000, 'active', datetime('now'))");
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
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES (10, 1, 'Hub A', 40, 500000, 'active', datetime('now'))");
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
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES (11, 1, 'Hub B', 15, 750000, 'damaged', datetime('now'))");
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

    public function testCompleteTaskRejectsTaskOwnedByAnotherPlayer(): void
    {
        $this->seedPlayerAndStaff(8);
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES (12, 2, 'Foreign Hub', 20, 750000, 'damaged', datetime('now'))");
        $this->db->exec("INSERT INTO technical_tasks (id, player_id, staff_id, task_type, hub_id, title, status, start_time, end_time, duration_hours, cost) VALUES (4, 2, 1, 'hub_repair', 12, 'Foreign repair', 'in_progress', datetime('now'), datetime('now'), 1, 0)");

        $task = [
            'id' => 4,
            'staff_id' => 1,
            'hub_id' => 12,
            'well_id' => null,
            'player_id' => 2,
            'task_type' => 'hub_repair',
            'title' => 'Foreign repair',
            'skill_level' => 8,
        ];

        $service = $this->makeService();
        $service->completeTask($task);

        $this->assertSame(
            'in_progress',
            $this->db->query("SELECT status FROM technical_tasks WHERE id = 4")->fetchColumn()
        );
        $this->assertSame(
            20.0,
            (float)$this->db->query("SELECT condition_pct FROM logistics_hubs WHERE id = 12")->fetchColumn()
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, cash REAL NOT NULL DEFAULT 0, bank_balance REAL NOT NULL DEFAULT 0, safety_procedures_level INTEGER DEFAULT 0, procedure_integrity REAL DEFAULT 100, procedures_last_decay_at TEXT NULL)');
        $this->db->exec('CREATE TABLE technical_staff (id INTEGER PRIMARY KEY, player_id INTEGER, first_name TEXT, last_name TEXT, spec_code TEXT, spec_name TEXT, skill_level INTEGER, salary REAL DEFAULT 0, status TEXT, fired_at TEXT NULL)');
        $this->db->exec('CREATE TABLE technical_tasks (id INTEGER PRIMARY KEY, player_id INTEGER, staff_id INTEGER, task_type TEXT, well_id INTEGER NULL, hub_id INTEGER NULL, pipeline_id INTEGER NULL, title TEXT, module_type TEXT NULL, start_time TEXT NULL, end_time TEXT NULL, duration_hours INTEGER DEFAULT 0, cost REAL DEFAULT 0, status TEXT, result_data TEXT NULL, notified INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE technical_task_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, staff_id INTEGER, task_type TEXT, well_id INTEGER NULL, hub_id INTEGER NULL, pipeline_id INTEGER NULL, module_type TEXT NULL, priority INTEGER DEFAULT 0, queued_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE technical_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER, well_id INTEGER NULL, type TEXT, message TEXT, is_read INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, player_id INTEGER, status TEXT)');
        $this->db->exec('CREATE TABLE logistics_hubs (id INTEGER PRIMARY KEY, player_id INTEGER DEFAULT 1, tenant_player_id INTEGER DEFAULT 0, name TEXT, condition_pct REAL, repair_cost_estimate REAL, status TEXT, last_maintenance_at TEXT NULL, updated_at TEXT NULL)');
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
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, name, condition_pct, repair_cost_estimate, status, updated_at) VALUES ({$hubId}, 1, 'Hub Używany', 55, 250000, 'active', datetime('now'))");
        $this->db->exec("INSERT INTO wells (id, player_id, status) VALUES (201, 1, 'active')");
        $this->db->exec("INSERT INTO logistics_hub_assignments (hub_id, well_id, status) VALUES ({$hubId}, 201, 'active')");
    }

    /**
     * Regresja regula #14: gdy zegar bazy (NOW()) wyprzedza zegar PHP, zlecone zadanie
     * NIE moze konczyc sie natychmiast. start_time/end_time zapisywane sa zegarem bazy,
     * wiec end_time zawsze > NOW() o czas trwania — zadanie zostaje "w toku".
     * Rule #14 regression: when the DB clock (NOW()) runs ahead of the PHP clock, a newly
     * assigned task must NOT complete instantly. start_time/end_time are written on the DB
     * clock, so end_time is always > NOW() by the duration — the task stays in-progress.
     */
    public function testAssignedTaskSurvivesTickWhenDbClockIsAheadOfPhp(): void
    {
        // Symuluj MySQL wyprzedzajacy PHP o 10 h — odwzorowuje roznice stref PHP vs MySQL w produkcji.
        // Simulate MySQL running 10 h ahead of PHP — mirrors a PHP/MySQL timezone skew in production.
        $this->db->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s', time() + 36000), 0);

        $this->seedPlayerAndStaff();
        $this->seedUsedHub(10);
        $service = $this->makeService();

        $cashBefore = (float)$this->db->query("SELECT cash FROM players WHERE id = 1")->fetchColumn();

        $result = $service->assignTask(1, 'hub_maintenance', null, null, 10);
        $this->assertTrue($result['success'], $result['message']);

        // Zadanie istnieje i jest "w toku", end_time > NOW() (zegar bazy).
        $task = $this->db->query("SELECT status, end_time FROM technical_tasks WHERE player_id = 1 ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame('in_progress', $task['status']);
        $nowDb = $this->db->query("SELECT NOW()")->fetchColumn();
        $this->assertGreaterThan($nowDb, $task['end_time'], 'end_time must be in the future on the DB clock');

        // Tick uruchamiany przy odsloneiu strony NIE moze zamknac swiezego zadania.
        // The tick that runs on page load must NOT close the fresh task.
        $service->processTick();

        $stillActive = (int)$this->db->query(
            "SELECT COUNT(*) FROM technical_tasks WHERE player_id = 1 AND status = 'in_progress'"
        )->fetchColumn();
        $this->assertSame(1, $stillActive, 'task must remain in-progress after the page-load tick');

        // Pieniadze pobrane (zadanie realne), ale nie znika — dokladnie odwrotnie niz w zgloszonym bugu.
        // Money was charged (real task) yet it does not vanish — the opposite of the reported bug.
        $cashAfter = (float)$this->db->query("SELECT cash FROM players WHERE id = 1")->fetchColumn();
        $this->assertLessThan($cashBefore, $cashAfter, 'task cost should have been charged');
    }

    /**
     * Atomowosc pieniadze<->zadanie: gdy INSERT zadania zawiedzie, oplata musi wrocic.
     * To dokladnie odwrotnosc pierwotnego bugu ("pobiera pieniadze, nie tworzy zadania").
     * Money<->task atomicity: if the task INSERT fails, the fee must be refunded.
     * This is the exact inverse of the original bug ("takes money, no task created").
     */
    public function testTaskFeeIsRefundedWhenTaskInsertFails(): void
    {
        $this->seedPlayerAndStaff();
        $this->seedUsedHub(10);
        // Wymus blad INSERT-a zadania PO pobraniu oplaty przez FTS.
        // Force the task INSERT to fail AFTER the FTS fee debit.
        $this->db->exec("CREATE TRIGGER fail_task_insert BEFORE INSERT ON technical_tasks BEGIN SELECT RAISE(ABORT, 'forced'); END");
        $service = $this->makeService();

        $cashBefore = (float)$this->db->query("SELECT cash FROM players WHERE id = 1")->fetchColumn();

        $result = $service->assignTask(1, 'hub_maintenance', null, null, 10);

        $this->assertFalse($result['success']);
        $cashAfter = (float)$this->db->query("SELECT cash FROM players WHERE id = 1")->fetchColumn();
        $this->assertSame($cashBefore, $cashAfter, 'fee must be refunded when the task cannot be created');
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM technical_tasks")->fetchColumn());
        // Zaden osierocony wpis audytu / no orphaned audit row.
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions")->fetchColumn());
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
