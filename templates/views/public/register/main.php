<?php extract($viewData, EXTR_SKIP); ?>

<div class="register-container">
    <section class="login-card fade-in" aria-labelledby="register-heading">
        <h1 id="register-heading"> Oil Game</h1>
        <p class="login-subtitle"><?= t('register.subtitle') ?></p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif ?>
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
            <p style="text-align:center;margin-top:20px;font-size:.9rem;color:rgba(232,232,240,.55)">
                 Sprawdź folder Odebrane (i Spam) i kliknij link aktywacyjny.
            </p>
            <p style="text-align:center;margin-top:16px">
                <a href="<?= url('login') ?>" class="link-primary"> Wróć do logowania</a>
            </p>
        <?php else: ?>

        <form method="post" aria-label="<?= t('register.form_label') ?>">
            <?= CSRF::field() ?>

            <?php
            $inputId          = 'email';
            $inputName        = 'email';
            $inputLabel       = t('register.label_email');
            $inputType        = 'email';
            $inputPlaceholder = t('register.placeholder_email');
            $inputRequired    = true;
            $inputValue       = $emailVal;
            require __DIR__ . '/../../../../templates/components/form_input.php';
            ?>

            <?php
            $inputId          = 'password';
            $inputName        = 'password';
            $inputLabel       = t('register.label_password');
            $inputType        = 'password';
            $inputPlaceholder = t('register.placeholder_password');
            $inputRequired    = true;
            unset($inputValue);
            require __DIR__ . '/../../../../templates/components/form_input.php';
            ?>

            <?php
            $inputId          = 'password_confirm';
            $inputName        = 'password_confirm';
            $inputLabel       = t('register.label_password_confirm');
            $inputType        = 'password';
            $inputPlaceholder = t('register.placeholder_password_confirm');
            $inputRequired    = true;
            require __DIR__ . '/../../../../templates/components/form_input.php';
            ?>

            <!-- Regulamin — obowiązkowy -->
            <div class="form-check-group">
                <label class="form-check-label">
                    <input
                        type="checkbox"
                        name="terms_accepted"
                        class="form-check-input"
                        required
                        <?= $termsChecked ? 'checked' : '' ?>
                    >
                    <span>
                        <?= t('register.label_terms_prefix') ?>
                        <a href="<?= url('regulamin') ?>" target="_blank" class="link-primary"><?= t('register.label_terms_link') ?></a>
                        <?= t('register.label_terms_suffix') ?>
                        <span class="form-check-required">*</span>
                    </span>
                </label>
            </div>

            <!-- Newsletter — opcjonalny -->
            <div class="form-check-group">
                <label class="form-check-label">
                    <input
                        type="checkbox"
                        name="newsletter_optin"
                        class="form-check-input"
                        <?= $newsletterChecked ? 'checked' : '' ?>
                    >
                    <span><?= t('register.label_newsletter_optin') ?></span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
                 <?= t('register.btn_submit') ?>
            </button>
        </form>

        <p class="login-footer">
            <?= t('register.have_account') ?> <a href="<?= url('login') ?>" class="link-primary"><?= t('register.link_login') ?></a>
        </p>

        <aside class="starter-pack" aria-labelledby="starter-heading">
            <h3 id="starter-heading"> <?= t('register.starter_heading') ?></h3>
            <ul>
                <li> <?= t('register.starter_cash') ?></li>
                <li> <?= t('register.starter_well') ?></li>
                <li> <?= t('register.starter_storage') ?></li>
            </ul>
        </aside>
        <?php endif /* !$success */ ?>
    </section>
</div>
