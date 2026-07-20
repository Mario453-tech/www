<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/init.php'; 
require_once __DIR__ . '/BaseTestCase.php';

class MoraleServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup odbywa sie w klasie bazowej testu (baza MySQL juz istnieje).
        // Wymusimy czystą tabelę logów dla naszego pracownika 9999
        $db = Database::getInstance()->getConnection();
        $db->exec("DELETE FROM staff_morale_logs WHERE technical_staff_id = 9999");
        $db->exec("DELETE FROM staff_strikes WHERE technical_staff_id = 9999");
        $db->exec("DELETE FROM technical_staff WHERE id = 9999");
        $db->exec("INSERT INTO technical_staff (id, player_id, status, base_morale, current_morale) VALUES (9999, 1, 'active', 50, 50)");
    }

    protected function tearDown(): void
    {
        $db = Database::getInstance()->getConnection();
        $db->exec("DELETE FROM staff_morale_logs WHERE technical_staff_id = 9999");
        $db->exec("DELETE FROM staff_strikes WHERE technical_staff_id = 9999");
        $db->exec("DELETE FROM technical_staff WHERE id = 9999");
        parent::tearDown();
    }

    public function testModifyMoraleIncreasesMoraleAndLogs()
    {
        $db = Database::getInstance()->getConnection();
        $db->exec("UPDATE technical_staff SET current_morale = 50 WHERE id = 9999");

        MoraleService::modifyMorale(9999, 10, 'bonus.granted');

        $stmt = $db->query("SELECT current_morale FROM technical_staff WHERE id = 9999");
        $this->assertEquals(60, $stmt->fetchColumn());

        $logs = MoraleService::getMoraleHistory(9999);
        $this->assertCount(1, $logs);
        $this->assertEquals(10, $logs[0]['change_amount']);
        $this->assertEquals('bonus.granted', $logs[0]['reason']);
    }

    public function testModifyMoraleCappedAt100()
    {
        $db = Database::getInstance()->getConnection();
        $db->exec("UPDATE technical_staff SET current_morale = 95 WHERE id = 9999");

        MoraleService::modifyMorale(9999, 20, 'big.bonus');

        $stmt = $db->query("SELECT current_morale FROM technical_staff WHERE id = 9999");
        $this->assertEquals(100, $stmt->fetchColumn());

        $logs = MoraleService::getMoraleHistory(9999);
        $this->assertCount(1, $logs);
        $this->assertEquals(5, $logs[0]['change_amount']);
    }

    public function testModifyMoraleCappedAt0()
    {
        $db = Database::getInstance()->getConnection();
        $db->exec("UPDATE technical_staff SET current_morale = 10 WHERE id = 9999");

        MoraleService::modifyMorale(9999, -15, 'crisis');

        $stmt = $db->query("SELECT current_morale FROM technical_staff WHERE id = 9999");
        $this->assertEquals(0, $stmt->fetchColumn());

        $logs = MoraleService::getMoraleHistory(9999);
        $this->assertCount(1, $logs);
        $this->assertEquals(-10, $logs[0]['change_amount']);
    }

    public function testStrikeServiceStartAndResolve()
    {
        $db = Database::getInstance()->getConnection();
        $db->exec("UPDATE technical_staff SET current_morale = 5 WHERE id = 9999");

        $this->assertFalse(StrikeService::isStriking(9999));

        StrikeService::startStrike(9999, 'low_morale');
        
        $this->assertTrue(StrikeService::isStriking(9999));
        
        $strikes = StrikeService::getActiveStrikes(1);
        $this->assertArrayHasKey(9999, $strikes);
        $this->assertEquals('low_morale', $strikes[9999]['reason']);

        StrikeService::resolveStrike(9999);
        
        $this->assertFalse(StrikeService::isStriking(9999));
        $strikesAfter = StrikeService::getActiveStrikes(1);
        $this->assertArrayNotHasKey(9999, $strikesAfter);
    }
}
