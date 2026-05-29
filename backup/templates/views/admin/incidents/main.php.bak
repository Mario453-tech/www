<?php
extract($viewData, EXTR_SKIP);
$levelMeta = [
    'micro'  => ['', t('admin.incidents.level_micro'),  'badge-ok',     t('admin.incidents.tab_micro')],
    'minor'  => ['', t('admin.incidents.level_minor'),  'badge-warn',   t('admin.incidents.tab_minor')],
    'medium' => ['', t('admin.incidents.level_medium'), 'badge-orange', t('admin.incidents.tab_medium')],
    'major'  => ['', t('admin.incidents.level_major'),  'badge-error',  t('admin.incidents.tab_major')],
];
// Pipeline incident level meta: key => [label, badge-class, tab-label]
$pipeLevelMeta = [
    'pipe_micro'  => [t('admin.incidents.pipe_micro_label'),  'badge-blue',   t('admin.incidents.tab_pipe_micro')],
    'pipe_minor'  => [t('admin.incidents.pipe_minor_label'),  'badge-warn',   t('admin.incidents.tab_pipe_minor')],
    'pipe_medium' => [t('admin.incidents.pipe_medium_label'), 'badge-orange', t('admin.incidents.tab_pipe_medium')],
];
?>

<h1><?= t('admin.incidents.page_title') ?></h1>

<?php if ($msg): ?><p role="status" class="alert alert-success"><?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p role="alert"  class="alert alert-error"><?= htmlspecialchars($err) ?></p><?php endif ?>

<!--  ZAKADKI  -->
<div class="admin-tabs" role="tablist">
    <button id="inc-btn-stats"  onclick="incShowTab('stats')"  class="admin-tab" role="tab"> <?= t('admin.incidents.tab_stats') ?></button>
    <button id="inc-btn-micro"  onclick="incShowTab('micro')"  class="admin-tab" role="tab"><?= t('admin.incidents.tab_micro') ?></button>
    <button id="inc-btn-minor"  onclick="incShowTab('minor')"  class="admin-tab" role="tab"><?= t('admin.incidents.tab_minor') ?></button>
    <button id="inc-btn-medium" onclick="incShowTab('medium')" class="admin-tab" role="tab"><?= t('admin.incidents.tab_medium') ?></button>
    <button id="inc-btn-major"      onclick="incShowTab('major')"      class="admin-tab" role="tab"><?= t('admin.incidents.tab_major') ?></button>
    <button id="inc-btn-pipe_micro"  onclick="incShowTab('pipe_micro')"  class="admin-tab" role="tab"><?= t('admin.incidents.tab_pipe_micro') ?></button>
    <button id="inc-btn-pipe_minor"  onclick="incShowTab('pipe_minor')"  class="admin-tab" role="tab"><?= t('admin.incidents.tab_pipe_minor') ?></button>
    <button id="inc-btn-pipe_medium" onclick="incShowTab('pipe_medium')" class="admin-tab" role="tab"><?= t('admin.incidents.tab_pipe_medium') ?></button>
    <button id="inc-btn-recent" onclick="incShowTab('recent')" class="admin-tab" role="tab"><?= t('admin.incidents.tab_recent') ?></button>
    <button id="inc-btn-help"    onclick="incShowTab('help')"    class="admin-tab" role="tab"><?= t('admin.incidents.tab_help') ?></button>
    <button id="inc-btn-trigger" onclick="incShowTab('trigger')" class="admin-tab admin-tab--danger" role="tab"><?= t('admin.incidents.tab_trigger') ?></button>
</div>

<!--  TAB: STATYSTYKI  -->
<div id="inc-tab-stats" class="admin-tab-content" role="tabpanel">
    <section class="panel" aria-label="<?= t('admin.incidents.stats_title') ?>">
        <p class="panel-title"> <?= t('admin.incidents.stats_title') ?></p>
        <div class="stats-grid stats-grid--6">
        <?php foreach ($LEVELS as $lvl):
            [$icon, $label, $badgeCls] = $levelMeta[$lvl];
            $s = $stats[$lvl] ?? null;
        ?>
            <div class="stat-card">
                <div class="stat-label"><?= $icon ?> <?= $label ?></div>
                <div class="stat-value <?= $lvl === 'major' ? 'red' : ($lvl === 'medium' ? 'orange' : '') ?>">
                    <?= $s ? number_format((int)$s['cnt']) : '0' ?>
                </div>
                <?php if ($s): ?>
                <div class="card-sub">
                    <?= t('admin.incidents.stat_avg_drop') ?>: <?= round((float)$s['avg_drop'], 1) ?>%
                    <?php if ((float)$s['total_cost'] > 0): ?>
                    <br><?= t('admin.incidents.stat_costs_prefix') ?>: $<?= number_format((float)$s['total_cost'], 0, '.', ' ') ?>
                    <?php endif ?>
                    <?php if ((int)$s['unrepaired'] > 0): ?>
                    <br><span class="text-red"><?= t('admin.incidents.stat_open_prefix') ?>: <?= (int)$s['unrepaired'] ?></span>
                    <?php endif ?>
                </div>
                <?php endif ?>
            </div>
        <?php endforeach ?>
            <div class="stat-card">
                <div class="stat-label"><?= t('admin.incidents.stats_total_label') ?></div>
                <div class="stat-value">
                    <?= number_format(array_sum(array_column($stats, 'cnt'))) ?>
                </div>
                <div class="card-sub"><?= t('admin.incidents.stats_total_sub') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><?= t('admin.incidents.stats_costs_label') ?></div>
                <div class="stat-value <?= array_sum(array_column($stats, 'total_cost')) > 1000000 ? 'red' : '' ?>">
                    $<?= number_format(array_sum(array_column($stats, 'total_cost')), 0, '.', ' ') ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
