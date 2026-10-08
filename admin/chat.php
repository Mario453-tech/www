<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/chat.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../src/ChatBootstrap.php';
require_once __DIR__ . '/../src/ChatService.php';
require_once __DIR__ . '/../src/ChatRequestPolicy.php';
require_once __DIR__ . '/../src/ChatLimiterMigrationService.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$flash = $_SESSION['admin_chat_flash'] ?? [];
unset($_SESSION['admin_chat_flash']);
$msg = (string)($flash['msg'] ?? '');
$err = (string)($flash['err'] ?? '');
$adminDraft = (string)($flash['draft'] ?? '');

// Moderation POST actions / Akcje moderacji POST

$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $action = $_POST['action'] ?? '';
        if (!in_array($action, ['delete_msg', 'delete_player_msgs', 'delete_expired', 'clear_all',
            'ban_player', 'unban_player', 'send_admin', 'pin_msg', 'unpin_msg', 'resolve_report',
            'add_blocked_word', 'delete_blocked_word', 'toggle_blocked_word', 'save_auto_clear',
            'create_room', 'update_room', 'archive_room', 'migrate_request_limits'], true)) {
            $_SESSION['admin_chat_flash'] = ['err' => t('common.app_error')];
            header('Location: chat.php');
            exit;
        }
        $permission = in_array($action, ['create_room', 'update_room', 'archive_room'], true) ? 'chat.rooms.manage' : 'chat.moderate';
        if (!ChatRequestPolicy::allows($permission, AdminAuth::isLoggedIn())) {
            throw new RuntimeException('Chat permission denied');
        }
        if ($action === 'migrate_request_limits') {
            try {
                (new ChatLimiterMigrationService($db))->apply();
                AdminLog::log('chat_limiter_migration', 'Chat request limiter schema verified');
                $_SESSION['admin_chat_flash'] = ['msg' => t('admin.chat.migration_success')];
            } catch (Throwable $e) {
                GameLog::error('admin/chat.php', 'Chat request limiter migration failed', $e);
                AdminLog::log('chat_limiter_migration_error', 'Chat request limiter migration failed');
                $_SESSION['admin_chat_flash'] = ['err' => t('admin.chat.migration_error')];
            }
            header('Location: /admin/chat.php#chat-migration', true, 303);
            exit;
        }
        $db->beginTransaction();
        $chatService = new ChatService($db);

        if ($action === 'delete_msg') {
            $id = (int)($_POST['msg_id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE chat_messages SET is_deleted = 1 WHERE id = ?")->execute([$id]);
                AdminLog::log('chat_delete_msg', "Deleted message #{$id}");
                $msg = t('admin.chat.msg_deleted', ['id' => $id]);
            }

        } elseif ($action === 'delete_player_msgs') {
            $pid = (int)($_POST['player_id'] ?? 0);
            if ($pid > 0) {
                $count = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE sender_id = ?");
                $count->execute([$pid]);
                $n = (int)$count->fetchColumn();
                $db->prepare("UPDATE chat_messages SET is_deleted = 1 WHERE sender_id = ?")->execute([$pid]);
                AdminLog::log('chat_delete_player', "Deleted {$n} messages of player #{$pid}", $pid, 'player');
                $msg = t('admin.chat.msg_player_deleted', ['n' => $n, 'pid' => $pid]);
            }

        } elseif ($action === 'delete_expired') {
            $cut    = date('Y-m-d H:i:s', strtotime('-30 minutes'));
            $cntStmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE created_at < ? AND is_deleted = 0");
            $cntStmt->execute([$cut]);
            $count = (int)$cntStmt->fetchColumn();
            $db->prepare("UPDATE chat_messages SET is_deleted = 1 WHERE created_at < ? AND is_deleted = 0")->execute([$cut]);
            AdminLog::log('chat_delete_expired', "Deleted {$count} expired messages (>30 min)");
            $msg = t('admin.chat.msg_expired_deleted', ['count' => $count]);

        } elseif ($action === 'clear_all') {
            $count = (int)$db->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();
            $db->exec("UPDATE chat_messages SET is_deleted = 1 WHERE is_deleted = 0");
            AdminLog::log('chat_clear_all', "Cleared entire chat ({$count} messages)");
            $msg = t('admin.chat.msg_cleared', ['count' => $count]);

        } elseif ($action === 'ban_player') {
            $pid    = (int)($_POST['ban_player_id'] ?? 0);
            $reason = trim($_POST['ban_reason'] ?? '');
            $dur    = $_POST['ban_duration'] ?? 'permanent';
            $who    = AdminAuth::getAdminUsername();
            $durMap = [
                '1h' => '+1 hour', '3h' => '+3 hours', '12h' => '+12 hours',
                '1d' => '+1 day',  '3d' => '+3 days',  '7d'  => '+7 days',
                '14d'=> '+14 days','30d'=> '+30 days',  '90d' => '+90 days',
                'permanent' => null,
            ];
            $expires = isset($durMap[$dur]) && $durMap[$dur] !== null
                ? date('Y-m-d H:i:s', strtotime($durMap[$dur])) : null;
            if ($pid > 0) {
                $db->prepare("
                    INSERT INTO chat_bans (player_id, reason, banned_by, expires_at)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE reason=VALUES(reason), banned_by=VALUES(banned_by),
                        banned_at=NOW(), expires_at=VALUES(expires_at)
                ")->execute([$pid, $reason, $who, $expires]);
                $durLabel = $dur === 'permanent' ? t('admin.chat.ban_permanent') : $dur;
                AdminLog::log('chat_ban', "Banned player #{$pid} in chat for {$dur}. Reason: {$reason}", $pid, 'player');
                $msg = t('admin.chat.msg_banned', ['pid' => $pid, 'dur' => $durLabel]);
            } else {
                $err = t('admin.chat.err_invalid_player');
            }

        } elseif ($action === 'unban_player') {
            $pid = (int)($_POST['ban_player_id'] ?? 0);
            if ($pid > 0) {
                $db->prepare("DELETE FROM chat_bans WHERE player_id = ?")->execute([$pid]);
                AdminLog::log('chat_unban', "Unbanned player #{$pid} in chat", $pid, 'player');
                $msg = t('admin.chat.msg_unbanned', ['pid' => $pid]);
            }

        } elseif ($action === 'send_admin') {
            $input = (string)($_POST['admin_msg'] ?? '');
            if (AdminChatBroadcast::send($db, $input)) {
                AdminLog::log('chat_admin_msg', 'Sent admin broadcast');
                $msg = t('admin.chat.msg_sent');
            } else {
                $err = t('admin.chat.err_msg_empty_or_long');
                $adminDraft = strlen($input) <= 16000 ? ChatMessageHtml::sanitize($input) : '';
            }

        } elseif ($action === 'pin_msg') {
            $id = (int)($_POST['msg_id'] ?? 0);
            if ($id > 0) {
                $isAdmin = (int)$db->prepare("SELECT is_admin FROM chat_messages WHERE id = ? LIMIT 1")->execute([$id]);
                $row = $db->prepare("SELECT is_admin FROM chat_messages WHERE id = ? LIMIT 1");
                $row->execute([$id]);
                $r = $row->fetch();
                if (!$r || !$r['is_admin']) {
                    $err = t('admin.chat.err_not_admin_msg');
                } else {
                    $db->prepare("UPDATE chat_messages SET is_pinned = 1, pinned_at = NOW() WHERE id = ?")->execute([$id]);
                    AdminLog::log('chat_pin_msg', "Pinned admin message #{$id}");
                    $msg = t('admin.chat.msg_pinned', ['id' => $id]);
                }
            }

        } elseif ($action === 'unpin_msg') {
            $id = (int)($_POST['msg_id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE chat_messages SET is_pinned = 0, pinned_at = NULL WHERE id = ?")->execute([$id]);
                AdminLog::log('chat_unpin_msg', "Unpinned admin message #{$id}");
                $msg = t('admin.chat.msg_unpinned', ['id' => $id]);
            }

        } elseif ($action === 'resolve_report') {
            $rid = (int)($_POST['report_id'] ?? 0);
            if ($rid > 0) {
                $db->prepare("UPDATE chat_reports SET status='resolved' WHERE id=?")->execute([$rid]);
                AdminLog::log('chat_resolve_report', "Resolved report #{$rid}");
                $msg = t('admin.chat.msg_report_resolved', ['rid' => $rid]);
            }

        } elseif ($action === 'add_blocked_word') {
            $word        = mb_strtolower(trim($_POST['word'] ?? ''));
            $replacement = trim($_POST['replacement'] ?? '***');
            if ($word !== '' && mb_strlen($word) <= 100) {
                try {
                    $db->prepare("
                        INSERT INTO chat_blocked_words (word, replacement, created_by)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE replacement=VALUES(replacement), active=1
                    ")->execute([$word, $replacement ?: '***', AdminAuth::getAdminUsername()]);
                    AdminLog::log('chat_blocked_word_add', "Added blocked word: {$word}");
                    $msg = t('admin.chat.word_added', ['word' => $word]);
                } catch (Throwable $e) {
                    $err = t('common.err_retry');
                }
            } else {
                $err = t('admin.chat.err_word_empty');
            }

        } elseif ($action === 'delete_blocked_word') {
            $wid = (int)($_POST['word_id'] ?? 0);
            if ($wid > 0) {
                $db->prepare("DELETE FROM chat_blocked_words WHERE id=?")->execute([$wid]);
                AdminLog::log('chat_blocked_word_del', "Deleted blocked word #{$wid}");
                $msg = t('admin.chat.word_deleted');
            }

        } elseif ($action === 'toggle_blocked_word') {
            $wid = (int)($_POST['word_id'] ?? 0);
            if ($wid > 0) {
                $db->prepare("UPDATE chat_blocked_words SET active = NOT active WHERE id=?")->execute([$wid]);
                $msg = t('admin.chat.word_toggled');
            }

        } elseif ($action === 'save_auto_clear') {
            $enabled  = (int)($_POST['auto_clear_enabled'] ?? 0) === 1 ? 1 : 0;
            $interval = (int)($_POST['auto_clear_interval'] ?? 30);
            $allowed  = [15, 30, 60, 90, 120];
            if (!in_array($interval, $allowed)) $interval = 30;
            $db->prepare("INSERT INTO well_config (`key`,`value`) VALUES ('chat_auto_clear_enabled',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$enabled]);
            $db->prepare("INSERT INTO well_config (`key`,`value`) VALUES ('chat_auto_clear_interval',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$interval]);
            AdminLog::log('chat_auto_clear_settings', "Auto-clear: enabled={$enabled}, interval={$interval}min");
            $msg = t('admin.chat.msg_auto_clear_saved');

        } elseif ($action === 'create_room') {
            $slug = (string)($_POST['slug'] ?? '');
            $type = (string)($_POST['type'] ?? 'custom');
            $localeCode = trim((string)($_POST['locale_code'] ?? ''));
            $translations = is_array($_POST['translations'] ?? null) ? array_values($_POST['translations']) : [];
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $status = (string)($_POST['status'] ?? 'active');

            try {
                $chatService = new ChatService($db);
                $adminId = (int)AdminAuth::getAdminId();
                $roomId = $chatService->createRoom(
                    $adminId, $slug, $type, $localeCode, $translations, $sortOrder, $status
                );
                AdminLog::log('chat_create_room', "Created room #{$roomId} ({$slug})");
                $msg = t('admin.chat.room_created', ['slug' => $slug]);
            } catch (Throwable $e) {
                GameLog::error('admin/chat', 'Room creation failed', ['exception_class' => get_class($e)]);
                $err = t('common.app_error');
            }

        } elseif ($action === 'update_room') {
            $roomId = (int)($_POST['room_id'] ?? 0);
            $translations = is_array($_POST['translations'] ?? null) ? array_values($_POST['translations']) : [];
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $status = (string)($_POST['status'] ?? 'active');

            if ($roomId > 0) {
                try {
                    $chatService = new ChatService($db);
                    $adminId = (int)AdminAuth::getAdminId();
                    $chatService->updateRoom(
                        $adminId, $roomId, $translations, $sortOrder, $status,
                        (string) ($_POST['type'] ?? 'custom'), (string) ($_POST['locale_code'] ?? '')
                    );
                    AdminLog::log('chat_update_room', "Updated room #{$roomId}");
                    $msg = t('admin.chat.room_updated', ['id' => $roomId]);
                } catch (Throwable $e) {
                    GameLog::error('admin/chat', 'Room update failed', ['exception_class' => get_class($e)]);
                    $err = t('common.app_error');
                }
            }

        } elseif ($action === 'archive_room') {
            $roomId = (int)($_POST['room_id'] ?? 0);
            if ($roomId > 0) {
                try {
                    $chatService = new ChatService($db);
                    $adminId = (int)AdminAuth::getAdminId();
                    $chatService->archiveRoom($adminId, $roomId, 'Archived via admin panel');
                    AdminLog::log('chat_archive_room', "Archived room #{$roomId}");
                    $msg = t('admin.chat.room_archived', ['id' => $roomId]);
                } catch (Throwable $e) {
                    GameLog::error('admin/chat', 'Room archive failed', ['exception_class' => get_class($e)]);
                    $err = t('common.app_error');
                }
            }
        }
    }
}

// Redirect after POST to prevent duplicate broadcasts. / Przekieruj po POST, aby nie dublowac komunikatow.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($db->inTransaction()) {
        if ($err !== '') {
            $db->rollBack();
        } else {
            if (!in_array($action, ['create_room', 'update_room', 'archive_room'], true)) {
                $chatService->recordAdminAction((int) AdminAuth::getAdminId(), (string) $action,
                    (int) ($_POST['room_id'] ?? $_POST['msg_id'] ?? $_POST['player_id'] ?? $_POST['ban_player_id'] ?? 0));
            }
            $db->commit();
        }
    }
    $_SESSION['admin_chat_flash'] = ['msg' => $msg, 'err' => $err, 'draft' => $adminDraft];
    header('Location: /admin/chat.php', true, 303);
    exit;
}

// Filters / Filtry

$filterPlayer = trim($_GET['player'] ?? '');
$page         = max(1, (int)($_GET['p'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;
$where        = '';
$params       = [];

if ($filterPlayer !== '') {
    $where    = 'WHERE cm.is_deleted = 0 AND cm.username LIKE ?';
    $params[] = '%' . $filterPlayer . '%';
} else {
    $where = 'WHERE cm.is_deleted = 0';
}

$expiredCutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));

$countStmt = $db->prepare("SELECT COUNT(*) FROM chat_messages cm " . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $db->prepare("
    SELECT cm.id, cm.sender_id AS player_id, cm.username, cm.message,
           cm.created_at, cm.is_deleted, cm.channel, cm.is_admin, cm.is_pinned
    FROM chat_messages cm
    {$where}
    ORDER BY cm.id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Statistics / Statystyki

$stats = $db->query("
    SELECT COUNT(*) AS total_msgs,
           COUNT(DISTINCT sender_id) AS unique_players,
           MAX(created_at) AS last_msg
    FROM chat_messages
    WHERE sender_id IS NOT NULL
")->fetch();

$topSenders = $db->query("
    SELECT username, sender_id AS player_id, COUNT(*) AS cnt
    FROM chat_messages
    WHERE sender_id IS NOT NULL
    GROUP BY sender_id, username
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll();

// Bans / Blokady

$activeBans = $db->query("
    SELECT cb.id, cb.player_id, cb.reason, cb.banned_by, cb.banned_at, cb.expires_at,
           p.username
    FROM chat_bans cb
    JOIN players p ON p.id = cb.player_id
    WHERE cb.expires_at IS NULL OR cb.expires_at > NOW()
    ORDER BY cb.banned_at DESC
")->fetchAll();

$playerList = $db->query("
    SELECT id, username FROM players WHERE status != 'banned' ORDER BY username ASC
")->fetchAll();

// Reports / Zgloszenia

$reports = [];
try {
    $reports = $db->query("
        SELECT cr.*, cm.message, cm.username AS msg_author,
               COALESCE(NULLIF(p.company_name,''), p.username) AS reporter_name
        FROM chat_reports cr
        JOIN chat_messages cm ON cm.id = cr.message_id
        LEFT JOIN players p ON p.id = cr.reporter_id
        WHERE cr.status = 'open'
        ORDER BY cr.created_at DESC
        LIMIT 50
    ")->fetchAll();
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        GameLog::error('admin/chat', 'Chat mutation failed', ['exception_class' => get_class($e)]);
        $_SESSION['admin_chat_flash'] = ['err' => t('common.app_error')];
        header('Location: /admin/chat.php', true, 303);
        exit;
    }
 // Table may not exist yet / Tabela moze jeszcze nie istniec
}

// Blocked words / Slowa blokowane

$blockedWords = [];
try {
    $blockedWords = $db->query("
        SELECT id, word, replacement, active, created_by, created_at
        FROM chat_blocked_words
        ORDER BY word ASC
    ")->fetchAll();
} catch (Throwable $e) {
 // Table may not exist yet / Tabela moze jeszcze nie istniec
}

// Auto-clear settings / Ustawienia auto-clear
$autoClearRows     = $db->query("SELECT `key`,`value` FROM well_config WHERE `key` IN ('chat_auto_clear_enabled','chat_auto_clear_interval','chat_auto_clear_last_at')")->fetchAll(PDO::FETCH_KEY_PAIR);
$autoClearEnabled  = (int)($autoClearRows['chat_auto_clear_enabled']  ?? 0);
$autoClearInterval = (int)($autoClearRows['chat_auto_clear_interval'] ?? 30);
$autoClearLastAt   = $autoClearRows['chat_auto_clear_last_at'] ?? null;

// Ban duration options / Opcje czasu blokady

$banDurations = [
    '1h'        => t('admin.chat.ban_dur_1h'),
    '3h'        => t('admin.chat.ban_dur_3h'),
    '12h'       => t('admin.chat.ban_dur_12h'),
    '1d'        => t('admin.chat.ban_dur_1d'),
    '3d'        => t('admin.chat.ban_dur_3d'),
    '7d'        => t('admin.chat.ban_dur_7d'),
    '14d'       => t('admin.chat.ban_dur_14d'),
    '30d'       => t('admin.chat.ban_dur_30d'),
    '90d'       => t('admin.chat.ban_dur_90d'),
    'permanent' => t('admin.chat.ban_permanent'),
];

// Rooms and moderation audit / Pokoje i audyt moderacji
$allRooms = [];
try {
    $allRooms = $db->query("
        SELECT r.*,
               (SELECT COUNT(*) FROM chat_messages m WHERE m.room_id = r.id AND m.is_deleted = 0) AS message_count
        FROM chat_rooms r
        ORDER BY r.sort_order ASC, r.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$roomTranslations = (new ChatService($db))->getRoomTranslations(array_map(
    static fn (array $room): int => (int) $room['id'],
    $allRooms
));
foreach ($allRooms as &$room) {
    $room['translations'] = $roomTranslations[(int) $room['id']] ?? [];
}
unset($room);
$defaultRoomTranslations = [
    ['locale' => 'pl', 'name' => '', 'description' => ''],
    ['locale' => 'en', 'name' => '', 'description' => ''],
    ['locale' => 'de', 'name' => '', 'description' => ''],
];

$moderationLog = [];
$auditPage = max(1, (int) ($_GET['audit_page'] ?? 1));
$auditPerPage = 50;
$auditTotalPages = 1;
try {
    $auditTotal = (int) $db->query('SELECT COUNT(*) FROM chat_moderation_actions')->fetchColumn();
    $auditTotalPages = max(1, (int) ceil($auditTotal / $auditPerPage));
    $auditPage = min($auditPage, $auditTotalPages);
    $auditOffset = ($auditPage - 1) * $auditPerPage;
    $moderationLog = $db->query("
        SELECT ma.*, COALESCE(a.username, 'Admin') AS actor_name
        FROM chat_moderation_actions ma
        LEFT JOIN admins a ON a.id = ma.actor_id
        ORDER BY ma.id DESC
        LIMIT {$auditPerPage} OFFSET {$auditOffset}
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// View data / Dane widoku

$viewData = compact(
    'msg', 'err', 'adminDraft', 'stats', 'topSenders',
    'activeBans', 'playerList', 'messages',
    'filterPlayer', 'page', 'totalPages', 'reports',
    'banDurations', 'blockedWords', 'expiredCutoff',
    'autoClearEnabled', 'autoClearInterval', 'autoClearLastAt',
    'allRooms', 'moderationLog', 'defaultRoomTranslations', 'auditPage', 'auditTotalPages'
);

$pageTitle = t('admin.chat.page_title');
$adminExtraCss = ['/assets/css/admin_chat.css'];
$extraJs = ['/assets/js/admin_chat_editor.js'];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/chat/main.php';
?>
<script src="https://cdn.tiny.cloud/1/n2m8igiixgfiasr4l4gha8fjz6hxp12sudqgnecovtt6y2nq/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<?php
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/chat.php', 'Exception', $e);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/chat.php', $_codexGuardStart);
}
