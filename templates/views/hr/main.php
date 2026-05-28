<?php extract($viewData, EXTR_SKIP); ?>

<div class="hr-tabs module-tabs">
    <button type="button" class="hr-tab module-tab active" onclick="switchTab('employees')"><?= t('hr.tab_employees') ?><span class="tab-badge module-tab-badge module-tab-badge--ok"><?= count($employees) ?></span></button>
    <button type="button" class="hr-tab module-tab" onclick="switchTab('candidates')"><?= t('hr.tab_candidates') ?><?php if (!empty($staffCandidates)): ?><span class="tab-badge module-tab-badge module-tab-badge--gold"><?= count($staffCandidates) ?></span><?php endif ?></button>
    <button type="button" class="hr-tab module-tab" onclick="switchTab('directors')"><?= t('hr.tab_directors') ?><span class="tab-badge module-tab-badge module-tab-badge--muted"><?= count($directors ?? []) ?></span></button>
    <button type="button" class="hr-tab module-tab" onclick="switchTab('contracts')"><?= t('hr.tab_contracts') ?><?php if (!empty($expiring)): ?><span class="tab-badge module-tab-badge module-tab-badge--warn"><?= count($expiring) ?></span><?php endif ?></button>
    <button type="button" class="hr-tab module-tab" onclick="switchTab('history')"><?= t('hr.tab_history') ?><span class="tab-badge module-tab-badge module-tab-badge--muted"><?= count($history) ?></span></button>
    <button type="button" class="hr-tab module-tab" onclick="switchTab('market')"><?= t('hr.tab_market') ?></button>
    <button type="button" class="hr-tab module-tab" onclick="switchTab('headhunter')"><?= t('hr.tab_headhunter') ?><?php if (!empty($hhCandidates)): ?><span class="tab-badge module-tab-badge module-tab-badge--gold"><?= count($hhCandidates) ?></span><?php endif ?></button>
</div>

<div class="hr-container">

