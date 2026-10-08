<?php
// Load site config and header nav from DB (silent fallback) / Zaladuj konfiguracje serwisu i nawigacje naglowka z bazy (cichy fallback)
$__siteName = t('header.site_name');
if ($__siteName === 'header.site_name' || $__siteName === '') {
    $__siteName = 'OilEmpire';
}
$__siteTagline = t('header.site_tagline');
$__navItems = [];

try {
    $__cfgDb = Database::getInstance()->getConnection();
    $__cfgRow = $__cfgDb->query("SELECT `key`, `value` FROM site_config")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($__cfgRow['site_name']) && $__cfgRow['site_name'] !== 'OilCorp') {
        $__siteName = $__cfgRow['site_name'];
    }
    if (!empty($__cfgRow['site_tagline']) && getLocale() === 'pl') {
        $__siteTagline = $__cfgRow['site_tagline'];
    }
    $__navItems = $__cfgDb
        ->query("SELECT * FROM nav_items WHERE active=1 AND location='header' ORDER BY sort_order ASC, id ASC")
        ->fetchAll();

 // Update last_active_at for logged-in player (online or offline detection in tick) / Aktualizuj last_active_at dla zalogowanego gracza (detekcja online lub offline w ticku)
    if (!empty($_SESSION['user_id'])) {
        try {
            $__cfgDb->prepare("
                UPDATE players
                SET last_active_at = NOW(), offline_mode = 0, offline_since = NULL
                WHERE id = ? AND (last_active_at IS NULL OR last_active_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE))
            ")->execute([(int) $_SESSION['user_id']]);
        } catch (Throwable $__actEx) {
 /* columns may not exist yet / kolumny moga jeszcze nie istniec */
        }
    }
} catch (Throwable $__cfgEx) {
    // table may not exist - fallback to default values / tabela moze nie istniec - fallback na wartosci domyslne
}

// Check cookie banner requirement before rendering head
// Sprawdz wymog banera cookies przed renderowaniem head
$__showPrivacyBanner = false;
$__privacyBannerData = null;
$__privacyCsrf       = null;
$__privacyConfig     = null;

