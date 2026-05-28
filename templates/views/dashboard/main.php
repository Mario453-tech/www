<div class="db-container">

<?php if ($msg):   ?><div class="db-alert db-alert--ok"><span class="db-alert-icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M6.4 11.4 3.3 8.3l-1 1 4.1 4.1 7.3-7.3-1-1z"/></svg></span><span><?= htmlspecialchars($msg) ?></span></div><?php endif ?>
<?php if ($error): ?><div class="db-alert db-alert--err"><span class="db-alert-icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M4.7 3.6 8 6.9l3.3-3.3 1.1 1.1L9.1 8l3.3 3.3-1.1 1.1L8 9.1l-3.3 3.3-1.1-1.1L6.9 8 3.6 4.7z"/></svg></span><span><?= htmlspecialchars($error) ?></span></div><?php endif ?>

<?php if ($isBankrupt): ?>
<div class="db-bankruptcy-bar">
    <span class="db-alert-icon db-alert-icon--warn" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M8 1.5 15 14H1zm0 4.1c.4 0 .7.3.7.7v3.4a.7.7 0 0 1-1.4 0V6.3c0-.4.3-.7.7-.7zm0 6.1a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8z"/></svg></span><strong><?= t('dashboard.bankruptcy_bar_text') ?></strong>
    <a href="<?= url('recovery') ?>"><?= t('dashboard.bankruptcy_bar_link') ?></a>
</div>
<?php endif ?>

<div class="db-stats">
    <div class="db-stat">
        <div class="db-stat-val"><?= $occupiedSeats ?>/<?= $totalSeats ?></div>
        <div class="db-stat-lbl"><?= t('dashboard.stat_board') ?></div>
    </div>
    <div class="db-stat <?= $pendingCount > 0 ? 'db-stat--urgent' : '' ?>">
        <div class="db-stat-val"><?= $pendingCount ?></div>
        <div class="db-stat-lbl"><?= t('dashboard.stat_pending') ?></div>
    </div>
    <div class="db-stat <?= $openRoleCount > 0 ? 'db-stat--ok' : '' ?>">
        <div class="db-stat-val"><?= $openRoleCount ?></div>
        <div class="db-stat-lbl"><?= t('dashboard.stat_open_roles') ?></div>
    </div>
    <div class="db-stat">
        <div class="db-stat-val"><?= $directorRecruitmentCount ?></div>
        <div class="db-stat-lbl"><?= t('dashboard.stat_recruitments') ?></div>
    </div>
</div>

<section class="db-section" id="director-recruitment">
    <div class="db-section-hdr">
        <?= t('dashboard.section_recruitment_panel') ?>
        <span class="db-section-badge"><?= $directorRecruitmentCount ?>/2</span>
    </div>
    <p class="db-section-desc"><?= t('dashboard.section_recruitment_panel_desc') ?></p>

    <?php if ($isBankrupt): ?>
    <div class="db-empty db-empty--inline"><?= t('dashboard.recruitment_locked_bankruptcy') ?></div>
    <?php elseif ($directorRecruitmentCount >= 2): ?>
    <div class="db-empty db-empty--inline"><?= t('dashboard.err_max_recruitments') ?></div>
    <?php elseif (empty($availableDirectorRoles)): ?>
    <div class="db-empty db-empty--inline"><?= t('dashboard.recruitment_no_roles') ?></div>
    <?php else: ?>
    <form method="post" class="db-recruit-form">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="start_director_recruitment">
        <input type="hidden" name="role_id" id="director-role-id" value="<?= (int)($availableDirectorRoles[0]['id'] ?? 0) ?>">
        <input type="hidden" name="region_code" id="director-region-code" value="<?= htmlspecialchars((string)($regions[0]['code'] ?? 'PL')) ?>">
        <div class="db-form-grid">
            <div class="db-form-field db-form-field--full">
                <span class="db-form-label"><?= t('dashboard.field_role') ?></span>
                <div class="db-region-grid db-role-grid" id="director-role-grid" role="listbox" aria-label="<?= htmlspecialchars(t('dashboard.field_role')) ?>">
                    <?php foreach ($availableDirectorRoles as $role): ?>
                    <button type="button"
                            class="db-region-card db-role-card<?= ((int)($availableDirectorRoles[0]['id'] ?? 0) === (int)$role['id']) ? ' is-selected' : '' ?>"
                            data-role-id="<?= (int)$role['id'] ?>">
                        <span class="db-region-name"><?= htmlspecialchars($role['name']) ?></span>
                    </button>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="db-form-field db-form-field--full">
                <span class="db-form-label"><?= t('dashboard.field_region') ?></span>
                <div class="db-region-grid" id="director-region-grid" role="listbox" aria-label="<?= htmlspecialchars(t('dashboard.field_region')) ?>">
                    <?php foreach ($regions as $region): ?>
                    <button type="button"
                            class="db-region-card<?= (($regions[0]['code'] ?? 'PL') === $region['code']) ? ' is-selected' : '' ?>"
                            data-region-code="<?= htmlspecialchars($region['code']) ?>">
                        <span class="db-region-name"><?= htmlspecialchars($region['name']) ?></span>
                        <span class="db-region-code"><?= htmlspecialchars($region['code']) ?></span>
                    </button>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-cta"><?= t('dashboard.btn_start_recruitment') ?></button>
    </form>
    <?php endif ?>
