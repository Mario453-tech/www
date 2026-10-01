<?php
$homeLocale = (string) ($_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl');
if (!in_array($homeLocale, ['pl', 'en'], true)) {
    $homeLocale = 'pl';
}
$homeRedirect = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!doctype html>
<html lang="<?= t('common.html_lang') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= t('home.meta_description') ?>">
    <meta name="theme-color" content="#0b0c10">
    <title><?= t('home.meta_title') ?></title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="<?= asset('/assets/css/public_home.css') ?>">
    <script src="<?= asset('/assets/js/public_home.js') ?>" defer></script>
</head>
<body class="public-home">
<a class="home-skip" href="#main-content"><?= t('home.skip') ?></a>
<header class="home-header" data-home-header>
    <div class="home-container home-header__inner">
        <a class="home-brand" href="#home-about" aria-label="<?= t('home.logo_alt') ?>">
            <img src="<?= asset('/assets/icons/home/oil-derrick.svg') ?>" width="32" height="32" alt="">
            <span>OIL EMPIRE</span>
        </a>
        <button class="home-menu-button" type="button" aria-expanded="false" aria-controls="home-primary-nav" aria-label="<?= t('home.menu_open') ?>" data-open-label="<?= t('home.menu_open') ?>" data-close-label="<?= t('home.menu_close') ?>" data-home-menu>
            <img src="<?= asset('/assets/icons/home/menu.svg') ?>" width="24" height="24" alt="">
        </button>
        <nav class="home-nav" id="home-primary-nav" aria-label="<?= t('home.nav_aria') ?>" data-home-nav>
            <a href="#home-about"><?= t('home.nav_about') ?></a>
            <a href="#home-gameplay"><?= t('home.nav_gameplay') ?></a>
            <a href="#home-growth"><?= t('home.nav_growth') ?></a>
        </nav>
        <div class="home-header__actions">
            <form class="home-language" method="post" action="<?= url('language') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($homeRedirect, ENT_QUOTES, 'UTF-8') ?>">
                <label class="home-visually-hidden" for="home-locale"><?= t('home.language') ?></label>
                <select id="home-locale" name="locale" aria-label="<?= t('home.language') ?>" data-home-language>
                    <option value="pl"<?= $homeLocale === 'pl' ? ' selected' : '' ?>>PL</option>
                    <option value="en"<?= $homeLocale === 'en' ? ' selected' : '' ?>>EN</option>
                </select>
                <noscript><button type="submit">OK</button></noscript>
            </form>
            <a class="home-button home-button--secondary" href="<?= url('login') ?>"><?= t('home.login') ?></a>
            <a class="home-button home-button--primary" href="<?= url('register') ?>"><?= t('home.register') ?></a>
        </div>
    </div>
</header>
<main id="main-content">
    <section class="home-hero" id="home-about" aria-labelledby="home-hero-title">
        <div class="home-container home-hero__grid">
            <div class="home-hero__copy">
                <p class="home-eyebrow"><?= t('home.eyebrow') ?></p>
                <h1 id="home-hero-title"><span><?= t('home.hero_title_lead') ?></span><strong><?= t('home.hero_title_accent') ?></strong></h1>
                <p class="home-hero__lead"><?= t('home.hero_lead') ?></p>
                <div class="home-hero__actions">
                    <a class="home-button home-button--primary home-button--large" href="<?= url('register') ?>"><?= t('home.start') ?></a>
                    <a class="home-text-link" href="#home-gameplay"><?= t('home.see_gameplay') ?> <span aria-hidden="true">↓</span></a>
                </div>
                <p class="home-hero__note"><?= t('home.hero_note') ?></p>
            </div>
            <button class="home-screen home-screen--hero" type="button" data-home-preview-src="<?= asset('/assets/images/home/game-overview.png') ?>" data-home-preview-alt="<?= t('home.hero_preview_alt') ?>" aria-label="<?= t('home.preview_open') ?>">
                <span class="home-screen__bar" aria-hidden="true"><span class="home-screen__brand"><img src="<?= asset('/assets/icons/home/oil-derrick.svg') ?>" width="18" height="18" alt=""> OIL EMPIRE</span><span class="home-screen__status">●</span></span>
                <img src="<?= asset('/assets/images/home/game-overview.png') ?>" width="880" height="820" alt="<?= t('home.hero_preview_alt') ?>">
                <span class="home-screen__open"><?= t('home.preview_open') ?></span>
            </button>
        </div>
    </section>
    <div class="home-bridge" aria-hidden="true"><span></span><strong><?= t('home.bridge') ?></strong><span></span></div>
    <section class="home-section home-gameplay" id="home-gameplay" aria-labelledby="home-gameplay-title">
        <div class="home-container">
            <header class="home-section__heading"><h2 id="home-gameplay-title"><?= t('home.inside_title') ?></h2><p><?= t('home.inside_note') ?></p></header>
            <div class="home-tabs" role="tablist" aria-label="<?= t('home.tabs_aria') ?>">
                <button id="home-tab-extraction" type="button" role="tab" aria-selected="true" aria-controls="home-panel-extraction" tabindex="0" data-home-tab="extraction"><?= t('home.tab_extraction') ?></button>
                <button id="home-tab-logistics" type="button" role="tab" aria-selected="false" aria-controls="home-panel-logistics" tabindex="-1" data-home-tab="logistics"><?= t('home.tab_logistics') ?></button>
                <button id="home-tab-management" type="button" role="tab" aria-selected="false" aria-controls="home-panel-management" tabindex="-1" data-home-tab="management"><?= t('home.tab_management') ?></button>
            </div>
            <section class="home-tab-panel" id="home-panel-extraction" role="tabpanel" aria-labelledby="home-tab-extraction" data-home-panel="extraction">
                <div class="home-logistics-diagram home-extraction-diagram" role="img" aria-label="<?= t('home.extraction_diagram') ?>">
                    <div class="home-logistics-diagram__title"><img src="<?= asset('/assets/icons/home/pumpjack.svg') ?>" width="30" height="30" alt=""><strong><?= t('home.tab_extraction') ?></strong></div>
                    <div class="home-logistics-flow">
                        <span><img src="<?= asset('/assets/icons/home/oil-derrick.svg') ?>" width="48" height="48" alt=""><b><?= t('home.extraction_wells') ?></b></span><i aria-hidden="true">→</i>
                        <span><img src="<?= asset('/assets/icons/home/pumpjack.svg') ?>" width="48" height="48" alt=""><b><?= t('home.extraction_condition') ?></b></span><i aria-hidden="true">→</i>
                        <span><img src="<?= asset('/assets/icons/home/storage-tank.svg') ?>" width="48" height="48" alt=""><b><?= t('home.extraction_storage') ?></b></span>
                    </div>
                    <p><?= t('home.preview_unavailable') ?></p>
                </div>
                <div class="home-feature-copy"><img class="home-feature-icon" src="<?= asset('/assets/icons/home/pumpjack.svg') ?>" width="48" height="48" alt=""><h3><?= t('home.extraction_title') ?></h3><p class="home-feature-copy__lead"><?= t('home.extraction_description') ?></p><span class="home-gold-rule" aria-hidden="true"></span><p><?= t('home.extraction_detail') ?></p></div>
            </section>
            <section class="home-tab-panel" id="home-panel-logistics" role="tabpanel" aria-labelledby="home-tab-logistics" data-home-panel="logistics">
                <div class="home-logistics-diagram" role="img" aria-label="<?= t('home.logistics_diagram') ?>">
                    <div class="home-logistics-diagram__title"><img src="<?= asset('/assets/icons/home/tanker-truck.svg') ?>" width="30" height="30" alt=""><strong><?= t('home.tab_logistics') ?></strong></div>
                    <div class="home-logistics-flow">
                        <span><img src="<?= asset('/assets/icons/home/oil-derrick.svg') ?>" width="42" height="42" alt=""><b><?= t('home.logistics_well') ?></b></span><i aria-hidden="true">→</i>
                        <span><img src="<?= asset('/assets/icons/home/tanker-truck.svg') ?>" width="42" height="42" alt=""><b><?= t('home.logistics_road') ?></b></span><i aria-hidden="true">→</i>
                        <span><img src="<?= asset('/assets/icons/home/hub.svg') ?>" width="42" height="42" alt=""><b><?= t('home.logistics_hub') ?></b></span><i aria-hidden="true">→</i>
                        <span><img src="<?= asset('/assets/icons/home/pipeline.svg') ?>" width="42" height="42" alt=""><b><?= t('home.logistics_pipe') ?></b></span><i aria-hidden="true">→</i>
                        <span><img src="<?= asset('/assets/icons/home/oil-tanker.svg') ?>" width="42" height="42" alt=""><b><?= t('home.logistics_ship') ?></b></span>
                    </div>
                    <p><?= t('home.preview_unavailable') ?></p>
                </div>
                <div class="home-feature-copy"><img class="home-feature-icon" src="<?= asset('/assets/icons/home/tanker-truck.svg') ?>" width="48" height="48" alt=""><h3><?= t('home.logistics_title') ?></h3><p class="home-feature-copy__lead"><?= t('home.logistics_description') ?></p><span class="home-gold-rule" aria-hidden="true"></span><p><?= t('home.logistics_detail') ?></p></div>
            </section>
            <section class="home-tab-panel" id="home-panel-management" role="tabpanel" aria-labelledby="home-tab-management" data-home-panel="management">
                <button class="home-feature-media" type="button" data-home-preview-src="<?= asset('/assets/images/home/game-management.png') ?>" data-home-preview-alt="<?= t('home.management_preview_alt') ?>" aria-label="<?= t('home.preview_open') ?>">
                    <img src="<?= asset('/assets/images/home/game-management.png') ?>" width="1080" height="760" alt="<?= t('home.management_preview_alt') ?>"><span><?= t('home.preview_open') ?></span>
                </button>
                <div class="home-feature-copy"><img class="home-feature-icon" src="<?= asset('/assets/icons/home/briefcase.svg') ?>" width="48" height="48" alt=""><h3><?= t('home.management_title') ?></h3><p class="home-feature-copy__lead"><?= t('home.management_description') ?></p><span class="home-gold-rule" aria-hidden="true"></span><p><?= t('home.management_detail') ?></p></div>
            </section>
        </div>
    </section>
    <section class="home-section home-growth" id="home-growth" aria-labelledby="home-growth-title">
        <div class="home-container">
            <header class="home-section__heading home-section__heading--line"><h2 id="home-growth-title"><?= t('home.growth_title') ?></h2></header>
            <ol class="home-steps">
                <li><span class="home-step-number">01</span><img src="<?= asset('/assets/icons/home/oil-derrick.svg') ?>" width="58" height="58" alt=""><div><h3><?= t('home.step_one_title') ?></h3><p><?= t('home.step_one_text') ?></p></div></li>
                <li><span class="home-step-number">02</span><img src="<?= asset('/assets/icons/home/tanker-truck.svg') ?>" width="58" height="58" alt=""><div><h3><?= t('home.step_two_title') ?></h3><p><?= t('home.step_two_text') ?></p></div></li>
                <li><span class="home-step-number">03</span><img src="<?= asset('/assets/icons/home/globe.svg') ?>" width="58" height="58" alt=""><div><h3><?= t('home.step_three_title') ?></h3><p><?= t('home.step_three_text') ?></p></div></li>
            </ol>
        </div>
    </section>
    <section class="home-cta" aria-labelledby="home-cta-title">
        <div class="home-container home-cta__inner"><div><h2 id="home-cta-title"><?= t('home.cta_title') ?></h2><p><?= t('home.cta_text') ?></p></div><a class="home-button home-button--primary home-button--large" href="<?= url('register') ?>"><?= t('home.register') ?></a><p><?= t('home.have_account') ?> <a href="<?= url('login') ?>"><?= t('home.login') ?></a></p></div>
    </section>
</main>
<footer class="home-footer">
    <div class="home-container home-footer__inner">
        <a class="home-brand" href="#home-about" aria-label="<?= t('home.logo_alt') ?>"><img src="<?= asset('/assets/icons/home/oil-derrick.svg') ?>" width="32" height="32" alt=""><span>OIL EMPIRE</span></a>
        <p><?= t('home.footer_tagline') ?></p>
        <nav aria-label="<?= t('home.nav_aria') ?>"><a href="/regulamin"><?= t('home.terms') ?></a><a href="/privacy-policy.php"><?= t('home.privacy') ?></a></nav>
    </div>
</footer>
<dialog class="home-preview-dialog" id="home-screenshot-dialog" aria-labelledby="home-preview-title">
    <div class="home-preview-dialog__bar"><h2 id="home-preview-title"><?= t('home.preview_open') ?></h2><button type="button" data-home-preview-close aria-label="<?= t('home.close_preview') ?>"><img src="<?= asset('/assets/icons/home/close.svg') ?>" width="24" height="24" alt=""></button></div>
    <img data-home-preview-image src="" alt="">
</dialog>
</body>
</html>
