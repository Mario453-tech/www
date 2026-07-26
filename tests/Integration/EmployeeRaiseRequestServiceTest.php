<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeRaiseRequestService.php';

final class EmployeeRaiseRequestServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSourceSchema();
        EmployeeSystemBootstrap::ensure($this->db);
        $this->seedEmployees();
    }

    public function testListIsPlayerScopedAndContainsEmployeeData(): void
    {
        $this->seedRequest(1, 1, 'technical_staff', 20, 20.0);
        $this->seedRequest(2, 2, 'technical_staff', 21, 30.0);

        $rows = $this->service()->listForPlayer(1);

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int)$rows[0]['id']);
        $this->assertSame(10000.0, $rows[0]['current_salary']);
        $this->assertSame(12000.0, $rows[0]['requested_salary']);
        $this->assertSame('Jan', $rows[0]['employee']['first_name']);
    }

    public function testFullAcceptanceUpdatesSalaryStateAndIsIdempotent(): void
    {
        $this->seedRequest(1, 1, 'technical_staff', 20, 20.0);
        (new EmployeeSystemConfigService($this->db))->save(['raise_accept_morale_gain' => 12]);
        $this->db->exec("UPDATE employee_state SET leave_risk=40 WHERE player_id=1 AND source_type='technical_staff' AND source_id=20");
        $this->db->exec("UPDATE technical_staff SET trait_loyalty=10 WHERE id=20 AND player_id=1");
        $service = $this->service();

        $first = $service->acceptFull(1, 1, 'accept-full-token');
        $second = $service->acceptFull(1, 1, 'accept-full-token');

        $this->assertSame('accepted', $first['result']);
        $this->assertSame(12000.0, $first['salary']);
        $this->assertSame(62.0, $first['morale']);
        $this->assertFalse($first['idempotent']);
        $this->assertTrue($second['idempotent']);
        $this->assertSame(12000.0, $this->salary('technical_staff', 20));
        $state = $this->state(1, 'technical_staff', 20);
        $this->assertSame('normal', $state['relation_status']);
        $this->assertSame(25.0, (float)$state['leave_risk']);
        $this->assertSame(10.0, $this->loyalty('technical_staff', 20));
        $this->assertSame(1, $this->countRows('employee_events'));
    }

    public function testSuccessfulNegotiationUsesBoardEmployeeAndSafePublicResult(): void
    {
        $this->seedRequest(1, 1, 'board_member', 10, 20.0);
        $service = $this->service(static fn(): float => 1.0);

        $result = $service->negotiate(1, 1, 11500.0, 'negotiation-success-token');

        $this->assertSame('negotiated', $result['result']);
        $this->assertSame(11500.0, $this->salary('board_member', 10));
        $this->assertSame(11500.0, (float)$this->db->query('SELECT negotiated_salary FROM employee_raise_requests WHERE id=1')->fetchColumn());
        $this->assertSame(58.0, $result['morale']);
        $this->assertArrayHasKey('chance', $result);
        $this->assertArrayNotHasKey('roll', $result);
        $this->assertArrayNotHasKey('formula', $result);
        $meta = $this->eventMeta();
        $this->assertArrayHasKey('formula', $meta);
        $this->assertSame(1.0, (float)$meta['formula']['random_roll']);
        $this->assertSame(1.0, (float)$meta['formula']['salary_negotiator_active']);
    }

    public function testFailedNegotiationKeepsRequestOpenAndAppliesMoralePenaltyOnce(): void
    {
        $this->seedRequest(1, 1, 'technical_staff', 20, 20.0);
        $service = $this->service(static fn(): float => 100.0);

        $first = $service->negotiate(1, 1, 10001.0, 'negotiation-failure-token');
        $second = $service->negotiate(1, 1, 10001.0, 'negotiation-failure-token');

        $this->assertSame('rejected_offer', $first['result']);
        $this->assertSame('open', $first['status']);
        $this->assertTrue($second['idempotent']);
        $this->assertSame(45.0, (float)$this->state(1, 'technical_staff', 20)['morale']);
        $this->assertSame(10000.0, $this->salary('technical_staff', 20));
        $this->assertSame('open', $this->requestStatus(1));
    }

    public function testRejectCreatesDisputeAndCannotTargetAnotherPlayer(): void
    {
        $this->seedRequest(1, 1, 'technical_staff', 20, 20.0);
        $service = $this->service();

        try {
            $service->reject(2, 1, 'foreign-player-token');
            $this->fail('Another player request must not be accessible.');
        } catch (RuntimeException) {
            $this->assertSame('open', $this->requestStatus(1));
        }

        $result = $service->reject(1, 1, 'owner-rejection-token');
        $state = $this->state(1, 'technical_staff', 20);
        $this->assertSame('rejected', $result['status']);
        $this->assertSame(30.0, (float)$state['morale']);
        $this->assertSame(35.0, (float)$state['strike_support']);
        $this->assertSame('dispute', $state['relation_status']);
    }

    public function testPostponeUsesConfiguredDeadlineAndEnforcesFallbackLimit(): void
    {
        $this->seedRequest(1, 1, 'technical_staff', 20, 20.0);
        (new EmployeeSystemConfigService($this->db))->save(['raise_max_postponements' => 2]);
        $service = $this->service();

        $first = $service->postpone(1, 1, 'postpone-token-one');
        $second = $service->postpone(1, 1, 'postpone-token-two');

        $this->assertSame('postponed', $first['status']);
        $this->assertNotSame('', $first['deadline_at']);
        $this->assertSame('postponed', $second['status']);
        $this->assertSame(2, (int)$this->db->query('SELECT postponed_count FROM employee_raise_requests WHERE id=1')->fetchColumn());
        $this->assertSame(40.0, (float)$this->state(1, 'technical_staff', 20)['morale']);

        $this->expectException(RuntimeException::class);
        $service->postpone(1, 1, 'postpone-token-three');
    }

    public function testInactiveEmployeeRollsBackRequestAndEvent(): void
    {
        $this->seedRequest(1, 1, 'technical_staff', 20, 20.0);
        $this->db->exec("UPDATE technical_staff SET status='fired' WHERE id=20");

        try {
            $this->service()->acceptFull(1, 1, 'inactive-staff-token');
            $this->fail('Inactive employee must not receive a raise.');
        } catch (RuntimeException) {
            $this->assertSame('open', $this->requestStatus(1));
            $this->assertSame(10000.0, $this->salary('technical_staff', 20));
            $this->assertSame(0, $this->countRows('employee_events'));
        }
    }

    public function testTokenCannotBeReusedForAnotherAction(): void
    {
        $this->seedRequest(1, 1, 'technical_staff', 20, 20.0);
        $service = $this->service();
        $service->postpone(1, 1, 'same-operation-token');

        $this->expectException(InvalidArgumentException::class);
        $service->reject(1, 1, 'same-operation-token');
    }
    public function testTokenValidationAndHistoricalResult(): void
    {
        $this->seedRequest(1, 1, 'technical_staff', 20, 20.0);
        $service = $this->service();

        try {
            $service->acceptFull(1, 1, 'short');
            $this->fail('Short token must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame('open', $this->requestStatus(1));
        }

        $service->acceptFull(1, 1, 'history-result-token');
        $history = $service->resultByToken(1, 1, 'history-result-token');
        $this->assertIsArray($history);
        $this->assertSame('accepted', $history['result']);
        $this->assertTrue($history['idempotent']);
        $this->assertNull($service->resultByToken(2, 1, 'history-result-token'));
    }

    private function service(?callable $randomRoll = null): EmployeeRaiseRequestService
    {
        return new EmployeeRaiseRequestService($this->db, $randomRoll);
    }

    private function seedRequest(
        int $id,
        int $playerId,
        string $sourceType,
        int $sourceId,
        float $raisePct
    ): void {
        $this->db->prepare(
            "INSERT INTO employee_raise_requests
                (id, player_id, source_type, source_id, request_no, requested_raise_pct, status, deadline_at)
             VALUES (?, ?, ?, ?, 1, ?, 'open', '2099-01-01 00:00:00')"
        )->execute([$id, $playerId, $sourceType, $sourceId, $raisePct]);
    }

    /** @return array<string,mixed> */
    private function state(int $playerId, string $sourceType, int $sourceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM employee_state WHERE player_id=? AND source_type=? AND source_id=?'
        );
        $stmt->execute([$playerId, $sourceType, $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        return $row;
    }

    private function requestStatus(int $requestId): string
    {
        $stmt = $this->db->prepare('SELECT status FROM employee_raise_requests WHERE id=?');
        $stmt->execute([$requestId]);
        return (string)$stmt->fetchColumn();
    }

    private function salary(string $sourceType, int $sourceId): float
    {
        $table = $sourceType === 'technical_staff' ? 'technical_staff' : 'board_members';
        $stmt = $this->db->prepare("SELECT salary FROM {$table} WHERE id=?");
        $stmt->execute([$sourceId]);
        return (float)$stmt->fetchColumn();
    }

    private function loyalty(string $sourceType, int $sourceId): float
    {
        $table = $sourceType === 'technical_staff' ? 'technical_staff' : 'board_members';
        $stmt = $this->db->prepare("SELECT trait_loyalty FROM {$table} WHERE id=?");
        $stmt->execute([$sourceId]);
        return (float)$stmt->fetchColumn();
    }
    /** @return array<string,mixed> */
    private function eventMeta(): array
    {
        $json = $this->db->query('SELECT meta_json FROM employee_events ORDER BY id DESC LIMIT 1')->fetchColumn();
        $meta = json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($meta);
        return $meta;
    }

    private function countRows(string $table): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function createSourceSchema(): void
    {
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE hr_specializations (
            id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NULL,
            base_salary_min REAL NULL, base_salary_max REAL NULL
        )');
        $this->db->exec("CREATE TABLE board_members (
            id INTEGER PRIMARY KEY, player_id INTEGER NULL, member_type TEXT NOT NULL DEFAULT 'staff',
            role_id INTEGER NULL, specialization_id INTEGER NULL,
            first_name TEXT NOT NULL DEFAULT 'Test', last_name TEXT NOT NULL DEFAULT 'Employee',
            experience_years INTEGER NOT NULL DEFAULT 0,
            skill_organization INTEGER NOT NULL DEFAULT 5, skill_negotiation INTEGER NOT NULL DEFAULT 5,
            skill_analysis INTEGER NOT NULL DEFAULT 5, skill_stress INTEGER NOT NULL DEFAULT 5,
            skill_ethics INTEGER NOT NULL DEFAULT 5, trait_loyalty INTEGER NOT NULL DEFAULT 5,
            trait_corruption_risk INTEGER NOT NULL DEFAULT 5, trait_ambition INTEGER NOT NULL DEFAULT 5,
            salary REAL NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'active',
            hired_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->db->exec("CREATE TABLE technical_staff (
            id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL, manager_id INTEGER NULL,
            first_name TEXT NOT NULL DEFAULT 'Test', last_name TEXT NOT NULL DEFAULT 'Employee',
            spec_code TEXT NULL, specialization TEXT NULL, spec_name TEXT NULL,
            experience_years INTEGER NOT NULL DEFAULT 0, skill_level INTEGER NOT NULL DEFAULT 5,
            trait_loyalty INTEGER NOT NULL DEFAULT 5, trait_corruption_risk INTEGER NOT NULL DEFAULT 5,
            trait_ambition INTEGER NOT NULL DEFAULT 5, salary REAL NOT NULL,
            status TEXT NOT NULL DEFAULT 'active', hired_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }

    private function seedEmployees(): void
    {
        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'technical'), (2, 'hr')");
        $this->db->exec("INSERT INTO hr_specializations
            (id, code, name, base_salary_min, base_salary_max)
            VALUES (1, 'engineer', 'Engineer', 8000, 14000),
                   (2, 'salary_negotiator', 'Salary Negotiator', 10000, 16500)");
        $this->db->exec("INSERT INTO board_members
            (id, player_id, role_id, specialization_id, first_name, last_name,
             skill_organization, skill_negotiation, trait_loyalty, trait_ambition, salary, status)
            VALUES
            (10, 1, 1, 1, 'Anna', 'Nowak', 7, 7, 8, 5, 10000, 'active'),
            (11, 1, 2, 2, 'Ewa', 'HR', 9, 9, 8, 5, 13000, 'active')");
        $this->db->exec("INSERT INTO technical_staff
            (id, player_id, manager_id, first_name, last_name, spec_code, spec_name,
             skill_level, trait_loyalty, trait_ambition, salary, status)
            VALUES
            (20, 1, 10, 'Jan', 'Kowalski', 'engineer', 'Engineer', 7, 8, 5, 10000, 'busy'),
            (21, 2, 10, 'Piotr', 'Obcy', 'engineer', 'Engineer', 6, 5, 6, 9000, 'active')");
        $this->db->exec("INSERT INTO employee_state
            (player_id, source_type, source_id, department_code, morale, salary_satisfaction,
             expected_salary, strike_support, workload, relation_status)
            VALUES
            (1, 'board_member', 10, 'technical', 50, 70, 12000, 20, 70, 'raise_requested'),
            (1, 'board_member', 11, 'hr', 80, 100, 13000, 0, 50, 'normal'),
            (1, 'technical_staff', 20, 'technical', 50, 70, 12000, 20, 70, 'raise_requested'),
            (2, 'technical_staff', 21, 'technical', 50, 70, 12000, 20, 70, 'raise_requested')");
    }
}
