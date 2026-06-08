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
        $cols = "id, username, email, password_hash, is_active, failed_attempts, lock_until";
        if (self::totpAvailable($db)) {
            $cols .= ", totp_secret, totp_enabled";
        }
        $stmt = $db->prepare("SELECT {$cols} FROM admins WHERE {$field} = ? LIMIT 1");
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

 // Haslo poprawne: zeruj liczniki bledow, ale NIE loguj jeszcze w pelni.
 // Najpierw 2FA. Ustawiamy stan oczekujacy (pending) -> krok kodu TOTP.
 // Password OK: clear failure counters, but DO NOT fully log in yet.
 // 2FA comes first. We set a pending state -> TOTP code step.
        $db->prepare("UPDATE admins SET failed_attempts = 0, lock_until = NULL WHERE id = ?")
            ->execute([$admin['id']]);
        self::setPending($admin);
        self::log('PASS_OK', "Password OK: '{$admin['username']}' via {$field} (awaiting 2FA)");
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

        $cols = "id, username, email, is_active";
        if (self::totpAvailable($db)) {
            $cols .= ", totp_secret, totp_enabled";
        }
        $stmt = $db->prepare("SELECT {$cols} FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$player['email']]);
        $admin = $stmt->fetch();
        if (!$admin || !(int)$admin['is_active']) {
            return false;
        }

 // SSO tez musi przejsc przez 2FA — ustaw pending, nie pelna sesje.
 // SSO must also pass 2FA — set pending, not a full session.
        self::setPending($admin);
        self::log('SSO', "Auto-login player_id={$_SESSION['user_id']} -> admin '{$admin['username']}' (awaiting 2FA)");
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

 // ── 2FA: stan oczekujacy miedzy haslem a kodem TOTP ──
 // ── 2FA: pending state between password and TOTP code ──

 // Czas na ukonczenie 2FA po podaniu hasla (sekundy).
 // Time allowed to finish 2FA after the password step (seconds).
    private const PENDING_TTL = 600; // 10 min

    /** @param array<string,mixed> $admin */
    private static function setPending(array $admin): void
    {
        session_regenerate_id(true);
        unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_2fa_setup_secret']);
        $_SESSION['admin_pending'] = [
            'id'           => (int)$admin['id'],
            'username'     => $admin['username'],
            'email'        => $admin['email'] ?? '',
            'totp_secret'  => $admin['totp_secret'] ?? null,
            'totp_enabled' => (int)($admin['totp_enabled'] ?? 0),
            'ts'           => time(),
        ];
    }

    public static function hasPending(): bool
    {
        return !empty($_SESSION['admin_pending'])
            && (time() - ($_SESSION['admin_pending']['ts'] ?? 0) < self::PENDING_TTL);
    }

    /** @return array<string,mixed>|null */
    public static function getPending(): ?array
    {
        return self::hasPending() ? $_SESSION['admin_pending'] : null;
    }

    public static function clearPending(): void
    {
        unset($_SESSION['admin_pending'], $_SESSION['admin_2fa_setup_secret']);
    }

 // Po pomyslnej weryfikacji 2FA: awansuj pending -> pelna sesja.
 // After successful 2FA: promote pending -> full session.
    public static function completeLogin(): void
    {
        $p = self::getPending();
        if (!$p) {
            throw new RuntimeException('AdminAuth::completeLogin without pending admin');
        }
        self::setSession(['id' => $p['id'], 'username' => $p['username'], 'email' => $p['email']]);
        self::clearPending();
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare("UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?")
                ->execute([$_SERVER['REMOTE_ADDR'] ?? '', $p['id']]);
        } catch (\Throwable $e) {
            // log-only; nie blokuj logowania / log-only; do not block login
        }
        self::log('LOGIN_2FA_OK', "Full login after 2FA: '{$p['username']}'");
    }

 // Zapisz nowo zarejestrowany sekret i wlacz 2FA dla pending admina.
 // Save a freshly enrolled secret and enable 2FA for the pending admin.
    public static function enableTotpForPending(string $secret): bool
    {
        $p = self::getPending();
        if (!$p) {
            return false;
        }
        $db = Database::getInstance()->getConnection();
        if (!self::totpAvailable($db)) {
            return false;
        }
        $db->prepare("UPDATE admins SET totp_secret = ?, totp_enabled = 1 WHERE id = ?")
            ->execute([$secret, $p['id']]);
        $_SESSION['admin_pending']['totp_secret']  = $secret;
        $_SESSION['admin_pending']['totp_enabled'] = 1;
        self::log('2FA_ENROLLED', "2FA enabled for '{$p['username']}'");
        return true;
    }

 // Czy kolumny TOTP istnieja w tabeli admins (graceful degradation).
 // Whether the TOTP columns exist in the admins table (graceful degradation).
    public static function totpAvailable(PDO $db): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $db->query("SELECT totp_secret, totp_enabled FROM admins LIMIT 0");
            $cache = true;
        } catch (\Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    public static function requireLogin(): void
    {
        if (self::isLoggedIn()) {
            $_SESSION['admin_last_active'] = time();
            return;
        }

 // Try SSO first if player session exists (may set a pending 2FA state).
 // PL: Najpierw sproboj SSO (moze ustawic stan oczekujacy 2FA).
        self::trySSO();

        if (self::isLoggedIn()) {
            $_SESSION['admin_last_active'] = time();
            return;
        }

 // Auto-login przez zaufane urządzenie — pomija hasło i 2FA.
 // Auto-login via trusted device — skips both password and 2FA.
        if (self::tryTrustedDevice()) {
            self::clearPending(); // wyczyść pending ustawiony przez SSO / clear pending set by SSO
            $_SESSION['admin_last_active'] = time();
            return;
        }

        $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/';

 // Haslo OK ale brak 2FA -> krok kodu / password OK but 2FA pending -> code step
        if (self::hasPending()) {
            header('Location: /admin/2fa.php');
            exit();
        }

        header('Location: /admin/login.php');
        exit();
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

 // ── Zaufane urządzenia (pomijanie 2FA przez 30 dni) ──
 // ── Trusted devices (skip 2FA for 30 days) ──

    private const TRUSTED_DEVICE_DAYS   = 30;
    private const TRUSTED_DEVICE_COOKIE = 'admin_td';

 // Zapisz token zaufanego urządzenia w DB i ustaw cookie na 30 dni.
 // Save trusted-device token in DB and set a 30-day cookie.
    public static function setTrustedDevice(int $adminId): void
    {
        $token     = bin2hex(random_bytes(32)); // 64-char hex token
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TRUSTED_DEVICE_DAYS * 86400);
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '';

        try {
            $db = Database::getInstance()->getConnection();
            self::ensureTrustedDevicesTable($db);
 // Usuń wygasłe tokeny dla tego admina / Remove expired tokens for this admin.
            $db->prepare("DELETE FROM admin_trusted_devices WHERE admin_id = ? AND expires_at < NOW()")
                ->execute([$adminId]);
            $db->prepare("INSERT INTO admin_trusted_devices (admin_id, token_hash, expires_at, created_ip) VALUES (?,?,?,?)")
                ->execute([$adminId, $tokenHash, $expiresAt, $ip]);
        } catch (\Throwable $e) {
 // Nie blokuj logowania gdy zapis się nie uda / Don't block login if storage fails.
            return;
        }

        $secure = !empty($_SERVER['HTTPS']);
        setcookie(self::TRUSTED_DEVICE_COOKIE, $token, [
            'expires'  => time() + self::TRUSTED_DEVICE_DAYS * 86400,
            'path'     => '/admin',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

 // Sprawdź cookie zaufanego urządzenia znając admin_id (używane w 2fa.php po haśle).
 // Check trusted-device cookie knowing the admin_id (used in 2fa.php after password).
    public static function checkTrustedDevice(int $adminId): bool
    {
        $cookie = $_COOKIE[self::TRUSTED_DEVICE_COOKIE] ?? '';
        if (!$cookie || strlen($cookie) !== 64) {
            return false;
        }
        $tokenHash = hash('sha256', $cookie);

        try {
            $db = Database::getInstance()->getConnection();
            self::ensureTrustedDevicesTable($db);
            $stmt = $db->prepare("SELECT id FROM admin_trusted_devices WHERE admin_id = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$adminId, $tokenHash]);
            $row = $stmt->fetch();
            if (!$row) {
                return false;
            }
 // Odśwież czas ostatniego użycia / Refresh last-used timestamp.
            $db->prepare("UPDATE admin_trusted_devices SET last_used_at = NOW() WHERE id = ?")
                ->execute([$row['id']]);
            self::log('TRUSTED_DEVICE', "Trusted-device bypass for admin_id={$adminId}");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

 // Auto-login przez cookie zaufanego urządzenia bez znajomości admin_id.
 // Auto-login via trusted-device cookie without a known admin_id upfront.
 // Używane w requireLogin() i login.php przed wyświetleniem formularza.
 // Used in requireLogin() and login.php before showing the form.
    public static function tryTrustedDevice(): bool
    {
        $cookie = $_COOKIE[self::TRUSTED_DEVICE_COOKIE] ?? '';
        if (!$cookie || strlen($cookie) !== 64) {
            return false;
        }
        $tokenHash = hash('sha256', $cookie);

        try {
            $db = Database::getInstance()->getConnection();
            self::ensureTrustedDevicesTable($db);
            $stmt = $db->prepare(
                "SELECT td.id, a.id AS admin_id, a.username, a.email, a.is_active
                 FROM admin_trusted_devices td
                 JOIN admins a ON a.id = td.admin_id
                 WHERE td.token_hash = ? AND td.expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch();
            if (!$row || !(int)$row['is_active']) {
                return false;
            }
 // Odśwież last_used_at i ustaw pełną sesję adminA / Refresh last_used_at and set full admin session.
            $db->prepare("UPDATE admin_trusted_devices SET last_used_at = NOW() WHERE id = ?")
                ->execute([$row['id']]);
            self::setSession(['id' => $row['admin_id'], 'username' => $row['username'], 'email' => $row['email']]);
            try {
                $db->prepare("UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?")
                    ->execute([$_SERVER['REMOTE_ADDR'] ?? '', $row['admin_id']]);
            } catch (\Throwable $ignored) {}
            self::log('TRUSTED_DEVICE_AUTO', "Auto-login via trusted device: '{$row['username']}'");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

 // Utwórz tabelę jeśli nie istnieje (idempotentne).
 // Create the table if it doesn't exist (idempotent).
    private static function ensureTrustedDevicesTable(PDO $db): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $db->exec("CREATE TABLE IF NOT EXISTS admin_trusted_devices (
            id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_id      INT          NOT NULL,
            token_hash    CHAR(64)     NOT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at    DATETIME     NOT NULL,
            last_used_at  DATETIME         NULL,
            created_ip    VARCHAR(45)  NOT NULL DEFAULT '',
            UNIQUE KEY uq_td_token  (token_hash),
            KEY           idx_td_admin   (admin_id),
            KEY           idx_td_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $checked = true;
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
