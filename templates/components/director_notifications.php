<?php
/**
 * Komponent: Komunikaty dla dyrektora
 * Wyswietla nieprzeczytane powiadomienia w dashboardzie
 *
 * Renderuje ikony SVG zamiast emoji (emoji rozwalaja kodowanie).
 * Renders SVG icons instead of emoji (emoji breaks encoding).
 */

/**
 * Zwraca inline SVG dla podanego identyfikatora ikony.
 * Returns inline SVG for the given icon identifier.
 */
function dirNotifIconSvg(string $icon): string
{
    // Parametry wspolne SVG / Common SVG attributes
    $s = "xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'";
    switch ($icon) {
        case 'bank':
            return "<svg $s><line x1='3' y1='22' x2='21' y2='22'/><rect x='5' y='11' width='2' height='9'/><rect x='11' y='11' width='2' height='9'/><rect x='17' y='11' width='2' height='9'/><path d='M12 2L2 9h20z'/></svg>";
        case 'warning':
            return "<svg $s><path d='M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg>";
        case 'briefcase':
            return "<svg $s><rect x='2' y='7' width='20' height='14' rx='2'/><path d='M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2'/><line x1='8' y1='12' x2='16' y2='12'/></svg>";
        case 'check':
            return "<svg $s><path d='M22 11.08V12a10 10 0 1 1-5.93-9.14'/><polyline points='22 4 12 14.01 9 11.01'/></svg>";
        case 'cross':
            return "<svg $s><circle cx='12' cy='12' r='10'/><line x1='15' y1='9' x2='9' y2='15'/><line x1='9' y1='9' x2='15' y2='15'/></svg>";
        case 'people':
            return "<svg $s><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>";
        case 'document':
            return "<svg $s><path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/><polyline points='14 2 14 8 20 8'/><line x1='16' y1='13' x2='8' y2='13'/><line x1='16' y1='17' x2='8' y2='17'/></svg>";
        case 'alert':
            return "<svg $s><circle cx='12' cy='12' r='10'/><line x1='12' y1='8' x2='12' y2='12'/><line x1='12' y1='16' x2='12.01' y2='16'/></svg>";
        case 'gear':
            return "<svg $s><circle cx='12' cy='12' r='3'/><path d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'/></svg>";
        case 'arrow-down':
            return "<svg $s><polyline points='22 7 13.5 15.5 8.5 10.5 2 17'/><polyline points='16 17 22 17 22 11'/></svg>";
        case 'arrow-up':
            return "<svg $s><polyline points='22 17 13.5 8.5 8.5 13.5 2 7'/><polyline points='16 7 22 7 22 13'/></svg>";
        case 'globe':
            return "<svg $s><circle cx='12' cy='12' r='10'/><line x1='2' y1='12' x2='22' y2='12'/><path d='M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z'/></svg>";
        case 'barrel':
            return "<svg $s><ellipse cx='12' cy='5' rx='9' ry='3'/><path d='M3 5v14a9 3 0 0 0 18 0V5'/><path d='M3 12a9 3 0 0 0 18 0'/></svg>";
        case 'box':
            return "<svg $s><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'/><polyline points='3.27 6.96 12 12.01 20.73 6.96'/><line x1='12' y1='22.08' x2='12' y2='12'/></svg>";
        case 'scales':
            return "<svg $s><line x1='12' y1='3' x2='12' y2='21'/><polyline points='8 8 4 14 8 14'/><line x1='4' y1='14' x2='12' y2='14'/><polyline points='16 8 20 14 16 14'/><line x1='12' y1='14' x2='20' y2='14'/><path d='M5 21h14'/></svg>";
        case 'siren':
            return "<svg $s><path d='M5.3 15H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2.3'/><path d='M18.7 15H21a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2.3'/><path d='M12 4v11'/><path d='M8 21h8'/><path d='M12 15v6'/><circle cx='12' cy='3' r='1'/></svg>";
        case 'star':
            return "<svg $s><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>";
        case 'chart':
            return "<svg $s><line x1='18' y1='20' x2='18' y2='10'/><line x1='12' y1='20' x2='12' y2='4'/><line x1='6' y1='20' x2='6' y2='14'/></svg>";
        case 'send':
            return "<svg $s><line x1='22' y1='2' x2='11' y2='13'/><polygon points='22 2 15 22 11 13 2 9 22 2'/></svg>";
        case 'receive':
            return "<svg $s><polyline points='8 17 12 21 16 17'/><line x1='12' y1='12' x2='12' y2='21'/><path d='M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29'/></svg>";
        case 'admin':
            return "<svg $s><path d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'/></svg>";
        default:
            return "<svg $s><circle cx='12' cy='12' r='10'/><line x1='12' y1='8' x2='12' y2='12'/><line x1='12' y1='16' x2='12.01' y2='16'/></svg>";
    }
}

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
                
                <div class="notification-icon" aria-hidden="true">
                    <?= dirNotifIconSvg((string)($notification['icon'] ?? 'alert')) ?>
                </div>

                <div class="notification-content">
                    <div class="notification-header-row">
                        <h3 class="notification-title">
                            <?= htmlspecialchars($notification['title']) ?>
                        </h3>
                        <span class="notification-priority-badge">
                            <?php
                            $priorityLabels = [
                                'critical' => t('director.priority_critical'),
                                'high'     => t('director.priority_high'),
                                'medium'   => t('director.priority_medium'),
                                'low'      => t('director.priority_low'),
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