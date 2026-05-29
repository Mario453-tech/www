<?php

/**
 * Unified admin authentication: login, game-session SSO and password reset.
 * PL: Zunifikowana autoryzacja admina: login, SSO z gry i reset hasla.
 */
class AdminAuth
{
    private const SESSION_TTL = 7200; // 2h
    private const MAX_ATTEMPTS = 3; // lock after this many bad attempts
    private const LOCKOUT_MINUTES = 30; // account lock duration in minutes

 // Login by username or email.
 // PL: Logowanie po nazwie uzytkownika lub emailu.
    public static function login(string $login, string $password): bool
    {
        $db = Database::getInstance()->getConnection();
        $field = str_contains($login, '@') ? 'email' : 'username';
        $stmt = $db->prepare("SELECT id, username, email, password_hash, is_active, failed_attempts, lock_until FROM admins WHERE {$field} = ? LIMIT 1");
        $stmt->execute([$login]);
        $admin = $stmt->fetch();

        if (!$admin) {
            self::logFail($login, "No account {$field}='$login'");
            return false;
        }
        if (!(int)$admin['is_active']) {
            self::logFail($login, 'Account inactive');
            return false;
        }

 // Check account lock status.
 // PL: Sprawdz blokade konta.
        if ($admin['lock_until'] && strtotime($admin['lock_until']) > time()) {
            $minutesLeft = (int)ceil((strtotime($admin['lock_until']) - time()) / 60);
            self::log('ACCOUNT_LOCKED', "Login attempt on locked account '$login' - {$minutesLeft} min left");
            return false;
        }

        if (!password_verify($password, $admin['password_hash'])) {
            self::logFailAccount($admin, $login);
            return false;
        }

 // Successful login clears failed-attempt counters.
 // PL: Udane logowanie zeruje liczniki bledow.
        self::setSession($admin);
        $db->prepare("UPDATE admins SET last_login_at = NOW(), last_login_ip = ?, failed_attempts = 0, lock_until = NULL WHERE id = ?")
            ->execute([$_SERVER['REMOTE_ADDR'] ?? '', $admin['id']]);
        self::log('LOGIN_OK', "Logged in: '{$admin['username']}' via {$field}");
        return true;
    }

 // Auto-login if a logged-in player also has an admin account.
 // PL: Auto-login, jesli zalogowany gracz ma tez konto admina.
    public static function trySSO(): bool
    {
        if (self::isLoggedIn()) {
            return true;
        }
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT email FROM players WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $player = $stmt->fetch();
        if (!$player || empty($player['email'])) {
            return false;
        }

        $stmt = $db->prepare("SELECT id, username, email, is_active FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$player['email']]);
        $admin = $stmt->fetch();
        if (!$admin || !(int)$admin['is_active']) {
            return false;
        }

        self::setSession($admin);
        self::log('SSO', "Auto-login player_id={$_SESSION['user_id']} -> admin '{$admin['username']}'");
        return true;
    }

 // Store admin session fields.
 // PL: Zapisz pola sesji admina.
    private static function setSession(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_user'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'] ?? '';
        $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_last_active'] = time();
    }

    public static function requireLogin(): void
    {
 // Try SSO first if player session exists.
 // PL: Najpierw sproboj SSO, jesli istnieje sesja gracza.
        if (!self::isLoggedIn()) {
            self::trySSO();
        }

        if (!self::isLoggedIn()) {
            $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
            header('Location: /admin/login.php');
            exit();
        }
        $_SESSION['admin_last_active'] = time();
    }

    public static function isLoggedIn(): bool
    {
        if (empty($_SESSION['admin_logged_in'])) {
            return false;
        }
        if (time() - ($_SESSION['admin_last_active'] ?? 0) > self::SESSION_TTL) {
            self::logout(false);
            return false;
        }
        return true;
    }

    public static function logout(bool $redirect = true): void
    {
        $user = $_SESSION['admin_user'] ?? '?';
        self::log('LOGOUT', "Logged out: '$user'");

 // Destroy the whole session, including the player SSO session.
 // PL: Zniszcz cala sesje, razem z sesja gracza od SSO.
        session_unset();
        session_destroy();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        if ($redirect) {
            header('Location: /admin/login.php?logged_out=1');
            exit();
        }
    }

