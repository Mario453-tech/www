<?php extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.wells.title') ?></h1>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif ?>

<div class="admin-tabs" role="tablist">
    <button onclick="wellsShowTab('config')"  id="tab-btn-config"  class="admin-tab" role="tab"><?= t('admin.wells.tab_config') ?></button>
    <button onclick="wellsShowTab('sell')"    id="tab-btn-sell"    class="admin-tab" role="tab"><?= t('admin.wells.tab_sell') ?></button>
    <button onclick="wellsShowTab('wells')"   id="tab-btn-wells"   class="admin-tab" role="tab"><?= t('admin.wells.tab_wells') ?> (<?= count($wells) ?>)</button>
    <button onclick="wellsShowTab('events')"  id="tab-btn-events"  class="admin-tab" role="tab"><?= t('admin.wells.tab_events') ?> (<?= count($events) ?>)</button>
    <button onclick="wellsShowTab('help')"    id="tab-btn-help"    class="admin-tab" role="tab"><?= t('admin.wells.tab_help') ?></button>
</div>

<?php
    $catMain   = ['drilling', 'opex', 'production', 'maintenance', 'repair', 'upgrade', 'market', 'incident', 'crisis', 'balance'];
    $catSell   = ['sell'];
    $catSystem = ['system'];
    $groupMain   = array_intersect_key($grouped, array_flip($catMain));
    $groupSell   = array_intersect_key($grouped, array_flip($catSell));
    $groupSystem = array_intersect_key($grouped, array_flip($catSystem));
    $catKnown  = array_merge($catMain, $catSell, $catSystem);
    foreach ($grouped as $cat => $rows) {
        if (!in_array($cat, $catKnown, true)) $groupMain[$cat] = $rows;
    }

    function wellsRenderSection(string $cat, array $rows, bool $hideTitle = false): void {
        if (!$hideTitle) {
            echo '<div class="config-section-title">' . t('admin.wells.cat.' . $cat) . '</div>';
        }
        echo '<div class="config-section"><div class="config-rows">';
        foreach ($rows as $r) {
            $lKey  = 'admin.wells.key.' . $r['key'];
            $label = t($lKey) !== $lKey ? t($lKey) : htmlspecialchars($r['label']);
            $key   = htmlspecialchars($r['key']);
            $val   = number_format((float)$r['value'], 2, '.', ' ');
            echo '<div class="config-row">';
            echo   '<div>';
            echo     '<div class="config-key-label">' . $label . '</div>';
            echo     '<div class="config-key-code">' . $key . '</div>';
            echo   '</div>';
            echo   '<input class="config-input" type="text" name="config[' . $key . ']" value="' . $val . '">';
            echo '</div>';
        }
        echo '</div></div>';
    }
?>

<!-- TAB: Parametry -->
<div id="tab-config" class="admin-tab-content" role="tabpanel">
    <form method="POST">
    <?php foreach ($groupMain as $cat => $rows): wellsRenderSection($cat, $rows); endforeach ?>
    <div class="config-save-bar">
        <button type="submit" class="btn btn-primary"><?= t('admin.wells.config_save') ?></button>
    </div>
    </form>
</div>

<!-- TAB: Wycena i sprzedaz -->
<div id="tab-sell" class="admin-tab-content" role="tabpanel">
    <form method="POST">
    <?php foreach ($groupSell as $cat => $rows): wellsRenderSection($cat, $rows); endforeach ?>
    <?php if ($groupSystem): ?>
    <div class="config-group-separator config-group-separator--system">
        <span><?= t('admin.wells.group_system') ?></span>
    </div>
    <?php foreach ($groupSystem as $cat => $rows): wellsRenderSection($cat, $rows, true); endforeach ?>
    <?php endif ?>
    <div class="config-save-bar">
        <button type="submit" class="btn btn-primary"><?= t('admin.wells.config_save') ?></button>
    </div>
    </form>
</div>

