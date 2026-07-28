<?php extract($viewData, EXTR_SKIP); ?>
<?php
$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$currencyLabel = 'PLN';
$raiseRequests = is_array($raiseRequests ?? null)
    ? array_values(array_filter(
        $raiseRequests,
        static fn(array $request): bool => in_array((string)($request['status'] ?? 'open'), ['open', 'pending', 'postponed'], true)
    ))
    : [];
$raiseDecisionLimits = is_array($raiseDecisionLimits ?? null) ? $raiseDecisionLimits : [];
$employeeDashboard = is_array($employeeDashboard ?? null) ? $employeeDashboard : [];
$canonicalEmployees = is_array($employeeDashboard['employees'] ?? null) ? $employeeDashboard['employees'] : [];
$moraleSummary = is_array($employeeDashboard['morale'] ?? null) ? $employeeDashboard['morale'] : [];
$trainingRows = is_array($employeeDashboard['trainings'] ?? null) ? $employeeDashboard['trainings'] : [];
$employeeEvents = is_array($employeeDashboard['events'] ?? null) ? $employeeDashboard['events'] : [];
$activeHrTab = (string)($activeHrTab ?? 'employees');
$focusedRecord = (string)($focusedRecord ?? '');
$canonicalEmployeeMap = [];
foreach ($canonicalEmployees as $canonicalEmployee) {
    $canonicalEmployeeMap[
        (string)$canonicalEmployee['source_type'] . ':' . (int)$canonicalEmployee['source_id']
    ] = $canonicalEmployee;
}
$assignmentTargetLabels = [];
foreach (['department', 'well', 'hub', 'pipeline', 'warehouse', 'road_transport', 'port', 'b2b'] as $targetType) {
    $assignmentTargetLabels[$targetType] = t('hr.assignment_target.' . $targetType);
}
$departmentLabel = static function (string $code): string {
    return in_array($code, ['technical', 'logistics', 'hr', 'legal', 'finance'], true)
        ? t('hr.department.' . $code)
        : t('hr.department.unknown');
};
$relationLabel = static function (string $status): string {
    return in_array($status, ['normal', 'unhappy', 'raise_requested', 'dispute', 'strike_threat', 'on_strike', 'leaving', 'inactive'], true)
        ? t('hr.relation.' . $status)
        : t('hr.relation.normal');
};
$hrSeniorityLevel = static function (array $employee): string {
    $experience = max(0, (float)($employee['experience_years'] ?? 0));
    $skills = [
        (float)($employee['skill_organization'] ?? $employee['skill_level'] ?? 5),
        (float)($employee['skill_negotiation'] ?? $employee['skill_level'] ?? 5),
        (float)($employee['skill_analysis'] ?? $employee['skill_level'] ?? 5),
        (float)($employee['skill_stress'] ?? $employee['skill_level'] ?? 5),
        (float)($employee['skill_ethics'] ?? $employee['skill_level'] ?? 5),
    ];
    $skillAvg = array_sum($skills) / max(1, count($skills));
    $score = $experience + max(0.0, ($skillAvg - 5.0) * 2.0);
    if ($score >= 12.0 || ($experience >= 9.0 && $skillAvg >= 7.0)) {
        return 'senior';
    }
    if ($score >= 6.0 || ($experience >= 4.0 && $skillAvg >= 6.0)) {
        return 'mid';
    }
    return 'junior';
};
?>
<div id="tab-conflicts" class="hr-tab-content" data-canonical-panel="conflicts">
<section class="hr-strike-center" aria-labelledby="hr-strike-center-title">
    <div class="hr-section-header hr-strike-center__header">
        <h2 id="hr-strike-center-title"><?= t('hr.strikes_title') ?></h2>
        <p><?= t('hr.strikes_desc') ?></p>
    </div>
    <?php if (empty($activeStrikes)): ?>
        <div class="hr-empty hr-empty--big"><p><?= t('hr.conflicts_empty') ?></p></div>
    <?php else: ?>
    <div class="hr-strike-list">
        <?php foreach ($activeStrikes as $strike):
            $strikeId = (int)$strike['id'];
            $status = (string)$strike['status'];
            $canNegotiate = !empty($strikeNegotiationLimits['enabled']) && in_array($status, ['active', 'negotiating'], true);
            $strikeDepartmentLabel = $departmentLabel((string)$strike['department_code']);
        ?>
        <article class="hr-strike-card<?= $focusedRecord === 'strike:' . $strikeId ? ' hr-record-focus' : '' ?>"
                 data-strike-card="<?= $strikeId ?>" data-record="strike:<?= $strikeId ?>">
            <div class="hr-strike-card__header">
                <div>
                    <span class="hr-strike-card__eyebrow"><?= t('hr.strike_department') ?></span>
                    <h3><?= htmlspecialchars($strikeDepartmentLabel, ENT_QUOTES, 'UTF-8') ?></h3>
                </div>
                <span class="hr-strike-status hr-strike-status--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                    <?= t('hr.strike_status.' . $status) ?>
                </span>
            </div>
            <div class="hr-strike-stats">
                <div><span><?= t('hr.strike_participants') ?></span><strong><?= (int)$strike['member_count'] ?></strong></div>
                <div><span><?= t('hr.strike_support') ?></span><strong><?= number_format((float)$strike['support_pct'], 1, ',', ' ') ?>%</strong></div>
                <div><span><?= t('hr.strike_avg_morale') ?></span><strong><?= number_format((float)$strike['avg_morale'], 1, ',', ' ') ?>%</strong></div>
            </div>

            <?php if ($status === 'threat'): ?>
                <p class="hr-strike-card__notice"><?= t('hr.strike_threat_notice') ?></p>
            <?php elseif (!$canNegotiate): ?>
                <p class="hr-strike-card__notice"><?= t('hr.strike_negotiation_disabled') ?></p>
            <?php elseif ($status === 'active'): ?>
                <button type="button" class="btn btn-primary" data-open-strike-negotiation="<?= $strikeId ?>">
                    <?= t('hr.btn_open_strike_negotiation') ?>
                </button>
            <?php else: ?>
                <form class="hr-strike-offer-form" data-strike-offer-form="<?= $strikeId ?>">
                    <div class="hr-strike-round">
                        <strong><?= t('hr.strike_round', [
                            'round' => (int)($strike['current_round'] ?? 1),
                            'max' => (int)($strike['max_rounds'] ?? 1),
                        ]) ?></strong>
                        <?php if (!empty($strike['round_deadline_at'])): ?>
                            <span><?= t('hr.strike_deadline') ?>: <?= date('d.m.Y H:i', strtotime((string)$strike['round_deadline_at'])) ?></span>
                        <?php endif ?>
                    </div>
                    <div class="hr-strike-offer-grid">
                        <label>
                            <span><?= t('hr.strike_raise_offer') ?></span>
                            <input type="number" name="raise_pct"
                                   min="<?= (float)$strikeNegotiationLimits['raise_min'] ?>"
                                   max="<?= (float)$strikeNegotiationLimits['raise_max'] ?>"
                                   step="0.5" required>
                        </label>
                        <label>
                            <span><?= t('hr.strike_bonus_offer') ?></span>
                            <input type="number" name="bonus_per_member" min="0"
                                   max="<?= (float)$strikeNegotiationLimits['bonus_max'] ?>"
                                   step="100" value="0" required>
                        </label>
                    </div>
                    <p class="hr-strike-offer-form__hint"><?= t('hr.strike_offer_hint') ?></p>
                    <button type="submit" class="btn btn-primary"><?= t('hr.btn_submit_strike_offer') ?></button>
                </form>
            <?php endif ?>
            <p class="hr-strike-dialogue" data-strike-dialogue="<?= $strikeId ?>" hidden></p>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
