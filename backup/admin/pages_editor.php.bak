<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/pages_editor.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

//  Upewnij siê ¿e tabela istnieje 
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `static_pages` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `slug`       VARCHAR(128) NOT NULL UNIQUE,
        `title`      VARCHAR(255) NOT NULL,
        `icon`       VARCHAR(16) NOT NULL DEFAULT '',
        `content`    MEDIUMTEXT NOT NULL,
        `active`     TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` SMALLINT NOT NULL DEFAULT 0,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` VARCHAR(64) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    GameLog::error('admin/pages_editor', 'CREATE TABLE failed', $e);
}

//  Funkcja: regeneruj regu³y htaccess 
function pagesRebuildHtaccess(PDO $db): void {
    $htaccessPath = dirname(__DIR__) . '/.htaccess';
    $content = file_get_contents($htaccessPath);
    if ($content === false) return;

    // Usuñ poprzedni blok stron statycznych
    $content = preg_replace(
        '/\n?#  BEGIN static_pages .*?#  END static_pages \n?/s',
        '',
        $content
    );

    // Pobierz aktywne strony
    $slugs = $db->query("SELECT slug FROM static_pages WHERE active=1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($slugs)) {
        $rules = "\n#  BEGIN static_pages \n";
        foreach ($slugs as $slug) {
            $escaped = preg_quote($slug, '/');
            $rules  .= "    RewriteRule ^{$escaped}$ /public/page.php?slug={$slug} [L,QSA]\n";
        }
        $rules .= "#  END static_pages ";

        // Wstaw PRZED lini¹ "#  Wszystko inne  public/ "
        $content = str_replace(
            "\n    #  Wszystko inne  public/ ",
            $rules . "\n\n    #  Wszystko inne  public/ ",
            $content
        );
    }

    file_put_contents($htaccessPath, $content);
}

//  POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $action = $_POST['action'] ?? '';
        $who    = AdminAuth::getAdminUsername();

        if ($action === 'save') {
            $id      = (int)($_POST['page_id'] ?? 0);
            $title   = trim($_POST['title']   ?? '');
            $icon    = trim($_POST['icon']    ?? '');
            $content = $_POST['content']      ?? '';
            $active  = isset($_POST['active']) ? 1 : 0;
            $sort    = (int)($_POST['sort_order'] ?? 0);

            if ($id > 0 && $title !== '') {
                $db->prepare("UPDATE static_pages SET title=?,icon=?,content=?,active=?,sort_order=?,updated_by=? WHERE id=?")
                   ->execute([$title, $icon, $content, $active, $sort, $who, $id]);
                pagesRebuildHtaccess($db);
                $msg = t('admin.pages_editor.msg_saved');
                GameLog::info('admin/pages_editor', 'Page saved', ['id' => $id, 'by' => $who]);
            } else {
                $err = t('admin.pages_editor.err_invalid_data');
            }

        } elseif ($action === 'add') {
            $slug  = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['new_slug']  ?? '')));
            $title = trim($_POST['new_title'] ?? '');
            $icon  = trim($_POST['new_icon']  ?? '');
            $sort  = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM static_pages")->fetchColumn();

            if ($slug && $title) {
                try {
                    $db->prepare("INSERT INTO static_pages (slug,title,icon,sort_order,content,updated_by) VALUES (?,?,?,?,?,?)")
                       ->execute([$slug, $title, $icon, $sort, '<p>Treœæ strony <strong>' . htmlspecialchars($title) . '</strong> — edytuj poni¿ej.</p>', $who]);
                    pagesRebuildHtaccess($db);
                    $newId = (int)$db->lastInsertId();
                    $msg = t('admin.pages_editor.msg_added', ['slug' => htmlspecialchars($slug)]);
                    header('Location: ?edit=' . $newId);
                    exit();
                } catch (Throwable $e) {
                    $err = t('admin.pages_editor.err_slug_exists');
                }
            } else {
                $err = t('admin.pages_editor.err_slug_title_required');
            }

        } elseif ($action === 'delete') {
            $id = (int)($_POST['page_id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM static_pages WHERE id=?")->execute([$id]);
                pagesRebuildHtaccess($db);
                $msg = t('admin.pages_editor.msg_deleted');
                GameLog::info('admin/pages_editor', 'Page deleted', ['id' => $id, 'by' => $who]);
                header('Location: ?');
                exit();
            }
        }
    }
}

//  Dane do widoku 
$pages  = $db->query("SELECT * FROM static_pages ORDER BY sort_order ASC, id ASC")->fetchAll();
$editId = (int)($_GET['edit'] ?? ($pages[0]['id'] ?? 0));
$editPage = null;
foreach ($pages as $p) {
    if ((int)$p['id'] === $editId) { $editPage = $p; break; }
}

$pageTitle = t('admin.pages_editor.page_title');
$viewData = [
    'msg'      => $msg,
    'err'      => $err,
    'pages'    => $pages,
    'editId'   => $editId,
    'editPage' => $editPage,
];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/pages_editor/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/pages_editor.php', 'Unhandled exception', $e);
    if (!headers_sent()) http_response_code(500);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/pages_editor.php', $_codexGuardStart);
}
