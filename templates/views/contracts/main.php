<?php
/**
 * Widok gracza modulu kontraktow dlugoterminowych.
 * Player view for the long-term contracts module.
 *
 * @var bool $moduleEnabled
 * @var array<int,array<string,mixed>> $available
 * @var array<int,array<string,mixed>> $active
 * @var array<int,array<string,mixed>> $deliveries
 * @var array<int,array<string,mixed>> $logs
 * @var float $marketPrice
 * @var string $error
 * @var string $success
 */
extract($viewData, EXTR_SKIP);

$fmtBbl = static fn(float $v): string => number_format($v, 0, ',', "\xc2\xa0");
$fmtPln = static fn(float $v): string => number_format($v, 2, ',', "\xc2\xa0");

$fmtMinutes = static function (float $minutes): string {
    $minutes = max(0.0, $minutes);
    if ($minutes >= 1440.0) {
        return number_format($minutes / 1440.0, ($minutes / 1440.0 == floor($minutes / 1440.0)) ? 0 : 1, ',', "\xc2\xa0") . "\xc2\xa0" . tPlain('contracts.unit_days');
    }
    return number_format($minutes / 60.0, ($minutes / 60.0 == floor($minutes / 60.0)) ? 0 : 1, ',', "\xc2\xa0") . "\xc2\xa0" . tPlain('contracts.unit_hours');
};

$termVal = static function (array $terms, string $key): float {
    if (!isset($terms[$key])) { return 0.0; }
    $entry = $terms[$key];
    return is_array($entry) ? (float)($entry['value'] ?? 0.0) : (float)$entry;
};
$termText = static function (array $terms, string $key): string {
    if (!isset($terms[$key]) || !is_array($terms[$key])) { return ''; }
    return (string)($terms[$key]['text'] ?? '');
};

$priceModeLabel = static function (string $mode): string {
    $langKey = 'contracts.price_mode.' . $mode;
    $label = tPlain($langKey);
    return $label !== $langKey ? $label : $mode;
};
$eventLabel = static function (string $eventKey): string {
    $langKey = 'contracts.event.' . $eventKey;
    $label = tPlain($langKey);
    return $label !== $langKey ? $label : $eventKey;
};
$statusLabel = static function (string $status): string {
    $langKey = 'contracts.status.' . $status;
    $label = tPlain($langKey);
    return $label !== $langKey ? $label : $status;
};
$deliveryStatusLabel = static function (string $status): string {
    $langKey = 'contracts.delivery_status.' . $status;
    $label = tPlain($langKey);
    return $label !== $langKey ? $label : $status;
};
$depositStatusLabel = static function (string $status): string {
    $langKey = 'contracts.deposit_status.' . $status;
    $label = tPlain($langKey);
    return $label !== $langKey ? $label : $status;
};
?>

<div class="fade-in contracts-page">

<?php if ($error): ?>
<noscript><div class="msg-bar msg-error"><?= htmlspecialchars($error) ?></div></noscript>
<?php endif ?>
<?php if ($success): ?>
<noscript><div class="msg-bar msg-success"><?= htmlspecialchars($success) ?></div></noscript>
<?php endif ?>
<?php if ($error || $success): ?>
<div id="contracts-flash" hidden
     data-error="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"
     data-success="<?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif ?>

<section class="card">
    <p><?= t('contracts.page_intro') ?></p>
    <?php if (!$moduleEnabled): ?>
    <div class="contracts-notice contracts-notice--off"><?= t('contracts.module_disabled_notice') ?></div>
    <?php endif ?>
</section>

