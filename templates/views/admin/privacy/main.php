<?php extract($viewData, EXTR_SKIP); ?>

<div class="admin-content">
    <div class="page-header">
        <h1><?= t('privacy.admin.heading') ?></h1>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif ?>
    <?php if ($err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endif ?>

    <?php if (empty($features)): ?>
        <p class="muted">Brak aktywnych podmodułów prywatności.</p>
    <?php else: ?>

    <nav class="privacy-tabs" aria-label="Zakładki modułu prywatności">
        <?php foreach ($features as $key => $feature): ?>
            <a href="?tab=<?= htmlspecialchars($key) ?>"
               class="privacy-tab <?= $tab === $key ? 'active' : '' ?>"
               aria-current="<?= $tab === $key ? 'page' : 'false' ?>">
                <?= htmlspecialchars($feature->getIcon()) ?>
                <?= htmlspecialchars($feature->getLabel()) ?>
            </a>
        <?php endforeach ?>
    </nav>

    <div class="privacy-tab-content">
        <?php
        $activeFeature = $features[$tab] ?? null;
        if ($activeFeature) {
            $viewPath = $activeFeature->getViewPath();
            if (file_exists($viewPath)) {
                require $viewPath;
            } else {
                echo '<p class="alert alert-error">Brak widoku: ' . htmlspecialchars($viewPath) . '</p>';
            }
        }
        ?>
    </div>

    <?php endif ?>
</div>
