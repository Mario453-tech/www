<?php

/**
 * HubQueryTrait shared getHub() used by all other traits and services.
 *
 * Hubs are SYSTEM-OWNED infrastructure (player_id = 0).
 * No player ownership check any authenticated user can read hub data.
 *
 * Extended by:
 * HubAdminQueryTrait getAllHubs, tick stats, events
 * HubPlayerQueryTrait getPlayerHubs, getRegionHubs, getUnassignedWells, getPlayerRegionIds
 * HubAssignmentQueryTrait getWellAssignment, getHubWells, getHubWellsForPlayer
 *
 * Used by HubService.
 */
trait HubQueryTrait
{
 /**
 * Returns a single hub by ID (no player ownership check).
 * Used by admin panel, tick service, and internal validation.
 * @return array<string, mixed>|null
 */
    public function getHub(int $hubId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*,
                    wr.name AS region_name,
                    (SELECT COUNT(*) FROM logistics_hub_assignments a
                      WHERE a.hub_id = h.id AND a.status = 'active') AS assigned_count
               FROM logistics_hubs h
               LEFT JOIN world_regions wr ON wr.id = h.region_id
              WHERE h.id = ?
              LIMIT 1"
        );
        $stmt->execute([$hubId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