</div>
<div id="tab-raises" class="hr-tab-content" data-canonical-panel="raises">
<section class="hr-raise-center" aria-labelledby="hr-raise-center-title">
    <div class="hr-section-header hr-raise-center__header">
        <h2 id="hr-raise-center-title"><?= t('hr.raises_title') ?></h2>
        <p><?= t('hr.raises_desc') ?></p>
    </div>
    <?php if (empty($raiseRequests)): ?>
        <div class="hr-empty hr-empty--big"><p><?= t('hr.raises_empty') ?></p></div>
    <?php else: ?>
    <div class="hr-raise-list">
        <?php foreach ($raiseRequests as $request):
            $requestId = (int)($request['id'] ?? 0);
            $employee = is_array($request['employee'] ?? null) ? $request['employee'] : [];
            $employeeName = trim((string)($request['employee_name']
                ?? (($request['first_name'] ?? $employee['first_name'] ?? '') . ' '
                    . ($request['last_name'] ?? $employee['last_name'] ?? ''))));
            $employeeName = $employeeName !== '' ? $employeeName : t('hr.raise_employee_unknown');
            $currentSalary = (float)($request['current_salary'] ?? $request['salary'] ?? 0);
            $requestedRaisePct = (float)($request['requested_raise_pct'] ?? 0);
            $requestedSalary = (float)($request['requested_salary']
                ?? ($currentSalary > 0 ? $currentSalary * (1 + ($requestedRaisePct / 100)) : 0));
            if ($requestedRaisePct <= 0 && $currentSalary > 0 && $requestedSalary > 0) {
                $requestedRaisePct = (($requestedSalary / $currentSalary) - 1) * 100;
            }
            $salaryStep = max(1, (float)($raiseDecisionLimits['salary_step'] ?? 100));
            $offerMin = max($currentSalary + $salaryStep, (float)($raiseDecisionLimits['min_offer_salary'] ?? 0));
            $offerMax = min(
                $requestedSalary - $salaryStep,
                (float)($raiseDecisionLimits['max_offer_salary'] ?? $requestedSalary)
            );
            $canOfferLess = $requestId > 0 && $offerMax >= $offerMin;
            $offerMidpoint = $currentSalary + (($requestedSalary - $currentSalary) / 2);
            $suggestedOffer = $canOfferLess
                ? $offerMin + floor(max(0, $offerMidpoint - $offerMin) / $salaryStep) * $salaryStep
                : 0;
            $suggestedOffer = $canOfferLess ? min($offerMax, max($offerMin, $suggestedOffer)) : 0;
            $maxPostponements = max(0, (int)($raiseDecisionLimits['max_postponements'] ?? 0));
            $canPostpone = (int)($request['postponed_count'] ?? 0) < $maxPostponements;
            $status = (string)($request['status'] ?? 'open');
            $statusKey = in_array($status, ['open', 'pending', 'postponed'], true) ? $status : 'open';
            $deadline = (string)($request['deadline_at'] ?? $request['decision_deadline_at'] ?? '');
            $deadlineTimestamp = $deadline !== '' ? strtotime($deadline) : false;
            $morale = max(0, min(100, (float)($request['morale'] ?? 0)));
            $satisfaction = max(0, min(120, (float)($request['salary_satisfaction'] ?? $request['satisfaction'] ?? 0)));
            $refusalRisk = max(0, min(100, round((100 - $morale) * 0.45 + (100 - min(100, $satisfaction)) * 0.55)));
            $departmentCode = (string)($request['department_code'] ?? 'unknown');
            $reasonCode = (string)($request['reason_code'] ?? 'other');
        ?>
        <article class="hr-raise-card<?= $focusedRecord === 'raise:' . $requestId ? ' hr-record-focus' : '' ?>"
                 data-raise-request="<?= $requestId ?>" data-record="raise:<?= $requestId ?>">
            <div class="hr-raise-card__header">
                <div>
                    <span class="hr-raise-card__eyebrow"><?= t('hr.raise_employee') ?></span>
                    <h3><?= htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') ?></h3>
                </div>
                <span class="hr-raise-status hr-raise-status--<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>">
                    <?= t('hr.raise_status.' . $statusKey) ?>
                </span>
            </div>
            <div class="hr-raise-salary">
                <div><span><?= t('hr.raise_current_salary') ?></span><strong><?= number_format($currentSalary, 0, ',', ' ') ?> <?= $currencyLabel ?></strong></div>
                <div><span><?= t('hr.raise_requested_salary') ?></span><strong><?= number_format($requestedSalary, 0, ',', ' ') ?> <?= $currencyLabel ?></strong></div>
                <div><span><?= t('hr.raise_requested_pct') ?></span><strong>+<?= number_format($requestedRaisePct, 1, ',', ' ') ?>%</strong></div>
            </div>
            <dl class="hr-raise-meta">
                <div><dt><?= t('hr.raise_morale') ?></dt><dd><?= number_format($morale, 0, ',', ' ') ?>%</dd></div>
                <div><dt><?= t('hr.raise_satisfaction') ?></dt><dd><?= number_format($satisfaction, 0, ',', ' ') ?>%</dd></div>
                <div>
                    <dt><?= t('hr.raise_deadline') ?></dt>
                    <dd><?= $deadlineTimestamp !== false ? date('d.m.Y H:i', $deadlineTimestamp) : t('hr.raise_deadline_none') ?></dd>
                </div>
                <div><dt><?= t('hr.raise_department') ?></dt><dd><?= $departmentLabel($departmentCode) ?></dd></div>
                <div><dt><?= t('hr.raise_reason') ?></dt><dd><?= t('hr.raise_reason.' . ($reasonCode === 'low_morale' ? 'low_morale' : 'other')) ?></dd></div>
                <div><dt><?= t('hr.raise_refusal_risk') ?></dt><dd><?= $refusalRisk ?>%</dd></div>
            </dl>
            <div class="hr-raise-actions">
                <button type="button" class="btn btn-primary" data-raise-action="accept"
                        data-request-id="<?= $requestId ?>" data-employee-name="<?= htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') ?>">
                    <?= t('hr.btn_accept_raise') ?>
                </button>
                <form class="hr-raise-offer-form" data-raise-offer-form="<?= $requestId ?>"
                      data-employee-name="<?= htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="raise-offer-<?= $requestId ?>"><?= t('hr.raise_smaller_offer') ?></label>
                    <div>
                        <input id="raise-offer-<?= $requestId ?>" name="offered_salary" type="number"
                               min="<?= $offerMin ?>" max="<?= $offerMax ?>" step="<?= $salaryStep ?>"
                               value="<?= $suggestedOffer ?>" <?= $canOfferLess ? 'required' : 'disabled' ?>>
                        <button type="submit" class="btn btn-secondary" <?= $canOfferLess ? '' : 'disabled' ?>>
                            <?= t('hr.btn_negotiate_raise') ?>
                        </button>
                    </div>
                    <?php if (!$canOfferLess): ?><small><?= t('hr.raise_smaller_offer_unavailable') ?></small><?php endif ?>
                </form>
                <button type="button" class="btn btn-secondary" data-raise-action="postpone"
                        data-request-id="<?= $requestId ?>" data-employee-name="<?= htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') ?>" <?= $canPostpone ? '' : 'disabled' ?>>
                    <?= t('hr.btn_postpone_raise') ?>
                </button>
                <button type="button" class="btn btn-danger" data-raise-action="reject"
                        data-request-id="<?= $requestId ?>" data-employee-name="<?= htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') ?>">
                    <?= t('hr.btn_reject_raise') ?>
                </button>
            </div>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
