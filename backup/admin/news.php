<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/news.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';
$editNews = null;

// == POST: akcje ==
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $title   = trim($_POST['title']   ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($title === '' || $content === '') {
                $err = t('admin.news.err_empty');
            } else {
                $who = AdminAuth::getAdminUsername();
                $db->prepare("INSERT INTO admin_news (title, content, created_by) VALUES (?, ?, ?)")
                   ->execute([$title, $content, $who]);
                AdminLog::log('news_add', "Dodano news: {$title}");
                $msg = t('admin.news.msg_added');
            }

        } elseif ($action === 'edit') {
            $id      = (int)($_POST['news_id'] ?? 0);
            $title   = trim($_POST['title']    ?? '');
            $content = trim($_POST['content']  ?? '');
            if ($id <= 0 || $title === '' || $content === '') {
                $err = t('admin.news.err_empty');
            } else {
                $db->prepare("UPDATE admin_news SET title = ?, content = ? WHERE id = ? AND active = 1")
                   ->execute([$title, $content, $id]);
                AdminLog::log('news_edit', "Zaktualizowano news #{$id}: {$title}");
                $msg = t('admin.news.msg_updated');
            }

        } elseif ($action === 'delete') {
            $id = (int)($_POST['news_id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE admin_news SET active = 0 WHERE id = ?")->execute([$id]);
                AdminLog::log('news_delete', "Usunięto news #{$id}");
                $msg = t('admin.news.msg_deleted');
            }

        } elseif ($action === 'pin') {
            $id = (int)($_POST['news_id'] ?? 0);
            if ($id > 0) {
                // Max 3 przypięte
                $pinCount = (int)$db->query("SELECT COUNT(*) FROM admin_news WHERE is_pinned = 1 AND active = 1")->fetchColumn();
                if ($pinCount >= 3) {
                    $err = t('admin.news.max_pinned_warn');
                } else {
                    $db->prepare("UPDATE admin_news SET is_pinned = 1, pinned_at = NOW() WHERE id = ? AND active = 1")->execute([$id]);
                    AdminLog::log('news_pin', "Przypieto news #{$id}");
                    $msg = t('admin.news.msg_pinned');
                }
            }

        } elseif ($action === 'unpin') {
            $id = (int)($_POST['news_id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE admin_news SET is_pinned = 0, pinned_at = NULL WHERE id = ?")->execute([$id]);
                AdminLog::log('news_unpin', "Odpięto news #{$id}");
                $msg = t('admin.news.msg_unpinned');
            }
        }
    }
}

// Tryb edycji — załaduj news do formularza
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    if ($eid > 0) {
        $s = $db->prepare("SELECT * FROM admin_news WHERE id = ? AND active = 1 LIMIT 1");
        $s->execute([$eid]);
        $editNews = $s->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$editNews) $err = t('admin.news.err_not_found');
    }
}

// Lista newsów
$newsList = [];
try {
    $newsList = $db->query("
        SELECT id, title, content, is_pinned, created_by, created_at, updated_at
        FROM admin_news
        WHERE active = 1
        ORDER BY is_pinned DESC, created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $err = 'Błąd pobierania newsów: ' . $e->getMessage();
}

$csrfToken = CSRF::generateToken();
$pageTitle = t('admin.news.page_title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/news/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/news.php', 'Exception', $e);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/news.php', $_codexGuardStart);
}
