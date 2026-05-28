<?php extract($viewData, EXTR_SKIP); ?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('admin.login.page_title') ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth-page">
<div class="auth-wrap">
    <div class="auth-logo">
        <div class="auth-logo-text">Oil<span>Corp</span></div>
        <div class="auth-logo-sub"><?= t('admin.login.logo_sub') ?></div>
    </div>

    <div class="auth-card">
        <div class="auth-card-title"><?= t('admin.login.card_title') ?></div>

        <?php if ($error): ?>
        <div class="auth-alert auth-alert-err"> <?= htmlspecialchars($error) ?></div>
        <?php endif ?>
        <?php if ($success): ?>
        <div class="auth-alert auth-alert-ok"> <?= htmlspecialchars($success) ?></div>
        <?php endif ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="auth-field">
                <label class="auth-label" for="logininput"><?= t('admin.login.label_login') ?></label>
                <input class="auth-input" type="text" id="logininput" name="login"
                    value="<?= htmlspecialchars($login) ?>"
                    placeholder="<?= t('admin.login.placeholder_login') ?>"
                    autofocus required>
            </div>

            <div class="auth-field">
                <label class="auth-label" for="pwinput"><?= t('admin.login.label_password') ?></label>
                <div class="auth-pw-wrap">
                    <input class="auth-input" type="password" name="password"
                        id="pwinput" placeholder="••••••••" required>
                    <button type="button" class="auth-pw-eye" onclick="togglePw()"
                        title="<?= t('admin.login.toggle_pw_title') ?>"></button>
                </div>
            </div>

            <button type="submit" class="auth-btn"><?= t('admin.login.btn_submit') ?></button>
        </form>

        <div class="auth-links">
            <a href="/admin/forgot_password.php" class="auth-lnk"><?= t('admin.login.link_forgot') ?></a>
            <a href="/login" class="auth-lnk"> <?= t('admin.login.link_back_game') ?></a>
        </div>
    </div>

    <?php if ($hasSso): ?>
    <div class="auth-sso-box">
        <?= t('admin.login.sso_logged_in') ?>
        <a href="/admin/login.php"><?= t('admin.login.sso_click') ?></a>
        <?= t('admin.login.sso_desc') ?>
    </div>
    <?php endif ?>
</div>

<script>
function togglePw(){var i=document.getElementById('pwinput');i.type=i.type==='password'?'text':'password';}
</script>
</body>
</html>
