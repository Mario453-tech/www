<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRepository.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeStateService.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRoleEffectService.php';
require_once __DIR__ . '/SqliteIntegrationTestCase.php';

final class EmployeeRoleEffectServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private EmployeeRepository $repository;
    private EmployeeStateService $employeeState;
    private EmployeeRoleEffectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->seedEmployees();
        EmployeeSystemBootstrap::ensure($this->db);
        $this->repository = new EmployeeRepository($this->db);
        $this->employeeState = new EmployeeStateService($this->db, $this->repository);
        $this->service = new EmployeeRoleEffectService($this->db, $this->repository, $this->employeeState);
    }

    public function testBootstrapSeedsLogisticsSpecializationsAndBaseEffects(): void
    {
        $specStmt = $this->db->query("SELECT COUNT(*) FROM hr_specializations WHERE department = 'logistics'");
        $effectStmt = $this->db->query("SELECT COUNT(*) FROM employee_role_effects");

        $this->assertGreaterThanOrEqual(7, (int)$specStmt->fetchColumn());
        $this->assertGreaterThanOrEqual(7, (int)$effectStmt->fetchColumn());
    }

    public function testCalculateEffectsUsesSkillAndMoraleFactors(): void
    {
        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->employeeState->ensureState($ref);
        $this->db->exec("UPDATE employee_state SET morale = 75 WHERE player_id = 1 AND source_type = 'board_member' AND source_id = 10");

        $result = $this->service->calculateEffects($ref, 'hub');
        $effect = $result['effects']['hub_throughput_pct'] ?? null;

        $this->assertNotNull($effect);
        $this->assertSame('hub_operator', $result['specialization_code']);
        $this->assertSame(1.05, $effect['morale_factor']);
        $this->assertEqualsWithDelta(1.31, $effect['skill_factor'], 0.0001);
        $this->assertEqualsWithDelta(6.8775, $effect['final_value'], 0.0001);
    }

    public function testCalculatedEffectDescriptionFollowsSelectedLocale(): void
    {
        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);

        $_SESSION['locale'] = 'en';
        $english = $this->service->calculateEffects($ref, 'hub');
        $_SESSION['locale'] = 'pl';
        $polish = $this->service->calculateEffects($ref, 'hub');

        $this->assertSame(
            'Increases hub throughput.',
            $english['effects']['hub_throughput_pct']['description']
        );
        $this->assertSame(
            'Zwiększa przepustowość huba.',
            $polish['effects']['hub_throughput_pct']['description']
        );
    }

    public function testTechnicalEmployeeFallsBackToRoleCodeWhenSpecializationPerkIsMissing(): void
    {
        $ref = new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1);

        $result = $this->service->calculateEffects($ref, 'pipeline');

        $this->assertSame('pipeline_logistics_specialist', $result['specialization_code']);
        $this->assertArrayHasKey('pipeline_loss_pct', $result['effects']);
    }

    public function testCalculatePlayerEffectsLoadsHubAndPipelineRuntimeEffectsTogether(): void
    {
        $stateCountBefore = (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn();
        $results = $this->service->calculatePlayerEffects(1, [
            'hub_operator' => 'hub',
            'pipeline_logistics_specialist' => 'pipeline',
        ]);

        $effects = [];
        foreach ($results as $result) {
            foreach ($result['effects'] as $effectKey => $effect) {
                $effects[$effectKey] = $effect;
            }
        }

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('hub_throughput_pct', $effects);
        $this->assertArrayHasKey('pipeline_loss_pct', $effects);
        $this->assertSame($stateCountBefore + 2, (int)$this->db->query('SELECT COUNT(*) FROM employee_state')->fetchColumn());
    }

    public function testCanonicalMirrorUsesLegacyStrikeStateDuringEffectCalculation(): void
    {
        $legacyRef = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->employeeState->ensureState($legacyRef);
        $this->db->exec(
            "UPDATE employee_state
                SET morale = 10, relation_status = 'on_strike', version = 2
              WHERE player_id = 1 AND source_type = 'board_member' AND source_id = 10"
        );
        $this->seedCanonicalHubOperatorMirror(21, 'busy');

        $result = $this->service->calculateEffects($legacyRef, 'hub');

        $this->assertSame(EmployeeRef::SOURCE_TECHNICAL_STAFF, $result['employee']['source_type']);
        $this->assertSame(10.0, $result['morale']);
        $this->assertSame([], $result['effects']);
        $this->assertSame(
            'on_strike',
            $this->db->query("SELECT relation_status FROM employee_state WHERE source_type = 'technical_staff' AND source_id = 21")->fetchColumn()
        );
    }

    public function testInactiveCanonicalMirrorCannotReceiveRuntimeEffectsFromActiveLegacyRecord(): void
    {
        $this->seedCanonicalHubOperatorMirror(21, 'fired');

        $results = $this->service->calculatePlayerEffects(1, ['hub_operator' => 'hub']);

        $this->assertSame([], $results);
    }

    public function testGlobalScopeDoesNotIncludeDepartmentOnlyEffects(): void
    {
        $this->service->saveEffect([
            'specialization_code' => 'hub_operator',
            'effect_key' => 'department_only_probe_pct',
            'effect_type' => 'percent',
            'effect_value' => 4.0,
            'target_scope' => 'department',
            'skill_weights' => ['organization' => 1.0],
            'description_pl' => 'Efekt testowy.',
            'is_active' => 1,
        ]);

        $result = $this->service->calculateEffects(
            new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1),
            'global'
        );

        $this->assertArrayNotHasKey('department_only_probe_pct', $result['effects']);
    }

    public function testBoardMemberFallsBackWhenCanonicalTechnicalMirrorIsMissing(): void
    {
        $this->db->exec("INSERT INTO employee_source_links (player_id, board_member_id, technical_staff_id, link_type) VALUES (1, 10, 999, 'legacy_headhunter_mirror')");

        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $result = $this->service->calculateEffects($ref, 'hub');

        $this->assertSame(EmployeeRef::SOURCE_BOARD_MEMBER, $result['employee']['source_type']);
        $this->assertArrayHasKey('hub_throughput_pct', $result['effects']);
    }

    public function testBoardMemberIgnoresStaleCanonicalLinkThatPointsAtAnotherPerson(): void
    {
        $this->db->exec("INSERT INTO employee_source_links (player_id, board_member_id, technical_staff_id, link_type) VALUES (1, 10, 20, 'legacy_headhunter_mirror')");

        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $result = $this->service->calculateEffects($ref, 'hub');

        $this->assertSame(EmployeeRef::SOURCE_BOARD_MEMBER, $result['employee']['source_type']);
        $this->assertSame('Anna', $result['employee']['first_name']);
        $this->assertSame('hub_operator', $result['specialization_code']);
    }

    public function testSaveEffectUpdatesAndDeleteRemovesRecord(): void
    {
        $id = $this->service->saveEffect([
            'specialization_code' => 'oil_flow_analyst',
            'effect_key' => 'global_transport_visibility_pct',
            'effect_type' => 'percent',
            'effect_value' => 4.0,
            'target_scope' => 'global',
            'skill_weights' => ['analysis' => 0.7, 'organization' => 0.3],
            'description_pl' => 'Poprawia widoczność wąskich gardeł.',
            'is_active' => 1,
        ]);

        $created = $this->db->prepare('SELECT effect_value, description_pl FROM employee_role_effects WHERE id = ?');
        $created->execute([$id]);
        $row = $created->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('4', (string)$row['effect_value']);

        $sameId = $this->service->saveEffect([
            'id' => $id,
            'specialization_code' => 'oil_flow_analyst',
            'effect_key' => 'global_transport_visibility_pct',
            'effect_type' => 'percent',
            'effect_value' => 6.5,
            'target_scope' => 'global',
            'skill_weights' => ['analysis' => 0.8, 'organization' => 0.2],
            'description_pl' => 'Aktualizacja efektu.',
            'is_active' => 1,
        ]);

        $updated = $this->db->prepare('SELECT effect_value, description_pl FROM employee_role_effects WHERE id = ?');
        $updated->execute([$sameId]);
        $updatedRow = $updated->fetch(PDO::FETCH_ASSOC);

        $this->assertSame($id, $sameId);
        $this->assertNotFalse($updatedRow);
        $this->assertSame('6.5', (string)$updatedRow['effect_value']);
        $this->assertSame('Aktualizacja efektu.', (string)$updatedRow['description_pl']);

        $this->service->deleteEffect($id);

        $count = $this->db->prepare('SELECT COUNT(*) FROM employee_role_effects WHERE id = ?');
        $count->execute([$id]);
        $this->assertSame(0, (int)$count->fetchColumn());
    }

    public function testSeededEffectRetainsDescriptionKeyAndPolishFallbackText(): void
    {
        $stmt = $this->db->prepare(
            "SELECT description_key, description_pl FROM employee_role_effects
              WHERE specialization_code = 'hub_operator' AND effect_key = 'hub_throughput_pct' LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('hr.effect_desc.hub_throughput_pct', (string)$row['description_key']);
        $this->assertSame('Zwiększa przepustowość huba.', (string)$row['description_pl']);
    }

    public function testSpecificScopeWinsOverDepartmentFallbackForSameEffectKey(): void
    {
        $this->service->saveEffect([
            'specialization_code' => 'hub_operator',
            'effect_key' => 'hub_throughput_pct',
            'effect_type' => 'percent',
            'effect_value' => 2.5,
            'target_scope' => 'department',
            'skill_weights' => ['organization' => 1.0],
            'description_pl' => 'Fallback działowy.',
            'is_active' => 1,
        ]);

        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->employeeState->ensureState($ref);
        $result = $this->service->calculateEffects($ref, 'hub');
        $effect = $result['effects']['hub_throughput_pct'] ?? null;

        $this->assertNotNull($effect);
        $this->assertSame('hub', $effect['target_scope']);
        $this->assertEqualsWithDelta(6.8775, $effect['final_value'], 0.0001);
    }

    public function testSaveEffectThrowsWhenUpdatedRecordDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->saveEffect([
            'id' => 9999,
            'specialization_code' => 'oil_flow_analyst',
            'effect_key' => 'missing_effect',
            'effect_type' => 'percent',
            'effect_value' => 1.0,
            'target_scope' => 'global',
            'skill_weights' => ['analysis' => 1.0],
            'description_pl' => 'Brakujący rekord.',
            'is_active' => 1,
        ]);
    }

    public function testSaveEffectThrowsControlledErrorForDuplicateTuple(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Employee role effect already exists');

        $this->service->saveEffect([
            'specialization_code' => 'hub_operator',
            'effect_key' => 'hub_throughput_pct',
            'effect_type' => 'percent',
            'effect_value' => 9.0,
            'target_scope' => 'hub',
            'skill_weights' => ['organization' => 1.0],
            'description_pl' => 'Duplikat efektu.',
            'is_active' => 1,
        ]);
    }

    public function testLogisticsManagerBonusReturnsDepartmentEffects(): void
    {
        $this->db->exec("UPDATE board_members SET member_type = 'director' WHERE id = 10");
        $this->db->exec('UPDATE board_members SET specialization_id = NULL WHERE id = 10');
        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->employeeState->ensureState($ref);
        $this->db->exec("UPDATE employee_state SET morale = 85 WHERE player_id = 1 AND source_type = 'board_member' AND source_id = 10");

        $bonus = $this->service->getLogisticsManagerBonus(1);

        $this->assertTrue($bonus['has_manager']);
        $this->assertGreaterThan(0.0, $bonus['score']);
        $this->assertSame(1.10, $bonus['morale_factor']);
        $this->assertArrayHasKey('department_transport_cost_pct', $bonus['effects']);
    }

    public function testCalculateEffectsSkipsTechnicalEmployeeOnLeave(): void
    {
        $this->db->exec("UPDATE technical_staff SET status = 'on_leave' WHERE id = 20");

        $ref = new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, 20, 1);
        $result = $this->service->calculateEffects($ref, 'pipeline');

        $this->assertSame('pipeline_logistics_specialist', $result['specialization_code']);
        $this->assertSame([], $result['effects']);
    }

    public function testCalculateEffectsSkipsEmployeeDuringStrike(): void
    {
        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 10, 1);
        $this->employeeState->ensureState($ref);
        $this->db->exec("UPDATE employee_state
            SET relation_status = 'on_strike', morale = 90
            WHERE player_id = 1 AND source_type = 'board_member' AND source_id = 10");

        $result = $this->service->calculateEffects($ref, 'hub');

        $this->assertSame('hub_operator', $result['specialization_code']);
        $this->assertSame(90.0, $result['morale']);
        $this->assertSame([], $result['effects']);
    }

    public function testBootstrapMigratesLegacySqliteRoleEffectSchemaWithoutOverwritingExistingValues(): void
    {
        $db = $this->createSqlitePdo();
        $this->createSchemaForBootstrapMigration($db);
        $db->exec("INSERT INTO hr_specializations
            (id, code, name, department, rarity, base_salary_min, base_salary_max, description)
            VALUES
            (50, 'hub_operator', 'Custom Hub Operator', 'logistics', 'rare', 11111, 22222, 'Custom description')");
        $db->exec("INSERT INTO employee_role_effects
            (id, specialization_code, effect_key, effect_type, effect_value, target_scope, skill_weights_json, is_active, created_at, updated_at)
            VALUES
            (1, 'hub_operator', 'hub_throughput_pct', 'flat', 1.5, 'hub', '{\"organization\":1}', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        EmployeeSystemBootstrap::ensure($db);

        $columns = $db->query("PRAGMA table_info(employee_role_effects)")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');
        $this->assertContains('description_key', $columnNames);
        $this->assertContains('description_pl', $columnNames);

        $row = $db->query("SELECT effect_type, effect_value, is_active, description_key, description_pl
            FROM employee_role_effects
            WHERE specialization_code = 'hub_operator' AND effect_key = 'hub_throughput_pct' AND target_scope = 'hub'
            LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $spec = $db->query("SELECT name, rarity, base_salary_min, base_salary_max, description
            FROM hr_specializations WHERE code = 'hub_operator' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('flat', (string)$row['effect_type']);
        $this->assertSame('1.5', (string)$row['effect_value']);
        $this->assertSame('0', (string)$row['is_active']);
        $this->assertSame('hr.effect_desc.hub_throughput_pct', (string)$row['description_key']);
        $this->assertNotFalse($spec);
        $this->assertSame('Custom Hub Operator', (string)$spec['name']);
        $this->assertSame('rare', (string)$spec['rarity']);
        $this->assertSame('11111', (string)$spec['base_salary_min']);
        $this->assertSame('22222', (string)$spec['base_salary_max']);
        $this->assertSame('Custom description', (string)$spec['description']);
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE hr_specializations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            name TEXT NOT NULL,
            department TEXT NOT NULL,
            rarity TEXT NOT NULL DEFAULT "common",
            base_salary_min REAL NOT NULL,
            base_salary_max REAL NOT NULL,
            min_age INTEGER NOT NULL DEFAULT 25,
            max_age INTEGER NOT NULL DEFAULT 58,
            description TEXT NULL
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
        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'logistics'), (2, 'technical')");
        $this->db->exec("INSERT INTO hr_specializations (id, code, name, department, rarity, base_salary_min, base_salary_max, description)
            VALUES
            (1, 'hub_operator', 'Operator huba', 'logistics', 'common', 8200, 11500, 'Obsługa huba'),
            (2, 'pipeline_logistics_specialist', 'Specjalista logistyki rurociągów', 'logistics', 'uncommon', 8800, 12800, 'Rurociągi'),
            (3, 'oil_flow_analyst', 'Analityk przepływu ropy', 'logistics', 'rare', 9800, 14500, 'Analiza'),
            (4, 'maintenance_engineer', 'Inżynier utrzymania ruchu', 'technical', 'common', 9000, 13000, 'Technika')");
        $this->db->exec("INSERT INTO board_members
            (id, player_id, member_type, role_id, specialization_id, first_name, last_name,
             experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress,
             skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary, status, hired_at)
            VALUES
            (10, 1, 'staff', 1, 1, 'Anna', 'Nowak', 10, 8, 6, 9, 7, 8, 9, 2, 7, 9000, 'active', '2026-01-01 10:00:00')");
        $this->db->exec("INSERT INTO technical_staff
            (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name,
             experience_years, skill_level, salary, status, hired_at)
            VALUES
            (20, 1, 10, 'Jan', 'Kowalski', 'pipeline_logistics_specialist', NULL, 'Specjalista logistyki rurociągów', 6, 7, 9700, 'busy', '2026-01-03 10:00:00')");
    }

    private function seedCanonicalHubOperatorMirror(int $technicalId, string $status): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO technical_staff
                (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name,
                 experience_years, skill_level, salary, status, hired_at)
             VALUES (?, 1, 10, \'Anna\', \'Nowak\', \'hub_operator\', NULL, \'Operator huba\', 10, 8, 9000, ?, \'2026-01-01 10:00:00\')'
        );
        $stmt->execute([$technicalId, $status]);
        $link = $this->db->prepare(
            "INSERT INTO employee_source_links
                (player_id, board_member_id, technical_staff_id, link_type)
             VALUES (1, 10, ?, 'legacy_headhunter_mirror')"
        );
        $link->execute([$technicalId]);
    }

    private function createSchemaForBootstrapMigration(PDO $db): void
    {
        $db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $db->exec('CREATE TABLE hr_specializations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            name TEXT NOT NULL,
            department TEXT NOT NULL,
            rarity TEXT NOT NULL DEFAULT "common",
            base_salary_min REAL NOT NULL,
            base_salary_max REAL NOT NULL,
            min_age INTEGER NOT NULL DEFAULT 25,
            max_age INTEGER NOT NULL DEFAULT 58,
            description TEXT NULL
        )');
        $db->exec("CREATE TABLE board_members (
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
        $db->exec("CREATE TABLE technical_staff (
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
        $db->exec("CREATE TABLE employee_role_effects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            specialization_code TEXT NOT NULL,
            effect_key TEXT NOT NULL,
            effect_type TEXT NOT NULL DEFAULT 'percent',
            effect_value REAL NOT NULL DEFAULT 0.0,
            target_scope TEXT NOT NULL DEFAULT 'department',
            skill_weights_json TEXT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_role_effect ON employee_role_effects (specialization_code, effect_key, target_scope)');
    }
}
