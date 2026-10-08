<?php
declare(strict_types=1);

final class ChatLimiterMigrationService
{
    public function __construct(private PDO $db) {}

    public function apply(): void
    {
        // MySQL DDL commits implicitly; only run from an explicit migration action.
        // DDL MySQL zatwierdza transakcje; uruchamiaj tylko jawna akcja migracji.
        if ($this->db->inTransaction()) {
            throw new LogicException('Chat limiter migration cannot run inside a transaction.');
        }
        $this->db->exec('CREATE TABLE IF NOT EXISTS chat_request_limits (
            bucket_key CHAR(64) NOT NULL PRIMARY KEY,
            started_at BIGINT NOT NULL,
            hits INT NOT NULL,
            INDEX idx_chat_request_limits_started_at (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->db->query('SELECT bucket_key, started_at, hits FROM chat_request_limits LIMIT 0');
        $primary = $this->db->query("SHOW INDEX FROM chat_request_limits WHERE Key_name = 'PRIMARY'")
            ->fetchAll(PDO::FETCH_ASSOC);
        if (count($primary) !== 1 || $primary[0]['Column_name'] !== 'bucket_key') {
            throw new RuntimeException('Chat limiter schema has an incompatible primary key.');
        }
    }
}
