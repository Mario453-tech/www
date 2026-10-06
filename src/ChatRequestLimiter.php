<?php
declare(strict_types=1);

final class ChatRequestLimiter
{
    public function __construct(private PDO $db) {}

    public function consume(string $key, int $maximum, int $window): int
    {
        $key = hash('sha256', $key);
        $now = time();
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO chat_request_limits (bucket_key, started_at, hits) VALUES (?, ?, 0)
                ON DUPLICATE KEY UPDATE bucket_key = VALUES(bucket_key)')->execute([$key, $now]);
            $stmt = $this->db->prepare('SELECT started_at, hits FROM chat_request_limits WHERE bucket_key = ? FOR UPDATE');
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $expired = $now - (int) $row['started_at'] >= $window;
            if (!$expired && (int) $row['hits'] >= $maximum) {
                $this->db->rollBack();
                return max(1, $window - ($now - (int) $row['started_at']));
            }
            $this->db->prepare('UPDATE chat_request_limits SET started_at = ?, hits = ? WHERE bucket_key = ?')
                ->execute([$expired ? $now : $row['started_at'], $expired ? 1 : (int) $row['hits'] + 1, $key]);
            $this->db->commit();
            return 0;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
