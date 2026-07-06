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
require_once __DIR__ . '/../src/Tick/TickRegistry.php';

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

 // Systemowy deltaHours z ostatniego ticka (last_system_tick_at jest nadpisywany dopiero
 // na koncu tego przebiegu, wiec tu wciaz trzyma znacznik poprzedniego ticka). Sluzy do
 // skalowania plaskiego decay po przerwie crona (L4 / regula #13).
 // System-level deltaHours since the last tick (last_system_tick_at is overwritten only at
 // the end of this run, so it still holds the previous tick's timestamp here).
    $bmDeltaHours = 1.0 / 12.0; // domyslnie 5 min / default 5 min
    try {
        $lastSysTs = $db->query("SELECT `value` FROM well_config WHERE `key` = 'last_system_tick_at' LIMIT 1")->fetchColumn();
        if ($lastSysTs !== false && (int)$lastSysTs > 0) {
            $elapsed = $now->getTimestamp() - (int)$lastSysTs;
            if ($elapsed > 0) $bmDeltaHours = $elapsed / 3600.0;
        }
    } catch (Throwable $e) {}

 // Decay black_market_score wszystkich graczy (skalowany czasem ticka)
    $bm->decayScores($bmDeltaHours);

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

// Wspolny kontekst dla modulow tikowych (Credibility, Contracts, ...).
// Shared context for tick modules (Credibility, Contracts, ...).
$tickCtx = new TickContext($db, $now, $source, $startTime);
$tickCtx->setMarketState($newPrice, $activeTrend, $isNewTrend);
$tickCtx->balanceMults = $gBalanceMults;
$tickCtx->bankNegAvailable = $bankNegAvailable;
$tickCtx->bankruptcyAvailable = $bankruptcyAvailable;

// 7. WIARYGODNOSC FIRMY

$credibilityCleanBonuses = 0;
try {
    $credibilityModule = TickRegistry::find('credibility');
    if ($credibilityModule === null) {
        throw new RuntimeException('CredibilityModule not found');
    }

    $credibilityModule->run($tickCtx);
    $tickCtx->mergeStats($credibilityModule->key(), $credibilityModule->stats());

    $credibilityStats = $credibilityModule->stats();
    $credibilityCleanBonuses = (int)($credibilityStats['clean_bonuses'] ?? 0);
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

// 9. SZKOLENIA — egzaminy zakonczonych szkolen pracownikow

$trainingExamined = 0;
try {
    $training = new TrainingSection($db);
    $training->run();
    $trainingExamined = $training->examined;
    if ($trainingExamined > 0) {
        GameLog::info('tick', "Szkolenia: przeprowadzono {$trainingExamined} egzaminow");
    }
} catch (Throwable $e) {
    GameLog::error('tick', 'Training section FAILED', $e);
}

// 10. KONTRAKTY DŁUGOTERMINOWE — rozliczanie wymagalnych dostaw

$contractsProcessed  = 0;
$contractsRevenue    = 0.0;
$contractsPenalties  = 0.0;
try {
    $contractsModule = TickRegistry::find('contracts');
    if ($contractsModule !== null) {
        $contractsModule->run($tickCtx);
        $tickCtx->mergeStats($contractsModule->key(), $contractsModule->stats());
        $contractsStats     = $contractsModule->stats();
        $contractsProcessed = (int)($contractsStats['processed']  ?? 0);
        $contractsRevenue   = (float)($contractsStats['revenue']   ?? 0.0);
        $contractsPenalties = (float)($contractsStats['penalties'] ?? 0.0);
    }
} catch (Throwable $e) {
    GameLog::error('tick', 'Contracts section FAILED', $e);
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

// Zapis last_system_tick_at + last_tick_oil_price + czyszczenie flagi tick_in_progress (H1)
// Save last_system_tick_at + last_tick_oil_price + clear tick_in_progress flag (H1)
try {
    $db->prepare("
        INSERT INTO well_config (`key`, `value`, `label`, `category`)
        VALUES ('last_system_tick_at', :ts, 'Ostatni tick systemu (timestamp)', 'system')
        ON DUPLICATE KEY UPDATE `value` = :ts2
    ")->execute([':ts' => $now->getTimestamp(), ':ts2' => $now->getTimestamp()]);
} catch (Throwable $e) {
    GameLog::error('tick', 'zapis last_system_tick_at FAILED', $e);
}
try {
    $db->prepare(
        "INSERT INTO well_config (`key`, `value`, `label`, `category`)
         VALUES ('last_tick_oil_price', :p, 'Cena ropy z ostatniego ticka (fallback H7)', 'system')
         ON DUPLICATE KEY UPDATE `value` = :p2"
    )->execute([':p' => $newPrice, ':p2' => $newPrice]);
} catch (Throwable $e) {}
try {
    $db->prepare("UPDATE well_config SET `value` = '0' WHERE `key` = 'tick_in_progress'")->execute();
} catch (Throwable $e) {
    GameLog::error('tick', 'tick_in_progress flag clear FAILED', $e);
}

// Zapis statystyk ticka
try {
    $durationMs = (int)round((microtime(true) - $startTime) * 1000);

 // Slow tick warning — tick trwajacy >60s sugeruje problem wydajnosci lub kolizje / >60s tick suggests performance issue or collision
    if ($durationMs > 60_000) {
        GameLog::warn('tick', 'WOLNY TICK / SLOW TICK', ['duration_ms' => $durationMs, 'threshold_ms' => 60_000]);
    }

    (new TickStatsRepository())->save([
        'ran_at'                       => $now->format('Y-m-d H:i:s'),
        'source'                       => $source,
        'duration_ms'                  => $durationMs,
        'oil_price'                    => $newPrice,
        'trend_name'                   => $activeTrend['trend_name'] ?? null,
        'trend_new'                    => $isNewTrend,
 // M7: bank_interest i installments: BankSection nie liczy dokladnie tych wartosci,
 // zapisujemy 0 zamiast NULL zeby zaznaczyc ze funkcje uruchomily sie poprawnie.
 // M7: interest and installments not tracked per-tick in BankSection — record 0 (ran OK) not NULL.
        'bank_interest_processed'      => 0,
        'bank_installments_processed'  => 0,
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
