<?php

/**
 * Database clock for well incident immunity and pressure.
 * Zegar bazy dla odpornosci i presji incydentow odwiertu.
 */
trait IncidentCooldownTrait
{
    /** @var array<int, int> */
    private array $cooldownAges = [];
    private ?int $cooldownPlayerId = null;

    public static function ensureCooldownSchema(\PDO $db): void
    {
        if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }
        if ($db->query("SHOW COLUMNS FROM wells LIKE 'incident_cooldown_started_at'")->fetch()) {
            return;
        }
        // DDL must precede the player transaction; never implicitly commit a tick.
        // DDL musi poprzedzac transakcje gracza; nie wolno niejawnie zatwierdzic ticka.
        if ($db->inTransaction()) {
            throw new \RuntimeException('Incident cooldown schema must be prepared before the player transaction');
        }
        try {
            $db->exec('ALTER TABLE wells ADD COLUMN incident_cooldown_started_at DATETIME NULL');
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1060) {
                throw $e;
            }
        }
    }

    public function preloadCooldowns(int $playerId): void
    {
        // One read per player; history is consulted only to initialize a missing clock.
        // Jeden odczyt na gracza; historia sluzy tylko do inicjalizacji brakujacego zegara.
        $stmt = $this->db->prepare(
            "SELECT c.id, c.stored_start, c.started_at, GREATEST(0, TIMESTAMPDIFF(SECOND, c.started_at, NOW())) AS age_seconds
             FROM (SELECT w.id, w.incident_cooldown_started_at AS stored_start, COALESCE(w.incident_cooldown_started_at,
                (SELECT wi.created_at FROM well_incidents wi
                 WHERE wi.well_id = w.id AND wi.player_id = w.player_id AND wi.level <> 'micro'
                 ORDER BY wi.created_at DESC LIMIT 1),
                CASE WHEN w.ticks_since_incident BETWEEN 1 AND 998
                     THEN GREATEST(w.created_at, TIMESTAMPADD(SECOND, -w.ticks_since_incident * 300, COALESCE(p.last_tick_at, NOW())))
                     ELSE w.created_at END) AS started_at
                FROM wells w LEFT JOIN players p ON p.id = w.player_id WHERE w.player_id = ?) c"
        );
        $stmt->execute([$playerId]);
        $this->cooldownAges = [];
        $initialStarts = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $this->cooldownAges[(int)$row['id']] = (int)$row['age_seconds'];
            if ($row['stored_start'] === null) {
                $initialStarts[(int)$row['id']] = (string)$row['started_at'];
            }
        }
        // Persist inactive wells too before retention can remove their incident history.
        // Zapisz tez nieaktywne odwierty, zanim retencja usunie ich historie incydentow.
        if ($initialStarts !== []) {
            $cases = [];
            $params = [];
            foreach ($initialStarts as $wellId => $startedAt) {
                $cases[] = 'WHEN ? THEN ?';
                $params[] = $wellId;
                $params[] = $startedAt;
            }
            $placeholders = implode(',', array_fill(0, count($initialStarts), '?'));
            $sql = 'UPDATE wells SET incident_cooldown_started_at = CASE id ' . implode(' ', $cases)
                . ' END WHERE player_id = ? AND incident_cooldown_started_at IS NULL AND id IN (' . $placeholders . ')';
            $this->db->prepare($sql)->execute([...$params, $playerId, ...array_keys($initialStarts)]);
        }
        $this->cooldownPlayerId = $playerId;
    }

    private function cooldownAgeSeconds(int $wellId, int $playerId): ?int
    {
        if ($this->cooldownPlayerId !== $playerId) {
            $this->preloadCooldowns($playerId);
        }
        return $this->cooldownAges[$wellId] ?? null;
    }
}
