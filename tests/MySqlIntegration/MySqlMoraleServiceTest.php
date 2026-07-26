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
