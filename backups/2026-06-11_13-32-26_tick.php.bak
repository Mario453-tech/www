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

// Opcjonalne serwisy
$bankNegAvailable       = file_exists(__DIR__ . '/../src/BankNegotiationService.php');
$bankruptcyAvailable    = file_exists(__DIR__ . '/../src/BankruptcyService.php');
if ($bankNegAvailable)    require_once __DIR__ . '/../src/BankNegotiationService.php';
if ($bankruptcyAvailable) require_once __DIR__ . '/../src/BankruptcyService.php';

$db        = Database::getInstance()->getConnection();
$now       = new DateTime();
$startTime = microtime(true);
$source    = (php_sapi_name() === 'cli') ? 'cron' : 'http';

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
 // hash_equals instead of !== - constant-time, resistant to timing attacks.
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
//
// ADMIN_FORCE_TICK (admin/force_tick.php): reczne wymuszenie przez admina zawsze
// przechodzi, nawet gdy cron akurat trzyma blokade.
// ADMIN_FORCE_TICK (admin/force_tick.php): manual admin force always runs, even if the
// cron currently holds the lock.
if (!defined('ADMIN_FORCE_TICK')) {
    try {
        $gotLock = (int)$db->query("SELECT GET_LOCK('oilcorp_tick', 0)")->fetchColumn();
        if ($gotLock !== 1) {
            GameLog::warn('tick', 'tick juz trwa - pomijam ten przebieg / tick already running - skipping this run');
            echo "Tick skipped: another run in progress\n";
            exit(0);
        }
        register_shutdown_function(static function () use ($db) {
            try {
                $db->query("SELECT RELEASE_LOCK('oilcorp_tick')");
            } catch (Throwable $e) {
                // Polaczenie i tak zwolni lock przy zamknieciu / connection close frees it anyway
            }
        });
    } catch (Throwable $e) {
        // Brak wsparcia GET_LOCK nie moze zatrzymac gry — kontynuuj bez blokady.
        // Missing GET_LOCK support must not stall the game — continue without the lock.
        GameLog::error('tick', 'GET_LOCK FAILED - kontynuuje bez blokady / continuing without lock', $e);
    }
}

GameLog::info('tick', '== START ==', ['time' => $now->format('Y-m-d H:i:s'), 'source' => $source]);

// 1-2. RYNEK 

$market = new MarketSection();
$market->run();

$activeTrend = $market->activeTrend;
$isNewTrend  = $market->isNewTrend;
$newPrice    = $market->newPrice;

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
    $hasNonDefault = array_filter($gBalanceMults, fn($v) => abs($v - 1.0) > 0.001);
    if (!empty($hasNonDefault)) GameLog::info('tick', 'globalne mnozniki balansu aktywne', $gBalanceMults);
} catch (Throwable $e) {
    GameLog::error('tick', 'odczyt mnoznikow balansu FAILED - uzywam 1.0', $e);
}

$players = new PlayersSection($db, $now, $newPrice, $gBalanceMults);
$players->run();

// 6. CZARNY RYNEK 

