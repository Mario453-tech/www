    <?php
        $pipelineHseTone = match ($pipelineHse['state'] ?? 'none') {
            'full' => 'ok',
            'partial' => 'warn',
            default => 'danger',
        };
        $pipelineTechTone = ($pipelineSummary['engineers'] ?? 0) <= 0
            ? 'danger'
            : (($pipelineSummary['maintenance_overdue'] ?? 0) > 0 || ($pipelineSummary['critical'] ?? 0) > 0 ? 'warn' : 'ok');
    ?>
    <section class="logistics-panel" aria-labelledby="logistics-pipelines-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-pipelines-heading"><?= t('logistics.pipeline.title') ?></h3>
            <span><?= t('logistics.pipeline.desc') ?></span>
        </div>

        <div class="logistics-insight-summary">
            <div class="logistics-insight-pill logistics-insight-pill--info">
                <span><?= t('logistics.pipeline.pill_total') ?></span>
                <strong><?= (int)($pipelineSummary['total'] ?? 0) ?></strong>
            </div>
            <div class="logistics-insight-pill <?= (int)($pipelineSummary['critical'] ?? 0) > 0 ? 'logistics-insight-pill--danger' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.pipeline.pill_critical') ?></span>
                <strong><?= (int)($pipelineSummary['critical'] ?? 0) ?></strong>
            </div>
            <div class="logistics-insight-pill <?= (int)($pipelineSummary['needs_service'] ?? 0) > 0 ? 'logistics-insight-pill--warn' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.pipeline.pill_service') ?></span>
                <strong><?= (int)($pipelineSummary['needs_service'] ?? 0) ?></strong>
            </div>
            <div class="logistics-insight-pill <?= (float)($pipelineSummary['avg_condition'] ?? 0) < 70 ? 'logistics-insight-pill--warn' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.pipeline.pill_condition') ?></span>
                <strong><?= number_format((float)($pipelineSummary['avg_condition'] ?? 0), 1, ',', ' ') ?>%</strong>
            </div>
            <div class="logistics-insight-pill logistics-insight-pill--info">
                <span><?= t('logistics.pipeline.pill_cost') ?></span>
                <strong><?= number_format((float)($pipelineSummary['avg_cost'] ?? 0), 2, ',', ' ') ?> <?= $currencyLabel ?></strong>
            </div>
        </div>

        <div class="logistics-pipeline-support">
            <article class="logistics-pipeline-state logistics-pipeline-state--<?= $pipelineHseTone ?>">
                <div class="logistics-pipeline-state-head">
                    <h4><?= t('logistics.pipeline.hse_title') ?></h4>
                    <span class="badge"><?= t('logistics.pipeline.hse_state_' . ($pipelineHse['state'] ?? 'none')) ?></span>
                </div>
                <p><?= t('logistics.pipeline.hse_summary', [
                    'count' => (int)($pipelineHse['pipelines'] ?? 0),
                    'units' => (int)($pipelineHse['supervised_units'] ?? 0),
                    'failure_pct' => (int)($pipelineHse['failure_pct'] ?? 0),
                    'cat_pct' => (int)($pipelineHse['catastrophe_pct'] ?? 0),
                ]) ?></p>
                <?php if (!empty($pipelineHse['label'])): ?>
                <small><?= htmlspecialchars($pipelineHse['label']) ?></small>
                <?php endif ?>
                <a class="btn btn-sm btn-secondary logistics-reco-btn" href="/technical?tab=safety"><?= t('logistics.pipeline.cta_hse') ?></a>
            </article>

            <article class="logistics-pipeline-state logistics-pipeline-state--<?= $pipelineTechTone ?>">
                <div class="logistics-pipeline-state-head">
                    <h4><?= t('logistics.pipeline.tech_title') ?></h4>
                    <span class="badge"><?= t('logistics.pipeline.tech_state_' . ((int)($pipelineSummary['engineers'] ?? 0) > 0 ? 'staffed' : 'empty')) ?></span>
                </div>
                <p><?= t('logistics.pipeline.tech_summary', [
                    'engineers' => (int)($pipelineSummary['engineers'] ?? 0),
                    'overdue' => (int)($pipelineSummary['maintenance_overdue'] ?? 0),
                    'critical' => (int)($pipelineSummary['critical'] ?? 0),
                ]) ?></p>
                <small><?= t('logistics.pipeline.tech_note') ?></small>
                <a class="btn btn-sm btn-secondary logistics-reco-btn" href="/technical?tab=infra"><?= t('logistics.pipeline.cta_technical') ?></a>
            </article>
        </div>

        <?php if (empty($pipelines)): ?>
            <div class="logistics-empty"><?= t('logistics.pipeline.empty') ?></div>
        <?php else: ?>
        <div class="logistics-pipeline-grid">
            <?php foreach ($pipelines as $pipe):
                $status = (string)($pipe['status'] ?? 'active');
                $conditionPct = (float)($pipe['condition_pct'] ?? 100);
                $conditionClass = $conditionPct < 40 ? 'c-bad' : ($conditionPct < 70 ? 'c-warn' : 'c-good');
                $utilizationPct = (float)($pipe['utilization_pct'] ?? 0);
                $utilizationClass = $utilizationPct >= 90 ? 'c-bad' : ($utilizationPct >= 70 ? 'c-warn' : 'c-good');
                $maintenanceHours = $pipe['maintenance_hours'] ?? null;
                $maintenanceLabel = $maintenanceHours === null
                    ? t('logistics.pipeline.maint_never')
                    : t('logistics.pipeline.maint_hours', ['hours' => (int)$maintenanceHours]);
            ?>
            <?php if ($status === 'building'): ?>
            <!-- Building pipeline card with countdown / Karta rurociagu w budowie z odliczaniem -->
            <article class="logistics-pipeline-card logistics-pipeline-card--building">
                <div class="logistics-pipeline-card-head">
                    <div>
                        <h4><?= htmlspecialchars((string)($pipe['name'] ?? t('logistics.pipeline.fallback_name', ['id' => (int)($pipe['id'] ?? 0)]))) ?></h4>
                        <span><?= htmlspecialchars((string)($pipe['well_name'] ?? ('#' . (int)($pipe['source_well_id'] ?? 0)))) ?></span>
                        <small><?= t('logistics.pipeline.type_' . ((string)($pipe['pipeline_type'] ?? 'standard'))) ?></small>
                    </div>
                    <span class="badge logistics-pipeline-badge logistics-pipeline-badge--building"><?= t('logistics.pipeline.status_building') ?></span>
                </div>

                <div class="logistics-pipeline-building-info">
                    <div class="logistics-pipeline-building-row">
                        <span><?= t('logistics.pipeline.building_label_cost') ?></span>
                        <strong><?= number_format((float)($pipe['build_cost'] ?? 0), 2, ',', ' ') ?> <?= $currencyLabel ?></strong>
                    </div>
                    <div class="logistics-pipeline-building-row">
                        <span><?= t('logistics.pipeline.building_label_finish') ?></span>
                        <strong>
                            <?php
                                $finishTs = !empty($pipe['build_finish_at']) ? strtotime($pipe['build_finish_at']) : 0;
                                echo $finishTs ? date('d.m H:i', $finishTs) : '-';
                            ?>
                        </strong>
                    </div>
                    <div class="logistics-pipeline-building-row">
                        <span><?= t('logistics.pipeline.building_label_remaining') ?></span>
                        <strong class="pipeline-countdown c-warn"
                                data-finish="<?= htmlspecialchars((string)($pipe['build_finish_at'] ?? ''), ENT_QUOTES) ?>"
                                data-seconds="<?= max(0, (int)($pipe['seconds_remaining'] ?? 0)) ?>">
                            <?php
                                $sec = max(0, (int)($pipe['seconds_remaining'] ?? 0));
                                $h   = (int)floor($sec / 3600);
                                $m   = (int)floor(($sec % 3600) / 60);
                                $s   = $sec % 60;
                                echo ($h > 0 ? $h . 'h ' : '') . $m . 'min ' . $s . 's';
                            ?>
                        </strong>
                    </div>
                    <div class="logistics-pipeline-building-progress">
                        <?php
                            $startTs  = !empty($pipe['build_started_at']) ? strtotime($pipe['build_started_at']) : 0;
                            $totalSec = $startTs && $finishTs ? max(1, $finishTs - $startTs) : 1;
                            $doneSec  = $startTs ? max(0, time() - $startTs) : 0;
                            $pct      = min(100, round($doneSec / $totalSec * 100));
                        ?>
                        <div class="logistics-pipeline-progress-bar">
                    <div class="logistics-pipeline-progress-fill" data-progress-width="<?= $pct ?>"></div>
                        </div>
                        <small class="c-muted2"><?= $pct ?>%</small>
                    </div>
                </div>
            </article>
            <?php else: ?>
            <article class="logistics-pipeline-card<?= !empty($pipe['is_critical']) ? ' is-critical' : (!empty($pipe['is_degraded']) ? ' is-degraded' : '') ?>">
                <div class="logistics-pipeline-card-head">
                    <div>
                        <h4><?= htmlspecialchars((string)($pipe['name'] ?? t('logistics.pipeline.fallback_name', ['id' => (int)($pipe['id'] ?? 0)]))) ?></h4>
                        <span><?= htmlspecialchars((string)($pipe['well_name'] ?? ('#' . (int)($pipe['source_well_id'] ?? 0)))) ?></span>
                        <small><?= t('logistics.pipeline.type_' . ((string)($pipe['pipeline_type'] ?? 'standard'))) ?></small>
                    </div>
                    <span class="badge logistics-pipeline-badge logistics-pipeline-badge--<?= htmlspecialchars($status) ?>"><?= t('logistics.pipeline.status_' . $status) ?></span>
                </div>

                <div class="logistics-pipeline-stats">
                    <div>
                        <span><?= t('logistics.pipeline.label_flow') ?></span>
                        <strong><?= number_format((float)($pipe['flow_bbl_h'] ?? 0), 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong>
                    </div>
                    <div>
                        <span><?= t('logistics.pipeline.label_capacity') ?></span>
                        <strong><?= number_format((float)($pipe['capacity_bbl_h'] ?? 0), 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong>
                    </div>
                    <div>
                        <span><?= t('logistics.pipeline.label_loss') ?></span>
                        <strong class="<?= (float)($pipe['transport_loss'] ?? 0) >= 6 ? 'c-bad' : ((float)($pipe['transport_loss'] ?? 0) > 0 ? 'c-warn' : 'c-good') ?>"><?= number_format((float)($pipe['transport_loss'] ?? 0), 2, ',', ' ') ?>% / <?= number_format((float)($pipe['loss_bbl_h'] ?? 0), 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong>
                    </div>
                    <div>
                        <span><?= t('logistics.pipeline.label_condition') ?></span>
                        <strong class="<?= $conditionClass ?>"><?= number_format($conditionPct, 1, ',', ' ') ?>%</strong>
                    </div>
                    <div>
                        <span><?= t('logistics.pipeline.label_utilization') ?></span>
                        <strong class="<?= $utilizationClass ?>"><?= number_format($utilizationPct, 1, ',', ' ') ?>%</strong>
                    </div>
                    <div>
                        <span><?= t('logistics.pipeline.label_maintenance') ?></span>
                        <strong><?= $maintenanceLabel ?></strong>
                    </div>
                    <div>
                        <span><?= t('logistics.pipeline.label_cost') ?></span>
                        <strong><?= number_format((float)($pipe['total_cost_est'] ?? 0), 2, ',', ' ') ?> <?= $currencyLabel ?></strong>
                    </div>
                    <div>
                        <span><?= t('logistics.pipeline.label_risk') ?></span>
                        <strong class="<?= (float)($pipe['risk_factor'] ?? 1.0) > 1.0 ? 'c-warn' : 'c-good' ?>">x<?= number_format((float)($pipe['risk_factor'] ?? 1.0), 2, ',', ' ') ?></strong>
                    </div>
                </div>

                <div class="logistics-pipeline-meta">
                    <?php if (!empty($pipe['maintenance_overdue'])): ?>
                    <span class="badge logistics-pipeline-meta-badge logistics-pipeline-meta-badge--warn"><?= t('logistics.pipeline.badge_overdue') ?></span>
                    <?php endif ?>
                    <?php if (!empty($pipe['needs_service'])): ?>
                    <span class="badge logistics-pipeline-meta-badge logistics-pipeline-meta-badge--danger"><?= t('logistics.pipeline.badge_service') ?></span>
                    <?php endif ?>
                    <?php if (($pipelineSummary['engineers'] ?? 0) <= 0): ?>
                    <span class="badge logistics-pipeline-meta-badge logistics-pipeline-meta-badge--danger"><?= t('logistics.pipeline.badge_no_engineer') ?></span>
                    <?php endif ?>
                    <?php if (($pipelineHse['state'] ?? 'none') !== 'full'): ?>
                    <span class="badge logistics-pipeline-meta-badge logistics-pipeline-meta-badge--warn"><?= t('logistics.pipeline.badge_hse_watch') ?></span>
                    <?php endif ?>
                </div>

                <?php
 // Estimated action costs for confirm dialogs
                $pDamage     = max(0.0, 100.0 - $conditionPct);
                $pRepairCost = max(2000.0, round((float)($pipe['build_cost'] ?? 0) * ($pDamage / 100.0) * 0.30));
                $pMaintCost  = max(500.0, round((float)($pipe['tick_cost_est'] ?? 0) * 24.0 * 0.4));
                $isSuspended  = ($status === 'suspended');
                $isServicing  = ($status === 'servicing');
                $canRepair    = ($pDamage > 0.1) && !$isSuspended && !in_array($status, ['building','disabled','servicing'], true);
                $canMaint     = !in_array($status, ['building','disabled','servicing'], true);
                $canToggle    = !in_array($status, ['building','disabled','planned','servicing'], true);
                ?>
                <div class="logistics-pipeline-actions">
                    <?php if ($isServicing): ?>
                    <span class="badge logistics-pipeline-badge logistics-pipeline-badge--servicing logistics-pipeline-badge--action-status">
                        <?= t('logistics.pipeline.status_servicing') ?> &mdash; <?= t('logistics.pipeline.servicing_no_actions') ?>
                    </span>
                    <?php endif ?>
                    <?php if ($canRepair): ?>
                    <button class="btn btn-xs btn-primary"
                            type="button"
                            data-pipeline-action="repair_pipeline"
                            data-pipeline-id="<?= (int)$pipe['id'] ?>"
                            data-confirm="<?= htmlspecialchars(t('logistics.pipeline.confirm_repair', ['cost' => number_format($pRepairCost, 0, ',', ' ')]), ENT_QUOTES, 'UTF-8') ?>">
                        <?= t('logistics.pipeline.btn_repair') ?>
                    </button>
                    <?php endif ?>
                    <?php if ($canMaint): ?>
                    <button class="btn btn-xs btn-secondary"
                            type="button"
                            data-pipeline-action="maintenance_pipeline"
                            data-pipeline-id="<?= (int)$pipe['id'] ?>"
                            data-confirm="<?= htmlspecialchars(t('logistics.pipeline.confirm_maint', ['cost' => number_format($pMaintCost, 0, ',', ' ')]), ENT_QUOTES, 'UTF-8') ?>">
                        <?= t('logistics.pipeline.btn_maintenance') ?>
                    </button>
                    <?php endif ?>
                    <?php if ($canToggle):
                        $pipeName    = (string)($pipe['name'] ?? ('#' . (int)$pipe['id']));
                        $confirmSusp = t('logistics.pipeline.confirm_suspend_named', ['name' => $pipeName]);
                        $confirmRes  = t('logistics.pipeline.confirm_resume_named',  ['name' => $pipeName]);
                        $labelSusp   = t('logistics.pipeline.btn_suspend');
                        $labelRes    = t('logistics.pipeline.btn_resume');
                    ?>
                    <button class="btn btn-xs <?= $isSuspended ? 'btn-secondary' : 'btn-danger' ?>"
                            data-pipeline-toggle="<?= (int)$pipe['id'] ?>"
                            data-suspended="<?= $isSuspended ? '1' : '0' ?>"
                            data-confirm-suspend="<?= htmlspecialchars($confirmSusp, ENT_QUOTES) ?>"
                            data-confirm-resume="<?= htmlspecialchars($confirmRes, ENT_QUOTES) ?>"
                            data-label-suspend="<?= htmlspecialchars($labelSusp, ENT_QUOTES) ?>"
                            data-label-resume="<?= htmlspecialchars($labelRes, ENT_QUOTES) ?>">
                        <?= $isSuspended ? $labelRes : $labelSusp ?>
                    </button>
                    <?php endif ?>
                </div>
            </article>
            <?php endif ?><!-- end building/active branch -->
            <?php endforeach ?>
        </div>
        <?php endif ?>

        <?php if (!empty($wellsWithoutPipeline)): ?>
        <div class="logistics-pipeline-nopipe">
            <h4 class="logistics-pipeline-nopipe-title"><?= t('logistics.pipeline.nopipe_title') ?></h4>
            <p class="logistics-pipeline-nopipe-desc"><?= t('logistics.pipeline.nopipe_desc') ?></p>
            <div class="logistics-pipeline-nopipe-grid">
                <?php foreach ($wellsWithoutPipeline as $npw): ?>
                <div class="logistics-pipeline-nopipe-card">
                    <div class="logistics-pipeline-nopipe-card-head">
                        <strong><?= htmlspecialchars((string)($npw['well_name'] ?? ('#' . (int)($npw['id'] ?? 0)))) ?></strong>
                        <span class="c-muted2"><?= htmlspecialchars((string)($npw['location_name'] ?? '')) ?></span>
                    </div>
                    <div class="logistics-pipeline-nopipe-card-hub">
                        <?= t('logistics.pipeline.nopipe_hub') ?>: <em><?= htmlspecialchars((string)($npw['hub_name'] ?? ('#' . (int)($npw['hub_id'] ?? 0)))) ?></em>
                    </div>
                    <?php if (($npw['well_status'] ?? '') === 'servicing'): ?>
                    <span class="badge logistics-pipeline-badge logistics-pipeline-badge--servicing logistics-pipeline-badge--compact"><?= t('logistics.pipeline.status_servicing') ?></span>
                    <?php else: ?>
                    <button class="btn btn-xs btn-primary" type="button"
                            data-pipeline-buy-open="<?= (int)($npw['id'] ?? 0) ?>">
                        <?= t('logistics.pipeline.nopipe_btn') ?>
                    </button>
                    <?php endif ?>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>
    </section>
