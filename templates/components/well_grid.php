<?php
$wgd = WellGridData::prepare($wells ?? [], $playerData ?? [], $storage ?? null);
extract($wgd);

if (class_exists('GameLog', false)) {
    GameLog::step('component/well_grid', 'render', 1, 'wells=' . count($wells ?? []));
}
if (empty($showWells)) {
    echo '<p>' . t('wg.no_wells') . '</p>';
    return;
}

// SVG icon helper - loads inline SVG from assets/img/icons/wg/
// Avoids emoji encoding issues; currentColor inherits CSS color.
// Unika problemow z kodowaniem emoji; currentColor dziedziczy kolor z CSS
if (!function_exists('wgIco')) {
    function wgIco(string $name, string $cls = ''): string {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            $path = __DIR__ . '/../../assets/img/icons/wg/' . preg_replace('/[^a-z0-9_-]/i', '', $name) . '.svg';
            $raw  = file_exists($path) ? file_get_contents($path) : '';
            $cache[$name] = $raw !== '' ? preg_replace('/<svg\b/', '<svg aria-hidden="true"', $raw, 1) : '&#x2753;';
        }
        $svg = $cache[$name];
        $svg = str_replace('<svg ', '<svg class="wg-ico' . ($cls !== '' ? ' ' . htmlspecialchars($cls, ENT_QUOTES) : '') . '" ', $svg);
        return $svg;
    }
}

// Group status summary helper / Pomocnik podsumowania statusow grupy
if (!function_exists('wgGroupSummary')) {
    function wgGroupSummary(array $wells, array $statusMap): string {
        $counts = [];
        foreach ($wells as $w) {
            $st = $w['status'] ?? 'active';
            if (!isset($counts[$st])) $counts[$st] = 0;
            $counts[$st]++;
        }
        $parts = [];
        foreach ($counts as $st => $cnt) {
            $icon = $statusMap[$st][2] ?? '';
            $parts[] = $icon . ($cnt > 1 ? ' ' . $cnt : '');
        }
        return implode('  ', $parts);
    }
}
?>

<div class="wg-regions">
<?php
$groupIdx = 0;
foreach ($groups as $regionName => $group):
    $groupIdx++;
    $groupId    = 'wg-group-' . $groupIdx;
    $regionCode = $group['code'];
    $icon       = $regionIcons[$regionCode] ?? $regionIcons[''];
    $color      = $group['color'];
    $groupWells = $group['wells'];
    $total      = count($groupWells);
    $active     = count(array_filter($groupWells, fn($w) => ($w['status'] ?? '') === 'active'));
    $problems   = count(array_filter($groupWells, fn($w) => in_array($w['status'] ?? '', ['broken','blowout','contaminated','seized'])));
    $paused     = $total - $active - $problems;
    $summary    = wgGroupSummary($groupWells, $statusMap);
    $totalProd  = array_sum(array_map(fn($w) => ($w['status'] ?? '') === 'active' ? (float)($w['base_production_per_hour'] ?? 0) : 0, $groupWells));
    $isOpen     = $groupIdx === 1;