<!-- TAB: Aktywne odwierty (GM) -->
<div id="tab-wells" class="admin-tab-content" role="tabpanel">

    <!-- Player filter / Filtr gracza -->
    <form method="GET" action="" class="aw-player-filter" id="aw-filter-form">
        <input type="hidden" name="tab" value="wells">
        <label class="aw-filter-label" for="aw-pid-sel"><?= t('admin.wells.filter_player') ?></label>
        <select id="aw-pid-sel" name="pid" class="aw-filter-select" onchange="this.form.submit()">
            <option value="0"><?= t('admin.wells.filter_all') ?></option>
            <?php foreach ($players as $pl): ?>
            <option value="<?= (int)$pl['id'] ?>"<?= (int)$pl['id'] === $filterPlayerId ? ' selected' : '' ?>>
                #<?= (int)$pl['id'] ?> — <?= htmlspecialchars((string)$pl['label']) ?>
            </option>
            <?php endforeach ?>
        </select>
        <?php if ($filterPlayerId > 0): ?>
        <a href="?tab=wells" class="btn btn-xs btn-secondary"><?= t('admin.wells.filter_clear') ?></a>
        <?php endif ?>
        <span class="aw-filter-count"><?= count($wells) ?> <?= t('admin.wells.filter_count') ?></span>
    </form>

    <?php if (empty($wells)): ?>
        <p class="empty-state"><?= t('admin.wells.wells_empty') ?></p>
    <?php else: ?>

    <div class="data-list">
        <!-- List header / Naglowek listy -->
        <div class="aw-list-head">
            <span><?= t('admin.wells.col_id') ?></span>
            <span><?= t('admin.wells.col_player') ?></span>
            <span><?= t('admin.wells.col_status') ?></span>
            <span><?= t('admin.wells.col_cond') ?></span>
            <span><?= t('admin.wells.col_type') ?></span>
            <span><?= t('admin.wells.col_transport') ?></span>
            <span><?= t('admin.wells.col_prod') ?></span>
            <span><?= t('admin.wells.col_hub') ?></span>
            <span><?= t('admin.wells.col_upgrades') ?></span>
            <span></span>
        </div>

        <?php foreach ($wells as $w):
            $wId          = (int)$w['id'];
            $cond         = (int)($w['technical_condition'] ?? 100);
            $condClass    = $cond >= 70 ? 'cond-ok' : ($cond >= 40 ? 'cond-warn' : 'cond-bad');
            $status       = (string)($w['status'] ?? '') ?: 'active';
            $sBadge       = match($status) {
                'active'         => 'badge-active',
                'seized','sold'  => 'badge-bankrupt',
                default          => 'badge-paused',
            };
            $transport    = (string)($w['transport_type'] ?? 'rurociag');
            $transportIcon = match($transport) {
                'rurociag'   => '|',
                'ciezarowki' => 'T',
                'tankowiec'  => 'S',
                default      => '?',
            };
            $pMode        = (string)($w['production_mode'] ?? 'normal');
            $pModeClass   = match($pMode) { 'boost' => 'c-warn', 'eco' => 'c-good', default => '' };
            $upgrades     = array_filter(explode(',', $w['upgrade_list'] ?? ''));
            $hubInfo      = $hubByWell[$wId] ?? null;

            $pressure     = (float)($w['pressure']            ?? 1.0);
            $resRem       = (float)($w['reservoir_remaining'] ?? 0);
            $resMax       = max(1.0, (float)($w['reservoir_max'] ?? 800000));
            $depletion    = max(0.10, min(1.0, $resRem / $resMax));
            $effP         = round($pressure * $depletion * 100, 1);
            $effPClass    = $effP >= 80 ? 'cv-good' : ($effP >= 50 ? 'cv-warn' : 'cv-bad');
            $depletionPct = round($resRem / $resMax * 100, 1);

            $wearLevel    = (float)($w['wear_level'] ?? 0);
            $riskScore    = (float)($w['risk_score'] ?? 0);
            $eqTier       = (string)($w['equipment_tier'] ?? 'standard');
        ?>
        <article class="aw-list-row aw-list-row--expandable" id="awell-<?= $wId ?>">
            <span class="muted">#<?= $wId ?></span>
            <span class="aw-col-player"><?= htmlspecialchars($w['username']) ?></span>
            <span><span class="badge <?= $sBadge ?>"><?= t('well.status.' . $status) ?></span></span>
            <span class="<?= $condClass ?>"><?= $cond ?>%</span>
            <span class="muted"><?= htmlspecialchars($w['well_type'] ?? 'onshore') ?></span>
            <span class="aw-transport-badge aw-transport-<?= $transport ?>"><?= t('admin.wells.transport.' . $transport) ?></span>
            <span><?= number_format((float)$w['base_production_per_hour'], 1) ?> <?= t('common.bbl') ?></span>
            <span class="muted">
                <?php if ($hubInfo): ?>
                <span class="aw-hub-badge" title="#<?= (int)$hubInfo['hub_id'] ?>"><?= htmlspecialchars((string)$hubInfo['hub_name']) ?></span>
                <?php else: ?>—<?php endif ?>
            </span>
            <span>
                <?php foreach ($upgrades as $u): ?>
                <span class="badge-up"><?= htmlspecialchars(trim($u)) ?></span>
                <?php endforeach ?>
            </span>
            <span>
                <button type="button" class="btn-admin-edit" onclick="awToggle(<?= $wId ?>)" title="<?= t('admin.wells.gm_edit_title') ?>"></button>
            </span>
        </article>

        <!-- GM edit panel / Panel edycji GM -->
        <div class="aw-gm-panel" id="aw-form-<?= $wId ?>" style="display:none">
            <form method="POST" class="aw-gm-form">
                <input type="hidden" name="gm_edit_well_id" value="<?= $wId ?>">
                <input type="hidden" name="gm_upgrades_submitted" value="1">

                <div class="aw-gm-header">
                    <strong><?= t('admin.wells.gm_panel_title', ['id' => $wId]) ?></strong>
                    <span class="muted"><?= htmlspecialchars((string)($w['location_name'] ?? $w['location'] ?? '')) ?></span>
                    <span class="muted">
                        <?= t('admin.wells.gm_well_type') ?>: <em><?= htmlspecialchars($w['well_type'] ?? 'onshore') ?></em>
                        &nbsp;|&nbsp; <?= t('admin.wells.gm_layer') ?>: <em><?= (int)($w['active_layer_id'] ?? 1) ?></em>
                        &nbsp;|&nbsp; <?= t('admin.wells.gm_created') ?>: <em><?= date('d.m.Y', strtotime($w['created_at'])) ?></em>
                    </span>
                </div>

                <div class="aw-gm-sections">

                    <!-- Section: Status i produkcja -->
                    <div class="aw-gm-section">
                        <div class="aw-gm-section-title"><?= t('admin.wells.gm_sec_status') ?></div>
                        <div class="aw-gm-fields">
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_status') ?></label>
                                <select name="status" class="aw-select">
                                    <?php foreach (['active','paused_storage','paused_cash','paused_staff','no_operator','no_technician','broken','blowout','contaminated','seized','layer_switch','sold'] as $sv): ?>
                                    <option value="<?= $sv ?>"<?= $status === $sv ? ' selected' : '' ?>><?= $sv ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_prod_mode') ?></label>
                                <select name="production_mode" class="aw-select">
                                    <?php foreach (['eco','normal','boost'] as $pm): ?>
                                    <option value="<?= $pm ?>"<?= $pMode === $pm ? ' selected' : '' ?>><?= $pm ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_cond') ?></label>
                                <input type="text" name="technical_condition" class="aw-input" value="<?= $cond ?>" placeholder="0–100">
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_wear') ?></label>
                                <input type="text" name="wear_level" class="aw-input" value="<?= number_format($wearLevel, 2, '.', '') ?>" placeholder="0.00+">
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_prod_bph') ?></label>
                                <input type="text" name="base_production_per_hour" class="aw-input" value="<?= number_format((float)$w['base_production_per_hour'], 2, '.', '') ?>">
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_opex') ?></label>
                                <input type="text" name="upkeep_cost_per_hour" class="aw-input" value="<?= number_format((float)$w['upkeep_cost_per_hour'], 2, '.', '') ?>">
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_prod_boost') ?></label>
                                <input type="text" name="production_boost_pct" class="aw-input" value="<?= number_format((float)($w['production_boost_pct'] ?? 0), 2, '.', '') ?>" placeholder="%">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Transport -->
                    <div class="aw-gm-section">
                        <div class="aw-gm-section-title"><?= t('admin.wells.gm_sec_transport') ?></div>
                        <div class="aw-gm-fields">
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_transport_type') ?></label>
                                <select name="transport_type" class="aw-select">
                                    <?php foreach (['rurociag','ciezarowki','tankowiec'] as $tt): ?>
                                    <option value="<?= $tt ?>"<?= $transport === $tt ? ' selected' : '' ?>><?= t('admin.wells.transport.' . $tt) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_transport_cap') ?></label>
                                <input type="text" name="transport_capacity_pct" class="aw-input"
                                       value="<?= number_format((float)($w['transport_capacity_pct'] ?? 120), 2, '.', '') ?>" placeholder="%">
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_transport_opex') ?></label>
                                <input type="text" name="transport_opex_pct" class="aw-input"
                                       value="<?= number_format((float)($w['transport_opex_pct'] ?? 7.5), 2, '.', '') ?>" placeholder="%">
                            </div>
                            <?php if ($hubInfo): ?>
                            <div class="aw-field aw-field--info">
                                <label><?= t('admin.wells.gm_hub_assigned') ?></label>
                                <span>#<?= (int)$hubInfo['hub_id'] ?> <?= htmlspecialchars((string)$hubInfo['hub_name']) ?> [<?= htmlspecialchars((string)$hubInfo['hub_status']) ?>]</span>
                            </div>
                            <?php endif ?>
                        </div>
                    </div>

                    <!-- Section: Zlozze i cisnienie -->
                    <div class="aw-gm-section">
                        <div class="aw-gm-section-title"><?= t('admin.wells.gm_sec_reservoir') ?></div>
                        <div class="aw-gm-fields">
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_pressure') ?></label>
                                <input type="text" name="pressure" class="aw-input"
                                       value="<?= number_format($pressure, 2, '.', '') ?>" placeholder="0.00–2.00">
                                <span class="aw-field-hint"><?= t('admin.wells.gm_eff_pressure') ?>: <strong class="<?= $effPClass ?>"><?= $effP ?>%</strong></span>
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_res_rem') ?></label>
                                <input type="text" name="reservoir_remaining" class="aw-input"
                                       value="<?= number_format($resRem, 0, '.', '') ?>" placeholder="bbl">
                                <span class="aw-field-hint"><?= t('admin.wells.gm_depletion') ?>: <strong><?= $depletionPct ?>%</strong></span>
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_res_max') ?></label>
                                <input type="text" name="reservoir_max" class="aw-input"
                                       value="<?= number_format($resMax, 0, '.', '') ?>" placeholder="bbl">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Ryzyko i sprzet -->
                    <div class="aw-gm-section">
                        <div class="aw-gm-section-title"><?= t('admin.wells.gm_sec_risk') ?></div>
                        <div class="aw-gm-fields">
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_risk_level') ?></label>
                                <input type="text" name="risk_level" class="aw-input"
                                       value="<?= (int)($w['risk_level'] ?? 10) ?>" placeholder="1–10">
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_risk_score') ?></label>
                                <input type="text" name="risk_score" class="aw-input"
                                       value="<?= number_format($riskScore, 2, '.', '') ?>" placeholder="0–100">
                            </div>
                            <div class="aw-field">
                                <label><?= t('admin.wells.gm_field_eq_tier') ?></label>
                                <select name="equipment_tier" class="aw-select">
                                    <?php foreach (['black_market','standard','premium'] as $et): ?>
                                    <option value="<?= $et ?>"<?= $eqTier === $et ? ' selected' : '' ?>><?= t('admin.wells.eq_tier.' . $et) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="aw-field aw-field--readonly">
                                <label><?= t('admin.wells.gm_field_post_inc_risk') ?></label>
                                <span><?= number_format((float)($w['post_incident_risk_boost'] ?? 0), 4, '.', '') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Modernizacje -->
                    <div class="aw-gm-section">
                        <div class="aw-gm-section-title"><?= t('admin.wells.gm_sec_upgrades') ?></div>
                        <div class="aw-gm-upgrade-list">
                            <?php
                            $currentUpgrades = array_filter(explode(',', $w['upgrade_list'] ?? ''));
                            foreach (['pump_electric', 'monitoring', 'water_injection'] as $uType):
                                $checked = in_array($uType, $currentUpgrades, true);
                            ?>
                            <label class="aw-upgrade-checkbox<?= $checked ? ' aw-upgrade-checkbox--on' : '' ?>">
                                <input type="checkbox" name="gm_upgrades[]" value="<?= $uType ?>"<?= $checked ? ' checked' : '' ?>>
                                <span class="aw-upgrade-name"><?= t('admin.wells.upgrade.' . $uType) ?></span>
                                <?php if ($checked): ?>
                                <span class="badge-up"><?= t('admin.wells.upgrade_active') ?></span>
                                <?php endif ?>
                            </label>
                            <?php endforeach ?>
                        </div>
                    </div>

                </div><!-- .aw-gm-sections -->

                <div class="aw-gm-actions">
                    <button type="submit" class="btn btn-primary btn-sm"><?= t('admin.wells.gm_save') ?></button>
                    <button type="button" class="btn btn-sm" onclick="awToggle(<?= $wId ?>)"><?= t('admin.wells.edit_cancel') ?></button>
                    <span class="aw-gm-warn"><?= t('admin.wells.gm_save_warn') ?></span>
                </div>
            </form>
        </div>

        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<!-- TAB: Dziennik zdarzen -->
