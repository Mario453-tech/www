<?php
declare(strict_types=1);

final class WellNamingMigrationService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{status:string,preview:array<string,mixed>,applied:array<string,mixed>,verification:array<string,mixed>} */
    public function deploy(): array
    {
        WellNamingSchema::ensure($this->db);
        $preview = $this->run(false);
        $applied = $this->run(true);
        $verification = $this->run(false);
        if ($verification['renamed'] !== 0) {
            throw new RuntimeException('Well name migration verification failed.');
        }
        return [
            'status' => 'completed',
            'preview' => $preview,
            'applied' => $applied,
            'verification' => $verification,
        ];
    }

    /** @return array{total:int,renamed:int,preview:list<array{id:int,from:string,to:string}>} */
    public function run(bool $apply): array
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($apply) {
            $this->db->beginTransaction();
        }
        try {
            if ($apply && $driver === 'mysql') {
                $this->db->query('SELECT id FROM players ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
            }
            $sql = 'SELECT w.id, w.player_id, COALESCE(w.region_id, 0) AS region_id,
                           w.well_name, wr.code AS region_code
                      FROM wells w LEFT JOIN world_regions wr ON wr.id = w.region_id
                     ORDER BY w.player_id, region_id, w.id';
            if ($apply && $driver === 'mysql') {
                $sql .= ' FOR UPDATE';
            }
            $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $counters = [];
            try {
                foreach ($this->db->query('SELECT player_id, region_id, last_number FROM well_name_counters') as $counter) {
                    $counters[$counter['player_id'] . ':' . $counter['region_id']] = (int)$counter['last_number'];
                }
            } catch (PDOException $e) {
                if ($apply) {
                    throw new RuntimeException('Well name counters are missing; run --apply-schema first.', 0, $e);
                }
                $missingTable = str_contains($e->getMessage(), 'no such table')
                    || str_contains($e->getMessage(), '1146')
                    || $e->getCode() === '42S02';
                if (!$missingTable) {
                    throw $e;
                }
            }
            $reserved = [];
            $max = $counters;
            foreach ($rows as $row) {
                $key = $row['player_id'] . ':' . $row['region_id'];
                $prefix = WellNaming::prefix($row['region_code']);
                $name = (string)($row['well_name'] ?? '');
                if (preg_match('/^' . preg_quote($prefix, '/') . '-([1-9][0-9]*)$/', $name, $match)) {
                    $number = (int)$match[1];
                    if ($number > 0 && !isset($reserved[$key][$number])) {
                        $reserved[$key][$number] = (int)$row['id'];
                        $max[$key] = max($max[$key] ?? 0, $number);
                    }
                }
            }
            $changes = [];
            foreach ($rows as $row) {
                $key = $row['player_id'] . ':' . $row['region_id'];
                $prefix = WellNaming::prefix($row['region_code']);
                $current = (string)($row['well_name'] ?? '');
                $number = null;
                if (preg_match('/^' . preg_quote($prefix, '/') . '-([1-9][0-9]*)$/', $current, $match)
                    && ($reserved[$key][(int)$match[1]] ?? null) === (int)$row['id']) {
                    $number = (int)$match[1];
                }
                if ($number === null) {
                    $number = ($max[$key] ?? 0) + 1;
                    $max[$key] = $number;
                    $changes[] = ['id' => (int)$row['id'], 'player_id' => (int)$row['player_id'],
                        'from' => $current, 'to' => WellNaming::name($row['region_code'], $number)];
                }
            }
            if ($apply) {
                $update = $this->db->prepare('UPDATE wells SET well_name = ? WHERE id = ? AND player_id = ?');
                foreach ($changes as $change) {
                    $update->execute([$change['to'], $change['id'], $change['player_id']]);
                }
                $upsertSql = $driver === 'mysql'
                    ? 'INSERT INTO well_name_counters (player_id, region_id, last_number) VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))'
                    : 'INSERT INTO well_name_counters (player_id, region_id, last_number) VALUES (?, ?, ?)
                       ON CONFLICT(player_id, region_id) DO UPDATE SET last_number = MAX(last_number, excluded.last_number)';
                $upsert = $this->db->prepare($upsertSql);
                foreach ($max as $key => $number) {
                    [$playerId, $regionId] = array_map('intval', explode(':', $key, 2));
                    $upsert->execute([$playerId, $regionId, $number]);
                }
                $this->db->commit();
            }
            return ['total' => count($rows), 'renamed' => count($changes),
                'preview' => array_map(static fn(array $change): array => [
                    'id' => $change['id'], 'from' => $change['from'], 'to' => $change['to'],
                ], array_slice($changes, 0, 10))];
        } catch (Throwable $e) {
            if ($apply && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