?>
<div class="wg-region-group">
    <div class="wg-region-header" onclick="wgToggleGroup('<?= $groupId ?>')"
         id="<?= $groupId ?>-hdr">
        <div class="wg-region-hdr-left">
            <span class="wg-region-icon"><?= $icon ?></span>
            <span class="wg-region-name" style="color:<?= htmlspecialchars($color) ?>">
                <?= htmlspecialchars($regionName) ?>
            </span>
            <span class="wg-region-count"><?= $total ?> <?= t('wg.wells_suffix') ?></span>
        </div>
        <div class="wg-region-hdr-right">
            <div class="wg-region-pills">
                <?php if ($active > 0): ?>
                <span class="wg-rpill wg-rpill--active"> <?= $active ?></span>
                <?php endif ?>
                <?php if ($paused > 0): ?>
                <span class="wg-rpill wg-rpill--warn"> <?= $paused ?></span>
                <?php endif ?>
                <?php if ($problems > 0): ?>
                <span class="wg-rpill wg-rpill--danger"> <?= $problems ?></span>
                <?php endif ?>
                <?php if ($totalProd > 0): ?>
                <span class="wg-rpill wg-rpill--prod"> <?= number_format($totalProd, 0) ?> <?= t('common.bbl_h') ?></span>
                <?php endif ?>
            </div>
            <span class="wg-region-arrow<?= $isOpen ? ' wg-arrow-open' : '' ?>" id="<?= $groupId ?>-arrow"><?= wgIco('chevron-down') ?></span>
        </div>
    </div>

    <div class="wg-grid" id="<?= $groupId ?>"
         style="<?= $isOpen ? '' : 'display:none' ?>">
        <?php foreach ($groupWells as $w):
            $wid    = (int)$w['id'];
            $status = $w['_status'];
            $st     = $w['_st'];
        ?>
        <div class="wg-card" id="wg-card-<?= $wid ?>">

            <!-- Card header / Naglowek karty -->
            <div class="wg-header wg-header--redesign" onclick="wgToggle(<?= $wid ?>)">
                <?php
                $__pillMod = match(true) {
                    $status === 'active'                                               => 'wg-status-pill--active',
                    in_array($status, ['paused_staff','paused_cash','paused_storage']) => 'wg-status-pill--warn',
                    default                                                            => 'wg-status-pill--danger',
                };
                ?>
                <div class="wg-hdr-top">
                    <div>
                        <div class="wg-badges">
                            <span class="wbadge wb-id">#<?= $wid ?></span>
                            <span class="wbadge wb-type"><?= strtoupper(t('technical.well_type_' . ($w['well_type'] ?? 'onshore'))) ?></span>
                            <span class="wbadge wb-lvl"><?= t('wg.eq_level_badge', ['level' => (int)$w['level']]) ?></span>
                        </div>
                        <div class="wg-well-name"><?= htmlspecialchars($w['location_name'] ?? t('wg.default_well_name')) ?></div>
                        <?php if (!empty($w['region_name'])): ?>
                        <div class="wg-well-loc"><?= htmlspecialchars($w['region_name']) ?></div>
                        <?php endif ?>
                    </div>
                    <div class="wg-status-pill <?= $__pillMod ?>">
                        <span class="wg-pulse"></span> <?= $st[2] ?> <?= $st[0] ?>
                    </div>
                </div>

                <?php if ($w['_isActive']): ?>
                <div class="wg-kpi-grid">
                    <div class="wg-kpi-card">
                        <div class="wg-kpi-label"><?= t('wg.stat_production') ?></div>
                        <div class="wg-kpi-value kv-green">
                            <?= number_format((float)$w['base_production_per_hour'], 0) ?>
                            <span class="wg-kpi-unit"><?= t('common.bbl_h') ?></span>
                        </div>
                        <div class="wg-kpi-sub"><?= $w['_wEffPct'] ?>% <?= t('wg.stat_pressure') ?></div>
                    </div>
                    <div class="wg-kpi-card">
                        <div class="wg-kpi-label"><?= t('wg.stat_condition') ?></div>
                        <div class="wg-kpi-value <?= $w['_cond'] >= 70 ? 'kv-green' : ($w['_cond'] >= 40 ? 'kv-orange' : 'kv-red') ?>">
                            <?= round($w['_cond'], 1) ?>%
                        </div>
                        <div class="wg-kpi-sub">
                            <div class="wg-kpi-bar"><div class="wg-kpi-bar-fill <?= $w['_condCls'] ?>" style="width:<?= $w['_cond'] ?>%"></div></div>
                        </div>
                    </div>
                    <div class="wg-kpi-card">
                        <div class="wg-kpi-label"><?= t('wg.metric_mode') ?></div>
                        <div class="wg-kpi-value kv-dim">
                            <?= match($w['production_mode'] ?? 'normal') { 'eco' => ' Eco', 'boost' => ' Boost', default => ' Auto' } ?>
                        </div>
                        <div class="wg-kpi-sub"><?= t('wg.stat_reservoir') ?>: <?= $w['_wResPct'] ?>%</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="wg-cond">
                    <div class="wg-cond-row">
                        <span class="<?= $w['_condCls'] ?>"><?= t('wg.stat_condition') ?></span>
                        <span class="<?= $w['_condCls'] ?> fw7"><?= round($w['_cond'], 1) ?>%</span>
                    </div>
                    <div class="wg-bar"><div class="wg-bar-fill <?= $w['_condCls'] ?>" style="width:<?= $w['_cond'] ?>%"></div></div>
                </div>
                <?php endif ?>

                <?php if ($w['_missingSpecs']): ?>
                <div class="wg-staff-hint"><?= t('wg.missing_label') ?> <?= htmlspecialchars(implode(', ', $w['_missingSpecs'])) ?></div>
                <?php endif ?>
                <div class="wg-toggle-hint" id="wg-hint-<?= $wid ?>"><?= t('well_grid.hint_open') ?></div>
            </div>

            <!-- Details / Szczegoly -->
            <div class="wg-detail wg-hidden" id="wg-detail-<?= $wid ?>">

                <?php if ($status === 'paused_staff'): ?>
                <div class="wg-diag wg-diag--staff">
                    <div class="wg-diag-title"><?= t('wg.diag_staff_title') ?></div>
                    <p class="wg-diag-note"><?= t('wg.diag_staff_need') ?></p>
                    <ul class="wg-missing-list">
                        <?php foreach ($w['_missingSpecs'] as $ms): ?>
                        <li> <?= htmlspecialchars($ms) ?></li>
                        <?php endforeach ?>
                    </ul>
                    <p class="wg-diag-note"><?= t('wg.diag_staff_resume') ?></p>
                    <p class="wg-diag-note wg-diag-note--danger">
                         <strong><?= t('wg.diag_staff_danger') ?></strong>
                        <?= t('wg.stat_condition') ?>: <?= number_format($w['_cond'], 1) ?>%
                    </p>
                    <a href="<?= url('technical', ['tab' => 'candidates']) ?>" class="btn btn-primary btn-sm"><?= t('wg.btn_candidates') ?></a>
                </div>

                <?php elseif ($status === 'paused_cash'): ?>
                <div class="wg-diag wg-diag--cash">
                    <div class="wg-diag-title"><?= t('wg.diag_cash_title') ?></div>
                    <p class="wg-diag-note"><?= t('wg.diag_cash_opex_pre') ?><strong><?= number_format((float)($w['upkeep_cost_per_hour'] ?? 0), 0) ?></strong><?= t('wg.diag_cash_opex_post') ?></p>
                    <a href="<?= url('market') ?>" class="btn btn-primary btn-sm"><?= t('wg.btn_sell_oil') ?></a>
                </div>

                <?php elseif ($status === 'paused_storage'): ?>
                <div class="wg-diag wg-diag--storage">
                    <div class="wg-diag-title"><?= t('wg.diag_storage_title') ?></div>
                    <p class="wg-diag-note"><?= t('wg.diag_storage_note') ?></p>
                    <a href="<?= url('market') ?>" class="btn btn-primary btn-sm"><?= t('wg.btn_sell_oil') ?></a>
                </div>

                <?php elseif (in_array($status, ['blowout','contaminated','broken'])): ?>
                <div class="wg-diag wg-diag--disaster">
                    <div class="wg-diag-title"><?= $st[2] ?> <?= $st[0] ?></div>
                    <p class="wg-diag-note"><?= t('wg.diag_repair_note') ?></p>
                    <a href="<?= url('technical', ['tab' => 'tasks']) ?>" class="btn btn-primary btn-sm"><?= t('wg.btn_repair') ?></a>
                </div>

                <?php elseif ($status === 'seized'): ?>
                <div class="wg-diag wg-diag--disaster">
                    <div class="wg-diag-title"><?= t('wg.diag_seized_title') ?></div>
                    <p class="wg-diag-note"><?= t('wg.diag_seized_note') ?></p>
                    <a href="<?= url('bank') ?>" class="btn btn-primary btn-sm"><?= t('wg.btn_bank') ?></a>
                </div>

                <?php else: ?>
                <?php if (!empty($w['region_name'])): ?>
                <?php
                $__risk       = (int)($w['region_political_risk'] ?? 1);
                $__riskLabels = [
                    1 => t('wg.loc_risk_1'),
                    2 => t('wg.loc_risk_2'),
                    3 => t('wg.loc_risk_3'),
                    4 => t('wg.loc_risk_4'),
                    5 => t('wg.loc_risk_5'),
                ];
                $__riskCls    = $__risk >= 4 ? 'cv-bad' : ($__risk >= 3 ? 'cv-warn' : 'cv-good');
                $__riskPipCls = $__risk >= 4 ? 'wg-risk-pip--red' : ($__risk >= 3 ? 'wg-risk-pip--orange' : 'wg-risk-pip--green');
                ?>
                <div class="wg-meta-grid">
                    <div class="wg-meta-cell">
                        <div class="wg-meta-label"><?= t('wg.loc_richness') ?></div>
                        <div class="wg-meta-value cv-warn">
                            <?= number_format((float)($w['oil_richness'] ?? 1.0), 1) ?>
                            <span class="wg-meta-unit"><?= t('wg.multiplier_unit') ?></span>
                        </div>
                    </div>
                    <div class="wg-meta-cell">
                        <div class="wg-meta-label"><?= t('wg.loc_tax') ?></div>
                        <div class="wg-meta-value cv-bad">
                            <?= round((float)($w['region_tax_rate'] ?? $w['regional_tax_rate'] ?? 0) * 100, 1) ?>%
                            <span class="wg-meta-unit"><?= t('wg.per_hour_short') ?></span>
                        </div>
                    </div>
                    <div class="wg-meta-cell wg-meta-cell--full">
                        <div class="wg-meta-label"><?= t('wg.loc_political_risk') ?></div>
                        <div class="wg-risk-meter">
                            <div class="wg-risk-pips">
                                <?php for ($__i = 1; $__i <= 5; $__i++): ?>
                                <span class="wg-risk-pip <?= $__i <= $__risk ? 'wg-risk-pip--on ' . $__riskPipCls : '' ?>"></span>
                                <?php endfor ?>
                            </div>
                            <span class="wg-risk-label <?= $__riskCls ?>"><?= $__riskLabels[$__risk] ?? '' ?> (<?= $__risk ?>/5)</span>
                            <?php if ($__risk >= 4): ?>
                            <span class="wg-risk-advice"><?= t('wg.loc_risk_advice') ?></span>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <?php endif ?>
                <?php endif /* else: active well */ ?>