</div>
<div class="hr-tabs module-tabs">
    <?php foreach ([
        'employees' => count($canonicalEmployees),
        'recruitment' => count($staffCandidates),
        'raises' => count($raiseRequests),
        'morale' => null,
        'conflicts' => count($activeStrikes),
        'training' => count($trainingRows),
        'history' => count($employeeEvents) + count($history),
    ] as $tabCode => $tabCount): ?>
        <button type="button"
                class="hr-tab module-tab<?= $activeHrTab === $tabCode ? ' active' : '' ?>"
                data-hr-tab="<?= htmlspecialchars($tabCode, ENT_QUOTES, 'UTF-8') ?>">
            <?= t('hr.tab_' . $tabCode) ?>
            <?php if ($tabCount !== null && $tabCount > 0): ?>
                <span class="tab-badge module-tab-badge module-tab-badge--muted"><?= $tabCount ?></span>
            <?php endif ?>
        </button>
    <?php endforeach ?>
</div>

<div class="hr-container">

<div id="tab-employees" class="hr-tab-content" data-canonical-panel="employees">
    <div class="hr-section-header">
        <h2><?= t('hr.employees_title') ?></h2>
        <p><?= t('hr.employees_desc') ?></p>
    </div>
    <?php if (empty($employees)): ?>
        <div class="hr-empty hr-empty--big">
            <p><?= t('hr.no_employees') ?></p>
        </div>
    <?php else: ?>
    <div class="employees-grid">
        <?php foreach ($employees as $emp):
            $expLevel = $hrSeniorityLevel($emp);
            $expLabel = t('hr.exp_' . $expLevel);
            $avg = round(($emp['skill_organization'] + $emp['skill_negotiation'] + $emp['skill_analysis'] + $emp['skill_stress'] + $emp['skill_ethics']) / 5, 1);
            $warn = isset($emp['contract_days_left']) && $emp['contract_days_left'] <= 14 && $emp['contract_days_left'] >= 0;
            $age = !empty($emp['birth_date']) ? date_diff(date_create($emp['birth_date']), date_create('today'))->y : null;
            $empDomId = ($emp['source'] ?? 'board_member') . '-' . (int)$emp['id'];
            $initials = mb_strtoupper(mb_substr((string)$emp['first_name'], 0, 1) . mb_substr((string)$emp['last_name'], 0, 1), 'UTF-8');
            $sourceType = (string)($emp['source'] ?? 'board_member');
            $canonical = $canonicalEmployeeMap[$sourceType . ':' . (int)$emp['id']] ?? [];
            $recordKey = 'employee:' . (int)$emp['id'];
        ?>
        <article class="employee-card <?= $warn ? 'contract-warning' : '' ?><?= $focusedRecord === $recordKey ? ' hr-record-focus' : '' ?>"
                 data-toggle-employee="<?= htmlspecialchars($empDomId, ENT_QUOTES, 'UTF-8') ?>"
                 data-record="<?= htmlspecialchars($recordKey, ENT_QUOTES, 'UTF-8') ?>" tabindex="0">
            <div class="emp-header">
                <div class="emp-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="emp-info">
                    <div class="emp-name"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                    <div class="emp-role"><?= htmlspecialchars($emp['role_name']) ?></div>
                    <div class="emp-meta">
                        <?= $age !== null ? $age . ' ' . t('hr.years_age') . ' · ' : '' ?><?= $emp['experience_years'] ?><?= t('hr.years_exp') ?>&nbsp;·&nbsp;
                        <span class="exp-badge exp-<?= htmlspecialchars($expLevel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($expLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        &nbsp;·&nbsp; <?= htmlspecialchars($emp['nationality'] ?? '') ?>
                    </div>
                </div>
                <div class="emp-salary-block">
                    <div class="emp-salary"><?= number_format((float)$emp['salary'], 0, ',', ' ') ?> <?= $currencyLabel ?></div>
                    <div class="emp-salary-label"><?= t('hr.salary_month') ?></div>
                    <?php if ($warn): ?><div class="emp-contract-warn"><?= t('hr.contract_expiring', ['days' => (int)$emp['contract_days_left']]) ?></div><?php endif ?>
                </div>
            </div>

            <div class="emp-details" id="emp-details-<?= $empDomId ?>">
                <dl class="hr-employee-metrics">
                    <div><dt><?= t('hr.expected_salary') ?></dt><dd><?= number_format((float)($canonical['expected_salary'] ?? $emp['salary']), 0, ',', ' ') ?> <?= $currencyLabel ?></dd></div>
                    <div><dt><?= t('hr.salary_satisfaction') ?></dt><dd><?= number_format((float)($canonical['salary_satisfaction'] ?? 70), 0, ',', ' ') ?>%</dd></div>
                    <div><dt><?= t('hr.morale_label') ?></dt><dd><?= number_format((float)($canonical['morale'] ?? $emp['morale'] ?? 65), 0, ',', ' ') ?>%</dd></div>
                    <div><dt><?= t('hr.workload') ?></dt><dd><?= number_format((float)($canonical['workload'] ?? 0), 0, ',', ' ') ?>%</dd></div>
                    <div><dt><?= t('hr.leave_risk') ?></dt><dd><?= number_format((float)($canonical['leave_risk'] ?? 0), 0, ',', ' ') ?>%</dd></div>
                    <div><dt><?= t('hr.strike_support') ?></dt><dd><?= number_format((float)($canonical['strike_support'] ?? 0), 0, ',', ' ') ?>%</dd></div>
                    <div><dt><?= t('hr.relation_status') ?></dt><dd><?= $relationLabel((string)($canonical['relation_status'] ?? 'normal')) ?></dd></div>
                    <div><dt><?= t('hr.assignments') ?></dt><dd><?= count((array)($canonical['assignments'] ?? [])) ?></dd></div>
                </dl>
                <?php if (!empty($canonical['assignments'])): ?>
                    <ul class="hr-assignment-list">
                        <?php foreach ($canonical['assignments'] as $assignment):
                            $targetType = (string)($assignment['target_type'] ?? '');
                            $targetLabel = $assignmentTargetLabels[$targetType] ?? t('hr.assignment_target.other');
                        ?>
                            <li>
                                <span><?= htmlspecialchars($targetLabel, ENT_QUOTES, 'UTF-8') ?> #<?= (int)($assignment['target_id'] ?? 0) ?></span>
                                <strong><?= number_format((float)($assignment['allocation_pct'] ?? 0), 0, ',', ' ') ?>%</strong>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
                <div class="cv-section-label"><?= t('hr.skill_label') ?> &nbsp;<span class="skills-avg-label"><?= sprintf(t('hr.skills_avg'), $avg) ?></span></div>
                <div class="emp-skills-grid">
                    <?php foreach (['skill_organization' => t('hr.skill_organization'), 'skill_negotiation' => t('hr.skill_negotiation'), 'skill_analysis' => t('hr.skill_analysis'), 'skill_stress' => t('hr.skill_stress'), 'skill_ethics' => t('hr.skill_ethics')] as $k => $l): ?>
                    <div class="skill-item">
                        <div class="skill-label"><?= $l ?></div>
                        <div class="skill-bar"><div class="skill-fill" style="--bar-w:<?= $emp[$k] * 10 ?>%"></div></div>
                        <div class="skill-value"><?= $emp[$k] ?>/10</div>
                    </div>
                    <?php endforeach ?>
                </div>

                <div class="cv-section-label cv-section-label--mt"><?= t('hr.traits_label') ?></div>
                <div class="cand-traits">
                    <div class="trait-item"><span class="trait-label"><?= t('hr.trait_loyalty') ?></span><div class="trait-bar"><div class="trait-fill trait-loyalty" style="--bar-w:<?= $emp['trait_loyalty'] * 10 ?>%"></div></div><span class="trait-val"><?= $emp['trait_loyalty'] ?>/10</span></div>
                    <div class="trait-item"><span class="trait-label"><?= t('hr.trait_corruption') ?></span><div class="trait-bar"><div class="trait-fill trait-corruption" style="--bar-w:<?= $emp['trait_corruption_risk'] * 10 ?>%"></div></div><span class="trait-val"><?= $emp['trait_corruption_risk'] ?>/10</span></div>
                    <div class="trait-item"><span class="trait-label"><?= t('hr.trait_ambition') ?></span><div class="trait-bar"><div class="trait-fill trait-ambition" style="--bar-w:<?= $emp['trait_ambition'] * 10 ?>%"></div></div><span class="trait-val"><?= $emp['trait_ambition'] ?>/10</span></div>
                </div>

                <?php if (($emp['source'] ?? '') === 'technical_staff'): ?>
                <div class="cv-section-label cv-section-label--mt"><?= t('hr.morale_label') ?></div>
                <div class="emp-morale-section">
                    <?php 
                        $m = (int)($emp['morale'] ?? 50);
                        $mColor = $m >= 70 ? 'c-green' : ($m >= 40 ? 'c-gold' : 'c-bad'); 
                        $mBg = $m >= 70 ? '#4caf50' : ($m >= 40 ? '#ffb300' : '#e53935');
                    ?>
                    <div class="morale-bar-container">
                        <span class="morale-val <?= $mColor ?>"><?= $m ?>%</span>
                        <div class="morale-bar">
                            <div class="morale-fill" style="--bar-w:<?= $m ?>%;--morale-color:<?= $mBg ?>"></div>
                        </div>
                    </div>

                </div>
                <?php endif ?>

                <div class="emp-footer-info">
                    <span><?= t('hr.hired_days_ago', ['days' => $emp['days_employed']]) ?></span>
                    <?php if (!empty($emp['contract_end'])): ?>
                    <span class="<?= $warn ? 'text-warning' : '' ?>"><?= t('hr.contract_until') ?>: <?= date('d.m.Y', strtotime($emp['contract_end'])) ?></span>
                    <?php endif ?>
                    <?php if (!empty($emp['spec_name'])): ?><span><?= htmlspecialchars($emp['spec_name']) ?></span><?php endif ?>
                </div>

                <div class="emp-actions">
                    <?php if (!empty($emp['contract_end'])): ?>
                    <select class="hr-select-small" id="renew-<?= $emp['id'] ?>">
                        <option value="1y"><?= t('hr.renew_1y') ?></option>
                        <option value="6m"><?= t('hr.renew_6m') ?></option>
                        <option value="2y"><?= t('hr.renew_2y') ?></option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" data-hr-action="renew" data-employee-id="<?= (int)$emp['id'] ?>" data-employee-name="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'], ENT_QUOTES, 'UTF-8') ?>"><?= t('hr.btn_renew') ?></button>
                    <?php endif ?>
                    <?php if (($emp['source'] ?? 'board_member') === 'technical_staff'): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-hr-action="bonus" data-employee-id="<?= (int)$emp['id'] ?>" data-employee-name="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'], ENT_QUOTES, 'UTF-8') ?>"><?= t('hr.btn_grant_bonus') ?></button>
                    <button type="button" class="btn btn-sm btn-danger" data-hr-action="fire-technical" data-employee-id="<?= (int)$emp['id'] ?>" data-employee-name="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'], ENT_QUOTES, 'UTF-8') ?>"><?= t('hr.btn_fire') ?></button>
                    <?php else: ?>
                    <button type="button" class="btn btn-sm btn-danger" data-hr-action="fire" data-employee-id="<?= (int)$emp['id'] ?>" data-employee-name="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'], ENT_QUOTES, 'UTF-8') ?>"><?= t('hr.btn_fire') ?></button>
                    <?php endif ?>
                </div>
            </div>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-candidates" class="hr-tab-content" data-canonical-panel="recruitment">
    <div class="hr-section-header">
        <h2><?= t('hr.candidates_title') ?></h2>
        <p><?= t('hr.candidates_desc') ?></p>
    </div>

    <?php if (empty($staffCandidates)): ?>
        <div class="hr-empty hr-empty--big">
            <p><?= t('hr.no_candidates') ?></p>
        </div>
    <?php else: ?>
    <div class="candidates-grid">
        <?php foreach ($staffCandidates as $candidate):
            $avg = round(($candidate['skill_organization'] + $candidate['skill_negotiation'] + $candidate['skill_analysis'] + $candidate['skill_stress'] + $candidate['skill_ethics']) / 5, 1);
            $expCode = $candidate['experience_years'] <= 5 ? 'junior' : ($candidate['experience_years'] <= 12 ? 'mid' : 'senior');
            $expLevel = t('hr.exp_' . $expCode);
            $hoursLeft = max(0, (int)$candidate['hours_remaining']);
            $isRecommended = ($candidate['tech_recommendation'] ?? '') === 'hire';
            $isRejected = ($candidate['tech_recommendation'] ?? '') === 'reject';
        ?>
        <article class="candidate-card-hr <?= $isRecommended ? 'hr-recommended' : '' ?>" data-candidate-card="<?= (int)$candidate['id'] ?>">
            <?php if ($isRecommended): ?><div class="hr-rec-badge"><?= t('hr.rec_badge') ?></div><?php endif ?>

            <div class="cand-header">
                <div>
                    <div class="cand-name"><?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?></div>
                    <div class="cand-meta">
                        <?= htmlspecialchars($candidate['spec_name'] ?? $candidate['role_name']) ?>
                        &nbsp;·&nbsp;<?= (int)$candidate['age'] ?> <?= t('hr.years_age') ?>
                        &nbsp;·&nbsp;<?= htmlspecialchars($candidate['nationality'] ?? '') ?>
                        - <span class="exp-badge exp-<?= $expCode ?>"><?= $expLevel ?> (<?= (int)$candidate['experience_years'] ?> <?= t('hr.years_exp_short') ?>)</span>
                    </div>
                </div>
                <div class="cand-salary">
                    <?= number_format((float)$candidate['expected_salary'], 0, ',', ' ') ?> <?= $currencyLabel ?>
                    <div class="cand-salary-label"><?= t('hr.salary_per_month') ?></div>
                </div>
            </div>

            <div class="cv-section-label"><?= t('hr.skill_label') ?></div>
            <div class="cand-skills">
                <?php foreach (['skill_organization' => t('hr.skill_organization'), 'skill_negotiation' => t('hr.skill_negotiation'), 'skill_analysis' => t('hr.skill_analysis'), 'skill_stress' => t('hr.skill_stress'), 'skill_ethics' => t('hr.skill_ethics')] as $key => $label): ?>
                <div class="skill-item">
                    <div class="skill-label"><?= $label ?></div>
                    <div class="skill-bar"><div class="skill-fill" style="--bar-w:<?= $candidate[$key] * 10 ?>%"></div></div>
                    <div class="skill-value"><?= $candidate[$key] ?>/10</div>
                </div>
                <?php endforeach ?>
            </div>

            <div class="cv-section-label cv-section-label--mt"><?= t('hr.traits_label') ?></div>
            <div class="cand-traits">
                <div class="trait-item"><span class="trait-label"><?= t('hr.trait_loyalty') ?></span><div class="trait-bar"><div class="trait-fill trait-loyalty" style="--bar-w:<?= $candidate['trait_loyalty'] * 10 ?>%"></div></div><span class="trait-val"><?= $candidate['trait_loyalty'] ?>/10</span></div>
                <div class="trait-item"><span class="trait-label"><?= t('hr.trait_corruption') ?></span><div class="trait-bar"><div class="trait-fill trait-corruption" style="--bar-w:<?= $candidate['trait_corruption_risk'] * 10 ?>%"></div></div><span class="trait-val"><?= $candidate['trait_corruption_risk'] ?>/10</span></div>
                <div class="trait-item"><span class="trait-label"><?= t('hr.trait_ambition') ?></span><div class="trait-bar"><div class="trait-fill trait-ambition" style="--bar-w:<?= $candidate['trait_ambition'] * 10 ?>%"></div></div><span class="trait-val"><?= $candidate['trait_ambition'] ?>/10</span></div>
            </div>

            <?php if (!empty($candidate['technical_score'])): ?>
            <div class="tech-review-badge <?= $isRecommended ? 'rev-hire' : ($isRejected ? 'rev-reject' : 'rev-pending') ?>">
                <span class="tech-rev-title"><?= t('hr.tech_review_title') ?></span>
                <span class="tech-rev-score"><?= (int)$candidate['technical_score'] ?>/10</span>
                <?php if (!empty($candidate['tech_comment'])): ?>
                <span class="tech-rev-comment">"<?= htmlspecialchars($candidate['tech_comment']) ?>"</span>
                <?php endif ?>
            </div>
            <?php else: ?>
            <div class="tech-review-badge rev-pending"><?= t('hr.tech_review_pending') ?></div>
            <?php endif ?>

            <div class="cand-footer">
                <div class="cand-footer-left">
                    <span class="cand-avg"><?= sprintf(t('hr.skills_avg'), $avg) ?></span>
                    <span class="cand-expires <?= $hoursLeft < 12 ? 'cand-expires--urgent' : '' ?>"><?= t('hr.expires_hours', ['hours' => $hoursLeft]) ?></span>
                    <?php if (!empty($candidate['region_name'])): ?><span class="cand-region-name"><?= htmlspecialchars($candidate['region_name']) ?></span><?php endif ?>
                </div>
                <div class="cand-footer-actions">
                    <select class="hr-select-small" id="contract-<?= (int)$candidate['id'] ?>">
                        <option value="1y"><?= t('hr.contract_1y') ?></option>
                        <option value="6m"><?= t('hr.contract_6m') ?></option>
                        <option value="2y"><?= t('hr.contract_2y') ?></option>
                    </select>
                    <button type="button" class="btn-cv btn-cv-reject" data-hr-action="reject-candidate" data-candidate-id="<?= (int)$candidate['id'] ?>" data-employee-name="<?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name'], ENT_QUOTES, 'UTF-8') ?>"><?= t('hr.btn_reject') ?></button>
                    <button type="button" class="btn-cv btn-cv-hire <?= $isRecommended ? 'btn-cv-hire-recommended' : '' ?>" data-hr-action="hire-candidate" data-candidate-id="<?= (int)$candidate['id'] ?>" data-employee-name="<?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= $isRecommended ? t('hr.btn_hire_recommended') : t('hr.btn_hire') ?>
                    </button>
                </div>
            </div>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-directors" class="hr-tab-content" data-canonical-panel="employees">
    <div class="hr-section-header">
        <h2><?= t('hr.directors_title') ?></h2>
        <p><?= t('hr.directors_desc') ?></p>
    </div>

    <?php if (empty($directors)): ?>
        <div class="hr-empty hr-empty--big">
            <p><?= t('hr.no_directors') ?></p>
        </div>
    <?php else: ?>
    <div class="employees-grid directors-grid">
        <?php foreach ($directors as $emp):
            $expLevel = $hrSeniorityLevel($emp);
            $expLabel = t('hr.exp_' . $expLevel);
            $avg = round(($emp['skill_organization'] + $emp['skill_negotiation'] + $emp['skill_analysis'] + $emp['skill_stress'] + $emp['skill_ethics']) / 5, 1);
            $age = (int)($emp['age'] ?? 0);
            $initials = mb_strtoupper(mb_substr((string)$emp['first_name'], 0, 1) . mb_substr((string)$emp['last_name'], 0, 1), 'UTF-8');
        ?>
        <article class="employee-card director-card" data-toggle-employee="director-<?= (int)$emp['id'] ?>" tabindex="0">
            <div class="emp-header">
                <div class="emp-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="emp-info">
                    <div class="emp-name"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                    <div class="emp-role"><?= htmlspecialchars($emp['role_name']) ?></div>
                    <div class="emp-meta">
                        <?= $age ?> <?= t('hr.years_age') ?> &nbsp;·&nbsp; <?= $emp['experience_years'] ?><?= t('hr.years_exp') ?>&nbsp;·&nbsp;
                        <span class="exp-badge exp-<?= htmlspecialchars($expLevel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($expLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        &nbsp;·&nbsp; <?= htmlspecialchars($emp['nationality'] ?? '') ?>
                    </div>
                </div>
                <div class="emp-salary-block">
                    <div class="emp-salary"><?= number_format((float)$emp['salary'], 0, ',', ' ') ?> <?= $currencyLabel ?></div>
                    <div class="emp-salary-label"><?= t('hr.salary_month') ?></div>
                </div>
            </div>

            <div class="emp-details" id="emp-details-director-<?= (int)$emp['id'] ?>">
                <div class="cv-section-label"><?= t('hr.skill_label') ?> &nbsp;<span class="skills-avg-label"><?= sprintf(t('hr.skills_avg'), $avg) ?></span></div>
                <div class="emp-skills-grid">
                    <?php foreach (['skill_organization' => t('hr.skill_organization'), 'skill_negotiation' => t('hr.skill_negotiation'), 'skill_analysis' => t('hr.skill_analysis'), 'skill_stress' => t('hr.skill_stress'), 'skill_ethics' => t('hr.skill_ethics')] as $k => $l): ?>
                    <div class="skill-item">
                        <div class="skill-label"><?= $l ?></div>
                        <div class="skill-bar"><div class="skill-fill" style="--bar-w:<?= $emp[$k] * 10 ?>%"></div></div>
                        <div class="skill-value"><?= $emp[$k] ?>/10</div>
                    </div>
                    <?php endforeach ?>
                </div>
                <div class="emp-footer-info">
                    <span><?= t('hr.hired_days_ago', ['days' => $emp['days_employed']]) ?></span>
                    <span><?= t('hr.directors_boardroom_hint') ?></span>
                </div>
            </div>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-contracts" class="hr-tab-content" data-canonical-panel="employees">
    <div class="hr-section-header">
        <h2><?= t('hr.contracts_title') ?></h2>
        <p><?= t('hr.contracts_desc') ?></p>
    </div>
    <?php if (empty($contracts)): ?>
        <div class="hr-empty hr-empty--big"><?= t('hr.no_contracts') ?></div>
    <?php else: ?>
    <div class="contracts-table">
        <div class="contracts-thead">
            <div><?= t('hr.col_employee') ?></div>
            <div><?= t('hr.col_position') ?></div>
            <div><?= t('hr.col_period') ?></div>
            <div><?= t('hr.col_salary') ?></div>
            <div><?= t('hr.col_status') ?></div>
        </div>
        <?php foreach ($contracts as $c):
            $isExp = ($c['days_left'] ?? 999) <= 14;
            $isDead = ($c['days_left'] ?? 0) < 0;
        ?>
        <div class="contracts-row <?= $isExp ? 'row-warning' : '' ?>">
            <div><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
            <div><?= htmlspecialchars($c['role_name']) ?></div>
            <div class="contract-dates"><?= date('d.m.Y', strtotime($c['contract_start'])) ?> - <?= date('d.m.Y', strtotime($c['contract_end'])) ?></div>
            <div><?= number_format((float)$c['salary'], 0, ',', ' ') ?> <?= $currencyLabel ?></div>
            <div>
                <?php if ($isDead): ?><span class="badge-expired"><?= t('hr.badge_expired') ?></span>
                <?php elseif ($isExp): ?><span class="badge-expiring"><?= t('hr.contract_expiring', ['days' => (int)$c['days_left']]) ?></span>
                <?php else: ?><span class="badge-active"><?= $c['days_left'] ?> <?= t('common.days') ?></span><?php endif ?>
            </div>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-morale" class="hr-tab-content" data-canonical-panel="morale">
    <div class="hr-section-header">
        <h2><?= t('hr.morale_title') ?></h2>
        <p><?= t('hr.morale_desc') ?></p>
    </div>
    <dl class="hr-summary-grid">
        <div><dt><?= t('hr.employee_count') ?></dt><dd><?= (int)($moraleSummary['employee_count'] ?? 0) ?></dd></div>
        <div><dt><?= t('hr.average_morale') ?></dt><dd><?= number_format((float)($moraleSummary['average_morale'] ?? 0), 1, ',', ' ') ?>%</dd></div>
        <div><dt><?= t('hr.average_leave_risk') ?></dt><dd><?= number_format((float)($moraleSummary['average_leave_risk'] ?? 0), 1, ',', ' ') ?>%</dd></div>
        <div><dt><?= t('hr.average_strike_support') ?></dt><dd><?= number_format((float)($moraleSummary['average_strike_support'] ?? 0), 1, ',', ' ') ?>%</dd></div>
    </dl>
    <?php if (empty($canonicalEmployees)): ?>
        <div class="hr-empty hr-empty--big"><p><?= t('hr.no_employees') ?></p></div>
    <?php else: ?>
        <div class="hr-morale-list">
            <?php foreach ($canonicalEmployees as $employee): ?>
                <article class="hr-morale-row">
                    <div>
                        <strong><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= $departmentLabel((string)$employee['department_code']) ?></span>
                    </div>
                    <div><span><?= t('hr.morale_label') ?></span><strong><?= number_format((float)$employee['morale'], 0, ',', ' ') ?>%</strong></div>
                    <div><span><?= t('hr.salary_satisfaction') ?></span><strong><?= number_format((float)$employee['salary_satisfaction'], 0, ',', ' ') ?>%</strong></div>
                    <div><span><?= t('hr.leave_risk') ?></span><strong><?= number_format((float)$employee['leave_risk'], 0, ',', ' ') ?>%</strong></div>
                    <div><span><?= t('hr.relation_status') ?></span><strong><?= $relationLabel((string)$employee['relation_status']) ?></strong></div>
                </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<div id="tab-training" class="hr-tab-content" data-canonical-panel="training">
    <div class="hr-section-header">
        <h2><?= t('hr.training_title') ?></h2>
        <p><?= t('hr.training_desc') ?></p>
    </div>
    <?php if (empty($trainingRows)): ?>
        <div class="hr-empty hr-empty--big"><p><?= t('hr.training_empty') ?></p></div>
    <?php else: ?>
        <div class="hr-training-list">
            <?php foreach ($trainingRows as $training):
                $trainingId = (int)($training['id'] ?? 0);
                $trainingName = $locale === 'en'
                    ? (string)($training['name_en'] ?? $training['name_pl'] ?? '')
                    : (string)($training['name_pl'] ?? $training['name_en'] ?? '');
                $trainingStatus = (string)($training['status'] ?? 'in_progress');
                $trainingStatus = in_array($trainingStatus, ['in_progress', 'passed', 'failed', 'cancelled'], true)
                    ? $trainingStatus
                    : 'cancelled';
                $skillCode = (string)($training['target_skill'] ?? 'other');
                $skillCode = in_array($skillCode, [
                    'skill_drilling', 'skill_maintenance', 'skill_safety', 'skill_analysis',
                    'skill_negotiation', 'skill_ethics', 'skill_stress', 'skill_organization',
                ], true) ? $skillCode : 'other';
            ?>
                <article class="hr-training-row<?= $focusedRecord === 'training:' . $trainingId ? ' hr-record-focus' : '' ?>"
                         data-record="training:<?= $trainingId ?>">
                    <div><strong><?= htmlspecialchars($trainingName, ENT_QUOTES, 'UTF-8') ?></strong><span><?= t('hr.training_skill') ?>: <?= t('hr.skill_code.' . $skillCode) ?></span></div>
                    <div><span><?= t('hr.training_status_label') ?></span><strong><?= t('hr.training_status.' . $trainingStatus) ?></strong></div>
                    <div><span><?= t('hr.training_started') ?></span><strong><?= !empty($training['started_at']) ? date('d.m.Y H:i', strtotime((string)$training['started_at'])) : '-' ?></strong></div>
                    <div><span><?= t('hr.training_result') ?></span><strong><?= isset($training['exam_score']) ? (int)$training['exam_score'] . '/100' : '-' ?></strong></div>
                </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<div id="tab-history" class="hr-tab-content" data-canonical-panel="history">
    <div class="hr-section-header">
        <h2><?= t('hr.history_title') ?></h2>
        <p><?= t('hr.history_desc') ?></p>
    </div>

    <?php if (!empty($employeeEvents)): ?>
        <div class="hr-event-list">
            <?php foreach ($employeeEvents as $event):
                $eventId = (int)($event['id'] ?? 0);
                $meta = json_decode((string)($event['meta_json'] ?? ''), true);
                $meta = is_array($meta) ? $meta : [];
                $eventTitle = t((string)$event['title_key'], $meta);
                $eventMessage = t((string)$event['message_key'], $meta);
                if ($eventTitle === (string)$event['title_key']) {
                    $eventTitle = t('hr.event_generic_title');
                }
                if ($eventMessage === (string)$event['message_key']) {
                    $eventMessage = t('hr.event_generic_message');
                }
            ?>
                <article class="hr-event-row<?= $focusedRecord === 'event:' . $eventId ? ' hr-record-focus' : '' ?>"
                         data-record="event:<?= $eventId ?>">
                    <time datetime="<?= htmlspecialchars((string)$event['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= date('d.m.Y H:i', strtotime((string)$event['created_at'])) ?></time>
                    <div>
                        <strong><?= htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                        <p><?= htmlspecialchars($eventMessage, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (empty($history) && empty($employeeEvents)): ?>
        <div class="hr-empty hr-empty--big">
            <p><?= t('hr.no_history') ?></p>
        </div>
    <?php else: ?>
    <div class="history-table">
        <div class="history-thead">
            <div><?= t('hr.col_date') ?></div>
            <div><?= t('hr.col_event') ?></div>
            <div><?= t('hr.col_employee') ?></div>
            <div><?= t('hr.col_position') ?></div>
            <div><?= t('hr.col_reason') ?></div>
        </div>
        <?php foreach ($history as $h): ?>
        <div class="history-row">
            <div class="history-date"><?= date('d.m.Y', strtotime($h['created_at'])) ?><span><?= date('H:i', strtotime($h['created_at'])) ?></span></div>
            <div>
                <?php
                $badges = [
                    'hired' => [t('hr.action_hired'), 'action-hired'],
                    'fired' => [t('hr.action_fired'), 'action-fired'],
                    'resigned' => [t('hr.action_resigned'), 'action-resigned'],
                    'suspended' => [t('hr.action_suspended'), 'action-suspended'],
                ];
                [$label, $cls] = $badges[$h['action']] ?? [t('hr.action_other'), 'action-hired'];
                ?>
                <span class="action-badge <?= $cls ?>"><?= $label ?></span>
            </div>
            <div class="history-name"><?= htmlspecialchars(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? '')) ?></div>
            <div class="history-role"><?= htmlspecialchars($h['role_name'] ?? '-') ?></div>
            <div class="history-reason"><?= htmlspecialchars($h['reason'] ?? '-') ?></div>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-market" class="hr-tab-content" data-canonical-panel="recruitment">
    <div class="hr-section-header">
        <h2><?= t('hr.market_title') ?></h2>
        <p><?= t('hr.market_desc') ?></p>
    </div>
    <div class="market-regions-grid">
        <?php foreach ($regions as $r): ?>
        <div class="market-region-card">
            <div class="market-region-name"><?= htmlspecialchars($r['name']) ?></div>
            <div class="market-region-code"><?= htmlspecialchars($r['code']) ?></div>
            <div class="market-stats">
                <div class="market-stat">
                    <span class="market-stat-label"><?= t('hr.stat_skills') ?></span>
                    <?php $sk = $r['skill_modifier']; $skCls = $sk >= 1.2 ? 'c-green' : ($sk < 1 ? 'c-bad' : 'c-gold'); ?>
                    <span class="market-stat-val <?= $skCls ?>">x<?= $sk ?></span>
                </div>
                <div class="market-stat">
                    <span class="market-stat-label"><?= t('hr.stat_salaries') ?></span>
                    <?php $sal = $r['salary_modifier']; $salCls = $sal >= 1.2 ? 'c-bad' : ($sal < 1 ? 'c-green' : 'c-gold'); ?>
                    <span class="market-stat-val <?= $salCls ?>">x<?= $sal ?></span>
                </div>
                <div class="market-stat">
                    <span class="market-stat-label"><?= t('hr.stat_availability') ?></span>
                    <div class="avail-bar"><div class="avail-fill" style="width:<?= (float)$r['availability'] ?>%"></div></div>
                    <span class="market-stat-val"><?= (int)$r['availability'] ?>%</span>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>