//  TABS: KONFIGURACJA PER POZIOM 
$formReset = '<form method="post" id="incident-reset-form">' . CSRF::field() . '<input type="hidden" name="action" value="reset_incident_cfg"></form>';
foreach ($LEVELS as $lvl):
    [$icon, $label, $badgeCls, $tabLabel] = $levelMeta[$lvl];
    $c = $config[$lvl];
    $d = $DEFAULTS[$lvl];
?>
<div id="inc-tab-<?= $lvl ?>" class="admin-tab-content" role="tabpanel">
    <section class="panel" aria-label="<?= $tabLabel ?>">
        <p class="panel-title"><?= $icon ?> <?= $label ?> <?= t('admin.incidents.cfg_level_title') ?></p>
        <p class="panel-hint"><?= t('admin.incidents.cfg_hint') ?></p>

        <form method="post" id="incident-cfg-form-<?= $lvl ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save_incident_cfg">
            <?php
            // Pass hidden fields for all other levels unchanged
            foreach ($LEVELS as $other):
                if ($other === $lvl) continue;
                $co = $config[$other];
            ?>
            <input type="hidden" name="inc_<?= $other ?>_prod_drop_min" value="<?= $co['prod_drop_min'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_prod_drop_max" value="<?= $co['prod_drop_max'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_deg_min"       value="<?= $co['deg_min'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_deg_max"       value="<?= $co['deg_max'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_cost_min"      value="<?= $co['cost_min'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_cost_max"      value="<?= $co['cost_max'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_risk_add"      value="<?= $co['risk_add'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_hours_min"     value="<?= $co['hours_min'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_hours_max"     value="<?= $co['hours_max'] ?>">
            <input type="hidden" name="inc_<?= $other ?>_base_chance"   value="<?= $co['base_chance'] ?>">
            <?php endforeach ?>

            <div class="inc-cfg-block">
                <div class="inc-cfg-grid">

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_prod_drop') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="inc_<?= $lvl ?>_prod_drop_min"
                                   value="<?= $c['prod_drop_min'] ?>" min="0" max="100" step="1" class="gm-input--short"
                                   title="<?= t('admin.incidents.field_min') ?>">
                            <span class="range-sep"></span>
                            <input type="number" name="inc_<?= $lvl ?>_prod_drop_max"
                                   value="<?= $c['prod_drop_max'] ?>" min="0" max="100" step="1" class="gm-input--short"
                                   title="<?= t('admin.incidents.field_max') ?>">
                            <span class="inc-cfg-unit">%</span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: <?= $d['prod_drop_min'] ?><?= $d['prod_drop_max'] ?>%)</span>
                        </div>
                    </div>

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_deg') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="inc_<?= $lvl ?>_deg_min"
                                   value="<?= $c['deg_min'] ?>" min="0" max="100" step="1" class="gm-input--short">
                            <span class="range-sep"></span>
                            <input type="number" name="inc_<?= $lvl ?>_deg_max"
                                   value="<?= $c['deg_max'] ?>" min="0" max="100" step="1" class="gm-input--short">
                            <span class="inc-cfg-unit"><?= t('admin.incidents.cfg_unit_pts') ?></span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: <?= $d['deg_min'] ?><?= $d['deg_max'] ?>)</span>
                        </div>
                    </div>

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_cost') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="inc_<?= $lvl ?>_cost_min"
                                   value="<?= $c['cost_min'] ?>" min="0" max="100000000" step="1000" class="gm-input--mid">
                            <span class="range-sep"></span>
                            <input type="number" name="inc_<?= $lvl ?>_cost_max"
                                   value="<?= $c['cost_max'] ?>" min="0" max="100000000" step="1000" class="gm-input--mid">
                            <span class="inc-cfg-unit">$</span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: $<?= number_format($d['cost_min'],0,'.','.') ?>$<?= number_format($d['cost_max'],0,'.','.') ?>)</span>
                        </div>
                    </div>

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_risk_add') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="inc_<?= $lvl ?>_risk_add"
                                   value="<?= $c['risk_add'] ?>" min="0" max="50" step="1" class="gm-input--short">
                            <span class="inc-cfg-unit"><?= t('admin.incidents.cfg_unit_pts') ?></span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: <?= $d['risk_add'] ?>)</span>
                        </div>
                    </div>

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_hours') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="inc_<?= $lvl ?>_hours_min"
                                   value="<?= $c['hours_min'] ?>" min="1" max="720" step="1" class="gm-input--short">
                            <span class="range-sep"></span>
                            <input type="number" name="inc_<?= $lvl ?>_hours_max"
                                   value="<?= $c['hours_max'] ?>" min="1" max="720" step="1" class="gm-input--short">
                            <span class="inc-cfg-unit">h</span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: <?= $d['hours_min'] ?><?= $d['hours_max'] ?>h)</span>
                        </div>
                    </div>

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_base_chance') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="inc_<?= $lvl ?>_base_chance"
                                   value="<?= $c['base_chance'] ?>" min="0.0001" max="1" step="0.0001" class="gm-input--short">
                            <span class="inc-cfg-unit">/h</span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: <?= $d['base_chance'] ?>)</span>
                        </div>
                    </div>

                </div>
            </div>

            <div class="form-row form-row--gap">
                <button type="submit" class="btn btn-primary"> <?= t('admin.incidents.btn_save') ?></button>
                <button type="button" class="btn btn-danger"
                        onclick="confirmAction('<?= t('admin.incidents.confirm_reset') ?>', function(){ document.getElementById('incident-reset-form').submit(); })">
                     <?= t('admin.incidents.btn_reset') ?>
                </button>
            </div>
        </form>
    </section>
