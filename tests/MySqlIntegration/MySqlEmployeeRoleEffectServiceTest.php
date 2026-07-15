<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRepository.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeStateService.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRoleEffectService.php';

final class MySqlEmployeeRoleEffectServiceTest extends MySqlIntegrationTestCase
{
    protected function tearDown(): void
    {
        if (isset($this->db, $this->seed)) {
            $this->db->prepare('DELETE FROM employee_state WHERE player_id = ?')->execute([$this->seed]);
            $this->db->prepare('DELETE FROM technical_staff WHERE player_id = ?')->execute([$this->seed]);
            $this->db->prepare('DELETE FROM board_members WHERE player_id = ?')->execute([$this->seed]);
        }
        parent::tearDown();
    }

    public function testMySqlBootstrapAndLogisticsManagerBonus(): void
    {
        EmployeeSystemBootstrap::ensure($this->db);

        $specStmt = $this->db->prepare("SELECT COUNT(*) FROM hr_specializations WHERE code IN ('hub_operator','pipeline_logistics_specialist','oil_flow_analyst')");
        $specStmt->execute();
        $this->assertGreaterThanOrEqual(3, (int)$specStmt->fetchColumn());

        $effectStmt = $this->db->prepare("SELECT COUNT(*) FROM employee_role_effects WHERE specialization_code = 'hub_operator'");
        $effectStmt->execute();
        $this->assertGreaterThanOrEqual(1, (int)$effectStmt->fetchColumn());

        $columnStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'employee_role_effects'
                AND COLUMN_NAME IN ('description_key', 'description_pl')"
        );
        $columnStmt->execute();
        $this->assertSame(2, (int)$columnStmt->fetchColumn());

        $descStmt = $this->db->prepare(
            "SELECT description_key, description_pl FROM employee_role_effects
              WHERE specialization_code = 'hub_operator' AND effect_key = 'hub_throughput_pct' LIMIT 1"
        );
        $descStmt->execute();
        $descRow = $descStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($descRow);
        $this->assertSame('hr.effect_desc.hub_throughput_pct', (string)$descRow['description_key']);

        $playerId = $this->seedPlayer();
        $roleId = $this->ensureLogisticsRole();
        $stmt = $this->db->prepare(
            'INSERT INTO board_members
                (id, player_id, member_type, role_id, specialization_id, status, first_name, last_name, birth_date, nationality,
                 experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress, skill_ethics,
                 trait_loyalty, trait_corruption_risk, trait_ambition, salary, hired_at)
             VALUES (?, ?, \'director\', ?, ?, \'active\', \'Ewa\', \'Logistics\', \'1985-01-01\', \'PL\',
                 12, 8, 7, 9, 6, 8, 9, 2, 6, 12000.00, NOW())'
        );
        $boardMemberId = $this->seed + 40;
        $stmt->execute([$boardMemberId, $playerId, $roleId, null]);

        $repository = new EmployeeRepository($this->db);
        $stateService = new EmployeeStateService($this->db, $repository);
        $roleEffectService = new EmployeeRoleEffectService($this->db, $repository, $stateService);
        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, $boardMemberId, $playerId);

        $stateService->ensureState($ref);
        $this->db->prepare(
            "UPDATE employee_state SET morale = 82 WHERE player_id = ? AND source_type = 'board_member' AND source_id = ?"
        )->execute([$playerId, $boardMemberId]);

        $bonus = $roleEffectService->getLogisticsManagerBonus($playerId);

        $this->assertTrue($bonus['has_manager']);
        $this->assertSame(1.10, $bonus['morale_factor']);
        $this->assertArrayHasKey('department_transport_cost_pct', $bonus['effects']);
    }

    public function testMySqlBatchCalculationCreatesMissingEmployeeState(): void
    {
        EmployeeSystemBootstrap::ensure($this->db);
        $playerId = $this->seedPlayer();
        $roleId = $this->ensureLogisticsRole();
        $specializationId = $this->findSpecializationId('hub_operator');
        $boardMemberId = $this->seed + 42;
        $stmt = $this->db->prepare(
            'INSERT INTO board_members
                (id, player_id, member_type, role_id, specialization_id, status, first_name, last_name, birth_date, nationality,
                 experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress, skill_ethics,
                 trait_loyalty, trait_corruption_risk, trait_ambition, salary, hired_at)
             VALUES (?, ?, \'staff\', ?, ?, \'active\', \'Anna\', \'Hub\', \'1990-01-01\', \'PL\',
                 8, 8, 5, 6, 7, 8, 9, 2, 6, 10000.00, NOW())'
        );
        $stmt->execute([$boardMemberId, $playerId, $roleId, $specializationId]);

        $repository = new EmployeeRepository($this->db);
        $stateService = new EmployeeStateService($this->db, $repository);
        $service = new EmployeeRoleEffectService($this->db, $repository, $stateService);
        $results = $service->calculatePlayerEffects($playerId, ['hub_operator' => 'hub']);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('hub_throughput_pct', $results[0]['effects']);
        $stateStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM employee_state
              WHERE player_id = ? AND source_type = 'board_member' AND source_id = ?"
        );
        $stateStmt->execute([$playerId, $boardMemberId]);
        $this->assertSame(1, (int)$stateStmt->fetchColumn());
    }

    private function ensureLogisticsRole(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM board_roles WHERE code = 'logistics' LIMIT 1");
        $stmt->execute();
        $roleId = $stmt->fetchColumn();
        if ($roleId !== false) {
            return (int)$roleId;
        }

        $roleId = $this->seed + 41;
        $this->db->prepare(
            "INSERT INTO board_roles (id, code, name, created_at) VALUES (?, 'logistics', 'Logistics', NOW())"
        )->execute([$roleId]);

        return $roleId;
    }

    private function findSpecializationId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM hr_specializations WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Expected seeded specialization was not found.');
        }

        return (int)$id;
    }
}
