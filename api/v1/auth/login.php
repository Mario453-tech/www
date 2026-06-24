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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Method Not Allowed — use POST');
}

$body     = apiBody();
$login    = trim((string)($body['login'] ?? $body['email'] ?? $body['username'] ?? ''));
$password = (string)($body['password'] ?? '');
$device   = isset($body['device']) ? substr((string)$body['device'], 0, 200) : null;

if ($login === '' || $password === '') {
    apiError(400, '"login" (email lub username) i "password" sa wymagane');
}

$db = Database::getInstance()->getConnection();

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

if (!$player || !password_verify($password, $player['password_hash'])) {
    // Celowo ten sam komunikat dla obu przypadkow (bezpieczenstwo).
    // Intentionally same message for both cases (security).
    apiError(401, 'Nieprawidlowy login lub haslo');
}
if (!(int)$player['ev']) {
    apiError(403, 'Email nie zostal potwierdzony');
}
if ($player['status'] !== 'active') {
    apiError(403, 'Konto jest ' . $player['status']);
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