<div id="tab-employees" class="hr-tab-content active">
    <div class="hr-section-header">
        <h2><?= t('hr.employees_title') ?></h2>
        <p><?= t('hr.employees_desc') ?></p>
    </div>
    <?php if (empty($employees)): ?>
        <div class="hr-empty hr-empty--big">
            <div class="hr-empty-icon">&#128101;</div>
            <p><?= t('hr.no_employees') ?></p>
        </div>
    <?php else: ?>
    <div class="employees-grid">
        <?php foreach ($employees as $emp):
            $expLevel = $emp['experience_years'] <= 5 ? 'Junior' : ($emp['experience_years'] <= 12 ? 'Mid' : 'Senior');
            $avg = round(($emp['skill_organization'] + $emp['skill_negotiation'] + $emp['skill_analysis'] + $emp['skill_stress'] + $emp['skill_ethics']) / 5, 1);
            $warn = isset($emp['contract_days_left']) && $emp['contract_days_left'] <= 14 && $emp['contract_days_left'] >= 0;
            $age = !empty($emp['birth_date']) ? date_diff(date_create($emp['birth_date']), date_create('today'))->y : null;
            $safeName = htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'], ENT_QUOTES);
            $empDomId = ($emp['source'] ?? 'board_member') . '-' . (int)$emp['id'];
        ?>
        <div class="employee-card <?= $warn ? 'contract-warning' : '' ?>" onclick="toggleEmployeeDetails('<?= $empDomId ?>')">
            <div class="emp-header">
                <div class="emp-avatar"><?= ($emp['gender'] ?? 'M') === 'F' ? '&#128105;' : '&#128104;' ?></div>
                <div class="emp-info">
                    <div class="emp-name"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                    <div class="emp-role"><?= htmlspecialchars($emp['role_name']) ?></div>
                    <div class="emp-meta">
                        <?= $age !== null ? $age . ' ' . t('hr.years_age') . ' � ' : '' ?><?= $emp['experience_years'] ?><?= t('hr.years_exp') ?>&nbsp;�&nbsp;
                        <span class="exp-badge exp-<?= strtolower($expLevel) ?>"><?= $expLevel ?></span>
                        &nbsp;�&nbsp; <?= htmlspecialchars($emp['nationality'] ?? '') ?>
                    </div>
                </div>
                <div class="emp-salary-block">
                    <div class="emp-salary"><?= number_format((float)$emp['salary'], 0, ',', ' ') ?> PLN</div>
                    <div class="emp-salary-label"><?= t('hr.salary_month') ?></div>
                    <?php if ($warn): ?><div class="emp-contract-warn">&#9888; <?= $emp['contract_days_left'] ?> <?= t('common.days') ?></div><?php endif ?>
                </div>
            </div>

            <div class="emp-details" id="emp-details-<?= $empDomId ?>">
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
                    <button type="button" class="btn btn-sm btn-primary" onclick="event.stopPropagation();renewContract(<?= $emp['id'] ?>,'<?= $safeName ?>')"><?= t('hr.btn_renew') ?></button>
                    <?php endif ?>
                    <?php if (($emp['source'] ?? 'board_member') === 'technical_staff'): ?>
                    <button type="button" class="btn btn-sm btn-danger" onclick="event.stopPropagation();fireTechnicalStaff(<?= $emp['id'] ?>,'<?= $safeName ?>')"><?= t('hr.btn_fire') ?></button>
                    <?php else: ?>
                    <button type="button" class="btn btn-sm btn-danger" onclick="event.stopPropagation();fireEmployee(<?= $emp['id'] ?>,'<?= $safeName ?>')"><?= t('hr.btn_fire') ?></button>
                    <?php endif ?>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-candidates" class="hr-tab-content">
    <div class="hr-section-header">
        <h2><?= t('hr.candidates_title') ?></h2>
        <p><?= t('hr.candidates_desc') ?></p>
    </div>

    <?php if (empty($staffCandidates)): ?>
        <div class="hr-empty hr-empty--big">
            <div class="hr-empty-icon">&#128196;</div>
            <p><?= t('hr.no_candidates') ?></p>
        </div>
    <?php else: ?>
    <div class="candidates-grid">
        <?php foreach ($staffCandidates as $candidate):
            $avg = round(($candidate['skill_organization'] + $candidate['skill_negotiation'] + $candidate['skill_analysis'] + $candidate['skill_stress'] + $candidate['skill_ethics']) / 5, 1);
            $expLevel = $candidate['experience_years'] <= 5 ? 'Junior' : ($candidate['experience_years'] <= 12 ? 'Mid' : 'Senior');
            $hoursLeft = max(0, (int)$candidate['hours_remaining']);
            $safeName = htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name'], ENT_QUOTES);
            $isRecommended = ($candidate['tech_recommendation'] ?? '') === 'hire';
            $isRejected = ($candidate['tech_recommendation'] ?? '') === 'reject';
        ?>
        <div class="candidate-card-hr <?= $isRecommended ? 'hr-recommended' : '' ?>">
            <?php if ($isRecommended): ?><div class="hr-rec-badge"><?= t('hr.rec_badge') ?></div><?php endif ?>

            <div class="cand-header">
                <div>
                    <div class="cand-name"><?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?></div>
                    <div class="cand-meta">
                        <?= htmlspecialchars($candidate['spec_name'] ?? $candidate['role_name']) ?>
                        &nbsp;�&nbsp;<?= (int)$candidate['age'] ?> <?= t('hr.years_age') ?>
                        &nbsp;�&nbsp;<?= htmlspecialchars($candidate['nationality'] ?? '') ?>
                        &nbsp;�&nbsp;<span class="exp-badge exp-<?= strtolower($expLevel) ?>"><?= $expLevel ?> (<?= (int)$candidate['experience_years'] ?> <?= t('hr.years_exp_short') ?>)</span>
                    </div>
                </div>
                <div class="cand-salary">
                    <?= number_format((float)$candidate['expected_salary'], 0, ',', ' ') ?> PLN
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
                <span class="tech-rev-icon"><?= $isRecommended ? '&#10003;' : ($isRejected ? '&#10007;' : '&#9679;') ?></span>
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
                    <span class="cand-expires <?= $hoursLeft < 12 ? 'cand-expires--urgent' : '' ?>">&#8634; <?= $hoursLeft ?>h</span>
                    <?php if (!empty($candidate['region_name'])): ?><span class="cand-region-name"><?= htmlspecialchars($candidate['region_name']) ?></span><?php endif ?>
                </div>
                <div class="cand-footer-actions">
                    <select class="hr-select-small" id="contract-<?= (int)$candidate['id'] ?>">
                        <option value="1y"><?= t('hr.contract_1y') ?></option>
                        <option value="6m"><?= t('hr.contract_6m') ?></option>
                        <option value="2y"><?= t('hr.contract_2y') ?></option>
                    </select>
                    <button type="button" class="btn-cv btn-cv-reject" onclick="rejectCandidate(<?= (int)$candidate['id'] ?>, '<?= $safeName ?>')"><?= t('hr.btn_reject') ?></button>
                    <button type="button" class="btn-cv btn-cv-hire <?= $isRecommended ? 'btn-cv-hire-recommended' : '' ?>" onclick="hireCandidate(<?= (int)$candidate['id'] ?>, '<?= $safeName ?>')">
                        <?= $isRecommended ? t('hr.btn_hire_recommended') : t('hr.btn_hire') ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-directors" class="hr-tab-content">
    <div class="hr-section-header">
        <h2><?= t('hr.directors_title') ?></h2>
        <p><?= t('hr.directors_desc') ?></p>
    </div>

    <?php if (empty($directors)): ?>
        <div class="hr-empty hr-empty--big">
            <div class="hr-empty-icon">&#128081;</div>
            <p><?= t('hr.no_directors') ?></p>
        </div>
    <?php else: ?>
    <div class="employees-grid directors-grid">
        <?php foreach ($directors as $emp):
            $expLevel = $emp['experience_years'] <= 5 ? 'Junior' : ($emp['experience_years'] <= 12 ? 'Mid' : 'Senior');
            $avg = round(($emp['skill_organization'] + $emp['skill_negotiation'] + $emp['skill_analysis'] + $emp['skill_stress'] + $emp['skill_ethics']) / 5, 1);
            $age = (int)($emp['age'] ?? 0);
        ?>
        <div class="employee-card director-card" onclick="toggleEmployeeDetails('director-<?= (int)$emp['id'] ?>')">
            <div class="emp-header">
                <div class="emp-avatar"><?= ($emp['gender'] ?? 'M') === 'F' ? '&#128105;' : '&#128104;' ?></div>
                <div class="emp-info">
                    <div class="emp-name"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                    <div class="emp-role"><?= htmlspecialchars($emp['role_name']) ?></div>
                    <div class="emp-meta">
                        <?= $age ?> <?= t('hr.years_age') ?> &nbsp;�&nbsp; <?= $emp['experience_years'] ?><?= t('hr.years_exp') ?>&nbsp;�&nbsp;
                        <span class="exp-badge exp-<?= strtolower($expLevel) ?>"><?= $expLevel ?></span>
                        &nbsp;�&nbsp; <?= htmlspecialchars($emp['nationality'] ?? '') ?>
                    </div>
                </div>
                <div class="emp-salary-block">
                    <div class="emp-salary"><?= number_format((float)$emp['salary'], 0, ',', ' ') ?> PLN</div>
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
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-contracts" class="hr-tab-content">
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
            <div><?= number_format((float)$c['salary'], 0, ',', ' ') ?> PLN</div>
            <div>
                <?php if ($isDead): ?><span class="badge-expired"><?= t('hr.badge_expired') ?></span>
                <?php elseif ($isExp): ?><span class="badge-expiring">&#9888; <?= $c['days_left'] ?> <?= t('common.days') ?></span>
                <?php else: ?><span class="badge-active"><?= $c['days_left'] ?> <?= t('common.days') ?></span><?php endif ?>
            </div>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<div id="tab-history" class="hr-tab-content">
    <div class="hr-section-header">
        <h2><?= t('hr.history_title') ?></h2>
        <p><?= t('hr.history_desc') ?></p>
    </div>

    <?php if (empty($history)): ?>
        <div class="hr-empty hr-empty--big">
            <div class="hr-empty-icon">&#128221;</div>
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
                [$label, $cls] = $badges[$h['action']] ?? [$h['action'], 'action-hired'];
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

