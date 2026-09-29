<?php
declare(strict_types=1);

/**
 * Server pages and retention for completed marine deliveries.
 * Strony serwera i retencja zakonczonych dostaw morskich.
 */
final class MarineHistoryService
{
    public function __construct(private PDO $db) {}

    /** @return array{items:array,total:int,page:int,pages:int} */
    public function page(int $playerId, int $page): array
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM marine_deliveries WHERE player_id = ? AND status IN ('delivered','lost')");
        $stmt->execute([$playerId]);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / 5));
        $page = min(max(1, $page), $pages);
        $stmt = $this->db->prepare("SELECT md.*, p.name AS port_name, COALESCE(w.name, w.location_name) AS well_name
            FROM marine_deliveries md LEFT JOIN ports p ON p.id = md.port_id
            LEFT JOIN wells w ON w.id = md.well_id AND w.player_id = md.player_id
            WHERE md.player_id = ? AND md.status IN ('delivered','lost')
            ORDER BY COALESCE(md.delivered_at, md.arrived_at, md.eta_at, md.created_at) DESC, md.id DESC
            LIMIT 5 OFFSET ?");
        $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(2, ($page - 1) * 5, PDO::PARAM_INT);
        $stmt->execute();
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function pruneBefore(string $cutoff): int
    {
        $stmt = $this->db->prepare("DELETE FROM marine_deliveries WHERE status IN ('delivered','lost')
            AND COALESCE(delivered_at, arrived_at, eta_at, created_at) < ?");
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }
}
