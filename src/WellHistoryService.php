<?php
declare(strict_types=1);

// Read the existing register without inventing events / Odczyt rejestru bez tworzenia zdarzen.
final class WellHistoryService
{
    private const PAGE_SIZE = 20;
    private const RETENTION_HOURS = 48;
    private const FILTERS = [
        'all' => [], 'failures' => ['failure', 'incident_minor', 'incident_major', 'incident_critical'],
        'service' => ['maintenance', 'repair', 'pump_replaced', 'inspection'],
        'configuration' => ['upgrade', 'layer_switch'],
        'production' => ['production', 'paused_cash', 'paused_storage', 'resumed'],
        'transport' => ['transport', 'transport_changed'],
    ];
    public function __construct(private PDO $db) {}

    /**
     * @return array{events:list<array<string, mixed>>,has_more:bool,page:int,total_pages:int,total_events:int,page_size:int,filter:string}|null
     */
    public function page(int $playerId, int $wellId, string $filter, int $page, ?DateTimeInterface $now = null): ?array
    {
        $owner = $this->db->prepare('SELECT id FROM wells WHERE id = ? AND player_id = ?');
        $owner->execute([$wellId, $playerId]);
        if (!$owner->fetchColumn()) return null;
        $filter = isset(self::FILTERS[$filter]) ? $filter : 'all';
        $cutoff = $this->retentionCutoff($now);
        $params = [$wellId, $playerId, $playerId, $cutoff];
        $where = ' FROM well_events e JOIN wells w ON w.id = e.well_id WHERE e.well_id = ? AND e.player_id = ? AND w.player_id = ? AND e.created_at >= ?';
        if (self::FILTERS[$filter]) {
            $where .= ' AND e.event_type IN (' . implode(',', array_fill(0, count(self::FILTERS[$filter]), '?')) . ')';
            $params = array_merge($params, self::FILTERS[$filter]);
        }
        $countStmt = $this->db->prepare('SELECT COUNT(*)' . $where);
        $countStmt->execute($params);
        $totalEvents = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalEvents / self::PAGE_SIZE));
        $page = max(1, min($totalPages, $page));

        $sql = 'SELECT e.*' . $where . ' ORDER BY e.created_at DESC, e.id DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'events' => $rows,
            'has_more' => $page < $totalPages,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_events' => $totalEvents,
            'page_size' => self::PAGE_SIZE,
            'filter' => $filter,
        ];
    }

    private function retentionCutoff(?DateTimeInterface $now): string
    {
        if ($now !== null) {
            $clock = DateTimeImmutable::createFromInterface($now);
        } else {
            $databaseNow = $this->db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
            $clock = is_string($databaseNow)
                ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $databaseNow, new DateTimeZone('UTC'))
                : false;
            if (!$clock) throw new RuntimeException('Database clock is unavailable.');
        }
        return $clock->sub(new DateInterval('PT' . self::RETENTION_HOURS . 'H'))->format('Y-m-d H:i:s');
    }

    public static function category(string $type): string
    {
        foreach (self::FILTERS as $category => $types) if (in_array($type, $types, true)) return $category;
        return 'all';
    }
}
