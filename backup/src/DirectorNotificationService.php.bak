<?php
/**
 * DirectorNotificationService - System komunikatów dla dyrektora | DirectorNotificationService - notification system for director
 * 
 * Zarzadza powiadomieniami wyswietlanymi w dashboardzie | Manages notifications displayed in the dashboard
 * Komunikaty wymagają potwierdzenia przeczytania | Messages require read acknowledgment
 */
class DirectorNotificationService
{
    private PDO $db;
    
    // Szablony komunikatow (20 typow)
    private const TEMPLATES = [
        'bank_payment_due' => [
            'type' => 'bank',
            'priority' => 'high',
            'icon' => '🏦',
            'title_key' => 'director.bank_payment_due.title',
            'message_key' => 'director.bank_payment_due.message',
            'requires_action' => true,
            'action_url' => 'bank.php',
            'action_label_key' => 'director.bank_payment_due.action'
        ],
        'bank_overdue' => [
            'type' => 'bank',
            'priority' => 'critical',
            'icon' => '⚠️',
            'title_key' => 'director.bank_overdue.title',
            'message_key' => 'director.bank_overdue.message',
            'requires_action' => true,
            'action_url' => 'bank.php',
            'action_label_key' => 'director.bank_overdue.action'
        ],
        'bank_restructure_offer' => [
            'type' => 'bank',
            'priority' => 'high',
            'icon' => '💼',
            'title_key' => 'director.bank_restructure_offer.title',
            'message_key' => 'director.bank_restructure_offer.message',
            'requires_action' => true,
            'action_url' => 'bank.php?tab=negotiations',
            'action_label_key' => 'director.bank_restructure_offer.action'
        ],
        'bank_negotiation_approved' => [
            'type' => 'bank',
            'priority' => 'medium',
            'icon' => '✅',
            'title_key' => 'director.bank_negotiation_approved.title',
            'message_key' => 'director.bank_negotiation_approved.message',
            'requires_action' => false
        ],
        'bank_negotiation_rejected' => [
            'type' => 'bank',
            'priority' => 'medium',
            'icon' => '❌',
            'title_key' => 'director.bank_negotiation_rejected.title',
            'message_key' => 'director.bank_negotiation_rejected.message',
            'requires_action' => false
        ],
        'hr_new_candidates' => [
            'type' => 'hr',
            'priority' => 'high',
            'icon' => '👥',
            'title_key' => 'director.hr_new_candidates.title',
            'message_key' => 'director.hr_new_candidates.message',
            'requires_action' => true,
            'action_url' => 'dashboard.php',
            'action_label_key' => 'director.hr_new_candidates.action'
        ],
        'hr_contract_expiring' => [
            'type' => 'hr',
            'priority' => 'medium',
            'icon' => '📋',
            'title_key' => 'director.hr_contract_expiring.title',
            'message_key' => 'director.hr_contract_expiring.message',
            'requires_action' => true,
            'action_url' => 'hr.php',
            'action_label_key' => 'director.hr_contract_expiring.action'
        ],
        'technical_well_failure' => [
            'type' => 'technical',
            'priority' => 'critical',
            'icon' => '🔴',
            'title_key' => 'director.technical_well_failure.title',
            'message_key' => 'director.technical_well_failure.message',
            'requires_action' => true,
            'action_url' => 'technical.php',
            'action_label_key' => 'director.technical_well_failure.action'
        ],
        'technical_low_condition' => [
            'type' => 'technical',
            'priority' => 'high',
            'icon' => '⚙️',
            'title_key' => 'director.technical_low_condition.title',
            'message_key' => 'director.technical_low_condition.message',
            'requires_action' => true,
            'action_url' => 'technical.php',
            'action_label_key' => 'director.technical_low_condition.action'
        ],
        'technical_task_completed' => [
            'type' => 'technical',
            'priority' => 'low',
            'icon' => '✅',
            'title_key' => 'director.technical_task_completed.title',
            'message_key' => 'director.technical_task_completed.message',
            'requires_action' => false
        ],
        'market_price_drop' => [
            'type' => 'market',
            'priority' => 'high',
            'icon' => '📉',
            'title_key' => 'director.market_price_drop.title',
            'message_key' => 'director.market_price_drop.message',
            'requires_action' => true,
            'action_url' => 'market.php',
            'action_label_key' => 'director.market_price_drop.action'
        ],
        'market_price_surge' => [
            'type' => 'market',
            'priority' => 'high',
            'icon' => '📈',
            'title_key' => 'director.market_price_surge.title',
            'message_key' => 'director.market_price_surge.message',
            'requires_action' => true,
            'action_url' => 'market.php',
            'action_label_key' => 'director.market_price_surge.action'
        ],
        'market_new_trend' => [
            'type' => 'market',
            'priority' => 'medium',
            'icon' => '🌍',
            'title_key' => 'director.market_new_trend.title',
            'message_key' => 'director.market_new_trend.message',
            'requires_action' => false
        ],
        'storage_full' => [
            'type' => 'urgent',
            'priority' => 'critical',
            'icon' => '🛢️',
            'title_key' => 'director.storage_full.title',
            'message_key' => 'director.storage_full.message',
            'requires_action' => true,
            'action_url' => 'market.php',
            'action_label_key' => 'director.storage_full.action'
        ],
        'storage_empty' => [
            'type' => 'info',
            'priority' => 'low',
            'icon' => '📦',
            'title_key' => 'director.storage_empty.title',
            'message_key' => 'director.storage_empty.message',
            'requires_action' => false
        ],
        'legal_bailiff_started' => [
            'type' => 'legal',
            'priority' => 'critical',
            'icon' => '⚖️',
            'title_key' => 'director.legal_bailiff_started.title',
            'message_key' => 'director.legal_bailiff_started.message',
            'requires_action' => true,
            'action_url' => 'bank.php',
            'action_label_key' => 'director.legal_bailiff_started.action'
        ],
        'legal_asset_seized' => [
            'type' => 'legal',
            'priority' => 'critical',
            'icon' => '🚨',
            'title_key' => 'director.legal_asset_seized.title',
            'message_key' => 'director.legal_asset_seized.message',
            'requires_action' => true,
            'action_url' => 'bank.php',
            'action_label_key' => 'director.legal_asset_seized.action'
        ],
        'urgent_bankruptcy_risk' => [
            'type' => 'urgent',
            'priority' => 'critical',
            'icon' => '💀',
            'title_key' => 'director.urgent_bankruptcy_risk.title',
            'message_key' => 'director.urgent_bankruptcy_risk.message',
            'requires_action' => true,
            'action_url' => 'bank.php',
            'action_label_key' => 'director.urgent_bankruptcy_risk.action'
        ],
        'info_new_feature' => [
            'type' => 'info',
            'priority' => 'low',
            'icon' => '🎉',
            'title_key' => 'director.info_new_feature.title',
            'message_key' => 'director.info_new_feature.message',
            'requires_action' => false
        ],
        'info_monthly_report' => [
            'type' => 'info',
            'priority' => 'low',
            'icon' => '📊',
            'title_key' => 'director.info_monthly_report.title',
            'message_key' => 'director.info_monthly_report.message',
            'requires_action' => false
        ]
    ];

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            GameLog::info('DirectorNotificationService', 'Service initialized');
        } catch (Throwable $e) {
            GameLog::error('DirectorNotificationService', 'Initialization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Tworzy nowy komunikat dla dyrektora
     */
    public function create(int $playerId, string $templateKey, array $params = [], ?int $expiresInHours = null): int
    {
        if (!isset(self::TEMPLATES[$templateKey])) {
            throw new InvalidArgumentException("Unknown template: {$templateKey}");
        }

        $template = self::TEMPLATES[$templateKey];

        // Przetlumacz title i message przez t(), podstaw parametry
        $title   = t($template['title_key']);
        $message = t($template['message_key']);
        foreach ($params as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        $expiresAt = $expiresInHours 
            ? date('Y-m-d H:i:s', strtotime("+{$expiresInHours} hours"))
            : null;

        $stmt = $this->db->prepare("
            INSERT INTO director_notifications 
                (player_id, type, priority, title, message, icon, requires_action, action_url, action_label, expires_at)
            VALUES 
                (:player_id, :type, :priority, :title, :message, :icon, :requires_action, :action_url, :action_label, :expires_at)
        ");

        $stmt->execute([
            ':player_id' => $playerId,
            ':type' => $template['type'],
            ':priority' => $template['priority'],
            ':title' => $title,
            ':message' => $message,
            ':icon' => $template['icon'],
            ':requires_action' => $template['requires_action'] ? 1 : 0,
            ':action_url' => $template['action_url'] ?? null,
            ':action_label' => isset($template['action_label_key']) ? t($template['action_label_key']) : null,
            ':expires_at' => $expiresAt
        ]);

        $notificationId = (int)$this->db->lastInsertId();

        // Zapisz w historii
        $this->logAction($notificationId, $playerId, 'created');

        GameLog::info('DirectorNotificationService', 'Notification created', [
            'notification_id' => $notificationId,
            'player_id' => $playerId,
            'template' => $templateKey
        ]);

        return $notificationId;
    }

    /**
     * Pobiera nieprzeczytane komunikaty dla gracza
     */
    public function getUnread(int $playerId, ?string $type = null): array
    {
        $sql = "
            SELECT * FROM director_notifications
            WHERE player_id = :player_id 
              AND is_read = FALSE
              AND (expires_at IS NULL OR expires_at > NOW())
        ";

        if ($type) {
            $sql .= " AND type = :type";
        }

        $sql .= " ORDER BY 
            FIELD(priority, 'critical', 'high', 'medium', 'low'),
            created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':player_id', $playerId, PDO::PARAM_INT);
        
        if ($type) {
            $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Oznacza komunikat jako przeczytany
     */
    public function markAsRead(int $notificationId, int $playerId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE director_notifications 
            SET is_read = TRUE, read_at = NOW()
            WHERE id = :id AND player_id = :player_id
        ");

        $result = $stmt->execute([
            ':id' => $notificationId,
            ':player_id' => $playerId
        ]);

        if ($result) {
            $this->logAction($notificationId, $playerId, 'read');
        }

        return $result;
    }

    /**
     * Oznacza wszystkie komunikaty jako przeczytane
     */
    public function markAllAsRead(int $playerId): int
    {
        $stmt = $this->db->prepare("
            UPDATE director_notifications 
            SET is_read = TRUE, read_at = NOW()
            WHERE player_id = :player_id AND is_read = FALSE
        ");

        $stmt->execute([':player_id' => $playerId]);
        return $stmt->rowCount();
    }

    /**
     * Zlicza nieprzeczytane komunikaty
     */
    public function countUnread(int $playerId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM director_notifications
            WHERE player_id = :player_id 
              AND is_read = FALSE
              AND (expires_at IS NULL OR expires_at > NOW())
        ");

        $stmt->execute([':player_id' => $playerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Usuwa wygasłe komunikaty
     */
    public function cleanupExpired(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM director_notifications
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
        ");

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Zapisuje akcję w historii
     */
    private function logAction(int $notificationId, int $playerId, string $action): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notification_history (notification_id, player_id, action)
            VALUES (:notification_id, :player_id, :action)
        ");

        $stmt->execute([
            ':notification_id' => $notificationId,
            ':player_id' => $playerId,
            ':action' => $action
        ]);
    }

    /**
     * Pobiera historię komunikatów (ostatnie 50)
     */
    public function getHistory(int $playerId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM director_notifications
            WHERE player_id = :player_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
