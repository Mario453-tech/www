<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('change_password.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password']     ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM admins WHERE id = :id");
        $stmt->execute([':id' => AdminAuth::getAdminId()]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $err = t('admin.change_password.err_wrong_current');
        } elseif (strlen($new) < 8) {
            $err = t('admin.change_password.err_too_short');
        } elseif ($new !== $confirm) {
            $err = t('admin.change_password.err_mismatch');
        } else {
            if (AdminAuth::changePassword((int)AdminAuth::getAdminId(), $new)) {
                AdminLog::log('password_change', 'Admin changed password', null, 'system');
                $msg = t('admin.change_password.msg_changed');
            } else {
                $err = t('admin.change_password.err_failed');
            }
        }
    }
}

$viewData = [
    'msg' => $msg,
    'err' => $err,
];

$pageTitle = t('admin.change_password.page_title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/change_password/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('change_password.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Wystapil blad aplikacji.';
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('change_password.php', $_codexGuardStart);
    }
}
