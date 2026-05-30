<?php
// Transport section / Sekcja transportu
?>
                <!-- Transport panel / Panel transportu -->
                <div class="wg-transport-wrap">
                    <div class="wg-section-hdr" onclick="wgToggleTransport(<?= $wid ?>)">
                        <div class="wg-section-title"><span class="wg-section-ico"><?= wgIco('oil-drop') ?></span> <?= t('wg.transport_title') ?></div>
                        <span class="wg-section-badge nsb-warn">
                            <span class="<?= $w['_trCls'] ?>"><?= $w['_trLabel'] ?></span>
                            - <?= $w['_trCapPct'] ?>%
                        </span>
                        <span class="wg-section-arrow" id="wg-tarrow-<?= $wid ?>"><?= wgIco('chevron-down') ?></span>
                    </div>
                    <div class="wg-section-body" id="wg-transport-<?= $wid ?>">
                        <?php
                        $__trRiskMap = [
                            'rurociag'   => ['kv-green', '-20%'],
                            'ciezarowki' => ['kv-red',   '+30%'],
                            'tankowiec'  => ['mv-neu',    '0%'],
                        ];
                        $__effectiveTransport = (string)($w['_trTypeEffective'] ?? $w['_trType']);
                        ?>
                        <div class="wg-cur-stats" style="grid-template-columns:repeat(3,1fr)">
                            <div class="wg-cs-item">
                                <div class="wg-cs-lbl"><?= t('wg.transport_capacity') ?></div>
                                <div class="wg-cs-val <?= $w['_trCapPct'] >= 100 ? 'kv-green' : 'kv-orange' ?>"><?= $w['_trCapPct'] ?>%</div>
                            </div>
                            <div class="wg-cs-item">
                                <div class="wg-cs-lbl"><?= t('wg.transport_opex') ?></div>
                                <div class="wg-cs-val <?= $w['_trOpexPct'] <= 10 ? 'kv-green' : ($w['_trOpexPct'] <= 15 ? 'kv-orange' : 'kv-red') ?>"><?= $w['_trOpexPct'] ?>%</div>
                            </div>
                            <div class="wg-cs-item">
                                <div class="wg-cs-lbl"><?= t('wg.transport_incident_risk') ?></div>
                                <div class="wg-cs-val <?= $__trRiskMap[$__effectiveTransport][0] ?? 'mv-neu' ?>">
                                    <?= $__trRiskMap[$__effectiveTransport][1] ?? '0%' ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($w['_transportSelectionRequired'])): ?>
                        <div class="wg-staff-hint"><?= t('well_grid.transport_selection_required') ?></div>
                        <?php elseif (!$w['_isOffshore'] && !empty($w['_pipelineBuilding'])): ?>
                        <div class="wg-staff-hint"><?= t('well_grid.transport_pipeline_building') ?></div>
                        <?php elseif (!$w['_isOffshore'] && !empty($w['_pipelineOperational'])): ?>
                        <div class="wg-staff-hint"><?= t('well_grid.transport_pipeline_owned') ?></div>
                        <?php elseif (!$w['_isOffshore'] && !empty($w['_pipelineBindingInvalid'])): ?>
                        <div class="wg-staff-hint"><?= t('well_grid.transport_pipeline_rebind_required') ?></div>
                        <?php elseif (!$w['_isOffshore'] && ($w['_trType'] ?? '') === 'rurociag'): ?>
                        <div class="wg-staff-hint"><?= t('well_grid.transport_pipeline_missing') ?></div>
                        <?php endif ?>
                        <?php if (!$w['_isOffshore'] && empty($w['_hasHubAssignment'])): ?>
                        <div class="wg-staff-hint"><?= t('well_grid.transport_pipeline_hub_required') ?></div>
                        <?php endif ?>

                        <div class="wg-opt-cards">
                        <?php foreach ($w['_trDefs'] as $tCode => [$tName, $tCls2, $tCap, $tOpex, $tDesc, $available]):
                            if ($tCode === 'nieustawiony') {
                                continue;
                            }
                            $isPendingPipeline = (
                                $tCode === 'rurociag'
                                && !$w['_isOffshore']
                                && !empty($w['_pipelineBuilding'])
                                && (($w['_trType'] ?? '') === 'rurociag')
                            );
                            // Zaden typ nie jest oznaczony jako "biezacy" - gracz wybiera sam,
                            // a kazdy dostepny typ ma zawsze widoczny przycisk przelaczenia.
                            // No transport type is pre-marked as current - the player always chooses,
                            // every available type keeps a visible switch button.
                            $isCurrent  = false;
                            $isDisabled = !$available && !$isCurrent;
                            if ($tCode === 'rurociag' && !$w['_isOffshore'] && empty($w['_hasHubAssignment'])) {
                                $isDisabled = true;
                            }
                            [$__rCls, $__rVal] = $__trRiskMap[$tCode] ?? ['mv-neu', '0%'];
                            $__capCls  = $tCap  >= 100 ? 'kv-green' : 'kv-orange';
                            $__opxCls  = $tOpex <= 10  ? 'kv-green' : ($tOpex <= 15 ? 'kv-orange' : 'kv-red');
                            $__cardCls = $isCurrent ? 'wg-opt-card--active' : ($isDisabled ? 'wg-opt-card--disabled' : '');
                        ?>
                            <div class="wg-opt-card <?= $__cardCls ?>">
                                <div class="wg-opt-hdr">
                                    <span class="wg-opt-name"><?= $tName ?></span>
                                    <?php if ($isCurrent): ?>
                                    <span class="wg-opt-badge wg-opt-badge--ok">
                                        <?= $isPendingPipeline ? t('logistics.pipeline.status_building') : t('wg.eq_current') ?>
                                    </span>
                                    <?php endif ?>
                                </div>
                                <div class="wg-opt-desc"><?= $tDesc ?></div>

                                <?php if ($isDisabled): ?>
                                <div class="wg-opt-unavail">
                                    <?php if ($tCode === 'rurociag' && !$w['_isOffshore'] && empty($w['_hasHubAssignment'])): ?>
                                        <?= t('well_grid.transport_pipeline_hub_required') ?>
                                    <?php else: ?>
                                        <?= $w['_isOffshore'] ? t('wg.transport_no_offshore') : t('wg.transport_offshore_only') ?>
                                    <?php endif ?>
                                </div>
                                <?php if ($tCode === 'rurociag' && !$w['_isOffshore'] && empty($w['_hasHubAssignment'])): ?>
                                <div class="wg-opt-footer">
                                    <a class="wg-opt-btn wg-opt-btn--primary" href="/logistics#logistics-available-hubs-heading">
                                        <?= t('well_grid.transport_pipeline_open_hubs') ?>
                                    </a>
                                </div>
                                <?php endif ?>
                                <?php else: ?>
                                <?php if ($tCode === 'rurociag' && !$w['_isOffshore'] && empty($w['_pipelineOwned'])): ?>
                                <div class="wg-opt-desc">
                                    <?= t('technical.pipe_build_cost') ?>:
                                    <strong class="cv-gold"><?= number_format((float)($w['_pipelineBuildCost'] ?? 0), 0, '.', ' ') ?> <?= t('common.pln') ?></strong>
                                </div>
                                <?php endif ?>
                                <?php if ($tCode === 'rurociag' && !$w['_isOffshore'] && !empty($w['_hubName'])): ?>
                                <div class="wg-opt-desc">
                                    <?= t('well_grid.transport_pipeline_hub_bound', ['hub' => $w['_hubName']]) ?>
                                </div>
                                <?php endif ?>
                                <div class="wg-mult-grid" style="grid-template-columns:repeat(3,1fr)">
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-lbl"><?= t('wg.transport_capacity') ?></div>
                                        <div class="wg-mult-val <?= $__capCls ?>"><?= $tCap ?>%</div>
                                    </div>
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-lbl"><?= t('wg.transport_opex') ?></div>
                                        <div class="wg-mult-val <?= $__opxCls ?>"><?= $tOpex ?>%</div>
                                    </div>
                                    <div class="wg-mult-item">
                                        <div class="wg-mult-lbl"><?= t('wg.transport_risk_short') ?></div>
                                        <div class="wg-mult-val <?= $__rCls ?>"><?= $__rVal ?></div>
                                    </div>
                                </div>
                                <?php if (!$isCurrent): ?>
                                <div class="wg-opt-footer">
                                    <button class="wg-opt-btn wg-opt-btn--primary"
                                        onclick="wgSetTransport(<?= $wid ?>, '<?= $tCode ?>', <?= !empty($w['_pipelineOwned']) ? 'true' : 'false' ?>, <?= htmlspecialchars(json_encode((float)($w['_pipelineBuildCost'] ?? 0), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($tName, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, <?= $tCap ?>, <?= $tOpex ?>, <?= htmlspecialchars(json_encode($__rVal, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                                        <?= t('wg.transport_btn_switch') ?>
                                    </button>
                                </div>
                                <?php endif ?>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>
                        </div>
                    </div>
                </div>
