<?php
/**
 * templates/views/public/home/main.php
 * Public homepage view template.
 * Szablon widoku publicznej strony glownej.
 */

$homeLocale = (string) ($_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl');
if (!in_array($homeLocale, ['pl', 'en', 'de'], true)) {
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
    <meta name="theme-color" content="#0a0b0e">
    <title><?= t('home.meta_title') ?></title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="<?= asset('/assets/css/public_home.css') ?>">
    <script src="<?= asset('/assets/js/public_home.js') ?>" defer></script>
</head>
<body class="public-home">
<a class="home-skip" href="#main-content"><?= t('home.skip') ?></a>

<!-- Navigation bar / Pasek nawigacyjny (HTML5 header & nav) -->
<header class="home-header" data-home-header>
    <div class="home-container home-header__inner">
        <a class="home-brand" href="#home-about" aria-label="<?= t('home.logo_alt') ?>">
            <img src="<?= asset('/assets/icons/home/small/derrick.svg') ?>" width="24" height="24" alt="<?= t('home.alt_derrick_icon') ?>">
            <span><?= t('home.brand_name') ?></span>
        </a>
        <button class="home-menu-button" type="button" aria-expanded="false" aria-controls="home-primary-nav" aria-label="<?= t('home.menu_open') ?>" data-open-label="<?= t('home.menu_open') ?>" data-close-label="<?= t('home.menu_close') ?>" data-home-menu>
            <img src="<?= asset('/assets/icons/home/menu.svg') ?>" width="24" height="24" alt="<?= t('home.alt_menu_icon') ?>">
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
                    <option value="de"<?= $homeLocale === 'de' ? ' selected' : '' ?>>DE</option>
                </select>
                <noscript><button type="submit"><?= t('home.submit_ok') ?></button></noscript>
            </form>
            <a class="home-button home-button--secondary" href="<?= url('login') ?>"><?= t('home.login') ?></a>
            <a class="home-button home-button--primary" href="<?= url('register') ?>"><?= t('home.register') ?></a>
        </div>
    </div>
</header>

<main id="main-content">
    <!-- Hero section / Sekcja hero -->
    <section class="home-hero" id="home-about" aria-labelledby="home-hero-title">
        <div class="home-container">
            <div class="home-hero__topbar">
                <span class="home-hero__badge"><?= t('home.hero_badge') ?></span>
            </div>
            <div class="home-hero__grid">
                <div class="home-hero__copy">
                    <p class="home-eyebrow"><?= t('home.eyebrow') ?></p>
                    <h1 id="home-hero-title"><span><?= t('home.hero_title_lead') ?></span> <strong><?= t('home.hero_title_accent') ?></strong></h1>
                    <p class="home-hero__lead"><?= t('home.hero_lead') ?></p>
                    <div class="home-hero__actions">
                        <a class="home-button home-button--primary home-button--large" href="<?= url('register') ?>"><?= t('home.start') ?></a>
                        <a class="home-text-link" href="#home-gameplay"><?= t('home.see_gameplay') ?> <span aria-hidden="true">↓</span></a>
                    </div>
                    <p class="home-hero__note"><?= t('home.hero_note') ?></p>
                </div>

                <!-- Game preview dashboard / Podglad panelu gry (HTML5 article) -->
                <div class="home-screen home-screen--hero" role="region" aria-label="<?= t('home.hero_preview_alt') ?>">
                    <article class="home-mockup">
                        <header class="home-mockup__header">
                            <div class="home-mockup__brand">
                                <img src="<?= asset('/assets/icons/home/small/derrick.svg') ?>" width="16" height="16" alt="<?= t('home.alt_derrick_icon') ?>">
                                <span><?= t('home.brand_name') ?></span>
                            </div>
                            <div class="home-mockup__company-info">
                                <span class="home-mockup__company"><?= t('home.mockup_company') ?> <svg width="9" height="5" viewBox="0 0 9 5" fill="none" aria-hidden="true"><path d="M1 1L4.5 4.5L8 1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
                                <span class="home-mockup__time"><strong><?= t('home.mockup_day') ?></strong> <time datetime="2025-04-12T14:37">12.04.2025 14:37</time></span>
                            </div>
                        </header>
                        <nav class="home-mockup__tabs" aria-label="<?= t('home.mockup_tabs_aria') ?>">
                            <span class="is-active"><?= t('home.mockup_tab_overview') ?></span>
                            <span><?= t('home.mockup_tab_wells') ?></span>
                            <span><?= t('home.mockup_tab_logistics') ?></span>
                            <span><?= t('home.mockup_tab_finances') ?></span>
                        </nav>
                        <ul class="home-mockup__kpis" role="list">
                            <li class="home-mockup__kpi">
                                <div class="home-mockup__kpi-icon"><img src="<?= asset('/assets/icons/home/small/oil-drop.svg') ?>" width="20" height="20" alt="<?= t('home.alt_oil_drop_icon') ?>"></div>
                                <div class="home-mockup__kpi-text">
                                    <span class="home-mockup__kpi-label"><?= t('home.mockup_kpi_prod') ?></span>
                                    <strong class="home-mockup__kpi-val">1 248 bbl/h</strong>
                                    <span class="home-mockup__kpi-delta is-up">↑ +6%</span>
                                </div>
                            </li>
                            <li class="home-mockup__kpi">
                                <div class="home-mockup__kpi-icon"><img src="<?= asset('/assets/icons/home/small/coins.svg') ?>" width="20" height="20" alt="<?= t('home.alt_coins_icon') ?>"></div>
                                <div class="home-mockup__kpi-text">
                                    <span class="home-mockup__kpi-label"><?= t('home.mockup_kpi_rev') ?></span>
                                    <strong class="home-mockup__kpi-val"><?= t('home.mockup_kpi_rev_val') ?></strong>
                                    <span class="home-mockup__kpi-delta is-up">↑ +12%</span>
                                </div>
                            </li>
                            <li class="home-mockup__kpi">
                                <div class="home-mockup__kpi-icon"><img src="<?= asset('/assets/icons/home/small/derrick.svg') ?>" width="20" height="20" alt="<?= t('home.alt_derrick_icon') ?>"></div>
                                <div class="home-mockup__kpi-text">
                                    <span class="home-mockup__kpi-label"><?= t('home.mockup_kpi_wells') ?></span>
                                    <strong class="home-mockup__kpi-val">12 <span class="slash">/</span> 18</strong>
                                    <span class="home-mockup__kpi-delta is-up">↑ +1</span>
                                </div>
                            </li>
                        </ul>
                        <div class="home-mockup__body">
                            <figure class="home-mockup__chart-box">
                                <figcaption class="home-mockup__box-head">
                                    <span><?= t('home.mockup_chart_title') ?></span>
                                    <span class="unit">bbl/h</span>
                                </figcaption>
                                <div class="home-mockup__chart">
                                    <svg viewBox="0 0 320 135" preserveAspectRatio="none" class="home-chart-svg" aria-label="<?= t('home.mockup_chart_aria') ?>">
                                        <defs>
                                            <linearGradient id="chartGlow" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#c8a84b" stop-opacity="0.35"/>
                                                <stop offset="100%" stop-color="#c8a84b" stop-opacity="0.0"/>
                                            </linearGradient>
                                        </defs>
                                        <line x1="32" y1="18" x2="315" y2="18" stroke="#22252e" stroke-width="1"/>
                                        <line x1="32" y1="46" x2="315" y2="46" stroke="#22252e" stroke-width="1"/>
                                        <line x1="32" y1="74" x2="315" y2="74" stroke="#22252e" stroke-width="1"/>
                                        <line x1="32" y1="102" x2="315" y2="102" stroke="#22252e" stroke-width="1"/>
                                        <line x1="32" y1="126" x2="315" y2="126" stroke="#22252e" stroke-width="1"/>
                                        <text x="26" y="21" fill="#6a6d7c" font-size="8.5" text-anchor="end">1 600</text>
                                        <text x="26" y="49" fill="#6a6d7c" font-size="8.5" text-anchor="end">1 200</text>
                                        <text x="26" y="77" fill="#6a6d7c" font-size="8.5" text-anchor="end">800</text>
                                        <text x="26" y="105" fill="#6a6d7c" font-size="8.5" text-anchor="end">400</text>
                                        <text x="26" y="129" fill="#6a6d7c" font-size="8.5" text-anchor="end">0</text>
                                        <path d="M 36 102 Q 68 96 98 86 T 154 78 T 208 62 T 252 52 T 282 56 T 312 42 L 312 126 L 36 126 Z" fill="url(#chartGlow)"/>
                                        <path d="M 36 102 Q 68 96 98 86 T 154 78 T 208 62 T 252 52 T 282 56 T 312 42" fill="none" stroke="#d4a754" stroke-width="1.8" stroke-linecap="round"/>
                                        <circle cx="312" cy="42" r="3" fill="#f5d680"/>
                                    </svg>
                                    <div class="home-mockup__chart-dates">
                                        <span><?= t('home.mockup_date_1') ?></span><span><?= t('home.mockup_date_2') ?></span><span><?= t('home.mockup_date_3') ?></span><span><?= t('home.mockup_date_4') ?></span><span><?= t('home.mockup_date_5') ?></span><span><?= t('home.mockup_date_6') ?></span><span><?= t('home.mockup_date_7') ?></span>
                                    </div>
                                </div>
                            </figure>
                            <section class="home-mockup__table-box" aria-label="<?= t('home.mockup_wells_title') ?>">
                                <header class="home-mockup__box-head">
                                    <span><?= t('home.mockup_wells_title') ?></span>
                                </header>
                                <ul class="home-mockup__list" role="list">
                                    <li class="home-mockup__row home-mockup__row--head">
                                        <span><?= t('home.mockup_th_well') ?></span>
                                        <span><?= t('home.mockup_th_prod') ?></span>
                                        <span><?= t('home.mockup_th_cond') ?></span>
                                    </li>
                                    <li class="home-mockup__row">
                                        <span class="col-name"><span class="status-dot green"></span> <?= t('home.well_name_k12') ?></span>
                                        <span class="col-val">256 bbl/h</span>
                                        <span class="col-cond">98%</span>
                                    </li>
                                    <li class="home-mockup__row">
                                        <span class="col-name"><span class="status-dot green"></span> <?= t('home.well_name_k11') ?></span>
                                        <span class="col-val">203 bbl/h</span>
                                        <span class="col-cond">92%</span>
                                    </li>
                                    <li class="home-mockup__row">
                                        <span class="col-name"><span class="status-dot green"></span> <?= t('home.well_name_p07') ?></span>
                                        <span class="col-val">189 bbl/h</span>
                                        <span class="col-cond">88%</span>
                                    </li>
                                    <li class="home-mockup__row">
                                        <span class="col-name"><span class="status-dot green"></span> <?= t('home.well_name_s03') ?></span>
                                        <span class="col-val">176 bbl/h</span>
                                        <span class="col-cond">95%</span>
                                    </li>
                                    <li class="home-mockup__row">
                                        <span class="col-name"><span class="status-dot green"></span> <?= t('home.well_name_k09') ?></span>
                                        <span class="col-val">162 bbl/h</span>
                                        <span class="col-cond">90%</span>
                                    </li>
                                </ul>
                            </section>
                        </div>
                        <footer class="home-mockup__footer">
                            <span class="home-mockup__quote"><img src="<?= asset('/assets/icons/home/small/oilfield.svg') ?>" width="16" height="16" alt="<?= t('home.alt_oilfield_icon') ?>"> <?= t('home.mockup_quote') ?></span>
                            <span class="home-mockup__motto"><?= t('home.mockup_motto') ?></span>
                        </footer>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- Bridge divider / Mostek laczacy sekcje -->
    <div class="home-bridge" aria-hidden="true"><span></span><strong><?= t('home.bridge') ?></strong><span></span></div>

    <!-- Inside the game section / Sekcja zajrzyj do srodka -->
    <section class="home-section home-gameplay" id="home-gameplay" aria-labelledby="home-gameplay-title">
        <div class="home-container">
            <header class="home-section__heading">
                <div>
                    <h2 id="home-gameplay-title"><?= t('home.inside_title') ?></h2>
                </div>
                <p class="home-section__subtitle"><?= t('home.inside_note') ?></p>
            </header>

            <div class="home-tabs" role="tablist" aria-label="<?= t('home.tabs_aria') ?>">
                <button id="home-tab-extraction" type="button" role="tab" aria-selected="true" aria-controls="home-panel-extraction" tabindex="0" data-home-tab="extraction"><?= t('home.tab_extraction') ?></button>
                <button id="home-tab-logistics" type="button" role="tab" aria-selected="false" aria-controls="home-panel-logistics" tabindex="-1" data-home-tab="logistics"><?= t('home.tab_logistics') ?></button>
                <button id="home-tab-management" type="button" role="tab" aria-selected="false" aria-controls="home-panel-management" tabindex="-1" data-home-tab="management"><?= t('home.tab_management') ?></button>
            </div>

            <!-- Panel: Extraction / Panel wydobycia -->
            <section class="home-tab-panel" id="home-panel-extraction" role="tabpanel" aria-labelledby="home-tab-extraction" data-home-panel="extraction">
                <article class="home-feature-board">
                    <header class="home-board-head">
                        <div class="home-board-title">
                            <img src="<?= asset('/assets/icons/home/small/oil-drop.svg') ?>" width="28" height="28" alt="<?= t('home.alt_oil_drop_icon') ?>">
                            <div>
                                <strong><?= t('home.tab_extraction') ?></strong>
                                <span><?= t('home.extraction_sub') ?></span>
                            </div>
                        </div>
                        <div class="home-board-controls">
                            <div class="home-board-select">
                                <span class="label"><?= t('home.filter_field') ?></span>
                                <span class="val"><?= t('home.filter_all') ?> <svg width="9" height="5" viewBox="0 0 9 5" fill="none"><path d="M1 1L4.5 4.5L8 1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
                            </div>
                            <div class="home-board-badge">
                                <img src="<?= asset('/assets/icons/home/small/derrick.svg') ?>" width="18" height="18" alt="<?= t('home.alt_derrick_icon') ?>">
                                <span><?= t('home.extraction_active_count', ['count' => 12]) ?></span>
                            </div>
                        </div>
                    </header>
                    <div class="home-flow-section">
                        <span class="home-flow-caption"><?= t('home.extraction_chain_title') ?></span>
                        <ol class="home-flow-chain home-flow-chain--extraction">
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/oilfield.svg') ?>" width="96" height="48" alt="<?= t('home.alt_oilfield_icon') ?>">
                                <strong><?= t('home.extraction_step_deposits') ?></strong>
                                <span><?= t('home.extraction_step_deposits_sub') ?></span>
                            </li>
                            <li class="home-flow-arrow" aria-hidden="true"><img src="<?= asset('/assets/icons/home/small/arrow-right.svg') ?>" width="18" height="18" alt="<?= t('home.alt_arrow_icon') ?>"></li>
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/derrick.svg') ?>" width="96" height="48" alt="<?= t('home.alt_derrick_icon') ?>">
                                <strong><?= t('home.extraction_step_wells') ?></strong>
                                <span><?= t('home.extraction_step_wells_sub') ?></span>
                            </li>
                            <li class="home-flow-arrow" aria-hidden="true"><img src="<?= asset('/assets/icons/home/small/arrow-right.svg') ?>" width="18" height="18" alt="<?= t('home.alt_arrow_icon') ?>"></li>
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/tank-farm.svg') ?>" width="96" height="48" alt="<?= t('home.alt_tank_farm_icon') ?>">
                                <strong><?= t('home.extraction_step_tanks') ?></strong>
                                <span><?= t('home.extraction_step_tanks_sub') ?></span>
                            </li>
                        </ol>
                    </div>
                    <div class="home-board-data">
                        <span class="home-flow-caption"><?= t('home.extraction_table_title') ?></span>
                        <ul class="home-data-list home-data-list--extraction" role="list">
                            <li class="home-data-row home-data-row--head">
                                <span class="col-id"><?= t('home.th_id') ?></span>
                                <span class="col-well"><?= t('home.th_name') ?></span>
                                <span class="col-loc"><?= t('home.th_location') ?></span>
                                <span class="col-rate"><?= t('home.th_yield') ?></span>
                                <span class="col-cond"><?= t('home.th_condition') ?></span>
                                <span class="col-stat"><?= t('home.th_status') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">W-101</span>
                                <span class="col-well"><?= t('home.well_name_k12') ?></span>
                                <span class="col-loc"><?= t('home.location_eastern_carpathians') ?></span>
                                <span class="col-rate">256 bbl/h</span>
                                <span class="col-cond">98%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_operating') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">W-102</span>
                                <span class="col-well"><?= t('home.well_name_k11') ?></span>
                                <span class="col-loc"><?= t('home.location_western_pomerania') ?></span>
                                <span class="col-rate">203 bbl/h</span>
                                <span class="col-cond">92%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_operating') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">W-103</span>
                                <span class="col-well"><?= t('home.well_name_p07') ?></span>
                                <span class="col-loc"><?= t('home.location_polish_lowlands') ?></span>
                                <span class="col-rate">189 bbl/h</span>
                                <span class="col-cond">88%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_operating') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">W-104</span>
                                <span class="col-well"><?= t('home.well_name_s03') ?></span>
                                <span class="col-loc"><?= t('home.location_baltic_shelf') ?></span>
                                <span class="col-rate">176 bbl/h</span>
                                <span class="col-cond">95%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_operating') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">W-105</span>
                                <span class="col-well"><?= t('home.well_name_k09') ?></span>
                                <span class="col-loc"><?= t('home.location_fore_carpathian') ?></span>
                                <span class="col-rate">162 bbl/h</span>
                                <span class="col-cond">90%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_operating') ?></span>
                            </li>
                        </ul>
                    </div>
                </article>
                <aside class="home-feature-aside">
                    <button class="home-feature-media" type="button" data-home-preview-src="<?= asset('/assets/images/home/game-overview.png') ?>" data-home-preview-alt="<?= t('home.extraction_preview_alt') ?>" aria-label="<?= t('home.preview_open') ?>">
                        <img src="<?= asset('/assets/images/home/game-overview.png') ?>" width="1080" height="760" alt="<?= t('home.extraction_preview_alt') ?>" loading="lazy">
                        <span><?= t('home.preview_open') ?></span>
                    </button>
                    <div class="home-feature-copy">
                        <img class="home-feature-icon" src="<?= asset('/assets/icons/home/small/oil-drop.svg') ?>" width="32" height="32" alt="<?= t('home.alt_oil_drop_icon') ?>">
                        <h3><?= t('home.extraction_title') ?></h3>
                        <p class="home-feature-copy__lead"><?= t('home.extraction_description') ?></p>
                        <span class="home-gold-rule" aria-hidden="true"></span>
                        <p><?= t('home.extraction_detail') ?></p>
                    </div>
                </aside>
            </section>

            <!-- Panel: Logistics / Panel logistyki (glowny wg makiety) -->
            <section class="home-tab-panel" id="home-panel-logistics" role="tabpanel" aria-labelledby="home-tab-logistics" data-home-panel="logistics">
                <article class="home-feature-board">
                    <header class="home-board-head">
                        <div class="home-board-title">
                            <img src="<?= asset('/assets/icons/home/small/truck.svg') ?>" width="28" height="28" alt="<?= t('home.alt_truck_icon') ?>">
                            <div>
                                <strong><?= t('home.tab_logistics') ?></strong>
                                <span><?= t('home.logistics_sub') ?></span>
                            </div>
                        </div>
                        <div class="home-board-controls">
                            <div class="home-board-select">
                                <span class="label"><?= t('home.filter_region') ?></span>
                                <span class="val"><?= t('home.filter_all') ?> <svg width="9" height="5" viewBox="0 0 9 5" fill="none"><path d="M1 1L4.5 4.5L8 1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
                            </div>
                            <div class="home-board-badge">
                                <img src="<?= asset('/assets/icons/home/small/oil-tanker.svg') ?>" width="18" height="18" alt="<?= t('home.alt_oil_tanker_icon') ?>">
                                <span><?= t('home.logistics_export_ongoing', ['count' => 3]) ?></span>
                            </div>
                        </div>
                    </header>
                    <div class="home-flow-section">
                        <span class="home-flow-caption"><?= t('home.logistics_chain_title') ?></span>
                        <ol class="home-flow-chain">
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/derrick.svg') ?>" width="84" height="42" alt="<?= t('home.alt_derrick_icon') ?>">
                                <strong><?= t('home.logistics_step_wells') ?></strong>
                                <span><?= t('home.logistics_step_wells_sub') ?></span>
                            </li>
                            <li class="home-flow-arrow" aria-hidden="true"><img src="<?= asset('/assets/icons/home/small/arrow-right.svg') ?>" width="16" height="16" alt="<?= t('home.alt_arrow_icon') ?>"></li>
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/tanker-truck.svg') ?>" width="84" height="42" alt="<?= t('home.alt_tanker_truck_icon') ?>">
                                <strong><?= t('home.logistics_step_road') ?></strong>
                                <span><?= t('home.logistics_step_road_sub') ?></span>
                            </li>
                            <li class="home-flow-arrow" aria-hidden="true"><img src="<?= asset('/assets/icons/home/small/arrow-right.svg') ?>" width="16" height="16" alt="<?= t('home.alt_arrow_icon') ?>"></li>
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/tank-farm.svg') ?>" width="84" height="42" alt="<?= t('home.alt_tank_farm_icon') ?>">
                                <strong><?= t('home.logistics_step_hubs') ?></strong>
                                <span><?= t('home.logistics_step_hubs_sub') ?></span>
                            </li>
                            <li class="home-flow-arrow" aria-hidden="true"><img src="<?= asset('/assets/icons/home/small/arrow-right.svg') ?>" width="16" height="16" alt="<?= t('home.alt_arrow_icon') ?>"></li>
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/pipeline.svg') ?>" width="84" height="42" alt="<?= t('home.alt_pipeline_icon') ?>">
                                <strong><?= t('home.logistics_step_pipes') ?></strong>
                                <span><?= t('home.logistics_step_pipes_sub') ?></span>
                            </li>
                            <li class="home-flow-arrow" aria-hidden="true"><img src="<?= asset('/assets/icons/home/small/arrow-right.svg') ?>" width="16" height="16" alt="<?= t('home.alt_arrow_icon') ?>"></li>
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/oil-tanker.svg') ?>" width="84" height="42" alt="<?= t('home.alt_oil_tanker_icon') ?>">
                                <strong><?= t('home.logistics_step_marine') ?></strong>
                                <span><?= t('home.logistics_step_marine_sub') ?></span>
                            </li>
                        </ol>
                    </div>
                    <div class="home-board-data">
                        <span class="home-flow-caption"><?= t('home.logistics_table_title') ?></span>
                        <ul class="home-data-list home-data-list--logistics" role="list">
                            <li class="home-data-row home-data-row--head">
                                <span class="col-id"><?= t('home.th_id') ?></span>
                                <span class="col-route"><?= t('home.th_route') ?></span>
                                <span class="col-cargo"><?= t('home.th_cargo') ?></span>
                                <span class="col-amount"><?= t('home.th_amount') ?></span>
                                <span class="col-stat"><?= t('home.th_status') ?></span>
                                <span class="col-eta"><?= t('home.th_eta') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">T-1042</span>
                                <span class="col-route"><?= t('home.route_1') ?></span>
                                <span class="col-cargo"><?= t('home.cargo_crude_oil') ?></span>
                                <span class="col-amount">24 000 bbl</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_in_transit') ?></span>
                                <span class="col-eta">2h 14m</span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">T-1041</span>
                                <span class="col-route"><?= t('home.route_2') ?></span>
                                <span class="col-cargo"><?= t('home.cargo_crude_oil') ?></span>
                                <span class="col-amount">50 000 bbl</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_in_transit') ?></span>
                                <span class="col-eta">8h 37m</span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">T-1040</span>
                                <span class="col-route"><?= t('home.route_3') ?></span>
                                <span class="col-cargo"><?= t('home.cargo_crude_oil') ?></span>
                                <span class="col-amount">28 000 bbl</span>
                                <span class="col-stat"><span class="status-dot orange"></span> <?= t('home.status_unloading') ?></span>
                                <span class="col-eta">1h 03m</span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">T-1039</span>
                                <span class="col-route"><?= t('home.route_4') ?></span>
                                <span class="col-cargo"><?= t('home.cargo_crude_oil') ?></span>
                                <span class="col-amount">65 000 bbl</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_in_transit') ?></span>
                                <span class="col-eta">12h 21m</span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-id">T-1038</span>
                                <span class="col-route"><?= t('home.route_5') ?></span>
                                <span class="col-cargo"><?= t('home.cargo_crude_oil') ?></span>
                                <span class="col-amount">47 000 bbl</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_delivered') ?></span>
                                <span class="col-eta">—</span>
                            </li>
                        </ul>
                    </div>
                </article>
                <aside class="home-feature-aside">
                    <div class="home-feature-photo">
                        <img src="<?= asset('/assets/images/home/port-tanker.png') ?>" width="540" height="320" alt="<?= t('home.logistics_photo_alt') ?>" loading="lazy">
                    </div>
                    <div class="home-feature-copy">
                        <img class="home-feature-icon" src="<?= asset('/assets/icons/home/small/truck.svg') ?>" width="32" height="32" alt="<?= t('home.alt_truck_icon') ?>">
                        <h3><?= t('home.logistics_title') ?></h3>
                        <p class="home-feature-copy__lead"><?= t('home.logistics_description') ?></p>
                        <span class="home-gold-rule" aria-hidden="true"></span>
                        <p><?= t('home.logistics_detail') ?></p>
                    </div>
                </aside>
            </section>

            <!-- Panel: Management / Panel zarzadzania -->
            <section class="home-tab-panel" id="home-panel-management" role="tabpanel" aria-labelledby="home-tab-management" data-home-panel="management">
                <article class="home-feature-board">
                    <header class="home-board-head">
                        <div class="home-board-title">
                            <img src="<?= asset('/assets/icons/home/small/coins.svg') ?>" width="28" height="28" alt="<?= t('home.alt_coins_icon') ?>">
                            <div>
                                <strong><?= t('home.tab_management') ?></strong>
                                <span><?= t('home.mgmt_sub') ?></span>
                            </div>
                        </div>
                        <div class="home-board-controls">
                            <div class="home-board-select">
                                <span class="label"><?= t('home.filter_area') ?></span>
                                <span class="val"><?= t('home.filter_board_finances') ?> <svg width="9" height="5" viewBox="0 0 9 5" fill="none"><path d="M1 1L4.5 4.5L8 1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
                            </div>
                            <div class="home-board-badge">
                                <img src="<?= asset('/assets/icons/home/small/status-dot.svg') ?>" width="18" height="18" alt="<?= t('home.alt_status_dot_icon') ?>">
                                <span><?= t('home.mgmt_rating', ['rating' => 'AAA']) ?></span>
                            </div>
                        </div>
                    </header>
                    <div class="home-flow-section">
                        <span class="home-flow-caption"><?= t('home.mgmt_chain_title') ?></span>
                        <ol class="home-flow-chain home-flow-chain--extraction">
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/oilfield.svg') ?>" width="96" height="48" alt="<?= t('home.alt_oilfield_icon') ?>">
                                <strong><?= t('home.mgmt_step_board') ?></strong>
                                <span><?= t('home.mgmt_step_board_sub') ?></span>
                            </li>
                            <li class="home-flow-arrow" aria-hidden="true"><img src="<?= asset('/assets/icons/home/small/arrow-right.svg') ?>" width="18" height="18" alt="<?= t('home.alt_arrow_icon') ?>"></li>
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/pipeline.svg') ?>" width="96" height="48" alt="<?= t('home.alt_pipeline_icon') ?>">
                                <strong><?= t('home.mgmt_step_invest') ?></strong>
                                <span><?= t('home.mgmt_step_invest_sub') ?></span>
                            </li>
                            <li class="home-flow-arrow" aria-hidden="true"><img src="<?= asset('/assets/icons/home/small/arrow-right.svg') ?>" width="18" height="18" alt="<?= t('home.alt_arrow_icon') ?>"></li>
                            <li class="home-flow-node">
                                <img src="<?= asset('/assets/icons/home/large/global-terminal.svg') ?>" width="96" height="48" alt="<?= t('home.alt_global_terminal_icon') ?>">
                                <strong><?= t('home.mgmt_step_expansion') ?></strong>
                                <span><?= t('home.mgmt_step_expansion_sub') ?></span>
                            </li>
                        </ol>
                    </div>
                    <div class="home-board-data">
                        <span class="home-flow-caption"><?= t('home.mgmt_table_title') ?></span>
                        <ul class="home-data-list home-data-list--mgmt" role="list">
                            <li class="home-data-row home-data-row--head">
                                <span class="col-cat"><?= t('home.th_category') ?></span>
                                <span class="col-item"><?= t('home.th_item') ?></span>
                                <span class="col-val"><?= t('home.th_value') ?></span>
                                <span class="col-dyn"><?= t('home.th_dynamics') ?></span>
                                <span class="col-stat"><?= t('home.th_status') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-cat"><?= t('home.mgmt_cat_capital') ?></span>
                                <span class="col-item"><?= t('home.mgmt_item_liquid') ?></span>
                                <span class="col-val"><?= t('home.mgmt_val_liquid') ?></span>
                                <span class="col-dyn">+14.2%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_stable') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-cat"><?= t('home.mgmt_cat_revenue') ?></span>
                                <span class="col-item"><?= t('home.mgmt_item_turnover') ?></span>
                                <span class="col-val"><?= t('home.mgmt_val_turnover') ?></span>
                                <span class="col-dyn">+12.0%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_growth') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-cat"><?= t('home.mgmt_cat_staff') ?></span>
                                <span class="col-item"><?= t('home.mgmt_item_execs') ?></span>
                                <span class="col-val"><?= t('home.mgmt_val_execs') ?></span>
                                <span class="col-dyn">100%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_complete') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-cat"><?= t('home.mgmt_cat_market') ?></span>
                                <span class="col-item"><?= t('home.mgmt_item_sales') ?></span>
                                <span class="col-val">1 200 bbl/h</span>
                                <span class="col-dyn">+8.5%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_active') ?></span>
                            </li>
                            <li class="home-data-row">
                                <span class="col-cat"><?= t('home.mgmt_cat_loans') ?></span>
                                <span class="col-item"><?= t('home.mgmt_item_debt') ?></span>
                                <span class="col-val"><?= t('home.mgmt_val_debt') ?></span>
                                <span class="col-dyn">0%</span>
                                <span class="col-stat"><span class="status-dot green"></span> <?= t('home.status_clean_balance') ?></span>
                            </li>
                        </ul>
                    </div>
                </article>
                <aside class="home-feature-aside">
                    <button class="home-feature-media" type="button" data-home-preview-src="<?= asset('/assets/images/home/game-management.png') ?>" data-home-preview-alt="<?= t('home.management_preview_alt') ?>" aria-label="<?= t('home.preview_open') ?>">
                        <img src="<?= asset('/assets/images/home/game-management.png') ?>" width="1080" height="760" alt="<?= t('home.management_preview_alt') ?>" loading="lazy">
                        <span><?= t('home.preview_open') ?></span>
                    </button>
                    <div class="home-feature-copy">
                        <img class="home-feature-icon" src="<?= asset('/assets/icons/home/small/coins.svg') ?>" width="32" height="32" alt="<?= t('home.alt_coins_icon') ?>">
                        <h3><?= t('home.management_title') ?></h3>
                        <p class="home-feature-copy__lead"><?= t('home.management_description') ?></p>
                        <span class="home-gold-rule" aria-hidden="true"></span>
                        <p><?= t('home.management_detail') ?></p>
                    </div>
                </aside>
            </section>
        </div>
    </section>

    <!-- Growth steps / Etapy rozwoju firmy -->
    <section class="home-section home-growth" id="home-growth" aria-labelledby="home-growth-title">
        <div class="home-container">
            <header class="home-section__heading home-section__heading--line">
                <h2 id="home-growth-title"><?= t('home.growth_title') ?></h2>
            </header>
            <ol class="home-steps">
                <li>
                    <div class="home-step-head">
                        <span class="home-step-number">01</span>
                        <h3><?= t('home.step_one_title') ?></h3>
                    </div>
                    <div class="home-step-art">
                        <img src="<?= asset('/assets/icons/home/large/oilfield.svg') ?>" width="144" height="72" alt="<?= t('home.alt_oilfield_icon') ?>">
                    </div>
                    <p><?= t('home.step_one_text') ?></p>
                </li>
                <li>
                    <div class="home-step-head">
                        <span class="home-step-number">02</span>
                        <h3><?= t('home.step_two_title') ?></h3>
                    </div>
                    <div class="home-step-art">
                        <img src="<?= asset('/assets/icons/home/large/road-network.svg') ?>" width="144" height="72" alt="<?= t('home.alt_road_network_icon') ?>">
                    </div>
                    <p><?= t('home.step_two_text') ?></p>
                </li>
                <li>
                    <div class="home-step-head">
                        <span class="home-step-number">03</span>
                        <h3><?= t('home.step_three_title') ?></h3>
                    </div>
                    <div class="home-step-art">
                        <img src="<?= asset('/assets/icons/home/large/global-terminal.svg') ?>" width="144" height="72" alt="<?= t('home.alt_global_terminal_icon') ?>">
                    </div>
                    <p><?= t('home.step_three_text') ?></p>
                </li>
            </ol>
        </div>
    </section>

    <!-- Call to action / Dolne wezwanie do dzialania -->
    <section class="home-cta" aria-labelledby="home-cta-title">
        <div class="home-container home-cta__inner">
            <div class="home-cta__copy">
                <h2 id="home-cta-title"><?= t('home.cta_title') ?></h2>
                <p><?= t('home.cta_text') ?></p>
            </div>
            <div class="home-cta__actions">
                <a class="home-button home-button--primary home-button--large" href="<?= url('register') ?>"><?= t('home.register') ?></a>
                <p class="home-cta__login"><?= t('home.have_account') ?> <a href="<?= url('login') ?>"><?= t('home.login') ?></a></p>
            </div>
            <div class="home-cta__watermark" aria-hidden="true">
                <span><?= t('home.cta_watermark_lead') ?></span>
                <strong><?= t('home.cta_watermark_sub') ?></strong>
            </div>
        </div>
    </section>
</main>

<!-- Footer / Stopka (HTML5 footer) -->
<footer class="home-footer">
    <div class="home-container home-footer__inner">
        <div class="home-footer__brand-col">
            <a class="home-brand" href="#home-about" aria-label="<?= t('home.logo_alt') ?>">
                <img src="<?= asset('/assets/icons/home/small/derrick.svg') ?>" width="24" height="24" alt="<?= t('home.alt_derrick_icon') ?>">
                <span><?= t('home.brand_name') ?></span>
            </a>
            <p class="home-footer__tagline"><?= t('home.footer_tagline') ?></p>
        </div>
        <nav class="home-footer__nav" aria-label="<?= t('home.nav_aria') ?>">
            <a href="/regulamin"><?= t('home.terms') ?></a>
            <span class="home-footer__sep" aria-hidden="true">·</span>
            <a href="/privacy-policy.php"><?= t('home.privacy') ?></a>
            <span class="home-footer__sep" aria-hidden="true">·</span>
            <a href="mailto:kontakt@oilempire.pl"><?= t('home.contact') ?></a>
            <span class="home-footer__rule" aria-hidden="true">—</span>
        </nav>
        <div class="home-footer__energy">
            <?= nl2br(htmlspecialchars(t('home.footer_energy'), ENT_QUOTES, 'UTF-8')) ?>
        </div>
    </div>
</footer>

<!-- Screenshot preview dialog / Dialog powiekszenia zrzutu (HTML5 dialog) -->
<dialog class="home-preview-dialog" id="home-screenshot-dialog" aria-labelledby="home-preview-title">
    <div class="home-preview-dialog__bar">
        <h2 id="home-preview-title"><?= t('home.preview_open') ?></h2>
        <button type="button" data-home-preview-close aria-label="<?= t('home.close_preview') ?>">
            <img src="<?= asset('/assets/icons/home/close.svg') ?>" width="24" height="24" alt="<?= t('home.alt_close_icon') ?>">
        </button>
    </div>
    <img data-home-preview-image src="" alt="<?= t('home.preview_modal_alt') ?>">
</dialog>
</body>
</html>
