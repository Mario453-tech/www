<?php if (!empty($pendingRecruitments)): ?>
<div class="g-card rec-status-card">
    <div class="g-card-title"><?= t('technical.recruitment_status_title') ?></div>
    <div class="rec-status-list">
        <?php foreach ($pendingRecruitments as $pendingRecruitment): ?>
        <?php $secsLeft = max(0, (int)($pendingRecruitment['seconds_remaining'] ?? 0)); ?>
        <div class="rec-status-bar rec-pending rec-status-bar--split">
            <div class="rec-status-main">
                <div class="rec-status-icon">...</div>
                <div class="rec-status-info">
                    <div class="rec-status-title"><?= t('technical.rec_pending_title') ?></div>
                    <div class="rec-status-meta">
                        <?= t('technical.rec_initiated_by') ?>
                        <?php if ($secsLeft > 0): ?>
                        &middot; <?= t('technical.rec_remaining') ?>:
                        <span class="countdown" data-end="<?= strtotime($pendingRecruitment['ready_at']) ?>"></span>
                        <?php endif ?>
                    </div>
                </div>
            </div>
            <form method="post" class="rec-cancel-form">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="cancel_recruitment">
                <input type="hidden" name="request_id" value="<?= (int)$pendingRecruitment['id'] ?>">
                <button
                    type="button"
                    class="btn btn-secondary btn-sm rec-cancel-btn"
                    onclick="confirmSubmit(this, '<?= htmlspecialchars(t('technical.confirm_cancel_recruitment'), ENT_QUOTES, 'UTF-8') ?>', { title: '<?= htmlspecialchars(t('technical.cancel_recruitment_title'), ENT_QUOTES, 'UTF-8') ?>', type: 'danger', confirmLabel: '<?= htmlspecialchars(t('technical.btn_cancel_recruitment_confirm'), ENT_QUOTES, 'UTF-8') ?>', bodyHtml: '<p><?= htmlspecialchars(t('technical.confirm_cancel_recruitment'), ENT_QUOTES, 'UTF-8') ?></p><p style=&quot;margin-top:8px;opacity:.8;font-size:.92rem;&quot;><?= htmlspecialchars(t('technical.cancel_recruitment_hint'), ENT_QUOTES, 'UTF-8') ?></p>' }); return false;"
                ><?= t('technical.btn_cancel_recruitment') ?></button>
            </form>
        </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<div class="g-card">
    <div class="g-card-title"><?= t('technical.spec_needs_title') ?></div>

    <div class="specs-need-grid">
    <?php foreach ($specRecruitmentCards as $card): ?>
    <div class="spec-need-card <?= $card['card_class'] ?>">
        <div class="spec-need-icon"><?= $card['icon'] ?></div>
        <div class="spec-need-name"><?= $card['name'] ?></div>
        <div class="spec-need-count">
            <span class="<?= $card['count_class'] ?>"><?= $card['count_text'] ?></span>
        </div>
        <?php if ($manager && $card['remaining_slots'] > 0): ?>
        <form method="post" class="spec-need-form">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="request_recruitment">
            <input type="hidden" name="spec_code" value="<?= $card['spec_code'] ?>">
            <div class="spec-need-row">
                <select name="region_code" class="form-input spec-need-region">
                    <option value="PL"><?= t('technical.region_pl') ?></option>
                    <option value="EU"><?= t('technical.region_eu') ?></option>
                    <option value="NO"><?= t('technical.region_no') ?></option>
                    <option value="US_CA"><?= t('technical.region_usca') ?></option>
                    <option value="ME"><?= t('technical.region_me') ?></option>
                    <option value="RU"><?= t('technical.region_ru') ?></option>
                    <option value="ASIA"><?= t('technical.region_asia') ?></option>
                </select>
                <select name="count" class="form-input spec-need-count" title="<?= t('technical.rec_count_title') ?>">
                    <?php foreach ($card['count_options'] as $countOption): ?>
                    <option value="<?= $countOption ?>">&times; <?= $countOption ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <input type="hidden" name="recruitment_type" id="rec-type-<?= $card['spec_code'] ?>" value="local">
            <button
                type="submit"
                class="btn btn-primary btn-sm"
                onclick="
                    var r=this.form.querySelector('[name=recruitment_type]');
                    var v=this.form.querySelector('[name=region_code]').value;
                    var intl=['NO','US_CA','ME','ASIA'];
                    r.value=intl.includes(v)?'international':'local';
                    return confirmSubmit(this, '<?= htmlspecialchars(t('technical.recruit_confirm'), ENT_QUOTES, 'UTF-8') ?>', { title: '<?= htmlspecialchars(t('technical.recruit_confirm_title'), ENT_QUOTES, 'UTF-8') ?>', confirmLabel: '<?= htmlspecialchars(t('technical.btn_recruit'), ENT_QUOTES, 'UTF-8') ?>' });
                "
            ><?= t('technical.btn_recruit') ?></button>
        </form>
        <?php elseif (!$manager): ?>
        <div class="sm-label c-muted2 mt-sm"><?= t('technical.no_manager_recruit') ?></div>
        <?php endif ?>
    </div>
    <?php endforeach ?>
    </div>
</div>

<?php if (empty($candidates)): ?>
<div class="empty-state"><?= t('technical.no_candidates') ?></div>
<?php else: ?>
<div class="section-h"><?= t('technical.candidates_section', ['count' => count($candidates)]) ?></div>

<?php foreach ($candidates as $candidate):
    $reviewed = $candidate['review_id'] !== null;
    $recommendedHire = $candidate['review_recommendation'] === 'hire';
    $hoursLeft = (int)$candidate['hours_remaining'];
    $expiryClass = $hoursLeft < 24 ? 'c-bad' : ($hoursLeft < 72 ? 'c-warn' : 'c-muted2');
    $specName = $candidate['spec_name'] ?? t('technical.default_spec_name');
