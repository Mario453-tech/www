<?php
/**
 * TTS/NotificationsTrait.php
 * Technical notifications for the player.
 * Powiadomienia techniczne dla gracza.
 */
trait TTSNotificationsTrait
{
    // Notifications.
    // Powiadomienia.

    public function countUnreadNotifications(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM technical_notifications WHERE player_id = ? AND is_read = 0");
        $stmt->execute([$this->playerId]);
        return (int) $stmt->fetchColumn();
    }

    public function getUnreadNotifications(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM technical_notifications
            WHERE player_id = ? AND is_read = 0
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $this->playerId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markRead(int $notifId): void
    {
        $this->db->prepare("UPDATE technical_notifications SET is_read = 1 WHERE id = ? AND player_id = ?")
            ->execute([$notifId, $this->playerId]);
    }

    public function notify(string $type, ?int $wellId, string $message): void
    {
        $this->db->prepare("INSERT INTO technical_notifications (player_id, well_id, type, message) VALUES (?,?,?,?)")
            ->execute([$this->playerId, $wellId, $type, $message]);
    }
}
