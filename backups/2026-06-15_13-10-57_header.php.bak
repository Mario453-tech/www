<?php
// Load site config and header nav from DB (silent fallback) / Zaladuj konfiguracje serwisu i nawigacje naglowka z bazy (cichy fallback)
$__siteName = 'OilCorp';
$__siteTagline = 'Strategiczna gra naftowa';
$__navItems = [];

try {
    $__cfgDb = Database::getInstance()->getConnection();
    $__cfgRow = $__cfgDb->query("SELECT `key`, `value` FROM site_config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $__siteName = $__cfgRow['site_name'] ?? $__siteName;
    $__siteTagline = $__cfgRow['site_tagline'] ?? $__siteTagline;
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
?>
<!DOCTYPE html>
<html lang="pl">
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
    <?php if (!empty($extraHead)) echo $extraHead; ?>
    <?php if (!($authPage ?? false)): ?>
    <link rel="stylesheet" href="<?= asset('/assets/css/modal.css') ?>">
    <?php endif ?>
    <script>
        window.APP_LOCALE = '<?= t('common.locale') ?>';
        window.APP_CURRENCY = '<?= t('common.currency') ?>';
        window.MODAL_LANG = <?= json_encode([
            'confirm' => t('modal.confirm'),
            'cancel' => t('modal.cancel'),
            'ok' => t('modal.ok'),
            'title_error' => t('modal.title_error'),
            'title_info' => t('modal.title_info'),
            'title_warn' => t('modal.title_warn'),
            'title_success' => t('modal.title_success'),
            'close' => t('modal.close'),
        ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= asset('/assets/js/modal.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('/assets/css/mobile.css') ?>">
</head>
<body<?= ($authPage ?? false) ? ' class="auth-page"' : '' ?>>
<?php if ($authPage ?? false): ?>
    <div class="auth-bg">
<?php else: ?>
    <div class="container">
        <header class="header header--redesign">

            <?php
 // User data / Dane uzytkownika 
            $__topbarName   = '';
            $__topbarAvatar = null;
            if (!empty($_SESSION['user_id'])) {
                try {
                    $__db    = Database::getInstance()->getConnection();
                    $__uRow  = $__db->prepare("SELECT username, company_name, avatar_path FROM players WHERE id=? LIMIT 1");
                    $__uRow->execute([$_SESSION['user_id']]);
                    $__uData = $__uRow->fetch();
                    $__topbarName   = $__uData['company_name'] ?: $__uData['username'] ?: ('Gracz #' . $_SESSION['user_id']);
                    $__topbarAvatar = $__uData['avatar_path'] ?? null;
                } catch (Throwable $e) {
                    $__topbarName = 'Gracz #' . $_SESSION['user_id'];
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

            if (!function_exists('__navBtn')) {
                function __navBtn(string $href, string $label, string $currentPath, string $extra = ''): string
                {
                    $targetPath  = parse_url($href, PHP_URL_PATH) ?: '/';
                    $targetNorm  = $targetPath === '/' ? '/' : rtrim($targetPath, '/');
                    $currentNorm = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
                    $isHome      = ($targetNorm === '/');
                    $active      = '';
                    if ($isHome) {
                        $active = ($currentNorm === '/' || $currentNorm === '/index.php') ? ' nav-active' : '';
                    } elseif ($currentNorm === $targetNorm || str_starts_with($currentNorm, $targetNorm . '/')) {
                        $active = ' nav-active';
                    }
                    $btnClass = 'btn-secondary';
                    if ($extra !== '') {
                        $btnClass = str_starts_with($extra, 'btn-') ? $extra : ('btn-' . $extra);
                    }
                    $cls = 'btn btn-sm ' . $btnClass . $active;
                    return '<a href="' . $href . '" class="' . $cls . '">' . $label . '</a>';
                }
            }
            ?>

            <!--  ROW 1: Logo + company pill + logout + burger  -->
            <div class="header-row1">

                <a href="<?= url('home') ?>" class="hdr-logo">
                    <span class="hdr-logo-icon"></span>
                    <span class="hdr-logo-text"><?= htmlspecialchars($__siteName) ?></span>
                    <small class="hdr-logo-sub"><?= htmlspecialchars($__siteTagline) ?></small>
                </a>

                <?php if (isset($_SESSION['user_id'])): ?>

                <a href="/profile" class="hdr-company-pill" title="Profil gracza">
                    <?php if ($__topbarAvatar): ?>
                    <img src="/<?= htmlspecialchars($__topbarAvatar) ?>" class="topbar-avatar" alt="avatar">
                    <?php else: ?>
                    <span class="topbar-avatar-initials"><?= strtoupper(substr($__topbarName, 0, 1)) ?></span>
                    <?php endif ?>
                    <span class="hdr-company-name"><?= htmlspecialchars($__topbarName) ?></span>
                    <span class="hdr-company-status">Aktywna</span>
                </a>

                <?php if ($__logoutItem): ?>
                <a href="<?= str_starts_with($__logoutItem['url_key'] ?? '', '/') ? $__logoutItem['url_key'] : url($__logoutItem['url_key'] ?? 'logout') ?>"
                   class="btn btn-sm btn-danger hdr-logout">
                    <?= $__logoutItem['icon'] ? $__logoutItem['icon'] . ' ' : ' ' ?><?= htmlspecialchars($__logoutItem['label'] ?? 'Wyloguj') ?>
                </a>
                <?php endif ?>

                <button class="nav-burger" id="nav-burger" aria-label="Otworz menu" aria-expanded="false" aria-controls="user-nav">
                    <span></span><span></span><span></span>
                </button>

                <?php endif ?>
            </div><!-- /.header-row1 -->

            <!--  ROW 2: Nav bar  -->
            <?php if (isset($_SESSION['user_id']) && !empty($__filteredNav)): ?>
            <nav class="user-nav user-nav--bar" id="user-nav" aria-label="Nawigacja uzytkownika">
                <?php
                $__prevOrder = null;
                foreach ($__filteredNav as $__ni):
 // Separator between sort_order groups (gap >= 10) / Separator miedzy grupami sort_order (odstep >= 10)
                    if ($__prevOrder !== null && ((int)$__ni['sort_order'] - $__prevOrder) >= 10):
                ?>
                <span class="nav-sep" role="separator" aria-hidden="true"></span>
                <?php
                    endif;
                    $__prevOrder = (int)$__ni['sort_order'];
                    $__niHref    = str_starts_with($__ni['url_key'], '/') ? $__ni['url_key'] : url($__ni['url_key']);
                    $__niLabel   = ($__ni['icon'] ? $__ni['icon'] . ' ' : '') . $__ni['label'];
                    $__niCss     = $__ni['css_class'] ?: '';
                    echo __navBtn($__niHref, $__niLabel, $__curPath, $__niCss);
                endforeach;
                ?>
            </nav>
            <?php endif ?>

        </header>

        <div class="nav-backdrop" id="nav-backdrop" aria-hidden="true"></div>
        <script>
        (function () {
            var burger   = document.getElementById('nav-burger');
            var nav      = document.getElementById('user-nav');
            var backdrop = document.getElementById('nav-backdrop');
            if (!burger || !nav) return;

            function openNav() {
                document.body.classList.add('nav-open');
                burger.setAttribute('aria-expanded', 'true');
                burger.setAttribute('aria-label', 'Zamknij menu');
            }
            function closeNav() {
                document.body.classList.remove('nav-open');
                burger.setAttribute('aria-expanded', 'false');
                burger.setAttribute('aria-label', 'Otworz menu');
            }
            function toggleNav() {
                document.body.classList.contains('nav-open') ? closeNav() : openNav();
            }

            burger.addEventListener('click', toggleNav);
            if (backdrop) backdrop.addEventListener('click', closeNav);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeNav();
            });

 // Close menu after nav link click / Zamknij menu po kliknieciu linka w menu 
            nav.querySelectorAll('a.btn').forEach(function (a) {
                a.addEventListener('click', closeNav);
            });
        })();
        </script>

        <!--  Event timer countdown / Licznik czasu wydarzenia  -->
        <script>
        (function () {
            'use strict';
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('trend-timer');
                if (!el) return;
                var secs = parseInt(el.getAttribute('data-seconds') || '0', 10);
                if (secs <= 0) return;
                function fmt(s) {
                    var h = Math.floor(s / 3600);
                    var m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
                    return String(h).padStart(2, '0') + ':' + m;
                }
                var iv = setInterval(function () {
                    secs--;
                    if (secs <= 0) { clearInterval(iv); el.textContent = '00:00'; return; }
                    el.textContent = fmt(secs);
                }, 1000);
            });
        })();
        </script>

        <main class="main-content" role="main">
        <?php
 // Flash: brak dostpu do dziau (BoardAccess::require)
        if (!empty($_SESSION['board_access_denied'])):
        ?>
        <div class="alert-boardroom">
             <?= htmlspecialchars($_SESSION['board_access_denied']) ?>
            <a href="/boardroom" class="alert-boardroom__link">Sala Zarzadu </a>
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
            <span>&#9888; <strong>Firma w restrukturyzacji</strong> - inwestycje i nowe kredyty zablokowane.</span>
            <a href="<?= url('recovery') ?>">Panel ratunkowy </a>
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
<?php endif // authPage ?>
