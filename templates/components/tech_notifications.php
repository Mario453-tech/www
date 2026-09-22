<?php
/**
 * Component: Technical notifications dashboard panel.
 *
 * Expects:
 *   $techNotifications (array) - rows from technical_notifications (is_read=0, type != 'task')
 */

if (empty($techNotifications)) {
    return;
}

$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$panelTitle = tPlain('director.tech_technical_alerts');
$markAllLabel = tPlain('director.tech_mark_all_as_read');
$wellLabel = tPlain('director.tech_well');
$markReadLabel = tPlain('director.tech_mark_as_read');

// Notification type labels.
$__typeLabels = [
    'failure'                          => tPlain('director.tech_failure'),
    'pipeline'                         => tPlain('director.tech_pipeline'),
    'pressure'                         => tPlain('director.tech_pressure'),
    'production'                       => tPlain('director.tech_production'),
    'drilling'                         => tPlain('director.tech_drilling'),
    'maintenance'                      => tPlain('director.tech_service'),
    'hse_warning'                      => tPlain('director.tech_hse_warning'),
    'hse_critical'                     => tPlain('director.tech_critical_hse'),
    'disaster_blowout'                 => tPlain('director.tech_disaster_blowout'),
    'disaster_pipeline_explosion'      => tPlain('director.tech_disaster_pipeline'),
    'disaster_reservoir_contamination' => tPlain('director.tech_disaster_contamination'),
    'disaster_surface_spill'           => tPlain('director.tech_disaster_spill'),
];

// CSS class by severity.
$__severityClass = function (string $type): string {
    if (str_starts_with($type, 'disaster_')) {
        return 'tech-notif--disaster';
    }
    if ($type === 'hse_critical') {
        return 'tech-notif--critical';
    }
    if ($type === 'hse_warning') {
        return 'tech-notif--warning';
    }
    return 'tech-notif--default';
};

$__count = count($techNotifications);
?>
<section class="card tech-notif-panel" id="tech-notif-panel" aria-labelledby="tech-notif-heading"
         data-csrf="<?= htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8') ?>"
         data-error="<?= t('director.notification_error') ?>">
    <p class="tech-notif-error" role="alert" hidden></p>
    <h2 id="tech-notif-heading">
        <?= htmlspecialchars($panelTitle, ENT_QUOTES, 'UTF-8') ?>
        <span class="tech-notif-count"><?= $__count ?></span>
        <?php if ($__count > 1): ?>
        <button class="btn-mark-all-read tech-notif-mark-all"
                data-tech-action="mark_all_read" data-confirm="<?= t('director.confirm_mark_all') ?>">
            <?= htmlspecialchars($markAllLabel, ENT_QUOTES, 'UTF-8') ?>
        </button>
        <?php endif ?>
    </h2>

    <div class="tech-notif-list" id="tech-notif-list">
    <?php foreach ($techNotifications as $__n):
        $__type   = (string)($__n['type'] ?? '');
        $__label  = $__typeLabels[$__type] ?? ucfirst(str_replace('_', ' ', $__type));
        $__cls    = $__severityClass($__type);
        $__time   = $__n['created_at'] ?? '';
        $__id     = (int)$__n['id'];
        $__wellId = (int)($__n['well_id'] ?? 0);
    ?>
    <div class="tech-notif-item <?= $__cls ?>" data-notif-id="<?= $__id ?>">
        <div class="tech-notif-item__header">
            <span class="tech-notif-type-badge"><?= htmlspecialchars($__label) ?></span>
            <?php if ($__wellId > 0): ?>
            <span class="tech-notif-well"><?= htmlspecialchars($wellLabel, ENT_QUOTES, 'UTF-8') ?><?= $__wellId ?></span>
            <?php endif ?>
            <span class="tech-notif-time">
                <?php
                try {
                    $__dt   = new DateTime($__time);
                    $__diff = (new DateTime())->diff($__dt);
                    if ($__diff->days > 0) {
                        echo t('director.time_days_ago', ['n' => $__diff->days]);
                    } elseif ($__diff->h > 0) {
                        echo t('director.time_hours_ago', ['n' => $__diff->h]);
                    } elseif ($__diff->i > 0) {
                        echo t('director.time_minutes_ago', ['n' => $__diff->i]);
                    } else {
                        echo t('director.time_just_now');
                    }
                } catch (Throwable $__ex) {
                    echo htmlspecialchars($__time);
                }
                ?>
            </span>
        </div>
        <p class="tech-notif-item__msg"><?= nl2br(htmlspecialchars((string)($__n['message'] ?? ''))) ?></p>
        <div class="tech-notif-item__actions">
            <button class="btn btn-sm btn-secondary"
                    data-tech-action="mark_read" data-tech-id="<?= $__id ?>">
                <?= htmlspecialchars($markReadLabel, ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
    <?php endforeach ?>
    </div>
</section>

<script src="/assets/js/tech_notifications.js" defer></script>