?>
<div class="cand-card <?= $reviewed ? ($recommendedHire ? 'cand-hire' : 'cand-reject') : '' ?>">
    <div class="cand-hdr">
        <div class="cand-identity">
            <div class="cand-name"><?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?></div>
            <div class="cand-spec"><?= htmlspecialchars($specName) ?></div>
            <div class="cand-meta">
                <?= htmlspecialchars($candidate['nationality'] ?? '') ?> &middot;
                <?= $candidate['age'] ?> <?= t('hr.years_age') ?> &middot;
                <?= $candidate['experience_years'] ?> <?= t('technical.exp_years') ?>
            </div>
        </div>
        <div class="cand-badges">
            <?php if ($reviewed): ?>
            <span class="badge <?= $recommendedHire ? 'b-active' : 'b-broken' ?>"><?= $recommendedHire ? t('technical.rec_hire') : t('technical.rec_reject') ?></span>
            <?php else: ?>
            <span class="badge b-paused"><?= t('technical.awaiting_review') ?></span>
            <?php endif ?>
            <div class="<?= $expiryClass ?> fs12"><?= t('technical.expires_in', ['h' => $hoursLeft]) ?></div>
        </div>
    </div>

    <div class="cand-stats">
        <div class="cand-stat"><div class="cand-stat-lbl"><?= t('technical.stat_exp') ?></div><div class="cand-stat-val c-gold"><?= $candidate['experience_years'] ?> <?= t('hr.years_age') ?></div></div>
        <div class="cand-stat"><div class="cand-stat-lbl"><?= t('hr.skill_analysis') ?></div><div class="cand-stat-val <?= $candidate['skill_analysis'] >= 7 ? 'c-green' : ($candidate['skill_analysis'] >= 5 ? 'c-warn' : 'c-bad') ?>"><?= $candidate['skill_analysis'] ?>/10</div></div>
        <div class="cand-stat"><div class="cand-stat-lbl"><?= t('hr.skill_organization') ?></div><div class="cand-stat-val"><?= $candidate['skill_organization'] ?>/10</div></div>
        <div class="cand-stat"><div class="cand-stat-lbl"><?= t('hr.skill_stress') ?></div><div class="cand-stat-val"><?= $candidate['skill_stress'] ?>/10</div></div>
        <div class="cand-stat"><div class="cand-stat-lbl"><?= t('technical.stat_salary_exp') ?></div><div class="cand-stat-val c-gold"><?= number_format($candidate['expected_salary'], 0, '.', ' ') ?> <?= t('common.currency') ?>/<?= t('hr.month_short') ?></div></div>
    </div>

    <?php if ($reviewed): ?>
    <div class="cand-prev-review">
        <span class="cand-prev-score"><?= t('technical.tech_score_label') ?>: <strong><?= $candidate['technical_score'] ?>/10</strong></span>
        <?php if ($candidate['review_comment']): ?>
        <span class="cand-prev-comment">"<?= htmlspecialchars($candidate['review_comment']) ?>"</span>
        <?php endif ?>
    </div>
    <?php endif ?>

    <?php if (!$manager): ?>
    <div class="sm-label c-muted2 mt-sm"><?= t('technical.no_manager_review') ?></div>
    <?php else: ?>
    <details <?= !$reviewed ? 'open' : '' ?>>
        <summary class="task-assign-toggle"><?= $reviewed ? t('technical.change_review') : t('technical.give_review') ?></summary>
        <form method="post" class="cand-review-form" onsubmit="return candReviewConfirm(this)">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="review_candidate">
            <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
            <div class="cand-review-grid">
                <div class="form-group form-group--flush">
                    <label class="form-label"><?= t('technical.score_label') ?></label>
                    <div class="score-buttons">
                        <?php for ($score = 1; $score <= 10; $score++): ?>
                        <label class="score-btn <?= ($reviewed && $candidate['technical_score'] == $score) ? 'selected' : '' ?>">
                            <input
                                type="radio"
                                name="technical_score"
                                value="<?= $score ?>"
                                <?= ($reviewed && $candidate['technical_score'] == $score) ? 'checked' : '' ?>
                                required
                            >
                            <?= $score ?>
                        </label>
                        <?php endfor ?>
                    </div>
                </div>
                <div class="form-group form-group--flush">
                    <label class="form-label"><?= t('technical.recommendation_label') ?></label>
                    <div class="rec-buttons">
                        <label class="rec-btn rec-hire <?= ($reviewed && $recommendedHire) ? 'selected' : '' ?>">
                            <input type="radio" name="recommendation" value="hire" <?= ($reviewed && $recommendedHire) ? 'checked' : '' ?> required>
                            <?= t('technical.rec_ok_short') ?> <?= t('technical.rec_hire') ?>
                        </label>
                        <label class="rec-btn rec-reject <?= ($reviewed && !$recommendedHire) ? 'selected' : '' ?>">
                            <input type="radio" name="recommendation" value="reject" <?= ($reviewed && !$recommendedHire) ? 'checked' : '' ?> required>
                            &times; <?= t('technical.rec_reject') ?>
                        </label>
                    </div>
                </div>
                <div class="form-group form-group--flush">
                    <label class="form-label"><?= t('technical.comment_label') ?></label>
                    <textarea
                        name="comment"
                        class="form-input cand-comment"
                        placeholder="<?= t('technical.comment_placeholder') ?>"
                    ><?= htmlspecialchars($candidate['review_comment'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><?= t('technical.btn_save_review') ?></button>
            </div>
        </form>
    </details>
    <?php endif ?>
</div>
<?php endforeach ?>
<?php endif ?>
