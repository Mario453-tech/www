<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/HubIncidentService.php';

/**
 * Integration tests for HubIncidentEffectsTrait on real MySQL.
 *
 * Test 5: applyConditionDamage is skipped when player_id is missing from $hub
 *
 * The fix: generateIncident() guards against a missing/null hub['player_id']
 * and calls applyConditionDamage() only when it is set. We verify the guard by
 * calling applyConditionDamage directly (via Reflection) with a wrong player_id
 * and confirming the hub condition_pct is unchanged.
 */
final class MySqlHubIncidentEffectsTraitTest extends MySqlIntegrationTestCase
{
    private function getHubCondition(int $hubId): float
    {
        return (float)$this->db->query(
            "SELECT condition_pct FROM logistics_hubs WHERE id = {$hubId}"
        )->fetchColumn();
    }

    // =========================================================================
    // Test 5a: applyConditionDamage with wrong player_id does not update the hub
    // =========================================================================

    /**
     * applyConditionDamage() filters by player_id in the UPDATE WHERE clause.
     * Calling it with a non-matching player_id must leave condition_pct unchanged.
     *
     * This directly tests the SQL guard added in the bug fix:
     *   WHERE id = ? AND player_id = ?
     */
    public function testApplyConditionDamageSkippedWhenPlayerIdMismatches(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId    = $ids['hubId'];

        $this->seedHub($hubId, 'PHPUnit IncidentEffects Hub', 77, 'A1', 90.0, 'active');

        $condBefore = $this->getHubCondition($hubId);
        $this->assertEqualsWithDelta(90.0, $condBefore, 0.001, 'Hub must start at 90% condition');

        $hubService      = new HubService($this->db);
        $incidentService = new HubIncidentService($this->db, $hubService);

        // Access the private applyConditionDamage via Reflection
        $ref    = new ReflectionClass($incidentService);
        $method = $ref->getMethod('applyConditionDamage');
        $method->setAccessible(true);

        // Call with a wrong player_id (playerId + 999) — must not touch the hub row
        $wrongPlayerId = $playerId + 999;
        $method->invoke($incidentService, $hubId, 10, $wrongPlayerId);

        $condAfterWrong = $this->getHubCondition($hubId);
        $this->assertEqualsWithDelta(
            $condBefore,
            $condAfterWrong,
            0.001,
            'condition_pct must be unchanged when wrong player_id is used'
        );

        // Sanity check: correct player_id DOES update condition
        $method->invoke($incidentService, $hubId, 10, $playerId);

        $condAfterCorrect = $this->getHubCondition($hubId);
        $this->assertEqualsWithDelta(
            $condBefore - 10.0,
            $condAfterCorrect,
            0.001,
            'condition_pct must decrease by 10 when correct player_id is used'
        );
    }

    // =========================================================================
    // Test 5b: generateIncident skips condition damage when hub['player_id'] is missing
    // =========================================================================

    /**
     * The guard in generateIncident() checks isset($hub['player_id']) before
     * calling applyConditionDamage(). When player_id is absent from the $hub
     * array, condition_pct must remain unchanged even if condDmg > 0.
     *
     * We force condDmg > 0 by using an incident type whose condition_dmg range
     * is [1, 1] (deterministic) and set $hub without 'player_id'.
     *
     * Because generateIncident() is private we drive it via forceIncident() with
     * a modified hub that lacks player_id, reached through a test-double approach:
     * instead, we test the guard in isolation using Reflection on generateIncident
     * with a hand-crafted $hub array missing 'player_id'.
     */
    public function testGenerateIncidentSkipsConditionDamageWhenHubPlayerIdMissing(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId    = $ids['hubId'];

        $this->seedHub($hubId, 'PHPUnit Guard Hub', 77, 'A1', 80.0, 'active');

        $condBefore = $this->getHubCondition($hubId);
        $this->assertEqualsWithDelta(80.0, $condBefore, 0.001);

        $hubService      = new HubService($this->db);
        $incidentService = new HubIncidentService($this->db, $hubService);

        $ref    = new ReflectionClass($incidentService);
        $method = $ref->getMethod('generateIncident');
        $method->setAccessible(true);

        // Incident type 'equipment_damage' has condition_dmg [5, 15] — always > 0
        // We use the real INCIDENTS constant from HubIncidentRiskTrait
        $incidents = $ref->getConstant('INCIDENTS');
        $type      = 'equipment_damage';
        $cfg       = $incidents[$type];

        // $hub without 'player_id' key — this is the condition the guard protects against
        $hubWithoutPlayerId = [
            'id'            => $hubId,
            'name'          => 'PHPUnit Guard Hub',
            'condition_pct' => 80.0,
            // 'player_id' intentionally absent
        ];

        $tickResult = ['load_pct' => 50.0];

        // generateIncident returns the incident data; condition damage must be skipped
        $method->invoke($incidentService, $type, $cfg, $hubWithoutPlayerId, 100.0, $tickResult, $playerId);

        $condAfter = $this->getHubCondition($hubId);
        $this->assertEqualsWithDelta(
            $condBefore,
            $condAfter,
            0.001,
            'condition_pct must be unchanged when hub array has no player_id key'
        );
    }
}