<?php require __DIR__ . '/well_grid/equipment.php'; ?>

                <!-- Rozbudowa magazynu -->
                <?php if ($storageData && $status !== 'seized'): ?>
                <?php
                $__storeUsed = (float)($storageData['used'] ?? 0);
                $__storePct  = $storageCap > 0 ? min(100, round($__storeUsed / $storageCap * 100)) : 0;
                ?>
                <div class="wg-storage-wrap">
                    <div class="wg-section-hdr" onclick="wgToggleStorage(<?= $wid ?>)">
                        <div class="wg-section-title"><span class="wg-section-ico"></span> <?= t('wg.storage_title') ?></div>
                        <span class="wg-section-badge <?= $__storePct >= 80 ? 'nsb-danger' : 'nsb-ok' ?>"><?= $__storePct ?>% pojemnoci</span>
                        <span class="wg-section-arrow" id="wg-sarrow-<?= $wid ?>"></span>
                    </div>
                    <div class="wg-section-body" id="wg-storage-<?= $wid ?>">
                        <div class="wg-storage-card">
                            <div class="wg-storage-row">
                                <div>
                                    <div class="wg-storage-title"><?= t('wg.storage_after') ?></div>
                                    <div class="wg-storage-cap">
                                        <?= number_format($storageCap, 0, '.', ' ') ?> 
                                        <strong class="cv-good"><?= number_format($storageAfter, 0, '.', ' ') ?> <?= t('common.bbl') ?></strong>
                                    </div>
                                </div>
                                <?php if ($canAffordUpg): ?>
                                <button type="button" class="wg-opt-btn wg-opt-btn--primary"
                                        onclick="wgConfirmStorage(<?= $wid ?>, <?= (int)$storageCap ?>, <?= (int)$storageAfter ?>, <?= (int)$upgradeCost ?>)">
                                    Rozbuduj
                                </button>
                                <?php endif ?>
                            </div>
                            <div class="wg-storage-track">
                                <div class="wg-storage-fill" style="width:<?= $__storePct ?>%"></div>
                            </div>
                            <div class="wg-storage-footer">
                                <span class="wg-storage-cost">
                                    <?= t('wg.storage_cost') ?>: <strong class="cv-gold"><?= number_format($upgradeCost, 0, '.', ' ') ?> <?= t('common.pln') ?></strong>
                                </span>
                                <span class="wg-storage-budget <?= $canAffordUpg ? 'cv-good' : 'cv-bad' ?>">
                                    <?= $canAffordUpg ? 'Sta Ci ' : t('wg.storage_blocked', ['amount' => number_format($upgradeCost - $playerCash, 0, '.', ' ')]) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif ?>


