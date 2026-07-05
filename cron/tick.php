<?php

/**
 * TICK cron gry (fasada)
 * Uruchamiany co ~5 minut przez cron serwera.
 *
 * Logika podzielona na sekcje w src/Tick/:
 * MarketSection trendy rynkowe + cena ropy
 * BankSection system bankowy, HR, bankruci
 * PlayersSection gracze, odwierty, produkcja
 *
 * Statystyki kazdego ticka zapisywane do tabeli tick_stats.
 */

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/DisasterMessages.php';
require_once __DIR__ . '/../src/WellService.php';
require_once __DIR__ . '/../src/WellStaffService.php';
require_once __DIR__ . '/../src/TechnicalTeamService.php';
require_once __DIR__ . '/../src/IncidentService.php';
require_once __DIR__ . '/../src/FinanceService.php';
require_once __DIR__ . '/../src/BlackMarketService.php';
require_once __DIR__ . '/../src/CompanyCredibilityService.php';
require_once __DIR__ . '/../src/Tick/MarketSection.php';
require_once __DIR__ . '/../src/Tick/BankSection.php';
require_once __DIR__ . '/../src/Tick/PlayersSection.php';
require_once __DIR__ . '/../src/Tick/TickStatsRepository.php';
require_once __DIR__ . '/../src/LegalService.php';
require_once __DIR__ . '/../src/Tick/CredibilitySection.php';
require_once __DIR__ . '/../src/Tick/LegalSection.php';
require_once __DIR__ . '/../src/Tick/TrainingSection.php';

// Opcjonalne serwisy
$bankNegAvailable       = file_exists(__DIR__ . '/../src/BankNegotiationService.php');
$bankruptcyAvailable    = file_exists(__DIR__ . '/../src/BankruptcyService.php');
if ($bankNegAvailable)    require_once __DIR__ . '/../src/BankNegotiationService.php';
if ($bankruptcyAvailable) require_once __DIR__ . '/../src/BankruptcyService.php';

$db        = Database::getInstance()->getConnection();

// Twarde limity, by zawieszony tick nie trzymal GET_LOCK w nieskonczonosc:
// - cron CLI ma max_execution_time=0 (bez limitu) -> wymuszamy hard limit;
// - lock_wait_timeout domyslnie ~1 rok, wiec ALTER czekajacy na metadata lock
//   moze wisiec w nieskonczonosc -> skracamy do 60 s (tick wtedy padnie i zwolni locka).
// Hard caps so a hung tick cannot hold GET_LOCK forever:
// - CLI cron has max_execution_time=0 (unlimited) -> enforce a hard limit;
// - lock_wait_timeout defaults to ~1 year, so an ALTER waiting on a metadata lock
//   can hang forever -> shorten to 60 s (the tick then fails and releases the lock).
@set_time_limit(290);
try { $db->exec('SET SESSION lock_wait_timeout = 60'); } catch (Throwable $e) {}

$now       = new DateTime();
$startTime = microtime(true);
$source    = (php_sapi_name() === 'cli') ? 'cron' : 'http';
$GLOBALS['OILCORP_TICK_BUSY'] = false;
$GLOBALS['OILCORP_TICK_LOCK_ERROR'] = false;

