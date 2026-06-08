<?php
try {
    GameLog::info('admin_partial_header', 'Render header partial');
} catch (Throwable $e) {
    error_log('[admin/partials/header] ' . $e->getMessage());
}
?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.nav.title_prefix') ?> — <?= htmlspecialchars($pageTitle ?? t('admin.nav.title_default')) ?></title>
    <link rel="stylesheet" href="<?= asset('/assets/css/admin.css') ?>">
    <link rel="stylesheet" href="<?= asset('/assets/css/modal.css') ?>">
</head>
<body>

<?php
$self = basename($_SERVER['PHP_SELF']);
$navSections = [
    t('admin.nav.section_game') => [
        'players.php'     => ['', t('admin.nav.players')],
        'wells.php'       => ['', t('admin.nav.wells')],
        'loans.php'       => ['', t('admin.nav.loans')],
        'gm_tools.php'    => ['', t('admin.nav.gm_tools')],
        'hr.php'          => ['', t('admin.nav.hr')],
        'boardroom.php'   => ['', t('boardroom.admin_nav')],
    ],
    t('admin.nav.section_market') => [
        'market.php'       => ['', t('admin.nav.market')],
        'market_debug.php' => ['', t('admin.nav.market_debug')],
        'balance.php'      => ['', t('admin.nav.balance')],
        'incidents.php'    => ['', t('admin.nav.incidents')],
        'black_market.php' => ['', t('black_market.admin_heading')],
    ],
    t('admin.nav.section_transport') => [
        'transport.php'       => ['', t('admin.nav.transport_config')],
        'transport_loss.php'  => ['', t('admin.nav.transport_loss')],
        'pipelines.php'       => ['', t('admin.nav.pipelines')],
        'logistics_hubs.php'  => ['', t('admin.nav.logistics_hubs')],
    ],
    t('admin.nav.section_legal') => [
        'legal.php' => ['', t('admin.nav.legal')],
    ],
    t('admin.nav.section_finance') => [
        'finance.php'          => ['', t('admin.nav.finance')],
        'financial-crisis.php' => ['', t('admin.nav.financial_crisis')],
        'credibility.php'      => ['', t('admin.nav.credibility')],
    ],
    t('admin.nav.section_tools') => [
        'alerts.php'      => ['', t('admin.nav.alerts')],
        'logs.php'        => ['', t('admin.nav.logs')],
        'chat.php'        => ['', t('admin.nav.chat')],
        'news.php'        => ['', t('admin.nav.news')],
        'newsletter.php'  => ['', t('admin.nav.newsletter')],
    ],
    t('admin.nav.section_content') => [
        'admin_help.php'      => ['', t('admin.nav.admin_help')],
        'help_editor.php'     => ['', t('admin.nav.help_editor')],
        'template_editor.php' => ['', t('admin.nav.template_editor')],
    ],
];
?>

<div class="admin-layout">

<div class="admin-mobile-topbar">
    <button
        type="button"
        class="admin-mobile-burger"
        id="admin-mobile-burger"
        aria-label="<?= t('admin.nav.mobile_open') ?>"
        aria-expanded="false"
        aria-controls="admin-sidebar"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="admin-mobile-brand"><?= t('admin.nav.brand') ?></div>
</div>

<button
    type="button"
    class="admin-sidebar-overlay"
    id="admin-sidebar-overlay"
    aria-label="<?= t('admin.nav.mobile_close') ?>"
    tabindex="-1"
></button>

<aside class="admin-sidebar" id="admin-sidebar" aria-label="<?= t('admin.nav.aria_label') ?>">
    <div class="sidebar-brand"> <?= t('admin.nav.brand') ?></div>

    <nav class="sidebar-nav">
        <?php foreach ($navSections as $sectionLabel => $items): ?>
        <div class="sidebar-section">
            <span class="sidebar-section-label"><?= $sectionLabel ?></span>
            <?php foreach ($items as $file => [$icon, $label]): ?>
            <a href="/admin/<?= $file ?>"
               class="sidebar-link<?= $self === $file ? ' active' : '' ?>">
                <span class="sidebar-icon"><?= $icon ?></span>
                <span><?= $label ?></span>
            </a>
            <?php endforeach ?>
        </div>
        <?php endforeach ?>
    </nav>

    <div class="sidebar-footer">
        <span class="sidebar-user"> <?= Security::escape(AdminAuth::getAdminUsername()) ?></span>
        <a href="/" class="sidebar-link sidebar-link--muted" target="_blank" rel="noopener">
            <span class="sidebar-icon"></span><span><?= t('admin.nav.main_site') ?></span>
        </a>
        <a href="/admin/change_password.php" class="sidebar-link sidebar-link--muted">
            <span class="sidebar-icon"></span><span><?= t('admin.nav.change_password') ?></span>
        </a>
        <a href="/admin/logout.php" class="sidebar-link sidebar-link--danger">
            <span class="sidebar-icon"></span><span><?= t('admin.nav.logout') ?></span>
        </a>
    </div>
</aside>

<div class="admin-wrap">
