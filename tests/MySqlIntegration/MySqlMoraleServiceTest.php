<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/init.php'; 

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

    public function testStrikeServiceStartAndResolve()
    {
        $this->db->exec("UPDATE technical_staff SET current_morale = 5 WHERE id = {$this->staffId}");

        $this->assertFalse(StrikeService::isStriking($this->staffId));

        StrikeService::startStrike($this->staffId, 'low_morale');
        
        $this->assertTrue(StrikeService::isStriking($this->staffId));
        
        $strikes = StrikeService::getActiveStrikes($this->playerId);
        $this->assertArrayHasKey($this->staffId, $strikes);
        $this->assertEquals('low_morale', $strikes[$this->staffId]['reason']);

        StrikeService::resolveStrike($this->staffId);
        
        $this->assertFalse(StrikeService::isStriking($this->staffId));
        $strikesAfter = StrikeService::getActiveStrikes($this->playerId);
        $this->assertArrayNotHasKey($this->staffId, $strikesAfter);
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
