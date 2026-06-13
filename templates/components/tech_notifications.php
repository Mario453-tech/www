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
$panelTitle = $locale === 'en' ? 'Technical alerts' : 'Alerty techniczne';
$markAllLabel = $locale === 'en' ? 'Mark all as read' : 'Odczytaj wszystkie';
$wellLabel = $locale === 'en' ? 'Well #' : 'Odwiert #';
$markReadLabel = $locale === 'en' ? 'Mark as read' : 'Odczytaj';

// Notification type labels.
$__typeLabels = [
    'failure'                          => $locale === 'en' ? 'Failure' : 'Awaria',
    'pipeline'                         => $locale === 'en' ? 'Pipeline' : 'Rurociag',
    'pressure'                         => $locale === 'en' ? 'Pressure' : 'Cisnienie',
    'production'                       => $locale === 'en' ? 'Production' : 'Produkcja',
    'drilling'                         => $locale === 'en' ? 'Drilling' : 'Wiercenie',
    'maintenance'                      => $locale === 'en' ? 'Service' : 'Serwis',
    'hse_warning'                      => $locale === 'en' ? 'HSE warning' : 'Ostrzezenie BHP',
    'hse_critical'                     => $locale === 'en' ? 'Critical HSE' : 'Krytyczny BHP',
    'disaster_blowout'                 => $locale === 'en' ? 'Disaster: blowout' : 'Katastrofa: blowout',
    'disaster_pipeline_explosion'      => $locale === 'en' ? 'Disaster: pipeline' : 'Katastrofa: rurociag',
    'disaster_reservoir_contamination' => $locale === 'en' ? 'Disaster: contamination' : 'Katastrofa: skazenie',
    'disaster_surface_spill'           => $locale === 'en' ? 'Disaster: spill' : 'Katastrofa: wyciek',
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
<section class="card tech-notif-panel" id="tech-notif-panel" aria-labelledby="tech-notif-heading">
    <h2 id="tech-notif-heading">
        <?= htmlspecialchars($panelTitle, ENT_QUOTES, 'UTF-8') ?>
        <span class="tech-notif-count"><?= $__count ?></span>
        <?php if ($__count > 1): ?>
        <button class="btn-mark-all-read tech-notif-mark-all"
                onclick="techNotifMarkAll()">
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
                    if ($locale === 'en') {
                        if ($__diff->days > 0) {
                            echo $__diff->days . ' d ago';
                        } elseif ($__diff->h > 0) {
                            echo $__diff->h . ' h ago';
                        } elseif ($__diff->i > 0) {
                            echo $__diff->i . ' min ago';
                        } else {
                            echo 'just now';
                        }
                    } else {
                        if ($__diff->days > 0) {
                            echo $__diff->days . ' dni temu';
                        } elseif ($__diff->h > 0) {
                            echo $__diff->h . ' godz. temu';
                        } elseif ($__diff->i > 0) {
                            echo $__diff->i . ' min temu';
                        } else {
                            echo 'przed chwila';
                        }
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
                    onclick="techNotifMarkRead(<?= $__id ?>, this)">
                <?= htmlspecialchars($markReadLabel, ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
    <?php endforeach ?>
    </div>
</section>

<script>
(function () {
    var csrfToken = <?= json_encode(CSRF::generateToken(), JSON_UNESCAPED_UNICODE) ?>;

    // Marks one notification as read and removes it from the view.
    window.techNotifMarkRead = function (id, btn) {
        fetch('/src/TechNotifApi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_read&notif_id=' + encodeURIComponent(id) + '&_token=' + encodeURIComponent(csrfToken)
        }).then(function () {
            var item = btn ? btn.closest('.tech-notif-item')
                           : document.querySelector('[data-notif-id="' + id + '"]');
            if (item) item.remove();
            checkTechNotifEmpty();
        });
    };

    // Marks all notifications as read and removes the entire panel.
    window.techNotifMarkAll = function () {
        fetch('/src/TechNotifApi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_all_read&_token=' + encodeURIComponent(csrfToken)
        }).then(function () {
            var panel = document.getElementById('tech-notif-panel');
            if (panel) panel.remove();
        });
    };

    // Hides the panel when the list is empty.
    function checkTechNotifEmpty() {
        var list = document.getElementById('tech-notif-list');
        if (list && !list.querySelector('.tech-notif-item')) {
            var panel = document.getElementById('tech-notif-panel');
            if (panel) panel.remove();
        }
    }
}());
</script>
