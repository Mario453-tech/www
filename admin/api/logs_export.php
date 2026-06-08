<?php
/**
 * admin/api/logs_export.php
 * Authenticated API endpoint — returns last N lines of game_debug.log.
 * Uwierzytelniony endpoint API — zwraca ostatnie N linii game_debug.log.
 *
 * Auth: Authorization: Bearer <LOG_EXPORT_TOKEN>
 * Query params:
 *   ?lines=N   — number of lines to return (default 2000, max 10000)
 */

// Minimal bootstrap — no DB, no session / Minimalny bootstrap — bez bazy, bez sesji

// Load .env directly (file may not be loaded yet) / Wczytaj .env bezposrednio
$envFile = __DIR__ . '/../../.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || $envLine[0] === '#') {
            continue;
        }
        $eqPos = strpos($envLine, '=');
        if ($eqPos === false) {
            continue;
        }
        $k = trim(substr($envLine, 0, $eqPos));
        $v = trim(substr($envLine, $eqPos + 1));
        if (!isset($_ENV[$k])) {
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
}

// Token must be configured and at least 16 chars / Token musi byc skonfigurowany
$configuredToken = $_ENV['LOG_EXPORT_TOKEN'] ?? getenv('LOG_EXPORT_TOKEN') ?: '';
if (strlen($configuredToken) < 16) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Log export not configured on this server']);
    exit;
}

// Get Authorization header — works under mod_php and php-fpm / Dziala pod mod_php i php-fpm
$authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';
if ($authHeader === '' && function_exists('apache_request_headers')) {
    $reqHeaders = apache_request_headers();
    $authHeader = $reqHeaders['Authorization'] ?? $reqHeaders['authorization'] ?? '';
}

if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
    http_response_code(401);
    header('Content-Type: application/json');
    header('WWW-Authenticate: Bearer realm="GameLogs"');
    echo json_encode(['error' => 'Unauthorized — Bearer token required']);
    exit;
}

// Constant-time comparison to prevent timing attacks / Porownanie w stalym czasie
if (!hash_equals($configuredToken, $m[1])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden — invalid token']);
    exit;
}

$logFile  = __DIR__ . '/../../game_debug.log';
$maxLines = 10000;
$lines    = max(1, min($maxLines, (int)($_GET['lines'] ?? 2000)));

if (!is_readable($logFile)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo '# game_debug.log not found or not readable' . PHP_EOL;
    exit;
}

// Efficient tail: seek near end, no full file load / Wydajny ogon: szukaj konca, nie laduj calego pliku
$spl = new SplFileObject($logFile, 'r');
$spl->seek(PHP_INT_MAX);
$totalLines = $spl->key();
$startLine  = max(0, $totalLines - $lines);

$output = [];
$spl->seek($startLine);
while (!$spl->eof()) {
    $line = $spl->current();
    if ($line !== '' && $line !== false) {
        $output[] = rtrim((string)$line);
    }
    $spl->next();
}

header('Content-Type: text/plain; charset=utf-8');
header('X-Log-Lines-Returned: ' . count($output));
header('X-Log-Total-Lines: ' . $totalLines);

echo implode(PHP_EOL, $output);
if (!empty($output)) {
    echo PHP_EOL;
}
