<?php extract($viewData, EXTR_SKIP); ?>

<div class="fade-in">
    <section class="card">
        <h2><?= html_entity_decode(strip_tags(tPlain('bank.title')), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= t('bank.subtitle') ?></p>
    </section>

    <?php if (!$bankService): ?>
    <section class="card">
        <div class="info-box info-box-red">
            <?= t('bank.service_unavailable') ?>
        </div>
    </section>
    <?php endif ?>

    <?php if ($isBankrupt): ?>
    <section class="card">
        <div class="info-box info-box-yellow">
            <?= t('bank.bankruptcy_notice', ['url' => url('recovery')]) ?>
        </div>
    </section>
    <?php endif ?>

    <?php if (!$isBankrupt && ($isCrisis ?? false)): ?>
    <section class="card">
        <div class="info-box info-box-red">
            <?= t('bank.crisis_notice', ['url' => url('recovery')]) ?>
        </div>
    </section>
    <?php endif ?>

    <?php if (!empty($error)): ?>
    <div class="bank-flash bank-flash--error">
        <span class="bank-flash-icon"></span>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif ?>

    <?php if (!empty($success)): ?>
    <div class="bank-flash bank-flash--success">
        <span class="bank-flash-icon"></span>
        <span><?= $success ?></span>
    </div>
    <?php endif ?>

    <!-- Wniosek kredytowy -->

    <?php if ($bankService && $appSt === 'pending'): ?>
    <section class="card">
        <h2><?= t('bank.application_pending') ?></h2>
        <p><?= htmlspecialchars($applicationStatus['message'] ?? '') ?></p>
        <p><small><?= t('bank.application_pending_desc') ?></small></p>
    </section>

    <?php elseif ($bankService && $appSt === 'approved'): ?>
    <section class="card">
        <h2><?= t('bank.offer_title') ?></h2>
        <div class="offer-details">
            <?php if ($offerReduced): ?>
            <div class="offer-item offer-item--alert">
                <span class="offer-alert-icon"></span>
                <span class="offer-alert-text"><?= t('bank.offer_reduced') ?></span>
            </div>
            <?php endif ?>
            <div class="offer-item">
                <span class="offer-label"><?= t('bank.offer_amount') ?></span>
                <strong class="money"><?= number_format((float)($offer['amount'] ?? 0)) ?> PLN</strong>
            </div>
            <div class="offer-item">
                <span class="offer-label"><?= t('bank.offer_rate') ?></span>
                <span><?= htmlspecialchars((string)($offer['interest_rate'] ?? '')) ?>%</span>
            </div>
            <div class="offer-item">
                <span class="offer-label"><?= t('bank.offer_installment') ?></span>
                <strong class="money"><?= number_format((float)($offer['installment_amount'] ?? 0)) ?> PLN</strong>
            </div>
            <div class="offer-item">
                <span class="offer-label"><?= t('bank.offer_total_cost') ?></span>
                <span class="money"><?= number_format((float)($offer['estimated_total_cost'] ?? 0)) ?> PLN</span>
            </div>
            <div class="offer-item offer-item--warning">
                <span class="offer-alert-icon"></span>
                <span class="offer-alert-text"><?= t('bank.offer_daily_interest', ['amount' => number_format((float)($offer['daily_interest_cost'] ?? 0))]) ?></span>
            </div>
            <?php if (!empty($offer['reason'])): ?>
            <div class="offer-item offer-item--note">
                <span class="offer-alert-icon"></span>
                <span class="offer-alert-text"><?= htmlspecialchars($offer['reason']) ?></span>
            </div>
            <?php endif ?>
        </div>
        <div class="offer-actions">
            <form method="post">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="accept_offer">
                <input type="hidden" name="application_id" value="<?= (int)($applicationStatus['application']['id'] ?? 0) ?>">
                <button type="submit" class="btn btn-success btn-full"><?= t('bank.offer_accept') ?></button>
            </form>
            <form method="post"
                  data-confirm="<?= htmlspecialchars(t('bank.offer_reject_confirm'), ENT_QUOTES) ?>"
                  data-confirm-title="<?= htmlspecialchars(t('bank.offer_reject'), ENT_QUOTES) ?>"
                  data-confirm-type="danger"
                  data-confirm-label="<?= htmlspecialchars(t('bank.offer_reject'), ENT_QUOTES) ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="reject_offer">
                <input type="hidden" name="application_id" value="<?= (int)($applicationStatus['application']['id'] ?? 0) ?>">
                <button type="submit" class="btn btn-danger btn-full"><?= t('bank.offer_reject') ?></button>
            </form>
        </div>
    </section>

    <?php elseif ($bankService && $appSt === 'rejected' && !($applicationStatus['can_reapply'] ?? true)): ?>
    <section class="card">
        <h2><?= t('bank.rejected_title') ?></h2>
        <p><?= htmlspecialchars($applicationStatus['reason'] ?? '') ?></p>
        <div class="info-box info-box-yellow">
            <?= t('bank.rejected_reapply', ['hours' => (int)($applicationStatus['hours_until_reapply'] ?? 0)]) ?>
        </div>
    </section>

    <?php elseif (!empty($blockReasons) && !$blockHasActiveLoan): ?>
    <section class="card">
        <h2><?= t('bank.blocked_title') ?></h2>
        <p><?= t('bank.blocked_desc') ?></p>
        <ul class="block-reasons-list">
            <?php foreach ($blockReasons as $reason): ?>
            <li><?= htmlspecialchars($reason) ?></li>
            <?php endforeach ?>
        </ul>
    </section>

    <?php elseif ($canApply): ?>
    <section class="card" aria-labelledby="application-heading">
        <h2 id="application-heading"><?= t('bank.apply_title') ?></h2>
        <?php if (empty($hasEverHadLoan)): ?>
        <div class="info-box info-box-blue">
            <h3><?= t('bank.apply_requirements') ?></h3>
            <ul>
                <li><?= t('bank.apply_req_activity') ?></li>
                <li><?= t('bank.apply_req_well') ?></li>
                <li><?= t('bank.apply_req_bailiff') ?></li>
                <li><?= t('bank.apply_req_loans') ?></li>
            </ul>
        </div>
        <?php endif ?>
        <?php if ($creditLimit > 0): ?>
        <div class="info-box info-box-green">
            <strong><?= t('bank.apply_limit_label') ?></strong>
            <span class="money"><?= number_format($creditLimit, 0, '.', ' ') ?> PLN</span>
            <small><?= t('bank.apply_limit_desc') ?></small>
        </div>
        <?php endif ?>
        <form method="post" class="form-grid">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="submit_application">
            <div class="form-group">
                <label for="amount"><?= t('bank.apply_amount_label') ?></label>
                <input type="number" id="amount" name="amount"
                       min="10000"
                       max="<?= $creditLimit > 0 ? $creditLimit : 150000000 ?>"
                       step="1000"
                       value="<?= $creditLimit > 0 ? min(50000, $creditLimit) : 50000 ?>"
                       required>
                <small>
                    <?= t('bank.apply_amount_min') ?>
                    <?php if ($creditLimit > 0): ?>
                    <?= t('bank.apply_amount_max', ['limit' => number_format($creditLimit, 0, '.', ' ')]) ?>
                    <?php else: ?>
                    <?= t('bank.apply_amount_auto') ?>
                    <?php endif ?>
                </small>
            </div>
            <button type="submit" class="btn btn-primary btn-full"><?= t('bank.apply_submit') ?></button>
        </form>
    </section>
    <?php endif ?>

    <!-- Aktywne kredyty -->

    <?php if (!empty($activeLoans)): ?>
    <section class="card" aria-labelledby="loans-heading">
        <h2 id="loans-heading"><?= t('bank.loans_title') ?></h2>

        <?php foreach ($activeLoans as $loan):
            $loanId      = (int)$loan['id'];
            $nd          = $negData[$loanId] ?? [];
            $active      = $nd['active']          ?? null;
            $can         = $nd['can']             ?? ['can_negotiate' => false, 'reason' => ''];
            $events      = $nd['events']          ?? [];
            $hasBailiff  = $nd['hasBailiff']      ?? false;
            $restruct    = $nd['restructDisplay'] ?? null;
            $canRecovery = ($loan['status'] === 'late') || $hasBailiff;
            $pct         = $nd['pct']             ?? 0;
        ?>
        <div class="loan-card <?= $loan['status'] === 'late' ? 'loan-late' : '' ?>">

            <div class="loan-header">
                <span class="loan-id"><?= t('bank.loan_id', ['id' => $loanId]) ?></span>
                <?= $loanStatusBadge($loan['status']) ?>
            </div>

            <div class="loan-body-row">

                <div class="loan-body-main">
                    <div class="loan-details">
                        <div class="loan-item">
                            <span><?= t('bank.loan_remaining') ?></span>
                            <strong class="money"><?= number_format((float)($loan['remaining_amount'] ?? 0)) ?> PLN</strong>
                        </div>
                        <div class="loan-item">
                            <span><?= t('bank.loan_principal') ?></span>
                            <span class="money"><?= number_format((float)($loan['principal_amount'] ?? 0)) ?> PLN</span>
                        </div>
                        <div class="loan-item">
                            <span><?= t('bank.loan_rate') ?></span>
                            <span><?= htmlspecialchars((string)($loan['interest_rate'] ?? '')) ?>% APR</span>
                        </div>
                        <div class="loan-item">
                            <span><?= t('bank.loan_next_installment') ?></span>
                            <span><?= htmlspecialchars($loan['next_installment_fmt'] ?? '�') ?></span>
                        </div>
                        <div class="loan-item">
                            <span><?= t('bank.loan_installment_amount') ?></span>
                            <strong class="money"><?= number_format((float)($loan['installment_amount'] ?? 0)) ?> PLN</strong>
                        </div>
                    </div>

                    <div class="repay-bar-wrap">
                        <div class="repay-bar-label"><?= t('bank.loan_repaid_pct', ['pct' => $pct]) ?></div>
                        <div class="repay-bar">
                            <div class="repay-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>

                    <?php if ($loan['status'] === 'late'): ?>
                    <div class="info-box info-box-red">
                        <?= t('bank.loan_late_warning') ?>
                    </div>
                    <?php endif ?>
                </div>

                <?php if ($restruct): ?>
                <div class="loan-restruct-panel <?= $restruct['isActive'] ? 'restruct-active' : 'restruct-done' ?>">
                    <div class="restruct-title"><?= t('bank.restruct_title') ?></div>
                    <?php if ($restruct['isActive']): ?>
                        <div class="restruct-countdown">
                            <span class="restruct-days"><?= $restruct['daysLeft'] ?></span>
                            <span class="restruct-days-label"><?= t('bank.restruct_days_left') ?></span>
                        </div>
                        <div class="restruct-meta"><?= t('bank.restruct_until', ['date' => $restruct['expiresAt']]) ?></div>
                        <?php if ($restruct['totalDays']): ?>
                        <div class="restruct-meta"><?= t('bank.restruct_total_days', ['days' => $restruct['totalDays']]) ?></div>
                        <?php endif ?>
                        <?php if ($restruct['months'] > 0): ?>
                        <div class="restruct-meta"><?= t('bank.restruct_extended', ['months' => $restruct['months']]) ?></div>
                        <?php endif ?>
                    <?php else: ?>
                        <div class="restruct-done-label"><?= t('bank.restruct_done') ?></div>
                        <div class="restruct-meta"><?= $restruct['expiresAt'] ?></div>
                        <?php if ($restruct['months'] > 0): ?>
                        <div class="restruct-meta"><?= t('bank.restruct_extended', ['months' => $restruct['months']]) ?></div>
                        <?php endif ?>
                    <?php endif ?>
                </div>
                <?php endif ?>

            </div>

            <!-- Sp�ata -->
            <div class="repay-block">
                <h3 class="repay-block-title"><?= t('bank.repay_title') ?></h3>
                <form method="post" class="repay-form" id="repay-form-<?= $loanId ?>"
                      onsubmit="return repayConfirm(event, <?= $loanId ?>, <?= (float)($loan['installment_amount'] ?? 0) ?>, <?= (float)($loan['remaining_amount'] ?? 0) ?>)">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"  value="repay_loan">
                    <input type="hidden" name="loan_id" value="<?= $loanId ?>">
                    <div class="repay-options">
                        <label class="repay-option">
                            <input type="radio" name="repay_mode" value="single" checked>
                            <span>
                                <?= t('bank.repay_single') ?>
                                <small><?= number_format((float)($loan['installment_amount'] ?? 0)) ?> PLN</small>
                            </span>
                        </label>
                        <label class="repay-option" id="repay-multi-label-<?= $loanId ?>">
                            <input type="radio" name="repay_mode" value="multiple"
                                   id="repay-multi-radio-<?= $loanId ?>">
                            <span>
                                <?= t('bank.repay_multiple') ?>
                                <small>
                                    <select name="repay_count"
                                            id="repay-count-<?= $loanId ?>"
                                            data-installment="<?= (float)($loan['installment_amount'] ?? 0) ?>"
                                            data-label="repay-multi-amount-<?= $loanId ?>"
                                            onchange="repayUpdateMulti(this)"
                                            onclick="document.getElementById('repay-multi-radio-<?= $loanId ?>').checked=true">
                                        <?php foreach ([2,3,5,10] as $n): ?>
                                        <option value="<?= $n ?>"><?= $n ?> rat</option>
                                        <?php endforeach ?>
                                    </select>
                                </small>
                                <small id="repay-multi-amount-<?= $loanId ?>" class="repay-multi-amount">
                                    = <?= number_format((float)($loan['installment_amount'] ?? 0) * 2) ?> PLN
                                </small>
                            </span>
                        </label>
                        <label class="repay-option">
                            <input type="radio" name="repay_mode" value="full">
                            <span>
                                <?= t('bank.repay_full') ?>
                                <small class="c-good"><?= number_format((float)($loan['remaining_amount'] ?? 0)) ?> PLN</small>
                            </span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success"><?= t('bank.repay_submit') ?></button>
                </form>
            </div>

            <!-- Negocjacje � tylko gdy kredyt late lub jest aktywna negocjacja -->
            <?php
            $showNeg = $bankNeg && (
                $loan['status'] === 'late'
                || $active !== null
                || ($nd['hasBailiff'] ?? false)
            );
            ?>
            <?php if ($showNeg): ?>
            <div class="neg-block">
                <h3 class="neg-title"><?= t('bank.neg_title') ?></h3>

                <?php if ($active): ?>
                <div class="neg-active neg-status-<?= htmlspecialchars($active['status']) ?>">
                    <div class="neg-active-header">
                        <span class="neg-type-label"><?= $negTypeLabel($active['type']) ?></span>
                        <?= $negStatusBadge($active['status']) ?>
                    </div>

                    <?php if (!empty($active['bank_decision'])): ?>
                    <div class="neg-bank-msg">
                        <strong><?= t('bank.neg_bank_decision') ?></strong>
                        <?= htmlspecialchars($active['bank_decision']) ?>
                    </div>
                    <?php endif ?>

                    <?php if ($active['status'] === 'pending' && !empty($active['decision_due_at'])): ?>
                    <div class="neg-due">
                        <?= t('bank.neg_due', ['date' => htmlspecialchars($active['decision_due_at_fmt'] ?? '�')]) ?>
                    </div>
                    <?php endif ?>

                    <?php if ($active['status'] === 'approved'): ?>
                    <div class="neg-offer-details">
                        <?php if (!empty($active['approved_deferral_days'])): ?>
                        <div class="neg-offer-item">
                            <span><?= t('bank.neg_deferral_days') ?></span>
                            <strong><?= (int)$active['approved_deferral_days'] ?> <?= t('common.days') ?></strong>
                        </div>
                        <?php endif ?>
                        <?php if (!empty($active['approved_extension_months'])): ?>
                        <div class="neg-offer-item">
                            <span><?= t('bank.neg_extension_months') ?></span>
                            <strong><?= (int)$active['approved_extension_months'] ?> <?= t('common.months') ?></strong>
                        </div>
                        <?php endif ?>
                        <?php if (!empty($active['new_interest_rate'])): ?>
                        <div class="neg-offer-item">
                            <span><?= t('bank.neg_new_rate') ?></span>
                            <strong><?= htmlspecialchars((string)$active['new_interest_rate']) ?>% APR</strong>
                        </div>
                        <?php endif ?>
                        <div class="neg-offer-item">
                            <span><?= t('bank.neg_fee') ?></span>
                            <strong class="money"><?= number_format((float)($active['additional_fee'] ?? 0)) ?> PLN</strong>
                        </div>
                        <?php if (!empty($active['expires_at'])): ?>
                        <div class="neg-offer-item neg-expire">
                            <span><?= t('bank.neg_expires') ?></span>
                            <strong><?= htmlspecialchars($active['expires_at_fmt'] ?? '�') ?></strong>
                        </div>
                        <?php endif ?>
                    </div>
                    <form method="post" class="neg-apply-form">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="neg_apply">
                        <input type="hidden" name="negotiation_id" value="<?= (int)$active['id'] ?>">
                        <button type="submit" class="btn btn-success">
                            <?= t('bank.neg_accept') ?>
                        </button>
                    </form>
                    <?php endif ?>

                    <?php if (!empty($events)): ?>
                    <div class="neg-events">
                        <h4 class="neg-events-title"><?= t('bank.neg_events_title') ?></h4>
                        <?php foreach ($events as $ev): ?>
                        <div class="neg-event neg-event-<?= htmlspecialchars($ev['event_type'] ?? '') ?>">
                            <span class="neg-event-icon"><?= $negEventIcon($ev['event_type'] ?? '') ?></span>
                            <div class="neg-event-body">
                                <span class="neg-event-msg"><?= htmlspecialchars($ev['message'] ?? '') ?></span>
                                <span class="neg-event-time"><?= htmlspecialchars($ev['created_at_fmt'] ?? '') ?></span>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                    <?php endif ?>

                </div>

                <?php elseif ($can['can_negotiate']): ?>
                <div class="neg-forms">

                    <?php if (!empty($can['last_chance'])): ?>
                    <div class="info-box info-box-red">
                        <?= t('bank.neg_last_chance') ?>
                    </div>
                    <?php endif ?>

                    <div class="neg-accordion">

                        <details class="neg-panel">
                            <summary class="neg-panel-header">
                                 <?= t('bank.neg_deferral_title') ?>
                                <span class="neg-panel-sub"><?= t('bank.neg_deferral_sub') ?></span>
                            </summary>
                            <div class="neg-panel-body">
                                <p class="neg-desc"><?= t('bank.neg_deferral_desc') ?></p>
                                <form method="post">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="neg_deferral">
                                    <input type="hidden" name="loan_id" value="<?= $loanId ?>">
                                    <div class="form-group">
                                        <label><?= t('bank.neg_deferral_period') ?></label>
                                        <div class="neg-options">
                                            <?php foreach ($deferralOpts[$loanId] as $days => $opt): ?>
                                            <label class="neg-option">
                                                <input type="radio" name="days" value="<?= $days ?>" <?= $days === 30 ? 'required' : '' ?>>
                                                <span class="neg-option-label">
                                                    <?= $days ?> <?= t('common.days') ?>
                                                    <small><?= htmlspecialchars($opt['apr']) ?> � prowizja: ~<?= number_format($opt['fee']) ?> PLN</small>
                                                </span>
                                            </label>
                                            <?php endforeach ?>
                                        </div>
                                        <small class="neg-fee-note"><?= t('bank.neg_deferral_fee_note') ?></small>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><?= t('bank.neg_deferral_submit') ?></button>
                                </form>
                            </div>
                        </details>

                        <details class="neg-panel">
                            <summary class="neg-panel-header">
                                <?= t('bank.neg_restructure_title') ?>
                                <span class="neg-panel-sub"><?= t('bank.neg_restructure_sub') ?></span>
                            </summary>
                            <div class="neg-panel-body">
                                <p class="neg-desc"><?= t('bank.neg_restructure_desc') ?></p>
                                <form method="post">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="neg_restructure">
                                    <input type="hidden" name="loan_id" value="<?= $loanId ?>">
                                    <div class="form-group">
                                        <label for="months_<?= $loanId ?>"><?= t('bank.neg_restructure_period') ?></label>
                                        <select name="months" id="months_<?= $loanId ?>">
                                            <option value="1">1 miesi�c</option>
                                            <option value="2">2 miesi�ce</option>
                                            <option value="3">3 miesi�ce</option>
                                            <option value="6" selected>6 miesi�cy</option>
                                            <option value="9">9 miesi�cy</option>
                                            <option value="12">12 miesi�cy</option>
                                        </select>
                                        <small class="neg-fee-note"><?= t('bank.neg_restructure_fee_note') ?></small>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><?= t('bank.neg_restructure_submit') ?></button>
                                </form>
                            </div>
                        </details>

                        <?php if ($canRecovery): ?>
                        <details class="neg-panel neg-panel-critical">
                            <summary class="neg-panel-header">
                                <?= t('bank.neg_recovery_title') ?>
                                <span class="neg-panel-sub"><?= t('bank.neg_recovery_sub') ?></span>
                            </summary>
                            <div class="neg-panel-body">
                                <div class="info-box info-box-red">
                                    <?= t('bank.neg_recovery_warning') ?>
                                </div>
                                <p class="neg-desc"><?= t('bank.neg_recovery_desc') ?></p>
                                <form method="post">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="neg_recovery">
                                    <input type="hidden" name="loan_id" value="<?= $loanId ?>">
                                    <button type="submit" class="btn btn-danger"><?= t('bank.neg_recovery_submit') ?></button>
                                </form>
                            </div>
                        </details>
                        <?php endif ?>

                    </div>
                </div>

                <?php else: ?>
                <div class="neg-unavailable">
                    <span class="neg-lock-icon"></span>
                    <span><?= t('bank.neg_unavailable') ?></span>
                </div>
                <?php endif ?>

            </div>
            <?php endif ?>

        </div>
        <?php endforeach ?>
    </section>
    <?php endif ?>


</div>

<!-- Modal potwierdzenia sp�aty -->
<div id="repay-modal" class="repay-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="repay-modal-title">
    <div class="repay-modal">
        <div class="repay-modal-icon"></div>
        <h3 id="repay-modal-title" class="repay-modal-title"><?= t('bank.repay_modal_title') ?></h3>
        <p class="repay-modal-desc" id="repay-modal-desc"></p>
        <div class="repay-modal-amount" id="repay-modal-amount"></div>
        <div class="repay-modal-actions">
            <button class="btn btn-ghost" onclick="repayModalClose()"><?= t('bank.repay_modal_cancel') ?></button>
            <button class="btn btn-success" id="repay-modal-confirm"><?= t('bank.repay_modal_confirm') ?></button>
        </div>
    </div>
</div>

<script>
window.BANK_LANG = <?= json_encode([
    'pln'                  => t('bank_js.pln'),
    'repay_modal_single'   => t('bank.repay_modal_desc_single'),
    'repay_modal_multiple' => t('bank.repay_modal_desc_multiple'),
    'repay_modal_full'     => t('bank.repay_modal_desc_full'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/bank.js"></script>
