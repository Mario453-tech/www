<?php
/** @var array<string,mixed> $viewData */
extract($viewData, EXTR_SKIP);
$esc = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$currency = tPlain('common.currency');
$blackMarketScore = (float)($playerData['black_market_score'] ?? 0);
$navUrl = static fn(array $overrides = []): string => url('sabotage', array_merge([
    'q' => $targetQuery, 'status' => $targetStatus,
    'tpage' => $targetPage, 'hpage' => $historyPage,
], $overrides));
?>
<div class="sabotage-page" data-sabotage-suggest-url="<?= $esc(url('sabotage')) ?>" data-sabotage-selected-target="<?= (int)$selectedTarget ?>" data-sabotage-currency="<?= $esc($currency) ?>" data-sabotage-cash="<?= $esc($playerData['cash']) ?>" data-sabotage-market-score="<?= $esc($blackMarketScore) ?>" data-sabotage-market-threshold="<?= $esc($bmThreshold) ?>" data-sabotage-empty="<?= $esc(tPlain('sabotage.selection_empty')) ?>" data-sabotage-inactive="<?= $esc(tPlain('sabotage.disabled_inactive')) ?>" data-sabotage-funds="<?= $esc(tPlain('sabotage.err_insufficient_funds')) ?>" data-sabotage-market="<?= $esc(tPlain('sabotage.disabled_black_market')) ?>" data-sabotage-cooldown="<?= $esc(tPlain('sabotage.disabled_cooldown_label')) ?>">
    <?php if ($error !== ''): ?><div class="sabotage-alert sabotage-alert--error" role="alert"><?= $esc($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="sabotage-alert sabotage-alert--success" role="status"><?= $esc($success) ?></div><?php endif; ?>

    <header class="sabotage-hero">
        <div><h2><?= t('sabotage.hero_title') ?></h2><p><?= t('sabotage.hero_text') ?></p></div>
        <dl class="sabotage-stats">
            <div><dt><?= t('sabotage.stat_black_market') ?></dt><dd><?= number_format($blackMarketScore, 1, ',', ' ') ?></dd></div>
            <div><dt><?= t('sabotage.stat_cash') ?></dt><dd><?= number_format((float)$playerData['cash'], 0, ',', ' ') ?> <?= $esc($currency) ?></dd></div>
        </dl>
    </header>

    <?php if (!$moduleEnabled || $options === []): ?>
        <section class="sabotage-alert sabotage-alert--warning" role="status">
            <h3><?= t($moduleEnabled ? 'sabotage.no_options_title' : 'sabotage.module_disabled_title') ?></h3>
            <p><?= t($moduleEnabled ? 'sabotage.no_options_text' : 'sabotage.module_disabled_text') ?></p>
        </section>
    <?php else: ?>
        <section class="sabotage-surface sabotage-options" aria-labelledby="sabotage-actions-heading">
            <div class="sabotage-section-heading"><h3 id="sabotage-actions-heading"><?= t('sabotage.options_title') ?></h3><span><?= t('sabotage.select_action_hint') ?></span></div>
            <div class="sabotage-option-grid" role="group" aria-label="<?= $esc(tPlain('sabotage.options_title')) ?>">
            <?php foreach ($options as $option): ?>
                <?php $severity = in_array($option['severity'], ['low', 'medium', 'high', 'critical'], true) ? $option['severity'] : 'high'; ?>
                <button type="button" class="sabotage-option-card" data-sabotage-action data-id="<?= (int)$option['id'] ?>" data-name="<?= $esc($option['name']) ?>" data-description="<?= $esc($option['description']) ?>" data-severity="<?= $esc(tPlain('sabotage.severity_' . $severity)) ?>" data-risk="<?= $severity ?>" data-chance="<?= $esc(number_format((float)$option['base_chance_pct'], 1, ',', ' ') . '%') ?>" data-cooldown="<?= (int)$option['cooldown_minutes'] ?>" data-market="<?= (int)$option['requires_black_market'] ?>" aria-pressed="false">
                    <span class="sabotage-option-head"><strong><?= $esc($option['name']) ?></strong><span class="sabotage-risk sabotage-risk--<?= $severity ?>"><?= t('sabotage.severity_' . $severity) ?></span></span>
                    <span class="sabotage-option-desc"><?= $esc($option['description']) ?></span>
                    <span class="sabotage-option-meta">
                        <span><small><?= t('sabotage.meta_chance') ?></small><b><?= number_format((float)$option['base_chance_pct'], 1, ',', ' ') ?>%</b></span>
                        <span><small><?= t('sabotage.meta_cost') ?></small><b><?php if ($option['cost_type'] === 'percent_reference'): ?><?= number_format((float)$option['cost_value'], 1, ',', ' ') ?>% <?= t('sabotage.cost_pct_of_cash') ?><?php else: ?><?= number_format((float)$option['cost_value'], 0, ',', ' ') ?> <?= $esc($currency) ?><?php endif; ?></b></span>
                        <span><small><?= t('sabotage.meta_cooldown') ?></small><b><?= (int)$option['cooldown_minutes'] ?> min</b></span>
                    </span>
                    <?php if ((int)$option['requires_black_market'] === 1): ?><span class="sabotage-option-flag"><?= t('sabotage.requires_black_market') ?></span><?php endif; ?>
                </button>
            <?php endforeach; ?>
            </div>
        </section>

        <div class="sabotage-work-grid">
            <section class="sabotage-surface sabotage-targets" aria-labelledby="sabotage-targets-heading">
                <div class="sabotage-section-heading"><h3 id="sabotage-targets-heading"><?= t('sabotage.targets_title') ?></h3><span><?= (int)$targetTotal ?> <?= t('sabotage.targets_count') ?></span></div>
                <form class="sabotage-target-controls" method="get" action="<?= $esc(url('sabotage')) ?>" data-sabotage-search-form>
                    <label class="sabotage-search-wrap"><span class="sr-only"><?= t('sabotage.search_label') ?></span><input type="search" name="q" value="<?= $esc($targetQuery) ?>" maxlength="80" placeholder="<?= $esc(tPlain('sabotage.search_placeholder')) ?>" autocomplete="off" data-sabotage-search aria-autocomplete="list" aria-controls="sabotage-suggestions" aria-expanded="false"><span class="sabotage-suggestions" id="sabotage-suggestions" role="listbox" hidden></span></label>
                    <label class="sabotage-filter-wrap"><span class="sr-only"><?= t('sabotage.filter_label') ?></span><select name="status" data-sabotage-status><?php foreach (['all', 'active', 'inactive'] as $status): ?><option value="<?= $status ?>"<?= $targetStatus === $status ? ' selected' : '' ?>><?= t('sabotage.filter_' . $status) ?></option><?php endforeach; ?></select></label>
                    <button type="submit" class="sabotage-search-submit"><?= t('sabotage.search_button') ?></button>
                </form>
                <div class="sabotage-target-headings" aria-hidden="true"><span><?= t('sabotage.target_player') ?></span><span><?= t('sabotage.filter_label') ?></span></div>
                <div class="sabotage-target-list" role="group" aria-label="<?= $esc(tPlain('sabotage.targets_title')) ?>">
                    <?php foreach ($targets as $target): ?>
                        <?php $active = $target['status'] === 'active'; $targetId = (int)$target['id']; ?>
                        <button type="button" class="sabotage-target-row" data-sabotage-target data-id="<?= $targetId ?>" data-name="<?= $esc($target['company_name'] ?: $target['username']) ?>" data-status="<?= $active ? 'active' : 'inactive' ?>" data-quotes="<?= $esc(json_encode($costQuotes[$targetId] ?? [], JSON_THROW_ON_ERROR)) ?>" data-cooldowns="<?= $esc(json_encode($cooldownMap[$targetId] ?? [], JSON_THROW_ON_ERROR)) ?>" aria-pressed="false"<?= !$active ? ' disabled' : '' ?>>
                            <span class="sabotage-target-name"><span class="sabotage-radio" aria-hidden="true"></span><strong><?= $esc($target['company_name'] ?: $target['username']) ?></strong><?php if ($target['company_name'] && $target['company_name'] !== $target['username']): ?><small><?= $esc($target['username']) ?></small><?php endif; ?></span>
                            <span class="sabotage-target-status sabotage-target-status--<?= $active ? 'active' : 'inactive' ?>"><?= t('sabotage.player_status_' . ($active ? 'active' : 'inactive')) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if ($targets === []): ?><p class="sabotage-empty" role="status"><?= t('sabotage.no_targets') ?></p><?php endif; ?>
                <p class="sabotage-result-count" aria-live="polite"><?= t('sabotage.results_count', ['shown' => count($targets), 'total' => $targetTotal]) ?></p>
                <?php if ($targetPages > 1): ?><nav class="sabotage-pagination" aria-label="<?= $esc(tPlain('sabotage.targets_pages')) ?>">
                    <?php for ($p = max(1, $targetPage - 2); $p <= min($targetPages, $targetPage + 2); $p++): ?><a class="sabotage-page-btn<?= $p === $targetPage ? ' active' : '' ?>" href="<?= $esc($navUrl(['tpage' => $p])) ?>"<?= $p === $targetPage ? ' aria-current="page"' : '' ?>><?= $p ?></a><?php endfor; ?>
                </nav><?php endif; ?>
            </section>

            <aside class="sabotage-surface sabotage-selection" aria-labelledby="sabotage-selection-heading">
                <div class="sabotage-section-heading"><h3 id="sabotage-selection-heading"><?= t('sabotage.selection_title') ?></h3></div>
                <div class="sabotage-chosen"><span class="sabotage-eyebrow"><?= t('sabotage.selection_action') ?></span><strong data-sabotage-chosen-action><?= t('sabotage.selection_empty') ?></strong><p data-sabotage-chosen-description></p><span class="sabotage-risk" data-sabotage-chosen-risk hidden></span></div>
                <div class="sabotage-chosen"><span class="sabotage-eyebrow"><?= t('sabotage.selection_target') ?></span><strong data-sabotage-chosen-target><?= t('sabotage.selection_empty') ?></strong></div>
                <div class="sabotage-summary"><h4><?= t('sabotage.selection_summary') ?></h4><dl><div><dt><?= t('sabotage.meta_chance') ?></dt><dd data-sabotage-chance>—</dd></div><div><dt><?= t('sabotage.meta_cost') ?></dt><dd data-sabotage-cost>—</dd></div><div><dt><?= t('sabotage.meta_cooldown') ?></dt><dd data-sabotage-cooldown>—</dd></div></dl></div>
                <p class="sabotage-disabled-note" role="status" aria-live="polite" data-sabotage-disabled></p>
                <button type="button" class="sabotage-execute" data-sabotage-execute disabled><?= t('sabotage.btn_execute') ?></button>
                <form id="sabotage-action-form" method="post" action="<?= $esc(url('sabotage')) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="operation_token" value="<?= $esc($operationToken) ?>">
                    <input type="hidden" name="action" value="execute_sabotage">
                    <input type="hidden" name="option_id" value="">
                    <input type="hidden" name="target_player_id" value="">
                </form>
            </aside>
        </div>
    <?php endif; ?>

    <section class="sabotage-surface sabotage-history" aria-labelledby="sabotage-history-heading">
        <div class="sabotage-section-heading"><h3 id="sabotage-history-heading"><?= t('sabotage.history_title') ?></h3><span><?= (int)$historyTotal ?></span></div>
        <?php if ($attempts === []): ?><p class="sabotage-empty"><?= t('sabotage.history_empty') ?></p><?php else: ?>
            <div class="sabotage-history-head" aria-hidden="true"><span><?= t('sabotage.history_action') ?></span><span><?= t('sabotage.history_target') ?></span><span><?= t('sabotage.history_result') ?></span><span><?= t('sabotage.history_date') ?></span></div>
            <ol class="sabotage-history-list">
                <?php foreach ($attempts as $attempt): ?>
                    <?php $status = in_array($attempt['status'], ['success', 'failed', 'partially_blocked', 'blocked_by_protection', 'detected', 'cancelled'], true) ? $attempt['status'] : 'failed'; ?>
                    <li class="sabotage-history-item"><span><?= $esc($attempt['option_name'] ?? $attempt['option_code'] ?? '') ?></span><span><?= $esc($attempt['target_company'] ?: ($attempt['target_username'] ?? '')) ?></span><span><span class="sabotage-history-status sabotage-history-status--<?= $status ?>"><?= t('sabotage.status_' . $status) ?></span></span><time datetime="<?= $esc($attempt['created_at']) ?>"><?= $esc($attempt['created_at']) ?></time></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php if ($historyPages > 1): ?><nav class="sabotage-pagination" aria-label="<?= $esc(tPlain('sabotage.history_pages')) ?>">
            <?php if ($historyPage > 1): ?><a class="sabotage-page-btn" href="<?= $esc($navUrl(['hpage' => $historyPage - 1])) ?>"><?= t('sabotage.prev') ?></a><?php else: ?><span class="sabotage-page-btn" aria-disabled="true"><?= t('sabotage.prev') ?></span><?php endif; ?>
            <?php for ($p = 1; $p <= $historyPages; $p++): ?><a class="sabotage-page-btn<?= $p === $historyPage ? ' active' : '' ?>" href="<?= $esc($navUrl(['hpage' => $p])) ?>"<?= $p === $historyPage ? ' aria-current="page"' : '' ?>><?= $p ?></a><?php endfor; ?>
            <?php if ($historyPage < $historyPages): ?><a class="sabotage-page-btn" href="<?= $esc($navUrl(['hpage' => $historyPage + 1])) ?>"><?= t('sabotage.next') ?></a><?php else: ?><span class="sabotage-page-btn" aria-disabled="true"><?= t('sabotage.next') ?></span><?php endif; ?>
        </nav><?php endif; ?>
    </section>

    <dialog id="sabotage-confirm-dialog" class="sabotage-dialog" aria-labelledby="sabotage-confirm-heading">
        <h3 id="sabotage-confirm-heading"><?= t('sabotage.confirm_title') ?></h3>
        <dl class="sabotage-confirm-details"><div><dt><?= t('sabotage.selection_action') ?></dt><dd data-sabotage-confirm-action></dd></div><div><dt><?= t('sabotage.selection_target') ?></dt><dd data-sabotage-confirm-target></dd></div><div><dt><?= t('sabotage.meta_cost') ?></dt><dd data-sabotage-confirm-cost></dd></div><div><dt><?= t('sabotage.meta_cooldown') ?></dt><dd data-sabotage-confirm-cooldown></dd></div></dl>
        <div class="sabotage-dialog-actions"><button type="button" data-sabotage-cancel><?= t('sabotage.cancel') ?></button><button type="button" data-sabotage-confirm><?= t('sabotage.btn_execute') ?></button></div>
    </dialog>
</div>
<script src="/assets/js/sabotage.js" defer></script>
