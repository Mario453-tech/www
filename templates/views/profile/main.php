<?php extract($viewData, EXTR_SKIP); ?>
<?php
/**
 * Profil gracza - widok zgodny z briefem BRIEF-AI-PROFIL.md.
 * Player profile - view per the BRIEF-AI-PROFIL.md brief.
 * Dwie kolumny: lewa szyna (tozsamosc, statystyki, czarny rynek,
 * wiarygodnosc), prawa kolumna (dane firmy, bezpieczenstwo konta).
 * Two columns: left rail (identity, stats, black market, credibility),
 * right column (company details, account security).
 *
 * Wszystkie interakcje sa w assets/js/profile.js; tutaj tylko dane
 * konfiguracyjne (data attributes) - bez inline JS i handlerow.
 * All interactions live in assets/js/profile.js; here only config data
 * (data attributes) - no inline JS or handlers.
 */

$__username = (string)($playerData['username'] ?? '');
$__fallbackInitial = mb_strtoupper(mb_substr($__username, 0, 1));
if ($__fallbackInitial === '') {
    $__fallbackInitial = ($_SESSION['locale'] ?? 'pl') === 'en' ? 'P' : 'G';
}
$__allowedLevels = ['critical', 'low', 'shaky', 'stable', 'high'];
$__level = in_array($credibilityLevel, $__allowedLevels, true) ? $credibilityLevel : 'shaky';
$__penalties = (float)($bmStats['total_penalties'] ?? 0);
$__currency = t('common.currency');
$__eyeShow = htmlspecialchars(asset('/assets/icons/profile/eye.svg'), ENT_QUOTES, 'UTF-8');
$__eyeHide = htmlspecialchars(asset('/assets/icons/profile/eye-off.svg'), ENT_QUOTES, 'UTF-8');
?>

