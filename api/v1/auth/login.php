<?php
declare(strict_types=1);

/**
 * POST /api/v1/auth/login
 *
 * Body JSON: { "login": "email@lub.username", "password": "...", "device": "Pixel 9" }
 * Odpowiedz: { "token": "...", "player_id": 42, "username": "..." }
 *
 * Token jest wazny 90 dni.
 * The token is valid for 90 days.
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/src/ApiAuthRateLimiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, ApiErrorHandler::message('method'));
}

$body     = apiBody();
$login    = ApiAuthRateLimiter::normalizeIdentifier($body['login'] ?? $body['email'] ?? $body['username'] ?? null);
$password = $body['password'] ?? null;
$device   = $body['device'] ?? null;

if ($login === null || !is_string($password) || $password === '' || strlen($password) > 4096
    || ($device !== null && (!is_string($device) || !mb_check_encoding($device, 'UTF-8')))) {
    apiError(400, ApiErrorHandler::message('invalid'));
}
$device = $device === null ? null : mb_substr($device, 0, 200, 'UTF-8');

$db = Database::getInstance()->getConnection();
$limiter = new ApiAuthRateLimiter($db);

if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
    $stmt = $db->prepare(
        "SELECT id, username, password_hash, status, COALESCE(email_verified,1) AS ev
           FROM players WHERE email = ? LIMIT 1"
    );
} else {
    $stmt = $db->prepare(
        "SELECT id, username, password_hash, status, COALESCE(email_verified,1) AS ev
           FROM players WHERE username = ? LIMIT 1"
    );
}
$stmt->execute([$login]);
$player = $stmt->fetch();

// Use the account ID so email and username share the same attempt budget.
// Uzywaj ID konta, aby email i nazwa gracza mialy wspolny limit prob.
$retryAfter = $limiter->consume($login, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $player ? (int) $player['id'] : null);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    apiError(429, ApiErrorHandler::message('limited'));
}

if (!$player || !password_verify($password, $player['password_hash'])) {
    // Celowo ten sam komunikat dla obu przypadkow (bezpieczenstwo).
    // Intentionally same message for both cases (security).
    apiError(401, ApiErrorHandler::message('credentials'));
}
if (!(int)$player['ev']) {
    apiError(403, ApiErrorHandler::message('unverified'));
}
if ($player['status'] !== 'active') {
    apiError(403, ApiErrorHandler::message('inactive'));
}

$token = ApiAuth::generateToken((int)$player['id'], $device);
$db->prepare("UPDATE players SET last_login_at = NOW() WHERE id = ?")
   ->execute([$player['id']]);

apiJson([
    'token'     => $token,
    'player_id' => (int)$player['id'],
    'username'  => $player['username'],
    'expires_in_days' => 90,
]);
