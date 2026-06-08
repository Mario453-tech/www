<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/news.php') : microtime(true);

try {
    require_once __DIR__ . '/init.php';
    require_once __DIR__ . '/../src/AdminNewsHtml.php';
    AdminAuth::requireLogin();

    $db       = Database::getInstance()->getConnection();
    $msg      = '';
    $err      = '';
    $editNews = null;
    $hasTitleHtml = true;

    try {
        Database::addColumnIfMissing('admin_news', 'title_html', 'TEXT NULL AFTER `title`');
    } catch (Throwable $e) {
        $hasTitleHtml = false;
        if (class_exists('GameLog', false)) {
            GameLog::error('admin/news.php', 'admin_news.title_html migration failed', $e);
        }
    }

 // POST actions / Akcje POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $err = t('common.csrf_error');
        } else {
            $action = $_POST['action'] ?? '';

            if ($action === 'add') {
                $titleHtml = AdminNewsHtml::sanitizeTitle(trim($_POST['title'] ?? ''));
                $title   = AdminNewsHtml::plainText($titleHtml);
                $content = trim($_POST['content'] ?? '');
                $contentPlain = AdminNewsHtml::plainText($content);

                if ($title === '' || $contentPlain === '') {
                    $err = t('admin.news.err_empty');
                } else {
                    $who = AdminAuth::getAdminUsername();
                    $dbTitle = AdminNewsHtml::limitText($title, 120);
                    if ($hasTitleHtml) {
                        $db->prepare("INSERT INTO admin_news (title, title_html, content, created_by) VALUES (?, ?, ?, ?)")
                            ->execute([$dbTitle, $titleHtml, $content, $who]);
                    } else {
                        $db->prepare("INSERT INTO admin_news (title, content, created_by) VALUES (?, ?, ?)")
                            ->execute([$dbTitle, $content, $who]);
                    }
                    AdminLog::log('news_add', "Dodano news: {$title}");
                    $msg = t('admin.news.msg_added');
                }
            } elseif ($action === 'edit') {
                $id      = (int) ($_POST['news_id'] ?? 0);
                $titleHtml = AdminNewsHtml::sanitizeTitle(trim($_POST['title'] ?? ''));
                $title   = AdminNewsHtml::plainText($titleHtml);
                $content = trim($_POST['content'] ?? '');
                $contentPlain = AdminNewsHtml::plainText($content);

                if ($id <= 0 || $title === '' || $contentPlain === '') {
                    $err = t('admin.news.err_empty');
                } else {
                    $dbTitle = AdminNewsHtml::limitText($title, 120);
                    if ($hasTitleHtml) {
                        $db->prepare("UPDATE admin_news SET title = ?, title_html = ?, content = ? WHERE id = ? AND active = 1")
                            ->execute([$dbTitle, $titleHtml, $content, $id]);
                    } else {
                        $db->prepare("UPDATE admin_news SET title = ?, content = ? WHERE id = ? AND active = 1")
                            ->execute([$dbTitle, $content, $id]);
                    }
                    AdminLog::log('news_edit', "Zaktualizowano news #{$id}: {$title}");
                    $msg = t('admin.news.msg_updated');
                }
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['news_id'] ?? 0);

                if ($id > 0) {
                    $db->prepare("UPDATE admin_news SET active = 0 WHERE id = ?")->execute([$id]);
                    AdminLog::log('news_delete', "Usunieto news #{$id}");
                    $msg = t('admin.news.msg_deleted');
                }
            } elseif ($action === 'pin') {
                $id = (int) ($_POST['news_id'] ?? 0);

                if ($id > 0) {
 // Max 3 pinned items / Maksymalnie 3 przypiete wpisy
                    $pinCount = (int) $db->query("SELECT COUNT(*) FROM admin_news WHERE is_pinned = 1 AND active = 1")->fetchColumn();
                    if ($pinCount >= 3) {
                        $err = t('admin.news.max_pinned_warn');
                    } else {
                        $db->prepare("UPDATE admin_news SET is_pinned = 1, pinned_at = NOW() WHERE id = ? AND active = 1")->execute([$id]);
                        AdminLog::log('news_pin', "Przypieto news #{$id}");
                        $msg = t('admin.news.msg_pinned');
                    }
                }
            } elseif ($action === 'unpin') {
                $id = (int) ($_POST['news_id'] ?? 0);

                if ($id > 0) {
                    $db->prepare("UPDATE admin_news SET is_pinned = 0, pinned_at = NULL WHERE id = ?")->execute([$id]);
                    AdminLog::log('news_unpin', "Odpieto news #{$id}");
                    $msg = t('admin.news.msg_unpinned');
                }
            }
        }
    }

 // Edit mode - load item into the form / Tryb edycji - zaladuj news do formularza
    if (isset($_GET['edit'])) {
        $editId = (int) $_GET['edit'];
        if ($editId > 0) {
            $stmt = $db->prepare("SELECT * FROM admin_news WHERE id = ? AND active = 1 LIMIT 1");
            $stmt->execute([$editId]);
            $editNews = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$editNews) {
                $err = t('admin.news.err_not_found');
            }
        }
    }

 // News list / Lista newsow
    $newsList = [];
    try {
        $titleHtmlSelect = $hasTitleHtml ? 'title_html' : 'NULL AS title_html';
        $newsList = $db->query("
            SELECT id, title, {$titleHtmlSelect}, content, is_pinned, created_by, created_at, updated_at
            FROM admin_news
            WHERE active = 1
            ORDER BY is_pinned DESC, created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($newsList as &$newsRow) {
            $titleSource = trim((string)($newsRow['title_html'] ?? ''));
            if ($titleSource === '') {
                $titleSource = (string)($newsRow['title'] ?? '');
            }
            $titleHtml = AdminNewsHtml::sanitizeTitle($titleSource);
            $titlePlain = AdminNewsHtml::plainText((string)($newsRow['title'] ?? ''));
            $newsRow['title_html'] = $titleHtml !== '' ? $titleHtml : htmlspecialchars($titlePlain, ENT_QUOTES, 'UTF-8');
            $newsRow['title_plain'] = $titlePlain;
            $newsRow['content_plain'] = AdminNewsHtml::plainText((string)($newsRow['content'] ?? ''), 120);
        }
        unset($newsRow);
    } catch (Throwable $e) {
        $err = t('admin.news.err_fetch') . ': ' . $e->getMessage();
    }

    $csrfToken = CSRF::generateToken();
    $pageTitle = t('admin.news.page_title');

    require_once __DIR__ . '/partials/header.php';
    require __DIR__ . '/../templates/views/admin/news/main.php';
    require_once __DIR__ . '/partials/footer.php';
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/news.php', 'Exception', $e);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/news.php', $_codexGuardStart);
    }
}