<div id="tab-market" class="hr-tab-content">
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

<div id="tab-headhunter" class="hr-tab-content">
    <div class="hr-section-header">
        <h2><?= t('hr.hh_title') ?></h2>
        <p><?= t('hr.hh_desc') ?></p>
    </div>

    <?php if ($hhActiveSearch): ?>
    <div class="hh-status-card hh-searching">
        <div class="hh-status-icon">&#128269;</div>
        <div class="hh-status-info">
            <div class="hh-status-title"><?= t('hr.hh_searching_for') ?>: <?= htmlspecialchars($hhActiveSearch['spec_name']) ?></div>
            <div class="hh-status-meta">
                <?= t('hr.hh_cost_label') ?>: <?= isset($hhActiveSearch['cost']) ? HeadhunterService::fmt((float)$hhActiveSearch['cost']) : t('hr.hh_cost_settled') ?>
                � <?= t('hr.hh_remaining') ?>: <span class="countdown" data-end="<?= strtotime($hhActiveSearch['finished_at']) ?>"></span>
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
        <button type="button" class="btn btn-primary btn-full" onclick="startHeadhunter()"><?= t('hr.hh_btn_launch') ?></button>
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
                <div class="hh-cand-company">&#127970; <?= htmlspecialchars($hc['current_company']) ?></div>
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
            <form class="hh-offer-form" onsubmit="makeHeadhunterOffer(event, <?= (int)$hc['id'] ?>)">
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
                    default => htmlspecialchars($sr['status']),
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
const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
const HR_API = '/src/HRApi.php';
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
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/hr.js"></script>
