<?php extract($viewData, EXTR_SKIP); ?>

<div class="login-container">
    <section class="login-card fade-in" aria-labelledby="reset-heading">
        <h1 id="reset-heading"> <?= t('reset_password.heading') ?></h1>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= t('reset_password.msg_success') ?></div>
            <p class="reset-login-link">
                <a href="<?= url('login') ?>" class="btn btn-primary btn-full"> <?= t('reset_password.btn_login') ?></a>
            </p>

        <?php elseif (!$tokenValid): ?>
            <div class="alert alert-error">
                <?= t('reset_password.err_token_invalid_full') ?><br>
                <?= t('reset_password.token_validity') ?>
            </div>
            <p class="reset-login-link">
                <a href="<?= url('forgot-password') ?>" class="link-primary"> <?= t('reset_password.link_resend') ?></a>
            </p>

        <?php else: ?>
            <p class="login-subtitle"><?= t('reset_password.subtitle', ['email' => htmlspecialchars($tokenData['email'] ?? '')]) ?></p>

            <?php if ($error): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif ?>

            <form method="post" aria-label="<?= t('reset_password.form_label') ?>" novalidate>
                <?= CSRF::field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label class="form-label" for="password"><?= t('reset_password.label_password') ?></label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="<?= t('reset_password.placeholder_password') ?>"
                            required
                            autocomplete="new-password"
                        >
                        <button type="button" class="btn-show-pass" onclick="togglePassword('password', this)" aria-label="<?= t('reset_password.aria_show_pass') ?>"><svg class="auth-eye-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg></button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password2"><?= t('reset_password.label_password2') ?></label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            id="password2"
                            name="password2"
                            class="form-input"
                            placeholder="<?= t('reset_password.placeholder_password2') ?>"
                            required
                            autocomplete="new-password"
                        >
                        <button type="button" class="btn-show-pass" onclick="togglePassword('password2', this)" aria-label="<?= t('reset_password.aria_show_pass') ?>"><svg class="auth-eye-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg></button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                     <?= t('reset_password.btn_submit') ?>
                </button>
            </form>
        <?php endif ?>
    </section>
</div>

<script>window.AUTH_LANG = <?= json_encode([
    'show_pass'    => tPlain('auth.show_password'),
    'hide_pass'    => tPlain('auth.hide_password'),
    'str_too_short'=> tPlain('auth_js.str_too_short'),
    'str_weak'     => tPlain('auth_js.str_weak'),
    'str_medium'   => tPlain('auth_js.str_medium'),
    'str_good'     => tPlain('auth_js.str_good'),
    'str_strong'   => tPlain('auth_js.str_strong'),
], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/assets/js/auth.js"></script>
