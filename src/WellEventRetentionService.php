<?php
declare(strict_types=1);

/** Deletes expired player-visible well history in bounded batches until the backlog is empty. */
final class WellEventRetentionService
{
    private const BATCH_SIZE = 500;

    public function __construct(private PDO $db)
    {
    }

    public function pruneBefore(string $cutoff): int
    {
        $deleted = 0;
        while (true) {
            $select = $this->db->prepare('SELECT id FROM well_events WHERE created_at < ? ORDER BY id LIMIT ?');
            $select->bindValue(1, $cutoff, PDO::PARAM_STR);
            $select->bindValue(2, self::BATCH_SIZE, PDO::PARAM_INT);
            $select->execute();
            $ids = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));
            if ($ids === []) break;

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $delete = $this->db->prepare("DELETE FROM well_events WHERE id IN ({$placeholders}) AND created_at < ?");
            foreach ($ids as $index => $id) $delete->bindValue($index + 1, $id, PDO::PARAM_INT);
            $delete->bindValue(count($ids) + 1, $cutoff, PDO::PARAM_STR);
            $delete->execute();
            $deleted += $delete->rowCount();
            if (count($ids) < self::BATCH_SIZE) break;
        }
        return $deleted;
    }
}