<div id="tab-events" class="admin-tab-content" role="tabpanel">
    <?php if (empty($events)): ?>
        <p class="empty-state"><?= t('admin.wells.events_empty') ?></p>
    <?php else: ?>
    <div class="data-list">
        <div class="events-list-header">
            <span><?= t('admin.wells.ev_col_date') ?></span>
            <span><?= t('admin.wells.ev_col_type') ?></span>
            <span><?= t('admin.wells.ev_col_player') ?></span>
            <span><?= t('admin.wells.ev_col_well') ?></span>
            <span><?= t('admin.wells.ev_col_cost') ?></span>
            <span><?= t('admin.wells.ev_col_cond') ?></span>
            <span><?= t('admin.wells.ev_col_desc') ?></span>
        </div>
        <?php foreach ($events as $e): ?>
        <article class="events-list-row">
            <span class="log-time"><?= date('d.m.Y H:i', strtotime($e['created_at'])) ?></span>
            <span class="log-action evt-<?= htmlspecialchars($e['event_type']) ?>"><?= htmlspecialchars($e['event_type']) ?></span>
            <span><?= htmlspecialchars($e['username']) ?></span>
            <span class="muted">#<?= (int)$e['well_id'] ?></span>
            <span><?= $e['cost'] > 0 ? number_format((float)$e['cost'], 0, '.', ' ') . ' ' . t('common.pln') : '' ?></span>
            <span class="muted">
                <?= $e['technical_condition_before'] !== null
                    ? $e['technical_condition_before'] . '% → ' . $e['technical_condition_after'] . '%'
                    : '' ?>
            </span>
            <span class="muted"><?= htmlspecialchars($e['description'] ?? '') ?></span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<!-- TAB: Pomoc -->
