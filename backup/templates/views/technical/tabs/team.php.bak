<?php if (!$manager): ?>
<div class="msg-bar msg-error">&#9888; <?= t('technical.no_manager_warn') ?></div>
<?php else: ?>

<?php
    $mgrInitials = strtoupper(substr($manager['first_name'] ?? '', 0, 1) . substr($manager['last_name'] ?? '', 0, 1));
    $mgrSkill    = (int)($mBonus['skill'] ?? 0);
?>
<div class="g-card mgr-card-wrap">
    <div class="mgr-card">
        <div class="mgr-avatar"><?= $mgrInitials ?: t('technical.manager_badge') ?></div>
        <div class="mgr-info">
            <div class="mgr-superlabel"><?= t('technical.manager_card_title') ?></div>
            <div class="mgr-name"><?= htmlspecialchars($manager['first_name'].' '.$manager['last_name']) ?></div>
            <div class="mgr-role"><?= t('technical.manager_role') ?></div>
            <div class="mgr-stats">
                <div class="mgr-stat"> <?= t('technical.mgr_tenure') ?>: <span><?= (int)($manager['days_employed'] ?? 0) ?> <?= t('common.days') ?></span></div>
                <div class="mgr-stat"> <?= t('technical.mgr_exp') ?>: <span><?= $manager['experience_years'] ?> <?= t('hr.years_age') ?></span></div>
                <div class="mgr-stat"> <?= t('technical.mgr_salary') ?>: <span class="c-gold"><?= number_format($manager['salary'], 0, '.', ' ') ?> <?= t('common.currency') ?>/<?= t('hr.month_short') ?></span></div>
            </div>
        </div>
    </div>
    <?php if ($mgrSkill > 0): ?>
    <div class="mgr-perks">
        <span class="mgr-perk mgr-perk--time"> -<?= $mgrSkill * 2.5 ?>% czasu</span>
        <span class="mgr-perk mgr-perk--cost"> -<?= $mgrSkill * 1.5 ?>% koszt�w</span>
        <span class="mgr-perk mgr-perk--skill"> Org. <?= $mgrSkill ?>/10</span>
        <div class="mgr-skill-track"><div class="mgr-skill-fill" style="width:<?= min(100,$mgrSkill*10) ?>%"></div></div>
    </div>
    <?php endif ?>
</div>

<?php endif ?>

