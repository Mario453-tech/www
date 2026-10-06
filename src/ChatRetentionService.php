<?php
declare(strict_types=1);

final class ChatRetentionService
{
    public function __construct(private PDO $db) {}

    public function cleanup(int $limit = 1000): int
    {
        $cutoff = time() - 86400;
        $this->db->prepare('DELETE FROM chat_request_limits WHERE started_at < ? LIMIT 1000')->execute([$cutoff]);
        $config = $this->db->query("SELECT `key`, `value` FROM well_config
            WHERE `key` IN ('chat_auto_clear_enabled', 'chat_auto_clear_interval')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if ((int) ($config['chat_auto_clear_enabled'] ?? 0) !== 1) return 0;
        $minutes = max(15, min(120, (int) ($config['chat_auto_clear_interval'] ?? 30)));
        $limit = max(1, min(1000, $limit));
        // Preserve private conversations and monotonic IDs. / Zachowaj rozmowy prywatne i rosnace ID.
        $count = $this->db->exec("UPDATE chat_messages SET is_deleted = 1
            WHERE channel IN ('global','room') AND is_deleted = 0 AND COALESCE(is_pinned, 0) = 0
                AND created_at < DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE) ORDER BY id LIMIT {$limit}");
        $this->db->exec("INSERT INTO well_config (`key`, `value`) VALUES ('chat_auto_clear_last_at', NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        return (int) $count;
    }
}
