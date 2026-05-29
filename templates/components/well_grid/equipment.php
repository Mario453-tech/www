<?php
// Equipment section / Sekcja sprzetu
?>
                <!-- Equipment panel / Panel sprzetu -->
                <?php if ($status !== 'seized' && $w['_eqMults'] !== null): ?>
                <?php
 // Base tier multipliers at level 0 / Bazowe mnozniki tierow dla poziomu 0
                $__tierMults = [
                    'black_market' => ['prod' => 1.10, 'wear' => 1.30, 'incident' => 1.25, 'spiral' => 1.20],
                    'standard'     => ['prod' => 1.00, 'wear' => 1.00, 'incident' => 1.00, 'spiral' => 1.00],
                    'premium'      => ['prod' => 1.20, 'wear' => 0.75, 'incident' => 0.80, 'spiral' => 0.85],
                ];
                if (!function_exists('wgFmtMult')) {
                    function wgFmtMult(float $mult, bool $lowerIsBetter = false): array {
                        $pct = (int) round(($mult - 1) * 100);
                        $str = ($pct > 0 ? '+' : '') . $pct . '%';
                        if ($pct === 0) return ['mv-neu', '0%'];
                        return [($lowerIsBetter ? $pct < 0 : $pct > 0) ? 'mv-good' : 'mv-bad', $str];
                    }
                }
                $__badgeCls = match($w['_eqTier']) {
                    'premium'      => 'nsb-ok',
                    'black_market' => 'nsb-danger',
                    default        => 'nsb-warn',
                };
                ?>
                <div class="wg-equipment-wrap">
                    <div class="wg-section-hdr" onclick="wgToggleEquipment(<?= $wid ?>)">
                        <div class="wg-section-title"><span class="wg-section-ico"><?= wgIco('gear') ?></span> <?= t('wg.eq_title') ?></div>
                        <span class="wg-section-badge <?= $__badgeCls ?>">
                            <?= $w['_tierLabel'][0] ?><?= $w['_eqLevel'] > 0 ? '  ' . t('wg.eq_level_badge', ['level' => $w['_eqLevel']]) : '' ?>
                        </span>
                        <span class="wg-section-arrow wg-arrow-open" id="wg-earrow-<?= $wid ?>"><?= wgIco('chevron-down') ?></span>
                    </div>
                    <div class="wg-section-body" id="wg-equipment-<?= $wid ?>">

                        <!-- Current equipment stats -->
                        <?php
                        [$__pc, $__pv] = wgFmtMult($w['_eqMults']['prod'], false);
                        [$__ic, $__iv] = wgFmtMult($w['_eqMults']['incident'], true);
                        [$__wc, $__wv] = wgFmtMult($w['_eqMults']['wear'], true);
                        [$__sc, $__sv] = wgFmtMult($w['_eqMults']['spiral'], true);
                        ?>
                        <div class="wg-cur-stats">
                            <div class="wg-cs-item">
                                <div class="wg-cs-label"><?= t('wg.eq_stat_prod') ?></div>
                                <div class="wg-cs-val <?= $__pc ?>"><?= $__pv ?></div>
                            </div>
                            <div class="wg-cs-item">
                                <div class="wg-cs-label"><?= t('wg.eq_stat_incident') ?></div>
                                <div class="wg-cs-val <?= $__ic ?>"><?= $__iv ?></div>
                            </div>
                            <div class="wg-cs-item">
                                <div class="wg-cs-label"><?= t('wg.eq_stat_wear') ?></div>
                                <div class="wg-cs-val <?= $__wc ?>"><?= $__wv ?></div>
                            </div>
                            <div class="wg-cs-item">
                                <div class="wg-cs-label"><?= t('wg.eq_stat_spiral') ?></div>
                                <div class="wg-cs-val <?= $__sc ?>"><?= $__sv ?></div>
                            </div>
                        </div>

                        <?php if (($w['status'] ?? '') === 'equipment_swap' && !empty($w['equipment_swap_until'])): ?>
                        <div class="wg-eq-swap-banner" id="wg-swap-banner-<?= $wid ?>"
                             data-until="<?= htmlspecialchars($w['equipment_swap_until']) ?>">
                            <span class="wg-eq-swap-icon"><?= wgIco('gear') ?></span>
                            <div class="wg-eq-swap-info">
                                <strong><?= t('wg.eq_swap_in_progress') ?></strong>
                                <span class="wg-eq-swap-timer" id="wg-swap-timer-<?= $wid ?>"><?= wgIco('hourglass') ?></span>
                            </div>
                        </div>
                        <?php else: ?>

                        <div class="wg-opt-label"><?= t('wg.eq_change_tier') ?></div>
                        <div class="wg-opt-cards">
                            <?php foreach ([
                                'black_market' => [t('wg.eq_tier_bm_label'),   t('wg.eq_tier_bm_desc'),   true],
                                'standard'     => [t('wg.eq_tier_std_label'),  t('wg.eq_tier_std_desc'),  false],
                                'premium'      => [t('wg.eq_tier_prem_label'), t('wg.eq_tier_prem_desc'), false],
                            ] as $t_key => [$tLabel, $tDesc, $isDanger]):
                                $isCurrent  = $t_key === $w['_eqTier'];
                                $tCost      = $w['_tierCosts'][$t_key];
                                $canAffordT = $playerCash >= $tCost;
                                $tm         = $__tierMults[$t_key];
                                [$__tpc, $__tpv] = wgFmtMult($tm['prod'], false);
                                [$__tic, $__tiv] = wgFmtMult($tm['incident'], true);
                                [$__twc, $__twv] = wgFmtMult($tm['wear'], true);
                                [$__tsc, $__tsv] = wgFmtMult($tm['spiral'], true);
                                $cardCls = 'wg-opt-card' . ($isCurrent ? ' wg-opt-card--active' : ($isDanger ? ' wg-opt-card--danger' : ''));
                            ?>
                            <div class="<?= $cardCls ?>">
                                <div class="wg-opt-card-top">
                                    <div>
                                        <div class="wg-opt-name"><?= $tLabel ?></div>
                                        <div class="wg-opt-desc"><?= $tDesc ?></div>
                                    </div>
                                    <?php if ($isCurrent): ?>
                                    <span class="wg-opt-active-badge"><?= t('wg.eq_current') ?></span>
                                    <?php endif ?>
                                </div>
                                <div class="wg-mult-grid">
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-label"><?= t('wg.eq_stat_prod') ?></div>
                                        <div class="wg-mult-val <?= $__tpc ?>"><?= $__tpv ?></div>
                                    </div>
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-label"><?= t('wg.eq_stat_incident') ?></div>
                                        <div class="wg-mult-val <?= $__tic ?>"><?= $__tiv ?></div>
                                    </div>
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-label"><?= t('wg.eq_stat_wear') ?></div>
                                        <div class="wg-mult-val <?= $__twc ?>"><?= $__twv ?></div>
                                    </div>
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-label"><?= t('wg.eq_stat_spiral') ?></div>
                                        <div class="wg-mult-val <?= $__tsc ?>"><?= $__tsv ?></div>
                                    </div>
                                </div>
                                <?php if (!$isCurrent): ?>
                                <div class="wg-opt-footer">
                                    <span class="wg-opt-cost"><?= wgIco('coin') ?> $<?= number_format($tCost, 0, '.', ' ') ?></span>
                                    <button type="button"
                                        class="wg-opt-btn <?= $isDanger ? 'wg-opt-btn--danger' : 'wg-opt-btn--primary' ?> <?= $canAffordT ? '' : 'wg-opt-btn--disabled' ?>"
                                        onclick="wgSetTier(<?= $wid ?>, '<?= $t_key ?>', <?= $tCost ?>)"
                                        <?= $canAffordT ? '' : 'disabled' ?>>
                                        <?= t('wg.eq_btn_install') ?>
                                    </button>
                                </div>
                                <?php endif ?>
                            </div>
                            <?php endforeach ?>
                        </div>

                        <?php endif /* swap */ ?>

                        <?php if ($w['_eqLevel'] < 3): ?>
                        <div class="wg-upgrade-box">
                            <div class="wg-upgrade-title"><?= wgIco('arrow-up') ?> <?= t('wg.eq_upgrade_title', ['cur' => $w['_eqLevel']]) ?></div>
                            <div class="wg-upgrade-desc"><?= t('wg.eq_upgrade_desc') ?></div>
                            <div class="wg-upgrade-footer">
                                <span class="wg-upgrade-cost <?= $playerCash >= $w['_nextUpgCost'] ? 'cv-gold' : 'cv-bad' ?>">
                                    $<?= number_format($w['_nextUpgCost'], 0, '.', ' ') ?>
                                </span>
                                <button type="button"
                                    class="wg-opt-btn wg-opt-btn--primary <?= $playerCash >= $w['_nextUpgCost'] ? '' : 'wg-opt-btn--disabled' ?>"
                                    onclick="wgUpgradeEquipment(<?= $wid ?>, <?= $w['_nextUpgCost'] ?>)"
                                    <?= $playerCash >= $w['_nextUpgCost'] ? '' : 'disabled' ?>>
                                    <?= t('wg.eq_btn_upgrade', ['level' => $w['_eqLevel'] + 1]) ?>
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="wg-eq-maxlvl"><?= t('wg.eq_max_level') ?></div>
                        <?php endif ?>

                    </div>
                </div>
                <?php endif ?>