<div id="tab-help" class="admin-tab-content" role="tabpanel">
    <div class="help-page-title"><?= t('admin.wells.help.page_title') ?></div>
    <p class="help-page-intro"><?= t('admin.wells.help.page_intro') ?></p>
    <div class="help-grid">
        <?php for ($s = 1; $s <= 11; $s++): ?>
        <div class="help-section<?= in_array($s, [4, 9], true) ? ' help-section--highlight' : '' ?>">
            <div class="help-section-title"><?= t('admin.wells.help.s' . $s . '_title') ?></div>
            <div class="help-section-body">
                <p><?= t('admin.wells.help.s' . $s . '_body') ?></p>
                <?php if (t('admin.wells.help.s' . $s . '_formula') !== 'admin.wells.help.s' . $s . '_formula'): ?>
                <div class="help-formula"><?= t('admin.wells.help.s' . $s . '_formula') ?></div>
                <?php endif ?>
                <ul class="help-list">
                    <?php for ($li = 1; $li <= 10; $li++):
                        $liKey = 'admin.wells.help.s' . $s . '_li' . $li;
                        if (t($liKey) === $liKey) break;
                    ?>
                    <li><?= t($liKey) ?></li>
                    <?php endfor ?>
                    <?php
                    // Special keys for s11
                    foreach (['_offers','_price','_risk','_penalty','_well','_score','_wear'] as $k):
                        $hKey = 'admin.wells.help.s' . $s . $k;
                        if (t($hKey) !== $hKey): ?>
                    <li><?= t($hKey) ?></li>
                    <?php endif; endforeach ?>
                </ul>
                <?php
                $noteKey = 'admin.wells.help.s' . $s . '_note';
                $noteWarnKey = 'admin.wells.help.s' . $s . '_note_warn';
                if (t($noteKey) !== $noteKey): ?>
                <div class="help-note<?= t($noteWarnKey) !== $noteWarnKey ? ' help-note--warn' : '' ?>"><?= t($noteKey) ?></div>
                <?php endif ?>
            </div>
        </div>
        <?php endfor ?>
    </div>
</div>

<script src="/assets/js/admin_wells.js"></script>
