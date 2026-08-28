<?php
declare(strict_types=1);

/**
 * Applies admin and game log retention rules.
 * Stosuje reguly retencji logow admina i gry.
 */
final class LogRetentionService
{
    public function __construct(
        private PDO $db,
        private GameLogReader $gameLogReader
    ) {
    }

    public function cleanupAdminLogs(int $days, ?DateTimeImmutable $now = null): int
    {
        if ($days <= 0) {
            return 0;
        }

        $cutoff = ($now ?? new DateTimeImmutable())->modify("-{$days} days");
        $stmt = $this->db->prepare('DELETE FROM admin_logs WHERE created_at < :cutoff');
        $stmt->bindValue(':cutoff', $cutoff->format('Y-m-d H:i:s'));
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function cleanupGameLog(
        string $path,
        int $days,
        ?DateTimeImmutable $now = null
    ): int {
        if ($days <= 0) {
            return 0;
        }

        $cutoff = ($now ?? new DateTimeImmutable())->modify("-{$days} days");
        return $this->gameLogReader->pruneOlderThan($path, $cutoff);
    }
}
