<?php extract($viewData, EXTR_SKIP); ?>

<?php
// Stage 4: bank account data (number, balance, history) with safe defaults.
$bankAccountNumber    = $accountNumber  ?? '';
$bankAccountBalance   = (float)($accountBalance ?? 0);  // bank_balance field
$playerCashBalance    = (float)($cashBalance   ?? 0);   // cash field
$bankAccountHistory   = $accountHistory ?? [];
$bankHistoryTotal     = (int)($accountHistoryTotal ?? count($bankAccountHistory));
$bankHistoryPage      = (int)($accountHistoryPage  ?? 1);
$bankHistoryPerPage   = \BankDataLoader::HISTORY_PER_PAGE;
$bankHistoryMaxPage   = max(1, (int)ceil($bankHistoryTotal / $bankHistoryPerPage));
$bankLocale           = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
?>

<div class="fade-in">
    <section class="card">
        <h2><?= html_entity_decode(strip_tags(tPlain('bank.title')), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= t('bank.subtitle') ?></p>
    </section>

    <!-- Stage 4: bank account card -->
    <?php if (!$isBankrupt && $bankAccountNumber !== ''): ?>
    <section class="card bank-account-card" aria-labelledby="account-heading">
        <header class="bank-account-header">
            <h2 id="account-heading" class="bank-account-title">
                <span class="bank-account-icon" aria-hidden="true"></span>
                <?= t('bank.account.section_title') ?>
            </h2>
            <p class="bank-account-subtitle"><?= t('bank.account.section_desc') ?></p>
        </header>

        <div class="bank-account-grid">
            <div class="bank-account-tile bank-account-tile--number">
                <span class="bank-account-tile-label"><?= t('bank.account.label_number') ?></span>
                <span class="bank-account-tile-value bank-account-tile-value--mono"
                      id="bank-account-number-text"
                      data-account="<?= htmlspecialchars($bankAccountNumber) ?>">
                    <?= htmlspecialchars($bankAccountNumber) ?>
                </span>
                <button type="button"
                        class="btn btn-ghost btn-sm bank-account-copy-btn"
                        id="bank-account-copy"
                        aria-label="<?= htmlspecialchars(t('bank.account.label_copy'), ENT_QUOTES) ?>">
                    <?= t('bank.account.label_copy') ?>
                </button>
            </div>

            <div class="bank-account-tile bank-account-tile--balance">
                <span class="bank-account-tile-label"><?= t('bank.account.label_balance') ?></span>
                <span class="bank-account-tile-value money">
                    <?= number_format($bankAccountBalance, 2, ',', ' ') ?> USD
                </span>
            </div>

            <div class="bank-account-tile bank-account-tile--action">
                <button type="button"
                        class="btn btn-primary btn-full bank-transfer-trigger"
                        id="bank-transfer-trigger"
                        data-balance="<?= htmlspecialchars((string)$bankAccountBalance, ENT_QUOTES) ?>">
                    <span class="bank-transfer-icon"></span>
                    <?= t('bank.account.transfer_btn') ?>
                </button>
            </div>
        </div>
    </section>
    <?php endif ?>

    <!-- Wallet: cash and bank account -->
    <?php if (!$isBankrupt): ?>
    <section class="card wallet-section" aria-labelledby="wallet-heading" id="wallet-section">
        <h2 id="wallet-heading"><?= t('wallet.section_title') ?></h2>
        <p class="muted"><?= t('wallet.section_desc') ?></p>

        <!-- Balance tiles -->
        <div class="wallet-balances">
            <div class="wallet-bal wallet-bal--cash">
                <span class="wallet-bal-label"><?= t('wallet.label_cash') ?></span>
                <span class="wallet-bal-value money"
                      data-wallet-cash
                      id="wallet-cash-display">
                    <?= number_format($playerCashBalance, 2, ',', ' ') ?> USD
                </span>
            </div>
            <div class="wallet-bal wallet-bal--bank">
                <span class="wallet-bal-label"><?= t('wallet.label_bank') ?></span>
                <span class="wallet-bal-value money"
                      data-wallet-bank
                      id="wallet-bank-display">
                    <?= number_format($bankAccountBalance, 2, ',', ' ') ?> USD
                </span>
            </div>
        </div>

        <!-- Transfer forms -->
        <div class="wallet-transfers">

            <!-- Cash -> Bank -->
            <div class="wallet-tf">
                <div class="wallet-arrow wallet-arrow--to-bank" aria-hidden="true">
                    <svg viewBox="0 0 20 20" width="16" height="16"><path d="M4 10h12M12 6l4 4-4 4" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <p class="wallet-tf-title"><?= t('wallet.btn_cash_to_bank') ?></p>
                <p class="wallet-tf-direction muted"><?= t('wallet.dir_cash_to_bank') ?></p>
                <form class="wallet-tf-form" id="wallet-form-cash-to-bank" data-action="cash_to_bank" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="wallet-amount-ctb"><?= t('wallet.label_amount') ?></label>
                        <input type="text"
                               inputmode="numeric"
                               pattern="[0-9 .,]*"
                               id="wallet-amount-ctb"
                               name="amount"
                               class="input-full"
                               autocomplete="off"
                               placeholder="<?= htmlspecialchars(t('wallet.amount_placeholder'), ENT_QUOTES) ?>">
                    </div>
                    <p class="wallet-tf-fee-preview"></p>
                    <button type="submit" class="btn btn-primary btn-sm btn-full">
                        <?= t('wallet.btn_cash_to_bank') ?>
                    </button>
                </form>
            </div>

            <!-- Bank -> Cash -->
            <div class="wallet-tf">
                <div class="wallet-arrow wallet-arrow--to-cash" aria-hidden="true">
                    <svg viewBox="0 0 20 20" width="16" height="16"><path d="M16 10H4M8 14l-4-4 4-4" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <p class="wallet-tf-title"><?= t('wallet.btn_bank_to_cash') ?></p>
                <p class="wallet-tf-direction muted"><?= t('wallet.dir_bank_to_cash') ?></p>
                <form class="wallet-tf-form" id="wallet-form-bank-to-cash" data-action="bank_to_cash" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="wallet-amount-btc"><?= t('wallet.label_amount') ?></label>
                        <input type="text"
                               inputmode="numeric"
                               pattern="[0-9 .,]*"
                               id="wallet-amount-btc"
                               name="amount"
                               class="input-full"
                               autocomplete="off"
                               placeholder="<?= htmlspecialchars(t('wallet.amount_placeholder'), ENT_QUOTES) ?>">
                    </div>
                    <p class="wallet-tf-fee-preview"></p>
                    <button type="submit" class="btn btn-primary btn-sm btn-full">
                        <?= t('wallet.btn_bank_to_cash') ?>
                    </button>
                </form>
            </div>

        </div><!-- /.wallet-transfers -->

        <p class="muted" style="font-size:12px;margin-top:10px">
            <?= t('wallet.fee_info', [
                'fee_pct' => number_format(WalletConfig::TRANSFER_FEE_PCT * 100, 1, ',', ''),
                'min'     => number_format(WalletConfig::TRANSFER_MIN_FEE, 0, ',', ' '),
                'min_amt' => number_format(WalletConfig::TRANSFER_MIN_AMOUNT, 0, ',', ' '),
                'max_amt' => number_format(WalletConfig::TRANSFER_MAX_AMOUNT, 0, ',', ' '),
            ]) ?>
        </p>
    </section>
    <?php endif ?>
    <!-- /Wallet -->

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
    <noscript><div class="bank-flash bank-flash--error"><span class="bank-flash-icon"></span><span><?= htmlspecialchars($error) ?></span></div></noscript>
    <script>(function(){var m=<?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>;function s(){if(typeof window.alertError==='function'){window.alertError(m);}else if(typeof window.showGameToast==='function'){window.showGameToast(m,'error');}}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',s);}else{s();}})();</script>
    <?php endif ?>

    <?php if (!empty($success)): ?>
    <noscript><div class="bank-flash bank-flash--success"><span class="bank-flash-icon"></span><span><?= htmlspecialchars($success) ?></span></div></noscript>
    <script>(function(){var m=<?= json_encode($success, JSON_UNESCAPED_UNICODE) ?>;function s(){if(typeof window.alertInfo==='function'){window.alertInfo(m);}else if(typeof window.showGameToast==='function'){window.showGameToast(m,'success');}}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',s);}else{s();}})();</script>
    <?php endif ?>

    <!-- Credit application -->

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
                <strong class="money"><?= number_format((float)($offer['amount'] ?? 0)) ?> USD</strong>
            </div>
            <div class="offer-item">
                <span class="offer-label"><?= t('bank.offer_rate') ?></span>
                <span><?= htmlspecialchars((string)($offer['interest_rate'] ?? '')) ?>%</span>
            </div>
            <div class="offer-item">
                <span class="offer-label"><?= t('bank.offer_installment') ?></span>
                <strong class="money"><?= number_format((float)($offer['installment_amount'] ?? 0)) ?> USD</strong>
            </div>
            <div class="offer-item">
                <span class="offer-label"><?= t('bank.offer_total_cost') ?></span>
                <span class="money"><?= number_format((float)($offer['estimated_total_cost'] ?? 0)) ?> USD</span>
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
            <form method="post"
                  data-confirm="<?= htmlspecialchars(t('bank.offer_accept_confirm'), ENT_QUOTES) ?>"
                  data-confirm-title="<?= htmlspecialchars(t('bank.offer_accept_title'), ENT_QUOTES) ?>"
                  data-confirm-label="<?= htmlspecialchars(t('bank.offer_accept_label'), ENT_QUOTES) ?>">
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
            <span class="money"><?= number_format($creditLimit, 0, '.', ' ') ?> USD</span>
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

    <!-- Active loans -->

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
                            <strong class="money"><?= number_format((float)($loan['remaining_amount'] ?? 0)) ?> USD</strong>
                        </div>
                        <div class="loan-item">
                            <span><?= t('bank.loan_principal') ?></span>
                            <span class="money"><?= number_format((float)($loan['principal_amount'] ?? 0)) ?> USD</span>
                        </div>
                        <div class="loan-item">
                            <span><?= t('bank.loan_rate') ?></span>
                            <span><?= htmlspecialchars((string)($loan['interest_rate'] ?? '')) ?>% APR</span>
                        </div>
                        <div class="loan-item">
                            <span><?= t('bank.loan_next_installment') ?></span>
                            <span><?= htmlspecialchars($loan['next_installment_fmt'] ?? '-') ?></span>
                        </div>
                        <div class="loan-item">
                            <span><?= t('bank.loan_installment_amount') ?></span>
                            <strong class="money"><?= number_format((float)($loan['installment_amount'] ?? 0)) ?> USD</strong>
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

            <!-- Repayment -->
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
                                <small><?= number_format((float)($loan['installment_amount'] ?? 0)) ?> USD</small>
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
                                    = <?= number_format((float)($loan['installment_amount'] ?? 0) * 2) ?> USD
                                </small>
                            </span>
                        </label>
                        <label class="repay-option">
                            <input type="radio" name="repay_mode" value="full">
                            <span>
                                <?= t('bank.repay_full') ?>
                                <small class="c-good"><?= number_format((float)($loan['remaining_amount'] ?? 0)) ?> USD</small>
                            </span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success"><?= t('bank.repay_submit') ?></button>
                </form>
            </div>

            <!-- Negotiations - only when the loan is late or negotiation is active -->
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
                        <?= t('bank.neg_due', ['date' => htmlspecialchars($active['decision_due_at_fmt'] ?? '-')]) ?>
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
                            <strong class="money"><?= number_format((float)($active['additional_fee'] ?? 0)) ?> USD</strong>
                        </div>
                        <?php if (!empty($active['expires_at'])): ?>
                        <div class="neg-offer-item neg-expire">
                            <span><?= t('bank.neg_expires') ?></span>
                            <strong><?= htmlspecialchars($active['expires_at_fmt'] ?? '-') ?></strong>
                        </div>
                        <?php endif ?>
                    </div>
                    <form method="post" class="neg-apply-form"
                          data-confirm="<?= htmlspecialchars(t('bank_neg.confirm_apply'), ENT_QUOTES) ?>"
                          data-confirm-title="<?= htmlspecialchars(t('bank_neg.confirm_apply_title'), ENT_QUOTES) ?>"
                          data-confirm-label="<?= htmlspecialchars(t('bank.neg_accept'), ENT_QUOTES) ?>">
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
                                <form method="post"
                                      data-confirm="<?= htmlspecialchars(t('bank_neg.confirm_deferral'), ENT_QUOTES) ?>"
                                      data-confirm-title="<?= htmlspecialchars(t('bank_neg.confirm_deferral_title'), ENT_QUOTES) ?>">
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
                                                    <small><?= htmlspecialchars($opt['apr']) ?> - <?= $bankLocale === 'en' ? 'fee' : 'prowizja' ?>: ~<?= number_format($opt['fee']) ?> USD</small>
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
                                <form method="post"
                                      data-confirm="<?= htmlspecialchars(t('bank_neg.confirm_restructure'), ENT_QUOTES) ?>"
                                      data-confirm-title="<?= htmlspecialchars(t('bank_neg.confirm_restructure_title'), ENT_QUOTES) ?>">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="neg_restructure">
                                    <input type="hidden" name="loan_id" value="<?= $loanId ?>">
                                    <div class="form-group">
                                        <label for="months_<?= $loanId ?>"><?= t('bank.neg_restructure_period') ?></label>
                                        <select name="months" id="months_<?= $loanId ?>">
                                            <option value="1"><?= $bankLocale === 'en' ? '1 month' : '1 miesiac' ?></option>
                                            <option value="2"><?= $bankLocale === 'en' ? '2 months' : '2 miesiace' ?></option>
                                            <option value="3"><?= $bankLocale === 'en' ? '3 months' : '3 miesiace' ?></option>
                                            <option value="6" selected><?= $bankLocale === 'en' ? '6 months' : '6 miesiecy' ?></option>
                                            <option value="9"><?= $bankLocale === 'en' ? '9 months' : '9 miesiecy' ?></option>
                                            <option value="12"><?= $bankLocale === 'en' ? '12 months' : '12 miesiecy' ?></option>
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
                                <form method="post"
                                      data-confirm="<?= htmlspecialchars(t('bank_neg.confirm_recovery'), ENT_QUOTES) ?>"
                                      data-confirm-title="<?= htmlspecialchars(t('bank_neg.confirm_recovery_title'), ENT_QUOTES) ?>"
                                      data-confirm-type="danger"
                                      data-confirm-label="<?= htmlspecialchars(t('bank.neg_recovery_submit'), ENT_QUOTES) ?>">
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

    <!-- Stage 4: transaction history -->
    <?php if (!$isBankrupt && $bankAccountNumber !== ''): ?>
    <section class="card bank-history-card" aria-labelledby="bank-history-heading">
        <header class="bank-history-header">
            <h2 id="bank-history-heading">
                <span class="bank-history-icon" aria-hidden="true"></span>
                <?= t('bank.account.history_title') ?>
            </h2>
        </header>

        <?php if (empty($bankAccountHistory)): ?>
        <div class="info-box info-box-blue bank-history-empty">
            <?= t('bank.account.history_empty') ?>
        </div>
        <?php else: ?>
        <div class="bank-history-list" role="list">
            <div class="bank-history-row bank-history-row--header" role="row">
                <span class="bank-history-cell bank-history-cell--date"><?= t('bank.account.history_col_date') ?></span>
                <span class="bank-history-cell bank-history-cell--type"><?= t('bank.account.history_col_type') ?></span>
                <span class="bank-history-cell bank-history-cell--counterparty"><?= t('bank.account.history_col_counterparty') ?></span>
                <span class="bank-history-cell bank-history-cell--desc"><?= t('bank.account.history_col_description') ?></span>
                <span class="bank-history-cell bank-history-cell--amount"><?= t('bank.account.history_col_amount') ?></span>
            </div>
            <?php foreach ($bankAccountHistory as $hRow):
                $hIsInflow = !empty($hRow['is_inflow']);
                $hType     = (string)($hRow['transaction_type'] ?? '');
                $hTypeKey  = 'bank.account.type.' . $hType;
                $hTypeLbl  = t($hTypeKey);
                if ($hTypeLbl === $hTypeKey) { $hTypeLbl = $hType; }
                $hAmount   = (float)($hRow['signed_amount'] ?? 0);
                $hClass    = $hIsInflow ? 'bank-history-amount--in' : 'bank-history-amount--out';
                $hSign     = $hIsInflow ? '+' : '';
                $hDesc     = (string)($hRow['description'] ?? '');
            ?>
            <div class="bank-history-row" role="listitem">
                <span class="bank-history-cell bank-history-cell--date"><?= htmlspecialchars($hRow['created_at_fmt'] ?? '') ?></span>
                <span class="bank-history-cell bank-history-cell--type">
                    <span class="bank-history-type-pill bank-history-type-<?= htmlspecialchars($hType) ?>">
                        <?= htmlspecialchars($hTypeLbl) ?>
                    </span>
                </span>
                <span class="bank-history-cell bank-history-cell--counterparty">
                    <?= htmlspecialchars((string)($hRow['counterparty_label'] ?? '')) ?>
                </span>
                <span class="bank-history-cell bank-history-cell--desc">
                    <?= $hDesc !== '' ? htmlspecialchars($hDesc) : '<span class="muted">&mdash;</span>' ?>
                </span>
                <span class="bank-history-cell bank-history-cell--amount">
                    <strong class="bank-history-amount <?= $hClass ?>">
                        <?= $hSign ?><?= number_format($hAmount, 2, ',', ' ') ?> USD
                    </strong>
                </span>
            </div>
            <?php endforeach ?>
        </div>

        <?php if ($bankHistoryMaxPage > 1): ?>
        <nav class="bank-history-pagination" aria-label="<?= htmlspecialchars($bankLocale === 'en' ? 'History pages' : 'Strony historii', ENT_QUOTES) ?>">
            <?php if ($bankHistoryPage > 1): ?>
            <a class="bank-history-page-btn" href="?txpage=<?= $bankHistoryPage - 1 ?>#bank-history-heading"><?= $bankLocale === 'en' ? '&lt;&lt; Previous' : '&lt;&lt; Poprzednia' ?></a>
            <?php endif ?>
            <span class="bank-history-page-info"><?= $bankLocale === 'en' ? 'Page' : 'Strona' ?> <?= $bankHistoryPage ?> <?= $bankLocale === 'en' ? 'of' : 'z' ?> <?= $bankHistoryMaxPage ?></span>
            <?php if ($bankHistoryPage < $bankHistoryMaxPage): ?>
            <a class="bank-history-page-btn" href="?txpage=<?= $bankHistoryPage + 1 ?>#bank-history-heading"><?= $bankLocale === 'en' ? 'Next &gt;&gt;' : 'Nastepna &gt;&gt;' ?></a>
            <?php endif ?>
        </nav>
        <?php endif ?>

        <?php endif ?>
    </section>
    <?php endif ?>

</div>

<!-- Stage 4: transfer modal -->
<?php if (!$isBankrupt && $bankAccountNumber !== ''): ?>
<div id="bank-transfer-modal" class="bank-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="bank-transfer-modal-title">
    <div class="bank-modal">
        <button type="button" class="bank-modal-close" id="bank-transfer-modal-close" aria-label="<?= htmlspecialchars(t('bank.account.transfer_cancel'), ENT_QUOTES) ?>">&times;</button>
        <div class="bank-modal-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#c8860a" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6"/>
            </svg>
        </div>
        <h3 id="bank-transfer-modal-title" class="bank-modal-title"><?= t('bank.account.transfer_modal_title') ?></h3>
        <p class="bank-modal-desc"><?= t('bank.account.transfer_modal_desc') ?></p>

        <form method="post"
              id="bank-transfer-form"
              class="bank-modal-form"
              data-confirm-title="<?= htmlspecialchars(t('bank.account.transfer_confirm_title'), ENT_QUOTES) ?>"
              data-confirm-label="<?= htmlspecialchars(t('bank.account.transfer_confirm_label'), ENT_QUOTES) ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="bank_transfer">

            <div class="form-group">
                <label for="bank-tr-recipient" class="form-label"><?= t('bank.account.transfer_recipient') ?></label>
                <input type="text"
                       id="bank-tr-recipient"
                       name="recipient_account"
                       class="input-full"
                       placeholder="<?= htmlspecialchars(t('bank.account.transfer_recipient_placeholder'), ENT_QUOTES) ?>"
                       autocomplete="off"
                       required>
            </div>

            <div class="form-group">
                <label for="bank-tr-amount" class="form-label"><?= t('bank.account.transfer_amount') ?></label>
                <input type="text"
                       inputmode="numeric"
                       pattern="[0-9 .,]*"
                       id="bank-tr-amount"
                       name="amount"
                       class="input-full"
                       placeholder="<?= htmlspecialchars(t('bank.account.transfer_amount_placeholder'), ENT_QUOTES) ?>"
                       required>
                <small class="muted">
                    <?= t('bank.account.label_balance') ?>:
                    <strong class="money"><?= number_format($bankAccountBalance, 2, ',', ' ') ?> USD</strong>
                </small>
            </div>

            <div class="form-group">
                <label for="bank-tr-description" class="form-label"><?= t('bank.account.transfer_description') ?></label>
                <input type="text"
                       id="bank-tr-description"
                       name="description"
                       class="input-full"
                       maxlength="255"
                       placeholder="<?= htmlspecialchars(t('bank.account.transfer_description_placeholder'), ENT_QUOTES) ?>">
            </div>

            <div class="bank-modal-actions">
                <button type="button" class="btn btn-ghost" id="bank-transfer-modal-cancel">
                    <?= t('bank.account.transfer_cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= t('bank.account.transfer_submit') ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif ?>

<!-- Repayment confirmation modal -->
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
window.WALLET_API      = '<?= htmlspecialchars(url('wallet-transfer'), ENT_QUOTES) ?>';
window.WALLET_CSRF     = '<?= htmlspecialchars(CSRF::generateToken(), ENT_QUOTES) ?>';
window.WALLET_FEE_PCT  = <?= json_encode(WalletConfig::TRANSFER_FEE_PCT) ?>;
window.WALLET_FEE_MIN  = <?= json_encode(WalletConfig::TRANSFER_MIN_FEE) ?>;
window.WALLET_LANG     = <?= json_encode([
    'fee_preview'          => tPlain('wallet.fee_preview'),
    'confirm_cash_to_bank' => t('wallet.confirm_cash_to_bank'),
    'confirm_bank_to_cash' => t('wallet.confirm_bank_to_cash'),
    'err_generic'          => t('wallet.err_generic'),
    'err_network'          => t('wallet.err_network'),
], JSON_UNESCAPED_UNICODE) ?>;
window.BANK_LANG = <?= json_encode([
    'pln'                  => 'USD',
    'repay_modal_single'   => t('bank.repay_modal_desc_single'),
    'repay_modal_multiple' => t('bank.repay_modal_desc_multiple'),
    'repay_modal_full'     => t('bank.repay_modal_desc_full'),
    'account_copied'       => t('bank.account.label_copied'),
    'account_copy'         => t('bank.account.label_copy'),
    'transfer_confirm_msg' => t('bank.account.transfer_modal_desc'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/bank.js"></script>

<!-- Stage 4: account and transfer scripts -->
<script>
(function () {
    'use strict';

    // ----- Copy account number to clipboard -----
    var copyBtn = document.getElementById('bank-account-copy');
    var numberEl = document.getElementById('bank-account-number-text');
    if (copyBtn && numberEl) {
        copyBtn.addEventListener('click', function () {
            var num = numberEl.dataset.account || numberEl.textContent.trim();
            var done = function () {
                var original = copyBtn.textContent;
                copyBtn.textContent = (window.BANK_LANG && window.BANK_LANG.account_copied) || 'Copied!';
                copyBtn.classList.add('bank-account-copy-btn--ok');
                setTimeout(function () {
                    copyBtn.textContent = original;
                    copyBtn.classList.remove('bank-account-copy-btn--ok');
                }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(num).then(done, function () {
                    // Fallback path using select + execCommand.
                    fallbackCopy(num); done();
                });
            } else {
                fallbackCopy(num); done();
            }
        });
    }
    function fallbackCopy(text) {
        try {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus(); ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        } catch (e) {}
    }

    // ----- Transfer modal open/close -----
    var modal = document.getElementById('bank-transfer-modal');
    var trigger = document.getElementById('bank-transfer-trigger');
    var btnClose = document.getElementById('bank-transfer-modal-close');
    var btnCancel = document.getElementById('bank-transfer-modal-cancel');
    var form = document.getElementById('bank-transfer-form');
    var recipientInput = document.getElementById('bank-tr-recipient');

    if (!modal || !trigger || !form) return;

    function openModal() {
        modal.style.display = 'flex';
        document.body.classList.add('bank-modal-open');
        setTimeout(function () { recipientInput && recipientInput.focus(); }, 30);
    }
    function closeModal() {
        modal.style.display = 'none';
        document.body.classList.remove('bank-modal-open');
    }

    trigger.addEventListener('click', openModal);
    btnClose && btnClose.addEventListener('click', closeModal);
    btnCancel && btnCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        // Clicking the overlay outside .bank-modal closes it.

        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
    });

    // ----- Dynamic data-confirm message before submit -----
    // Build a clear confirmation message for the transfer modal.
    form.addEventListener('submit', function (e) {
        var locale = <?= json_encode($bankLocale === 'en' ? 'en-US' : 'pl-PL') ?>;
        if (form.dataset.confirmBound === '1') return; // Modal already accepted.
        // Sanitize amount and calculate a readable preview in JS.
        var amountRaw = (document.getElementById('bank-tr-amount').value || '').replace(/\s+/g, '').replace(',', '.');
        var amount = parseFloat(amountRaw);
        var account = (document.getElementById('bank-tr-recipient').value || '').trim();
        var desc = (document.getElementById('bank-tr-description').value || '').trim();
        var fmt = isFinite(amount) ? amount.toLocaleString(locale, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (locale === 'en-US' ? '0.00' : '0,00');
        var msg = locale === 'en-US' ? ('Transferring ' + fmt + ' USD to account ' + account + '.') : ('Przelewasz ' + fmt + ' USD na konto ' + account + '.');
        if (desc) { msg += locale === 'en-US' ? ('\nTitle: ' + desc) : ('\nTytul: ' + desc); }
        msg += locale === 'en-US' ? '\n\nContinue?' : '\n\nKontynuowac?';
        form.setAttribute('data-confirm', msg);
        // modal.js reads data-confirm and shows the confirmation modal.
    });
})();
</script>
