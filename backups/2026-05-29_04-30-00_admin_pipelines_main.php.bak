<?php extract($viewData, EXTR_SKIP); ?>
<?php
$stats = $stats ?? [];
$profiles = $pipelineProfiles ?? [];
$grantableWells = $grantableWells ?? [];
$historyRows = $historyRows ?? [];
$historyTypes = $historyTypes ?? [];
$historyFilters = $historyFilters ?? ['player' => '', 'type' => '', 'days' => 0, 'per_page' => 20, 'page' => 1];
$historyTotal = (int)($historyTotal ?? 0);
$historyPerPage = max(1, (int)($historyFilters['per_page'] ?? 20));
$historyPage = max(1, (int)($historyFilters['page'] ?? 1));
$historyPages = max(1, (int)ceil($historyTotal / $historyPerPage));
$eventLabels = [
    'admin_pipeline_granted' => t('admin.pipelines.event_type_admin_pipeline_granted'),
    'pipeline_build_started' => t('admin.pipelines.event_type_pipeline_build_started'),
    'pipeline_build_complete' => t('admin.pipelines.event_type_pipeline_build_complete'),
    'pipeline_status_change' => t('admin.pipelines.event_type_pipeline_status_change'),
    'pipeline_leak' => t('admin.pipelines.event_type_pipeline_leak'),
    'pipeline_incident' => t('admin.pipelines.event_type_pipeline_incident'),
];
?>

<h1><?= t('admin.pipelines.title') ?></h1>

<?php if ($msg): ?><p role="status" class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p><?php endif ?>
<?php if ($err): ?><p role="alert" class="alert alert-error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p><?php endif ?>

<section class="panel">
    <div class="panel-title-row">
        <p class="panel-title"><?= t('admin.pipelines.types_title') ?></p>
        <p class="panel-hint"><?= t('admin.pipelines.types_hint') ?></p>
    </div>
    <form method="post"
          data-confirm="<?= htmlspecialchars(tPlain('admin.pipelines.confirm_save_profiles'), ENT_QUOTES, 'UTF-8') ?>"
          data-confirm-title="<?= htmlspecialchars(tPlain('admin.pipelines.types_title'), ENT_QUOTES, 'UTF-8') ?>"
          data-confirm-type="warning"
          data-confirm-label="<?= htmlspecialchars(tPlain('admin.pipelines.btn_save_profiles'), ENT_QUOTES, 'UTF-8') ?>">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_pipeline_profiles">
        <div class="pipeline-types-grid">
            <?php foreach ($profiles as $type => $profile): ?>
            <article class="pipeline-type-card">
                <div class="pipeline-type-card__head">
                    <strong><?= t('logistics.pipeline.type_' . $type) ?></strong>
                    <span><?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <label>
                    <span><?= t('admin.pipelines.type_price_pct') ?></span>
                    <input type="number" name="price_pct[<?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?>]" min="1" max="1000" step="0.01" value="<?= htmlspecialchars((string)($profile['price_pct'] ?? 100), ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span><?= t('admin.pipelines.type_capacity_pct') ?></span>
                    <input type="number" name="capacity_pct[<?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?>]" min="1" max="1000" step="0.01" value="<?= htmlspecialchars((string)($profile['capacity_pct'] ?? 100), ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span><?= t('admin.pipelines.type_durability_pct') ?></span>
                    <input type="number" name="durability_pct[<?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?>]" min="1" max="1000" step="0.01" value="<?= htmlspecialchars((string)($profile['durability_pct'] ?? 100), ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <div class="pipeline-type-card__calc">
                    <?= t('admin.pipelines.type_calc_cost') ?>:
                    <strong><?= number_format((float)($profile['build_cost'] ?? 0), 2, ',', ' ') ?> PLN</strong>
                </div>
            </article>
            <?php endforeach ?>
        </div>
        <button type="submit" class="btn btn-primary"><?= t('admin.pipelines.btn_save_profiles') ?></button>
    </form>
</section>