    public static function getAdminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
    }

    public static function getAdminUsername(): string
    {
        return $_SESSION['admin_user'] ?? 'admin';
    }

    public static function getAdminEmail(): string
    {
        return $_SESSION['admin_email'] ?? '';
    }

 // Password reset flow.
 // PL: Obsluga resetu hasla.
    public static function sendPasswordReset(string $email): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username FROM admins WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

 // Always return success, do not reveal account existence.
 // PL: Zawsze zwracaj sukces, bez ujawniania czy konto istnieje.
        if (!$admin) {
            self::log('RESET_NOTFOUND', "Password reset - no account: $email");
            return ['success' => true];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $db->prepare("DELETE FROM admin_password_resets WHERE email = ?")->execute([$email]);
        $db->prepare("INSERT INTO admin_password_resets (email, token_hash, expires_at) VALUES (?,?,?)")
            ->execute([$email, $tokenHash, $expiresAt]);

        $resetUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'oilempire.pl') . "/admin/reset_password.php?token={$token}";

        $body = "
        <div style='font-family:monospace;background:#1a1a1a;color:#e0e0e0;padding:32px;max-width:480px;margin:0 auto'>
            <div style='font-size:20px;color:#f90;margin-bottom:24px'>" . t('admin_auth.email_title') . "</div>
            <p style='margin-bottom:16px'>" . t('admin_auth.email_greeting', ['name' => $admin['username']]) . "</p>
            <p style='color:#aaa;margin-bottom:24px'>" . t('admin_auth.email_body') . "</p>
            <a href='{$resetUrl}' style='display:inline-block;background:#f90;color:#111;padding:12px 28px;border-radius:4px;font-weight:bold;text-decoration:none;font-size:14px;letter-spacing:1px'>
                " . t('admin_auth.email_btn') . "
            </a>
            <p style='margin-top:28px;font-size:11px;color:#555'>
                " . t('admin_auth.email_footer') . "<br>
                Link: {$resetUrl}
            </p>
        </div>";

        require_once __DIR__ . '/Mailer.php';
        $sent = Mailer::send($email, t('admin_auth.email_subject'), $body);
        self::log('RESET_' . ($sent ? 'SENT' : 'MAIL_FAIL'), "Password reset -> $email");

        return ['success' => true, 'mail_sent' => $sent];
    }

    public static function verifyResetToken(string $token): ?array
    {
        if (strlen($token) !== 64) {
            return null;
        }
        $db = Database::getInstance()->getConnection();
        $hash = hash('sha256', $token);
        $stmt = $db->prepare("SELECT email FROM admin_password_resets WHERE token_hash = ? AND expires_at > NOW() AND used = 0 LIMIT 1");
        $stmt->execute([$hash]);
        return $stmt->fetch() ?: null;
    }

    public static function resetPassword(string $token, string $newPass): array
    {
        if (strlen($newPass) < 8) {
            return ['success' => false, 'message' => t('admin_auth.err_password_short')];
        }

        $data = self::verifyResetToken($token);
        if (!$data) {
            return ['success' => false, 'message' => t('admin_auth.err_token_invalid')];
        }

        $db = Database::getInstance()->getConnection();
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE admins SET password_hash = ? WHERE email = ?")->execute([$hash, $data['email']]);
        $db->prepare("UPDATE admin_password_resets SET used = 1 WHERE token_hash = ?")->execute([hash('sha256', $token)]);

        self::log('RESET_OK', "Password changed: {$data['email']}");
        return ['success' => true, 'message' => t('admin_auth.msg_password_changed')];
    }

 // Simple IP rate limiting.
 // PL: Prosty rate limiting po IP.
    public static function isIpBlocked(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM admin_login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$ip]);
            return (int)$stmt->fetchColumn() >= 10;
        } catch (Throwable $e) {
            GameLog::error('AdminAuth', 'isIpBlocked failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

 // Used when no admin account exists for the login attempt.
 // PL: Uzywane, gdy dla probowanego loginu nie ma konta admina.
    private static function logFail(string $login, string $reason): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare("INSERT INTO admin_login_attempts (ip_address, username) VALUES (?,?)")->execute([$ip, $login]);
            $db->query("DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        } catch (Throwable $e) {
            GameLog::error('AdminAuth', 'logFail persistence failed', [
                'login' => $login,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
        }
        self::log('LOGIN_FAIL', "$reason | login='$login'");
    }

 // Used when admin exists and failed attempts must be updated.
 // PL: Uzywane, gdy admin istnieje i trzeba zaktualizowac liczniki bledow.
    private static function logFailAccount(array $admin, string $login): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $newAttempts = (int)$admin['failed_attempts'] + 1;
        $lockUntil = null;

        if ($newAttempts >= self::MAX_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
            $newAttempts = 0;
            self::log('ACCOUNT_LOCKED', "Account '{$admin['username']}' locked until $lockUntil (IP: $ip)");
        }

        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare("UPDATE admins SET failed_attempts = ?, lock_until = ? WHERE id = ?")
                ->execute([$newAttempts, $lockUntil, $admin['id']]);
            $db->prepare("INSERT INTO admin_login_attempts (ip_address, username) VALUES (?,?)")->execute([$ip, $login]);
            $db->query("DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        } catch (Throwable $e) {
            GameLog::error('AdminAuth', 'logFailAccount persistence failed', [
                'admin_id' => $admin['id'] ?? null,
                'login' => $login,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
        }

        self::log('LOGIN_FAIL', "Bad password | login='$login' | attempt={$newAttempts}");
    }

    public static function changePassword(int $adminId, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $db = Database::getInstance()->getConnection();
        return $db->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$hash, $adminId]);
    }

    public static function log(string $level, string $message): void
    {
        $dir = __DIR__ . '/../admin/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
        GameLog::info('AdminAuth', $level . ': ' . $message, ['ip' => $ip]);
        @file_put_contents(
            "$dir/admin_auth.log",
            date('[Y-m-d H:i:s]') . " [$level] [IP:$ip] $message\n",
            FILE_APPEND | LOCK_EX
        );
    }

 // Backward-compatible alias for legacy callers.
 // PL: Alias wsteczny dla starszych wywolan.
    public static function writeLog(string $level, string $message): void
    {
        self::log($level, $message);
    }
}
