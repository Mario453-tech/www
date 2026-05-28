<?php
/**
 * Komponent: Komunikaty dla dyrektora
 * Wyswietla nieprzeczytane powiadomienia w dashboardzie
 */

if (class_exists('GameLog', false)) {
    GameLog::step('component/director_notifications', 'render', 1,
        'count=' . count($notifications ?? []));
}

if (!isset($notifications) || empty($notifications)) {
    if (class_exists('GameLog', false)) {
        GameLog::info('component/director_notifications', 'No notifications — component hidden');
    }
    return;
}

try {
    $criticalCount = count(array_filter($notifications, fn($n) => $n['priority'] === 'critical'));
    $highCount     = count(array_filter($notifications, fn($n) => $n['priority'] === 'high'));

    if (class_exists('GameLog', false)) {
        GameLog::info('component/director_notifications', 'Rendering', [
            'total'    => count($notifications),
            'critical' => $criticalCount,
            'high'     => $highCount,
        ]);
    }
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('component/director_notifications', 'Error filtering notifications', $e);
    }
    return;
}
?>

<section class="notifications-panel" id="director-notifications">
    <div class="notifications-header">
        <h2>
            <span class="notifications-icon"></span>
            <?= t('director.panel_title') ?>
            <span class="notifications-count"><?= count($notifications) ?></span>
        </h2>
        <?php if (count($notifications) > 1): ?>
            <button class="btn-mark-all-read" onclick="markAllNotificationsRead()">
                 <?= t('director.btn_mark_all_read') ?>
            </button>
        <?php endif ?>
    </div>

    <?php if ($criticalCount > 0): ?>
        <div class="notifications-alert critical">
            <strong> <?= t('director.alert_warning') ?>:</strong> <?= t('director.alert_critical', ['count' => $criticalCount]) ?>
        </div>
    <?php endif ?>

    <div class="notifications-list">
        <?php foreach ($notifications as $notification): ?>
            <div class="notification-item priority-<?= htmlspecialchars($notification['priority']) ?> type-<?= htmlspecialchars($notification['type']) ?>" 
                 data-notification-id="<?= $notification['id'] ?>">
                
                <div class="notification-icon">
                    <?= $notification['icon'] ?>
                </div>

                <div class="notification-content">
                    <div class="notification-header-row">
                        <h3 class="notification-title">
                            <?= htmlspecialchars($notification['title']) ?>
                        </h3>
                        <span class="notification-priority-badge">
                            <?php
                            $priorityLabels = [
                                'critical' => ' ' . t('director.priority_critical'),
                                'high'     => ' ' . t('director.priority_high'),
                                'medium'   => ' ' . t('director.priority_medium'),
                                'low'      => ' ' . t('director.priority_low'),
                            ];
                            echo $priorityLabels[$notification['priority']] ?? '';
                            ?>
                        </span>
                    </div>

                    <p class="notification-message">
                        <?= nl2br(htmlspecialchars($notification['message'])) ?>
                    </p>

                    <div class="notification-meta">
                        <span class="notification-time">
                            <?php
                            $time = new DateTime($notification['created_at']);
                            $now = new DateTime();
                            $diff = $now->diff($time);
                            
                            if ($diff->days > 0) {
                                echo t('director.time_days_ago', ['n' => $diff->days]);
                            } elseif ($diff->h > 0) {
                                echo t('director.time_hours_ago', ['n' => $diff->h]);
                            } elseif ($diff->i > 0) {
                                echo t('director.time_minutes_ago', ['n' => $diff->i]);
                            } else {
                                echo t('director.time_just_now');
                            }
                            ?>
                        </span>
                        
                        <?php if ($notification['expires_at']): ?>
                            <span class="notification-expires">
                                <?= t('director.expires') ?>: <?= date('Y-m-d H:i', strtotime($notification['expires_at'])) ?>
                            </span>
                        <?php endif ?>
                    </div>

                    <div class="notification-actions">
                        <?php if ($notification['requires_action'] && $notification['action_url']): ?>
                            <a href="<?= htmlspecialchars($notification['action_url']) ?>" 
                               class="btn btn-primary btn-sm notification-action-btn">
                                <?= htmlspecialchars($notification['action_label'] ?? t('director.btn_go')) ?>
                            </a>
                        <?php endif ?>
                        
                        <button class="btn btn-secondary btn-sm" 
                                onclick="markNotificationRead(<?= $notification['id'] ?>)">
                             <?= t('director.btn_mark_read') ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</section>

<script>
const CSRF_TOKEN = '<?= CSRF::generateToken() ?>';
</script>
<script src="/assets/js/director_notifications.js"></script>