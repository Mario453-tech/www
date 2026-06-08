<?php
if (!isset($statusItems) || !is_array($statusItems)) {
    $statusItems = [];
}
if (!isset($actions) || !is_array($actions)) {
    $actions = [];
}
$gameShellTitle = $gameShellTitle ?? '';
?>
<div class="dashboard fade-in game-shell">
    <?php require __DIR__ . '/status_grid.php'; ?>

    <?php if ($gameShellTitle !== ''): ?>
    <section class="game-shell-heading" aria-label="<?= htmlspecialchars(strip_tags($gameShellTitle)) ?>">
        <h2><?= isset($gameShellTitleHtml) ? $gameShellTitleHtml : htmlspecialchars($gameShellTitle) ?></h2>
    </section>
    <?php endif ?>

    <section class="game-shell-module">
        <?php require $gameShellView; ?>
    </section>

    <section class="card" aria-labelledby="actions-heading">
        <h2 id="actions-heading"><?= t('index.actions_heading') ?></h2>
        <?php require __DIR__ . '/action_grid.php'; ?>
    </section>
</div>
