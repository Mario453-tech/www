<?php extract($viewData, EXTR_SKIP); ?>
<?php
$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$currencyLabel = $locale === 'en' ? 'USD' : 'PLN';
?>

<div class="fade-in">

    <!-- Company state -->
    <section class="card card-danger" aria-labelledby="recovery-heading">
        <h2 id="recovery-heading"> <?= t('recovery.heading') ?></h2>
        <p><?= t('recovery.intro') ?></p>
        <div class="detail-grid recovery-stats">
            <article>
                <p class="dl"><?= t('recovery.cash') ?></p>
                <p class="dv money"><?= number_format($cash, 0, ',', ' ') ?> <?= $currencyLabel ?></p>
            </article>
            <article>
                <p class="dl"><?= t('recovery.debt_active') ?></p>
                <p class="dv <?= $debtActive > 0 ? 'red' : 'green' ?>"><?= number_format($debtActive, 0, ',', ' ') ?> <?= $currencyLabel ?></p>
            </article>
            <article>
                <p class="dl"><?= t('recovery.debt_late') ?></p>
                <p class="dv <?= $debtLate > 0 ? 'red' : 'muted' ?>"><?= number_format($debtLate, 0, ',', ' ') ?> <?= $currencyLabel ?></p>
            </article>
            <article>
                <p class="dl"><?= t('recovery.status') ?></p>
                <p class="dv"><?= htmlspecialchars($statusLabels[$bkStatus] ?? $bkStatus) ?></p>
            </article>
            <article>
                <p class="dl"><?= t('recovery.critical_events') ?></p>
                <p class="dv <?= $criticalOpen > 0 ? 'red' : 'green' ?>">
                    <?= $criticalOpen > 0 ? " {$criticalOpen} " . t('recovery.critical_open') : ' ' . t('recovery.critical_none') ?>
                </p>
            </article>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($notice) ?></div>
    <?php endif ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>
    <?php if ($message): ?>
        <div class="alert alert-success"> <?= htmlspecialchars($message) ?></div>
    <?php endif ?>

    <!-- Crisis events -->
    <?php if (!empty($events)): ?>
    <section class="card" aria-labelledby="events-heading">
        <h2 id="events-heading"> <?= t('recovery.events_heading') ?></h2>
        <?php foreach ($events as $ev):
            $sev    = (string)($ev['severity'] ?? 'medium');
            $isOpen = empty($ev['resolved_at']);
            $cls    = match($sev) {
                'critical' => 'card-danger',
                'high'     => 'card-warning',
                default    => '',
            };
        ?>
        <div class="card <?= $cls ?> recovery-event">
            <p><strong><?= htmlspecialchars((string)($ev['message'] ?? '')) ?></strong></p>
            <p class="muted2 recovery-event-meta">
                <?= t('recovery.event_type') ?>: <code><?= htmlspecialchars((string)($ev['event_type'] ?? '')) ?></code>
                &nbsp;·&nbsp; <?= htmlspecialchars((string)($ev['created_at'] ?? '—')) ?>
                <?php if (!empty($ev['due_at'])): ?>
                    &nbsp;·&nbsp; <?= t('recovery.event_deadline') ?>:
                    <span class="<?= strtotime($ev['due_at']) < time() && $isOpen ? 'red' : '' ?>">
                        <?= htmlspecialchars(date('d.m.Y H:i', strtotime($ev['due_at']))) ?>
                        <?= strtotime($ev['due_at']) < time() && $isOpen ? ' ' : '' ?>
                    </span>
                <?php endif ?>
                &nbsp;·&nbsp; <span class="<?= $isOpen ? 'red' : 'green' ?>"><?= $isOpen ? t('recovery.event_open') : t('recovery.event_closed') ?></span>
            </p>
            <?php if (!$isOpen && !empty($ev['resolution_note'])): ?>
                <p class="muted recovery-event-note"><?= t('recovery.event_resolution') ?>: <?= htmlspecialchars((string)$ev['resolution_note']) ?></p>
            <?php endif ?>
        </div>
        <?php endforeach ?>
    </section>
    <?php endif ?>

    <!-- Recovery options -->
    <h2 class="recovery-options-heading"> <?= t('recovery.options_heading') ?></h2>

    <div class="recovery-options-grid">

        <!-- 1. Asset sale -->
        <section class="card" aria-labelledby="opt1">
            <h2 id="opt1">1) <?= t('recovery.opt1_title') ?></h2>
            <p class="muted"><?= t('recovery.opt1_desc') ?></p>
            <form method="post" class="recovery-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="sell_asset">
                <div class="recovery-field">
                    <label class="recovery-label" for="asset_type"><?= t('recovery.opt1_asset_label') ?></label>
                    <select id="asset_type" name="asset_type" class="recovery-select">
                        <option value="well"><?= t('recovery.opt1_asset_well') ?></option>
                        <?php if (!empty($options['sell_asset']['can_sell_storage'])): ?>
                            <option value="storage"><?= t('recovery.opt1_asset_storage') ?></option>
                        <?php endif ?>
                    </select>
                    <?php if (!empty($options['sell_asset']['storage_sold'])): ?>
                        <p class="muted recovery-note"> <?= t('recovery.opt1_storage_sold') ?></p>
                    <?php endif ?>
                </div>
                <?php if (!empty($options['sell_asset']['sellable_wells'])): ?>
                <div class="recovery-field">
                    <label class="recovery-label" for="well_id"><?= t('recovery.opt1_well_label') ?></label>
                    <select id="well_id" name="well_id" class="recovery-select">
                        <option value="0"><?= t('recovery.opt1_well_default') ?></option>
                        <?php foreach (($options['sell_asset']['sellable_wells'] ?? []) as $w): ?>
                            <option value="<?= (int)$w['id'] ?>">#<?= (int)$w['id'] ?> | poziom <?= (int)$w['level'] ?> | <?= number_format((float)$w['base_production_per_hour'], 0, ',', ' ') ?> bbl/h</option>
                        <?php endforeach ?>
                    </select>
                </div>
                <?php endif ?>
                <button type="submit" class="btn <?= empty($options['sell_asset']['enabled']) ? 'btn-secondary' : 'btn-warning' ?> btn-full"
                    <?= empty($options['sell_asset']['enabled']) ? 'disabled' : '' ?>>
                    <?= t('recovery.opt1_btn') ?>
                </button>
            </form>
        </section>

        <!-- 2. Bank takeover -->
        <section class="card" aria-labelledby="opt2">
            <h2 id="opt2">2) <?= t('recovery.opt2_title') ?></h2>
            <p class="muted"><?= t('recovery.opt2_desc') ?></p>
            <form method="post" class="recovery-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="bank_takeover">
                <button type="submit" class="btn <?= empty($options['bank_takeover']['enabled']) ? 'btn-secondary' : 'btn-danger' ?> btn-full"
                    <?= empty($options['bank_takeover']['enabled']) ? 'disabled' : '' ?>>
                    <?= t('recovery.opt2_btn') ?>
                </button>
            </form>
        </section>

        <!-- 3. Emergency loan -->
        <section class="card" aria-labelledby="opt3">
            <h2 id="opt3">3) <?= t('recovery.opt3_title') ?></h2>
            <p class="muted"><?= t('recovery.opt3_desc') ?></p>
            <?php if (!empty($options['emergency_loan']['enabled'])): ?>
                <div class="info-box recovery-info"><?= t('recovery.opt3_available') ?>: <?= htmlspecialchars($options['emergency_loan']['apr_range'] ?? '15–25%') ?></div>
            <?php else: ?>
                <div class="info-box recovery-info"><?= t('recovery.opt3_unavailable') ?></div>
            <?php endif ?>
            <form method="post" class="recovery-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="emergency_loan">
                <button type="submit" class="btn <?= empty($options['emergency_loan']['enabled']) ? 'btn-secondary' : 'btn-primary' ?> btn-full"
                    <?= empty($options['emergency_loan']['enabled']) ? 'disabled' : '' ?>>
                    <?= t('recovery.opt3_btn') ?>
                </button>
            </form>
        </section>

        <!-- 4. Cost cuts -->
        <section class="card" aria-labelledby="opt4">
            <h2 id="opt4">4) <?= t('recovery.opt4_title') ?></h2>
            <p class="muted"><?= t('recovery.opt4_desc') ?></p>
            <form method="post" class="recovery-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="cost_cuts">
                <button type="submit" class="btn <?= empty($options['cost_cuts']['enabled']) ? 'btn-secondary' : 'btn-warning' ?> btn-full"
                    <?= empty($options['cost_cuts']['enabled']) ? 'disabled' : '' ?>>
                    <?= t('recovery.opt4_btn') ?>
                </button>
            </form>
        </section>

        <!-- 5. Inwestor ratunkowy -->
        <?php
            $investorUsed = !empty($options['rescue_investor']['already_used']);
            $estInj       = (int)($options['rescue_investor']['est_injection'] ?? 0);
            $investorDebt = (float)($options['rescue_investor']['debt_active']  ?? 0);
        ?>
        <section class="card" aria-labelledby="opt5">
            <h2 id="opt5">5) <?= t('recovery.opt5_title') ?></h2>
            <?php if ($investorUsed): ?>
                <p class="muted"><?= t('recovery.opt5_used') ?></p>
                <button class="btn btn-secondary btn-full" disabled><?= t('recovery.opt5_used_btn') ?></button>
            <?php else: ?>
                <p class="muted"><?= t('recovery.opt5_desc') ?></p>
                <?php if ($investorDebt > 0): ?>
                <div class="info-box recovery-info">
                    <p><?= t('recovery.opt5_debt') ?>: <strong class="red"><?= number_format($investorDebt, 0, ',', ' ') ?> <?= $currencyLabel ?></strong></p>
                    <p class="recovery-info-row"><?= t('recovery.opt5_injection') ?>: <strong class="money">~<?= number_format($estInj, 0, ',', ' ') ?> <?= $currencyLabel ?></strong></p>
                    <p class="muted2 recovery-note"><?= t('recovery.opt5_injection_note') ?></p>
                </div>
                <?php endif ?>
                <form method="post" class="recovery-form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="rescue_investor">
                    <button type="submit" class="btn <?= empty($options['rescue_investor']['enabled']) ? 'btn-secondary' : 'btn-success' ?> btn-full"
                        <?= empty($options['rescue_investor']['enabled']) ? 'disabled' : '' ?>>
                        <?= t('recovery.opt5_btn') ?>
                    </button>
                </form>
            <?php endif ?>
        </section>

        <!-- 6. Nowy start -->
        <section class="card card-danger" aria-labelledby="opt6">
            <h2 id="opt6">6) <?= t('recovery.opt6_title') ?></h2>
            <p class="muted"><?= t('recovery.opt6_desc') ?></p>
            <div class="info-box recovery-info">
                <p class="red"> <?= t('recovery.opt6_warning') ?></p>
            </div>
            <form method="post" class="recovery-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="new_start">
                <button class="btn btn-danger btn-full" type="submit"
                    <?= empty($options['new_start']['enabled']) ? 'disabled' : '' ?>>
                    <?= t('recovery.opt6_btn') ?>
                </button>
            </form>
        </section>

    </div>

    <div class="recovery-back">
        <a href="<?= url('home') ?>" class="btn btn-secondary"><?= t('recovery.back_btn') ?></a>
    </div>

</div>
