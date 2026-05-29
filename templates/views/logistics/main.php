<div class="logistics-page">
    <section class="logistics-kpi-grid" aria-label="<?= htmlspecialchars(t('logistics.kpi_aria')) ?>">
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_efficiency') ?></span>
            <strong class="<?= $efficiency >= 90 ? 'c-good' : ($efficiency >= 75 ? 'c-warn' : 'c-bad') ?>"><?= number_format($efficiency, 1, ',', ' ') ?>%</strong>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_loss') ?></span>
            <strong class="<?= $lossPct >= 15 ? 'c-bad' : ($lossPct >= 8 ? 'c-warn' : 'c-good') ?>"><?= number_format((float)$totals['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_cost') ?></span>
            <strong><?= number_format((float)$totals['cost'], 2, ',', ' ') ?> PLN/h</strong>
        </div>
        <div class="logistics-kpi">
            <span class="logistics-kpi-label"><?= t('logistics.kpi_wells') ?></span>
            <strong><?= count($wells) ?></strong>
        </div>
    </section>

    <?php
        $hasUnassignedWells = !empty($hubUnassigned);
        $hasAvailableRegions = !empty($hubAvailByRegion);
    ?>
    <section class="logistics-panel" aria-labelledby="logistics-available-hubs-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-available-hubs-heading"><?= t('logistics.hub.avail_section_title') ?></h3>
            <span><?= t('logistics.hub.avail_section_desc') ?></span>
        </div>

        <?php if (!$hasAvailableRegions): ?>
        <div class="logistics-empty"><?= t('logistics.hub.avail_no_regions') ?></div>
        <?php else: ?>

        <div class="logistics-hub-filter">
            <input class="logistics-hub-search" type="search" id="lhb-search"
                   placeholder="<?= htmlspecialchars(t('logistics.hub.filter_placeholder')) ?>" autocomplete="off">
            <div class="logistics-hub-filter-chips">
                <button class="logistics-filter-chip active" type="button" data-lhb-filter="all"><?= t('logistics.hub.filter_all') ?></button>
                <button class="logistics-filter-chip" type="button" data-lhb-filter="free"><?= t('logistics.hub.filter_free') ?></button>
                <button class="logistics-filter-chip" type="button" data-lhb-filter="new"><?= t('logistics.hub.filter_new') ?></button>
                <button class="logistics-filter-chip" type="button" data-lhb-filter="used"><?= t('logistics.hub.filter_used') ?></button>
                <button class="logistics-filter-chip" type="button" data-lhb-filter="rental"><?= t('logistics.hub.filter_rental') ?></button>
                <button class="logistics-filter-chip" type="button" data-lhb-filter="large"><?= t('logistics.hub.type_large') ?></button>
                <button class="logistics-filter-chip" type="button" data-lhb-filter="medium"><?= t('logistics.hub.type_medium') ?></button>
                <button class="logistics-filter-chip" type="button" data-lhb-filter="small"><?= t('logistics.hub.type_small') ?></button>
            </div>
            <span id="lhb-count"
                  class="logistics-filter-count"
                  data-filter-template="<?= htmlspecialchars(t('logistics.hub.filter_count', ['shown' => '{shown}', 'total' => '{total}']), ENT_QUOTES) ?>"></span>
        </div>
        <div class="logistics-hub-filter-note"><?= t('logistics.hub.preview_limit_note', ['count' => 5]) ?></div>

        <div id="lhb-browser" class="logistics-hub-browser">
        <?php foreach ($hubAvailByRegion as $rgIdx => $regionGroup): ?>
        <?php
            $rHubs      = $regionGroup['hubs'] ?? [];
            $rHubCount  = count($rHubs);
            $rFreeSlots = 0;
            $rPreviewLimit = 5;
            foreach ($rHubs as $_h) {
                $rFreeSlots += max(0, (int)($_h['slots_avail'] ?? 0));
            }
            $rHasFree = $rFreeSlots > 0;
        ?>
        <div class="logistics-region-group<?= $rgIdx === 0 ? ' is-open' : '' ?>"
             data-region-id="<?= (int)($regionGroup['region_id'] ?? 0) ?>"
             data-region-name-lc="<?= htmlspecialchars(mb_strtolower($regionGroup['region_name'] ?? ''), ENT_QUOTES) ?>">

            <button class="logistics-region-toggle" type="button" data-lhb-toggle>
                <span class="logistics-region-caret"></span>
                <span class="logistics-region-title-wrap">
                    <span class="logistics-region-title"><?= htmlspecialchars($regionGroup['region_name'] ?? ('Region #' . (int)($regionGroup['region_id'] ?? $rgIdx))) ?></span>
                    <span class="logistics-region-subtitle"><?= t('logistics.hub.region_summary', ['count' => $rHubCount]) ?></span>
                </span>
                <span class="logistics-region-badge<?= $rHasFree ? ' has-free' : '' ?>">
                    <?= t('logistics.hub.region_stats', ['free' => $rFreeSlots, 'count' => $rHubCount]) ?>
                </span>
            </button>

            <div class="logistics-region-body">
            <?php if (empty($rHubs)): ?>
                <div class="logistics-empty" style="padding:12px 14px"><?= t('logistics.hub.avail_none_in_region') ?></div>
            <?php else: ?>
                <div class="logistics-hub-avail-grid">
                <?php foreach ($rHubs as $hubIdx => $hub): ?>
                <?php
                    $hId        = (int)($hub['id'] ?? 0);
                    $slotsAvail = max(0, (int)($hub['slots_avail'] ?? 0));
                    $slotLimit  = max(0, (int)($hub['slot_limit'] ?? $hub['slots_total'] ?? 0));
                    $assignedN  = $slotLimit > 0 ? max(0, $slotLimit - $slotsAvail) : 0;
                    $isFull     = !empty($hub['slots_full']) || $slotsAvail === 0;
                    $hStatus    = $hub['status'] ?? 'active';
                    $leaseFee   = (float)($hub['lease_fee_per_tick'] ?? 0);
                    $acqType    = (string)($hub['acquisition_type'] ?? 'new');
                    $hubType    = $hub['hub_type'] ?? 'small';
                    $workMode   = $hub['work_mode'] ?? 'standard';
                    $condPct    = isset($hub['condition_pct']) ? (float)$hub['condition_pct'] : -1;
                    $condClass  = $condPct < 0 ? 'c-muted2'
                                : ($condPct <= 30 ? 'c-bad' : ($condPct <= 60 ? 'c-warn' : 'c-good'));
                    $tierKey    = match($workMode) {
                        'premium' => 'prem',
                        'elite'   => 'elite',
                        default   => 'std',
                    };
                    // Dots: show up to 8; use slotLimit if known, else slotsAvail
                    $dotTotal   = $slotLimit > 0 ? min(8, $slotLimit) : min(8, max(1, $slotsAvail));
                    $hRegionId  = (int)($regionGroup['region_id'] ?? 0);
                    $hZoneKey   = (string)($hub['zone_key'] ?? '');
                    $hName      = (string)($hub['name'] ?? ('Hub #' . $hId));
                    $statusKey  = 'logistics.hub.status_' . $hStatus;
                    $statusText = t($statusKey) !== $statusKey ? t($statusKey) : ucfirst($hStatus);
                    $acqLabelKey = 'logistics.hub.acquisition_' . $acqType;
                    $acqLabel = t($acqLabelKey) !== $acqLabelKey ? t($acqLabelKey) : $acqType;
                    $cardClasses = ['logistics-hub-avail-card', 'hub-status-' . preg_replace('/[^a-z0-9_-]/i', '', $hStatus)];
                    if ($isFull) {
                        $cardClasses[] = 'slots-full';
                    }
                    if ($hubIdx >= $rPreviewLimit) {
                        $cardClasses[] = 'is-preview-hidden';
                    }
                ?>
                <article class="<?= implode(' ', $cardClasses) ?>"
                         data-lhb-card
                         data-hub-id="<?= $hId ?>"
                         data-hub-type="<?= htmlspecialchars($hubType) ?>"
                         data-hub-free="<?= $slotsAvail ?>"
                         data-hub-name-lc="<?= htmlspecialchars(mb_strtolower($hub['name'] ?? ''), ENT_QUOTES) ?>"
                         data-hub-name="<?= htmlspecialchars($hName, ENT_QUOTES) ?>"
                         data-hub-acq-type="<?= htmlspecialchars($acqType, ENT_QUOTES) ?>"
                         data-hub-lease-fee="<?= number_format($leaseFee, 2, '.', '') ?>"
                         data-hub-region-id="<?= $hRegionId ?>"
                         data-hub-zone-key="<?= htmlspecialchars($hZoneKey, ENT_QUOTES) ?>">
                    <div class="logistics-hub-avail-top">
                        <div>
                            <div class="logistics-hub-avail-name"><?= htmlspecialchars($hName) ?></div>
                            <?php if ($hZoneKey !== ''): ?>
                            <div class="logistics-hub-avail-zone"><?= htmlspecialchars($hZoneKey) ?></div>
                            <?php endif ?>
                        </div>
                        <span class="badge"><?= htmlspecialchars($statusText) ?></span>
                    </div>

                    <div class="logistics-hub-avail-meta">
                        <span class="badge logistics-hub-type-<?= $hubType ?>"><?= t('logistics.hub.type_' . $hubType) ?></span>
                        <span class="badge logistics-tier-<?= $tierKey ?>"><?= t('logistics.hub.mode_' . $workMode) ?></span>
                        <span class="acq-badge acq-badge--<?= htmlspecialchars($acqType) ?>"><?= htmlspecialchars($acqLabel) ?></span>
                    </div>

                    <div class="logistics-hub-avail-stats">
                        <div class="logistics-hub-avail-stat">
                            <span class="logistics-hub-avail-label"><?= t('logistics.hub.col_slots') ?></span>
                            <div class="logistics-hub-avail-value">
                                <div class="logistics-slot-dots">
                                    <?php for ($d = 0; $d < $dotTotal; $d++): ?>
                                    <span class="logistics-slot-dot<?= ($slotLimit > 0 && $d < $assignedN) ? ' used' : '' ?>"></span>
                                    <?php endfor ?>
                                </div>
                                <span class="logistics-slot-count<?= $isFull ? ' full' : '' ?>"><?= $slotsAvail ?> / <?= $slotLimit ?></span>
                            </div>
                        </div>
                        <div class="logistics-hub-avail-stat">
                            <span class="logistics-hub-avail-label"><?= t('logistics.hub.col_condition') ?></span>
                            <span class="logistics-hub-avail-value <?= $condPct >= 0 ? $condClass : 'c-muted2' ?>">
                                <?= $condPct >= 0 ? number_format($condPct, 0) . '%' : '&mdash;' ?>
                            </span>
                        </div>
                        <div class="logistics-hub-avail-stat">
                            <span class="logistics-hub-avail-label"><?= t('logistics.hub.col_fee') ?></span>
                            <span class="logistics-hub-avail-value">
                                <?= $leaseFee > 0 ? number_format($leaseFee, 2, ',', ' ') . ' PLN' : '&mdash;' ?>
                            </span>
                        </div>
                    </div>

                    <div class="logistics-hub-avail-footer">
                        <?php if ($hStatus === 'disabled'): ?>
                        <span class="badge" style="opacity:.35;font-size:.7rem"><?= t('logistics.hub.badge_disabled') ?></span>
                        <?php elseif ($isFull): ?>
                        <span class="badge" style="opacity:.35;font-size:.7rem"><?= t('logistics.hub.badge_full') ?></span>
                        <?php else: ?>
                        <button class="logistics-hub-assign-btn" type="button" onclick="hubAssignWellToHubModal(<?= $hId ?>)">
                            <?= t('logistics.hub.avail_btn_assign') ?>
                        </button>
                        <?php endif ?>
                    </div>
                </article>
                <?php endforeach ?>
                </div>

                <?php if ($rHubCount > $rPreviewLimit): ?>
                <div class="logistics-region-more">
                    <button class="btn btn-xs btn-secondary logistics-region-more-btn"
                            type="button"
                            data-lhb-expand
                            data-expanded-label="<?= htmlspecialchars(t('logistics.hub.show_less'), ENT_QUOTES) ?>"
                            data-collapsed-label="<?= htmlspecialchars(t('logistics.hub.show_all', ['count' => $rHubCount]), ENT_QUOTES) ?>">
                        <?= t('logistics.hub.show_all', ['count' => $rHubCount]) ?>
                    </button>
                </div>
                <?php endif ?>
            <?php endif ?>
            </div>
        </div>
        <?php endforeach ?>
        </div><!-- /lhb-browser -->

        <?php endif ?>
    </section>

    <section class="logistics-alerts">
        <?php foreach ($alerts as $alert): ?>
        <div class="logistics-alert logistics-alert--<?= htmlspecialchars($alert['type']) ?>">
            <?= htmlspecialchars($alert['text']) ?>
        </div>
        <?php endforeach ?>
    </section>


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
                <strong><?= number_format((float)($pipelineSummary['avg_cost'] ?? 0), 2, ',', ' ') ?> PLN</strong>
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
                <a class="btn btn-sm btn-secondary logistics-reco-btn" href="/technical.php?tab=safety"><?= t('logistics.pipeline.cta_hse') ?></a>
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
                <a class="btn btn-sm btn-secondary logistics-reco-btn" href="/technical.php?tab=infra"><?= t('logistics.pipeline.cta_technical') ?></a>
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
                        <strong><?= number_format((float)($pipe['build_cost'] ?? 0), 2, ',', ' ') ?> PLN</strong>
                    </div>
                    <div class="logistics-pipeline-building-row">
                        <span><?= t('logistics.pipeline.building_label_finish') ?></span>
                        <strong>
                            <?php
                                $finishTs = !empty($pipe['build_finish_at']) ? strtotime($pipe['build_finish_at']) : 0;
                                echo $finishTs ? date('d.m H:i', $finishTs) : 'â€”';
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
                            <div class="logistics-pipeline-progress-fill" style="width:<?= $pct ?>%"></div>
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
                        <strong><?= number_format((float)($pipe['total_cost_est'] ?? 0), 2, ',', ' ') ?> PLN</strong>
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
                $isSuspended = ($status === 'suspended');
                $canRepair   = ($pDamage > 0.1) && !$isSuspended && !in_array($status, ['building','disabled'], true);
                $canMaint    = !in_array($status, ['building','disabled'], true);
                $canToggle   = !in_array($status, ['building','disabled','planned'], true);
                ?>
                <div class="logistics-pipeline-actions">
                    <?php if ($canRepair): ?>
                    <button class="btn btn-xs btn-primary"
                            onclick="pipelineActionConfirm(<?= (int)$pipe['id'] ?>, 'repair_pipeline',
                                <?= htmlspecialchars(json_encode(t('logistics.pipeline.confirm_repair', ['cost' => number_format($pRepairCost, 0, ',', ' ')])), ENT_QUOTES) ?>)">
                        <?= t('logistics.pipeline.btn_repair') ?>
                    </button>
                    <?php endif ?>
                    <?php if ($canMaint): ?>
                    <button class="btn btn-xs btn-secondary"
                            onclick="pipelineActionConfirm(<?= (int)$pipe['id'] ?>, 'maintenance_pipeline',
                                <?= htmlspecialchars(json_encode(t('logistics.pipeline.confirm_maint', ['cost' => number_format($pMaintCost, 0, ',', ' ')])), ENT_QUOTES) ?>)">
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
                            data-label-resume="<?= htmlspecialchars($labelRes, ENT_QUOTES) ?>"
                            onclick="pipelineToggleConfirm(this)">
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
                    <button class="btn btn-xs btn-primary"
                            onclick="openPipelineBuyModal(<?= (int)($npw['id'] ?? 0) ?>)">
                        <?= t('logistics.pipeline.nopipe_btn') ?>
                    </button>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>
    </section>
    <section class="logistics-panel" aria-labelledby="logistics-insights-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-insights-heading"><?= t('logistics.insight_title') ?></h3>
            <span><?= t('logistics.insight_desc') ?></span>
        </div>

        <div class="logistics-insight-summary">
            <div class="logistics-insight-pill <?= (int)($logisticsInsights['unassigned_count'] ?? 0) > 0 ? 'logistics-insight-pill--warn' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.insight_pill_unassigned') ?></span>
                <strong><?= (int)($logisticsInsights['unassigned_count'] ?? 0) ?></strong>
            </div>
            <div class="logistics-insight-pill <?= $lossPct >= 8 ? 'logistics-insight-pill--danger' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.insight_pill_loss') ?></span>
                <strong><?= number_format($lossPct, 1, ',', ' ') ?>%</strong>
            </div>
            <div class="logistics-insight-pill <?= !empty($logisticsInsights['hub_hotspots']) ? 'logistics-insight-pill--danger' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.insight_pill_hubs') ?></span>
                <strong><?= count($logisticsInsights['hub_hotspots'] ?? []) ?></strong>
            </div>
            <div class="logistics-insight-pill logistics-insight-pill--info">
                <span><?= t('logistics.insight_pill_cost') ?></span>
                <strong><?= number_format((float)$totals['cost'], 2, ',', ' ') ?> PLN/h</strong>
            </div>
        </div>

        <div class="logistics-insight-grid">
            <article class="logistics-insight-card">
                <div class="logistics-insight-card-hdr">
                    <h4><?= t('logistics.insight_loss_title') ?></h4>
                    <span><?= t('logistics.insight_loss_subtitle') ?></span>
                </div>
                <?php if (empty($logisticsInsights['loss_wells'])): ?>
                    <div class="logistics-insight-empty"><?= t('logistics.insight_loss_empty') ?></div>
                <?php else: ?>
                    <div class="logistics-insight-list">
                        <?php foreach ($logisticsInsights['loss_wells'] as $row): ?>
                        <div class="logistics-insight-row">
                            <div>
                                <strong>#<?= (int)$row['id'] ?></strong>
                                <span><?= t('logistics.type_' . ($row['transport'] ?? 'nieustawiony')) ?></span>
                            </div>
                            <strong class="c-bad"><?= number_format((float)$row['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong>
                        </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </article>

            <article class="logistics-insight-card">
                <div class="logistics-insight-card-hdr">
                    <h4><?= t('logistics.insight_cost_title') ?></h4>
                    <span><?= t('logistics.insight_cost_subtitle') ?></span>
                </div>
                <?php if (empty($logisticsInsights['cost_wells'])): ?>
                    <div class="logistics-insight-empty"><?= t('logistics.insight_cost_empty') ?></div>
                <?php else: ?>
                    <div class="logistics-insight-list">
                        <?php foreach ($logisticsInsights['cost_wells'] as $row): ?>
                        <div class="logistics-insight-row">
                            <div>
                                <strong>#<?= (int)$row['id'] ?></strong>
                                <span><?= t('logistics.type_' . ($row['transport'] ?? 'nieustawiony')) ?></span>
                            </div>
                            <strong><?= number_format((float)$row['cost'], 2, ',', ' ') ?> PLN/h</strong>
                        </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </article>

            <article class="logistics-insight-card">
                <div class="logistics-insight-card-hdr">
                    <h4><?= t('logistics.insight_hubs_title') ?></h4>
                    <span><?= t('logistics.insight_hubs_subtitle') ?></span>
                </div>
                <?php if (empty($logisticsInsights['hub_hotspots'])): ?>
                    <div class="logistics-insight-empty"><?= t('logistics.insight_hubs_empty') ?></div>
                <?php else: ?>
                    <div class="logistics-insight-list">
                        <?php foreach ($logisticsInsights['hub_hotspots'] as $row): ?>
                        <div class="logistics-insight-row logistics-insight-row--stack">
                            <div class="logistics-insight-row-main">
                                <strong><?= htmlspecialchars($row['name']) ?></strong>
                                <span><?= t('logistics.insight_hub_load', ['pct' => number_format((float)$row['load_pct'], 1, ',', ' ')]) ?></span>
                            </div>
                            <div class="logistics-insight-row-meta">
                                <span><?= t('logistics.insight_hub_condition', ['pct' => number_format((float)$row['condition_pct'], 1, ',', ' ')]) ?></span>
                                <?php if ((float)$row['lost_bbl'] > 0): ?>
                                <strong class="c-bad"><?= t('logistics.insight_hub_loss', ['bbl' => number_format((float)$row['lost_bbl'], 1, ',', ' ')]) ?></strong>
                                <?php endif ?>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </article>

            <article class="logistics-insight-card">
                <div class="logistics-insight-card-hdr">
                    <h4><?= t('logistics.insight_pipe_title') ?></h4>
                    <span><?= t('logistics.insight_pipe_subtitle') ?></span>
                </div>
                <?php if (empty($logisticsInsights['worst_pipelines'])): ?>
                    <div class="logistics-insight-empty"><?= t('logistics.insight_pipe_empty') ?></div>
                <?php else: ?>
                    <div class="logistics-insight-list">
                        <?php foreach ($logisticsInsights['worst_pipelines'] as $row):
                            $wpCond   = (float)($row['condition_pct']  ?? 100);
                            $wpLoss   = (float)($row['transport_loss'] ?? 0);
                            $wpStatus = (string)($row['status'] ?? 'active');
                            $wpCondCls = $wpCond < 40 ? 'c-bad' : ($wpCond < 70 ? 'c-warn' : 'c-good');
                            $wpLossCls = $wpLoss >= 6  ? 'c-bad' : ($wpLoss > 0  ? 'c-warn' : '');
                        ?>
                        <div class="logistics-insight-row logistics-insight-row--stack">
                            <div class="logistics-insight-row-main">
                                <strong><?= htmlspecialchars((string)($row['name'] ?? ('#' . (int)($row['id'] ?? 0)))) ?></strong>
                                <span class="badge logistics-pipeline-badge logistics-pipeline-badge--<?= htmlspecialchars($wpStatus) ?>"><?= t('logistics.pipeline.status_' . $wpStatus) ?></span>
                            </div>
                            <div class="logistics-insight-row-meta">
                                <span class="<?= $wpCondCls ?>"><?= t('logistics.insight_hub_condition', ['pct' => number_format($wpCond, 1, ',', ' ')]) ?></span>
                                <?php if ($wpLoss > 0): ?>
                                <span class="<?= $wpLossCls ?>"><?= t('logistics.pipeline.label_loss') ?>: <?= number_format($wpLoss, 1, ',', ' ') ?>%</span>
                                <?php endif ?>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </article>
        </div>

        <div class="logistics-reco-grid">
            <?php foreach (($logisticsInsights['recommendations'] ?? []) as $item): ?>
            <article class="logistics-reco-card logistics-reco-card--<?= htmlspecialchars($item['tone']) ?>">
                <div class="logistics-reco-body">
                    <h4><?= htmlspecialchars($item['title']) ?></h4>
                    <p><?= htmlspecialchars($item['text']) ?></p>
                </div>
                <a class="btn btn-sm btn-secondary logistics-reco-btn" href="<?= htmlspecialchars($item['cta_href']) ?>">
                    <?= htmlspecialchars($item['cta_label']) ?>
                </a>
            </article>
            <?php endforeach ?>
        </div>
    </section>

    <section class="logistics-panel" aria-labelledby="logistics-overview-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-overview-heading"><?= t('logistics.overview_title') ?></h3>
            <span><?= t('logistics.overview_subtitle') ?></span>
        </div>
        <div class="logistics-mix-grid">
            <?php foreach (['nieustawiony', 'rurociag', 'ciezarowki', 'tankowiec'] as $type):
                $row = $transportMix[$type] ?? ['count' => 0, 'transported' => 0, 'loss' => 0, 'cost' => 0];
            ?>
            <article class="logistics-mix-card" data-transport="<?= htmlspecialchars($type) ?>">
                <div class="logistics-mix-title"><?= t('logistics.type_' . $type) ?></div>
                <div class="logistics-mix-meta"><?= t('logistics.mix_wells', ['count' => (int)$row['count']]) ?></div>
                <div class="logistics-mix-stats">
                    <span><?= t('logistics.label_flow') ?> <strong><?= number_format((float)$row['transported'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong></span>
                    <span><?= t('logistics.label_loss') ?> <strong><?= number_format((float)$row['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong></span>
                    <span><?= t('logistics.label_cost') ?> <strong><?= number_format((float)$row['cost'], 2, ',', ' ') ?> PLN/h</strong></span>
                </div>
            </article>
            <?php endforeach ?>
        </div>
    </section>

    <!--  -->
    <!-- SEKCJA: Dostawy morskie (Etap 5 - tankowce w drodze)              -->
    <!--  -->
    <?php
        $marineDeliveriesList = is_array($marineDeliveries ?? null) ? $marineDeliveries : [];
        $marineHistoryList = is_array($marineHistory ?? null) ? $marineHistory : [];
        $marineInTransitBbl = (float)($marineInTransitBbl ?? 0.0);
        $wellTransportTypes = array_column(array_filter($wells, 'is_array'), 'transport');
        $hasMarineSection = !empty($marineDeliveriesList) || !empty($marineHistoryList) || $marineInTransitBbl > 0;
        if ($hasMarineSection || in_array('tankowiec', $wellTransportTypes, true)):
    ?>
    <section class="logistics-panel" aria-labelledby="logistics-marine-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-marine-heading"> <?= t('marine.section_title') ?></h3>
            <span><?= t('marine.section_desc') ?></span>
        </div>

        <!-- KPI morskie / Marine KPI -->
        <div class="logistics-insight-summary">
            <div class="logistics-insight-pill <?= $marineInTransitBbl > 0 ? 'logistics-insight-pill--info' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('marine.kpi_in_transit') ?></span>
                <strong><?= number_format($marineInTransitBbl, 1, ',', ' ') ?> <?= t('common.bbl') ?></strong>
            </div>
            <div class="logistics-insight-pill logistics-insight-pill--info">
                <span><?= t('marine.kpi_active') ?></span>
                <strong><?= count($marineDeliveriesList) ?></strong>
            </div>
        </div>

        <!-- Aktywne dostawy / Active deliveries -->
        <?php if (empty($marineDeliveriesList)): ?>
            <div class="logistics-empty"><?= t('marine.no_deliveries') ?></div>
        <?php else: ?>
        <div class="logistics-table">
            <div class="logistics-table-head" style="grid-template-columns:1fr 1.2fr 0.7fr 1fr 0.9fr">
                <span><?= t('marine.col_well') ?></span>
                <span><?= t('marine.col_port') ?></span>
                <span><?= t('marine.col_volume') ?></span>
                <span><?= t('marine.col_status') ?></span>
                <span><?= t('marine.col_eta') ?></span>
            </div>
            <?php foreach ($marineDeliveriesList as $del):
                $delStatus = (string)($del['status'] ?? 'in_transit');
                $statusClass = match($delStatus) {
                    'departing'        => 'c-muted2',
                    'in_transit'       => '',
                    'waiting_for_port' => 'c-warn',
                    'processing'       => 'c-good',
                    'delayed'          => 'c-bad',
                    default            => '',
                };
                $etaTs   = strtotime((string)($del['eta_at'] ?? ''));
                $etaStr  = $etaTs ? date('d.m H:i', $etaTs) : '-';
                $wellLabel = ($del['well_name'] ?? null)
                    ? htmlspecialchars($del['well_name'])
                    : t('marine.well_unknown', ['id' => (int)($del['well_id'] ?? 0)]);
                $portLabel = ($del['port_name'] ?? null)
                    ? htmlspecialchars($del['port_name'])
                    : t('marine.port_unknown');
            ?>
            <div class="logistics-table-row" style="grid-template-columns:1fr 1.2fr 0.7fr 1fr 0.9fr">
                <span><?= $wellLabel ?></span>
                <span><?= $portLabel ?></span>
                <span><?= number_format((float)($del['volume_bbl'] ?? 0), 1, ',', ' ') ?> bbl</span>
                <span class="<?= $statusClass ?>">
                    <?= t('marine.status_' . $delStatus) ?>
                    <?php if ((int)($del['delay_ticks'] ?? 0) > 0): ?>
                    <small class="c-bad" style="display:block;font-size:.7rem">
                        <?= t('marine.delay_ticks', ['n' => (int)$del['delay_ticks']]) ?>
                    </small>
                    <?php endif ?>
                </span>
                <span><?= htmlspecialchars($etaStr) ?></span>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>

        <!-- Historia dostaw / Delivery history -->
        <?php if (!empty($marineHistoryList)): ?>
        <div style="margin-top:18px">
            <div class="logistics-section-title" style="margin-bottom:8px"><?= t('marine.history_title') ?></div>
            <div class="logistics-table">
                <div class="logistics-table-head" style="grid-template-columns:1fr 1.2fr 0.7fr 1fr 0.9fr">
                    <span><?= t('marine.col_well') ?></span>
                    <span><?= t('marine.col_port') ?></span>
                    <span><?= t('marine.col_volume') ?></span>
                    <span><?= t('marine.col_status') ?></span>
                    <span><?= t('marine.col_eta') ?></span>
                </div>
                <?php foreach ($marineHistoryList as $hist):
                    $hStatus = (string)($hist['status'] ?? 'delivered');
                    $hClass  = $hStatus === 'lost' ? 'c-bad' : 'c-good';
                    $hTs     = strtotime((string)($hist['delivered_at'] ?? $hist['created_at'] ?? ''));
                    $hDate   = $hTs ? date('d.m H:i', $hTs) : '-';
                    $hWell   = ($hist['well_name'] ?? null)
                        ? htmlspecialchars($hist['well_name'])
                        : t('marine.well_unknown', ['id' => (int)($hist['well_id'] ?? 0)]);
                    $hPort   = ($hist['port_name'] ?? null)
                        ? htmlspecialchars($hist['port_name'])
                        : t('marine.port_unknown');
                ?>
                <div class="logistics-table-row" style="grid-template-columns:1fr 1.2fr 0.7fr 1fr 0.9fr;opacity:.7">
                    <span><?= $hWell ?></span>
                    <span><?= $hPort ?></span>
                    <span><?= number_format((float)($hist['volume_bbl'] ?? 0), 1, ',', ' ') ?> bbl</span>
                    <span class="<?= $hClass ?>"><?= t('marine.status_' . $hStatus) ?></span>
                    <span><?= htmlspecialchars($hDate) ?></span>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>
    </section>
    <?php endif ?>

    <section class="logistics-panel" aria-labelledby="logistics-optimizer-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-optimizer-heading"><?= t('logistics.optimizer_title') ?></h3>
            <span><?= t('logistics.optimizer_desc') ?></span>
        </div>
        <div class="logistics-optimizer-bar logistics-optimizer-bar--page">
            <div class="logistics-optimizer-info">
                <span class="logistics-icon"></span>
                <div>
                    <div class="logistics-title"><?= t('logistics.optimizer_card_title') ?></div>
                    <div class="logistics-desc"><?= t('logistics.optimizer_card_desc') ?></div>
                </div>
            </div>
            <button class="btn btn-primary btn-sm logistics-btn" onclick="openLogisticsModal()" id="btn-logistics-open">
                 <?= t('logistics.optimizer_btn') ?>
            </button>
        </div>
    </section>

    <section class="logistics-panel" aria-labelledby="logistics-transport-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-transport-heading"><?= t('logistics.transport_title') ?></h3>
            <span><?= t('logistics.transport_desc') ?></span>
        </div>
        <?php if (empty($wells)): ?>
            <div class="logistics-empty"><?= t('logistics.no_wells') ?></div>
        <?php else: ?>
        <div class="logistics-table">
            <div class="logistics-table-head">
                <span><?= t('logistics.col_well') ?></span>
                <span><?= t('logistics.col_type') ?></span>
                <span><?= t('logistics.col_transport') ?></span>
                <span><?= t('logistics.col_capacity') ?></span>
                <span><?= t('logistics.col_loss') ?></span>
                <span><?= t('logistics.col_cost') ?></span>
            </div>
            <?php foreach ($wells as $well): ?>
            <div class="logistics-table-row">
                <span>#<?= (int)$well['id'] ?></span>
                <span><?= t('logistics.well_type_' . ($well['well_type'] ?? 'onshore')) ?></span>
                <span><?= t('logistics.type_' . ($well['transport'] ?? 'nieustawiony')) ?></span>
                <span><?= number_format((float)$well['capacity_pct'], 1, ',', ' ') ?>%</span>
                <span class="<?= (float)$well['loss'] > 0 ? 'c-warn' : 'c-good' ?>"><?= number_format((float)$well['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></span>
                <span><?= number_format((float)$well['cost'], 2, ',', ' ') ?> PLN/h</span>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>

    <!-- ============================================================ -->
    <!-- SEKCJA: Aktywne kursy drogowe (P1.2)                       -->
    <!-- Active road trips in transit                                -->
    <!-- ============================================================ -->
    <?php if (!empty($activeRoadTrips)): ?>
    <section class="logistics-panel" aria-labelledby="logistics-road-trips-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-road-trips-heading"><?= t('logistics.road_trips.section_title') ?></h3>
        </div>
        <div class="logistics-table">
            <div class="logistics-table-head" style="grid-template-columns:2fr 1fr 1fr 1fr 2fr 1fr">
                <span><?= t('logistics.road_trips.col_well') ?></span>
                <span><?= t('logistics.road_trips.col_volume') ?></span>
                <span><?= t('logistics.road_trips.col_trips') ?></span>
                <span><?= t('logistics.road_trips.col_truck') ?></span>
                <span><?= t('logistics.road_trips.col_eta') ?></span>
                <span><?= t('logistics.road_trips.col_remaining') ?></span>
            </div>
            <?php foreach ($activeRoadTrips as $trip):
                $secRem = max(0, (int)($trip['seconds_remaining'] ?? 0));
                $hRem   = (int)floor($secRem / 3600);
                $mRem   = (int)floor(($secRem % 3600) / 60);
                $truckKey = 'logistics.road_trips.truck_' . ($trip['truck_type'] ?? 'standard');
            ?>
            <div class="logistics-table-row" style="grid-template-columns:2fr 1fr 1fr 1fr 2fr 1fr">
                <span><?= htmlspecialchars((string)($trip['well_name'] ?? ('#' . (int)$trip['well_id']))) ?></span>
                <span><?= number_format((float)($trip['volume_bbl'] ?? 0), 1, ',', ' ') ?> bbl</span>
                <span><?= (int)($trip['trips_count'] ?? 1) ?></span>
                <span><?= t($truckKey) ?></span>
                <span><?= htmlspecialchars(substr((string)($trip['eta_at'] ?? ''), 0, 16)) ?></span>
                <span class="c-warn">
                    <strong class="road-trip-countdown"
                            data-seconds="<?= $secRem ?>"><?= $hRem ?>h <?= str_pad((string)$mRem, 2, '0', STR_PAD_LEFT) ?>m</strong>
                </span>
            </div>
            <?php endforeach ?>
        </div>
    </section>
    <?php endif ?>

    <!--  -->
    <!-- SEKCJA: Twoje przypisane huby                                      -->
    <!--  -->
    <section class="logistics-panel" aria-labelledby="logistics-hubs-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-hubs-heading"><?= t('logistics.hub.my_hubs_title') ?></h3>
            <span><?= t('logistics.hub.my_hubs_desc') ?></span>
        </div>

        <?php if (!empty($hubAlerts)): ?>
        <div class="logistics-hub-alerts">
            <?php foreach ($hubAlerts as $ha): ?>
            <div class="logistics-alert logistics-alert--<?= $ha['severity'] === 'critical' ? 'danger' : 'warn' ?>">
                <?= htmlspecialchars($ha['message']) ?>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>

        <?php if (empty($hubCards)): ?>
            <div class="logistics-empty"><?= t('logistics.hub.no_hubs_assigned') ?></div>
        <?php else: ?>
        <div class="logistics-hub-grid">
            <?php foreach ($hubCards as $card):
                $hub       = $card['hub'];
                $hubId     = (int)$hub['id'];
                $lastStats = $card['last_stats'];
                $loadPct   = (float)($lastStats['load_pct'] ?? 0);
                $condPct   = (float)$hub['condition_pct'];
                $condClass = $condPct <= 20 ? 'c-bad' : ($condPct < 60 ? 'c-warn' : 'c-good');
                $loadClass = $loadPct > 100 ? 'c-bad' : ($loadPct > 80 ? 'c-warn' : 'c-good');
                // Combined risk level (condition + load)
                $riskLevel = 'none';
                if ($condPct <= 20 || ($condPct <= 40 && $loadPct > 80)) {
                    $riskLevel = 'critical';
                } elseif ($condPct <= 40 || ($condPct <= 60 && $loadPct > 100)) {
                    $riskLevel = 'high';
                } elseif ($condPct <= 60 || $loadPct > 80) {
                    $riskLevel = 'medium';
                }
                $myWells   = count($card['wells']);
            ?>
            <article class="logistics-hub-card hub-status-<?= htmlspecialchars($hub['status']) ?>"
                     data-hub-id="<?= $hubId ?>"
                     data-hub-region-id="<?= (int)$hub['region_id'] ?>"
                     data-hub-zone-key="<?= htmlspecialchars((string)($hub['zone_key'] ?? ''), ENT_QUOTES) ?>"
                     data-hub-name="<?= htmlspecialchars((string)$hub['name'], ENT_QUOTES) ?>">

                <div class="logistics-hub-card-hdr">
                    <span class="logistics-hub-name"><?= htmlspecialchars($hub['name']) ?></span>
                    <span class="badge <?= $card['status_class'] ?>">
                        <?= t('logistics.hub.status_' . $hub['status']) ?>
                    </span>
                    <?php if ($riskLevel !== 'none'): ?>
                    <span class="badge hub-risk-badge hub-risk-badge--<?= $riskLevel ?>">
                        <?= t('logistics.hub.risk_' . $riskLevel) ?>
                    </span>
                    <?php endif ?>
                </div>

                <div class="logistics-hub-meta">
                    <span><?= t('logistics.hub.type_' . $hub['hub_type']) ?></span>
                    <span class="sep">&middot;</span>
                    <?php $acqKey = $hub['acquisition_type'] ?? 'new'; ?>
                    <span class="acq-badge acq-badge--<?= htmlspecialchars($acqKey) ?>"><?= t('logistics.hub.acquisition_' . $acqKey) ?></span>
                    <span class="sep">&middot;</span>
                    <span><?= htmlspecialchars($hub['region_name'] ?? 'Region #' . $hub['region_id']) ?></span>
                    <?php if (($hub['zone_key'] ?? '') !== ''): ?>
                    <span class="sep">&middot;</span>
                    <span><?= htmlspecialchars($hub['zone_key']) ?></span>
                    <?php endif ?>
                    <span class="sep">&middot;</span>
                    <span><?= t('logistics.hub.mode_' . ($hub['work_mode'] ?? 'standard')) ?></span>
                </div>

                <div class="logistics-hub-stats">
                    <div class="logistics-hub-stat">
                        <span><?= t('logistics.hub.label_condition') ?></span>
                        <strong class="<?= $condClass ?>"><?= number_format($condPct, 1, ',', ' ') ?>%</strong>
                    </div>
                    <div class="logistics-hub-stat">
                        <span><?= t('logistics.hub.label_load') ?></span>
                        <strong class="<?= $loadClass ?>"><?= number_format($loadPct, 1, ',', ' ') ?>%</strong>
                    </div>
                    <div class="logistics-hub-stat">
                        <span><?= t('logistics.hub.label_slots') ?></span>
                        <strong><?= (int)$hub['assigned_count'] ?>/<?= (int)$hub['slot_limit'] ?></strong>
                    </div>
                    <div class="logistics-hub-stat">
                        <span><?= t('logistics.hub.label_my_wells') ?></span>
                        <strong><?= $myWells ?></strong>
                    </div>
                    <?php
                    $bufferCap     = (float)($hub['buffer_capacity_bbl'] ?? 0);
                    $bufferCurrent = (float)($hub['buffer_current_bbl']  ?? 0);
                    $bufferPct     = $bufferCap > 0 ? min(100, round($bufferCurrent / $bufferCap * 100, 1)) : 0;
                    $bufferClass   = $bufferPct >= 90 ? 'c-bad' : ($bufferPct >= 60 ? 'c-warn' : 'c-good');
                    ?>
                    <?php if ($bufferCap > 0): ?>
                    <div class="logistics-hub-stat logistics-hub-stat--buffer">
                        <span><?= t('logistics.hub.label_buffer') ?></span>
                        <div class="hub-buffer-bar">
                            <div class="hub-buffer-bar__fill hub-buffer-bar__fill--<?= $bufferPct >= 90 ? 'full' : ($bufferPct >= 60 ? 'mid' : 'low') ?>"
                                 style="width:<?= $bufferPct ?>%"></div>
                        </div>
                        <strong class="<?= $bufferClass ?>">
                            <?= number_format($bufferCurrent, 1, ',', ' ') ?>&nbsp;/&nbsp;<?= number_format($bufferCap, 0, ',', ' ') ?>&nbsp;<?= t('common.bbl') ?>
                        </strong>
                    </div>
                    <?php endif ?>
                    <div class="logistics-hub-stat">
                        <span><?= t('logistics.hub.label_acquisition') ?></span>
                        <?php $acqStat = $hub['acquisition_type'] ?? 'new'; ?>
                        <strong><span class="acq-badge acq-badge--<?= htmlspecialchars($acqStat) ?>"><?= t('logistics.hub.acquisition_' . $acqStat) ?></span></strong>
                    </div>
                    <div class="logistics-hub-stat">
                        <span><?= t('logistics.hub.label_lease_fee') ?></span>
                        <strong><?= number_format((float)($hub['lease_fee_per_tick'] ?? 0), 2, ',', ' ') ?> PLN</strong>
                    </div>
                </div>

                <div class="logistics-hub-actions">
                    <?php if (!empty($hubUnassigned) && (int)$hub['assigned_count'] < (int)$hub['slot_limit']): ?>
                    <button class="btn btn-sm btn-primary" onclick="hubAssignWellToHubModal(<?= $hubId ?>)">
                        <?= t('logistics.hub.btn_assign_well') ?>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-secondary" onclick="hubWellsModal(<?= $hubId ?>)">
                         <?= t('logistics.hub.btn_my_wells') ?> (<?= $myWells ?>)
                    </button>
                </div>
            </article>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>

    <!--  -->
    <!-- SEKCJA: Odwierty bez huba                                          -->
    <!--  -->
    <?php if (!empty($hubUnassigned)): ?>
    <section class="logistics-panel logistics-panel--warn" aria-labelledby="logistics-unassigned-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-unassigned-heading"><?= t('logistics.hub.unassigned_title') ?></h3>
            <span><?= t('logistics.hub.unassigned_desc') ?></span>
        </div>
        <div class="logistics-table">
            <div class="logistics-table-head">
                <span><?= t('logistics.col_well') ?></span>
                <span><?= t('logistics.hub.label_region') ?></span>
                <span><?= t('logistics.col_capacity') ?></span>
                <span></span>
            </div>
            <?php foreach ($hubUnassigned as $uw):
                $uwCooldownUntil  = $uw['cooldown_until'] ?? null;
                $uwCooldownActive = $uwCooldownUntil && strtotime($uwCooldownUntil) > time();
                $uwCooldownSecs   = $uwCooldownActive ? max(0, strtotime($uwCooldownUntil) - time()) : 0;
                $uwCooldownH      = intdiv($uwCooldownSecs, 3600);
                $uwCooldownM      = intdiv($uwCooldownSecs % 3600, 60);
                $uwCooldownLabel  = $uwCooldownH > 0 ? "{$uwCooldownH}h {$uwCooldownM}min" : "{$uwCooldownM}min";
            ?>
            <div class="logistics-table-row">
                <span>#<?= (int)$uw['id'] ?> <?= htmlspecialchars($uw['name'] ?? $uw['location_name'] ?? '') ?></span>
                <span><?= htmlspecialchars($uw['region_name'] ?? 'Region #' . $uw['region_id']) ?>
                    <?= ($uw['zone_key'] ?? '') !== '' ? '/ ' . htmlspecialchars($uw['zone_key']) : '' ?>
                </span>
                <span><?= number_format((float)$uw['base_production_per_hour'], 1, ',', ' ') ?> bph</span>
                <span>
                    <?php if ($uwCooldownActive): ?>
                    <span class="hub-cooldown-badge"
                          title="<?= t('logistics.hub.cooldown_title') ?>"
                          data-cooldown-until="<?= htmlspecialchars($uwCooldownUntil) ?>">
                        ⏳ <?= $uwCooldownLabel ?>
                    </span>
                    <?php else: ?>
                    <button class="btn btn-xs btn-primary" onclick="hubAssignModal(<?= (int)$uw['id'] ?>)">
                         <?= t('logistics.hub.unassigned_assign') ?>
                    </button>
                    <?php endif ?>
                </span>
            </div>
            <?php endforeach ?>
        </div>
        <?php if ($unassignedTotalPages > 1): ?>
        <div class="logistics-pagination">
            <div class="logistics-pagination-info">
                <?= $unassignedPage ?> / <?= $unassignedTotalPages ?> (<?= $unassignedTotal ?>)
            </div>
            <div class="logistics-pagination-buttons">
                <?php if ($unassignedPage > 1): ?>
                <a href="?tab=logistics&unassigned_page=<?= $unassignedPage - 1 ?>" class="btn btn-xs btn-secondary">
                     <?= t('logistics.pagination_prev') ?>
                </a>
                <?php endif ?>
                <?php if ($unassignedPage < $unassignedTotalPages): ?>
                <a href="?tab=logistics&unassigned_page=<?= $unassignedPage + 1 ?>" class="btn btn-xs btn-secondary">
                    <?= t('logistics.pagination_next') ?> 
                </a>
                <?php endif ?>
            </div>
        </div>
        <?php endif ?>
    </section>
    <?php endif ?>

    <!--  -->
    <!-- SEKCJA: Incydenty logistyczne hubow                                -->
    <!--  -->
    <?php if (!empty($hubIncidents)): ?>
    <section class="logistics-panel" aria-labelledby="logistics-hub-incidents-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-hub-incidents-heading"><?= t('logistics.hub.incidents_title') ?></h3>
            <span><?= t('logistics.hub.incidents_desc') ?></span>
        </div>
        <div class="logistics-hub-incidents-list">
        <?php foreach ($hubIncidents as $hi):
            $sev     = $hi['severity'] ?? 'low';
            $sevIcon = match($sev) {
                'critical' => '',
                'high'     => '',
                'medium'   => '',
                default    => '',
            };
            $evType  = $hi['event_type'] ?? '';
            $typeKey = str_replace('hub_incident_', '', $evType);
        ?>
        <div class="logistics-hub-incident-row logistics-hub-incident--<?= htmlspecialchars($sev) ?>">
            <div class="logistics-hub-incident-icon"><?= $sevIcon ?></div>
            <div class="logistics-hub-incident-body">
                <div class="logistics-hub-incident-title">
                    <strong><?= htmlspecialchars($hi['title'] ?? t('logistics.hub.incident.title.' . $typeKey)) ?></strong>
                    <span class="logistics-hub-incident-hub">
                        &middot; <?= htmlspecialchars($hi['hub_name'] ?? 'Hub #' . $hi['hub_id']) ?>
                    </span>
                </div>
                <div class="logistics-hub-incident-msg"><?= htmlspecialchars($hi['message']) ?></div>
                <div class="logistics-hub-incident-meta">
                    <span class="c-muted2"><?= date('d.m H:i', strtotime($hi['created_at'])) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach ?>
        </div>
    </section>
    <?php endif ?>

</div>

<script>
/* Pipeline player actions: repair / maintenance / toggle */
/* Akcje gracza na rurociagach — modal potwierdzenia zamiast confirm() */
(function() {
    var PIPELINE_API  = '/src/PipelineApi.php';
    var PIPELINE_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /* State for pending action / Stan oczekujacej akcji */
    var _pendingId     = 0;
    var _pendingAction = '';
    var _pendingBtn    = null;

    /* Action title map / Mapa tytulow dla akcji */
    var ACTION_TITLES = {
        repair_pipeline:      '<?= t('logistics.pipeline.btn_repair') ?>',
        maintenance_pipeline: '<?= t('logistics.pipeline.btn_maintenance') ?>',
        toggle_pipeline:      '<?= t('logistics.pipeline.btn_suspend') ?> / <?= t('logistics.pipeline.btn_resume') ?>',
    };

    /* Toggle confirm: reads state from data-* attrs, no page reload on success */
    /* Potwierdzenie toggle: odczytuje stan z data-*, po sukcesie aktualizuje przycisk */
    window.pipelineToggleConfirm = function(btn) {
        var pipelineId  = parseInt(btn.dataset.pipelineToggle, 10);
        var isSuspended = btn.dataset.suspended === '1';
        var confirmMsg  = isSuspended ? btn.dataset.confirmResume : btn.dataset.confirmSuspend;

        _pendingId     = pipelineId;
        _pendingAction = 'toggle_pipeline';
        _pendingBtn    = btn;

        var modal = document.getElementById('pipeline-action-modal');
        modal.querySelector('.pipeline-action-modal-title').textContent =
            isSuspended ? btn.dataset.labelResume : btn.dataset.labelSuspend;
        modal.querySelector('.pipeline-action-modal-msg').textContent = confirmMsg;

        var confirmBtn = modal.querySelector('.pipeline-action-modal-confirm');
        confirmBtn.disabled     = false;
        confirmBtn.style.opacity = '';

        modal.style.display = '';
        confirmBtn.focus();
    };

    /* Open confirm modal / Otworz modal potwierdzenia */
    window.pipelineActionConfirm = function(pipelineId, action, confirmMsg) {
        _pendingId     = pipelineId;
        _pendingAction = action;
        _pendingBtn    = document.activeElement;

        var modal = document.getElementById('pipeline-action-modal');
        modal.querySelector('.pipeline-action-modal-title').textContent =
            ACTION_TITLES[action] || '<?= t('modal.confirm') ?>';
        modal.querySelector('.pipeline-action-modal-msg').textContent = confirmMsg;

        /* Reset confirm btn state / Resetuj stan przycisku */
        var confirmBtn = modal.querySelector('.pipeline-action-modal-confirm');
        confirmBtn.disabled    = false;
        confirmBtn.style.opacity = '';

        modal.style.display = '';
        confirmBtn.focus();
    };

    /* Close confirm modal / Zamknij modal */
    window.closePipelineActionModal = function() {
        document.getElementById('pipeline-action-modal').style.display = 'none';
        _pendingId     = 0;
        _pendingAction = '';
        _pendingBtn    = null;
    };

    /* Execute action after confirmation / Wykonaj akcje po potwierdzeniu */
    window.executePipelineAction = function() {
        if (!_pendingId || !_pendingAction) return;

        var confirmBtn = document.querySelector('#pipeline-action-modal .pipeline-action-modal-confirm');
        if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.style.opacity = '0.5'; }

        var body = new URLSearchParams({
            action:      _pendingAction,
            pipeline_id: _pendingId,
            _token:      PIPELINE_CSRF,
        });

        var pendingAction = _pendingAction;
        var pendingBtn    = _pendingBtn;

        fetch(PIPELINE_API, { method: 'POST', body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                closePipelineActionModal();
                if (!data.success) {
                    alert('<?= t('logistics.pipeline.action_error') ?>: ' + (data.error || '?'));
                    return;
                }
                if (pendingAction === 'toggle_pipeline' && pendingBtn) {
                    /* Update button in-place without reload */
                    var nowSuspended = (data.new_status === 'suspended');
                    pendingBtn.dataset.suspended = nowSuspended ? '1' : '0';
                    pendingBtn.textContent = nowSuspended
                        ? pendingBtn.dataset.labelResume
                        : pendingBtn.dataset.labelSuspend;
                    pendingBtn.className = 'btn btn-xs ' + (nowSuspended ? 'btn-secondary' : 'btn-danger');
                } else {
                    location.reload();
                }
            })
            .catch(function() {
                closePipelineActionModal();
                alert('<?= t('logistics.pipeline.action_error') ?>');
            });
    };
})();
</script>

<!--  -->
<!-- MODAL: Moje odwierty na hubie                                          -->
<!--  -->
<div id="hub-wells-modal" class="logistics-modal-overlay" style="display:none"
     onclick="if(event.target===this)closeHubModal('hub-wells-modal')">
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span id="hub-wells-modal-title"> <?= t('logistics.hub.wells_modal_title') ?></span>
            <button class="logistics-modal-close" onclick="closeHubModal('hub-wells-modal')"></button>
        </div>
        <div id="hub-wells-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" onclick="closeHubModal('hub-wells-modal')">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
    </div>
</div>

<!--  -->
<!-- MODAL: Przenies odwiert do innego huba                                 -->
<!--  -->
<div id="hub-transfer-modal" class="logistics-modal-overlay" style="display:none"
     onclick="if(event.target===this)closeHubModal('hub-transfer-modal')">
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span id="hub-transfer-modal-title"> <?= t('logistics.hub.transfer_modal_title') ?></span>
            <button class="logistics-modal-close" onclick="closeHubModal('hub-transfer-modal')"></button>
        </div>
        <div id="hub-transfer-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" onclick="closeHubModal('hub-transfer-modal')">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
    </div>
</div>

<!--  -->
<!-- MODAL: Uniwersalny dialog (komunikaty + potwierdzenia)                  -->
<!--  -->

<!-- MODAL: Przypisz odwiert do huba                                        -->
<!--  -->
<div id="hub-assign-modal" class="logistics-modal-overlay" style="display:none"
     onclick="if(event.target===this)closeHubModal('hub-assign-modal')">
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span> <?= t('logistics.hub.avail_title') ?></span>
            <button class="logistics-modal-close" onclick="closeHubModal('hub-assign-modal')"></button>
        </div>
        <div id="hub-assign-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" onclick="closeHubModal('hub-assign-modal')">
                <?= t('logistics.cancel') ?>
            </button>
        </div>
    </div>
</div>

<script>
window.HUB_API   = '/src/HubApi.php';
window.HUB_CSRF  = document.querySelector('meta[name="csrf-token"]')?.content || '';
window.HUB_LANG  = <?= json_encode([
    // Stan i ladowanie / State and loading
    'loading'        => t('logistics.loading'),
    'err_generic'    => t('logistics.hub.err_generic'),
    // Dialogi  tytuly i przyciski / Dialog titles and buttons
    'title_info'     => t('logistics.hub.title_info'),
    'title_error'    => t('logistics.hub.title_error'),
    'title_warning'  => t('logistics.hub.title_warning'),
    'confirm_title'  => t('logistics.hub.confirm_title'),
    'confirm_label'  => t('logistics.hub.confirm_label'),
    // Akcje sukcesu / Success messages
    'ok_build'       => t('logistics.hub.ok_build'),
    'ok_repair'      => t('logistics.hub.ok_repair'),
    'ok_upgrade'     => t('logistics.hub.ok_upgrade',     ['level' => '{level}']),
    'ok_mode'        => t('logistics.hub.ok_mode',        ['mode'  => '{mode}']),
    'ok_pause'       => t('logistics.hub.ok_pause'),
    'ok_resume'      => t('logistics.hub.ok_resume'),
    'ok_assign'      => t('logistics.hub.ok_assign'),
    'ok_detach'      => t('logistics.hub.ok_detach'),
    'ok_transfer'    => t('logistics.hub.ok_transfer'),
    // Potwierdzenia operacji / Confirm dialogs
    'repair_confirm'  => t('logistics.hub.repair_confirm',  ['cost' => '{cost}']),
    'upgrade_confirm' => t('logistics.hub.upgrade_confirm', ['cost' => '{cost}']),
    'detach_confirm'  => t('logistics.hub.wells_detach_confirm', ['id' => '{id}']),
    // Modal odwiertow huba / Hub wells modal
    'wells_none'     => t('logistics.hub.wells_none'),
    'col_well'       => t('logistics.hub.wells_col_well'),
    'col_region'     => t('logistics.hub.wells_col_region'),
    'col_prod'       => t('logistics.hub.wells_col_prod'),
    'col_status'     => t('logistics.hub.wells_col_status'),
    'col_actions'    => t('logistics.hub.wells_col_actions'),
    'btn_detach'     => t('logistics.hub.wells_btn_detach'),
    'btn_transfer'   => t('logistics.hub.wells_btn_transfer'),
    // Modal transferu / Transfer modal
    'transfer_none'  => t('logistics.hub.transfer_none'),
    // Modal przypisania odwiertu / Well assign modal
    'assign_well_title' => t('logistics.hub.assign_well_title'),
    'assign_well_none'  => t('logistics.hub.assign_well_none'),
    'btn_assign_well'   => t('logistics.hub.btn_assign_well'),
    'btn_assign'        => t('logistics.hub.avail_btn_assign'),
    // Dostepne huby / Assignable hubs
    'avail_title'    => t('logistics.hub.avail_title'),
    'avail_none'     => t('logistics.hub.avail_none'),
    'avail_slots'    => t('logistics.hub.avail_slots',    ['free' => '{free}', 'total' => '{total}']),
    'avail_zone_pen' => t('logistics.hub.avail_zone_penalty', ['pct' => '{pct}']),
    'avail_fee'      => t('logistics.hub.avail_fee',      ['fee' => '{fee}']),
    // Kondycja / Condition
    'cond_critical_short'     => t('logistics.hub.cond_critical_short'),
    'cond_low_short'          => t('logistics.hub.cond_low_short'),
    'warn_condition_critical' => t('logistics.hub.warn_condition_critical'),
    'warn_condition_low'      => t('logistics.hub.warn_condition_low'),
    // Typy hubow / Hub types
    'type_small'     => t('logistics.hub.type_small'),
    'type_medium'    => t('logistics.hub.type_medium'),
    'type_large'     => t('logistics.hub.type_large'),
    // Tryby pracy / Operation modes
    'mode_eco'       => t('logistics.hub.mode_eco'),
    'mode_standard'  => t('logistics.hub.mode_standard'),
    'mode_max'       => t('logistics.hub.mode_max'),
    // Paginacja / Pagination
    'pagination_prev'=> t('logistics.hub.pagination_prev'),
    'pagination_next'=> t('logistics.hub.pagination_next'),
    // Model pozyskania / Acquisition type labels
    'acq_new'        => t('logistics.hub.acquisition_new'),
    'acq_used'       => t('logistics.hub.acquisition_used'),
    'acq_rental'     => t('logistics.hub.acquisition_rental'),
    'acq_title'      => t('logistics.hub.acq_title'),
    'acq_wear'       => t('logistics.hub.acq_wear'),
    'acq_risk'       => t('logistics.hub.acq_risk'),
    'acq_opex'       => t('logistics.hub.acq_opex'),
    'acq_start_cond' => t('logistics.hub.acq_start_cond'),
    'acq_lease'      => t('logistics.hub.acq_lease'),
    // Wynajem potwierdzenia i komunikaty / Rental confirmations and messages
    'confirm_rental'          => t('logistics.hub.confirm_rental'),
    'confirm_rental_transfer' => t('logistics.hub.confirm_rental_transfer'),
    'ok_assign_with_lease'    => t('logistics.hub.ok_assign_with_lease'),
    'ok_assign_with_fee'      => t('logistics.hub.ok_assign_with_fee',   ['fee' => '{fee}']),
    'ok_transfer_with_lease'  => t('logistics.hub.ok_transfer_with_lease'),
    // Potwierdzenie kosztow przypisania / Assignment cost breakdown confirmation
    'confirm_assign_costs'    => t('logistics.hub.confirm_assign_costs'),
    'confirm_access_fee'      => t('logistics.hub.confirm_access_fee'),
    'confirm_usage_fee'       => t('logistics.hub.confirm_usage_fee'),
    'confirm_lease_fee'       => t('logistics.hub.confirm_lease_fee'),
    'confirm_per_tick'        => t('logistics.hub.confirm_per_tick'),
    'confirm_question'        => t('logistics.hub.confirm_question'),
    'err_insufficient_funds'  => t('logistics.hub.err_insufficient_funds'),
    // Well status labels for hub wells modal / Tlumaczenia statusow odwiertow w modalu huba
    'ws_active'          => t('technical.ws_active'),
    'ws_paused_staff'    => t('technical.ws_paused_staff'),
    'ws_paused_cash'     => t('technical.ws_paused_cash'),
    'ws_paused_storage'  => t('technical.ws_paused_storage'),
    'ws_contaminated'    => t('technical.ws_contaminated'),
    'ws_blowout'         => t('technical.ws_blowout'),
    'ws_seized'          => t('technical.ws_seized'),
    'ws_broken'          => t('technical.ws_broken'),
    'ws_no_operator'     => t('technical.ws_no_operator'),
    'ws_no_technician'   => t('technical.ws_no_technician'),
    'ws_sold'            => t('technical.ws_sold'),
    'ws_layer_switch'    => t('technical.ws_layer_switch'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<div id="logistics-modal" class="logistics-modal-overlay" style="display:none" onclick="if(event.target===this)closeLogisticsModal()">
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span> <?= t('logistics.modal_title') ?></span>
            <button class="logistics-modal-close" onclick="closeLogisticsModal()" title="<?= t('common.close') ?>"></button>
        </div>

        <div id="logistics-current" class="logistics-section">
            <div class="logistics-section-title"><?= t('logistics.current_title') ?></div>
            <div id="logistics-current-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        </div>

        <div class="logistics-section">
            <div class="logistics-section-title"><?= t('logistics.mode_title') ?></div>
            <div class="logistics-modes">
                <label class="logistics-mode-card selected">
                    <input type="radio" name="logistics-mode" value="balans" checked>
                    <div class="logistics-mode-icon"></div>
                    <div class="logistics-mode-name"><?= t('logistics.mode_balans') ?></div>
                    <div class="logistics-mode-desc"><?= t('logistics.mode_balans_desc') ?></div>
                </label>
                <label class="logistics-mode-card">
                    <input type="radio" name="logistics-mode" value="max_prod">
                    <div class="logistics-mode-icon"></div>
                    <div class="logistics-mode-name"><?= t('logistics.mode_maxprod') ?></div>
                    <div class="logistics-mode-desc"><?= t('logistics.mode_maxprod_desc') ?></div>
                </label>
                <label class="logistics-mode-card">
                    <input type="radio" name="logistics-mode" value="min_cost">
                    <div class="logistics-mode-icon"></div>
                    <div class="logistics-mode-name"><?= t('logistics.mode_mincost') ?></div>
                    <div class="logistics-mode-desc"><?= t('logistics.mode_mincost_desc') ?></div>
                </label>
            </div>
        </div>

        <div id="logistics-results" class="logistics-section" style="display:none">
            <div class="logistics-section-title"><?= t('logistics.results_title') ?></div>
            <div id="logistics-results-body"></div>
        </div>

        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" onclick="closeLogisticsModal()"><?= t('logistics.cancel') ?></button>
            <button class="btn btn-primary btn-sm" id="btn-logistics-run" onclick="runLogisticsOptimize()">
                 <?= t('logistics.run') ?>
            </button>
        </div>
    </div>
</div>

<script>
window.LOGISTICS_API  = '/src/LogisticsApi.php';
window.LOGISTICS_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
window.LOGISTICS_LANG = <?= json_encode([
    'api_missing'       => t('logistics_js.api_missing'),
    'loading'           => t('logistics.loading'),
    'err'               => t('logistics.err'),
    'optimizing'        => t('logistics_js.optimizing'),
    'run'               => t('logistics.run'),
    'cancel'            => t('logistics.cancel'),
    'no_changes'        => t('logistics.no_changes'),
    'changed_count'     => t('logistics.changed_count'),
    'confirm_btn'       => t('logistics.confirm_btn'),
    'label_mode'        => t('logistics.label_mode'),
    'label_fee'         => t('logistics.label_fee'),
    'type_rurociag'     => t('logistics.type_rurociag'),
    'type_ciezarowki'   => t('logistics.type_ciezarowki'),
    'type_tankowiec'    => t('logistics.type_tankowiec'),
    'label_loss'        => t('logistics.label_loss'),
    'label_cost'        => t('logistics.label_cost'),
    'label_eff'         => t('logistics.label_eff'),
    'label_before'      => t('logistics.label_before'),
    'label_after'       => t('logistics.label_after'),
    'well_type_onshore' => t('logistics_js.well_type_onshore'),
    'well_type_offshore'=> t('logistics_js.well_type_offshore'),
    'mode_balans'       => t('logistics.mode_balans'),
    'mode_max_prod'     => t('logistics.mode_maxprod'),
    'mode_min_cost'     => t('logistics.mode_mincost'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
/* Pipeline build countdown / Odliczanie budowy rurociagu */
(function () {
    var countdowns = document.querySelectorAll('.pipeline-countdown[data-finish]');
    if (!countdowns.length) return;

    function fmtSec(sec) {
        if (sec <= 0) return 'â€”';
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        return (h > 0 ? h + 'h ' : '') + m + 'min ' + s + 's';
    }

    countdowns.forEach(function (el) {
        var finish = new Date(el.dataset.finish.replace(' ', 'T')).getTime();
        function tick() {
            var rem = Math.floor((finish - Date.now()) / 1000);
            el.textContent = fmtSec(rem);
            if (rem > 0) {
                setTimeout(tick, 1000);
            } else {
                el.textContent = 'â€”';
                setTimeout(function () { window.location.reload(); }, 3000);
            }
        }
        tick();
    });
})();
</script>

<script>
/* Road trip countdown / Odliczanie kursu drogowego */
(function () {
    var els = document.querySelectorAll('.road-trip-countdown[data-seconds]');
    if (!els.length) return;
    function fmtSec(sec) {
        if (sec <= 0) return '0h 00m';
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        return h + 'h ' + (m < 10 ? '0' : '') + m + 'm';
    }
    els.forEach(function (el) {
        var rem = parseInt(el.dataset.seconds, 10) || 0;
        var start = Date.now();
        function tick() {
            var elapsed = Math.floor((Date.now() - start) / 1000);
            var cur = Math.max(0, rem - elapsed);
            el.textContent = fmtSec(cur);
            if (cur > 0) {
                setTimeout(tick, 30000); // aktualizacja co 30s / update every 30s
            } else {
                el.textContent = 'â€”';
                setTimeout(function () { window.location.reload(); }, 5000);
            }
        }
        tick();
    });
})();
</script>

<!-- Pipeline buy modal / Modal zakupu rurociagu -->
<div id="pipeline-buy-modal" class="logistics-modal-overlay" style="display:none"
     onclick="if(event.target===this)closePipelineBuyModal()">
    <div class="logistics-modal-box">
        <div class="logistics-modal-hdr">
            <span><?= t('logistics.pipeline.buy_modal_title') ?></span>
            <button class="logistics-modal-close" onclick="closePipelineBuyModal()"></button>
        </div>
        <div id="pipeline-buy-modal-body" class="logistics-loading"><?= t('logistics.loading') ?></div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" onclick="closePipelineBuyModal()">
                <?= t('logistics.cancel') ?>
            </button>
            <button class="btn btn-primary btn-sm" id="pipeline-buy-confirm-btn" style="display:none"
                    onclick="confirmPipelinePurchase()">
                <?= t('logistics.pipeline.buy_confirm_btn') ?>
            </button>
        </div>
    </div>
</div>

<!--  -->
<!-- MODAL: Potwierdzenie akcji na rurociagu                                 -->
<!--  -->
<div id="pipeline-action-modal" class="logistics-modal-overlay" style="display:none"
     onclick="if(event.target===this)closePipelineActionModal()">
    <div class="logistics-modal-box logistics-modal-box--sm">
        <div class="logistics-modal-hdr">
            <span class="pipeline-action-modal-title"><?= t('modal.confirm') ?></span>
            <button class="logistics-modal-close" onclick="closePipelineActionModal()"
                    title="<?= t('modal.close') ?>"></button>
        </div>
        <div class="logistics-modal-body">
            <p class="pipeline-action-modal-msg"></p>
        </div>
        <div class="logistics-modal-footer">
            <button class="btn btn-secondary btn-sm" onclick="closePipelineActionModal()">
                <?= t('modal.cancel') ?>
            </button>
            <button class="btn btn-primary btn-sm pipeline-action-modal-confirm"
                    onclick="executePipelineAction()">
                <?= t('modal.confirm') ?>
            </button>
        </div>
    </div>
</div>

<script>
window.PIPELINE_API  = '/src/PipelineApi.php';
window.PIPELINE_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
window.PIPELINE_LANG = <?= json_encode([
    'loading'         => t('logistics.loading'),
    'err'             => t('common.generic_error'),
    'label_cost'      => t('logistics.pipeline.building_label_cost'),
    'label_hours'     => t('logistics.pipeline.buy_label_hours'),
    'insufficient'    => t('pipeline.err_insufficient_funds'),
    'already_exists'  => t('pipeline.err_already_exists'),
    'ok_started'      => t('pipeline.ok_build_started'),
    'buy_confirm_btn' => t('logistics.pipeline.buy_confirm_btn'),
    'confirm_header'  => t('logistics.pipeline.confirm_header'),
    'confirm_btn'     => t('logistics.pipeline.confirm_btn'),
    'back_btn'        => t('logistics.pipeline.back_btn'),
], JSON_UNESCAPED_UNICODE) ?>;

var _pipelineBuyWellId      = 0;
var _pipelineBuyType        = 'standard';
var _pipelineBuyProfiles    = null;
var _pipelineBuyConfirming  = false;

function openPipelineBuyModal(wellId) {
    _pipelineBuyWellId     = wellId;
    _pipelineBuyType       = 'standard';
    _pipelineBuyConfirming = false;
    var confirmBtn = document.getElementById('pipeline-buy-confirm-btn');
    confirmBtn.style.display = 'none';
    confirmBtn.textContent   = PIPELINE_LANG.buy_confirm_btn;
    document.getElementById('pipeline-buy-modal').style.display = '';
    var body = document.getElementById('pipeline-buy-modal-body');
    body.className = 'logistics-loading';
    body.innerHTML = PIPELINE_LANG.loading;

    if (_pipelineBuyProfiles) {
        renderPipelineBuyProfiles();
        return;
    }
    fetch(PIPELINE_API + '?action=pipeline_profiles')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            _pipelineBuyProfiles = data.profiles || {};
            renderPipelineBuyProfiles();
        })
        .catch(function () {
            body.className = '';
            body.innerHTML = '<p class="c-bad">' + PIPELINE_LANG.err + '</p>';
        });
}

function renderPipelineBuyProfiles() {
    _pipelineBuyConfirming = false;
    var btn = document.getElementById('pipeline-buy-confirm-btn');
    btn.textContent = PIPELINE_LANG.buy_confirm_btn;
    btn.disabled    = false;
    btn.style.display = '';
    var body = document.getElementById('pipeline-buy-modal-body');
    body.className = '';
    var profiles = _pipelineBuyProfiles;
    var html = '<div class="logistics-pipeline-buy-types">';
    ['light','standard','heavy'].forEach(function (k) {
        var p = profiles[k];
        if (!p) return;
        var sel = k === _pipelineBuyType ? ' selected' : '';
        html += '<label class="logistics-mode-card' + sel + '" onclick="selectPipelineType(\'' + k + '\')">'
              + '<input type="radio" name="pipeline-type" value="' + k + '"' + (k === _pipelineBuyType ? ' checked' : '') + '>'
              + '<div class="logistics-mode-name">' + p.label + '</div>'
              + '<div class="logistics-mode-desc">'
              + PIPELINE_LANG.label_cost + ': <strong>' + p.build_cost.toLocaleString('pl-PL', {minimumFractionDigits:2}) + ' PLN</strong><br>'
              + PIPELINE_LANG.label_hours + ': <strong>' + p.build_hours + 'h</strong>'
              + '</div></label>';
    });
    html += '</div>';
    body.innerHTML = html;
    document.getElementById('pipeline-buy-confirm-btn').style.display = '';
}

function selectPipelineType(type) {
    _pipelineBuyType = type;
    document.querySelectorAll('#pipeline-buy-modal-body .logistics-mode-card').forEach(function (el) {
        el.classList.toggle('selected', el.querySelector('input').value === type);
        el.querySelector('input').checked = el.querySelector('input').value === type;
    });
}

function closePipelineBuyModal() {
    document.getElementById('pipeline-buy-modal').style.display = 'none';
}

function confirmPipelinePurchase() {
    var btn  = document.getElementById('pipeline-buy-confirm-btn');
    var body = document.getElementById('pipeline-buy-modal-body');

    // Step 1: show cost summary before committing
    if (!_pipelineBuyConfirming) {
        var p = _pipelineBuyProfiles ? _pipelineBuyProfiles[_pipelineBuyType] : null;
        if (!p) return;
        _pipelineBuyConfirming = true;
        btn.textContent = PIPELINE_LANG.confirm_btn;
        body.innerHTML =
            '<div class="logistics-pipeline-confirm">'
          + '<p class="logistics-confirm-title">' + PIPELINE_LANG.confirm_header
          + ' <strong>' + p.label + '</strong></p>'
          + '<dl class="logistics-confirm-dl">'
          + '<dt>' + PIPELINE_LANG.label_cost  + '</dt>'
          + '<dd><strong>' + p.build_cost.toLocaleString('pl-PL', {minimumFractionDigits:2}) + ' PLN</strong></dd>'
          + '<dt>' + PIPELINE_LANG.label_hours + '</dt>'
          + '<dd><strong>' + p.build_hours + 'h</strong></dd>'
          + '</dl>'
          + '<p class="logistics-confirm-back">'
          + '<a href="#" onclick="renderPipelineBuyProfiles();return false;">'
          + PIPELINE_LANG.back_btn + '</a></p>'
          + '</div>';
        return;
    }

    // Step 2: execute purchase
    btn.disabled = true;

    var fd = new FormData();
    fd.append('_token',        PIPELINE_CSRF);
    fd.append('action',        'buy_pipeline');
    fd.append('well_id',       _pipelineBuyWellId);
    fd.append('pipeline_type', _pipelineBuyType);

    fetch(PIPELINE_API, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            if (data.success) {
                _pipelineBuyConfirming = false;
                body.innerHTML = '<p class="c-good">' + PIPELINE_LANG.ok_started + '</p>';
                btn.style.display = 'none';
                setTimeout(function () { window.location.reload(); }, 4500);
            } else {
                // On error go back to step 1 so user can retry
                _pipelineBuyConfirming = false;
                btn.textContent = PIPELINE_LANG.buy_confirm_btn;
                body.innerHTML += '<p class="c-bad" style="margin-top:10px">' + (data.error || PIPELINE_LANG.err) + '</p>';
            }
        })
        .catch(function () {
            btn.disabled = false;
            _pipelineBuyConfirming = false;
            btn.textContent = PIPELINE_LANG.buy_confirm_btn;
            body.innerHTML += '<p class="c-bad">' + PIPELINE_LANG.err + '</p>';
        });
}
</script>