<section aria-label="<?= t('admin.pipelines.stats_label') ?>" class="pipeline-admin-stats">
    <div class="cards">
        <div class="card">
            <p class="label"><?= t('admin.pipelines.stat_total') ?></p>
            <p class="value"><?= (int)($stats['total'] ?? 0) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.pipelines.stat_active') ?></p>
            <p class="value green"><?= (int)($stats['active_count'] ?? 0) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.pipelines.stat_building') ?></p>
            <p class="value orange"><?= (int)($stats['building_count'] ?? 0) ?></p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.pipelines.stat_problem') ?></p>
            <p class="value <?= ((int)($stats['problem_count'] ?? 0) > 0) ? 'red' : 'green' ?>"><?= (int)($stats['problem_count'] ?? 0) ?></p>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-title-row">
        <p class="panel-title"><?= t('admin.pipelines.grant_title') ?></p>
        <p class="panel-hint"><?= t('admin.pipelines.grant_hint') ?></p>
    </div>

    <?php if (empty($grantableWells)): ?>
        <p class="empty-state"><?= t('admin.pipelines.grant_empty') ?></p>
    <?php else: ?>
        <form method="post" class="pipeline-grant-form"
              data-confirm="<?= htmlspecialchars(tPlain('admin.pipelines.confirm_grant'), ENT_QUOTES, 'UTF-8') ?>"
              data-confirm-title="<?= htmlspecialchars(tPlain('admin.pipelines.grant_title'), ENT_QUOTES, 'UTF-8') ?>"
              data-confirm-type="warning"
              data-confirm-label="<?= htmlspecialchars(tPlain('admin.pipelines.grant_btn'), ENT_QUOTES, 'UTF-8') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="grant_pipeline">
            <label class="form-field pipeline-grant-field--wide">
                <span class="form-label"><?= t('admin.pipelines.grant_well_label') ?></span>
                <select name="well_id" required>
                    <option value=""><?= t('admin.pipelines.grant_choose') ?></option>
                    <?php foreach ($grantableWells as $well): ?>
                        <?php
                        $option = '#' . (int)$well['well_id'] . ' - ' . (string)$well['username'] . ' - ' . (string)$well['well_label'] . ' - ' . (string)$well['hub_name'];
                        if (!empty($well['location_name'])) {
                            $option .= ' / ' . (string)$well['location_name'];
                        }
                        ?>
                        <option value="<?= (int)$well['well_id'] ?>"><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <label class="form-field">
                <span class="form-label"><?= t('admin.pipelines.grant_type_label') ?></span>
                <select name="pipeline_type" required>
                    <?php foreach (array_keys($profiles) as $type): ?>
                        <option value="<?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?>"><?= t('logistics.pipeline.type_' . $type) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <button type="submit" class="btn btn-primary"><?= t('admin.pipelines.grant_btn') ?></button>
        </form>
    <?php endif ?>
</section>

