<?php
declare(strict_types=1);

/** Renew active session cookies. / Odnawiaj cookie aktywnych sesji. */
final class SessionCookie
{
    public static function refresh(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent() || !ini_get('session.use_cookies')) {
            return;
        }
        $now = time();
        $activePlayer = !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])
            && $now - (int)($_SESSION['last_active'] ?? 0) <= 7200;
        $activeAdmin = !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])
            && $now - (int)($_SESSION['admin_last_active'] ?? 0) <= 7200;
        if (!$activePlayer && !$activeAdmin) {
            return;
        }
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => $now + 7200,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
}