</div>
<?php endforeach ?>

<?= $formReset ?>

<?php
//  TABS: KONFIGURACJA INCYDENTOW RUROCIAGU
$formPipeReset = '<form method="post" id="pipeline-inc-reset-form">' . CSRF::field() . '<input type="hidden" name="action" value="reset_pipeline_inc_cfg"></form>';
foreach ($PIPE_LEVELS as $lvl):
    [$plabel, $pbadgeCls, $ptabLabel] = $pipeLevelMeta[$lvl];
    $pc = $pipeConfig[$lvl];
    $pd = $PIPE_DEFAULTS[$lvl];
?>
<div id="inc-tab-<?= $lvl ?>" class="admin-tab-content" role="tabpanel">
    <section class="panel" aria-label="<?= htmlspecialchars($ptabLabel) ?>">
        <p class="panel-title"><span class="badge <?= $pbadgeCls ?>"><?= htmlspecialchars($plabel) ?></span> &nbsp;<?= t('admin.incidents.cfg_level_title') ?></p>
        <p class="panel-hint"><?= t('admin.incidents.pipe_cfg_hint') ?></p>

        <form method="post" id="pipeline-inc-cfg-form-<?= $lvl ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save_pipeline_inc_cfg">
            <?php
            // Pass hidden fields for all other pipeline levels unchanged
            foreach ($PIPE_LEVELS as $other):
                if ($other === $lvl) continue;
                $pco = $pipeConfig[$other];
            ?>
            <input type="hidden" name="pinc_<?= $other ?>_loss_add_min"   value="<?= $pco['loss_add_min'] ?>">
            <input type="hidden" name="pinc_<?= $other ?>_loss_add_max"   value="<?= $pco['loss_add_max'] ?>">
            <input type="hidden" name="pinc_<?= $other ?>_cond_drop_min"  value="<?= $pco['cond_drop_min'] ?>">
            <input type="hidden" name="pinc_<?= $other ?>_cond_drop_max"  value="<?= $pco['cond_drop_max'] ?>">
            <input type="hidden" name="pinc_<?= $other ?>_base_chance"    value="<?= $pco['base_chance'] ?>">
            <?php endforeach ?>

            <div class="inc-cfg-block">
                <div class="inc-cfg-grid">

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_loss_add') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="pinc_<?= $lvl ?>_loss_add_min"
                                   value="<?= $pc['loss_add_min'] ?>" min="0" max="10" step="0.1" class="gm-input--short"
                                   title="<?= t('admin.incidents.field_min') ?>">
                            <span class="range-sep"></span>
                            <input type="number" name="pinc_<?= $lvl ?>_loss_add_max"
                                   value="<?= $pc['loss_add_max'] ?>" min="0" max="10" step="0.1" class="gm-input--short"
                                   title="<?= t('admin.incidents.field_max') ?>">
                            <span class="inc-cfg-unit">%</span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: <?= $pd['loss_add_min'] ?><?= $pd['loss_add_max'] ?>%)</span>
                        </div>
                    </div>

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_cond_drop') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="pinc_<?= $lvl ?>_cond_drop_min"
                                   value="<?= $pc['cond_drop_min'] ?>" min="0" max="50" step="0.1" class="gm-input--short">
                            <span class="range-sep"></span>
                            <input type="number" name="pinc_<?= $lvl ?>_cond_drop_max"
                                   value="<?= $pc['cond_drop_max'] ?>" min="0" max="50" step="0.1" class="gm-input--short">
                            <span class="inc-cfg-unit"><?= t('admin.incidents.cfg_unit_pts') ?></span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: <?= $pd['cond_drop_min'] ?><?= $pd['cond_drop_max'] ?>)</span>
                        </div>
                    </div>

                    <div class="inc-cfg-row">
                        <label class="inc-cfg-label"><?= t('admin.incidents.field_base_chance') ?></label>
                        <div class="inc-cfg-range">
                            <input type="number" name="pinc_<?= $lvl ?>_base_chance"
                                   value="<?= $pc['base_chance'] ?>" min="0.0001" max="1" step="0.0001" class="gm-input--short">
                            <span class="inc-cfg-unit">/h</span>
                            <span class="inc-cfg-default">(<?= t('admin.incidents.cfg_default') ?>: <?= $pd['base_chance'] ?>)</span>
                        </div>
                    </div>

                </div>
            </div>

            <div class="form-row form-row--gap">
                <button type="submit" class="btn btn-primary"> <?= t('admin.incidents.btn_save') ?></button>
                <button type="button" class="btn btn-danger"
                        onclick="confirmAction('<?= t('admin.incidents.confirm_reset') ?>', function(){ document.getElementById('pipeline-inc-reset-form').submit(); })">
                     <?= t('admin.incidents.btn_reset') ?>
                </button>
            </div>
        </form>
    </section>