<div class="g-card">
    <div class="g-card-title"><?= t('technical.team_card_title', ['count' => count($staff)]) ?></div>

    <?php if (empty($staff)): ?>
    <div class="empty-state"><?= t('technical.no_staff') ?></div>
    <?php else:
    $sectionMap = [
        'well_operator'        => ['label' => t('technical.section_operators'),    'icon' => 'OP'],
        'production_engineer'  => ['label' => t('technical.section_engineers'),    'icon' => 'INZ'],
        'drilling_engineer'    => ['label' => t('technical.section_engineers'),    'icon' => 'INZ'],
        'well_technician'      => ['label' => t('technical.section_technicians'),  'icon' => 'TECH'],
        'maintenance_engineer' => ['label' => t('technical.section_technicians'),  'icon' => 'TECH'],
        'safety_officer'       => ['label' => t('technical.section_safety'),       'icon' => 'BHP'],
        'pipeline_engineer'    => ['label' => t('technical.section_pipeline'),     'icon' => 'RUR'],
    ];
    $sections = [];
    foreach ($staff as $s) {
        $sec = $sectionMap[$s['spec_code']]['label'] ?? t('technical.section_other');
        $sections[$sec][] = $s;
    }
    foreach ($sections as $sectionLabel => $sectionStaff):
    ?>
    <div class="staff-section-title"><?= htmlspecialchars($sectionLabel) ?> <span class="muted">(<?= count($sectionStaff) ?>)</span></div>
    <div class="staff-grid">
    <?php foreach ($sectionStaff as $s):
        $specDef   = TechnicalTeamService::getSpecDefinition($s['spec_code']) ?? ['icon'=>'?','name'=>$s['spec_name']];
        $isBusy    = $s['active_task_id'] !== null;
        $skillFill = (int)$s['skill_level'] * 10;
        $statusCls = $isBusy ? 'b-busy' : 'b-active';
        $statusLbl = $isBusy ? t('technical.status_busy') : t('technical.status_available');

        $progress = 0;
        if ($isBusy && $s['active_task_end']) {
            $taskStmt = $db->prepare("SELECT start_time, end_time FROM technical_tasks WHERE id = ? LIMIT 1");
            $taskStmt->execute([$s['active_task_id']]);
            $taskRow = $taskStmt->fetch();
            if ($taskRow) {
                $total    = strtotime($taskRow['end_time']) - strtotime($taskRow['start_time']);
                $elapsed  = time() - strtotime($taskRow['start_time']);
                $progress = $total > 0 ? min(100, round($elapsed / $total * 100)) : 100;
            }
        }
        $safeName = htmlspecialchars($s['first_name'].' '.$s['last_name'], ENT_QUOTES);
    ?>
    <div class="staff-card <?= $isBusy ? 'busy' : '' ?>">
        <div class="staff-hdr">
            <div>
                <span class="staff-spec-icon"><?= $specDef['icon'] ?></span>
                <span class="staff-name"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></span>
                <div class="staff-spec">
                    <?= htmlspecialchars($specDef['name']) ?>
                    <?php if (!empty($s['specialization']) && !empty($s['specialization_name'])): ?>
                    <span class="ts-perk-badge ts-perk--<?= htmlspecialchars($s['spec_rarity'] ?? 'uncommon') ?>"
                          title="<?= t('technical.spec_badge_title') ?>: <?= htmlspecialchars($s['specialization_name']) ?>">
                        &#9733; <?= htmlspecialchars($s['specialization_name']) ?>
                    </span>
                    <?php endif ?>
                </div>
            </div>
            <span class="badge <?= $statusCls ?>"><?= $statusLbl ?></span>
        </div>

        <div class="skill-bar-row">
            <div class="skill-bar-label">
                <span><?= t('technical.skill_label') ?></span>
                <span class="c-gold fw7"><?= $s['skill_level'] ?>/10</span>
            </div>
            <div class="skill-bar"><div class="skill-fill" style="--bar-w:<?= $skillFill ?>%"></div></div>
        </div>

        <div class="staff-meta">
            <span><?= $s['experience_years'] ?> <?= t('technical.exp_years') ?></span>
            <span class="c-gold"><?= number_format($s['salary'], 0, '.', ' ') ?> <?= t('common.currency') ?>/<?= t('hr.month_short') ?></span>
            <?php if ($s['queued_tasks'] > 0): ?>
            <span class="c-warn"><?= $s['queued_tasks'] ?> <?= t('technical.in_queue') ?></span>
            <?php endif ?>
        </div>

        <?php if ($isBusy): ?>
        <div class="task-running">
            <div class="task-running-label"><?= t('technical.active_task') ?></div>
            <div class="task-running-title"><?= htmlspecialchars($s['active_task_title'] ?? '') ?></div>
            <div class="task-running-time">
                <?= t('technical.task_end') ?>: <?= date('d.m H:i', strtotime($s['active_task_end'])) ?>
                &middot; <?= t('technical.task_remaining') ?>: <span class="countdown" data-end="<?= strtotime($s['active_task_end']) ?>"></span>
            </div>
            <div class="task-running-bar"><div class="task-running-fill" style="--bar-w:<?= $progress ?>%"></div></div>
        </div>
        <?php endif ?>

        <?php if (!$isBusy && $manager): ?>
        <div class="staff-actions">
            <details>
                <summary class="task-assign-toggle"><?= t('technical.assign_task_toggle') ?></summary>
                <div class="task-form">
                    <form method="post" onsubmit="return techTaskConfirm(this)">
                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="assign_task">
                        <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                        <div class="task-form-grid">
                            <div class="form-group form-group--flush">
                                <label class="form-label"><?= t('technical.task_type_label') ?></label>
                                <select name="task_type" class="form-input" onchange="toggleWellSelect(this, '<?= $s['id'] ?>')">
                                    <?php foreach (TechnicalTeamService::getTasksCatalog() as $code => $td):
                                        if (!in_array($s['spec_code'], $td['assignable'])) continue;
                                        $costMin = $td['cost_min'] ?? 0;
                                        $costMax = $td['cost_max'] ?? 0;
                                    ?>
                                    <option value="<?= $code ?>"
                                            data-needs-well="<?= $td['needs_well'] ? '1' : '0' ?>"
                                            data-needs-hub="<?= !empty($td['needs_hub']) ? '1' : '0' ?>"
                                            data-needs-module="<?= $code === 'install_module' ? '1' : '0' ?>"
                                            data-cost-min="<?= $costMin ?>"
                                            data-cost-max="<?= $costMax ?>">
                                        <?= $td['icon'] ?> <?= $td['label'] ?> - <?= $td['effect'] ?>
                                    </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="form-group form-group--flush" id="well-sel-<?= $s['id'] ?>">
                                <label class="form-label"><?= t('technical.well_label') ?></label>
                                <select name="well_id" class="form-input">
                                    <option value=""><?= t('technical.no_well_option') ?></option>
                                    <?php foreach ($wells as $w): ?>
                                    <?php $wSt = $w['status'] ?? 'active'; ?>
                                    <option value="<?= $w['id'] ?>">#<?= $w['id'] ?> <?= htmlspecialchars($w['location_name'] ?? t('technical.well_default_name')) ?> - <?= $statusLabels[$wSt]['icon'] ?? '' ?> <?= $statusLabels[$wSt]['lbl'] ?? $wSt ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="form-group form-group--flush form-group--hidden" id="hub-sel-<?= $s['id'] ?>" style="display:none">
                                <label class="form-label"><?= t('technical.hub_label') ?></label>
                                <select name="hub_id" class="form-input">
                                    <option value=""><?= t('technical.no_hub_option') ?></option>
                                    <?php foreach (($playerHubs ?? []) as $hub): ?>
                                    <option value="<?= (int)$hub['id'] ?>">
                                        #<?= (int)$hub['id'] ?> <?= htmlspecialchars($hub['name'] ?? ('Hub #' . (int)$hub['id'])) ?>
                                    </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="form-group form-group--flush form-group--hidden" id="mod-sel-<?= $s['id'] ?>">
                                <label class="form-label"><?= t('technical.module_label') ?></label>
                                <select name="module_type" class="form-input">
                                    <?php foreach (TechnicalTeamService::getModulesCatalog() as $mCode => $mDef): ?>
                                    <option value="<?= $mCode ?>"><?= $mDef['label'] ?> - <?= $mDef['effect'] ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><?= t('technical.btn_assign') ?></button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
        <?php endif ?>

        <?php if (!$isBusy): ?>
        <form method="post" class="fire-form" onsubmit="return confirmSubmit(this, '<?= t('technical.confirm_fire', ['name' => $safeName]) ?>')">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="fire_engineer">
            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?= t('technical.btn_fire') ?></button>
        </form>
        <?php endif ?>
    </div>
    <?php endforeach ?>
    </div>
    <?php endforeach ?>
    <?php endif ?>
</div>
