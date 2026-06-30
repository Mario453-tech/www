<?php
declare(strict_types=1);

/**
 * E2E smoke-test endpointow api/v1/* — uruchamiany w CI na bazie ci-schema (= produkcja).
 * E2E smoke test for api/v1/* endpoints — run in CI against ci-schema (= production).
 *
 * DLACZEGO: bug "Dane rynku niedostepne" (api/v1/market pytalo o nieistniejace kolumny
 * market_offers) przeszedl przez cale CI, bo NIC nie uderzalo w api/v1/*. Ten skrypt
 * seeduje krotkotrwalego gracza + token i realnie odpytuje endpointy, sprawdzajac 200
 * (nie 500). Lapie rozjazd kolumn kod<->schema i bledy SQL ZANIM trafia do aplikacji.
 *
 * WHY: the "market data unavailable" bug (api/v1/market selecting non-existent
 * market_offers columns) slipped through all of CI because nothing hit api/v1/*.
 * This seeds a throwaway player + token and really calls the endpoints, asserting 200.
 *
 * Uzycie / Usage:
 *   php -S 127.0.0.1:8099 -t .    # w tle, dziedziczy env DB_* / in background, inherits DB_*
 *   php tests/api_smoke.php http://127.0.0.1:8099
 *
 * Nowy modul = dopisz jedna linie do listy $endpoints ponizej.
 * New module = add one line to the $endpoints list below.
 */

$base = rtrim((string)($argv[1] ?? 'http://127.0.0.1:8099'), '/');

require_once __DIR__ . '/../src/GameLog.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ApiAuth.php';
require_once __DIR__ . '/../src/Market.php';

GameLog::setEnabled(false);

function smokeOk(string $m): void   { fwrite(STDOUT, "[API SMOKE] OK:   {$m}\n"); }
function smokeFail(string $m): void { fwrite(STDERR, "[API SMOKE] FAIL: {$m}\n"); }

/**
 * @return array{0:int,1:string} [kod HTTP, body]
 */
function smokeGet(string $url, ?string $token): array
{
    $header = "Accept: application/json\r\n";
    if ($token !== null) {
        $header .= "Authorization: Bearer {$token}\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method'        => 'GET',
        'header'        => $header,
        'timeout'       => 15,
        'ignore_errors' => true, // pobierz body takze przy 4xx/5xx / capture body on 4xx/5xx
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('{HTTP/\S+\s+(\d+)}', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    return [$code, $body === false ? '' : (string)$body];
}

$db = Database::getInstance()->getConnection();
ApiAuth::ensureSchema();   // tabela api_tokens (tworzona w runtime, nie w ci-schema)
Market::ensureState();     // wiersz singleton market_state id=1

$pid   = 990000123;        // staly, wysoki id testowy / fixed high test id
$token = bin2hex(random_bytes(32));

$cleanup = static function () use ($db, $pid): void {
    foreach (['api_tokens', 'market_offers', 'storage'] as $t) {
        try { $db->prepare("DELETE FROM `{$t}` WHERE player_id = ?")->execute([$pid]); } catch (Throwable) {}
    }
    try { $db->prepare("DELETE FROM players WHERE id = ?")->execute([$pid]); } catch (Throwable) {}
};

$cleanup();

// Seed gracza (te same kolumny co MySqlIntegrationTestCase::seedPlayer — sprawdzone na ci-schema).
$db->prepare(
    "INSERT INTO players (id, username, email, password_hash, cash, status, created_at, last_tick_at, safety_procedures_level, procedure_integrity)
     VALUES (?, ?, ?, ?, 1000000.00, 'active', NOW(), NOW(), 0, 100)"
)->execute([$pid, 'apismoke_' . $pid, 'apismoke_' . $pid . '@example.test', password_hash('x', PASSWORD_BCRYPT)]);

$db->prepare(
    "INSERT INTO api_tokens (player_id, token, device, created_at, expires_at)
     VALUES (?, ?, 'api-smoke', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))"
)->execute([$pid, $token]);

// storage + jedna oferta 'pending' — exercise mapowania kolumn (opcjonalne, w try/catch).
try { $db->prepare("INSERT INTO storage (player_id, capacity, used) VALUES (?, 1000, 100)")->execute([$pid]); } catch (Throwable) {}
try { $db->prepare("INSERT INTO market_offers (player_id, amount, limit_price, status, created_at) VALUES (?, 50, 80, 'pending', NOW())")->execute([$pid]); } catch (Throwable) {}

// Endpointy do sprawdzenia (GET, wymagaja auth). Nowy modul -> dopisz tu linie.
$endpoints = [
    '/api/v1/player/',
    '/api/v1/market/',
    '/api/v1/wells/',
    '/api/v1/maps/',
];

$failures = 0;

foreach ($endpoints as $ep) {
    [$code, $body] = smokeGet($base . $ep, $token);
    $json = json_decode($body, true);

    if ($code !== 200) {
        $failures++;
        smokeFail("{$ep} -> HTTP {$code}; body: " . substr($body, 0, 400));
        continue;
    }
    if (!is_array($json)) {
        $failures++;
        smokeFail("{$ep} -> 200 ale odpowiedz nie jest JSON: " . substr($body, 0, 200));
        continue;
    }
    if (isset($json['error'])) {
        $failures++;
        smokeFail("{$ep} -> 200 z kluczem 'error' (handler wyjatkow): " . (string)$json['error']);
        continue;
    }
    smokeOk("{$ep} -> 200, poprawny JSON");
}

// Auth nadal egzekwowany: bez tokenu -> 401.
[$code401] = smokeGet($base . '/api/v1/player/', null);
if ($code401 !== 401) {
    $failures++;
    smokeFail("/api/v1/player/ bez tokenu powinno byc 401, jest {$code401}");
} else {
    smokeOk("/api/v1/player/ bez tokenu -> 401 (auth dziala)");
}

$cleanup();

if ($failures > 0) {
    fwrite(STDERR, "[API SMOKE] {$failures} sprawdzen nie przeszlo.\n");
    exit(1);
}
fwrite(STDOUT, "[API SMOKE] Wszystkie endpointy OK.\n");
exit(0);
