<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

/**
 * Integration tests for WellShop and Well upgrade logic on real MySQL.
 *
 * Test 4: WellShop.getPlayerWellCount excludes sold wells
 * Test 6: Well.upgrade() deducts cost based on the locked row level
 */
final class MySqlWellShopTest extends MySqlIntegrationTestCase
{
    private function getCash(int $playerId): float
    {
        return (float)$this->db->query(
            "SELECT cash FROM players WHERE id = {$playerId}"
        )->fetchColumn();
    }

    // =========================================================================
    // Test 4: getPlayerWellCount excludes sold wells
    // =========================================================================

    /**
     * WellShop::getPlayerWellCount() must NOT count wells with status='sold'.
     *
     * Bug fix: previously the query counted all wells for the player; it now
     * adds AND status != 'sold' so sold wells are excluded from the limit check.
     */
    public function testGetPlayerWellCountExcludesSoldWells(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();

        // Seed two wells: one active, one sold
        $this->seedWell($playerId, $ids['wellId'],    'active');
        $this->seedWell($playerId, $ids['auxWellId'], 'active');

        // Mark auxWellId as sold directly in the DB
        $this->db->prepare("UPDATE wells SET status = 'sold' WHERE id = ?")
            ->execute([$ids['auxWellId']]);

        $shop  = new WellShop();
        $count = $shop->getPlayerWellCount($playerId);

        $this->assertSame(1, $count,
            'getPlayerWellCount must return 1 — sold well must be excluded');
    }

    // =========================================================================
    // Test 6: Well.upgrade() calculates cost from the locked row level
    // =========================================================================

    /**
     * Well::upgrade() must read the current level from the locked DB row
     * and deduct (level * 10 000) rather than using any stale in-memory value.
     *
     * Level 1 -> cost 10 000; level 2 -> cost 20 000.
     */
    public function testUpgradeCostCalculatedFromLockedRow(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $wellId   = $ids['wellId'];

        // Seed a level-1 well
        $this->seedWell($playerId, $wellId, 'active');
        // Ensure level=1 explicitly (seedWell does not set level)
        $this->db->prepare("UPDATE wells SET level = 1 WHERE id = ?")
            ->execute([$wellId]);

        $cashBefore = $this->getCash($playerId);

        $well = new Well($playerId);

        // First upgrade: level 1 -> 2, cost = 1 * 10 000 = 10 000
        $result1 = $well->upgrade($wellId);
        $this->assertTrue($result1, 'First upgrade must succeed');

        $cashAfterFirst = $this->getCash($playerId);
        $this->assertEqualsWithDelta(
            $cashBefore - 10_000.0,
            $cashAfterFirst,
            0.01,
            'First upgrade must deduct 1 * 10 000 = 10 000'
        );

        $levelAfterFirst = (int)$this->db->query(
            "SELECT level FROM wells WHERE id = {$wellId}"
        )->fetchColumn();
        $this->assertSame(2, $levelAfterFirst, 'Well level must be 2 after first upgrade');

        // Second upgrade: level 2 -> 3, cost = 2 * 10 000 = 20 000
        $result2 = $well->upgrade($wellId);
        $this->assertTrue($result2, 'Second upgrade must succeed');

        $cashAfterSecond = $this->getCash($playerId);
        $this->assertEqualsWithDelta(
            $cashAfterFirst - 20_000.0,
            $cashAfterSecond,
            0.01,
            'Second upgrade must deduct 2 * 10 000 = 20 000'
        );

        $levelAfterSecond = (int)$this->db->query(
            "SELECT level FROM wells WHERE id = {$wellId}"
        )->fetchColumn();
        $this->assertSame(3, $levelAfterSecond, 'Well level must be 3 after second upgrade');
    }
}
