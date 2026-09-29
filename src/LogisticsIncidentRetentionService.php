<?php
declare(strict_types=1);

/**
 * Prunes player-visible logistics incident history in bounded batches.
 * Usuwa historie incydentow logistyki gracza w ograniczonych partiach.
 */
final class LogisticsIncidentRetentionService
{
    private const BATCH_SIZE = 500;
    private const MAX_BATCHES = 4;

    public function __construct(private PDO $db)
    {
    }

    /** @return array{hub:int,pipeline:int} */
    public function pruneBefore(string $cutoff): array
    {
        return [
            'hub' => $this->pruneTable('logistics_hub_events', "event_type LIKE 'hub_incident!_%' ESCAPE '!'", $cutoff),
            'pipeline' => $this->pruneTable('well_pipeline_events', "event_type = 'incident'", $cutoff),
        ];
    }

    private function pruneTable(string $table, string $eventFilter, string $cutoff): int
    {
        $deleted = 0;
        for ($batch = 0; $batch < self::MAX_BATCHES; $batch++) {
            $select = $this->db->prepare(
                "SELECT id FROM {$table} WHERE {$eventFilter} AND created_at < ? ORDER BY id LIMIT ?"
            );
            $select->bindValue(1, $cutoff, PDO::PARAM_STR);
            $select->bindValue(2, self::BATCH_SIZE, PDO::PARAM_INT);
            $select->execute();
            $ids = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));
            if ($ids === []) {
                break;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $delete = $this->db->prepare(
                "DELETE FROM {$table} WHERE id IN ({$placeholders}) AND {$eventFilter} AND created_at < ?"
            );
            foreach ($ids as $index => $id) {
                $delete->bindValue($index + 1, $id, PDO::PARAM_INT);
            }
            $delete->bindValue(count($ids) + 1, $cutoff, PDO::PARAM_STR);
            $delete->execute();
            $deleted += $delete->rowCount();
            if (count($ids) < self::BATCH_SIZE) {
                break;
            }
        }
        return $deleted;
    }
}
