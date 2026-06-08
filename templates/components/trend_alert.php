<?php
if (class_exists('GameLog', false)) {
    GameLog::step('component/trend_alert', 'render', 1,
        'trend=' . ($activeTrend ? ($activeTrend['id'] ?? '?') : 'none'));
}
?>
<?php if ($activeTrend ?? false):
    $trendIcons = [
        'crisis' => '', 'war' => '', 'boom' => '',
        'discovery' => '', 'winter' => '', 'opec' => '',
    ];
    $trendIcon = $trendIcons[$trendClass] ?? '';
    $isPriceDrop = ($trendPricePct ?? 0) < 0;
    $pctLabel = ($trendPricePct ?? 0) > 0 ? '+' . $trendPricePct . '%' : $trendPricePct . '%';
 // Format timer
    $remSecs = (int)($eventRemainingSeconds ?? 0);
    $remH = str_pad((string)floor($remSecs / 3600), 2, '0', STR_PAD_LEFT);
    $remM = str_pad((string)floor(($remSecs % 3600) / 60), 2, '0', STR_PAD_LEFT);
?>
<aside class="trend-alert trend-alert--redesign trend-<?= htmlspecialchars($trendClass) ?>" role="alert" aria-live="polite">
        <div class="trend-alert__icon-wrap">
        <span class="trend-alert__icon"><?= $trendIcon ?></span>
    </div>
    <div class="trend-alert__content">
        <!-- Header row: event label left, price + date badges right / Wiersz nagłówka: etykieta po lewej, odznaki po prawej -->
        <div class="trend-alert__header-row">
            <span class="trend-tag trend-tag--event"><?= t('trend_alert.active_event') ?></span>
            <div class="trend-alert__event-badges">
                <?php if ($trendPricePct ?? 0): ?>
                <span class="trend-tag trend-tag--price <?= $isPriceDrop ? 'trend-tag--neg' : 'trend-tag--pos' ?>"><?= htmlspecialchars($pctLabel) ?> <?= t('trend_alert.price_tag_suffix') ?></span>
                <?php endif ?>
                <span class="trend-tag trend-tag--date"><?= htmlspecialchars(date('d.m.Y', strtotime($activeTrend['activated_at']))) ?></span>
            </div>
        </div>
        <div class="trend-alert__message"><?= htmlspecialchars($trendMessage) ?></div>
        <?php if ($eventImpactPerHour ?? 0): ?>
        <div class="trend-alert__impact <?= ($eventImpactPerHour < 0) ? 'trend-impact--neg' : 'trend-impact--pos' ?>">
            <?= t('trend_alert.impact_label') ?>
            ~<?= ($eventImpactPerHour < 0 ? '' : '+') . number_format(abs($eventImpactPerHour), 0, ',', ' ') ?> $/h
        </div>
        <?php endif ?>
    </div>
    <div class="trend-alert__timer-wrap">
        <span class="trend-pulse-dot" aria-hidden="true"></span>
        <div class="trend-alert__timer-label"><?= t('trend_alert.remaining_label') ?></div>
        <div class="trend-alert__timer" id="trend-timer" data-seconds="<?= (int)($eventRemainingSeconds ?? 0) ?>"><?= $remH ?>:<?= $remM ?></div>
        <div class="trend-alert__timer-sub"><?= t('trend_alert.timer_sub') ?> <?= htmlspecialchars($remainingTime) ?></div>
    </div>
</aside>
<?php endif ?>