</div>
<?php endforeach ?>

<?= $formPipeReset ?>

<!--  TAB: HISTORIA INCYDENTOW  -->
<div id="inc-tab-recent" class="admin-tab-content" role="tabpanel">
    <section class="panel" aria-label="<?= t('admin.incidents.history_title') ?>">
        <p class="panel-title"> <?= t('admin.incidents.history_title') ?>
            <span class="badge badge-inactive"><?= $hTotal ?></span>
        </p>

        <!-- Filtr -->
        <form method="get" class="inc-history-filter">
            <input type="hidden" name="hpage" value="1">
            <div class="inc-filter-field">
                <label class="form-label"><?= t('admin.incidents.filter_source') ?></label>
                <select name="hsource" class="trend-input-cat">
                    <option value=""><?= t('admin.incidents.source_all') ?></option>
                    <option value="well"     <?= $hSource === 'well'     ? 'selected' : '' ?>><?= t('admin.incidents.source_well') ?></option>
                    <option value="pipeline" <?= $hSource === 'pipeline' ? 'selected' : '' ?>><?= t('admin.incidents.source_pipeline') ?></option>
                </select>
            </div>
            <div class="inc-filter-field">
                <label class="form-label"><?= t('admin.incidents.filter_level') ?></label>
                <select name="hlevel" class="trend-input-cat">
                    <option value=""><?= t('admin.incidents.filter_all') ?></option>
                    <?php foreach ($LEVELS as $lvl): ?>
                    <option value="<?= $lvl ?>" <?= $hLevel === $lvl ? 'selected' : '' ?>>
                        <?= htmlspecialchars($levelMeta[$lvl][1]) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="inc-filter-field">
                <label class="form-label"><?= t('admin.incidents.filter_player') ?></label>
                <input type="text" name="hplayer" value="<?= htmlspecialchars($hPlayer) ?>"
                       class="trend-input-name" placeholder="<?= t('admin.incidents.filter_placeholder') ?>">
            </div>
            <div class="inc-filter-field">
                <label class="form-label"><?= t('admin.incidents.filter_status') ?></label>
                <select name="hstatus" class="trend-input-cat">
                    <option value=""><?= t('admin.incidents.filter_all') ?></option>
                    <option value="open"     <?= $hStatus === 'open'     ? 'selected' : '' ?>><?= t('admin.incidents.status_open') ?></option>
                    <option value="repaired" <?= $hStatus === 'repaired' ? 'selected' : '' ?>><?= t('admin.incidents.status_repaired') ?></option>
                    <option value="auto"     <?= $hStatus === 'auto'     ? 'selected' : '' ?>><?= t('admin.incidents.status_auto') ?></option>
                </select>
            </div>
            <div class="inc-filter-field">
                <label class="form-label"><?= t('admin.incidents.filter_days') ?></label>
                <input type="number" name="hdays" value="<?= $hDays ?: '' ?>"
                       min="0" max="3650" class="trend-input-num" placeholder="np. 7">
            </div>
            <div class="inc-filter-field">
                <label class="form-label"><?= t('admin.incidents.filter_per_page') ?></label>
                <select name="hper" class="trend-input-num">
                    <?php foreach ([10,20,50,100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $hPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="inc-filter-field inc-filter-btns">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-secondary"><?= t('admin.incidents.btn_filter') ?></button>
                <a href="?hpage=1" class="btn btn-secondary"><?= t('admin.incidents.btn_clear') ?></a>
            </div>
        </form>

        <!-- Bulk delete -->
        <form method="post" id="inc-bulk-del-form" class="inc-bulk-del">
            <?= CSRF::field() ?>
            <input type="hidden" name="action"        value="delete_history_bulk">
            <input type="hidden" name="filter_level"  value="<?= htmlspecialchars($hLevel) ?>">
            <input type="hidden" name="filter_player" value="<?= htmlspecialchars($hPlayer) ?>">
            <input type="hidden" name="filter_status" value="<?= htmlspecialchars($hStatus) ?>">
            <input type="hidden" name="filter_days"   value="<?= $hDays ?>">
            <button type="button" class="btn btn-danger btn-xs"
                    onclick="confirmSubmit(this, '<?= t('admin.incidents.confirm_bulk') ?>')">
                 <?= t('admin.incidents.btn_bulk_delete') ?>
                <?php if ($hLevel || $hPlayer || $hStatus || $hDays): ?>
                    <?= t('admin.incidents.bulk_filter_active') ?>
                <?php else: ?>
                    <?= t('admin.incidents.bulk_filter_all') ?>
                <?php endif ?>
            </button>
        </form>

        <!-- Retencja / auto-cleanup -->
        <div class="inc-retention-bar">
            <span class="inc-retention-label"><?= t('admin.incidents.retention_title') ?>:</span>
            <form method="post" class="inc-retention-form" id="inc-retention-save-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="save_retention">
                <label class="inc-retention-field">
                    <?= t('admin.incidents.retention_label') ?>
                    <input type="number" name="retention_days" id="inc-retention-days-input"
                           value="<?= (int)$retentionDays ?>" min="1" max="3650" class="gm-input--short">
                    <?= t('admin.incidents.retention_days') ?>
                </label>
                <button type="submit" class="btn btn-secondary btn-xs"><?= t('admin.incidents.btn_save_retention') ?></button>
            </form>
            <form method="post" class="inc-retention-run" id="inc-retention-run-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="run_retention_now">
                <input type="hidden" name="retention_days" id="inc-retention-run-days" value="<?= (int)$retentionDays ?>">
                <button type="button" class="btn btn-danger btn-xs"
                        onclick="
                            var d = document.getElementById('inc-retention-days-input').value;
                            document.getElementById('inc-retention-run-days').value = d;
                            confirmSubmit(this, '<?= t('admin.incidents.confirm_run_now') ?> (' + d + ' <?= t('admin.incidents.retention_days') ?>)');
                        ">
                     <?= t('admin.incidents.btn_run_now') ?>
                </button>
            </form>
            <span class="panel-hint"><?= t('admin.incidents.retention_hint') ?></span>
        </div>

        <!-- Lista -->
        <?php if (empty($recentIncidents)): ?>
        <p class="muted list-empty-msg"><?= t('admin.incidents.history_empty') ?></p>
        <?php else: ?>
        <div class="table-scroll-wrap">
        <div class="data-list inc-history-list inc-history-list--wide">
            <div class="list-header" role="row">
                <span>#</span>
                <span><?= t('admin.incidents.col_source') ?></span>
                <span><?= t('admin.incidents.col_level') ?></span>
                <span><?= t('admin.incidents.col_player') ?></span>
                <span><?= t('admin.incidents.col_well') ?></span>
                <span><?= t('admin.incidents.col_cause') ?></span>
                <span><?= t('admin.incidents.col_drop') ?></span>
                <span><?= t('admin.incidents.col_cost') ?></span>
                <span><?= t('admin.incidents.col_status') ?></span>
                <span><?= t('admin.incidents.col_date') ?></span>
                <span></span>
            </div>
            <?php foreach ($recentIncidents as $inc):
                $isPipeline = ($inc['src'] === 'pipeline');

                if ($isPipeline) {
                    // Map pipeline level (micro/minor/medium) to pipeLevelMeta key
                    $pipeKey = 'pipe_' . ($inc['level'] ?? 'micro');
                    $pmeta   = $pipeLevelMeta[$pipeKey] ?? [t('admin.incidents.pipe_micro_label'), 'badge-blue', ''];
                    $lvlCls  = $pmeta[1];
                    $lvlText = $pmeta[0];
                } else {
                    $lm      = $levelMeta[$inc['level']] ?? ['', $inc['level'], 'badge-neutral', ''];
                    $lvlCls  = $lm[2];
                    $lvlText = $lm[1];
                }
            ?>
            <article class="list-row" role="row" title="<?= htmlspecialchars($inc['message'] ?? '') ?>">
                <span class="muted"><?= (int)$inc['id'] ?></span>
                <span>
                    <?php if ($isPipeline): ?>
                    <span class="badge badge-blue"><?= t('admin.incidents.source_pipeline') ?></span>
                    <?php else: ?>
                    <span class="badge badge-inactive"><?= t('admin.incidents.source_well') ?></span>
                    <?php endif ?>
                </span>
                <span><span class="badge <?= $lvlCls ?>"><?= $lvlText ?></span></span>
                <span><?= htmlspecialchars($inc['player_name'] ?? '') ?></span>
                <span>
                    #<?= (int)$inc['well_id'] ?><?= !empty($inc['well_name']) ? ' '.htmlspecialchars($inc['well_name']) : '' ?>
                    <?php if ($isPipeline && !empty($inc['pipeline_id'])): ?>
                    <span class="muted">(rur.#<?= (int)$inc['pipeline_id'] ?>)</span>
                    <?php endif ?>
                </span>
                <span><?= htmlspecialchars((string)($inc['cause_type'] ?? '')) ?></span>
                <?php if (!$isPipeline): ?>
                <span class="<?= ($inc['prod_drop'] ?? 0) >= 40 ? 'text-red' : '' ?>"><?= $inc['prod_drop'] ?>%</span>
                <span><?= ($inc['cost'] ?? 0) > 0 ? '$'.number_format((float)$inc['cost'],0,'.',' ') : '' ?></span>
                <span>
                    <?= $inc['repaired_at']
                        ? ''
                        : ($inc['auto_repair'] ? t('admin.incidents.status_auto_short') : '<span class="text-red">' . t('admin.incidents.status_open_short') . '</span>') ?>
                </span>
                <?php else: ?>
                <span class="muted">—</span>
                <span class="muted">—</span>
                <span class="muted">—</span>
                <?php endif ?>
                <span class="muted"><?= date('d.m H:i', strtotime($inc['created_at'])) ?></span>
                <span>
                    <?php if (!$isPipeline): ?>
                    <form method="post" class="form-inline">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action"      value="delete_incident">
                        <input type="hidden" name="incident_id" value="<?= (int)$inc['id'] ?>">
                        <button type="button" class="btn btn-danger btn-xs"
                                onclick="confirmSubmit(this, '<?= t('admin.incidents.confirm_delete_one') ?>')"></button>
                    </form>
                    <?php endif ?>
                </span>
            </article>
            <?php endforeach ?>
        </div>
        </div>

        <!-- Paginacja -->
        <?php if ($hTotalPages > 1): ?>
        <div class="pagination">
            <?php
            $baseUrl = '?hsource=' . urlencode($hSource)
                     . '&hlevel=' . urlencode($hLevel)
                     . '&hplayer=' . urlencode($hPlayer)
                     . '&hstatus=' . urlencode($hStatus)
                     . '&hdays='   . $hDays
                     . '&hper='    . $hPerPage;
            $start = max(1, $hPage - 3);
            $end   = min($hTotalPages, $hPage + 3);
            if ($hPage > 1): ?>
            <a href="<?= $baseUrl ?>&hpage=1" class="page-btn"></a>
            <a href="<?= $baseUrl ?>&hpage=<?= $hPage - 1 ?>" class="page-btn"></a>
            <?php endif ?>
            <?php for ($p = $start; $p <= $end; $p++): ?>
            <a href="<?= $baseUrl ?>&hpage=<?= $p ?>"
               class="page-btn <?= $p === $hPage ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor ?>
            <?php if ($hPage < $hTotalPages): ?>
            <a href="<?= $baseUrl ?>&hpage=<?= $hPage + 1 ?>" class="page-btn"></a>
            <a href="<?= $baseUrl ?>&hpage=<?= $hTotalPages ?>" class="page-btn"></a>
            <?php endif ?>
            <span class="pagination-info">
                <?= t('admin.incidents.pagination_info', ['page' => $hPage, 'total' => $hTotalPages, 'count' => $hTotal]) ?>
            </span>
        </div>
        <?php endif ?>
        <?php endif ?>
    </section>
</div>

<!--  TAB: POMOC  -->
<div id="inc-tab-help" class="admin-tab-content" role="tabpanel">
    <section class="panel" aria-label="<?= t('admin.incidents.help_title') ?>">
        <p class="panel-title"> <?= t('admin.incidents.help_title') ?></p>
        <p class="panel-hint"><?= t('admin.incidents.help_intro') ?></p>

        <div class="inc-help-grid">
            <div class="inc-help-card">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_micro_title') ?></p>
                <p><?= t('admin.incidents.help_micro_body') ?></p>
            </div>
            <div class="inc-help-card">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_minor_title') ?></p>
                <p><?= t('admin.incidents.help_minor_body') ?></p>
            </div>
            <div class="inc-help-card">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_medium_title') ?></p>
                <p><?= t('admin.incidents.help_medium_body') ?></p>
            </div>
            <div class="inc-help-card">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_major_title') ?></p>
                <p><?= t('admin.incidents.help_major_body') ?></p>
            </div>
            <div class="inc-help-card inc-help-card--wide">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_chance_title') ?></p>
                <p><?= t('admin.incidents.help_chance_body') ?></p>
            </div>
            <div class="inc-help-card inc-help-card--wide">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_config_title') ?></p>
                <p><?= t('admin.incidents.help_config_body') ?></p>
            </div>
            <div class="inc-help-card inc-help-card--wide">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_basechance_title') ?></p>
                <p><?= t('admin.incidents.help_basechance_body') ?></p>
            </div>
            <div class="inc-help-card inc-help-card--wide">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_mults_title') ?></p>
                <p><?= t('admin.incidents.help_mults_body') ?></p>
            </div>
            <div class="inc-help-card inc-help-card--wide">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_staff_title') ?></p>
                <p><?= t('admin.incidents.help_staff_body') ?></p>
            </div>
            <div class="inc-help-card">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_floor_title') ?></p>
                <p><?= t('admin.incidents.help_floor_body') ?></p>
            </div>
            <div class="inc-help-card">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_bhp_title') ?></p>
                <p><?= t('admin.incidents.help_bhp_body') ?></p>
            </div>
            <div class="inc-help-card inc-help-card--wide">
                <p class="inc-help-card-title"><?= t('admin.incidents.help_tips_title') ?></p>
                <p><?= t('admin.incidents.help_tips_body') ?></p>
            </div>
        </div>
    </section>
</div>

<!--  TAB: WYWOAJ INCYDENT  -->
<div id="inc-tab-trigger" class="admin-tab-content" role="tabpanel">
    <section class="panel" aria-label="<?= t('admin.incidents.trig_title') ?>">
        <p class="panel-title"> <?= t('admin.incidents.trig_title') ?></p>
        <p class="panel-hint"><?= t('admin.incidents.trig_hint') ?></p>

        <form method="post" id="inc-trig-form" class="inc-trig-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="trigger_incident">

            <div class="inc-trig-row">
                <!-- Gracz -->
                <div class="inc-trig-field">
                    <label class="form-label" for="trig-player"><?= t('admin.incidents.trig_player') ?></label>
                    <select name="trig_player_id" id="trig-player" class="trend-input-cat" onchange="incTrigUpdateWells(this.value)" required>
                        <option value=""><?= t('admin.incidents.trig_select_player') ?></option>
                        <?php foreach ($trigPlayers as $pid => $uname): ?>
                        <option value="<?= $pid ?>"><?= htmlspecialchars($uname) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <!-- Odwiert -->
                <div class="inc-trig-field">
                    <label class="form-label" for="trig-well"><?= t('admin.incidents.trig_well') ?></label>
                    <select name="trig_well_id" id="trig-well" class="trend-input-cat" required>
                        <option value=""><?= t('admin.incidents.trig_select_well') ?></option>
                    </select>
                </div>
            </div>

            <!-- Poziom incydentu -->
            <p class="form-label"><?= t('admin.incidents.trig_level_label') ?></p>
            <div class="inc-trig-levels">
                <?php
                $trigLevelMeta = [
                    'micro'  => ['', t('admin.incidents.level_micro'),  'trig-card--micro',  t('admin.incidents.trig_micro_desc')],
                    'minor'  => ['', t('admin.incidents.level_minor'),  'trig-card--minor',  t('admin.incidents.trig_minor_desc')],
                    'medium' => ['', t('admin.incidents.level_medium'), 'trig-card--medium', t('admin.incidents.trig_medium_desc')],
                    'major'  => ['', t('admin.incidents.level_major'),  'trig-card--major',  t('admin.incidents.trig_major_desc')],
                ];
                foreach ($trigLevelMeta as $lvl => [$icon, $label, $cls, $desc]):
                    $c = $config[$lvl];
                ?>
                <label class="inc-trig-card <?= $cls ?>">
                    <input type="radio" name="trig_level" value="<?= $lvl ?>" required>
                    <span class="trig-card-icon"><?= $icon ?></span>
                    <span class="trig-card-label"><?= $label ?></span>
                    <span class="trig-card-range">
                        <?= t('admin.incidents.cfg_default') ?>:
                        drop <?= (int)$c['prod_drop_min'] ?><?= (int)$c['prod_drop_max'] ?>%,
                        <?= (int)$c['hours_min'] ?><?= (int)$c['hours_max'] ?>h
                        <?php if ($c['cost_max'] > 0): ?>, koszt $<?= number_format($c['cost_min'],0,'.','.')?>$<?= number_format($c['cost_max'],0,'.','.') ?><?php endif ?>
                    </span>
                    <span class="trig-card-desc"><?= $desc ?></span>
                </label>
                <?php endforeach ?>
            </div>

            <div class="form-row form-row--gap mt-md">
                <button type="button" class="btn btn-danger"
                        onclick="confirmSubmit(this, '<?= t('admin.incidents.trig_confirm') ?>')">
                     <?= t('admin.incidents.trig_btn') ?>
                </button>
            </div>
        </form>
    </section>

    <!-- Sekcja: wywolanie incydentu rurociagu -->
    <section class="panel mt-md" aria-label="<?= t('admin.incidents.trig_pipe_title') ?>">
        <p class="panel-title">⚡ <?= t('admin.incidents.trig_pipe_title') ?></p>
        <p class="panel-hint"><?= t('admin.incidents.trig_pipe_hint') ?></p>

        <form method="post" id="inc-trig-pipe-form" class="inc-trig-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="trigger_pipeline_incident">

            <div class="inc-trig-row">
                <!-- Gracz -->
                <div class="inc-trig-field">
                    <label class="form-label" for="trig-pipe-player"><?= t('admin.incidents.trig_pipe_player') ?></label>
                    <select name="trig_pipe_player_id" id="trig-pipe-player" class="trend-input-cat"
                            onchange="incTrigUpdatePipelines(this.value)" required>
                        <option value=""><?= t('admin.incidents.trig_pipe_select_player') ?></option>
                        <?php foreach ($trigPlayers as $pid => $uname): ?>
                        <option value="<?= $pid ?>"><?= htmlspecialchars($uname) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <!-- Rurociag -->
                <div class="inc-trig-field">
                    <label class="form-label" for="trig-pipe-pipeline"><?= t('admin.incidents.trig_pipe_pipeline') ?></label>
                    <select name="trig_pipeline_id" id="trig-pipe-pipeline" class="trend-input-cat" required>
                        <option value=""><?= t('admin.incidents.trig_pipe_select_pipeline') ?></option>
                    </select>
                </div>
            </div>

            <!-- Poziom incydentu rurociagu -->
            <p class="form-label"><?= t('admin.incidents.trig_pipe_level_label') ?></p>
            <div class="inc-trig-levels">
                <?php
                $trigPipeLevelMeta = [
                    'pipe_micro'  => ['', t('admin.incidents.pipe_micro_label'),  'trig-card--micro',  t('admin.incidents.trig_pipe_micro_desc')],
                    'pipe_minor'  => ['', t('admin.incidents.pipe_minor_label'),  'trig-card--minor',  t('admin.incidents.trig_pipe_minor_desc')],
                    'pipe_medium' => ['', t('admin.incidents.pipe_medium_label'), 'trig-card--medium', t('admin.incidents.trig_pipe_medium_desc')],
                ];
                foreach ($trigPipeLevelMeta as $lvl => [$icon, $label, $cls, $desc]):
                    $pc = $pipeConfig[$lvl];
                ?>
                <label class="inc-trig-card <?= $cls ?>">
                    <input type="radio" name="trig_pipe_level" value="<?= $lvl ?>" required>
                    <span class="trig-card-icon"><?= $icon ?></span>
                    <span class="trig-card-label"><?= $label ?></span>
                    <span class="trig-card-range">
                        loss +<?= $pc['loss_add_min'] ?>–<?= $pc['loss_add_max'] ?>%,
                        cond -<?= $pc['cond_drop_min'] ?>–<?= $pc['cond_drop_max'] ?> pkt
                    </span>
                    <span class="trig-card-desc"><?= $desc ?></span>
                </label>
                <?php endforeach ?>
            </div>

            <div class="form-row form-row--gap mt-md">
                <button type="button" class="btn btn-danger"
                        onclick="confirmSubmit(this, '<?= t('admin.incidents.trig_pipe_confirm') ?>')">
                     <?= t('admin.incidents.trig_pipe_btn') ?>
                </button>
            </div>
        </form>
    </section>

    <!-- Dane odwiertow per gracz jako JSON dla JS -->
    <script>
    var incTrigWells = <?= json_encode($trigWells, JSON_UNESCAPED_UNICODE) ?>;
    function incTrigUpdateWells(playerId) {
        var sel = document.getElementById('trig-well');
        sel.innerHTML = '<option value=""><?= t('admin.incidents.trig_select_well') ?></option>';
        var wells = incTrigWells[playerId] || [];
        wells.forEach(function(w) {
            var opt = document.createElement('option');
            opt.value = w.id;
            opt.textContent = w.location_name + ' [' + w.status + ', cond:' + w.technical_condition + '%]';
            sel.appendChild(opt);
        });
    }
    </script>
    <script>
    /* Pipeline trigger data and selector */
    var incTrigPipelines = <?= json_encode($trigPipelines, JSON_UNESCAPED_UNICODE) ?>;
    function incTrigUpdatePipelines(playerId) {
        var sel = document.getElementById('trig-pipe-pipeline');
        sel.innerHTML = '<option value=""><?= t('admin.incidents.trig_pipe_select_pipeline') ?></option>';
        var pipes = incTrigPipelines[playerId] || [];
        pipes.forEach(function(p) {
            var opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name + ' [' + p.status + ', cond:' + p.condition_pct + '%, loss:' + p.transport_loss + '%]';
            sel.appendChild(opt);
        });
    }
    </script>
</div><!-- end inc-tab-trigger -->

<script src="/assets/js/admin_incidents.js"></script>
