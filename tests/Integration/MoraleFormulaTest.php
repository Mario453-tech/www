<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeSystemConfigService.php';
require_once dirname(__DIR__, 2) . '/src/HR/MoraleServiceV2.php';

final class MoraleFormulaTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private EmployeeSystemConfigService $config;
    private MoraleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSourceSchema();
        $this->config = new EmployeeSystemConfigService($this->db);
        $this->service = new MoraleService($this->db);
    }

    public function testExpectedSalaryUsesExactFortyPercentLowerClamp(): void
    {
        $this->config->save([
            'skill_factor_min'=>0.1,
            'skill_factor_max'=>0.1,
            'experience_factor_max'=>1.0,
            'ambition_factor_min'=>0.5,
            'ambition_factor_max'=>0.5,
            'tenure_year_pct'=>0,
            'training_pct_each'=>0,
            'responsibility_pct_max'=>0,
        ]);
        $service = new MoraleService($this->db);
        $employee = $this->employee(10000, 20000, 0, 0, 0);

        $this->assertSame(4000.0, $service->calculateExpectedSalary($employee, 0, 0));
    }

    public function testExpectedSalaryUsesExactNinetyPercentUpperClamp(): void
    {
        $employee = $this->employee(10000, 20000, 10, 20, 10);

        $this->assertSame(18000.0, $this->service->calculateExpectedSalary($employee, 5, 100));
    }

    public function testWorkloadRespectsStatusAndDefaults(): void
    {
        $technical = $this->employee(10000, 20000, 5, 5, 5);
        $technical['source_type'] = EmployeeRef::SOURCE_TECHNICAL_STAFF;
        $technical['status'] = 'active';
        $this->assertSame(20.0, $this->service->calculateWorkload($technical, 0));
        $technical['status'] = 'busy';
        $this->assertSame(100.0, $this->service->calculateWorkload($technical, 25));
        $technical['status'] = 'on_leave';
        $this->assertSame(0.0, $this->service->calculateWorkload($technical, 100));
    }

    public function testRelationshipLoyaltyModifierImprovesCalculatedRiskWithoutChangingTraits(): void
    {
        $employee = $this->employee(10000, 20000, 5, 5, 5);
        $state = [
            'morale' => 40.0,
            'relation_status' => 'unhappy',
            'low_morale_streak' => 0,
            'dispute_ticks' => 0,
            'loyalty_modifier' => 0.0,
        ];

        $withoutBonus = $this->service->calculateMetrics($employee, $state, 90.0, 0, 'normal');
        $state['loyalty_modifier'] = 5.0;
        $withBonus = $this->service->calculateMetrics($employee, $state, 90.0, 0, 'normal');

        $this->assertGreaterThan($withBonus['leave_risk'], $withoutBonus['leave_risk']);
        $this->assertGreaterThan($withBonus['strike_support'], $withoutBonus['strike_support']);
        $this->assertSame(5, $employee['traits']['loyalty']);
    }
    /** @return array<string,mixed> */
    private function employee(float $minimum, float $maximum, int $skill, int $experience, int $ambition): array
    {
        return [
            'source_type'=>EmployeeRef::SOURCE_BOARD_MEMBER,
            'salary'=>10000.0,
            'salary_range_min'=>$minimum,
            'salary_range_max'=>$maximum,
            'skills'=>['organization'=>$skill,'negotiation'=>$skill,'analysis'=>$skill,'stress'=>$skill,'ethics'=>$skill],
            'traits'=>['loyalty'=>5,'ambition'=>$ambition],
            'experience_years'=>$experience,
            'hired_at'=>'2010-01-01 00:00:00',
            'status'=>'active',
        ];
    }

    private function createSourceSchema(): void
    {
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE hr_specializations (
            id INTEGER PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL,
            base_salary_min REAL NOT NULL, base_salary_max REAL NOT NULL
        )');
        $this->db->exec('CREATE TABLE board_members (
            id INTEGER PRIMARY KEY, player_id INTEGER NULL, member_type TEXT NOT NULL,
            role_id INTEGER NOT NULL, specialization_id INTEGER NULL, first_name TEXT NOT NULL,
            last_name TEXT NOT NULL, experience_years INTEGER NOT NULL,
            skill_organization INTEGER NOT NULL, skill_negotiation INTEGER NOT NULL,
            skill_analysis INTEGER NOT NULL, skill_stress INTEGER NOT NULL, skill_ethics INTEGER NOT NULL,
            trait_loyalty INTEGER NOT NULL, trait_corruption_risk INTEGER NOT NULL,
            trait_ambition INTEGER NOT NULL, salary REAL NOT NULL, status TEXT NOT NULL, hired_at TEXT NULL
        )');
        $this->db->exec('CREATE TABLE technical_staff (
            id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL, manager_id INTEGER NOT NULL DEFAULT 0,
            first_name TEXT NOT NULL, last_name TEXT NOT NULL, spec_code TEXT NOT NULL,
            specialization TEXT NULL, spec_name TEXT NOT NULL, experience_years INTEGER NOT NULL,
            skill_level INTEGER NOT NULL, salary REAL NOT NULL, status TEXT NOT NULL, hired_at TEXT NULL
        )');
    }
}
