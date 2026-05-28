<?php
if (class_exists('GameLog', false)) {
    if (!isset($showLimitAlert)) {
        GameLog::warn('component/limit_alert', 'Brak zmiennej $showLimitAlert');
    }
}
?>
<?php if ($showLimitAlert ?? false): ?>
    <div class="limit-alert">
        <?= $limitMessage ?>
    </div>
<?php endif ?>