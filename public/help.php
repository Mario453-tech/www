<?php
require_once __DIR__ . '/../src/init.php';
Auth::requireLogin();

$pageTitle = 'Instrukcja obsługi - OilCorp';

// Pobierz sekcje z bazy; fallback = pusta tablica (wyswietli info admina)
$helpPages   = [];
$dbAvailable = false;
try {
    $db = Database::getInstance()->getConnection();
    $helpPages   = $db->query("SELECT slug, title, icon, content FROM game_help_pages WHERE active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    $dbAvailable = true;
} catch (Throwable $e) {
 // tabela moze nie istniec cicha degradacja, fallback ponizej
}

require_once __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="/assets/css/help.css">

<div class="help-wrap">

<div class="help-hero">
    <h1> Instrukcja obslugi</h1>
    <p>Wszystko co musisz wiedziec, zeby zostac naftowym magnatem. Dla zupelnych poczatkujacych.</p>
</div>

<?php if (!empty($helpPages)): ?>

<!-- Table of contents - dynamic -->
<div class="help-toc">
    <h2>Spis tresci</h2>
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
<!-- Fallback gdy baza niedostepna lub tabela nie istnieje -->
<div class="help-warn" style="margin-top:20px">
    <span class="help-tip-icon"></span>
    <p>Instrukcja obslugi jest w trakcie konfiguracji. Admin moze wypelnic tresc w <strong>Panelu Admina  Instrukcja obslugi</strong>.</p>
</div>
<?php endif ?>

<div style="text-align:center; margin-top:40px;">
    <a href="<?= url('home') ?>" class="btn btn-secondary"> Wroc do Dashboardu</a>
</div>

</div><!-- /.help-wrap -->

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
