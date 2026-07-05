<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/WellService.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/DisasterMessages.php';

/**
 * Integration tests for WellSellTrait (src/Well/SellTrait.php).
 *
 * Bug fixed: the FOR UPDATE query in sellWell() previously used
 *   $this->playerId  (which is undefined on WellService → evaluates to null)
 * instead of the $playerId method parameter. With player_id = NULL in the
 * WHERE clause, the lock query always returns 0 rows, so sellWell() always
 * failed with 'well_not_available', even for a fully valid active well.
 *
 * After the fix ($this->playerId → $playerId) the FOR UPDATE properly locks
 * the row, and:
 *   - active wells can be sold successfully,
 *   - broken/paused wells pass the lock step but fail at the UPDATE rowCount
 *     check (UPDATE requires status='active'), returning 'well_not_available'
 *     via the correct code path.
 */
final class MySqlWellSellTraitTest extends MySqlIntegrationTestCase
{
    private WellService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        // WellService uses Database::getInstance() which connects to the same
        // DB as the test PDO (same config/database.php).
        $this->svc = new WellService();
    }

    protected function tearDown(): void
    {
        $ids = $this->getTrackedIds();

        // Clean up tables written by sellWell() that are not covered by the
        // base cleanupTrackedIds().
        foreach (['bankruptcy_events', 'admin_logs', 'failure_log',
                  'industrial_disasters', 'director_notifications'] as $table) {
            try {
                $this->db->prepare("DELETE FROM `{$table}` WHERE player_id = ?")
                    ->execute([$ids['playerId']]);
            } catch (Throwable) {}
        }

        parent::tearDown();
    }

    // =========================================================================
    // Helper: seed a well that is old enough to pass the 2 h cooldown.
    // =========================================================================

    private function seedOldWell(int $playerId, int $wellId, string $status = 'active'): void
    {
        // base_production_per_hour = 200 keeps the sale profitable at realistic prices.
        // created_at comes from PHP to avoid DB/PHP timezone drift.
        $createdAt = date('Y-m-d H:i:s', time() - 3 * 3600);
        $this->db->prepare(
            "INSERT INTO wells
                (id, player_id, status, created_at, region_id, zone_key,
                 location_name, name, transport_type, transport_capacity_pct,
                 base_production_per_hour)
             VALUES (?, ?, ?, ?, 77, 'A1',
                     'PHPUnit Field', 'PHPUnit Well', 'rurociag', 120.0, 200.0)"
        )->execute([$wellId, $playerId, $status, $createdAt]);
    }

    // =========================================================================
    // Test 1: calculateSellValue succeeds for an active, profitable well.
    //
    // This verifies that the pre-sell value calculation path works correctly.
    // calculateSellValue() does NOT use FOR UPDATE so it is not affected by
    // the $this->playerId bug; testing it separately isolates the sell logic.
    // =========================================================================

    public function testCalculateSellValueSucceedsForActiveWell(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedOldWell($playerId, $ids['wellId'], 'active');

        $calc = $this->svc->calculateSellValue($ids['wellId'], $playerId);

        $this->assertNull(
            $calc['error'] ?? null,
            'calculateSellValue must not return an error for a profitable active well. Got: '
                . json_encode($calc['error'] ?? null)
        );
        $this->assertArrayHasKey('sell_value', $calc);
        $this->assertGreaterThan(0.0, (float)$calc['sell_value'], 'sell_value must be positive');
    }

    // =========================================================================
    // Test 2: sellWell succeeds for an active well after the FOR UPDATE fix.
    //
    // Before the fix: $this->playerId = null → player_id = NULL in the lock
    // query → 0 rows → 'well_not_available'.
    // After the fix:  $playerId (parameter) is used → lock succeeds → UPDATE
    // sets status='sold' → success=true.
    // =========================================================================

    public function testSellActiveWellSucceedsWithNewStatusFilter(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedOldWell($playerId, $ids['wellId'], 'active');

        $result = $this->svc->sellWell($ids['wellId'], $playerId);

        $this->assertTrue(
            $result['success'] ?? false,
            'sellWell must return success=true for an active well. Got: ' . json_encode($result)
        );
        $this->assertArrayHasKey('sell_value', $result);
        $this->assertGreaterThan(0.0, (float)($result['sell_value'] ?? 0));

        // Well status in DB must be 'sold'.
        $stmt = $this->db->prepare('SELECT status FROM wells WHERE id = ?');
        $stmt->execute([$ids['wellId']]);
        $this->assertSame('sold', $stmt->fetchColumn(),
            'Well status must be "sold" in DB after a successful sellWell()');
    }

    // =========================================================================
    // Test 3: selling a BROKEN well fails at the UPDATE rowCount step (not the
    // FOR UPDATE lock step). After the fix, broken wells pass the lock (they
    // are not in the excluded status list), but the UPDATE requires
    // status='active', so rowCount() = 0 → 'well_not_available'.
    // The well must remain in 'broken' status (transaction rolled back).
    // =========================================================================

    public function testForUpdateAllowsBrokenWellInLockStepButUpdateFails(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedOldWell($playerId, $ids['wellId'], 'active');

        // Force status to 'broken' to simulate post-crash state.
        $this->db->prepare("UPDATE wells SET status = 'broken' WHERE id = ?")
            ->execute([$ids['wellId']]);

        // calculateSellValue: 'broken' is NOT in the blocked list
        // (['seized','blowout','sold']), so it must not raise a status error.
        $calc = $this->svc->calculateSellValue($ids['wellId'], $playerId);
        if (!empty($calc['error'])) {
            $this->assertStringNotContainsString(
                'blocked_status',
                (string)$calc['error'],
                "'broken' status must not be blocked by calculateSellValue"
            );
        }

        // sellWell: after the fix the FOR UPDATE finds the broken well, but the
        // subsequent UPDATE (status='active') matches 0 rows → 'well_not_available'.
        $result = $this->svc->sellWell($ids['wellId'], $playerId);

        $this->assertFalse(
            $result['success'] ?? true,
            'sellWell must not succeed for a broken well'
        );
        $this->assertNotEmpty(
            $result['message'] ?? null,
            'sellWell must return a non-empty message on failure (UPDATE rowCount=0 path)'
        );

        // Well must remain in 'broken' status (transaction was rolled back).
        $stmt = $this->db->prepare('SELECT status FROM wells WHERE id = ?');
        $stmt->execute([$ids['wellId']]);
        $this->assertSame('broken', $stmt->fetchColumn(),
            'Well must remain broken after a failed sellWell()');
    }

    // =========================================================================
    // Test 4: calculateSellValue blocks 'blowout' status at the status-check
    // level (before the FOR UPDATE step).
    // =========================================================================

    public function testCalculateSellValueBlocksBlowoutStatus(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedOldWell($playerId, $ids['wellId'], 'active');

        $this->db->prepare("UPDATE wells SET status = 'blowout' WHERE id = ?")
            ->execute([$ids['wellId']]);

        $calc = $this->svc->calculateSellValue($ids['wellId'], $playerId);

        $this->assertNotEmpty($calc['error'],
            'calculateSellValue must return an error for a blowout well');
    }

    // =========================================================================
    // Test 5: calculateSellValue returns an error when the player does not own
    // the well (ownership guard).
    // =========================================================================

    public function testCalculateSellValueReturnsErrorForWrongPlayer(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $this->seedOldWell($playerId, $ids['wellId'], 'active');

        $wrongPlayerId = $ids['playerId'] + 9999;

        $calc = $this->svc->calculateSellValue($ids['wellId'], $wrongPlayerId);

        $this->assertNotEmpty($calc['error'],
            'Must return an error when the player does not own the well');
    }
}