<?php require __DIR__ . '/well_grid/transport.php'; ?>

<?php require __DIR__ . '/well_grid/layers.php'; ?>

<?php require __DIR__ . '/well_grid/danger_zone.php'; ?>

            </div><!-- /.wg-detail -->
        </div><!-- /.wg-card -->
        <?php endforeach ?>
    </div><!-- /.wg-grid -->
</div><!-- /.wg-region-group -->
<?php endforeach ?>
</div><!-- /.wg-regions -->

<script>
window.WG_CSRF = '<?= CSRF::generateToken() ?>';
window.WG_LANG = <?= json_encode([
    'pln'                  => t('common.pln'),
    'bbl'                  => t('common.bbl'),
    'cancel'               => t('common.cancel'),
    'err_prefix'           => t('common.error_prefix'),
    'err_retry'            => t('common.err_retry'),
    'err_connection'       => t('common.err_connection'),
    'err_connection_msg'   => t('common.err_connection_msg'),
    'err_unknown'          => t('common.err_unknown'),
    'hint_open'            => t('well_grid.hint_open'),
    'hint_close'           => t('well_grid.hint_close'),
    'storage_modal_title'  => t('well_grid.storage_modal_title'),
    'storage_cap_before'   => t('well_grid.storage_cap_before'),
    'storage_cap_after'    => t('well_grid.storage_cap_after'),
    'storage_cost_label'   => t('well_grid.storage_cost_label'),
    'storage_confirm_btn'  => t('well_grid.storage_confirm_btn'),
    'storage_upgrading'    => t('well_grid.storage_upgrading'),
    'storage_done'         => t('well_grid.storage_done'),
    'tier_black_market'    => t('well_grid.tier_black_market'),
    'tier_standard'        => t('well_grid.tier_standard'),
    'tier_premium'         => t('well_grid.tier_premium'),
    'eq_swap_remaining'    => t('wg.eq_swap_remaining'),
    'tier_confirm'         => t('well_grid.tier_confirm'),
    'tier_change_title'    => t('well_grid.tier_change_title'),
    'upgrade_confirm'      => t('well_grid.upgrade_confirm'),
    'upgrade_title'        => t('well_grid.upgrade_title'),
    'eq_confirm_title'     => t('well_grid.eq_confirm_title'),
    'eq_confirm_ok'        => t('well_grid.eq_confirm_ok'),
    'layer_confirm'        => t('well_grid.layer_confirm'),
    'layer_confirm_title'  => t('well_grid.layer_confirm_title'),
    'layer_confirm_ok'     => t('well_grid.layer_confirm_ok'),
    'layer_cost'           => t('well_grid.layer_cost'),
    'layer_paused'         => t('well_grid.layer_paused'),
    'layer_reset'          => t('well_grid.layer_reset'),
    'sell_title'           => t('well_grid.sell_title'),
    'sell_modal_title'     => t('well_grid.sell_modal_title'),
    'sell_confirm_btn'     => t('well_grid.sell_confirm_btn'),
    'sell_row_base'        => t('well_grid.sell_row_base'),
    'sell_row_condition'   => t('well_grid.sell_row_condition'),
    'sell_row_wear'        => t('well_grid.sell_row_wear'),
    'sell_row_risk'        => t('well_grid.sell_row_risk'),
    'sell_row_equipment'   => t('well_grid.sell_row_equipment'),
    'sell_row_depth'       => t('well_grid.sell_row_depth'),
    'sell_row_incident'    => t('well_grid.sell_row_incident'),
    'sell_reservoir_label' => t('well_grid.sell_reservoir_label'),
    'sell_reservoir_val'   => t('well_grid.sell_reservoir_val'),
    'sell_price_label'     => t('well_grid.sell_price_label'),
    'sell_note'            => t('well_grid.sell_note'),
    'sold_toast'           => t('well_grid.sold_toast'),
    'err_sell'             => t('well_grid.err_sell'),
    'swap_done'            => t('well_grid.swap_done'),
    'transport_switched'   => t('well_grid.transport_switched'),
    'transport_buy_title'  => t('well_grid.transport_buy_title'),
    'transport_buy_confirm'=> t('well_grid.transport_buy_confirm'),
    'transport_btn_clear'          => t('wg.transport_btn_clear'),
    'transport_unset_label'        => t('wg.transport_unset_label'),
    'transport_unset_desc'         => t('wg.transport_unset_desc'),
    'transport_pipe_label'         => t('wg.transport_pipe_label'),
    'transport_pipe_desc'          => t('wg.transport_pipe_desc'),
    'pipe_build_cost'              => t('technical.pipe_build_cost'),
    'transport_title'              => t('wg.transport_title'),
    'transport_capacity'           => t('wg.transport_capacity'),
    'transport_opex'               => t('wg.transport_opex'),
    'transport_risk_short'         => t('wg.transport_risk_short'),
    'transport_switch_confirm_btn' => t('well_grid.transport_switch_confirm_btn'),
    'pipe_type_select_title'       => t('well_grid.pipe_type_select_title'),
    'pipe_type_light'              => t('well_grid.pipe_type_light'),
    'pipe_type_standard'           => t('well_grid.pipe_type_standard'),
    'pipe_type_heavy'              => t('well_grid.pipe_type_heavy'),
    'pipe_hours_label'             => t('well_grid.pipe_hours_label'),
 // Second transport leg (hub -> storage) / Odcinek 2 (hub -> magazyn)
    'leg2_title'                   => t('well_grid.leg2_title'),
    'leg2_btn_pipeline'            => t('well_grid.leg2_btn_pipeline'),
    'leg2_btn_road'                => t('well_grid.leg2_btn_road'),
    'leg2_btn_direct'              => t('well_grid.leg2_btn_direct'),
    'leg2_confirm_pipeline'        => t('well_grid.leg2_confirm_pipeline', ['cost' => '{cost}']),
    'leg2_confirm_road'            => t('well_grid.leg2_confirm_road'),
    'leg2_confirm_direct'          => t('well_grid.leg2_confirm_direct'),
], JSON_UNESCAPED_UNICODE) ?>;
window.WG_PIPELINE_API = '/src/PipelineApi.php';
</script>
<script src="<?= asset('/assets/js/well_grid.js') ?>"></script>
