<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('profile.php') : microtime(true);
require_once __DIR__ . '/src/init.php';

Auth::requireLogin();
$playerId = Auth::getUserId();
$db = Database::getInstance()->getConnection();
GameLog::info('profile.php', 'Zaladowano profil', ['player_id' => $playerId]);

$errors = [];
$success = [];
$csrfToken = CSRF::generateToken();

$avatarDir = __DIR__ . '/assets/img/avatars/';
if (!is_dir($avatarDir)) {
    mkdir($avatarDir, 0755, true);
}

function profileIsAjaxAvatarUpload(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && (($_SERVER['HTTP_X_UPLOAD_ACTION'] ?? '') === 'upload_avatar'
            || ((($_POST['action'] ?? '') === 'upload_avatar')
                && (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')));
}

function profileSendAvatarJson(bool $ok, string $message, ?string $avatarUrl = null, array $extra = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'ok' => $ok,
        'message' => $message,
        'avatar_url' => $avatarUrl,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function profilePersistAvatar(PDO $db, int $playerId, string $avatarDir, string $mime, string $bytes): array
{
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $maxSize = 2 * 1024 * 1024;
    $size = strlen($bytes);

    if ($size <= 0) {
        return ['ok' => false, 'error_key' => 'profile.err_upload_error'];
    }
    if (!in_array($mime, $allowed, true)) {
        return ['ok' => false, 'error_key' => 'profile.err_upload_format'];
    }
    if ($size > $maxSize) {
        return ['ok' => false, 'error_key' => 'profile.err_upload_size'];
    }
    if (!is_dir($avatarDir) && !mkdir($avatarDir, 0755, true) && !is_dir($avatarDir)) {
        return ['ok' => false, 'error_key' => 'profile.err_upload_save'];
    }

    $filename = 'avatar_' . $playerId . '_' . time() . '.' . ($extMap[$mime] ?? 'jpg');
    $destPath = $avatarDir . $filename;

    if (file_put_contents($destPath, $bytes, LOCK_EX) === false) {
        GameLog::error('profile.php', 'file_put_contents avatar FAILED', null, [
            'dest' => $destPath,
            'player_id' => $playerId,
            'dir_exists' => is_dir($avatarDir),
            'dir_write' => is_writable($avatarDir),
        ]);
        return ['ok' => false, 'error_key' => 'profile.err_upload_save'];
    }

    $oldStmt = $db->prepare("SELECT avatar_path FROM players WHERE id=?");
    $oldStmt->execute([$playerId]);
    $old = $oldStmt->fetchColumn();
    if ($old) {
        $oldPath = __DIR__ . '/' . ltrim((string) $old, '/');
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $relPath = 'assets/img/avatars/' . $filename;
    $db->prepare("UPDATE players SET avatar_path=? WHERE id=?")->execute([$relPath, $playerId]);

    GameLog::info('profile.php', 'Avatar zapisany', [
        'player_id' => $playerId,
        'path' => $relPath,
        'mime' => $mime,
        'size' => $size,
    ]);

    return [
        'ok' => true,
        'path' => $relPath,
        'url' => '/' . $relPath . '?v=' . time(),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjaxAvatarUpload = profileIsAjaxAvatarUpload();
    $csrfValue = $isAjaxAvatarUpload
        ? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')
        : ($_POST['_token'] ?? '');

    if (!CSRF::validateToken($csrfValue)) {
        $errors[] = t('profile.err_csrf');
        GameLog::warn('profile.php', 'CSRF fail', ['player_id' => $playerId]);
        if ($isAjaxAvatarUpload) {
            profileSendAvatarJson(false, t('profile.err_csrf'));
        }
    } else {
        $action = $isAjaxAvatarUpload
            ? ($_SERVER['HTTP_X_UPLOAD_ACTION'] ?? 'upload_avatar')
            : ($_POST['action'] ?? '');

        if ($isAjaxAvatarUpload && $action === 'upload_avatar') {
            $rawBytes = file_get_contents('php://input') ?: '';
            $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream')[0]));

            $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $realMime = $contentType;
            if ($fi !== false) {
                $detected = finfo_buffer($fi, $rawBytes);
                finfo_close($fi);
                if ($detected !== false) {
                    $realMime = $detected;
                }
            }

            GameLog::info('profile.php', 'Avatar AJAX upload request', [
                'player_id' => $playerId,
                'content_length' => $contentLength,
                'bytes' => strlen($rawBytes),
                'content_type' => $contentType,
                'real_mime' => $realMime,
            ]);

            $result = profilePersistAvatar($db, $playerId, $avatarDir, $realMime, $rawBytes);
            if (!$result['ok']) {
                profileSendAvatarJson(false, t($result['error_key'] ?? 'profile.err_upload_error'));
            }
            profileSendAvatarJson(true, t('profile.success_avatar'), $result['url'] ?? null);
        }

        if ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            $row = $db->prepare("SELECT password_hash FROM players WHERE id=?");
            $row->execute([$playerId]);
            $player = $row->fetch();

            if (!$player || !password_verify($current, $player['password_hash'])) {
                $errors[] = t('profile.err_wrong_pass');
            } elseif (strlen($new) < 8) {
                $errors[] = t('profile.err_pass_too_short');
            } elseif ($new !== $confirm) {
                $errors[] = t('profile.err_pass_mismatch');
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $db->prepare("UPDATE players SET password_hash=? WHERE id=?")->execute([$hash, $playerId]);
                $success[] = t('profile.success_pass');
                GameLog::info('profile.php', 'Haslo zmienione', ['player_id' => $playerId]);
            }
        }

        if ($action === 'update_profile') {
            $companyName = trim($_POST['company_name'] ?? '');
            if (strlen($companyName) > 80) {
                $errors[] = t('profile.err_company_too_long');
            } else {
                $db->prepare("UPDATE players SET company_name=? WHERE id=?")->execute([$companyName ?: null, $playerId]);
                $success[] = t('profile.success_company');
                GameLog::info('profile.php', 'Nazwa firmy zaktualizowana', [
                    'player_id' => $playerId,
                    'company_name' => $companyName,
                ]);
            }
        }

        if ($action === 'upload_avatar' && isset($_FILES['avatar'])) {
            $file = $_FILES['avatar'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $extMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            $maxSize = 2 * 1024 * 1024;

            GameLog::info('profile.php', 'Avatar FORM upload request', [
                'player_id' => $playerId,
                'file_error' => $file['error'] ?? null,
                'file_size' => $file['size'] ?? null,
                'file_type' => $file['type'] ?? null,
                'tmp_name' => $file['tmp_name'] ?? null,
                'content_length' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
            ]);

            $realMime = 'application/octet-stream';
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                if (function_exists('finfo_open')) {
                    $fi = finfo_open(FILEINFO_MIME_TYPE);
                    if ($fi !== false) {
                        $detected = finfo_file($fi, $file['tmp_name']);
                        finfo_close($fi);
                        if ($detected !== false) {
                            $realMime = $detected;
                        } else {
                            $realMime = $file['type'];
                        }
                    } else {
                        $realMime = $file['type'];
                    }
                } else {
                    $realMime = $file['type'];
                }
            }

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = t('profile.err_upload_error');
            } elseif (!in_array($realMime, $allowed, true)) {
                $errors[] = t('profile.err_upload_format');
            } elseif (($file['size'] ?? 0) > $maxSize) {
                $errors[] = t('profile.err_upload_size');
            } else {
                $ext = $extMap[$realMime] ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'avatar_' . $playerId . '_' . time() . '.' . $ext;
                $destPath = $avatarDir . $filename;

                if (!is_dir($avatarDir)) {
                    mkdir($avatarDir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $oldStmt = $db->prepare("SELECT avatar_path FROM players WHERE id=?");
                    $oldStmt->execute([$playerId]);
                    $old = $oldStmt->fetchColumn();
                    if ($old) {
                        $oldPath = __DIR__ . '/' . ltrim((string) $old, '/');
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }

                    $relPath = 'assets/img/avatars/' . $filename;
                    $db->prepare("UPDATE players SET avatar_path=? WHERE id=?")->execute([$relPath, $playerId]);
                    $success[] = t('profile.success_avatar');
                    GameLog::info('profile.php', 'Avatar zapisany przez move_uploaded_file', [
                        'player_id' => $playerId,
                        'path' => $relPath,
                        'mime' => $realMime,
                        'size' => $file['size'] ?? null,
                    ]);
                } else {
                    $phpErr = error_get_last();
                    GameLog::error('profile.php', 'move_uploaded_file FAILED', null, [
                        'dest' => $destPath,
                        'tmp' => $file['tmp_name'] ?? null,
                        'tmp_exists' => !empty($file['tmp_name']) && file_exists($file['tmp_name']),
                        'dir_exists' => is_dir($avatarDir),
                        'dir_write' => is_writable($avatarDir),
                        'php_error' => $phpErr['message'] ?? 'brak',
                        'player_id' => $playerId,
                    ]);
                    $errors[] = t('profile.err_upload_save');
                }
            }
        } elseif ($action === 'upload_avatar') {
            GameLog::warn('profile.php', 'Avatar upload bez pliku', [
                'player_id' => $playerId,
                'post_keys' => array_keys($_POST),
                'files_keys' => array_keys($_FILES),
                'content_length' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
            ]);
        }
    }
}

$stmt = $db->prepare("
    SELECT p.*,
        (SELECT COUNT(*) FROM wells WHERE player_id=p.id) AS well_count,
        (SELECT COUNT(*) FROM wells WHERE player_id=p.id AND status NOT IN ('seized','blowout')) AS active_wells
    FROM players p WHERE p.id=?
");
$stmt->execute([$playerId]);
$playerData = $stmt->fetch();

try {
    $statsStmt = $db->prepare("
        SELECT COALESCE(SUM(sold_amount), 0) AS total_sold,
               COUNT(*) AS total_offers
        FROM market_offers
        WHERE player_id = ? AND status = 'completed'
    ");
    $statsStmt->execute([$playerId]);
    $sellStats = $statsStmt->fetch();
} catch (Throwable $e) {
    $sellStats = ['total_sold' => 0, 'total_offers' => 0];
}

$bmStats = ['total_transactions' => 0, 'total_revenue' => 0, 'total_penalties' => 0, 'times_detected' => 0, 'total_bbl' => 0];
$bmScore = 0;
try {
    $bmService = new BlackMarketService();
    $bmStats = $bmService->getPlayerStats($playerId);
    $bmScore = (float) ($playerData['black_market_score'] ?? 0);
} catch (Throwable $e) {
}

// Wiarygodnosc firmy / Company credibility
$credibilityScore = CompanyCredibilityService::DEFAULT_SCORE;
$credibilityLevel = 'shaky';
try {
    $credService    = new CompanyCredibilityService($db);
    $credibilityScore = $credService->getScore($playerId);
    $credibilityLevel = $credService->getLevel($credibilityScore);
} catch (Throwable $e) {
    GameLog::error('profile.php', 'CompanyCredibilityService failed', $e, ['player_id' => $playerId]);
}

$avatarUrl = $playerData['avatar_path']
    ? '/' . htmlspecialchars($playerData['avatar_path']) . '?v=' . time()
    : null;

$companyName = htmlspecialchars($playerData['company_name'] ?? '');
$memberSince = date('d.m.Y', strtotime($playerData['created_at']));
$lastLogin = $playerData['last_login_at']
    ? date('d.m.Y H:i', strtotime($playerData['last_login_at']))
    : '-';

$viewData = compact(
    'playerId',
    'playerData',
    'csrfToken',
    'errors',
    'success',
    'avatarUrl',
    'companyName',
    'memberSince',
    'lastLogin',
    'sellStats',
    'bmStats',
    'bmScore',
    'credibilityScore',
    'credibilityLevel'
);

$pageTitle = t('profile.page_title');
$extraCss = ['/assets/css/profile.css', '/assets/css/credibility.css'];
require_once __DIR__ . '/templates/header.php';
require __DIR__ . '/templates/views/profile/main.php';
require_once __DIR__ . '/templates/footer.php';
