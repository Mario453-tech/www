<?php

/**
 * HubPlayerQueryTrait - player-facing read operations for logistics hubs.
 * Used by HubService.
 */
trait HubPlayerQueryTrait
{
 /**
 * Returns hubs where a given player has at least one active well assigned.
 * @return list<array<string, mixed>>
 */
    public function getPlayerHubs(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT h.*,
                    wr.name AS region_name,
                    (SELECT COUNT(*) FROM logistics_hub_assignments a2
                      WHERE a2.hub_id = h.id AND a2.status = 'active') AS assigned_count
               FROM logistics_hubs h
               JOIN logistics_hub_assignments a ON a.hub_id = h.id
               JOIN wells w                     ON w.id = a.well_id AND w.player_id = ?
               LEFT JOIN world_regions wr       ON wr.id = h.region_id
              WHERE a.status = 'active'
              ORDER BY h.region_id, h.zone_key, h.name"
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * Returns hubs OWNED by the player (player_id = playerId).
 * @return list<array<string, mixed>>
 */
    public function getMyOwnedHubs(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*,
                    wr.name AS region_name,
                    (SELECT COUNT(*) FROM logistics_hub_assignments a2
                      WHERE a2.hub_id = h.id AND a2.status = 'active') AS assigned_count
               FROM logistics_hubs h
               LEFT JOIN world_regions wr ON wr.id = h.region_id
              WHERE h.player_id = ?
                AND h.status NOT IN ('disabled')
              ORDER BY h.region_id, h.zone_key, h.name"
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * Returns hubs RENTED by the player (player_id = 0, tenant_player_id = playerId).
 * @return list<array<string, mixed>>
 */
    public function getMyRentedHubs(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*,
                    wr.name AS region_name,
                    (SELECT COUNT(*) FROM logistics_hub_assignments a2
                      WHERE a2.hub_id = h.id AND a2.status = 'active') AS assigned_count
               FROM logistics_hubs h
               LEFT JOIN world_regions wr ON wr.id = h.region_id
              WHERE h.player_id = 0
                AND h.tenant_player_id = ?
                AND h.status NOT IN ('disabled')
              ORDER BY h.region_id, h.zone_key, h.name"
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * Returns available market hubs in a region (player_id=0, no active tenant, available to buy/rent).
 * @return list<array<string, mixed>>
 */
    public function getMarketHubs(int $regionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*,
                    wr.name AS region_name,
                    0 AS assigned_count
               FROM logistics_hubs h
               LEFT JOIN world_regions wr ON wr.id = h.region_id
              WHERE h.region_id = ?
                AND h.player_id = 0
                AND h.tenant_player_id = 0
                AND h.status NOT IN ('disabled','building')
              ORDER BY h.hub_type, h.zone_key, h.name"
        );
        $stmt->execute([$regionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns one filtered market page scoped to the player's operating regions.
     * Zwraca jedna filtrowana strone rynku tylko z regionow gracza.
     * @return array{hubs: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function getMarketHubsPageForPlayer(
        int $playerId,
        string $search,
        string $filter,
        int $page,
        int $perPage = 10
    ): array {
        $regionIds = $this->getPlayerRegionIds($playerId);
        if ($regionIds === []) {
            return ['hubs' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        $search = mb_substr(trim($search), 0, 100);
        $filter = in_array($filter, ['all', 'free', 'new', 'used', 'rental', 'large', 'medium', 'small'], true)
            ? $filter : 'all';
        $perPage = max(1, min(50, $perPage));
        $placeholders = implode(',', array_fill(0, count($regionIds), '?'));
        $where = "h.region_id IN ($placeholders)
            AND h.player_id = 0 AND h.tenant_player_id = 0
            AND h.status NOT IN ('disabled','building')";
        $params = $regionIds;
        if ($search !== '') {
            $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $where .= " AND (h.name LIKE ? ESCAPE '!' OR h.zone_key LIKE ? ESCAPE '!'
                OR wr.name LIKE ? ESCAPE '!')";
            $like = '%' . $escapedSearch . '%';
            array_push($params, $like, $like, $like);
        }
        if ($filter === 'free') {
            $where .= " AND h.slot_limit > (SELECT COUNT(*) FROM logistics_hub_assignments a WHERE a.hub_id = h.id AND a.status = 'active')";
        } elseif (in_array($filter, ['new', 'used', 'rental'], true)) {
            $where .= ' AND h.acquisition_type = ?';
            $params[] = $filter;
        } elseif (in_array($filter, ['large', 'medium', 'small'], true)) {
            $where .= ' AND h.hub_type = ?';
            $params[] = $filter;
        }

        $from = ' FROM logistics_hubs h LEFT JOIN world_regions wr ON wr.id = h.region_id WHERE ' . $where;
        $countStmt = $this->db->prepare('SELECT COUNT(*)' . $from);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $stmt = $this->db->prepare(
            "SELECT h.*, wr.name AS region_name,
                (SELECT COUNT(*) FROM logistics_hub_assignments a WHERE a.hub_id = h.id AND a.status = 'active') AS assigned_count" . $from
            . ' ORDER BY wr.name, h.hub_type, h.zone_key, h.name, h.id LIMIT ? OFFSET ?'
        );
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return ['hubs' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

 /**
 * Returns ALL active hubs in a region accessible to the player (owned + rented + legacy market).
 * Used for well assignment - only player's own hubs shown.
 * @return list<array<string, mixed>>
 */
    public function getRegionHubs(int $regionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*,
                    wr.name AS region_name,
                    (SELECT COUNT(*) FROM logistics_hub_assignments a
                      WHERE a.hub_id = h.id AND a.status = 'active') AS assigned_count
               FROM logistics_hubs h
               LEFT JOIN world_regions wr ON wr.id = h.region_id
              WHERE h.region_id = ?
                AND h.status NOT IN ('disabled','building')
              ORDER BY h.hub_type, h.zone_key, h.name"
        );
        $stmt->execute([$regionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * Returns wells of a player that have no active hub assignment.
 * Includes cooldown_until when the well is still in detach cooldown (blocks re-assignment).
 * @return list<array<string, mixed>>
 */
    public function getUnassignedWells(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT w.id, w.name, w.well_name, w.location_name, w.region_id, w.zone_key, w.status,
                    w.base_production_per_hour,
                    wr.name AS region_name,
                    (SELECT a.cooldown_until
                       FROM logistics_hub_assignments a
                      WHERE a.well_id = w.id
                        AND a.status  = 'detached'
                        AND a.cooldown_until > NOW()
                      ORDER BY a.cooldown_until DESC
                      LIMIT 1
                    ) AS cooldown_until
               FROM wells w
               LEFT JOIN world_regions wr ON wr.id = w.region_id
              WHERE w.player_id = ?
                AND w.status NOT IN ('sold','seized')
                AND NOT EXISTS (
                    SELECT 1 FROM logistics_hub_assignments a
                     WHERE a.well_id = w.id AND a.status = 'active'
                )
              ORDER BY w.region_id, w.location_name"
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * Returns distinct region_ids where a player has active wells.
 * @return list<int>
 */
    public function getPlayerRegionIds(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT region_id FROM wells
              WHERE player_id = ? AND status NOT IN ('sold','seized')
                AND region_id IS NOT NULL AND region_id > 0"
        );
        $stmt->execute([$playerId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

 /**
 * Returns zone definitions for a region.
 * @return list<array<string, mixed>>
 */
    public function getRegionZones(int $regionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM logistics_region_zones
              WHERE region_id = ? AND is_active = 1
              ORDER BY zone_key"
        );
        $stmt->execute([$regionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