<!-- == DOSTEPNE KONTRAKTY == -->
<section class="card">
    <h3><?= t('contracts.section_available') ?></h3>
    <?php if (empty($available)): ?>
    <p class="contracts-empty"><?= t('contracts.none_available') ?></p>
    <?php else: ?>
    <div class="contracts-grid">
        <?php foreach ($available as $option): ?>
        <?php
            $terms       = is_array($option['terms'] ?? null) ? $option['terms'] : [];
            $totalBbl    = $termVal($terms, 'total_bbl');
            $deliveryBbl = $termVal($terms, 'delivery_bbl');
            $intervalMin = $termVal($terms, 'delivery_interval_minutes');
            $durationMin = $termVal($terms, 'duration_minutes');
            $bonusPct    = $termVal($terms, 'bonus_pct');
            $penaltyPct  = $termVal($terms, 'penalty_pct');
            $minContractRep = $termVal($terms, 'min_contract_reputation');
            $priceMode   = $termText($terms, 'price_mode') ?: (string)($option['price_mode'] ?? 'market_plus_bonus');
            $estRevenue  = $deliveryBbl * $marketPrice * (1.0 + $bonusPct / 100.0);
            $deposit     = (float)($option['estimated_security_deposit'] ?? 0.0);
            $met         = !empty($option['requirements_met']);
            $lockReason  = (string)($option['locked_reason'] ?? '');
        ?>
        <div class="contracts-card<?= $met ? '' : ' contracts-card--locked' ?>">
            <div class="contracts-card__head">
                <span class="contracts-card__name"><?= htmlspecialchars((string)($option['name'] ?? '')) ?></span>
                <span class="contracts-badge contracts-badge--<?= htmlspecialchars((string)($option['severity'] ?? 'low'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($option['buyer_name'] ?? '')) ?></span>
            </div>
            <?php if (!empty($option['description'])): ?>
            <p class="contracts-card__desc"><?= htmlspecialchars((string)$option['description']) ?></p>
            <?php endif ?>
            <div class="contracts-terms">
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.total_volume') ?></span>
                    <strong class="ct-val"><?= $fmtBbl($totalBbl) ?>&nbsp;<?= t('contracts.unit_bbl') ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.delivery_volume') ?></span>
                    <strong class="ct-val"><?= $fmtBbl($deliveryBbl) ?>&nbsp;<?= t('contracts.unit_bbl') ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.interval') ?></span>
                    <strong class="ct-val"><?= htmlspecialchars($fmtMinutes($intervalMin)) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.duration') ?></span>
                    <strong class="ct-val"><?= htmlspecialchars($fmtMinutes($durationMin)) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.bonus') ?></span>
                    <strong class="ct-val ct-val--bonus">+<?= $fmtBbl($bonusPct) ?>%</strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.penalty') ?></span>
                    <strong class="ct-val ct-val--penalty"><?= $fmtBbl($penaltyPct) ?>%</strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.price_mode') ?></span>
                    <strong class="ct-val"><?= htmlspecialchars($priceModeLabel($priceMode)) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.est_revenue') ?></span>
                    <strong class="ct-val"><?= $fmtPln($estRevenue) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.security_deposit') ?></span>
                    <strong class="ct-val"><?= $fmtPln($deposit) ?></strong>
                </div>
            </div>
            <?php if ($met): ?>
            <form method="post" class="contracts-action-form"
                  data-confirm="<?= htmlspecialchars(tPlain('contracts.confirm_sign', ['name' => (string)($option['name'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>"
                  data-confirm-label="<?= htmlspecialchars(tPlain('contracts.btn_sign'), ENT_QUOTES, 'UTF-8') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="accept_contract">
                <input type="hidden" name="option_id" value="<?= (int)$option['id'] ?>">
                <button type="submit" class="btn btn-success btn-full"><?= t('contracts.btn_sign') ?></button>
            </form>
            <?php else: ?>
            <div class="contracts-lock">
                <?php if ($lockReason === 'credibility'): ?>
                    <?= t('contracts.locked_credibility', ['min' => (int)($option['min_credibility'] ?? 0)]) ?>
                <?php elseif ($lockReason === 'legal_level'): ?>
                    <?= t('contracts.locked_legal_level', ['level' => (int)($option['requires_legal_level'] ?? 0)]) ?>
                <?php elseif ($lockReason === 'contract_reputation'): ?>
                    <?= t('contracts.locked_contract_reputation', ['min' => (int)$minContractRep]) ?>
                <?php else: ?>
                    <?= t('contracts.locked_generic') ?>
                <?php endif ?>
            </div>
            <?php endif ?>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<!-- == AKTYWNE KONTRAKTY == -->
