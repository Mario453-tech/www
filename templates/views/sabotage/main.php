<?php
/** @var bool $moduleEnabled */
/** @var array<int,array<string,mixed>> $options */
/** @var array<int,array<string,mixed>> $targets */
/** @var array<int,array<string,mixed>> $attempts */
/** @var array<int,array<int,string>> $cooldownMap */
/** @var array<string,mixed> $playerData */
/** @var string $error */
/** @var string $success */
extract($viewData, EXTR_SKIP);

$currency = tPlain('common.currency');
$blackMarketScore = (float)($playerData['black_market_score'] ?? 0.0);
?>

<div class="sabotage-page">
    <?php if ($error !== ''): ?>
    <section class="card sabotage-alert sabotage-alert--error">
        <strong><?= t('sabotage.alert_error') ?></strong>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
    <section class="card sabotage-alert sabotage-alert--success">
        <strong><?= t('sabotage.alert_success') ?></strong>
        <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php endif; ?>

    <section class="card sabotage-hero">
        <div>
            <h2><?= t('sabotage.hero_title') ?></h2>
            <p><?= t('sabotage.hero_text') ?></p>
        </div>
        <dl class="sabotage-stats">
            <div>
                <dt><?= t('sabotage.stat_black_market') ?></dt>
                <dd><?= number_format($blackMarketScore, 1, ',', ' ') ?></dd>
            </div>
            <div>
                <dt><?= t('sabotage.stat_cash') ?></dt>
                <dd><?= number_format((float)($playerData['cash'] ?? 0.0), 0, ',', ' ') ?> <?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
        </dl>
    </section>

    <?php if (!$moduleEnabled): ?>
    <section class="card sabotage-alert sabotage-alert--warning">
        <strong><?= t('sabotage.module_disabled_title') ?></strong>
        <p><?= t('sabotage.module_disabled_text') ?></p>
    </section>
    <?php elseif ($options === []): ?>
    <section class="card sabotage-alert sabotage-alert--warning">
        <strong><?= t('sabotage.no_options_title') ?></strong>
        <p><?= t('sabotage.no_options_text') ?></p>
    </section>
    <?php else: ?>
    <section class="card sabotage-options">
        <h3><?= t('sabotage.options_title') ?></h3>
        <div class="sabotage-option-grid">
            <?php foreach ($options as $option): ?>
            <?php
            $requiresBlackMarket = (int)($option['requires_black_market'] ?? 0) === 1;
            ?>
            <article class="sabotage-option-card">
                <div class="sabotage-option-head">
                    <h4><?= htmlspecialchars((string)$option['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <span class="sabotage-option-severity sabotage-option-severity--<?= htmlspecialchars((string)$option['severity'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= t('sabotage.severity_' . (string)$option['severity']) ?>
                    </span>
                </div>
                <p class="sabotage-option-desc"><?= htmlspecialchars((string)$option['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <dl class="sabotage-option-meta">
                    <div>
                        <dt><?= t('sabotage.meta_chance') ?></dt>
                        <dd><?= number_format((float)$option['base_chance_pct'], 1, ',', ' ') ?>%</dd>
                    </div>
                    <div>
                        <dt><?= t('sabotage.meta_cost') ?></dt>
                        <dd>
                        <?php if ((string)$option['cost_type'] === 'percent_reference'): ?>
                            <?= number_format((float)$option['cost_value'], 1, ',', ' ') ?>% <?= t('sabotage.cost_pct_of_cash') ?>
                        <?php elseif ((string)$option['cost_type'] === 'per_bbl'): ?>
                            <?= number_format((float)$option['cost_value'], 0, ',', ' ') ?> <?= t('sabotage.cost_per_bbl') ?>
                        <?php else: ?>
                            <?= number_format((float)$option['cost_value'], 0, ',', ' ') ?> <?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt><?= t('sabotage.meta_cooldown') ?></dt>
                        <dd><?= (int)$option['cooldown_minutes'] ?> min</dd>
                    </div>
                </dl>
                <?php if ($requiresBlackMarket): ?>
                <div class="sabotage-option-flag"><?= t('sabotage.requires_black_market') ?></div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card sabotage-targets">
        <h3><?= t('sabotage.targets_title') ?></h3>
        <?php if ($targets === []): ?>
        <p class="sabotage-empty"><?= t('sabotage.no_targets') ?></p>
        <?php else: ?>
        <div class="sabotage-target-grid">
            <?php foreach ($targets as $target): ?>
            <?php
            $targetId = (int)$target['id'];
            $targetName = (string)($target['company_name'] ?: $target['username']);
            ?>
            <article class="sabotage-target-card">
                <div class="sabotage-target-head">
                    <div>
                        <h4><?= htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') ?></h4>
                        <p><?= htmlspecialchars((string)$target['username'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <span class="sabotage-target-status sabotage-target-status--<?= htmlspecialchars((string)($target['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string)($target['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="sabotage-target-actions">
                    <?php foreach ($options as $option): ?>
                    <?php
                    $optionId = (int)$option['id'];
                    $requiresBlackMarket = (int)($option['requires_black_market'] ?? 0) === 1;
                    $cooldownUntil = $cooldownMap[$targetId][$optionId] ?? null;
                    $disabledReason = '';
                    if ($requiresBlackMarket && $blackMarketScore <= 0.0) {
                        $disabledReason = tPlain('sabotage.disabled_black_market');
                    } elseif ($cooldownUntil !== null) {
                        $disabledReason = tPlain('sabotage.disabled_cooldown', ['time' => $cooldownUntil]);
                    }
                    ?>
                    <form method="post" action="<?= url('sabotage') ?>"
                          class="sabotage-fire-form"
                          <?= $disabledReason === '' ? 'data-confirm="' . htmlspecialchars(tPlain('sabotage.confirm_fire', ['name' => (string)$option['name'], 'target' => $targetName]), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                          <?= $disabledReason === '' ? 'data-confirm-title="' . htmlspecialchars(tPlain('sabotage.confirm_title'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                          <?= $disabledReason === '' ? 'data-confirm-type="warning"' : '' ?>
                          <?= $disabledReason === '' ? 'data-confirm-label="' . htmlspecialchars(tPlain('sabotage.btn_execute'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="execute_sabotage">
                        <input type="hidden" name="target_player_id" value="<?= $targetId ?>">
                        <input type="hidden" name="option_id" value="<?= $optionId ?>">
                        <button type="submit" class="btn btn-secondary sabotage-fire-btn"<?= $disabledReason !== '' ? ' disabled' : '' ?>>
                            <?= htmlspecialchars((string)$option['name'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <?php if ($disabledReason !== ''): ?>
                        <div class="sabotage-disabled-note"><?= htmlspecialchars($disabledReason, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php else: ?>
                        <div class="sabotage-enabled-note">
                            <?= t('sabotage.fire_hint', ['chance' => number_format((float)$option['base_chance_pct'], 1, ',', ' ')]) ?>
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php endforeach; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card sabotage-history">
        <h3><?= t('sabotage.history_title') ?></h3>
        <?php if ($attempts === []): ?>
        <p class="sabotage-empty"><?= t('sabotage.history_empty') ?></p>
        <?php else: ?>
        <div class="sabotage-history-list">
            <?php foreach ($attempts as $attempt): ?>
            <article class="sabotage-history-item">
                <div>
                    <strong><?= htmlspecialchars((string)($attempt['option_name'] ?? $attempt['option_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <p><?= htmlspecialchars((string)($attempt['target_company'] ?: $attempt['target_username']), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="sabotage-history-meta">
                    <span class="sabotage-history-status sabotage-history-status--<?= htmlspecialchars((string)$attempt['status'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= t('sabotage.status_' . (string)$attempt['status']) ?>
                    </span>
                    <span><?= htmlspecialchars((string)$attempt['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
