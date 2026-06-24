<?php
declare(strict_types=1);

// CORS: wymagane zeby Flutter (i kazdy klient spoza przegladarki) mogl wysylac zapytania.
// CORS: required so Flutter (and any non-browser client) can send requests.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Preflight OPTIONS: przegldarki i HttpClient wysylaja OPTIONS przed POST/DELETE z naglowkiem
// Preflight OPTIONS: browsers and HttpClient send OPTIONS before POST/DELETE with custom header
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$_API_ROOT = dirname(__DIR__, 2);
require_once $_API_ROOT . '/vendor/autoload.php';
require_once $_API_ROOT . '/src/GameLog.php';
require_once $_API_ROOT . '/src/Database.php';
require_once $_API_ROOT . '/src/ApiAuth.php';

// Auto-tworzy tabele api_tokens jesli nie istnieje (raz na proces, bezpieczne no-op).
// Auto-creates api_tokens table if missing (once per process, safe no-op).
ApiAuth::ensureSchema();

GameLog::setEnabled(false);

/**
 * Konczy zadanie z bledem JSON.
 * Terminates the request with a JSON error.
 */
function apiError(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Wysyla JSON i konczy zadanie.
 * Sends JSON and terminates the request.
 */
function apiJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Weryfikuje token Bearer i zwraca wiersz gracza lub konczy z 401.
 * Verifies the Bearer token and returns the player row or exits with 401.
 *
 * @return array<string,mixed>
 */
function apiRequireAuth(): array
{
    $player = ApiAuth::getPlayerFromRequest();
    if (!$player) {
        apiError(401, 'Unauthorized: pass token via "Authorization: Bearer <token>"');
    }
    return $player;
}

/**
 * Parsuje JSON body lub konczy z 400.
 * Parses JSON body or exits with 400.
 *
 * @return array<string,mixed>
 */
function apiBody(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        apiError(400, 'Invalid JSON body');
    }
    return $data;
}
