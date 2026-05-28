<?php
ob_start();
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/ChatBootstrap.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    Auth::requireLogin();
} catch (Throwable $e) {
    echo json_encode(['error' => t('common.not_logged_in')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::isLoggedIn()) {
    echo json_encode(['error' => t('common.not_logged_in')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    ensureChatSchema();
} catch (Throwable $e) {
    echo json_encode(['error' => t('common.app_error')], JSON_UNESCAPED_UNICODE);
    exit;
}

$playerId = Auth::getUserId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input') ?: '';
$jsonInput = null;
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($rawInput !== '' && ($contentType === 'application/json' || str_contains($contentType, 'json'))) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $jsonInput = $decoded;
    }
}
$action = $_REQUEST['action']
    ?? ($jsonInput['action'] ?? null)
    ?? ($_SERVER['HTTP_X_UPLOAD_ACTION'] ?? '')
    ?? '';

function chatJson(array $payload): never
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function chatGetDisplayName(PDO $db, int $pid): string
{
    $stmt = $db->prepare("SELECT COALESCE(NULLIF(company_name,''), username) FROM players WHERE id=? LIMIT 1");
    $stmt->execute([$pid]);
    return $stmt->fetchColumn() ?: 'Gracz';
}

function chatIsBanned(PDO $db, int $pid): ?string
{
    try {
        $stmt = $db->prepare("SELECT reason, expires_at FROM chat_bans WHERE player_id=? LIMIT 1");
        $stmt->execute([$pid]);
        $ban = $stmt->fetch();
        if (!$ban) {
            return null;
        }
        if ($ban['expires_at'] !== null && strtotime((string) $ban['expires_at']) <= time()) {
            $db->prepare("DELETE FROM chat_bans WHERE player_id=?")->execute([$pid]);
            return null;
        }
        $until = $ban['expires_at'] ? date('d.m.Y H:i', strtotime((string) $ban['expires_at'])) : t('chat.ban_permanent');
        return t('chat.banned_until', ['until' => $until, 'reason' => $ban['reason']]);
    } catch (Throwable $e) {
        return null;
    }
}

function chatIsRateLimited(PDO $db, int $pid): bool
{
    $stmt = $db->prepare("SELECT created_at FROM chat_messages WHERE sender_id=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$pid]);
    $last = $stmt->fetchColumn();
    if ($last && (time() - strtotime((string) $last)) < 2) {
        return true;
    }

    $stmt2 = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE sender_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)");
    $stmt2->execute([$pid]);
    if ((int) $stmt2->fetchColumn() >= 5) {
        try {
            $db->prepare("
                INSERT INTO chat_bans (player_id, reason, banned_by, expires_at)
                VALUES (?, 'Auto-mute: flood', 'system', DATE_ADD(NOW(), INTERVAL 5 MINUTE))
                ON DUPLICATE KEY UPDATE reason='Auto-mute: flood', expires_at=DATE_ADD(NOW(), INTERVAL 5 MINUTE)
            ")->execute([$pid]);
        } catch (Throwable $e) {
        }
        return true;
    }
    return false;
}

function chatUploadTempDir(): string
{
    $dir = dirname(__DIR__) . '/sessions/chat_uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function chatUploadFinalDir(): string
{
    $dir = dirname(__DIR__) . '/assets/uploads/chat';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function chatTokenBasePath(int $playerId, string $token): string
{
    return chatUploadTempDir() . '/dm_' . $playerId . '_' . $token;
}

function chatNormalizePreview(array $row): array
{
    if (!empty($row['attachment_path']) && trim((string) ($row['message'] ?? '')) === '') {
        $row['message'] = t('dm.attachment_preview_label');
    }
    return $row;
}

function chatUpsertRead(PDO $db, int $playerId, int $partnerId, int $lastId): void
{
    if ($lastId <= 0 || $partnerId <= 0) {
        return;
    }
    $db->prepare("
        INSERT INTO chat_conversation_reads (player_id, partner_id, last_read_message_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id))
    ")->execute([$playerId, $partnerId, $lastId]);
}

function chatHandleUploadChunk(PDO $db, int $playerId, string $rawInput): never
{
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? ''));
    $name = trim((string) ($_SERVER['HTTP_X_UPLOAD_NAME'] ?? ''));
    $mime = trim((string) ($_SERVER['HTTP_X_UPLOAD_MIME'] ?? 'application/octet-stream'));
    $index = max(0, (int) ($_SERVER['HTTP_X_UPLOAD_INDEX'] ?? 0));
    $total = max(1, (int) ($_SERVER['HTTP_X_UPLOAD_TOTAL'] ?? 1));
    $size = max(0, (int) ($_SERVER['HTTP_X_UPLOAD_SIZE'] ?? 0));
    $receiverId = max(0, (int) ($_SERVER['HTTP_X_UPLOAD_RECEIVER'] ?? 0));

    if ($token === '' || $name === '' || $receiverId <= 0) {
        chatJson(['error' => t('dm.err_upload_invalid')]);
    }
    if (strlen($rawInput) === 0 || strlen($rawInput) > 15360) {
        chatJson(['error' => t('dm.err_upload_chunk')]);
    }

    $base = chatTokenBasePath($playerId, $token);
    $partPath = $base . '.part';
    $metaPath = $base . '.json';

    if ($index === 0 && file_exists($partPath)) {
        @unlink($partPath);
        @unlink($metaPath);
    }

    $meta = [
        'player_id' => $playerId,
        'receiver_id' => $receiverId,
        'name' => $name,
        'mime' => $mime,
        'size' => $size,
        'total' => $total,
        'last_index' => $index,
        'updated_at' => time(),
    ];
    if (file_exists($metaPath)) {
        $existing = json_decode((string) file_get_contents($metaPath), true);
        if (is_array($existing)) {
            $meta = array_merge($existing, $meta);
        }
    }

    if ($index !== (int) ($meta['last_index'] ?? 0) && $index !== ((int) ($meta['last_index'] ?? 0) + 1)) {
        chatJson(['error' => t('dm.err_upload_order')]);
    }

    $writeFlags = $index === 0 ? 0 : FILE_APPEND;
    if (file_put_contents($partPath, $rawInput, $writeFlags) === false) {
        GameLog::error('ChatApi.php', 'DM chunk write failed', null, [
            'player_id' => $playerId,
            'token' => $token,
            'index' => $index,
        ]);
        chatJson(['error' => t('dm.err_upload_save')]);
    }

    $meta['last_index'] = $index;
    $meta['updated_at'] = time();
    file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE));

    chatJson([
        'ok' => true,
        'token' => $token,
        'index' => $index,
        'complete' => ($index + 1) >= $total,
    ]);
}

function chatFinalizeAttachment(PDO $db, int $playerId, int $receiverId, string $token): array
{
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
    if ($token === '') {
        throw new RuntimeException(t('dm.err_upload_invalid'));
    }

    $base = chatTokenBasePath($playerId, $token);
    $partPath = $base . '.part';
    $metaPath = $base . '.json';
    if (!is_file($partPath) || !is_file($metaPath)) {
        throw new RuntimeException(t('dm.err_upload_missing'));
    }

    $meta = json_decode((string) file_get_contents($metaPath), true);
    if (!is_array($meta) || (int) ($meta['player_id'] ?? 0) !== $playerId || (int) ($meta['receiver_id'] ?? 0) !== $receiverId) {
        throw new RuntimeException(t('dm.err_upload_invalid'));
    }

    $size = filesize($partPath);
    if ($size === false || $size <= 0 || $size > (3 * 1024 * 1024)) {
        throw new RuntimeException(t('dm.err_upload_limit'));
    }

    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $fi ? finfo_file($fi, $partPath) : false;
    if ($fi) {
        finfo_close($fi);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!$realMime || !isset($allowed[$realMime])) {
        @unlink($partPath);
        @unlink($metaPath);
        throw new RuntimeException(t('dm.err_upload_format'));
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) ($meta['name'] ?? 'image')));
    $finalDir = chatUploadFinalDir();
    $finalName = 'dm_' . $playerId . '_' . time() . '_' . substr($token, 0, 8) . '.' . $allowed[$realMime];
    $finalPath = $finalDir . '/' . $finalName;

    if (!@rename($partPath, $finalPath)) {
        if (!@copy($partPath, $finalPath)) {
            throw new RuntimeException(t('dm.err_upload_save'));
        }
        @unlink($partPath);
    }
    @unlink($metaPath);

    return [
        'attachment_path' => 'assets/uploads/chat/' . $finalName,
        'attachment_name' => $safeName,
        'attachment_type' => $realMime,
        'attachment_size' => (int) $size,
    ];
}

