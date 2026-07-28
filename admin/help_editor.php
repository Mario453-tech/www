<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/help_editor.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$msg = '';
$err = '';

// Ensure the table exists. / Upewnij sie, ze tabela istnieje.
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `game_help_pages` (
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
    
    // Ensure EN columns exist for older installations. / Upewnijmy sie, ze kolumny EN istnieja dla starszych instalacji.
    if (method_exists('Database', 'addColumnIfMissing')) {
        Database::addColumnIfMissing('game_help_pages', 'title_en', 'VARCHAR(255) NULL AFTER `title`');
        Database::addColumnIfMissing('game_help_pages', 'content_en', 'MEDIUMTEXT NULL AFTER `content`');
    }
} catch (Throwable $e) {
    GameLog::error('admin/help_editor', 'CREATE TABLE / ALTER failed', $e);
}

// Insert default sections if table is empty. / Wstaw domyslne sekcje jesli tabela pusta.
$count = (int)$db->query("SELECT COUNT(*) FROM game_help_pages")->fetchColumn();
if ($count === 0) {
    $defaults = [
        ['start',       '', 'Jak zacząć',                'How to start',                 0],
        ['odwierty',    '', 'Odwierty',                  'Wells',                        1],
        ['transport',   '', 'Transport',                 'Transport',                    2],
        ['magazyn',     '', 'Magazyn',                   'Storage',                      3],
        ['pracownicy',  '', 'Pracownicy',                'Employees',                    4],
        ['awarie',      '', 'Awarie i incydenty',        'Failures and incidents',       5],
        ['rynek',       '', 'Rynek i sprzedaż',          'Market and sales',             6],
        ['bank',        '', 'Bank i kredyty',            'Bank and credits',             7],
        ['bankructwo',  '', 'Bankructwo i restrukturyzacja', 'Bankruptcy and restructuring', 8],
        ['statusy',     '', 'Statusy odwiertu',          'Well statuses',                9],
        ['tips',        '', 'Wskazówki dla początkujących', 'Tips for beginners',        10],
    ];
    $ins = $db->prepare("INSERT IGNORE INTO game_help_pages (slug, icon, title, title_en, sort_order, content, content_en) VALUES (?,?,?,?,?,?,?)");
    foreach ($defaults as [$slug, $icon, $title, $title_en, $order]) {
        $content = str_replace(':title', htmlspecialchars($title), t('admin.help_editor.default_content_pl'));
        $content_en = str_replace(':title', htmlspecialchars($title_en), t('admin.help_editor.default_content_en'));
        $ins->execute([$slug, $icon, $title, $title_en, $order, $content, $content_en]);
    }
}

// POST: save section / POST: zapisz sekcje
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id      = (int)($_POST['page_id'] ?? 0);
            $title   = trim($_POST['title']   ?? '');
            $title_en = trim($_POST['title_en'] ?? '');
            $icon    = trim($_POST['icon']    ?? '');
            $content = $_POST['content']      ?? '';
            $content_en = $_POST['content_en'] ?? '';
            $active  = isset($_POST['active']) ? 1 : 0;
            $sort    = (int)($_POST['sort_order'] ?? 0);
            $who     = AdminAuth::getAdminUsername();

            if ($id > 0 && $title !== '') {
                $upd = $db->prepare("UPDATE game_help_pages
                    SET title=?, title_en=?, icon=?, content=?, content_en=?, active=?, sort_order=?, updated_by=?
                    WHERE id=?");
                $upd->execute([$title, $title_en, $icon, $content, $content_en, $active, $sort, $who, $id]);
                $msg = t('admin.help_editor.msg_saved');
                GameLog::info('admin/help_editor', 'Section saved', ['id' => $id, 'by' => $who]);
            } else {
                $err = t('admin.help_editor.err_invalid_data');
            }

        } elseif ($action === 'add') {
            $slug  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['new_slug'] ?? '')));
            $title = trim($_POST['new_title'] ?? '');
            $title_en = trim($_POST['new_title_en'] ?? '');
            $icon  = trim($_POST['new_icon']  ?? '');
            $sort  = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM game_help_pages")->fetchColumn();
            $who   = AdminAuth::getAdminUsername();

            if ($slug && $title) {
                try {
                    $db->prepare("INSERT INTO game_help_pages (slug,title,title_en,icon,sort_order,content,content_en,updated_by) VALUES (?,?,?,?,?,?,?,?)")
                       ->execute([$slug, $title, $title_en, $icon, $sort, t('admin.help_editor.new_section_pl'), t('admin.help_editor.new_section_en'), $who]);
                    $msg = t('admin.help_editor.msg_added');
                } catch (Throwable $e) {
                    $err = t('admin.help_editor.err_slug_exists');
                }
            } else {
                $err = t('admin.help_editor.err_slug_title_required');
            }

        } elseif ($action === 'delete') {
            $id = (int)($_POST['page_id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM game_help_pages WHERE id=?")->execute([$id]);
                $msg = t('admin.help_editor.msg_deleted');
                GameLog::info('admin/help_editor', 'Section deleted', ['id' => $id]);
            }
        }
    }
}

// Get all sections / Pobierz wszystkie sekcje
$pages = $db->query("SELECT * FROM game_help_pages ORDER BY sort_order ASC, id ASC")->fetchAll();

// Which section is active in the editor / Ktora sekcja jest aktywna w edytorze
$editId   = (int)($_GET['edit'] ?? ($pages[0]['id'] ?? 0));
$editPage = null;
foreach ($pages as $p) {
    if ((int)$p['id'] === $editId) { $editPage = $p; break; }
}
if (!$editPage && !empty($pages)) { $editPage = $pages[0]; $editId = (int)$editPage['id']; }

$pageTitle = t('admin.help_editor.page_title');
$viewData = [
    'msg'      => $msg,
    'err'      => $err,
    'pages'    => $pages,
    'editId'   => $editId,
    'editPage' => $editPage,
];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/help_editor/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/help_editor.php', 'Unhandled exception', $e);
    if (!headers_sent()) http_response_code(500);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/help_editor.php', $_codexGuardStart);
}
