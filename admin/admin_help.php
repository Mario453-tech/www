<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/admin_help.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

// Ensure table exists (same as game_help_pages but for admin docs)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `admin_help_pages` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `slug`       VARCHAR(64) NOT NULL UNIQUE,
        `title`      VARCHAR(255) NOT NULL,
        `icon`       VARCHAR(16) NOT NULL DEFAULT '',
        `content`    MEDIUMTEXT NOT NULL,
        `sort_order` SMALLINT NOT NULL DEFAULT 0,
        `active`     TINYINT(1) NOT NULL DEFAULT 1,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` VARCHAR(64) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    GameLog::error('admin/admin_help', 'CREATE TABLE failed', $e);
}

// Seed default sections if empty
$count = (int)$db->query("SELECT COUNT(*) FROM admin_help_pages")->fetchColumn();
if ($count === 0) {
    $defaults = [
        ['gracze',      '', 'Gracze',                0,
            '<h3>Sekcja Gracze</h3><p>Lista wszystkich zarejestrowanych graczy. Moesz przeglda dane konta, edytowa saldo, zmienia status oraz wysya powiadomienia. Uywaj z ostronoci  zmiany salda s nieodwracalne.</p>'],
        ['odwierty',    '', 'Odwierty',              1,
            '<h3>Sekcja Odwierty</h3><p>Zarzdzanie parametrami kosztw odwiertw, przegldanie aktywnych odwiertw i zdarze. Moesz tu ustawi bazowe koszty OPEX, mnoniki i progi zuycia sprztu.</p>'],
        ['balans',      '', 'Balans gry',            2,
            '<h3>Balans gry</h3><p>Globalne mnoniki wpywajce na ca gr: mnonik produkcji, mnonik incydentw, OPEX, degradacja, straty transportowe. Warto 1.0 = domylny balans. Zmiana na 0.5 oznacza o poow mniejszy efekt.</p>'],
        ['rynek',       '', 'Rynek ropy',            3,
            '<h3>Rynek ropy</h3><p>Panel rynku pozwala ustawia cen ropy, mnonik zmiennoci oraz zarzdza trendami rynkowymi. Trendy aktywne wpywaj na cen przez okrelony czas. Moesz dodawa, edytowa i usuwa trendy.</p>'],
        ['incydenty',   '', 'Incydenty',             4,
            '<h3>Incydenty</h3><p>Konfiguracja parametrw losowych awarii na odwiertach. Cztery poziomy: Mikro (auto-naprawa), Drobny (auto-naprawa), Powany (wymaga technika, koszt) i Krytyczny (zatrzymanie, wysoki koszt). Szansa bazowa jest mnoona przez stan techniczny, ryzyko, umiejtnoci pracownikw i globalny mnonik z Balansu gry.</p>'],
        ['transport',   '', 'Transport',             5,
            '<h3>Transport</h3><p>Konfiguracja stawek strat transportowych per warstwa geologiczna i typ transportu. Monitorowanie historycznych strat. Rurocigi maj inne mnoniki ni ciarwki i tankowce.</p>'],
        ['finanse',     '', 'Finanse',               6,
            '<h3>Finanse</h3><p>Przegld finansw gry, konfiguracja kryzysw finansowych i progw bankructwa. Panel finansowy pozwala na ledzenie globalnych przepyww pieninych w grze.</p>'],
        ['narzedzia',   '', 'Narzdzia GM',          7,
            '<h3>Narzdzia GM</h3><p>Narzdzia Mistrza Gry: manualne uruchomienie tiku, wysyanie alertw do graczy, przegldanie logw systemowych i tick-logw. Uywaj tylko gdy wiesz co robisz  manualne tiki wpywaj na ca gr.</p>'],
        ['hr',          '', 'HR  Pracownicy',       8,
            '<h3>Dzia HR</h3><p>Zarzdzanie personelem technicznym w grze: specjalizacje, limity, parametry. Moesz edytowa dostpne specjalizacje i ich bonusy wpywajce na produkcj i ryzyko awarii.</p>'],
        ['logi',        '', 'Logi i historia',       9,
            '<h3>Logi</h3><p>Pena historia zdarze systemowych. Logi tickw pokazuj wyniki kadego cyklu gry. Logi systemowe pomagaj diagnozowa bdy. Filtruj po typie, czasie i graczu.</p>'],
        ['instrukcja',  '', 'Instrukcja gracza',    10,
            '<h3>Instrukcja gracza</h3><p>Edytor treci instrukcji wywietlanej graczom w grze. Moesz dodawa i edytowa sekcje pomocowe widoczne w panelu gracza (dzia Pomoc).</p>'],
    ];
    $ins = $db->prepare("INSERT IGNORE INTO admin_help_pages (slug, icon, title, sort_order, content) VALUES (?,?,?,?,?)");
    foreach ($defaults as [$slug, $icon, $title, $order, $content]) {
        $ins->execute([$slug, $icon, $title, $order, $content]);
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id      = (int)($_POST['page_id'] ?? 0);
            $title   = trim($_POST['title']   ?? '');
            $icon    = trim($_POST['icon']    ?? '');
            $content = $_POST['content']      ?? '';
            $active  = isset($_POST['active']) ? 1 : 0;
            $sort    = (int)($_POST['sort_order'] ?? 0);
            $who     = AdminAuth::getAdminUsername();

            if ($id > 0 && $title !== '') {
                $db->prepare("UPDATE admin_help_pages
                    SET title=?, icon=?, content=?, active=?, sort_order=?, updated_by=?
                    WHERE id=?")
                   ->execute([$title, $icon, $content, $active, $sort, $who, $id]);
                $msg = t('admin.help.msg_saved');
                GameLog::info('admin/admin_help', 'Section saved', ['id' => $id, 'by' => $who]);
            } else {
                $err = t('admin.help.err_invalid');
            }

        } elseif ($action === 'add') {
            $slug  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['new_slug']  ?? '')));
            $title = trim($_POST['new_title'] ?? '');
            $icon  = trim($_POST['new_icon']  ?? '');
            $sort  = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM admin_help_pages")->fetchColumn();
            $who   = AdminAuth::getAdminUsername();

            if ($slug && $title) {
                try {
                    $db->prepare("INSERT INTO admin_help_pages (slug,title,icon,sort_order,content,updated_by) VALUES (?,?,?,?,?,?)")
                       ->execute([$slug, $icon, $title, $sort, '<p>Nowa sekcja  uzupenij tre.</p>', $who]);
                    $msg = t('admin.help.msg_added');
                    GameLog::info('admin/admin_help', 'Section added', ['slug' => $slug, 'by' => $who]);
                } catch (Throwable $e) {
                    $err = t('admin.help.err_slug_exists');
                }
            } else {
                $err = t('admin.help.err_invalid');
            }

        } elseif ($action === 'delete') {
            $id = (int)($_POST['page_id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM admin_help_pages WHERE id=?")->execute([$id]);
                $msg = t('admin.help.msg_deleted');
                GameLog::info('admin/admin_help', 'Section deleted', ['id' => $id]);
            }
        }
    }
}

// Fetch all sections
$pages = $db->query("SELECT * FROM admin_help_pages ORDER BY sort_order ASC, id ASC")->fetchAll();

// Active edit section
$editId   = (int)($_GET['edit'] ?? ($pages[0]['id'] ?? 0));
$editPage = null;
foreach ($pages as $p) {
    if ((int)$p['id'] === $editId) { $editPage = $p; break; }
}
if (!$editPage && !empty($pages)) { $editPage = $pages[0]; $editId = (int)$editPage['id']; }

$pageTitle = t('admin.help.page_title');
$viewData  = [
    'msg'      => $msg,
    'err'      => $err,
    'pages'    => $pages,
    'editId'   => $editId,
    'editPage' => $editPage,
];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/admin_help/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/admin_help.php', 'Unhandled exception', $e);
    if (!headers_sent()) http_response_code(500);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/admin_help.php', $_codexGuardStart);
}
