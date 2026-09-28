<?php
declare(strict_types=1);

/**
 * Player-scoped read model for hub and pipeline incidents.
 * Model odczytu incydentow hubow i rurociagow ograniczony do gracza.
 */
final class LogisticsIncidentFeed
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function page(int $playerId, int $limit, int $offset, string $query = '', string $severity = ''): array
    {
        [$filterSql, $filterParams] = $this->filters($query, $severity);
        $sql = 'SELECT events.* FROM (' . $this->sourceSql() . ') events WHERE 1=1 ' . $filterSql
            . ' ORDER BY events.created_at DESC, events.source DESC, events.id DESC LIMIT ? OFFSET ?';
        $stmt = $this->db->prepare($sql);
        $params = array_merge([$playerId, $playerId], $filterParams);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $playerId, string $query = '', string $severity = ''): int
    {
        [$filterSql, $filterParams] = $this->filters($query, $severity);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM (' . $this->sourceSql() . ') events WHERE 1=1 ' . $filterSql);
        $stmt->execute(array_merge([$playerId, $playerId], $filterParams));
        return (int)$stmt->fetchColumn();
    }

    private function sourceSql(): string
    {
        return "SELECT e.id, e.player_id, e.hub_id, NULL AS pipeline_id, e.severity,
                       e.message, e.meta_json, e.created_at,
                       COALESCE(h.name, CONCAT('Hub #', e.hub_id)) AS hub_name, 'hub' AS source
                  FROM logistics_hub_events e
                  LEFT JOIN logistics_hubs h ON h.id = e.hub_id AND h.player_id = e.player_id
                 WHERE e.player_id = ? AND e.event_type LIKE 'hub_incident_%'
                UNION ALL
                SELECT p.id, p.player_id, NULL AS hub_id, p.pipeline_id,
                       CASE p.severity WHEN 'danger' THEN 'critical' WHEN 'warning' THEN 'medium' ELSE 'low' END,
                       p.message, NULL AS meta_json, p.created_at,
                       COALESCE(wp.name, CONCAT('Pipeline #', p.pipeline_id)) AS hub_name, 'pipeline' AS source
                  FROM well_pipeline_events p
                  LEFT JOIN well_pipelines wp ON wp.id = p.pipeline_id AND wp.player_id = p.player_id
                 WHERE p.player_id = ? AND p.event_type = 'incident'";
    }

    /** @return array{0:string,1:list<string>} */
    private function filters(string $query, string $severity): array
    {
        $sql = '';
        $params = [];
        if (in_array($severity, ['critical', 'high', 'medium', 'low'], true)) {
            $sql .= ' AND events.severity = ?';
            $params[] = $severity;
        }
        $query = trim(mb_substr($query, 0, 100));
        if ($query !== '') {
            $sql .= ' AND (events.message LIKE ? OR events.hub_name LIKE ?)';
            $pattern = '%' . addcslashes($query, '%_\\') . '%';
            $params[] = $pattern;
            $params[] = $pattern;
        }
        return [$sql, $params];
    }
}
