<?php
/** admin/training - glowny widok modulu szkolen. */
extract($viewData, EXTR_SKIP);
?>

<div class="admin-content">
    <div class="admin-page-header">
        <h1><?= t('admin.training.page_title') ?></h1>
    </div>

    <?php if ($msg !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif ?>
    <?php if ($err !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php endif ?>

    <nav class="admin-tabs">
        <a href="?tab=programs"
           class="admin-tab<?= $tab === 'programs' ? ' active' : '' ?>">
            <?= t('admin.training.tab_programs') ?>
        </a>
        <a href="?tab=monitor"
           class="admin-tab<?= $tab === 'monitor' ? ' active' : '' ?>">
            <?= t('admin.training.tab_monitor') ?>
        </a>
    </nav>

    <div class="admin-tab-content">
        <?php if ($tab === 'programs'): ?>
            <?php require __DIR__ . '/tab_programs.php' ?>
        <?php elseif ($tab === 'monitor'): ?>
            <?php require __DIR__ . '/tab_monitor.php' ?>
        <?php endif ?>
    </div>
</div>