if (!($authPage ?? false)) {
    try {
        require_once __DIR__ . '/../src/Privacy/PrivacyFeatureRegistry.php';
        $__privDb        = $__cfgDb ?? Database::getInstance()->getConnection();
        $__privSettings  = new PrivacySettingsService($__privDb);
        $__privConsent   = new PrivacyConsentService($__privDb, $__privSettings);
        $__privBannerSvc = new PrivacyBannerService($__privDb, $__privSettings);
        $__privPlayerId  = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $__privAnonToken = $__privPlayerId ? '' : PrivacyConsentService::getOrCreateAnonymousToken();

        if ($__privConsent->shouldShowBanner($__privPlayerId, $__privAnonToken)) {
            $__showPrivacyBanner = true;
            $__privacyBannerData = $__privBannerSvc->getBannerData();
            $__privacyCsrf       = CSRF::generateToken();
            $__privacyConfig     = [
                'bannerEnabled'     => (bool)$__privSettings->get('privacy.banner.enabled', true),
                'bannerVersion'     => (string)$__privSettings->get('privacy.banner.version', '1.0'),
                'policyVersion'     => (string)$__privSettings->get('privacy.cookies.policy_version', '1.0'),
                'forceReconsent'    => (bool)$__privSettings->get('privacy.banner.force_reconsent', false),
                'reconsentOnPolicy' => (bool)$__privSettings->get('privacy.cookies.reconsent_after_policy_change', true),
                'allCategories'     => ['necessary', 'preferences', 'analytics', 'marketing'],
                'csrfToken'         => $__privacyCsrf,
            ];
        }
    } catch (Throwable $__privEx) {
        if (class_exists('GameLog', false)) {
            GameLog::error('header', 'privacy banner check FAILED', $__privEx);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= t('common.html_lang') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($__siteName) ?> - <?= htmlspecialchars($__siteTagline) ?>">
    <meta name="theme-color" content="#08080f">
    <title><?= htmlspecialchars($pageTitle ?? $__siteName) ?></title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/favicon.png">
    <!-- preconnect: speeds up Google Fonts WOFF2 loading (critical for Polish latin-ext subset) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= asset('/assets/css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
    <?php if ($authPage ?? false): ?>
    <link rel="stylesheet" href="<?= asset('/assets/css/auth.css') ?>">
    <?php endif ?>
    <?php if (!empty($extraCss)): foreach ((array) $extraCss as $__css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset($__css)) ?>">
    <?php endforeach; endif; ?>
    <?php if ($__showPrivacyBanner): ?>
    <link rel="stylesheet" href="<?= asset('/assets/css/privacy.css') ?>">
    <?php endif; ?>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
    <?php if (!($authPage ?? false)): ?>
    <link rel="stylesheet" href="<?= asset('/assets/css/modal.css') ?>">
    <?php endif ?>
    <meta id="appConfig" name="application-config" content=""
          data-locale="<?= htmlspecialchars(tPlain('common.locale'), ENT_QUOTES, 'UTF-8') ?>"
          data-currency="<?= htmlspecialchars(tPlain('common.currency'), ENT_QUOTES, 'UTF-8') ?>"
          data-modal-lang="<?= htmlspecialchars((string) json_encode([
            'confirm' => t('modal.confirm'),
            'cancel' => t('modal.cancel'),
            'ok' => t('modal.ok'),
            'title_error' => t('modal.title_error'),
            'title_info' => t('modal.title_info'),
            'title_warn' => t('modal.title_warn'),
            'title_success' => t('modal.title_success'),
            'close' => t('modal.close'),
          ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
          data-open-menu="<?= htmlspecialchars(tPlain('header.open_menu'), ENT_QUOTES, 'UTF-8') ?>"
          data-close-menu="<?= htmlspecialchars(tPlain('header.close_menu'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= asset('/assets/js/modal.js') ?>"></script>
    <script src="<?= asset('/assets/js/language_switcher.js') ?>"></script>
    <script src="<?= asset('/assets/js/header.js') ?>" defer></script>
    <?php if (!($authPage ?? false)): ?><script src="<?= asset('/assets/js/game_nav.js') ?>" defer></script><?php endif ?>
    <link rel="stylesheet" href="<?= asset('/assets/css/mobile.css') ?>">
    <?php if (!($authPage ?? false)): ?><link rel="stylesheet" href="<?= asset('/assets/css/game_nav.css') ?>"><?php endif ?>
</head>
<body<?= ($authPage ?? false) ? ' class="auth-page"' : '' ?>>
<?php if ($authPage ?? false): ?>
    <div class="auth-bg">
        <?php
            $__authCurrentLocale = (string)($_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl');
            if (!in_array($__authCurrentLocale, ['pl', 'en', 'de'], true)) {
                $__authCurrentLocale = 'pl';
            }
            $__authLanguageRedirect = $_SERVER['REQUEST_URI'] ?? '/';
        ?>
        <form method="post" action="<?= url('language') ?>" class="auth-language" aria-label="<?= t('language.select_aria') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($__authLanguageRedirect, ENT_QUOTES, 'UTF-8') ?>">
            <label class="visually-hidden" for="auth-locale"><?= t('language.label') ?></label>
            <select id="auth-locale" name="locale" class="auth-language-select" data-language-switcher>
                <option value="pl"<?= $__authCurrentLocale === 'pl' ? ' selected' : '' ?>>PL</option>
                <option value="en"<?= $__authCurrentLocale === 'en' ? ' selected' : '' ?>>EN</option>
                <option value="de"<?= $__authCurrentLocale === 'de' ? ' selected' : '' ?>>DE</option>
            </select>
            <noscript>
                <button type="submit" class="btn btn-sm btn-secondary"><?= t('language.change') ?></button>
            </noscript>
        </form>
<?php else: ?>
    <div class="container">
        <header class="header header--redesign">

            <?php
 // User data / Dane uzytkownika
            $__topbarName   = '';
            $__topbarAvatar = null;
            $__statusLabel  = t('header.company_active');
            $__statusMod    = 'active';
            if (!empty($_SESSION['user_id'])) {
                try {
                    $__db  = Database::getInstance()->getConnection();
                    $__uRow = $__db->prepare("
                        SELECT p.username, p.company_name, p.avatar_path, p.status,
                               (SELECT COUNT(*) FROM wells w
                                 WHERE w.player_id = p.id
                                   AND w.status = 'active'
                                   AND w.technical_condition > 1) AS active_wells_count,
                               (SELECT COUNT(*) FROM wells w
                                 WHERE w.player_id = p.id
                                   AND w.status NOT IN ('sold','seized')) AS total_wells_count,
                               (SELECT COUNT(*) FROM wells w
                                 WHERE w.player_id = p.id
                                   AND w.status NOT IN ('sold','seized')
                                   AND (w.status IN ('broken','blowout','contaminated') OR w.technical_condition <= 1)) AS damaged_wells_count
                          FROM players p WHERE p.id = ? LIMIT 1
                    ");
                    $__uRow->execute([$_SESSION['user_id']]);
                    $__uData = $__uRow->fetch();
                    $__topbarName   = $__uData['company_name'] ?: $__uData['username'] ?: t('header.player_fallback', ['id' => (int)$_SESSION['user_id']]);
                    $__topbarAvatar = $__uData['avatar_path'] ?? null;

                    // Compute the company status from account and actual well states.
                    // Wyznacz status firmy z konta i rzeczywistych stanow odwiertow.
                    $__ps = (string)($__uData['status'] ?? 'active');
                    $__aw = (int)($__uData['active_wells_count'] ?? 0);
                    $__tw = (int)($__uData['total_wells_count'] ?? 0);
                    $__dw = (int)($__uData['damaged_wells_count'] ?? 0);
                    $__companyStatus = GameShell::companyStatusPresentation($__ps, $__aw, $__tw, $__dw);
                    $__statusLabel = $__companyStatus['label'];
                    $__statusMod = $__companyStatus['modifier'];
                    unset($__ps, $__aw, $__tw, $__dw, $__companyStatus);
                } catch (Throwable $e) {
                    $__topbarName = t('header.player_fallback', ['id' => (int)$_SESSION['user_id']]);
                }
            }

 // Filter navigation by access rights / Filtruj nawigacje po prawach dostepu 
            if (!empty($_SESSION['user_id']) && class_exists('BoardAccess', false)) {
                $__navItems = BoardAccess::filterNav(array_values($__navItems), (int) $_SESSION['user_id']);
            }

 // Split logout from nav items / Wydziel logout z listy elementow nawigacji 
            $__logoutItem    = null;
            $__filteredNav   = [];
            foreach ($__navItems as $__ni) {
                if (($__ni['url_key'] ?? '') === 'logout') {
                    $__logoutItem = $__ni;
                } else {
                    $__filteredNav[] = $__ni;
                }
            }

 // Current path (for active nav item) / Biezaca sciezka (do oznaczenia aktywnego linka) 
            $__curPath = parse_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? '/'), PHP_URL_PATH) ?: '/';
            $__groupedNav = GameNavigation::build($__filteredNav, $__curPath);
            ?>

            <!--  ROW 1: Logo + company pill + logout + burger  -->
            <div class="header-row1">

                <a href="<?= url('home') ?>" class="hdr-logo">
                    <span class="hdr-logo-icon"></span>
                    <span class="hdr-logo-text"><?= htmlspecialchars($__siteName) ?></span>
                    <small class="hdr-logo-sub"><?= htmlspecialchars($__siteTagline) ?></small>
                </a>

                <?php if (isset($_SESSION['user_id'])): ?>

                <a href="/profile" class="hdr-company-pill" title="<?= t('header.profile_title') ?>">
                    <?php if ($__topbarAvatar): ?>
                    <img src="/<?= htmlspecialchars($__topbarAvatar) ?>" class="topbar-avatar" alt="avatar">
                    <?php else: ?>
                    <span class="topbar-avatar-initials"><?= strtoupper(substr($__topbarName, 0, 1)) ?></span>
                    <?php endif ?>
                    <span class="hdr-company-name"><?= htmlspecialchars($__topbarName) ?></span>
                    <span class="hdr-company-status hdr-company-status--<?= $__statusMod ?>"><?= htmlspecialchars($__statusLabel) ?></span>
                </a>

                <?php
                    $__currentLocale = (string)($_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl');
                    if (!in_array($__currentLocale, ['pl', 'en', 'de'], true)) {
                        $__currentLocale = 'pl';
                    }
                    $__languageRedirect = $_SERVER['REQUEST_URI'] ?? '/';
                ?>
                <form method="post" action="<?= url('language') ?>" class="hdr-language" aria-label="<?= t('language.select_aria') ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($__languageRedirect, ENT_QUOTES, 'UTF-8') ?>">
                    <label class="visually-hidden" for="topbar-locale"><?= t('language.label') ?></label>
                    <select id="topbar-locale" name="locale" class="hdr-language-select" data-language-switcher>
                        <option value="pl"<?= $__currentLocale === 'pl' ? ' selected' : '' ?>>PL</option>
                        <option value="en"<?= $__currentLocale === 'en' ? ' selected' : '' ?>>EN</option>
                        <option value="de"<?= $__currentLocale === 'de' ? ' selected' : '' ?>>DE</option>
                    </select>
                    <noscript>
                        <button type="submit" class="btn btn-sm btn-secondary"><?= t('language.change') ?></button>
                    </noscript>
                </form>

                <?php if ($__logoutItem): ?>
                <a href="<?= str_starts_with($__logoutItem['url_key'] ?? '', '/') ? $__logoutItem['url_key'] : url($__logoutItem['url_key'] ?? 'logout') ?>"
                   class="btn btn-sm btn-danger hdr-logout">
                    <?= htmlspecialchars(!empty($__logoutItem['lang_key']) ? t($__logoutItem['lang_key']) : ($__logoutItem['label'] ?? t('header.logout_fallback'))) ?>
                </a>
                <?php endif ?>

                <button class="nav-burger" id="nav-burger" aria-label="<?= t('header.open_menu') ?>" aria-expanded="false" aria-controls="user-nav">
                    <span></span><span></span><span></span>
                </button>

                <?php endif ?>
            </div><!-- /.header-row1 -->

            <!--  ROW 2: Nav bar  -->
            <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="user-nav user-nav--bar game-nav" id="user-nav" aria-label="<?= t('header.nav_aria') ?>">
                <div class="game-nav__primary">
                    <a class="game-nav__item<?= in_array($__curPath, ['/', '/index.php'], true) ? ' nav-active' : '' ?>" href="<?= url('home') ?>"<?= in_array($__curPath, ['/', '/index.php'], true) ? ' aria-current="page"' : '' ?>>
                        <img src="<?= asset('/assets/img/icons/nav/dashboard.svg') ?>" alt=""><span><?= t('nav.home') ?></span>
                    </a>
                    <?php foreach ($__groupedNav as $__groupId => $__group): if (!$__group['items']) continue; ?>
                    <button class="game-nav__item game-nav__toggle<?= $__group['active'] ? ' nav-active' : '' ?>" type="button"
                            data-nav-group="<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>"
                            aria-expanded="<?= $__group['active'] ? 'true' : 'false' ?>"
                            aria-controls="nav-context-<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= asset('/assets/img/icons/nav/' . ['operations' => 'map', 'business' => 'market', 'company' => 'team'][$__groupId] . '.svg') ?>" alt="">
                        <span><?= htmlspecialchars($__group['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <?php endforeach; ?>
                    <span class="game-nav__spacer"></span>
                    <a class="game-nav__item" href="<?= url('help') ?>"><img src="<?= asset('/assets/img/icons/nav/help.svg') ?>" alt=""><span><?= t('nav.help') ?></span></a>
                    <a class="game-nav__item<?= $__curPath === '/chat' ? ' nav-active' : '' ?>" href="<?= url('chat') ?>"><span><?= t('nav.chat') ?></span></a>
                </div>
                <?php foreach ($__groupedNav as $__groupId => $__group): if (!$__group['items']) continue; ?>
                <div class="game-nav__context" id="nav-context-<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>" data-nav-panel="<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>"<?= $__group['active'] ? '' : ' hidden' ?>>
                    <?php foreach ($__group['items'] as $__link): ?>
                    <a href="<?= htmlspecialchars($__link['href'], ENT_QUOTES, 'UTF-8') ?>" class="game-nav__subitem<?= $__link['active'] ? ' nav-active' : '' ?>"<?= $__link['active'] ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($__link['label'], ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </nav>
            <?php endif ?>

        </header>

        <div class="nav-backdrop" id="nav-backdrop" aria-hidden="true"></div>
        <main class="main-content" role="main">
        <?php
 // Flash: brak dostpu do dziau (BoardAccess::require)
        if (!empty($_SESSION['board_access_denied'])):
        ?>
        <div class="alert-boardroom">
             <?= htmlspecialchars($_SESSION['board_access_denied']) ?>
            <a href="/boardroom" class="alert-boardroom__link"><?= t('header.boardroom_link') ?></a>
        </div>
        <?php
        unset($_SESSION['board_access_denied']);
        endif;

 // Globalny baner bankruta
        if (!empty($_SESSION['user_id'])) {
            try {
                $__hDb = Database::getInstance()->getConnection();
                $__hStmt = $__hDb->prepare("
                    SELECT status,
                           COALESCE(recovery_mode, 0)          AS recovery_mode,
                           COALESCE(bankruptcy_status, 'none') AS bankruptcy_status
                    FROM players WHERE id = ? LIMIT 1
                ");
                $__hStmt->execute([(int) $_SESSION['user_id']]);
                $__hRow = $__hStmt->fetch();

                if ($__hRow && (
                    (string) $__hRow['status'] === 'bankrupt'
                    || (int) $__hRow['recovery_mode'] === 1
                    || !in_array((string) $__hRow['bankruptcy_status'], ['none', 'recovered'], true)
                )):
        ?>
        <div class="header-bankruptcy-bar" role="alert">
            <span>&#9888; <strong><?= t('header.bankruptcy_strong') ?></strong> <?= t('header.bankruptcy_desc') ?></span>
            <a href="<?= url('recovery') ?>"><?= t('header.recovery_panel_link') ?></a>
        </div>
        <?php
                endif;
                unset($__hDb, $__hStmt, $__hRow);
            } catch (Throwable $__hEx) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('header', 'bankruptcy bar FAILED', $__hEx);
                }
                unset($__hEx);
            }
        }
        ?>
<?php
// ---- Baner cookies — renderowany gdy wymagany ----
if ($__showPrivacyBanner && $__privacyBannerData !== null) {
    require __DIR__ . '/../templates/views/privacy/banner.php';
    echo '<script src="' . asset('/assets/js/privacy_banner.js') . '" defer></script>';
}
?>
<?php endif // authPage ?>