<div id="tab-headhunter" class="hr-tab-content" data-canonical-panel="recruitment">
    <div class="hr-section-header">
        <h2><?= t('hr.hh_title') ?></h2>
        <p><?= t('hr.hh_desc') ?></p>
    </div>

    <?php if ($hhActiveSearch): ?>
    <div class="hh-status-card hh-searching">
        <div class="hh-status-info">
            <div class="hh-status-title"><?= t('hr.hh_searching_for') ?>: <?= htmlspecialchars($hhActiveSearch['spec_name']) ?></div>
            <div class="hh-status-meta">
                <?= t('hr.hh_cost_label') ?>: <?= isset($hhActiveSearch['cost']) ? HeadhunterService::fmt((float)$hhActiveSearch['cost']) : t('hr.hh_cost_settled') ?>
                · <?= t('hr.hh_remaining') ?>: <span class="countdown" data-end="<?= strtotime($hhActiveSearch['finished_at']) ?>"></span>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="hh-launch-form">
        <div class="hh-launch-title"><?= t('hr.hh_launch_title') ?></div>
        <div class="hh-launch-grid">
            <div class="form-group">
                <label class="hr-label"><?= t('hr.hh_spec_label') ?></label>
                <select id="hh-spec" class="hr-select">
                    <?php foreach ($specializations as $sp): ?>
                    <?php if (($sp['department'] ?? '') !== 'technical') { continue; } ?>
                    <option value="<?= (int)$sp['id'] ?>"><?= htmlspecialchars($sp['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="hh-cost-info">
                <div class="hh-cost-label"><?= t('hr.hh_cost_label') ?></div>
                <div class="hh-cost-range"><?= t('hr.hh_cost_range') ?></div>
                <div class="hh-cost-note"><?= t('hr.hh_cost_note') ?></div>
            </div>
            <div class="hh-time-info">
                <div class="hh-time-label"><?= t('hr.hh_time_label') ?></div>
                <div class="hh-time-range"><?= t('hr.hh_time_range') ?></div>
                <div class="hh-time-note"><?= t('hr.hh_time_note') ?></div>
            </div>
        </div>
        <button type="button" class="btn btn-primary btn-full" data-hr-action="start-headhunter"><?= t('hr.hh_btn_launch') ?></button>
    </div>
    <?php endif ?>

    <?php if (!empty($hhCandidates)): ?>
    <h3 class="hr-subtitle hr-subtitle--mt"><?= t('hr.hh_found_candidates', ['count' => count($hhCandidates)]) ?></h3>
    <?php foreach ($hhCandidates as $hc):
        $hoursLeftHH = (int)$hc['hours_remaining'];
        $minBonusHH = (float)($hc['signing_bonus_min'] ?? $hc['signing_bonus'] ?? 0);
        $loyaltyHH = (int)($hc['loyalty'] ?? $hc['trait_loyalty'] ?? 0);
    ?>
    <div class="hh-candidate-card">
        <div class="hh-cand-hdr">
            <div>
                <div class="hh-cand-name"><?= htmlspecialchars($hc['first_name'] . ' ' . $hc['last_name']) ?></div>
                <div class="hh-cand-spec"><?= htmlspecialchars($hc['spec_name']) ?></div>
                <div class="hh-cand-company"><?= htmlspecialchars($hc['current_company']) ?></div>
            </div>
            <div class="hh-cand-skill-badge">
                <div class="hh-skill-num"><?= $hc['skill_level'] ?></div>
                <div class="hh-skill-lbl"><?= t('technical.skill_label') ?></div>
            </div>
        </div>
        <div class="hh-cand-stats">
            <div class="hh-stat"><div class="hh-stat-lbl"><?= t('hr.hh_salary_exp') ?></div><div class="hh-stat-val c-gold"><?= HeadhunterService::fmt((float)$hc['salary_expectation']) ?>/<?= t('hr.month_short') ?></div></div>
            <div class="hh-stat"><div class="hh-stat-lbl"><?= t('hr.hh_bonus_min') ?></div><div class="hh-stat-val"><?= HeadhunterService::fmt($minBonusHH) ?></div></div>
            <div class="hh-stat"><div class="hh-stat-lbl"><?= t('hr.hh_join_prob') ?></div><div class="hh-stat-val c-blue"><?= (int)$hc['join_probability'] ?>%</div></div>
            <div class="hh-stat"><div class="hh-stat-lbl"><?= t('hr.hh_loyalty') ?></div><div class="hh-stat-val <?= $loyaltyHH >= 7 ? 'c-bad' : ($loyaltyHH >= 5 ? 'c-warn' : 'c-green') ?>"><?= $loyaltyHH ?>/10</div></div>
            <div class="hh-stat"><div class="hh-stat-lbl"><?= t('hr.hh_expires') ?></div><div class="hh-stat-val <?= $hoursLeftHH < 12 ? 'c-bad' : 'c-muted2' ?>"><?= $hoursLeftHH ?>h</div></div>
        </div>
        <details>
            <summary class="task-assign-toggle"><?= t('hr.hh_offer_btn') ?></summary>
            <form class="hh-offer-form" data-headhunter-offer="<?= (int)$hc['id'] ?>">
                <div class="hh-offer-grid">
                    <div class="form-group form-group--flush">
                        <label class="form-label"><?= t('hr.hh_salary_input') ?></label>
                        <input type="number" id="hh-salary-<?= (int)$hc['id'] ?>" class="form-input" value="<?= (float)$hc['salary_expectation'] ?>" min="5000" step="500">
                    </div>
                    <div class="form-group form-group--flush">
                        <label class="form-label"><?= t('hr.hh_bonus_input') ?></label>
                        <input type="number" id="hh-bonus-<?= (int)$hc['id'] ?>" class="form-input" value="<?= $minBonusHH ?>" min="0" step="50000">
                    </div>
                </div>
                <div class="hh-offer-hint"><?= t('hr.hh_offer_hint') ?></div>
                <button type="submit" class="btn btn-primary btn-sm"><?= t('hr.hh_submit_offer') ?></button>
            </form>
        </details>
    </div>
    <?php endforeach ?>
    <?php endif ?>

    <?php if (!empty($hhRecentSearches)): ?>
    <h3 class="hr-subtitle hr-subtitle--mt"><?= t('hr.hh_recent_searches') ?></h3>
    <div class="data-list">
        <div class="data-list-head">
            <span><?= t('hr.hh_col_spec') ?></span>
            <span><?= t('hr.hh_col_status') ?></span>
            <span><?= t('hr.hh_col_candidates') ?></span>
            <span><?= t('hr.hh_col_cost') ?></span>
        </div>
        <?php foreach ($hhRecentSearches as $sr): ?>
        <div class="data-list-row">
            <span><?= htmlspecialchars($sr['spec_name']) ?></span>
            <span class="<?= $sr['status'] === 'completed' ? 'c-green' : ($sr['status'] === 'failed' ? 'c-bad' : 'c-warn') ?>">
                <?= match($sr['status']) {
                    'completed' => t('hr.hh_completed'),
                    'failed' => t('hr.hh_failed'),
                    'searching' => t('hr.hh_searching_status'),
                    default => t('hr.hh_unknown_status'),
                } ?>
            </span>
            <span><?= (int)$sr['result_count'] ?></span>
            <span class="c-muted2"><?= isset($sr['cost']) ? HeadhunterService::fmt((float)$sr['cost']) : '-' ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

</div>
<div id="hr-events-container"></div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const HR_API = '/src/HRApi.php';
window.HR_LOCALE = <?= json_encode($locale) ?>;
window.HR_ACTIVE_TAB = <?= json_encode($activeHrTab) ?>;
window.HR_LANG = <?= json_encode([
    'contract_1y' => t('hr_js.contract_1y'),
    'contract_6m' => t('hr_js.contract_6m'),
    'contract_2y' => t('hr_js.contract_2y'),
    'confirm_hire' => t('hr_js.confirm_hire'),
    'confirm_hire_btn' => t('hr_js.confirm_hire_btn'),
    'confirm_reject' => t('hr_js.confirm_reject'),
    'confirm_reject_btn' => t('hr_js.confirm_reject_btn'),
    'confirm_fire' => t('hr_js.confirm_fire'),
    'confirm_fire_btn' => t('hr_js.confirm_fire_btn'),
    'confirm_renew' => t('hr_js.confirm_renew'),
    'confirm_renew_btn' => t('hr_js.confirm_renew_btn'),
    'prompt_fire_reason' => t('hr_js.prompt_fire_reason'),
    'prompt_fire_default' => t('hr_js.prompt_fire_default'),
    'toast_hired' => t('hr_js.toast_hired'),
    'toast_rejected' => t('hr_js.toast_rejected'),
    'toast_fired' => t('hr_js.toast_fired'),
    'toast_renewed' => t('hr_js.toast_renewed'),
    'toast_negotiating' => t('hr_js.toast_negotiating'),
    'toast_offer_rejected' => t('hr_js.toast_offer_rejected'),
    'toast_headhunter' => t('hr_js.toast_headhunter_start'),
    'toast_err' => t('hr_js.toast_err'),
    'btn_hire' => t('hr.btn_hire'),
    'btn_reject' => t('hr.btn_reject'),
    'no_candidates' => t('hr.no_candidates'),
    'err_no_salary' => t('hr_js.err_no_salary'),
    'err_no_spec' => t('hr_js.err_no_spec'),
    'headhunter_btn' => t('hr_js.headhunter_btn'),
    'headhunter_starting' => t('hr_js.headhunter_starting'),
    'negotiate_msg' => t('hr_js.negotiate_msg'),
    'confirm_bonus' => t('hr_js.confirm_bonus'),
    'confirm_bonus_btn' => t('hr_js.confirm_bonus_btn'),
    'toast_bonus_granted' => t('hr_js.toast_bonus_granted'),
    'strike_negotiation_title' => t('hr_js.strike_negotiation_title'),
    'strike_offer_invalid' => t('hr_js.strike_offer_invalid'),
    'confirm_strike_offer' => t('hr_js.confirm_strike_offer'),
    'confirm_strike_offer_btn' => t('hr_js.confirm_strike_offer_btn'),
    'raise_title' => t('hr_js.raise_title'),
    'raise_offer_invalid' => t('hr_js.raise_offer_invalid'),
    'confirm_raise_accept' => t('hr_js.confirm_raise_accept'),
    'confirm_raise_accept_btn' => t('hr_js.confirm_raise_accept_btn'),
    'confirm_raise_negotiate' => t('hr_js.confirm_raise_negotiate'),
    'confirm_raise_negotiate_btn' => t('hr_js.confirm_raise_negotiate_btn'),
    'confirm_raise_reject' => t('hr_js.confirm_raise_reject'),
    'confirm_raise_reject_btn' => t('hr_js.confirm_raise_reject_btn'),
    'confirm_raise_postpone' => t('hr_js.confirm_raise_postpone'),
    'confirm_raise_postpone_btn' => t('hr_js.confirm_raise_postpone_btn'),
    'err_api_config' => t('hr_js.err_api_config'),
    'err_invalid_response' => t('hr_js.err_invalid_response'),
    'err_http' => t('hr_js.err_http'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/hr.js"></script>
<script src="/assets/js/hr_strikes.js"></script>
<script src="/assets/js/hr_raises.js"></script>