<div class="profile-shell">

    <?php if (!empty($errors)): ?>
    <div class="profile-flash profile-flash--error" role="alert">
        <?php foreach ($errors as $__e): ?><p><?= $__e ?></p><?php endforeach ?>
    </div>
    <?php endif ?>
    <?php if (!empty($success)): ?>
    <div class="profile-flash profile-flash--success" role="status">
        <?php foreach ($success as $__s): ?><p><?= $__s ?></p><?php endforeach ?>
    </div>
    <?php endif ?>

    <div id="profileConfig" hidden
         data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
         data-avatar-uploading="<?= t('profile_js.avatar_uploading') ?>"
         data-avatar-invalid="<?= t('profile.avatar_invalid') ?>"
         data-avatar-preview="<?= t('profile.avatar_preview_note') ?>"
         data-avatar-failed="<?= t('profile.err_upload_error') ?>"
         data-name-prefix="<?= t('profile.name_preview') ?>"
         data-name-empty="<?= t('profile.name_preview_empty', ['n' => $playerId]) ?>"
         data-toggle-show="<?= t('profile.toggle_show') ?>"
         data-toggle-hide="<?= t('profile.toggle_hide') ?>"
         data-pass-very-weak="<?= t('profile_js.pass_very_weak') ?>"
         data-pass-weak="<?= t('profile_js.pass_weak') ?>"
         data-pass-medium="<?= t('profile_js.pass_medium') ?>"
         data-pass-strong="<?= t('profile_js.pass_strong') ?>"
         data-pass-very-strong="<?= t('profile_js.pass_very_strong') ?>"
         data-pass-match="<?= t('profile_js.pass_match') ?>"
         data-pass-no-match="<?= t('profile_js.pass_no_match') ?>"
    ></div>

    <header class="profile-heading"><h1><?= t('profile.heading') ?></h1></header>

    <div class="profile-layout">
        <div class="profile-rail">

            <section class="profile-card profile-identity" aria-label="<?= t('profile.identity_aria') ?>">
                <?php if ($avatarUrl): ?>
                <img class="profile-avatar-img" id="avatar-preview" src="<?= $avatarUrl ?>" alt="<?= t('profile.avatar_alt') ?>">
                <?php else: ?>
                <div class="profile-avatar-ph" id="avatar-preview" aria-hidden="true"><span><?= $__fallbackInitial ?></span></div>
                <?php endif ?>
                <div class="identity-copy">
                    <h2><?= htmlspecialchars($__username, ENT_QUOTES, 'UTF-8') ?></h2>
                    <strong><?= $companyName !== '' ? $companyName : t('profile.no_company', ['n' => $playerId]) ?></strong>
                    <button class="profile-photo-button" id="photo-button" type="button" aria-label="<?= t('profile.avatar_change_title') ?>">
                        <img src="<?= asset('/assets/icons/profile/camera.svg') ?>" alt="" aria-hidden="true"><?= t('profile.change_photo') ?>
                    </button>
                    <form method="post" enctype="multipart/form-data" id="avatar-form" class="profile-avatar-form">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="upload_avatar">
                        <input type="file" name="avatar" id="photo-input" class="visually-hidden" accept="image/jpeg,image/png,image/webp" tabindex="-1">
                    </form>
                    <small><?= t('profile.avatar_hint') ?></small>
                    <small class="photo-feedback" id="photo-feedback" role="status"></small>
                </div>
            </section>

            <section class="profile-card profile-account-stats" aria-labelledby="stats-heading">
                <h2 id="stats-heading"><img src="<?= asset('/assets/icons/profile/stats.svg') ?>" alt="" aria-hidden="true"><?= t('profile.stats_title') ?></h2>
                <dl>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/calendar.svg') ?>" alt="" aria-hidden="true"><?= t('profile.member_since') ?></dt>
                        <dd><?= $memberSince ?></dd>
                    </div>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/sign-in.svg') ?>" alt="" aria-hidden="true"><?= t('profile.last_login') ?></dt>
                        <dd><?= $lastLogin ?></dd>
                    </div>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/well.svg') ?>" alt="" aria-hidden="true"><?= t('profile.wells_label') ?></dt>
                        <dd><?= sprintf(t('profile.wells_stat'), (int)$playerData['active_wells'], (int)$playerData['well_count']) ?></dd>
                    </div>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/oil.svg') ?>" alt="" aria-hidden="true"><?= t('profile.sold_oil_label') ?></dt>
                        <dd><?= number_format((float)($sellStats['total_sold'] ?? 0), 0, '.', ' ') ?> bbl</dd>
                    </div>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/safety.svg') ?>" alt="" aria-hidden="true"><?= t('profile.safety_label') ?></dt>
                        <dd><?= sprintf(t('profile.safety_level'), (int)($playerData['safety_procedures_level'] ?? 0)) ?></dd>
                    </div>
                </dl>
            </section>

            <section class="profile-card profile-market-stats" aria-labelledby="market-heading">
                <h2 id="market-heading"><img src="<?= asset('/assets/icons/profile/black-market.svg') ?>" alt="" aria-hidden="true"><?= t('profile.market_title') ?></h2>
                <dl>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/transactions.svg') ?>" alt="" aria-hidden="true"><?= t('profile.bm_transactions') ?></dt>
                        <dd><?= (int)$bmStats['total_transactions'] ?></dd>
                    </div>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/revenue.svg') ?>" alt="" aria-hidden="true"><?= t('profile.bm_revenue') ?></dt>
                        <dd class="profile-value--positive"><?= number_format((float)$bmStats['total_revenue'], 0, ',', ' ') ?> <?= $__currency ?></dd>
                    </div>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/penalty.svg') ?>" alt="" aria-hidden="true"><?= t('profile.bm_penalties') ?></dt>
                        <dd class="<?= $__penalties > 0 ? 'profile-value--negative' : '' ?>"><?= number_format($__penalties, 0, ',', ' ') ?> <?= $__currency ?></dd>
                    </div>
                    <div>
                        <dt><img src="<?= asset('/assets/icons/profile/detected.svg') ?>" alt="" aria-hidden="true"><?= t('profile.bm_detected') ?></dt>
                        <dd><?= (int)$bmStats['times_detected'] ?></dd>
                    </div>
                </dl>
            </section>

            <section class="profile-card profile-reputation" aria-labelledby="reputation-heading">
                <div class="reputation-head">
                    <h2 id="reputation-heading"><img src="<?= asset('/assets/icons/profile/trust.svg') ?>" alt="" aria-hidden="true"><?= t('profile.credibility_title') ?></h2>
                    <span class="profile-rep-badge profile-rep-badge--<?= $__level ?>"><?= t('profile.credibility_level_' . $__level) ?></span>
                </div>
                <p><?= t('profile.credibility_desc_' . $__level) ?></p>
            </section>

        </div>

        <div class="profile-content">

            <section class="profile-card profile-company" aria-labelledby="company-heading">
                <h2 id="company-heading"><img src="<?= asset('/assets/icons/profile/company.svg') ?>" alt="" aria-hidden="true"><?= t('profile.company_title') ?></h2>
                <form method="post" id="company-form">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <label for="company-name"><?= t('profile.company_label') ?></label>
                    <input id="company-name" name="company_name" type="text" value="<?= $companyName ?>" placeholder="<?= t('profile.company_placeholder') ?>" maxlength="80" autocomplete="off" required>
                    <p id="company-name-preview"><?= t('profile.name_preview_empty', ['n' => $playerId]) ?></p>
                    <button class="profile-gold-button" type="submit"><?= t('profile.company_save_btn') ?></button>
                </form>
            </section>

            <section class="profile-card profile-security" aria-labelledby="security-heading">
                <h2 id="security-heading"><img src="<?= asset('/assets/icons/profile/lock.svg') ?>" alt="" aria-hidden="true"><?= t('profile.security_title') ?></h2>
                <form method="post" id="password-form" autocomplete="off">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="password-field">
                        <label for="current-password"><?= t('profile.current_pass_label') ?></label>
                        <div class="password-input">
                            <input id="current-password" name="current_password" type="password" placeholder="<?= t('profile.current_pass_placeholder') ?>" autocomplete="current-password" required>
                            <button type="button" data-password-toggle="current-password" data-field-label="<?= t('profile.current_pass_label') ?>" data-icon-show="<?= $__eyeShow ?>" data-icon-hide="<?= $__eyeHide ?>" aria-label="<?= t('profile.toggle_show') ?> <?= t('profile.current_pass_label') ?>">
                                <img src="<?= asset('/assets/icons/profile/eye.svg') ?>" alt="" aria-hidden="true">
                            </button>
                        </div>
                    </div>

                    <div class="password-field">
                        <label for="new-password"><?= t('profile.new_pass_label') ?></label>
                        <div class="password-input">
                            <input id="new-password" name="new_password" type="password" placeholder="<?= t('profile.new_pass_placeholder') ?>" autocomplete="new-password" minlength="8" required>
                            <button type="button" data-password-toggle="new-password" data-field-label="<?= t('profile.new_pass_label') ?>" data-icon-show="<?= $__eyeShow ?>" data-icon-hide="<?= $__eyeHide ?>" aria-label="<?= t('profile.toggle_show') ?> <?= t('profile.new_pass_label') ?>">
                                <img src="<?= asset('/assets/icons/profile/eye.svg') ?>" alt="" aria-hidden="true">
                            </button>
                        </div>
                        <small id="pass-strength" role="status"></small>
                        <small><?= t('profile.new_pass_hint') ?></small>
                    </div>

                    <div class="password-field">
                        <label for="confirm-password"><?= t('profile.confirm_pass_label') ?></label>
                        <div class="password-input">
                            <input id="confirm-password" name="confirm_password" type="password" placeholder="<?= t('profile.confirm_pass_placeholder') ?>" autocomplete="new-password" required>
                            <button type="button" data-password-toggle="confirm-password" data-field-label="<?= t('profile.confirm_pass_label') ?>" data-icon-show="<?= $__eyeShow ?>" data-icon-hide="<?= $__eyeHide ?>" aria-label="<?= t('profile.toggle_show') ?> <?= t('profile.confirm_pass_label') ?>">
                                <img src="<?= asset('/assets/icons/profile/eye.svg') ?>" alt="" aria-hidden="true">
                            </button>
                        </div>
                        <small id="pass-match" role="status"></small>
                    </div>

                    <button class="profile-gold-button" type="submit"><?= t('profile.change_pass_btn') ?></button>
                </form>
            </section>

        </div>
    </div>
</div>
