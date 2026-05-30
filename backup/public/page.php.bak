<?php
require_once __DIR__ . '/../src/init.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['slug'] ?? '')));

if (!$slug) {
    http_response_code(404);
    require_once __DIR__ . '/../templates/header.php';
    echo '<div class="card"><h2>' . t('page.404_heading') . '</h2></div>';
    require_once __DIR__ . '/../templates/footer.php';
    exit();
}

$page = null;
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM static_pages WHERE slug=? AND active=1 LIMIT 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // tabela nie istnieje lub blad — 404
}

if (!$page) {
    http_response_code(404);
    $pageTitle = tPlain('page.404_title');
    require_once __DIR__ . '/../templates/header.php';
    echo '<div class="card static-page-wrap"><h2>404</h2><p>' . t('page.404_not_found', ['slug' => htmlspecialchars($slug)]) . '</p></div>';
    require_once __DIR__ . '/../templates/footer.php';
    exit();
}

$pageTitle = htmlspecialchars($page['title']) . ' – OilCorp';
require_once __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="/assets/css/static_page.css">

<div class="static-page-wrap">
    <div class="static-page-hdr">
        <span class="static-page-icon"><?= htmlspecialchars($page['icon']) ?></span>
        <h1><?= htmlspecialchars($page['title']) ?></h1>
        <p class="static-page-meta"><?= t('page.last_updated', ['date' => date('d.m.Y', strtotime($page['updated_at']))]) ?></p>
    </div>
    <div class="static-page-content">
        <?= $page['content'] ?>
    </div>
    <div class="static-page-back">
        <a href="<?= url('home') ?>" class="btn btn-secondary"><?= t('page.back_to_game') ?></a>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
