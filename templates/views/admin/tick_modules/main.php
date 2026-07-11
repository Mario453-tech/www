<?php
/** @var array<string,mixed> $viewData */
extract($viewData, EXTR_SKIP);

$moduleLabel = static function (array $row): string {
    $label = t((string)($row['label_key'] ?? ''));
    return $label !== '' ? $label : htmlspecialchars((string)($row['key'] ?? $row['module_key'] ?? ''), ENT_QUOTES, 'UTF-8');
};
$statusLabel = static function (string $status): string {
    $key = 'admin.tick_modules.status_' . $status;
    $label = t($key);
    return $label === $key ? htmlspecialchars($status, ENT_QUOTES, 'UTF-8') : $label;
};
$statusClass = static function (string $status): string {
    return match ($status) {
        'success' => 'tick-status--success',
        'error', 'failed' => 'tick-status--error',
        'running' => 'tick-status--running',
        'skipped' => 'tick-status--skipped',
        'disabled' => 'tick-status--disabled',
        default => 'tick-status--neutral',
    };
};
$moduleTypeLabel = static function (bool $critical): string {
    return $critical ? t('admin.tick_modules.type_required') : t('admin.tick_modules.type_scheduled');
};
$failureLabel = static function (string $policy): string {
    return $policy === 'stop'
        ? t('admin.tick_modules.failure_stop_simple')
        : t('admin.tick_modules.failure_continue_simple');
};
$formatJson = static function (array $data): string {
    if ($data === []) {
        return t('admin.tick_modules.stats_no_data');
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return htmlspecialchars($json === false ? '{}' : $json, ENT_QUOTES, 'UTF-8');
};
$pageUrl = static function (int $newLogsPage, int $newStatsPage, string $module): string {
    $params = [
        'logs_page' => max(1, $newLogsPage),
        'stats_page' => max(1, $newStatsPage),
    ];
    if ($module !== '') {
        $params['module'] = $module;
    }
    return '/admin/tick_modules.php?' . http_build_query($params);
};
$profileSummary = static function (array $profile): string {
    if (($profile['sections'] ?? []) === []) {
        return t('admin.tick_modules.profile_none');
    }
    $parts = [];
    foreach (array_slice($profile['sections'], 0, 4, true) as $key => $value) {
        $parts[] = str_replace('_', ' ', $key) . ': ' . (int)$value . ' ms';
    }
    if (($profile['slowest_player_ms'] ?? 0) > 0) {
        $parts[] = t('admin.tick_modules.profile_slowest') . ': #' . (int)($profile['slowest_player_id'] ?? 0) . ' (' . (int)$profile['slowest_player_ms'] . ' ms)';
    }
    return htmlspecialchars(implode(' | ', $parts), ENT_QUOTES, 'UTF-8');
};
?>

<div class="admin-page-header">
    <h1 class="admin-page-title"><?= t('admin.tick_modules.page_title') ?></h1>
    <p class="admin-page-lead"><?= t('admin.tick_modules.page_lead') ?></p>
</div>

<?php if (is_array($msg ?? null)): ?>
<div class="alert alert-<?= htmlspecialchars((string)($msg['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars((string)($msg['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif ?>

<section class="section-card tick-modules-help">
    <p><?= t('admin.tick_modules.help_critical') ?></p>
    <p><?= t('admin.tick_modules.help_interval') ?></p>
    <p><?= t('admin.tick_modules.help_cleanup') ?></p>
</section>

<section class="section-card">
    <h2 class="section-title"><?= t('admin.tick_modules.section_modules') ?></h2>

    <div class="tick-module-list">
        <div class="tick-module-head">
            <span><?= t('admin.tick_modules.col_module') ?></span>
            <span><?= t('admin.tick_modules.col_status') ?></span>
            <span><?= t('admin.tick_modules.col_last_run') ?></span>
            <span><?= t('admin.tick_modules.col_config') ?></span>
            <span><?= t('admin.tick_modules.col_actions') ?></span>
        </div>

        <?php foreach ($modules as $module): ?>
        <?php
            $key = (string)$module['key'];
            $status = (string)$module['last_status'];
            $critical = !empty($module['critical']);
            $statusCss = $statusClass($status);
        ?>
        <article class="tick-module-row">
            <div class="tick-module-name">
                <strong><?= $moduleLabel($module) ?></strong>
                <span class="muted"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="tick-module-meta">
                    #<?= (int)$module['order'] ?>
                    &middot; <?= htmlspecialchars($moduleTypeLabel($critical), ENT_QUOTES, 'UTF-8') ?>
                    &middot; <?= htmlspecialchars($failureLabel((string)$module['policy']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div>
                <span class="tick-status <?= $statusCss ?>"><?= $statusLabel($status) ?></span>
                <?php if (!empty($module['last_error'])): ?>
                <span class="tick-module-error"><?= htmlspecialchars((string)$module['last_error'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif ?>
            </div>

            <div class="tick-module-last">
                <span><?= $module['last_run_at'] ? htmlspecialchars((string)$module['last_run_at'], ENT_QUOTES, 'UTF-8') : '&mdash;' ?></span>
                <span class="muted">
                    <?= $module['last_duration_ms'] !== null ? (int)$module['last_duration_ms'] . ' ms' : '&mdash;' ?>
                    &middot; #<?= (int)$module['last_run_tick'] ?>
                </span>
            </div>

            <form method="post" action="/admin/tick_modules.php?module=<?= rawurlencode($key) ?>" class="tick-module-form <?= $critical ? 'tick-module-form--critical' : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="module_key" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="logs_page" value="<?= (int)$logsPage ?>">
                <input type="hidden" name="stats_page" value="<?= (int)$statsPage ?>">
                <?php if ($critical): ?>
                <input type="hidden" name="enabled" value="1">
                <input type="hidden" name="interval_ticks" value="<?= (int)$module['interval_ticks'] ?>">
                <span class="tick-module-fixed"><?= t('admin.tick_modules.always_active') ?></span>
                <?php else: ?>
                <label class="tick-module-switch">
                    <input type="checkbox" name="enabled" value="1" <?= !empty($module['enabled']) ? 'checked' : '' ?>>
                    <span><?= !empty($module['enabled']) ? t('admin.tick_modules.enabled') : t('admin.tick_modules.disabled') ?></span>
                </label>
                <input type="number" name="interval_ticks" min="1" max="100000" value="<?= (int)$module['interval_ticks'] ?>" class="form-input form-input--sm" aria-label="<?= htmlspecialchars(t('admin.tick_modules.col_interval'), ENT_QUOTES, 'UTF-8') ?>">
                <?php endif ?>
                <input type="number" name="max_items_per_run" min="1" max="1000000" value="<?= (int)$module['max_items_per_run'] ?>" class="form-input form-input--sm" aria-label="<?= htmlspecialchars(t('admin.tick_modules.col_limit'), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-sm btn-primary"><?= t('admin.tick_modules.btn_save') ?></button>
            </form>

            <div class="tick-module-actions">
                <form method="post" action="/admin/tick_modules.php?module=<?= rawurlencode($key) ?>" data-confirm="<?= htmlspecialchars(t('admin.tick_modules.confirm_run'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="run_module">
                    <input type="hidden" name="module_key" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="logs_page" value="<?= (int)$logsPage ?>">
                    <input type="hidden" name="stats_page" value="<?= (int)$statsPage ?>">
                    <button type="submit" class="btn btn-sm btn-warning"><?= t('admin.tick_modules.btn_run') ?></button>
                </form>
                <form method="post" action="/admin/tick_modules.php?module=<?= rawurlencode($key) ?>" data-confirm="<?= htmlspecialchars(t('admin.tick_modules.confirm_restore'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="restore_recommended">
                    <input type="hidden" name="module_key" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="logs_page" value="<?= (int)$logsPage ?>">
                    <input type="hidden" name="stats_page" value="<?= (int)$statsPage ?>">
                    <button type="submit" class="btn btn-sm btn-secondary"><?= t('admin.tick_modules.btn_restore') ?></button>
                </form>
            </div>
        </article>
        <?php endforeach ?>
    </div>
</section>

<section class="section-card">
    <div class="section-toolbar">
        <h2 class="section-title"><?= t('admin.tick_modules.section_logs') ?></h2>
        <div class="tick-toolbar-actions">
            <form method="get" action="/admin/tick_modules.php" class="inline-form">
                <input type="hidden" name="stats_page" value="<?= (int)$statsPage ?>">
                <select name="module" class="form-select form-select--sm">
                    <option value=""><?= t('admin.tick_modules.filter_all') ?></option>
                    <?php foreach ($modules as $module): ?>
                    <option value="<?= htmlspecialchars((string)$module['key'], ENT_QUOTES, 'UTF-8') ?>" <?= $selectedModule === (string)$module['key'] ? 'selected' : '' ?>>
                        <?= $moduleLabel($module) ?>
                    </option>
                    <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-sm btn-secondary"><?= t('admin.tick_modules.filter_btn') ?></button>
            </form>
            <form method="post" action="/admin/tick_modules.php" data-confirm="<?= htmlspecialchars(t('admin.tick_modules.confirm_cleanup'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="cleanup_history">
                <input type="hidden" name="module_key" value="<?= htmlspecialchars($selectedModule, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="logs_page" value="<?= (int)$logsPage ?>">
                <input type="hidden" name="stats_page" value="<?= (int)$statsPage ?>">
                <button type="submit" class="btn btn-sm btn-secondary"><?= t('admin.tick_modules.btn_cleanup') ?></button>
            </form>
        </div>
    </div>

    <?php if (empty($recentLogs)): ?>
    <p class="empty-state"><?= t('admin.tick_modules.empty_logs') ?></p>
    <?php else: ?>
    <div class="tick-run-list">
        <?php foreach ($recentLogs as $log): ?>
        <?php $logStatus = (string)$log['status']; ?>
        <article class="tick-run-row">
            <div>
                <strong><?= $moduleLabel($log) ?></strong>
                <span class="muted"><?= htmlspecialchars((string)$log['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div>
                <span class="tick-status <?= $statusClass($logStatus) ?>"><?= $statusLabel($logStatus) ?></span>
                <span class="muted"><?= (int)$log['duration_ms'] ?> ms &middot; #<?= (int)$log['tick_sequence'] ?></span>
            </div>
            <div>
                <span class="badge badge--<?= !empty($log['forced']) ? 'orange' : 'blue' ?>">
                    <?= !empty($log['forced']) ? t('admin.tick_modules.log_forced') : t('admin.tick_modules.log_auto') ?>
                </span>
                <span class="muted"><?= htmlspecialchars((string)$log['source'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <details>
                <summary><?= t('admin.tick_modules.stats_modules') ?></summary>
                <?php if (!empty($log['error_message'])): ?>
                <p class="tick-module-error"><?= htmlspecialchars((string)$log['error_message'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif ?>
                <pre class="tick-json"><?= $formatJson($log['stats'] ?? []) ?></pre>
            </details>
        </article>
        <?php endforeach ?>
    </div>
    <nav class="tick-pagination" aria-label="module log pagination">
        <?php if ($logsPage > 1): ?>
        <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($pageUrl($logsPage - 1, $statsPage, $selectedModule), ENT_QUOTES, 'UTF-8') ?>"><?= t('admin.tick_modules.btn_prev') ?></a>
        <?php endif ?>
        <span class="muted"><?= t('admin.tick_modules.page_indicator', ['page' => (string)$logsPage, 'pages' => (string)$logsPages]) ?></span>
        <?php if ($logsPage < $logsPages): ?>
        <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($pageUrl($logsPage + 1, $statsPage, $selectedModule), ENT_QUOTES, 'UTF-8') ?>"><?= t('admin.tick_modules.btn_next') ?></a>
        <?php endif ?>
    </nav>
    <?php endif ?>
</section>

<section class="section-card">
    <div class="section-toolbar">
        <h2 class="section-title"><?= t('admin.tick_modules.section_stats') ?></h2>
        <span class="muted"><?= t('admin.tick_modules.page_indicator', ['page' => (string)$statsPage, 'pages' => (string)$statsPages]) ?></span>
    </div>

    <?php if (empty($recentTickStats)): ?>
    <p class="empty-state"><?= t('admin.tick_modules.empty_stats') ?></p>
    <?php else: ?>
    <div class="tick-stats-list">
        <?php foreach ($recentTickStats as $tick): ?>
        <article class="tick-stats-row">
            <div class="tick-stats-summary">
                <strong>#<?= (int)$tick['tick_sequence'] ?></strong>
                <span><?= htmlspecialchars((string)$tick['ran_at'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="muted"><?= htmlspecialchars((string)$tick['source'], ENT_QUOTES, 'UTF-8') ?> &middot; <?= (int)$tick['duration_ms'] ?> ms</span>
                <?php if (($tick['players_profile']['sections'] ?? []) !== []): ?>
                <span class="tick-profile-summary"><?= $profileSummary($tick['players_profile']) ?></span>
                <?php endif ?>
            </div>
            <details>
                <summary><?= t('admin.tick_modules.stats_modules') ?></summary>
                <pre class="tick-json"><?= $formatJson($tick['module_runs'] ?? []) ?></pre>
                <pre class="tick-json"><?= $formatJson($tick['module_stats'] ?? []) ?></pre>
            </details>
        </article>
        <?php endforeach ?>
    </div>
    <nav class="tick-pagination" aria-label="tick stats pagination">
        <?php if ($statsPage > 1): ?>
        <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($pageUrl($logsPage, $statsPage - 1, $selectedModule), ENT_QUOTES, 'UTF-8') ?>"><?= t('admin.tick_modules.btn_prev') ?></a>
        <?php endif ?>
        <span class="muted"><?= t('admin.tick_modules.page_indicator', ['page' => (string)$statsPage, 'pages' => (string)$statsPages]) ?></span>
        <?php if ($statsPage < $statsPages): ?>
        <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($pageUrl($logsPage, $statsPage + 1, $selectedModule), ENT_QUOTES, 'UTF-8') ?>"><?= t('admin.tick_modules.btn_next') ?></a>
        <?php endif ?>
    </nav>
    <?php endif ?>
</section>
