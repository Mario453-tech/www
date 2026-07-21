    <!--  -->
    <!-- SEKCJA: Twoje przypisane huby                                      -->
    <!--  -->
    <section class="logistics-panel" aria-labelledby="logistics-hubs-heading">
        <div class="logistics-panel-head">
            <div>
                <h3 id="logistics-hubs-heading"><?= t('logistics.hub.my_hubs_title') ?></h3>
                <span><?= t('logistics.hub.my_hubs_desc') ?></span>
            </div>
            <button class="btn btn-sm btn-primary" type="button" data-hub-action="buy-new">
                <?= t('logistics.hub.market_btn_buy_new') ?>
            </button>
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
                $hubLevel  = (int)($hub['level'] ?? 1);
                $hubMaxLevel = (int)($card['max_level'] ?? 3);
                $hubRepairCost = (float)($card['repair_cost'] ?? 0.0);
                $hubUpgradeCost = (float)($card['upgrade_cost'] ?? 0.0);
                $hubCanUpgrade = ($card['ownership'] ?? 'owned') === 'owned'
                    && !empty($card['can_upgrade'])
                    && !in_array((string)($hub['status'] ?? ''), ['disabled', 'building'], true);
 // Laczony poziom ryzyka (stan + obciazenie) / Combined risk level (condition + load)
                $riskLevel = 'none';
                if ($condPct <= 20 || ($condPct <= 40 && $loadPct > 80)) {
                    $riskLevel = 'critical';
                } elseif ($condPct <= 40 || ($condPct <= 60 && $loadPct > 100)) {
                    $riskLevel = 'high';
                } elseif ($condPct <= 60 || $loadPct > 80) {
                    $riskLevel = 'medium';
                }
                $myWells   = count($card['wells']);
                $hubStaffing = $hubStaffingViewByHub[$hubId] ?? null;
                $staffSummary = is_array($hubStaffing['summary'] ?? null) ? $hubStaffing['summary'] : [];
                $staffAssignments = is_array($hubStaffing['active_assignments'] ?? null) ? $hubStaffing['active_assignments'] : [];
                $coveragePct = (float)($staffSummary['coverage_pct'] ?? 0.0);
                $avgSkillPct = max(0.0, min(100.0, ((float)($staffSummary['average_skill'] ?? 0.0) / 10.0) * 100.0));
                $avgMoralePct = max(0.0, min(100.0, (float)($staffSummary['average_morale'] ?? 0.0)));
                $throughputDeltaPct = (float)($staffSummary['runtime_effects']['hub_throughput_pct'] ?? 0.0);
                $incidentMult = (float)($staffSummary['runtime_incident_mods']['incident_mult'] ?? 1.0);
                $maintenanceMult = (float)($staffSummary['maintenance_cost_mult'] ?? 1.0);
                $missingRoles = is_array($staffSummary['missing_roles'] ?? null) ? $staffSummary['missing_roles'] : [];
                $coverageClass = $coveragePct >= 100.0 ? 'c-good' : ($coveragePct >= 60.0 ? 'c-warn' : 'c-bad');
            ?>
            <article class="logistics-hub-card hub-status-<?= htmlspecialchars($hub['status']) ?>"
                     data-hub-id="<?= $hubId ?>"
                     data-hub-region-id="<?= (int)$hub['region_id'] ?>"
                     data-hub-zone-key="<?= htmlspecialchars((string)($hub['zone_key'] ?? ''), ENT_QUOTES) ?>"
                     data-hub-name="<?= htmlspecialchars((string)$hub['name'], ENT_QUOTES) ?>"
                     data-repair-cost="<?= htmlspecialchars((string)$hubRepairCost, ENT_QUOTES) ?>"
                     data-upgrade-cost="<?= htmlspecialchars((string)$hubUpgradeCost, ENT_QUOTES) ?>">

                <div class="logistics-hub-card-hdr">
                    <span class="logistics-hub-name"><?= htmlspecialchars($hub['name']) ?></span>
                    <?php $ownership = $card['ownership'] ?? 'owned'; ?>
                    <span class="badge hub-ownership-badge hub-ownership-badge--<?= htmlspecialchars($ownership) ?>">
                        <?= t('logistics.hub.ownership_' . $ownership) ?>
                    </span>
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
                    <span><?= htmlspecialchars($hub['region_name'] ?? (($locale === 'en' ? 'Region #' : 'Region #') . $hub['region_id'])) ?></span>
                    <?php if (($hub['zone_key'] ?? '') !== ''): ?>
                    <span class="sep">&middot;</span>
                    <span><?= htmlspecialchars($hub['zone_key']) ?></span>
                    <span class="sep">&middot;</span>
                    <span><?= t('logistics.hub.label_level', ['level' => $hubLevel, 'max' => $hubMaxLevel]) ?></span>
                </div>

                <?php $workMode = (string)($hub['work_mode'] ?? 'standard'); ?>
                <div class="logistics-hub-workmode-bar">
                    <span class="logistics-hub-workmode-label"><?= t('logistics.hub.label_work_mode') ?>:</span>
                    <div class="logistics-hub-workmode-buttons" role="group" aria-label="<?= t('logistics.hub.label_work_mode') ?>">
                        <button type="button"
                                class="workmode-btn workmode-btn--eco <?= $workMode === 'eco' ? 'is-active' : '' ?>"
                                onclick="window.hubSetMode(<?= $hubId ?>, 'eco')"
                                title="<?= htmlspecialchars(t('logistics.hub.desc_mode_eco')) ?>">
                            <span class="dot">🟢</span> <?= t('logistics.hub.mode_eco') ?>
                        </button>
                        <button type="button"
                                class="workmode-btn workmode-btn--standard <?= $workMode === 'standard' ? 'is-active' : '' ?>"
                                onclick="window.hubSetMode(<?= $hubId ?>, 'standard')"
                                title="<?= htmlspecialchars(t('logistics.hub.desc_mode_standard')) ?>">
                            <span class="dot">🔵</span> <?= t('logistics.hub.mode_standard') ?>
                        </button>
                        <button type="button"
                                class="workmode-btn workmode-btn--max <?= $workMode === 'max' ? 'is-active' : '' ?>"
                                onclick="window.hubSetMode(<?= $hubId ?>, 'max')"
                                title="<?= htmlspecialchars(t('logistics.hub.desc_mode_max')) ?>">
                            <span class="dot">🔴</span> <?= t('logistics.hub.mode_max') ?>
                        </button>
                    </div>
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
                            data-progress-width="<?= $bufferPct ?>"></div>
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
                    <?php if (($hub['acquisition_type'] ?? 'new') === 'rental'): ?>
                    <div class="logistics-hub-stat">
                        <span><?= t('logistics.hub.label_lease_fee') ?></span>
                        <strong><?= number_format((float)($hub['lease_fee_per_tick'] ?? 0), 2, ',', ' ') ?> <?= $currencyLabel ?></strong>
                    </div>
                    <?php endif ?>
                </div>

                <div class="logistics-hub-staffing">
                    <div class="logistics-hub-staffing-head">
                        <div>
                            <span class="logistics-hub-staffing-label"><?= t('logistics.hub.staffing.card_title') ?></span>
                            <strong class="<?= $coverageClass ?>"><?= number_format($coveragePct, 0, $numberDecimalSep, $numberThousandsSep) ?>%</strong>
                        </div>
                        <span class="badge <?= !empty($hubStaffing['runtime_enabled']) ? 'badge-ok' : 'badge-muted' ?>">
                            <?= !empty($hubStaffing['runtime_enabled'])
                                ? t('logistics.hub.staffing.runtime_on')
                                : t('logistics.hub.staffing.runtime_off') ?>
                        </span>
                    </div>
                    <div class="logistics-hub-staffing-bar" role="progressbar" aria-valuenow="<?= (int)round($coveragePct) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="logistics-hub-staffing-fill logistics-hub-staffing-fill--<?= $coveragePct >= 100.0 ? 'good' : ($coveragePct >= 60.0 ? 'warn' : 'bad') ?>" data-progress-width="<?= min(100, max(0, $coveragePct)) ?>"></div>
                    </div>
                    <div class="logistics-hub-staffing-stats">
                        <div class="logistics-hub-staffing-stat">
                            <span><?= t('logistics.hub.staffing.coverage') ?></span>
                            <strong><?= (int)($staffSummary['assigned_count'] ?? 0) ?>/<?= (int)($staffSummary['required_count'] ?? 0) ?></strong>
                        </div>
                        <div class="logistics-hub-staffing-stat">
                            <span><?= t('logistics.hub.staffing.skill') ?></span>
                            <strong><?= number_format((float)($staffSummary['average_skill'] ?? 0.0), 1, $numberDecimalSep, $numberThousandsSep) ?>/10</strong>
                        </div>
                        <div class="logistics-hub-staffing-stat">
                            <span><?= t('logistics.hub.staffing.morale') ?></span>
                            <strong><?= number_format((float)($staffSummary['average_morale'] ?? 0.0), 0, $numberDecimalSep, $numberThousandsSep) ?>%</strong>
                        </div>
                    </div>
                    <div class="logistics-hub-staffing-effects">
                        <span class="badge <?= $throughputDeltaPct >= 0 ? 'badge-ok' : 'badge-warn' ?>">
                            <?= t('logistics.hub.staffing.effect_throughput', ['value' => number_format($throughputDeltaPct, 1, $numberDecimalSep, $numberThousandsSep)]) ?>
                        </span>
                        <span class="badge <?= $incidentMult <= 1.0 ? 'badge-ok' : 'badge-warn' ?>">
                            <?= t('logistics.hub.staffing.effect_incident', ['value' => number_format(($incidentMult - 1.0) * 100.0, 1, $numberDecimalSep, $numberThousandsSep)]) ?>
                        </span>
                        <span class="badge <?= $maintenanceMult <= 1.0 ? 'badge-ok' : 'badge-warn' ?>">
                            <?= t('logistics.hub.staffing.effect_maintenance', ['value' => number_format(($maintenanceMult - 1.0) * 100.0, 1, $numberDecimalSep, $numberThousandsSep)]) ?>
                        </span>
                    </div>
                    <?php if ($missingRoles !== []): ?>
                    <div class="logistics-hub-staffing-missing">
                        <span><?= t('logistics.hub.staffing.missing_roles') ?></span>
                        <strong><?= htmlspecialchars(implode(', ', array_map(static fn(string $code): string => t('logistics.hub.staffing.role_' . $code), $missingRoles)), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <?php endif ?>
                    <?php if ($staffAssignments !== []): ?>
                    <div class="logistics-hub-staffing-team">
                        <?php foreach ($staffAssignments as $assignmentRow): ?>
                        <div class="logistics-hub-staffing-member">
                            <strong><?= htmlspecialchars((string)$assignmentRow['full_name']) ?></strong>
                            <span><?= htmlspecialchars((string)($assignmentRow['specialization_name'] ?? t('logistics.hub.staffing.no_specialization'))) ?></span>
                            <span><?= number_format((float)($assignmentRow['allocation_pct'] ?? 0.0), 0, $numberDecimalSep, $numberThousandsSep) ?>%</span>
                        </div>
                        <?php endforeach ?>
                    </div>
                    <?php else: ?>
                    <div class="logistics-hub-staffing-empty"><?= t('logistics.hub.staffing.empty_card') ?></div>
                    <?php endif ?>
                </div>

                <div class="logistics-hub-actions">
                    <?php if (!empty($hubUnassigned) && (int)$hub['assigned_count'] < (int)$hub['slot_limit']): ?>
                    <button class="btn btn-sm btn-primary" type="button" data-hub-action="assign-well" data-hub-id="<?= $hubId ?>">
                        <?= t('logistics.hub.btn_assign_well') ?>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-secondary" type="button" data-hub-action="staffing" data-hub-id="<?= $hubId ?>">
                        <?= t('logistics.hub.staffing.btn_manage') ?>
                    </button>
                    <button class="btn btn-sm btn-secondary" type="button" data-hub-action="wells" data-hub-id="<?= $hubId ?>">
                         <?= t('logistics.hub.btn_my_wells') ?> (<?= $myWells ?>)
                    </button>
                    <?php if ($hubCanUpgrade): ?>
                    <button class="btn btn-sm btn-primary" type="button" data-hub-action="upgrade" data-hub-id="<?= $hubId ?>">
                        <?= t('logistics.hub.btn_upgrade') ?>
                    </button>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>