$bmOffersGenerated = 0;
try {
    $bm = new BlackMarketService($db);

 // Expiracja przeterminowanych ofert
    $bm->expireOffers();

 // Decay black_market_score wszystkich graczy
    $bm->decayScores();

 // Generowanie ofert co N tickow
    $bmInterval = 3;
    try {
        $intStmt = $db->prepare("SELECT `value` FROM well_config WHERE `key` = 'bm_offer_interval_ticks' LIMIT 1");
        $intStmt->execute();
        $intVal = $intStmt->fetchColumn();
        if ($intVal !== false) $bmInterval = max(1, (int)$intVal);
    } catch (Throwable $e) {}

 // Pobierz licznik tickow (inkrementuj)
    $bmTickCount = 0;
    try {
        $db->prepare("
            INSERT INTO well_config (`key`, `value`, `label`, `category`)
            VALUES ('bm_tick_counter', '1', 'Czarny rynek - licznik tickow', 'black_market')
            ON DUPLICATE KEY UPDATE `value` = `value` + 1
        ")->execute();
        $cStmt = $db->prepare("SELECT `value` FROM well_config WHERE `key` = 'bm_tick_counter' LIMIT 1");
        $cStmt->execute();
        $bmTickCount = (int)$cStmt->fetchColumn();
    } catch (Throwable $e) {}

    if ($bmTickCount > 0 && $bmTickCount % $bmInterval === 0) {
 // Generuj oferty dla kazdego aktywnego gracza 
        $activePlayers = $db->query("
            SELECT id FROM players
            WHERE financial_state != 'crisis'
            AND id IN (SELECT DISTINCT player_id FROM wells WHERE status NOT IN ('seized','blowout','sold'))
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($activePlayers as $pid) {
            $bmOffersGenerated += $bm->generateOffers((int)$pid, $newPrice);
        }

        if ($bmOffersGenerated > 0) {
            GameLog::info('tick', "Czarny rynek: wygenerowano $bmOffersGenerated ofert dla " . count($activePlayers) . " graczy");
        }
    }
} catch (Throwable $e) {
    GameLog::error('tick', 'Black market section FAILED', $e);
}

// 7. WIARYGODNOSC FIRMY

$credibilityCleanBonuses = 0;
try {
    $credibility = new CredibilitySection($db, $now);
    $credibility->run();
    $credibilityCleanBonuses = $credibility->cleanBonuses;
    if ($credibilityCleanBonuses > 0) {
        GameLog::info('tick', "Wiarygodnosc firmy: przyznano {$credibilityCleanBonuses} bonusow za czysty okres");
    }
} catch (Throwable $e) {
    GameLog::error('tick', 'Credibility section FAILED', $e);
}

// 8. DZIAŁ PRAWNY — rozpatrywanie wniosków o zezwolenia

$legalDecided  = 0;
$legalNotified = 0;
try {
    $legal = new LegalSection($db, $now);
    $legal->run();
    $legalDecided  = $legal->decided;
    $legalNotified = $legal->notified;
    if ($legalDecided > 0) {
        GameLog::info('tick', "Dział prawny: rozpatrzono {$legalDecided} wniosków, powiadomień: {$legalNotified}");
    }
} catch (Throwable $e) {
    GameLog::error('tick', 'Legal section FAILED', $e);
}

// PODSUMOWANIE + ZAPIS STATYSTYK

$trendInfo = $activeTrend
    ? " | Trend: {$activeTrend['trend_name']}" . ($isNewTrend ? ' [NOWY]' : '')
    : '';

GameLog::info('tick', '== END ==', [
    'price'    => $newPrice,
    'trend'    => $activeTrend['trend_name'] ?? 'brak',
    'players'  => $players->playersProcessed,
    'bbl'      => round($players->totalBbl, 2),
    'revenue'  => round($players->totalRevenue, 2),
    'disasters'=> $players->disastersTriggered,
]);

// Zapis last_system_tick_at
try {
    $db->prepare("
        INSERT INTO well_config (`key`, `value`, `label`, `category`)
        VALUES ('last_system_tick_at', :ts, 'Ostatni tick systemu (timestamp)', 'system')
        ON DUPLICATE KEY UPDATE `value` = :ts2
    ")->execute([':ts' => $now->getTimestamp(), ':ts2' => $now->getTimestamp()]);
} catch (Throwable $e) {
    GameLog::error('tick', 'zapis last_system_tick_at FAILED', $e);
}

// Zapis statystyk ticka
try {
    $durationMs = (int)round((microtime(true) - $startTime) * 1000);
    (new TickStatsRepository())->save([
        'ran_at'                       => $now->format('Y-m-d H:i:s'),
        'source'                       => $source,
        'duration_ms'                  => $durationMs,
        'oil_price'                    => $newPrice,
        'trend_name'                   => $activeTrend['trend_name'] ?? null,
        'trend_new'                    => $isNewTrend,
        'bank_negotiations_resolved'   => $bank->negotiationsResolved,
        'bank_loan_decisions'          => $bank->loanDecisions,
        'hr_recruitments_processed'    => $bank->hrRecruitmentsProcessed,
        'bankruptcy_processed'         => $bank->bankruptcyProcessed,
        'bankruptcy_recovered'         => $bank->bankruptcyRecovered,
        'players_processed'            => $players->playersProcessed,
        'wells_active'                 => $players->wellsActive,
        'total_production_bbl'         => round($players->totalBbl, 4),
        'total_revenue_pln'            => round($players->totalRevenue, 2),
        'total_opex_pln'               => round($players->totalOpex, 2),
        'disasters_triggered'          => $players->disastersTriggered,
        'incidents_triggered'          => $players->incidentsTriggered,
    ]);
} catch (Throwable $e) {
    GameLog::error('tick', 'zapis tick_stats FAILED', $e);
}

// Cleanup starych statystyk (zachowaj 7 dni)
// Old tick stats cleanup (keep 7 days)
try {
    (new TickStatsRepository())->cleanup(7);
} catch (Throwable $e) {}

// Cleanup historii incydentow wg konfigurowalnej retencji.
// Incident history cleanup based on configurable retention setting.
$incRetention = 30;
try {
    $r = $db->query("SELECT `value` FROM well_config WHERE `key` = 'incident_retention_days' LIMIT 1")->fetchColumn();
    if ($r !== false) $incRetention = max(1, (int)$r);
    $stmt = $db->prepare("DELETE FROM well_incidents WHERE created_at < NOW() - INTERVAL ? DAY");
    $stmt->bindValue(1, $incRetention, PDO::PARAM_INT);
    $stmt->execute();
} catch (Throwable $e) {
    GameLog::error('tick', 'incident_retention_cleanup FAILED', $e);
}

// Cleanup przeczytanych powiadomien technicznych wg tej samej retencji.
// Technical notifications cleanup (read ones) using the same retention setting.
try {
    $stmt = $db->prepare("DELETE FROM technical_notifications WHERE is_read = 1 AND created_at < NOW() - INTERVAL ? DAY");
    $stmt->bindValue(1, $incRetention, PDO::PARAM_INT);
    $stmt->execute();
} catch (Throwable $e) {
    GameLog::error('tick', 'notif_retention_cleanup FAILED', $e);
}

// Nieprzeczytane notyfikacje starsze niz 2x retencja - tez usun (ochrona przed zaleglosciami) | Unread notifications older than 2x retention - also purge (prevents accumulation)
try {
    $oldUnread = $incRetention * 2;
    $stmt = $db->prepare("DELETE FROM technical_notifications WHERE is_read = 0 AND created_at < NOW() - INTERVAL ? DAY");
    $stmt->bindValue(1, $oldUnread, PDO::PARAM_INT);
    $stmt->execute();
} catch (Throwable $e) {
    GameLog::error('tick', 'notif_old_unread_cleanup FAILED', $e);
}

// Cleanup zbiorczych wpisow tickowych w historii bankowej (ta sama retencja co incydenty).
// Przelewy, kredyty i zakupy zostaja na zawsze - usuwane sa tylko typy tickowe.
// Aggregated tick entries cleanup in bank history (same retention as incidents).
// Transfers, loans and purchases are kept forever - only tick types are purged.
try {
    (new FinancialTransactionService($db))->purgeTickAudit($incRetention);
} catch (Throwable $e) {
    GameLog::error('tick', 'bank_tick_audit_cleanup FAILED', $e);
}

echo "Tick OK: " . $now->format('Y-m-d H:i:s') . " | Cena: {$newPrice}\${$trendInfo}"
    . " | Gracze: {$players->playersProcessed} | Bbl: " . round($players->totalBbl, 1) . "\n";
