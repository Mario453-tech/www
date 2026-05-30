<?php

/**
 * HubAdminQueryTrait - admin-only read operations for logistics hubs.
 * Used by HubService.
 */
trait HubAdminQueryTrait
{
 /**
 * Returns all hubs in the system with optional status filter (admin use).
 * @return list<array<string, mixed>>
 */
    public function getAllHubs(string $statusFilter = '', int $regionFilter = 0): array
    {
        $conditions = [];
        $params     = [];

        if ($statusFilter !== '') {
            $conditions[] = 'h.status = ?';
            $params[]     = $statusFilter;
        }
        if ($regionFilter > 0) {
            $conditions[] = 'h.region_id = ?';
            $params[]     = $regionFilter;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $this->db->prepare(
            "SELECT h.*,
                    wr.name AS region_name,
                    (SELECT COUNT(*) FROM logistics_hub_assignments a
                      WHERE a.hub_id = h.id AND a.status = 'active') AS assigned_count
               FROM logistics_hubs h
               LEFT JOIN world_regions wr ON wr.id = h.region_id
              {$where}
              ORDER BY h.region_id, h.hub_type, h.name"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * Returns the most recent tick stats for a hub.
 * @return array<string, mixed>|null
 */
    public function getLastTickStats(int $hubId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM logistics_hub_tick_stats
              WHERE hub_id = ?
              ORDER BY tick_time DESC
              LIMIT 1"
        );
        $stmt->execute([$hubId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

 /**
 * Returns recent tick stats history for a hub (chronological order).
 * @return list<array<string, mixed>>
 */
    public function getTickStatsHistory(int $hubId, int $limit = 24): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM logistics_hub_tick_stats
              WHERE hub_id = ?
              ORDER BY tick_time DESC
              LIMIT ?"
        );
        $stmt->execute([$hubId, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

 /**
 * Returns unread events for a player.
 * @return list<array<string, mixed>>
 */
    public function getUnreadEvents(int $playerId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM logistics_hub_events
              WHERE player_id = ? AND is_read = 0
              ORDER BY created_at DESC
              LIMIT ?"
        );
        $stmt->execute([$playerId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
