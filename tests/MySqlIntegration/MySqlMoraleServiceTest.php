<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/init.php';
require_once __DIR__ . '/../../src/HR/EmployeeStrikeService.php';
require_once __DIR__ . '/../../src/HR/EmployeeBonusService.php';

class MySqlMoraleServiceTest extends MySqlIntegrationTestCase
{
    private int $staffId;
    private int $playerId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->playerId = $this->seedPlayer();
        $this->staffId = $this->seedTechnicalWorker($this->playerId, 6);
        (new EmployeeStateService($this->db))->ensureState($this->employeeRef());
        $this->setCanonicalMorale(50.0);
    }

    public function testModifyMoraleIncreasesMoraleAndLogs()
    {
        MoraleService::modifyMorale($this->staffId, 10, 'bonus.granted');

        $this->assertSame(60.0, $this->canonicalMorale());

        $logs = MoraleService::getMoraleHistory($this->staffId);
        $this->assertCount(1, $logs);
        $this->assertEquals(10, $logs[0]['change_amount']);
        $this->assertEquals('bonus.granted', $logs[0]['reason']);
    }

    public function testModifyMoraleCappedAt100()
    {
        $this->setCanonicalMorale(95.0);

        MoraleService::modifyMorale($this->staffId, 20, 'big.bonus');

        $this->assertSame(100.0, $this->canonicalMorale());

        $logs = MoraleService::getMoraleHistory($this->staffId);
        $this->assertCount(1, $logs);
        $this->assertEquals(5, $logs[0]['change_amount']);
    }

    public function testModifyMoraleCappedAt0()
    {
        $this->setCanonicalMorale(10.0);

        MoraleService::modifyMorale($this->staffId, -15, 'crisis');

        $this->assertSame(0.0, $this->canonicalMorale());

        $logs = MoraleService::getMoraleHistory($this->staffId);
        $this->assertCount(1, $logs);
        $this->assertEquals(-10, $logs[0]['change_amount']);
    }

    public function testTechnicalBonusUsesFinancialTransactionAndCanonicalMorale(): void
    {
        $before = $this->walletTotal();

        $result = (new EmployeeBonusService($this->db))->grantTechnicalBonus(
            $this->playerId,
            $this->staffId,
            'mysql-bonus-token-' . $this->playerId,
            100.0,
            5.0
        );

        $this->assertTrue($result['success']);
        $this->assertSame(55.0, $this->canonicalMorale());
        $this->assertSame($before - 100.0, $this->walletTotal());
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM bank_transactions
              WHERE from_player_id=? AND transaction_type='hr_bonus'
                AND reference_type='technical_staff' AND reference_id=?"
        );
        $stmt->execute([$this->playerId, $this->staffId]);
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }
    public function testEmployeeStrikeEscalatesAndResolvesCanonicalConflict(): void
    {
        $config = new EmployeeSystemConfigService($this->db);
        $keys = ['feature_threats', 'feature_strikes', 'threat_min_disputes', 'threat_cycles_required'];
        $original = array_intersect_key($config->all(), array_fill_keys($keys, true));
        $config->save([
            'feature_threats' => true,
            'feature_strikes' => true,
            'threat_min_disputes' => 1,
            'threat_cycles_required' => 2,
        ]);

        try {
            $stmt = $this->db->prepare(
                "UPDATE employee_state
                    SET morale=30, salary_satisfaction=60, strike_support=70,
                        workload=80, relation_status='dispute'
                  WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
            );
            $stmt->execute([$this->playerId, $this->staffId]);
            $this->assertSame(1, $stmt->rowCount());
            $service = new EmployeeStrikeService($this->db);

            $first = $service->processEscalations(new DateTimeImmutable('2026-07-22 10:00:00'));
            $this->assertSame(1, $first['threats_started']);
            $this->assertSame('threat', $service->activeForPlayer($this->playerId)[0]['status']);

            $second = $service->processEscalations(new DateTimeImmutable('2026-07-22 10:05:00'));
            $strikes = $service->activeForPlayer($this->playerId);
            $this->assertSame(1, $second['strikes_started']);
            $this->assertCount(1, $strikes);
            $this->assertSame('active', $strikes[0]['status']);
            $this->assertCount(1, $service->members($this->playerId, (int)$strikes[0]['id']));
            $this->assertSame('on_strike', $this->canonicalRelationStatus());

            $service->closeByAgreement($this->playerId, (int)$strikes[0]['id'], 10.0);

            $this->assertSame([], $service->activeForPlayer($this->playerId));
            $this->assertSame('normal', $this->canonicalRelationStatus());
            $this->assertSame(40.0, $this->canonicalMorale());
        } finally {
            $config->save($original);
        }
    }

    public function testAdminCanForceActiveTestStrike(): void
    {
        $this->db->prepare(
            "UPDATE employee_state
                SET morale=70, salary_satisfaction=90, strike_support=10,
                    workload=20, relation_status='normal'
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        )->execute([$this->playerId, $this->staffId]);

        $result = (new EmployeeStrikeService($this->db))->forceActiveForTesting(
            $this->playerId,
            'technical',
            new DateTimeImmutable('2026-07-26 12:00:00')
        );
        $strikes = (new EmployeeStrikeService($this->db))->activeForPlayer($this->playerId);

        $this->assertSame(1, $result['member_count']);
        $this->assertSame('active', $result['status']);
        $this->assertCount(1, $strikes);
        $this->assertSame((int)$strikes[0]['id'], $result['strike_id']);
        $this->assertSame('active', $strikes[0]['status']);
        $this->assertCount(
            1,
            (new EmployeeStrikeService($this->db))->members($this->playerId, $result['strike_id'])
        );
        $this->assertSame('on_strike', $this->canonicalRelationStatus());
        $this->assertSame(30.0, $this->canonicalMorale());
    }

    public function testRaiseRequestPersistsSalaryAndDoesNotDuplicatePostponedRequest(): void
    {
        $salaryStmt = $this->db->prepare(
            'SELECT salary FROM technical_staff WHERE id=? AND player_id=?'
        );
        $salaryStmt->execute([$this->staffId, $this->playerId]);
        $salary = (float)$salaryStmt->fetchColumn();
        $this->db->prepare(
            "UPDATE employee_state SET relation_status='raise_requested', last_raise_request_at=NULL
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        )->execute([$this->playerId, $this->staffId]);
        $service = new EmployeeStrikeService($this->db);
        $now = new DateTimeImmutable('2026-07-22 10:00:00');

        $first = $service->processEscalations($now);
        $this->db->prepare(
            "UPDATE employee_raise_requests SET status='postponed', deadline_at=?
              WHERE player_id=? AND source_type='technical_staff' AND source_id=? AND status='open'"
        )->execute(['2026-07-23 10:00:00', $this->playerId, $this->staffId]);
        $second = $service->processEscalations($now->modify('+1 hour'));

        $stmt = $this->db->prepare(
            'SELECT current_salary, requested_salary, negotiated_salary, reason_code, postponed_count, status
               FROM employee_raise_requests
              WHERE player_id=? AND source_type=\'technical_staff\' AND source_id=?'
        );
        $stmt->execute([$this->playerId, $this->staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, $first['raise_requests']);
        $this->assertSame(0, $second['raise_requests']);
        $this->assertSame($salary, (float)$row['current_salary']);
        $this->assertSame(round($salary * 1.10, 2), (float)$row['requested_salary']);
        $this->assertNull($row['negotiated_salary']);
        $this->assertSame('low_morale', $row['reason_code']);
        $this->assertSame(0, (int)$row['postponed_count']);
        $this->assertSame('postponed', $row['status']);
    }
    public function testRaiseAcceptanceOnMySqlDoesNotLowerLiveSalaryOrMutatePersonality(): void
    {
        $employeeStmt = $this->db->prepare(
            'SELECT salary, trait_loyalty FROM technical_staff WHERE id=? AND player_id=?'
        );
        $employeeStmt->execute([$this->staffId, $this->playerId]);
        $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($employee);
        $baseLoyalty = (int)$employee['trait_loyalty'];
        $this->db->prepare(
            "UPDATE employee_state SET relation_status='raise_requested', last_raise_request_at=NULL
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        )->execute([$this->playerId, $this->staffId]);
        (new EmployeeStrikeService($this->db))->processEscalations(
            new DateTimeImmutable('+1 day')
        );

        $requestStmt = $this->db->prepare(
            "SELECT id, requested_salary FROM employee_raise_requests
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?
              ORDER BY id DESC LIMIT 1"
        );
        $requestStmt->execute([$this->playerId, $this->staffId]);
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($request);
        $liveSalary = (float)$request['requested_salary'] + 1000.0;
        $this->db->prepare(
            "UPDATE technical_staff SET salary=? WHERE id=? AND player_id=? AND status IN ('active','busy')"
        )->execute([$liveSalary, $this->staffId, $this->playerId]);

        $result = (new EmployeeRaiseRequestService($this->db))->acceptFull(
            $this->playerId,
            (int)$request['id'],
            'mysql-stale-salary-token'
        );

        $employeeStmt->execute([$this->staffId, $this->playerId]);
        $updated = $employeeStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($updated);
        $this->assertSame($liveSalary, (float)$updated['salary']);
        $this->assertSame($baseLoyalty, (int)$updated['trait_loyalty']);
        $this->assertSame($liveSalary, (float)$result['salary']);
        $idempotent = (new EmployeeRaiseRequestService($this->db))->acceptFull(
            $this->playerId,
            (int)$request['id'],
            'mysql-stale-salary-token'
        );
        $this->assertTrue($idempotent['idempotent']);
        $modifierStmt = $this->db->prepare(
            "SELECT loyalty_modifier FROM employee_state
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        );
        $modifierStmt->execute([$this->playerId, $this->staffId]);
        $this->assertGreaterThan(0.0, (float)$modifierStmt->fetchColumn());
    }
    private function employeeRef(): EmployeeRef
    {
        return new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $this->staffId, $this->playerId);
    }

    private function setCanonicalMorale(float $morale): void
    {
        $stmt = $this->db->prepare(
            "UPDATE employee_state SET morale=?
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        );
        $stmt->execute([$morale, $this->playerId, $this->staffId]);
        $this->assertSame(1, $stmt->rowCount());
    }

    private function canonicalRelationStatus(): string
    {
        $stmt = $this->db->prepare(
            "SELECT relation_status FROM employee_state
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        );
        $stmt->execute([$this->playerId, $this->staffId]);
        return (string)$stmt->fetchColumn();
    }

    private function walletTotal(): float
    {
        $stmt = $this->db->prepare('SELECT cash + bank_balance FROM players WHERE id=?');
        $stmt->execute([$this->playerId]);
        return (float)$stmt->fetchColumn();
    }
    private function canonicalMorale(): float
    {
        $stmt = $this->db->prepare(
            "SELECT morale FROM employee_state
              WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
        );
        $stmt->execute([$this->playerId, $this->staffId]);
        return (float)$stmt->fetchColumn();
    }
}
