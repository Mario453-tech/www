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
        
        $ids = $this->getTrackedIds();
        $this->playerId = $this->seedPlayer();
        $this->staffId = $this->seedTechnicalWorker($this->playerId, 6);
        
        $this->db->exec("UPDATE technical_staff SET current_morale = 50 WHERE id = {$this->staffId}");
    }

    public function testModifyMoraleIncreasesMoraleAndLogs()
    {
        $this->db->exec("UPDATE technical_staff SET current_morale = 50 WHERE id = {$this->staffId}");

        MoraleService::modifyMorale($this->staffId, 10, 'bonus.granted');

        $stmt = $this->db->query("SELECT current_morale FROM technical_staff WHERE id = {$this->staffId}");
        $this->assertEquals(60, $stmt->fetchColumn());

        $logs = MoraleService::getMoraleHistory($this->staffId);
        $this->assertCount(1, $logs);
        $this->assertEquals(10, $logs[0]['change_amount']);
        $this->assertEquals('bonus.granted', $logs[0]['reason']);
    }

    public function testModifyMoraleCappedAt100()
    {
        $this->db->exec("UPDATE technical_staff SET current_morale = 95 WHERE id = {$this->staffId}");

        MoraleService::modifyMorale($this->staffId, 20, 'big.bonus');

        $stmt = $this->db->query("SELECT current_morale FROM technical_staff WHERE id = {$this->staffId}");
        $this->assertEquals(100, $stmt->fetchColumn());

        $logs = MoraleService::getMoraleHistory($this->staffId);
        $this->assertCount(1, $logs);
        $this->assertEquals(5, $logs[0]['change_amount']);
    }

    public function testModifyMoraleCappedAt0()
    {
        $this->db->exec("UPDATE technical_staff SET current_morale = 10 WHERE id = {$this->staffId}");

        MoraleService::modifyMorale($this->staffId, -15, 'crisis');

        $stmt = $this->db->query("SELECT current_morale FROM technical_staff WHERE id = {$this->staffId}");
        $this->assertEquals(0, $stmt->fetchColumn());

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
}
