<?php

/**
 * OPcache reset endpoint — token-guarded, called after FTP deploy.
 * Endpoint resetu OPcache — chroniony tokenem, wolany po deployu FTP.
 *
 * Why / Dlaczego:
 * FTP upload writes files empty-then-content. If a web request hits during
 * that window, OPcache can cache the EMPTY file, breaking the whole site.
 * PL: FTP zapisuje plik najpierw pusty, potem trescia. Zadanie WWW w tym oknie
 * moze zapamietac w OPcache pusty plik i polozyc cala strone.
 * Resetting OPcache right after deploy clears any such stale entries.
 * PL: Reset OPcache zaraz po deployu czysci takie nieaktualne wpisy.
 *
 * Usage / Uzycie:
 * GET /opcache-reset.php?token=SECRET
 * or header: X-Reset-Token: SECRET
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

 // Load the reset token from .env without bootstrapping the app or DB.
 // PL: Wczytaj token z .env bez ladowania aplikacji ani bazy.
 // Endpoint must work even if the database is down.
 // PL: Endpoint musi dzialac nawet gdy baza jest niedostepna.
$token = null;
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        if (trim($key) === 'OPCACHE_RESET_TOKEN') {
            $token = trim($value, " \t\"'");
            break;
        }
    }
}

$provided = (string)($_GET['token'] ?? $_SERVER['HTTP_X_RESET_TOKEN'] ?? '');

 // Reject when no token is configured or it does not match.
 // PL: Odrzuc, gdy token nie jest skonfigurowany lub sie nie zgadza.
if ($token === null || $token === '' || !hash_equals($token, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

if (!function_exists('opcache_reset')) {
    echo json_encode(['ok' => true, 'reset' => false, 'note' => 'opcache_unavailable']);
    exit;
}

$ok = opcache_reset();
echo json_encode(['ok' => true, 'reset' => (bool)$ok]);
