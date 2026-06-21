<?php
/**
 * Zapisuje dzialania admina w module prywatnosci do tabeli privacy_audit_logs.
 */
class PrivacyAuditLogger
{
    public function __construct(private readonly PDO $db) {}

    public function log(
        ?int   $adminId,
        string $action,
        string $entityType,
        ?int   $entityId  = null,
        mixed  $oldData   = null,
        mixed  $newData   = null,
        string $ip        = '',
        string $ua        = ''
    ): void {
        try {
            $this->db->prepare("
                INSERT INTO privacy_audit_logs
                    (admin_id, action, entity_type, entity_id, old_data_json, new_data_json, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $adminId,
                $action,
                $entityType,
                $entityId,
                $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                $ip ?: null,
                $ua ?: null,
            ]);
        } catch (Throwable $e) {
            GameLog::error('Privacy', 'AuditLogger::log FAILED', $e);
        }
    }
}
