<?php extract($viewData, EXTR_SKIP); ?>

<div class="dashboard fade-in">

    <?php require __DIR__ . '/../../components/status_grid.php'; ?>

    <?php require __DIR__ . '/../../components/trend_alert.php'; ?>

    <?php require __DIR__ . '/../../components/director_notifications.php'; ?>

    <?php require __DIR__ . '/../../components/tech_notifications.php'; ?>

    <?php if (!empty($alertWells)): ?>
    <?php $__firstAlert = $alertWells[array_key_first($alertWells)]; ?>
    <div class="alert-strip" role="alert">
        <span class="alert-strip__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </span>
        <div class="alert-strip__body">
            <strong><?= count($alertWells) ?> <?= count($alertWells) === 1 ? 'odwiert wymaga' : (count($alertWells) < 5 ? 'odwierty wymagają' : 'odwiertów wymaga') ?> uwagi</strong>
            <div class="alert-strip__chips">
                <?php foreach ($alertWells as $__aw): ?>
                <a class="alert-strip__chip <?= (float)($__aw['_cond'] ?? 100) < 30 ? 'alert-strip__chip--crit' : 'alert-strip__chip--warn' ?>"
                   href="#wg-card-<?= (int)$__aw['id'] ?>"
                   onclick="return wgFocusWell(<?= (int)$__aw['id'] ?>);">
                    <?= htmlspecialchars($__aw['location_name'] ?? ('Odwiert #' . $__aw['id'])) ?>
                     <?= round((float)($__aw['_cond'] ?? 0), 0) ?>%
                </a>
                <?php endforeach ?>
            </div>
        </div>
        <a class="alert-strip__cta"
           href="#wg-card-<?= (int)$__firstAlert['id'] ?>"
           onclick="return wgFocusWell(<?= (int)$__firstAlert['id'] ?>);">
            PRZEJDŹ →
        </a>
    </div>
    <?php endif ?>

    <?php require __DIR__ . '/../../components/urgent_offer_alert.php'; ?>

    <?php if ($activeBailiff): ?>
    <aside class="card bailiff-alert" role="alert" aria-labelledby="bailiff-heading">
        <h2 id="bailiff-heading" class="bailiff-heading"><?= t('index.bailiff_heading') ?></h2>
        <div class="bailiff-stage-info">
            <p class="bailiff-stage-label">
                <strong><?= t('index.bailiff_stage', ['stage' => $activeBailiff['stage'], 'name' => $bailiffStageName]) ?></strong>
            </p>
            <p class="bailiff-stage-time"><?= t('index.bailiff_next', ['hours' => $bailiffHoursLeft]) ?></p>
            <p class="bailiff-stage-debt"><?= t('index.bailiff_debt', ['amount' => number_format($activeBailiff['debt'], 2)]) ?></p>
        </div>

        <?php if ($activeBailiff['cash_seized'] > 0 || $activeBailiff['oil_seized'] > 0 || $activeBailiff['wells_seized'] > 0): ?>
        <div class="bailiff-seized-summary">
            <p class="bailiff-seized-title"><?= t('index.bailiff_seized_title') ?></p>
            <ul class="bailiff-seized-list">
                <?php if ($activeBailiff['cash_seized'] > 0): ?>
                <li><?= t('index.bailiff_cash', ['amount' => number_format($activeBailiff['cash_seized'], 2)]) ?></li>
                <?php endif ?>
                <?php if ($activeBailiff['oil_seized'] > 0): ?>
                <li><?= t('index.bailiff_oil', ['amount' => number_format($activeBailiff['oil_seized'])]) ?></li>
                <?php endif ?>
                <?php if ($activeBailiff['wells_seized'] > 0): ?>
                <li class="bailiff-wells-critical"><?= t('index.bailiff_wells', ['count' => $activeBailiff['wells_seized']]) ?></li>
                <?php endif ?>
            </ul>
        </div>
        <?php endif ?>

        <?php if (!empty($seizedWells)): ?>
        <div class="bailiff-wells-box">
            <p class="bailiff-wells-title"><?= t('index.bailiff_seized_wells', ['count' => count($seizedWells)]) ?></p>
            <ul class="bailiff-wells-list">
                <?php foreach ($seizedWells as $sw): ?>
                <li>
                    <strong><?= htmlspecialchars($sw['name'] ?? 'Odwiert #' . $sw['id']) ?></strong>
                    (Poziom <?= (int)$sw['level'] ?>, <?= number_format((float)$sw['base_production_per_hour'], 2) ?> baryłek/h)
                </li>
                <?php endforeach ?>
            </ul>
            <p class="bailiff-wells-warning"><?= t('index.bailiff_wells_warning') ?></p>
        </div>
        <?php endif ?>

        <div class="bailiff-actions">
            <a href="<?= url('bank') ?>" class="btn btn-danger"><?= t('index.bailiff_pay_now') ?></a>
            <?php if ($activeBailiff['stage'] >= 4): ?>
            <span class="bailiff-next-warning"><?= t('index.bailiff_next_warning', ['hours' => $bailiffHoursLeft]) ?></span>
            <?php endif ?>
        </div>
    </aside>

    <?php elseif ($hasLateLoan): ?>
    <aside class="card card-warning" role="alert" aria-labelledby="loan-warning-heading">
        <h2 id="loan-warning-heading"><?= t('index.late_loan_heading') ?></h2>
        <p><?= t('index.late_loan_body') ?></p>
        <a href="<?= url('bank') ?>" class="btn btn-danger"><?= t('index.late_loan_pay') ?></a>
    </aside>

    <?php elseif (($loanAppStatus['status'] ?? '') === 'approved'): ?>
    <aside class="card card--bank-approved" role="alert">
        <h2><?= t('index.bank_offer_heading') ?></h2>
        <p><?= t('index.bank_offer_body') ?></p>
        <a href="<?= url('bank') ?>" class="btn btn-success"><?= t('index.bank_offer_see') ?></a>
    </aside>
    <?php endif ?>

    <div class="chat-news-wrapper">
        <?php require __DIR__ . '/../../components/news_panel.php'; ?>
        <?php require __DIR__ . '/../../components/chat.php'; ?>
    </div>

    <section class="card" aria-labelledby="wells-heading">
        <h2 id="wells-heading"><?= t('index.wells_heading') ?></h2>
        <?php require __DIR__ . '/../../components/well_grid.php'; ?>
    </section>

    <section class="card" aria-labelledby="actions-heading">
        <h2 id="actions-heading"><?= t('index.actions_heading') ?></h2>
        <?php require __DIR__ . '/../../components/action_grid.php'; ?>
        <?php if ($financialState === 'crisis'): ?>
        <div class="crisis-mode-notice">
            <span><?= t('index.crisis_blocked') ?></span>
        </div>
        <?php endif ?>
    </section>

    <?php if (($playerData['used'] ?? 0) > 0): ?>
    <aside class="tip-panel" aria-labelledby="tip-heading">
        <div class="tip-panel__icon"></div>
        <div class="tip-panel__body">
            <div class="tip-panel__title" id="tip-heading">Okazja do sprzedaży</div>
            <p class="tip-panel__text">
                Masz <strong><?= number_format((float)$playerData['used'], 0, ',', ' ') ?> baryłek</strong> ropy w magazynie.
                Przy obecnej cenie <strong><?= number_format((float)$marketData['current_price'], 0, ',', ' ') ?> $/bbl</strong>
                możesz zarobić <strong class="tip-panel__value"><?= number_format((float)$playerData['used'] * (float)$marketData['current_price'], 0, ',', ' ') ?> gotówki</strong>.
            </p>
            <a href="<?= url('market') ?>" class="btn btn-sm btn-secondary tip-panel__cta">Sprzedaj teraz</a>
        </div>
    </aside>
    <?php endif ?>

    <?php if (($playerData['used'] ?? 0) >= ($playerData['capacity'] ?? 0) * 0.8): ?>
    <aside class="card card-warning" role="alert" aria-labelledby="warning-heading">
        <h2 id="warning-heading"><?= t('index.storage_warning') ?></h2>
        <p><?= t('index.storage_warning_body') ?></p>
        <p><?= t('index.storage_warning_sell') ?></p>
    </aside>
    <?php endif ?>

</div>
