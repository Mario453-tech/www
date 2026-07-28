<?php
require_once __DIR__ . '/../src/init.php';
Auth::requireLogin();

$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$pageTitle = t('help.page_title');
$heroTitle = t('help.hero_title');
$heroSubtitle = t('help.hero_subtitle');
$tocTitle = t('help.toc_title');
$fallbackMessage = t('help.fallback_message');
$backLabel = t('help.back_label');

// Fetch sections from the database; fallback to an empty array. / Pobierz sekcje z bazy danych; uzyj pustej tablicy awaryjnie.
$helpPages = [];
try {
    $db = Database::getInstance()->getConnection();
    // Use an error-tolerant query that checks if columns exist, or simply query the new columns. / Uzyj zapytania odpornego na bledy dla nowej struktury.
    try {
        $helpPages = $db->query("SELECT slug, title, title_en, icon, content, content_en FROM game_help_pages WHERE active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    } catch (PDOException $e) {
        // Fallback for when the columns are not yet added / Awaryjne wczytywanie jesli kolumny nie zostaly jeszcze dodane
        $helpPages = $db->query("SELECT slug, title, icon, content FROM game_help_pages WHERE active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    }
} catch (Throwable $e) {
    // The table may not exist yet; keep the fallback below. / Tabela moze jeszcze nie istniec; zachowaj powyzszy fallback.
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

<!-- Table of contents - dynamic. / Spis tresci - dynamiczny. -->
<div class="help-toc">
    <h2><?= htmlspecialchars($tocTitle, ENT_QUOTES, 'UTF-8') ?></h2>
    <ul>
        <?php foreach ($helpPages as $p): 
            $displayTitle = ($locale === 'en' && !empty($p['title_en'])) ? $p['title_en'] : $p['title'];
        ?>
        <li><a href="#<?= htmlspecialchars($p['slug']) ?>"><?= htmlspecialchars($p['icon'] . ' ' . $displayTitle) ?></a></li>
        <?php endforeach ?>
    </ul>
</div>

<!-- Sections - DB content rendered as raw HTML; admin is responsible for safety. / Sekcje - tresc DB renderowana jako surowy HTML; admin odpowiada za bezpieczenstwo. -->
<?php foreach ($helpPages as $p): 
    $displayTitle = ($locale === 'en' && !empty($p['title_en'])) ? $p['title_en'] : $p['title'];
    $displayContent = ($locale === 'en' && !empty($p['content_en'])) ? $p['content_en'] : $p['content'];
?>
<div class="help-section" id="<?= htmlspecialchars($p['slug']) ?>">
    <div class="help-section-hdr">
        <span class="help-section-icon"><?= htmlspecialchars($p['icon']) ?></span>
        <h2><?= htmlspecialchars($displayTitle) ?></h2>
    </div>
    <div class="help-content">
        <?= $displayContent ?>
    </div>
</div>
<?php endforeach ?>

<?php else: ?>
<!-- Fallback when the database is unavailable or the table does not exist. / Awaryjne zachowanie gdy baza nie odpowiada lub tabela nie istnieje. -->
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