<section class="card">
    <h3><?= t('contracts.section_active') ?></h3>
    <?php if (empty($active)): ?>
    <p class="contracts-empty"><?= t('contracts.none_active') ?></p>
    <?php else: ?>
    <div class="contracts-grid">
        <?php foreach ($active as $contract): ?>
        <?php
            $total       = (float)($contract['total_bbl'] ?? 0.0);
            $delivered   = (float)($contract['delivered_bbl'] ?? 0.0);
            $missed      = (float)($contract['missed_bbl'] ?? 0.0);
            $deposit     = (float)($contract['security_deposit'] ?? 0.0);
            $depositRefunded = (float)($contract['security_deposit_refunded'] ?? 0.0);
            $depositStatus = (string)($contract['security_deposit_status'] ?? 'none');
            $progressPct = $total > 0.0 ? max(0, min(100, (int)round($delivered / $total * 100))) : 0;
            $status      = (string)($contract['status'] ?? 'active');
        ?>
        <div class="contracts-card contracts-card--active">
            <div class="contracts-card__head">
                <span class="contracts-card__name"><?= htmlspecialchars((string)($contract['contract_name'] ?? '')) ?></span>
                <span class="contracts-badge contracts-badge--status"><?= htmlspecialchars($statusLabel($status)) ?></span>
            </div>
            <div class="contracts-card__desc"><?= t('contracts.buyer') ?>: <?= htmlspecialchars((string)($contract['buyer_name'] ?? '')) ?></div>
            <div class="contracts-progress" role="img"
                 aria-label="<?= htmlspecialchars(tPlain('contracts.progress', ['done' => $fmtBbl($delivered), 'total' => $fmtBbl($total)]), ENT_QUOTES, 'UTF-8') ?>">
                <div class="contracts-progress__bar" style="--bar-w: <?= $progressPct ?>%"></div>
            </div>
            <div class="contracts-terms">
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.delivered') ?></span>
                    <strong class="ct-val"><?= $fmtBbl($delivered) ?>&nbsp;/&nbsp;<?= $fmtBbl($total) ?>&nbsp;<?= t('contracts.unit_bbl') ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.missed') ?></span>
                    <strong class="ct-val ct-val--penalty"><?= $fmtBbl($missed) ?>&nbsp;<?= t('contracts.unit_bbl') ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.next_delivery') ?></span>
                    <strong class="ct-val"><?= htmlspecialchars(substr((string)($contract['next_delivery_at'] ?? ''), 0, 16)) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.ends_at') ?></span>
                    <strong class="ct-val"><?= htmlspecialchars(substr((string)($contract['ends_at'] ?? ''), 0, 16)) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.security_deposit') ?></span>
                    <strong class="ct-val"><?= $fmtPln($deposit) ?></strong>
                </div>
                <div class="contracts-term">
                    <span class="ct-label"><?= t('contracts.security_deposit_status') ?></span>
                    <strong class="ct-val"><?= htmlspecialchars($depositStatusLabel($depositStatus)) ?><?= $depositRefunded > 0.0 ? ' · ' . $fmtPln($depositRefunded) : '' ?></strong>
                </div>
            </div>
            <form method="post" class="contracts-action-form"
                  data-confirm="<?= htmlspecialchars(tPlain('contracts.confirm_cancel', ['name' => (string)($contract['contract_name'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>"
                  data-confirm-type="warning"
                  data-confirm-label="<?= htmlspecialchars(tPlain('contracts.btn_cancel'), ENT_QUOTES, 'UTF-8') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="cancel_contract">
                <input type="hidden" name="contract_id" value="<?= (int)$contract['id'] ?>">
                <button type="submit" class="btn btn-danger btn-full"><?= t('contracts.btn_cancel') ?></button>
            </form>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<!-- == HISTORIA DOSTAW == -->
<section class="card">
    <h3><?= t('contracts.section_deliveries') ?></h3>
    <?php if (empty($deliveries)): ?>
    <p class="contracts-empty"><?= t('contracts.none_deliveries') ?></p>
    <?php else: ?>
    <div class="contracts-list">
        <div class="contracts-list__head contracts-row">
            <span><?= t('contracts.due_at') ?></span>
            <span><?= t('contracts.delivered') ?></span>
            <span><?= t('contracts.missed') ?></span>
            <span><?= t('contracts.price_per_bbl') ?></span>
            <span><?= t('contracts.revenue') ?></span>
            <span><?= t('contracts.penalty') ?></span>
            <span><?= t('contracts.status_label') ?></span>
        </div>
        <?php foreach ($deliveries as $d): ?>
        <div class="contracts-row">
            <span data-label="<?= t('contracts.due_at') ?>"><?= htmlspecialchars(substr((string)($d['due_at'] ?? ''), 0, 16)) ?></span>
            <span data-label="<?= t('contracts.delivered') ?>"><?= $fmtBbl((float)($d['delivered_bbl'] ?? 0.0)) ?></span>
            <span data-label="<?= t('contracts.missed') ?>"><?= $fmtBbl((float)($d['missed_bbl'] ?? 0.0)) ?></span>
            <span data-label="<?= t('contracts.price_per_bbl') ?>"><?= $fmtPln((float)($d['price_per_bbl'] ?? 0.0)) ?></span>
            <span data-label="<?= t('contracts.revenue') ?>"><?= $fmtPln((float)($d['revenue'] ?? 0.0)) ?></span>
            <span data-label="<?= t('contracts.penalty') ?>"><?= $fmtPln((float)($d['penalty'] ?? 0.0)) ?></span>
            <span data-label="<?= t('contracts.status_label') ?>">
                <span class="contracts-badge contracts-badge--<?= htmlspecialchars((string)($d['status'] ?? 'delivered'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($deliveryStatusLabel((string)($d['status'] ?? 'delivered'))) ?></span>
            </span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<!-- == LOGI KONTRAKTOW == -->
<section class="card">
    <h3><?= t('contracts.section_logs') ?></h3>
    <?php if (empty($logs)): ?>
    <p class="contracts-empty"><?= t('contracts.none_logs') ?></p>
    <?php else: ?>
    <div class="contracts-list">
        <?php foreach ($logs as $log): ?>
        <div class="contracts-row contracts-row--log">
            <span data-label="<?= t('contracts.log_time') ?>"><?= htmlspecialchars(substr((string)($log['created_at'] ?? ''), 0, 16)) ?></span>
            <span data-label="<?= t('contracts.log_event') ?>"><?= htmlspecialchars($eventLabel((string)($log['event_key'] ?? ''))) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<nav class="page-nav" aria-label="<?= htmlspecialchars(strip_tags(t('contracts.page_title'))) ?>">
    <a href="<?= url('dashboard') ?>" class="btn btn-secondary"><?= t('contracts.btn_back') ?></a>
</nav>

</div>