function chatDeleteAttachmentFile(?string $path): void
{
    if (!$path) {
        return;
    }
    $full = dirname(__DIR__) . '/' . ltrim($path, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

if ($method === 'POST' && ($_SERVER['HTTP_X_UPLOAD_ACTION'] ?? '') === 'dm_upload_chunk') {
    chatHandleUploadChunk($db, $playerId, $rawInput);
}

if ($method === 'POST') {
    if ($action === '' || $action === 'send') {
        $input = $jsonInput ?? [];
        $msg = trim((string) ($input['message'] ?? ''));
        $receiverId = isset($input['receiver_id']) ? (int) $input['receiver_id'] : null;
        $channel = $receiverId ? 'private' : 'global';
        $attachmentToken = trim((string) ($input['attachment_token'] ?? ''));

        if ($msg === '' && $attachmentToken === '') {
            chatJson(['error' => t('chat.err_msg_length')]);
        }
        if ($channel === 'global' && mb_strlen($msg) > 300) {
            chatJson(['error' => t('chat.err_msg_length')]);
        }
        if ($channel === 'private' && mb_strlen($msg) > 500) {
            chatJson(['error' => t('dm.err_msg_length')]);
        }

        $banMsg = chatIsBanned($db, $playerId);
        if ($banMsg) {
            chatJson(['error' => $banMsg]);
        }
        if (chatIsRateLimited($db, $playerId)) {
            chatJson(['error' => t('chat.err_rate_limit')]);
        }

        $displayName = chatGetDisplayName($db, $playerId);
        if ($channel === 'global') {
            $msg = ChatFilter::filter($db, $msg);
        }

        $attachment = [
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_type' => null,
            'attachment_size' => null,
        ];

        if ($attachmentToken !== '') {
            if ($channel !== 'private' || !$receiverId) {
                chatJson(['error' => t('dm.err_attachment_private_only')]);
            }
            try {
                $attachment = chatFinalizeAttachment($db, $playerId, $receiverId, $attachmentToken);
            } catch (Throwable $e) {
                chatJson(['error' => $e->getMessage()]);
            }
        }

        $stmt = $db->prepare("
            INSERT INTO chat_messages (
                sender_id, receiver_id, channel, username, message,
                attachment_path, attachment_name, attachment_type, attachment_size
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $playerId,
            $receiverId,
            $channel,
            $displayName,
            $msg,
            $attachment['attachment_path'],
            $attachment['attachment_name'],
            $attachment['attachment_type'],
            $attachment['attachment_size'],
        ]);
        $messageId = (int) $db->lastInsertId();

        if ($receiverId) {
            chatUpsertRead($db, $playerId, $receiverId, $messageId);
        }

        chatJson([
            'ok' => true,
            'id' => $messageId,
            'attachment' => !empty($attachment['attachment_path']),
        ]);
    }

    if ($action === 'report') {
        $input = $jsonInput ?? [];
        $messageId = (int) ($input['message_id'] ?? 0);
        $reason = (string) ($input['reason'] ?? 'inne');
        if (!in_array($reason, ['spam', 'obraza', 'inne'], true)) {
            $reason = 'inne';
        }
        if ($messageId <= 0) {
            chatJson(['error' => t('chat.err_no_message_id')]);
        }

        $dup = $db->prepare("SELECT id FROM chat_reports WHERE message_id=? AND reporter_id=? LIMIT 1");
        $dup->execute([$messageId, $playerId]);
        if ($dup->fetch()) {
            chatJson(['error' => t('chat.err_already_reported')]);
        }

        try {
            $db->prepare("INSERT INTO chat_reports (message_id, reporter_id, reason) VALUES (?,?,?)")
                ->execute([$messageId, $playerId, $reason]);
            chatJson(['ok' => true, 'message' => t('chat.report_success')]);
        } catch (Throwable $e) {
            GameLog::error('ChatApi.php', 'report insert failed', $e, [
                'player_id' => $playerId,
                'message_id' => $messageId,
            ]);
            chatJson(['error' => t('common.app_error')]);
        }
    }

    if ($action === 'delete_attachment') {
        $input = $jsonInput ?? [];
        $messageId = (int) ($input['message_id'] ?? 0);
        if ($messageId <= 0) {
            chatJson(['error' => t('dm.err_invalid_message')]);
        }
        $stmt = $db->prepare("
            SELECT id, sender_id, channel, attachment_path, message
            FROM chat_messages
            WHERE id=? AND is_deleted=0
            LIMIT 1
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        if (!$message || (int) $message['sender_id'] !== $playerId || $message['channel'] !== 'private' || empty($message['attachment_path'])) {
            chatJson(['error' => t('dm.err_delete_attachment')]);
        }

        chatDeleteAttachmentFile($message['attachment_path']);
        $newMessage = trim((string) $message['message']);
        if ($newMessage === '') {
            $db->prepare("
                UPDATE chat_messages
                SET is_deleted=1, attachment_path=NULL, attachment_name=NULL, attachment_type=NULL, attachment_size=NULL
                WHERE id=?
            ")->execute([$messageId]);
        } else {
            $db->prepare("
                UPDATE chat_messages
                SET attachment_path=NULL, attachment_name=NULL, attachment_type=NULL, attachment_size=NULL
                WHERE id=?
            ")->execute([$messageId]);
        }

        chatJson(['ok' => true, 'message' => t('dm.attachment_deleted')]);
    }

    chatJson(['error' => t('common.unknown_action', ['action' => $action])]);
}

try {
    $autoClearRows = $db->query("SELECT `key`,`value` FROM well_config WHERE `key` IN ('chat_auto_clear_enabled','chat_auto_clear_interval','chat_auto_clear_last_at')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($autoClearRows['chat_auto_clear_enabled']) && (int) $autoClearRows['chat_auto_clear_enabled'] === 1) {
        $interval = max(15, (int) ($autoClearRows['chat_auto_clear_interval'] ?? 30));
        $lastAt = $autoClearRows['chat_auto_clear_last_at'] ?? null;
        $ago = $lastAt ? (time() - strtotime((string) $lastAt)) : PHP_INT_MAX;
        if ($ago >= $interval * 60) {
            $cutoff = date('Y-m-d H:i:s', time() - $interval * 60);
            $db->prepare("
                UPDATE chat_messages
                SET is_deleted=1
                WHERE created_at < ? AND is_deleted=0 AND (is_pinned=0 OR is_pinned IS NULL) AND channel='global'
            ")->execute([$cutoff]);
            $db->prepare("
                INSERT INTO well_config (`key`,`value`) VALUES ('chat_auto_clear_last_at',NOW())
                ON DUPLICATE KEY UPDATE `value`=NOW()
            ")->execute([]);
        }
    }
} catch (Throwable $e) {
}

if ($action === 'conversations') {
    $stmt = $db->prepare("
        SELECT
            IF(cm.sender_id = :pid, cm.receiver_id, cm.sender_id) AS partner_id,
            MAX(cm.id) AS last_id,
            (
                SELECT cm3.message
                FROM chat_messages cm3
                WHERE cm3.id = MAX(cm.id)
            ) AS last_message,
            (
                SELECT cm4.attachment_path
                FROM chat_messages cm4
                WHERE cm4.id = MAX(cm.id)
            ) AS attachment_path,
            (
                SELECT cm5.created_at
                FROM chat_messages cm5
                WHERE cm5.id = MAX(cm.id)
            ) AS last_at
        FROM chat_messages cm
        WHERE cm.channel='private'
          AND cm.is_deleted=0
          AND (cm.sender_id = :pid OR cm.receiver_id = :pid)
        GROUP BY partner_id
        ORDER BY last_id DESC
        LIMIT 50
    ");
    $stmt->execute([':pid' => $playerId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unreadStmt = $db->prepare("
        SELECT cm.sender_id AS partner_id, COUNT(*) AS unread_count
        FROM chat_messages cm
        LEFT JOIN chat_conversation_reads r
            ON r.player_id = ? AND r.partner_id = cm.sender_id
        WHERE cm.channel='private'
          AND cm.receiver_id = ?
          AND cm.is_deleted = 0
          AND cm.id > COALESCE(r.last_read_message_id, 0)
        GROUP BY cm.sender_id
    ");
    $unreadStmt->execute([$playerId, $playerId]);
    $unreadMap = [];
    foreach ($unreadStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $unreadMap[(int) $row['partner_id']] = (int) $row['unread_count'];
    }

    foreach ($conversations as &$conversation) {
        $p = $db->prepare("SELECT COALESCE(NULLIF(company_name,''), username) AS name, avatar_path FROM players WHERE id=? LIMIT 1");
        $p->execute([(int) $conversation['partner_id']]);
        $partner = $p->fetch() ?: ['name' => '?', 'avatar_path' => null];
        $conversation['partner_name'] = $partner['name'];
        $conversation['partner_avatar'] = $partner['avatar_path'] ?? null;
        $conversation['unread_count'] = $unreadMap[(int) $conversation['partner_id']] ?? 0;
        $conversation = chatNormalizePreview($conversation);
    }
    unset($conversation);

    chatJson(['conversations' => $conversations, 'my_id' => $playerId]);
}

if ($action === 'players') {
    $stmt = $db->prepare("
        SELECT id, COALESCE(NULLIF(company_name,''), username) AS name
        FROM players
        WHERE status='active' AND id != ?
        ORDER BY name ASC
        LIMIT 50
    ");
    $stmt->execute([$playerId]);
    chatJson(['players' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'dm_status') {
    $stmt = $db->prepare("
        SELECT cm.sender_id AS partner_id, COUNT(*) AS unread_count, MAX(cm.id) AS last_id
        FROM chat_messages cm
        LEFT JOIN chat_conversation_reads r
            ON r.player_id = ? AND r.partner_id = cm.sender_id
        WHERE cm.channel='private'
          AND cm.receiver_id = ?
          AND cm.is_deleted = 0
          AND cm.id > COALESCE(r.last_read_message_id, 0)
        GROUP BY cm.sender_id
    ");
    $stmt->execute([$playerId, $playerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($rows as $row) {
        $total += (int) $row['unread_count'];
    }
    chatJson([
        'ok' => true,
        'unread_total' => $total,
        'partners' => $rows,
    ]);
}

$chatHasAdminCols = false;
try {
    $colCheck = $db->query("SHOW COLUMNS FROM chat_messages LIKE 'is_admin'");
    $chatHasAdminCols = (bool) $colCheck->fetch();
} catch (Throwable $e) {
    $chatHasAdminCols = false;
}

if (isset($_GET['pinned_only'])) {
    $pinned = [];
    if ($chatHasAdminCols) {
        try {
            $pinned = $db->query("
                SELECT cm.id, cm.sender_id, cm.username, cm.message, cm.is_deleted, cm.is_admin, cm.is_pinned,
                       DATE_FORMAT(cm.created_at, '%H:%i') AS time,
                       p.avatar_path
                FROM chat_messages cm
                LEFT JOIN players p ON p.id = cm.sender_id
                WHERE cm.channel='global' AND cm.is_deleted=0 AND cm.is_admin=1 AND cm.is_pinned=1
                ORDER BY cm.pinned_at ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $pinned = [];
        }
    }
    chatJson(['pinned' => $pinned, 'my_id' => $playerId]);
}

$since = (int) ($_GET['since'] ?? 0);
$withPlayer = isset($_GET['with']) ? (int) $_GET['with'] : null;

if ($withPlayer) {
    if ($since > 0) {
        $stmt = $db->prepare("
            SELECT cm.id, cm.sender_id, cm.receiver_id, cm.username, cm.message, cm.is_deleted,
                   cm.attachment_path, cm.attachment_name, cm.attachment_type, cm.attachment_size,
                   DATE_FORMAT(cm.created_at, '%H:%i') AS time,
                   p.avatar_path
            FROM chat_messages cm
            LEFT JOIN players p ON p.id = cm.sender_id
            WHERE cm.channel='private'
              AND cm.is_deleted=0
              AND cm.id > ?
              AND ((cm.sender_id=? AND cm.receiver_id=?) OR (cm.sender_id=? AND cm.receiver_id=?))
            ORDER BY cm.id ASC
            LIMIT 50
        ");
        $stmt->execute([$since, $playerId, $withPlayer, $withPlayer, $playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("
            SELECT cm.id, cm.sender_id, cm.receiver_id, cm.username, cm.message, cm.is_deleted,
                   cm.attachment_path, cm.attachment_name, cm.attachment_type, cm.attachment_size,
                   DATE_FORMAT(cm.created_at, '%H:%i') AS time,
                   p.avatar_path
            FROM chat_messages cm
            LEFT JOIN players p ON p.id = cm.sender_id
            WHERE cm.channel='private'
              AND cm.is_deleted=0
              AND ((cm.sender_id=? AND cm.receiver_id=?) OR (cm.sender_id=? AND cm.receiver_id=?))
            ORDER BY cm.id DESC
            LIMIT 50
        ");
        $stmt->execute([$playerId, $withPlayer, $withPlayer, $playerId]);
        $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $maxId = 0;
    foreach ($rows as &$row) {
        $maxId = max($maxId, (int) $row['id']);
        $row = chatNormalizePreview($row);
    }
    unset($row);
    chatUpsertRead($db, $playerId, $withPlayer, $maxId);

    chatJson(['messages' => $rows, 'my_id' => $playerId]);
}

if ($since > 0) {
    if ($chatHasAdminCols) {
        $stmt = $db->prepare("
            SELECT cm.id, cm.sender_id, cm.username, cm.message, cm.is_deleted, cm.is_admin, cm.is_pinned,
                   DATE_FORMAT(cm.created_at, '%H:%i') AS time,
                   p.avatar_path
            FROM chat_messages cm
            LEFT JOIN players p ON p.id = cm.sender_id
            WHERE cm.channel='global' AND cm.is_deleted=0 AND cm.is_pinned=0 AND cm.id > ?
            ORDER BY cm.id ASC
            LIMIT 50
        ");
    } else {
        $stmt = $db->prepare("
            SELECT cm.id, cm.sender_id, cm.username, cm.message, cm.is_deleted,
                   0 AS is_admin, 0 AS is_pinned,
                   DATE_FORMAT(cm.created_at, '%H:%i') AS time,
                   p.avatar_path
            FROM chat_messages cm
            LEFT JOIN players p ON p.id = cm.sender_id
            WHERE cm.channel='global' AND cm.is_deleted=0 AND cm.id > ?
            ORDER BY cm.id ASC
            LIMIT 50
        ");
    }
    $stmt->execute([$since]);
    chatJson(['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'my_id' => $playerId]);
}

$pinned = [];
if ($chatHasAdminCols) {
    try {
        $pinned = $db->query("
            SELECT cm.id, cm.sender_id, cm.username, cm.message, cm.is_deleted, cm.is_admin, cm.is_pinned,
                   DATE_FORMAT(cm.created_at, '%H:%i') AS time,
                   p.avatar_path
            FROM chat_messages cm
            LEFT JOIN players p ON p.id = cm.sender_id
            WHERE cm.channel='global' AND cm.is_deleted=0 AND cm.is_admin=1 AND cm.is_pinned=1
            ORDER BY cm.pinned_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $pinned = [];
    }

    $stmt = $db->query("
        SELECT cm.id, cm.sender_id, cm.username, cm.message, cm.is_deleted, cm.is_admin, cm.is_pinned,
               DATE_FORMAT(cm.created_at, '%H:%i') AS time,
               p.avatar_path
        FROM chat_messages cm
        LEFT JOIN players p ON p.id = cm.sender_id
        WHERE cm.channel='global' AND cm.is_deleted=0 AND cm.is_pinned=0
        ORDER BY cm.id DESC
        LIMIT 50
    ");
} else {
    $stmt = $db->query("
        SELECT cm.id, cm.sender_id, cm.username, cm.message, cm.is_deleted,
               0 AS is_admin, 0 AS is_pinned,
               DATE_FORMAT(cm.created_at, '%H:%i') AS time,
               p.avatar_path
        FROM chat_messages cm
        LEFT JOIN players p ON p.id = cm.sender_id
        WHERE cm.channel='global' AND cm.is_deleted=0
        ORDER BY cm.id DESC
        LIMIT 50
    ");
}

$rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
chatJson(['messages' => $rows, 'pinned' => $pinned, 'my_id' => $playerId]);
