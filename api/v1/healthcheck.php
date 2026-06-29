<?php
declare(strict_types=1);

/**
 * Strona diagnostyczna API — otworz w przegladarce:
 *   https://oilempire.pl/api/v1/healthcheck.php
 *
 * Sprawdza: autoload, polaczenie z baza, istnienie tabel, uprawnienie CREATE TABLE,
 * oraz wykonuje probne zapytanie logowania. Wypisuje czytelny raport (bez sekretow).
 *
 * Diagnostic page — open in a browser. Checks autoload, DB connection, tables,
 * CREATE TABLE permission, and a trial login query. Plain readable report.
 *
 * BEZPIECZENSTWO: nie ujawnia hasel ani danych graczy. Usun po diagnozie.
 */

header('Content-Type: text/plain; charset=utf-8');

function line(string $s = ''): void { echo $s . "\n"; }
function ok(string $s): void   { line('[ OK ]   ' . $s); }
function fail(string $s): void { line('[ FAIL ] ' . $s); }
function info(string $s): void { line('[ .. ]   ' . $s); }

line('==================================================');
line(' OilEmpire API — Healthcheck');
line(' ' . date('Y-m-d H:i:s'));
line('==================================================');
line('PHP: ' . PHP_VERSION);
line('');

// 1. Autoload + klasy / Autoload + classes
$root = dirname(__DIR__, 2);
line('-- 1. Pliki / Files --');
// vendor/autoload.php jest OPCJONALNY na produkcji (FTP deploy wyklucza vendor/),
// wiec jego brak to info, nie [FAIL]. Pozostale pliki sa wymagane.
// vendor/autoload.php is OPTIONAL in production (FTP deploy excludes vendor/), so a
// missing one is info, not [FAIL]. The remaining files are required.
foreach (['/src/Database.php', '/src/ApiAuth.php', '/config/database.php'] as $rel) {
    file_exists($root . $rel) ? ok("istnieje $rel") : fail("BRAK $rel");
}
file_exists($root . '/vendor/autoload.php')
    ? ok('istnieje /vendor/autoload.php')
    : info('/vendor/autoload.php nieobecny — pomijam (nie jest wymagany na produkcji)');
try {
    if (is_file($root . '/vendor/autoload.php')) {
        require_once $root . '/vendor/autoload.php';
        ok('vendor/autoload.php wczytany');
    } else {
        info('vendor/autoload.php nieobecny — pomijam (nie jest wymagany na produkcji)');
    }
    require_once $root . '/src/GameLog.php';
    require_once $root . '/src/Database.php';
    require_once $root . '/src/ApiAuth.php';
    ok('require_once klas aplikacji przeszedl (GameLog, Database, ApiAuth)');
} catch (\Throwable $e) {
    fail('require_once: ' . $e->getMessage());
    line('');
    line('>>> ZATRZYMANO: nie mozna zaladowac klas. To jest przyczyna.');
    exit;
}
line('');

// 2. Polaczenie z baza / DB connection
line('-- 2. Polaczenie z baza / DB connection --');
$db = null;
try {
    $db = Database::getInstance()->getConnection();
    $ver = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
    ok("polaczono z MySQL $ver");
    $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
    info("aktualna baza: " . ($dbName ?: '(brak)'));
} catch (\Throwable $e) {
    fail('polaczenie: ' . $e->getMessage());
    line('');
    line('>>> ZATRZYMANO: brak polaczenia z baza. To jest przyczyna.');
    exit;
}
line('');

// 3. Tabele / Tables
line('-- 3. Tabele / Tables --');
$needed = ['players', 'api_tokens', 'storage', 'wells', 'loans', 'market_state', 'market_trends', 'market_offers'];
$missing = [];
foreach ($needed as $t) {
    try {
        $exists = $db->query("SHOW TABLES LIKE " . $db->quote($t))->fetchColumn();
        if ($exists) {
            $cnt = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            ok("tabela `$t` istnieje (wierszy: $cnt)");
        } else {
            fail("tabela `$t` NIE ISTNIEJE");
            $missing[] = $t;
        }
    } catch (\Throwable $e) {
        fail("tabela `$t`: " . $e->getMessage());
        $missing[] = $t;
    }
}
line('');