</section>

<section class="db-section">
    <div class="db-section-hdr">
        <?= t('dashboard.section_cv') ?>
        <span class="db-section-badge"><?= $pendingCount ?></span>
    </div>
    <p class="db-section-desc"><?= t('dashboard.section_cv_desc') ?></p>

    <?php if (empty($candidates)): ?>
    <div class="db-empty db-empty--inline"><?= t('dashboard.empty_candidates') ?></div>
    <?php else: ?>
    <?php foreach ($candidates as $c):
        $avg = ($c['skill_organization'] + $c['skill_negotiation'] +
                $c['skill_analysis']     + $c['skill_stress']      + $c['skill_ethics']) / 5;
        $hours = max(0, (int)$c['hours_remaining']);
        $urgentColor = $hours < 12 ? 'cv-bad' : ($hours < 24 ? 'cv-warn' : '');
    ?>
    <div class="db-cand-card">
        <div class="db-cand-header">
            <div>
                <div class="db-cand-name"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                <div class="db-cand-role"><?= htmlspecialchars($c['role_name']) ?></div>
            </div>
            <div class="db-cand-salary">
                <?= number_format((float)$c['expected_salary'], 0, ',', ' ') ?> <?= t('common.currency_month') ?>
            </div>
        </div>
        <div class="db-cand-meta">
            <span><?= t('dashboard.cand_age', ['age' => (int)$c['age']]) ?></span>
            <span><?= t('dashboard.cand_exp', ['years' => (int)$c['experience_years']]) ?></span>
            <span><?= t('dashboard.cand_avg', ['avg' => number_format($avg, 1)]) ?></span>
            <span class="<?= $urgentColor ?>"><?= t('dashboard.cand_hours', ['h' => $hours]) ?></span>
            <?php if (!empty($c['region_code'])): ?>
            <span><?= htmlspecialchars($c['region_code']) ?></span>
            <?php endif ?>
        </div>
        <div class="db-skills">
            <?php foreach ($skillLabels as $skillKey => $skillLabel): ?>
            <div class="db-skill">
                <div class="db-skill-lbl"><?= $skillLabel ?></div>
                <div class="db-skill-bar">
                    <div class="db-skill-fill" style="width:<?= $c[$skillKey] * 10 ?>%"></div>
                </div>
                <div class="db-skill-val"><?= $c[$skillKey] ?></div>
            </div>
            <?php endforeach ?>
        </div>
        <div class="db-cand-actions">
            <form method="post" class="db-inline-form db-inline-form--hire">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="hire_candidate">
                <input type="hidden" name="cand_id" value="<?= (int)$c['id'] ?>">
                <select name="contract_type" class="db-select db-select--compact">
                    <option value="1y"><?= t('hr.contract_1y') ?></option>
                    <option value="6m"><?= t('hr.contract_6m') ?></option>
                    <option value="2y"><?= t('hr.contract_2y') ?></option>
                </select>
                <button type="submit" class="btn btn-success"><?= t('dashboard.btn_hire') ?></button>
            </form>
            <form method="post" class="db-inline-form"
                  data-confirm="<?= htmlspecialchars(t('dashboard.confirm_reject'), ENT_QUOTES) ?>"
                  data-confirm-type="danger"
                  data-confirm-title="<?= htmlspecialchars(t('dashboard.btn_reject'), ENT_QUOTES) ?>"
                  data-confirm-label="<?= htmlspecialchars(t('dashboard.btn_reject'), ENT_QUOTES) ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="reject_candidate">
                <input type="hidden" name="cand_id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn btn-danger"><?= t('dashboard.btn_reject') ?></button>
            </form>
        </div>
    </div>
    <?php endforeach ?>
    <?php endif ?>
</section>