<section class="panel">
    <div class="panel-title-row">
        <p class="panel-title"><?= t('admin.pipelines.history_title') ?> <span class="badge"><?= $historyTotal ?></span></p>
        <p class="panel-hint"><?= t('admin.pipelines.history_hint') ?></p>
    </div>

    <form method="get" class="pipeline-history-filter">
        <label class="form-field">
            <span class="form-label"><?= t('admin.pipelines.filter_player') ?></span>
            <input type="text" name="hplayer" value="<?= htmlspecialchars((string)($historyFilters['player'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= t('admin.pipelines.filter_player_placeholder') ?>">
        </label>
        <label class="form-field">
            <span class="form-label"><?= t('admin.pipelines.filter_type') ?></span>
            <select name="htype">
                <option value=""><?= t('admin.pipelines.filter_all') ?></option>
                <?php foreach ($historyTypes as $typeRow): ?>
                    <?php $type = (string)($typeRow['event_type'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= ($historyFilters['type'] ?? '') === $type ? 'selected' : '' ?>>
                        <?= $eventLabels[$type] ?? htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?> (<?= (int)($typeRow['cnt'] ?? 0) ?>)
                    </option>
                <?php endforeach ?>
            </select>
        </label>
        <label class="form-field">
            <span class="form-label"><?= t('admin.pipelines.filter_days') ?></span>
            <input type="number" name="hdays" min="0" max="3650" value="<?= (int)($historyFilters['days'] ?? 0) ?>" placeholder="7">
        </label>
        <label class="form-field">
            <span class="form-label"><?= t('admin.pipelines.filter_per_page') ?></span>
            <select name="hper">
                <?php foreach ([10, 20, 50, 100] as $per): ?>
                    <option value="<?= $per ?>" <?= (int)($historyFilters['per_page'] ?? 20) === $per ? 'selected' : '' ?>><?= $per ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <button type="submit" class="btn btn-secondary"><?= t('admin.pipelines.btn_filter') ?></button>
        <a class="btn btn-secondary" href="/admin/pipelines.php"><?= t('admin.pipelines.btn_clear') ?></a>
    </form>

    <form method="post" class="pipeline-history-clear"
          data-confirm="<?= htmlspecialchars(tPlain('admin.pipelines.confirm_clear_history'), ENT_QUOTES, 'UTF-8') ?>"
          data-confirm-title="<?= htmlspecialchars(tPlain('admin.pipelines.history_title'), ENT_QUOTES, 'UTF-8') ?>"
          data-confirm-type="danger"
          data-confirm-label="<?= htmlspecialchars(tPlain('admin.pipelines.btn_clear_history'), ENT_QUOTES, 'UTF-8') ?>">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="delete_history_filtered">
        <input type="hidden" name="hplayer" value="<?= htmlspecialchars((string)($historyFilters['player'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="htype" value="<?= htmlspecialchars((string)($historyFilters['type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="hdays" value="<?= (int)($historyFilters['days'] ?? 0) ?>">
        <button type="submit" class="btn btn-danger btn-sm"><?= t('admin.pipelines.btn_clear_history') ?></button>
        <span class="muted text-sm"><?= t('admin.pipelines.clear_history_hint') ?></span>
    </form>

    <?php if (empty($historyRows)): ?>
        <p class="empty-state"><?= t('admin.pipelines.history_empty') ?></p>
    <?php else: ?>
        <div class="pipeline-history-grid">
            <div class="list-header">
                <span><?= t('admin.pipelines.col_id') ?></span>
                <span><?= t('admin.pipelines.col_event') ?></span>
                <span><?= t('admin.pipelines.col_player') ?></span>
                <span><?= t('admin.pipelines.col_well') ?></span>
                <span><?= t('admin.pipelines.col_pipeline') ?></span>
                <span><?= t('admin.pipelines.col_message') ?></span>
                <span><?= t('admin.pipelines.col_date') ?></span>
                <span><?= t('admin.pipelines.col_actions') ?></span>
            </div>
            <div class="data-list">
                <?php foreach ($historyRows as $row): ?>
                    <?php $eventType = (string)($row['event_type'] ?? ''); ?>
                    <article class="list-row">
                        <span class="muted">#<?= (int)$row['id'] ?></span>
                        <span><?= $eventLabels[$eventType] ?? htmlspecialchars($eventType, ENT_QUOTES, 'UTF-8') ?></span>
                        <span>
                            <?php if (!empty($row['player_id'])): ?>
                                <a href="/admin/player.php?id=<?= (int)$row['player_id'] ?>"><?= htmlspecialchars((string)($row['username'] ?? '?'), ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                                <?= t('common.dash') ?>
                            <?php endif ?>
                        </span>
                        <span>
                            #<?= (int)($row['well_id'] ?? 0) ?>
                            <span class="muted"><?= htmlspecialchars((string)($row['well_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <span>
                            #<?= (int)($row['pipeline_id'] ?? 0) ?>
                            <?php if (!empty($row['pipeline_type'])): ?>
                                <span class="badge"><?= t('logistics.pipeline.type_' . $row['pipeline_type']) ?></span>
                            <?php endif ?>
                        </span>
                        <span><?= htmlspecialchars((string)($row['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="muted text-sm"><?= htmlspecialchars((string)($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span>
                            <form method="post" class="form-inline"
                                  data-confirm="<?= htmlspecialchars(tPlain('admin.pipelines.confirm_delete_event'), ENT_QUOTES, 'UTF-8') ?>"
                                  data-confirm-type="danger"
                                  data-confirm-label="<?= htmlspecialchars(tPlain('admin.pipelines.btn_delete_event'), ENT_QUOTES, 'UTF-8') ?>">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="delete_history_event">
                                <input type="hidden" name="event_id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><?= t('admin.pipelines.btn_delete_event') ?></button>
                            </form>
                        </span>
                    </article>
                <?php endforeach ?>
            </div>
        </div>

        <?php if ($historyPages > 1): ?>
            <nav class="pagination pipeline-pagination" aria-label="<?= t('admin.pipelines.history_title') ?>">
                <?php for ($page = 1; $page <= $historyPages; $page++): ?>
                    <?php
                    $query = http_build_query([
                        'hplayer' => $historyFilters['player'] ?? '',
                        'htype' => $historyFilters['type'] ?? '',
                        'hdays' => (int)($historyFilters['days'] ?? 0),
                        'hper' => (int)($historyFilters['per_page'] ?? 20),
                        'hpage' => $page,
                    ]);
                    ?>
                    <a class="btn btn-sm <?= $page === $historyPage ? 'btn-primary' : 'btn-secondary' ?>" href="/admin/pipelines.php?<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>"><?= $page ?></a>
                <?php endfor ?>
            </nav>
        <?php endif ?>
    <?php endif ?>
</section>
