<?php
declare(strict_types=1);

// Read the existing register without inventing events / Odczyt rejestru bez tworzenia zdarzen.
final class WellHistoryService
{
    private const FILTERS = [
        'all' => [], 'failures' => ['failure', 'incident_minor', 'incident_major', 'incident_critical'],
        'service' => ['maintenance', 'repair', 'pump_replaced', 'inspection'],
        'configuration' => ['upgrade', 'layer_switch'],
        'production' => ['production', 'paused_cash', 'paused_storage', 'resumed'],
        'transport' => ['transport', 'transport_changed'],
    ];
    public function __construct(private PDO $db) {}

    /**
     * @return array{events:list<array<string, mixed>>,has_more:bool,page:int,filter:string}|null
     */
    public function page(int $playerId, int $wellId, string $filter, int $page): ?array
    {
        $owner = $this->db->prepare('SELECT id FROM wells WHERE id = ? AND player_id = ?');
        $owner->execute([$wellId, $playerId]);
        if (!$owner->fetchColumn()) return null;
        $filter = isset(self::FILTERS[$filter]) ? $filter : 'all';
        $page = max(1, min(10000, $page));
        $params = [$wellId, $playerId, $playerId];
        $sql = 'SELECT e.* FROM well_events e JOIN wells w ON w.id = e.well_id WHERE e.well_id = ? AND e.player_id = ? AND w.player_id = ?';
        if (self::FILTERS[$filter]) {
            $sql .= ' AND e.event_type IN (' . implode(',', array_fill(0, count(self::FILTERS[$filter]), '?')) . ')';
            $params = array_merge($params, self::FILTERS[$filter]);
        }
        $sql .= ' ORDER BY e.created_at DESC, e.id DESC LIMIT 21 OFFSET ' . (($page - 1) * 20);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['events' => array_slice($rows, 0, 20), 'has_more' => count($rows) > 20, 'page' => $page, 'filter' => $filter];
    }

    public static function category(string $type): string
    {
        foreach (self::FILTERS as $category => $types) if (in_array($type, $types, true)) return $category;
        return 'all';
    }
}
