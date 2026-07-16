<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/HubIncidentService.php';

/**
 * Integration tests for player and tenant isolation in hub incident effects.
 * Testy integracyjne izolacji wlasciciela i najemcy w efektach incydentow huba.
 */
final class MySqlHubIncidentEffectsTraitTest extends MySqlIntegrationTestCase
{
    private function getHubCondition(int $hubId): float
    {
        return (float)$this->db->query(
            "SELECT condition_pct FROM logistics_hubs WHERE id = {$hubId}"
        )->fetchColumn();
    }

    public function testApplyConditionDamageRejectsForeignPlayer(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId = $ids['hubId'];

        $this->seedHub($hubId, 'PHPUnit IncidentEffects Hub', 77, 'A1', 90.0, 'active');

        $condBefore = $this->getHubCondition($hubId);
        $this->assertEqualsWithDelta(90.0, $condBefore, 0.001);

        $hubService = new HubService($this->db);
        $incidentService = new HubIncidentService($this->db, $hubService);

        $ref = new ReflectionClass($incidentService);
        $method = $ref->getMethod('applyConditionDamage');
        $method->setAccessible(true);

        $method->invoke($incidentService, $hubId, 10, $playerId + 999);

        $this->assertEqualsWithDelta(
            $condBefore,
            $this->getHubCondition($hubId),
            0.001,
            'A foreign player must not change the hub condition'
        );

        $method->invoke($incidentService, $hubId, 10, $playerId);

        $this->assertEqualsWithDelta(
            $condBefore - 10.0,
            $this->getHubCondition($hubId),
            0.001,
            'The hub owner must be able to receive condition damage'
        );
    }

    public function testGenerateIncidentUsesExplicitTenantPlayerId(): void
    {
        $ids = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId = $ids['hubId'];

        $this->seedHub($hubId, 'PHPUnit Tenant Hub', 77, 'A1', 80.0, 'active');
        $this->db->prepare(
            'UPDATE logistics_hubs SET player_id = 0, tenant_player_id = ? WHERE id = ?'
        )->execute([$playerId, $hubId]);

        $condBefore = $this->getHubCondition($hubId);
        $hubService = new HubService($this->db);
        $incidentService = new HubIncidentService($this->db, $hubService);

        $ref = new ReflectionClass($incidentService);
        $method = $ref->getMethod('generateIncident');
        $method->setAccessible(true);

        $incidents = $ref->getConstant('INCIDENTS');
        $type = 'equipment_damage';
        $cfg = $incidents[$type];
        $tenantHub = [
            'id' => $hubId,
            'name' => 'PHPUnit Tenant Hub',
            'condition_pct' => 80.0,
        ];

        $method->invoke(
            $incidentService,
            $type,
            $cfg,
            $tenantHub,
            100.0,
            ['load_pct' => 50.0],
            $playerId
        );

        $this->assertLessThan(
            $condBefore,
            $this->getHubCondition($hubId),
            'The controlling tenant must receive condition damage for the rented hub'
        );
    }
}
