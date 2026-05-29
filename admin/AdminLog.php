<?php

class AdminLog
{
 /**
 * Save admin action to admin_logs table.
 */
    public static function log(
        string $action,
        string $description = '',
        ?int $targetPlayerId = null,
        string $targetType = 'player',
        ?int $targetId = null
    ): void {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO admin_logs
                    (action, description, target_player_id, target_type, target_id, admin_user, admin_ip, created_at)
                VALUES
                    (:action, :desc, :player_id, :ttype, :tid, :admin, :ip, NOW())
            ");

            $stmt->execute([
                ':action' => $action,
                ':desc' => $description,
                ':player_id' => $targetPlayerId,
                ':ttype' => $targetType,
                ':tid' => $targetId,
                ':admin' => $_SESSION['admin_user'] ?? 'admin',
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('AdminLog', 'Log insert failed', $e, [
                    'action'           => $action,
                    'target_player_id' => $targetPlayerId,
                    'target_type'      => $targetType,
                    'target_id'        => $targetId,
                ]);
            }
            error_log('[AdminLog] ' . $e->getMessage());
        }
    }
}