// 4. Kolumny players wymagane przez login / players columns used by login
line('-- 4. Kolumny `players` uzywane przez login --');
try {
    $cols = $db->query('SHOW COLUMNS FROM players')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['id', 'username', 'email', 'password_hash', 'status', 'email_verified'] as $c) {
        in_array($c, $cols, true) ? ok("kolumna `$c`") : fail("BRAK kolumny `$c`");
    }
} catch (\Throwable $e) {
    fail('SHOW COLUMNS players: ' . $e->getMessage());
}
line('');

// 5. Uprawnienie CREATE TABLE (czy auto-migracje moga dzialac)
line('-- 5. Uprawnienie CREATE TABLE --');
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `_hc_test` (`id` INT)");
    ok('CREATE TABLE dziala (uzytkownik bazy ma uprawnienia)');
    try { $db->exec("DROP TABLE IF EXISTS `_hc_test`"); ok('DROP TABLE dziala'); } catch (\Throwable $e) { info('DROP nieudany: ' . $e->getMessage()); }
} catch (\Throwable $e) {
    fail('CREATE TABLE: ' . $e->getMessage());
    info('>>> Jesli to FAIL, tabele typu api_tokens nie powstana automatycznie.');
}
line('');

// 6. ensureSchema (tworzenie api_tokens)
line('-- 6. ApiAuth::ensureSchema() (auto api_tokens) --');
try {
    ApiAuth::ensureSchema();
    $exists = $db->query("SHOW TABLES LIKE 'api_tokens'")->fetchColumn();
    $exists ? ok('po ensureSchema tabela api_tokens istnieje') : fail('api_tokens NADAL nie istnieje po ensureSchema');
} catch (\Throwable $e) {
    fail('ensureSchema: ' . $e->getMessage());
}
line('');

// 7. Probne zapytanie logowania (jak w login.php) — bez ujawniania danych
line('-- 7. Probne zapytanie logowania / Trial login query --');
try {
    $stmt = $db->prepare(
        "SELECT id, username, password_hash, status, COALESCE(email_verified,1) AS ev
           FROM players WHERE username = ? LIMIT 1"
    );
    $stmt->execute(['__healthcheck_nonexistent__']);
    $stmt->fetch();
    ok('zapytanie logowania wykonuje sie poprawnie (SQL OK)');
} catch (\Throwable $e) {
    fail('zapytanie logowania: ' . $e->getMessage());
    info('>>> To jest dokladnie zapytanie z login.php — jego blad = przyczyna 500.');
}
line('');

// 8. Probne generowanie tokenu (INSERT do api_tokens) — uzywa nieistniejacego gracza,
//    wiec FK zablokuje wstawienie, ale pokaze czy tabela/SQL sa OK.
line('-- 8. Test INSERT do api_tokens (generateToken) --');
try {
    $db->beginTransaction();
    $stmt = $db->prepare("
        INSERT INTO api_tokens (player_id, token, device, created_at, expires_at)
        VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))
    ");
    // player_id = 0 raczej nie istnieje -> FK error, ale to inny blad niz 'brak tabeli'
    $stmt->execute([0, str_repeat('a', 64), 'healthcheck', 90]);
    ok('INSERT do api_tokens przeszedl (nieoczekiwane, ale SQL/tabela OK)');
    $db->rollBack();
} catch (\Throwable $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    $msg = $e->getMessage();
    if (stripos($msg, "doesn't exist") !== false || stripos($msg, 'foreign key') !== false || stripos($msg, '1452') !== false) {
        if (stripos($msg, 'foreign key') !== false || stripos($msg, '1452') !== false) {
            ok('tabela api_tokens OK (INSERT odrzucony tylko przez klucz obcy — to normalne dla testu)');
        } else {
            fail('api_tokens: ' . $msg);
        }
    } else {
        fail('INSERT api_tokens: ' . $msg);
    }
}
line('');

line('==================================================');
if ($missing) {
    line(' WYNIK: BRAKUJE TABEL -> ' . implode(', ', $missing));
} else {
    line(' WYNIK: wszystkie kluczowe tabele istnieja.');
}
line(' Zobacz sekcje [FAIL] powyzej — to sa przyczyny.');
line('==================================================');
