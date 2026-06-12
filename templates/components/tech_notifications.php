<?php
/**
 * Komponent: Powiadomienia techniczne (awarie i incydenty odwiertow) — panel na dashboardzie.
 * Component: Technical notifications (well failures and incidents) — dashboard panel.
 *
 * Oczekuje / Expects:
 *   $techNotifications (array) — wiersze z tabeli technical_notifications (is_read=0, type != 'task')
 * Powrot wczesny gdy brak powiadomien / Early return when no notifications.
 * Odczytywanie przez /src/TechNotifApi.php (action=mark_read|mark_all_read).
 * Dismissed via /src/TechNotifApi.php (action=mark_read|mark_all_read).
 */

if (empty($techNotifications)) {
    return;
}

// Etykiety typow powiadomien / Notification type labels
$__typeLabels = [
    'failure'                          => 'Awaria',
    'pipeline'                         => 'Rurociąg',
    'pressure'                         => 'Ciśnienie',
    'production'                       => 'Produkcja',
    'drilling'                         => 'Wiercenie',
    'maintenance'                      => 'Serwis',
    'hse_warning'                      => 'Ostrzeżenie BHP',
    'hse_critical'                     => 'Krytyczny BHP',
    'disaster_blowout'                 => 'Katastrofa: blowout',
    'disaster_pipeline_explosion'      => 'Katastrofa: rurociąg',
    'disaster_reservoir_contamination' => 'Katastrofa: skażenie',
    'disaster_surface_spill'           => 'Katastrofa: wyciek',
];

// Klasa CSS wg krytycznosci / CSS class by severity
$__severityClass = function (string $type): string {
    if (str_starts_with($type, 'disaster_')) return 'tech-notif--disaster';
    if ($type === 'hse_critical')             return 'tech-notif--critical';
    if ($type === 'hse_warning')              return 'tech-notif--warning';
    return 'tech-notif--default';
};

$__count = count($techNotifications);
?>
<section class="card tech-notif-panel" id="tech-notif-panel" aria-labelledby="tech-notif-heading">
    <h2 id="tech-notif-heading">
        Alerty techniczne
        <span class="tech-notif-count"><?= $__count ?></span>
        <?php if ($__count > 1): ?>
        <button class="btn-mark-all-read tech-notif-mark-all"
                onclick="techNotifMarkAll()">
            Odczytaj wszystkie
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
            <span class="tech-notif-well">Odwiert #<?= $__wellId ?></span>
            <?php endif ?>
            <span class="tech-notif-time">
                <?php
                try {
                    $__dt   = new DateTime($__time);
                    $__diff = (new DateTime())->diff($__dt);
                    if ($__diff->days > 0)  echo $__diff->days . ' dni temu';
                    elseif ($__diff->h > 0) echo $__diff->h . ' godz. temu';
                    elseif ($__diff->i > 0) echo $__diff->i . ' min temu';
                    else                    echo 'przed chwilą';
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
                Odczytaj
            </button>
        </div>
    </div>
    <?php endforeach ?>
    </div>
</section>

<script>
(function () {
    var csrfToken = <?= json_encode(CSRF::generateToken(), JSON_UNESCAPED_UNICODE) ?>;

    // Oznacza jedno powiadomienie jako przeczytane i usuwa z widoku.
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

    // Oznacza wszystkie powiadomienia jako przeczytane i usuwa caly panel.
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

    // Ukrywa panel gdy lista jest pusta / Hides the panel when the list is empty.
    function checkTechNotifEmpty() {
        var list = document.getElementById('tech-notif-list');
        if (list && !list.querySelector('.tech-notif-item')) {
            var panel = document.getElementById('tech-notif-panel');
            if (panel) panel.remove();
        }
    }
}());
</script>
