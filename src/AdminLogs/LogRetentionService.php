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

    /**
     * Release the session and send the response before touching retained data.
     * Zwolnij sesje i wyslij odpowiedz przed przetwarzaniem danych retencji.
     */
    public function cleanupAfterResponse(string $path, int $adminDays, int $gameDays, callable $finishResponse): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $finishResponse();
        $this->cleanupIfDue($path, $adminDays, $gameDays);
    }

    /**
     * Run maintenance at most hourly without queuing concurrent requests.
     * Uruchamiaj konserwacje najwyzej co godzine, bez kolejkowania zadan.
     * @return array{admin:int,game:int}|null
     */
    public function cleanupIfDue(string $path, int $adminDays, int $gameDays, ?DateTimeImmutable $now = null): ?array
    {
        if ($adminDays <= 0 && $gameDays <= 0) {
            return null;
        }
        $handle = @fopen($path . '.retention.log', 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open log maintenance marker');
        }
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                return null;
            }
            try {
                $now ??= new DateTimeImmutable();
                $last = json_decode((string)stream_get_contents($handle), true);
                if (is_array($last) && ($last['admin'] ?? null) === $adminDays
                    && ($last['game'] ?? null) === $gameDays
                    && $now->getTimestamp() - (int)($last['at'] ?? 0) < 3600) {
                    return null;
                }
                // Record the attempt first so failures cannot flood the worker pool.
                // Zapisz probe najpierw, aby bledy nie zajely calej puli procesow.
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, json_encode(['at' => $now->getTimestamp(), 'admin' => $adminDays, 'game' => $gameDays]));
                fflush($handle);
                return [
                    'admin' => $this->cleanupAdminLogs($adminDays, $now),
                    'game' => $this->cleanupGameLog($path, $gameDays, $now),
                ];
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
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
