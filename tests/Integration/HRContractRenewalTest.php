<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HRService.php';

final class HRContractRenewalTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private HRService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSourceSchema();
        EmployeeSystemBootstrap::ensure($this->db);
        $reflection = new ReflectionClass(HRService::class);
        $this->service = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('db');
        $property->setValue($this->service, $this->db);
    }

    public function testRenewalRejectsLeavingEmployee(): void
    {
        $this->seedBoardEmployee('leaving');

        $result = $this->service->renewContract(10, '1y', 1, 'leaving-contract-token');

        self::assertFalse($result['success']);
        self::assertSame('2026-12-31', $this->contractEnd());
    }

    public function testRenewalAcceptsActiveLinkedCanonicalEmployee(): void
    {
        $this->seedBoardEmployee(null);
        $this->db->exec(
            "INSERT INTO technical_staff (id, player_id, status) VALUES (20, 1, 'active');
             INSERT INTO employee_state
                (player_id, source_type, source_id, department_code, relation_status)
             VALUES (1, 'technical_staff', 20, 'hr', 'normal');
             INSERT INTO employee_source_links
                (player_id, board_member_id, technical_staff_id, link_type)
             VALUES (1, 10, 20, 'legacy_headhunter_mirror')"
        );

        $result = $this->service->renewContract(10, '1y', 1, 'linked-contract-token');

        self::assertTrue($result['success']);
        self::assertSame('2027-12-31', $this->contractEnd());
    }

    private function createSourceSchema(): void
    {
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY)');
        $this->db->exec(
            "CREATE TABLE board_members (
                id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL,
                first_name TEXT NOT NULL, last_name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active'
            )"
        );
        $this->db->exec(
            "CREATE TABLE technical_staff (
                id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'active'
            )"
        );
        $this->db->exec(
            "CREATE TABLE employee_contracts (
                id INTEGER PRIMARY KEY AUTOINCREMENT, member_id INTEGER NOT NULL,
                contract_start TEXT NOT NULL, contract_end TEXT NOT NULL,
                salary REAL NOT NULL, contract_type TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active'
            )"
        );
    }

    private function seedBoardEmployee(?string $relation): void
    {
        $this->db->exec(
            "INSERT INTO players (id) VALUES (1);
             INSERT INTO board_members (id, player_id, first_name, last_name, status)
             VALUES (10, 1, 'Anna', 'Nowak', 'active');
             INSERT INTO employee_contracts
                (member_id, contract_start, contract_end, salary, contract_type, status)
             VALUES (10, '2026-01-01', '2026-12-31', 12000, '1y', 'active')"
        );
        if ($relation !== null) {
            $stmt = $this->db->prepare(
                "INSERT INTO employee_state
                    (player_id, source_type, source_id, department_code, relation_status)
                 VALUES (1, 'board_member', 10, 'hr', ?)"
            );
            $stmt->execute([$relation]);
        }
    }

    private function contractEnd(): string
    {
        return (string)$this->db->query(
            'SELECT contract_end FROM employee_contracts WHERE member_id=10'
        )->fetchColumn();
    }
}
