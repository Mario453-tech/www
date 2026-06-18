<?php
if (class_exists('GameLog', false)) {
    if (!isset($statusItems) || !is_array($statusItems)) {
        GameLog::warn('component/status_grid', 'Brak lub nieprawidlowa zmienna $statusItems');
    }
}
?>
<div class="status-grid status-grid--redesign">
<?php foreach ($statusItems as $item):
    $hasPulse = !empty($item['pulse']);
    $hasPct   = isset($item['pct']);
    $hasSub   = !empty($item['sub']);
    $bodyClass = 'status-kpi-body' . (!$hasSub && !$hasPct ? ' status-kpi-body--compact' : '');
    $iconHtml = $item['icon_html'] ?? null;
?>
    <div class="status-kpi">
        <div class="status-kpi-icon" style="background:<?= htmlspecialchars($item['icon_color'] ?? '#c8860a') ?>22; border-color:<?= htmlspecialchars($item['icon_color'] ?? '#c8860a') ?>44">
            <span><?= $iconHtml !== null ? $iconHtml : ($item['icon'] ?? '') ?></span>
        </div>
        <div class="<?= $bodyClass ?>">
            <div class="status-kpi-label"><?= $item['label'] ?></div>
            <div class="status-kpi-value <?= $item['class'] ?? '' ?>">
                <?php if ($hasPulse): ?><span class="status-pulse"></span><?php endif ?>
                <?= $item['value'] ?>
            </div>
            <?php if (!empty($item['sub'])): ?>
            <div class="status-kpi-sub"><?= htmlspecialchars($item['sub']) ?></div>
            <?php endif ?>
            <?php if ($hasPct): ?>
            <div class="status-kpi-bar">
                <div class="status-kpi-bar-fill" style="width:<?= (float)$item['pct'] ?>%"></div>
            </div>
            <?php endif ?>
        </div>
    </div>
<?php endforeach ?>
</div>
