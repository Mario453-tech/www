<?php
if (class_exists('GameLog', false)) {
    GameLog::step('component/action_grid', 'render', 1, 'actions=' . count($actions ?? []));
}
?>
<div class="action-grid action-grid--redesign">
    <?php
    $primaryAction    = null;
    $secondaryActions = [];
    foreach ($actions as $action) {
        if (!empty($action['primary'])) {
            $primaryAction = $action;
        } else {
            $secondaryActions[] = $action;
        }
    }
    ?>
    <?php if ($primaryAction): ?>
    <a href="<?= htmlspecialchars($primaryAction['url']) ?>" class="btn action-btn action-btn--primary">
        <?php if (!empty($primaryAction['icon_html'])): ?>
        <span class="action-btn-icon"><?= $primaryAction['icon_html'] ?></span>
        <?php endif ?>
        <?= $primaryAction['label'] ?>
    </a>
    <?php endif ?>
    <?php if (!empty($secondaryActions)): ?>
    <div class="action-btn-row">
        <?php foreach ($secondaryActions as $action): ?>
            <?php if ($action['type'] === 'link'): ?>
            <a href="<?= htmlspecialchars($action['url']) ?>" class="btn action-btn action-btn--secondary <?= $action['class'] ?>">
                <?php if (!empty($action['icon_html'])): ?>
                <span class="action-btn-icon"><?= $action['icon_html'] ?></span>
                <?php endif ?>
                <?= $action['label'] ?>
            </a>
            <?php elseif ($action['type'] === 'form'): ?>
            <form action="<?= $action['url'] ?>" method="post" style="flex:1">
                <?= CSRF::field() ?>
                <?php if (isset($action['hidden'])): foreach ($action['hidden'] as $n => $v): ?>
                <input type="hidden" name="<?= $n ?>" value="<?= $v ?>">
                <?php endforeach; endif ?>
                <button type="submit" class="btn action-btn action-btn--secondary <?= $action['class'] ?>" <?= !empty($action['disabled']) ? 'disabled' : '' ?>>
                    <?php if (!empty($action['icon_html'])): ?>
                    <span class="action-btn-icon"><?= $action['icon_html'] ?></span>
                    <?php endif ?>
                    <?= $action['label'] ?>
                </button>
            </form>
            <?php endif ?>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>
