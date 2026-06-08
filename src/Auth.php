<?php

/**
 * Player authentication service.
 * PL: Serwis autoryzacji gracza.
 */
class Auth
{
    private const SESSION_TTL = 7200; // 2h

 /**
 * Player login by email or username.
 * PL: Logowanie gracza po emailu lub nazwie uzytkownika.
 *
 * Returns true on success, or an error key string on failure.
 * PL: Zwraca true przy sukcesie lub klucz bledu przy porazce.
 */
    public static function login(string $login, string $password): bool|string
    {
        try {
            $db = Database::getInstance()->getConnection();

            if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $stmt = $db->prepare("SELECT id, username, email, password_hash, COALESCE(email_verified,1) AS email_verified FROM players WHERE email = ? LIMIT 1");
            } else {
                $stmt = $db->prepare("SELECT id, username, email, password_hash, COALESCE(email_verified,1) AS email_verified FROM players WHERE username = ? LIMIT 1");
            }

            $stmt->execute([$login]);
            $player = $stmt->fetch();

            if (!$player) {
                GameLog::info('Auth', 'Login failed: player not found', ['login' => $login]);
                return false;
            }

            if (!password_verify($password, $player['password_hash'])) {
                GameLog::info('Auth', 'Login failed: bad password', [
                    'login' => $login,
                    'player_id' => $player['id'] ?? null,
                ]);
                return false;
            }

 // Block login if email is not verified.
 // PL: Blokuj logowanie, jesli email nie jest potwierdzony.
            if (!(int)$player['email_verified']) {
                GameLog::warn('Auth', 'Login blocked: email not verified', [
                    'player_id' => $player['id'],
                    'email' => $player['email'],
                ]);
                return 'not_verified';
            }

            self::setSession($player);

            $db->prepare("UPDATE players SET last_login_at = NOW() WHERE id = ?")
                ->execute([$player['id']]);

            GameLog::info('Auth', 'Login successful', [
                'player_id' => $player['id'],
                'username' => $player['username'] ?? '',
            ]);
            return true;
        } catch (Throwable $e) {
            GameLog::error('Auth', 'Login exception', [
                'login' => $login,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

 /**
 * Sends a verification email to a newly registered player.
 * PL: Wysyla email weryfikacyjny do nowo zarejestrowanego gracza.
 */
    public static function sendVerificationEmail(int $playerId, string $email, string $username): bool
    {
        try {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400);

            $db = Database::getInstance()->getConnection();

 // Remove any previous token for this player.
 // PL: Usun poprzedni token tego gracza.
            $db->prepare("DELETE FROM email_verifications WHERE player_id = ?")
                ->execute([$playerId]);

            $db->prepare("
                INSERT INTO email_verifications (player_id, token_hash, expires_at)
                VALUES (?, ?, ?)
            ")->execute([$playerId, $tokenHash, $expiresAt]);

            $mailCfg = require __DIR__ . '/../config/mail.php';
            $baseUrl = rtrim($mailCfg['base_url'] ?? 'https://oilempire.pl', '/');
            $verifyUrl = "{$baseUrl}/verify-email?token={$token}";

            require_once __DIR__ . '/Mailer.php';
            require_once __DIR__ . '/EmailTemplate.php';

            $safeUser = htmlspecialchars($username);
            $body = EmailTemplate::build(
                t('auth.email_verify_title'),
                t('auth.email_verify_greeting', ['name' => $safeUser]),
                t('auth.email_verify_body'),
                t('auth.email_verify_button'),
                $verifyUrl,
                t('auth.email_verify_footer')
            );

            $sent = Mailer::send($email, t('auth.email_verify_subject'), $body);

            GameLog::info('Auth', 'Verification email sent', [
                'player_id' => $playerId,
                'email' => $email,
                'sent' => $sent,
            ]);

            return $sent;
        } catch (Throwable $e) {
            GameLog::error('Auth', 'sendVerificationEmail failed', [
                'player_id' => $playerId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

 /**
 * Verifies email token.
 * PL: Weryfikuje token emaila.
 *
 * Returns ['success'=>true] or ['success'=>false,'message'=>'...'].
 * PL: Zwraca ['success'=>true] albo ['success'=>false,'message'=>'...'].
 */
    public static function verifyEmail(string $token): array
    {
        try {
            if (strlen($token) !== 64) {
                return ['success' => false, 'message' => t('auth.err_verify_token_invalid')];
            }

            $db = Database::getInstance()->getConnection();
            $tokenHash = hash('sha256', $token);

            $stmt = $db->prepare("
                SELECT ev.player_id, p.email, p.username
                FROM email_verifications ev
                JOIN players p ON p.id = ev.player_id
                WHERE ev.token_hash = ?
                  AND ev.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch();

            if (!$row) {
                GameLog::warn('Auth', 'Email verification failed: token invalid or expired', [
                    'token_hash' => substr($tokenHash, 0, 10) . '...',
                ]);
                return ['success' => false, 'message' => t('auth.err_verify_token_invalid')];
            }

            $db->prepare("UPDATE players SET email_verified = 1, email_verified_at = NOW() WHERE id = ?")
                ->execute([$row['player_id']]);

            $db->prepare("DELETE FROM email_verifications WHERE player_id = ?")
                ->execute([$row['player_id']]);

            GameLog::info('Auth', 'Email verified successfully', [
                'player_id' => $row['player_id'],
                'email' => $row['email'],
            ]);

            return ['success' => true, 'username' => $row['username']];
        } catch (Throwable $e) {
            GameLog::error('Auth', 'verifyEmail failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 /**
 * Registers a new player.
 * PL: Rejestruje nowego gracza.
 */
    public static function register(string $username, string $email, string $password): array
    {
        if (strlen($username) < 3 || strlen($username) > 20) {
            return ['success' => false, 'message' => t('auth.err_username_length')];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['success' => false, 'message' => t('auth.err_username_chars')];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => t('auth.err_email_invalid')];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => t('auth.err_password_short')];
        }

        try {
            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare("SELECT COUNT(*) FROM players WHERE username = ?");
            $stmt->execute([$username]);
            if ((int)$stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => t('auth.err_username_taken')];
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM players WHERE email = ?");
            $stmt->execute([$email]);
            if ((int)$stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => t('auth.err_email_taken')];
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $db->prepare("
                INSERT INTO players (username, email, password_hash, cash, created_at, last_login_at, last_tick_at)
                VALUES (?, ?, ?, 50000.00, NOW(), NOW(), NOW())
            ");
            $stmt->execute([$username, $email, $passwordHash]);

            $playerId = (int)$db->lastInsertId();

            self::setSession([
                'id' => $playerId,
                'username' => $username,
                'email' => $email,
            ]);

            GameLog::info('Auth', 'Register successful', [
                'player_id' => $playerId,
                'username' => $username,
                'email' => $email,
            ]);

            return ['success' => true, 'message' => t('auth.msg_registered')];
        } catch (Throwable $e) {
            GameLog::error('Auth', 'Register failed', [
                'username' => $username,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 /**
 * Stores authenticated player session data.
 * PL: Zapisuje dane sesji zalogowanego gracza.
 */
    private static function setSession(array $player): void
    {
        try {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = (int)$player['id'];
            $_SESSION['username'] = $player['username'];
            $_SESSION['email'] = $player['email'] ?? '';
            $_SESSION['login_time'] = time();
            $_SESSION['last_active'] = time();
        } catch (Throwable $e) {
            GameLog::error('Auth', 'setSession failed', ['error' => $e->getMessage()]);
        }
    }

 /**
 * Requires an authenticated player session.
 * PL: Wymaga zalogowanej sesji gracza.
 */
    public static function requireLogin(): void
    {
        try {
            if (!self::isLoggedIn()) {
 // Auto-logowanie przez cookie "zapamiętaj mnie" / Auto-login via remember-me cookie.
                if (self::tryRememberMe()) {
                    $_SESSION['last_active'] = time();
                    return;
                }
                $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
                header('Location: /login');
                exit();
            }
            $_SESSION['last_active'] = time();
        } catch (Throwable $e) {
            GameLog::error('Auth', 'requireLogin failed', ['error' => $e->getMessage()]);
            header('Location: /login');
            exit();
        }
    }

 /**
 * Checks whether the player is logged in and session is still valid.
 * PL: Sprawdza czy gracz jest zalogowany i czy sesja jest wazna.
 */
    public static function isLoggedIn(): bool
    {
        try {
            if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
                return false;
            }

            if (time() - ($_SESSION['last_active'] ?? 0) > self::SESSION_TTL) {
                self::logout(false);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            GameLog::error('Auth', 'isLoggedIn failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

 /**
 * Logs out the player and optionally redirects to login.
 * PL: Wylogowuje gracza i opcjonalnie przekierowuje na logowanie.
 */
    public static function logout(bool $redirect = true): void
    {
        try {
 // Usuń token "zapamiętaj mnie" / Remove remember-me token.
            self::clearRememberMe();
            unset($_SESSION['logged_in'], $_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['login_time'], $_SESSION['last_active']);

            if ($redirect) {
                header('Location: /login?logged_out=1');
                exit();
            }
        } catch (Throwable $e) {
            GameLog::error('Auth', 'logout failed', ['error' => $e->getMessage()]);
            if ($redirect) {
                header('Location: /login?logged_out=1');
                exit();
            }
        }
    }

    public static function getUserId(): ?int
    {
        try {
            return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        } catch (Throwable $e) {
            GameLog::error('Auth', 'getUserId failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function getUsername(): string
    {
        try {
            return $_SESSION['username'] ?? t('common.guest');
        } catch (Throwable $e) {
            GameLog::error('Auth', 'getUsername failed', ['error' => $e->getMessage()]);
            return t('common.guest');
        }
    }

    public static function getEmail(): string
    {
        try {
            return $_SESSION['email'] ?? '';
        } catch (Throwable $e) {
            GameLog::error('Auth', 'getEmail failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

 /**
 * Changes player password.
 * PL: Zmienia haslo gracza.
 */
    public static function changePassword(int $playerId, string $newPassword): bool
    {
        if (strlen($newPassword) < 6) {
            return false;
        }

        try {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $db = Database::getInstance()->getConnection();

            return $db->prepare("UPDATE players SET password_hash = ? WHERE id = ?")
                ->execute([$hash, $playerId]);
        } catch (Throwable $e) {
            GameLog::error('Auth', 'changePassword failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

 /**
 * Sends password reset email and does not reveal account existence.
 * PL: Wysyla reset hasla bez ujawniania czy konto istnieje.
 */
    public static function sendPasswordReset(string $email): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, username FROM players WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $player = $stmt->fetch();

            if (!$player) {
                GameLog::info('Auth', 'Password reset requested for unknown email', ['email' => $email]);
                return ['success' => true];
            }

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $db->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)")
                ->execute([$email, $tokenHash, $expiresAt]);

            $mailCfg = require __DIR__ . '/../config/mail.php';
            $baseUrl = rtrim($mailCfg['base_url'] ?? 'https://oilempire.pl', '/');
            $resetUrl = "{$baseUrl}/reset-password?token={$token}";

            require_once __DIR__ . '/Mailer.php';

            $body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px'>
                <h2 style='color:#333'>" . t('auth.reset_email_title') . "</h2>
                <p>" . t('auth.reset_email_greeting', ['name' => htmlspecialchars($player['username'])]) . "</p>
                <p>" . t('auth.reset_email_body') . "</p>
                <p style='margin:20px 0'>
                    <a href='{$resetUrl}' style='background:#007bff;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block'>
                        " . t('auth.reset_email_button') . "
                    </a>
                </p>
                <p style='color:#666;font-size:12px'>
                    " . t('auth.reset_email_footer') . "<br>
                    Link: {$resetUrl}
                </p>
            </div>";

            $sent = Mailer::send($email, t('auth.reset_email_subject'), $body);

            GameLog::info('Auth', 'Password reset email processed', [
                'email' => $email,
                'player_id' => $player['id'] ?? null,
                'mail_sent' => $sent,
            ]);

            return ['success' => true, 'mail_sent' => $sent];
        } catch (Throwable $e) {
            GameLog::error('Auth', 'sendPasswordReset failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 /**
 * Verifies password reset token.
 * PL: Weryfikuje token resetu hasla.
 */
    public static function verifyResetToken(string $token): ?array
    {
        try {
            if (strlen($token) !== 64) {
                return null;
            }

            $db = Database::getInstance()->getConnection();
            $hash = hash('sha256', $token);

            $stmt = $db->prepare("
                SELECT email
                FROM password_resets
                WHERE token_hash = ?
                AND expires_at > NOW()
                AND used = 0
                LIMIT 1
            ");
            $stmt->execute([$hash]);

            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('Auth', 'verifyResetToken failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

 // ── Zapamiętaj mnie (pomijanie logowania przez 30 dni) ──
 // ── Remember-me (skip login for 30 days) ──

    private const REMEMBER_DAYS   = 30;
    private const REMEMBER_COOKIE = 'player_rt';

 // Zapisz token "zapamiętaj mnie" w DB i ustaw cookie na 30 dni.
 // Save "remember-me" token in DB and set a 30-day cookie.
    public static function setRememberMe(int $playerId): void
    {
        try {
            $token     = bin2hex(random_bytes(32)); // 64-char hex token
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + self::REMEMBER_DAYS * 86400);
            $ip        = $_SERVER['REMOTE_ADDR'] ?? '';

            $db = Database::getInstance()->getConnection();
            self::ensureRememberTokensTable($db);
 // Usuń wygasłe tokeny tego gracza / Remove expired tokens for this player.
            $db->prepare("DELETE FROM player_remember_tokens WHERE player_id = ? AND expires_at < NOW()")
                ->execute([$playerId]);
            $db->prepare("INSERT INTO player_remember_tokens (player_id, token_hash, expires_at, created_ip) VALUES (?,?,?,?)")
                ->execute([$playerId, $tokenHash, $expiresAt, $ip]);

            $secure = !empty($_SERVER['HTTPS']);
            setcookie(self::REMEMBER_COOKIE, $token, [
                'expires'  => time() + self::REMEMBER_DAYS * 86400,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } catch (Throwable $e) {
 // Nie blokuj logowania gdy zapis się nie uda / Don't block login if storage fails.
            GameLog::error('Auth', 'setRememberMe failed', [
                'player_id' => $playerId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

 // Sprawdź cookie i zaloguj automatycznie bez hasła.
 // Check cookie and auto-login without a password.
    public static function tryRememberMe(): bool
    {
        try {
            $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
            if (!$cookie || strlen($cookie) !== 64) {
                return false;
            }
            $tokenHash = hash('sha256', $cookie);

            $db = Database::getInstance()->getConnection();
            self::ensureRememberTokensTable($db);
            $stmt = $db->prepare(
                "SELECT rt.id, p.id AS player_id, p.username, p.email,
                        COALESCE(p.email_verified, 1) AS email_verified
                 FROM player_remember_tokens rt
                 JOIN players p ON p.id = rt.player_id
                 WHERE rt.token_hash = ? AND rt.expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch();

            if (!$row || !(int)$row['email_verified']) {
                return false;
            }

 // Odśwież last_used_at i ustaw sesję / Refresh last_used_at and set session.
            $db->prepare("UPDATE player_remember_tokens SET last_used_at = NOW() WHERE id = ?")
                ->execute([$row['id']]);
            self::setSession(['id' => $row['player_id'], 'username' => $row['username'], 'email' => $row['email']]);
            $db->prepare("UPDATE players SET last_login_at = NOW() WHERE id = ?")
                ->execute([$row['player_id']]);

            GameLog::info('Auth', 'Auto-login via remember-me', ['player_id' => $row['player_id']]);
            return true;
        } catch (Throwable $e) {
            GameLog::error('Auth', 'tryRememberMe failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

 // Usuń token "zapamiętaj mnie" podczas wylogowania.
 // Delete "remember-me" token on logout.
    public static function clearRememberMe(): void
    {
        try {
            $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
            if ($cookie && strlen($cookie) === 64) {
                $tokenHash = hash('sha256', $cookie);
                $db = Database::getInstance()->getConnection();
                self::ensureRememberTokensTable($db);
                $db->prepare("DELETE FROM player_remember_tokens WHERE token_hash = ?")
                    ->execute([$tokenHash]);
            }
        } catch (Throwable $e) {
            GameLog::error('Auth', 'clearRememberMe failed', ['error' => $e->getMessage()]);
        }
 // Usuń cookie niezależnie od błędu DB / Delete cookie regardless of DB errors.
        $secure = !empty($_SERVER['HTTPS']);
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

 // Utwórz tabelę tokenów jeśli nie istnieje (idempotentne).
 // Create remember-me tokens table if it doesn't exist (idempotent).
    private static function ensureRememberTokensTable(PDO $db): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $db->exec("CREATE TABLE IF NOT EXISTS player_remember_tokens (
            id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            player_id     INT          NOT NULL,
            token_hash    CHAR(64)     NOT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at    DATETIME     NOT NULL,
            last_used_at  DATETIME         NULL,
            created_ip    VARCHAR(45)  NOT NULL DEFAULT '',
            UNIQUE KEY uq_prt_token   (token_hash),
            KEY           idx_prt_player  (player_id),
            KEY           idx_prt_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $checked = true;
    }

 /**
 * Resets password using a valid token.
 * PL: Resetuje haslo przy pomocy waznego tokenu.
 */
    public static function resetPassword(string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => t('auth.err_password_short')];
        }

        try {
            $data = self::verifyResetToken($token);
            if (!$data) {
                return ['success' => false, 'message' => t('auth.err_token_invalid')];
            }

            $db = Database::getInstance()->getConnection();
            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            $db->prepare("UPDATE players SET password_hash = ? WHERE email = ?")
                ->execute([$hash, $data['email']]);

            $db->prepare("UPDATE password_resets SET used = 1 WHERE token_hash = ?")
                ->execute([hash('sha256', $token)]);

            GameLog::info('Auth', 'Password reset completed', ['email' => $data['email']]);

            return ['success' => true, 'message' => t('auth.msg_password_changed')];
        } catch (Throwable $e) {
            GameLog::error('Auth', 'resetPassword failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }
}
