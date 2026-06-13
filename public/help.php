<?php
require_once __DIR__ . '/../src/init.php';
Auth::requireLogin();

$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$pageTitle = $locale === 'en' ? 'Help - OilCorp' : 'Instrukcja obslugi - OilCorp';
$heroTitle = $locale === 'en' ? 'Help' : 'Instrukcja obslugi';
$heroSubtitle = $locale === 'en'
    ? 'Everything you need to know to become an oil magnate. For complete beginners.'
    : 'Wszystko co musisz wiedziec, zeby zostac naftowym magnatem. Dla zupelnych poczatkujacych.';
$tocTitle = $locale === 'en' ? 'Table of contents' : 'Spis tresci';
$fallbackMessage = $locale === 'en'
    ? 'The help section is still being configured. An admin can fill in the content in <strong>Admin Panel -> Help</strong>.'
    : 'Instrukcja obslugi jest w trakcie konfiguracji. Admin moze wypelnic tresc w <strong>Panelu Admina -> Instrukcja obslugi</strong>.';
$backLabel = $locale === 'en' ? 'Back to Dashboard' : 'Wroc do Dashboardu';

// Fetch sections from the database; fallback to an empty array.
$helpPages = [];
try {
    $db = Database::getInstance()->getConnection();
    $helpPages = $db->query("SELECT slug, title, icon, content FROM game_help_pages WHERE active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Throwable $e) {
    // The table may not exist yet; keep the fallback below.
}

require_once __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="/assets/css/help.css">

<div class="help-wrap">

<div class="help-hero">
    <h1><?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
</div>

<?php if (!empty($helpPages)): ?>

<!-- Table of contents - dynamic. -->
<div class="help-toc">
    <h2><?= htmlspecialchars($tocTitle, ENT_QUOTES, 'UTF-8') ?></h2>
    <ul>
        <?php foreach ($helpPages as $p): ?>
        <li><a href="#<?= htmlspecialchars($p['slug']) ?>"><?= htmlspecialchars($p['icon'] . ' ' . $p['title']) ?></a></li>
        <?php endforeach ?>
    </ul>
</div>

<!-- Sections - DB content rendered as raw HTML; admin is responsible for safety. -->
<?php foreach ($helpPages as $p): ?>
<div class="help-section" id="<?= htmlspecialchars($p['slug']) ?>">
    <div class="help-section-hdr">
        <span class="help-section-icon"><?= htmlspecialchars($p['icon']) ?></span>
        <h2><?= htmlspecialchars($p['title']) ?></h2>
    </div>
    <div class="help-content">
        <?= $p['content'] ?>
    </div>
</div>
<?php endforeach ?>

<?php else: ?>
<!-- Fallback when the database is unavailable or the table does not exist. -->
<div class="help-warn" style="margin-top:20px">
    <span class="help-tip-icon"></span>
    <p><?= $fallbackMessage ?></p>
</div>
<?php endif ?>

<div style="text-align:center; margin-top:40px;">
    <a href="<?= url('home') ?>" class="btn btn-secondary"><?= htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8') ?></a>
</div>

</div><!-- /.help-wrap -->

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