// Zabezpieczenie: HTTP tylko z poprawnym kluczem lub z include (force_tick.php).
// HTTP access guard: allow CLI always, HTTP only with matching key or internal include.
if (php_sapi_name() !== 'cli' && !defined('FORCE_TICK_INTERNAL')) {
    $cronKey = '';
    try {
        $r = $db->query("SELECT `value` FROM well_config WHERE `key` = 'cron_secret_key' LIMIT 1")->fetchColumn();
        if ($r !== false) $cronKey = (string)$r;
    } catch (Throwable $e) {}

    $provided = (string)($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
 // hash_equals zamiast !== - stala czasowo, odporna na timing attack.
 // hash_equals instead of !== - constant-time, resistant to timing attack.
    if ($cronKey === '' || !hash_equals($cronKey, $provided)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $source = 'cron_http';
}

// Lock wykonania: zapobiega nakladaniu sie tickow gdy poprzedni trwa > interwal crona.
// Bez tego drugi proces przetwarzalby tych samych graczy z tym samym deltaSeconds
// (podwojona produkcja, koszty i incydenty).
// Execution lock: prevents overlapping ticks when a previous run exceeds the cron interval.
// Without it a second process would reprocess the same players with the same deltaSeconds
// (doubled production, costs and incidents).
//
// Uzywamy MySQL GET_LOCK zamiast flock: dziala na shared hostingu (az.pl), gdzie
// fopen(sys_get_temp_dir()) potrafi byc zablokowany przez open_basedir (flock padal
// przy KAZDYM przebiegu i zatrzymal cron). GET_LOCK jest przypiety do polaczenia DB,
// wiec gdy proces ticku padnie/zostanie zabity, blokada zwalnia sie automatycznie —
// zaden zawieszony tick nie zablokuje gry na stale.
// We use MySQL GET_LOCK instead of flock: it works on shared hosting (az.pl) where
// fopen(sys_get_temp_dir()) can be blocked by open_basedir (flock failed on EVERY run
// and stalled the cron). GET_LOCK is bound to the DB connection, so if the tick process
// dies/is killed the lock auto-releases — no hung tick can block the game permanently.
try {
    $gotLock = (int)$db->query("SELECT GET_LOCK('oilcorp_tick', 0)")->fetchColumn();
    if ($gotLock !== 1) {
        $GLOBALS['OILCORP_TICK_BUSY'] = true;
        GameLog::warn('tick', 'tick juz trwa - pomijam ten przebieg / tick already running - skipping this run');
        echo "Tick skipped: another run in progress\n";
        return;
    }
    register_shutdown_function(static function () use ($db) {
        try {
            $db->query("SELECT RELEASE_LOCK('oilcorp_tick')");
        } catch (Throwable $e) {
            // Polaczenie i tak zwolni lock przy zamknieciu / connection close frees it anyway
        }
    });
} catch (Throwable $e) {
    $GLOBALS['OILCORP_TICK_BUSY'] = true;
    $GLOBALS['OILCORP_TICK_LOCK_ERROR'] = true;
    GameLog::error('tick', 'GET_LOCK FAILED - tick aborted', $e);
    echo "Tick skipped: lock acquisition failed\n";
    return;
}

// H1: Wykrycie niedokonczonegopierwszego ticka — crash detection via tick_in_progress flag.
// Jesli poprzedni tick padl w polowie, flaga zostala jako 1 i ostrzegamy przy nastepnym uruchomieniu.
// If the previous tick crashed mid-run, the flag stayed at 1 and we warn on the next run.
$prevTickIncomplete = false;
try {
    $r = $db->query("SELECT `value` FROM well_config WHERE `key` = 'tick_in_progress' LIMIT 1")->fetchColumn();
    $prevTickIncomplete = ($r !== false && (int)$r === 1);
    $db->prepare(
        "INSERT INTO well_config (`key`, `value`, `label`, `category`)
         VALUES ('tick_in_progress', '1', 'Tick w toku — crash detection', 'system')
         ON DUPLICATE KEY UPDATE `value` = '1'"
    )->execute();
} catch (Throwable $e) {
    GameLog::error('tick', 'tick_in_progress flag write FAILED', $e);
}
if ($prevTickIncomplete) {
    GameLog::warn('tick', 'POPRZEDNI TICK NIE ZAKONCZYL SIE — mozliwa niespojnosc danych / PREVIOUS TICK DID NOT FINISH — possible data inconsistency');
}

GameLog::info('tick', '== START ==', ['time' => $now->format('Y-m-d H:i:s'), 'source' => $source]);

// 1-2. RYNEK 

$market = new MarketSection();
$market->run();

$activeTrend = $market->activeTrend;
$isNewTrend  = $market->isNewTrend;
$newPrice    = $market->newPrice;

// H7: Guard przed zerowa cena ropy po awarii MarketSection.
// oilPrice=0 sprawiloby ze caly przychod i straty gracza liczylyby sie jako 0 PLN.
// Guard against zero oil price after MarketSection failure.
// oilPrice=0 would make all player revenue and losses calculate as 0 PLN.
if ($newPrice <= 0.0) {
    GameLog::error('tick', 'CENA ROPY = 0 po MarketSection — uzyje poprzedniej ceny lub 70 / OIL PRICE = 0 after MarketSection — using previous price or 70', []);
    try {
        $prevPrice = $db->query(
            "SELECT `value` FROM well_config WHERE `key` = 'last_tick_oil_price' LIMIT 1"
        )->fetchColumn();
        $newPrice = ($prevPrice !== false && (float)$prevPrice > 0) ? (float)$prevPrice : 70.0;
    } catch (Throwable $e) {
        $newPrice = 70.0;
    }
    GameLog::warn('tick', 'fallback cena ropy / fallback oil price', ['price' => $newPrice]);
}

// 2b. CZYSZCZENIE ZALEGAJACYCH DOSTAW MORSKICH (raz na tick, globalnie)
// 2b. PURGE STALE MARINE DELIVERIES (once per tick, global)
require_once __DIR__ . '/../src/Tick/MarineDeliverySection.php';
MarineDeliverySection::purgeStale($db);

// 3-4k. SYSTEM BANKOWY / HR / BANKRUCI

$bank = new BankSection($db, $bankNegAvailable, $bankruptcyAvailable);
$bank->run();

// 5. GRACZE ODWIERTY I PRODUKCJA 

// Globalne mnoznik balansu z well_config (admin/balance.php)
$gBalanceMults = ['incident' => 1.0, 'disaster' => 1.0, 'wear' => 1.0, 'degradation' => 1.0, 'loss' => 1.0, 'opex' => 1.0, 'production' => 1.0, 'tax' => 1.0];
try {
    $balanceKeys = ['global_incident_multiplier' => 'incident', 'global_disaster_multiplier' => 'disaster', 'global_wear_multiplier' => 'wear', 'global_degradation_mult' => 'degradation', 'global_loss_multiplier' => 'loss', 'global_opex_multiplier' => 'opex', 'global_production_mult' => 'production', 'global_tax_multiplier' => 'tax'];
    $balanceStmt = $db->prepare("SELECT `key`, `value` FROM well_config WHERE `key` IN ('global_incident_multiplier','global_disaster_multiplier','global_wear_multiplier','global_degradation_mult','global_loss_multiplier','global_opex_multiplier','global_production_mult','global_tax_multiplier')");
    $balanceStmt->execute();
    foreach ($balanceStmt->fetchAll() as $bRow) {
        $shortKey = $balanceKeys[$bRow['key']] ?? null;
        if ($shortKey !== null) $gBalanceMults[$shortKey] = max(0.1, min(10.0, (float)$bRow['value']));
    }
} catch (Throwable $e) {}
