<?php
// Geological layers section / Sekcja warstw geologicznych
?>
                <!-- Geological layers panel / Panel warstw geologicznych -->
                <?php if ($status !== 'seized' && $w['_glCur'] !== null): ?>
                <?php
                $__glR   = (float)($w['_glCur']['richness_mult'] ?? 1.0);
                $__glDesc = (string)($w['_glCur']['name'] ?? t('wg.layer_default_name'));
                if ($__glR < 1.0)      $__glDesc .= '  ' . t('wg.layer_desc_low');
                elseif ($__glR >= 2.0) $__glDesc .= '  ' . t('wg.layer_desc_high');
                $__layerBadgeCls = $__glR >= 1.5 ? 'nsb-ok' : ($__glR >= 1.0 ? 'nsb-warn' : 'nsb-danger');
                ?>
                <div class="wg-layer-wrap">
                    <div class="wg-section-hdr" onclick="wgToggleLayer(<?= $wid ?>)">
                        <div class="wg-section-title"><span class="wg-section-ico"><?= wgIco('rock') ?></span> <?= t('wg.layer_title') ?></div>
                        <span class="wg-section-badge <?= $__layerBadgeCls ?>">
                            <?= htmlspecialchars($__glDesc) ?>
                            <?php if ($w['_glSwitchHoursLeft'] > 0): ?> - <?= wgIco('hourglass') ?> <?= $w['_glSwitchHoursLeft'] ?>h<?php endif ?>
                        </span>
                        <span class="wg-section-arrow" id="wg-larrow-<?= $wid ?>"><?= wgIco('chevron-down') ?></span>
                    </div>

                    <div class="wg-section-body" id="wg-layer-<?= $wid ?>" style="display:none">

                        <?php if ($w['_glSwitchHoursLeft'] > 0): ?>
                        <div class="wg-layer-switching">
                            <?= t('wg.layer_switching', ['hours' => $w['_glSwitchHoursLeft']]) ?>
                        </div>
                        <?php endif ?>

                        <!-- Current layer stats -->
                        <div class="wg-cur-stats">
                            <div class="wg-cs-item">
                                <div class="wg-cs-label"><?= t('wg.layer_stat_richness') ?></div>
                                <div class="wg-cs-val <?= (float)($w['_glCur']['richness_mult'] ?? 1.0) >= 1.0 ? 'mv-good' : 'mv-warn' ?>">
                                    ×<?= number_format((float)($w['_glCur']['richness_mult'] ?? 1.0), 2) ?>
                                </div>
                            </div>
                            <div class="wg-cs-item">
                                <div class="wg-cs-label"><?= t('wg.layer_stat_risk') ?></div>
                                <div class="wg-cs-val <?= (float)($w['_glCur']['risk_mult'] ?? 1.0) <= 7 ? 'mv-warn' : 'mv-bad' ?>">
                                    ×<?= number_format((float)($w['_glCur']['risk_mult'] ?? 1.0), 1) ?>
                                </div>
                            </div>
                            <div class="wg-cs-item">
                                <div class="wg-cs-label"><?= t('wg.layer_stat_wear') ?></div>
                                <div class="wg-cs-val mv-neu">×<?= number_format((float)($w['_glCur']['wear_mult'] ?? 1.0), 1) ?></div>
                            </div>
                            <div class="wg-cs-item">
                                <div class="wg-cs-label"><?= t('wg.layer_stat_spiral') ?></div>
                                <div class="wg-cs-val mv-neu">×<?= number_format((float)($w['_glCur']['spiral_mult'] ?? 1.0), 1) ?></div>
                            </div>
                        </div>

                        <div class="wg-opt-label"><?= t('wg.layer_change') ?></div>
                        <div class="wg-opt-cards">
                            <?php
                            $__curR  = (float)($w['_glCur']['richness_mult'] ?? 1.0);
                            $__curRk = (float)($w['_glCur']['risk_mult'] ?? 1.0);
                            $__curW  = (float)($w['_glCur']['wear_mult'] ?? 1.0);
                            $__curSp = (float)($w['_glCur']['spiral_mult'] ?? 1.0);
                            $__curRes = (float)($glAll[$w['_glCurId']]['reservoir_bbl'] ?? 1);
                            foreach ($glAll as $layerId => $layer):
                                $layerId   = (int)($layer['id'] ?? $layerId);
                                $isCur     = $layerId === $w['_glCurId'];
                                $cost      = (int)($layer['switch_cost'] ?? 0);
                                $hours     = (int)($layer['switch_hours'] ?? 0);
                                $canSwitch = !$isCur && $w['_glSwitchHoursLeft'] <= 0;
                                $__lR      = (float)($layer['richness_mult'] ?? 1.0);
                                $__lRk     = (float)($layer['risk_mult'] ?? 1.0);
                                $__lW      = (float)($layer['wear_mult'] ?? 1.0);
                                $__lSp     = (float)($layer['spiral_mult'] ?? 1.0);
                                $__resMax  = (float)($layer['reservoir_bbl'] ?? 0);
                                $__resMult = $__curRes > 0 ? (int)round($__resMax / max(1, $__curRes)) : 0;
                            ?>
                            <div class="wg-opt-card <?= $isCur ? 'wg-opt-card--active' : '' ?>">
                                <div class="wg-opt-card-top">
                                    <div>
                                        <div class="wg-opt-name"><?= htmlspecialchars((string)($layer['name'] ?? '')) ?></div>
                                        <div class="wg-opt-zloza">
                                            <?= t('wg.layer_reservoir', ['amount' => number_format($__resMax, 0, '.', ' ')]) ?>
                                            <?php if (!$isCur && $__resMult > 1): ?>
                                            <span class="wg-layer-growth"><?= wgIco('arrow-up') ?> <?= $__resMult ?>×</span>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                    <?php if ($isCur): ?>
                                    <span class="wg-opt-active-badge"><?= t('wg.layer_active') ?></span>
                                    <?php endif ?>
                                </div>
                                <div class="wg-mult-grid">
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-label"><?= t('wg.layer_stat_richness') ?></div>
                                        <div class="wg-mult-val <?= $__lR >= 1.3 ? 'mv-good' : ($__lR >= 1.0 ? 'mv-warn' : 'mv-bad') ?>">
                                            ×<?= number_format($__lR, 2) ?>
                                            <?php if (!$isCur && $__lR != $__curR): ?><span class="wg-delta <?= $__lR > $__curR ? 'wg-delta--up' : 'wg-delta--down' ?>"><?= $__lR > $__curR ? wgIco('arrow-up') : wgIco('chevron-down') ?></span><?php endif ?>
                                        </div>
                                    </div>
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-label"><?= t('wg.layer_stat_risk') ?></div>
                                        <div class="wg-mult-val <?= $__lRk <= 7 ? 'mv-warn' : 'mv-bad' ?>">
                                            ×<?= number_format($__lRk, 1) ?>
                                            <?php if (!$isCur && $__lRk != $__curRk): ?><span class="wg-delta <?= $__lRk < $__curRk ? 'wg-delta--up' : 'wg-delta--down' ?>"><?= $__lRk < $__curRk ? wgIco('arrow-up') : wgIco('chevron-down') ?></span><?php endif ?>
                                        </div>
                                    </div>
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-label"><?= t('wg.layer_stat_wear') ?></div>
                                        <div class="wg-mult-val mv-neu">
                                            ×<?= number_format($__lW, 1) ?>
                                            <?php if (!$isCur && $__lW != $__curW): ?><span class="wg-delta <?= $__lW < $__curW ? 'wg-delta--up' : 'wg-delta--down' ?>"><?= $__lW < $__curW ? wgIco('arrow-up') : wgIco('chevron-down') ?></span><?php endif ?>
                                        </div>
                                    </div>
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-label"><?= t('wg.layer_stat_spiral') ?></div>
                                        <div class="wg-mult-val mv-neu">
                                            ×<?= number_format($__lSp, 1) ?>
                                            <?php if (!$isCur && $__lSp != $__curSp): ?><span class="wg-delta <?= $__lSp < $__curSp ? 'wg-delta--up' : 'wg-delta--down' ?>"><?= $__lSp < $__curSp ? wgIco('arrow-up') : wgIco('chevron-down') ?></span><?php endif ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!$isCur): ?>
                                <div class="wg-opt-footer">
                                    <div>
                                        <div class="wg-opt-cost"><?= wgIco('coin') ?> $<?= number_format($cost, 0, '.', ' ') ?></div>
                                        <?php if ($hours > 0): ?>
                                        <div class="wg-opt-time"><?= t('wg.layer_downtime_hours', ['hours' => $hours]) ?></div>
                                        <?php endif ?>
                                    </div>
                                    <button type="button"
                                        class="wg-opt-btn wg-opt-btn--primary <?= ($canSwitch && $playerCash >= $cost) ? '' : 'wg-opt-btn--disabled' ?>"
                                        onclick="wgSwitchLayer(<?= $wid ?>, <?= $layerId ?>, <?= $cost ?>, '<?= htmlspecialchars((string)($layer['name'] ?? ''), ENT_QUOTES) ?>', <?= $hours ?>)"
                                        <?= ($canSwitch && $playerCash >= $cost) ? '' : 'disabled' ?>>
                                        <?= t('wg.layer_btn_switch') ?>
                                    </button>
                                </div>
                                <?php endif ?>
                            </div>
                            <?php endforeach ?>
                        </div>

                    </div>
                </div>
                <?php endif ?>