<section class="db-section">
    <div class="db-section-hdr">
        <?= t('dashboard.section_recruitments') ?>
        <span class="db-section-badge"><?= $directorRecruitmentCount ?></span>
    </div>
    <?php if (empty($activeRecruitments)): ?>
    <div class="db-empty db-empty--inline"><?= t('dashboard.recruitment_empty') ?></div>
    <?php else: ?>
    <div class="db-list">
    <?php foreach ($activeRecruitments as $r): ?>
    <div class="db-list-row">
        <div class="db-list-main">
            <span class="db-list-title"><?= htmlspecialchars($r['role_name']) ?></span>
            <?php if (!empty($r['region_code'])): ?><span class="db-list-role"><?= htmlspecialchars($r['region_code']) ?></span><?php endif ?>
            <?php if ($r['status'] === 'pending'): ?>
            <span class="db-badge db-badge--wait"><?= t('dashboard.recruitment_wait', ['min' => max(0, (int)ceil($r['seconds_remaining'] / 60))]) ?></span>
            <?php else: ?>
            <span class="db-badge db-badge--ok"><?= t('dashboard.recruitment_ready') ?></span>
            <?php endif ?>
        </div>
    </div>
    <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<section class="db-section">
    <div class="db-section-hdr">
        <?= t('dashboard.section_board', ['occupied' => $occupiedSeats, 'total' => $totalSeats]) ?>
    </div>

    <?php if (empty($boardMembers)): ?>
    <div class="db-empty">
        <?= t('dashboard.board_empty') ?>
        <a href="#director-recruitment"><?= t('dashboard.board_empty_link') ?></a>
    </div>
    <?php else: ?>
    <div class="db-list">
    <?php foreach ($boardMembers as $m): ?>
    <div class="db-list-row">
        <div class="db-list-main">
            <span class="db-list-title"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></span>
            <span class="db-list-role"><?= htmlspecialchars($m['role_name']) ?></span>
            <span class="db-list-meta">
                <?= t('dashboard.member_days', ['days' => (int)$m['days_employed']]) ?> &middot;
                <?= number_format((float)($m['salary'] ?? $m['contract_salary'] ?? 0), 0, ',', ' ') ?> <?= t('common.currency_month') ?>
            </span>
        </div>
        <?php if (!$isBankrupt): ?>
        <form method="post" class="form-inline"
              data-confirm="<?= htmlspecialchars(t('dashboard.confirm_fire', ['name' => $m['first_name']]), ENT_QUOTES) ?>"
              data-confirm-type="danger"
              data-confirm-title="<?= htmlspecialchars(t('dashboard.btn_fire'), ENT_QUOTES) ?>"
              data-confirm-label="<?= htmlspecialchars(t('dashboard.btn_fire'), ENT_QUOTES) ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="fire_employee">
            <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn btn-danger"><?= t('dashboard.btn_fire') ?></button>
        </form>
        <?php endif ?>
    </div>
    <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php if (!empty($recentHistory)): ?>
<section class="db-section">
    <div class="db-section-hdr">
        <?= t('dashboard.section_history') ?>
    </div>
    <div class="db-history">
    <?php foreach ($recentHistory as $e): ?>
    <div class="db-history-row">
        <span class="db-history-icon <?= $e['action'] === 'hired' ? 'db-history-icon--ok' : 'db-history-icon--err' ?>" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="<?= $e['action'] === 'hired' ? 'M6.4 11.4 3.3 8.3l-1 1 4.1 4.1 7.3-7.3-1-1z' : 'M4.7 3.6 8 6.9l3.3-3.3 1.1 1.1L9.1 8l3.3 3.3-1.1 1.1L8 9.1l-3.3 3.3-1.1-1.1L6.9 8 3.6 4.7z' ?>"/></svg></span>
        <span class="db-history-text">
            <?= $e['action'] === 'hired' ? t('dashboard.history_hired') : t('dashboard.history_fired') ?>:
            <strong><?= htmlspecialchars(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?></strong>
            <?php if ($e['role_name']): ?><em>(<?= htmlspecialchars($e['role_name']) ?>)</em><?php endif ?>
        </span>
        <span class="db-history-time"><?= date('d.m H:i', strtotime($e['created_at'])) ?></span>
    </div>
    <?php endforeach ?>
    </div>
</section>
<?php endif ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleInput = document.getElementById('director-role-id');
    const roleGrid = document.getElementById('director-role-grid');
    const regionInput = document.getElementById('director-region-code');
    const regionGrid = document.getElementById('director-region-grid');
    if (roleInput && roleGrid) {
        roleGrid.addEventListener('click', function (event) {
            const card = event.target.closest('.db-role-card');
            if (!card) {
                return;
            }
            roleInput.value = card.dataset.roleId || '';
            roleGrid.querySelectorAll('.db-role-card').forEach(function (node) {
                node.classList.toggle('is-selected', node === card);
            });
        });
    }

    if (!regionInput || !regionGrid) {
        return;
    }

    regionGrid.addEventListener('click', function (event) {
        const card = event.target.closest('.db-region-card');
        if (!card) {
            return;
        }
        regionInput.value = card.dataset.regionCode || 'PL';
        regionGrid.querySelectorAll('.db-region-card').forEach(function (node) {
            node.classList.toggle('is-selected', node === card);
        });
    });
});
</script>
